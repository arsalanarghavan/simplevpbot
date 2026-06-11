<?php
/**
 * Bot admin nav contract tests.
 *
 * @package SimpleVPBot
 */

/**
 * Class BotAdminNavTest
 */
class BotAdminNavTest extends WP_UnitTestCase {

	public function test_section_ids_match_dashboard_groups() {
		$this->assertContains( 'users', SimpleVPBot_Bot_Admin_Nav::section_ids() );
		$this->assertContains( 'resellers', SimpleVPBot_Bot_Admin_Nav::section_ids() );
		$this->assertContains( 'marketing', SimpleVPBot_Bot_Admin_Nav::section_ids() );
		$this->assertContains( 'finance', SimpleVPBot_Bot_Admin_Nav::section_ids() );
		$this->assertContains( 'settings', SimpleVPBot_Bot_Admin_Nav::section_ids() );
	}

	public function test_site_admin_sees_all_non_reseller_only_tabs() {
		$map = SimpleVPBot_Reseller_Permission_Gate::site_admin_allowed_tabs_map();
		$this->assertTrue( $map['users'] );
		$this->assertTrue( $map['xui_panels'] );
		$this->assertFalse( $map['reseller_settings'] );
	}

	public function test_reseller_default_permissions_allow_dashboard() {
		$map = SimpleVPBot_Reseller_Permission_Gate::reseller_allowed_tabs_map( 0 );
		$this->assertTrue( $map['dashboard'] );
		$this->assertFalse( $map['site_settings'] );
		$this->assertTrue( $map['reseller_settings'] );
	}

	public function test_hub_code_for_tab_deprecated_empty() {
		$this->assertSame( '', SimpleVPBot_Bot_Admin_Nav::hub_code_for_tab( 'plans' ) );
		$this->assertSame( '', SimpleVPBot_Bot_Admin_Nav::hub_code_for_tab( 'receipts' ) );
		$this->assertSame( '', SimpleVPBot_Bot_Admin_Nav::hub_code_for_tab( 'users' ) );
	}

	public function test_panel_handler_and_router_wired() {
		$router = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/class-router.php' );
		$this->assertStringContainsString( "'panel'", $router );
		$this->assertStringContainsString( 'SimpleVPBot_Handler_Admin_Panel::send_panel_entry', $router );
	}
}
