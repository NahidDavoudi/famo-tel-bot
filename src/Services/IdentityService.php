<?php
declare(strict_types=1);

namespace App\Services;

use App\Api\FamoApiClient;
use App\Handlers\CallBackHandler;
use App\Helpers\PhoneNormalizer;

final class IdentityService
{
    private static ?FamoApiClient $api = null;
    protected CallBackHandler $callBackHandler;
    protected PhoneNormalizer $phoneNormalizer;

    public static function boot(FamoApiClient $api, CallBackHandler $callBackHandler , PhoneNormalizer $phoneNormalizer): void
    {
        self::$api = $api;
        self::$phoneNormalizer = $phoneNormalizer;
        self::$callBackHandler = $callBackHandler;
    }

    public static function isLinked(int $chatId): bool
    {
        $res = self::api()->request('GET', '/bot/identity/resolve', [], null, [
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

    public static function linkGuest($phone, $chatId)
    {
        $res = self::api()->request('POST', '');
    }
    private static function api(): FamoApiClient
    {
        if (self::$api === null) {
            throw new \RuntimeException('IdentityService is not booted.');
        }

        return self::$api;
    }
}
