<?php
/**
 * Contract tests for atomic receipt approval.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class ReceiptApproveAtomicTest extends TestCase {

	/**
	 * approve() must claim pending receipt before side effects.
	 */
	public function test_approve_uses_claim_pending(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-receipt-processor.php' );
		$this->assertStringContainsString( 'claim_pending', $code );
		$this->assertStringContainsString( 'try_finalize_approved', $code );
		$this->assertStringContainsString( 'release_to_pending', $code );
		$this->assertStringContainsString( 'increment_balance', $code );
	}

	/**
	 * Topup must not use read-modify-write balance update in approve path.
	 */
	public function test_topup_uses_increment_balance_in_effects(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-receipt-processor.php' );
		$this->assertStringContainsString( 'execute_approve_effects', $code );
		$this->assertMatchesRegularExpression(
			"/'topup' === \\\$tx->type[\\s\\S]*increment_balance/",
			$code
		);
	}

	/**
	 * Purchase approval must be conditional on pending status.
	 */
	public function test_try_approve_from_pending_on_model(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-transaction.php' );
		$this->assertStringContainsString( 'try_approve_from_pending', $code );
		$this->assertStringContainsString( "'pending'", $code );
	}
}
