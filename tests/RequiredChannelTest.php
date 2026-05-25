<?php
/**
 * Unit tests for mandatory channel join helper.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class RequiredChannelTest extends TestCase {

	/**
	 * Helper file and settings keys exist.
	 */
	public function test_settings_and_class_present(): void {
		$root = dirname( __DIR__ );
		$this->assertFileExists( $root . '/includes/helpers/class-required-channel.php' );
		$settings = (string) file_get_contents( $root . '/includes/class-settings.php' );
		$this->assertStringContainsString( 'force_join_telegram_enabled', $settings );
		$this->assertStringContainsString( 'force_join_bale_announce_text', $settings );
		$actions = (string) file_get_contents( $root . '/includes/admin/class-admin-actions.php' );
		$this->assertStringContainsString( "case 'force_join':", $actions );
	}

	/**
	 * @dataProvider member_status_provider
	 */
	public function test_member_status_ok( array $member, bool $expected ): void {
		$this->assertSame( $expected, SimpleVPBot_Required_Channel::member_status_ok( $member ) );
	}

	/**
	 * @return array<string, array{0: array<string, mixed>, 1: bool}>
	 */
	public static function member_status_provider(): array {
		return array(
			'member'        => array( array( 'status' => 'member' ), true ),
			'administrator' => array( array( 'status' => 'administrator' ), true ),
			'creator'       => array( array( 'status' => 'creator' ), true ),
			'restricted_in' => array( array( 'status' => 'restricted', 'is_member' => true ), true ),
			'left'          => array( array( 'status' => 'left' ), false ),
			'kicked'        => array( array( 'status' => 'kicked' ), false ),
		);
	}

	/**
	 * Username normalization strips @.
	 */
	public function test_normalize_username(): void {
		$this->assertSame( 'mychannel', SimpleVPBot_Required_Channel::normalize_username( '@mychannel' ) );
	}

	/**
	 * Router references channel gate.
	 */
	public function test_router_gate_hook(): void {
		$router = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/class-router.php' );
		$this->assertStringContainsString( 'maybe_block_required_channel', $router );
		$this->assertStringContainsString( 'chjoin:verify', $router );
	}

	/**
	 * Callback handles chjoin prefix.
	 */
	public function test_callback_chjoin_handler(): void {
		$cb = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-callback.php' );
		$this->assertStringContainsString( 'handle_channel_join', $cb );
		$this->assertStringContainsString( "strpos( \$data, 'chjoin:' )", $cb );
	}

	/**
	 * Dashboard mutation for publish.
	 */
	public function test_dashboard_publish_mutation(): void {
		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( "case 'force_join_publish':", $mut );
		$this->assertStringContainsString( 'op_force_join_publish', $mut );
	}
}
