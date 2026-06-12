# انحراف‌های زمان‌بندی Cron (v3)

مبنا: `docs/LARAVEL-BACKEND-SPEC-FA.md` §12

## Intervalها (هم‌تراز spec)

| Job | Laravel (`routes/console.php`) | Spec |
|-----|-------------------------------|------|
| Expiry | hourly | hourly |
| Autorenew | hourly | hourly |
| PanelEconomics | hourly | hourly |
| Marketing | hourly (if module) | hourly |
| AdminAlerts | every 10 minutes | every 10 minutes |
| IdleOffers | hourly (if module) | hourly |
| PurgeExpired | hourly | hourly |

## Intervalهای عمدی متفاوت

| Job | Laravel | دلیل |
|-----|---------|------|
| Backup | `*/N` دقیقه (پیش‌فرض ۶۰) | `SVP_BACKUP_INTERVAL_MINUTES` — فقط اگر `backup` module enabled |
| Broadcast / users_bulk / inbound_queue | every minute | worker queues |
| panel_online / panel_service_sync / inbound_clients_cache | every 10 minutes | بار پنل — فقط اگر `xui_panel` enabled |

## Module gating (§6.2)

| Job | Gate |
|-----|------|
| `svp:backup` | `backup` module |
| `svp:marketing`, `svp:idle_offers` | `marketing` module |
| panel crons | `xui_panel` module |

## عمق منطق Expiry / Autorenew (v3)

| قابلیت | Laravel | WP |
|--------|---------|-----|
| IP-fill از ip_log | بله (`maybeFillIpFromLog`) | بله |
| IP-fill alert (limit_ip) | بله (`maybeIpFillAlert` + cache/onlines) | بله |
| traffic stale alert | بله | بله |
| L2TP `sync_l2tp_usage` | بله | بله |
| L2TP expired cleanup | بله | بله |
| L2TP volume/expiry alerts | بله (v3 — دیگر early-return نیست) | بله |
| Autorenew skip L2TP | بله | بله |
| Autorenew `checkout_price_renew` | بله | بله |

مرجع: `ExpiryNotificationService.php`, `includes/cron/class-cron-expiry.php`

## Metrics

Cron jobs از `CronTimer` برای `cron_job_duration_seconds{job="..."}` استفاده می‌کنند.
