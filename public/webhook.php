<?php

ini_set('log_errors', 'On');
ini_set('error_log', __DIR__ . '/error_log');

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Config;
use App\Api\FamoApiClient;
use App\Services\IdentityService;

Dotenv::createImmutable(dirname(__DIR__))->load();

require_once dirname(__DIR__) . '/src/Bot.php';

try {
    $config = Config::fromEnv();

    IdentityService::boot(new FamoApiClient(
        $config->require('BOT_SERVICE_KEY'),
        $config->apiBaseUrl()
    ));

    $bot = new Bot($config->require('TELEGRAM_BOT_TOKEN'));
    $bot->handle();
} catch (\Throwable $e) {
    error_log('bot webhook error: ' . $e->getMessage() . ' ' . $e->getCode() ?? '');
}

http_response_code(200);
