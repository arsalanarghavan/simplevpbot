<?php
/**
 * Reseller dashboard mutation authorization (op → required permission).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Dashboard_Mutate_Policy
 */
class SimpleVPBot_Dashboard_Mutate_Policy {

	/**
	 * Required reseller permission for an op, or null if reseller may not call it.
	 *
	 * @param string $op Sanitized operation key.
	 * @return string|null Permission key from {@see SimpleVPBot_Model_User::RESELLER_PERMISSION_KEYS}, or null if forbidden.
	 */
	public static function reseller_mutate_required_permission( $op ) {
		$op = sanitize_key( (string) $op );
		static $map = array(
			'plan'                       => 'plans.manage',
			'plan_category'              => 'plans.manage',
			'broadcast_send'             => 'broadcast.send',
			'broadcast_cancel'           => 'broadcast.send',
			'discount_save'              => 'plans.manage',
			'discount_delete'            => 'plans.manage',
			'discount_redemptions'       => 'plans.manage',
			'card_add'                   => 'plans.manage',
			'card_update'                => 'plans.manage',
			'card_delete'                => 'plans.manage',
			'receipt_action'             => 'receipts.review',
			'receipt_set_status'         => 'receipts.review',
			'receipt_update'             => 'receipts.review',
			'receipt_reject_reasons_save' => 'receipts.review',
			'membership'                 => 'users.manage',
			'user_status'                => 'users.manage',
			'user_balance_delta'         => 'users.manage',
			'user_create_service'        => 'services.manage',
			'user_renew_service'         => 'services.manage',
			'user_add_volume'            => 'services.manage',
			'user_reduce_volume'         => 'services.manage',
			'user_add_days'              => 'services.manage',
			'user_reduce_days'           => 'services.manage',
			'user_service_reduce_slots'  => 'services.manage',
			'user_service_transfer'      => 'services.manage',
			'user_service_toggle_enable' => 'services.manage',
			'service_delete'             => 'services.manage',
			'user_admin_message'         => 'users.manage',
			'service_alerts_patch'       => 'services.manage',
			'service_panel_sync'         => 'services.manage',
			'service_regen_key'          => 'services.manage',
			'service_panel_refresh'      => 'services.manage',
			'service_panel_delete_client' => 'services.manage',
			'user_service_add_slots'     => 'services.manage',
			'service_set_limit_ip'       => 'services.manage',
			'user_manual_create'         => 'users.manage',
			'bot_reseller_save'          => 'services.manage',
			'bot_reseller_secret_rotate' => 'services.manage',
			'bot_reseller_toggle_enabled' => 'services.manage',
			'bot_test_telegram'          => 'services.manage',
			'bot_test_bale'              => 'services.manage',
			'reseller_bot_webhook_set'   => 'services.manage',
			'reseller_bot_webhook_delete' => 'services.manage',
			'bot_delete_webhook'         => 'services.manage',
			'bot_admin_id_add'           => 'services.manage',
			'bot_admin_id_remove'        => 'services.manage',
			'reseller_panel_prices_save' => 'users.manage',
			'users_bulk_wallet'          => 'users.bulk',
			'users_bulk_volume'          => 'users.bulk',
			'users_bulk_extend'          => 'users.bulk',
			'users_bulk_alerts'          => 'users.bulk',
			'users_bulk_slots'           => 'users.bulk',
			'users_bulk_job_cancel'      => 'users.bulk',
			'users_bulk_job_resume'      => 'users.bulk',
			'reseller_wallet_topup_checkout' => 'plans.manage',
		);
		if ( ! isset( $map[ $op ] ) ) {
			return null;
		}
		return $map[ $op ];
	}
}
