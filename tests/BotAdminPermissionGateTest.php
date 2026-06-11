<?php
/**
 * Bot admin permission gate contract tests.
 *
 * @package SimpleVPBot
 */

/**
 * Class BotAdminPermissionGateTest
 */
class BotAdminPermissionGateTest extends WP_UnitTestCase {

	public function test_tab_permission_map_has_core_keys() {
		$this->assertSame( 'users.manage', SimpleVPBot_Reseller_Permission_Gate::TAB_PERMISSION_MAP['users'] );
		$this->assertSame( 'receipts.review', SimpleVPBot_Reseller_Permission_Gate::TAB_PERMISSION_MAP['receipts'] );
		$this->assertSame( 'marketing.lifecycle', SimpleVPBot_Reseller_Permission_Gate::TAB_PERMISSION_MAP['marketing_lifecycle'] );
	}

	public function test_may_call_op_site_admin_always_true() {
		$this->assertTrue( SimpleVPBot_Reseller_Permission_Gate::may_call_op( 0, 'receipt_review' ) );
	}

	public function test_rest_delegates_to_gate() {
		$rest = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( 'SimpleVPBot_Reseller_Permission_Gate::reseller_allowed_tabs_map', $rest );
	}

	public function test_pnl_receipt_guard() {
		$pnl = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-pnl.php' );
		$this->assertStringContainsString( 'may_call_op', $pnl );
		$this->assertStringContainsString( 'receipt_review', $pnl );
		$this->assertStringContainsString( 'bot_admin_guard_op', $pnl );
		$this->assertStringContainsString( 'user_approve', $pnl );
	}

	public function test_portal_delegates_permission_gate() {
		$ajax = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-ajax.php' );
		$this->assertStringContainsString( 'SimpleVPBot_Reseller_Permission_Gate::may_call_op_by_permission', $ajax );
	}

	public function test_callback_inline_permission_guards() {
		$cb = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-callback.php' );
		$this->assertStringContainsString( 'user_approve', $cb );
		$this->assertStringContainsString( 'user_reject', $cb );
		$this->assertStringContainsString( 'receipt_approve', $cb );
		$this->assertStringContainsString( 'receipt_reject', $cb );
		$this->assertStringContainsString( 'Bot_Admin_Guard::may_call_op', $cb );
		$this->assertStringContainsString( 'bootstrap_acting_admin_from_ctx', $cb );
	}

	public function test_broadcast_downline_scoped() {
		$admin = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin.php' );
		$this->assertStringContainsString( 'broadcast_recipients', $admin );
		$finance = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-finance.php' );
		$this->assertStringContainsString( 'bot_admin_scope_user_ids', $finance );
	}

	public function test_reg_callback_show_alert_on_deny() {
		$cb = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-callback.php' );
		$this->assertStringContainsString( 'handle_registration', $cb );
		$this->assertStringContainsString( 'show_alert', $cb );
		$this->assertStringContainsString( 'msg.admin.denied_permission', $cb );
	}
}
