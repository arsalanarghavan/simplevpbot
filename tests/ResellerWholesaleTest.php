<?php
/**
 * Contract tests for wholesale line wiring and catalog helpers.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class ResellerWholesaleTest extends TestCase {

	/**
	 * Wholesale mutations must be wired in apply_inner.
	 */
	public function test_wholesale_mutations_wired(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( "case 'wholesale_line_save':", $code );
		$this->assertStringContainsString( "case 'wholesale_line_delete':", $code );
		$this->assertStringContainsString( "case 'reseller_wholesale_lines_assign':", $code );
	}

	/**
	 * REST bootstrap exposes wholesale catalog for admin UI.
	 */
	public function test_rest_wholesale_payload(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( 'wholesaleLinesCatalog', $code );
		$this->assertStringContainsString( 'wholesaleLines', $code );
		$this->assertStringContainsString( 'resellerWholesaleLineIdsMap', $code );
	}

	/**
	 * Unified floor helper includes catalog + parent floor.
	 */
	public function test_effective_wholesale_floor_helper(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-reseller-panel-price.php' );
		$this->assertStringContainsString( 'effective_wholesale_floor', $code );
		$this->assertStringContainsString( 'resolve_catalog_defaults', $code );
	}

	/**
	 * Plan save persists wholesale_line_id and calls apply_line_to_plan_row.
	 */
	public function test_wholesale_line_persist_on_plan_save(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/services/class-service-admin-catalog.php' );
		$this->assertStringContainsString( 'apply_line_to_plan_row', $code );
		$this->assertStringContainsString( "'wholesale_line_id'  => isset( \$post['wholesale_line_id'] )", $code );
		$this->assertStringNotContainsString( "\$row_data['wholesale_line_id'] = null;\n\t\t\$row_data['owner_svp_user_id'] = \$actor;\n\t\treturn array( 'row' => \$row_data );", $code );
	}

	/**
	 * REST floors use effective_wholesale_floor and wholesale panels merge.
	 */
	public function test_rest_floors_and_wholesale_panels(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( 'effective_wholesale_floor( $actor_uid, $pid )', $code );
		$this->assertStringContainsString( 'lines_for_reseller( $actor_uid )', $code );
	}

	/**
	 * Reseller bootstrap strips site uiLayout surfaces.
	 */
	public function test_reseller_ui_layout_stripped(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( '$reseller_mode || ! class_exists', $code );
		$this->assertStringContainsString( "'surfaces' => array()", $code );
	}

	/**
	 * Reseller bootstrap strips uiRegistry surfaces (same as uiLayout).
	 */
	public function test_reseller_ui_registry_stripped(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( '$ui_registry = ( $reseller_mode || ! class_exists( \'SimpleVPBot_UI_Action_Registry\' ) )', $code );
	}

	/**
	 * invited_by cycle rejected before write and in bind/referrer APIs.
	 */
	public function test_invited_by_cycle_guard(): void {
		$user = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-user.php' );
		$mut  = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$bf   = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-reseller-backfill.php' );
		$this->assertStringContainsString( 'invited_by_would_cycle', $user );
		$this->assertStringContainsString( 'referrer_cycle', $mut );
		$this->assertStringContainsString( 'referrer_cycle', $bf );
	}

	/**
	 * Plans admin shows wholesale ladder for reseller mode.
	 */
	public function test_plans_admin_wholesale_ladder(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-plans-admin.tsx' );
		$this->assertStringContainsString( 'wholesaleLadderTitle', $code );
	}

	/**
	 * Plan form sends optional wholesale_line_id for accrual wiring.
	 */
	public function test_plans_admin_wholesale_line_picker(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-plans-admin.tsx' );
		$this->assertStringContainsString( 'wholesale_line_id', $code );
		$this->assertStringContainsString( 'wholesaleLine', $code );
		$this->assertStringContainsString( 'wholesaleLinesForPanel', $code );
	}

	/**
	 * Backend auto-infers wholesale_line_id when absent from POST.
	 */
	public function test_wholesale_line_auto_infer(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/services/class-service-admin-catalog.php' );
		$this->assertStringContainsString( 'resolve_catalog_defaults', $code );
		$this->assertStringContainsString( 'lines_for_reseller', $code );
		$this->assertStringContainsString( '1 === count( $line_ids )', $code );
	}

	/**
	 * Backend requires wholesale_line_id when panel has assigned lines.
	 */
	public function test_wholesale_line_required_when_assigned(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/services/class-service-admin-catalog.php' );
		$this->assertStringContainsString( 'wholesale_line_required', $code );
	}

	/**
	 * Dashboard invoice checkout sets billing_reseller_svp_id.
	 */
	public function test_invoice_billing_reseller_meta(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-admin-user-ops.php' );
		$this->assertStringContainsString( "meta['billing_reseller_svp_id'] = $scope", $code );
	}

	/**
	 * Impersonation uses unrestricted site admin check for wholesale catalog.
	 */
	public function test_impersonation_wholesale_catalog_parity(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( 'dashboard_rest_is_unrestricted_site_admin() && class_exists( \'SimpleVPBot_Model_Reseller_Panel_Price\' )', $code );
	}

	/**
	 * Reseller plan floors include per-line tier-aware wholesale_line_id.
	 */
	public function test_reseller_plan_floors_line_aware(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( 'wholesale_floor_unit', $code );
		$this->assertStringContainsString( "'wholesale_line_id'", $code );
	}

	/**
	 * Plan form validates wholesale line when multiple lines on panel.
	 */
	public function test_plans_admin_wholesale_line_validation(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-plans-admin.tsx' );
		$this->assertStringContainsString( 'wholesaleLinesOnPanel > 1', $code );
		$this->assertStringContainsString( 'validationWholesaleLine', $code );
	}

	/**
	 * Admin-gift purchases skip wholesale accrual.
	 */
	public function test_admin_gift_skips_wholesale_accrual(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-service-reseller-wholesale-pricing.php' );
		$this->assertStringContainsString( "\$meta['admin_gift']", $code );
	}

	/**
	 * Renew transactions accrue wholesale usage for fixed plans (renew_same).
	 */
	public function test_renew_accrual_for_fixed_plans(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-service-reseller-wholesale-pricing.php' );
		$this->assertStringContainsString( "'renew' === $type", $code );
		$this->assertStringContainsString( "'renew_same'", $code );
	}

	/**
	 * Wholesale totals memoized per request to avoid bootstrap N+1.
	 */
	public function test_wholesale_totals_request_cache(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-reseller-wholesale-accrual.php' );
		$this->assertStringContainsString( '$totals_memo', $code );
		$this->assertStringContainsString( 'unset( self::$totals_memo', $code );
	}

	/**
	 * Plans UI maps wholesale_line_required and mutate errors i18n (Round 3).
	 */
	public function test_round3_ui_i18n_wiring(): void {
		$plans = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-plans-admin.tsx' );
		$mut   = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/lib/dash-admin-mutate.ts' );
		$loc   = (string) file_get_contents( dirname( __DIR__ ) . '/shared/locales/dashboard.ts' );
		$this->assertStringContainsString( 'errorCode_wholesale_line_required', $plans );
		$this->assertStringContainsString( 'wholesaleLadderPerGb', $plans );
		$this->assertStringContainsString( 'forbidden_op', $mut );
		$this->assertStringContainsString( 'mutateErrors', $loc );
		$this->assertStringContainsString( 'errorCode_wholesale_line_required', $loc );
	}

	/**
	 * User detail/search redact internal fields for reseller dashboard actors.
	 */
	public function test_user_detail_search_redaction(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( 'function route_admin_user_search', $code );
		$this->assertStringContainsString( 'function route_admin_user(', $code );
		$this->assertStringContainsString( 'sanitize_user_row_for_dashboard( $ra )', $code );
		$this->assertStringContainsString( 'reseller_reports', $code );
		$this->assertStringContainsString( "'reseller_reports',", $code );
	}

	/**
	 * Per-GB plan renew skips wholesale accrual (billing OK, ladder GB unchanged).
	 */
	public function test_per_gb_renew_skips_accrual(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-service-reseller-wholesale-pricing.php' );
		$this->assertStringContainsString( "'renew' === $type", $code );
		$this->assertStringContainsString( "'renew_same'", $code );
		$this->assertStringContainsString( 'SimpleVPBot_Model_Plan::is_per_gb( $plan )', $code );
		$this->assertStringContainsString( 'return;', $code );
		$plans = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-plans-admin.tsx' );
		$loc   = (string) file_get_contents( dirname( __DIR__ ) . '/shared/locales/dashboard.ts' );
		$this->assertStringContainsString( 'wholesaleLadderRenewNote', $plans );
		$this->assertStringContainsString( 'wholesaleLadderRenewNote', $loc );
	}

	/**
	 * Request-level memo avoids repeated lines_for_reseller queries in bootstrap.
	 */
	public function test_lines_for_reseller_request_memo(): void {
		$model = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-reseller-wholesale-line.php' );
		$this->assertStringContainsString( '$lines_for_reseller_memo', $model );
		$rest = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( 'reseller_dashboard_allowed_tabs_map( $actor_uid )', $rest );
		$this->assertStringContainsString( '$nav_tabs         = array_values', $rest );
	}

	/**
	 * Panel price list memo and batch panel lookup in bootstrap (Round 5).
	 */
	public function test_bootstrap_panel_batch_and_price_memo(): void {
		$pp = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-reseller-panel-price.php' );
		$panel = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-panel.php' );
		$rest = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( '$list_for_reseller_memo', $pp );
		$this->assertStringContainsString( 'find_by_ids', $panel );
		$this->assertStringContainsString( 'build_reseller_customer_charges', $rest );
		$this->assertStringContainsString( 'resellerCustomerChargesPagination', $rest );
		$this->assertStringContainsString( 'labels_by_ids', $rest );
	}

	/**
	 * Client tab fallback includes reseller_settings (Round 5).
	 */
	public function test_app_reseller_settings_fallback(): void {
		$app = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/App.tsx' );
		$this->assertStringContainsString( 'reseller_settings: null', $app );
	}

	/**
	 * Client tab fallback includes reseller_reports for permitted resellers.
	 */
	public function test_app_reseller_reports_fallback(): void {
		$app = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/App.tsx' );
		$this->assertStringContainsString( 'reseller_reports: "users.manage"', $app );
	}

	/**
	 * Reseller nav injects reseller_reports when tab is allowed (not admin-only hidden).
	 */
	public function test_reseller_reports_nav_injection_contract(): void {
		$nav = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/config/admin-nav.ts' );
		$this->assertDoesNotMatchRegularExpression(
			'/ADMIN_ONLY_TAB_KEYS[\s\S]*"reseller_reports"/',
			$nav
		);
		$this->assertStringContainsString( 'allowedTabs.has("reseller_reports")', $nav );
		$this->assertStringContainsString( 'tabKey: "reseller_reports"', $nav );
	}
}
