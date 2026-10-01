<?php
declare(strict_types=1);

namespace App;

use App\Logging\Logger;

final class WebhookHandler
{
    public function __construct(private Config $config) {}

    /**
     * Validate an inbound webhook request and log it.
     *
     * @return int HTTP status to answer Telegram with.
     */
    public function handle(string $rawBody, ?string $secretHeader): int
    {
        try {
            $expected = $this->config->get('BOT_WEBHOOK_SECRET');
            if ($expected === null || $secretHeader === null || !hash_equals($expected, $secretHeader)) {
                Logger::warning('webhook.rejected', ['reason' => 'bad_secret']);

                return 403;
            }

            $update = json_decode($rawBody, true);
            if (!is_array($update) || !isset($update['update_id'])) {
                Logger::info('webhook.malformed', ['body' => $rawBody]);

                return 200;
            }

            Logger::info('webhook.accepted', ['update_id' => (int) $update['update_id']]);

            return 200;
        } catch (\Throwable $e) {
            Logger::error('webhook.handler_error', ['message' => $e->getMessage()]);

            return 200;
        }
    }
}
