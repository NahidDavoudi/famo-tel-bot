<?php
declare(strict_types=1);

namespace BotHost;

final class Config
{
    private const KEYS = [
        'TELEGRAM_BOT_TOKEN', 'TELEGRAM_API_URL', 'BOT_WEBHOOK_URL',
        'BOT_WEBHOOK_SECRET', 'BOT_ROLLBACK_WEBHOOK_URL',
        'API_BASE_URL', 'BOT_SERVICE_KEY', 'BOT_INTERNAL_SECRET',
        'BOT_STORAGE_DIR', 'BOT_RUNTIME_FILE', 'BOT_LOG_FILE',
        'BOT_DRAIN_BUDGET_SECONDS', 'BOT_DRAIN_BATCH_LIMIT', 'BOT_MAX_CHAIN',
    ];

    /** @param array<string,string> $values */
    public function __construct(private array $values) {}

    /** @param array<string,mixed> $values */
    public static function fromArray(array $values): self
    {
        return new self(array_filter(
            array_map(static fn ($v) => $v === null ? null : (string) $v, $values),
            static fn ($v) => $v !== null && $v !== ''
        ));
    }

    public static function fromEnv(): self
    {
        $values = [];
        foreach (self::KEYS as $key) {
            $v = $_ENV[$key] ?? getenv($key);
            if ($v !== false && $v !== null && $v !== '') {
                $values[$key] = (string) $v;
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
        $v = $this->values[$key] ?? null;
        if ($v === null || $v === '') {
            throw new \RuntimeException("Missing required config: {$key}");
        }
        return $v;
    }

    public function int(string $key, int $default): int
    {
        $v = $this->values[$key] ?? null;
        return ($v !== null && ctype_digit($v)) ? (int) $v : $default;
    }

    public function apiBaseUrl(): string
    {
        return rtrim($this->get('API_BASE_URL', 'https://api.famoacademy.ir') ?? '', '/');
    }

    public function telegramBaseUrl(): string
    {
        $url = $this->get('TELEGRAM_API_URL');
        return $url ? rtrim($url, '/') : 'https://api.telegram.org';
    }
}
