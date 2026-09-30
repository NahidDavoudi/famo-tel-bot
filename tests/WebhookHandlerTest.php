<?php
declare(strict_types=1);

require_once __DIR__ . '/harness.php';

use BotHost\Config;
use Storage\LocalStore;
use Storage\RuntimeStats;
use Logging\RuntimeLogger;
use Outbox\OutboxDrainer;
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
check($stats->snapshot()['last_webhook_at'] !== null, 'valid update recorded in stats');

@unlink($dir . '/runtime.json');
@unlink($dir . '/runtime.json.lock');

checkSame(200, $handler->handle($update, 'SECRET123'), 'duplicate update still 200');
check($stats->snapshot()['last_webhook_at'] === null, 'duplicate short-circuits before recording stats');
checkSame(1, count($store->dequeueUpdates(10)), 'duplicate not enqueued twice');

checkSame(200, $handler->handle('not json', 'SECRET123'), 'malformed body tolerated with 200');

$handler->finish();
check($stats->snapshot()['last_drain_at'] !== null, 'finish drains');

// A replayed update_id after finish() (which drains the queue) must not be re-enqueued.
$rootDir = $dir . '/replay';
$store2 = new LocalStore($rootDir . '/s');
$stats2 = new RuntimeStats($rootDir . '/runtime.json');
$logger2 = new RuntimeLogger($rootDir . '/logs.json');
$drainer2 = new OutboxDrainer($rootDir . '/drain.lock', $stats2);
$handler2 = new WebhookHandler($config, $store2, $stats2, $logger2, $drainer2);
$replay = json_encode(['update_id' => 777, 'message' => ['text' => 'hi']]);

checkSame(200, $handler2->handle($replay, 'SECRET123'), 'first delivery accepted');
checkSame(200, $handler2->handle($replay, 'SECRET123'), 'immediate duplicate still 200');
checkSame(1, count($store2->dequeueUpdates(10)), 'delivery enqueued exactly once');

checkSame(200, $handler2->handle(json_encode(['update_id' => 778, 'message' => ['text' => 'x']]), 'SECRET123'), 'second update accepted');
$handler2->finish();
checkSame([], $store2->dequeueUpdates(10), 'finish drains the queue under the drain lock');

checkSame(200, $handler2->handle($replay, 'SECRET123'), 'replay after finish still 200');
checkSame([], $store2->dequeueUpdates(10), 'replay after finish creates no second queue entry');

echo "OK\n";
