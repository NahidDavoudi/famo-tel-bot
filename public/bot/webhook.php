<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Bootstrap;
use Telegram\Bot\Api;
use App\StartCommand;

try {
$app = Bootstrap::create();

$api = new Api($app->config()->require('TELEGRAM_BOT_TOKEN'));
$api->addCommand(StartCommand::class);

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

$api->commandsHandler(true);
} catch (\Throwable $e) {
    if (!headers_sent()) http_response_code(200);
    error_log('bot webhook fatal: ' . $e->getMessage());
}