#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Bootstrap;

$app = Bootstrap::create();
$previous = $app->config()->get('BOT_ROLLBACK_WEBHOOK_URL');

if ($previous !== null && $previous !== '') {
    $app->telegram()->setWebhook($previous, '', ['message', 'callback_query']);
    echo "Rolled back webhook to {$previous}\n";
} else {
    $app->telegram()->deleteWebhook(false);
    echo "No rollback URL configured; webhook deleted (pending updates kept)\n";
}
