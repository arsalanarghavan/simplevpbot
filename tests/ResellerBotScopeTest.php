<?php
/**
 * Contract tests for reseller bot scope helpers.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class ResellerBotScopeTest extends TestCase {

	/**
	 * Scope helper must exist and expose signup + checkout hooks.
	 */
	public function test_reseller_scope_helper_contract(): void {
		$file = dirname( __DIR__ ) . '/includes/helpers/class-bot-reseller-scope.php';
		$code = (string) file_get_contents( $file );
		$this->assertStringContainsString( 'resolve_invited_by_for_signup', $code );
		$this->assertStringContainsString( 'enrich_checkout_meta', $code );
		$this->assertStringContainsString( 'billing_reseller_svp_id', $code );
		$this->assertStringContainsString( 'invoice_card_owner_scope_svp_id', $code );
		$this->assertStringContainsString( 'admin_ids_for_context', $code );
		$this->assertStringContainsString( 'by_category_for_owners', (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-plan.php' ) );
	}

	/**
	 * Start handler must use reseller scope for invited_by on new users.
	 */
	public function test_start_handler_uses_reseller_scope_bind(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-start.php' );
		$this->assertStringContainsString( 'SimpleVPBot_Bot_Reseller_Scope::resolve_invited_by_for_signup', $code );
	}

	/**
	 * Buy handler must enrich checkout meta and filter plans by owner.
	 */
	public function test_buy_handler_uses_catalog_scope(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-buy.php' );
		$this->assertStringContainsString( 'enrich_checkout_meta', $code );
		$this->assertStringContainsString( 'plans_for_category', $code );
		$this->assertStringContainsString( 'plan_available_in_context', $code );
		$this->assertStringContainsString( 'SimpleVPBot_Bot_Reseller_Scope::admin_ids_for_context', $code );
	}

	/**
	 * Referral service exposes bind validator without referral_enabled gate.
	 */
	public function test_referral_validate_bind_inviter(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-referral-service.php' );
		$this->assertStringContainsString( 'validate_bind_inviter_id', $code );
		$this->assertStringContainsString( 'function validate_bind_inviter_id', $code );
	}

	/**
	 * Phase 2: reseller-scoped notify, referral username, category/panel filters.
	 */
	public function test_phase2_reseller_notify_referral_catalog(): void {
		$scope = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-bot-reseller-scope.php' );
		$this->assertStringContainsString( 'resolve_reseller_id_for_notify', $scope );
		$this->assertStringContainsString( 'signup_reseller_svp_id', $scope );
		$this->assertStringContainsString( 'allowed_panel_ids', $scope );
		$this->assertStringContainsString( 'panel_allowed_in_context', $scope );
		$this->assertStringContainsString( 'reseller_can_sell_on_panel', $scope );
		$this->assertStringContainsString( 'reseller_blocks_global_settings', $scope );
		$this->assertStringContainsString( 'deny_global_settings_bot_action', $scope );

		$hub = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-hub.php' );
		$this->assertStringContainsString( 'reseller_hub_submenu_blocked', $hub );
		$set = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-settings.php' );
		$this->assertStringContainsString( 'deny_global_settings_bot_action', $set );

		$rest = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( 'annotate_reseller_panels_for_dashboard', $rest );
		$this->assertStringContainsString( 'can_sell_plan', $rest );

		$ref = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-referral-service.php' );
		$this->assertStringContainsString( 'bot_username_for_platform', $ref );
		$this->assertStringContainsString( '$reseller_svp_user_id', $ref );

		$buy = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-buy.php' );
		$this->assertStringContainsString( 'buyable_categories_for_context', $buy );

		$rp = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-receipt-processor.php' );
		$this->assertStringContainsString( 'send_message_for_reseller', $rp );
		$this->assertStringContainsString( 'resolve_reseller_id_for_notify', $rp );

		$rt = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/class-bot-runtime.php' );
		$this->assertStringContainsString( 'send_message_for_reseller', $rt );
		$this->assertStringContainsString( 'token_for_platform', $rt );
	}

	/**
	 * Phase 3: encrypted tokens and per-reseller text overrides.
	 */
	public function test_phase4_backfill_and_notify_helpers(): void {
		$bf = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-reseller-backfill.php' );
		$this->assertStringContainsString( 'infer_billing_reseller_for_tx', $bf );
		$this->assertStringContainsString( 'bind_users_to_reseller', $bf );

		$nt = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-user-notify.php' );
		$this->assertStringContainsString( 'platforms_for_user', $nt );
		$this->assertStringContainsString( 'send_to_user', $nt );
		$this->assertStringContainsString( 'send_message_for_reseller', $nt );

		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( 'SimpleVPBot_User_Notify::send_to_user', $mut );
		$this->assertStringContainsString( 'send_message_for_reseller', $mut );

		$u = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-user.php' );
		$this->assertStringContainsString( 'invalidate_reseller_scope_cache', $u );
		$this->assertStringContainsString( 'signup_reseller_svp_id', (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-activator.php' ) );
	}

	public function test_phase3_encryption_and_text_overrides(): void {
		$prof = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-reseller-bot-profile.php' );
		$this->assertStringContainsString( 'encrypt_token_field', $prof );
		$this->assertStringContainsString( 'decrypt_token_field', $prof );
		$this->assertStringContainsString( 'allowed_text_override_keys', $prof );
		$this->assertStringContainsString( 'text_overrides_json', $prof );

		$texts = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/class-texts.php' );
		$this->assertStringContainsString( 'get_text_override', $texts );
		$this->assertStringContainsString( 'get_in_bot_context', $texts );

		$app = (string) file_get_contents( dirname( __DIR__ ) . '/dashboard-ui/src/App.tsx' );
		$this->assertStringContainsString( 'receipts: "receipts.review"', $app );
	}
}
