<?php
/**
 * Minimal smoke tests when PHPUnit cannot run (missing ext-dom/xml).
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

$root = dirname( __DIR__ );

$fail = static function ( string $msg ) use ( $root ): void {
	fwrite( STDERR, "FAIL: {$msg}\n" );
	exit( 1 );
};

foreach (
	array(
		$root . '/simplevpbot.php',
		$root . '/includes/class-plugin.php',
		$root . '/includes/helpers/class-backup-export.php',
		$root . '/includes/helpers/class-backup-restore.php',
		$root . '/includes/helpers/class-backup-merge-restore.php',
		$root . '/includes/helpers/class-admin-user-ops.php',
		$root . '/includes/helpers/class-feature-l2tp.php',
		$root . '/includes/helpers/class-telegram-http.php',
		$root . '/includes/models/class-model-log.php',
		$root . '/includes/frontend/class-portal-admin.php',
		$root . '/dashboard-ui/src/components/dashboard-site-settings-admin.tsx',
	) as $f
) {
	if ( ! is_file( $f ) ) {
		$fail( "missing file: {$f}" );
	}
}

$line = "INSERT INTO `wp_svp_users` (`id`) VALUES (1);";
if ( ! preg_match( '/^\s*INSERT\s+INTO\s+`([^`]+)`/i', trim( $line ), $m ) || 'wp_svp_users' !== $m[1] ) {
	$fail( 'INSERT table parse' );
}

$settings = (string) file_get_contents( $root . '/includes/class-settings.php' );
if ( false === strpos( $settings, "'l2tp_enabled'" ) ) {
	$fail( 'settings missing l2tp_enabled' );
}
$feat = (string) file_get_contents( $root . '/includes/helpers/class-feature-l2tp.php' );
if ( false === strpos( $feat, 'function filter_plans' ) ) {
	$fail( 'Feature_L2tp missing filter_plans' );
}
$prov = (string) file_get_contents( $root . '/includes/helpers/class-service-provisioner.php' );
if ( false === strpos( $prov, 'l2tp_disabled' ) ) {
	$fail( 'provisioner missing l2tp_disabled guard' );
}

$rest = (string) file_get_contents( $root . '/includes/api/class-rest-dashboard.php' );
if ( false === strpos( $rest, '/dashboard/admin/logs' ) ) {
	$fail( 'REST missing admin logs route' );
}
if ( false === strpos( $rest, '/dashboard/admin/audit' ) ) {
	$fail( 'REST missing admin audit route' );
}
$activator = (string) file_get_contents( $root . '/includes/class-activator.php' );
if ( false === strpos( $activator, "DB_VERSION = '2.3.2'" ) ) {
	$fail( 'activator missing DB_VERSION 2.3.2' );
}
if ( false === strpos( $activator, 'maybe_migrate_232_receipt_stored_image' ) ) {
	$fail( 'activator missing receipt stored_image_path migration' );
}
if ( false === strpos( $activator, 'maybe_migrate_231_bulk_panel_items' ) ) {
	$fail( 'activator missing maybe_migrate_231_bulk_panel_items' );
}
if ( ! is_file( $root . '/includes/helpers/class-reseller-closure.php' ) ) {
	$fail( 'missing class-reseller-closure.php' );
}
if ( ! is_file( $root . '/includes/helpers/class-audit-log.php' ) ) {
	$fail( 'missing class-audit-log.php' );
}
if ( ! is_file( $root . '/includes/helpers/class-branding-resolver.php' ) ) {
	$fail( 'missing class-branding-resolver.php' );
}
if ( ! is_file( $root . '/dashboard-ui/src/components/dashboard-audit-admin.tsx' ) ) {
	$fail( 'missing dashboard-audit-admin.tsx' );
}
$rest_backup = (string) file_get_contents( $root . '/includes/api/class-rest-dashboard.php' );
if ( false === strpos( $rest_backup, '/dashboard/admin/backups' ) ) {
	$fail( 'REST missing admin backups route' );
}
if ( false === strpos( $rest_backup, '/dashboard/admin/panel/rebuild-from-db' ) ) {
	$fail( 'REST missing panel rebuild-from-db route' );
}
$panel_rebuild = (string) file_get_contents( $root . '/includes/helpers/class-service-panel-rebuild.php' );
if ( false === strpos( $panel_rebuild, 'rebuild_all' ) || false === strpos( $panel_rebuild, 'dry_run' ) ) {
	$fail( 'Service_Panel_Rebuild missing rebuild_all/dry_run' );
}
if ( false === strpos( (string) file_get_contents( $root . '/includes/helpers/class-service-provisioner.php' ), 'add_panel_client_from_service_row' ) ) {
	$fail( 'Service_Provisioner missing add_panel_client_from_service_row' );
}
if ( false === strpos( (string) file_get_contents( $root . '/includes/helpers/class-service-renew.php' ), 'sync_service_row_to_panel' ) ) {
	$fail( 'Service_Renew missing sync_service_row_to_panel' );
}
$backup_ui = (string) file_get_contents( $root . '/dashboard-ui/src/components/dashboard-backup-admin.tsx' );
if ( false === strpos( $backup_ui, 'rebuild-from-db' ) ) {
	$fail( 'dashboard-backup-admin missing panel rebuild UI' );
}
$export = (string) file_get_contents( $root . '/includes/helpers/class-backup-export.php' );
if ( false === strpos( $export, 'list_site_backup_files' ) ) {
	$fail( 'Backup_Export missing list_site_backup_files' );
}
if ( false === strpos( $export, 'parse_sql_dump' ) ) {
	$fail( 'Backup_Export missing parse_sql_dump' );
}
$restore = (string) file_get_contents( $root . '/includes/helpers/class-backup-restore.php' );
if ( false !== strpos( $restore, 'TRUNCATE TABLE' ) ) {
	$fail( 'Backup_Restore must not TRUNCATE tables' );
}
if ( false === strpos( $restore, 'Backup_Merge_Restore' ) ) {
	$fail( 'Backup_Restore must delegate to Backup_Merge_Restore' );
}
if ( false === strpos( $restore, 'restore_panel_dbs_from_zip' ) ) {
	$fail( 'Backup_Restore missing panel DB import path' );
}
if ( false === strpos( $backup_ui, 'restore_panel_db' ) ) {
	$fail( 'dashboard-backup-admin missing restore_panel_db UI' );
}
if ( false === strpos( $backup_ui, 'inbound-map' ) ) {
	$fail( 'dashboard-backup-admin missing inbound-map UI' );
}
$inbound_map = (string) file_get_contents( $root . '/includes/helpers/class-service-panel-inbound-map.php' );
if ( false === strpos( $inbound_map, 'suggest_map' ) ) {
	$fail( 'Service_Panel_Inbound_Map missing suggest_map' );
}
$merge = (string) file_get_contents( $root . '/includes/helpers/class-backup-merge-restore.php' );
if ( false === strpos( $merge, 'find_by_telegram' ) || false === strpos( $merge, 'ambiguous_identity' ) ) {
	$fail( 'Backup_Merge_Restore missing tg/bale/wp user match' );
}
if ( ! is_file( $root . '/dashboard-ui/src/components/dashboard-backup-admin.tsx' ) ) {
	$fail( 'missing dashboard-backup-admin.tsx' );
}
$actions = (string) file_get_contents( $root . '/includes/admin/class-admin-actions.php' );
if ( false === strpos( $actions, "case 'whitelabel':" ) ) {
	$fail( 'apply_settings_tab missing whitelabel' );
}
$xui = (string) file_get_contents( $root . '/includes/api/class-xui-client.php' );
if ( false === strpos( $xui, 'import_db_from_path' ) ) {
	$fail( 'Xui_Client missing import_db_from_path' );
}
if ( false === strpos( $xui, 'login_legacy_cookie' ) ) {
	$fail( 'Xui_Client missing legacy cookie login fallback' );
}
if ( false === strpos( $xui, "'loginSecret'" ) ) {
	$fail( 'Xui_Client missing loginSecret for legacy panels' );
}
$cfg = (string) file_get_contents( $root . '/includes/helpers/class-config-link.php' );
if ( false === strpos( $cfg, 'flatten_subscription_uri_lines' ) ) {
	$fail( 'Config_Link missing flatten_subscription_uri_lines' );
}
$handler = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-service.php' );
if ( false !== strpos( $handler, 'telegram_send_followup_config_lines' ) ) {
	$fail( 'Handler_Service must not send duplicate telegram config follow-up messages' );
}
if ( false === strpos( $handler, 'uri_fragment_label' ) ) {
	$fail( 'Handler_Service caption should use uri_fragment_label' );
}
if ( false === strpos( $cfg, 'uri_fragment_label' ) ) {
	$fail( 'Config_Link missing uri_fragment_label' );
}
$portal = (string) file_get_contents( $root . '/includes/frontend/class-shortcode-portal.php' );
if ( false === strpos( $portal, 'foreach ( $uris as $uri )' ) ) {
	$fail( 'Shortcode portal missing multi-uri loop' );
}
if ( false === strpos( $portal, 'uri_fragment_label' ) ) {
	$fail( 'Shortcode portal should label configs via uri_fragment_label' );
}
$cron_bulk = (string) file_get_contents( $root . '/includes/cron/class-cron-users-bulk.php' );
if ( false === strpos( $cron_bulk, 'users_bulk_maybe_notify_service_op' ) ) {
	$fail( 'Cron_Users_Bulk missing service-op notify' );
}
$mut_bulk = (string) file_get_contents( $root . '/includes/admin/class-dashboard-admin-mutations.php' );
if ( false === strpos( $mut_bulk, 'users_bulk_notify_fields' ) ) {
	$fail( 'Dashboard mutations missing users_bulk_notify_fields' );
}
$reconcile = (string) file_get_contents( $root . '/includes/helpers/class-service-reconcile.php' );
if ( false === strpos( $reconcile, 'reconcile_for_user' ) ) {
	$fail( 'Service_Reconcile missing reconcile_for_user' );
}
$linker_r = (string) file_get_contents( $root . '/includes/helpers/class-inbound-linker.php' );
if ( false === strpos( $linker_r, 'resolve_user_id_from_panel_client' ) ) {
	$fail( 'Inbound_Linker missing resolve_user_id_from_panel_client' );
}
$start_r = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-start.php' );
if ( false === strpos( $start_r, 'Service_Reconcile::reconcile_for_user' ) ) {
	$fail( 'Handler_Start missing service reconcile on login' );
}
$renew_bulk = (string) file_get_contents( $root . '/includes/helpers/class-service-renew.php' );
if ( false === strpos( $renew_bulk, 'apply_panel_volume_delta' ) ) {
	$fail( 'Service_Renew missing apply_panel_volume_delta' );
}
if ( false === strpos( $xui, 'build_update_client_settings_payload' ) ) {
	$fail( 'Xui_Client missing 3x-ui v2.9.4 single-client updateClient payload builder' );
}
if ( false === strpos( $xui, 'resolve_client_path_id_for_update' ) ) {
	$fail( 'Xui_Client missing protocol-aware resolve_client_path_id_for_update' );
}
$buy = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-buy.php' );
if ( false === strpos( $buy, 'send_admin_receipt_photo_review' ) ) {
	$fail( 'Handler_Buy missing send_admin_receipt_photo_review' );
}
$hub = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-hub.php' );
if ( false === strpos( $hub, 'receipt_review_lock_key' ) ) {
	$fail( 'Handler_Admin_Hub missing receipt_review_lock_key anti-spam lock' );
}
$mut = (string) file_get_contents( $root . '/includes/admin/class-dashboard-admin-mutations.php' );
if ( false !== strpos( $mut, 'amount_locked' ) ) {
	$fail( 'Dashboard mutations must not block approved receipt amount edits with amount_locked' );
}
if ( false === strpos( $mut, 'adjust_receipt_amount' ) ) {
	$fail( 'Dashboard mutations missing adjust_receipt_amount' );
}
if ( false !== strpos( $mut, '$new <= 0' ) && false !== strpos( $mut, 'bad_amount' ) ) {
	$fail( 'adjust_receipt_amount must reject negative amounts only ($new < 0), not zero' );
}
if ( false === strpos( $mut, '$new < 0' ) ) {
	$fail( 'adjust_receipt_amount must use $new < 0 for bad_amount' );
}
$caption = (string) file_get_contents( $root . '/includes/helpers/class-bot-admin-user-caption.php' );
if ( false === strpos( $caption, 'is_zero_toman' ) || false === strpos( $caption, 'msg.admin.caption.amount_line_free' ) ) {
	$fail( 'Bot_Admin_User_Caption missing zero-amount free line (is_zero_toman / amount_line_free)' );
}
$text_defaults = (string) file_get_contents( $root . '/includes/class-bot-text-defaults-extended.php' );
if ( false === strpos( $text_defaults, 'msg.admin.caption.amount_line_free' ) ) {
	$fail( 'Bot text defaults missing msg.admin.caption.amount_line_free' );
}
$persian = (string) file_get_contents( $root . '/includes/helpers/class-bot-persian-text.php' );
if ( false === strpos( $persian, 'function is_zero_toman' ) ) {
	$fail( 'Bot_Persian_Text missing is_zero_toman' );
}
if ( false === strpos( $caption, "case 'add_volume'" ) || false === strpos( $caption, 'extra_gb' ) ) {
	$fail( 'Bot_Admin_User_Caption missing add_volume extra_gb line' );
}
if ( false === strpos( $mut_bulk, 'users_bulk_resolve_panel_targets' ) ) {
	$fail( 'Dashboard mutations missing users_bulk_resolve_panel_targets' );
}
if ( false === strpos( $cron_bulk, 'run_one_panel_item' ) ) {
	$fail( 'Cron_Users_Bulk missing run_one_panel_item' );
}
$bulk_ui = (string) file_get_contents( $root . '/dashboard-ui/src/components/dashboard-users-bulk-admin.tsx' );
if ( false === strpos( $bulk_ui, 'panel_active_clients' ) ) {
	$fail( 'dashboard-users-bulk-admin missing panel_active_clients scope' );
}
if ( ! is_file( $root . '/includes/helpers/class-card-rotation.php' ) ) {
	$fail( 'missing class-card-rotation.php' );
}
$card_rot = (string) file_get_contents( $root . '/includes/helpers/class-card-rotation.php' );
if ( false === strpos( $card_rot, 'pick_round_robin' ) || false === strpos( $card_rot, 'pick_random' ) ) {
	$fail( 'Card_Rotation missing loop/random pickers' );
}
$card_model = (string) file_get_contents( $root . '/includes/models/class-model-card.php' );
if ( false === strpos( $card_model, 'Card_Rotation::pick_for_checkout' ) ) {
	$fail( 'Model_Card must delegate to Card_Rotation' );
}
$settings_cards = (string) file_get_contents( $root . '/includes/class-settings.php' );
if ( false === strpos( $settings_cards, 'cards_rotation_cursors' ) ) {
	$fail( 'settings missing cards_rotation_cursors default' );
}
$cards_ui = (string) file_get_contents( $root . '/dashboard-ui/src/components/dashboard-cards-admin.tsx' );
if ( false === strpos( $cards_ui, 'value="random"' ) ) {
	$fail( 'dashboard-cards-admin missing random display mode' );
}
$rbp = (string) file_get_contents( $root . '/includes/models/class-model-reseller-bot-profile.php' );
if ( false === strpos( $rbp, 'public static function upsert_tokens' ) ) {
	$fail( 'Reseller_Bot_Profile missing upsert_tokens method' );
}
$plans_ui = (string) file_get_contents( $root . '/dashboard-ui/src/components/dashboard-plans-admin.tsx' );
if ( false === strpos( $plans_ui, 'formatPlanMutateError' ) || false === strpos( $plans_ui, 'validationCategory' ) ) {
	$fail( 'dashboard-plans-admin missing plan validation error helpers' );
}
if ( ! is_file( $root . '/includes/helpers/class-support-contacts.php' ) ) {
	$fail( 'missing class-support-contacts.php' );
}
if ( false === strpos( $settings, "'support_info'" ) || false === strpos( $actions, "'support_telegram_username'" ) ) {
	$fail( 'support settings keys or whitelabel save missing' );
}
$whitelabel_ui = (string) file_get_contents( $root . '/dashboard-ui/src/components/site-settings/site-settings-whitelabel-tab.tsx' );
if ( false === strpos( $whitelabel_ui, 'whitelabel-support' ) || false === strpos( $whitelabel_ui, 'ImageUrlField' ) ) {
	$fail( 'whitelabel tab missing support section or image upload' );
}
$sidebar_ui = (string) file_get_contents( $root . '/dashboard-ui/src/components/app-sidebar.tsx' );
if ( false === strpos( $sidebar_ui, 'whitelabel-support' ) ) {
	$fail( 'app-sidebar support link not wired to whitelabel section' );
}
$dt_picker = (string) file_get_contents( $root . '/dashboard-ui/src/components/dashboard-datetime-picker.tsx' );
if ( false !== strpos( $dt_picker, 'JalaliDateTimeFields' ) || false !== strpos( $dt_picker, 'datetime-local' ) ) {
	$fail( 'dashboard-datetime-picker still uses legacy dropdown/datetime-local' );
}
if ( ! is_file( $root . '/dashboard-ui/src/components/ui/calendar.tsx' ) || ! is_file( $root . '/dashboard-ui/src/components/ui/popover.tsx' ) ) {
	$fail( 'missing shadcn calendar or popover' );
}
$configs_ui = (string) file_get_contents( $root . '/dashboard-ui/src/components/dashboard-configs-admin.tsx' );
if ( false !== strpos( $configs_ui, 'ConfigJalaliExpiryFields' ) || false !== strpos( $configs_ui, 'datetime-local' ) ) {
	$fail( 'configs-admin still uses legacy jalali dropdown or datetime-local' );
}
$caption = (string) file_get_contents( $root . '/includes/helpers/class-bot-admin-user-caption.php' );
if ( false === strpos( $caption, 'function card_deposit_line' ) || false === strpos( $caption, 'function invited_by_line' ) ) {
	$fail( 'Bot_Admin_User_Caption missing card_deposit_line or invited_by_line' );
}
if ( false === strpos( $caption, '💳 کارت واریز:' ) || false === strpos( $caption, '🔗 با لینک کسب درآمد از طرف' ) ) {
	$fail( 'Bot_Admin_User_Caption missing receipt card or membership referral lines' );
}
if ( ! is_file( $root . '/tests/BotAdminUserCaptionTest.php' ) ) {
	$fail( 'missing BotAdminUserCaptionTest.php' );
}
$receipt_delivery = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-buy.php' );
if ( false === strpos( $receipt_delivery, 'notify_admin_receipt_photo_fallback' ) || false === strpos( $receipt_delivery, 'download_bot_file_to_path' ) ) {
	$fail( 'receipt photo fallback or secure download missing in Handler_Buy' );
}
$receipt_cb = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-callback.php' );
if ( false === strpos( $receipt_cb, 'admin_feedback_text' ) || false === strpos( $receipt_cb, 'finalize_clicked_admin_message' ) ) {
	$fail( 'receipt callback missing admin feedback wiring' );
}
if ( ! is_file( $root . '/tests/ReceiptDeliveryContractsTest.php' ) ) {
	$fail( 'missing ReceiptDeliveryContractsTest.php' );
}
$receipts_ui = (string) file_get_contents( $root . '/dashboard-ui/src/components/dashboard-receipts-admin.tsx' );
if ( false === strpos( $receipts_ui, 'setPreviewReceipt' ) || false === strpos( $receipts_ui, 'clickToEnlarge' ) ) {
	$fail( 'dashboard receipts missing thumbnail preview popup' );
}
if ( ! is_file( $root . '/includes/helpers/class-receipt-image-store.php' ) ) {
	$fail( 'missing class-receipt-image-store.php' );
}
if ( ! is_file( $root . '/tests/ReceiptImageStoreTest.php' ) ) {
	$fail( 'missing ReceiptImageStoreTest.php' );
}

echo "OK run-smoke-tests.php\n";
exit( 0 );
