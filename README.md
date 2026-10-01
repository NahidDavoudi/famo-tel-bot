# Famo Telegram Bot — راهنمای کامل توسعه

این سند راهنمای کامل پروژهٔ ربات تلگرام فامو است: معماری، راه‌اندازی، نحوهٔ افزودن قابلیت، و عیب‌یابی.
سیستم نام‌گذاری متن‌ها جداگانه در [`lang/README.md`](lang/README.md) توضیح داده شده است.

---

## ۱) این پروژه چیست

لایهٔ نازکی بین **تلگرام** و **API فامو** (`api.famoacademy.ir`). ربات برای هر تصمیم/داده از API فامو می‌پرسد و هیچ دیتابیس محلیِ حقیقت ندارد. ارتباط با فامو با هدر `X-Bot-Key` انجام می‌شود.

- PHP 8.2+ (روی سرور فعلی 8.4)
- بدون فریم‌ورک؛ فقط دو پکیج: `irazasyed/telegram-bot-sdk` و `vlucas/phpdotenv`
- مسیر فعال ربات: `public/webhook.php` → `src/Bot.php`

---

## ۲) معماری و جریان درخواست

```
Telegram
   │  POST update (JSON)
   ▼
public/webhook.php        ← نقطهٔ ورود (composition root)
   │  Dotenv، ساخت Config، boot کردن سرویس‌ها، try/catch → همیشه 200
   ▼
src/Bot.php               ← راه‌اندازی SDK، register کردن commandها
   │  getWebhookUpdate()
   ├── callback_query؟  → src/Handlers/CallBackHandler.php
   └── message/command؟ → Telegram SDK  → (CommandBus)
                                   ▼
                          src/Commands/*Command.php   ← فقط Telegram I/O
                                   ▼
                          src/Services/*Service.php   ← منطق کسب‌وکار (استاتیک)
                                   ▼
                          src/Api/FamoApiClient.php   ← HTTP به فامو (X-Bot-Key)
                                   ▼
                          api.famoacademy.ir/api/v1/bot/*
```

قواعد لایه‌ها:
- **Command**: فقط آپدیت را می‌خواند، سرویس را صدا می‌زند، و `replyWithMessage` می‌کند. هیچ HTTP و منطق ندارد.
- **Service**: منطق و تصمیم‌گیری؛ با `FamoApiClient` کار می‌کند و مقادیر ساده برمی‌گرداند (نه آبجکت SDK).
- **FamoApiClient**: یک `ApiResult` برمی‌گرداند (`status`, `body`, `errorCode`, `transportError`, `ok()`, `data()`).

---

## ۳) ساختار پوشه‌ها (مسیر فعال)

```
public/
  webhook.php              # نقطهٔ ورود وب‌هوک
src/
  Bot.php                  # SDK + register commandها + مسیریابی callback
  Config.php               # خواندن .env
  Api/
    FamoApiClient.php      # HTTP به فامو
    ApiResult.php
  Commands/
    StartCommand.php  HelpCommand.php  ReportCommand.php
  Handlers/
    CallBackHandler.php    # مدیریت callback_query
  Services/
    IdentityService.php    # (استاتیک) شناسایی/اتصال حساب
    MessageService.php     # (استاتیک) خواندن متن‌ها از lang/fa.json
  Errors/
    ErrorHandler.php       # Throwable → پیام فارسی
    ApiErrorMessages.php   # کد خطای API → پیام فارسی
lang/
  fa.json                  # همهٔ متن‌ها
  README.md                # قرارداد نام‌گذاری متن‌ها
```

> فایل‌های `src/Bootstrap.php`، `src/WebhookHandler.php`، `src/Outbox/`، `src/Logging/` و `public/bot/internal-drain.php` باقی‌ماندهٔ یک معماری قدیمی‌ترند و با کد فعلی کار نمی‌کنند (به بخش «کارهای باقی‌مانده» نگاه کن).

---

## ۴) راه‌اندازی

### پیش‌نیازها
- PHP 8.2+ با اکستنشن‌های `curl`, `json`, `mbstring`, `openssl`
- Composer

### نصب
```bash
composer install
```

### فایل `.env` (در ریشهٔ پروژه، هرگز کامیت نشود)
از `.env.example` کپی بگیر:
```bash
cp .env.example .env
```
سپس مقادیر را پر کن. متغیرهای مهم:

| متغیر | لازم؟ | توضیح |
| --- | --- | --- |
| `TELEGRAM_BOT_TOKEN` | بله | توکن ربات از @BotFather |
| `FAMO_API_URL` (یا `API_BASE_URL`) | بله | بیس API فامو **بدون** `/api/v1`؛ مثل `https://api.famoacademy.ir` |
| `FAMO_API_TOKEN` (یا `BOT_SERVICE_KEY`) | بله | همان `BOT_SERVICE_KEY` سرور API؛ به‌عنوان هدر `X-Bot-Key` فرستاده می‌شود |
| `BOT_WEBHOOK_URL` | برای ثبت وب‌هوک | مثل `https://nadcorp.ir/public/webhook.php` |
| `TELEGRAM_API_URL` | خیر | فقط اگر از relay استفاده می‌کنی |
| `BOT_WEBHOOK_SECRET` | خیر | فعلاً در `webhook.php` اعتبارسنجی نمی‌شود (بخش امنیت) |
| `ADMIN_NAME` / `ADMIN_PASS` / `ADMIN_CHAT_ID` | برای پنل ادمین | ورود و 2FA |

نام‌های جایگزین پشتیبانی‌شده: `API_BASE_URL`, `FAMO_API_BASE_URL` برای URL و `BOT_SERVICE_KEY`, `FAMO_SERVICE_KEY` برای کلید.

### ثبت وب‌هوک
```bash
php bot-admin set-webhook      # از BOT_WEBHOOK_URL در .env استفاده می‌کند
php bot-admin status           # getWebhookInfo
php bot-admin delete-webhook [--drop-pending]
```
انتظار: در `status` مقدار `URL` درست و `last_error_message` خالی.

---

## ۵) سیستم متن‌ها (JSON)

همهٔ متن‌ها در `lang/fa.json` هستند و با سرویس استاتیک خوانده می‌شوند:
```php
use App\Services\MessageService;

MessageService::get('start.desc');
MessageService::get('profile.name', ['name' => 'علی']);   // {name} جایگزین می‌شود
```
قرارداد کامل کلیدها: [`lang/README.md`](lang/README.md).
خلاصهٔ قاعده: `گروه.نام`؛ برای هر feature مثل `/report` کلیدهای `report.*`، دکمه‌ها `btn.*`، ایموجی‌ها `ico.*`، خطاها `error.*`.

---

## ۶) افزودن یک قابلیت جدید

### الف) افزودن یک Command (مثلاً `/profile`)
1. متن‌ها را در `lang/fa.json` اضافه کن (طبق قرارداد):
   ```json
   "profile.desc": "حساب من",
   "profile.title": "حساب کاربری"
   ```
2. کلاس command را بساز:
   ```php
   <?php
   namespace App\Commands;

   use Telegram\Bot\Commands\Command;
   use App\Services\MessageService;

   class ProfileCommand extends Command
   {
       protected string $name = 'profile';

       public function __construct()
       {
           $this->description = MessageService::get('profile.desc');
       }

       public function handle()
       {
           $chatId = (int) $this->getUpdate()->getChat()->get('id');
           // منطق را به سرویس بسپار:
           // $data = IdentityService::profile($chatId);

           $this->replyWithMessage([
               'text' => MessageService::get('profile.title'),
               'parse_mode' => 'HTML',
           ]);
       }
   }
   ```
3. در `src/Bot.php` ثبت کن:
   ```php
   $this->telegram->addCommands([
       StartCommand::class, HelpCommand::class, ReportCommand::class, ProfileCommand::class,
   ]);
   ```

> نکته: SDK هر command را با `new $class` می‌سازد؛ پس **constructor اجباری نگذار** یا اگر لازم داری، سرویس را استاتیک صدا بزن.

### ب) افزودن یک Service (استاتیک)
الگوی `IdentityService` را دنبال کن:
```php
namespace App\Services;

use App\Api\FamoApiClient;

final class StudentService
{
    private static ?FamoApiClient $api = null;

    public static function boot(FamoApiClient $api): void { self::$api = $api; }

    public static function today(int $chatId, int $userId): array
    {
        $res = self::api()->request('GET', '/bot/students/today', [
            'X-Telegram-User-Id' => (string) $userId,
            'X-Telegram-Chat-Id' => (string) $chatId,
            'X-Bot-Role' => 'student',
        ]);
        if (!$res->ok()) {
            throw new \RuntimeException('API status ' . $res->status);
        }
        return (array) ($res->data()['items'] ?? []);
    }
}
```
و در `public/webhook.php` یک‌بار `boot` کن:
```php
StudentService::boot($api);   // $api همان FamoApiClient است
```

### ج) افزودن callback (دکمه‌های inline)
قرارداد `callback_data`: `scope.action` (مثلاً `guest.identify`, `report.submit`).
- در `src/Bot.php` مسیر callback به `CallBackHandler` می‌رود.
- در `CallBackHandler::handle()` یک `case` جدید به `match` اضافه کن:
  ```php
  match ((string) $callback->getData()) {
      'guest.identify' => $this->guestIdentify((int) $chatId),
      'report.submit'  => $this->reportSubmit((int) $chatId),
      default => null,
  };
  ```
- همیشه اول `answerCallbackQuery` بزن تا اسپینر دکمه بسته شود.

### د) فراخوانی API فامو
```php
$res = $api->request('POST', '/bot/identity/link', $headers, $jsonBody, $query);
if ($res->ok()) { $data = $res->data(); }
else { $msg = \App\Errors\ApiErrorMessages::toPersian($res->errorCode); }
```
هدرهای رایج که API لازم دارد: `X-Bot-Key` (خودکار)، `X-Bot-Role`, `X-Telegram-User-Id`, `X-Telegram-Chat-Id`. مرجع قطعی: `openapi.yaml`.

---

## ۷) مدیریت خطا

- `Bot::handle()` کل dispatch را در `try/catch` دارد؛ در صورت خطا لاگ می‌کند و پیام کاربرپسند با `ErrorHandler::message($e)` می‌فرستد.
- `public/webhook.php` هم هر `Throwable` را لاگ می‌کند و **همیشه ۲۰۰** برمی‌گرداند تا تلگرام retry بی‌پایان نکند.
- استثناها را با پیام‌هایی لاگ کن که کد وضعیت API را داشته باشد (مثل `API status 401`) تا `ErrorHandler` درست map کند.

---

## ۸) تست و دیباگ

- به‌جای تست واقعی، از HTTP client ماک استفاده کن (نمونه در همین پروژه برای `/start` و callback استفاده شد):
  ```php
  $handler = fn () => \GuzzleHttp\Promise\Create::promiseFor(
      new \GuzzleHttp\Psr7\Response(200, [], json_encode(['ok' => true, 'result' => [...]]))
  );
  $api = new \Telegram\Bot\Api('123:FAKE', false,
      new \Telegram\Bot\HttpClients\GuzzleHttpClient(new \GuzzleHttp\Client(['handler' => \GuzzleHttp\HandlerStack::create($handler)])));
  $api->processCommand(new \Telegram\Bot\Objects\Update([...]));
  ```
- لاگ خطا: `public/error_log` (مسیر در `public/webhook.php`). **هشدار:** این فایل داخل web root است.
- بررسی syntax: `php -l <file>`.
- `getWebhookInfo` را با `php bot-admin status` ببین.

---

## ۹) دیپلوی

1. کد را روی سرور بکش (`git pull`).
2. اگر dependency عوض شده: `composer install --no-dev`.
3. `.env` سرور را چک کن (مخصوصاً `FAMO_API_TOKEN` و `BOT_WEBHOOK_URL`).
4. **OPcache را ریست کن** (تغییرات PHP تا ری‌استارت PHP-FPM/Apache دیده نمی‌شوند): با `pkill -USR2 php-fpm` یا مقدار `opcache.validate_timestamps=1`.
5. `php bot-admin set-webhook` و بعد `php bot-admin status`.
6. یک `/start` بفرست و `pending_update_count` را چک کن.

---

## ۱۰) عیب‌یابی

| نشانه | علت احتمالی | راه‌حل |
| --- | --- | --- |
| `500 Internal Server Error` از وب‌هوک | خطای PHP؛ نبودن `vendor`؛ مسیر `.env` | `composer install`؛ `public/error_log` را ببین؛ `php -l` |
| `BOT_UNAUTHORIZED` (401) از API | مقدار `X-Bot-Key` با `BOT_SERVICE_KEY` سرور API یکی نیست | مقدار `FAMO_API_TOKEN` بات = مقدار `BOT_SERVICE_KEY` در `api/.env`؛ بدون کوتیشن/فاصله |
| `BOT_NOT_CONFIGURED` (500) از API | `BOT_SERVICE_KEY` سمت API خالی است | در `api/.env` مقدار بگذار و ری‌استارت کن |
| `pending_update_count` کم نمی‌شود | وب‌هوک خطا می‌دهد | `bot-admin status` → `last_error_message` |
| دکمهٔ inline هیچ کاری نمی‌کند | `callback_query` مدیریت نشده یا خطای داخل handler | `public/error_log`؛ `handle()` را چک کن |
| تغییر کد دیده نمی‌شود | OPcache | ری‌استارت PHP-FPM/Apache |

---

## ۱۱) وضعیت فعلی و کارهای باقی‌مانده (Known gaps)

- **پنل ادمین (`index.php`)**: به `admin/functions.php` نیاز دارد که در این branch حذف شده → باز کردن `/` خطا می‌دهد.
- **باقی‌مانده‌های قدیمی**: `src/Bootstrap.php`, `src/WebhookHandler.php`, `src/Outbox/`, `src/Logging/`, `public/bot/internal-drain.php` به کلاس‌های حذف‌شده (`App\Storage\LocalStore`, `App\Storage\RuntimeStats`, `App\Telegram\TelegramClient`) ارجاع می‌دهند و اگر صدا زده شوند خطا می‌دهند.
- **جریان شناسایی حساب نیمه‌کاره است**: `IdentityService::isLinked` نوشته شده ولی توسط هیچ command صدا زده نمی‌شود؛ و پیامِ `contact` ارسالی کاربر پردازش نمی‌شود.
- **امنیت**: `public/error_log` داخل web root است؛ و `public/webhook.php` هدر `X-Telegram-Bot-Api-Secret-Token` را اعتبارسنجی نمی‌کند در حالی که `bot-admin set-webhook` می‌تواند secret ثبت کند.
- **`ErrorHandler`** بر اساس جست‌وجوی زیررشته (`'401'`, `'500'`, ...) تشخیص می‌دهد؛ بهتر است بر اساس `ApiResult->status` کار کند.
- **`Config`** هنگام autoload شدن، `Dotenv::load()` را در سطح فایل اجرا می‌کند؛ بهتر است فقط در نقطهٔ ورود یک‌بار انجام شود.

---

## پیوست: چه چیزی را کجا بنویسم؟

| نیاز | فایل |
| --- | --- |
| متن جدید | `lang/fa.json` (+ قرارداد در `lang/README.md`) |
| دستور جدید تلگرام | `src/Commands/<Name>Command.php` + ثبت در `src/Bot.php` |
| منطق کسب‌وکار | `src/Services/<Name>Service.php` (استاتیک) |
| دکمهٔ inline جدید | `callback_data` در Command + `case` در `src/Handlers/CallBackHandler.php` |
| درخواست به فامو | `src/Api/FamoApiClient.php` از داخل سرویس |
| خطای فارسی | `src/Errors/ErrorHandler.php` / `src/Errors/ApiErrorMessages.php` |
