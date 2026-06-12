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

$php_lint = static function ( string $rel ) use ( $root, $fail ): void {
	$path = $root . '/' . ltrim( $rel, '/' );
	if ( ! is_file( $path ) ) {
		$fail( "php -l missing file: {$rel}" );
	}
	$out = array();
	$code = 0;
	exec( 'php -l ' . escapeshellarg( $path ) . ' 2>&1', $out, $code );
	if ( 0 !== $code ) {
		$fail( 'php -l ' . $rel . ': ' . implode( ' ', $out ) );
	}
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
		$root . '/includes/helpers/class-telegram-relay.php',
		$root . '/includes/models/class-model-log.php',
		$root . '/includes/frontend/class-portal-admin.php',
		$root . '/frontend/src/components/dashboard-site-settings-admin.tsx',
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
if ( false === strpos( $activator, "DB_VERSION = '2.4.6'" ) ) {
	$fail( 'activator missing DB_VERSION 2.4.6' );
}
if ( false === strpos( $activator, 'maybe_migrate_246_panel_api_flavor' ) ) {
	$fail( 'activator missing panel_api_flavor migration' );
}
$xui = (string) file_get_contents( $root . '/includes/api/class-xui-client.php' );
if ( false === strpos( $xui, 'function detect_api_flavor' ) ) {
	$fail( 'Xui_Client missing detect_api_flavor' );
}
if ( false === strpos( $xui, 'function client_create_v3' ) ) {
	$fail( 'Xui_Client missing client_create_v3' );
}
if ( false === strpos( $xui, 'function request_routed' ) ) {
	$fail( 'Xui_Client missing request_routed' );
}
if ( false === strpos( $xui, 'function parse_client_ips_response' ) ) {
	$fail( 'Xui_Client missing parse_client_ips_response' );
}
if ( false === strpos( $xui, 'function clients_for_inbound_id' ) ) {
	$fail( 'Xui_Client missing clients_for_inbound_id' );
}
if ( false === strpos( $xui, 'function parse_onlines_response' ) ) {
	$fail( 'Xui_Client missing parse_onlines_response' );
}
if ( false === strpos( $xui, 'function fetch_onlines' ) ) {
	$fail( 'Xui_Client missing fetch_onlines' );
}
$panel_live = (string) file_get_contents( $root . '/includes/helpers/class-dashboard-panel-live.php' );
if ( false === strpos( $panel_live, 'fetch_onlines' ) ) {
	$fail( 'Dashboard_Panel_Live missing fetch_onlines for onlineNow' );
}
$renew = (string) file_get_contents( $root . '/includes/helpers/class-service-renew.php' );
if ( ! preg_match( '/apply_add_user_slots_after_payment[\s\S]{0,2500}client_update_v3/', $renew ) ) {
	$fail( 'Service_Renew apply_add_user_slots missing v3 client_update_v3 branch' );
}
$admin_ops = (string) file_get_contents( $root . '/includes/admin/services/class-service-admin-ops.php' );
if ( ! preg_match( '/clients\/onlines[\s\S]{0,800}clients_onlines/', $admin_ops ) ) {
	$fail( 'test_panel missing v3 clients/onlines probe' );
}
if ( ! preg_match( '/configs_apply_enable_logged_in[\s\S]{0,2000}client_update_v3/', $admin_ops ) ) {
	$fail( 'configs_apply_enable_logged_in missing v3 client_update_v3 branch' );
}
$dash_panel = (string) file_get_contents( $root . '/includes/helpers/class-service-dashboard-panel.php' );
if ( ! preg_match( '/function xray_set_limit_ip[\s\S]{0,2500}client_update_v3/', $dash_panel ) ) {
	$fail( 'xray_set_limit_ip missing v3 client_update_v3 branch' );
}
if ( false === strpos( $dash_panel, 'xray_regenerate_sub_id' ) ) {
	$fail( 'Service_Dashboard_Panel missing xray_regenerate_sub_id' );
}
$mutations = (string) file_get_contents( $root . '/includes/admin/class-dashboard-admin-mutations.php' );
if ( false === strpos( $mutations, 'service_regen_sub_id' ) ) {
	$fail( 'dashboard mutations missing service_regen_sub_id' );
}
$handler_svc = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-service.php' );
if ( false === strpos( $handler_svc, "if ( 'rs' === \$action )" ) ) {
	$fail( 'Handler_Service missing rs subId regen action' );
}
if ( false === strpos( $handler_svc, 'parse_client_ips_response' ) ) {
	$fail( 'Handler_Service missing parse_client_ips_response for ip action' );
}
$linker = (string) file_get_contents( $root . '/includes/helpers/class-inbound-linker.php' );
if ( false === strpos( $linker, 'clients_for_inbound_id' ) ) {
	$fail( 'Inbound_Linker missing clients_for_inbound_id' );
}
if ( ! is_file( $root . '/tests/XuiClientV3CompatTest.php' ) ) {
	$fail( 'missing XuiClientV3CompatTest.php' );
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
if ( ! is_file( $root . '/frontend/src/components/dashboard-audit-admin.tsx' ) ) {
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
$backup_ui = (string) file_get_contents( $root . '/frontend/src/components/dashboard-backup-admin.tsx' );
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
if ( ! is_file( $root . '/frontend/src/components/dashboard-backup-admin.tsx' ) ) {
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
if ( false === strpos( $portal, 'foreach ( $uris as' ) ) {
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
$pnl = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-pnl.php' );
$receipts_handler = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-receipts.php' );
if ( false === strpos( $receipts_handler, 'review_lock_key' ) ) {
	$fail( 'Handler_Admin_Receipts missing review_lock_key anti-spam lock' );
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
if ( false === strpos( $caption, 'add_volume' ) || false === strpos( $caption, 'extra_gb' ) ) {
	$fail( 'Bot_Admin_User_Caption missing add_volume extra_gb line' );
}
if ( false === strpos( $mut_bulk, 'users_bulk_resolve_panel_targets' ) ) {
	$fail( 'Dashboard mutations missing users_bulk_resolve_panel_targets' );
}
if ( false === strpos( $cron_bulk, 'run_one_panel_item' ) ) {
	$fail( 'Cron_Users_Bulk missing run_one_panel_item' );
}
$bulk_ui = (string) file_get_contents( $root . '/frontend/src/components/dashboard-users-bulk-admin.tsx' );
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
$cards_ui = (string) file_get_contents( $root . '/frontend/src/components/dashboard-cards-admin.tsx' );
if ( false === strpos( $cards_ui, '"random"' ) || false === strpos( $cards_ui, 'displayModeRandom' ) ) {
	$fail( 'dashboard-cards-admin missing random display mode' );
}
$rbp = (string) file_get_contents( $root . '/includes/models/class-model-reseller-bot-profile.php' );
if ( false === strpos( $rbp, 'public static function upsert_tokens' ) ) {
	$fail( 'Reseller_Bot_Profile missing upsert_tokens method' );
}
$plans_ui = (string) file_get_contents( $root . '/frontend/src/components/dashboard-plans-admin.tsx' );
if ( false === strpos( $plans_ui, 'formatPlanMutateError' ) || false === strpos( $plans_ui, 'validationCategory' ) ) {
	$fail( 'dashboard-plans-admin missing plan validation error helpers' );
}
if ( ! is_file( $root . '/includes/helpers/class-support-contacts.php' ) ) {
	$fail( 'missing class-support-contacts.php' );
}
if ( false === strpos( $settings, "'support_info'" ) || false === strpos( $actions, "'support_telegram_username'" ) ) {
	$fail( 'support settings keys or whitelabel save missing' );
}
$whitelabel_ui = (string) file_get_contents( $root . '/frontend/src/components/site-settings/site-settings-whitelabel-tab.tsx' );
if ( false === strpos( $whitelabel_ui, 'whitelabel-support' ) || false === strpos( $whitelabel_ui, 'ImageUrlField' ) ) {
	$fail( 'whitelabel tab missing support section or image upload' );
}
$sidebar_ui = (string) file_get_contents( $root . '/frontend/src/components/app-sidebar.tsx' );
if ( false === strpos( $sidebar_ui, 'whitelabel-support' ) ) {
	$fail( 'app-sidebar support link not wired to whitelabel section' );
}
$dt_picker = (string) file_get_contents( $root . '/frontend/src/components/dashboard-datetime-picker.tsx' );
if ( false !== strpos( $dt_picker, 'JalaliDateTimeFields' ) || false !== strpos( $dt_picker, 'datetime-local' ) ) {
	$fail( 'dashboard-datetime-picker still uses legacy dropdown/datetime-local' );
}
if ( ! is_file( $root . '/frontend/src/components/ui/calendar.tsx' ) || ! is_file( $root . '/frontend/src/components/ui/popover.tsx' ) ) {
	$fail( 'missing shadcn calendar or popover' );
}
$configs_ui = (string) file_get_contents( $root . '/frontend/src/components/dashboard-configs-admin.tsx' );
if ( false !== strpos( $configs_ui, 'ConfigJalaliExpiryFields' ) || false !== strpos( $configs_ui, 'datetime-local' ) ) {
	$fail( 'configs-admin still uses legacy jalali dropdown or datetime-local' );
}
$caption = (string) file_get_contents( $root . '/includes/helpers/class-bot-admin-user-caption.php' );
if ( false === strpos( $caption, 'function card_deposit_line' ) || false === strpos( $caption, 'function invited_by_line' ) ) {
	$fail( 'Bot_Admin_User_Caption missing card_deposit_line or invited_by_line' );
}
if ( false === strpos( $caption, '💳 کارت واریز:' ) || false === strpos( $caption, 'invited_by_line' ) ) {
	$fail( 'Bot_Admin_User_Caption missing receipt card or membership referral lines' );
}
if ( ! is_file( $root . '/tests/BotAdminUserCaptionTest.php' ) ) {
	$fail( 'missing BotAdminUserCaptionTest.php' );
}
$receipt_delivery = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-buy.php' );
if ( false === strpos( $receipt_delivery, 'send_admin_receipt_photo_ladder' ) || false === strpos( $receipt_delivery, 'notify_admin_receipt_photo_fallback' ) ) {
	$fail( 'Handler_Buy missing single-message receipt admin delivery ladder' );
}
if ( false !== strpos( $receipt_delivery, 'photo without caption' ) ) {
	$fail( 'Handler_Buy must not accept photo without caption as delivered receipt' );
}
if ( false === strpos( $caption, 'receipt_new_caption_for_platform' ) || false === strpos( $caption, 'fit_receipt_caption_for_photo' ) ) {
	$fail( 'Bot_Admin_User_Caption missing photo caption helpers' );
}
$receipt_cb = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-callback.php' );
if ( false === strpos( $receipt_cb, 'admin_feedback_text' ) || false === strpos( $receipt_cb, 'finalize_clicked_admin_message' ) ) {
	$fail( 'receipt callback missing admin feedback wiring' );
}
if ( ! is_file( $root . '/tests/ReceiptDeliveryContractsTest.php' ) ) {
	$fail( 'missing ReceiptDeliveryContractsTest.php' );
}
$receipts_ui = (string) file_get_contents( $root . '/frontend/src/components/dashboard-receipts-admin.tsx' );
$receipts_list = (string) file_get_contents( $root . '/frontend/src/components/dashboard-receipts-list.tsx' );
$receipts_src = $receipts_ui . $receipts_list;
if ( false === strpos( $receipts_src, 'setPreviewReceipt' ) || false === strpos( $receipts_src, 'clickToEnlarge' ) ) {
	$fail( 'dashboard receipts missing thumbnail preview popup' );
}
if ( ! is_file( $root . '/includes/helpers/class-receipt-image-store.php' ) ) {
	$fail( 'missing class-receipt-image-store.php' );
}
if ( ! is_file( $root . '/tests/ReceiptImageStoreTest.php' ) ) {
	$fail( 'missing ReceiptImageStoreTest.php' );
}

$rest_dash = (string) file_get_contents( $root . '/includes/api/class-rest-dashboard.php' );
if ( false === strpos( $rest_dash, 'reseller_may_request_admin_tab' ) || false === strpos( $rest_dash, 'forbidden_tab' ) ) {
	$fail( 'REST missing reseller activeTab validation (L-2)' );
}
$admin_ajax = (string) file_get_contents( $root . '/includes/admin/class-admin-ajax.php' );
if ( preg_match( '/function receipt_image[\s\S]*Settings::get\(\s*\$is_bale\s*\?\s*[\'"]bale_token/', $admin_ajax ) ) {
	$fail( 'receipt_image still falls back to global bot token (L-3)' );
}
if ( false === strpos( $export, 'redact_plugin_settings_for_export' ) ) {
	$fail( 'Backup_Export missing plugin settings redaction (S-5)' );
}
$admin_nav = (string) file_get_contents( $root . '/frontend/src/config/admin-nav.ts' );
if ( false === strpos( $admin_nav, '"notifications"' ) || false === strpos( $admin_nav, '"logs"' ) ) {
	$fail( 'admin-nav missing notifications/logs in ADMIN_ONLY_TAB_KEYS (L-1)' );
}
$user_detail = (string) file_get_contents( $root . '/frontend/src/components/dashboard-user-detail-admin.tsx' );
if ( false === strpos( $user_detail, 'canManageUsers' ) || false === strpos( $user_detail, 'actorPermissions' ) ) {
	$fail( 'user detail missing permission gates (N-3)' );
}
if ( ! is_file( $root . '/tests/ResellerIdorIntegrationTest.php' ) ) {
	$fail( 'missing ResellerIdorIntegrationTest.php' );
}
if ( ! is_file( $root . '/tests/ResellerStagingContractTest.php' ) ) {
	$fail( 'missing ResellerStagingContractTest.php' );
}
$portal = (string) file_get_contents( $root . '/includes/helpers/class-portal-link.php' );
if ( false === strpos( $portal, 'ADMIN_TTL' ) ) {
	$fail( 'portal admin TTL missing (H-1)' );
}
if ( false === strpos( $portal, 'CUSTOMER_TTL' ) || false === strpos( $portal, '31536000' ) ) {
	$fail( 'portal CUSTOMER_TTL missing (unified subscription)' );
}
$portal_sub = (string) file_get_contents( $root . '/includes/helpers/class-portal-subscription.php' );
if ( false === strpos( $portal_sub, 'maybe_serve' ) || false === strpos( $portal_sub, 'is_browser_request' ) ) {
	$fail( 'Portal_Subscription missing maybe_serve / is_browser_request' );
}
$portal_front = (string) file_get_contents( $root . '/includes/frontend/class-portal-front.php' );
if ( false === strpos( $portal_front, "Portal_Subscription', 'maybe_serve'" ) ) {
	$fail( 'Portal_Front missing subscription template_redirect hook' );
}
if ( ! is_file( $root . '/tests/PortalSubscriptionTest.php' ) ) {
	$fail( 'missing PortalSubscriptionTest.php' );
}
$webhook = (string) file_get_contents( $root . '/includes/bot/class-webhook.php' );
if ( false === strpos( $webhook, 'rate_limit_ok_for_reseller' ) ) {
	$fail( 'webhook missing per-reseller rate limit (R-1)' );
}
if ( false === strpos( $webhook, 'HTTP_X_SVP_WEBHOOK_SECRET' ) ) {
	$fail( 'webhook missing header secret auth (W-1)' );
}
$profile = (string) file_get_contents( $root . '/includes/models/class-model-reseller-bot-profile.php' );
if ( false === strpos( $profile, 'webhook_secret_plaintext' ) ) {
	$fail( 'reseller bot profile missing webhook secret encryption (E-3)' );
}
$mkt = (string) file_get_contents( $root . '/frontend/src/components/dashboard-marketing-lifecycle-admin.tsx' );
if ( ! preg_match( '/canMutate\s*\?[\s\S]*saveRule/', $mkt ) ) {
	$fail( 'marketing lifecycle saveRule not gated (N-4)' );
}
if ( ! is_file( $root . '/includes/helpers/class-bot-admin-nav.php' ) ) {
	$fail( 'missing class-bot-admin-nav.php (Bot-Panel)' );
}
if ( ! is_file( $root . '/includes/helpers/class-reseller-permission-gate.php' ) ) {
	$fail( 'missing class-reseller-permission-gate.php (Bot-Panel)' );
}
$router = (string) file_get_contents( $root . '/includes/bot/class-router.php' );
if ( false === strpos( $router, "'panel'" ) || false === strpos( $router, 'send_panel_entry' ) ) {
	$fail( 'router missing /panel admin entry (Bot-Panel)' );
}
if ( false !== strpos( $router, "'admin'" ) ) {
	$fail( 'router must not alias /admin (Bot-Panel-Audit)' );
}
$start = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-start.php' );
if ( false === strpos( $start, 'admin_mode' ) || false === strpos( $start, 'State::clear' ) ) {
	$fail( '/start must clear admin_mode (Bot-Panel-Audit)' );
}
if ( ! is_file( $root . '/tests/BotPanelToggleTest.php' ) ) {
	$fail( 'missing BotPanelToggleTest.php' );
}
$bot_nav = (string) file_get_contents( $root . '/includes/helpers/class-bot-admin-nav.php' );
if ( false === strpos( $bot_nav, "'resellers'" ) || false === strpos( $bot_nav, "'marketing'" ) ) {
	$fail( 'Bot_Admin_Nav missing 5 sections (Bot-Panel)' );
}
if ( ! is_file( $root . '/tests/BotAdminNavTest.php' ) ) {
	$fail( 'missing BotAdminNavTest.php' );
}
$guard = (string) file_get_contents( $root . '/includes/helpers/class-bot-admin-guard.php' );
if ( false === strpos( $guard, 'bootstrap_acting_admin_from_ctx' ) || false === strpos( $guard, 'broadcast_recipients' ) ) {
	$fail( 'Bot_Admin_Guard missing bootstrap or broadcast_recipients (Bot-Panel-Remaining)' );
}
$callback = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-callback.php' );
if ( false === strpos( $callback, 'Bot_Admin_Guard::may_call_op' ) || false === strpos( $callback, 'user_approve' ) ) {
	$fail( 'callback missing inline permission guards (Bot-Panel-Remaining)' );
}
$marketing = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-marketing.php' );
if ( false === strpos( $marketing, 'admin_discount_toggle' ) || false === strpos( $marketing, 'admin_discount_edit' ) ) {
	$fail( 'marketing missing discount toggle/edit (Bot-Panel-Remaining)' );
}
if ( false === strpos( $bot_nav, "'notifications'" ) ) {
	$fail( 'Bot_Admin_Nav missing notifications tab (Bot-Panel-Remaining)' );
}
$resellers = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-resellers.php' );
if ( false === strpos( $resellers, 'send_reseller_xui_panels' ) ) {
	$fail( 'resellers handler missing xui panels list (Bot-Panel-Remaining)' );
}

if ( ! is_file( $root . '/includes/helpers/class-bot-admin-mutate.php' ) ) {
	$fail( 'missing class-bot-admin-mutate.php (Bot-Full-Parity)' );
}
$mutate = (string) file_get_contents( $root . '/includes/helpers/class-bot-admin-mutate.php' );
if ( false === strpos( $mutate, 'apply_for_user' ) || false === strpos( $mutate, 'discount_post_from_wizard' ) ) {
	$fail( 'Bot_Admin_Mutate missing core methods (Bot-Full-Parity)' );
}
if ( false === strpos( $bot_nav, "'logs'" ) ) {
	$fail( 'Bot_Admin_Nav missing logs tab (Bot-Full-Parity)' );
}
if ( false === strpos( $marketing, 'Bot_Admin_Mutate::apply_for_user' ) || false === strpos( $marketing, 'admin_lifecycle_create' ) ) {
	$fail( 'marketing missing mutate bridge or lifecycle wizards (Bot-Full-Parity)' );
}
if ( ! is_file( $root . '/includes/bot/handlers/class-handler-admin-catalog.php' ) ) {
	$fail( 'missing class-handler-admin-catalog.php (Bot-Full-Parity)' );
}
$finance = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-finance.php' );
if ( false === strpos( $finance, 'reseller_wallet_topup_checkout' ) || false === strpos( $finance, 'admin_reseller_charge_reply' ) ) {
	$fail( 'finance missing reseller top-up or charge filters (Bot-Full-Parity)' );
}
if ( false === strpos( $resellers, 'list_for_reseller' ) || false === strpos( $resellers, 'admin_xui_assign' ) ) {
	$fail( 'resellers missing xui assign merge (Bot-Full-Parity)' );
}
if ( ! is_file( $root . '/tests/BotAdminMutateBridgeTest.php' ) ) {
	$fail( 'missing BotAdminMutateBridgeTest.php (Bot-Full-Parity)' );
}
$scope_cls = (string) file_get_contents( $root . '/includes/helpers/class-bot-reseller-scope.php' );
if ( false === strpos( $scope_cls, 'is_scoped_bot_admin_context' ) ) {
	$fail( 'Bot_Reseller_Scope missing is_scoped_bot_admin_context (Phase-8 scope)' );
}
$pnl = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-pnl.php' );
if ( false === strpos( $pnl, 'bootstrap_scope_from_chat' ) ) {
	$fail( 'Handler_Admin_Pnl missing bootstrap_scope_from_chat (Phase-8 scope)' );
}
if ( false !== strpos( $pnl, 'is_reseller_bot_request' ) ) {
	$fail( 'Handler_Admin_Pnl still uses is_reseller_bot_request (Phase-8 scope)' );
}
if ( is_file( $root . '/includes/bot/handlers/class-handler-admin-hub.php' ) ) {
	$fail( 'Handler_Admin_Hub must be removed (Bot Complete Parity)' );
}
$catalog = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-catalog.php' );
if ( false === strpos( $catalog, "'plan'" ) || false === strpos( $catalog, 'pnl:cat:t:' ) ) {
	$fail( 'catalog missing mutate toggle via pnl:cat (Bot Complete Parity)' );
}
$finance = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-finance.php' );
if ( false === strpos( $finance, 'date_from' ) || false === strpos( $finance, 'DATE(t.created_at)' ) ) {
	$fail( 'finance missing charges date filter SQL (Bot Complete Parity)' );
}
$callback = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-callback.php' );
if ( false !== strpos( $callback, "Handler_Admin_Hub::handle" ) || false !== strpos( $callback, "'adm' === \$head" ) ) {
	$fail( 'callback must not route adm: to hub (Bot Complete Parity)' );
}
$admin_handler = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin.php' );
if ( false !== strpos( $admin_handler, 'is_reseller_bot_request() && ! SimpleVPBot_Bot_Reseller_Scope::bot_admin_may' ) ) {
	$fail( 'Handler_Admin still gates may_* behind is_reseller_bot_request (Phase-8 scope)' );
}

$finance = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-finance.php' );
if ( false === strpos( $finance, 'has_next' ) || false === strpos( $finance, 'SELECT COUNT(*)' ) ) {
	$fail( 'finance missing charges has_next/total pagination (Bot Parity Audit Fix)' );
}
$catalog = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-catalog.php' );
if ( false !== strpos( $catalog, "'pln'" ) || false === strpos( $catalog, 'dispatch_legacy' ) ) {
	$fail( 'catalog create codes or legacy dispatch broken (Bot Parity Audit Fix)' );
}
$economics = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-economics.php' );
if ( false === strpos( $economics, 'panel_economics_save' ) || false === strpos( $economics, 'shared_economics_save' ) ) {
	$fail( 'economics missing cost-line wizards (Bot Parity Audit Fix)' );
}
$bot_nav = (string) file_get_contents( $root . '/includes/helpers/class-bot-admin-nav.php' );
if ( false !== strpos( $bot_nav, 'TAB_HUB_CODES' ) ) {
	$fail( 'TAB_HUB_CODES must be removed (Bot Parity Audit Fix)' );
}
foreach ( array(
	'class-handler-admin-bulk.php',
	'class-handler-admin-inbound.php',
	'class-handler-admin-backup.php',
	'class-handler-admin-texts.php',
	'class-handler-admin-logs.php',
	'class-handler-admin-stats.php',
) as $facade ) {
	if ( ! is_file( $root . '/includes/bot/handlers/' . $facade ) ) {
		$fail( 'missing modular facade ' . $facade . ' (Bot Parity Audit Fix)' );
	}
}
$pnl = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-pnl.php' );
if ( false !== strpos( $pnl, 'SimpleVPBot_Model_Card::delete' ) ) {
	$fail( 'Pnl must not delete cards directly — use catalog mutate (Bot Parity Audit Fix)' );
}
$ui_reg = (string) file_get_contents( $root . '/includes/bot/class-ui-action-registry.php' );
if ( false !== strpos( $ui_reg, "'route' => array( 'hub'" ) || false !== strpos( $ui_reg, "'route.hub'" ) ) {
	$fail( 'UI registry must not use route.hub (Bot Parity Audit Fix)' );
}

$economics = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-economics.php' );
if ( false === strpos( $economics, 'volume_mode' ) || false === strpos( $economics, 'route_delete_line_state' ) ) {
	$fail( 'economics missing volume_mode or line delete (Bot Flawless Audit)' );
}
$marketing = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-marketing.php' );
if ( false === strpos( $marketing, 'route_discount_allow_minmax' ) || false === strpos( $marketing, 'allow_flags' ) ) {
	$fail( 'marketing missing discount allow/min-max wizard (Bot Flawless Audit)' );
}
$settings = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-settings.php' );
if ( false === strpos( $settings, 'Bot_Admin_Mutate::apply_for_user' ) ) {
	$fail( 'settings catalog create must use mutate bridge (Bot Flawless Audit)' );
}
$scope = (string) file_get_contents( $root . '/includes/helpers/class-bot-admin-catalog-scope.php' );
if ( false === strpos( $scope, 'guard_plan' ) || false === strpos( $scope, 'guard_category' ) ) {
	$fail( 'catalog scope missing plan/category guards (Bot Flawless Audit)' );
}
$pnl = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-pnl.php' );
if ( false !== strpos( $pnl, 'send_plans_list' ) ) {
	$fail( 'Pnl must not retain legacy send_plans_list (Bot Flawless Audit)' );
}
$catalog = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-catalog.php' );
if ( false === strpos( $catalog, 'route_card_edit_state' ) || false === strpos( $catalog, 'route_category_edit_state' ) ) {
	$fail( 'catalog missing card/category edit wizards (post-BFA remediation)' );
}
if ( false === strpos( $catalog, 'guard_plan' ) || false === strpos( $catalog, 'route_plan_edit_state' ) ) {
	$fail( 'catalog plan edit submit missing guard_plan re-check (post-BFA remediation)' );
}
$economics = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-economics.php' );
if ( false === strpos( $economics, 'admin_economics_edit_line' ) || false === strpos( $economics, 'admin_economics_deactivate_line' ) ) {
	$fail( 'economics missing line edit/deactivate wizards (post-BFA remediation)' );
}
$backup = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-backup.php' );
if ( false === strpos( $backup, 'reply_label_map' ) || false === strpos( $backup, 'btn.admin.backup.now' ) ) {
	$fail( 'backup missing i18n reply_label_map (post-BFA remediation)' );
}
$users = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-users.php' );
if ( false === strpos( $users, 'send_user_admin_card' ) || false === strpos( $users, 'route_moderation_reply_text' ) ) {
	$fail( 'users facade must own user card + moderation routes (post-BFA remediation)' );
}
$receipts = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-receipts.php' );
if ( false === strpos( $receipts, 'send_pending_review_paged' ) || false === strpos( $receipts, 'send_one_pending_review' ) ) {
	$fail( 'receipts facade must own pending review flow (post-BFA remediation)' );
}
$inbound = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-inbound.php' );
if ( false === strpos( $inbound, 'send_inbounds_list_for_panel' ) ) {
	$fail( 'inbound facade must own inbounds list (post-BFA remediation)' );
}
if ( ! is_file( $root . '/tests/integration/BotAdminCatalogIdorIntegrationTest.php' ) ) {
	$fail( 'missing BotAdminCatalogIdorIntegrationTest.php (post-BFA remediation)' );
}
if ( false === strpos( $inbound, 'send_clients' ) || false === strpos( $inbound, 'start_link' ) ) {
	$fail( 'inbound facade must own clients + link flow (final re-audit)' );
}
$users = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-users.php' );
if ( false === strpos( $users, 'send_pending_page' ) || false === strpos( $users, 'send_approved_page' ) ) {
	$fail( 'users facade must own queue pages (final re-audit)' );
}
$mutate = (string) file_get_contents( $root . '/includes/helpers/class-bot-admin-mutate.php' );
if ( false === strpos( $mutate, 'enforce_catalog_entity_scope' ) ) {
	$fail( 'mutate bridge missing enforce_catalog_entity_scope (final re-audit)' );
}
if ( false === strpos( $pnl, "bot_admin_guard_op( \$platform, \$chat_id, 'user_search' )" ) ) {
	$fail( 'Pnl blk/ub missing user_search permission gate (final re-audit)' );
}
if ( false === strpos( $economics, 'apply_line_edit_parts' ) || false === strpos( $economics, 'tunnel_mode' ) ) {
	$fail( 'economics line edit missing full SPA fields (final re-audit)' );
}
if ( false === strpos( $economics, 'prompt_economics_line_id_invalid' ) ) {
	$fail( 'economics missing prompt_economics_line_id_invalid key usage' );
}
$defaults     = (string) file_get_contents( $root . '/includes/class-bot-text-defaults.php' );
$defaults_ext = (string) file_get_contents( $root . '/includes/class-bot-text-defaults-extended.php' );
if ( false === strpos( $defaults_ext, 'msg.admin.submenu.gen' ) || false === strpos( $defaults, 'btn.admin.panel_full' ) ) {
	$fail( 'defaults missing final re-audit i18n keys' );
}
$idor_test = (string) file_get_contents( $root . '/tests/integration/BotAdminCatalogIdorIntegrationTest.php' );
if ( false === strpos( $idor_test, 'test_cross_tenant_plan_update_forbidden' )
	|| false === strpos( $idor_test, 'test_guard_card_blocks_cross_tenant' ) ) {
	$fail( 'IDOR integration test missing expanded coverage (final re-audit)' );
}
if ( ! is_file( $root . '/tests/integration/BotAdminServiceIdorIntegrationTest.php' ) ) {
	$fail( 'missing BotAdminServiceIdorIntegrationTest.php (flawless verdict)' );
}
$pnl = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-pnl.php' );
if ( false === strpos( $pnl, "bot_admin_guard_op( \$platform, \$chat_id, 'service_manage' )" )
	|| false === strpos( $pnl, 'bot_admin_delegate_service_callback' ) ) {
	$fail( 'Pnl missing service_manage guard or delegate (flawless verdict)' );
}
if ( false === strpos( $pnl, 'self::bot_admin_delegate_service_callback( $platform, $chat_id, $from_id, $pick_sid' ) ) {
	$fail( 'service pick in route_menu_text must use bot_admin_delegate_service_callback (flawless verdict)' );
}
if ( false === strpos( $pnl, 'admin_apply_registration' ) ) {
	$fail( 'Pnl approve flow must use admin_apply_registration (flawless verdict)' );
}
$admin_handler = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin.php' );
if ( false === strpos( $admin_handler, "may_call_op( \$user, 'user_search' )" )
	|| substr_count( $admin_handler, "may_call_op( \$user, 'user_search' )" ) < 2 ) {
	$fail( 'Handler_Admin missing user_search gate on find user entry + completion (flawless verdict)' );
}
$marketing = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-marketing.php' );
if ( false === strpos( $marketing, 'msg.admin.lifecycle_rule_line' )
	|| false !== strpos( $marketing, '$rid > 0 ?' ) ) {
	$fail( 'marketing lifecycle list i18n or $rid bug not fixed (flawless verdict)' );
}
$catalog = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-catalog.php' );
if ( false === strpos( $catalog, "self::send_list( \$platform, \$chat_id, \$user, 'plans', 0, \$msg )" ) ) {
	$fail( 'catalog plan edit must use single-message send_list prefix (flawless verdict)' );
}
$users = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-users.php' );
if ( false === strpos( $users, 'msg.admin.wallet_credit' ) || false === strpos( $users, 'msg.admin.wallet_debit' ) ) {
	$fail( 'users wallet inline labels must use i18n keys (flawless verdict)' );
}
if ( false === strpos( $pnl, 'btn.admin.texts_prev' ) || false === strpos( $pnl, 'btn.admin.texts_reset_all' ) ) {
	$fail( 'send_text_keys_page nav must use i18n keys (flawless verdict)' );
}
$backup = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-backup.php' );
if ( false === strpos( $backup, 'handle_callback' ) ) {
	$fail( 'backup facade must own handle_callback (flawless verdict)' );
}
$bulk = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin-bulk.php' );
if ( false === strpos( $bulk, 'execute_extend_days' ) || false === strpos( $bulk, 'execute_add_volume' ) ) {
	$fail( 'bulk facade must own execute_extend_days/execute_add_volume (flawless verdict)' );
}
if ( false !== strpos( $pnl, 'private static function handle_backup_callback' ) ) {
	$fail( 'Pnl must not retain handle_backup_callback (flawless verdict)' );
}

foreach (
	array(
		'includes/bot/handlers/class-handler-admin.php',
		'includes/bot/handlers/class-handler-admin-pnl.php',
		'includes/bot/class-router.php',
	) as $lint_rel
) {
	$php_lint( $lint_rel );
}
$admin_handler = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-admin.php' );
if ( false === strpos( $admin_handler, 'dispatch_admin_route' )
	|| false === strpos( $admin_handler, 'Handler_Admin_Finance::route_text' ) ) {
	$fail( 'Handler_Admin missing dispatch_admin_route or Finance route_text (admin panel fix)' );
}
$ui_router = (string) file_get_contents( $root . '/includes/bot/class-ui-reply-router.php' );
if ( false === strpos( $ui_router, "dispatch_admin_route( \$ctx" )
	|| false === strpos( $ui_router, "'admin_route'" ) ) {
	$fail( 'UI_Reply_Router must dispatch admin_route (admin panel fix)' );
}

if ( ! is_file( $root . '/includes/helpers/class-deferred-work.php' ) ) {
	$fail( 'missing class-deferred-work.php (force join + speed)' );
}
$deferred = (string) file_get_contents( $root . '/includes/helpers/class-deferred-work.php' );
if ( false === strpos( $deferred, 'run_after_response_or_cron' ) ) {
	$fail( 'Deferred_Work missing run_after_response_or_cron' );
}
$chjoin = (string) file_get_contents( $root . '/includes/helpers/class-required-channel.php' );
if ( false === strpos( $chjoin, 'fetch_member_status' ) || false === strpos( $chjoin, 'fail-open' ) ) {
	$fail( 'Required_Channel missing fetch_member_status fail-open' );
}
$webhook = (string) file_get_contents( $root . '/includes/bot/class-webhook.php' );
if ( false === strpos( $webhook, 'serve_webhook_update' ) || false === strpos( $webhook, 'rest_pre_serve_request' ) ) {
	$fail( 'Webhook missing early-ack serve_webhook_update' );
}
$router = (string) file_get_contents( $root . '/includes/bot/class-router.php' );
if ( false === strpos( $router, 'Deferred_Work::run_after_response' ) ) {
	$fail( 'Router must defer activity log via Deferred_Work' );
}
$buy = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-buy.php' );
if ( false === strpos( $buy, 'deliver_receipt_to_admins' ) ) {
	$fail( 'Handler_Buy missing deliver_receipt_to_admins deferred path' );
}
$callback = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-callback.php' );
if ( false === strpos( $callback, 'approve_continue' ) || false !== strpos( $callback, 'RECEIPT_APPROVE_CRON_HOOK' ) ) {
	$fail( 'Handler_Callback must sync receipt approve (no deferred cron)' );
}
if ( false === strpos( $callback, '$clicked' ) ) {
	$fail( 'Handler_Callback missing clicked message context for receipt approve' );
}
$settings_smoke = (string) file_get_contents( $root . '/includes/class-settings.php' );
if ( false === strpos( $settings_smoke, 'bot_admin_notify_usleep_us' )
	|| false === strpos( $settings_smoke, 'force_join_cache_ttl_sec' )
	|| false === strpos( $settings_smoke, 'buy_catalog_cache_ttl_sec' )
	|| false === strpos( $settings_smoke, 'crypto_invoice_timeout_sec' )
	|| false === strpos( $settings_smoke, 'bot_interactive_timeout_sec' ) ) {
	$fail( 'Settings missing bot speed tuning keys' );
}
if ( false === strpos( $buy, 'buyable_categories_for_context_fast' )
	|| false === strpos( $buy, 'all_active_for_owners' )
	|| false === strpos( $buy, 'buy_catalog_cache_key' )
	|| false === strpos( $buy, 'plans_for_category_cached' )
	|| false === strpos( $buy, 'send_crypto_invoice_deferred' )
	|| false === strpos( $buy, 'schedule_wallet_fulfill' ) ) {
	$fail( 'Handler_Buy missing buy flow speed optimizations' );
}
if ( false === strpos( $callback, 'buy:pm:' )
	|| false === strpos( $callback, 'buy:swy:' )
	|| false === strpos( $callback, 'buy:cf:' )
	|| false === strpos( $callback, 'buy:bw:' ) ) {
	$fail( 'Handler_Callback missing deferred buy checkout callback answer' );
}
if ( false === strpos( $buy, 'resolve_checkout_cards' )
	|| false === strpos( $buy, 'prescope_checkout_meta' )
	|| false === strpos( $buy, 'send_bale_wallet_invoice_deferred' ) ) {
	$fail( 'Handler_Buy missing post-confirm checkout speed optimizations' );
}
$branding = (string) file_get_contents( $root . '/includes/helpers/class-reseller-branding.php' );
if ( false === strpos( $branding, 'nearest_reseller_cache' ) ) {
	$fail( 'Reseller_Branding missing nearest_reseller per-request cache' );
}
$crypto = (string) file_get_contents( $root . '/includes/helpers/class-crypto-payment.php' );
if ( false === strpos( $crypto, 'schedule_crypto_fulfill' ) || false === strpos( $crypto, 'queued' ) ) {
	$fail( 'Crypto_Payment missing deferred IPN fulfill' );
}
if ( false === strpos( $deferred, 'CRYPTO_FULFILL_CRON_HOOK' ) || false === strpos( $deferred, 'RECEIPT_ADMIN_NOTIFY_CRON_HOOK' ) ) {
	$fail( 'Deferred_Work missing fulfill delivery cron hooks' );
}
$receipt_proc = (string) file_get_contents( $root . '/includes/helpers/class-receipt-processor.php' );
if ( false === strpos( $receipt_proc, 'approve_async_start' ) || false === strpos( $receipt_proc, 'finalize_clicked_if' ) || false === strpos( $receipt_proc, 'service_ready_summary_line' ) ) {
	$fail( 'Receipt_Processor missing sync approve / clicked finalize / service ready enrich' );
}
if ( preg_match( '/function approve_async_start[\s\S]*?function approve_continue/', $receipt_proc, $approve_async_block )
	&& false !== strpos( $approve_async_block[0], 'run_after_response_or_cron' ) ) {
	$fail( 'Receipt_Processor approve_async_start must not defer approve_continue' );
}
if ( preg_match( "/'cf' === \\\$act[\s\S]*?schedule_deferred_purchase_checkout/", $buy )
	|| preg_match( "/'pm' === \\\$act[\s\S]*?schedule_deferred_c2c_invoice/", $buy ) ) {
	$fail( 'Handler_Buy buy:cf/buy:pm must run checkout/c2c inline (no defer)' );
}
$format_locale = (string) file_get_contents( $root . '/frontend/src/lib/format-locale.ts' );
if ( false === strpos( $format_locale, 'parseMysqlDatetimeInSiteZone' ) ) {
	$fail( 'format-locale missing site-timezone MySQL parse' );
}
$rest_dash = (string) file_get_contents( $root . '/includes/api/class-rest-dashboard.php' );
if ( false === strpos( $rest_dash, 'mysql_datetime_to_utc_ts' ) || false === strpos( $rest_dash, 'created_at_ts' ) ) {
	$fail( 'REST dashboard missing created_at_ts timezone helper' );
}
$svc_handler = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-service.php' );
if ( false === strpos( $svc_handler, 'get_portal_service_data_fast' ) || false === strpos( $svc_handler, 'schedule_svc_panel_full_delivery' ) ) {
	$fail( 'Handler_Service missing fast portal delivery' );
}
if ( false === strpos( $callback, 'svc:p:' ) || false === strpos( $callback, 'svc:w:' ) ) {
	$fail( 'Handler_Callback missing deferred svc panel callbacks' );
}
if ( false === strpos( $buy, 'deferred_receipt_admin_notify_cron' ) || false === strpos( $buy, 'send_admin_receipt_photo_review' ) ) {
	$fail( 'Handler_Buy missing optimized receipt admin notify' );
}
if ( false === strpos( $buy, 'receipt_admin_photo_delivered' ) || false === strpos( $buy, 'svp_receipt_admin_notify_' ) ) {
	$fail( 'Handler_Buy missing receipt admin notify idempotency guards' );
}
if ( false === strpos( $buy, 'clear_receipt_admin_notify_cron' ) || false === strpos( $buy, "'kind'" ) ) {
	$fail( 'Handler_Buy missing receipt photo delivery guards' );
}
if ( false === strpos( $deferred, 'clear_scheduled_cron' ) ) {
	$fail( 'Deferred_Work missing clear_scheduled_cron helper' );
}
$receipt_proc = (string) file_get_contents( $root . '/includes/helpers/class-receipt-processor.php' );
if ( false === strpos( $receipt_proc, 'clear_scheduled_cron' ) || false === strpos( $receipt_proc, 'RECEIPT_APPROVE_CRON_HOOK' ) ) {
	$fail( 'Receipt_Processor missing approve cron cancellation' );
}
$svc_handler = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-service.php' );
if ( false === strpos( $svc_handler, 'portal_data_has_sendable_config' ) || false === strpos( $svc_handler, 'build_qr_caption_html' ) ) {
	$fail( 'Handler_Service missing config delivery retry/qr guards' );
}
if ( false === strpos( $svc_handler, 'SVC_CONFIG_DELIVERY_CRON_HOOK' ) ) {
	$fail( 'Handler_Service missing config delivery cron clear' );
}
if ( false === strpos( $svc_handler, 'resolve_telegram_chat_id' ) || false !== strpos( $svc_handler, 'telegram_id ?? 0' ) ) {
	$fail( 'Handler_Service must use tg_user_id via resolve_telegram_chat_id' );
}
if ( false === strpos( $svc_handler, 'panel_sub_url' ) || false === strpos( $svc_handler, 'build_subscription_link_message_html' ) ) {
	$fail( 'Handler_Service missing subscription link delivery helpers' );
}
if ( false === strpos( $svc_handler, 'resolve_user_dashboard_url' ) || false === strpos( $svc_handler, 'get_portal_service_data_for_delivery' ) ) {
	$fail( 'Handler_Service missing dashboard URL / fast delivery helpers' );
}
if ( false === strpos( $svc_handler, 'build_combined_config_message_html' ) || false === strpos( $svc_handler, 'warm_subscription_cache_for_service' ) ) {
	$fail( 'Handler_Service missing combined config delivery helpers' );
}
if ( false === strpos( $svc_handler, 'send_config_qr_photo' ) ) {
	$fail( 'Handler_Service missing send_config_qr_photo helper' );
}
if ( false === strpos( $receipt_proc, 'user_gets_telegram_config_delivery' ) || false === strpos( $receipt_proc, 'build_purchase_delivery_intro_html' ) ) {
	$fail( 'Receipt_Processor missing combined telegram purchase delivery' );
}
$service_naming = (string) file_get_contents( $root . '/includes/helpers/class-service-naming.php' );
if ( false === strpos( $service_naming, 'uri_fragment_label' ) || false === strpos( $service_naming, 'config_line_remark_for_uri' ) ) {
	$fail( 'Service_Naming missing external proxy fragment labels' );
}
if ( false === strpos( $crypto, 'tg_user_id' ) || false === strpos( $crypto, 'bale_user_id' ) ) {
	$fail( 'Crypto_Payment must use tg_user_id and bale_user_id for notify' );
}
if ( false === strpos( $receipt_proc, 'config_delivery_skipped_no_telegram' ) ) {
	$fail( 'Receipt_Processor missing bale-only config delivery guard' );
}
$mutations = (string) file_get_contents( $root . '/includes/admin/class-dashboard-admin-mutations.php' );
if ( false === strpos( $mutations, 'approve_async_start' ) ) {
	$fail( 'Dashboard mutations missing async receipt approve' );
}
if ( false === strpos( $deferred, 'SVC_CONFIG_DELIVERY_CRON_HOOK' ) || false === strpos( $deferred, 'SVC_PANEL_DELIVERY_CRON_HOOK' ) ) {
	$fail( 'Deferred_Work missing svc config/panel delivery cron hooks' );
}
if ( false === strpos( $svc_handler, 'resolve_preparing_panel_message' ) || false === strpos( $svc_handler, 'config_already_sent' ) ) {
	$fail( 'Handler_Service missing fulfill safety guards' );
}
if ( false === strpos( $svc_handler, 'notify_panel_delivery_failed' ) || false === strpos( $svc_handler, 'deferred_svc_panel_delivery_cron' ) ) {
	$fail( 'Handler_Service missing panel delivery failure handling' );
}
if ( false === strpos( $crypto, 'notify_crypto_fulfill_failed' ) || false === strpos( $crypto, 'CRYPTO_FULFILL_MAX_ATTEMPTS' ) ) {
	$fail( 'Crypto_Payment missing fulfill failure notify/retry' );
}
if ( false === strpos( $receipt_proc, 'آماده‌سازی سرویس با تأخیر مواجه شد' ) ) {
	$fail( 'Receipt_Processor missing user notify on provision failure' );
}
if ( false === strpos( $receipt_proc, 'notify_user_after_purchase_approved' ) ) {
	$fail( 'Receipt_Processor missing notify_user_after_purchase_approved' );
}
if ( false === strpos( $receipt_proc, 'receipt_notify_skipped_no_service' ) ) {
	$fail( 'Receipt_Processor missing receipt_notify_skipped_no_service log' );
}
$deferred = (string) file_get_contents( $root . '/includes/helpers/class-deferred-work.php' );
if ( false === strpos( $deferred, 'function schedule_cron_retry' ) ) {
	$fail( 'Deferred_Work missing schedule_cron_retry' );
}
if ( false === strpos( $deferred, 'RECEIPT_PROVISION_RETRY_CRON_HOOK' ) ) {
	$fail( 'Deferred_Work missing RECEIPT_PROVISION_RETRY_CRON_HOOK' );
}
$handler_svc = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-service.php' );
if ( false === strpos( $handler_svc, 'config_delivery_retry_scheduled' ) ) {
	$fail( 'Handler_Service missing config_delivery_retry_scheduled log' );
}
if ( false === strpos( $handler_svc, 'config_delivery_exhausted_retries' ) ) {
	$fail( 'Handler_Service missing config_delivery_exhausted_retries log' );
}
if ( false === strpos( $buy, 'plan_checkout_summary_text' ) || false === strpos( $buy, 'plan_confirm_message_text' ) ) {
	$fail( 'Handler_Buy missing unified plan checkout summary helpers' );
}
if ( false === strpos( $buy, 'schedule_deferred_purchase_checkout' ) || false === strpos( $buy, 'deferred_purchase_checkout_cron' ) ) {
	$fail( 'Handler_Buy missing deferred buy:cf checkout' );
}
if ( false === strpos( $deferred, 'BUY_CHECKOUT_CRON_HOOK' ) ) {
	$fail( 'Deferred_Work missing buy checkout cron hook' );
}
$text_defaults = (string) file_get_contents( $root . '/includes/class-bot-text-defaults-extended.php' );
if ( false === strpos( $text_defaults, 'msg.buy.plan_checkout_summary' ) ) {
	$fail( 'Bot text defaults missing plan_checkout_summary template' );
}
if ( false === strpos( $buy, 'schedule_deferred_c2c_invoice' ) || false === strpos( $buy, 'deferred_c2c_invoice_cron' ) ) {
	$fail( 'Handler_Buy missing deferred buy:pm c2c invoice' );
}
if ( false === strpos( $deferred, 'C2C_INVOICE_CRON_HOOK' ) ) {
	$fail( 'Deferred_Work missing c2c invoice cron hook' );
}
if ( false === strpos( $text_defaults, 'msg.buy.preparing_invoice' ) ) {
	$fail( 'Bot text defaults missing preparing_invoice template' );
}
if ( false === strpos( $callback, 'buy:pm:' ) || false === strpos( $callback, 'buy:cd:' ) ) {
	$fail( 'Callback router missing payment continuation skip_clear' );
}
$keyboards = (string) file_get_contents( $root . '/includes/bot/class-keyboards.php' );
if ( ! preg_match( '/copy_card_number[\s\S]{0,400}copy_amount/', $keyboards ) ) {
	$fail( 'inline_invoice_actions missing side-by-side copy card/amount buttons' );
}
$pay_methods = (string) file_get_contents( $root . '/includes/helpers/class-payment-methods.php' );
if ( false === strpos( $pay_methods, '$cards = null' ) ) {
	$fail( 'Payment_Methods checkout_has_any_method missing pre-resolved cards param' );
}
foreach (
	array(
		'includes/helpers/class-deferred-work.php',
		'includes/helpers/class-required-channel.php',
		'includes/bot/class-webhook.php',
		'includes/helpers/class-receipt-processor.php',
		'includes/bot/handlers/class-handler-buy.php',
		'includes/bot/handlers/class-handler-callback.php',
		'includes/bot/class-bot-runtime.php',
		'includes/helpers/class-crypto-payment.php',
		'includes/bot/handlers/class-handler-service.php',
		'includes/bot/handlers/class-handler-admin-users.php',
		'includes/admin/class-dashboard-admin-mutations.php',
		'includes/helpers/class-service-renew.php',
		'includes/helpers/class-service-dashboard-panel.php',
		'includes/helpers/class-inbound-linker.php',
		'includes/api/class-xui-client.php',
	) as $lint_rel
) {
	$php_lint( $lint_rel );
}

echo "OK run-smoke-tests.php\n";
exit( 0 );
