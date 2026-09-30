<?php
declare(strict_types=1);

require_once __DIR__ . '/harness.php';

use Errors\ApiErrorMessages;

checkSame('کلید سرویس ربات نامعتبر است.', ApiErrorMessages::toPersian('BOT_UNAUTHORIZED'), 'maps documented code');
checkSame('خطای نامشخصی رخ داد. لطفاً دوباره تلاش کنید.', ApiErrorMessages::toPersian('NO_SUPPORTER_ASSIGNED'), 'unknown code falls back, not invented');
checkSame('خطای نامشخصی رخ داد. لطفاً دوباره تلاش کنید.', ApiErrorMessages::toPersian(null), 'null falls back');

echo "OK\n";
