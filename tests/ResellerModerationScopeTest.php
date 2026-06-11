<?php
/**
 * Behavioral-style tests for reseller moderation scope helpers.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class ResellerModerationScopeTest extends TestCase {

	/**
	 * Moderation scope helpers exist and delegate signup_reseller attribution.
	 */
	public function test_moderation_scope_helpers_wired(): void {
		$scope = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-bot-reseller-scope.php' );
		$this->assertStringContainsString( 'function effective_moderatable_user_ids', $scope );
		$this->assertStringContainsString( 'signup_reseller_svp_id', $scope );
		$this->assertStringContainsString( 'function plan_visible_for_reseller', $scope );

		$user = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-user.php' );
		$this->assertStringContainsString( 'function reseller_moderation_scope_clause', $user );
		$this->assertStringContainsString( 'function reseller_permissions_map_for_ids', $user );
	}

	/**
	 * Receipt and user read paths use moderation helper (Round 8).
	 */
	public function test_moderation_adopted_in_receipt_and_user_routes(): void {
		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( 'reseller_may_moderate_user_for', $mut );
		$this->assertStringContainsString( 'plan_visible_for_reseller', $mut );

		$ajax = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-ajax.php' );
		$this->assertStringContainsString( 'service_transfer', $ajax );
		$this->assertStringContainsString( 'receipts_page', $ajax );
		$this->assertStringContainsString( 'plan_visible_for_reseller', $ajax );

		$rest = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( 'effective_moderatable_user_ids', $rest );
		$this->assertStringContainsString( 'reseller_moderation_scope_clause', $rest );
		$this->assertStringContainsString( 'daily_scoped', $rest );
		$this->assertStringContainsString( 'statsDay', $rest );
		$this->assertStringContainsString( 'charge_type', $rest );
	}

	/**
	 * Batch reseller maps replace per-row N+1 (Round 8 perf).
	 */
	public function test_batch_reseller_maps(): void {
		$panel = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-reseller-panel-price.php' );
		$this->assertStringContainsString( 'rows_map_for_resellers', $panel );

		$bot = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-reseller-bot-profile.php' );
		$this->assertStringContainsString( 'summary_map_for_resellers', $bot );

		$assign = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-reseller-wholesale-assignment.php' );
		$this->assertStringContainsString( 'line_ids_map_for_resellers', $assign );

		$rest = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( '$needs_resellers_tab_data', $rest );
		$this->assertStringContainsString( '$needs_panel_health', $rest );
		$this->assertStringContainsString( 'rows_map_for_resellers', $rest );
	}

	/**
	 * Bot admin list scope uses moderatable ids.
	 */
	public function test_bot_admin_scope_uses_moderatable_ids(): void {
		$scope = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-bot-reseller-scope.php' );
		$this->assertMatchesRegularExpression(
			'/function bot_admin_scope_user_ids[\s\S]*effective_moderatable_user_ids/',
			$scope
		);
	}

	/**
	 * Round 9: bulk, marketing, stats, and search use moderation scope.
	 */
	public function test_round9_moderation_scope_consumers(): void {
		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( 'reseller_moderation_scope_clause', $mut );

		$mkt = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-marketing-lifecycle-analytics.php' );
		$this->assertStringContainsString( 'reseller_moderation_scope_clause', $mkt );

		$rest = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertMatchesRegularExpression(
			'/route_admin_user_search[\s\S]*effective_moderatable_user_ids/',
			$rest
		);
		$this->assertMatchesRegularExpression(
			'/build_reseller_payload[\s\S]*\$moderatable_user_ids/',
			$rest
		);

		$ajax = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-ajax.php' );
		$this->assertStringContainsString( 'effective_moderatable_user_ids', $ajax );

		$scope = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-bot-reseller-scope.php' );
		$this->assertMatchesRegularExpression(
			'/function bot_admin_may_access_user[\s\S]*bot_admin_may_moderate_user/',
			$scope
		);
	}

	/**
	 * Behavioral matrix: moderation scope helper (WP-free).
	 */
	public function test_reseller_may_moderate_user_for_matrix(): void {
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			$this->markTestSkipped( 'SimpleVPBot_Bot_Reseller_Scope not loaded' );
		}
		$this->assertFalse( SimpleVPBot_Bot_Reseller_Scope::reseller_may_moderate_user_for( 0, 1 ) );
		$this->assertFalse( SimpleVPBot_Bot_Reseller_Scope::reseller_may_moderate_user_for( 1, 0 ) );
	}

	/**
	 * Round 12: dashboard_actor_may_read_user matrix (site admin vs scoped reseller ctx shape).
	 */
	public function test_dashboard_actor_may_read_user_matrix(): void {
		if ( ! class_exists( 'SimpleVPBot_Rest_Dashboard' ) ) {
			$this->markTestSkipped( 'SimpleVPBot_Rest_Dashboard not loaded' );
		}
		$this->assertFalse( SimpleVPBot_Rest_Dashboard::dashboard_actor_may_read_user( array(), 0 ) );
		$this->assertFalse( SimpleVPBot_Rest_Dashboard::dashboard_actor_may_read_user( array(), -1 ) );
		$this->assertTrue(
			SimpleVPBot_Rest_Dashboard::dashboard_actor_may_read_user(
				array(
					'isReseller'             => false,
					'actorUserId'            => 1,
					'impersonationTargetId'  => 0,
				),
				99
			)
		);
		$this->assertFalse(
			SimpleVPBot_Rest_Dashboard::dashboard_actor_may_read_user(
				array(
					'isReseller'    => true,
					'actorUserId'   => 42,
				),
				99
			)
		);
		$this->assertFalse(
			SimpleVPBot_Rest_Dashboard::dashboard_actor_may_read_user(
				array(
					'isReseller'            => false,
					'actorUserId'           => 1,
					'impersonationTargetId' => 42,
				),
				99
			)
		);
	}

	/**
	 * Round 12: charge date filter regex (matches REST bootstrap validation).
	 */
	public function test_reseller_charge_date_filter_regex(): void {
		$valid = static function ( $s ) {
			return is_string( $s ) && '' !== $s && (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s );
		};
		$this->assertTrue( $valid( '2026-06-06' ) );
		$this->assertTrue( $valid( '2025-01-31' ) );
		$this->assertFalse( $valid( '' ) );
		$this->assertFalse( $valid( '06-06-2026' ) );
		$this->assertFalse( $valid( '2026/06/06' ) );
		$this->assertFalse( $valid( '2026-6-6' ) );
	}

	/**
	 * Round 13: configs batch requires resolvable panel client owner.
	 */
	public function test_configs_batch_owner_guard_contract(): void {
		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertMatchesRegularExpression(
			'/function require_configs_batch_items_moderation[\s\S]*require_panel_client_moderation_for_actor\( \$check, true \)/',
			$mut
		);
		$this->assertStringContainsString( 'client_owner_unresolved', $mut );
	}

	/**
	 * Round 14: configs batch on shared panels requires workspace context for unrestricted admin.
	 */
	public function test_configs_batch_shared_panel_workspace_contract(): void {
		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertMatchesRegularExpression(
			'/function require_configs_batch_items_moderation[\s\S]*require_configs_batch_workspace_context/',
			$mut
		);
		$this->assertStringContainsString( 'workspace_context_required', $mut );
	}

	/**
	 * Round 14: portal discount list uses owner-scoped SQL helper.
	 */
	public function test_portal_discount_owner_scope_contract(): void {
		$ajax = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-ajax.php' );
		$this->assertStringContainsString( 'all_ordered_for_owner', $ajax );
		$this->assertStringContainsString( 'discount_save_from_post', $ajax );
	}

	/**
	 * Round 15: single configs ops use owner resolve + shared-panel workspace guard.
	 */
	public function test_configs_single_ops_guard_contract(): void {
		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( 'require_configs_single_client_moderation_for_actor', $mut );
		$this->assertMatchesRegularExpression(
			'/configs_client_toggle_enable[\s\S]*require_configs_single_client_moderation_for_actor\( \$params \)/',
			$mut
		);
	}

	/**
	 * Round 15: legacy admin AJAX scopes user-targeting ops for WP-linked resellers.
	 */
	public function test_legacy_ajax_reseller_scope_contract(): void {
		$ajax = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-ajax.php' );
		$this->assertStringContainsString( 'legacy_ajax_linked_reseller_id', $ajax );
		$this->assertMatchesRegularExpression(
			'/function inbound_link[\s\S]*legacy_ajax_may_moderate_user/',
			$ajax
		);
		$this->assertMatchesRegularExpression(
			'/function user_merge[\s\S]*legacy_ajax_require_pure_site_admin/',
			$ajax
		);
	}

	/**
	 * Round 15: dashboard REST blocks reseller discount/marketing mutate (portal path separate).
	 */
	public function test_dashboard_reseller_readonly_mutate_policy_contract(): void {
		$policy = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-mutate-policy.php' );
		$this->assertStringContainsString( 'discount_redemptions', $policy );
		$this->assertStringNotContainsString( "'discount_save'", $policy );
		$this->assertStringNotContainsString( 'marketing_send_manual', $policy );
		$this->assertStringContainsString( 'signed Telegram/Bale admin portal', $policy );
		$ajax = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-ajax.php' );
		$this->assertStringContainsString( 'Reseller discount writes: signed portal only', $ajax );
	}

	/**
	 * Round 16: legacy inbound list/autolink scoped for WP-linked resellers.
	 */
	public function test_legacy_ajax_inbound_scope_contract(): void {
		$ajax = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-ajax.php' );
		$this->assertMatchesRegularExpression(
			'/function legacy_ajax_may_access_panel[\s\S]*allowed_panel_ids_for/',
			$ajax
		);
		$this->assertMatchesRegularExpression(
			'/function legacy_ajax_filter_inbound_clients[\s\S]*legacy_ajax_may_moderate_user/',
			$ajax
		);
		$this->assertMatchesRegularExpression(
			'/function inbound_clients[\s\S]*forbidden_scope/',
			$ajax
		);
	}

	/**
	 * Round 16: portal discount save maps full dashboard field set.
	 */
	public function test_portal_discount_field_parity_contract(): void {
		$ajax = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-ajax.php' );
		$this->assertStringContainsString( 'discount_valid_until', $ajax );
		$this->assertStringContainsString( 'discount_plan_ids', $ajax );
		$this->assertStringContainsString( 'discount_allow_renew', $ajax );
		$this->assertStringContainsString( 'svpc_max_discount', $ajax );

		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( 'active_with_plan_overlap', $mut );
	}

	/**
	 * Round 17: parent reseller may create sub-resellers with forced invited_by.
	 */
	public function test_parent_sub_reseller_create_contract(): void {
		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertMatchesRegularExpression(
			'/function op_user_manual_create[\s\S]*\$parent_actor/',
			$mut
		);
		$this->assertMatchesRegularExpression(
			'/function op_user_manual_create[\s\S]*parent_reseller_id/',
			$mut
		);
		$this->assertMatchesRegularExpression(
			'/function op_reseller_wp_provision[\s\S]*invited_by/',
			$mut
		);

		$rest = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( "if ( 'user_manual_create' === \$op )", $rest );
		$this->assertStringContainsString( "\$params['invited_by'] = (int) \$ctx['actorUserId']", $rest );

		$reports = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-admin-reseller-reports.php' );
		$this->assertStringContainsString( 'c.depth > 0', $reports );
		$this->assertStringContainsString( "u.role = 'reseller'", $reports );
	}
}
