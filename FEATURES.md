# گزارش جامع وضعیت پنل نماینده و ربات مستقل

**آخرین به‌روزرسانی:** **Laravel backend v17** — B.3.2 proxy runtime، reseller matrix 72، cron smoke 14/14، Playwright `dashboard-v17.spec.ts` (full `ADMIN_TAB_KEYS`)، evidence `*-v17.md` + staging logs، §14 honest matrix 81+2.

## Laravel dashboard (spec v17 — خلاصه)

- §14 B.3.2: `telegram_http_proxy` در `AbstractPlatformClient` + `BotRuntime`
- Tests: `MutateResellerPositiveMatrixTest`, `CronJobHandleBatchTest` (14 jobs), `MutateDepthBatchV17Part1/2`, `WpImportAccentMetaTest`, `AuditLogServiceRedactTest`
- Secrets: `.env.example` IPN/relay SSL؛ env→DB bot token hydration؛ nested audit redact
- Playwright: `frontend/e2e/dashboard-v17.spec.ts` — all tabs, Group F/H, 60s poll mock, cards
- Docs: `SPEC-DEVIATIONS-FA.md` v17، `SECTION14-GAP-MATRIX-V17-FA.md`، `NAV-TABS-NOTIFICATIONS-FA.md`
- OPS: `docs/evidence/*-v17.md` + `import-verify/run/soak/relay-forward` logs (staging)

## Laravel dashboard (spec v16 — خلاصه)

- Service naming: `ServiceNaming::formatServiceDisplayLabel` — bot + user detail API
- Monitoring: auto-refresh 60s (`dashboard-monitoring.tsx`)
- Tests: `MutatePolicyPositiveMatrixTest` (72 ops), `CronJobMetricsTest` (14 jobs), `PanelDownSustainedTest`, backup valid zip restore
- Metrics: `mutate_op_total:{op}` per successful mutate
- Frontend: `normalizeAdminApiPath` in `App.tsx` + `dash-admin-upload.ts`
- Playwright: `frontend/e2e/dashboard-v16.spec.ts` (tabs, reseller scope, whitelabel, cards, reports chart + impersonate)
- Docs: `SPEC-DEVIATIONS-FA.md` v16، `SECTION14-GAP-MATRIX-V16-FA.md`
- OPS: `docs/evidence/*-v16.md` + `import-verify-*.log` / `relay-forward-*.log` templates

## Laravel dashboard (spec v15 — خلاصه)

- RBAC: `resellers` tab در `resellerAllowedTabsMap`؛ `TabPermissionParityTest`؛ HTTP broadcast-queue + purge-expired gates
- §14 A.2.2: `MonitorHostSnapshotService` — `externalHostSnapshots` در monitoring refresh
- Policy: 72 `$resellerMap` (marketing lifecycle ops)؛ `MutatePolicyPositiveMatrixTest`
- Gates: `user_create_service` + bot/l2tp batch در `MutateModuleGateBatchTest`
- §12: `CronJobHandleBatchTest`؛ `CronJobMetricsTest` extended (autorenew, admin_alerts)
- §18: PanelDown sustained 300s؛ webhook `message` field؛ `HealthDeepTokenTest`؛ `RedactSecretsMiddlewareTest`
- Auth: `AuthSanctumFlowTest`، `LoginRateLimitTest`، `ImpersonationHttpsTest`
- §14: `GroupAcceptanceV15Test` — overview isolation, monitoring scope, receipt deliver, panel access toggle
- Playwright: `frontend/e2e/dashboard-v15.spec.ts`
- Docs: `SPEC-DEVIATIONS-FA.md` v15، `WEBHOOK-RESELLER-SECRET-FA.md`
- OPS: `docs/evidence/*-v15.md` checklists

## Laravel dashboard (spec v14 — خلاصه)

- Policy: 68 `$resellerMap` entries؛ `MutateAdminOnlyMatrixTest` + `MutateResellerModuleGateBatchTest`
- Gates: `MutateModuleGateBatchTest` — `l2tp_update`, `bot_test_bale`
- §12–§13: `ScheduleListTest` (14 jobs), `InboundQueueDrainJobTest`, `BackupJobCronTest`, Bale/TG webhook tests
- §7 REST: `AdminRestRoutesBatchTest`, `AuthLogoutTest`, `UiPreferencesTest`, `ResellerAdminOnlyRoutesTest`
- §14: `GroupAcceptanceV14Test`, `BuyFlowApproveDeliverTest` (deliver assertion), `BackupRestoreZipTest`
- §18: `MetricsWebhookTest`, `CronJobMetricsTest`, `LogRedactionTest`, rate limit 60/min default
- Playwright: `frontend/e2e/dashboard-v14.spec.ts`
- Docs: `SPEC-DEVIATIONS-FA.md` v14, `ARCH-1-API-ROUTES-FA.md`, `PORTAL-SIGNED-LINKS-FA.md`
- CI: `scripts/ci-check-frontend-fetch.sh`
- OPS: `docs/evidence/*-v14.md` checklists (live execution by operator)

## Laravel dashboard (spec v13 — خلاصه)

- Policy: `MutatePolicyParityTest` 67 entries؛ `MutatePolicyMatrixTest` forbidden_perm data-driven
- Gates: `MutateModuleGateBatchTest` — relay(22)، xui، marketing
- Depth: `MutateL2tpParityTest`، `MutateUserMergeDepthTest`، `MutateAuditTest` sensitive ops
- §14: `GroupAcceptanceV13Test`، `BackupRestHttpTest`، `BuyFlowApproveDeliverTest`
- §12–§18: `CronJobDispatchTest`، `WebhookResellerRateLimitTest`، `AdminAlertsExtendedTest`، `MetricsIncrementTest`
- Migration: `WpImportForceTest`، `WpImportBackupsFromTest`، `RegisterWebhooksCommandTest`
- Playwright: `frontend/e2e/dashboard-auth.spec.ts` (CI migrate+seed)
- ARCH-11: legacy `scripts/*` → deprecation redirect to `backend artisan test`
- Evidence: `docs/evidence/*-v13.md`؛ OPS live items remain operator-run

## Laravel dashboard (spec v11 — خلاصه)

- `EnsureAdminStateModule`: xui_panels، configs، backup، marketing_lifecycle، finance/crypto subtab
- `MutationPipeline`: xui_panel + marketing ops gated؛ `settings_tab` bots/relay/finance gated
- Mutate depth v11: bot/site، reseller admin/bot، service/user، configs/economics، bulk/broadcast، finance، L2TP CRUD
- `MutateNegativeTest` + `AdminStateModuleGateTest` گسترش یافته
- `GroupAcceptanceV11Test` — §14 gaps (quick links، monitoring refresh، wpPages، bulk API)
- Playwright: `frontend/e2e/dashboard.spec.ts`
- WP: `includes/`, `simplevpbot.php`, root WP tests حذف؛ branch `archive/wp-plugin`
- Evidence: `docs/evidence/import-checklist-v11.md`، CUTOVER-SIGNOFF v11

## Laravel dashboard (spec v10 — خلاصه)

- HTTP module gates روی `admin/state` (l2tp، bots، relay/proxy subtabs)
- Mutate depth: relay admin nginx/ssl، reseller bot، service panel، configs `panel_access`
- Deploy artifact: `frontend/dist` → `assets/dashboard/dist`
- Broadcast load smoke (100 targets) + API E2E script (`scripts/e2e-dashboard-api.sh`)
- Evidence: `docs/evidence/CUTOVER-SIGNOFF-FA.md` بخش v10

## Laravel dashboard (spec v7 — خلاصه)

| بخش | وضعیت |
|-----|--------|
| 141/141 mutate handlers | ✅ |
| Module gates (xui, marketing, relay, backup) | ✅ HTTP + schedule |
| Reseller RBAC HTTP (`services.manage`, `users.bulk`) | ✅ middleware |
| CSRF Sanctum + frontend nonce cleanup | ✅ |
| User portal `/me/portal` | ✅ |
| CI: test + preflight + soak + load + frontend build | ✅ |
| Cutover evidence | `docs/evidence/` + CI artifacts |
| WP `includes/` decommission | ✅ v11 staged (`CONFIRM=1` اجرا شد) |

راهنمای عملیاتی: [RESELLER_SETUP.md](RESELLER_SETUP.md)

---

## نتیجه نهایی (Executive Verdict)

- **ربات مستقل نماینده: قابل اتکا برای فروش روزمره**  
  توکن/وب‌هوک جدا، bind خودکار مشتری، فیلتر پلن/پنل/کارت، meta مالی، رسید و اعلان‌ها با توکن نماینده (`User_Notify` + `send_message_for_reseller`).
- **داشبورد نماینده: کاربردی**  
  CRUD، scope `invited_by`، mutate policy، گیت تب با `tab_perm` هم‌تراز REST/UI.
- **استقرار:** پس از reload پلاگین، migration `2.2.4` و backfill یک‌باره (`simplevpbot_reseller_backfill_v1_done`) اجرا می‌شود.
- **عمداً خارج از scope:** قیمت عمده در checkout ربات (`plan.price` فقط)، برندینگ پیشرفته (logo/theme/domain)، audit log جدا، closure table برای scope.

---

## فاز ۱ — پایه

| موضوع | وضعیت |
|--------|--------|
| `/start` روی ربات نماینده → `invited_by` | ✅ `resolve_invited_by_for_signup` |
| `signup_reseller_svp_id` هنگام ثبت‌نام | ✅ handler-start + ستون DB |
| فیلتر پلن/پنل/دسته در ربات | ✅ `catalog_owner_ids`, `panel_allowed_in_context` |
| meta مالی checkout | ✅ `billing_reseller_svp_id`, `invoice_card_owner_scope_svp_id` |
| ادمین رسید از پروفایل ربات | ✅ `admin_ids_for_context` |
| تأیید رسید اتمیک | ✅ claim / finalize / increment_balance |

---

## فاز ۲–۳ — اعلان، لینک، امنیت متن

| موضوع | وضعیت |
|--------|--------|
| اعلان کاربر با توکن نماینده (رسید، cron، transfer) | ✅ `SimpleVPBot_User_Notify` |
| اعلان تک‌پلتفرم (فقط TG یا فقط Bale) | ✅ `platforms_for_user` |
| لینک `ref_*` per-bot | ✅ usernames در پروفایل ربات |
| رمزنگاری توکن در DB | ✅ `encrypt_token_field` / `token_for_platform` |
| متن per-reseller | ✅ `text_overrides_json` + `Texts::get_in_bot_context` |
| گیت تب receipts | ✅ `receipts.review` در App.tsx و REST |

---

## فاز ۴–۶ — backfill، داشبورد، hardening

| موضوع | وضعیت |
|--------|--------|
| backfill meta مالی تراکنش‌های قدیمی | ✅ `Reseller_Backfill` + migration خودکار |
| backfill `invited_by` از تراکنش | ✅ batch + bind دستی UI |
| فیلتر مالی داشبورد نماینده | ✅ `tx_belongs_to_reseller` |
| broadcast با توکن نماینده | ✅ `client_for_broadcast_bot` |
| cache `reseller_scope_user_ids` | ✅ transient + invalidate |
| impersonation فقط HTTPS | ✅ `route_impersonate_start` |
| پیام دستی از داشبورد | ✅ `user_admin_message` → `User_Notify` |
| fallback notify از `signup_reseller_svp_id` | ✅ وقتی `invited_by` خالی است |
| اجرای دستی backfill (ادمین) | ✅ mutate + دکمه در تب نمایندگان |

---

## شکاف‌های باقی‌مانده (اولویت پایین)

1. **قیمت rule-based / عمده در ربات** — فقط در داشبورد عمده؛ checkout = `plan.price`.
2. **مقیاس درخت بزرگ** — `IN (...)` برای scope؛ closure table پیشنهاد فاز بعد.
3. **برندینگ پیشرفته / audit log** — فاز بعد.

### بسته‌شده (فاز کم‌اولویت)

- Admin Hub روی ربات نماینده: مسدودسازی `Settings` سراسری + زیرمنوهای gen/bot/crypto/backup.
- dropdown پنل: `can_sell_plan` + غیرفعال per-panel وقتی فقط کف قیمت است.
- شارژ کیف نماینده از داشبورد: notify با `send_message_for_reseller`.

---

## چک‌لیست deploy

1. Reload/deactivate-activate پلاگین → `DB_VERSION` = `2.2.4`
2. بررسی option `simplevpbot_reseller_backfill_v1_done` = true
3. وب‌هوک HTTPS برای هر ربات نماینده
4. تست: `/start` → خرید → رسید → اعلان از همان ربات
5. تب مالی نماینده پس از backfill

---

## پوشش بررسی

`frontend/*`, `backend/app/*` (Laravel). مرجع تاریخی WP: `archive/wp-plugin` (`includes/*`).
