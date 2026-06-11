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
	 * Dashboard mutate handlers share patch_reseller_bot_profile_tokens helper.
	 */
	public function test_bot_reseller_save_uses_shared_token_helper(): void {
		$root = dirname( __DIR__ );
		$mut  = (string) file_get_contents( $root . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( 'patch_reseller_bot_profile_tokens', $mut );
		$this->assertGreaterThanOrEqual( 2, substr_count( $mut, 'patch_reseller_bot_profile_tokens' ) );
	}

	/**
	 * Legacy plaintext bot tokens are migrated to encrypted storage on upgrade.
	 */
	public function test_plaintext_token_migration_contract(): void {
		$root      = dirname( __DIR__ );
		$model     = (string) file_get_contents( $root . '/includes/models/class-model-reseller-bot-profile.php' );
		$activator = (string) file_get_contents( $root . '/includes/class-activator.php' );
		$this->assertStringContainsString( 'migrate_plaintext_tokens_to_encrypted', $model );
		$this->assertStringContainsString( 'maybe_migrate_244_reseller_bot_token_encryption', $activator );
		$this->assertStringContainsString( "'2.4.4'", $activator );
		$this->assertStringContainsString( 'migrate_plaintext_webhook_secrets_to_encrypted', $model );
		$this->assertStringContainsString( 'maybe_migrate_245_reseller_webhook_secret_encryption', $activator );
		$this->assertStringContainsString( "'2.4.5'", $activator );
	}
}
