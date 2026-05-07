# گزارش ممیزی جامع سیستم (Full System Audit)

**تاریخ:** ۲۰۲۶-۰۵-۰۶  
**دامنه:** امنیت/دسترسی، ایزولاسیون ربات، حسابداری، UX نقش‌محور، Cron و پایداری  
**روش:** مرور قراردادهای REST، مدل کاربر/نماینده، وبهوک، پردازش رسید، Cron، و گیتینگ UI (بدون ویرایش فایل پلن اصلی)

---

## خلاصه اجرایی

سیستم در مسیر **جداسازی نماینده (reseller)** و **محدودسازی `admin/state` + `mutate`** پیشرفت قابل‌توجهی دارد (`class-rest-dashboard.php`, `class-model-user.php`, `class-bot-runtime.php`). با این حال، **ledger مالی** عمدتاً بدون تراکنش پایگاه‌داده و بدون قفل سطر است؛ **وب‌هوک‌ها** عمومی با `permission_callback` آزاد اما با secret در مسیر/هدر کنترل می‌شوند. برای آمادگی تولید، اولویت با **تراکنش‌دار کردن مسیر تأیید رسید/شارژ** و **هم‌ترازی مداوم allow-list عملیات REST با `Dashboard_Admin_Mutations::apply`** است.

---

## فاز ۱ — معماری امنیت و کنترل دسترسی (P0)

### یافته‌ها

| شدت | شرح فنی | مسیر / نماد | اثر | اصلاح حداقلی |
|-----|---------|-------------|-----|----------------|
| **Medium** | مسیر `POST /dashboard/login` با `__return_true` عمومی است؛ امنیت به `login_nonce` + `wp_signon` + rate limit وابسته است. | `SimpleVPBot_Rest_Dashboard::register`, `route_dashboard_login` | brute-force محدود به ۵ تلاش/۱۵دقیقه per IP؛ NAT مشترک ممکن است قفل کند | نگه‌داشتن nonce در صفحه لاگین؛ در صورت نیاز CAPTCHA یا limit per user |
| **Low** | `perm_admin_or_reseller`: هر کاربر وردپرس با `manage_options` مسیرهای admin dashboard را می‌بیند حتی اگر هدف «فقط ربات» باشد. | `perm_admin_or_reseller`, `dashboard_actor_context` | سطح دسترسی وردپرس = دسترسی کامل داشبورد | سیاست نقش WP جدا از `svp_users`؛ Audit لاگ |
| **Low** | نقش نماینده در DB (`role=reseller`) + لینک `wp_user_id`؛ اگر ردیف نادرست شود، scope اشتباه. | `SimpleVPBot_Model_User::find_by_wp_user`, `is_reseller_row` | دسترسی بیش از حد یا کم | اعتبارسنجی هنگام لینک؛ تست قراردادی |
| **Medium** | `route_admin_user`: برای ادمین اصلی بدون محدودیت ID (طراحی عمدی)؛ برای نماینده فیلتر `reseller_can_access_user`. | `route_admin_user` | IDOR فقط برای نقش مدیریت کل سایت | پذیرفتنی برای super admin؛ برای نقش‌های WP محدودتر آینده: capability جدا |
| **Low** | `reseller_permission_for_op` و whitelist `$allow` در `route_admin_mutate` باید با `switch` در `Dashboard_Admin_Mutations::apply` **هم‌نوا** بمانند. | `route_admin_mutate`, `Dashboard_Admin_Mutations::apply` | drift → باگ مجوز یا op پنهان | تست خودکار یا map واحد مشترک |
| **Low** | لاگ فعالیت REST با `actor_svp_user_id: 0` در `log_rest_user`. | `class-dashboard-admin-mutations.php` | ردیابی نماینده ضعیف | پر کردن `actor_svp_user_id` از `__actor_svp_user_id` |

### Fix First (فاز ۱)

1. هم‌ترازی مکانیکی allow-list `mutate` با تمام `case`های حساس در mutations (تست رگرسیون).
2. بهبود audit: ثبت `actor_svp_user_id` برای اقدامات نماینده.
3. مستندسازی مدل تهدید برای `dashboard/login` (nonce، rate limit، HTTPS).

### ریسک قابل‌پذیرش (فاز ۱)

- ادمین کامل WP با دسترسی به تمام داده‌های داشبورد (مدل اعتماد وردپرس).
- Rate limit مبتنی بر IP برای لاگین داشبورد.

---

## فاز ۲ — امنیت Bot و ایزولاسیون نماینده (P0)

### یافته‌ها

| شدت | شرح فنی | مسیر / نماد | اثر | اصلاح حداقلی |
|-----|---------|-------------|-----|----------------|
| **Medium** | وبهوک‌ها: `permission_callback` = `__return_true`؛ احراز هویت با secret در URL و optional Telegram secret header. | `SimpleVPBot_Webhook::register_routes`, `handle`, `handle_reseller` | افشای secret در لاگ سرور/Proxy | چرخش دوره‌ای secret؛ HTTPS اجباری؛ حداقل طول/entropy |
| **Medium** | `client_ip()` از `X-Forwarded-For` و مشابه استفاده می‌کند؛ در صورت عدم اعتماد صحیح به proxy، rate limit دور زده می‌شود. | `SimpleVPBot_Webhook::client_ip` | DoS یا RL ناکارا | تنظیم لیست proxyهای معتبر یا استفاده از `rest_get_ip_address` با فیلتر |
| **Low** | پاسخ `200` با `ok:true` برای بدنه JSON نامعتبر — از نظر امنیتی خنثی، اما تشخیص حمله را سخت می‌کند. | `handle` | کم | لاگ سطح debug برای reject |
| **Low** | `SimpleVPBot_Bot_Context`: state استاتیک request؛ باید پس از request ریست شود (در handler فعلی فراخوانی `reset` بررسی شود). | `class-bot-context.php` | نشت context در PHP long-running نادر | `reset` در پایان webhook |
| **Low** (پس از hardening اخیر) | در context نماینده، **عدم fallback** به توکن سراسری در `bot_token_for_current_context`. | `SimpleVPBot_Bot_Runtime::bot_token_for_current_context` | جلوگیری از cross-tenant ارسال | نگه‌داشت تست؛ اگر توکن خالی → پیام خطای کنترل‌شده به کاربر |

### Fix First (فاز ۲)

1. سخت‌گیری منبع IP برای rate limit وقتی پشت CDN نیست.
2. چک‌لیست استقرار: secretهای وبهوک، هدر Telegram، مسیر `reseller/{id}/…`.
3. تأیید `Bot_Context::reset` در مسیر خروج وبهوک.

### ریسک قابل‌پذیرش (فاز ۲)

- اتکا به obscurity جزئی برای URL webhook (در کنار `hash_equals`).

---

## فاز ۳ — حسابداری و تراکنش‌ها (P0)

### یافته‌ها

| شدت | شرح فنی | مسیر / نماد | اثر | اصلاح حداقلی |
|-----|---------|-------------|-----|----------------|
| **High** | `Receipt_Processor::approve`: مسیر `topup` با `find` → `update` balance بدون تراکنش DB؛ دو تأیید همزمان یا race نادر ممکن است ناسازگار شود. | `class-receipt-processor.php` | اعتبار اضافی | `BEGIN … FOR UPDATE` یا atomic `UPDATE balance = balance + %f WHERE id=%d` + چک receipt |
| **High** | همان تابع: به‌روزرسانی receipt به `approved` پس از منطق مالی؛ اگر بین دو درخواست همپوشانی باشد، نیاز به **idempotency** سطح DB (مثلاً `WHERE status='pending'`) | `SimpleVPBot_Model_Receipt::update` | دوباره‌کاری side-effect | یک `UPDATE ... AND status='pending'` با `rows_affected` |
| **Medium** | `SimpleVPBot_Model_Transaction::set_status` / `update` بدون constraint منحصر به فرد برای جلوگیری از double-approve در سطح اپلیکیشن. | `class-model-transaction.php` | وضعیت ناسازگار | state machine + تراکنش |
| **Medium** | Cron `users_bulk` فراخوانی `Dashboard_Admin_Mutations::apply('user_balance_delta')` بدون زمینه نماینده — فرض بر jobs ایجادشده توسط ادمین. | `class-cron-users-bulk.php` | اگر job جعل شود (نیاز دسترسی DB/ادمین) | امضای job یا capability فقط ادمین |
| **Low** | `last_approved_timestamp` برای گزارش؛ وابسته به `created_at` و timezone. | `SimpleVPBot_Model_Transaction::last_approved_timestamp` | گزارش نادرست حاشیه‌ای | تست واحد timezone |

### Fix First (فاز ۳)

1. **Atomic approve receipt**: یک تراکنش DB که ابتدا receipt را از `pending` به `approved` (شرطی) تغییر دهد، سپس balance/سرویس را اعمال کند.
2. شمارش `affected rows` برای رد درخواست تکراری.
3. بررسی `Purchase_Side_Effects::on_paid_transaction` برای فراخوانی تکراری.

### ریسک قابل‌پذیرش (فاز ۳)

- خطای provision پس از کسر/ثبت — در صورت وجود مسیر جبران دستی و لاگ قوی.

---

## فاز ۴ — ویژگی‌ها و UX نقش‌محور (P1)

### یافته‌ها

| شدت | شرح فنی | مسیر / نماد | اثر | اصلاح حداقلی |
|-----|---------|-------------|-----|----------------|
| **Low** | `RESELLER_ALLOWED_BY_PERMISSION` در `App.tsx`: تب‌هایی بدون perm (مثلاً `dashboard`, `monitoring`) برای همه نمایندگان باز است؛ باید با payload واقعی `admin/state` هم‌خوان باشد. | `dashboard-ui/src/App.tsx` | نمایش تب بدون داده | پیام خالی یا مخفی کردن ویجت |
| **Medium** | ناهم‌خوانی احتمالی **فهرست تب‌های ناوبری** (`admin-nav.ts`) با آنچه بک‌اند برای نماینده برمی‌گرداند (مثلاً referral/cards). | `admin-nav.ts`, `route_admin_state` | UX گمراه‌کننده | فیلتر سمت UI بر اساس `isAdmin` + پرچم‌های state |
| **Low** | نماینده: `users.merge` و عملیات bulk در REST از whitelist `mutate` حذف شده‌اند — UI باید دکمه‌ها را غیرفعال کند (الگوی اخیر). | چند کامپوننت `dashboard-*-admin.tsx` | کلیک بی‌اثر | گیت مشترک با `actorPermissions` |

### ماتریس کوتاه «ادعا vs واقعیت»

| نقش | ادعای UI | واقعیت بک‌اند |
|-----|-----------|----------------|
| Admin WP | دسترسی کامل | `perm_manage` روی مسیرهای حساس؛ `mutate` بدون محدودیت نماینده |
| Reseller | تب‌ها بر اساس `actorPermissions` | `route_admin_mutate` + scope SQL + مالکیت پلن برای `user_create_service` |
| End user (`/me`) | فقط کاربر لینک‌شده | `route_me_state` بدون دسترسی به دیگران |

### Fix First (فاز ۴)

1. یک تابع کمکی UI: «آیا این تب برای نماینده معتبر است؟» بر اساس ترکیب `boot.isReseller` + `actorPermissions` + وجود کلید در پاسخ state.
2. مرور `dashboard-resellers-admin` / merge / receipts برای جلوگیز از دکمه‌های ghost.

### ریسک قابل‌پذیرش (فاز ۴)

- تفاوت جزئی لیست‌ها تا وقتی که داده حساس نشان داده نشود.

---

## فاز ۵ — پایداری فنی، Cron و کیفیت داده (P1)

### یافته‌ها

| شدت | شرح فنی | مسیر / نماد | اثر | اصلاح حداقلی |
|-----|---------|-------------|-----|----------------|
| **Medium** | چندین `ensure_*_scheduled` روی `init`؛ در ترافیک بالا ریسک race برای `wp_schedule_event` تکراری (WordPress معمولاً تحمل می‌کند). | `class-cron-manager.php` | رویداد تکراری نادر | `wp_next_scheduled` قبل از schedule؛ migration one-shot |
| **Low** | `Cron_Users_Bulk::run`: batch ۲۰ آیتم؛ partial failure با `tries` — نیاز به مانیتورینگ صف. | `class-cron-users-bulk.php` | کار گیرکرده | داشبورد job + هشدار |
| **Low** | وابستگی به `wp-cron` پیش‌فرض برای دقیقه‌ای؛ در تولید توصیه به cron سیستم. | `schedule_all` comments | تأخیر job | `DISABLE_WP_CRON` + curl wp-cron.php |
| **Medium** | `class-activator.php` / dbDelta: ارتقا بدون فعال‌سازی مجدد ممکن است cron جدید را از قلم بیندازد — mitigated با `ensure_*`. | `class-cron-manager.php` | job اجرا نشود | همان hooks ensure |

### Fix First (فاز ۵)

1. مانیتورینگ: تعداد `pending` در `users_bulk` و سن job.
2. مستند عملیاتی: cron سیستم + intervalهای سفارشی `simplevpbot_minute`.

### ریسک قابل‌پذیرش (فاز ۵)

- تأخیر چند دقیقه‌ای WP-Cron در سایت‌های کم‌ترافیک.

---

## Top Fixes برای Production Readiness (اولویت‌دار)

1. **تراکنش پایگاه‌داده + به‌روزرسانی شرطی receipt در `approve`** (رفع race/double-apply).
2. **سخت‌سازی منبع IP** برای rate limit وبهوک در محیط‌های بدون proxy معتبر.
3. **تست خودکار** هم‌پوشانی allow-list `mutate` با operations mutations (یا تولید map از یک منبع).
4. **Audit trail**: `actor_svp_user_id` در لاگ REST برای نماینده.
5. **هم‌ترازی ناوبری UI** با داده واقعی `admin/state` برای نماینده.
6. **Runbook استقرار**: secretهای وبهوک، TLS، cron سیستم.

---

## پیوست: فایل‌های کلیدی مرورشده

- `includes/api/class-rest-dashboard.php`
- `includes/admin/class-dashboard-admin-mutations.php`
- `includes/models/class-model-user.php`
- `includes/bot/class-webhook.php`, `class-bot-runtime.php`, `class-bot-context.php`
- `includes/helpers/class-receipt-processor.php`
- `includes/models/class-model-transaction.php`
- `includes/cron/class-cron-manager.php`, `class-cron-users-bulk.php`
- `dashboard-ui/src/App.tsx`, `dashboard-ui/src/config/admin-nav.ts`

---

*پایان گزارش.*
