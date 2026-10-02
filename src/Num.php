<?php
declare(strict_types=1);

namespace App;

final class Num
{
    private const DIGITS = [
        '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
        '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
    ];

    public static function fa(mixed $value): string
    {
        return strtr((string) $value, self::DIGITS);
    }
}
