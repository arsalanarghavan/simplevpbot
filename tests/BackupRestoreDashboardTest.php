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
		$this->assertStringContainsString( 'restore_site_backup_file', $code );
		$this->assertStringContainsString( "'data'    => \$stats", $code );
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
		$this->assertStringContainsString( '/dashboard/admin/backup/restore', $code );
		$this->assertStringContainsString( 'restore-upload', $code );
		$this->assertStringContainsString( 'restore_panel_db', $code );
		$this->assertStringContainsString( 'backup.restore', $code );
	}

	/**
	 * Dashboard UI calls restore endpoints.
	 */
	public function test_dashboard_backup_ui_contract(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-backup-admin.tsx' );
		$this->assertStringContainsString( '/dashboard/admin/backups', $code );
		$this->assertStringContainsString( '/dashboard/admin/backup/restore-upload', $code );
		$this->assertStringContainsString( 'postAdminFormData', $code );
		$this->assertStringContainsString( 'restore_panel_db', $code );
		$this->assertStringContainsString( 'restorePanelDb', $code );
	}
}
