<?php
declare(strict_types=1);

namespace App;

final class Config
{
    private const KEYS = [
        'TELEGRAM_BOT_TOKEN',
        'FAMO_API_URL',
        'BOT_SERVICE_KEY',
        'BOT_LOGIN_URL',
        'BOT_WEBHOOK_SECRET',
        'BOT_STORAGE_DIR',
        'BOT_LOG_FILE',
    ];

    /** @param array<string,string> $values */
    private function __construct(private array $values) {}

    public static function fromEnv(): self
    {
        $values = [];
        foreach (self::KEYS as $key) {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
            if ($value !== false && $value !== null && $value !== '') {
                $values[$key] = (string) $value;
            }
        }

        return new self($values);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->values[$key] ?? $default;
    }

    public function require(string $key): string
    {
        $value = $this->values[$key] ?? null;
        if ($value === null || $value === '') {
            throw new \RuntimeException("Missing required config: {$key}");
        }

        return $value;
    }

    public function int(string $key, int $default): int
    {
        $value = $this->values[$key] ?? null;

        return ($value !== null && ctype_digit($value)) ? (int) $value : $default;
    }

    public function apiBaseUrl(): string
    {
        return rtrim($this->get('FAMO_API_URL', '') ?? '', '/');
    }

    public function storageDir(): string
    {
        $dir = $this->get('BOT_STORAGE_DIR');
        if ($dir !== null && $dir !== '') {
            return rtrim($dir, "/\\");
        }

        return dirname(__DIR__) . '/storage';
    }
}
