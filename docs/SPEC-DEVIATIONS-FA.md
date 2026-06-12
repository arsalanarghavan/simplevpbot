# انحراف‌های آگاهانه از spec (v8)

مبنا: [`LARAVEL-BACKEND-SPEC-FA.md`](LARAVEL-BACKEND-SPEC-FA.md)

## معماری و مسیرها

| موضوع | Spec | پیاده‌سازی |
|-------|------|------------|
| مسیر REST admin | `/api/v1/dashboard/admin/*` | `/api/v1/admin/*` + `normalizeAdminApiPath` در frontend |
| Bootstrap / login | `/api/v1/dashboard/bootstrap` | `/api/v1/bootstrap`, `/api/v1/auth/login` |
| Impersonate | `/dashboard/impersonate/*` | aliases: `dashboard/impersonate/*` + `admin/impersonate/*` |
| اپراتور dashboard | جدول `users` | `dashboard_users` |
| Migrations | ۴۳ فایل جدا | یک migration + `svp_wp_parity.sql` |
| Queue worker | Horizon | `queue-worker` Docker profile |
| Docker service | `nginx` | نام سرویس `web` در compose |
| Module env | `MODULE_*_ENABLED` | `SVP_MODULE_*` |

## پاسخ API

| موضوع | Spec | پیاده‌سازی |
|-------|------|------------|
| Dashboard REST / mutate | `{ok, message, data?}` | `svp_ok` / `svp_err` |
| Portal admin | `{ok, message}` | `{success, data}` — سازگاری WP |
| Login errors | `message` | **v8:** `svp_err('invalid_credentials'|'rate_limited')` |

## Permissions

| موضوع | Spec | پیاده‌سازی |
|-------|------|------------|
| Reseller RBAC | Spatie (optional) | `permissions_json` + `MutatePolicyService` |
| HTTP gates | per-route | **v8:** `reseller.perm:*` روی panel/config/bulk/broadcast-queue |
| `configs_client_*` | `services.manage` | **v8:** اضافه به `$resellerMap` |
| Impersonate stop | هر sanctum user | admin-only (امنیت عملیاتی) |

## Cron / Modules

- **v8:** `ModuleManager::bootOrder()` topological؛ `EnsureInternalWebhookDrain` روی drain؛ `SVP_QUEUE_DRAIN_KEY` بدون fallback در production
- **v7:** xui/marketing HTTP gates؛ RedactSecrets؛ relay mutate gate
- purge gated `xui_panel`؛ backup/marketing crons module-gated

## Cutover

| موضوع | Spec | پیاده‌سازی |
|-------|------|------------|
| `includes/` در main | حذف پس از decommission | آرشیو branch؛ حذف با `CONFIRM=1 remove-includes-from-main.sh` |
| Evidence | soak 24h + import verify | `docs/evidence/` + CI artifacts |

## NavTabsBuilder

**v8:** تب‌های `users_bulk`, `bot_ui`, `unit_economics`, `reseller_charge`, `reseller_settings`, `reseller_xui_panels` به boot `navTabs` اضافه شدند. `notifications`/`logs` زیر `site_settings` در SPA (نه top-level tab).

## Whitelabel / CSS

| موضوع | Spec | پیاده‌سازی |
|-------|------|------------|
| کلیدهای settings_tab | flat در WP | **v8:** mirror flat + `whitelabel.{key}`؛ `BrandingResolver` برای `cssVariables` |
| CSS سفارشی | editor آزاد | textarea `--var: value` در whitelabel tab |
