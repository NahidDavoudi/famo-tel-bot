#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use BotHost\Bootstrap;

function line(string $label, string $value): void
{
    echo str_pad($label, 34) . ': ' . $value . "\n";
}

echo "== Famo bot host environment check ==\n";
line('PHP version', PHP_VERSION);
line('Minimum required', '8.2');
line('SAPI', PHP_SAPI);
line('fastcgi_finish_request', function_exists('fastcgi_finish_request') ? 'yes' : 'NO (will flush + ignore_user_abort)');
line('max_execution_time', (string) ini_get('max_execution_time'));
line('ignore_user_abort', (string) ini_get('ignore_user_abort'));
foreach (['curl', 'json', 'mbstring', 'pdo_sqlite', 'openssl'] as $ext) {
    line('ext ' . $ext, extension_loaded($ext) ? 'yes' : 'NO');
}

$app = null;
$config = null;
$configError = null;
try {
    $app = Bootstrap::create();
    $config = $app->config();
} catch (\Throwable $e) {
    $configError = $e;
}

$root = dirname(__DIR__);
$storageDir = $config?->get('BOT_STORAGE_DIR') ?? $root . '/storage';
$runtimeFile = $config?->get('BOT_RUNTIME_FILE') ?? $root . '/admin/data/bot-runtime.json';
$logFile = $config?->get('BOT_LOG_FILE') ?? $root . '/admin/data/logs.json';
$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
$webRoot = rtrim((string) (($documentRoot !== '' ? realpath($documentRoot) : false) ?: $root), "/\\") . DIRECTORY_SEPARATOR;
foreach (['storage' => $storageDir, 'runtime' => dirname($runtimeFile), 'log' => dirname($logFile)] as $label => $path) {
    $exists = is_dir($path);
    line("{$label} dir", $path . ' exists=' . ($exists ? 'yes' : 'no') . ' writable=' . (is_writable($path) ? 'yes' : 'NO'));
    if (str_starts_with((string) realpath($path), $webRoot)) {
        line("  warning", "{$label} path is inside the web root; set it outside for production");
    }
}

$missingKey = null;
if ($configError !== null) {
    if (preg_match('/^Missing required config: ([A-Z0-9_]+)$/', $configError->getMessage(), $m) === 1) {
        $missingKey = $m[1];
    }
} elseif ($config !== null) {
    foreach (['TELEGRAM_BOT_TOKEN', 'BOT_SERVICE_KEY'] as $required) {
        if ($config->get($required) === null) {
            $missingKey = $required;
            break;
        }
    }
}

if ($configError !== null || $missingKey !== null) {
    echo "\n-- Config --\n";
    line('config', 'INCOMPLETE' . ($missingKey !== null ? " (missing {$missingKey})" : '') . '; check .env and set required variables');
    echo "\nNo secrets were printed.\n";
    exit(0);
}

echo "\n-- Telegram --\n";
try {
    $me = $app->telegram()->getMe();
    line('getMe', 'OK id=' . ($me['result']['id'] ?? '?') . ' username=@' . ($me['result']['username'] ?? '?'));
} catch (\Throwable $e) {
    line('getMe', 'FAILED: ' . $e->getMessage());
}

echo "\n-- Famo API (GET /bot/ping) --\n";
$result = $app->api()->ping();
if ($result->transportError !== null) {
    line('ping', 'TRANSPORT/TIMEOUT: ' . $result->transportError);
} elseif ($result->ok()) {
    line('ping', 'OK (200, key accepted)');
} else {
    line('ping', 'HTTP ' . $result->status . ($result->errorCode ? " code={$result->errorCode}" : ''));
    line('  hint', '401/wrong-key => key problem; 403 => possible IP/WAF block; 0 => network/firewall');
}

echo "\n-- Self-request to internal drain --\n";
$internalUrl = $config->get('BOT_WEBHOOK_URL');
if ($internalUrl !== null && $internalUrl !== '' && $config->get('BOT_INTERNAL_SECRET') !== null) {
    $drainUrl = preg_replace('#/webhook\.php$#', '/internal-drain.php', $internalUrl);
    if ($drainUrl === null || $drainUrl === $internalUrl) {
        line('HEAD internal-drain', 'skipped (BOT_WEBHOOK_URL does not end in /webhook.php)');
    } else {
        $ch = curl_init($drainUrl);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['X-Bot-Internal-Secret: ' . $config->get('BOT_INTERNAL_SECRET')],
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        line('HEAD internal-drain', $err !== '' ? 'ERROR: ' . $err : 'HTTP ' . $code . ($code === 204 ? ' (reachable)' : ''));
    }
} else {
    line('HEAD internal-drain', 'skipped (BOT_WEBHOOK_URL/BOT_INTERNAL_SECRET not set)');
}

echo "\nNo secrets were printed.\n";
