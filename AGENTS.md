# Famo Bot — Agent Guide

## Project structure

```
.
├── index.php           # Admin panel (single-file app: login/2FA/dashboard)
├── admin/functions.php # Legacy helpers: initBot, sendTelegram, getBotInfo, webhook CRUD
├── admin/data/         # JSON runtime/logs/bot-status (gitignored inside storage/)
├── public/
│   ├── webhook.php     # Legacy webhook (uses irazasyed/telegram-bot-sdk) **removed** 
│   └── bot/
│       ├── webhook.php       # New BotHost webhook (secret-validated, dedup, flush-then-drain)
│       └── internal-drain.php# Internal drain trigger (guarded by BOT_INTERNAL_SECRET)
├── src/
│   ├── Bot.php         # Legacy handler (checks admin/data/bot-status.json) **removed** in BotHost
│   └── BotHost/        # New Module 1 foundation (namespaced under BotHost\)
├── bin/
│   ├── set-webhook.php
│   ├── delete-webhook.php
│   ├── rollback-webhook.php
│   └── environment-check.php
├── tests/              # Plain PHP scripts (NO phpunit): exit 0 = OK, exit 1 = FAIL
├── storage/            # SQLite + drain.lock (.htaccess Deny from all)
├── composer.json       # Only 2 packages: irazasyed/telegram-bot-sdk, vlucas/phpdotenv
└── .env.example
```

## Key facts

- **No framework.** Plain PHP 8.2+, PSR-4 autoload under `BotHost\` → `src/BotHost/`.
- **Two bot handler systems coexist.** Legacy (`src/Bot.php` + `public/webhook.php`) and new BotHost (`src/BotHost/*` + `public/bot/webhook.php`). BotHost is the active development target.
- **Admin panel is Persian/RTL** (`dir="rtl" lang="fa"`). All UI text is in Farsi.
- **Tests are raw PHP scripts** — no test runner. `php tests/SmokeTest.php` runs each file independently.
- **Secrets must never be logged, rendered, or exposed.** Runtime stats explicitly exclude tokens/keys/secrets.

## Commands

```bash
# Tests (exit 0 = OK, exit 1 = FAIL)
php tests/SmokeTest.php

# Webhook management
php bin/set-webhook.php
php bin/delete-webhook.php
php bin/rollback-webhook.php

# Environment check (prints no secrets)
php bin/environment-check.php
```

## Conventions

- `fastcgi_finish_request()` for post-response processing; falls back to `ob_end_flush() + flush()` on non-FastCGI SAPIs.
- `hash_equals()` for all secret comparisons.
- SQLite in WAL mode via `LocalStore`.
- Atomic JSON writes with `.lock`/`.tmp` helper files in `RuntimeStats` and `RuntimeLogger`.
- Drain uses file-level `flock(LOCK_EX|LOCK_NB)` for cross-process mutual exclusion.
- Env vars loaded via `Dotenv::createImmutable()->load()` in legacy; `Bootstrap::create()` handles it for BotHost.
- `TELEGRAM_API_URL` optional (defaults to `https://api.telegram.org`) for custom Bot API server.

## Gotchas

- `composer.json` has **no dev dependencies** (no phpunit, no code style tool).
- Webhook secret (`BOT_WEBHOOK_SECRET`) and internal secret (`BOT_INTERNAL_SECRET`) must be **different** values.
- `storage/.htaccess` contains `Deny from all` — do not remove.
- New `.env` keys must be added to `.env.example`.