<?php
declare(strict_types=1);

namespace Storage;

final class RuntimeStats
{
    public function __construct(private string $file) {}

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        if (!is_file($this->file)) {
            return $this->empty();
        }
        $decoded = json_decode((string) file_get_contents($this->file), true);
        return is_array($decoded) ? array_replace($this->empty(), $decoded) : $this->empty();
    }

    public function recordWebhook(): void
    {
        $this->mutate(static function (array &$s): void { $s['last_webhook_at'] = date('c'); });
    }

    public function recordApi(bool $ok): void
    {
        $this->mutate(static function (array &$s) use ($ok): void {
            $s[$ok ? 'last_api_ok_at' : 'last_api_error_at'] = date('c');
        });
    }

    public function recordDrain(): void
    {
        $this->mutate(static function (array &$s): void { $s['last_drain_at'] = date('c'); });
    }

    public function increment(string $counter): void
    {
        $this->mutate(static function (array &$s) use ($counter): void {
            $s['counters'][$counter] = (int) ($s['counters'][$counter] ?? 0) + 1;
        });
    }

    public function addError(string $message): void
    {
        $this->mutate(static function (array &$s) use ($message): void {
            $s['counters']['errors'] = (int) ($s['counters']['errors'] ?? 0) + 1;
            $s['recent_errors'][] = ['time' => date('c'), 'message' => mb_substr($message, 0, 200)];
            $s['recent_errors'] = array_slice($s['recent_errors'], -20);
        });
    }

    /** @return array<string,mixed> */
    private function empty(): array
    {
        return [
            'last_webhook_at' => null,
            'last_api_ok_at' => null,
            'last_api_error_at' => null,
            'last_drain_at' => null,
            'counters' => ['outbox_sent' => 0, 'outbox_failed' => 0, 'outbox_blocked' => 0, 'errors' => 0],
            'recent_errors' => [],
        ];
    }

    private function mutate(callable $fn): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create runtime dir: {$dir}");
        }
        $lock = fopen($this->file . '.lock', 'c');
        if ($lock !== false) {
            flock($lock, LOCK_EX);
        }
        $snapshot = $this->snapshot();
        $fn($snapshot);
        $tmp = $this->file . '.tmp.' . getmypid();
        file_put_contents($tmp, json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        rename($tmp, $this->file);
        if ($lock !== false) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
