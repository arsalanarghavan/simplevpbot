<?php
/**
 * Telegram combined config HTML send contracts.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class TelegramConfigSendTest extends TestCase {

	/**
	 * Handler must send one combined HTML message for all config URIs.
	 */
	public function test_combined_config_send_contract(): void {
		$src = (string) file_get_contents(
			dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-service.php'
		);

		$this->assertStringContainsString( 'build_combined_config_message_html', $src );
		$this->assertStringContainsString( 'build_single_config_message_html', $src );
		$this->assertStringContainsString( 'telegram_send_config_unified', $src );
		$this->assertStringContainsString( 'resolve_user_dashboard_url', $src );
		$this->assertStringContainsString( 'send_document_file', $src );
		$this->assertStringContainsString( "'parse_mode' => 'HTML'", $src );
		$this->assertStringContainsString( '<code>', $src );
		$this->assertStringNotContainsString( 'build_telegram_config_caption_html', $src );
		$this->assertStringNotContainsString( 'بقیه را با دکمهٔ کانفیگ بگیرید.', $src );
		$this->assertStringNotContainsString( 'wp_strip_all_tags( $caption )', $src );
	}

	/**
	 * Single-config HTML must escape URI and close code tags (no mid-URI truncation).
	 */
	public function test_single_config_html_shape(): void {
		$uri = 'vless://uuid@host:80?encryption=none&host=example.com&path=%2F';
		$esc = htmlspecialchars( $uri, ENT_QUOTES, 'UTF-8' );
		$html = '🧾 <b>کانفیگ 1</b>' . "\n" . '<code>' . $esc . '</code>';

		$this->assertStringContainsString( '&amp;host=example.com', $html );
		$this->assertStringEndsWith( '</code>', $html );
		$this->assertGreaterThan( strlen( $uri ), strlen( $html ) );
	}

	/**
	 * Config wire callback uses HTML on Telegram only.
	 */
	public function test_config_wire_telegram_html_contract(): void {
		$src = (string) file_get_contents(
			dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-service.php'
		);

		$this->assertStringContainsString( "if ( 'telegram' === \$platform )", $src );
		$this->assertStringContainsString(
			'self::build_single_config_message_html( $line, $frag, $idx, count( $uris ) )',
			$src
		);
	}
}
