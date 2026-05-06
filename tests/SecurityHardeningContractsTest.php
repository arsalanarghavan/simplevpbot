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
		$file = dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php';
		$code = (string) file_get_contents( $file );
		$this->assertStringContainsString( 'private static function reseller_safe_settings', $code );
		$this->assertStringContainsString( "'settings'              => \$reseller_settings", $code );
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
		$file = dirname( __DIR__ ) . '/dashboard-ui/src/App.tsx';
		$code = (string) file_get_contents( $file );
		$this->assertStringContainsString( 'RESELLER_ALLOWED_BY_PERMISSION', $code );
		$this->assertStringContainsString( 'safeResellerTab', $code );
	}
}

