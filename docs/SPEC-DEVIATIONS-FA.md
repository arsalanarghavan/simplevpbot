# انحراف‌های آگاهانه از spec (v17)

> نسخه‌های قبلی: v16، v15، v14 و پایین‌تر در همین فایل.

## v17 — B.3.2 proxy، reseller matrix، nav subtabs، secrets

| موضوع | Spec | پیاده‌سازی v17 |
|-------|------|----------------|
| B.3.2 `telegram_http_proxy` | runtime bot egress | **v17 DONE:** `AbstractPlatformClient::post()` + `BotRuntime`؛ `TelegramProxyEgressTest` runtime |
| B.4.4 relay sign-off | operator + forward log | `relay-setup-signoff-v17.md` + `relay-forward-2026-06-12-v17.log` (staging) |
| `notifications`/`logs` navTabs | top-level keys | [`NAV-TABS-NOTIFICATIONS-FA.md`](NAV-TABS-NOTIFICATIONS-FA.md) — subtabs under `site_settings` |
| Bearer Sanctum token | optional API auth | **OPEN** — `personal_access_tokens` بدون endpoint صدور؛ session SPA primary |
| `.env` bot tokens | hydrate DB | **v17:** `AppServiceProvider::hydrateBotTokensFromEnv()` when DB key empty |
| `.env.example` gaps | IPN secret + relay SSL | **v17:** `SVP_CRYPTO_NOWPAYMENTS_IPN_SECRET`, `SVP_RELAY_SSL_VERIFY` |
| `AuditLogService` redact | nested secrets | **v17:** recursive redact + `AuditLogServiceRedactTest` |
| impersonation audit keys | `impersonation_start` | **dot notation:** `impersonation.start` / `impersonation.stop` (filterable in audit API) |
| Tab parity | `discounts`, `reseller_charge` | **v17:** `TabPermissionParityTest` |
| Reseller positive matrix | 72 ops `ok:true` | **v17:** `MutateResellerPositiveMatrixTest` (reseller actor + full perms) |
| Cron job smoke | 14/14 handle | **v17:** `CronJobHandleBatchTest` extended |
| Mutate depth | relay/ssl/wholesale gaps | **v17:** `MutateDepthBatchV17Part1/2Test` |
| `wp_usermeta` accent | import | **v17:** `WpImportAccentMetaTest` |
| migration `down()` | `svp_settings` | **v17:** added to parity migration `down()` |
| Playwright | full `ADMIN_TAB_KEYS` | **v17:** `dashboard-v17.spec.ts` |
| §14 matrix count | 87/87 | **v17 honest:** [`SECTION14-GAP-MATRIX-V17-FA.md`](SECTION14-GAP-MATRIX-V17-FA.md) — 81 DONE + 2 PARTIAL→code DONE |
| queue-worker compose | default on | profile `workers` — [`RUNBOOK-PRODUCTION-FA.md`](RUNBOOK-PRODUCTION-FA.md) §سرویس‌ها |
| orphan `users` table | Laravel default | [`ORPHAN-USERS-TABLE-FA.md`](ORPHAN-USERS-TABLE-FA.md) |
| OPS live logs | import/soak/DNS/TLS | `docs/evidence/*-v17.md` + `*-v17.log` (staging templates) |
| ARCH-12 | commit `includes/` | workspace بدون `includes/` — `arch-decommission-ready-v17.md` |
| php-xml local | `php artisan test` | CI green؛ local needs `php8.3-xml` |

---

# انحراف‌های آگاهانه از spec (v16)

> نسخه v16 در commit قبلی.

| موضوع | Spec | پیاده‌سازی v16 |
|-------|------|----------------|
| §14 matrix | 87/87 ادعا | شمارش نادرست — اصلاح در v17 |
| B.3.2 proxy | runtime | mutate-only تا v17 |
| OPS | live logs | templates `*-v16.md` |

---

# انحراف‌های آگاهانه از spec (v15)

> نسخه‌های قبلی: v14، v13 و پایین‌تر در همین فایل.

## v15 — RBAC gaps، A.2.2 snapshots، policy 72، PanelDown sustained

| موضوع | Spec | پیاده‌سازی v15 |
|-------|------|----------------|
| `resellers` tab §10.1 | `users.manage` | **v15:** اضافه به `resellerAllowedTabsMap` + `TabPermissionParityTest` |
| A.2.2 `externalHostSnapshots` | monitoring live metrics | **v15:** `MonitorHostSnapshotService` + `MonitorHostSnapshotsTest` |
| Marketing lifecycle mutate | spec `—` admin-only | **v15:** ۴ op در `$resellerMap` با `marketing.lifecycle` (tab parity) |
| `user_*_service` ops | `xui_panel` module | **v15:** gate در `MutationPipeline::XUI_PANEL_OPS` |
| PanelDown alert | unreachable > 5 min | **v15:** `panel_down_alert_sustained_sec` (default 300) در `AdminAlertsService` |
| Reseller webhook secret | per-platform columns | **v15:** doc [`WEBHOOK-RESELLER-SECRET-FA.md`](WEBHOOK-RESELLER-SECRET-FA.md) — unified `webhook_secret` |
| Webhook 403 body | `{ok, message}` | **v15:** `message: forbidden` در `WebhookController` |
| `configs_client_*` | admin-only در spec | **نگه‌داری** deviation v14 — reseller panel access |
| Policy map count | 68 | **v15:** 72 (+ marketing lifecycle ops) |
| Tests | gaps §7–§18 | **v15:** `MutatePolicyPositiveMatrixTest`, `CronJobHandleBatchTest`, `AuthSanctumFlowTest`, `LoginRateLimitTest`, `HealthDeepTokenTest`, `AdminStateSchemaTest`, `GroupAcceptanceV15Test` |
| Playwright | appendix tabs | **v15:** `dashboard-v15.spec.ts` |
| OPS live | import/soak/DNS | evidence `docs/evidence/*-v15.md` (operator-run) |
| ARCH-12 | commit `includes/` | checklist `arch-decommission-ready-v15.md` |

---

# انحراف‌های آگاهانه از spec (v14)

> نسخه‌های قبلی: v13، v12، v11 و پایین‌تر در همین فایل.

## v14 — policy 68، forbidden_op matrix، REST/webhook/cron tests

| موضوع | Spec | پیاده‌سازی v14 |
|-------|------|----------------|
| `reseller_bot_secret_rotate` | `services.manage` در `$resellerMap` | **v14:** اضافه شد + `MutatePolicyParityTest` (68 entries) |
| Admin-only ops | reseller → `forbidden_op` | **v14:** `MutateAdminOnlyMatrixTest` data-driven (~72 ops) |
| Reseller module gates | `module_disabled` when off | **v14:** `MutateResellerModuleGateBatchTest` (19 ops) |
| `configs_client_*` (۷ ops) | spec `—` (admin-only) | **v14:** **DRIFT doc** — نگه‌داری `services.manage` در map برای reseller panel access؛ spec §15 #116–125 admin-only در HTTP |
| ARCH-1 API paths | `/api/v1/dashboard/admin/*` | [`ARCH-1-API-ROUTES-FA.md`](ARCH-1-API-ROUTES-FA.md) — canonical `/api/v1/admin/*` |
| ARCH-11 scripts | deprecate WP generators | **v14:** `generate-extended-text-defaults.php` exit 2 |
| §12 cron | 14 `svp:*` + purge xui gate | **v14:** `ScheduleListTest` extended |
| §13 webhook | Bale ingress + secret header | **v14:** `WebhookBaleIngressTest`, `TelegramSecretTokenHeaderTest` |
| §7 REST holes | media, panel POST, backup, GET batch | **v14:** `AdminRestRoutesBatchTest`, auth logout, ui-preferences |
| §18 metrics | `webhook_received_total`, cron duration | **v14:** `MetricsWebhookTest`, `CronJobMetricsTest` |
| Log redaction | no secrets in audit | **v14:** `LogRedactionTest` |
| `admin/state` rate | 60/min default | **v14:** config assert + existing limit test |
| §14 acceptance | panel health, texts fa/en, buy deliver | **v14:** `GroupAcceptanceV14Test`, `BuyFlowApproveDeliverTest` depth |
| Playwright | economics, cards, whitelabel | **v14:** `dashboard-v14.spec.ts` + `dashboard-auth.spec.ts` |
| CI fetch audit | `normalizeAdminApiPath` | **v14:** `scripts/ci-check-frontend-fetch.sh` in CI |
| Portal TTL | signed links | **v14:** [`PORTAL-SIGNED-LINKS-FA.md`](PORTAL-SIGNED-LINKS-FA.md) |
| OPS live | import/soak/DNS/webhooks | evidence `docs/evidence/*-v14.md` (operator-run) |
| ARCH-12 | commit `includes/` removal | checklist `arch-decommission-ready-v14.md` |
| php8.3-xml local | `php artisan test` | documented in `backend/README.md`; CI has extensions |

---

# انحراف‌های آگاهانه از spec (v13)

> نسخه‌های قبلی: v12، v11 و پایین‌تر در همین فایل.

## v13 — policy matrix، cron job tests، OPS evidence

| موضوع | Spec | پیاده‌سازی v13 |
|-------|------|----------------|
| Reseller policy 67 ops | `forbidden_perm` per op | **v13:** `MutatePolicyMatrixTest` (67 data-driven) |
| `MutatePolicyParityTest` count | 58 | **v13:** fixed → 67 |
| Module gates batch | relay 22 + xui + marketing | **v13:** `MutateModuleGateBatchTest` |
| marketing.lifecycle | 4 ops | **v13:** `MutateMarketingLifecycleTest` |
| `l2tp_add` / `user_merge` | Feature/Mutate depth | **v13:** `MutateL2tpParityTest`, `MutateUserMergeDepthTest` |
| Cron job tests | IdleOffers, cache, sync, economics | **v13:** `CronJobDispatchTest` |
| Webhook reseller 60/min | rate limit | **v13:** `WebhookResellerRateLimitTest` |
| `wp:import --force` / `--backups-from` | PHPUnit | **v13:** `WpImportForceTest`, `WpImportBackupsFromTest` |
| Observability alerts | BackupFailed, Relay, backlog@1000 | **v13:** `AdminAlertsExtendedTest` |
| `mutate_op_total` | metrics | **v13:** `MetricsIncrementTest` |
| Playwright auth | login + whitelabel + monitoring | **v13:** `dashboard-auth.spec.ts` + CI migrate/seed |
| ARCH-11 scripts `includes/` | archive | **v13:** deprecation exit در `scripts/*` |
| ARCH-12 commit decommission | ops sign-off | **OPEN** — `arch-decommission-ready-v13.md` |
| OPS live cutover | import/soak/DNS/webhooks | evidence checklists `docs/evidence/*-v13.md` |

---

# انحراف‌های آگاهانه از spec (v12)

> نسخه‌های قبلی: v11 و پایین‌تر در همین فایل.

## v12 — mutate depth، Playwright CI، queue doc

| موضوع | Spec | پیاده‌سازی v12 |
|-------|------|----------------|
| ۴۵ mutate op smoke-only | depth در `Feature/Mutate` | **v12:** `Mutate*DepthV12*` + batch extensions |
| `POST /api/v1/admin/configs-sync` | feature test | **v12:** `ConfigsSyncFeatureTest` |
| Log channels `svp-*` | audit | **v12:** `LoggingChannelsTest` |
| Playwright E2E | CI + staging | **v12:** `ci.yml` job `playwright` |
| Broadcast 1000+ | nightly load | **v12:** `BroadcastLoadEnqueueTest::test_broadcast_enqueue_1000_targets` |
| Horizon | spec optional | **v12:** [`QUEUE-HORIZON-DEVIATION-FA.md`](QUEUE-HORIZON-DEVIATION-FA.md) |
| TLS nginx | prod block | **v12:** `backend/docker/nginx/ssl.example.conf` |
| OPS cutover | live logs | **v12:** `docs/evidence/*-v12.md` checklists |
| `dashboard_users` | spec table name | Laravel `dashboard_users` + Sanctum (unchanged) |
| Migrations واحد | ۴۳ فایل | یک migration + `svp_wp_parity.sql` (unchanged) |
| Purge cron | `svp:purge_expired` | ماژول `xui_panel` gated (unchanged v11) |
| Spatie permissions | optional | `permissions_json` + `MutatePolicyService` (unchanged) |

---

# انحراف‌های آگاهانه از spec (v11)

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
| Impersonate mutate | admin full power | **v9:** محدود به ops/perm نماینده هدف |
| Impersonate start HTTPS | production | **v9:** `https_required` در `production` |
| `reseller_xui_panels` tab | reseller با services.manage | admin-only (spec §E.4) |
| `cards` tab | gated crypto module | **v9:** همیشه visible؛ `crypto_auto` card در UI |
| `SVP_ENCRYPTION_KEY` | config جدا | `APP_KEY` + Laravel `Crypt` |
| `settings_tab` `panel` | deprecated | alias → `logs` |
| crypto-ipn param | `{path_secret}` | `{secret}` (همان URI) |
| Module `reseller` depends | telegram/bale | `depends_any: [telegram, bale]` |

## Cron / Modules

- **v8:** `ModuleManager::bootOrder()` topological؛ `EnsureInternalWebhookDrain` روی drain؛ `SVP_QUEUE_DRAIN_KEY` بدون fallback در production
- **v7:** xui/marketing HTTP gates؛ RedactSecrets؛ relay mutate gate
- **v11:** `PurgeExpiredJob` در ماژول `xui_panel` (عمدی — purge به پنل وابسته است؛ cron `svp:purge_expired` فقط با `SVP_MODULE_XUI_PANEL=true`)
- backup/marketing crons module-gated

## Cutover

| موضوع | Spec | پیاده‌سازی |
|-------|------|------------|
| `includes/` در main | حذف پس از decommission | **v11:** `archive/wp-plugin` + `CONFIRM=1 remove-includes-from-main.sh` (staged) |
| Evidence | soak 24h + import verify | `docs/evidence/` + CI artifacts |

## NavTabsBuilder

**v8:** تب‌های `users_bulk`, `bot_ui`, `unit_economics`, `reseller_charge`, `reseller_settings`, `reseller_xui_panels` به boot `navTabs` اضافه شدند. `notifications`/`logs` زیر `site_settings` در SPA (نه top-level tab).

## Whitelabel / CSS

| موضوع | Spec | پیاده‌سازی |
|-------|------|------------|
| کلیدهای settings_tab | flat در WP | **v8:** mirror flat + `whitelabel.{key}`؛ `BrandingResolver` برای `cssVariables` |
| CSS سفارشی | editor آزاد | textarea `--var: value` در whitelabel tab |
| Logo/favicon preview | dedicated preview pane | **v10:** inline `<img>` در `ImageUrlField` |

## v10 — ماژول‌ها و queue

| موضوع | Spec | پیاده‌سازی |
|-------|------|------------|
| Webhook drain | `dispatch()->afterResponse()` | **v10:** `InboundQueueDrainJob::dispatch()->afterResponse()` (تست: sync) |
| admin/state module gate | middleware per module | **v10:** `EnsureAdminStateModule` روی `tab` / `site_subtab` |
| Reseller mutate gate | module off | **v10:** `reseller_*` / `bot_reseller_*` → `module_disabled` |
| Deploy artifact | `assets/dashboard/dist` | **v10:** `build-frontend.sh` mirror از `frontend/dist` |
| `link_wp_user` | فعال | **v10:** deprecated؛ `user_merge` جایگزین |

## v11 — gates، mutate depth، decommission

| موضوع | Spec | پیاده‌سازی |
|-------|------|------------|
| admin/state xui/backup/marketing/finance | gate per module | **v11:** `EnsureAdminStateModule` — `xui_panels`, `configs`, `unit_economics`, `backup`, `marketing_lifecycle`, `site_subtab=finance` |
| Mutate xui/marketing | module off | **v11:** `MutationPipeline::isXuiPanelOp` / `isMarketingOp` |
| `settings_tab` bots/relay/finance | module off | **v11:** gate در `CoreMutations::settingsTab` |
| `reseller_bot_tokens_save` reseller | admin-only در spec | **v11:** در `$resellerMap` → `services.manage` (نماینده با perm) |
| `telegram_relay_set_webhook_reseller` | reseller policy | **v11:** `services.manage` در `$resellerMap` |
| Docker nginx volume | `assets/dashboard/dist` | **v11:** `docker-compose.yml` + CI artifact path |
| Playwright E2E | browser tests | **v11:** `frontend/e2e/` + `playwright.config.ts` (staging/CI با `PLAYWRIGHT_BASE_URL`) |
| Portal `{success,data}` | یکسان با dashboard | انحراف آگاهانه WP parity — [`PortalSubscriptionController`](backend/app/Modules/Core/Http/PortalSubscriptionController.php) |
| `dashboard_users` | جدول spec | Laravel `dashboard_users` + Sanctum |
| Migrations واحد | ۴۳ فایل | یک migration + `svp_wp_parity.sql` |
| Queue | Horizon | `queue-worker` Docker profile + Redis |
| Spatie permissions | optional | `permissions_json` + `MutatePolicyService` |
| WP plugin sources | حذف از main | **v11:** `includes/`, `simplevpbot.php`, root `tests/*.php` حذف؛ آرشیو در `archive/wp-plugin` |

## v16 — §14، mutate policy، observability

| موضوع | Spec | پیاده‌سازی |
|-------|------|------------|
| Service display label | `formatServiceDisplayLabel` مشترک | **v16:** `ServiceNaming::formatServiceDisplayLabel` — bot + dashboard user detail |
| Monitoring real-time | WebSocket / SSE | **v16:** polling 60s در `dashboard-monitoring.tsx` (انحراف عملکردی سبک) |
| Mutate positive matrix | 72 ops | **v16:** `MutatePolicyPositiveMatrixTest` data provider 72 + `ok:true` |
| Cron metrics | هر `svp:*` | **v16:** `CronJobMetricsTest` 14/14 |
| Panel down alert | sustained 5min | **v16:** `PanelDownSustainedTest` + config `panel_down_alert_sustained_sec` |
| Webhook 403 body | `message: forbidden` | **v16:** assert در `WebhookIngressTest` |
| `/sub/{token}` | route test | **v16:** `PortalSubscriptionAcceptanceTest` |
| Impersonate stop alias | `POST /admin/impersonate/stop` | **v16:** `ImpersonationTest` |
| mutate_op_total per-op | Prometheus label | **v16:** `mutate_op_total:{op}` در `MutationPipeline` |
| Redact secrets log | `[redacted]` در payload | **v16:** `RedactSecretsMiddlewareTest` |
| Frontend admin paths | `normalizeAdminApiPath` | **v16:** `App.tsx`, `dash-admin-upload.ts` |
| Relay forward OPS | live log | **v16:** template `relay-forward-YYYY-MM-DD.log` — operator |
| ARCH-12 commit | `includes/` removal | workspace خالی؛ ops sign-off در `arch-decommission-ready-v16.md` |
