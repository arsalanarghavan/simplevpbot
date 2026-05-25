<?php
/**
 * Contract tests for reseller closure table scope (DB 2.3.0).
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class ResellerClosureScopeTest extends TestCase {

	/**
	 * Closure helper exists with rebuild and scope query helpers.
	 */
	public function test_closure_helper_contract(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-reseller-closure.php' );
		$this->assertStringContainsString( 'class SimpleVPBot_Reseller_Closure', $code );
		$this->assertStringContainsString( 'rebuild_all', $code );
		$this->assertStringContainsString( 'rebuild_for_user', $code );
		$this->assertStringContainsString( 'on_invited_by_changed', $code );
		$this->assertStringContainsString( 'descendant_ids_for_ancestor', $code );
		$this->assertStringContainsString( 'is_descendant_of', $code );
		$this->assertStringContainsString( 'reseller_scope_clause', $code );
		$this->assertStringContainsString( 'LARGE_IN_THRESHOLD', $code );
	}

	/**
	 * Model_User uses closure for scope instead of only BFS.
	 */
	public function test_model_user_wires_closure(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-user.php' );
		$this->assertStringContainsString( 'SimpleVPBot_Reseller_Closure::descendant_ids_for_ancestor', $code );
		$this->assertStringContainsString( 'SimpleVPBot_Reseller_Closure::is_descendant_of', $code );
		$this->assertStringContainsString( 'reseller_scope_clause', $code );
		$this->assertStringContainsString( 'rebuild_for_user', $code );
	}

	/**
	 * Migration 2.3.0 creates closure + audit tables and backfill option.
	 */
	public function test_migration_230_contract(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-activator.php' );
		$this->assertStringContainsString( "const DB_VERSION = '2.3.0'", $code );
		$this->assertStringContainsString( 'svp_reseller_closure', $code );
		$this->assertStringContainsString( 'svp_audit_log', $code );
		$this->assertStringContainsString( 'maybe_migrate_230_branding_closure_audit', $code );
		$this->assertStringContainsString( 'simplevpbot_closure_backfill_v1_done', $code );
	}

	/**
	 * Branding resolver and dashboard boot inject branding.
	 */
	public function test_branding_and_audit_contract(): void {
		$brand = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-branding-resolver.php' );
		$this->assertStringContainsString( 'resolve_for_request', $brand );
		$this->assertStringContainsString( 'resolve_for_dashboard_actor', $brand );
		$this->assertStringContainsString( 'to_css_variables', $brand );

		$front = (string) file_get_contents( dirname( __DIR__ ) . '/includes/frontend/class-dashboard-front.php' );
		$this->assertStringContainsString( 'apply_branding_to_boot', $front );
		$this->assertStringContainsString( 'maybe_redirect_custom_domain', $front );

		$rest = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( '/dashboard/admin/audit', $rest );
		$this->assertStringContainsString( 'route_admin_audit', $rest );
	}
}
