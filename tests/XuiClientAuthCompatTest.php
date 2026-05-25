<?php
/**
 * Contract tests for 3x-ui auth compatibility (legacy cookie + modern CSRF + bearer).
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class XuiClientAuthCompatTest extends TestCase {

	/**
	 * Client must cascade: modern CSRF login then legacy loginSecret.
	 */
	public function test_login_cascades_modern_then_legacy(): void {
		$file = dirname( __DIR__ ) . '/includes/api/class-xui-client.php';
		$code = (string) file_get_contents( $file );
		$this->assertStringContainsString( 'function login_modern_cookie', $code );
		$this->assertStringContainsString( 'function login_legacy_cookie', $code );
		$this->assertStringContainsString( 'ensure_csrf_token()', $code );
		$this->assertStringContainsString( 'login_legacy_cookie( $c )', $code );
		$this->assertStringContainsString( "'loginSecret' =>", $code );
		$this->assertStringContainsString( "'twoFactorCode' =>", $code );
	}

	/**
	 * Legacy path must not require CSRF before login.
	 */
	public function test_legacy_login_uses_login_secret_without_csrf_header(): void {
		$file = dirname( __DIR__ ) . '/includes/api/class-xui-client.php';
		$code = (string) file_get_contents( $file );
		$this->assertStringContainsString( 'delete_transient( self::csrf_transient_name() )', $code );
		$this->assertStringContainsString( "'legacy_cookie'", $code );
		$this->assertMatchesRegularExpression(
			'/attempt_login_post\([^)]+array\(\)[^)]+false\s*\)/s',
			$code
		);
	}

	/**
	 * Auth base discovery and caching helpers exist.
	 */
	public function test_auth_base_candidates_supports_discovery(): void {
		$file = dirname( __DIR__ ) . '/includes/api/class-xui-client.php';
		$code = (string) file_get_contents( $file );
		$this->assertStringContainsString( 'function discover_auth_bases_from_index', $code );
		$this->assertStringContainsString( 'discover_auth_bases_from_index( $root )', $code );
		$this->assertStringContainsString( 'auth_base_transient_name()', $code );
	}

	/**
	 * normalize_panel_url strips erroneous trailing /panel only.
	 */
	public function test_normalize_panel_url_strips_trailing_panel_segment(): void {
		$file = dirname( __DIR__ ) . '/includes/api/class-xui-client.php';
		$code = (string) file_get_contents( $file );
		$this->assertStringContainsString( '#/panel$#i', $code );
		$this->assertStringContainsString( 'function normalize_panel_url', $code );
	}

	/**
	 * POST requests attach CSRF only when transient is set (modern flow).
	 */
	public function test_request_attaches_csrf_only_from_transient(): void {
		$file = dirname( __DIR__ ) . '/includes/api/class-xui-client.php';
		$code = (string) file_get_contents( $file );
		$this->assertStringContainsString( "get_transient( self::csrf_transient_name() )", $code );
		$this->assertStringContainsString( "'X-CSRF-Token'", $code );
	}

	/**
	 * Admin test_panel surfaces auth_flow in diagnostics.
	 */
	public function test_admin_test_panel_exposes_auth_flow(): void {
		$file = dirname( __DIR__ ) . '/includes/admin/services/class-service-admin-ops.php';
		$code = (string) file_get_contents( $file );
		$this->assertStringContainsString( "'auth_flow'", $code );
		$this->assertStringContainsString( "'csrf_skipped'", $code );
		$this->assertStringContainsString( 'legacy_cookie', $code );
	}
}
