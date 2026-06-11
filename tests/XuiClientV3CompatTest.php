<?php
/**
 * Contract tests for MHSanaei 3x-ui v3 clients/* dual-stack support.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class XuiClientV3CompatTest extends TestCase {

	/**
	 * Flavor detection and persistence helpers exist.
	 */
	public function test_api_flavor_detection_helpers_exist(): void {
		$file = dirname( __DIR__ ) . '/includes/api/class-xui-client.php';
		$code = (string) file_get_contents( $file );
		$this->assertStringContainsString( "const FLAVOR_V3 = 'v3_clients'", $code );
		$this->assertStringContainsString( "const FLAVOR_LEGACY = 'legacy_inbound'", $code );
		$this->assertStringContainsString( 'function detect_api_flavor', $code );
		$this->assertStringContainsString( 'function get_api_flavor', $code );
		$this->assertStringContainsString( 'function set_api_flavor_for_bound_panel', $code );
		$this->assertStringContainsString( 'clients/list/paged?page=1&pageSize=1', $code );
	}

	/**
	 * v3 client CRUD and bulk endpoints are implemented.
	 */
	public function test_v3_client_methods_exist(): void {
		$file = dirname( __DIR__ ) . '/includes/api/class-xui-client.php';
		$code = (string) file_get_contents( $file );
		foreach (
			array(
				'function client_create_v3',
				'function client_update_v3',
				'function client_delete_v3',
				'function client_get_v3',
				'function client_traffic_v3',
				'function clients_bulk_adjust_v3',
				'function client_sub_links_v3',
				'function client_links_v3',
				'function clients_list_paged_v3',
			) as $fn
		) {
			$this->assertStringContainsString( $fn, $code, $fn );
		}
		$this->assertStringContainsString( "'clients/add'", $code );
		$this->assertStringContainsString( "'clients/bulkAdjust'", $code );
	}

	/**
	 * Public facade routes legacy callers through flavor-aware paths.
	 */
	public function test_facade_routes_by_flavor(): void {
		$file = dirname( __DIR__ ) . '/includes/api/class-xui-client.php';
		$code = (string) file_get_contents( $file );
		$this->assertStringContainsString( 'function request_routed', $code );
		$this->assertStringContainsString( 'function is_v3_clients_api', $code );
		$this->assertMatchesRegularExpression(
			'/function add_client_request[\s\S]+is_v3_clients_api\(\)/',
			$code
		);
		$this->assertMatchesRegularExpression(
			'/function update_inbound_client_sequential[\s\S]+client_update_v3/',
			$code
		);
		$this->assertMatchesRegularExpression(
			'/function get_client_traffics[\s\S]+client_traffic_v3/',
			$code
		);
	}

	/**
	 * Panel model and DB migration for panel_api_flavor.
	 */
	public function test_panel_api_flavor_schema(): void {
		$activator = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-activator.php' );
		$this->assertStringContainsString( 'maybe_migrate_246_panel_api_flavor', $activator );
		$this->assertStringContainsString( 'panel_api_flavor', $activator );
		$model = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-panel.php' );
		$this->assertStringContainsString( 'function api_flavor', $model );
		$this->assertStringContainsString( 'function set_api_flavor', $model );
	}

	/**
	 * test_panel persists detected flavor in diag.
	 */
	public function test_panel_detects_api_flavor(): void {
		$file = dirname( __DIR__ ) . '/includes/admin/services/class-service-admin-ops.php';
		$code = (string) file_get_contents( $file );
		$this->assertStringContainsString( 'set_api_flavor_for_bound_panel', $code );
		$this->assertStringContainsString( "diag['api_flavor']", $code );
		$this->assertStringContainsString( 'clients/onlines', $code );
		$this->assertStringContainsString( 'clients_onlines', $code );
	}

	/**
	 * Shared IP parser and inbound client listing helpers for v3.
	 */
	public function test_v3_shared_helpers_exist(): void {
		$xui = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-xui-client.php' );
		$this->assertStringContainsString( 'function parse_client_ips_response', $xui );
		$this->assertStringContainsString( 'function clients_for_inbound_id', $xui );
		$this->assertStringContainsString( 'function parse_onlines_response', $xui );
		$this->assertStringContainsString( 'function fetch_onlines', $xui );
	}

	/**
	 * Onlines parser and v3 fetch facade are implemented.
	 */
	public function test_onlines_v3_wiring(): void {
		$xui = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-xui-client.php' );
		$this->assertMatchesRegularExpression(
			'/function fetch_onlines[\s\S]+clients\/onlines[\s\S]+inbounds\/onlines/',
			$xui
		);
		$this->assertMatchesRegularExpression(
			'/function onlines\(\)[\s\S]+fetch_onlines/',
			$xui
		);
		$cron = (string) file_get_contents( dirname( __DIR__ ) . '/includes/cron/class-cron-panel-online.php' );
		$this->assertStringContainsString( 'Xui_Client::count_onlines_response', $cron );
		$live = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-dashboard-panel-live.php' );
		$this->assertStringContainsString( 'fetch_onlines', $live );
	}

	/**
	 * Audit fix paths use v3 client API instead of inbound.settings.clients only.
	 */
	public function test_audit_v3_branches_wired(): void {
		$renew = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-service-renew.php' );
		$this->assertMatchesRegularExpression(
			'/function apply_add_user_slots_after_payment[\s\S]+is_v3_clients_api\(\)[\s\S]+client_update_v3/',
			$renew
		);
		$this->assertMatchesRegularExpression(
			'/function apply_reduce_user_slots_free[\s\S]+is_v3_clients_api\(\)[\s\S]+client_update_v3/',
			$renew
		);

		$admin = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/services/class-service-admin-ops.php' );
		$this->assertMatchesRegularExpression(
			'/function configs_apply_enable_logged_in[\s\S]+is_v3_clients_api\(\)[\s\S]+client_update_v3/',
			$admin
		);

		$dash = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-service-dashboard-panel.php' );
		$this->assertMatchesRegularExpression(
			'/function xray_set_limit_ip[\s\S]+is_v3_clients_api\(\)[\s\S]+client_update_v3/',
			$dash
		);
		$this->assertStringContainsString( 'parse_client_ips_response', $dash );

		$linker = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-inbound-linker.php' );
		$this->assertStringContainsString( 'clients_for_inbound_id', $linker );
		$this->assertStringContainsString( 'client_get_v3', $linker );

		$bot = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-service.php' );
		$this->assertStringContainsString( 'parse_client_ips_response', $bot );

		$alerts = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-service-alerts.php' );
		$this->assertStringContainsString( 'parse_client_ips_response', $alerts );
	}

	/**
	 * subId regeneration is exposed via dashboard mutations and bot.
	 */
	public function test_sub_id_regen_wired(): void {
		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( 'service_regen_sub_id', $mut );
		$this->assertStringContainsString( 'xray_regenerate_sub_id', $mut );

		$policy = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-mutate-policy.php' );
		$this->assertStringContainsString( "'service_regen_sub_id'", $policy );

		$bot = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-service.php' );
		$this->assertStringContainsString( "if ( 'rs' === \$action )", $bot );
	}

	/**
	 * Manual QA on a real MHSanaei 3x-ui v3 staging panel (not runnable in CI).
	 *
	 * @group manual
	 */
	public function test_manual_v3_staging_checklist(): void {
		$this->markTestSkipped(
			'Run on real v3 staging: (1) test_panel shows clients_onlines 200, '
			. '(2) monitoring onlineNow matches connected client, (3) configs sync is_online, '
			. '(4) new purchase, (5) renew + volume, (6) add/reduce user slots.'
		);
	}
}
