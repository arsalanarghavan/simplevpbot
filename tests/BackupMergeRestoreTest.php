<?php
/**
 * Contract tests for merge backup restore (no TRUNCATE, tg/bale/wp user match).
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class BackupMergeRestoreTest extends TestCase {

	/**
	 * Restore entrypoint must not truncate tables.
	 */
	public function test_restore_has_no_truncate(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-backup-restore.php' );
		$this->assertStringNotContainsString( 'TRUNCATE TABLE', $code );
		$this->assertStringContainsString( 'SimpleVPBot_Backup_Merge_Restore', $code );
	}

	/**
	 * Merge restore matches users only by platform ids.
	 */
	public function test_merge_restore_user_match_contract(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-backup-merge-restore.php' );
		$this->assertStringContainsString( 'find_by_telegram', $code );
		$this->assertStringContainsString( 'find_by_bale', $code );
		$this->assertStringContainsString( 'find_by_wp_user', $code );
		$this->assertStringNotContainsString( 'find_by_phone', $code );
		$this->assertStringNotContainsString( 'username', $code );
		$this->assertStringContainsString( 'ambiguous_identity', $code );
	}

	/**
	 * SQL parser for INSERT lines exists.
	 */
	public function test_parse_insert_export_contract(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-backup-export.php' );
		$this->assertStringContainsString( 'parse_insert_line', $code );
		$this->assertStringContainsString( 'parse_sql_dump', $code );
	}

	/**
	 * Parse sample export INSERT.
	 */
	public function test_parse_insert_line_values(): void {
		if ( ! class_exists( 'SimpleVPBot_Backup_Export' ) ) {
			$this->markTestSkipped( 'Plugin not loaded' );
		}
		$line = "INSERT INTO `wp_svp_users` (`id`,`tg_user_id`,`bale_user_id`,`first_name`) VALUES (99,123,NULL,'Ali');";
		$parsed = SimpleVPBot_Backup_Export::parse_insert_line( $line );
		$this->assertIsArray( $parsed );
		$this->assertSame( 'wp_svp_users', $parsed['table'] );
		$this->assertSame( array( 'id', 'tg_user_id', 'bale_user_id', 'first_name' ), $parsed['columns'] );
		$this->assertSame( 99, $parsed['values'][0] );
		$this->assertSame( 123, $parsed['values'][1] );
		$this->assertNull( $parsed['values'][2] );
		$this->assertSame( 'Ali', $parsed['values'][3] );
	}
}
