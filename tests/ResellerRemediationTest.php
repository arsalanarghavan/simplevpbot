<?php
/**
 * Contract tests for reseller audit remediation (phases 1–3).
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class ResellerRemediationTest extends TestCase {

	/**
	 * T-1: explicit reseller notify must not fall back to main bot token.
	 */
	public function test_send_message_for_reseller_no_global_fallback(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/class-bot-runtime.php' );
		$this->assertMatchesRegularExpression(
			'/function send_message_for_reseller[\s\S]*?return null;/',
			$code
		);
		$this->assertDoesNotMatchRegularExpression(
			'/function send_message_for_reseller[\s\S]*?self::send_message\(/',
			$code
		);
	}

	/**
	 * N-5: site-wide receipt reject presets are not reseller-mutable via REST policy.
	 */
	public function test_receipt_reject_reasons_not_in_reseller_policy(): void {
		$policy = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-mutate-policy.php' );
		$this->assertStringNotContainsString( "'receipt_reject_reasons_save'", $policy );
	}

	/**
	 * N-8: signed portal mirrors reseller_permissions before ops.
	 */
	public function test_portal_reseller_permission_gate(): void {
		$ajax = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-ajax.php' );
		$this->assertStringContainsString( 'portal_reseller_required_permission', $ajax );
		$this->assertStringContainsString( 'portal_reseller_may_call_op', $ajax );
		$this->assertStringContainsString( 'forbidden_perm', $ajax );
	}

	/**
	 * F-2: daily reseller report chart uses billing attribution filter.
	 */
	public function test_daily_series_uses_billing_has_filter(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-admin-reseller-reports.php' );
		$this->assertMatchesRegularExpression(
			'/function build_daily_series_for_resellers[\s\S]*AND \{\$billing_has\}/',
			$code
		);
	}

	/**
	 * N-1: reseller_xui_panels admin tab hidden from reseller SPA router.
	 */
	public function test_reseller_xui_panels_admin_only_in_spa(): void {
		$view = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-admin-view.tsx' );
		$this->assertStringContainsString( 'activeTab === "reseller_xui_panels" && !isReseller', $view );
	}

	/**
	 * N-9/N-10: legacy AJAX panel scope on probe/link handlers.
	 */
	public function test_legacy_ajax_panel_scope_guards(): void {
		$ajax = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-ajax.php' );
		$this->assertMatchesRegularExpression(
			'/function test_panel[\s\S]*legacy_ajax_may_access_panel/',
			$ajax
		);
		$this->assertMatchesRegularExpression(
			'/function inbounds_list[\s\S]*legacy_ajax_may_access_panel/',
			$ajax
		);
		$this->assertMatchesRegularExpression(
			'/function inbound_link[\s\S]*legacy_ajax_may_access_panel/',
			$ajax
		);
		$this->assertMatchesRegularExpression(
			'/function test_telegram[\s\S]*legacy_ajax_require_pure_site_admin/',
			$ajax
		);
		$this->assertMatchesRegularExpression(
			'/function l2tp_test[\s\S]*legacy_ajax_require_pure_site_admin/',
			$ajax
		);
	}

	/**
	 * W-2: reseller webhook rejects non-approved reseller accounts.
	 */
	public function test_reseller_webhook_requires_approved_status(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/class-webhook.php' );
		$this->assertStringContainsString( "reseller_not_approved", $code );
		$this->assertStringContainsString( "'approved' !== (string) ( \$row->status ?? '' )", $code );
	}

	/**
	 * F-3: billing inference prefers signup_reseller_svp_id before branding walk.
	 */
	public function test_billing_inference_signup_reseller_priority(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-reseller-backfill.php' );
		$this->assertMatchesRegularExpression(
			'/function infer_billing_reseller_for_tx[\s\S]*signup_reseller_svp_id[\s\S]*nearest_reseller_id_for_user/',
			$code
		);
	}

	/**
	 * B-1/S-5: backup manifest lists wordpress sidecar files and secret warning.
	 */
	public function test_backup_manifest_lists_wordpress_files(): void {
		$export = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-backup-export.php' );
		$this->assertStringContainsString( 'wordpress_files', $export );
		$this->assertStringContainsString( 'reseller-permissions.json', $export );
		$this->assertStringContainsString( 'plugin_settings_secrets_redacted', $export );
		$this->assertStringContainsString( "'plugin_settings_contains_secrets' => false", $export );
		$restore = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-backup-restore.php' );
		$this->assertStringContainsString( 'reseller_permissions_skipped', $restore );
	}

	/**
	 * N-6: discount redemption rows filtered by actor moderation scope.
	 */
	public function test_discount_redemptions_scope_filter(): void {
		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertMatchesRegularExpression(
			'/function op_discount_redemptions[\s\S]*actor_may_moderate_user/',
			$mut
		);
	}

	/**
	 * bot_delete_webhook removed from reseller policy (use reseller_bot_webhook_delete).
	 */
	public function test_bot_delete_webhook_not_in_reseller_policy(): void {
		$policy = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-mutate-policy.php' );
		$this->assertStringNotContainsString( "'bot_delete_webhook'", $policy );
		$this->assertStringContainsString( "'reseller_bot_webhook_delete'", $policy );
	}
}
