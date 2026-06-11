<?php
/**
 * Bot admin panel /start ↔ /panel toggle contract tests.
 *
 * @package SimpleVPBot
 */

/**
 * Class BotPanelToggleTest
 */
class BotPanelToggleTest extends WP_UnitTestCase {

	public function test_router_panel_only_no_admin_alias() {
		$router = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/class-router.php' );
		$this->assertStringContainsString( "'panel' === \$cmd", $router );
		$this->assertStringNotContainsString( "'admin'", $router );
		$this->assertStringContainsString( 'msg.admin.panel_denied', $router );
	}

	public function test_start_clears_admin_mode() {
		$start = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-start.php' );
		$this->assertStringContainsString( 'admin_mode', $start );
		$this->assertStringContainsString( 'State::clear', $start );
		$this->assertStringContainsString( 'is_platform_admin', $start );
	}

	public function test_panel_entry_clears_state_and_sets_admin_mode() {
		$panel = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-panel.php' );
		$this->assertStringContainsString( 'State::clear', $panel );
		$this->assertStringContainsString( "'admin_mode' => 1", $panel );
	}

	public function test_admin_nav_frees_wizard() {
		$nav = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-bot-admin-nav.php' );
		$this->assertStringContainsString( 'is_admin_nav_text', $nav );
		$router = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/class-router.php' );
		$this->assertStringContainsString( 'is_admin_nav_text', $router );
	}

	public function test_bot_admin_guard_bootstrap_and_broadcast() {
		$guard = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-bot-admin-guard.php' );
		$this->assertStringContainsString( 'bootstrap_acting_admin_from_ctx', $guard );
		$this->assertStringContainsString( 'broadcast_recipients', $guard );
		$this->assertStringContainsString( 'effective_moderatable_user_ids', $guard );
		$scope = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-bot-reseller-scope.php' );
		$this->assertStringContainsString( 'resolve_scope_reseller_id', $scope );
	}

	public function test_notifications_tab_in_bot_nav() {
		$nav = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-bot-admin-nav.php' );
		$this->assertStringContainsString( "'notifications'", $nav );
		$this->assertStringContainsString( "'not'", $nav );
	}
}
