<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use BotHost\Bootstrap;
use Telegram\Bot\Api;
use BotHost\StartCommand;

$app = Bootstrap::create();

$api = new Api($app->config()->require('TELEGRAM_BOT_TOKEN'));
$api->addCommand(StartCommand::class);
// بعداً بقیه کامندها رو همینجا اضافه کن

$secret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;
$expected = $app->config()->get('BOT_WEBHOOK_SECRET');
if ($expected === null || !is_string($secret) || !hash_equals($expected, $secret)) {
    http_response_code(403);
    exit;
}

http_response_code(200);
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    if (ob_get_level() > 0) ob_end_flush();
    flush();
}

// commandsHandler خودش php://input رو میخونه و به کامند مناسب می‌ده
$api->commandsHandler(true);