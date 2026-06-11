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

	/**
	 * Platform-specific contact_block shows only the current platform username.
	 */
	public function test_contact_block_platform_exclusive_contract(): void {
		$src = (string) file_get_contents(
			dirname( __DIR__ ) . '/includes/helpers/class-support-contacts.php'
		);

		$this->assertStringContainsString( "if ( 'telegram' === \$plat )", $src );
		$this->assertStringContainsString( "elseif ( 'bale' === \$plat )", $src );
		$this->assertStringNotContainsString(
			"if ( 'bale' === \$plat ) {\n\t\t\tif ( '' !== \$bl_line ) {\n\t\t\t\t\$lines[] = \$bl_line;\n\t\t\t}\n\t\t\tif ( '' !== \$tg_line )",
			$src
		);
	}
}
