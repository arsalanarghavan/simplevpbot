<?php
/**
 * Contract smoke: Telegram relay integration files and routing hooks.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class TelegramRelayTest extends TestCase {

	/**
	 * Relay helper class and settings keys exist.
	 */
	public function test_relay_files_and_settings_keys(): void {
		$root     = dirname( __DIR__ );
		$settings = (string) file_get_contents( $root . '/includes/class-settings.php' );
		$relay    = (string) file_get_contents( $root . '/includes/helpers/class-telegram-relay.php' );
		$http     = (string) file_get_contents( $root . '/includes/helpers/class-telegram-http.php' );
		$plugin   = (string) file_get_contents( $root . '/includes/class-plugin.php' );

		$this->assertFileExists( $root . '/includes/helpers/class-telegram-relay.php' );
		$this->assertFileExists( $root . '/relay-server/package.json' );
		$this->assertFileExists( $root . '/relay-server/scripts/install.sh' );
		$this->assertFileExists( $root . '/relay-server/src/cli/svp-relay.ts' );

		foreach (
			array(
				'telegram_relay_enabled',
				'telegram_relay_base_url',
				'telegram_relay_public_url',
				'telegram_relay_shared_secret',
				'telegram_relay_wp_forward_url',
				'telegram_relay_tenant_id',
				'telegram_relay_force',
			) as $key
		) {
			$this->assertStringContainsString( "'{$key}'", $settings );
		}

		$this->assertStringContainsString( 'build_config_snapshot', $relay );
		$this->assertStringContainsString( 'push_config_to_relay', $relay );
		$this->assertStringContainsString( 'set_webhook_via_relay', $relay );
		$this->assertStringContainsString( 'collect_domains', $relay );
		$this->assertStringContainsString( 'status_via_relay', $relay );
		$this->assertStringContainsString( 'public_url_for_reseller', $relay );
		$this->assertStringContainsString( 'SimpleVPBot_Telegram_Relay::bot_api_base_url', $http );
		$this->assertStringContainsString( 'SimpleVPBot_Telegram_Relay::init()', $plugin );
	}

	/**
	 * Admin routing and dashboard mutations for relay mode.
	 */
	public function test_relay_admin_routing_and_mutations(): void {
		$root    = dirname( __DIR__ );
		$ops     = (string) file_get_contents( $root . '/includes/admin/services/class-service-admin-ops.php' );
		$actions = (string) file_get_contents( $root . '/includes/admin/class-admin-actions.php' );
		$mut     = (string) file_get_contents( $root . '/includes/admin/class-dashboard-admin-mutations.php' );

		$this->assertStringContainsString( "SimpleVPBot_Telegram_Relay::set_webhook_via_relay", $ops );
		$this->assertStringContainsString( 'expected_webhook_url_main', $ops );
		$this->assertStringContainsString( "case 'relay':", $actions );
		foreach (
			array(
				'telegram_relay_test',
				'telegram_relay_sync',
				'telegram_relay_set_webhook',
				'telegram_relay_rotate_secret',
				'telegram_relay_status',
				'telegram_relay_domains_sync',
				'telegram_relay_set_webhook_reseller',
			) as $op
		) {
			$this->assertStringContainsString( "case '{$op}':", $mut );
		}
	}

	/**
	 * Dashboard relay tab UI exists.
	 */
	public function test_relay_dashboard_ui(): void {
		$root = dirname( __DIR__ ) . '/dashboard-ui/src';
		$this->assertFileExists( $root . '/components/site-settings/site-settings-relay-tab.tsx' );
		$admin = (string) file_get_contents( $root . '/components/dashboard-site-settings-admin.tsx' );
		$this->assertStringContainsString( 'SiteSettingsRelayTab', $admin );
		$this->assertStringContainsString( 'value="relay"', $admin );
		$sub = (string) file_get_contents( $root . '/lib/site-settings-subtab.ts' );
		$this->assertStringContainsString( '"relay"', $sub );
	}
}
