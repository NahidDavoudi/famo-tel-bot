# Module 1 — Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the foundation of the Famo Telegram bot host — config, secure webhook entry with dedupe, thin cURL clients, central error map, local SQLite state, runtime logging/stats, an outbox-drain lock skeleton, webhook scripts, and an environment-check — without breaking the existing admin panel or live webhook.

**Architecture:** New, isolated PHP 8.2 code under `src/BotHost/` (namespace `BotHost\`) with two new public endpoints (`public/bot/webhook.php`, `public/bot/internal-drain.php`). A thin `Bootstrap` wires config, storage, stats, logger, clients, and the drainer. The legacy `public/webhook.php`, `src/Bot.php`, `src/FamoApi.php`, and the admin panel keep working unchanged except for the small, clearly separated panel additions in Task 13.

**Tech Stack:** PHP 8.2+, plain cURL, `vlucas/phpdotenv` (already installed), SQLite via `pdo_sqlite`, zero-dependency test scripts.

**Spec:** `docs/superpowers/specs/2026-09-30-bot-host-integration-design.md`

## Global Constraints

- Target PHP **8.2+**; minimum actually required is **8.2**.
- New code uses plain cURL for Telegram and the API. **Do not use `irazasyed/telegram-bot-sdk` in new code.** Keep existing Composer dependencies so the panel keeps working.
- **No cron** and no external scheduler dependency.
- Secrets live only in env (outside the web root) and are **never** logged or printed.
- Use a **TEST bot token** only; never the production bot.
- Persian UI; Jalali dates from API strings; split outgoing Telegram text longer than 4096 chars; escape user text or send plain text.
- `callback_data` is opaque, ≤64 bytes, never trusted; the API re-authorizes every action.
- Never guess endpoints, fields, or error codes. Record gaps as "API requests" in `docs/superpowers/specs/2026-09-30-bot-host-integration-design.md`.
- API base defaults to `https://api.famoacademy.ir`; bot endpoints use header `X-Bot-Key`.
- Commit after each task (repo commits are lowercase, e.g. `add ...`).

---

## File Structure

- `composer.json` — add PSR-4 autoload `BotHost\` → `src/BotHost/`.
- `.env.example` — documented variable names, no secrets.
- `src/BotHost/Config.php` — env-driven configuration.
- `src/BotHost/Bootstrap.php` — dependency wiring / factory.
- `src/BotHost/Errors/ApiErrorMessages.php` — central code→Persian map.
- `src/BotHost/Telegram/TelegramClient.php` — Telegram Bot API via cURL.
- `src/BotHost/Api/ApiResult.php` — API response value object.
- `src/BotHost/Api/FamoApiClient.php` — Famo API via cURL.
- `src/BotHost/Storage/LocalStore.php` — SQLite dedupe, queue, state, meta.
- `src/BotHost/Storage/RuntimeStats.php` — atomic runtime summary for the panel.
- `src/BotHost/Logging/RuntimeLogger.php` — panel-compatible, secret-safe log.
- `src/BotHost/Outbox/OutboxDrainer.php` — single shared lock + run budget.
- `src/BotHost/WebhookHandler.php` — secret check, dedupe, enqueue, finish.
- `public/bot/webhook.php` — new webhook entry.
- `public/bot/internal-drain.php` — secret-protected internal drain entry.
- `bin/set-webhook.php`, `bin/delete-webhook.php`, `bin/rollback-webhook.php`.
- `bin/environment-check.php` — host capability report.
- `tests/harness.php` + `tests/*Test.php` — zero-dependency tests.
- `admin/functions.php`, `index.php` — minimal, separated panel additions (Task 13).
- `docs/README-bot-host.md` — Module 1 setup/test notes.

---

### Task 1: Bootstrap the new code (autoload, env example, test harness)

**Files:**
- Modify: `composer.json`
- Create: `.env.example`
- Create: `tests/harness.php`
- Test: `tests/SmokeTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: autoloaded namespace `BotHost\`; `tests/harness.php` helpers `check(bool $cond, string $msg)` and `checkSame($expected, $actual, string $msg)`.

- [ ] **Step 1: Write the failing smoke test**

Create `tests/harness.php`:

```php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

function check(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

function checkSame($expected, $actual, string $msg): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$msg} (expected " . var_export($expected, true)
            . ", got " . var_export($actual, true) . ")\n");
        exit(1);
    }
}
```

Create `tests/SmokeTest.php`:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/harness.php';

check(class_exists(BotHost\Config::class), 'BotHost\\Config autoloads');

echo "OK\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/SmokeTest.php`
Expected: FAIL — `Class "BotHost\Config" not found` (autoload not configured yet).

- [ ] **Step 3: Add PSR-4 autoload and env example**

Modify `composer.json`:

```json
{
    "require": {
        "irazasyed/telegram-bot-sdk": "^3.16",
        "vlucas/phpdotenv": "^5.7"
    },
    "autoload": {
        "psr-4": {
            "BotHost\\": "src/BotHost/"
        }
    }
}
```

Create `.env.example` (placeholders only — real `.env` is gitignored and lives outside version control):

```dotenv
# Telegram
TELEGRAM_BOT_TOKEN=
TELEGRAM_API_URL=
BOT_WEBHOOK_URL=
BOT_WEBHOOK_SECRET=
BOT_ROLLBACK_WEBHOOK_URL=

# Famo API
API_BASE_URL=https://api.famoacademy.ir
BOT_SERVICE_KEY=
BOT_INTERNAL_SECRET=

# Local storage (prefer a path OUTSIDE the web root in production)
BOT_STORAGE_DIR=
BOT_RUNTIME_FILE=
BOT_LOG_FILE=

# Drain budget
BOT_DRAIN_BUDGET_SECONDS=20
BOT_DRAIN_BATCH_LIMIT=10
BOT_MAX_CHAIN=5
```

Create a minimal `src/BotHost/Config.php` placeholder so the smoke test can autoload (full body in Task 2):

```php
<?php
declare(strict_types=1);

namespace BotHost;

final class Config
{
}
```

- [ ] **Step 4: Regenerate autoload and run the test**

Run: `composer dump-autoload; php tests/SmokeTest.php`
Expected: `OK`.

- [ ] **Step 5: Commit**

```bash
git add composer.json .env.example src/BotHost/Config.php tests/harness.php tests/SmokeTest.php
git commit -m "add bot host namespace autoload, env example and test harness"
```

---

### Task 2: Config

**Files:**
- Modify: `src/BotHost/Config.php`
- Test: `tests/ConfigTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `Config::fromArray(array<string,string> $values): self`
  - `Config::fromEnv(): self`
  - `get(string $key, ?string $default = null): ?string`
  - `require(string $key): string` (throws `RuntimeException`)
  - `int(string $key, int $default): int`
  - `apiBaseUrl(): string`, `telegramBaseUrl(): string`

- [ ] **Step 1: Write the failing test**

Create `tests/ConfigTest.php`:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/harness.php';

use BotHost\Config;

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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/ConfigTest.php`
Expected: FAIL — methods do not exist.

- [ ] **Step 3: Implement Config**

Replace `src/BotHost/Config.php`:

```php
<?php
declare(strict_types=1);

namespace BotHost;

final class Config
{
    private const KEYS = [
        'TELEGRAM_BOT_TOKEN', 'TELEGRAM_API_URL', 'BOT_WEBHOOK_URL',
        'BOT_WEBHOOK_SECRET', 'BOT_ROLLBACK_WEBHOOK_URL',
        'API_BASE_URL', 'BOT_SERVICE_KEY', 'BOT_INTERNAL_SECRET',
        'BOT_STORAGE_DIR', 'BOT_RUNTIME_FILE', 'BOT_LOG_FILE',
        'BOT_DRAIN_BUDGET_SECONDS', 'BOT_DRAIN_BATCH_LIMIT', 'BOT_MAX_CHAIN',
    ];

    /** @param array<string,string> $values */
    public function __construct(private array $values) {}

    /** @param array<string,mixed> $values */
    public static function fromArray(array $values): self
    {
        return new self(array_filter(
            array_map(static fn ($v) => $v === null ? null : (string) $v, $values),
            static fn ($v) => $v !== null && $v !== ''
        ));
    }

    public static function fromEnv(): self
    {
        $values = [];
        foreach (self::KEYS as $key) {
            $v = $_ENV[$key] ?? getenv($key);
            if ($v !== false && $v !== null && $v !== '') {
                $values[$key] = (string) $v;
            }
        }
        return new self($values);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->values[$key] ?? $default;
    }

    public function require(string $key): string
    {
        $v = $this->values[$key] ?? null;
        if ($v === null || $v === '') {
            throw new \RuntimeException("Missing required config: {$key}");
        }
        return $v;
    }

    public function int(string $key, int $default): int
    {
        $v = $this->values[$key] ?? null;
        return ($v !== null && ctype_digit($v)) ? (int) $v : $default;
    }

    public function apiBaseUrl(): string
    {
        return rtrim($this->get('API_BASE_URL', 'https://api.famoacademy.ir') ?? '', '/');
    }

    public function telegramBaseUrl(): string
    {
        $url = $this->get('TELEGRAM_API_URL');
        return $url ? rtrim($url, '/') : 'https://api.telegram.org';
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/ConfigTest.php`
Expected: `OK`.

- [ ] **Step 5: Commit**

```bash
git add src/BotHost/Config.php tests/ConfigTest.php
git commit -m "add bot host config loader"
```

---

### Task 3: Central error → Persian map

**Files:**
- Create: `src/BotHost/Errors/ApiErrorMessages.php`
- Test: `tests/ApiErrorMessagesTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `ApiErrorMessages::toPersian(?string $code): string`.

- [ ] **Step 1: Write the failing test**

Create `tests/ApiErrorMessagesTest.php`:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/harness.php';

use BotHost\Errors\ApiErrorMessages;

checkSame('کلید سرویس ربات نامعتبر است.', ApiErrorMessages::toPersian('BOT_UNAUTHORIZED'), 'maps documented code');
checkSame('خطای نامشخصی رخ داد. لطفاً دوباره تلاش کنید.', ApiErrorMessages::toPersian('NO_SUPPORTER_ASSIGNED'), 'unknown code falls back, not invented');
checkSame('خطای نامشخصی رخ داد. لطفاً دوباره تلاش کنید.', ApiErrorMessages::toPersian(null), 'null falls back');

echo "OK\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/ApiErrorMessagesTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement the map**

Create `src/BotHost/Errors/ApiErrorMessages.php`. Only codes documented in `openapi.yaml` are mapped; unknown codes get the generic fallback (do not invent codes):

```php
<?php
declare(strict_types=1);

namespace BotHost\Errors;

final class ApiErrorMessages
{
    private const MAP = [
        'AUTH_ERROR' => 'احراز هویت ناموفق بود.',
        'BOT_UNAUTHORIZED' => 'کلید سرویس ربات نامعتبر است.',
        'BOT_ACCOUNT_BLOCKED' => 'حساب تلگرام شما مسدود شده است.',
        'FORBIDDEN' => 'دسترسی غیرمجاز.',
        'NOT_FOUND' => 'موردی یافت نشد.',
        'VALIDATION_ERROR' => 'اطلاعات ارسالی نامعتبر است.',
        'CONTENT_ACCESS_DISABLED' => 'دسترسی به محتوای پیام‌ها فعال نیست.',
        'REGISTRATION_ERROR' => 'کاربر قبلاً ثبت‌نام کرده است.',
        'INTERNAL_ERROR' => 'خطای داخلی سرور.',
    ];

    private const FALLBACK = 'خطای نامشخصی رخ داد. لطفاً دوباره تلاش کنید.';

    public static function toPersian(?string $code): string
    {
        if ($code !== null && isset(self::MAP[$code])) {
            return self::MAP[$code];
        }
        return self::FALLBACK;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/ApiErrorMessagesTest.php`
Expected: `OK`.

- [ ] **Step 5: Commit**

```bash
git add src/BotHost/Errors/ApiErrorMessages.php tests/ApiErrorMessagesTest.php
git commit -m "add central api error to persian map"
```

---

### Task 4: Telegram cURL client

**Files:**
- Create: `src/BotHost/Telegram/TelegramClient.php`
- Test: `tests/TelegramClientTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `new TelegramClient(string $token, string $baseUrl = 'https://api.telegram.org', int $timeout = 15)`
  - `call(string $method, array<string,mixed> $params = []): array<string,mixed>` (throws `RuntimeException` on transport or API error; returns decoded result)
  - `getMe(): array`, `setWebhook(string $url, string $secret, array $allowedUpdates = []): array`, `deleteWebhook(bool $dropPending = false): array`, `getWebhookInfo(): array`

- [ ] **Step 1: Write the failing test**

Create `tests/TelegramClientTest.php`. It uses a subclass that overrides the transport so no network is needed:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/TelegramClientTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement TelegramClient**

Create `src/BotHost/Telegram/TelegramClient.php`:

```php
<?php
declare(strict_types=1);

namespace BotHost\Telegram;

class TelegramClient
{
    public function __construct(
        private string $token,
        private string $baseUrl = 'https://api.telegram.org',
        private int $timeout = 15,
    ) {}

    /** @param array<string,mixed> $params @return array<string,mixed> */
    public function call(string $method, array $params = []): array
    {
        $url = $this->baseUrl . '/bot' . $this->token . '/' . $method;
        $decoded = $this->transport($url, $params);

        if (($decoded['ok'] ?? false) !== true) {
            $desc = (string) ($decoded['description'] ?? 'unknown error');
            $code = (int) ($decoded['error_code'] ?? 0);
            throw new \RuntimeException("Telegram API error {$code}: {$desc}");
        }
        return $decoded;
    }

    /** @param array<string,mixed> $params @return array<string,mixed> */
    protected function transport(string $url, array $params): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $errno !== 0) {
            throw new \RuntimeException('Telegram transport error: ' . ($error !== '' ? $error : (string) $errno));
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Telegram returned invalid JSON');
        }
        return $decoded;
    }

    /** @return array<string,mixed> */
    public function getMe(): array
    {
        return $this->call('getMe');
    }

    /** @param list<string> $allowedUpdates @return array<string,mixed> */
    public function setWebhook(string $url, string $secret, array $allowedUpdates = []): array
    {
        $params = ['url' => $url, 'drop_pending_updates' => false];
        if ($secret !== '') {
            $params['secret_token'] = $secret;
        }
        if ($allowedUpdates !== []) {
            $params['allowed_updates'] = json_encode(array_values($allowedUpdates));
        }
        return $this->call('setWebhook', $params);
    }

    /** @return array<string,mixed> */
    public function deleteWebhook(bool $dropPending = false): array
    {
        return $this->call('deleteWebhook', ['drop_pending_updates' => $dropPending]);
    }

    /** @return array<string,mixed> */
    public function getWebhookInfo(): array
    {
        return $this->call('getWebhookInfo');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/TelegramClientTest.php`
Expected: `OK`.

- [ ] **Step 5: Commit**

```bash
git add src/BotHost/Telegram/TelegramClient.php tests/TelegramClientTest.php
git commit -m "add telegram curl client"
```

---

### Task 5: Famo API cURL client

**Files:**
- Create: `src/BotHost/Api/ApiResult.php`
- Create: `src/BotHost/Api/FamoApiClient.php`
- Test: `tests/FamoApiClientTest.php`

**Interfaces:**
- Consumes: `Config::apiBaseUrl()`, `Config::require('BOT_SERVICE_KEY')`.
- Produces:
  - `ApiResult` readonly props: `int $status`, `?array $body`, `?string $errorCode`, `?string $transportError`; methods `ok(): bool`, `data(): mixed`.
  - `new FamoApiClient(string $serviceKey, string $baseUrl, int $timeout = 15)`
  - `request(string $method, string $path, array<string,string> $headers = [], ?array $json = null, array<string,string> $query = []): ApiResult`
  - `ping(): ApiResult` → `GET /api/v1/bot/ping` with `X-Bot-Key`.

- [ ] **Step 1: Write the failing test**

Create `tests/FamoApiClientTest.php` using an override for the HTTP call:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/harness.php';

use BotHost\Api\FamoApiClient;
use BotHost\Api\ApiResult;

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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/FamoApiClientTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement ApiResult and FamoApiClient**

Create `src/BotHost/Api/ApiResult.php`:

```php
<?php
declare(strict_types=1);

namespace BotHost\Api;

final class ApiResult
{
    /** @param array<string,mixed>|null $body */
    public function __construct(
        public readonly int $status,
        public readonly ?array $body,
        public readonly ?string $errorCode = null,
        public readonly ?string $transportError = null,
    ) {}

    public function ok(): bool
    {
        return $this->transportError === null
            && $this->status >= 200 && $this->status < 300
            && is_array($this->body) && ($this->body['success'] ?? false) === true;
    }

    public function data(): mixed
    {
        return $this->body['data'] ?? null;
    }
}
```

Create `src/BotHost/Api/FamoApiClient.php`:

```php
<?php
declare(strict_types=1);

namespace BotHost\Api;

class FamoApiClient
{
    public function __construct(
        private string $serviceKey,
        private string $baseUrl,
        private int $timeout = 15,
    ) {}

    /**
     * @param array<string,string> $headers
     * @param array<string,mixed>|null $json
     * @param array<string,string> $query
     */
    public function request(string $method, string $path, array $headers = [], ?array $json = null, array $query = []): ApiResult
    {
        $url = $this->baseUrl . '/api/v1' . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $allHeaders = ['X-Bot-Key: ' . $this->serviceKey, 'Accept: application/json'];
        foreach ($headers as $name => $value) {
            $allHeaders[] = $name . ': ' . $value;
        }
        $body = null;
        if ($json !== null) {
            $body = json_encode($json, JSON_UNESCAPED_UNICODE);
            $allHeaders[] = 'Content-Type: application/json';
        }

        $response = $this->http($method, $url, $allHeaders, $body);

        if (($response['transport'] ?? null) !== null) {
            return new ApiResult(0, null, null, (string) $response['transport']);
        }
        $status = (int) $response['status'];
        $decoded = $response['body'];
        $decoded = is_array($decoded) ? $decoded : null;
        $code = $decoded['error']['code'] ?? null;
        return new ApiResult($status, $decoded, is_string($code) ? $code : null);
    }

    public function ping(): ApiResult
    {
        return $this->request('GET', '/bot/ping');
    }

    /**
     * @param list<string> $headers
     * @return array{status:int, body:array<string,mixed>|null, transport:?string}
     */
    protected function http(string $method, string $url, array $headers, ?string $body): array
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $errno !== 0) {
            return ['status' => 0, 'body' => null, 'transport' => $error !== '' ? $error : (string) $errno];
        }
        $decoded = json_decode((string) $raw, true);
        return ['status' => $status, 'body' => is_array($decoded) ? $decoded : null, 'transport' => null];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/FamoApiClientTest.php`
Expected: `OK`.

- [ ] **Step 5: Commit**

```bash
git add src/BotHost/Api/ApiResult.php src/BotHost/Api/FamoApiClient.php tests/FamoApiClientTest.php
git commit -m "add famo api curl client"
```

---

### Task 6: LocalStore (dedupe, queue, state, meta)

**Files:**
- Create: `src/BotHost/Storage/LocalStore.php`
- Test: `tests/LocalStoreTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `new LocalStore(string $storageDir)` (creates dir + `bot.sqlite`)
  - `markProcessed(int $updateId): bool` (false on duplicate)
  - `enqueueUpdate(int $updateId, array $update): void`
  - `dequeueUpdates(int $limit): array<int,array{update_id:int,payload:array}>`
  - `setState(string $chatId, array $data, int $ttlSeconds): void`
  - `getState(string $chatId): ?array` (null when missing/expired)
  - `clearState(string $chatId): void`
  - `setMeta(string $key, string $value): void`, `getMeta(string $key): ?string`

- [ ] **Step 1: Write the failing test**

Create `tests/LocalStoreTest.php`:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/harness.php';

use BotHost\Storage\LocalStore;

$dir = sys_get_temp_dir() . '/bothost_' . bin2hex(random_bytes(4));
$store = new LocalStore($dir);

check($store->markProcessed(101), 'first update_id is new');
check(!$store->markProcessed(101), 'duplicate update_id rejected');

$store->enqueueUpdate(101, ['update_id' => 101, 'message' => ['text' => 'hi']]);
$store->enqueueUpdate(102, ['update_id' => 102]);
$batch = $store->dequeueUpdates(10);
checkSame(2, count($batch), 'dequeues both');
checkSame(101, $batch[0]['update_id'], 'ordered by update_id');
checkSame([], $store->dequeueUpdates(10), 'queue is emptied');

$store->setState('55', ['step' => 'reply'], 60);
checkSame('reply', $store->getState('55')['step'], 'state round-trips');
$store->setState('56', ['step' => 'x'], -1);
checkSame(null, $store->getState('56'), 'expired state returns null');
$store->clearState('55');
checkSame(null, $store->getState('55'), 'clearState removes');

$store->setMeta('last_drain', '2026-09-30T00:00:00Z');
checkSame('2026-09-30T00:00:00Z', $store->getMeta('last_drain'), 'meta round-trips');

echo "OK\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/LocalStoreTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement LocalStore**

Create `src/BotHost/Storage/LocalStore.php`:

```php
<?php
declare(strict_types=1);

namespace BotHost\Storage;

final class LocalStore
{
    private \PDO $pdo;

    public function __construct(string $storageDir)
    {
        if (!is_dir($storageDir) && !mkdir($storageDir, 0770, true) && !is_dir($storageDir)) {
            throw new \RuntimeException("Cannot create storage dir: {$storageDir}");
        }
        $dsn = 'sqlite:' . rtrim($storageDir, "/\\") . DIRECTORY_SEPARATOR . 'bot.sqlite';
        $this->pdo = new \PDO($dsn, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('PRAGMA journal_mode=WAL');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS processed_updates (update_id INTEGER PRIMARY KEY, processed_at TEXT NOT NULL)');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS update_queue (update_id INTEGER PRIMARY KEY, payload TEXT NOT NULL, created_at TEXT NOT NULL)');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS state (chat_id TEXT PRIMARY KEY, data TEXT NOT NULL, expires_at INTEGER NOT NULL)');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS meta (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
    }

    public function markProcessed(int $updateId): bool
    {
        try {
            $this->pdo->prepare('INSERT INTO processed_updates (update_id, processed_at) VALUES (?, ?)')
                ->execute([$updateId, date('c')]);
            return true;
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
    }

    /** @param array<string,mixed> $update */
    public function enqueueUpdate(int $updateId, array $update): void
    {
        $this->pdo->prepare('INSERT OR IGNORE INTO update_queue (update_id, payload, created_at) VALUES (?, ?, ?)')
            ->execute([$updateId, json_encode($update, JSON_UNESCAPED_UNICODE), date('c')]);
    }

    /** @return list<array{update_id:int,payload:array<string,mixed>}> */
    public function dequeueUpdates(int $limit): array
    {
        $stmt = $this->pdo->prepare('SELECT update_id, payload FROM update_queue ORDER BY update_id ASC LIMIT ?');
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if ($rows === []) {
            return [];
        }
        $ids = array_map(static fn ($r) => (int) $r['update_id'], $rows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->pdo->prepare("DELETE FROM update_queue WHERE update_id IN ({$placeholders})")->execute($ids);

        return array_map(static fn ($r) => [
            'update_id' => (int) $r['update_id'],
            'payload' => json_decode((string) $r['payload'], true) ?: [],
        ], $rows);
    }

    /** @param array<string,mixed> $data */
    public function setState(string $chatId, array $data, int $ttlSeconds): void
    {
        $this->pdo->prepare(
            'INSERT INTO state (chat_id, data, expires_at) VALUES (?, ?, ?)
             ON CONFLICT(chat_id) DO UPDATE SET data = excluded.data, expires_at = excluded.expires_at'
        )->execute([$chatId, json_encode($data, JSON_UNESCAPED_UNICODE), time() + $ttlSeconds]);
    }

    /** @return array<string,mixed>|null */
    public function getState(string $chatId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT data, expires_at FROM state WHERE chat_id = ?');
        $stmt->execute([$chatId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        if ((int) $row['expires_at'] < time()) {
            $this->clearState($chatId);
            return null;
        }
        $data = json_decode((string) $row['data'], true);
        return is_array($data) ? $data : null;
    }

    public function clearState(string $chatId): void
    {
        $this->pdo->prepare('DELETE FROM state WHERE chat_id = ?')->execute([$chatId]);
    }

    public function setMeta(string $key, string $value): void
    {
        $this->pdo->prepare(
            'INSERT INTO meta (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value'
        )->execute([$key, $value]);
    }

    public function getMeta(string $key): ?string
    {
        $stmt = $this->pdo->prepare('SELECT value FROM meta WHERE key = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (string) $value;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/LocalStoreTest.php`
Expected: `OK`.

- [ ] **Step 5: Commit**

```bash
git add src/BotHost/Storage/LocalStore.php tests/LocalStoreTest.php
git commit -m "add local sqlite store for dedupe, queue and state"
```

---

### Task 7: RuntimeStats and RuntimeLogger (panel-compatible)

**Files:**
- Create: `src/BotHost/Storage/RuntimeStats.php`
- Create: `src/BotHost/Logging/RuntimeLogger.php`
- Test: `tests/RuntimeTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `new RuntimeStats(string $file)`
  - `snapshot(): array` with keys `last_webhook_at`, `last_api_ok_at`, `last_api_error_at`, `last_drain_at`, `counters.{outbox_sent,outbox_failed,outbox_blocked,errors}`, `recent_errors` (list of `{time,message}`).
  - `recordWebhook(): void`, `recordApi(bool $ok): void`, `recordDrain(): void`, `increment(string $counter): void`, `addError(string $message): void`
  - `new RuntimeLogger(string $logFile, int $maxEntries = 500)`; `log(string $type, string $message, array $data = []): void` writing the panel's `{time,type,message,data}` shape.

- [ ] **Step 1: Write the failing test**

Create `tests/RuntimeTest.php`:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/harness.php';

use BotHost\Storage\RuntimeStats;
use BotHost\Logging\RuntimeLogger;

$dir = sys_get_temp_dir() . '/bothost_' . bin2hex(random_bytes(4));
mkdir($dir, 0770, true);

$stats = new RuntimeStats($dir . '/runtime.json');
checkSame(null, $stats->snapshot()['last_webhook_at'], 'empty snapshot has nulls');

$stats->recordWebhook();
$stats->recordApi(true);
$stats->recordApi(false);
$stats->recordDrain();
$stats->increment('outbox_sent');
$stats->increment('outbox_sent');
$stats->addError('boom');

$snap = $stats->snapshot();
check($snap['last_webhook_at'] !== null, 'webhook time recorded');
check($snap['last_api_ok_at'] !== null, 'api ok recorded');
check($snap['last_api_error_at'] !== null, 'api error recorded');
check($snap['last_drain_at'] !== null, 'drain recorded');
checkSame(2, $snap['counters']['outbox_sent'], 'counter increments');
checkSame('boom', $snap['recent_errors'][0]['message'], 'recent error stored');

$logger = new RuntimeLogger($dir . '/logs.json');
$logger->log('webhook', 'received update');
$logger->log('error', 'failed api call', ['code' => 'BOT_UNAUTHORIZED']);
$logs = json_decode((string) file_get_contents($dir . '/logs.json'), true);
checkSame(2, count($logs), 'two log entries');
checkSame('webhook', $logs[0]['type'], 'panel-compatible type');
check(array_key_exists('data', $logs[0]), 'panel-compatible data key');

echo "OK\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/RuntimeTest.php`
Expected: FAIL — classes not found.

- [ ] **Step 3: Implement RuntimeStats and RuntimeLogger**

Create `src/BotHost/Storage/RuntimeStats.php`:

```php
<?php
declare(strict_types=1);

namespace BotHost\Storage;

final class RuntimeStats
{
    public function __construct(private string $file) {}

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        if (!is_file($this->file)) {
            return $this->empty();
        }
        $decoded = json_decode((string) file_get_contents($this->file), true);
        return is_array($decoded) ? array_replace($this->empty(), $decoded) : $this->empty();
    }

    public function recordWebhook(): void
    {
        $this->mutate(static function (array &$s): void { $s['last_webhook_at'] = date('c'); });
    }

    public function recordApi(bool $ok): void
    {
        $this->mutate(static function (array &$s) use ($ok): void {
            $s[$ok ? 'last_api_ok_at' : 'last_api_error_at'] = date('c');
        });
    }

    public function recordDrain(): void
    {
        $this->mutate(static function (array &$s): void { $s['last_drain_at'] = date('c'); });
    }

    public function increment(string $counter): void
    {
        $this->mutate(static function (array &$s) use ($counter): void {
            $s['counters'][$counter] = (int) ($s['counters'][$counter] ?? 0) + 1;
        });
    }

    public function addError(string $message): void
    {
        $this->mutate(static function (array &$s) use ($message): void {
            $s['counters']['errors'] = (int) ($s['counters']['errors'] ?? 0) + 1;
            $s['recent_errors'][] = ['time' => date('c'), 'message' => mb_substr($message, 0, 200)];
            $s['recent_errors'] = array_slice($s['recent_errors'], -20);
        });
    }

    /** @return array<string,mixed> */
    private function empty(): array
    {
        return [
            'last_webhook_at' => null,
            'last_api_ok_at' => null,
            'last_api_error_at' => null,
            'last_drain_at' => null,
            'counters' => ['outbox_sent' => 0, 'outbox_failed' => 0, 'outbox_blocked' => 0, 'errors' => 0],
            'recent_errors' => [],
        ];
    }

    private function mutate(callable $fn): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create runtime dir: {$dir}");
        }
        $lock = fopen($this->file . '.lock', 'c');
        if ($lock !== false) {
            flock($lock, LOCK_EX);
        }
        $snapshot = $this->snapshot();
        $fn($snapshot);
        $tmp = $this->file . '.tmp.' . getmypid();
        file_put_contents($tmp, json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        rename($tmp, $this->file);
        if ($lock !== false) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
```

Create `src/BotHost/Logging/RuntimeLogger.php`:

```php
<?php
declare(strict_types=1);

namespace BotHost\Logging;

final class RuntimeLogger
{
    public function __construct(private string $logFile, private int $maxEntries = 500) {}

    /** @param array<string,mixed> $data */
    public function log(string $type, string $message, array $data = []): void
    {
        $entries = [];
        if (is_file($this->logFile)) {
            $decoded = json_decode((string) file_get_contents($this->logFile), true);
            if (is_array($decoded)) {
                $entries = $decoded;
            }
        }
        $entries[] = [
            'time' => date('Y-m-d H:i:s'),
            'type' => $type,
            'message' => mb_substr($message, 0, 500),
            'data' => $data === [] ? null : $data,
        ];
        if (count($entries) > $this->maxEntries) {
            $entries = array_slice($entries, -$this->maxEntries);
        }
        $dir = dirname($this->logFile);
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create log dir: {$dir}");
        }
        file_put_contents($this->logFile, json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/RuntimeTest.php`
Expected: `OK`.

- [ ] **Step 5: Commit**

```bash
git add src/BotHost/Storage/RuntimeStats.php src/BotHost/Logging/RuntimeLogger.php tests/RuntimeTest.php
git commit -m "add runtime stats and panel-compatible logger"
```

---

### Task 8: OutboxDrainer lock skeleton

**Files:**
- Create: `src/BotHost/Outbox/OutboxDrainer.php`
- Test: `tests/OutboxDrainerTest.php`

**Interfaces:**
- Consumes: `RuntimeStats::recordDrain()`.
- Produces: `new OutboxDrainer(string $lockFile, RuntimeStats $stats)`; `run(int $budgetSeconds): array{ran:bool, reason?:string, elapsed?:float}`. In Module 1 it acquires the single lock, records the drain time, and releases; no claim/send yet.

- [ ] **Step 1: Write the failing test**

Create `tests/OutboxDrainerTest.php`:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/harness.php';

use BotHost\Outbox\OutboxDrainer;
use BotHost\Storage\RuntimeStats;

$dir = sys_get_temp_dir() . '/bothost_' . bin2hex(random_bytes(4));
mkdir($dir, 0770, true);
$stats = new RuntimeStats($dir . '/runtime.json');

$drainer = new OutboxDrainer($dir . '/drain.lock', $stats);
$first = $drainer->run(1);
check($first['ran'], 'first run acquires lock');
check($stats->snapshot()['last_drain_at'] !== null, 'drain time recorded');

// Second drainer holds the lock while the first is still inside is simulated
// by holding the lock file open in this process.
$handle = fopen($dir . '/drain.lock', 'c');
flock($handle, LOCK_EX | LOCK_NB);
$blocked = $drainer->run(1);
flock($handle, LOCK_UN);
fclose($handle);
check(!$blocked['ran'], 'second run is blocked by held lock');
checkSame('locked', $blocked['reason'], 'reports locked reason');

echo "OK\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/OutboxDrainerTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement OutboxDrainer**

Create `src/BotHost/Outbox/OutboxDrainer.php`:

```php
<?php
declare(strict_types=1);

namespace BotHost\Outbox;

use BotHost\Storage\RuntimeStats;

final class OutboxDrainer
{
    public function __construct(
        private string $lockFile,
        private RuntimeStats $stats,
    ) {}

    /**
     * Module 1: acquire the single shared lock and record the drain time.
     * The claim/send/report loop is added in the Outbox module.
     *
     * @return array{ran:bool, reason?:string, elapsed?:float}
     */
    public function run(int $budgetSeconds): array
    {
        $dir = dirname($this->lockFile);
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            return ['ran' => false, 'reason' => 'lock_dir_failed'];
        }
        $lock = fopen($this->lockFile, 'c');
        if ($lock === false) {
            return ['ran' => false, 'reason' => 'lock_open_failed'];
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return ['ran' => false, 'reason' => 'locked'];
        }

        try {
            $start = microtime(true);
            $this->stats->recordDrain();
            return ['ran' => true, 'elapsed' => microtime(true) - $start];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/OutboxDrainerTest.php`
Expected: `OK`.

- [ ] **Step 5: Commit**

```bash
git add src/BotHost/Outbox/OutboxDrainer.php tests/OutboxDrainerTest.php
git commit -m "add outbox drainer lock skeleton"
```

---

### Task 9: Bootstrap wiring and WebhookHandler

**Files:**
- Create: `src/BotHost/Bootstrap.php`
- Create: `src/BotHost/WebhookHandler.php`
- Test: `tests/WebhookHandlerTest.php`

**Interfaces:**
- Consumes: `Config`, `LocalStore`, `RuntimeStats`, `RuntimeLogger`, `OutboxDrainer`, `TelegramClient`, `FamoApiClient`.
- Produces:
  - `Bootstrap::create(): self`; accessors `config()`, `store()`, `stats()`, `logger()`, `drainer()`, `telegram()`, `api()`.
  - `new WebhookHandler(Config, LocalStore, RuntimeStats, RuntimeLogger, OutboxDrainer)`
  - `handle(string $rawBody, ?string $secretHeader): int` → HTTP status; validates secret, parses, dedupes, enqueues.
  - `finish(): void` → processes queued updates (placeholder dispatcher) then drains.

`Bootstrap` path defaults:
- storage: env `BOT_STORAGE_DIR` or `<root>/storage`
- runtime file: env `BOT_RUNTIME_FILE` or `<root>/admin/data/bot-runtime.json`
- log file: env `BOT_LOG_FILE` or `<root>/admin/data/logs.json`
- drain lock: `<storage>/drain.lock`

- [ ] **Step 1: Write the failing test**

Create `tests/WebhookHandlerTest.php`:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/harness.php';

use BotHost\Config;
use BotHost\Storage\LocalStore;
use BotHost\Storage\RuntimeStats;
use BotHost\Logging\RuntimeLogger;
use BotHost\Outbox\OutboxDrainer;
use BotHost\WebhookHandler;

$dir = sys_get_temp_dir() . '/bothost_' . bin2hex(random_bytes(4));
mkdir($dir, 0770, true);

$config = Config::fromArray(['BOT_WEBHOOK_SECRET' => 'SECRET123', 'BOT_DRAIN_BATCH_LIMIT' => '5']);
$store = new LocalStore($dir . '/s');
$stats = new RuntimeStats($dir . '/runtime.json');
$logger = new RuntimeLogger($dir . '/logs.json');
$drainer = new OutboxDrainer($dir . '/drain.lock', $stats);
$handler = new WebhookHandler($config, $store, $stats, $logger, $drainer);

$update = json_encode(['update_id' => 500, 'message' => ['text' => 'hi']]);

checkSame(403, $handler->handle($update, 'WRONG'), 'bad secret rejected');
checkSame(403, $handler->handle($update, null), 'missing secret rejected');

checkSame(200, $handler->handle($update, 'SECRET123'), 'valid secret accepted');
checkSame(200, $handler->handle($update, 'SECRET123'), 'duplicate update still 200');
checkSame(1, count($store->dequeueUpdates(10)), 'duplicate not enqueued twice');

checkSame(200, $handler->handle('not json', 'SECRET123'), 'malformed body tolerated with 200');

$handler->finish();
check($stats->snapshot()['last_drain_at'] !== null, 'finish drains');

echo "OK\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/WebhookHandlerTest.php`
Expected: FAIL — classes not found.

- [ ] **Step 3: Implement WebhookHandler**

Create `src/BotHost/WebhookHandler.php`:

```php
<?php
declare(strict_types=1);

namespace BotHost;

use BotHost\Logging\RuntimeLogger;
use BotHost\Outbox\OutboxDrainer;
use BotHost\Storage\LocalStore;
use BotHost\Storage\RuntimeStats;

final class WebhookHandler
{
    public function __construct(
        private Config $config,
        private LocalStore $store,
        private RuntimeStats $stats,
        private RuntimeLogger $logger,
        private OutboxDrainer $drainer,
    ) {}

    public function handle(string $rawBody, ?string $secretHeader): int
    {
        $expected = $this->config->get('BOT_WEBHOOK_SECRET');
        if ($expected === null || $secretHeader === null || !hash_equals($expected, $secretHeader)) {
            $this->logger->log('error', 'webhook rejected: bad secret');
            return 403;
        }

        $update = json_decode($rawBody, true);
        if (!is_array($update) || !isset($update['update_id'])) {
            $this->logger->log('webhook', 'ignored malformed update');
            return 200;
        }

        $updateId = (int) $update['update_id'];
        if (!$this->store->markProcessed($updateId)) {
            return 200;
        }

        $this->store->enqueueUpdate($updateId, $update);
        $this->stats->recordWebhook();
        return 200;
    }

    public function finish(): void
    {
        try {
            foreach ($this->store->dequeueUpdates($this->config->int('BOT_DRAIN_BATCH_LIMIT', 10)) as $item) {
                // Dispatch to feature handlers is added in later modules.
            }
            $this->drainer->run($this->config->int('BOT_DRAIN_BUDGET_SECONDS', 20));
        } catch (\Throwable $e) {
            $this->logger->log('error', 'finish failed: ' . $e->getMessage());
            $this->stats->addError($e->getMessage());
        }
    }
}
```

Create `src/BotHost/Bootstrap.php`:

```php
<?php
declare(strict_types=1);

namespace BotHost;

use BotHost\Api\FamoApiClient;
use BotHost\Logging\RuntimeLogger;
use BotHost\Outbox\OutboxDrainer;
use BotHost\Storage\LocalStore;
use BotHost\Storage\RuntimeStats;
use BotHost\Telegram\TelegramClient;

final class Bootstrap
{
    private function __construct(
        private Config $config,
        private LocalStore $store,
        private RuntimeStats $stats,
        private RuntimeLogger $logger,
        private OutboxDrainer $drainer,
        private TelegramClient $telegram,
        private FamoApiClient $api,
    ) {}

    public static function create(?string $root = null): self
    {
        $root ??= dirname(__DIR__, 2);
        if (is_file($root . '/.env')) {
            \Dotenv\Dotenv::createImmutable($root)->safeLoad();
        }

        $config = Config::fromEnv();
        $storageDir = $config->get('BOT_STORAGE_DIR') ?? $root . '/storage';
        $runtimeFile = $config->get('BOT_RUNTIME_FILE') ?? $root . '/admin/data/bot-runtime.json';
        $logFile = $config->get('BOT_LOG_FILE') ?? $root . '/admin/data/logs.json';

        $store = new LocalStore($storageDir);
        $stats = new RuntimeStats($runtimeFile);
        $logger = new RuntimeLogger($logFile);
        $drainer = new OutboxDrainer(rtrim($storageDir, "/\\") . DIRECTORY_SEPARATOR . 'drain.lock', $stats);
        $telegram = new TelegramClient($config->require('TELEGRAM_BOT_TOKEN'), $config->telegramBaseUrl());
        $api = new FamoApiClient($config->require('BOT_SERVICE_KEY'), $config->apiBaseUrl());

        return new self($config, $store, $stats, $logger, $drainer, $telegram, $api);
    }

    public function config(): Config { return $this->config; }
    public function store(): LocalStore { return $this->store; }
    public function stats(): RuntimeStats { return $this->stats; }
    public function logger(): RuntimeLogger { return $this->logger; }
    public function drainer(): OutboxDrainer { return $this->drainer; }
    public function telegram(): TelegramClient { return $this->telegram; }
    public function api(): FamoApiClient { return $this->api; }

    public function webhookHandler(): WebhookHandler
    {
        return new WebhookHandler($this->config, $this->store, $this->stats, $this->logger, $this->drainer);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/WebhookHandlerTest.php`
Expected: `OK`.

- [ ] **Step 5: Commit**

```bash
git add src/BotHost/Bootstrap.php src/BotHost/WebhookHandler.php tests/WebhookHandlerTest.php
git commit -m "add bootstrap wiring and webhook handler"
```

---

### Task 10: Public entry points (webhook + internal drain)

**Files:**
- Create: `public/bot/webhook.php`
- Create: `public/bot/internal-drain.php`
- Test: verification via `php -l` and the Module 1 manual checklist (Task 14)

**Interfaces:**
- Consumes: `BotHost\Bootstrap`.
- Produces: two HTTP entry points. `webhook.php` reads `HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN`; `internal-drain.php` reads `HTTP_X_BOT_INTERNAL_SECRET` (or `?secret=`) and supports `HEAD`.

- [ ] **Step 1: Create the webhook entry**

Create `public/bot/webhook.php`:

```php
<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use BotHost\Bootstrap;

ignore_user_abort(true);
set_time_limit(60);

try {
    $app = Bootstrap::create();
    $handler = $app->webhookHandler();
    $secret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;
    $raw = (string) file_get_contents('php://input');

    $status = $handler->handle($raw, is_string($secret) ? $secret : null);
    http_response_code($status);

    if ($status === 200) {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }
        $handler->finish();
    }
} catch (\Throwable $e) {
    if (!headers_sent()) {
        http_response_code(200);
    }
    error_log('bot webhook fatal: ' . $e->getMessage());
}
```

- [ ] **Step 2: Create the internal drain entry**

Create `public/bot/internal-drain.php`:

```php
<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use BotHost\Bootstrap;

try {
    $app = Bootstrap::create();
    $expected = $app->config()->get('BOT_INTERNAL_SECRET');
    $provided = $_SERVER['HTTP_X_BOT_INTERNAL_SECRET'] ?? ($_GET['secret'] ?? null);

    if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
        http_response_code($expected !== null && is_string($provided) && hash_equals($expected, $provided) ? 204 : 403);
        exit;
    }

    if ($expected === null || !is_string($provided) || !hash_equals($expected, $provided)) {
        http_response_code(403);
        exit;
    }

    ignore_user_abort(true);
    set_time_limit(0);
    http_response_code(200);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
    }

    $app->drainer()->run($app->config()->int('BOT_DRAIN_BUDGET_SECONDS', 20));
} catch (\Throwable $e) {
    if (!headers_sent()) {
        http_response_code(200);
    }
    error_log('bot internal drain fatal: ' . $e->getMessage());
}
```

- [ ] **Step 3: Lint both files**

Run: `php -l public/bot/webhook.php; php -l public/bot/internal-drain.php`
Expected: `No syntax errors detected in ...` for both.

- [ ] **Step 4: Commit**

```bash
git add public/bot/webhook.php public/bot/internal-drain.php
git commit -m "add versioned webhook and internal drain entries"
```

---

### Task 11: Webhook management scripts (set / delete / rollback)

**Files:**
- Create: `bin/set-webhook.php`
- Create: `bin/delete-webhook.php`
- Create: `bin/rollback-webhook.php`
- Test: `php -l` on each (network calls are exercised in the manual checklist)

**Interfaces:**
- Consumes: `Bootstrap::config()`, `Bootstrap::telegram()`.
- Produces: CLI scripts. `set` uses `BOT_WEBHOOK_URL` + `BOT_WEBHOOK_SECRET` with `allowed_updates = ['message','callback_query']` and `drop_pending_updates=false`. `rollback` uses `BOT_ROLLBACK_WEBHOOK_URL` if set, else deletes the webhook. `delete` uses `drop_pending_updates=false`.

- [ ] **Step 1: Create the set script**

Create `bin/set-webhook.php`:

```php
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
```

- [ ] **Step 2: Create the delete script**

Create `bin/delete-webhook.php`:

```php
#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use BotHost\Bootstrap;

$app = Bootstrap::create();
$app->telegram()->deleteWebhook(false);
echo "Webhook deleted (pending updates kept)\n";
```

- [ ] **Step 3: Create the rollback script**

Create `bin/rollback-webhook.php`:

```php
#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use BotHost\Bootstrap;

$app = Bootstrap::create();
$previous = $app->config()->get('BOT_ROLLBACK_WEBHOOK_URL');

if ($previous !== null && $previous !== '') {
    $app->telegram()->setWebhook($previous, '', ['message', 'callback_query']);
    echo "Rolled back webhook to {$previous}\n";
} else {
    $app->telegram()->deleteWebhook(false);
    echo "No rollback URL configured; webhook deleted (pending updates kept)\n";
}
```

- [ ] **Step 4: Lint**

Run: `php -l bin/set-webhook.php; php -l bin/delete-webhook.php; php -l bin/rollback-webhook.php`
Expected: no syntax errors.

- [ ] **Step 5: Commit**

```bash
git add bin/set-webhook.php bin/delete-webhook.php bin/rollback-webhook.php
git commit -m "add webhook set, delete and rollback scripts"
```

---

### Task 12: Environment-check script

**Files:**
- Create: `bin/environment-check.php`
- Test: run it against a TEST bot token; verify no secrets appear in output

**Interfaces:**
- Consumes: `Bootstrap`.
- Produces: readable CLI report of PHP version, SAPI, extensions, `fastcgi_finish_request`, `max_execution_time`, writable storage/runtime/log paths, cURL TLS, Telegram `getMe`, `GET /bot/ping` with distinct outcomes, and a `HEAD` self-request to the internal drain URL. Never prints secrets.

- [ ] **Step 1: Create the script**

Create `bin/environment-check.php`:

```php
#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use BotHost\Bootstrap;

$app = Bootstrap::create();
$config = $app->config();

function line(string $label, string $value): void
{
    echo str_pad($label, 34) . ': ' . $value . "\n";
}

echo "== Famo bot host environment check ==\n";
line('PHP version', PHP_VERSION);
line('Minimum required', '8.2');
line('SAPI', PHP_SAPI);
line('fastcgi_finish_request', function_exists('fastcgi_finish_request') ? 'yes' : 'NO (will flush + ignore_user_abort)');
line('max_execution_time', (string) ini_get('max_execution_time'));
line('ignore_user_abort', (string) ini_get('ignore_user_abort'));
foreach (['curl', 'json', 'mbstring', 'pdo_sqlite', 'openssl'] as $ext) {
    line('ext ' . $ext, extension_loaded($ext) ? 'yes' : 'NO');
}

$storageDir = $config->get('BOT_STORAGE_DIR') ?? dirname(__DIR__) . '/storage';
$runtimeFile = $config->get('BOT_RUNTIME_FILE') ?? dirname(__DIR__) . '/admin/data/bot-runtime.json';
$logFile = $config->get('BOT_LOG_FILE') ?? dirname(__DIR__) . '/admin/data/logs.json';
$webRoot = realpath(dirname(__DIR__) . '/public') ?: '';
foreach (['storage' => $storageDir, 'runtime' => dirname($runtimeFile), 'log' => dirname($logFile)] as $label => $path) {
    $exists = is_dir($path);
    line("{$label} dir", $path . ' exists=' . ($exists ? 'yes' : 'no') . ' writable=' . (is_writable($path) ? 'yes' : 'NO'));
    if ($webRoot !== '' && str_starts_with((string) realpath($path), $webRoot)) {
        line("  warning", "{$label} path is inside the web root; set it outside for production");
    }
}

echo "\n-- Telegram --\n";
try {
    $me = $app->telegram()->getMe();
    line('getMe', 'OK id=' . ($me['result']['id'] ?? '?') . ' username=@' . ($me['result']['username'] ?? '?'));
} catch (\Throwable $e) {
    line('getMe', 'FAILED: ' . $e->getMessage());
}

echo "\n-- Famo API (GET /bot/ping) --\n";
$result = $app->api()->ping();
if ($result->transportError !== null) {
    line('ping', 'TRANSPORT/TIMEOUT: ' . $result->transportError);
} elseif ($result->ok()) {
    line('ping', 'OK (200, key accepted)');
} else {
    line('ping', 'HTTP ' . $result->status . ($result->errorCode ? " code={$result->errorCode}" : ''));
    line('  hint', '401/wrong-key => key problem; 403 => possible IP/WAF block; 0 => network/firewall');
}

echo "\n-- Self-request to internal drain --\n";
$internalUrl = $config->get('BOT_WEBHOOK_URL');
if ($internalUrl !== null && $internalUrl !== '' && $config->get('BOT_INTERNAL_SECRET') !== null) {
    $drainUrl = preg_replace('#/webhook\.php$#', '/internal-drain.php', $internalUrl) ?? '';
    $ch = curl_init($drainUrl);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => ['X-Bot-Internal-Secret: ' . $config->get('BOT_INTERNAL_SECRET')],
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    line('HEAD internal-drain', $err !== '' ? 'ERROR: ' . $err : 'HTTP ' . $code . ($code === 204 ? ' (reachable)' : ''));
} else {
    line('HEAD internal-drain', 'skipped (BOT_WEBHOOK_URL/BOT_INTERNAL_SECRET not set)');
}

echo "\nNo secrets were printed.\n";
```

- [ ] **Step 2: Lint**

Run: `php -l bin/environment-check.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Smoke-run locally**

Run: `php bin/environment-check.php`
Expected: a readable report; `getMe`/`ping` may fail or timeout locally but the script must not crash and must not print any token/key.

- [ ] **Step 4: Commit**

```bash
git add bin/environment-check.php
git commit -m "add environment check script"
```

---

### Task 13: Minimal, separated admin panel integration

**Files:**
- Modify: `admin/functions.php`
- Modify: `index.php`
- Test: manual (Task 14) — panel loads, new section shows, no token printed

**Interfaces:**
- Consumes: runtime file from Task 7 (`bot-runtime.json`).
- Produces:
  - `admin/functions.php`: `getRuntimeFile(): string`, `getRuntimeSummary(): array` reading env `BOT_RUNTIME_FILE` or `admin/data/bot-runtime.json`.
  - `index.php`: new AJAX `case 'runtime'`; a new, clearly separated "اجرای ربات" tab/card; token removed from page JavaScript and shown only as configured/not; `delete-webhook` no longer drops pending updates.

- [ ] **Step 1: Add the runtime reader to the panel functions**

Append to `admin/functions.php` (after `getLogs`):

```php
function getRuntimeFile(): string
{
    $path = $_ENV['BOT_RUNTIME_FILE'] ?? getenv('BOT_RUNTIME_FILE');
    return ($path !== false && $path !== '' && $path !== null) ? (string) $path : getDataDir() . '/bot-runtime.json';
}

function getRuntimeSummary(): array
{
    $file = getRuntimeFile();
    if (!is_file($file)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : [];
}
```

- [ ] **Step 2: Add the AJAX case**

In `index.php`, inside the `switch ($type)` block, add a new case (keep the existing ones untouched):

```php
            case 'runtime': jsonResponse(getRuntimeSummary()); break;
```

- [ ] **Step 3: Remove the full token from the page and mask it**

In `renderDashboard()`, replace:

```php
    $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
    $botUsername = explode(':', $botToken)[0] ?? '';
```

with:

```php
    $tokenConfigured = !empty($_ENV['TELEGRAM_BOT_TOKEN']);
```

Then, in the `<script>` block, replace:

```php
            var botToken = <?= json_encode($botToken) ?>;
```

with:

```php
            var tokenConfigured = <?= $tokenConfigured ? 'true' : 'false' ?>;
```

And in `loadBotInfo`, replace the token row:

```php
                                    '<tr><td>توکن</td><td style="font-family:Inter,monospace;direction:ltr;font-size:12px;color:#737373;">' + maskedToken + '</td></tr>' +
```

with:

```php
                                    '<tr><td>توکن</td><td>' + (tokenConfigured ? '<span class="badge badge-success">تنظیم شده</span>' : '<span class="badge badge-danger">تنظیم نشده</span>') + '</td></tr>' +
```

and delete the now-unused line:

```php
                    var maskedToken = info.id ? '••••' + botToken.slice(-6) : '—';
```

- [ ] **Step 4: Stop the panel from dropping pending updates**

In `deleteWebhookAction()`, replace:

```php
                api('delete-webhook', { drop_pending: '1' }).then(function(res) {
```

with:

```php
                api('delete-webhook', { drop_pending: '0' }).then(function(res) {
```

- [ ] **Step 5: Add the separated "Bot runtime" section**

In `index.php`, add a nav entry after the `logs` link in **both** nav blocks:

```html
                    <a onclick="switchTab('runtime')" data-tab="runtime">
                        <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        اجرای ربات
                    </a>
```

In `loadTab`'s switch, add:

```javascript
                    case 'runtime': loadRuntime(container); break;
```

Add the renderer after `loadLogs`:

```javascript
            function loadRuntime(container) {
                api('runtime').then(function(res) {
                    var s = res.data || {};
                    var c = s.counters || {};
                    container.innerHTML =
                        '<div class="page-header">' +
                            '<div><h1>اجرای ربات</h1>' +
                            '<div class="subtitle">وضعیت هستهٔ جدید (Module 1)</div></div>' +
                        '</div>' +
                        '<div class="grid-4" style="margin-bottom:16px;">' +
                            '<div class="card"><div class="card-title">آخرین وب‌هوک</div><div class="card-value" style="font-size:16px;">' + (s.last_webhook_at || '—') + '</div></div>' +
                            '<div class="card"><div class="card-title">آخرین API موفق</div><div class="card-value" style="font-size:16px;">' + (s.last_api_ok_at || '—') + '</div></div>' +
                            '<div class="card"><div class="card-title">آخرین API ناموفق</div><div class="card-value" style="font-size:16px;">' + (s.last_api_error_at || '—') + '</div></div>' +
                            '<div class="card"><div class="card-title">آخرین drain</div><div class="card-value" style="font-size:16px;">' + (s.last_drain_at || '—') + '</div></div>' +
                        '</div>' +
                        '<div class="grid-4" style="margin-bottom:16px;">' +
                            '<div class="card"><div class="card-title">ارسال‌شده</div><div class="card-value">' + (c.outbox_sent || 0) + '</div></div>' +
                            '<div class="card"><div class="card-title">ناموفق</div><div class="card-value">' + (c.outbox_failed || 0) + '</div></div>' +
                            '<div class="card"><div class="card-title">مسدود</div><div class="card-value">' + (c.outbox_blocked || 0) + '</div></div>' +
                            '<div class="card"><div class="card-title">خطاها</div><div class="card-value">' + (c.errors || 0) + '</div></div>' +
                        '</div>' +
                        '<div class="card"><div class="card-title" style="margin-bottom:12px;">خطاهای اخیر</div>' +
                            ((s.recent_errors && s.recent_errors.length)
                                ? '<div class="table-wrap"><table><tr><th>زمان</th><th>پیام</th></tr>' +
                                  s.recent_errors.map(function(e) { return '<tr><td class="log-time">' + e.time + '</td><td>' + e.message + '</td></tr>'; }).join('') +
                                  '</table></div>'
                                : '<div class="empty-state">خطایی ثبت نشده است</div>') +
                        '</div>';
                }).catch(function(err) {
                    container.innerHTML = '<div class="empty-state">خطا: ' + err.message + '</div>';
                });
            }
```

- [ ] **Step 6: Lint PHP and verify the panel files parse**

Run: `php -l admin/functions.php; php -l index.php`
Expected: no syntax errors.

- [ ] **Step 7: Commit**

```bash
git add admin/functions.php index.php
git commit -m "add separated bot runtime panel section and remove token exposure"
```

---

### Task 14: Module 1 docs and manual test checklist

**Files:**
- Create: `docs/README-bot-host.md`
- Test: run the checklist against a TEST bot token

**Interfaces:**
- Consumes: everything above.
- Produces: setup/deployment notes and the manual checklist.

- [ ] **Step 1: Write `docs/README-bot-host.md`**

Include: purpose; required env variables (names only, from `.env.example`); where storage/runtime/log live and how to move them outside the web root (`BOT_STORAGE_DIR`, `BOT_RUNTIME_FILE`, `BOT_LOG_FILE`); webhook registration (`bin/set-webhook.php`), rollback (`bin/rollback-webhook.php`), delete (`bin/delete-webhook.php`); `X-Telegram-Bot-Api-Secret-Token` requirement; the no-cron drain model (webhook-triggered + internal chain, Module 4); `fastcgi_finish_request` vs flush fallback; secret rotation steps; and the checklist below. Note the Module 1 decision to default storage under `<root>/storage` with a `.htaccess` `Deny from all`, and that production should set `BOT_STORAGE_DIR` outside the web root.

- [ ] **Step 2: Add the storage `.htaccess` defense**

Create `src/BotHost/.htaccess` (empty file does nothing) — instead create the storage deny file at runtime is not possible before the dir exists; document that `bin/environment-check.php` warns if storage is inside the web root. As defense in depth, create `storage/.htaccess`:

```apache
Deny from all
```

- [ ] **Step 3: Manual test checklist (TEST bot token)**

- [ ] `php tests/SmokeTest.php` → `OK`; repeat for each `tests/*Test.php` → all `OK`.
- [ ] `php bin/environment-check.php` → prints PHP/SAPI/extensions, paths, Telegram `getMe`, API ping outcome (`OK`/`TRANSPORT/TIMEOUT`/`HTTP 403`/`HTTP 401`), self-request `HEAD` result; **no** token/key/secret in output.
- [ ] Set the TEST bot webhook: `php bin/set-webhook.php` → prints the new URL; Telegram `getWebhookInfo` shows the new URL and `has_custom_certificate=false`.
- [ ] Send a normal message to the TEST bot → Telegram receives HTTP 200; the runtime panel section shows a new `last_webhook_at`; the same update replayed by hand returns 200 without creating a second queue entry.
- [ ] POST the webhook endpoint with a wrong/absent secret header → HTTP 403 and a `webhook rejected` log line with no message content.
- [ ] POST a malformed body with the correct secret → HTTP 200, no crash, `ignored malformed update` logged.
- [ ] `curl -I` the internal drain URL with the correct secret → HTTP 204; with a wrong secret → HTTP 403; with `HEAD` → no claim/drain side effects.
- [ ] Open the admin panel → existing tabs still work; new "اجرای ربات" tab renders; no token is exposed in page source; deleting the webhook from the panel keeps pending updates.
- [ ] `php bin/rollback-webhook.php` → prints rollback/delete result; `getWebhookInfo` reflects it.

- [ ] **Step 4: Commit**

```bash
git add docs/README-bot-host.md storage/.htaccess
git commit -m "add bot host module 1 docs and storage guard"
```

---

## Self-Review

**Spec coverage:** Config (Task 2), webhook secret + dedupe + fast 200 + shape validation (Tasks 9–10), Telegram/API cURL clients with X-Bot-Key and identity-header support (Tasks 4–5), central error→Persian map (Task 3), SQLite storage with expiry/reset primitives (Task 6), logging/runtime stats without secrets (Task 7), outbox-drain lock skeleton (Task 8), set/delete/rollback webhook (Task 11), environment-check with distinct API outcomes and self-request (Task 12), separated panel integration (Task 13). Outbox sending, login, student/supporter flows are intentionally out of Module 1 scope per the spec's module order.

**Placeholder scan:** No "TBD"/"TODO"; every code step contains full code. Feature dispatch inside `WebhookHandler::finish()` and the outbox claim loop are explicitly deferred with a stated reason (later modules) — they are deliverables of later modules, not placeholders in Module 1's deliverable.

**Type consistency:** `Config`, `ApiResult`, `LocalStore`, `RuntimeStats`, `RuntimeLogger`, `OutboxDrainer`, `TelegramClient`, `FamoApiClient`, `Bootstrap`, `WebhookHandler` names, signatures, and method names match across tasks. `OutboxDrainer::run()` returns `{ran, reason?, elapsed?}` and is consumed consistently in the internal-drain entry. Runtime snapshot keys match the panel renderer.
