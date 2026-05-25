<?php
/**
 * Contract tests for panel transfer DB failure compensation.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class PanelTransferCompensateTest extends TestCase {

	/**
	 * After panel steps, DB update is verified; target client removed on failure.
	 */
	public function test_transfer_db_failed_compensates_target(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-service-panel-transfer.php' );
		$this->assertStringContainsString( 'transfer_db_failed', $code );
		$this->assertStringContainsString( 'delete_target_client', $code );
		$this->assertMatchesRegularExpression(
			'/Model_Service::update[\\s\\S]*verify = SimpleVPBot_Model_Service::find/s',
			$code
		);
	}
}
