<?php
/**
 * Auto-renew services from balance (same rules as paid renew via Service_Renew).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Cron_Autorenew
 */
class SimpleVPBot_Cron_Autorenew {

	const LOCK_KEY = 'simplevpbot_cron_autorenew_lock';

	/**
	 * Run.
	 */
	public static function run() {
		if ( ! SimpleVPBot_Settings::get( 'enabled', true ) ) {
			return;
		}
		if ( false !== get_transient( self::LOCK_KEY ) ) {
			return;
		}
		set_transient( self::LOCK_KEY, 1, 15 * MINUTE_IN_SECONDS );

		try {
			$services = SimpleVPBot_Model_Service::all();
			foreach ( $services as $svc ) {
				self::renew_one( $svc );
			}
		} finally {
			delete_transient( self::LOCK_KEY );
		}
	}

	/**
	 * Attempt to renew a single service (paid-renew semantics: same cap, conditional +30d / reset).
	 *
	 * @param object $svc Service row.
	 */
	private static function renew_one( $svc ) {
		if ( ! (int) $svc->autorenew || SimpleVPBot_Model_Service::is_l2tp( $svc ) || ! $svc->expires_at ) {
			return;
		}
		$exp = strtotime( $svc->expires_at . ' UTC' );
		if ( false === $exp ) {
			return;
		}
		if ( $exp - time() > DAY_IN_SECONDS ) {
			return;
		}
		$plan = SimpleVPBot_Model_Service::effective_plan_for_pricing( $svc );
		if ( ! $plan ) {
			return;
		}
		$user = SimpleVPBot_Model_User::find( (int) $svc->user_id );
		if ( ! $user ) {
			return;
		}
		$price = SimpleVPBot_Service_Renew::checkout_price_renew( $svc, $plan );
		if ( (float) $user->balance < $price ) {
			self::msg( $user, '❌ تمدید خودکار سرویس «' . (string) $svc->remark . '» ناموفق بود: موجودی کیف پول کافی نیست.' );
			return;
		}

		if ( ! SimpleVPBot_Model_User::decrement_balance_if_sufficient( (int) $user->id, $price ) ) {
			SimpleVPBot_Logger::error( 'autorenew: balance deduct race', array( 'user_id' => (int) $user->id, 'svc_id' => (int) $svc->id ) );
			return;
		}

		$rn = SimpleVPBot_Service_Renew::apply_after_payment( (int) $svc->id );
		if ( empty( $rn['ok'] ) ) {
			SimpleVPBot_Model_User::increment_balance( (int) $user->id, $price );
			SimpleVPBot_Logger::error(
				'autorenew: panel renew failed (refunded)',
				array(
					'svc_id' => (int) $svc->id,
					'msg'    => (string) ( $rn['message'] ?? '' ),
				)
			);
			self::msg( $user, '⚠️ تمدید خودکار سرویس «' . (string) $svc->remark . '» به دلیل خطا در پنل انجام نشد. مبلغ به کیف پول برگشت.' );
			return;
		}

		SimpleVPBot_Model_Transaction::insert(
			array(
				'user_id'    => (int) $user->id,
				'service_id' => (int) $svc->id,
				'amount'     => $price,
				'type'       => 'renew',
				'status'     => 'approved',
				'meta_json'  => wp_json_encode(
					array(
						'autorenew' => true,
						'plan_id'   => (int) $plan->id,
					)
				),
			)
		);
		self::msg( $user, '✅ تمدید خودکار سرویس «' . (string) $svc->remark . '» انجام شد (همان سقف حجم؛ در صورت نزدیک بودن انقضا یا اتمام حجم، ۳۰ روز و ریست مصرف اعمال می‌شود).' );
	}

	/**
	 * Message user on both linked bots.
	 *
	 * @param object $user User.
	 * @param string $text Text.
	 */
	private static function msg( $user, $text ) {
		if ( class_exists( 'SimpleVPBot_User_Notify' ) ) {
			SimpleVPBot_User_Notify::send_to_user( $user, $text );
		}
	}
}
