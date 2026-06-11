<?php
/**
 * Bot admin mutation bridge contract tests.
 *
 * @package SimpleVPBot
 */

/**
 * Class BotAdminMutateBridgeTest
 */
class BotAdminMutateBridgeTest extends WP_UnitTestCase {

	public function test_mutate_bridge_class_exists() {
		$this->assertTrue( class_exists( 'SimpleVPBot_Bot_Admin_Mutate' ) );
	}

	public function test_bot_op_permission_map_has_core_ops() {
		$map = SimpleVPBot_Bot_Admin_Mutate::BOT_OP_PERMISSION;
		$this->assertArrayHasKey( 'discount_save', $map );
		$this->assertArrayHasKey( 'discount_delete', $map );
		$this->assertArrayHasKey( 'marketing_rule_save', $map );
		$this->assertArrayHasKey( 'reseller_panel_prices_save', $map );
		$this->assertNull( $map['marketing_rule_save'] );
	}

	public function test_apply_for_user_rejects_invalid_actor() {
		$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user( 0, 'discount_save', array() );
		$this->assertFalse( $result['ok'] );
	}

	public function test_discount_post_from_wizard_maps_fields() {
		$post = SimpleVPBot_Bot_Admin_Mutate::discount_post_from_wizard(
			array(
				'code'               => 'TEST10',
				'type'               => 'percent',
				'value'              => 10,
				'max_uses'           => 5,
				'valid_until'        => '2026-12-31',
				'allowed_plan_ids'   => array( 1, 2 ),
				'active'             => 1,
			)
		);
		$this->assertSame( 'TEST10', $post['svpc_code'] );
		$this->assertSame( 'percent', $post['svpc_type'] );
		$this->assertSame( '5', $post['svpc_max_uses'] );
		$this->assertSame( '2026-12-31', $post['svpc_valid_until'] );
		$this->assertSame( array( 1, 2 ), $post['svpc_allowed_plan_ids'] );
	}

	public function test_dashboard_mutations_has_bot_site_admin_wrapper() {
		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( 'with_bot_site_admin', $mut );
		$this->assertStringContainsString( 'bot_acting_as_site_admin', $mut );
	}
}
