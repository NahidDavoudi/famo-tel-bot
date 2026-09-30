<?php
declare(strict_types=1);

namespace BotHost;

use BotHost\Logging\RuntimeLogger;
use BotHost\Outbox\OutboxDrainer;
use BotHost\Storage\LocalStore;
use BotHost\Storage\RuntimeStats;

final class WebhookHandler
{
    public function __construct(
        private Config $config,
        private LocalStore $store,
        private RuntimeStats $stats,
        private RuntimeLogger $logger,
        private OutboxDrainer $drainer,
    ) {}

    public function handle(string $rawBody, ?string $secretHeader): int
    {
        $expected = $this->config->get('BOT_WEBHOOK_SECRET');
        if ($expected === null || $secretHeader === null || !hash_equals($expected, $secretHeader)) {
            $this->logger->log('error', 'webhook rejected: bad secret');
            return 403;
        }

        $update = json_decode($rawBody, true);
        if (!is_array($update) || !isset($update['update_id'])) {
            $this->logger->log('webhook', 'ignored malformed update');
            return 200;
        }

        $updateId = (int) $update['update_id'];
        if (!$this->store->markProcessed($updateId)) {
            return 200;
        }

        $this->store->enqueueUpdate($updateId, $update);
        $this->stats->recordWebhook();
        return 200;
    }

    public function finish(): void
    {
        try {
            foreach ($this->store->dequeueUpdates($this->config->int('BOT_DRAIN_BATCH_LIMIT', 10)) as $item) {
                // Dispatch to feature handlers is added in later modules.
            }
            $this->drainer->run($this->config->int('BOT_DRAIN_BUDGET_SECONDS', 20));
        } catch (\Throwable $e) {
            $this->logger->log('error', 'finish failed: ' . $e->getMessage());
            $this->stats->addError($e->getMessage());
        }
    }
}
