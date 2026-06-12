# گزارش جامع وضعیت پنل نماینده و ربات مستقل

**آخرین به‌روزرسانی:** **Laravel backend v8** — NavTabsBuilder parity، RBAC `configs_client_*`، webhook drain IP gate، module boot topological، `BrandingResolver` + CSS vars editor، settings_tab flat mirror، impersonate stop admin-only، CI load 100 + alert-smoke + deploy artifact workflow، acceptance/mutate tests گسترش‌یافته.

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
| WP `includes/` decommission | ⏳ پس از `CONFIRM=1` اپراتور |

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

`frontend/*`, `includes/api/*`, `includes/admin/*`, `includes/bot/*`, `includes/models/*`, `includes/helpers/class-bot-reseller-scope.php`, `class-user-notify.php`, `class-reseller-backfill.php`
