<?php

namespace App\Errors;

final class ErrorHandler
{
    public static function message(\Throwable $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'timed out')) {
            return '⚠️ در حال حاضر ارتباط با سرور برقرار نشد. لطفاً کمی بعد دوباره تلاش کنید.';
        }

        if (str_contains($message, '401')) {
            return '🔐 احراز هویت سرویس با مشکل مواجه شده است.';
        }

        if (str_contains($message, '404')) {
            return '🔎 سرویس موردنظر پیدا نشد.';
        }

        if (str_contains($message, '500')) {
            return '⚠️ سرور فامو با مشکل مواجه شده است. لطفاً کمی بعد دوباره تلاش کنید.';
        }

        return '⚠️ خطای غیرمنتظره‌ای رخ داد. لطفاً دوباره تلاش کنید.';
    }
}