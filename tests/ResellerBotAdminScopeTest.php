<?php
/**
 * Contract tests for bot admin hub reseller scoping and checkout isolation.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class ResellerBotAdminScopeTest extends TestCase {

	/**
	 * Empty panel allow-list must deny for reseller bot context.
	 */
	public function test_empty_panel_allowlist_denies_reseller(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-bot-reseller-scope.php' );
		$this->assertStringContainsString( 'if ( empty( $allowed ) ) {', $code );
		$this->assertStringContainsString( 'return false;', $code );
		$this->assertStringContainsString( 'bot_admin_may_moderate_user', $code );
		$this->assertMatchesRegularExpression(
			'/function bot_admin_may_access_user[\s\S]*bot_admin_may_moderate_user/',
			$code
		);
	}

	/**
	 * Admin hub handlers guard user access in reseller bot context.
	 */
	public function test_admin_hub_user_guard(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-pnl.php' );
		$this->assertStringContainsString( 'bot_admin_guard_user', $code );
		$this->assertStringContainsString( 'bot_admin_scope_user_ids', $code );
	}

	/**
	 * Checkout cards scoped to reseller owner only when billing context set.
	 */
	public function test_checkout_card_owner_scope_no_site_global(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-card.php' );
		$this->assertStringContainsString( 'invoice_card_owner_scope_svp_id', $code );
		$this->assertStringContainsString( 'active_ordered_for_owners( array( $scope_rid ) )', $code );
	}

	/**
	 * Discount service excludes site-global owner when billing reseller set.
	 */
	public function test_discount_billing_reseller_only(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-discount-service.php' );
		$this->assertStringContainsString( 'billing_reseller_svp_id', $code );
		$this->assertStringContainsString( '$owner_candidates = array( $billing_rid );', $code );
	}

	/**
	 * Service/receipt guards and site bulk block for reseller bot admin hub.
	 */
	public function test_admin_hub_service_receipt_and_bulk_guards(): void {
		$scope = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-bot-reseller-scope.php' );
		$hub   = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-pnl.php' );
		$this->assertStringContainsString( 'bot_admin_may_access_service', $scope );
		$this->assertStringContainsString( 'bot_admin_may_access_receipt', $scope );
		$this->assertStringContainsString( 'bot_admin_site_bulk_blocked', $scope );
		$this->assertStringContainsString( 'bot_admin_guard_service', $hub );
		$this->assertStringContainsString( 'bot_admin_guard_receipt', $hub );
		$this->assertStringContainsString( 'admin_stats_text_for_context', $hub );
	}

	/**
	 * Receipt/registration callbacks respect reseller downline scope.
	 */
	public function test_callback_receipt_registration_scope(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-callback.php' );
		$this->assertStringContainsString( 'bot_admin_may_access_receipt', $code );
		$this->assertStringContainsString( 'bot_admin_may_moderate_user', $code );
	}

	/**
	 * Bot ops prefer __actor_svp_user_id over WP user lookup.
	 */
	public function test_bot_mutations_use_actor_svp_user_id(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( 'mutate_reseller_actor_id', $code );
		$this->assertStringContainsString( '__actor_svp_user_id', $code );
	}

	/**
	 * Bot catalog delete passes actor context and guards card/panel ownership.
	 */
	public function test_admin_hub_catalog_delete_scoped(): void {
		$hub = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-pnl.php' );
		$this->assertStringContainsString( 'bot_admin_catalog_post_for_context', $hub );
		$this->assertStringContainsString( 'bot_admin_guard_card', $hub );
		$this->assertStringContainsString( 'bot_admin_guard_panel', $hub );
	}

	/**
	 * Reseller bot denies site broadcast and global L2TP settings.
	 */
	public function test_admin_broadcast_and_l2tp_denied_on_reseller_bot(): void {
		$admin = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin.php' );
		$hub   = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-pnl.php' );
		$this->assertStringContainsString( 'deny_global_settings_bot_action', $admin );
		$this->assertStringContainsString( 'admin_broadcast', $admin );
		$this->assertStringContainsString( 'bot_admin_deny_global_l2tp', $hub );
		$this->assertStringContainsString( 'bot_admin_guard_service', $hub );
	}

	/**
	 * Bot catalog toggle uses mutate bridge via Handler_Admin_Catalog.
	 */
	public function test_admin_hub_catalog_toggle_scoped(): void {
		$catalog = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-catalog.php' );
		$pnl     = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-pnl.php' );
		$this->assertStringContainsString( "Bot_Admin_Mutate::apply_for_user", $catalog );
		$this->assertStringContainsString( "'plan_action' => 'toggle'", $catalog );
		$this->assertStringContainsString( 'Handler_Admin_Catalog::dispatch_legacy', $pnl );
		$this->assertStringContainsString( 'bot_admin_filter_plans_for_context', $pnl );
	}

	/**
	 * Text-keys submenu denied at handler entry on reseller bot (Round 5).
	 */
	public function test_admin_hub_tx_handler_reseller_deny(): void {
		$hub = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-pnl.php' );
		$this->assertStringContainsString( "if ( 'tx' === \$sub )", $hub );
		$this->assertStringContainsString( 'bot_admin_deny_reseller_global( $platform, $chat_id )', $hub );
	}

	/**
	 * Service reply routes delegate through bot_admin_guard_service.
	 */
	public function test_admin_hub_service_reply_guarded(): void {
		$hub = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-pnl.php' );
		$this->assertStringContainsString( 'bot_admin_delegate_service_callback', $hub );
	}

	/**
	 * Reseller bot blocks catalog wizard, backup, logs, and L2TP submenu.
	 */
	public function test_reseller_global_bot_blocks_extended(): void {
		$scope = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-bot-reseller-scope.php' );
		$hub   = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-pnl.php' );
		$this->assertStringContainsString( "'l2p', 'txt', 'brd'", $scope );
		$this->assertStringContainsString( "'w', 'lg', 'th', 'tv'", $hub );
		$this->assertStringContainsString( "'tx'", $hub );
	}

	/**
	 * Bot admin service billing passes reseller invoice/card scope on reseller bot.
	 */
	public function test_bot_admin_billing_scope_parity(): void {
		$scope = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-bot-reseller-scope.php' );
		$hub   = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-pnl.php' );
		$admin = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin.php' );
		$this->assertStringContainsString( 'bot_admin_invoice_card_scope_reseller_id', $scope );
		$this->assertStringContainsString( 'bot_admin_invoice_card_scope_reseller_id()', $hub );
		$this->assertStringContainsString( 'admin_create_service( (int) $target_uid, (int) $plan_id, $vol, $mode, $scope )', $hub );
		$this->assertStringContainsString( 'admin_renew_service( $sid, $mode, $scope )', $hub );
		$this->assertStringContainsString( 'bot_admin_guard_panel( $platform, $chat_id, $pid )', $hub );
		$this->assertStringContainsString( 'bot_admin_invoice_card_scope_reseller_id()', $admin );
	}

	/**
	 * Reseller bot denies invalid panel id on inbound and bulk wizard entry (Round 3).
	 */
	public function test_reseller_inbound_panel_and_bulk_entry_guards(): void {
		$hub      = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-pnl.php' );
		$settings = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-settings.php' );
		$this->assertStringContainsString( 'if ( $pid < 1 ) {', $hub );
		$this->assertStringContainsString( 'if ( $pn < 1 ) {', $settings );
		$this->assertStringContainsString( "'hcb' === \$sub", $hub );
		$this->assertStringContainsString( 'bot_admin_deny_site_bulk( $platform, $chat_id )', $hub );
	}

	/**
	 * Legacy portal AJAX passes billing scope for reseller actors (Round 3).
	 */
	public function test_portal_ajax_billing_scope(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-ajax.php' );
		$this->assertStringContainsString( 'portal_admin_invoice_card_scope', $code );
		$this->assertStringContainsString( 'admin_create_service( $tuid, $pid, $vol, $mode, $scope )', $code );
	}

	public function test_impersonation_cookie_admin_bind(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( 'get_current_user_id() !== $admin_uid', $code );
		$this->assertStringContainsString( '$id . \'|\' . $exp . \'|\' . $admin_uid', $code );
	}

	/**
	 * Hub catalog filters use scoped admin context (dual-role on main bot).
	 */
	public function test_hub_filters_use_resolve_scope(): void {
		$hub   = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-pnl.php' );
		$scope = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-bot-reseller-scope.php' );
		$this->assertStringContainsString( 'bootstrap_scope_from_chat', $hub );
		$this->assertStringContainsString( 'is_scoped_bot_admin_context', $hub );
		$this->assertStringContainsString( 'is_scoped_bot_admin_context', $scope );
		$this->assertStringNotContainsString( 'is_reseller_bot_request', $hub );
	}

	/**
	 * Legacy admin handler always enforces may_* (not only on reseller bot webhook).
	 */
	public function test_handler_admin_scope_without_reseller_bot_gate(): void {
		$admin = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin.php' );
		$this->assertStringNotContainsString(
			'is_reseller_bot_request() && ! SimpleVPBot_Bot_Reseller_Scope::bot_admin_may',
			$admin
		);
		$this->assertStringContainsString( 'bot_admin_may_moderate_user', $admin );
	}

	/**
	 * Invoice/card billing scope uses resolve_scope_reseller_id for dual-role.
	 */
	public function test_invoice_card_scope_uses_resolve(): void {
		$scope = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-bot-reseller-scope.php' );
		$this->assertStringContainsString( 'function bot_admin_invoice_card_scope_reseller_id', $scope );
		$this->assertStringContainsString( 'resolve_scope_reseller_id()', $scope );
		$this->assertStringNotContainsString(
			"if ( ! self::is_reseller_bot_request() ) {\n\t\t\treturn 0;\n\t\t}",
			$scope
		);
	}
}
