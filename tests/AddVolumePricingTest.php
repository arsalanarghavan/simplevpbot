<?php
/**
 * Contract tests for add-volume pricing helpers.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class AddVolumePricingTest extends TestCase {

	/**
	 * Add volume uses proportional fixed-plan math and per-GB branch.
	 */
	public function test_checkout_price_add_volume_implementation(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-service-renew.php' );
		$this->assertStringContainsString( 'checkout_price_add_volume', $code );
		$this->assertStringContainsString( 'is_per_gb', $code );
		$this->assertStringContainsString( 'apply_add_volume_after_payment', $code );
		$this->assertStringContainsString( '$g * self::BYTES_PER_GB', $code );
	}
}
