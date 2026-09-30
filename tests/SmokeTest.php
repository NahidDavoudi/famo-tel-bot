<?php
declare(strict_types=1);

require_once __DIR__ . '/harness.php';

check(class_exists(App\Config::class), 'App\\Config autoloads');

echo "OK\n";
