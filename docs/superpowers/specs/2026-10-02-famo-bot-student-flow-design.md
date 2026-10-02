# Famo Telegram Bot — Rebuild Design (Foundation + Student Flow)

- **Date:** 2026-10-02
- **Branch:** `agent` (isolated from `dev`)
- **Source spec:** `famo-bot-ux-flow.md`
- **API contract:** `openapi.yaml` (`/api/v1/bot/*`)
- **Status:** approved in chat, pending written-spec review

## 1. Goal

Rebuild the Famo Telegram bot as a thin, stateless client of the Famo API with one
small local SQLite store for **ephemeral conversation state only**. This iteration
delivers the foundation plus the complete **student** flow. Supporter flows,
broadcast and the outbox worker are explicitly deferred (section 14).

Design constraints from the owner:

- One developer maintains it. Keep the codebase small and readable.
- No enterprise abstraction (no DI container, interfaces, factories, DTO layers "just in case").
- But keep Telegram I/O, API calls, view building and state logic separated — no tangled god class.
- No dedicated database; local SQLite only for short-lived state.
- Modern, minimal UI; emoji used only as functional status markers, not decoratively.

## 2. Non-goals

- Supporter flows (inbox, student list/card, reply mode).
- Broadcast flow.
- Outbox worker (`/bot/outbox/claim`, `/bot/outbox/report`) and event delivery
  (supporter replies, broadcast events, notifications).
- Scheduled reminders (the UX spec says the bot sends none).
- Any admin panel or bot-status dashboard (none exists; not in scope).
- Collecting phone numbers. Linking is done site-side via the Telegram Login Widget.

## 3. Architecture

Approach A — **UpdateRouter + Screen registry**.

```
Telegram ──POST──► public/webhook.php
   load .env → Logger::boot → Config
   validate X-Telegram-Bot-Api-Secret-Token (if BOT_WEBHOOK_SECRET set)
   build FamoApi + Famo services + TelegramApi
   Bot::handle() ──► UpdateRouter::route(update)
        ├─ no update_id → return
        ├─ StateStore: dedupe processed_update
        ├─ load ChatState (expire mode/payload after 30 min idle)
        ├─ callback_query       → CallbackRouter → ScreenManager
        ├─ reply-keyboard label → navigation
        ├─ /start /menu /cancel /help → command handler
        └─ message by role/mode → report send
   commit → always HTTP 200
```

The only local decisions are navigation and state. Role, access, week/day status,
unread counts and recipient logic always come from the API.

### 3.1 File layout

```
public/
  webhook.php
src/
  Bot.php
  Config.php
  Support/
    TelegramApi.php
    KeyboardKit.php
    ScreenManager.php
  Router/
    UpdateRouter.php
    CallbackRouter.php
  State/
    StateStore.php
  Screens/
    WelcomeScreen.php
    HomeScreen.php
    DayScreen.php
    WeekScreen.php
    AccountScreen.php
  Famo/
    FamoApi.php
    ApiResult.php
    IdentityService.php
    ThreadService.php
  ErrorMap.php
  Lang.php
  Logger.php
lang/fa.json
tools/
  replay.php
tests/
  ...
docs/superpowers/specs/
```

A "Screen" is a pure builder returning `{text, inline_keyboard}`. It performs no
Telegram I/O and no state mutation. `ScreenManager` renders it.

## 4. State store

`storage/bot.sqlite`, WAL mode, `busy_timeout=5000`.

```sql
CREATE TABLE chat_state (
  chat_id INTEGER PRIMARY KEY,
  telegram_user_id INTEGER,
  role TEXT,                              -- student | supporter | NULL
  mode TEXT NOT NULL DEFAULT 'idle',
  payload TEXT,                           -- JSON (reply target, draft)
  active_screen_message_id INTEGER,
  updated_at INTEGER NOT NULL
);

CREATE TABLE processed_update (
  update_id INTEGER PRIMARY KEY,
  created_at INTEGER NOT NULL
);
```

Rules:

- **Dedupe:** `INSERT OR IGNORE` into `processed_update`; if 0 rows affected, the
  update was already handled → return without side effects.
- **Expiry:** on load, if `now - updated_at > 1800` → `mode='idle'`, `payload=NULL`
  (role and `active_screen_message_id` are kept).
- **Cleanup:** lazily `DELETE FROM processed_update WHERE created_at < now-86400`
  on roughly 1% of requests (random) to avoid a cron.
- **Concurrency:** each webhook wraps dedupe + state read/write in a short
  `BEGIN IMMEDIATE` transaction, so two updates for the same chat serialize.
- **No business data, no message history, no outbox** is stored locally.

### 4.1 Modes

| mode | meaning |
|---|---|
| `unlinked` | no linked account |
| `idle` | normal (student) |
| `replying` | (deferred — supporter) |
| `composing_broadcast` / `confirming_broadcast` | (deferred) |

`/start`, `/menu`, `/cancel`, reply-keyboard taps, or an API "not linked/disabled"
response reset to `idle` or `unlinked`.

## 5. ScreenManager and edit-vs-new rule

`show(Screen $screen, Render $how)` where `Render` is one of:

- `EDIT` — navigation between screens. `editMessageText`; ignore
  `message is not modified`; on "message to edit not found" fall back to a new message.
- `NEW` — events, prompts, results, and any reply from a fresh command; send a new message.

After adopting a new active screen:

- clear the previous active screen's keyboard via `editMessageReplyMarkup` with an
  empty keyboard (errors swallowed),
- persist the new `active_screen_message_id`.

Reply keyboard: set only when the link/role is established or the role changes;
removed on unlink. It is never used for actions.

`KeyboardKit` holds the reply-keyboard label constants and the `callback_data`
constants, so a label or prefix cannot desync between builder and router.

## 6. Famo API client and errors

`FamoApi` (cURL):

- URL: `rtrim(FAMO_API_URL,'/') . '/api/v1' . path`.
- Headers: `X-Bot-Key` (always), plus `X-Bot-Role`, `X-Telegram-User-Id`,
  `X-Telegram-Chat-Id` when the call is identity-scoped.
- Timeout 15s, connect timeout 5s, **no retries**.
- Returns `ApiResult{status, body, errorCode, transportError}` with `ok()` and `data()`.

`ErrorMap` is the single map from documented API codes and transport failures to
Persian text. Unknown codes → generic message. Documented codes only; never guess.

`X-Bot-Key` is never logged. `Logger` keeps the existing Telegram-token redaction.

## 7. Student flow (implemented)

### 7.1 Linking

- `/start` while unlinked → `WelcomeScreen`: text + URL button (`BOT_LOGIN_URL`) +
  `ln:check`.
- On `/start linked` or `ln:check`, call `GET /bot/identity/resolve`. The payload is
  never trusted.
- 0 links → still unlinked, show WelcomeScreen again.
- 1 link → set role + reply keyboard; post a short success message; render Home.
- \>1 links → role chooser (`ac:role:student` / `ac:role:supporter`); supporter
  deferred but the chooser may exist; choosing student proceeds.

### 7.2 Home (S-H)

Text: greeting + supporter name + today's status + optional new-replies line +
one-line explanation that anything sent is today's report.
Buttons: `گفتگوی امروز`, `وضعیت هفته`, `پاسخهای جدید (k)` when k>0, `حساب من`.
No-supporter variant replaces the status with a warning and hides day/week.

### 7.3 Report sending (idle student)

Any text/photo/document/voice/video/audio/video_note → `POST /bot/threads/messages`
with `MessageInput`. On success → 👍 reaction on the user's message; no text reply
(except the first message of the day, which gets one short confirmation). Albums:
**for this iteration each album part is sent as its own API call and gets its own
reaction** (Telegram delivers album parts as independent updates and there is no
completion marker; coalescing needs a scheduler, which is deferred). Coalescing by
`media_group_id` is a follow-up once the worker exists. Unsupported types
(sticker/location/poll/…) get a short "not supported" reply.

### 7.4 Day view (S-T / S-D)

`GET /bot/threads/day?day=&page=`. One message renders the newest page of up to 10
messages with sender labels (`شما`, supporter name, `پیام همگانی`), file lines as
summaries, and a footer page indicator. Then `POST /bot/threads/read`. Navigation:
older/newer page, refresh, back. Files are delivered as separate new messages via
file_id on demand (`st:f:{day}:{page}`), max 10.

### 7.5 Week view (S-W)

`GET /bot/threads/weekly?week_start=`. Seven day buttons (2 columns + last row),
each showing state marker (✅ sent / ❌ missed / ⏳ pending) and reply marker
(💬 / 🔵 unread). Future days are `nop`. Prev/next week, home. Clicking a day →
DayScreen.

### 7.6 New replies (S-N)

Shortcut to the newest day with unread replies (derived from weekly data if the API
has no dedicated list). Hidden when k=0.

### 7.7 Account (S-A)

Shows name/role/supporter. Buttons: switch role (only if multiple links), link
another account (URL), unlink (confirm screen), back. Unlink → API
`/bot/identity/unlink`, remove reply keyboard, go to WelcomeScreen.

### 7.8 Global commands and unexpected input

| input | behavior |
|---|---|
| `/start` | reset state; linked → Home (new message); unlinked → Welcome |
| `/menu` | same as `/start` for linked users |
| `/cancel` | exit temporary modes → Home |
| `/help` | short role-aware help, home button only |
| reply-keyboard label | exact trimmed match; navigation; delete the user's button message if possible |
| unlinked → anything | "connect first" + `ln:check` |
| supporter idle → free text | (deferred) |
| `edited_message`, groups/channels | ignored (private chat only) |

### 7.9 callback_data catalog (student subset)

| callback | meaning |
|---|---|
| `nop` | display-only button; silent answer |
| `h` | home (edit) |
| `ln:check` | re-check linking |
| `ac`, `ac:role:{role}`, `ac:switch`, `ac:unlink`, `ac:unlink:ok`, `ac:home` | account |
| `st:t` | today's thread |
| `st:w:{weekStart}` | week view |
| `st:d:{day}:{page}` | a day |
| `st:f:{day}:{page}` | fetch files |
| `st:n` | new replies |

All ids are references only; the API re-checks authorization every call.

## 8. Reply keyboard

Student, persistent, resized:

```
[ گفتگوی امروز ] [ وضعیت هفته ]
[        منوی اصلی        ]
```

Unlinked users get `ReplyKeyboardRemove`. Labels are matched exactly (trimmed)
before any other message handling, so they are never stored as reports.

## 9. Persian texts

`lang/fa.json` is the single source. Emoji are limited to functional status markers
(✅ ❌ ⏳ 🔵 💬) and a small set of icons (📎 for files, ⚠️ for warnings). Titles,
greetings and buttons carry no decorative emoji. Exact strings are finalized during
implementation from `famo-bot-ux-flow.md` with decorative emoji removed.

## 10. Configuration

`.env` keys read by the code:

| key | required | note |
|---|---|---|
| `TELEGRAM_BOT_TOKEN` | yes | SDK token |
| `FAMO_API_URL` | yes | base without `/api/v1` |
| `FAMO_API_TOKEN` | yes | sent as `X-Bot-Key` |
| `BOT_LOGIN_URL` | yes | site login page with Telegram widget |
| `BOT_WEBHOOK_SECRET` | no | if set, validate `X-Telegram-Bot-Api-Secret-Token` |
| `BOT_STORAGE_DIR` | no | default `<root>/storage` |
| `BOT_LOG_FILE` | no | default `<root>/storage/logs/bot.log` |

Removed from the code and `.env.example`: `JWT_SECRET`, `BOT_RUNTIME_FILE`,
`BOT_DRAIN_*`, `BOT_ROLLBACK_WEBHOOK_URL`, `BOT_MAX_CHAIN`, and the multi-name
aliases (`API_BASE_URL`, `FAMO_API_BASE_URL`, `BOT_SERVICE_KEY`,
`FAMO_SERVICE_KEY`). `.env.example` is updated to match and to include
`BOT_LOGIN_URL`.

## 11. Deletions

| Path | Reason |
|---|---|
| `src/Bootstrap.php` | legacy bootstrapper, only used by the dead drain endpoint |
| `src/WebhookHandler.php` | legacy secret validator, never called |
| `src/Outbox/OutboxDrainer.php` | legacy flock drain; outbox is deferred |
| `public/bot/internal-drain.php` | legacy endpoint, not in active path |
| `src/Logging/RuntimeLogger.php` | unused |
| `src/Errors/InvalidArgumentException.php` | empty stub |
| `src/Services/JwtService.php` | called but unused (linking is site-side) |
| `src/Helpers/PhoneNormalizer.php` | no phone collection in the new flow |
| `src/Commands/ReportCommand.php` | `/report` is not a command in the spec |
| `src/Commands/StartCommand.php`, `src/Commands/HelpCommand.php` | replaced by Router + Screens |
| `src/Handlers/CallBackHandler.php` | replaced by CallbackRouter |
| `src/Services/MessageService.php`, `src/Services/IdentityService.php` | replaced by `Lang` / `Famo\IdentityService` |
| `storage/bot.sqlite` | empty/unreferenced; recreated with new schema |

Kept: `src/Config.php`, `src/Logging/Logger.php`, `lang/`, `openapi.yaml`,
`README.md` (rewritten later), `.env`, `CheatSheet.md`, swagger helper files,
`node_modules/`.

## 12. Tooling and tests

- `composer lint` — `php -l` sweep over `src/`, `public/`, `tools/`.
- `composer test` — PHPUnit for pure units only, no network:
  callback_data parsing, week/day grouping, keyboard shape/length limits,
  error map, state expiry, reply-keyboard label detection.
- `tools/replay.php` — feed a sample update through `UpdateRouter` using the SDK's
  fake Guzzle handler, for local screen debugging without Telegram.

## 13. Acceptance (student subset)

- `/start` unlinked → Welcome, no reply keyboard; link button opens site; return
  resolves and shows Home with reply keyboard.
- Forged `/start linked` from an unlinked user does not link.
- Any text/photo/file/voice in idle → API call + 👍, no text reply except first of day.
- Album of 3 photos → 3 API calls, 3 reactions (coalescing deferred; see §7.3).
- Reply-keyboard "منوی اصلی" is never stored as a report.
- Week: future days never ❌; day click edits; back edits.
- Viewing a day marks supporter messages read; 🔵 → 💬 next render.
- All callbacks are answered; no spinner is left.
- No user text is rendered into bot messages without HTML escaping.
- Every `callback_data` ≤ 64 bytes.

## 14. Deferred / open items

- Supporter flows, broadcast, and the outbox worker (`claim`/`report`) with event
  delivery. The router and services leave a seam for a worker invoked later.
- Exact `BOT_LOGIN_URL` value.
- Whether the backend's Telegram-widget linking endpoints (`/auth/telegram/verify`
  etc., UX spec §12.1) exist yet. The bot only needs `resolve`, so this does not
  block the student flow.
- Album coalescing by `media_group_id` (needs a scheduler/buffer; deferred, see §7.3).
