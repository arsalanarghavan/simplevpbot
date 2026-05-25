<?php
/**
 * Tests for multi-URI subscription parsing.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class ConfigLinkMultiUriTest extends TestCase {

	/**
	 * Bootstrap Config_Link without full WordPress.
	 */
	private static function config_link_class(): string {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', dirname( __DIR__ ) . '/' );
		}
		if ( ! class_exists( 'SimpleVPBot_Config_Link' ) ) {
			require_once dirname( __DIR__ ) . '/includes/helpers/class-config-link.php';
		}
		return 'SimpleVPBot_Config_Link';
	}

	/**
	 * Newline-separated URIs stay separate.
	 */
	public function test_parse_newline_separated_uris(): void {
		$cls  = self::config_link_class();
		$u1   = 'vless://11111111-1111-1111-1111-111111111111@1.2.3.4:443?encryption=none#one';
		$u2   = 'vless://22222222-2222-2222-2222-222222222222@5.6.7.8:8080?encryption=none#two';
		$body = $u1 . "\n" . $u2;
		$out  = $cls::parse_subscription_body( $body );
		$this->assertCount( 2, $out );
		$this->assertSame( $u1, $out[0] );
		$this->assertSame( $u2, $out[1] );
	}

	/**
	 * Two URIs glued on one line (no newline) must split.
	 */
	public function test_parse_two_uris_on_one_line(): void {
		$cls  = self::config_link_class();
		$u1   = 'vless://11111111-1111-1111-1111-111111111111@1.2.3.4:443?encryption=none#one';
		$u2   = 'trojan://pass@5.6.7.8:8080?security=none#two';
		$body = $u1 . $u2;
		$out  = $cls::parse_subscription_body( $body );
		$this->assertGreaterThanOrEqual( 2, count( $out ) );
		$this->assertStringContainsString( 'vless://', $out[0] );
		$this->assertStringContainsString( 'trojan://', $out[1] );
	}

	/**
	 * Base64 body with two newline URIs.
	 */
	public function test_parse_base64_wrapped_newlines(): void {
		$cls  = self::config_link_class();
		$u1   = 'vless://aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa@host.example:443?type=tcp#A';
		$u2   = 'vless://bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb@host2.example:8443?type=tcp#B';
		$plain = $u1 . "\n" . $u2;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$body = base64_encode( $plain );
		$out  = $cls::parse_subscription_body( $body );
		$this->assertCount( 2, $out );
	}

	/**
	 * JSON array of URI strings.
	 */
	public function test_parse_json_uri_array(): void {
		$cls = self::config_link_class();
		$u1  = 'vless://11111111-1111-1111-1111-111111111111@1.2.3.4:443#x';
		$u2  = 'vless://22222222-2222-2222-2222-222222222222@1.2.3.4:8443#y';
		$body = json_encode( array( $u1, $u2 ) );
		$out = $cls::parse_subscription_body( $body );
		$this->assertGreaterThanOrEqual( 2, count( $out ) );
	}

	/**
	 * Fragment after # is the per-line remark label (3x-ui external proxy, etc.).
	 */
	public function test_uri_fragment_label(): void {
		$cls = self::config_link_class();
		$uri = 'vless://11111111-1111-1111-1111-111111111111@1.2.3.4:443?encryption=none#External%20Proxy';
		$this->assertSame( 'External Proxy', $cls::uri_fragment_label( $uri ) );
		$this->assertSame( '', $cls::uri_fragment_label( 'vless://uuid@host:443' ) );
	}
}
