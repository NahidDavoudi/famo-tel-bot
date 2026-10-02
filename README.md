# ربات تلگرام فامو

لایهٔ نازکی بین **تلگرام** و **API فامو** (`api.famoacademy.ir`). ربات هیچ دیتابیس
کسب‌وکاری ندارد؛ فقط یک SQLite محلی برای **state موقت گفتگو** نگه می‌دارد. هر تصمیم،
دسترسی و داده از API فامو می‌آید.

- PHP 8.2+ (روی سرور 8.4)
- بدون فریم‌ورک؛ پکیج‌ها: `irazasyed/telegram-bot-sdk`, `vlucas/phpdotenv`, `monolog/monolog`
- شاخهٔ توسعه: `agent` (نسخهٔ جدید)، شاخهٔ قبلی `dev` دست‌نخورده می‌ماند
- مرجع UX: `famo-bot-ux-flow.md` — مرجع متن‌ها/دکمه‌ها: `lang/fa.json` + `KeyboardKit`

## معماری

```
Telegram ──POST──► public/webhook.php
   .env → Logger/Lang → Config
   اعتبارسنجی X-Telegram-Bot-Api-Secret-Token (اگر BOT_WEBHOOK_SECRET تنظیم باشد)
   ساخت FamoApi, TelegramApi, StateStore, ScreenManager, Handlerها
   Router::route(update)
        ├─ dedupe (processed_update)
        ├─ بارگذاری ChatState (انقضای ۳۰ دقیقه‌ای mode/payload)
        ├─ callback_query  → CallbackRouter (prefix) → Handler
        ├─ دکمهٔ Reply     → Handler
        ├─ دستور /...      → Handler
        └─ پیام بر اساس role/mode → Handler
   ذخیرهٔ state → همیشه HTTP 200
```

لایه‌ها:

- **Router** فقط update را تشخیص می‌دهد و به handler می‌رساند؛ نه API صدا می‌زند نه صفحه می‌سازد.
- **Handlers** (`Link`, `Student`, `Account`؛ بعداً `Supporter`, `Broadcast`) داده را از API
  می‌گیرند، `Screen` می‌سازند و با `ScreenManager` نمایش می‌دهند.
- **Screens** فقط متن و کیبورد می‌سازند؛ بدون I/O.
- **Famo** کلاینت API و ErrorMap؛ **State** ذخیره‌سازی موقت؛ **Telegram** کلاینت/ScreenManager/کیبورد.

### ساختار پوشه‌ها

```
public/webhook.php                 # نقطهٔ ورود
src/
  Router.php  Config.php  Lang.php  RawHtml.php  Num.php  Logger.php
  Telegram/  TelegramApi.php  ScreenManager.php  Screen.php  KeyboardKit.php  UpdateContext.php
  Famo/      FamoApi.php  ApiResult.php  ErrorMap.php
  State/     StateStore.php  ChatState.php
  Handlers/  LinkHandler.php  StudentHandler.php  AccountHandler.php
  Screens/   WelcomeScreen.php  HomeScreen.php  DayScreen.php  WeekScreen.php  AccountScreen.php  HelpScreen.php
lang/fa.json
tools/lint.php  tools/replay.php
tests/run.php
```

## راه‌اندازی

```bash
composer install
cp .env.example .env
```

### متغیرهای محیطی

| کلید | لازم؟ | توضیح |
| --- | --- | --- |
| `TELEGRAM_BOT_TOKEN` | بله | توکن ربات از BotFather |
| `FAMO_API_URL` | بله | بیس API فامو بدون `/api/v1` |
| `BOT_SERVICE_KEY` | بله | همان `BOT_SERVICE_KEY` سرور API؛ به‌عنوان `X-Bot-Key` فرستاده می‌شود |
| `BOT_LOGIN_URL` | بله | صفحهٔ ورود سایت فامو (ویجت ورود تلگرام) |
| `BOT_WEBHOOK_SECRET` | خیر | اگر تنظیم شود، هدر secret اعتبارسنجی می‌شود |
| `BOT_STORAGE_DIR` | خیر | پیش‌فرض `<root>/storage` |
| `BOT_LOG_FILE` | خیر | پیش‌فرض `<root>/storage/logs/bot.log` |

> نام قبلی `FAMO_API_TOKEN` به `BOT_SERVICE_KEY` تغییر کرده تا در ربات و بک‌اند یک نام باشد.

## جریان دانشجو (نسخهٔ ۱)

- **اتصال:** `/start` بدون اتصال ← صفحهٔ خوش‌آمد با دکمهٔ URL سایت + «وارد شدم، ادامه».
  ربات فقط `resolve` را صدا می‌زند و به payload اعتماد نمی‌کند.
- **منوی اصلی:** نام، پشتیبان، وضعیت امروز، پاسخ‌های خوانده‌نشده.
- **ارسال گزارش:** هر متن/عکس/فایل/ویس/ویدیو در حالت idle ← ثبت در API + ری‌اکشن 👍 (بدون پیام متنی).
- **گفتگوی امروز / یک روز (S-T/S-D):** صفحهٔ ۱۰ پیام آخر؛ سپس mark read.
- **وضعیت هفته (S-W):** ۷ روز با نشانگر ✅/❌/⏳ و 💬/🔵؛ روزهای آینده غیرفعال.
- **پاسخ‌های جدید (S-N)** و **حساب من (S-A)** (تغییر نقش، اتصال دیگر، قطع اتصال).
- دستورات: `/start`, `/menu`, `/cancel`, `/help`. دکمه‌های Reply فقط ناوبری‌اند.

### callback_data

`nop`, `h`, `ln:check`, `ac`, `ac:role:{role}`, `ac:switch`, `ac:unlink`,
`ac:unlink:ok`, `ac:home`, `st:t`, `st:w[:{weekStart}]`, `st:d:{day}:{page}`,
`st:f:{day}:{page}`, `st:n`. قالب `scope:action[:params]`، حداکثر ۶۴ بایت.

## State

`storage/bot.sqlite` (WAL): جدول `chat_state` (role, mode, payload, active_screen_message_id,
updated_at) و `processed_update` (dedupe). انقضای ۳۰ دقیقه‌ای state؛ پاک‌سازی روزانهٔ
`processed_update`. هیچ دادهٔ کسب‌وکاری محلی ذخیره نمی‌شود.

## تست و ابزار

```bash
composer lint    # php -l روی src/public/tools/tests
composer test    # تست‌های خالص (بدون شبکه)
php tools/replay.php   # نمایش صفحه‌ها با دادهٔ نمونه
```

## ثبت وب‌هوک

```
https://<host>/public/webhook.php
```
اگر `BOT_WEBHOOK_SECRET` تنظیم شود، همان مقدار باید هنگام `setWebhook` به‌عنوان
`secret_token` ثبت شود.

## وضعیت و کارهای بعدی

فاز ۲: **outbox + پشتیبان** (صندوق ورودی، لیست دانشجوها، پاسخ‌دهی، پیام همگانی)
تا چرخهٔ «دانشجو می‌فرستد → پشتیبان جواب می‌دهد» کامل شود. تا آن زمان، eventها و
پاسخ پشتیبان به دانشجو ارسال نمی‌شود.

پنل ادمین و کدهای drain قدیمی حذف شده‌اند و در صورت نیاز از نو و کوچک‌تر ساخته می‌شوند.
