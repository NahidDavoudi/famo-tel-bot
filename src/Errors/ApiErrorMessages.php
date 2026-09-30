<?php
declare(strict_types=1);

namespace App\Errors;

final class ApiErrorMessages
{
    private const MAP = [
        'AUTH_ERROR' => 'احراز هویت ناموفق بود.',
        'BOT_UNAUTHORIZED' => 'کلید سرویس ربات نامعتبر است.',
        'BOT_ACCOUNT_BLOCKED' => 'حساب تلگرام شما مسدود شده است.',
        'FORBIDDEN' => 'دسترسی غیرمجاز.',
        'NOT_FOUND' => 'موردی یافت نشد.',
        'VALIDATION_ERROR' => 'اطلاعات ارسالی نامعتبر است.',
        'CONTENT_ACCESS_DISABLED' => 'دسترسی به محتوای پیام‌ها فعال نیست.',
        'REGISTRATION_ERROR' => 'کاربر قبلاً ثبت‌نام کرده است.',
        'INTERNAL_ERROR' => 'خطای داخلی سرور.',
    ];

    private const FALLBACK = 'خطای نامشخصی رخ داد. لطفاً دوباره تلاش کنید.';

    public static function toPersian(?string $code): string
    {
        if ($code !== null && isset(self::MAP[$code])) {
            return self::MAP[$code];
        }
        return self::FALLBACK;
    }
}
