<?php
/**
 * Contract tests for admin receipt/membership captions.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once dirname( __DIR__ ) . '/includes/helpers/class-bot-persian-text.php';
require_once dirname( __DIR__ ) . '/includes/helpers/class-bot-admin-user-caption.php';

/**
 * @coversNothing
 */
class BotAdminUserCaptionTest extends TestCase {

	/**
	 * Caption helper exposes card deposit and referral line builders.
	 */
	public function test_caption_helper_methods_exist(): void {
		$root = dirname( __DIR__ );
		$code = (string) file_get_contents( $root . '/includes/helpers/class-bot-admin-user-caption.php' );
		$this->assertStringContainsString( 'function card_deposit_line', $code );
		$this->assertStringContainsString( 'function invited_by_line', $code );
		$this->assertStringContainsString( 'function referrer_display_label', $code );
		$this->assertStringContainsString( 'append_volume_suffix', $code );
		$this->assertStringContainsString( 'is_zero_toman', $code );
		$this->assertStringContainsString( 'msg.admin.caption.amount_line_free', $code );
		$this->assertStringContainsString( 'card_deposit_line( (int) $receipt_id )', $code );
		$this->assertStringContainsString( 'invited_by_line( $user )', $code );
		$this->assertStringContainsString( '💳 کارت واریز:', $code );
		$this->assertStringContainsString( '🔗 با لینک کسب درآمد از طرف', $code );
	}

	/**
	 * Per-GB plan captions append گیگ from meta volume_gb.
	 */
	public function test_selected_service_line_per_gb_volume(): void {
		$root = dirname( __DIR__ );
		$code = (string) file_get_contents( $root . '/includes/helpers/class-bot-admin-user-caption.php' );
		$this->assertStringContainsString( 'SimpleVPBot_Model_Plan::is_per_gb', $code );
		$this->assertStringContainsString( 'volume_gb', $code );
		$this->assertStringContainsString( 'گیگ', $code );
	}

	/**
	 * Referrer label prefers name, then @username, then #id.
	 */
	public function test_is_zero_toman_matches_dashboard_epsilon(): void {
		$this->assertTrue( SimpleVPBot_Bot_Persian_Text::is_zero_toman( 0 ) );
		$this->assertTrue( SimpleVPBot_Bot_Persian_Text::is_zero_toman( 0.008 ) );
		$this->assertFalse( SimpleVPBot_Bot_Persian_Text::is_zero_toman( 0.01 ) );
		$this->assertFalse( SimpleVPBot_Bot_Persian_Text::is_zero_toman( 100 ) );
	}

	public function test_referrer_display_label(): void {
		$user = (object) array(
			'first_name' => 'Ali',
			'last_name'  => 'Reza',
			'username'   => '',
			'id'         => 5,
		);
		$this->assertSame( 'Ali Reza', SimpleVPBot_Bot_Admin_User_Caption::referrer_display_label( $user ) );

		$user2 = (object) array(
			'first_name' => '',
			'last_name'  => '',
			'username'   => 'foo',
			'id'         => 7,
		);
		$this->assertSame( '@foo', SimpleVPBot_Bot_Admin_User_Caption::referrer_display_label( $user2 ) );

		$user3 = (object) array(
			'first_name' => '',
			'last_name'  => '',
			'username'   => '',
			'id'         => 12,
		);
		$this->assertSame( '#۱۲', SimpleVPBot_Bot_Admin_User_Caption::referrer_display_label( $user3 ) );
	}

	/**
	 * Handler_Start reuses invited_by_line for welcome message.
	 */
	public function test_handler_start_reuses_invited_by_line(): void {
		$root  = dirname( __DIR__ );
		$start = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-start.php' );
		$this->assertStringContainsString( 'SimpleVPBot_Bot_Admin_User_Caption::invited_by_line', $start );
	}
}
