<?php
/**
 * Contract tests for reseller data isolation and catalog sourcing.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class ResellerIsolationTest extends TestCase {

	/**
	 * Reseller bot catalog must not include site-global owner id 0.
	 */
	public function test_catalog_owner_ids_reseller_only(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-bot-reseller-scope.php' );
		$this->assertStringContainsString( 'return array( $rid );', $code );
		$this->assertStringNotContainsString( 'return array( 0, $rid );', $code );
	}

	/**
	 * Panel price rows can resolve wholesale defaults from catalog lines.
	 */
	public function test_panel_price_resolve_catalog_defaults(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-reseller-panel-price.php' );
		$this->assertStringContainsString( 'resolve_catalog_defaults', $code );
		$this->assertStringContainsString( 'site_wholesale_catalog_by_panel', $code );
	}

	/**
	 * Dashboard REST scopes owned catalog entities strictly for resellers.
	 */
	public function test_rest_owner_scoped_catalog_queries(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( '$owner_scoped_catalog', $code );
		$this->assertStringContainsString( 'owner_svp_user_id = %d', $code );
		$this->assertStringContainsString( 'wholesaleCatalogByPanel', $code );
		$this->assertStringContainsString( 'wholesaleLinesCatalog', $code );
		$this->assertStringContainsString( 'allowed_panel_ids_for', $code );
	}

	/**
	 * Plan userCount scoped to reseller downline when in reseller mode.
	 */
	public function test_plan_user_count_reseller_scope(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( 'user_id IN ({$scope_in})', $code );
	}

	/**
	 * Impersonation must not override owner on mutate or leak inbound catalog.
	 */
	public function test_impersonation_mutate_and_catalog_guards(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( 'dashboard_rest_is_unrestricted_site_admin', $code );
		$this->assertStringContainsString( "params['owner_svp_user_id'] = $owner_ctx", $code );
	}

	/**
	 * Resellers admin UI must not post manual wholesale prices for site admin.
	 */
	public function test_resellers_admin_access_only_save_payload(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-resellers-admin.tsx' );
		$this->assertStringContainsString( 'panel_access: true', $code );
		$this->assertStringContainsString( 'wholesaleCatalogByPanel', $code );
		$this->assertStringContainsString( 'panelPricesCatalogWholesale', $code );
		$this->assertStringContainsString( 'reseller_wholesale_lines_assign', $code );
		$this->assertStringContainsString( 'actorIsReseller', $code );
	}

	/**
	 * Site settings reseller permissions include marketing lifecycle.
	 */
	public function test_site_settings_reseller_permissions_complete(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/site-settings/site-settings-resellers-tab.tsx' );
		$this->assertStringContainsString( 'marketing.lifecycle', $code );
	}

	/**
	 * Reseller scope helper never returns empty for valid actor (Round 3).
	 */
	public function test_effective_reseller_scope_user_ids(): void {
		$scope = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-bot-reseller-scope.php' );
		$this->assertStringContainsString( 'function effective_downline_user_ids', $scope );
		$this->assertStringContainsString( 'return array( $rid );', $scope );
		$rest = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( 'effective_downline_user_ids', $rest );
	}

	/**
	 * Discount aggregates use owner_id derived from reseller context (Round 3).
	 */
	public function test_discount_owner_id_scoped(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( '$owner_id         = $reseller_mode ? $actor_uid', $code );
		$this->assertStringContainsString( 'global_summary( $owner_id )', $code );
	}

	/**
	 * Referral reports never fall back to site-global for reseller mode (Round 3).
	 */
	public function test_referral_reports_reseller_no_global_fallback(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( '} elseif ( $reseller_mode ) {', $code );
		$this->assertStringContainsString( '$top_rows        = array();', $code );
	}

	/**
	 * External monitor snapshots hidden from reseller monitoring (Round 3).
	 */
	public function test_external_snapshots_reseller_gated(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( '! $reseller_mode && class_exists( \'SimpleVPBot_Model_Monitor_Host\' )', $code );
	}

	/**
	 * Shared panel categories deny delete/toggle when foreign-owned plans exist (Round 4).
	 */
	public function test_plan_category_foreign_plans_guard(): void {
		$catalog = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/services/class-service-admin-catalog.php' );
		$this->assertStringContainsString( 'reseller_plan_category_blocked_by_foreign_plans', $catalog );
		$this->assertStringContainsString( "'code' => 'category_foreign_plans'", $catalog );
		$this->assertStringContainsString( 'owner_svp_user_id <> %d', $catalog );
		$ui = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-plan-cats-admin.tsx' );
		$this->assertStringContainsString( 'category_foreign_plans', $ui );
		$this->assertStringContainsString( 'adminMutateErrorText', $ui );
	}

	/**
	 * Category update blocks foreign-owned plans on shared panel slug (Round 5).
	 */
	public function test_plan_category_update_foreign_plans_guard(): void {
		$catalog = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/services/class-service-admin-catalog.php' );
		$this->assertStringContainsString( "'update' === \$action", $catalog );
		$this->assertStringContainsString( 'reseller_plan_category_blocked_by_foreign_plans', $catalog );
	}

	/**
	 * Receipt image AJAX checks dashboard actor scope (Round 5).
	 */
	public function test_receipt_image_actor_scope_guard(): void {
		$ajax = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-ajax.php' );
		$this->assertStringContainsString( 'receipt_image_allowed_for_actor', $ajax );
		$this->assertStringContainsString( 'dashboard_actor_context', $ajax );
		$this->assertMatchesRegularExpression(
			'/receipt_image_allowed_for_actor[\s\S]*reseller_may_moderate_user_for/',
			$ajax
		);
	}

	/**
	 * Portal admin scopes membership and service ops for reseller actors (Round 5).
	 */
	public function test_portal_admin_reseller_scope(): void {
		$ajax = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-ajax.php' );
		$this->assertStringContainsString( 'portal_admin_can_access_user', $ajax );
		$this->assertStringContainsString( 'portal_deny_reseller_global', $ajax );
		$this->assertStringContainsString( 'build_reseller_payload', $ajax );
	}

	/**
	 * Portal TG avatar, plan ownership, and referral read parity for reseller actors (Round 6).
	 */
	public function test_portal_security_parity_round6(): void {
		$ajax = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-ajax.php' );
		$this->assertStringContainsString( 'portal_reseller_may_use_plan', $ajax );
		$this->assertStringContainsString( "'reason' => 'forbidden_plan'", $ajax );
		$this->assertMatchesRegularExpression(
			"/portal_tg_avatar[\s\S]*portal_admin_can_access_user/",
			$ajax
		);
		$this->assertMatchesRegularExpression(
			"/'referral_get' === \$op[\s\S]*portal_deny_reseller_global/",
			$ajax
		);
	}

	/**
	 * Bootstrap perf: panel price index + single actor fetch for floors/annotate (Round 6).
	 */
	public function test_bootstrap_panel_price_memo_index(): void {
		$model = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-reseller-panel-price.php' );
		$this->assertStringContainsString( 'index_rows_by_panel', $model );
		$this->assertStringContainsString( 'actor_user_row', $model );
		$rest = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( 'reseller_panel_price_index', $rest );
		$this->assertStringContainsString( 'reseller_wholesale_lines', $rest );
		$this->assertStringContainsString( 'annotate_reseller_panels_for_dashboard( $panels, $actor_uid, $reseller_panel_price_index )', $rest );
	}

	/**
	 * Round 7: shared moderation scope + portal stats + transfer target guard.
	 */
	public function test_reseller_moderation_scope_round7(): void {
		$scope = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-bot-reseller-scope.php' );
		$this->assertStringContainsString( 'function reseller_may_moderate_user_for', $scope );
		$this->assertStringContainsString( 'signup_reseller_svp_id', $scope );
		$this->assertStringContainsString( 'bot_admin_may_moderate_user', $scope );
		$this->assertStringContainsString( 'reseller_may_moderate_user_for', $scope );

		$ajax = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-ajax.php' );
		$this->assertStringContainsString( 'effective_moderatable_user_ids', $ajax );
		$this->assertStringContainsString( 'format_reseller_text', $ajax );
		$this->assertStringContainsString( 'allowed_panel_ids_for', $ajax );
		$this->assertStringContainsString( 'reseller_may_moderate_user_for', $ajax );
		$this->assertMatchesRegularExpression(
			"/'renew_service' === \$op[\s\S]*'not_found'/",
			$ajax
		);

		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( 'op_user_service_transfer', $mut );
		$this->assertStringContainsString( 'reseller_may_moderate_user_for', $mut );

		$rest = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( 'user_service_transfer', $rest );
		$this->assertStringContainsString( 'transfer_target', $rest );
	}

	/**
	 * Round 7: bootstrap tab-gating + overview metrics + scoped reports chart.
	 */
	public function test_reseller_perf_and_overview_round7(): void {
		$rest = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( '$needs_reseller_customer_charges', $rest );
		$this->assertStringContainsString( '$needs_reseller_plan_floors', $rest );
		$this->assertStringContainsString( '$needs_reseller_wholesale_ladders', $rest );
		$this->assertStringContainsString( 'resellerOverviewMetrics', $rest );
		$this->assertStringContainsString( 'build_actor_summary', $rest );

		$reports = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-admin-reseller-reports.php' );
		$this->assertStringContainsString( 'build_actor_summary', $reports );
		$this->assertStringContainsString( 'overview_metrics_days_from_request', $reports );
		$this->assertStringContainsString( 'build_daily_series_for_resellers', $reports );
		$this->assertStringContainsString( 'daily_scoped', $reports );

		$charge = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-reseller-charge-admin.tsx' );
		$this->assertStringContainsString( 'onCustomerChargesPerPageChange', $charge );

		$overview = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-overview.tsx' );
		$this->assertStringContainsString( 'resellerOverviewMetrics', $overview );
		$this->assertStringContainsString( 'onOverviewMetricsWindowChange', $overview );

		$reports_ui = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-reseller-reports-admin.tsx' );
		$this->assertStringContainsString( 'chartSubtitleFiltered', $reports_ui );
		$this->assertStringContainsString( 'daily_scoped', $reports_ui );
	}

	/**
	 * Round 8: moderation parity, portal transfer, perf batch maps, statsDay.
	 */
	public function test_reseller_audit_round8(): void {
		$scope = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-bot-reseller-scope.php' );
		$this->assertStringContainsString( 'effective_moderatable_user_ids', $scope );
		$this->assertStringContainsString( 'plan_visible_for_reseller', $scope );

		$overview = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-overview.tsx' );
		$this->assertStringContainsString( 'resellerFocused', $overview );
		$this->assertStringContainsString( 'onStatsDayChange', $overview );
		$this->assertStringContainsString( 'perfReceipts', $overview );

		$resellers_ui = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-resellers-admin.tsx' );
		$this->assertStringContainsString( 'resellerBotMap', $resellers_ui );
		$this->assertStringContainsString( 'colBot', $resellers_ui );
	}

	/**
	 * Round 9: unified moderation scope, portal UI, perf, preview fetch.
	 */
	public function test_reseller_audit_round9(): void {
		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( 'reseller_moderation_scope_clause', $mut );
		$this->assertStringContainsString( 'actor_may_moderate_user', $mut );
		$this->assertStringContainsString( 'effective_moderatable_user_ids', $mut );

		$mkt = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-marketing-lifecycle-analytics.php' );
		$this->assertStringContainsString( 'reseller_moderation_scope_clause', $mkt );

		$rest = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( '$needs_resellers_preview', $rest );
		$this->assertStringContainsString( '$needs_child_reseller_maps', $rest );
		$this->assertStringContainsString( 'reseller_xui_panels', $rest );
		$this->assertStringContainsString( 'customerChargesType', $rest );
		$this->assertStringContainsString( 'by_line_ids', $rest );

		$portal = (string) file_get_contents( dirname( __DIR__ ) . '/includes/frontend/class-portal-admin.php' );
		$this->assertStringContainsString( 'service_transfer', $portal );
		$this->assertStringContainsString( 'svp-rcpt-root', $portal );

		$portal_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/portal.js' );
		$this->assertStringContainsString( 'receipts_page', $portal_js );
		$this->assertStringContainsString( 'rcptFetch', $portal_js );

		$reports = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-admin-reseller-reports.php' );
		$this->assertStringContainsString( 'aggregate_maps( $since, array( $actor_uid ) )', $reports );
		$this->assertStringContainsString( '$aggregate_maps_cache', $reports );

		$reports_ui = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-reseller-reports-admin.tsx' );
		$this->assertStringContainsString( 'openBackup', $reports_ui );
	}

	/**
	 * Round 10: owner_ctx moderatable, parent resellers, impersonation reads, reports scope, charge dates.
	 */
	public function test_reseller_audit_round10(): void {
		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( "'reseller' === \$role && ! self::mutate_is_unrestricted_site_admin()", $mut );
		$this->assertStringContainsString( 'actor_may_moderate_user( $post, $restricted )', $mut );
		$this->assertStringContainsString( 'actor_may_moderate_user( $p, $uid )', $mut );

		$rest = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( 'dashboard_actor_may_read_user', $rest );
		$this->assertStringContainsString( '$resellers_direct_children_only', $rest );
		$this->assertStringContainsString( '$owner_ctx > 0 ? $owner_ctx : 0', $rest );
		$this->assertStringContainsString( 'customerChargesDateFrom', $rest );
		$this->assertStringContainsString( 'resolve_catalog_defaults_map', $rest );
		$this->assertStringContainsString( 'begin_floor_batch', $rest );

		$ajax = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-ajax.php' );
		$this->assertStringContainsString( 'impersonationTargetId', $ajax );

		$reports = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-admin-reseller-reports.php' );
		$this->assertStringContainsString( 'build_summary_from_maps', $reports );
		$this->assertStringContainsString( 'aggregate_maps( $since, $match_ids )', $reports );

		$portal_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/portal.js' );
		$this->assertStringContainsString( 'applyPortalI18n', $portal_js );
		$this->assertStringContainsString( 'receipts_page', $portal_js );

		$resellers_ui = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-resellers-admin.tsx' );
		$this->assertStringContainsString( 'canViewResellerControls', $resellers_ui );
		$this->assertStringContainsString( 'canManagePanelPriceForReseller', $resellers_ui );
		$this->assertStringContainsString( 'reseller_wp_provision', $resellers_ui );
	}

	/**
	 * Round 11: discount guard fix, owner_ctx scope, service gates, resellers_q, batch floors, reports rank.
	 */
	public function test_reseller_audit_round11(): void {
		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( 'actor_may_moderate_user( $post, $restricted )', $mut );
		$this->assertStringContainsString( 'gate_service_moderation_for_op', $mut );
		$this->assertStringContainsString( 'require_panel_client_moderation_for_actor', $mut );
		$this->assertStringContainsString( 'forbidden_plan', $mut );
		$this->assertStringContainsString( 'effective_moderatable_user_ids( $actor )', $mut );

		$rest = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( 'users_moderatable_scope_clause', $rest );
		$this->assertStringContainsString( 'resellers_q', $rest );
		$this->assertStringContainsString( 'effective_moderatable_user_ids( $owner_ctx', $rest );
		$this->assertStringContainsString( 'map_for_parent_children', $rest );
		$this->assertStringContainsString( 'dashboard_actor_may_read_user( $ctx', $rest );
		$this->assertStringContainsString( 'service_ids', $rest );

		$parent_floor = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-reseller-parent-panel-floor.php' );
		$this->assertStringContainsString( 'function map_for_parent_children', $parent_floor );

		$reports = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-admin-reseller-reports.php' );
		$this->assertStringContainsString( 'rank_reseller_ids_by_metric', $reports );

		$app = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/App.tsx' );
		$this->assertStringContainsString( 'resellers_q', $app );

		$resellers_ui = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-resellers-admin.tsx' );
		$this->assertStringContainsString( 'permissionsReadOnlyHint', $resellers_ui );
		$this->assertStringContainsString( 'openUserDetail', $resellers_ui );

		$app_ui = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/App.tsx' );
		$this->assertStringContainsString( 'customerChargesDateTo', $app_ui );

		$reports_ui = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-reseller-reports-admin.tsx' );
		$this->assertStringContainsString( 'searchDraft', $reports_ui );
	}

	/**
	 * Round 12: bulk panel allowlist, configs batch guards, owner_ctx validation, billing column, resellers_q isolation.
	 */
	public function test_reseller_audit_round12(): void {
		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( 'users_bulk_append_panel_allowlist_sql', $mut );
		$this->assertStringContainsString( 'require_configs_batch_items_moderation', $mut );
		$this->assertStringContainsString( 'configs_panel_client_patch', $mut );
		$this->assertStringContainsString( 'configs_clients_batch', $mut );
		$this->assertStringContainsString( 'configs_assign_plan', $mut );
		$this->assertMatchesRegularExpression(
			'/function users_bulk_service_ids_for_user[\s\S]*users_bulk_append_panel_allowlist_sql/',
			$mut
		);

		$rest = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( 'validate_reseller_context_id', $rest );
		$this->assertStringContainsString( 'invalid_reseller_context', $rest );
		$this->assertStringContainsString( 'is_reseller_row', $rest );
		$this->assertStringContainsString( 'billing_reseller_id_sql_expr', $rest );
		$this->assertStringNotContainsString( "'' === \$resellers_q ) {\n\t\t\t\$resellers_q = \$users_q;", $rest );

		$tx = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-transaction.php' );
		$this->assertStringContainsString( 'billing_reseller_svp_id', $tx );
		$this->assertStringContainsString( 'billing_reseller_id_sql_expr', $tx );

		$act = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-activator.php' );
		$this->assertStringContainsString( "DB_VERSION = '2.4.4'", $act );
		$this->assertStringContainsString( 'maybe_migrate_243_billing_reseller_svp_id', $act );

		$user = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-user.php' );
		$this->assertMatchesRegularExpression(
			'/function reseller_permissions_map_for_ids[\s\S]*\$wpdb->options/',
			$user
		);

		$reports = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-admin-reseller-reports.php' );
		$this->assertStringContainsString( 'billing_reseller_id_sql_expr', $reports );

		$app = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/App.tsx' );
		$this->assertStringContainsString( 'resellersSearchQuery={listQuery.resellers_q ?? ""}', $app );
		$this->assertStringNotContainsString( 'resellers_q ?? listQuery.users_q', $app );

		$resellers_ui = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-resellers-admin.tsx' );
		$this->assertStringNotContainsString( 'default_service_type', $resellers_ui );
		$this->assertStringNotContainsString( 'default_inbound_id', $resellers_ui );
	}

	/**
	 * Round 13: configs owner guard, S9 admin checks, perf cleanup, reseller read-only UI.
	 */
	public function test_reseller_audit_round13(): void {
		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( 'client_owner_unresolved', $mut );
		$this->assertStringContainsString( 'require_owner_resolved', $mut );
		$this->assertMatchesRegularExpression(
			'/function op_user_merge_preview[\s\S]*mutate_is_unrestricted_site_admin/',
			$mut
		);
		$this->assertStringNotContainsString( "current_user_can( 'manage_options' )", $mut );

		$rest = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( 'needs_resellers_list_data', $rest );
		$this->assertStringContainsString( 'attach_reseller_direct_user_counts', $rest );
		$this->assertStringContainsString( 'direct_children_count_map_for_ids', $rest );
		$this->assertStringContainsString( 'labels_by_ids', $rest );

		$tx = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-transaction.php' );
		$this->assertStringContainsString( 'return "{$alias}.billing_reseller_svp_id";', $tx );
		$this->assertStringNotContainsString( 'COALESCE({$alias}.billing_reseller_svp_id', $tx );

		$reports = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-admin-reseller-reports.php' );
		$this->assertStringContainsString( 'wp_cache_set', $reports );
		$this->assertStringContainsString( 'wp_cache_get', $reports );

		$cron = (string) file_get_contents( dirname( __DIR__ ) . '/includes/cron/class-cron-users-bulk.php' );
		$this->assertStringContainsString( 'forbidden_scope', $cron );

		$discounts_ui = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-discounts-admin.tsx' );
		$this->assertStringContainsString( 'readOnlySettings', $discounts_ui );
		$this->assertStringContainsString( 'readOnlyResellerHint', $discounts_ui );

		$mkt_ui = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-marketing-lifecycle-admin.tsx' );
		$this->assertStringContainsString( 'canMutate', $mkt_ui );
		$this->assertStringContainsString( 'readOnlySettings', $mkt_ui );

		$admin_view = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-admin-view.tsx' );
		$this->assertStringContainsString( 'readOnlySettings={isReseller}', $admin_view );
	}

	/**
	 * Round 14: shared-panel workspace guard, portal discount parity, reports paginate-before-aggregate, scope hints.
	 */
	public function test_reseller_audit_round14(): void {
		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( 'workspace_context_required', $mut );
		$this->assertStringContainsString( 'panel_is_multi_reseller_shared', $mut );
		$this->assertStringContainsString( 'require_configs_batch_workspace_context', $mut );
		$this->assertStringContainsString( 'audit_configs_batch_items', $mut );
		$this->assertStringContainsString( 'configs_clients_batch', $mut );
		$this->assertStringContainsString( 'discount_save_from_post', $mut );

		$disc = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-discount-code.php' );
		$this->assertStringContainsString( 'all_ordered_for_owner', $disc );

		$ajax = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-ajax.php' );
		$this->assertStringContainsString( "'discount_save' === \$op", $ajax );
		$this->assertStringContainsString( 'all_ordered_for_owner', $ajax );

		$portal = (string) file_get_contents( dirname( __DIR__ ) . '/includes/frontend/class-portal-admin.php' );
		$this->assertStringContainsString( 'data-svp-i18n="discountTitle"', $portal );
		$this->assertStringContainsString( 'discount_save', $portal );

		$portal_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/portal.js' );
		$this->assertStringContainsString( 'discountTitle', $portal_js );
		$this->assertStringContainsString( 'discount_save', $portal_js );

		$reports = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-admin-reseller-reports.php' );
		$this->assertStringContainsString( 'rank_reseller_ids_by_metric_sql', $reports );
		$this->assertStringContainsString( '$summary_maps = self::aggregate_maps', $reports );
		$this->assertStringContainsString( '$page_maps = self::aggregate_maps', $reports );

		$broadcast_ui = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-broadcast-admin.tsx' );
		$this->assertStringContainsString( 'scopedResellerHint', $broadcast_ui );
		$this->assertStringContainsString( 'isReseller', $broadcast_ui );

		$monitor_ui = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-monitoring.tsx' );
		$this->assertStringContainsString( 'scopedResellerHint', $monitor_ui );
		$this->assertStringContainsString( 'isReseller', $monitor_ui );

		$admin_view = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-admin-view.tsx' );
		$this->assertStringContainsString( 'isReseller={isReseller}', $admin_view );
	}

	/**
	 * Round 15: single configs guards, legacy ajax scope, dashboard read-only mutate policy, reports summary SQL.
	 */
	public function test_reseller_audit_round15(): void {
		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( 'require_configs_single_client_moderation_for_actor', $mut );
		$this->assertMatchesRegularExpression(
			'/function require_configs_single_client_moderation_for_actor[\s\S]*require_panel_client_moderation_for_actor\( \$p, true \)/',
			$mut
		);

		$ajax = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-ajax.php' );
		$this->assertStringContainsString( 'legacy_ajax_may_moderate_user', $ajax );
		$this->assertStringContainsString( 'legacy_ajax_require_pure_site_admin', $ajax );
		$this->assertStringContainsString( 'legacy_admin_reseller_forbidden', $ajax );

		$policy = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-mutate-policy.php' );
		$this->assertStringNotContainsString( "'discount_save'", $policy );
		$this->assertStringNotContainsString( "'discount_delete'", $policy );
		$this->assertStringNotContainsString( 'marketing_rule_save', $policy );

		$reports = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-admin-reseller-reports.php' );
		$this->assertStringContainsString( 'build_summary_sql', $reports );
		$this->assertStringContainsString( 'fetch_scoped_daily_maps', $reports );

		$resellers_ui = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-resellers-admin.tsx' );
		$this->assertStringContainsString( 'canManageResellerControls ?', $resellers_ui );

		$referral_ui = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-referral-admin.tsx' );
		$this->assertStringContainsString( 'settingsReadOnlyHint', $referral_ui );
	}

	/**
	 * Round 16: legacy inbound scope, portal discount field parity, dashboard portal link.
	 */
	public function test_reseller_audit_round16(): void {
		$ajax = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-ajax.php' );
		$this->assertStringContainsString( 'legacy_ajax_may_access_panel', $ajax );
		$this->assertStringContainsString( 'legacy_ajax_filter_inbound_clients', $ajax );
		$this->assertMatchesRegularExpression(
			'/function inbound_clients[\s\S]*legacy_ajax_may_access_panel/',
			$ajax
		);
		$this->assertMatchesRegularExpression(
			'/function inbound_clients[\s\S]*legacy_ajax_filter_inbound_clients/',
			$ajax
		);
		$this->assertMatchesRegularExpression(
			'/function inbound_autolink[\s\S]*legacy_ajax_may_access_panel/',
			$ajax
		);
		$this->assertMatchesRegularExpression(
			'/function inbound_autolink[\s\S]*legacy_admin_reseller_forbidden/',
			$ajax
		);
		$this->assertStringContainsString( 'svpc_valid_from', $ajax );
		$this->assertStringContainsString( 'svpc_allowed_plan_ids', $ajax );
		$this->assertStringContainsString( 'svpc_allow_new', $ajax );

		$rest = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( 'portalAdminUrl', $rest );
		$this->assertStringContainsString( 'SimpleVPBot_Portal_Link::build_admin_url', $rest );

		$portal = (string) file_get_contents( dirname( __DIR__ ) . '/includes/frontend/class-portal-admin.php' );
		$this->assertStringContainsString( 'svp-disc-from', $portal );
		$this->assertStringContainsString( 'svp-disc-plans', $portal );
		$this->assertStringContainsString( 'percent_per_gb', $portal );

		$portal_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/portal.js' );
		$this->assertStringContainsString( 'discount_valid_from', $portal_js );
		$this->assertStringContainsString( 'discount_allow_new', $portal_js );
		$this->assertStringContainsString( 'discountPlanIds', $portal_js );

		$discounts_ui = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-discounts-admin.tsx' );
		$this->assertStringContainsString( 'portalAdminUrl', $discounts_ui );
		$this->assertStringContainsString( 'portalManageLink', $discounts_ui );

		$admin_view = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-admin-view.tsx' );
		$this->assertStringContainsString( 'portalAdminUrl={', $admin_view );
	}

	/**
	 * Round 17: parent sub-reseller create, downline reports tab, integration harness.
	 */
	public function test_reseller_audit_round17(): void {
		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( 'parent_actor', $mut );
		$this->assertStringContainsString( 'parent_reseller_id', $mut );
		$this->assertMatchesRegularExpression(
			'/function op_reseller_wp_provision[\s\S]*forbidden_scope/',
			$mut
		);

		$policy = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-mutate-policy.php' );
		$this->assertStringContainsString( "'reseller_wp_provision'", $policy );

		$reports = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-admin-reseller-reports.php' );
		$this->assertStringContainsString( 'downline_reseller_ids_for', $reports );
		$this->assertStringContainsString( 'empty_scoped_reports_payload', $reports );
		$this->assertStringContainsString( '$scope_ancestor_id', $reports );

		$rest = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertMatchesRegularExpression(
			'/\'unit_economics\',\s*\n\t\t\);/',
			$rest
		);
		$this->assertMatchesRegularExpression(
			"/'reseller_reports' === \\\$active_tab[\s\S]*scope_ancestor/",
			$rest
		);

		$resellers_ui = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-resellers-admin.tsx' );
		$this->assertStringContainsString( 'canCreateSubReseller', $resellers_ui );
		$this->assertStringContainsString( 'canWpProvisionForReseller', $resellers_ui );
		$this->assertStringContainsString( 'subResellerCreateHint', $resellers_ui );

		$reports_ui = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/components/dashboard-reseller-reports-admin.tsx' );
		$this->assertStringContainsString( 'readOnlyAdminActions', $reports_ui );
		$this->assertStringContainsString( 'downlineReportsHint', $reports_ui );

		$this->assertFileExists( dirname( __DIR__ ) . '/.wp-env.json' );
		$this->assertFileExists( dirname( __DIR__ ) . '/tests/bootstrap-integration.php' );
		$this->assertFileExists( dirname( __DIR__ ) . '/tests/fixtures/class-reseller-tree-fixture.php' );
		$this->assertFileExists( dirname( __DIR__ ) . '/tests/integration/ResellerDownlineIntegrationTest.php' );
	}
}
