# Famo Bot Host (Module 1 — Foundation)

Setup and deployment notes for the Telegram bot host that lives alongside the
existing admin panel. Module 1 ships the foundation only: configuration,
webhook intake with secret validation and de-duplication, outbound clients,
local SQLite storage, runtime logging/stats, a drain lock skeleton, the
webhook management CLIs, an environment check, and a separated admin panel
section. Feature handlers (login, student/supporter flows) and the actual
outbox send loop are delivered in later modules.

## Purpose

- Receive Telegram updates over a webhook at `public/bot/webhook.php`.
- Validate every request with the Telegram secret token, reject bad requests
  fast, and answer `200` quickly so Telegram does not retry.
- De-duplicate updates by `update_id` and persist them locally so nothing is
  lost if processing is slow.
- Provide transport clients for the Telegram Bot API and the Famo API
  (`X-Bot-Key` service key), and a central error-to-Persian message map.
- Expose runtime health (last webhook / last API success / last drain /
  counters) to the admin panel without ever writing secrets or message
  content.
- Drain the local outbox without cron: triggered by webhooks and, later, by an
  internal chained self-request.

## Requirements

- PHP 8.2 or newer.
- Extensions: `curl`, `json`, `mbstring`, `pdo_sqlite`, `openssl`.
- Apache with `mod_rewrite`/`mod_headers` is recommended. `mod_authz_core`
  (Apache 2.4) or `mod_access_compat` is needed for the storage deny file.
- Composer autoloader at `vendor/autoload.php` (already required by the
  entry points).

## Configuration

All configuration is read from the project-root `.env` file (copied from
`.env.example`). Bootstrap loads it via `Dotenv` with `safeLoad()`, so a
missing `.env` does not crash the CLI scripts; required keys are only enforced
when the code actually needs them.

Environment variable names (see `.env.example` — never commit real values):

- Telegram: `TELEGRAM_BOT_TOKEN`, `TELEGRAM_API_URL`, `BOT_WEBHOOK_URL`,
  `BOT_WEBHOOK_SECRET`, `BOT_ROLLBACK_WEBHOOK_URL`
- Famo API: `API_BASE_URL`, `BOT_SERVICE_KEY`, `BOT_INTERNAL_SECRET`
- Local storage: `BOT_STORAGE_DIR`, `BOT_RUNTIME_FILE`, `BOT_LOG_FILE`
- Drain budget: `BOT_DRAIN_BUDGET_SECONDS`, `BOT_DRAIN_BATCH_LIMIT`,
  `BOT_MAX_CHAIN`

Notes:

- `TELEGRAM_API_URL` is optional and defaults to `https://api.telegram.org`
  (used to point at a local Bot API server when needed).
- `API_BASE_URL` defaults to `https://api.famoacademy.ir`.
- `BOT_WEBHOOK_SECRET` is the value sent to Telegram as `secret_token`; it must
  match the `X-Telegram-Bot-Api-Secret-Token` header on inbound requests.
- `BOT_INTERNAL_SECRET` protects the internal drain endpoint and must be
  different from `BOT_WEBHOOK_SECRET`.
- Never print or commit tokens, secrets, or service keys. The runtime files and
  the environment check are designed to never include them.

### Storage, runtime, and log locations

| Data | Env variable | Module 1 default |
| --- | --- | --- |
| SQLite store + drain lock | `BOT_STORAGE_DIR` | `<root>/storage` |
| Runtime stats JSON | `BOT_RUNTIME_FILE` | `<root>/admin/data/bot-runtime.json` |
| Log JSON | `BOT_LOG_FILE` | `<root>/admin/data/logs.json` |

- `BOT_STORAGE_DIR` holds `bot.sqlite` (WAL mode) and `drain.lock`. The
  directory is created on demand with mode `0770`.
- `BOT_RUNTIME_FILE` and `BOT_LOG_FILE` (and a `.lock`/`.tmp` companion) are
  written atomically next to the configured paths.

**Module 1 decision:** storage defaults to `<root>/storage`, which sits under
the document root. A defense-in-depth `storage/.htaccess` containing
`Deny from all` is shipped so the SQLite file and lock cannot be fetched over
HTTP. `bin/environment-check.php` prints a warning whenever the storage,
runtime, or log path is detected inside the web root.

**Production:** set `BOT_STORAGE_DIR` (and preferably `BOT_RUNTIME_FILE` /
`BOT_LOG_FILE`) to an absolute path *outside* the web root so the data is never
reachable by URL at all, for example:

```
BOT_STORAGE_DIR=/var/lib/famo-bot/storage
BOT_RUNTIME_FILE=/var/lib/famo-bot/bot-runtime.json
BOT_LOG_FILE=/var/lib/famo-bot/logs.json
```

Keep the outside-web-root directory writable by the PHP/web-server user only.

## Webhook management

All three commands run from the project root and use the `.env` configuration.

```bash
php bin/set-webhook.php        # register the webhook at BOT_WEBHOOK_URL
php bin/rollback-webhook.php   # restore BOT_ROLLBACK_WEBHOOK_URL, else delete
php bin/delete-webhook.php     # remove the webhook, keeping pending updates
```

- `set-webhook.php` calls Telegram `setWebhook` with `BOT_WEBHOOK_URL`,
  `secret_token = BOT_WEBHOOK_SECRET`, and `allowed_updates = [message,
  callback_query]`, then prints the new URL. `drop_pending_updates` is `false`.
- `delete-webhook.php` calls `deleteWebhook(drop_pending_updates = false)` so
  queued updates are preserved.
- `rollback-webhook.php` re-registers `BOT_ROLLBACK_WEBHOOK_URL` if it is set
  (note: the previous URL is registered without a secret, matching the legacy
  endpoint); otherwise it deletes the webhook and keeps pending updates.
- Verify with Telegram `getWebhookInfo` (the admin panel shows it): the URL
  shown must match and `has_custom_certificate` must be `false`.

### Inbound secret requirement

Every request hitting `public/bot/webhook.php` must carry the
`X-Telegram-Bot-Api-Secret-Token` header equal to `BOT_WEBHOOK_SECRET`.
Comparison uses `hash_equals`. A missing or wrong header returns **HTTP 403**
and writes a `webhook rejected` log line containing no message content.
Malformed bodies (missing `update_id`, unreadable JSON) are logged as
`ignored malformed update` and answered with **HTTP 200** so Telegram does not
retry. Updates are inserted into `update_queue` and `processed_updates`; a
replayed `update_id` is recognised and does not create a second queue entry.

## No-cron drain model

The host is intentionally cron-free.

1. On a webhook request, after the `200` response has been flushed, the handler
   processes a small batch of queued updates and then calls the outbox drainer
   for a short budget (`BOT_DRAIN_BUDGET_SECONDS`, default 20 s).
2. In the Outbox module (Module 4) the drainer will claim outbox items, send
   them through the Telegram/API clients, and report success/failure/blocked
   counters. Module 1 ships the single shared lock skeleton only: `run()`
   acquires an exclusive non-blocking `flock` on `drain.lock` and records the
   drain time, returning `{ran, reason?, elapsed?}` so a concurrent drain
   cannot run twice.
3. When a batch does not finish within the time budget, the remaining work is
   continued by a non-blocking self-request to the internal endpoint
   `public/bot/internal-drain.php`, chained until the queue is empty, bounded
   by `BOT_MAX_CHAIN` and the single active drainer. This chain is completed in
   the Outbox module.

`internal-drain.php` is guarded by `X-Bot-Internal-Secret`
(`BOT_INTERNAL_SECRET`, or `?secret=` as a fallback). `HEAD` is used purely as
an access probe and returns `204` on a correct secret / `403` otherwise with no
claim or drain side effects.

### `fastcgi_finish_request` vs flush fallback

To answer Telegram fast and do work afterwards:

- **PHP-FPM / FastCGI:** the entry points call `fastcgi_finish_request()`,
  which sends the response to the client and lets the script keep running.
- **Other SAPIs (Apache mod_php, CLI server, etc.):** there is no
  `fastcgi_finish_request`. The entry points fall back to
  `ignore_user_abort(true)`, flushing the output buffers (`ob_end_flush()` +
  `flush()`) and continuing. This is best-effort — the connection may be tied
  up until the script ends, so keep the drain budget below
  `max_execution_time`. `bin/environment-check.php` reports which path is in
  use.

## Secret rotation

Rotate `BOT_WEBHOOK_SECRET` and/or `BOT_INTERNAL_SECRET` without downtime:

1. Generate a new random secret (e.g. 32+ bytes, hex/base64). Never reuse the
   webhook secret for the internal endpoint.
2. Update `.env` with the new value on the server.
3. Re-register the webhook so Telegram starts sending the new header:
   `php bin/set-webhook.php`.
4. Confirm via `getWebhookInfo` / the admin panel that the URL is unchanged and
   `has_custom_certificate=false`, then send a test message and confirm HTTP
   200 and a fresh `last_webhook_at`.
5. For the internal secret, deploy the new value and re-run the internal
   self-probe (`HEAD` should be `204`). Old in-flight chains stop at the next
   request; there is no long-lived session to invalidate.
6. Delete any local copies of the old secret and confirm the environment check
   prints no secret material.

If a secret is suspected leaked: rotate both secrets, delete and re-set the
webhook, review `storage`/logs for unexpected entries, and check the admin
runtime counters for spikes.

## Tooling

- `php bin/environment-check.php` — prints PHP version/SAPI, whether
  `fastcgi_finish_request` exists, `max_execution_time`, extension presence,
  storage/runtime/log paths with a web-root warning, Telegram `getMe`, the Famo
  API `GET /bot/ping` outcome (`OK` / `TRANSPORT`/`TIMEOUT` / `HTTP 403` /
  `HTTP 401` with hints), and a self-request `HEAD` to the internal drain
  URL. It prints **no** token, key, or secret.
- `php tests/SmokeTest.php` and every `tests/*Test.php` — dependency-free
  checks for config, secret validation, de-duplication, error mapping, storage
  expiry/reset, and the drain lock.

## Manual test checklist (TEST bot token)

Run these against a **TEST** bot token only — never the production bot.

- [ ] `php tests/SmokeTest.php` → `OK`; repeat for each `tests/*Test.php` → all
      `OK`.
- [ ] `php bin/environment-check.php` → prints PHP/SAPI/extensions, paths,
      Telegram `getMe`, API ping outcome (`OK` / `TRANSPORT`/`TIMEOUT` /
      `HTTP 403` / `HTTP 401`), and the self-request `HEAD` result; output
      contains **no** token/key/secret.
- [ ] Set the TEST bot webhook: `php bin/set-webhook.php` prints the new URL;
      Telegram `getWebhookInfo` shows the new URL and
      `has_custom_certificate=false`.
- [ ] Send a normal message to the TEST bot → Telegram receives HTTP 200; the
      runtime panel section shows a new `last_webhook_at`; replaying the same
      update by hand returns 200 without creating a second queue entry.
- [ ] POST the webhook endpoint with a wrong or absent secret header → HTTP 403
      and a `webhook rejected` log line with no message content.
- [ ] POST a malformed body with the correct secret → HTTP 200, no crash,
      `ignored malformed update` logged.
- [ ] `curl -I` the internal drain URL with the correct secret → HTTP 204; with
      a wrong secret → HTTP 403; with `HEAD` → no claim/drain side effects.
- [ ] Open the admin panel → existing tabs still work; the new «اجرای ربات»
      tab renders; no token is exposed in page source; deleting the webhook
      from the panel keeps pending updates.
- [ ] `php bin/rollback-webhook.php` → prints the rollback/delete result;
      `getWebhookInfo` reflects it.

## Module 1 scope reminder

Implemented: config/env, webhook secret + dedupe + fast 200 + shape
validation, Telegram/API cURL clients with `X-Bot-Key` and identity-header
support, central error→Persian map, SQLite storage with expiry/reset
primitives, logging/runtime stats without secrets, outbox-drain lock skeleton,
set/delete/rollback webhook, environment check with distinct API outcomes and
self-request, and the separated panel section.

Deferred to later modules: outbox sending and the chained internal drain
(Outbox module), login, and student/supporter flows.
