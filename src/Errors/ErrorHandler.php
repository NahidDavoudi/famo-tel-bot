<?php

namespace App\Errors;

use App\Services\MessageService;

final class ErrorHandler
{
    public static function message(\Throwable $e): string
    {
        $message = $e->getMessage();

        return match (true) {
            str_contains($message, 'timed out'), str_contains($message, 'timeout') => MessageService::get('error.timeout'),
            str_contains($message, '401') => MessageService::get('error.unauthorized'),
            str_contains($message, '403') => MessageService::get('error.forbidden'),
            str_contains($message, '404') => MessageService::get('error.not_found'),
            str_contains($message, '422') => MessageService::get('error.invalid'),
            str_contains($message, '429') => MessageService::get('error.rate_limit'),
            str_contains($message, '500') => MessageService::get('error.server'),
            str_contains($message, 'transport') => MessageService::get('error.network'),
            default => MessageService::get('error.unknown'),
        };
    }
}
