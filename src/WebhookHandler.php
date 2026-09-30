<?php
declare(strict_types=1);

namespace BotHost;

use Logging\RuntimeLogger;
use Outbox\OutboxDrainer;
use Storage\LocalStore;
use Storage\RuntimeStats;

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
        try {
            $expected = $this->config->get('BOT_WEBHOOK_SECRET');
            if ($expected === null || $secretHeader === null || !hash_equals($expected, $secretHeader)) {
                $this->safeLog('error', 'webhook rejected: bad secret');
                return 403;
            }

            $update = json_decode($rawBody, true);
            if (!is_array($update) || !isset($update['update_id'])) {
                $this->safeLog('webhook', 'ignored malformed update');
                return 200;
            }

            $updateId = (int) $update['update_id'];

            if (!$this->store->acceptUpdate($updateId, $update)) {
                return 200;
            }

            $this->stats->recordWebhook();
            return 200;
        } catch (\Throwable $e) {
            $this->recordInternalError($e);
            return 200;
        }
    }

    public function finish(): void
    {
        try {
            $this->drainer->run(
                $this->config->int('BOT_DRAIN_BUDGET_SECONDS', 20),
                function (): void {
                    foreach ($this->store->dequeueUpdates($this->config->int('BOT_DRAIN_BATCH_LIMIT', 10)) as $item) {
                        // Dispatch to feature handlers is added in later modules.
                    }
                }
            );
        } catch (\Throwable $e) {
            $this->recordInternalError($e);
        }
    }

    private function safeLog(string $type, string $message): void
    {
        try {
            $this->logger->log($type, $message);
        } catch (\Throwable) {
        }
    }

    private function recordInternalError(\Throwable $e): void
    {
        $message = $e->getMessage();
        $this->safeLog('error', 'webhook handler error: ' . $message);
        try {
            $this->stats->addError($message);
        } catch (\Throwable) {
        }
    }
}
