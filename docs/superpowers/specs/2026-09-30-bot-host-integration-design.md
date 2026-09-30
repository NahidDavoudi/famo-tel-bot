# طراحی میزبان ربات تلگرام فامو و ادغام با پنل ادمین

## هدف

ساخت میزبانی که تنها واسط میان تلگرام و API فامو است (لایهٔ نازک): رابط فارسی
را رندر می‌کند، وضعیت موقت گفتگو را نگه می‌دارد و برای هر تصمیم، دسترسی و داده از
API می‌پرسد. این سند معماری کل ربات و به‌طور خاص ادغام امن webhook و پنل ادمین
فعلی را تعریف می‌کند، سپس پیاده‌سازی از **Module 1 (Foundation)** شروع می‌شود.

## قرارداد مرجع

- فایل `openapi.yaml` تنها مرجع endpointها، فیلدها، enumها، هدرها و کدهای خطا است.
- هیچ endpoint، فیلد یا کد خطایی حدس زده نمی‌شود. هر کمبود به‌عنوان «API request» ثبت
  می‌شود و کوچک‌ترین راه‌حل موقت انتخاب می‌گردد.
- API تغییر نمی‌کند.

## محدودیت‌های سراسری (Global Constraints)

- هدف PHP **8.2+**؛ حداقل موردنیاز **8.2**. ذخیره‌سازی پیش‌فرض SQLite با `pdo_sqlite`.
- cURL مستقیم برای Telegram و API؛ `phpdotenv` مجاز است؛ **ممنوعیت استفاده از
  `irazasyed/telegram-bot-sdk` در کد جدید**. پنل و فایل‌های legacy دست‌نخورده می‌مانند
  تا وابستگی موجود نشکند.
- **بدون cron** و بدون وابستگی به scheduler خارجی.
- رابط فارسی، تاریخ شمسی با رشته‌های آماده‌ی API. متن‌های بلندتر از ۴۰۹۶ کاراکتر
  هنگام ارسال به تلگرام تکه‌تکه می‌شوند. متن کاربر escape می‌شود یا plain text فرستاده می‌شود.
- secrets فقط در env خارج از web root و هرگز لاگ نمی‌شوند.
- فقط **TEST bot token** در توسعه/آزمون؛ هرگز توکن ربات تولیدی.
- callback_data مبهم، کوتاه (≤64 بایت) و غیرقابل‌اعتماد؛ API هر عمل را دوباره مجازسنجی می‌کند.

## معماری کلان

```
Telegram --(webhook, secret_token)--> public/bot/webhook.php
                                        |  (1) اعتبارسنجی secret + shape
                                        |  (2) ثبت update_id (dedupe) در SQLite
                                        |  (3) پاسخ 200 سریع
                                        v
                                   پردازش update + drain
                                        |
                                        v
                              src/BotHost/*  (هستهٔ ربات)
                                   |            |
                                   v            v
                            X-Bot-Key API   Telegram Bot API (cURL)
                                   |
                                   v
                       api.famoacademy.ir  (منبع حقیقت)

public/bot/internal-drain.php  <-- self-request زنجیره‌ای (secret جدا)
پنل ادمین (index.php/admin)  <-- خواندن خلاصهٔ runtime از storage محلی
```

## کامپوننت‌ها (Module 1)

- `src/BotHost/Config.php` — بارگذاری و اعتبارسنجی env؛ مسیر storage از env.
- `src/BotHost/Api/FamoApiClient.php` — cURL به API؛ هدر `X-Bot-Key` و در صورت نیاز
  `X-Bot-Role`/`X-Telegram-User-Id`/`X-Telegram-Chat-Id`؛ timeout و ثبت نتیجه بدون secrets.
- `src/BotHost/Telegram/TelegramClient.php` — cURL به Telegram Bot API (`getMe`, `setWebhook`, `deleteWebhook`, بعداً `send*`).
- `src/BotHost/Errors/ApiErrorMessages.php` — نگاشت مرکزی کدهای مستند به پیام فارسی + fallback عمومی.
- `src/BotHost/Storage/LocalStore.php` — SQLite خارج از web root: `update_id`های پردازش‌شده، صف updateهای ورودی و state موقت با expiry.
- `src/BotHost/Storage/RuntimeStats.php` — ثبت اتمیک خلاصهٔ runtime و لاگ‌های سازگار با پنل.
- `src/BotHost/Logging/RuntimeLogger.php` — لاگ ساخت‌یافتهٔ بدون محتوا/secret.
- `src/BotHost/Outbox/OutboxDrainer.php` — skeleton با یک lock مشترک و بودجهٔ اجرا؛ در Module 1 بدون claim/ارسال.
- `public/bot/webhook.php` — endpoint جدید نسخه‌دار.
- `public/bot/internal-drain.php` — endpoint داخلی محافظت‌شده؛ `HEAD` فقط آزمون دسترسی.
- `bin/set-webhook.php`, `bin/delete-webhook.php`, `bin/rollback-webhook.php`.
- `bin/environment-check.php` — گزارش محیط روی میزبان واقعی، بدون چاپ secrets.

## جریان داده

### ورودی webhook

1. فقط اگر هدر `X-Telegram-Bot-Api-Secret-Token` دقیقاً برابر secret باشد پذیرفته می‌شود؛ در غیر این صورت رد و لاگ بدون محتوا.
2. shape به‌صورت دفاعی اعتبارسنجی می‌شود.
3. اگر `update_id` تکراری باشد، بی‌اثر پاسخ 200 داده می‌شود.
4. update در صف محلی SQLite ثبت و `update_id` یکتا علامت‌گذاری می‌شود، سپس پاسخ 200 سریع داده می‌شود (`fastcgi_finish_request()` در صورت وجود؛ در غیر این صورت flush + `ignore_user_abort` و مستندسازی محدودیت).
5. پردازش update و سپس drain کوتاه؛ در شروع webhook بعدی اگر آیتمی در انتظار باشد، یک batch دیگر تخلیه می‌شود (برای retry).
6. هر خطای داخلی لاگ می‌شود ولی پاسخ به Telegram همیشه سریع و بدون retry بی‌پایان است.

### خروجی (Outbox، بدون cron)

- یک lock محلی مشترک + پروتکل claim در API، تضمین می‌کند چیزی دو بار ارسال نشود.
- پردازش دسته‌ای کوچک؛ ارسال با cURL؛ سپس `report` نتیجه (`sent` با telegram_message_id / `failed` با دلیل / `blocked`).
- هرگز پیش از تأیید Telegram موفق علامت زده نمی‌شود.
- محدودیت‌ها: زیر ~20-25 پیام/ثانیه کلی و ~1 پیام/ثانیه در هر chat؛ احترام به `429 retry_after`؛ `403` به‌عنوان blocked؛ retry محدود و پیروی از سقف تلاش API؛ خطای یک آیتم باعث توقف کل batch نمی‌شود.
- پیام‌های همگانی بزرگ: پس از تأیید پشتیبان، با بودجهٔ زمانی (~20 ثانیه، قابل تنظیم و پایین‌تر از `max_execution_time`) پردازش می‌شود؛ اگر آیتم باقی ماند، self-request غیرمسدودکننده به `internal-drain.php` زنجیره را ادامه می‌دهد تا صف خالی شود. محافظت: حداکثر طول زنجیره و تنها یک drainer فعال.

## ادغام پنل ادمین

- پنل موجود، داده‌های `admin/data/bot-status.json` و `admin/data/logs.json` و APIهای ajax را حفظ می‌کند؛ هیچ داده‌ای جابه‌جا یا حذف نمی‌شود.
- ربات جدید خلاصهٔ runtime را در `bot-runtime.json` (پیش‌فرض داخل `admin/data` که با `.htaccess` مسدود است) اتمیک می‌نویسد: آخرین webhook، آخرین API موفق/ناموفق، زمان آخرین drain، شمارنده‌های مشاهده‌شدهٔ success/failed/blocked، شمارش خطا و چند خطای اخیر پاک‌سازی‌شده — **بدون token، secret، service key یا محتوای پیام**.
- لاگ ربات با ساختار موجود `time/type/message/data` در `logs.json` نوشته می‌شود تا تب «لاگ‌ها» بدون تغییر کار کند.
- در `index.php` فقط یک بخش **جدا** «اجرای ربات» با type جدید ajax (`runtime`) اضافه می‌شود که همین خلاصه را نشان دهد.
- تزریق token کامل به JavaScript صفحه حذف و نمایش token به «تنظیم‌شده/تنظیم‌نشده» محدود می‌شود.
- رفتار `delete-webhook` پنل به عدم‌حذف pending تغییر می‌کند.
- token، secret و service key هرگز به پنل یا summary راه نمی‌یابند.

## ذخیره‌سازی محلی

- SQLite در مسیری از env (`BOT_STORAGE_DIR`) **خارج از web root**، با fallback فایلی در نبود `pdo_sqlite`.
- جداول/کلیدها: `update_id` پردازش‌شده، صف updateهای ورودی، state گفتگو با expiry، ثبت زمان drain.
- state با `/start` و دکمهٔ «لغو» قابل reset است و به‌صورت خودکار منقضی می‌شود.
- اگر API بگوید حساب غیرفعال/بدون اتصال است، state محلی پاک و ورود دوباره اجباری می‌شود.

## نگاشت خطا

- یک نقطهٔ مرکزی کدهای مستند (`BOT_UNAUTHORIZED`, `BOT_ACCOUNT_BLOCKED`, `VALIDATION_ERROR`, `FORBIDDEN`, `NOT_FOUND`, `CONTENT_ACCESS_DISABLED`, ...) را به پیام کوتاه فارسی نگاشت می‌کند و برای کد ناشناخته fallback عمومی دارد.
- کد جدید (مثل `NO_SUPPORTER_ASSIGNED`) تنها پس از افزوده‌شدن به `openapi.yaml` به نگاشت اضافه می‌شود.

## امنیت

- `X-Telegram-Bot-Api-Secret-Token` روی همهٔ درخواست‌های webhook؛ رد هر چیز دیگر.
- secret جدا برای `internal-drain.php`.
- secrets فقط از env خارج از web root؛ هیچ لاگ یا خروجی صفحه آن‌ها را چاپ نمی‌کند.
- callback_data مبهم و کوتاه؛ هر عمل مجدداً در API مجازسنجی می‌شود.

## Rollout و Rollback

- endpoint جدید `public/bot/webhook.php`؛ `public/webhook.php` قدیمی برای rollback دست‌نخورده می‌ماند.
- `bin/set-webhook.php`: ثبت endpoint جدید با `secret_token`، `allowed_updates` و `drop_pending_updates=false`.
- `bin/rollback-webhook.php`: ثبت دوبارهٔ URL قبلی یا حذف webhook برای بازگشت فوری.
- `BOT_WEBHOOK_URL` پنل به endpoint جدید اشاره می‌کند؛ عملیات set/delete پنل همان مسیر جدید را مدیریت می‌کند.

## استراتژی آزمون

- آزمون‌های بدون وابستگی تازه در `tests/` برای: بارگذاری config، بررسی secret، dedupe، نگاشت خطا، expiry و reset state، و lock drain.
- چک‌لیست آزمون دستی با **TEST bot token**: رد secret نادرست، بی‌اثربودن update تکراری، مقاوم‌بودن به payload خراب، reset شدن state با `/start`/cancel، گزارش درست `HEAD` داخلی، و نبود token/secret در خروجی.

## ترتیب ماژول‌ها

1. **Foundation** (این نوبت): config/env، webhook با secret و dedupe، کلاینت‌های Telegram/API، نگاشت خطا، storage و state، logging/runtime، skeleton drain با lock، setWebhook/deleteWebhook/rollback، environment-check.
2. Login & linking
3. Student messages/today
4. Outbox sending
5. Supporter inbox/reply
6. Student weekly/day view
7. Supporter list + broadcasts
8. Hardening + deployment README + final gaps

پس از هر ماژول: چک‌لیست آزمون دستی کوتاه و توقف تا تأیید.

## API requests (کمبودهای فعلی قرارداد)

1. فیلد صریح «روز مرجع» در پیام‌های unread پشتیبان (تا `day` قابل‌اتکا برای پاسخ ارسال شود).
2. کد خطای مشخص برای IP خارج از allow-list در `/bot/ping` تا «IP not allowed» از WAF/403 عمومی تفکیک شود.
3. endpoint آمار سراسری outbox (pending/sent/failed/blocked) برای نمایش دقیق در پنل.
4. روشن‌سازی قواعد چند-link و رفتار unlink/تغییر نقش.
5. مستندسازی اعتبار claim، انقضای lock و رفتار `failed` در آخرین تلاش.
6. فهرست کامل کدهای خطای Bot endpointها (مثل `NO_SUPPORTER_ASSIGNED`).

## ریسک‌ها و راه‌حل موقت

- **صف SQLite پیش از ACK:** اگر ذخیره‌سازی کوتاه‌مدت update مطلوب نباشد، باید بین تضمین عدم‌گم‌شدن و ACK فوری یکی محدود شود.
- **سرور بدون `fastcgi_finish_request()`:** flush + `ignore_user_abort` و مستندسازی محدودیت؛ در environment-check صریح گزارش می‌شود.
- **دسترسی شبکهٔ میزبان هلند به API:** در environment-check با `GET /bot/ping` و خروجی‌های متمایز (OK/timeout/403/401) گزارش می‌شود.
