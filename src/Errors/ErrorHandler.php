<?php

namespace App\Errors;

use App\Services\MessageService;

final class ErrorHandler
{
    public static function message(\Throwable $e): string
    {
        $message = $e->getMessage();

        return match (true) {
            $e->getCode() === 404 => MessageService::get('error.not_found'),
            $e->getCode() === 403 => MessageService::get('error.forbidden'),
            $e->getCode() === 401 => MessageService::get('error.unauthorized'),
            $e->getCode() === 422 => MessageService::get('error.invalid'),
            $e->getCode() === 429 => MessageService::get('error.rate_limit'),
            $e->getCode() === 500 => MessageService::get('error.server'),
            $e->getCode() === 502 => MessageService::get('error.bad_gateway'),
            $e->getCode() === 503 => MessageService::get('error.service_unavailable'),
            $e->getCode() === 504 => MessageService::get('error.gateway_timeout'),
            default => MessageService::get('error.unknown'),
        };
    }
}
