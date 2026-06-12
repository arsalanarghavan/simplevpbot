# انحراف‌های آگاهانه از spec (v3)

مبنا: [`LARAVEL-BACKEND-SPEC-FA.md`](LARAVEL-BACKEND-SPEC-FA.md)

## معماری و مسیرها

| موضوع | Spec | پیاده‌سازی |
|-------|------|------------|
| مسیر REST admin | `/api/v1/dashboard/admin/*` | `/api/v1/admin/*` + `normalizeAdminApiPath` در frontend |
| Bootstrap / login | `/api/v1/dashboard/bootstrap` | `/api/v1/bootstrap`, `/api/v1/auth/login` |
| اپراتور dashboard | جدول `users` | `dashboard_users` |
| Settings | مدل `SvpSetting` | `SettingsStore` روی `svp_settings` |
| Migrations | ۴۳ فایل جدا | `2026_06_11_000003_create_svp_wp_parity_schema.php` |
| Frontend build | `frontend/dist` در repo | gitignored؛ CI artifact |
| Relay tenant | `wp_base_url` | `laravel_base_url` + alias deprecated |
| Queue worker | Laravel Horizon | `queue-worker` service با `php artisan queue:work redis` |
| Module defaults | `crypto=false`, `l2tp=false` | هر دو `true` در `config/modules.php` — قابل override با env |

## پاسخ API

| موضوع | Spec §18.1 | پیاده‌سازی |
|-------|------------|------------|
| Dashboard REST / mutate | `{ok, message, data?}` | `svp_ok` / `svp_err` — هم‌تراز |
| Portal admin | `{ok, message}` | `{success, data}` — سازگاری WP admin-ajax |

## Permissions

| موضوع | Spec | پیاده‌سازی |
|-------|------|------------|
| Reseller RBAC | Spatie (optional) | `dashboard_users.permissions_json` — بدون Spatie |

## Portal / Whitelabel

| موضوع | Spec | پیاده‌سازی |
|-------|------|------------|
| `wpPages` | `get_pages()` وردپرس | `portal_pages` در settings یا synthetic از `portal_page_id` — [`PortalPagesBuilder.php`](../backend/app/Services/PortalPagesBuilder.php) |

## Mutate ops — عمق ساده‌شده

| Op | یادداشت |
|----|---------|
| `link_wp_user` | deprecated → `user_merge` |
| `telegram_relay_admin_*` | relay VPS ops — نیاز relay-server زنده |
| `inbound_autolink` | fuzzy match ساده‌تر از WP edge cases |
| `purge_expired_*` | parity اصلی؛ grace/warned filters موجود |
| `service_panel_transfer` | **v3:** parity WP (batch، plan، compensate، notify) — [`ServicePanelTransferService.php`](../backend/app/Modules/XuiPanel/Services/ServicePanelTransferService.php) |

لیست کامل ۱۴۱ op در `MutateOpCatalog.php` — همه handler دارند.

## Cron

جزئیات: [`CRON-SPEC-DEVIATIONS-FA.md`](CRON-SPEC-DEVIATIONS-FA.md)

- IP-fill alert: **v3 پیاده شد** در `ExpiryNotificationService` + `ServiceAlertsHelper`
- L2TP expiry: **v3** پس از sync، alertهای volume/expiry ادامه می‌یابد (مثل WP)
- backup/marketing crons: gated با `svp_modules()->isEnabled()`

## Cutover

| موضوع | Spec | پیاده‌سازی |
|-------|------|------------|
| `includes/` در main | حذف پس از decommission | آرشیو branch `archive/wp-plugin` — [`archive-wp-plugin.sh`](../backend/scripts/ops/archive-wp-plugin.sh) |
| Evidence | soak 24h + import verify | [`docs/evidence/`](evidence/) |
