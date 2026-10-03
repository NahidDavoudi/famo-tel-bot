<?php
declare(strict_types=1);

namespace App\Famo;

use App\Lang;

final class ErrorMap
{
    private const CODE_KEYS = [
        'BOT_UNAUTHORIZED' => 'error.system_unavailable',
        'BOT_NOT_CONFIGURED' => 'error.system_unavailable',
        'BOT_IP_FORBIDDEN' => 'error.system_unavailable',
        'BOT_ACCOUNT_BLOCKED' => 'error.account_disabled',
        'FORBIDDEN' => 'error.forbidden',
        'NOT_FOUND' => 'error.generic',
        'VALIDATION_ERROR' => 'error.validation',
        'CHAT_ALREADY_LINKED' => 'error.chat_already_linked',
        'USER_ALREADY_LINKED' => 'error.user_already_linked',
        'PHONE_ALREADY_REGISTERED' => 'error.phone_already_registered',
        'CONTENT_ACCESS_DISABLED' => 'error.account_disabled',
        'REGISTRATION_ERROR' => 'error.generic',
        'INTERNAL_ERROR' => 'error.system_unavailable',
        'NO_SUPPORTER_ASSIGNED' => 'error.no_supporter',
    ];

    public static function toPersian(?string $code, ?int $status = null, ?string $transportError = null): string
    {
        if ($transportError !== null && $transportError !== '') {
            return Lang::t('error.system_unavailable');
        }

        if ($status === 429) {
            return Lang::t('error.daily_limit');
        }

        if ($status !== null && $status >= 500) {
            return Lang::t('error.system_unavailable');
        }

        if ($code !== null && isset(self::CODE_KEYS[$code])) {
            return Lang::t(self::CODE_KEYS[$code]);
        }

        return Lang::t('error.generic');
    }

    public static function isUnlinked(ApiResult $result): bool
    {
        return $result->errorCode === 'BOT_UNAUTHORIZED' || $result->status === 401;
    }

    public static function isDisabled(ApiResult $result): bool
    {
        return $result->errorCode === 'BOT_ACCOUNT_BLOCKED'
            || $result->errorCode === 'CONTENT_ACCESS_DISABLED';
    }

    public static function isTransport(ApiResult $result): bool
    {
        return $result->transportError !== null && $result->transportError !== '';
    }
}
