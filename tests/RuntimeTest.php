<?php
declare(strict_types=1);

require_once __DIR__ . '/harness.php';

use BotHost\Storage\RuntimeStats;
use BotHost\Logging\RuntimeLogger;

$dir = sys_get_temp_dir() . '/bothost_' . bin2hex(random_bytes(4));
mkdir($dir, 0770, true);

$stats = new RuntimeStats($dir . '/runtime.json');
checkSame(null, $stats->snapshot()['last_webhook_at'], 'empty snapshot has nulls');

$stats->recordWebhook();
$stats->recordApi(true);
$stats->recordApi(false);
$stats->recordDrain();
$stats->increment('outbox_sent');
$stats->increment('outbox_sent');
$stats->addError('boom');

$snap = $stats->snapshot();
check($snap['last_webhook_at'] !== null, 'webhook time recorded');
check($snap['last_api_ok_at'] !== null, 'api ok recorded');
check($snap['last_api_error_at'] !== null, 'api error recorded');
check($snap['last_drain_at'] !== null, 'drain recorded');
checkSame(2, $snap['counters']['outbox_sent'], 'counter increments');
checkSame('boom', $snap['recent_errors'][0]['message'], 'recent error stored');

$logger = new RuntimeLogger($dir . '/logs.json');
$logger->log('webhook', 'received update');
$logger->log('error', 'failed api call', ['code' => 'BOT_UNAUTHORIZED']);
$logs = json_decode((string) file_get_contents($dir . '/logs.json'), true);
checkSame(2, count($logs), 'two log entries');
checkSame('webhook', $logs[0]['type'], 'panel-compatible type');
check(array_key_exists('data', $logs[0]), 'panel-compatible data key');

echo "OK\n";
