<?php
declare(strict_types=1);

use App\Config;
use App\Famo\FamoApi;
use App\Handlers\AccountHandler;
use App\Handlers\LinkHandler;
use App\Handlers\StudentHandler;
use App\Lang;
use App\Logger;
use App\Router;
use App\State\StateStore;
use App\Telegram\ScreenManager;
use App\Telegram\TelegramApi;

$root = dirname(__DIR__);
$logDir = $root . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0770, true);
}

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $logDir . '/php-error.log');

require_once $root . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable($root)->safeLoad();
Lang::boot();
Logger::boot();

try {
    $config = Config::fromEnv();

    $logFile = $config->get('BOT_LOG_FILE');
    if ($logFile !== null && $logFile !== '') {
        Logger::boot($logFile);
    }

    $secret = $config->get('BOT_WEBHOOK_SECRET');
    if ($secret !== null && $secret !== '') {
        $provided = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
        if (!is_string($provided) || !hash_equals($secret, $provided)) {
            http_response_code(403);
            exit;
        }
    }

    $state = new StateStore($config->storageDir() . '/bot.sqlite');
    $telegram = TelegramApi::fromToken($config->require('TELEGRAM_BOT_TOKEN'));
    $famo = new FamoApi($config->require('BOT_SERVICE_KEY'), $config->apiBaseUrl());
    $screens = new ScreenManager($telegram, $state);

    $student = new StudentHandler($famo, $telegram, $screens);
    $link = new LinkHandler($famo, $telegram, $screens, $student, $config);
    $account = new AccountHandler($famo, $telegram, $screens, $student, $link, $config);

    $router = new Router($state, $telegram, $link, $student, $account);
    $router->route($telegram->update());
} catch (Throwable $e) {
    Logger::error('webhook.error', [
        'exception' => get_class($e),
        'message' => $e->getMessage(),
    ]);
    error_log('famo bot webhook error: ' . $e->getMessage());
}

http_response_code(200);
