<?php
/**
 * Auto-renew services from balance (updates panel + DB atomically).
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
	 * Attempt to renew a single service.
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
		if ( SimpleVPBot_Model_Plan::is_per_gb( $plan ) ) {
			$price    = SimpleVPBot_Service_Renew::checkout_price_renew( $svc, $plan );
			$total_gb = SimpleVPBot_Service_Renew::per_gb_renew_billable_volume( $svc, $plan );
		} else {
			$price    = (float) $plan->price;
			$total_gb = (int) $plan->traffic_gb;
		}
		if ( (float) $user->balance < $price ) {
			self::msg( $user, '❌ تمدید خودکار سرویس «' . (string) $svc->remark . '» ناموفق بود: موجودی کیف پول کافی نیست.' );
			return;
		}

		$base_time   = max( time(), $exp );
		$new_exp_ts  = $base_time + (int) $plan->duration_days * DAY_IN_SECONDS;
		$new_expiry_ms = $new_exp_ts * 1000;
		$total_bytes = $total_gb > 0 ? $total_gb * 1073741824 : 0;
		$total_bytes = SimpleVPBot_Inbound_Linker::cap_traffic_bytes( (int) $total_bytes );

		if ( ! self::push_panel_renewal( $svc, $new_expiry_ms, $total_bytes ) ) {
			SimpleVPBot_Logger::error( 'autorenew: panel update failed', array( 'svc_id' => (int) $svc->id ) );
			self::msg( $user, '⚠️ تمدید خودکار سرویس «' . (string) $svc->remark . '» به دلیل خطا در پنل انجام نشد. به‌زودی دوباره تلاش می‌کنیم.' );
			return;
		}

		global $wpdb;
		$users_table    = $wpdb->prefix . 'svp_users';
		$services_table = $wpdb->prefix . 'svp_services';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$affected = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$users_table} SET balance = balance - %f WHERE id = %d AND balance >= %f",
				$price,
				(int) $user->id,
				$price
			)
		);
		if ( ! $affected ) {
			SimpleVPBot_Logger::error( 'autorenew: balance deduct race', array( 'user_id' => (int) $user->id, 'svc_id' => (int) $svc->id ) );
			return;
		}

		$new_exp = gmdate( 'Y-m-d H:i:s', $new_exp_ts );
		$svc_up  = array(
			'expires_at'        => $new_exp,
			'total_traffic'     => $total_bytes,
			'used_traffic'      => 0,
			'last_warn_sent_at' => null,
		);
		if ( (int) ( $svc->plan_id ?? 0 ) < 1 ) {
			$svc_up['plan_id'] = (int) $plan->id;
		}
		SimpleVPBot_Model_Service::update( (int) $svc->id, $svc_up );
		SimpleVPBot_Model_Transaction::insert(
			array(
				'user_id'    => (int) $user->id,
				'service_id' => (int) $svc->id,
				'amount'     => $price,
				'type'       => 'renew',
				'status'     => 'approved',
				'meta_json'  => wp_json_encode( array( 'autorenew' => true, 'plan_id' => (int) $plan->id ) ),
			)
		);
		self::msg( $user, '✅ تمدید خودکار سرویس «' . (string) $svc->remark . '» انجام شد.' );
	}

	/**
	 * Update client on panel: expiryTime + totalGB + reset traffic counter.
	 *
	 * @param object $svc Service.
	 * @param int    $expiry_ms New expiry (ms).
	 * @param int    $total_traffic_bytes Total traffic limit in bytes (0 = unlimited); sent as 3x-ui client `totalGB` JSON field.
	 * @return bool
	 */
	private static function push_panel_renewal( $svc, $expiry_ms, $total_traffic_bytes ) {
		return SimpleVPBot_Xui_Client::run_with_panel(
			max( 1, (int) ( $svc->panel_id ?? 1 ) ),
			function () use ( $svc, $expiry_ms, $total_traffic_bytes ) {
				return self::push_panel_renewal_on_bound( $svc, $expiry_ms, $total_traffic_bytes );
			}
		);
	}

	/**
	 * @param object $svc Service.
	 * @param int    $expiry_ms Expiry ms.
	 * @param int    $total_traffic_bytes Bytes.
	 * @return bool
	 */
	private static function push_panel_renewal_on_bound( $svc, $expiry_ms, $total_traffic_bytes ) {
		if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
			return false;
		}
		$inbound = SimpleVPBot_Xui_Client::inbound_get( (int) $svc->inbound_id );
		if ( ! $inbound ) {
			return false;
		}
		$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
		$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
		if ( ! is_array( $dec ) || empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
			return false;
		}
		$found = false;
		foreach ( $dec['clients'] as &$cl ) {
			if ( isset( $cl['email'] ) && (string) $cl['email'] === (string) $svc->email ) {
				$cl['expiryTime'] = $expiry_ms;
				$cl['totalGB']    = SimpleVPBot_Inbound_Linker::panel_client_totalgb_json_value( (int) $total_traffic_bytes );
				$cl['enable']     = true;
				$found            = true;
				break;
			}
		}
		unset( $cl );
		if ( ! $found ) {
			return false;
		}
		$payload = array(
			'id'       => (int) $svc->inbound_id,
			'settings' => wp_json_encode( $dec ),
		);
		$res = SimpleVPBot_Xui_Client::update_client( (string) $svc->xui_client_id, $payload );
		$ok  = is_array( $res ) && ( ! empty( $res['success'] ) || ! empty( $res['obj'] ) );
		if ( $ok ) {
			SimpleVPBot_Xui_Client::reset_client_traffic( (int) $svc->inbound_id, (string) $svc->email );
		}
		return (bool) $ok;
	}

	/**
	 * Message user on both linked bots.
	 *
	 * @param object $user User.
	 * @param string $text Text.
	 */
	private static function msg( $user, $text ) {
		$tg_tok = (string) SimpleVPBot_Settings::get( 'telegram_token', '' );
		$bl_tok = (string) SimpleVPBot_Settings::get( 'bale_token', '' );
		if ( $tg_tok && ! empty( $user->tg_user_id ) ) {
			( new SimpleVPBot_Telegram_Client( $tg_tok ) )->send_message( array( 'chat_id' => (int) $user->tg_user_id, 'text' => $text ) );
		}
		if ( $bl_tok && ! empty( $user->bale_user_id ) ) {
			( new SimpleVPBot_Bale_Client( $bl_tok ) )->send_message(
				array(
					'chat_id' => (int) $user->bale_user_id,
					'text'    => SimpleVPBot_Bot_Runtime::scrub_bale_text( $text ),
				)
			);
		}
	}
}
