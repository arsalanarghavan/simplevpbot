<?php
/**
 * Unified portal subscription (dual-mode URL) contract tests.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class PortalSubscriptionTest extends TestCase {

	/**
	 * @var array<string, mixed>
	 */
	private $savedServer = array();

	/**
	 * @var array<string, mixed>
	 */
	private $savedGet = array();

	/**
	 * Bootstrap Portal_Subscription without full WordPress.
	 */
	private static function portal_subscription_class(): string {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', dirname( __DIR__ ) . '/' );
		}
		if ( ! function_exists( 'wp_unslash' ) ) {
			/**
			 * @param mixed $v Value.
			 * @return mixed
			 */
			function wp_unslash( $v ) {
				return is_string( $v ) ? stripslashes( $v ) : $v;
			}
		}
		if ( ! class_exists( 'SimpleVPBot_Portal_Subscription' ) ) {
			require_once dirname( __DIR__ ) . '/includes/helpers/class-portal-subscription.php';
		}
		return 'SimpleVPBot_Portal_Subscription';
	}

	protected function setUp(): void {
		parent::setUp();
		$this->savedServer = $_SERVER;
		$this->savedGet    = $_GET;
	}

	protected function tearDown(): void {
		$_SERVER = $this->savedServer;
		$_GET    = $this->savedGet;
		parent::tearDown();
	}

	/**
	 * Chrome-like Accept → browser.
	 */
	public function test_is_browser_request_with_text_html(): void {
		$cls = self::portal_subscription_class();
		$_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
		unset( $_GET['svp_fmt'] );
		$this->assertTrue( $cls::is_browser_request() );
	}

	/**
	 * v2rayNG-like Accept → subscription client.
	 */
	public function test_is_browser_request_without_text_html(): void {
		$cls = self::portal_subscription_class();
		$_SERVER['HTTP_ACCEPT'] = '*/*';
		unset( $_GET['svp_fmt'] );
		$this->assertFalse( $cls::is_browser_request() );
	}

	/**
	 * Empty Accept → subscription client (not a browser).
	 */
	public function test_is_browser_request_empty_accept(): void {
		$cls = self::portal_subscription_class();
		unset( $_SERVER['HTTP_ACCEPT'] );
		unset( $_GET['svp_fmt'] );
		$this->assertFalse( $cls::is_browser_request() );
	}

	/**
	 * svp_fmt=sub forces subscription even with text/html Accept.
	 */
	public function test_force_subscription_format_overrides_browser(): void {
		$cls = self::portal_subscription_class();
		$_SERVER['HTTP_ACCEPT'] = 'text/html';
		$_GET['svp_fmt']        = 'sub';
		$this->assertFalse( $cls::is_browser_request() );
		$this->assertTrue( $cls::force_subscription_format() );
	}

	/**
	 * build_body returns base64 newline URIs decodable by Config_Link parser.
	 */
	public function test_build_body_base64_newline_uris(): void {
		$cls = self::portal_subscription_class();
		$u1  = 'vless://11111111-1111-1111-1111-111111111111@1.2.3.4:443?encryption=none#one';
		$u2  = 'vless://22222222-2222-2222-2222-222222222222@5.6.7.8:8080?encryption=none#two';
		$body = $cls::build_body( array( $u1, $u2 ) );
		$this->assertNotSame( '', $body );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$plain = base64_decode( $body, true );
		$this->assertIsString( $plain );
		$this->assertStringContainsString( 'vless://', $plain );
		$this->assertStringContainsString( $u1, $plain );
		$this->assertStringContainsString( $u2, $plain );
	}

	/**
	 * subscription-userinfo header fragment from service row + usage data.
	 */
	public function test_userinfo_from_service(): void {
		$cls  = self::portal_subscription_class();
		$svc  = (object) array(
			'total_traffic' => 10737418240,
			'used_traffic'  => 1073741824,
			'expires_at'    => '2030-01-15 12:00:00',
		);
		$data = array(
			'down_gb' => '0.50',
			'up_gb'   => '0.25',
		);
		$info = $cls::userinfo_from_service( $svc, $data );
		$this->assertStringContainsString( 'upload=', $info );
		$this->assertStringContainsString( 'download=', $info );
		$this->assertStringContainsString( 'total=10737418240', $info );
		$this->assertMatchesRegularExpression( '/expire=\d+/', $info );
	}

	/**
	 * Portal front registers subscription hook before HTML render.
	 */
	public function test_portal_front_registers_maybe_serve(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/frontend/class-portal-front.php' );
		$this->assertStringContainsString( "SimpleVPBot_Portal_Subscription', 'maybe_serve' ), -1", $code );
	}

	/**
	 * Customer portal links use 365-day TTL.
	 */
	public function test_customer_ttl_used_in_portal_links(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-portal-link.php' );
		$this->assertStringContainsString( 'CUSTOMER_TTL', $code );
		$this->assertStringContainsString( '31536000', $code );
		$this->assertMatchesRegularExpression(
			'/function build_url[\s\S]*CUSTOMER_TTL/',
			$code
		);
		$this->assertMatchesRegularExpression(
			'/function build_service_url[\s\S]*CUSTOMER_TTL/',
			$code
		);
	}

	/**
	 * Unified import URL points to signed portal link in handler.
	 */
	public function test_handler_unified_import_sub_url(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-service.php' );
		$this->assertStringContainsString( "'import_sub_url'   => \$unified", $code );
		$this->assertStringContainsString( "'subscription_url' => \$unified", $code );
	}
}
