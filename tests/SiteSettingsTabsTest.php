<?php
/**
 * Smoke: Site settings hub files, settings keys, logs REST route.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class SiteSettingsTabsTest extends TestCase {

	/**
	 * New settings keys and admin tabs exist in PHP.
	 */
	public function test_settings_keys_and_apply_tabs(): void {
		$root     = dirname( __DIR__ );
		$settings = (string) file_get_contents( $root . '/includes/class-settings.php' );
		$actions  = (string) file_get_contents( $root . '/includes/admin/class-admin-actions.php' );

		foreach (
			array(
				'dashboard_site_name',
				'support_info',
				'support_telegram_username',
				'support_bale_username',
				'telegram_proxy_enabled',
				'default_reseller_permissions',
				'subscription_config_label_override',
				'config_label_prefix',
				'config_label_number_start',
				'inbound_display_names',
				'alert_ip_warn_min_distinct',
			) as $key
		) {
			$this->assertStringContainsString( "'{$key}'", $settings );
		}

		foreach ( array( 'whitelabel', 'service_naming', 'proxy', 'relay', 'resellers_defaults', 'force_join' ) as $tab ) {
			$this->assertStringContainsString( "case '{$tab}':", $actions );
		}

		$this->assertStringContainsString( 'force_join_telegram_enabled', $settings );
		$this->assertFileExists( $root . '/includes/helpers/class-required-channel.php' );
	}

	/**
	 * Logs model, REST route, and mutations registered.
	 */
	public function test_logs_api_and_mutations(): void {
		$root = dirname( __DIR__ );
		$this->assertFileExists( $root . '/includes/models/class-model-log.php' );
		$this->assertFileExists( $root . '/includes/helpers/class-telegram-http.php' );

		$rest = (string) file_get_contents( $root . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( "'/dashboard/admin/logs'", $rest );
		$this->assertStringContainsString( 'route_admin_logs', $rest );

		$mut = (string) file_get_contents( $root . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( "case 'logs_clear':", $mut );
		$this->assertStringContainsString( "case 'telegram_proxy_test':", $mut );
	}

	/**
	 * Dashboard UI hub component and subtabs exist.
	 */
	public function test_dashboard_ui_files(): void {
		$root = dirname( __DIR__ ) . '/frontend/src';
		$this->assertFileExists( $root . '/components/dashboard-site-settings-admin.tsx' );
		foreach (
			array(
				'site-settings-whitelabel-tab.tsx',
				'site-settings/image-url-field.tsx',
				'site-settings/color-hex-field.tsx',
				'site-settings-proxy-tab.tsx',
				'site-settings-relay-tab.tsx',
				'site-settings-notifications-tab.tsx',
				'site-settings-logs-tab.tsx',
				'site-settings-resellers-tab.tsx',
				'site-settings-service-naming-tab.tsx',
			) as $f
		) {
			$this->assertFileExists( $root . '/components/site-settings/' . $f );
		}
		$this->assertFileExists( $root . '/components/ui/tabs.tsx' );
		$this->assertFileExists( $root . '/components/dashboard-force-join-admin.tsx' );
	}
}
