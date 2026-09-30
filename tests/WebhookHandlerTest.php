<?php
declare(strict_types=1);

require_once __DIR__ . '/harness.php';

use BotHost\Config;
use BotHost\Storage\LocalStore;
use BotHost\Storage\RuntimeStats;
use BotHost\Logging\RuntimeLogger;
use BotHost\Outbox\OutboxDrainer;
use BotHost\WebhookHandler;

$dir = sys_get_temp_dir() . '/bothost_' . bin2hex(random_bytes(4));
mkdir($dir, 0770, true);

$config = Config::fromArray(['BOT_WEBHOOK_SECRET' => 'SECRET123', 'BOT_DRAIN_BATCH_LIMIT' => '5']);
$store = new LocalStore($dir . '/s');
$stats = new RuntimeStats($dir . '/runtime.json');
$logger = new RuntimeLogger($dir . '/logs.json');
$drainer = new OutboxDrainer($dir . '/drain.lock', $stats);
$handler = new WebhookHandler($config, $store, $stats, $logger, $drainer);

$update = json_encode(['update_id' => 500, 'message' => ['text' => 'hi']]);

checkSame(403, $handler->handle($update, 'WRONG'), 'bad secret rejected');
checkSame(403, $handler->handle($update, null), 'missing secret rejected');

checkSame(200, $handler->handle($update, 'SECRET123'), 'valid secret accepted');
checkSame(200, $handler->handle($update, 'SECRET123'), 'duplicate update still 200');
checkSame(1, count($store->dequeueUpdates(10)), 'duplicate not enqueued twice');

checkSame(200, $handler->handle('not json', 'SECRET123'), 'malformed body tolerated with 200');

$handler->finish();
check($stats->snapshot()['last_drain_at'] !== null, 'finish drains');

echo "OK\n";
