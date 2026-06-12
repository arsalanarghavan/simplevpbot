<?php
/**
 * Contract tests for reseller hardening changes.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class SecurityHardeningContractsTest extends TestCase {

	/**
	 * Reseller dashboard state must use safe settings whitelist.
	 */
	public function test_reseller_state_uses_safe_settings_whitelist(): void {
		$rest = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$set  = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-settings.php' );
		$this->assertStringContainsString( 'dashboard_slice_for_reseller_operator', $rest );
		$this->assertStringContainsString( 'function dashboard_slice_for_reseller_operator', $set );
		$this->assertStringContainsString( "'telegram_bot_username'", $set );
	}

	/**
	 * Runtime must not fall back to global token in reseller context.
	 */
	public function test_runtime_disables_global_token_fallback_for_reseller_context(): void {
		$file = dirname( __DIR__ ) . '/includes/bot/class-bot-runtime.php';
		$code = (string) file_get_contents( $file );
		$this->assertStringContainsString( 'never fall back to global bot token', $code );
		$this->assertStringContainsString( "return '';", $code );
	}

	/**
	 * Frontend must include reseller tab guard map.
	 */
	public function test_frontend_has_reseller_permission_tab_guard(): void {
		$file = dirname( __DIR__ ) . '/frontend/src/App.tsx';
		$code = (string) file_get_contents( $file );
		$this->assertStringContainsString( 'RESELLER_ALLOWED_BY_PERMISSION', $code );
		$this->assertStringContainsString( 'safeResellerTab', $code );
	}
}

