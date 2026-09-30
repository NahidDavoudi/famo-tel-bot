<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    http_response_code(400);
    exit;
}

require_once dirname(__DIR__) . '/src/Bot.php';

$bot = new Bot($_ENV['TELEGRAM_BOT_TOKEN']);
$bot->addCommand(StartCommand::class);
$bot->handle($update);
$bot->commandsHandler(true);
http_response_code(200);
