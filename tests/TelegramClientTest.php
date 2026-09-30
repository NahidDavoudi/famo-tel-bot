<?php
declare(strict_types=1);

require_once __DIR__ . '/harness.php';

use BotHost\Telegram\TelegramClient;

final class FakeTelegram extends TelegramClient
{
    public array $calls = [];
    public function __construct(private array $responses) { parent::__construct('TEST:TOKEN'); }

    protected function transport(string $url, array $params): array
    {
        $this->calls[] = ['url' => $url, 'params' => $params];
        return array_shift($this->responses) ?? ['ok' => false, 'description' => 'no response'];
    }
}

$t = new FakeTelegram([
    ['ok' => true, 'result' => ['id' => 1, 'first_name' => 'Test']],
    ['ok' => false, 'error_code' => 400, 'description' => 'Bad Request'],
]);

checkSame(1, $t->getMe()['result']['id'], 'getMe returns result');
checkSame('https://api.telegram.org/botTEST:TOKEN/getMe', $t->calls[0]['url'], 'url is built from token and method');

$threw = false;
try {
    $t->getMe();
} catch (\RuntimeException $e) {
    $threw = true;
}
check($threw, 'API error throws');

echo "OK\n";
