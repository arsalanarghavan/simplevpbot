<?php
/**
 * Contract tests for dashboard backup list/restore REST.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class BackupRestoreDashboardTest extends TestCase {

	/**
	 * Export helper lists site backups safely.
	 */
	public function test_backup_export_list_contract(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-backup-export.php' );
		$this->assertStringContainsString( 'list_site_backup_files', $code );
		$this->assertStringContainsString( 'resolve_site_backup_path', $code );
		$this->assertStringContainsString( 'is_valid_site_backup_filename', $code );
		$this->assertStringContainsString( 'zip_panel_db_summary', $code );
		$this->assertStringContainsString( 'panel_db_failures', $code );
	}

	/**
	 * Service ops expose list and site restore.
	 */
	public function test_service_admin_ops_backup_contract(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/services/class-service-admin-ops.php' );
		$this->assertStringContainsString( 'list_site_backups', $code );
		$this->assertStringContainsString( 'resolve_site_backup_download_path', $code );
		$this->assertStringContainsString( 'next_backup_at', $code );
		$this->assertStringContainsString( 'last_cron_ping_at', $code );
		$this->assertStringContainsString( 'server_crontab_line', $code );
		$this->assertStringContainsString( 'restore_site_backup_file', $code );
		$this->assertStringContainsString( "'data'    => \$stats", $code );
	}

	/**
	 * WP-Cron keeper: loopback ping, throttle, webhook shutdown, multisite script.
	 */
	public function test_cron_keeper_contract(): void {
		$cron = (string) file_get_contents( dirname( __DIR__ ) . '/includes/cron/class-cron-manager.php' );
		$this->assertStringContainsString( 'ping_wp_cron_loopback', $cron );
		$this->assertStringContainsString( 'maybe_ping_wp_cron_throttled', $cron );
		$this->assertStringContainsString( 'schedule_ping_on_shutdown', $cron );
		$this->assertStringContainsString( 'server_crontab_line', $cron );
		$this->assertStringContainsString( "maybe_ping_wp_cron_throttled' ), 40", $cron );
		$this->assertStringNotContainsString( 'DISABLE_WP_CRON', $cron );

		$webhook = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/class-webhook.php' );
		$this->assertStringContainsString( 'schedule_ping_on_shutdown', $webhook );

		$script = (string) file_get_contents( dirname( __DIR__ ) . '/scripts/wp-cron-multisite.example.sh' );
		$this->assertStringContainsString( 'SITE_URLS', $script );
		$this->assertStringContainsString( 'wp-cron.php', $script );
	}

	/**
	 * Backup zip includes per-reseller permission options and restore applies them.
	 */
	public function test_reseller_permissions_backup_contract(): void {
		$export = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-backup-export.php' );
		$restore = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-backup-restore.php' );
		$user = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-user.php' );
		$this->assertStringContainsString( 'export_reseller_permissions_json', $export );
		$this->assertStringContainsString( 'wordpress/reseller-permissions.json', $export );
		$this->assertStringContainsString( 'export_all_reseller_permissions', $user );
		$this->assertStringContainsString( 'restore_reseller_permissions_from_export', $user );
		$this->assertStringContainsString( 'reseller-permissions.json', $restore );
		$this->assertStringContainsString( 'reseller_permissions_restored', $restore );
		$this->assertStringContainsString( 'reseller_permissions_skipped', $restore );
		$this->assertStringContainsString( 'wordpress_files', $export );
		$this->assertStringContainsString( 'plugin_settings_secrets_redacted', $export );
		$this->assertStringContainsString( "'plugin_settings_contains_secrets' => false", $export );
	}

	/**
	 * Merge restore module is wired.
	 */
	public function test_merge_restore_contract(): void {
		$restore = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-backup-restore.php' );
		$this->assertStringNotContainsString( 'TRUNCATE TABLE', $restore );
		$this->assertStringContainsString( 'SimpleVPBot_Backup_Merge_Restore', $restore );
		$this->assertStringContainsString( 'restore_panel_dbs_from_zip', $restore );
		$this->assertStringContainsString( 'restore_panel_db', $restore );
	}

	/**
	 * Xui client exposes panel DB import for restore.
	 */
	public function test_xui_import_db_contract(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-xui-client.php' );
		$this->assertStringContainsString( 'import_db_from_path', $code );
		$this->assertStringContainsString( 'server/importDB', $code );
		$this->assertStringContainsString( 'get_db_binary_with_retries', $code );
	}

	/**
	 * REST dashboard registers backup routes.
	 */
	public function test_rest_backup_routes_contract(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( '/dashboard/admin/backups', $code );
		$this->assertStringContainsString( 'route_admin_backups', $code );
		$this->assertStringContainsString( '/dashboard/admin/backup/run', $code );
		$this->assertStringContainsString( '/dashboard/admin/backup/status', $code );
		$this->assertStringContainsString( '/dashboard/admin/backup/reset-stuck', $code );
		$this->assertStringContainsString( 'route_admin_backup_reset_stuck', $code );
		$this->assertStringContainsString( '/dashboard/admin/backup/download', $code );
		$this->assertStringContainsString( 'route_admin_backup_download', $code );
		$this->assertStringContainsString( 'route_admin_backup_status', $code );
		$this->assertStringContainsString( 'backup_now_start_async', $code );
		$this->assertStringContainsString( 'Always 200 for logical outcomes', $code );
		$this->assertStringContainsString( '/dashboard/admin/backup/restore', $code );
		$this->assertStringContainsString( 'restore-upload', $code );
		$this->assertStringContainsString( 'restore_panel_db', $code );
		$this->assertStringContainsString( 'backup.restore', $code );
	}

	/**
	 * Telegram channel/supergroup chat ids are negative; must not use "id < 1" as empty check.
	 */
	public function test_backup_cron_ensure_contract(): void {
		$cron = (string) file_get_contents( dirname( __DIR__ ) . '/includes/cron/class-cron-manager.php' );
		$this->assertStringContainsString( 'ensure_backup_cron_scheduled', $cron );
		$this->assertStringContainsString( 'backup_cron_diagnostics', $cron );
		$this->assertStringContainsString( 'get_backup_cron_schedule', $cron );
	}

	public function test_manual_backup_async_hook_contract(): void {
		$cron = (string) file_get_contents( dirname( __DIR__ ) . '/includes/cron/class-cron-manager.php' );
		$ops  = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/services/class-service-admin-ops.php' );
		$this->assertStringContainsString( 'simplevpbot_manual_backup', $cron );
		$this->assertStringContainsString( 'run_manual_backup_job', $cron );
		$this->assertStringContainsString( 'backup_now_start_async', $ops );
		$this->assertStringContainsString( 'get_manual_backup_status_api', $ops );
		$this->assertStringContainsString( 'maybe_run_manual_backup_fallback', $ops );
		$this->assertStringContainsString( 'MANUAL_BACKUP_WORKER_LOCK', $ops );
		$this->assertStringContainsString( 'register_manual_backup_shutdown_runner', $ops );
		$this->assertStringContainsString( 'reset_stale_manual_backup_status', $ops );
		$this->assertStringContainsString( 'validate_delivery_for_manual_backup', $ops );
		$this->assertStringContainsString( 'fastcgi_finish_request', $ops );
		$this->assertStringContainsString( 'SimpleVPBot_Cron_Manager::ping_wp_cron_loopback', $ops );
		$this->assertStringNotContainsString( 'function trigger_wp_cron_loopback', $ops );
		$this->assertStringNotContainsString( 'register_manual_backup_shutdown_fallback', $ops );
		$this->assertStringNotContainsString( 'wp_next_scheduled( self::MANUAL_BACKUP_CRON_HOOK )', $ops );
	}

	public function test_jalali_backup_timezone_contract(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-jalali-date.php' );
		$this->assertStringContainsString( "BACKUP_TIMEZONE = 'Asia/Tehran'", $code );
		$this->assertStringContainsString( 'wp_date_backup', $code );
	}

	public function test_backup_channel_chat_id_accepts_negative_telegram_ids(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/cron/class-cron-backup.php' );
		$this->assertStringContainsString( 'backup_channel_chat_id_is_set', $code );
		$this->assertStringContainsString( 'persist_last_run', $code );
		$this->assertStringContainsString( '0 !== (int) $id', $code );
		$this->assertStringNotContainsString( '$tg_chan < 1', $code );
		$this->assertStringNotContainsString( '$bl_chan < 1', $code );
		$this->assertStringContainsString( '$stored_on_site', $code );
		$this->assertStringContainsString( 'simplevpbot_last_backup_at', $code );
	}

	/**
	 * Dashboard UI calls restore endpoints.
	 */
	public function test_dashboard_backup_ui_contract(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-backup-admin.tsx' );
		$this->assertStringContainsString( '/dashboard/admin/backups', $code );
		$this->assertStringContainsString( '/dashboard/admin/backup/status', $code );
		$this->assertStringContainsString( 'pollManualBackupUntilDone', $code );
		$this->assertStringContainsString( 'downloadAdminBackupFile', $code );
		$this->assertStringContainsString( 'downloadBtn', $code );
		$this->assertStringContainsString( 'backupRunningLong', $code );
		$this->assertStringContainsString( 'BACKUP_POLL_LONG_HINT_MS', $code );
		$this->assertStringContainsString( '/dashboard/admin/backup/restore-upload', $code );
		$this->assertStringContainsString( 'postAdminFormData', $code );
		$this->assertStringContainsString( 'restore_panel_db', $code );
		$this->assertStringContainsString( 'restorePanelDb', $code );
		$this->assertStringContainsString( 'cronKeeperTitle', $code );
		$this->assertStringContainsString( 'serverCrontabLine', $code );
		$this->assertStringContainsString( 'onCopyCrontabLine', $code );
		$this->assertStringContainsString( 'backupResetStuck', $code );
		$this->assertStringContainsString( '/dashboard/admin/backup/reset-stuck', $code );
		$this->assertStringContainsString( 'onResetBackupStuck', $code );
	}
}
