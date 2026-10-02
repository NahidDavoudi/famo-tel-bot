# Famo Telegram Bot — Rebuild Design (Iteration 1: Foundation + Student Flow)

- **Date:** 2026-10-02
- **Branch:** `agent` (isolated from `dev`)
- **Source UX:** `famo-bot-ux-flow.md`
- **API contract:** `openapi.yaml` (`/api/v1/bot/*`)
- **Status:** approved in chat; structural revisions folded in (rev 2)

## 1. Goal

Rebuild the Famo Telegram bot as a thin client of the Famo API with one small local
SQLite store for **ephemeral conversation state only**. Iteration 1 delivers the
foundation plus the complete **student** flow.

Owner constraints:

- One developer maintains it. Small and readable.
- No enterprise abstraction (no DI container, interfaces, factories, DTO layers "just in case").
- Telegram I/O, API calls, view building and state logic stay separated — no god class.
- No dedicated database; local SQLite only for short-lived state.
- Modern, minimal UI; emoji only as functional status markers, never decorative.
- The UX document's labels/buttons must match one source of truth: `lang/fa.json` + `KeyboardKit`.

## 2. Non-goals (Iteration 1)

- Supporter flows (inbox, student list/card, reply mode).
- Broadcast flow.
- Outbox worker (`/bot/outbox/claim`, `/bot/outbox/report`) and event delivery.
- Scheduled reminders (the UX spec sends none).
- Admin panel: **removed for good**; it was dead code and is not needed. No admin
  CLI/panel is introduced.
- Collecting phone numbers. Linking is site-side via the Telegram Login Widget.

> Iteration 2 (mandatory next) is **outbox + supporter**, because the
> "student sends → supporter replies" loop is incomplete without it.

## 3. Architecture

**Layered: Router detects and routes only; Handlers orchestrate API + screens;
Screens build text/keyboard; Support classes do I/O.**

```
Telegram ──POST──► public/webhook.php
   load .env → Logger::boot → Config
   validate X-Telegram-Bot-Api-Secret-Token (if BOT_WEBHOOK_SECRET set)
   build FamoApi, TelegramApi, StateStore, ScreenManager, Router
   Router::route(update)
        ├─ no update_id → return
        ├─ StateStore dedupe
        ├─ load ChatState (expire mode/payload after 30 min idle)
        ├─ callback_query       → parse prefix → Handler method
        ├─ reply-keyboard label → Handler (navigation)
        ├─ slash command        → Handler (link/student/account)
        └─ message by role/mode → Handler (report)
   commit → always HTTP 200
```

Responsibilities:

- **Router** — the ONLY place that inspects an update. It dedupes, loads state,
  detects the update kind and dispatches to a handler. It does **not** call the Famo
  API and does **not** build screens.
- **Handlers** (one per domain: `Link`, `Student`, `Account`; later `Supporter`,
  `Broadcast`) — fetch data from the API, build a `Screen`, and show it via
  `ScreenManager`. One handler per concern keeps the future supporter/broadcast
  work from turning Router into a god class.
- **Screens** — pure `text + keyboard` builders. No I/O, no state mutation.
- **Support** — `Telegram/` (client, ScreenManager, keyboard), `Famo/` (API client,
  ErrorMap), `State/`.

`Bot.php` is deleted; `webhook.php` calls `Router` directly.

### 3.1 File layout

```
public/
  webhook.php
src/
  Router.php
  Config.php
  Lang.php
  RawHtml.php
  Logger.php
  Telegram/
    TelegramApi.php
    ScreenManager.php
    Screen.php
    KeyboardKit.php
  Famo/
    FamoApi.php
    ApiResult.php
    ErrorMap.php
  State/
    StateStore.php
    ChatState.php
  Handlers/
    LinkHandler.php
    StudentHandler.php
    AccountHandler.php
  Screens/
    WelcomeScreen.php
    HomeScreen.php
    DayScreen.php
    WeekScreen.php
    AccountScreen.php
lang/fa.json
tools/replay.php
tests/
```

No `Support/` catch-all folder. `IdentityService`/`ThreadService` are **not**
separate classes: they are methods on one `FamoApi`. Split `FamoApi` only if it
exceeds ~400 lines.

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

- **Dedupe:** `INSERT OR IGNORE`; 0 rows affected → already handled → return.
- **Expiry:** on load, `now - updated_at > 1800` → `mode='idle'`, `payload=NULL`
  (keep `role` and `active_screen_message_id`).
- **Cleanup:** probabilistic (~1/100 requests) `DELETE FROM processed_update WHERE created_at < now-86400`.
- **Concurrency:** dedupe + state read/write in a short `BEGIN IMMEDIATE` transaction.
- **No business data, no message history, no outbox** stored locally.

Modes: `unlinked`, `idle`, and (deferred) `replying`, `composing_broadcast`,
`confirming_broadcast`.

## 5. Roles and identity

- Role values are **exactly** the `openapi.yaml` enum: `student` | `supporter`
  (also the `X-Bot-Role` header values). No guessing, no `mentor`, no typos.
- Every identity-scoped API call sends `X-Bot-Key` + `X-Bot-Role` +
  `X-Telegram-User-Id` + `X-Telegram-Chat-Id`. The API returns
  "not linked / disabled" on its own; the bot does **not** call `/bot/me` on every
  interaction — only during `/start` (resolve).

## 6. Error handling

`ErrorMap` is the single map from documented API codes and transport failures to
Persian text. Unknown codes → generic. Documented codes only.

Handler rule: when an identity call returns "unlinked" or "disabled",
`ErrorMap` classifies it and the handler **resets chat state** (to `unlinked`,
removing the reply keyboard) before showing the appropriate screen. This prevents
stale links from lingering.

## 7. Lang and escaping

`Lang::t(string $key, array $params = []): string` loads `lang/fa.json`.

- **Every param is HTML-escaped by default** (`htmlspecialchars`, `ENT_QUOTES`, UTF-8).
- Raw HTML is only possible by passing a `RawHtml` value object explicitly:
  `Lang::t('x', ['name' => new RawHtml($trusted)])`.
- This makes forgetting to escape impossible for normal values.

The single source of truth for labels/buttons is `lang/fa.json` + `KeyboardKit`.
The UX document is aligned to this rule (decorative emoji removed; functional status
markers kept: ✅ ❌ ⏳ 🔵 💬, plus 📎 and ⚠️).

## 8. ScreenManager and edit-vs-new

`Screen` = `{text, keyboard}` (keyboard is a Telegram `inline_keyboard` array or null).

`ScreenManager`:

- `show(chatId, Screen, bool $edit)`: `$edit=true` → `editMessageText`
  (ignore `message is not modified`; on "not found" fall back to new message);
  `$edit=false` → send new.
- After adopting a new active screen, clear the previous one's keyboard
  (`editMessageReplyMarkup` with empty), errors swallowed.
- Persist `active_screen_message_id`.
- Reply keyboard is set only on link/role change; removed on unlink.

## 9. Student flow (Iteration 1)

- **Linking:** `/start` unlinked → `WelcomeScreen` with URL button (`BOT_LOGIN_URL`)
  and `ln:check`. On `/start linked` or `ln:check`, `FamoApi::resolve..`;
  payload never trusted. 0 links → Welcome again; 1 link → set role + reply keyboard,
  short success, Home; >1 → role chooser (`ac:role:student`, supporter deferred).
- **Home (S-H):** greeting, supporter name, today's status, new-replies line,
  one-line explanation. Buttons: `گفتگوی امروز`, `وضعیت هفته`, `پاسخهای جدید (k)`,
  `حساب من`. No-supporter variant shows a warning and hides day/week.
- **Report sending (idle):** any text/photo/document/voice/video/audio/video_note →
  `POST /bot/threads/messages`; on success 👍 reaction; no text reply.
  **Album parts are sent separately this iteration.** The "first message today" text
  confirmation is **omitted** because the API does not return a `first_today` flag;
  it will be requested from the backend. (Per-part is acceptable only when that flag
  exists; otherwise 3 simultaneous photos would give 3 confirmations.)
  Unsupported types get a short "not supported" reply.
  (Follow-up, no scheduler needed: buffer album parts in a temp SQLite table, wait
  ~1.2 s, and whichever request grabs the lock sends them in one API call.)
- **Day view (S-T/S-D):** `GET /bot/threads/day`; one message, newest page of ≤10
  messages, sender labels, file lines as summaries, page indicator; then
  `POST /bot/threads/read`. Older/newer/refresh/back; files on demand (`st:f:`).
- **Week view (S-W):** `GET /bot/threads/weekly`; 7 day buttons with status
  (✅/❌/⏳) and reply (💬/🔵) markers; future days `nop`; prev/next week.
- **New replies (S-N):** shortcut to newest day with unread replies; hidden when 0.
- **Account (S-A):** name/role/supporter; switch role (if multi-link), link another
  (URL), unlink (confirm) → API unlink, remove reply keyboard, Welcome.
- **Global:** `/start`, `/menu`, `/cancel`, `/help`; reply-keyboard label exact
  match before any report handling; unlinked → Welcome; `edited_message`/groups ignored.

### 9.1 callback_data catalog (student subset)

`nop`, `h`, `ln:check`, `ac`, `ac:role:{role}`, `ac:switch`, `ac:unlink`,
`ac:unlink:ok`, `ac:home`, `st:t`, `st:w:{weekStart}`, `st:d:{day}:{page}`,
`st:f:{day}:{page}`, `st:n`. Format `scope:action[:params]`, ≤64 bytes ASCII.
Handlers re-check authorization via the API; ids are references only.

## 10. Reply keyboard

Student, persistent, resized:

```
[ گفتگوی امروز ] [ وضعیت هفته ]
[        منوی اصلی        ]
```

Unlinked → `ReplyKeyboardRemove`. Labels matched exactly (trimmed) before message
handling, so they are never stored as reports.

## 11. Configuration

`.env` keys read by the code:

| key | required | note |
|---|---|---|
| `TELEGRAM_BOT_TOKEN` | yes | SDK token |
| `FAMO_API_URL` | yes | base without `/api/v1` |
| `BOT_SERVICE_KEY` | yes | sent as `X-Bot-Key` (same name the backend uses) |
| `BOT_LOGIN_URL` | yes | site login page with Telegram widget |
| `BOT_WEBHOOK_SECRET` | no | if set, validate `X-Telegram-Bot-Api-Secret-Token` |
| `BOT_STORAGE_DIR` | no | default `<root>/storage` |
| `BOT_LOG_FILE` | no | default `<root>/storage/logs/bot.log` |

`FAMO_API_TOKEN` is renamed to `BOT_SERVICE_KEY` everywhere (bot + `.env.example`) so
both sides use one name. Removed: `JWT_SECRET`, `BOT_RUNTIME_FILE`, `BOT_DRAIN_*`,
`BOT_ROLLBACK_WEBHOOK_URL`, `BOT_MAX_CHAIN`, and multi-name aliases.

## 12. Deletions

`src/Bootstrap.php`, `src/WebhookHandler.php`, `src/Outbox/OutboxDrainer.php`,
`public/bot/internal-drain.php`, `src/Logging/RuntimeLogger.php`,
`src/Errors/InvalidArgumentException.php`, `src/Services/JwtService.php`,
`src/Helpers/PhoneNormalizer.php`, `src/Commands/ReportCommand.php`,
`src/Commands/StartCommand.php`, `src/Commands/HelpCommand.php`,
`src/Handlers/CallBackHandler.php`, `src/Services/MessageService.php`,
`src/Services/IdentityService.php`, `storage/bot.sqlite`.
Drain code deletion is fine (isolated branch; will be rebuilt smaller later).

Kept: `src/Config.php`, `src/Logging/Logger.php`, `lang/`, `openapi.yaml`,
`README.md` (rewritten later), `.env`, `CheatSheet.md`, swagger helper files.

## 13. Tooling and tests

- `composer lint` — `php -l` sweep over `src/`, `public/`, `tools/`.
- `composer test` — PHPUnit, pure units only (no network): callback parsing,
  week/day mapping, keyboard shape/limits, error map, state expiry, label detection.
- `tools/replay.php` — feed a sample update through `Router` with the SDK's fake
  Guzzle handler for local screen debugging.

## 14. Acceptance (Iteration 1)

- `/start` unlinked → Welcome, no reply keyboard; link button opens site; return
  resolves and shows Home with reply keyboard.
- Forged `/start linked` does not link.
- Any text/photo/file/voice idle → API call + 👍, no text reply.
- Reply-keyboard "منوی اصلی" is never stored as a report.
- Week: future days never ❌; day click edits; back edits.
- Viewing a day marks supporter messages read; 🔵 → 💬 next render.
- All callbacks answered; no spinner left.
- No user text rendered into bot messages without escaping (enforced by `Lang`).
- Every `callback_data` ≤ 64 bytes.
- Role value sent/compared is exactly `student` or `supporter`.

## 15. Open items

- Supporter + outbox (iteration 2).
- Exact `BOT_LOGIN_URL` value.
- Backend login-widget endpoints (`/auth/telegram/*`, UX §12.1): bot only needs
  `resolve`, so not blocking.
- Backend `first_today` flag on `POST /bot/threads/messages` (to restore the
  first-of-day confirmation).
- Album coalescing via temp-table + ~1.2 s lock (follow-up).
