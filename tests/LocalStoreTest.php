<?php
declare(strict_types=1);

require_once __DIR__ . '/harness.php';

use BotHost\Storage\LocalStore;

$dir = sys_get_temp_dir() . '/bothost_' . bin2hex(random_bytes(4));
$store = new LocalStore($dir);

check($store->markProcessed(101), 'first update_id is new');
check(!$store->markProcessed(101), 'duplicate update_id rejected');

$store->enqueueUpdate(101, ['update_id' => 101, 'message' => ['text' => 'hi']]);
$store->enqueueUpdate(102, ['update_id' => 102]);
$batch = $store->dequeueUpdates(10);
checkSame(2, count($batch), 'dequeues both');
checkSame(101, $batch[0]['update_id'], 'ordered by update_id');
checkSame([], $store->dequeueUpdates(10), 'queue is emptied');

$store->setState('55', ['step' => 'reply'], 60);
checkSame('reply', $store->getState('55')['step'], 'state round-trips');
$store->setState('56', ['step' => 'x'], -1);
checkSame(null, $store->getState('56'), 'expired state returns null');
$store->clearState('55');
checkSame(null, $store->getState('55'), 'clearState removes');

$store->setMeta('last_drain', '2026-09-30T00:00:00Z');
checkSame('2026-09-30T00:00:00Z', $store->getMeta('last_drain'), 'meta round-trips');

check($store->acceptUpdate(201, ['update_id' => 201, 'message' => ['text' => 'a']]), 'acceptUpdate accepts a new id');
check(!$store->acceptUpdate(201, ['update_id' => 201]), 'acceptUpdate rejects a repeated id');
checkSame(1, count($store->dequeueUpdates(10)), 'accepted update is enqueued exactly once');
checkSame([], $store->dequeueUpdates(10), 'queue is empty after drain');
check(!$store->acceptUpdate(201, ['update_id' => 201]), 'processed id stays rejected after dequeue');
checkSame([], $store->dequeueUpdates(10), 'drained/processed id is never re-enqueued');

echo "OK\n";
