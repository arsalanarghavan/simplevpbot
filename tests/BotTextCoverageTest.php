<?php
/**
 * CI guard: bot send_message strings must use svp_texts catalog keys.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class BotTextCoverageTest extends TestCase {

	public function test_bot_text_coverage_validator_exits_zero(): void {
		$script = dirname( __DIR__ ) . '/scripts/validate-bot-text-coverage.php';
		$this->assertFileExists( $script );

		$cmd = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $script ) . ' 2>&1';
		exec( $cmd, $output, $exit_code );

		$this->assertSame(
			0,
			$exit_code,
			"validate-bot-text-coverage.php failed:\n" . implode( "\n", $output )
		);
	}

	public function test_model_text_dashboard_merge_contract(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-text.php' );
		$this->assertStringContainsString( 'all_grouped_for_dashboard', $code );
		$this->assertStringContainsString( 'catalog_only', $code );
	}

	public function test_rest_dashboard_uses_merged_texts(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( 'all_grouped_for_dashboard()', $code );
	}
}
