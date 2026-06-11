<?php
/**
 * Staging checklist contract tests (reseller IDOR / guards without live WP HTTP).
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class ResellerStagingContractTest extends TestCase {

	/**
	 * Cross-reseller user read guard exists on REST.
	 */
	public function test_cross_reseller_user_read_guard(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( 'dashboard_actor_may_read_user', $code );
		$this->assertStringContainsString( 'reseller_may_moderate_user_for', $code );
	}

	/**
	 * Mutate pipeline blocks out-of-scope service owners.
	 */
	public function test_mutate_service_scope_guard(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertMatchesRegularExpression(
			'/function route_admin_mutate[\s\S]*forbidden_scope/',
			$code
		);
	}

	/**
	 * Wholesale line save is site-admin only (impersonation cannot assign lines).
	 */
	public function test_wholesale_line_save_admin_only(): void {
		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertMatchesRegularExpression(
			'/function op_wholesale_line_save[\s\S]*mutate_is_unrestricted_site_admin/',
			$mut
		);
	}

	/**
	 * Portal discount_save checks reseller permissions.
	 */
	public function test_portal_discount_permission_gate(): void {
		$ajax = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-ajax.php' );
		$this->assertMatchesRegularExpression(
			'/function portal_admin[\s\S]*portal_reseller_may_call_op/',
			$ajax
		);
		$this->assertStringContainsString( 'discount_save', $ajax );
	}

	/**
	 * Suspended reseller webhook rejected.
	 */
	public function test_reseller_webhook_suspended_rejected(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/class-webhook.php' );
		$this->assertStringContainsString( 'reseller_not_approved', $code );
	}

	/**
	 * Admin portal uses shorter TTL than customer portal.
	 */
	public function test_admin_portal_shorter_ttl(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-portal-link.php' );
		$this->assertStringContainsString( 'ADMIN_TTL', $code );
		$this->assertMatchesRegularExpression(
			'/build_admin_url[\s\S]*ADMIN_TTL/',
			$code
		);
	}

	/**
	 * Reseller webhook supports optional header secret (W-1).
	 */
	public function test_reseller_webhook_header_secret(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/class-webhook.php' );
		$this->assertStringContainsString( 'HTTP_X_SVP_WEBHOOK_SECRET', $code );
		$this->assertStringContainsString( 'reseller_webhook_secret_candidate', $code );
	}

	/**
	 * Per-reseller webhook rate limit bucket (R-1).
	 */
	public function test_reseller_webhook_rate_limit_bucket(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/class-webhook.php' );
		$this->assertStringContainsString( 'rate_limit_ok_for_reseller', $code );
	}

	/**
	 * Webhook secrets encrypted at rest (E-3).
	 */
	public function test_webhook_secret_encryption_helpers(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-reseller-bot-profile.php' );
		$this->assertStringContainsString( 'webhook_secret_plaintext', $code );
		$this->assertStringContainsString( 'migrate_plaintext_webhook_secrets_to_encrypted', $code );
	}

	/**
	 * Marketing rule sheet save gated for resellers (N-4).
	 */
	public function test_marketing_rule_sheet_save_gated(): void {
		$view = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-marketing-lifecycle-admin.tsx' );
		$this->assertMatchesRegularExpression(
			'/canMutate\s*\?[\s\S]*saveRule/',
			$view
		);
	}
}
