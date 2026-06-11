<?php
/**
 * Contract tests for auto-purge expired Xray services.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class PurgeExpiredServicesTest extends TestCase {

	public function test_purge_expired_cron_contract(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/cron/class-cron-purge-expired.php' );
		$this->assertStringContainsString( 'run_batch', $code );
		$this->assertStringContainsString( 'list_expired_candidates', $code );
		$this->assertStringContainsString( 'purge_service_by_id', $code );
		$this->assertStringContainsString( 'service_purge_status', $code );
		$this->assertStringContainsString( 'service.purge_expired', $code );
		$this->assertStringContainsString( 'build_purge_notify_text', $code );
		$this->assertStringContainsString( 'msg.cron_purge_warn_today', $code );
	}

	public function test_purge_expired_dedup_contract(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/cron/class-cron-purge-expired.php' );
		$this->assertStringContainsString( 'SimpleVPBot_Notification_Dedup::claim', $code );
		$this->assertStringContainsString( 'simplevpbot_purge_expired_sent_buckets', $code );
	}

	public function test_purge_expired_text_keys_contract(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-bot-text-defaults-extended.php' );
		$this->assertStringContainsString( 'msg.cron_purge_warn', $code );
	}

	public function test_settings_purge_keys_contract(): void {
		$settings = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-settings.php' );
		$this->assertStringContainsString( 'purge_expired_enabled', $settings );
		$this->assertStringContainsString( 'purge_expired_grace_days', $settings );
		$this->assertStringContainsString( 'purge_expired_warn_days', $settings );
		$this->assertStringContainsString( 'purge_expired_notify_user', $settings );
	}

	public function test_settings_tab_purge_expired_in_admin_actions(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-actions.php' );
		$this->assertStringContainsString( "case 'purge_expired':", $code );
		$this->assertDoesNotMatchRegularExpression(
			"/case 'notifications':[\\s\\S]*purge_expired_grace_days/s",
			$code
		);
	}

	public function test_purge_mutations_contract(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( 'purge_expired_run_cron', $code );
		$this->assertStringContainsString( 'purge_expired_purge_ready', $code );
		$this->assertStringContainsString( 'purge_expired_purge_one', $code );
	}

	public function test_purge_rest_route_contract(): void {
		$rest = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( '/dashboard/admin/purge-expired', $rest );
		$this->assertStringContainsString( 'route_admin_purge_expired', $rest );
	}

	public function test_cron_manager_registers_purge_expired(): void {
		$cron = (string) file_get_contents( dirname( __DIR__ ) . '/includes/cron/class-cron-manager.php' );
		$this->assertStringContainsString( 'simplevpbot_cron_purge_expired', $cron );
	}

	public function test_dashboard_purge_tab_contract(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/site-settings/site-settings-purge-tab.tsx' );
		$this->assertStringContainsString( 'purge_expired_run_cron', $code );
		$this->assertStringContainsString( 'purge_expired_purge_ready', $code );
		$this->assertStringContainsString( 'configs_delete_expired_linked', $code );
	}

	public function test_notifications_tab_no_purge_card(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/site-settings/site-settings-notifications-tab.tsx' );
		$this->assertStringNotContainsString( 'purgeTitle', $code );
		$this->assertStringNotContainsString( 'purge_expired_enabled', $code );
	}

	public function test_configs_no_delete_expired_ui(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-configs-admin.tsx' );
		$this->assertStringNotContainsString( 'runDeleteExpired', $code );
		$this->assertStringContainsString( 'purgeMovedLink', $code );
	}

	public function test_audit_format_purge_event_contract(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/lib/format-audit-log.ts' );
		$this->assertStringContainsString( 'service.purge_expired', $code );
		$this->assertStringContainsString( 'summary_service_purge_expired', $code );
	}
}
