<?php

namespace App\Helpers;

use InvalidArgumentException;

final class PhoneNumberService
{
    public static function normalizeIranianMobile(string $input): string
    {
        $phone = trim($input);

        // Remove common separators users may type.
        $phone = str_replace([' ', '-', '(', ')'], '', $phone);

        // Must contain digits only after normalization.
        if (!preg_match('/^\+?\d+$/', $phone)) {
            throw new InvalidArgumentException(
                'Please enter a valid Iranian mobile number.'
            );
        }

        // +98xxxxxxxxxx → 09xxxxxxxxxx
        if (str_starts_with($phone, '+98')) {
            $phone = '0' . substr($phone, 3);
        }

        // 0098xxxxxxxxxx → 09xxxxxxxxxx
        elseif (str_starts_with($phone, '0098')) {
            $phone = '0' . substr($phone, 4);
        }

        // 98xxxxxxxxxx → 09xxxxxxxxxx
        elseif (str_starts_with($phone, '98')) {
            $phone = '0' . substr($phone, 2);
        }

        // 9xxxxxxxxx → 09xxxxxxxxx
        elseif (str_starts_with($phone, '9')) {
            $phone = '0' . $phone;
        }

        // Everything else should already be 09xxxxxxxxx
        elseif (!str_starts_with($phone, '09')) {
            throw new InvalidArgumentException(
                'Please enter a valid Iranian mobile number.'
            );
        }

        // Iranian mobile numbers are exactly 11 digits.
        if (strlen($phone) !== 11) {
            throw new InvalidArgumentException(
                'Please enter a valid Iranian mobile number.'
            );
        }

        // Iranian mobile prefix: 09xx
        if (!preg_match('/^09\d{9}$/', $phone)) {
            throw new InvalidArgumentException(
                'Please enter a valid Iranian mobile number.'
            );
        }

        return $phone;
    }
}