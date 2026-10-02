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

    public static function boot(FamoApiClient $api): void
    {
        self::$api = $api;
    }

    public static function resolve(int $chatId): array
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
        $result = $res->data() ?? [];
        return $result;
    }

    public static function isLinked(int $chatId)
    {
    }

    public static function linkGuest($phone, $chatId)
    {
        $res = self::api()->request('POST', '');
    }
    public static function getRole(int $chatId): string
    {
        if(self::resolve($chatId)){
            $role = self::resolve($chatId)['role'];
        } else {
            return 'geust';
        }
        return $role;
    }
    private static function api(): FamoApiClient
    {
        if (self::$api === null) {
            throw new \RuntimeException('IdentityService is not booted.');
        }

        return self::$api;
    }
    
}
