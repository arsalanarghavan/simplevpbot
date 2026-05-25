<?php
/**
 * Contract tests for paid renew semantics shared with autorenew.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class ServiceRenewSemanticsTest extends TestCase {

	/**
	 * Autorenew must delegate to apply_after_payment.
	 */
	public function test_autorenew_calls_apply_after_payment(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/cron/class-cron-autorenew.php' );
		$this->assertStringContainsString( 'apply_after_payment', $code );
		$this->assertStringNotContainsString( 'push_panel_renewal', $code );
		$this->assertStringContainsString( 'decrement_balance_if_sufficient', $code );
		$this->assertStringContainsString( 'increment_balance', $code );
	}

	/**
	 * Renew must not update DB when panel traffic reset fails.
	 */
	public function test_renew_checks_reset_client_traffic(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-service-renew.php' );
		$this->assertStringContainsString( 'reset_client_traffic', $code );
		$this->assertStringContainsString( 'resetClientTraffic failed', $code );
		$this->assertMatchesRegularExpression(
			'/reset_client_traffic[\\s\\S]*response_is_success[\\s\\S]*Model_Service::update/s',
			$code
		);
	}

	/**
	 * Paid renew extends +30 days only when near expiry or exhausted.
	 */
	public function test_renew_conditional_extend(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-service-renew.php' );
		$this->assertStringContainsString( '$days_left < 5', $code );
		$this->assertStringContainsString( '$exhausted', $code );
		$this->assertStringContainsString( '30 * DAY_IN_SECONDS', $code );
	}
}
