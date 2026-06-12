# خاموش‌کردن WordPress — فاز ۱۲

چک‌لیست غیرفعال‌سازی افزونه SimpleVPBot روی وردپرس پس از پایدار شدن Laravel.

## پیش‌نیازها

Evidence template: [`evidence/CUTOVER-SIGNOFF-FA.md`](evidence/CUTOVER-SIGNOFF-FA.md)

اسکریپت‌ها (repo):

- `backend/scripts/ops/import-verify.sh` — verify-only + log (+ post-import ops)
- `backend/scripts/ops/post-import-ops.sh` — rebuild-closure + register-webhooks + schedule:list
- `backend/scripts/ops/remove-includes-from-main.sh` — حذف `includes/` پس از آرشیو (نیاز `CONFIRM=1`)
- `backend/scripts/ops/staging-cutover-runbook.sh` — import + post-import + E2E
- `backend/scripts/ops/soak-24h.sh` — soak با `SVP_SOAK_DURATION_SEC=86400`
- `backend/scripts/ops/wp-disable-staging.sh` — غیرفعال WP cron روی staging
- `backend/scripts/ops/archive-wp-plugin.sh` — آرشیو `includes/` به branch

- [ ] `wp:import` کامل و `wp:import --verify-only` بدون diff بحرانی — `SVP_MYSQL_DSN` یا dump
- [ ] `svp:rebuild-reseller-closure` و `svp:register-webhooks` اجرا شده — [`staging-cutover-runbook.sh`](../backend/scripts/ops/staging-cutover-runbook.sh)
- [ ] DNS: API و dashboard → Laravel
- [ ] Webhook تلگرام/بله → Laravel (`POST /api/v1/webhook/{platform}/{secret}`)
- [ ] Relay forward به Laravel: `laravel_base_url` / `telegram_relay_laravel_forward_url`
- [ ] Soak 24h — `docs/evidence/soak-24h-*.log` ([`soak-24h-template.log`](evidence/soak-24h-template.log))
- [ ] container `scheduler` Laravel در حال اجرا (۱۴ cron job)

## پرتال — روی Laravel

پرتال دیگر به وردپرس وابسته نیست:

| قابلیت | مسیر Laravel |
|--------|--------------|
| پنل ادمین پرتال | `GET /info?svp_adm=1` + `POST /api/v1/portal/admin` |
| اشتراک کاربر (plain) | `GET /info?svp_p=1&svp_fmt=sub` |
| اشتراک کاربر (HTML) | `GET /info?svp_p=1` با `Accept: text/html` |
| آواتار تلگرام | `GET /api/v1/portal/tg-avatar` |

nginx باید `/info` و `/api/v1/*` را به Laravel proxy کند — **نه** به WP.

## مراحل خاموش‌کردن WP

### ۱. Snapshot نهایی

```bash
mysqldump wordpress_db > wp-final-$(date +%F).sql
```

### ۲. غیرفعال‌کردن cron وردپرس

- `wp cron event list` — رویدادهای `simplevpbot_*` را unschedule کنید
- یا `DISABLE_WP_CRON` در `wp-config.php` اگر کل سایت WP خاموش می‌شود

### ۳. غیرفعال‌کردن افزونه

```bash
wp plugin deactivate simplevpbot
```

یا از پنل وردپرس — **بدون حذف فایل‌ها** تا rollback سریع ممکن باشد.

### ۴. تأیید Laravel-only

| جریان | تست |
|-------|-----|
| Dashboard login | `/dashboard/` |
| Bot webhook (مستقیم یا relay) | پیام تست به ربات |
| Portal admin signed link | `?svp_adm=1` |
| Portal subscription | `?svp_p=1` |
| Receipt approve | از داشبورد |
| Backup | تب backup |
| Relay forward | `relay-server` → `/api/v1/webhook/telegram/*` |

```bash
SVP_BASE_URL=https://staging.example.com backend/scripts/ops/staging-cutover-checklist.sh
```

### ۵. مانیتور ۴۸h

- [`RUNBOOK-PRODUCTION-FA.md`](RUNBOOK-PRODUCTION-FA.md)
- `GET /health/ready` هر دقیقه

## Rollback

1. فعال‌سازی مجدد افزونه WP
2. DNS revert
3. relay `laravel_base_url` → URL قدیمی WP (موقت)
4. restore MySQL snapshot در صورت نیاز

جزئیات: [`CUTOVER-STAGING-FA.md`](CUTOVER-STAGING-FA.md).

## پس از decommission

- نگهداری snapshot WP حداقل ۳۰ روز
- آرشیو `includes/` در branch `archive/wp-plugin` (فاز نهایی)
- حذف فیزیکی WP فقط پس از تأیید نهایی ops (`CONFIRM=1 backend/scripts/ops/remove-includes-from-main.sh`)
- Evidence: [`evidence/CUTOVER-SIGNOFF-FA.md`](evidence/CUTOVER-SIGNOFF-FA.md) بخش v10
