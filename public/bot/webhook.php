<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use BotHost\Bootstrap;

ignore_user_abort(true);
set_time_limit(60);

try {
    $app = Bootstrap::create();
    $handler = $app->webhookHandler();
    $secret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;
    $raw = (string) file_get_contents('php://input');

    $status = $handler->handle($raw, is_string($secret) ? $secret : null);
    http_response_code($status);

    if ($status === 200) {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }
        $handler->finish();
    }
} catch (\Throwable $e) {
    if (!headers_sent()) {
        http_response_code(200);
    }
    error_log('bot webhook fatal: ' . $e->getMessage());
}
