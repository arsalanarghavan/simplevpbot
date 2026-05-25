<?php
/**
 * Contract tests for bank card checkout rotation.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class CardRotationTest extends TestCase {

	/**
	 * Helper class and settings default exist.
	 */
	public function test_card_rotation_files_and_defaults(): void {
		$root = dirname( __DIR__ );
		$this->assertFileExists( $root . '/includes/helpers/class-card-rotation.php' );
		$settings = (string) file_get_contents( $root . '/includes/class-settings.php' );
		$this->assertStringContainsString( "'cards_rotation_cursors'", $settings );
		$plugin = (string) file_get_contents( $root . '/includes/class-plugin.php' );
		$this->assertStringContainsString( 'SimpleVPBot_Card_Rotation', $plugin );
	}

	/**
	 * Model delegates to Card_Rotation instead of first-eligible loop.
	 */
	public function test_model_card_delegates_to_rotation_helper(): void {
		$root  = dirname( __DIR__ );
		$model = (string) file_get_contents( $root . '/includes/models/class-model-card.php' );
		$this->assertStringContainsString( 'SimpleVPBot_Card_Rotation::pick_for_checkout', $model );
		$this->assertStringContainsString( 'resolve_owner_scope_key', $model );
		$this->assertStringNotContainsString( 'approved_sum_for_card_today', $model );
	}

	/**
	 * Rotation helper implements round-robin cursor and random mode.
	 */
	public function test_rotation_helper_has_loop_and_random(): void {
		$root = dirname( __DIR__ );
		$code = (string) file_get_contents( $root . '/includes/helpers/class-card-rotation.php' );
		$this->assertStringContainsString( 'pick_round_robin', $code );
		$this->assertStringContainsString( 'pick_random', $code );
		$this->assertStringContainsString( 'advance_cursor', $code );
		$this->assertStringContainsString( "'random'", $code );
		$this->assertStringContainsString( 'allowed_display_modes', $code );
	}

	/**
	 * Admin allows random display mode when helper is present.
	 */
	public function test_admin_actions_allows_random_mode(): void {
		$root = dirname( __DIR__ );
		$code = (string) file_get_contents( $root . '/includes/admin/class-admin-actions.php' );
		$this->assertStringContainsString( 'SimpleVPBot_Card_Rotation::sanitize_display_mode', $code );
	}

	/**
	 * Dashboard UI exposes random mode option.
	 */
	public function test_dashboard_ui_random_mode(): void {
		$root = dirname( __DIR__ );
		$tsx  = (string) file_get_contents( $root . '/dashboard-ui/src/components/dashboard-cards-admin.tsx' );
		$this->assertStringContainsString( 'value="random"', $tsx );
		$this->assertStringContainsString( 'parseCardsDisplayMode', $tsx );
		$wl = (string) file_get_contents( $root . '/dashboard-ui/src/components/site-settings/site-settings-whitelabel-tab.tsx' );
		$this->assertStringContainsString( 'value="random"', $wl );
	}
}
