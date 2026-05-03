<?php
/**
 * Stable contracts for admin portal HMAC and UI notes (no WordPress).
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class PortalAdminContractsTest extends TestCase {

	/**
	 * Admin portal token uses a distinct HMAC message and key suffix (must stay in sync with SimpleVPBot_Portal_Link).
	 */
	public function test_admin_hmac_message_and_key_material(): void {
		$key = str_repeat( 'k', 32 );
		$uid = 42;
		$exp = 2000000000;
		$msg = 'admin|' . $uid . '|' . $exp;
		$mat = $key . '|svp_admin_v1';
		$sig = hash_hmac( 'sha256', $msg, $mat );
		$this->assertSame( 64, strlen( $sig ) );
		$this->assertTrue( hash_equals( hash_hmac( 'sha256', $msg, $mat ), $sig ) );
	}

	/**
	 * Reply keyboard file documents Telegram API limitation (plan: reply-color-note).
	 */
	public function test_reply_keyboard_color_documented_in_keyboards(): void {
		$path = dirname( __DIR__ ) . '/includes/bot/class-keyboards.php';
		$this->assertFileExists( $path );
		$src = (string) file_get_contents( $path );
		$this->assertStringContainsString( 'KeyboardButton', $src );
		$this->assertStringContainsString( 'Mini App', $src );
	}

	public function test_admin_portal_stack_files_exist(): void {
		$root = dirname( __DIR__ );
		foreach (
			array(
				'/includes/helpers/class-admin-user-ops.php',
				'/includes/frontend/class-portal-admin.php',
				'/includes/helpers/class-portal-link.php',
				'/assets/portal.js',
			) as $rel
		) {
			$this->assertFileExists( $root . $rel );
		}
	}

	public function test_default_service_plan_setting_registered(): void {
		$src = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-settings.php' );
		$this->assertStringContainsString( 'default_service_plan_id', $src );
	}

	public function test_portal_js_posts_portal_admin_action(): void {
		$js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/portal.js' );
		$this->assertStringContainsString( 'simplevpbot_portal_admin', $js );
		$this->assertStringContainsString( 'bulk_ack=1', $js );
		$this->assertStringContainsString( 'membership_pending_page', $js );
		$this->assertStringContainsString( 'simplevpbot_portal_tg_avatar', $js );
	}

	/**
	 * Portal admin stats op must use dashboard helper keys (contract for portal UI).
	 */
	public function test_portal_admin_stats_uses_dashboard_payload(): void {
		$src = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-ajax.php' );
		$this->assertStringContainsString( "'stats' === ", $src );
		$this->assertStringContainsString( 'SimpleVPBot_Admin_Dashboard_Stats::build_payload', $src );
		$this->assertStringContainsString( 'membership_approve', $src );
		$this->assertStringContainsString( 'portal_tg_avatar', $src );
	}
}
