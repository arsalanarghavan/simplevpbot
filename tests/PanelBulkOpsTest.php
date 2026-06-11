<?php
/**
 * Contract tests for panel-first bulk volume/extend and safe updateClient.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class PanelBulkOpsTest extends TestCase {

	public function test_update_client_uses_single_client_payload_v294(): void {
		$xui = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-xui-client.php' );
		$this->assertStringContainsString( 'build_update_client_settings_payload', $xui );
		$this->assertStringContainsString( "array( 'clients' => array( \$single_client ) )", $xui );
		$this->assertMatchesRegularExpression(
			'/function update_inbound_client_sequential[\\s\\S]*build_update_client_settings_payload[\\s\\S]*wp_json_encode\\( \\$payload_dec \\)/s',
			$xui
		);
	}

	public function test_update_inbound_client_sequential_retries_with_relogin(): void {
		$xui = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-xui-client.php' );
		$this->assertStringContainsString( 'function update_inbound_client_sequential', $xui );
		$this->assertStringContainsString( 'for ( $attempt = 0; $attempt < 4; $attempt++ )', $xui );
		$this->assertStringContainsString( 'merge_client_into_inbound_settings', $xui );
		$this->assertMatchesRegularExpression(
			'/update_inbound_client_sequential[\\s\\S]*clear_session[\\s\\S]*login_with_retries/s',
			$xui
		);
	}

	public function test_provisioner_quota_soft_fail_when_client_exists(): void {
		$prov = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-service-provisioner.php' );
		$this->assertStringContainsString( 'wait_for_client_in_inbound', $prov );
		$this->assertStringContainsString( 'client exists — continuing', $prov );
		$this->assertStringContainsString( 'add_client_request_ok', $prov );
	}

	public function test_approve_surfaces_provision_error_as_reason(): void {
		$rp = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-receipt-processor.php' );
		$this->assertStringContainsString( "'reason'          => \$provision_err", $rp );
		$this->assertStringContainsString( 'reason_code', $rp );
		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( 'receipt_mutate_rest_response', $mut );
	}

	public function test_receipt_mutate_response_json_safe(): void {
		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( 'sanitize_receipt_processor_result_for_json', $mut );
		$this->assertStringContainsString( 'sanitize_receipt_processor_result_for_json( $res )', $mut );
		$this->assertStringNotContainsString( "'data' => \$res", $mut );
		$rest = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( 'dashboard admin mutate exception', $rest );
		$this->assertStringContainsString( 'response_encode_failed', $rest );
		$this->assertStringContainsString( 'wp_json_encode( $res )', $rest );
	}

	public function test_panel_volume_delta_uses_resolve_quota_bytes(): void {
		$renew = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-service-renew.php' );
		$this->assertStringContainsString( 'function apply_panel_volume_delta', $renew );
		$this->assertStringContainsString( 'resolve_quota_bytes', $renew );
		$this->assertStringContainsString( 'apply_panel_extend_days', $renew );
		$this->assertStringContainsString( 'panel_client_expiry_ts', $renew );
		$this->assertStringContainsString( 'panel_update_fail_message', $renew );
		$this->assertStringContainsString( 'clients_bulk_adjust_v3', $renew );
	}

	public function test_renew_v3_bulk_adjust_path(): void {
		$renew = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-service-renew.php' );
		$this->assertStringContainsString( 'is_v3_clients_api()', $renew );
		$this->assertMatchesRegularExpression(
			'/apply_after_payment[\\s\\S]*clients_bulk_adjust_v3/s',
			$renew
		);
	}

	public function test_receipt_provision_error_formatter(): void {
		$rp = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-receipt-processor.php' );
		$this->assertStringContainsString( 'format_provision_error_for_admin', $rp );
		$this->assertStringContainsString( 'panel_quota_patch_failed', $rp );
		$xui = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-xui-client.php' );
		$this->assertStringContainsString( 'function add_client_request', $xui );
		$this->assertStringContainsString( 'function add_client_request_ok', $xui );
	}

	public function test_bulk_panel_targets_and_enqueue(): void {
		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( 'users_bulk_resolve_panel_targets', $mut );
		$this->assertStringContainsString( 'panel_active_clients', $mut );
		$this->assertStringContainsString( 'users_bulk_uses_panel_targets', $mut );
		$model = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-users-bulk-job.php' );
		$this->assertStringContainsString( 'enqueue_panel_targets', $model );
		$cron = (string) file_get_contents( dirname( __DIR__ ) . '/includes/cron/class-cron-users-bulk.php' );
		$this->assertStringContainsString( 'run_one_panel_item', $cron );
		$this->assertStringContainsString( 'apply_panel_volume_delta', $cron );
	}

	public function test_migration_231_bulk_panel_items(): void {
		$act = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-activator.php' );
		$this->assertStringContainsString( "DB_VERSION = '2.4.4'", $act );
		$this->assertStringContainsString( 'maybe_migrate_231_bulk_panel_items', $act );
		$this->assertStringContainsString( 'client_email', $act );
	}

	public function test_ensure_client_protocol_fields_before_update_client(): void {
		$xui = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-xui-client.php' );
		$this->assertStringContainsString( 'function ensure_client_protocol_fields', $xui );
		$this->assertStringContainsString( "case 'trojan':", $xui );
		$this->assertMatchesRegularExpression(
			'/function update_inbound_client_sequential[\\s\\S]*ensure_client_protocol_fields/s',
			$xui
		);
	}

	public function test_resolve_client_path_id_for_update(): void {
		$xui = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-xui-client.php' );
		$this->assertStringContainsString( 'function resolve_client_path_id_for_update', $xui );
		$this->assertStringContainsString( "case 'trojan':", $xui );
		$this->assertStringContainsString( 'resolve_client_path_id_for_update', $xui );
	}

	public function test_renew_and_provisioner_sync_xui_client_id(): void {
		$renew = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-service-renew.php' );
		$this->assertStringContainsString( 'maybe_sync_service_xui_client_id', $renew );
		$this->assertStringContainsString( "'xui_client_uuid' => \$new_id", $renew );
		$prov = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-service-provisioner.php' );
		$this->assertStringContainsString( "quota_patch['uuid']", $prov );
		$this->assertStringContainsString( 'resolve_client_path_id_for_update', $prov );
	}
}
