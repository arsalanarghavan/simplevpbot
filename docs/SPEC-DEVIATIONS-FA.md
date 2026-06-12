# انحراف‌های آگاهانه از spec (v6)

مبنا: [`LARAVEL-BACKEND-SPEC-FA.md`](LARAVEL-BACKEND-SPEC-FA.md)

## معماری و مسیرها

| موضوع | Spec | پیاده‌سازی |
|-------|------|------------|
| مسیر REST admin | `/api/v1/dashboard/admin/*` | `/api/v1/admin/*` + `normalizeAdminApiPath` در frontend |
| Bootstrap / login | `/api/v1/dashboard/bootstrap` | `/api/v1/bootstrap`, `/api/v1/auth/login` |
| اپراتور dashboard | جدول `users` | `dashboard_users` |
| Settings | مدل `SvpSetting` | `SettingsStore` روی `svp_settings` — encrypt at rest؛ **v6:** `PanelSecretCipher` برای `svp_panels` |
| Migrations | ۴۳ فایل جدا | `2026_06_11_000003_create_svp_wp_parity_schema.php` |
| Frontend build | `frontend/dist` در repo | gitignored؛ CI artifact |
| Relay tenant | `wp_base_url` | `laravel_base_url` + alias deprecated |
| Queue worker | Laravel Horizon | `queue-worker` service با `php artisan queue:work redis` |
| Module defaults | `crypto=false`, `l2tp=false` | **v6:** پیش‌فرض `false` در `config/modules.php` و `.env.example` — override با `SVP_MODULE_*` |

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
| `purge_expired_*` | **v5:** `PurgeExpiredService` — grace days، warn، notify، L2TP skip، stats |
| `service_panel_transfer` | **v3:** parity WP (batch، plan، compensate، notify) — [`ServicePanelTransferService.php`](../backend/app/Modules/XuiPanel/Services/ServicePanelTransferService.php) |

لیست کامل ۱۴۱ op در `MutateOpCatalog.php` — همه handler دارند.

## Cron

جزئیات: [`CRON-SPEC-DEVIATIONS-FA.md`](CRON-SPEC-DEVIATIONS-FA.md)

- **v6:** `SVP_QUEUE_DRAIN_KEY` env؛ `PanelSecretCipher`؛ reseller `webhook_secret` encrypt؛ relay mutate/route gate؛ `RedactSecretsInLogs`؛ Sanctum `X-XSRF-TOKEN`؛ `/api/v1/me/portal` برای user SPA؛ scheduler `schedule:work` در Docker
- **v5:** `PurgeExpiredService` WP parity؛ `InboundQueueDrainJob`؛ purge gated با `xui_panel`
- **v4:** `notify_user_*` keys، `notify_after_expire`، `client_ips` API، economics renewal gated
- IP-fill: live panel API + `client_ips_json` on configs sync + ip_log fallback
- L2TP expiry: volume/expiry alerts پس از sync ادامه می‌یابد
- backup/marketing/xui_panel crons: gated با `svp_modules()->isEnabled()`
- backup interval: `BackupIntervalResolver` (settings SSOT، env fallback)

## Cutover

| موضوع | Spec | پیاده‌سازی |
|-------|------|------------|
| `includes/` در main | حذف پس از decommission | آرشیو branch `archive/wp-plugin` — [`archive-wp-plugin.sh`](../backend/scripts/ops/archive-wp-plugin.sh) |
| Evidence | soak 24h + import verify | [`docs/evidence/`](evidence/) |
