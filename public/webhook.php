<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

Dotenv::createImmutable(dirname(__DIR__))->load();

require_once dirname(__DIR__) . '/src/Bot.php';

$bot = new Bot($_ENV['TELEGRAM_BOT_TOKEN']);

$bot->handle();

http_response_code(200);