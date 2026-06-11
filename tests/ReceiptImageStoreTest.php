<?php
/**
 * Contract tests for permanent receipt image storage.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class ReceiptImageStoreTest extends TestCase {

	public function test_store_helper_exists(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-receipt-image-store.php' );
		$this->assertStringContainsString( 'function persist_from_temp', $code );
		$this->assertStringContainsString( 'function persist_from_bytes', $code );
		$this->assertStringContainsString( 'simplevpbot/receipts/', $code );
	}

	public function test_upload_persists_image(): void {
		$buy = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-buy.php' );
		$this->assertStringContainsString( 'SimpleVPBot_Receipt_Image_Store::persist_from_temp', $buy );
	}

	public function test_ajax_serves_local_first(): void {
		$ajax = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-ajax.php' );
		$this->assertStringContainsString( 'readable_path_for_receipt', $ajax );
		$this->assertStringContainsString( 'persist_from_bytes', $ajax );
	}

	public function test_migration_adds_stored_image_path(): void {
		$act = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-activator.php' );
		$this->assertStringContainsString( "DB_VERSION = '2.4.4'", $act );
		$this->assertStringContainsString( 'maybe_migrate_232_receipt_stored_image', $act );
		$this->assertStringContainsString( 'stored_image_path', $act );
	}
}
