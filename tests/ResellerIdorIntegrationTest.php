<?php
/**
 * Contract + behavioral IDOR tests for reseller isolation (phase D audit).
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/fixtures/class-reseller-tree-fixture.php';

/**
 * @coversNothing
 */
class ResellerIdorIntegrationTest extends TestCase {

	/**
	 * L-2: reseller admin/state rejects forbidden activeTab before payload build.
	 */
	public function test_reseller_active_tab_validation_in_rest(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( 'reseller_may_request_admin_tab', $code );
		$this->assertStringContainsString( 'forbidden_tab', $code );
		$this->assertMatchesRegularExpression(
			'/function route_admin_state[\s\S]*reseller_may_request_admin_tab/',
			$code
		);
	}

	/**
	 * L-3: receipt image proxy must not fall back to main bot token.
	 */
	public function test_receipt_image_no_global_token_fallback(): void {
		$ajax = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-ajax.php' );
		$this->assertMatchesRegularExpression(
			'/function receipt_image[\s\S]*bot_token_for_reseller/',
			$ajax
		);
		$this->assertDoesNotMatchRegularExpression(
			'/function receipt_image[\s\S]*Settings::get\(\s*\$is_bale\s*\?\s*[\'"]bale_token/',
			$ajax
		);
	}

	/**
	 * S-5: backup export redacts plugin settings secrets.
	 */
	public function test_backup_export_redacts_plugin_settings(): void {
		$export = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-backup-export.php' );
		$this->assertStringContainsString( 'redact_plugin_settings_for_export', $export );
		$this->assertStringContainsString( 'plugin_settings_secrets_redacted', $export );
		$this->assertStringContainsString( "'plugin_settings_contains_secrets' => false", $export );

		if ( class_exists( 'SimpleVPBot_Backup_Export' ) ) {
			$redacted = SimpleVPBot_Backup_Export::redact_plugin_settings_for_export(
				array(
					'telegram_token'     => 'secret-token',
					'portal_link_secret' => 'portal-secret',
					'site_name'          => 'Test',
				)
			);
			$this->assertArrayNotHasKey( 'telegram_token', $redacted );
			$this->assertArrayNotHasKey( 'portal_link_secret', $redacted );
			$this->assertTrue( ! empty( $redacted['telegram_token_set'] ) );
			$this->assertSame( 'Test', $redacted['site_name'] );
		}
	}

	/**
	 * Cross-reseller moderation: peer tree user not moderatable by parent.
	 */
	public function test_peer_user_not_in_parent_scope(): void {
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			$this->markTestSkipped( 'Reseller scope classes not loaded.' );
		}
		$fixture = SimpleVPBot_Reseller_Tree_Fixture::seed( 882000000 + random_int( 1000, 99999 ) );
		try {
			$this->assertFalse(
				SimpleVPBot_Bot_Reseller_Scope::reseller_may_moderate_user_for(
					$fixture->parent_id,
					$fixture->end_user_id
				)
			);
			$this->assertTrue(
				SimpleVPBot_Bot_Reseller_Scope::reseller_may_moderate_user_for(
					$fixture->peer_id,
					$fixture->end_user_id
				)
			);
		} finally {
			$fixture->tear_down();
		}
	}

	/**
	 * Portal mirrors reseller_permissions (forbidden_perm when perm off).
	 */
	public function test_portal_permission_gate_contract(): void {
		$ajax = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-ajax.php' );
		$this->assertStringContainsString( 'portal_reseller_may_call_op', $ajax );
		$this->assertStringContainsString( 'forbidden_perm', $ajax );
		$this->assertMatchesRegularExpression(
			'/function portal_admin[\s\S]*portal_reseller_may_call_op/',
			$ajax
		);
	}

	/**
	 * N-3: user detail SPA gates mutations by actor permissions.
	 */
	public function test_user_detail_permission_gates_in_spa(): void {
		$view = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-user-detail-admin.tsx' );
		$this->assertStringContainsString( 'canManageUsers', $view );
		$this->assertStringContainsString( 'canManageServices', $view );
		$this->assertStringContainsString( 'actorPermissions', $view );
	}

	/**
	 * L-1: notifications/logs hidden from reseller nav.
	 */
	public function test_admin_nav_blocks_notifications_logs(): void {
		$nav = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/config/admin-nav.ts' );
		$this->assertMatchesRegularExpression(
			'/ADMIN_ONLY_TAB_KEYS[\s\S]*"notifications"[\s\S]*"logs"/',
			$nav
		);
	}
}
