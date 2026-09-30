<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Telegram\Bot\Api;
use Telegram\Bot\HttpClients\GuzzleHttpClient;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';

// === GET /?test=1 → Simple health check ===
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['test'])) {
    header('Content-Type: application/json');
    if (!$token) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'TELEGRAM_BOT_TOKEN not set']);
        exit;
    }

    try {
        $httpClient = null;
        if (!empty($_ENV['TELEGRAM_PROXY'])) {
            $guzzle = new Client([RequestOptions::PROXY => $_ENV['TELEGRAM_PROXY']]);
            $httpClient = new GuzzleHttpClient($guzzle);
        }
        $baseBotUrl = null;
        if (!empty($_ENV['TELEGRAM_API_URL'])) {
            $baseBotUrl = rtrim($_ENV['TELEGRAM_API_URL'], '/') . '/bot';
        }
        $bot = new Api($token, false, $httpClient, $baseBotUrl);
        $me = $bot->getMe();
        echo json_encode([
            'ok' => true,
            'bot' => [
                'id' => $me['id'] ?? null,
                'name' => $me['first_name'] ?? null,
                'username' => $me['username'] ?? null,
            ],
            'proxy' => !empty($_ENV['TELEGRAM_PROXY']),
        ]);
    } catch (\Throwable $e) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// === POST → Webhook handler ===
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update || !isset($update['message'])) {
    http_response_code(200);
    exit;
}

try {
    $httpClient = null;
    if (!empty($_ENV['TELEGRAM_PROXY'])) {
        $guzzle = new Client([RequestOptions::PROXY => $_ENV['TELEGRAM_PROXY']]);
        $httpClient = new GuzzleHttpClient($guzzle);
    }
    $baseBotUrl = null;
    if (!empty($_ENV['TELEGRAM_API_URL'])) {
        $baseBotUrl = rtrim($_ENV['TELEGRAM_API_URL'], '/') . '/bot';
    }

    $api = new Api($token, false, $httpClient, $baseBotUrl);

    require_once dirname(__DIR__) . '/src/Bot.php';

    $handler = new Bot($api);
    $handler->handle($update);
} catch (\Throwable $e) {
    error_log('Webhook error: ' . $e->getMessage());
}

http_response_code(200);