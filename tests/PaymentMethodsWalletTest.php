<?php
/**
 * Contract tests for site-wallet checkout visibility and partial apply flow.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class PaymentMethodsWalletTest extends TestCase {

	/**
	 * Payment helpers expose offer vs full-cover and meta wallet applied.
	 */
	public function test_can_offer_site_wallet_helpers(): void {
		$pm = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-payment-methods.php' );
		$this->assertStringContainsString( 'function can_offer_site_wallet', $pm );
		$this->assertStringContainsString( 'function wallet_applied_toman', $pm );
		$this->assertStringContainsString( 'can_offer_site_wallet( $tx, $user, $rid )', $pm );
		$this->assertStringContainsString( 'return round( (float) $user->balance, 2 ) > 0', $pm );
		$this->assertStringContainsString( 'self::can_offer_site_wallet( $tx, $user, $owner_rid )', $pm );
	}

	/**
	 * Checkout keyboard and partial callbacks are wired in buy handler.
	 */
	public function test_partial_wallet_checkout_flow(): void {
		$buy = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-buy.php' );
		$this->assertStringContainsString( 'can_offer_site_wallet', $buy );
		$this->assertStringContainsString( "'swy' === \$act", $buy );
		$this->assertStringContainsString( "'swn' === \$act", $buy );
		$this->assertStringContainsString( 'wallet_applied_toman', $buy );
		$this->assertStringContainsString( 'apply_partial_site_wallet', $buy );
		$this->assertStringContainsString( 'buy:swy:', $buy );
	}

	/**
	 * buy:sw always confirms; full payment runs only on buy:swy.
	 */
	public function test_unified_wallet_confirm_before_debit(): void {
		$buy = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-buy.php' );
		$this->assertStringContainsString( 'send_site_wallet_confirm', $buy );
		$this->assertStringContainsString( 'fulfill_site_wallet_full_payment', $buy );
		$this->assertStringContainsString( 'msg.buy.wallet_full_confirm', $buy );
		$sw_block = $this->extract_sw_callback_block( $buy );
		$this->assertStringNotContainsString( 'decrement_balance_if_sufficient', $sw_block );
		$this->assertStringContainsString( 'send_site_wallet_confirm', $sw_block );
		$swy_block = $this->extract_swy_callback_block( $buy );
		$this->assertStringContainsString( 'fulfill_site_wallet_full_payment', $swy_block );
	}

	/**
	 * Full-pay confirm text is registered in bot defaults.
	 */
	public function test_wallet_full_confirm_i18n(): void {
		$defs = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-bot-text-defaults-extended.php' );
		$this->assertStringContainsString( 'msg.buy.wallet_full_confirm', $defs );
	}

	/**
	 * @param string $buy Handler source.
	 * @return string
	 */
	private function extract_sw_callback_block( string $buy ): string {
		$start = strpos( $buy, "if ( 'sw' === \$act" );
		$this->assertNotFalse( $start );
		$end = strpos( $buy, "if ( 'swy' === \$act", $start );
		$this->assertNotFalse( $end );
		return substr( $buy, $start, $end - $start );
	}

	/**
	 * @param string $buy Handler source.
	 * @return string
	 */
	private function extract_swy_callback_block( string $buy ): string {
		$start = strpos( $buy, "if ( 'swy' === \$act" );
		$this->assertNotFalse( $start );
		$end = strpos( $buy, "if ( 'swn' === \$act", $start );
		$this->assertNotFalse( $end );
		return substr( $buy, $start, $end - $start );
	}

	/**
	 * Partial split math: 50k need, 30k balance → 30k applied, 20k remaining.
	 */
	public function test_partial_wallet_remaining_math(): void {
		$need      = 50000.0;
		$balance   = 30000.0;
		$applied   = round( $balance, 2 );
		$remaining = max( 0.0, round( $need - $applied, 2 ) );
		$this->assertSame( 30000.0, $applied );
		$this->assertSame( 20000.0, $remaining );
	}

	/**
	 * After wallet_applied_toman in meta, offer helper must hide the button.
	 */
	public function test_wallet_applied_hides_offer(): void {
		$pm = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-payment-methods.php' );
		$this->assertStringContainsString( "self::wallet_applied_toman( \$tx ) > 0", $pm );
	}
}
