<?php
/**
 * Smoke: SimpleVPBot_Support_Contacts helper.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/helpers/class-support-contacts.php';

/**
 * @coversNothing
 */
class SupportContactsTest extends TestCase {

	/**
	 * Username normalization strips @ prefix.
	 */
	public function test_normalize_username(): void {
		$this->assertSame( 'admin', SimpleVPBot_Support_Contacts::normalize_username( '@admin' ) );
		$this->assertSame( 'help', SimpleVPBot_Support_Contacts::normalize_username( ' help ' ) );
	}

	/**
	 * Contact block includes configured lines when settings mock available.
	 */
	public function test_contact_block_empty_without_settings(): void {
		if ( ! class_exists( 'SimpleVPBot_Settings' ) ) {
			$this->markTestSkipped( 'WordPress bootstrap not loaded' );
		}
		$this->assertIsString( SimpleVPBot_Support_Contacts::contact_block( 'telegram' ) );
	}
}
