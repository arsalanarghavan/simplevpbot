<?php
/**
 * Contract tests for dashboard mutate forbidden responses.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class ResellerMutatePolicyTest extends TestCase {

	/**
	 * REST mutate route returns standardized forbidden codes.
	 */
	public function test_rest_mutate_forbidden_codes(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( "'message' => 'forbidden_op'", $code );
		$this->assertStringContainsString( "'message' => 'forbidden_perm'", $code );
		$this->assertStringContainsString( "'message' => 'forbidden_scope'", $code );
	}

	/**
	 * Dashboard admin mutations use forbidden_scope for out-of-scope writes.
	 */
	public function test_admin_mutations_forbidden_scope(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( "'message' => 'forbidden_scope'", $code );
	}

	/**
	 * SPA maps forbidden codes to mutateErrors i18n keys.
	 */
	public function test_ui_mutate_error_mapping(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/lib/dash-admin-mutate.ts' );
		$this->assertStringContainsString( 'forbidden_op: "mutateErrors.forbiddenOp"', $code );
		$this->assertStringContainsString( 'forbidden_perm: "mutateErrors.forbiddenPerm"', $code );
		$this->assertStringContainsString( 'forbidden_scope: "mutateErrors.forbiddenScope"', $code );
	}
}
