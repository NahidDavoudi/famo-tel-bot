<?php
declare(strict_types=1);

require_once __DIR__ . '/harness.php';

use BotHost\Bootstrap;
use BotHost\Config;
use BotHost\Api\FamoApiClient;
use BotHost\Logging\RuntimeLogger;
use BotHost\Outbox\OutboxDrainer;
use BotHost\Storage\LocalStore;
use BotHost\Storage\RuntimeStats;
use BotHost\Telegram\TelegramClient;
use BotHost\WebhookHandler;

$root = sys_get_temp_dir() . '/bothost_' . bin2hex(random_bytes(4));
mkdir($root, 0770, true);

// Webhook intake must work without the token/key secrets present.
$config = Config::fromArray(['BOT_STORAGE_DIR' => $root . '/storage']);
$app = Bootstrap::create($root, $config);

check($app->config() === $config, 'injected config is preserved');
check($app->store() instanceof LocalStore, 'store available without secrets');
check($app->stats() instanceof RuntimeStats, 'stats available without secrets');
check($app->logger() instanceof RuntimeLogger, 'logger available without secrets');
check($app->drainer() instanceof OutboxDrainer, 'drainer available without secrets');
check($app->webhookHandler() instanceof WebhookHandler, 'webhook handler available without secrets');

$threw = false;
try {
    $app->telegram();
} catch (\RuntimeException $e) {
    $threw = true;
}
check($threw, 'telegram() requires TELEGRAM_BOT_TOKEN lazily');

$threw = false;
try {
    $app->api();
} catch (\RuntimeException $e) {
    $threw = true;
}
check($threw, 'api() requires BOT_SERVICE_KEY lazily');

// With secrets present the lazy clients are constructed on first access.
$config2 = Config::fromArray([
    'BOT_STORAGE_DIR' => $root . '/storage2',
    'TELEGRAM_BOT_TOKEN' => '123:ABC',
    'BOT_SERVICE_KEY' => 'KEY',
]);
$app2 = Bootstrap::create($root, $config2);
check($app2->telegram() instanceof TelegramClient, 'telegram() builds lazily when token present');
check($app2->api() instanceof FamoApiClient, 'api() builds lazily when key present');

echo "OK\n";
