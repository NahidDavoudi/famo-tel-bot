<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Bootstrap;
use Telegram\Bot\Api;
use App\StartCommand;

try {
$app = Bootstrap::create();

$api = new Api($app->config()->require('TELEGRAM_BOT_TOKEN'));
$api->addCommand(StartCommand::class);

$api->commandsHandler(true);
} catch (\Throwable $e) {
    if (!headers_sent()) http_response_code(200);
    error_log('bot webhook fatal: ' . $e->getMessage());
}