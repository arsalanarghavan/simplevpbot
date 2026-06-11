<?php
/**
 * Unit tests for deferred background work helper.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class DeferredWorkTest extends TestCase {

	/**
	 * Helper class file exists and exposes queue API.
	 */
	public function test_class_and_api_present(): void {
		$root = dirname( __DIR__ );
		$this->assertFileExists( $root . '/includes/helpers/class-deferred-work.php' );
		$src = (string) file_get_contents( $root . '/includes/helpers/class-deferred-work.php' );
		$this->assertStringContainsString( 'run_after_response', $src );
		$this->assertStringContainsString( 'run_after_response_or_cron', $src );
		$this->assertStringContainsString( 'run_shutdown_queue', $src );
		$this->assertStringContainsString( 'RECEIPT_APPROVE_CRON_HOOK', $src );
	}

	/**
	 * Plugin bootstrap wires Deferred_Work.
	 */
	public function test_plugin_init_registers_deferred_work(): void {
		$plugin = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-plugin.php' );
		$this->assertStringContainsString( 'SimpleVPBot_Deferred_Work', $plugin );
		$this->assertStringContainsString( 'class-deferred-work.php', $plugin );
		$this->assertStringContainsString( 'Deferred_Work::init', $plugin );
	}

	/**
	 * Receipt processor exposes approve_continue for async path.
	 */
	public function test_receipt_approve_continue(): void {
		$rp = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-receipt-processor.php' );
		$this->assertStringContainsString( 'approve_continue', $rp );
		$this->assertStringContainsString( 'approve_continue_cron', $rp );
	}
}
