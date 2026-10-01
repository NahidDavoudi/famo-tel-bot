<?php
declare(strict_types=1);

namespace App\Services;

use App\Api\FamoApiClient;

final class IdentityService
{
    private static ?FamoApiClient $api = null;

    public static function boot(FamoApiClient $api): void
    {
        self::$api = $api;
    }

    public static function isLinked(int $chatId): bool
    {
        $res = self::api()->request('GET', '/api/v1/bot/identity/resolve', [], null, [
            'chat_id' => (string) $chatId,
        ]);

        if (!$res->ok()) {
            throw new \RuntimeException(
                'identity resolve failed: ' . $res->status
                . ' ' . ($res->errorCode ?? $res->transportError ?? 'unknown')
            );
        }

        $links = $res->data()['links'] ?? [];

        return is_array($links) && $links !== [];
    }

    private static function api(): FamoApiClient
    {
        if (self::$api === null) {
            throw new \RuntimeException('IdentityService is not booted.');
        }

        return self::$api;
    }
}
