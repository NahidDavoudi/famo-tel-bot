# سیستم نام‌گذاری متن‌ها (`lang/fa.json`)

همهٔ متن‌های ربات فقط در همین فایل هستند و با `MessageService::get('key')` خوانده می‌شوند.

## قاعدهٔ کلی

`گروه.نام`

- با نقطه (`.`) جدا می‌شود.
- همه‌چیز lowercase؛ برای چندکلمه‌ای از snake_case استفاده کن (`link_hint`).
- بخش اول (گروه) از فهرست ثابت زیر انتخاب می‌شود تا حدس‌زدن آسان باشد.

## گروه‌ها

| گروه | کاربرد | نمونه |
| --- | --- | --- |
| `common` | متن‌های مشترک/برند | `common.title`, `common.intro` |
| `start` | دستور `/start` و جریان آن | `start.desc`, `start.link_hint` |
| `help` | دستور `/help` | `help.title`, `help.body` |
| `report` | دستور `/report` | `report.title`, `report.body` |
| `identity` | شناسایی/اتصال حساب فامو | `identity.ask_phone` |
| `profile` | اطلاعات حساب کاربری | `profile.name` |
| `menu` | منوی اصلی | `menu.body` |
| `support` | پشتیبانی | `support.body` |
| `unknown` | پیام پیش‌فرض/ناشناخته | `unknown.message` |
| `notify` | اعلان‌ها | `notify.report_done` |
| `btn` | برچسب دکمه‌ها (بدون ایموجی) | `btn.web` |
| `ico` | ایموجی/آیکون | `ico.web` |
| `error` | پیام خطا | `error.timeout` |
| `loading` | پیام انتظار | `loading.default` |

## پسوندهای استاندارد داخل گروه‌ها

| پسوند | معنی |
| --- | --- |
| `.desc` | توضیح کوتاه دستور در منوی تلگرام |
| `.title` | تیتر پیام |
| `.body` | متن اصلی |
| `.hint` | راهنما/توضیح تکمیلی |
| `.ok` | موفقیت |
| `.fail` | شکست |
| `.empty` | حالت خالی |
| `.loading` | در حال انجام |
| `.not_found` | پیدا نشد |

## قاعده‌های سریع (برای حدس‌زدن)

- متن مربوط به `/report` است؟ → `report.*`
- برچسب دکمه است؟ → `btn.<action>` (بدون ایموجی)
- ایموجی دکمه یا پیام است؟ → `ico.<name>`
- پیام خطاست؟ → `error.<type>`
- ترکیب دکمه: `MessageService::get('ico.web') . ' ' . MessageService::get('btn.web')`

## افزودن کلید جدید

1. گروه مناسب را از جدول بالا انتخاب کن.
2. اگر گروه یک feature است، پسوند استاندارد (`.title`, `.body`, ...) را به‌کار ببر.
3. کلید را lowercase و snake_case بنویس.
4. در کد از `MessageService::get('group.key')` استفاده کن.
5. اگر جای `{placeholder}` مقدار پویا لازم داری، آرایهٔ جایگزینی بده:
   `MessageService::get('profile.name', ['name' => $name])`.

## نکته

- `btn.*` فقط برچسب است و ایموجی داخلش نیست؛ ایموجی جدا در `ico.*` نگه داشته می‌شود تا ترکیب آزاد باشد.
- placeholderها با `{...}` نوشته می‌شوند؛ `MessageService` اگر متنی با همان نام کلید وجود داشته باشد خودش جایگزین می‌کند، وگرنه مقدار پاس‌داده‌شده در آرایهٔ دوم اولویت دارد.
