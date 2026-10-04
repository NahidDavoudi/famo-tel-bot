<?php

declare(strict_types=1);

use App\Config;
use App\Famo\FamoApi;
use App\Handlers\AccountHandler;
use App\Handlers\BroadcastHandler;
use App\Handlers\LinkHandler;
use App\Handlers\StudentHandler;
use App\Handlers\SupporterHandler;
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

// ─── Fallback: هر چیزی که می‌تونه throw بشه، اینجا لاگ می‌شه ───
$fatalLog = static function (string $label, \Throwable $e) use ($logDir): void {
    $line = sprintf(
        "[%s] %s: %s in %s:%d\n%s\n",
        date('Y-m-d H:i:s'),
        $label,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    @file_put_contents($logDir . '/fatal.log', $line, FILE_APPEND | LOCK_EX);
};

// Fatal errors که catch نمی‌شن (parse error, out of memory, ...)
register_shutdown_function(static function () use ($fatalLog): void {
    $err = error_get_last();
    if ($err === null) {
        return;
    }
    if (!in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        return;
    }
    $fatalLog('SHUTDOWN_FATAL', new \ErrorException(
        $err['message'],
        0,
        $err['type'],
        $err['file'],
        $err['line']
    ));
});

// هر Throwable که از هر جایی رد بشه
set_exception_handler(static function (\Throwable $e) use ($fatalLog): void {
    $fatalLog('UNCAUGHT', $e);
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => true]);
});

// PHP warning/notice/deprecation → ErrorException (اختیاری)
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

// ─── Bootstrap ───
try {
    require_once $root . '/vendor/autoload.php';

    Dotenv\Dotenv::createImmutable($root)->safeLoad();

    Lang::boot();
    Logger::boot();
} catch (\Throwable $e) {
    $fatalLog('BOOTSTRAP', $e);
    if (!headers_sent()) {
        http_response_code(200);
    }
    exit;
}

// ─── Main ───
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
    $supporter = new SupporterHandler($famo, $telegram, $screens, $student);
    $broadcast = new BroadcastHandler($famo, $telegram, $screens, $student, $supporter);
    $link = new LinkHandler($famo, $telegram, $screens, $student, $supporter, $config);
    $account = new AccountHandler($famo, $telegram, $screens, $student, $link, $config);

    $router = new Router($state, $telegram, $link, $student, $account, $supporter, $broadcast);
    $router->route($telegram->update());
} catch (\Throwable $e) {
    // اول به فایل fallback (بدون وابستگی به Monolog)
    $fatalLog('HANDLED', $e);

    // بعد سعی کن به Monolog هم بفرستی، ولی اگه خودش ترکید، بی‌خیال شو
    try {
        Logger::error('webhook.error', [
            'type'    => get_class($e),
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'trace'   => $e->getTraceAsString(),
        ]);
    } catch (\Throwable $inner) {
        @error_log('Logger::error itself failed: ' . $inner->getMessage());
    }
}

http_response_code(200);
