<?php
/**
 * Bot UI layout button style / premium emoji tests.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class UiLayoutButtonDecorTest extends TestCase {

	/**
	 * Load layout + runtime classes without WordPress.
	 */
	protected function setUp(): void {
		parent::setUp();
		if ( ! defined( 'ABSPATH' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
			define( 'ABSPATH', '/tmp/' );
		}
		$root = dirname( __DIR__ );
		if ( ! class_exists( 'SimpleVPBot_UI_Layout' ) ) {
			require_once $root . '/includes/bot/class-ui-layout.php';
		}
		if ( ! class_exists( 'SimpleVPBot_Bot_Runtime' ) ) {
			require_once $root . '/includes/bot/class-bot-runtime.php';
		}
	}

	public function test_normalize_cell_style(): void {
		$this->assertSame( 'primary', SimpleVPBot_UI_Layout::normalize_cell_style( 'primary' ) );
		$this->assertSame( 'success', SimpleVPBot_UI_Layout::normalize_cell_style( 'SUCCESS' ) );
		$this->assertSame( '', SimpleVPBot_UI_Layout::normalize_cell_style( 'blue' ) );
		$this->assertSame( '', SimpleVPBot_UI_Layout::normalize_cell_style( '' ) );
	}

	public function test_normalize_cell_icon_custom_emoji_id(): void {
		$this->assertSame( '12345', SimpleVPBot_UI_Layout::normalize_cell_icon_custom_emoji_id( '12345' ) );
		$this->assertSame( '', SimpleVPBot_UI_Layout::normalize_cell_icon_custom_emoji_id( 'abc' ) );
		$this->assertSame( '', SimpleVPBot_UI_Layout::normalize_cell_icon_custom_emoji_id( '12 34' ) );
	}

	public function test_decorate_button(): void {
		$btn = SimpleVPBot_UI_Layout::decorate_button(
			array( 'text' => 'Buy' ),
			array(
				'style'                 => 'danger',
				'icon_custom_emoji_id' => '999',
			)
		);
		$this->assertSame( 'Buy', $btn['text'] );
		$this->assertSame( 'danger', $btn['style'] );
		$this->assertSame( '999', $btn['icon_custom_emoji_id'] );
	}

	public function test_scrub_bale_reply_markup(): void {
		$in = array(
			'keyboard' => array(
				array(
					array(
						'text'                  => 'OK',
						'style'                 => 'primary',
						'icon_custom_emoji_id' => '1',
					),
				),
			),
			'inline_keyboard' => array(
				array(
					array(
						'text'          => 'Go',
						'callback_data' => 'x',
						'style'         => 'success',
					),
				),
			),
		);
		$out = SimpleVPBot_Bot_Runtime::scrub_bale_reply_markup( $in );
		$this->assertArrayNotHasKey( 'style', $out['keyboard'][0][0] );
		$this->assertArrayNotHasKey( 'icon_custom_emoji_id', $out['keyboard'][0][0] );
		$this->assertSame( 'OK', $out['keyboard'][0][0]['text'] );
		$this->assertArrayNotHasKey( 'style', $out['inline_keyboard'][0][0] );
		$this->assertSame( 'x', $out['inline_keyboard'][0][0]['callback_data'] );
	}

	public function test_wiring_in_keyboards_and_validate(): void {
		$root = dirname( __DIR__ );
		$layout = (string) file_get_contents( $root . '/includes/bot/class-ui-layout.php' );
		$this->assertStringContainsString( 'decorate_button', $layout );
		$this->assertStringContainsString( 'bad_style:', $layout );
		$kb = (string) file_get_contents( $root . '/includes/bot/class-keyboards.php' );
		$this->assertStringContainsString( 'SimpleVPBot_UI_Layout::decorate_button', $kb );
		$rt = (string) file_get_contents( $root . '/includes/bot/class-bot-runtime.php' );
		$this->assertStringContainsString( 'prepare_api_extra', $rt );
		$this->assertStringContainsString( 'scrub_bale_reply_markup', $rt );
	}
}
