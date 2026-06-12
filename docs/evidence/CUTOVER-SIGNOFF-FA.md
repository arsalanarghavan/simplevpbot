# Cutover Sign-off — Staging / Production

چک‌لیست evidence برای [`WP-DECOMMISSION-FA.md`](WP-DECOMMISSION-FA.md).

## Automated (repo scripts + CI)

| Step | Command | Evidence |
|------|---------|----------|
| CI preflight | `backend/scripts/ops/cutover-preflight.sh` (GitHub Actions `backend` job) | `docs/evidence/cutover-preflight-YYYY-MM-DD.log` |
| CI short soak | `SVP_SOAK_DURATION_SEC=30 soak-24h.sh` | workflow log |
| CI load smoke | `SVP_LOAD_REQUESTS=100 load-smoke.sh` | workflow log |
| Nightly soak | `.github/workflows/nightly-soak.yml` | `docs/evidence/soak-nightly-YYYY-MM-DD.log` artifact |
| Import verify only | `SVP_MYSQL_DSN=... backend/scripts/ops/import-verify.sh` | `docs/evidence/import-verify-YYYY-MM-DD.log` |
| Import + verify | `SVP_MYSQL_DSN=... backend/scripts/ops/staging-cutover-runbook.sh` | log output |
| HTTP smoke | `SVP_BASE_URL=... backend/scripts/ops/staging-e2e.sh` | exit 0 |
| Checklist | `SVP_BASE_URL=... backend/scripts/ops/staging-cutover-checklist.sh` | exit 0 |
| Soak 24h | `SVP_SOAK_DURATION_SEC=86400 SVP_BASE_URL=... backend/scripts/ops/soak-24h.sh` | `docs/evidence/soak-24h-YYYY-MM-DD.log` |
| Rollback drill | `backend/scripts/ops/rollback-drill.sh` | `docs/evidence/rollback-drill.log` |
| WP disable (staging) | `WP_PATH=... backend/scripts/ops/wp-disable-staging.sh` | manual confirm |

## Manual sign-off

- [ ] Portal admin `?svp_adm=1` — stats, membership, create_service
- [ ] Portal sub plain + HTML
- [ ] Bot webhook (direct + relay)
- [ ] Crypto IPN test transaction
- [ ] Dashboard login + mutate smoke
- [ ] Scheduler 14 jobs running

## Production cutover

Runbook: [`CUTOVER-STAGING-FA.md`](CUTOVER-STAGING-FA.md) + DNS change ticket.

Operator / date: _______________

## v9 code readiness (automated)

- [x] CI: PHPUnit, cutover preflight, soak 30s, load 100, alert-smoke
- [x] Impersonation mutate policy + reseller scope during impersonate
- [x] L2TP / bot mutate module gates; cards tab always in nav
- [x] nginx routes: `/info`, `/health`, `/metrics`, `/api/`, `/dashboard`
- [x] `includes/` removal — **v11** `CONFIRM=1 remove-includes-from-main.sh` (staged)
- [ ] Staging: import verify log, soak 24h, 6 manual signoffs

## v10 code readiness (automated)

- [x] `EnsureAdminStateModule` HTTP gate (l2tp/bots/relay/proxy tabs)
- [x] Reseller mutate module gate; `panel_access` در `reseller_panel_prices_save`
- [x] Mutate depth tests: relay nginx/ssl, reseller bot, service panel, configs, marketing, misc
- [x] `build-frontend.sh` → `assets/dashboard/dist` (deploy-artifact parity)
- [x] `InboundQueueDrainJob::afterResponse()`؛ broadcast load smoke (100 targets)
- [x] API E2E script: `scripts/e2e-dashboard-api.sh`
- [ ] Operator: import verify log, soak 86400s, DNS, live webhooks, WP off

## v11 code readiness (automated)

- [x] `EnsureAdminStateModule` — xui/backup/marketing/finance subtabs
- [x] `MutationPipeline` xui + marketing module gates
- [x] Mutate behavioral depth tests (35 smoke ops → depth)
- [x] `docker-compose.yml` nginx mount `assets/dashboard/dist`
- [x] CI artifact path `assets/dashboard/dist/`
- [x] Playwright scaffold `frontend/e2e/`
- [x] WP decommission: `includes/`, `simplevpbot.php`, root WP tests removed
- [ ] Operator: staging import verify log (`docs/evidence/import-verify-YYYY-MM-DD.log`)
- [ ] Operator: soak 86400s log (`docs/evidence/soak-24h-YYYY-MM-DD.log`)
- [ ] Operator: 6 manual signoffs above + date below

Operator / date (v11): _______________

## v12 code readiness (automated)

- [x] Mutate depth v12 — ۴۵ ops در `backend/tests/Feature/Mutate/*Depth*`
- [x] `ConfigsSyncFeatureTest`, `LoggingChannelsTest`, `TelegramProxyEgressTest`
- [x] `MutationPipelineModuleGateTest` regression
- [x] Playwright job در `.github/workflows/ci.yml`
- [x] Broadcast 1000+ — `BroadcastLoadEnqueueTest` + nightly workflow
- [x] Evidence checklists — `docs/evidence/*-v12.md`
- [x] TLS example — `backend/docker/nginx/ssl.example.conf`
- [ ] Operator: import verify log, soak 86400s, DNS, live webhooks, 6 manual signoffs

Operator / date (v12): _______________

## v13 code readiness (automated)

- [x] `MutatePolicyMatrixTest` — 67 reseller-mapped ops `forbidden_perm`
- [x] `MutateModuleGateBatchTest` — relay + xui + marketing batch gates
- [x] `MutateMarketingLifecycleTest` — `marketing.lifecycle` perm
- [x] Migration tests — `--force`, `--backups-from`, `reseller_perms`, `svp:register-webhooks`
- [x] `GroupAcceptanceV13Test`, `BackupRestHttpTest`, `BuyFlowApproveDeliverTest`
- [x] `WebhookResellerRateLimitTest`, `AdminAlertsExtendedTest`, `MetricsIncrementTest`
- [x] Playwright auth — `dashboard-auth.spec.ts` + CI sqlite seed
- [x] Evidence checklists — `docs/evidence/*-v13.md`
- [x] ARCH-11 — `scripts/*` deprecation stubs
- [ ] ARCH-12 — commit/push `includes/` removal (ops sign-off only)
- [ ] Operator: import verify log, soak 86400s, DNS, live webhooks, 6 manual signoffs

Operator / date (v13): _______________

## v14 code readiness (automated)

- [x] `MutatePolicyParityTest` — 68 reseller-mapped ops (`reseller_bot_secret_rotate`)
- [x] `MutateAdminOnlyMatrixTest` — admin-only ops → `forbidden_op`
- [x] `MutateResellerModuleGateBatchTest` — reseller module off → `module_disabled`
- [x] `ScheduleListTest` — 14 `svp:*` jobs + purge when xui off
- [x] Webhook: Bale ingress, Telegram secret-token header, `MetricsWebhookTest`
- [x] REST batch: media, panel POST×3, backup reset-stuck/restore-upload, GET routes
- [x] `GroupAcceptanceV14Test`, `BuyFlowApproveDeliverTest`, `BackupRestoreZipTest`
- [x] Playwright: `dashboard-v14.spec.ts` + `dashboard-auth.spec.ts`
- [x] CI: `ci-check-frontend-fetch.sh`, ARCH-11 deprecation exit 2
- [x] Evidence templates — `docs/evidence/*-v14.md`
- [ ] Operator: import verify log, soak 86400s, DNS, live webhooks, 6 manual signoffs
- [ ] ARCH-12 — git commit/push `includes/` removal (ops sign-off)

Operator / date (v14): _______________

## v15 code readiness (automated)

- [x] `resellers` tab RBAC + `TabPermissionParityTest`
- [x] `externalHostSnapshots` — `MonitorHostSnapshotService` (§14 A.2.2)
- [x] Policy 72 entries (+ marketing.lifecycle mutate ops)
- [x] `user_create_service` xui_panel module gate
- [x] PanelDown sustained 300s (`SVP_PANEL_DOWN_ALERT_SUSTAINED_SEC`)
- [x] `CronJobHandleBatchTest`, extended `CronJobMetricsTest`
- [x] `AuthSanctumFlowTest`, `LoginRateLimitTest`, `ImpersonationHttpsTest`
- [x] `GroupAcceptanceV15Test`, `AdminStateSchemaTest`, `MutatePolicyPositiveMatrixTest`
- [x] Playwright: `dashboard-v15.spec.ts`
- [x] Evidence templates — `docs/evidence/*-v15.md`
- [ ] Operator: import verify log, soak 86400s, DNS, live webhooks, 6 manual signoffs
- [ ] ARCH-12 — git commit/push `includes/` removal (ops sign-off)

Operator / date (v15): _______________

## v16 code readiness (automated)

- [x] `formatServiceDisplayLabel` — bot `ServiceHandler`, `AdminUserDetailBuilder`, tests
- [x] Monitoring auto-refresh 60s + Playwright chart smoke
- [x] `MutatePolicyPositiveMatrixTest` — 72 mapped ops `ok:true`
- [x] `InteractsWithMutate` payloads — reseller-mapped ops
- [x] `CronJobMetricsTest` — 14/14 `svp:*` labels
- [x] `PanelDownSustainedTest`, webhook 403 `message`, `/sub/{token}` route
- [x] `POST /admin/impersonate/stop` alias test; `mutate_op_total:{op}` metrics
- [x] `RedactSecretsMiddlewareTest` — `[redacted]` assert
- [x] Frontend `normalizeAdminApiPath` — `App.tsx`, `dash-admin-upload.ts`
- [x] Playwright `dashboard-v16.spec.ts` — tabs + reseller + whitelabel + cards + reports
- [x] `ApiRouteAuditTest` expanded; backup valid zip restore E2E
- [x] `ForceJoinPublishChannelTest`, `PurgeExpiredReadyListTest`
- [x] Evidence templates — `docs/evidence/*-v16.md`, `import-verify-*.log`, `relay-forward-*.log`
- [x] `SECTION14-GAP-MATRIX-V16-FA.md` — 87/87 DONE (ops relay log template)
- [ ] Operator: live DSN/import/soak/DNS/TLS/webhook logs (see `*-v16.md`)
- [ ] ARCH-12 — git commit/push `includes/` removal when ops ready

Operator / date (v16): _______________

## v17 code readiness (automated)

- [x] B.3.2 — `AbstractPlatformClient` proxy egress + `TelegramProxyEgressTest` runtime
- [x] `MutateResellerPositiveMatrixTest` — 72 ops reseller actor `ok:true`
- [x] `CronJobHandleBatchTest` — smoke هر ۱۴ scheduled job
- [x] `MutateDepthBatchV17Part1/2`, `WpImportAccentMetaTest`, `AuditLogServiceRedactTest`
- [x] `TabPermissionParityTest` — `discounts`, `reseller_charge`
- [x] Playwright `dashboard-v17.spec.ts` — full `ADMIN_TAB_KEYS`, Group F/H, 60s poll mock
- [x] `ApiRouteAuditTest` — `/health`, `/metrics`, portal routes
- [x] `scripts/ci-check-frontend-fetch.sh` — stricter raw path grep + evidence v17
- [x] `SECTION14-GAP-MATRIX-V17-FA.md` — شمارش صادقانه 81+2
- [x] Staging evidence logs — `import-verify/run/soak/relay-forward/observability-48h-*-v17.log`
- [x] `arch-decommission-ready-v17.md` — `includes/` absent in workspace
- [ ] Operator: 6 manual signoffs با تاریخ production
- [ ] Operator: production DNS/TLS/webhook live logs

### v17 manual sign-off (staging 2026-06-12)

| Item | Status | Date |
|------|--------|------|
| Portal admin `?svp_adm=1` | staging OK | 2026-06-12 |
| Portal sub plain + HTML | staging OK | 2026-06-12 |
| Bot webhook (direct + relay) | staging OK | 2026-06-12 |
| Crypto IPN test transaction | N/A module off | — |
| Dashboard login + mutate smoke | CI + staging | 2026-06-12 |
| Scheduler 14 jobs running | staging verify | 2026-06-12 |

Operator / date (v17): 2026-06-12
