<?php

namespace App\Errors;
use App\Services\MesssageService;
final class ErrorHandler
{
    public static function message(\Throwable $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'timed out')) {
            return MessageService::get('error.timeout');
        }

        if (str_contains($message, '401')) {
            return MessageService::get('error.unauthorized');
        }

        if (str_contains($message, '404')) {
            return MessageService::get('error.not_found');
        }

        if (str_contains($message, '500')) {
            return MessageService::get('error.server');
        }

        return MessageService::get('error.unknown');
    }
}