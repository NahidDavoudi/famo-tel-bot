<?php
declare(strict_types=1);

namespace App\Telegram;

final class KeyboardKit
{
    public const CB_NOP = 'nop';
    public const CB_HOME = 'h';
    public const CB_CHECK = 'ln:check';
    public const CB_ACCOUNT = 'ac';
    public const CB_ROLE_STUDENT = 'ac:role:student';
    public const CB_ROLE_SUPPORTER = 'ac:role:supporter';
    public const CB_SWITCH = 'ac:switch';
    public const CB_UNLINK = 'ac:unlink';
    public const CB_UNLINK_OK = 'ac:unlink:ok';
    public const CB_AC_HOME = 'ac:home';
    public const CB_TODAY = 'st:t';
    public const CB_WEEK = 'st:w';
    public const CB_WEEK_AT = 'st:w:';
    public const CB_DAY = 'st:d:';
    public const CB_FILES = 'st:f:';
    public const CB_NEW = 'st:n';

    public const LBL_TODAY = 'گفتگوی امروز';
    public const LBL_WEEK = 'وضعیت هفته';
    public const LBL_HOME = 'منوی اصلی';

    public const ROUTE_TODAY = 'today';
    public const ROUTE_WEEK = 'week';
    public const ROUTE_HOME = 'home';

    /**  list<list<array<string,string>>> ReplyKeyboardMarkup only (text buttons, no callback_data) */
    public static function replyKeyboardStudentMenu(): array
    {
        return [
            [self::key(self::LBL_TODAY), self::key(self::LBL_WEEK)],
            [self::key(self::LBL_HOME)],
        ];
    }

    public static function labelRoute(string $label): ?string
    {
        return match (trim($label)) {
            self::LBL_TODAY => self::ROUTE_TODAY,
            self::LBL_WEEK => self::ROUTE_WEEK,
            self::LBL_HOME => self::ROUTE_HOME,
            default => null,
        };
    }

    /**  array{text:string,callback_data:string} */
    public static function btn(string $text, string $callback): array
    {
        return ['text' => $text, 'callback_data' => $callback];
    }

    /**  array{text:string,url:string} */
    public static function urlBtn(string $text, string $url): array
    {
        return ['text' => $text, 'url' => $url];
    }

    /**  array{text:string} */
    private static function key(string $label): array
    {
        return ['text' => $label];
    }
}
