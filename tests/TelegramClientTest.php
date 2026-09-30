<?php
declare(strict_types=1);

require_once __DIR__ . '/harness.php';

use App\Telegram\TelegramClient;

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
    ['ok' => true, 'result' => true],
    ['ok' => true, 'result' => true],
    ['ok' => true, 'result' => true],
    ['ok' => true, 'result' => true],
    ['ok' => true, 'result' => true],
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

$t->setWebhook('https://example.test/hook', '');
$emptySecret = $t->calls[2]['params'];
check(!array_key_exists('secret_token', $emptySecret), 'setWebhook omits secret_token when empty');
checkSame(false, $emptySecret['drop_pending_updates'], 'setWebhook sends drop_pending_updates=false');
check(!array_key_exists('allowed_updates', $emptySecret), 'setWebhook omits allowed_updates when none given');

$t->setWebhook('https://example.test/hook', 'abc');
$withSecret = $t->calls[3]['params'];
checkSame('abc', $withSecret['secret_token'], 'setWebhook includes secret_token when set');
checkSame(false, $withSecret['drop_pending_updates'], 'setWebhook keeps drop_pending_updates=false with secret');
check(!array_key_exists('allowed_updates', $withSecret), 'setWebhook omits allowed_updates when none given');

$t->setWebhook('https://example.test/hook', 'abc', ['message', 'callback_query']);
$withUpdates = $t->calls[4]['params'];
checkSame(json_encode(['message', 'callback_query']), $withUpdates['allowed_updates'], 'setWebhook encodes allowed_updates as JSON');
checkSame('abc', $withUpdates['secret_token'], 'setWebhook keeps secret_token with allowed_updates');
checkSame(false, $withUpdates['drop_pending_updates'], 'setWebhook keeps drop_pending_updates=false with allowed_updates');

$t->deleteWebhook(false);
checkSame(false, $t->calls[5]['params']['drop_pending_updates'], 'deleteWebhook(false) sends drop_pending_updates=false');

$t->deleteWebhook(true);
checkSame(true, $t->calls[6]['params']['drop_pending_updates'], 'deleteWebhook(true) sends drop_pending_updates=true');

echo "OK\n";
