<?php

ini_set('log_errors', 'On');
ini_set('error_log', __DIR__ . '/error_log');

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Api\FamoApiClient;
use App\Services\IdentityService;
use App\Services\JwtService;
use App\Logging\Logger;

Dotenv::createImmutable(dirname(__DIR__))->load();

Logger::boot();

require_once dirname(__DIR__) . '/src/Bot.php';

$rawBody = (string) file_get_contents('php://input');
Logger::info('webhook.request', [
    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    'method' => $_SERVER['REQUEST_METHOD'] ?? null,
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
    'body' => $rawBody,
]);

try {

    JwtService::boot($_ENV('JWT_SECRET'));

    IdentityService::boot(new FamoApiClient(
        $_ENV('BOT_SERVICE_KEY'),
        $_ENV('FAMO_API_URL')
    ));

    $bot = new Bot($_ENV('TELEGRAM_BOT_TOKEN'));
    $bot->handle();
} catch (\Throwable $e) {
    Logger::error('webhook.error', [
        'exception' => get_class($e),
        'message' => $e->getMessage(),
    ]);
    error_log('bot webhook error: ' . $e->getMessage());
}

http_response_code(200);
