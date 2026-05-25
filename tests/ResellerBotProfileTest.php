<?php
/**
 * Contract tests for reseller bot profile (token upsert).
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class ResellerBotProfileTest extends TestCase {

	/**
	 * upsert_tokens must exist (used by bot_reseller_save).
	 */
	public function test_upsert_tokens_method_exists(): void {
		$root = dirname( __DIR__ );
		$code = (string) file_get_contents( $root . '/includes/models/class-model-reseller-bot-profile.php' );
		$this->assertStringContainsString( 'public static function upsert_tokens', $code );
		$this->assertStringNotContainsString( "Upsert tokens (empty string clears). Optionally updates brand_name.\n\t\tglobal \$wpdb;", $code );
	}

	/**
	 * Dashboard mutate handler calls upsert_tokens.
	 */
	public function test_bot_reseller_save_calls_upsert_tokens(): void {
		$root = dirname( __DIR__ );
		$mut  = (string) file_get_contents( $root . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( 'SimpleVPBot_Model_Reseller_Bot_Profile::upsert_tokens', $mut );
	}
}
