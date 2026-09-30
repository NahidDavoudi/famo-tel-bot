#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use BotHost\Bootstrap;

$app = Bootstrap::create();
$url = $app->config()->require('BOT_WEBHOOK_URL');
$secret = $app->config()->require('BOT_WEBHOOK_SECRET');

$app->telegram()->setWebhook($url, $secret, ['message', 'callback_query']);
echo "Webhook set to {$url}\n";
