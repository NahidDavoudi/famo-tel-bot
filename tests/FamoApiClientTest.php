<?php
declare(strict_types=1);

require_once __DIR__ . '/harness.php';

use Api\FamoApiClient;
use Api\ApiResult;

final class FakeApi extends FamoApiClient
{
    public array $seen = [];
    public function __construct(private array $responses) { parent::__construct('KEY', 'https://api.famoacademy.ir'); }

    protected function http(string $method, string $url, array $headers, ?string $body): array
    {
        $this->seen[] = compact('method', 'url', 'headers', 'body');
        return array_shift($this->responses) ?? ['status' => 0, 'body' => null, 'transport' => 'no response'];
    }
}

$api = new FakeApi([
    ['status' => 200, 'body' => ['success' => true, 'data' => ['status' => 'healthy'], 'pagination' => null, 'error' => null], 'transport' => null],
    ['status' => 401, 'body' => ['success' => false, 'data' => null, 'pagination' => null, 'error' => ['code' => 'BOT_UNAUTHORIZED', 'message' => 'x']], 'transport' => null],
    ['status' => 0, 'body' => null, 'transport' => 'timeout'],
]);

$r = $api->ping();
check($r->ok(), 'ok on success envelope');
checkSame('https://api.famoacademy.ir/api/v1/bot/ping', $api->seen[0]['url'], 'ping url');
check(in_array('X-Bot-Key: KEY', $api->seen[0]['headers'], true), 'sends X-Bot-Key');

$e = $api->ping();
check(!$e->ok(), 'not ok on 401');
checkSame('BOT_UNAUTHORIZED', $e->errorCode, 'extracts error code');

$t = $api->ping();
check(!$t->ok(), 'not ok on transport error');
checkSame('timeout', $t->transportError, 'exposes transport error');

echo "OK\n";
