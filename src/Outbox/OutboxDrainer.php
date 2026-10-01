<?php
declare(strict_types=1);

namespace App\Outbox;

use App\Logging\Logger;

final class OutboxDrainer
{
    public function __construct(private string $lockFile) {}

    /**
     * Acquire the single shared drain lock and run the work while it is held.
     *
     * @param callable():void|null $work optional work executed while the drain lock is held
     * @return array{ran:bool, reason?:string, elapsed?:float}
     */
    public function run(int $budgetSeconds, ?callable $work = null): array
    {
        $dir = dirname($this->lockFile);
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            Logger::error('outbox.lock_dir_failed', ['dir' => $dir]);

            return ['ran' => false, 'reason' => 'lock_dir_failed'];
        }

        $lock = fopen($this->lockFile, 'c');
        if ($lock === false) {
            Logger::error('outbox.lock_open_failed', ['lock' => $this->lockFile]);

            return ['ran' => false, 'reason' => 'lock_open_failed'];
        }

        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            Logger::info('outbox.locked', ['lock' => $this->lockFile]);

            return ['ran' => false, 'reason' => 'locked'];
        }

        try {
            $start = microtime(true);
            Logger::info('outbox.drain_start', ['budget' => $budgetSeconds]);

            if ($work !== null) {
                $work();
            }

            $elapsed = microtime(true) - $start;
            Logger::info('outbox.drain_done', ['elapsed' => $elapsed]);

            return ['ran' => true, 'elapsed' => $elapsed];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
