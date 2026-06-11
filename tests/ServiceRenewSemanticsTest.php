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
		$this->assertStringNotContainsString( '۳۰ روز', $code );
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
	 * Paid renew always extends by plan duration_days (not hardcoded 30).
	 */
	public function test_renew_uses_plan_duration_days(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-service-renew.php' );
		$this->assertStringContainsString( '$plan_days', $code );
		$this->assertStringContainsString( 'duration_days', $code );
		$this->assertStringNotContainsString( '30 * DAY_IN_SECONDS', $code );
		$this->assertStringContainsString( '$plan_days * DAY_IN_SECONDS', $code );
		$this->assertStringContainsString( '$days_left <= (float) self::EXPIRY_ACTION_THRESHOLD_DAYS', $code );
		$this->assertStringContainsString( '$exhausted', $code );
	}

	/**
	 * Five-day window helpers and threshold constant.
	 */
	public function test_expiry_window_helpers(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-service-renew.php' );
		$this->assertStringContainsString( 'EXPIRY_ACTION_THRESHOLD_DAYS = 5', $code );
		$this->assertStringContainsString( 'function days_until_expiry_floor', $code );
		$this->assertStringContainsString( 'function user_may_add_volume', $code );
		$this->assertStringContainsString( 'function user_may_renew_same', $code );
		$this->assertStringContainsString( '$days > self::EXPIRY_ACTION_THRESHOLD_DAYS', $code );
		$this->assertStringContainsString( '$days <= self::EXPIRY_ACTION_THRESHOLD_DAYS', $code );
		$this->assertStringContainsString( 'purchase_meta_bypasses_expiry_window', $code );
	}

	/**
	 * Bot handler gates renew/add_volume before checkout.
	 */
	public function test_handler_expiry_window_gates(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-service.php' );
		$this->assertStringContainsString( 'user_may_renew_same', $code );
		$this->assertStringContainsString( 'user_may_add_volume', $code );
		$this->assertStringContainsString( 'reject_renew_message', $code );
		$this->assertStringContainsString( 'reject_add_volume_message', $code );
		$this->assertStringContainsString( 'is_platform_admin_managing_other_users_service', $code );
	}

	/**
	 * Fulfillment gates user purchases; admin meta bypasses window.
	 */
	public function test_fulfillment_expiry_window_gates(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-receipt-processor.php' );
		$this->assertStringContainsString( 'user_purchase_expiry_window_rejection', $code );
		$this->assertStringContainsString( 'purchase_meta_bypasses_expiry_window', $code );
		$this->assertStringContainsString( 'renew_window', $code );
		$this->assertStringContainsString( 'add_volume_window', $code );
	}
}
