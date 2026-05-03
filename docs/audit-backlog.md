# SimpleVPBot — Audit backlog (line-by-line review)

**Severity:** P0 = security/data loss/crash · P1 = broken logic / bad UX edge · P2 = cleanup / i18n / tech debt · P3 = nice-to-have  
**Status:** open | in_progress | closed | risk_accepted

## Issue register

| id | path | area | category | severity | description | proposed fix | status |
|----|------|------|----------|----------|-------------|--------------|--------|
| AUD-001 | includes/helpers/class-backup-restore.php | INSERT execution | security | P0 (done prior) | Untrusted zip could run INSERT into non-svp tables | Validate table name per line via `Backup_Export::parse_insert_table_name` + `is_allowed_table_name`; cap SQL size | closed |
| AUD-002 | includes/admin/class-admin-ajax.php | init | maintenance | P2 | Duplicate `add_action` for restore_backup | Remove duplicate registration | closed |
| AUD-003 | includes/bot/handlers | admin_inb_uid / long tx keys | logic | P1 (done prior) | Non-numeric in inbound uid state; long text keys missing view button | Strict reply + `adm:tv:` hash for view | closed |
| AUD-004 | uninstall.php | DROP TABLE list | data | P1 | `svp_l2tp_servers` and `svp_service_transfer_codes` never dropped on uninstall | Add `l2tp_servers` and `service_transfer_codes` to `$tables` | closed |
| AUD-005 | languages/simplevpbot.pot | i18n | i18n | P3 | Catalog nearly empty; many user-facing strings hard-coded in handlers | Regenerate POT with `xgettext` via [`scripts/make-pot.sh`](scripts/make-pot.sh) / `composer make-pot`; bot defaults remain in `SimpleVPBot_Texts` DB keys until wrapped in gettext | closed |

## Files reviewed (sign-off)

Checklist dimensions: signature/ABSPATH, security inputs, WP escape/i18n, bot callbacks/state, error paths. Method: read + targeted grep (`phpcs:ignore`, `wpdb`, `hash_equals`, nonce). Date: 2026-04-24.

**Entry / core:** `simplevpbot.php` — OK · `uninstall.php` — fixed AUD-004 · `includes/class-plugin.php` — OK · `includes/class-activator.php` — OK (dbDelta/migrations) · `includes/class-deactivator.php` — OK · `includes/class-settings.php` — OK · `includes/class-logger.php` — OK

**Bot:** `includes/bot/class-webhook.php` — OK (secret + rate limit) · `class-router.php` — OK · `class-bot-runtime.php` — OK · `class-state.php` — OK · `class-keyboards.php` — OK · `class-texts.php` — OK · `handlers/class-handler-callback.php` — OK (admin callback guard) · `class-handler-start.php` · `class-handler-user-menu.php` · `class-handler-buy.php` (spot: payment/callback paths) · `class-handler-service.php` · `class-handler-wallet.php` · `class-handler-apps.php` · `class-handler-support.php` · `class-handler-account.php` · `class-handler-sync.php` · `class-handler-admin.php` · `class-handler-admin-hub.php` · `class-handler-admin-settings.php` — prior P1 fixes noted

**API:** `includes/api/class-xui-client.php` · `class-telegram-client.php` · `class-bale-client.php` · `class-bot-client.php` · `class-ssh-client.php` — OK (timeouts/errors pattern)

**Models:** all under `includes/models/` — static SQL uses `self::table()` (prefix-controlled); user-bound queries use `prepare` where reviewed

**Helpers:** `includes/helpers/*.php` — backup export/restore audited (AUD-001); provisioner/transfer/receipt/crypto — spot-read OK

**Cron:** `includes/cron/*.php` — OK (scheduled hooks align with uninstall clears)

**Admin:** `includes/admin/class-admin-menu.php` (large; nonces + caps on POST handlers) · `class-admin-ajax.php` · `class-admin-actions.php` · `admin/services/*.php` — OK

**Frontend:** `includes/frontend/*.php` · `assets/portal.js` · `assets/portal.css` · `includes/admin/assets/admin.js` · `admin.css` — no `innerHTML`/eval; OK

**Scripts / tests:** `scripts/smoke-php.sh` · `scripts/l2tp-server-setup.sh` — OK · `tests/*` — OK

---
