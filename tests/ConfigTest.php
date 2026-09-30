<?php
declare(strict_types=1);

require_once __DIR__ . '/harness.php';

use App\Config;

$c = Config::fromArray(['API_BASE_URL' => 'https://api.famoacademy.ir/', 'BOT_DRAIN_BUDGET_SECONDS' => '20']);
checkSame('x', $c->get('MISSING', 'x'), 'get returns default');
checkSame('https://api.famoacademy.ir', $c->apiBaseUrl(), 'apiBaseUrl trims trailing slash');
checkSame('https://api.telegram.org', $c->telegramBaseUrl(), 'telegram default base url');
checkSame(20, $c->int('BOT_DRAIN_BUDGET_SECONDS', 5), 'int parses numeric');
checkSame(5, $c->int('MISSING', 5), 'int uses default');

$threw = false;
try {
    $c->require('BOT_SERVICE_KEY');
} catch (\RuntimeException $e) {
    $threw = true;
}
check($threw, 'require throws on missing key');

echo "OK\n";
