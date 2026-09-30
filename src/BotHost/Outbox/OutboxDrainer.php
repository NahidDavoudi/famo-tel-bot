<?php
declare(strict_types=1);

namespace BotHost\Outbox;

use BotHost\Storage\RuntimeStats;

final class OutboxDrainer
{
    public function __construct(
        private string $lockFile,
        private RuntimeStats $stats,
    ) {}

    /**
     * Module 1: acquire the single shared lock and record the drain time.
     * The claim/send/report loop is added in the Outbox module.
     *
     * @return array{ran:bool, reason?:string, elapsed?:float}
     */
    public function run(int $budgetSeconds): array
    {
        $dir = dirname($this->lockFile);
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            return ['ran' => false, 'reason' => 'lock_dir_failed'];
        }
        $lock = fopen($this->lockFile, 'c');
        if ($lock === false) {
            return ['ran' => false, 'reason' => 'lock_open_failed'];
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return ['ran' => false, 'reason' => 'locked'];
        }

        try {
            $start = microtime(true);
            $this->stats->recordDrain();
            return ['ran' => true, 'elapsed' => microtime(true) - $start];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
