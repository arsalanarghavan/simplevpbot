<?php
/**
 * Contract tests for strict intent service resolution.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class IntentServiceResolveTest extends TestCase {

	/**
	 * Receipt renew/add_volume must resolve with strict=true.
	 */
	public function test_receipt_resolve_uses_strict_mode(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-receipt-processor.php' );
		$this->assertStringContainsString( "resolve_intent_service_for_transaction( \$tx, \$meta, true )", $code );
	}

	/**
	 * Fallback single-service only when not strict.
	 */
	public function test_fallback_gated_by_strict_flag(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-receipt-processor.php' );
		$this->assertStringContainsString( 'if ( ! $strict )', $code );
		$this->assertStringContainsString( 'single_eligible_intent_service_for_user', $code );
	}
}
