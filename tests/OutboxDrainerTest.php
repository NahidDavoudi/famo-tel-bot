<?php
declare(strict_types=1);

require_once __DIR__ . '/harness.php';

use App\Outbox\OutboxDrainer;
use App\Storage\RuntimeStats;

$dir = sys_get_temp_dir() . '/bothost_' . bin2hex(random_bytes(4));
mkdir($dir, 0770, true);
$stats = new RuntimeStats($dir . '/runtime.json');

$drainer = new OutboxDrainer($dir . '/drain.lock', $stats);
$first = $drainer->run(1);
check($first['ran'], 'first run acquires lock');
check($stats->snapshot()['last_drain_at'] !== null, 'drain time recorded');

// Second drainer holds the lock while the first is still inside is simulated
// by holding the lock file open in this process.
$handle = fopen($dir . '/drain.lock', 'c');
flock($handle, LOCK_EX | LOCK_NB);
$blocked = $drainer->run(1);
flock($handle, LOCK_UN);
fclose($handle);
check(!$blocked['ran'], 'second run is blocked by held lock');
checkSame('locked', $blocked['reason'], 'reports locked reason');

echo "OK\n";
