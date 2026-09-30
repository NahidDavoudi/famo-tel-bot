<?php
declare(strict_types=1);

namespace BotHost\Logging;

final class RuntimeLogger
{
    public function __construct(private string $logFile, private int $maxEntries = 500) {}

    /** @param array<string,mixed> $data */
    public function log(string $type, string $message, array $data = []): void
    {
        $entries = [];
        if (is_file($this->logFile)) {
            $decoded = json_decode((string) file_get_contents($this->logFile), true);
            if (is_array($decoded)) {
                $entries = $decoded;
            }
        }
        $entries[] = [
            'time' => date('Y-m-d H:i:s'),
            'type' => $type,
            'message' => mb_substr($message, 0, 500),
            'data' => $data === [] ? null : $data,
        ];
        if (count($entries) > $this->maxEntries) {
            $entries = array_slice($entries, -$this->maxEntries);
        }
        $dir = dirname($this->logFile);
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create log dir: {$dir}");
        }
        file_put_contents($this->logFile, json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }
}
