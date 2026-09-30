<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Telegram\Bot\Api;
use Telegram\Bot\HttpClients\GuzzleHttpClient;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

function initBot(): Api
{
    $token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
    if (!$token) {
        throw new \Exception('TELEGRAM_BOT_TOKEN not set');
    }

    $httpClient = null;
    if (!empty($_ENV['TELEGRAM_PROXY'])) {
        $guzzle = new Client([RequestOptions::PROXY => $_ENV['TELEGRAM_PROXY']]);
        $httpClient = new GuzzleHttpClient($guzzle);
    }

    $baseBotUrl = null;
    if (!empty($_ENV['TELEGRAM_API_URL'])) {
        $baseBotUrl = rtrim($_ENV['TELEGRAM_API_URL'], '/') . '/bot';
    }

    return new Api($token, false, $httpClient, $baseBotUrl);
}

function sendTelegram(Api $bot, int $chatId, string $text)
{
    try {
        return $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ]);
    } catch (\Throwable $e) {
        error_log('sendTelegram error: ' . $e->getMessage());
        return null;
    }
}

function getBotInfo(Api $bot)
{
    try {
        return $bot->getMe();
    } catch (\Throwable $e) {
        error_log('getBotInfo error: ' . $e->getMessage());
        return null;
    }
}

function getWebhookInfo(Api $bot)
{
    try {
        return $bot->getWebhookInfo();
    } catch (\Throwable $e) {
        error_log('getWebhookInfo error: ' . $e->getMessage());
        return null;
    }
}

function getUpdatesCount(Api $bot)
{
    try {
        $info = $bot->getWebhookInfo();
        return $info['pending_update_count'] ?? 0;
    } catch (\Throwable $e) {
        return null;
    }
}

function setWebhook(Api $bot, string $url): bool
{
    try {
        $bot->setWebhook(['url' => $url]);
        return true;
    } catch (\Throwable $e) {
        error_log('setWebhook error: ' . $e->getMessage());
        return false;
    }
}

function deleteWebhook(Api $bot, bool $dropPending = false): bool
{
    try {
        $params = ['drop_pending_updates' => $dropPending];
        $bot->deleteWebhook($params);
        return true;
    } catch (\Throwable $e) {
        error_log('deleteWebhook error: ' . $e->getMessage());
        return false;
    }
}

function readJson(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }
    $data = file_get_contents($path);
    return json_decode($data, true) ?: [];
}

function writeJson(string $path, array $data): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function getDataDir(): string
{
    return __DIR__ . '/data';
}

function getBotStatus(): array
{
    return readJson(getDataDir() . '/bot-status.json');
}

function setBotStatus(bool $active): void
{
    writeJson(getDataDir() . '/bot-status.json', [
        'active' => $active,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
    addLog('bot', $active ? 'ربات فعال شد' : 'ربات غیرفعال شد');
}

function getLogs(int $limit = 50): array
{
    $logs = readJson(getDataDir() . '/logs.json');
    return array_slice($logs, -$limit);
}

function getRuntimeFile(): string
{
    $path = $_ENV['BOT_RUNTIME_FILE'] ?? getenv('BOT_RUNTIME_FILE');
    return ($path !== false && $path !== '' && $path !== null) ? (string) $path : getDataDir() . '/bot-runtime.json';
}

function getRuntimeSummary(): array
{
    $file = getRuntimeFile();
    if (!is_file($file)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function addLog(string $type, string $message, ?array $data = null): void
{
    $logs = readJson(getDataDir() . '/logs.json');
    $logs[] = [
        'time' => date('Y-m-d H:i:s'),
        'type' => $type,
        'message' => $message,
        'data' => $data,
    ];
    if (count($logs) > 500) {
        $logs = array_slice($logs, -500);
    }
    writeJson(getDataDir() . '/logs.json', $logs);
}

function generateCode(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn(): bool
{
    startSession();
    return !empty($_SESSION['admin_logged_in']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: /');
        exit;
    }
}