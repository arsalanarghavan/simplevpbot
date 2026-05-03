<?php
/**
 * Smoke: repository layout and syntax-adjacent checks without WP bootstrap.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class ProjectLayoutTest extends TestCase {

	/**
	 * Core plugin files exist after checkout.
	 */
	public function test_core_files_exist(): void {
		$root = dirname( __DIR__ );
		$this->assertFileExists( $root . '/simplevpbot.php' );
		$this->assertFileExists( $root . '/includes/class-plugin.php' );
		$this->assertFileExists( $root . '/includes/helpers/class-backup-export.php' );
		$this->assertFileExists( $root . '/includes/helpers/class-backup-restore.php' );
	}

	/**
	 * Backup INSERT parser matches export style (regex lives in SimpleVPBot_Backup_Export).
	 */
	public function test_insert_table_parse_pattern(): void {
		$line = "INSERT INTO `wp_svp_users` (`id`) VALUES (1);";
		if ( ! preg_match( '/^\s*INSERT\s+INTO\s+`([^`]+)`/i', trim( $line ), $m ) ) {
			$this->fail( 'INSERT parse regex should match plugin export format.' );
		}
		$this->assertSame( 'wp_svp_users', $m[1] );
	}
}
