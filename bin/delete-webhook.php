#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Bootstrap;

$app = Bootstrap::create();
$app->telegram()->deleteWebhook(false);
echo "Webhook deleted (pending updates kept)\n";
