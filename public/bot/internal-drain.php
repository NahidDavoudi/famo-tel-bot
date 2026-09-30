<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Bootstrap;

try {
    $app = Bootstrap::create();
    $expected = $app->config()->get('BOT_INTERNAL_SECRET');
    $provided = $_SERVER['HTTP_X_BOT_INTERNAL_SECRET'] ?? ($_GET['secret'] ?? null);

    if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
        http_response_code($expected !== null && is_string($provided) && hash_equals($expected, $provided) ? 204 : 403);
        exit;
    }

    if ($expected === null || !is_string($provided) || !hash_equals($expected, $provided)) {
        http_response_code(403);
        exit;
    }

    ignore_user_abort(true);
    set_time_limit(0);
    http_response_code(200);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
    }

    $app->drainer()->run($app->config()->int('BOT_DRAIN_BUDGET_SECONDS', 20));
} catch (\Throwable $e) {
    if (!headers_sent()) {
        http_response_code(($_SERVER['REQUEST_METHOD'] ?? '') === 'HEAD' ? 403 : 200);
    }
    error_log('bot internal drain fatal: ' . $e->getMessage());
}
