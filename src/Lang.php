<?php
declare(strict_types=1);

namespace App;

final class Lang
{
    /** @var array<string,string> */
    private static array $strings = [];

    private static string $path = '';

    public static function boot(?string $path = null): void
    {
        self::$path = $path ?? dirname(__DIR__) . '/lang/fa.json';
        self::$strings = [];

        if (!is_file(self::$path)) {
            return;
        }

        $decoded = json_decode((string) file_get_contents(self::$path), true);
        if (!is_array($decoded)) {
            return;
        }

        foreach ($decoded as $key => $value) {
            self::$strings[(string) $key] = (string) $value;
        }
    }

    /** @param array<string,mixed> $params */
    public static function t(string $key, array $params = []): string
    {
        if (self::$path === '') {
            self::boot();
        }

        $text = self::$strings[$key] ?? $key;
        if ($params === []) {
            return $text;
        }

        $replace = [];
        foreach ($params as $name => $value) {
            if ($value instanceof RawHtml) {
                $replace['{' . $name . '}'] = $value->html;
            } else {
                $replace['{' . $name . '}'] = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            }
        }

        return strtr($text, $replace);
    }
}
