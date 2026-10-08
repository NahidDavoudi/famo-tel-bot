<?php
declare(strict_types=1);

namespace App;

final class Html
{
    public const SEP = '. . . . . . . . .';

    /** Wrap a value in <code> using English digits (Persian/Arabic → ASCII). */
    public static function num(int|string $value): string
    {
        return '<code>' . self::escape(Num::en((string) $value)) . '</code>';
    }

    /** Wrap a value in <code> as-is (no digit conversion). */
    public static function code(string $value): string
    {
        return '<code>' . self::escape($value) . '</code>';
    }

    public static function bold(string $value): string
    {
        return '<b>' . self::escape($value) . '</b>';
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /** Quoted message body (expandable if long). */
    public static function quote(string $body): string
    {
        $safe = self::escape($body);
        $tag = mb_strlen($body) > 250 ? '<blockquote expandable>' : '<blockquote>';

        return $tag . $safe . '</blockquote>';
    }
}