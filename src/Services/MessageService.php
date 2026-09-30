<?php
declare(strict_types=1);

namespace App\Services;

final class MessageService
{
    /** @var array<string,string>|null */
    private static ?array $messages = null;

    private static string $path = '';

    public static function boot(?string $path = null): void
    {
        self::$path = $path ?? self::defaultPath();
        self::$messages = null;
    }

    /**
     * Resolve a message by key and replace {placeholders}.
     *
     * A placeholder is filled from $replace first, otherwise from another
     * message key of the same name (e.g. {common.header}).
     *
     * @param array<string,scalar> $replace
     */
    public static function get(string $key, array $replace = []): string
    {
        $messages = self::load();
        $text = (string) ($messages[$key] ?? $key);

        if (preg_match_all('/\{([a-zA-Z0-9_.]+)\}/', $text, $matches)) {
            foreach (array_unique($matches[1]) as $token) {
                $value = $replace[$token] ?? $messages[$token] ?? null;
                if ($value !== null) {
                    $text = str_replace('{' . $token . '}', (string) $value, $text);
                }
            }
        }

        return $text;
    }

    /** @return array<string,string> */
    private static function load(): array
    {
        if (self::$messages !== null) {
            return self::$messages;
        }

        $path = self::$path !== '' ? self::$path : self::defaultPath();
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return self::$messages = is_array($data) ? array_filter($data, 'is_string') : [];
    }

    private static function defaultPath(): string
    {
        return dirname(__DIR__, 2) . '/lang/fa.json';
    }
}
