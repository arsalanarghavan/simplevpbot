=== SimpleVPBot ===
Contributors: simplevpbot
Tags: telegram, bale, vpn, 3x-ui, bot
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

ربات VIP VPN با اتصال به پنل MHSanaei 3x-ui، تلگرام و بله؛ مدیریت کامل از پنل وردپرس.

== نصب ==

1. پوشه `simplevpbot` را در `wp-content/plugins/` کپی کنید.
1b. در ریشهٔ افزونه دستور `composer install` را اجرا کنید تا کتابخانهٔ داخلی QR (`chillerlan/php-qrcode`) نصب شود و PHP باید `ext-gd` داشته باشد. بدون این دو، تولید QR در ربات غیرفعال می‌شود.
2. از منوی افزونه‌ها، SimpleVPBot را فعال کنید.
3. به SimpleVPBot در پیشخوان بروید: توکن ربات‌ها، URL پنل، یوزر/پس پنل، آیدی ادمین‌ها و مسیر webhook را تنظیم کنید.
4. دکمه‌های «Set Telegram Webhook» و «Set Bale Webhook» را بزنید (سایت باید HTTPS معتبر داشته باشد).
5. در BotFather تلگرام، Secret Token را مطابق مقدار «Telegram secret header token» در تنظیمات وارد کنید (اگر از setWebhook با secret_token استفاده می‌کنید).

== Webhook ==

* تلگرام: `POST /wp-json/simplevpbot/v1/webhook/telegram/{secret}`
* بله: `POST /wp-json/simplevpbot/v1/webhook/bale/{secret}`

مقدار `{secret}` باید با فیلدهای «webhook secret» در تب ربات‌ها یکسان باشد.

== Nginx (مثال) ==

در کنار وردپرس، مسیر REST را به PHP بفرستید (معمولا با try_files به index.php).

== بکاپ خودکار ==

Cron وردپرس هر N دقیقه (تب بکاپ) با `GET /panel/api/server/getDb` و `getConfigJson` فایل‌ها را ZIP و برای ادمین‌ها ارسال می‌کند. نیاز به ZipArchive در PHP.

== API های استفاده‌شده ==

* 3x-ui: https://github.com/MHSanaei/3x-ui/wiki/Configuration
* Telegram Bot API: https://core.telegram.org/bots/api
* Bale: https://docs.bale.ai/

== عیب‌یابی ==

* اگر webhook کار نکرد: permalink ساختار را روی «نام نوشته» بگذارید، SSL را بررسی کنید، فایروال 443 را باز کنید.
* اگر پنل login نشد: آدرس پنل باید با `/` تمام شود؛ مسیر API پیش‌فرض `/panel/api` است.
* اگر QR ارسال نشد: `composer install` در پوشهٔ افزونه و فعال بودن `gd` در PHP را بررسی کنید؛ لاگ افزونه را ببینید.
* لاگ‌ها در تب «لاگ‌ها» و جدول `wp_svp_logs` ذخیره می‌شوند.

== Changelog ==

= 1.0.0 =
* نسخه اولیه
