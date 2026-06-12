<?php
/**
 * Contract tests for bot webhook diagnostics.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class BotDiagnosticsTest extends TestCase {

	public function test_bot_client_has_get_webhook_info(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-bot-client.php' );
		$this->assertStringContainsString( 'function get_webhook_info', $code );
		$this->assertStringContainsString( "'getWebhookInfo'", $code );
	}

	public function test_service_admin_ops_bot_diagnostics_contract(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/services/class-service-admin-ops.php' );
		$this->assertStringContainsString( 'function bot_diagnostics', $code );
		$this->assertStringContainsString( 'function mask_bot_token', $code );
		$this->assertStringContainsString( 'pending_update_count', $code );
		$this->assertStringContainsString( 'get_webhook_info', $code );
	}

	public function test_mutations_bot_diagnostics_op_contract(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( "case 'bot_diagnostics':", $code );
		$this->assertStringContainsString( 'function op_bot_diagnostics', $code );
		$this->assertStringContainsString( 'reveal_token', $code );
		$this->assertStringContainsString( 'manage_options', $code );
	}

	public function test_mutate_policy_includes_bot_diagnostics(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-mutate-policy.php' );
		$this->assertStringContainsString( "'bot_diagnostics'", $code );
	}

	public function test_mask_bot_token_contract(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/services/class-service-admin-ops.php' );
		$this->assertStringContainsString( 'function mask_bot_token', $code );
		$this->assertStringContainsString( "substr( \$t, 0, 12 ) . '…' . substr( \$t, -4 )", $code );
	}

	public function test_dashboard_ui_diagnostics_dialog_contract(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/frontend/src/components/dashboard-bot-diagnostics-dialog.tsx' );
		$this->assertStringContainsString( 'bot_diagnostics', $code );
		$this->assertStringContainsString( 'reveal_token', $code );
		$this->assertStringContainsString( 'pending_update_count', $code );
	}
}
