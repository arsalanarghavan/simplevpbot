<?php
/**
 * Bot admin IDOR / scope contract tests (Bot Complete Parity + Audit Fix).
 *
 * @package SimpleVPBot
 */

use PHPUnit\Framework\TestCase;

/**
 * Class BotAdminIdorRuntimeTest
 */
class BotAdminIdorRuntimeTest extends TestCase {

	public function test_catalog_scope_helper_exists() {
		$this->assertTrue( class_exists( 'SimpleVPBot_Bot_Admin_Catalog_Scope' ) );
		$this->assertTrue( method_exists( 'SimpleVPBot_Bot_Admin_Catalog_Scope', 'filter_plans' ) );
		$this->assertTrue( method_exists( 'SimpleVPBot_Bot_Admin_Catalog_Scope', 'guard_card' ) );
	}

	public function test_economics_denies_reseller_in_handler() {
		$src = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-economics.php' );
		$this->assertStringContainsString( 'permission_actor_id', $src );
		$this->assertStringContainsString( 'denied_tab', $src );
		$this->assertStringContainsString( 'panel_economics_save', $src );
		$this->assertStringContainsString( 'shared_economics_save', $src );
	}

	public function test_hub_removed_pnl_only() {
		$this->assertFileDoesNotExist( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-hub.php' );
		$this->assertFileExists( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-pnl.php' );
	}

	public function test_callback_routes_pnl_not_adm() {
		$src = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-callback.php' );
		$this->assertStringContainsString( 'Handler_Admin_Pnl::handle', $src );
		$this->assertStringNotContainsString( 'Handler_Admin_Hub::handle', $src );
	}

	public function test_mutate_bridge_has_catalog_and_economics_ops() {
		$this->assertTrue( class_exists( 'SimpleVPBot_Bot_Admin_Mutate' ) );
		$perm = SimpleVPBot_Bot_Admin_Mutate::BOT_OP_PERMISSION;
		$this->assertArrayHasKey( 'plan', $perm );
		$this->assertArrayHasKey( 'card_delete', $perm );
		$this->assertArrayHasKey( 'unit_economics_config_save', $perm );
		$this->assertArrayHasKey( 'panel_economics_save', $perm );
		$this->assertArrayHasKey( 'panel_economics_mark_paid', $perm );
	}

	public function test_catalog_unified_mutate_path_no_direct_card_delete_in_pnl() {
		$pnl = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-pnl.php' );
		$this->assertStringContainsString( 'Handler_Admin_Catalog::dispatch_legacy', $pnl );
		$this->assertStringNotContainsString( 'SimpleVPBot_Model_Card::delete', $pnl );
	}

	public function test_catalog_create_wizard_uses_valid_codes() {
		$catalog = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-catalog.php' );
		$this->assertStringContainsString( "=> 'pl'", $catalog );
		$this->assertStringNotContainsString( "'pln'", $catalog );
		$this->assertStringContainsString( 'admin_catalog_plan_edit', $catalog );
	}

	public function test_tab_hub_codes_removed() {
		$nav = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-bot-admin-nav.php' );
		$this->assertStringNotContainsString( 'TAB_HUB_CODES', $nav );
		$this->assertSame( '', SimpleVPBot_Bot_Admin_Nav::hub_code_for_tab( 'plans' ) );
	}

	public function test_modular_admin_facades_exist() {
		foreach ( array(
			'SimpleVPBot_Handler_Admin_Bulk',
			'SimpleVPBot_Handler_Admin_Inbound',
			'SimpleVPBot_Handler_Admin_Backup',
			'SimpleVPBot_Handler_Admin_Texts',
			'SimpleVPBot_Handler_Admin_Logs',
			'SimpleVPBot_Handler_Admin_Stats',
		) as $cls ) {
			$this->assertTrue( class_exists( $cls ), $cls );
		}
	}

	public function test_charges_pagination_has_next_guard() {
		$finance = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-finance.php' );
		$this->assertStringContainsString( 'has_next', $finance );
		$this->assertStringContainsString( 'SELECT COUNT(*)', $finance );
	}

	public function test_catalog_scope_guards_plan_and_category() {
		$scope = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-bot-admin-catalog-scope.php' );
		$this->assertStringContainsString( 'guard_plan', $scope );
		$this->assertStringContainsString( 'guard_category', $scope );
		$catalog = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-catalog.php' );
		$this->assertStringContainsString( 'guard_plan', $catalog );
		$this->assertStringContainsString( 'guard_category', $catalog );
	}

	public function test_settings_catalog_create_uses_mutate() {
		$settings = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-settings.php' );
		$this->assertStringContainsString( 'Bot_Admin_Mutate::apply_for_user', $settings );
		$this->assertStringContainsString( "'plan_category'", $settings );
		$this->assertStringContainsString( "'card_add'", $settings );
	}

	public function test_marketing_discount_allow_minmax_wizard() {
		$mkt = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-marketing.php' );
		$this->assertStringContainsString( 'route_discount_allow_minmax', $mkt );
		$this->assertStringContainsString( 'allow_flags', $mkt );
		$this->assertStringContainsString( "'enabled' === \$step", $mkt );
	}

	public function test_economics_volume_mode_and_line_delete() {
		$eco = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-economics.php' );
		$this->assertStringContainsString( 'volume_mode', $eco );
		$this->assertStringContainsString( 'volume_window', $eco );
		$this->assertStringContainsString( 'route_delete_line_state', $eco );
	}

	public function test_pnl_no_legacy_catalog_list_fallbacks() {
		$pnl = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-pnl.php' );
		$this->assertStringNotContainsString( 'send_plans_list', $pnl );
		$this->assertStringNotContainsString( 'send_cards_list', $pnl );
		$this->assertStringNotContainsString( 'send_plan_categories_list', $pnl );
	}
}
