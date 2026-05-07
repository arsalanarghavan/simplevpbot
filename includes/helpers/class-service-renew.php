<?php
/**
 * Renew / add volume after payment: same-cap renew, optional traffic reset and +30d expiry.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Service_Renew
 */
class SimpleVPBot_Service_Renew {

	const BYTES_PER_GB = 1073741824;

	/**
	 * @param object $svc Service row.
	 * @return int
	 */
	private static function svc_panel_id( $svc ) {
		return max( 1, (int) ( is_object( $svc ) ? ( $svc->panel_id ?? 1 ) : 1 ) );
	}

	/**
	 * For per-GB plans: GB count used on renew / autorenew invoice (service cap rounded to GB, clamped to plan min–max).
	 * Prevents billing e.g. 51200 GB when plan only allows 2–200 GB.
	 *
	 * @param object $svc  Service row.
	 * @param object $plan Plan row.
	 * @return int
	 */
	public static function per_gb_renew_billable_volume( $svc, $plan ) {
		if ( ! SimpleVPBot_Model_Plan::is_per_gb( $plan ) ) {
			return 1;
		}
		$cap = (int) $svc->total_traffic;
		$gb  = $cap > 0 ? max( 1, (int) round( $cap / self::BYTES_PER_GB ) ) : 1;
		$mn  = (int) ( $plan->traffic_gb_min ?? 0 );
		$mx  = (int) ( $plan->traffic_gb_max ?? 0 );
		if ( $mn >= 1 && $mx >= 1 && $mn <= $mx ) {
			return max( $mn, min( $gb, $mx ) );
		}
		return $gb;
	}

	/**
	 * Invoice amount for «same cap» renew (one cycle), from service quota + plan.
	 *
	 * @param object $svc  Service row.
	 * @param object $plan Plan row.
	 * @return float
	 */
	public static function checkout_price_renew( $svc, $plan ) {
		if ( SimpleVPBot_Model_Plan::is_per_gb( $plan ) ) {
			$gb = self::per_gb_renew_billable_volume( $svc, $plan );
			return round( SimpleVPBot_Model_Plan::total_price( $plan, $gb ), 2 );
		}
		return round( (float) ( $plan->price ?? 0 ), 2 );
	}

	/**
	 * Invoice amount for adding extra GB to an existing service.
	 *
	 * @param object $plan    Plan row.
	 * @param int    $extra_gb Extra gigabytes (>=1).
	 * @return float
	 */
	public static function checkout_price_add_volume( $plan, $extra_gb ) {
		$g = max( 1, (int) $extra_gb );
		if ( SimpleVPBot_Model_Plan::is_per_gb( $plan ) ) {
			return round( SimpleVPBot_Model_Plan::total_price( $plan, $g ), 2 );
		}
		$tb = (int) ( $plan->traffic_gb ?? 0 );
		if ( $tb < 1 ) {
			return round( (float) ( $plan->price ?? 0 ), 2 );
		}
		return round( (float) ( $plan->price ?? 0 ) * $g / $tb, 2 );
	}

	/**
	 * Used bytes from panel traffic API.
	 *
	 * @param string $email Client email.
	 * @return float
	 */
	private static function panel_used_bytes( $email ) {
		$tr  = SimpleVPBot_Xui_Client::get_client_traffics( (string) $email );
		$obj = is_array( $tr ) && isset( $tr['obj'] ) && is_array( $tr['obj'] ) ? $tr['obj'] : array();
		$up  = isset( $obj['up'] ) ? (float) $obj['up'] : 0.0;
		$dn  = isset( $obj['down'] ) ? (float) $obj['down'] : 0.0;
		return $up + $dn;
	}

	/**
	 * Apply same-cap renew on panel + DB after successful payment (no wallet debit).
	 *
	 * @param int $service_id svp_services.id.
	 * @return array{ok:bool, message:string}
	 */
	public static function apply_after_payment( $service_id ) {
		$sid = (int) $service_id;
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'message' => '⛔ سرویس پیدا نشد.' );
		}
		if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			return array( 'ok' => false, 'message' => '⛔ تمدید پرداختی فقط برای Xray است.' );
		}
		$plan = SimpleVPBot_Model_Service::effective_plan_for_pricing( $svc );
		if ( ! $plan ) {
			return array(
				'ok'      => false,
				'message' => '⛔ پلن سرویس برای این عملیات تنظیم نشده. در تنظیمات عمومی پلاگین، «پلن پیش‌فرض سرویس‌های بدون پلن» را روی یک پلن Xray فعال بگذارید.',
			);
		}
		return SimpleVPBot_Xui_Client::run_with_panel(
			self::svc_panel_id( $svc ),
			function () use ( $sid, $svc, $plan ) {
				if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
					return array( 'ok' => false, 'message' => '⛔ ورود به پنل ناموفق است.' );
				}

				$new_total_bytes = SimpleVPBot_Inbound_Linker::cap_traffic_bytes( (int) $svc->total_traffic );
				$panel_totalgb   = SimpleVPBot_Inbound_Linker::panel_client_totalgb_json_value( $new_total_bytes );

				$used_bytes  = self::panel_used_bytes( (string) $svc->email );
				$cap_limited = $new_total_bytes > 0;
				$exhausted   = $cap_limited && $used_bytes >= (float) $new_total_bytes;

				$has_exp   = ! empty( $svc->expires_at );
				$days_left = 99999.0;
				if ( $has_exp ) {
					$exp_ts = strtotime( (string) $svc->expires_at . ' UTC' );
					if ( false !== $exp_ts ) {
						$days_left = ( $exp_ts - time() ) / DAY_IN_SECONDS;
					}
				}
				$near_expiry = $has_exp && $days_left < 5;
				$do_reset    = $exhausted || $near_expiry;
				$extend_days = $near_expiry;

				$new_exp_mysql = $svc->expires_at ? (string) $svc->expires_at : '';
				$new_exp_ms    = null;
				if ( $extend_days && $has_exp ) {
					$exp_ts = strtotime( (string) $svc->expires_at . ' UTC' );
					if ( false !== $exp_ts ) {
						if ( $days_left >= 0 ) {
							$new_ts = $exp_ts + 30 * DAY_IN_SECONDS;
						} else {
							$new_ts = time() + 30 * DAY_IN_SECONDS;
						}
						$new_exp_mysql = gmdate( 'Y-m-d H:i:s', $new_ts );
						$new_exp_ms    = $new_ts * 1000;
					}
				}

				$inbound = SimpleVPBot_Xui_Client::inbound_get( (int) $svc->inbound_id );
				if ( ! $inbound ) {
					return array( 'ok' => false, 'message' => '⛔ اینباند پنل یافت نشد.' );
				}
				$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
				$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
				if ( ! is_array( $dec ) || empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
					return array( 'ok' => false, 'message' => '⛔ فهرست کلاینت خالی است.' );
				}

				$updated = null;
				$panel_remark = (string) $svc->remark;
				if ( class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
					$panel_remark = SimpleVPBot_Reseller_Branding::panel_client_name_for_user( (int) $svc->user_id, (string) $svc->remark );
				}
				foreach ( $dec['clients'] as &$cl ) {
					if ( isset( $cl['email'] ) && (string) $cl['email'] === (string) $svc->email ) {
						$cl['totalGB'] = $panel_totalgb;
						$cl['remark']  = $panel_remark;
						$cl['enable']  = true;
						if ( null !== $new_exp_ms ) {
							$cl['expiryTime'] = $new_exp_ms;
						}
						$updated = $cl;
						break;
					}
				}
				unset( $cl );
				if ( ! is_array( $updated ) ) {
					return array( 'ok' => false, 'message' => '⛔ کلاینت این سرویس روی پنل پیدا نشد.' );
				}

				$old_key = SimpleVPBot_Xui_Client::resolve_client_key_for_update( (string) $svc->xui_client_id, $inbound, (string) $svc->email );
				if ( ! $old_key ) {
					return array( 'ok' => false, 'message' => '⛔ شناسه کلاینت روی پنل پیدا نشد.' );
				}
				$path_ids = array( (string) $old_key );
				$em       = (string) $svc->email;
				if ( '' !== $em && $em !== (string) $old_key ) {
					$path_ids[] = $em;
				}

				$res = SimpleVPBot_Xui_Client::update_inbound_client_sequential( (int) $svc->inbound_id, $dec, $updated, $path_ids );
				if ( ! SimpleVPBot_Xui_Client::response_is_success( $res ) ) {
					SimpleVPBot_Logger::error(
						'renew apply updateClient failed',
						array(
							'svc_id'    => $sid,
							'res'       => $res,
							'panel_msg' => is_array( $res ) ? (string) ( $res['msg'] ?? '' ) : '',
						)
					);
					return array( 'ok' => false, 'message' => '⛔ بروزرسانی روی پنل انجام نشد.' );
				}

				if ( $do_reset ) {
					SimpleVPBot_Xui_Client::reset_client_traffic( (int) $svc->inbound_id, (string) $svc->email );
				}

				$db_up = array( 'total_traffic' => $new_total_bytes );
				if ( $extend_days && '' !== $new_exp_mysql ) {
					$db_up['expires_at'] = $new_exp_mysql;
				}
				if ( $do_reset ) {
					$db_up['used_traffic'] = 0;
				}
				if ( (int) ( $svc->plan_id ?? 0 ) < 1 && $plan ) {
					$db_up['plan_id'] = (int) $plan->id;
				}
				SimpleVPBot_Model_Service::update( $sid, $db_up );

				$msg = '✅ تمدید انجام شد. سقف حجم همان مقدار قبلی است.';
				if ( $extend_days ) {
					$msg .= ' ۳۰ روز به مهلت انقضا اضافه شد.';
				}
				if ( $do_reset ) {
					$msg .= ' شمارندهٔ مصرف صفر شد.';
				}
				return array( 'ok' => true, 'message' => $msg );
			}
		);
	}

	/**
	 * Add traffic quota after successful payment (does not reset usage).
	 *
	 * @param int $service_id svp_services.id.
	 * @param int $extra_gb   Extra gigabytes.
	 * @return array{ok:bool, message:string}
	 */
	public static function apply_add_volume_after_payment( $service_id, $extra_gb ) {
		$sid = (int) $service_id;
		$g   = max( 1, (int) $extra_gb );
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'message' => '⛔ سرویس پیدا نشد.' );
		}
		if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			return array( 'ok' => false, 'message' => '⛔ افزایش حجم پرداختی فقط برای Xray است.' );
		}
		return SimpleVPBot_Xui_Client::run_with_panel(
			self::svc_panel_id( $svc ),
			function () use ( $sid, $svc, $g ) {
				if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
					return array( 'ok' => false, 'message' => '⛔ ورود به پنل ناموفق است.' );
				}

				$add_bytes       = $g * self::BYTES_PER_GB;
				$new_total_bytes = SimpleVPBot_Inbound_Linker::cap_traffic_bytes( (int) $svc->total_traffic + $add_bytes );
				if ( $new_total_bytes < 0 ) {
					return array( 'ok' => false, 'message' => '⛔ حجم نامعتبر است.' );
				}
				$panel_totalgb = SimpleVPBot_Inbound_Linker::panel_client_totalgb_json_value( $new_total_bytes );

				$inbound = SimpleVPBot_Xui_Client::inbound_get( (int) $svc->inbound_id );
				if ( ! $inbound ) {
					return array( 'ok' => false, 'message' => '⛔ اینباند پنل یافت نشد.' );
				}
				$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
				$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
				if ( ! is_array( $dec ) || empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
					return array( 'ok' => false, 'message' => '⛔ فهرست کلاینت خالی است.' );
				}

				$updated = null;
				$panel_remark = (string) $svc->remark;
				if ( class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
					$panel_remark = SimpleVPBot_Reseller_Branding::panel_client_name_for_user( (int) $svc->user_id, (string) $svc->remark );
				}
				foreach ( $dec['clients'] as &$cl ) {
					if ( isset( $cl['email'] ) && (string) $cl['email'] === (string) $svc->email ) {
						$cl['totalGB'] = $panel_totalgb;
						$cl['remark']  = $panel_remark;
						$cl['enable']  = true;
						$updated       = $cl;
						break;
					}
				}
				unset( $cl );
				if ( ! is_array( $updated ) ) {
					return array( 'ok' => false, 'message' => '⛔ کلاینت این سرویس روی پنل پیدا نشد.' );
				}

				$old_key = SimpleVPBot_Xui_Client::resolve_client_key_for_update( (string) $svc->xui_client_id, $inbound, (string) $svc->email );
				if ( ! $old_key ) {
					return array( 'ok' => false, 'message' => '⛔ شناسه کلاینت روی پنل پیدا نشد.' );
				}
				$path_ids = array( (string) $old_key );
				$em       = (string) $svc->email;
				if ( '' !== $em && $em !== (string) $old_key ) {
					$path_ids[] = $em;
				}

				$res = SimpleVPBot_Xui_Client::update_inbound_client_sequential( (int) $svc->inbound_id, $dec, $updated, $path_ids );
				if ( ! SimpleVPBot_Xui_Client::response_is_success( $res ) ) {
					SimpleVPBot_Logger::error(
						'add volume updateClient failed',
						array(
							'svc_id'    => $sid,
							'res'       => $res,
							'panel_msg' => is_array( $res ) ? (string) ( $res['msg'] ?? '' ) : '',
						)
					);
					return array( 'ok' => false, 'message' => '⛔ بروزرسانی روی پنل انجام نشد.' );
				}

				$up = array( 'total_traffic' => $new_total_bytes );
				if ( (int) ( $svc->plan_id ?? 0 ) < 1 ) {
					$pfb = SimpleVPBot_Model_Service::effective_plan_for_pricing( $svc );
					if ( $pfb ) {
						$up['plan_id'] = (int) $pfb->id;
					}
				}
				SimpleVPBot_Model_Service::update( $sid, $up );
				return array(
					'ok'      => true,
					'message' => '✅ ' . $g . ' گیگ به سقف حجم سرویس اضافه شد.',
				);
			}
		);
	}

	/**
	 * Reduce traffic quota (admin) with floor at zero.
	 *
	 * @param int $service_id svp_services.id.
	 * @param int $reduce_gb  Gigabytes to reduce.
	 * @return array{ok:bool, message:string, applied_gb?:int}
	 */
	public static function apply_reduce_volume_free( $service_id, $reduce_gb ) {
		$sid = (int) $service_id;
		$g   = max( 1, (int) $reduce_gb );
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'message' => '⛔ سرویس پیدا نشد.' );
		}
		if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			return array( 'ok' => false, 'message' => '⛔ کاهش حجم فقط برای Xray است.' );
		}
		return SimpleVPBot_Xui_Client::run_with_panel(
			self::svc_panel_id( $svc ),
			function () use ( $sid, $svc, $g ) {
				if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
					return array( 'ok' => false, 'message' => '⛔ ورود به پنل ناموفق است.' );
				}
				$reduce_bytes    = $g * self::BYTES_PER_GB;
				$old_total_bytes = max( 0, (int) $svc->total_traffic );
				$new_total_bytes = max( 0, $old_total_bytes - $reduce_bytes );
				$applied_bytes   = max( 0, $old_total_bytes - $new_total_bytes );
				$applied_gb      = (int) floor( $applied_bytes / self::BYTES_PER_GB );
				$panel_totalgb   = SimpleVPBot_Inbound_Linker::panel_client_totalgb_json_value( $new_total_bytes );

				$inbound = SimpleVPBot_Xui_Client::inbound_get( (int) $svc->inbound_id );
				if ( ! $inbound ) {
					return array( 'ok' => false, 'message' => '⛔ اینباند پنل یافت نشد.' );
				}
				$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
				$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
				if ( ! is_array( $dec ) || empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
					return array( 'ok' => false, 'message' => '⛔ فهرست کلاینت خالی است.' );
				}
				$updated = null;
				$panel_remark = (string) $svc->remark;
				if ( class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
					$panel_remark = SimpleVPBot_Reseller_Branding::panel_client_name_for_user( (int) $svc->user_id, (string) $svc->remark );
				}
				foreach ( $dec['clients'] as &$cl ) {
					if ( isset( $cl['email'] ) && (string) $cl['email'] === (string) $svc->email ) {
						$cl['totalGB'] = $panel_totalgb;
						$cl['remark']  = $panel_remark;
						$cl['enable']  = true;
						$updated       = $cl;
						break;
					}
				}
				unset( $cl );
				if ( ! is_array( $updated ) ) {
					return array( 'ok' => false, 'message' => '⛔ کلاینت این سرویس روی پنل پیدا نشد.' );
				}
				$old_key = SimpleVPBot_Xui_Client::resolve_client_key_for_update( (string) $svc->xui_client_id, $inbound, (string) $svc->email );
				if ( ! $old_key ) {
					return array( 'ok' => false, 'message' => '⛔ شناسه کلاینت روی پنل پیدا نشد.' );
				}
				$path_ids = array( (string) $old_key );
				$em       = (string) $svc->email;
				if ( '' !== $em && $em !== (string) $old_key ) {
					$path_ids[] = $em;
				}
				$res = SimpleVPBot_Xui_Client::update_inbound_client_sequential( (int) $svc->inbound_id, $dec, $updated, $path_ids );
				if ( ! SimpleVPBot_Xui_Client::response_is_success( $res ) ) {
					return array( 'ok' => false, 'message' => '⛔ بروزرسانی روی پنل انجام نشد.' );
				}
				SimpleVPBot_Model_Service::update( $sid, array( 'total_traffic' => $new_total_bytes ) );
				return array(
					'ok'         => true,
					'applied_gb' => $applied_gb,
					'message'    => '✅ ' . $applied_gb . ' گیگ از سقف حجم سرویس کسر شد.',
				);
			}
		);
	}

	/**
	 * Invoice amount for buying extra concurrent-user slots.
	 *
	 * @param int $extra_users Count (>=1).
	 * @return float Toman.
	 */
	public static function checkout_price_add_user_slots( $extra_users ) {
		$n = max( 1, (int) $extra_users );
		$u = (float) SimpleVPBot_Settings::get( 'price_per_extra_user', 0 );
		return round( $n * $u, 2 );
	}

	/**
	 * Increase client concurrent-user cap after successful payment.
	 *
	 * @param int $service_id svp_services.id.
	 * @param int $extra_users Slots to add (1..50).
	 * @return array{ok:bool, message:string}
	 */
	public static function apply_add_user_slots_after_payment( $service_id, $extra_users ) {
		$sid = (int) $service_id;
		$add = max( 1, min( 50, (int) $extra_users ) );
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'message' => '⛔ سرویس پیدا نشد.' );
		}
		if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			return array( 'ok' => false, 'message' => '⛔ این بخش فقط برای سرویس‌های اتصال معمولی است.' );
		}
		return SimpleVPBot_Xui_Client::run_with_panel(
			self::svc_panel_id( $svc ),
			function () use ( $sid, $svc, $add ) {
				if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
					return array( 'ok' => false, 'message' => '⛔ اتصال به سرور ناموفق بود.' );
				}

				$inbound = SimpleVPBot_Xui_Client::inbound_get( (int) $svc->inbound_id );
				if ( ! $inbound ) {
					return array( 'ok' => false, 'message' => '⛔ سرویس روی سرور پیدا نشد.' );
				}
				$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
				$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
				if ( ! is_array( $dec ) || empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
					return array( 'ok' => false, 'message' => '⛔ فهرست کاربران خالی است.' );
				}

				$updated = null;
				$panel_remark = (string) $svc->remark;
				if ( class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
					$panel_remark = SimpleVPBot_Reseller_Branding::panel_client_name_for_user( (int) $svc->user_id, (string) $svc->remark );
				}
				foreach ( $dec['clients'] as &$cl ) {
					if ( isset( $cl['email'] ) && (string) $cl['email'] === (string) $svc->email ) {
						$cur = (int) ( $cl['limitIp'] ?? 0 );
						$def = max( 1, (int) SimpleVPBot_Settings::get( 'default_concurrent_users', 2 ) );
						$base = $cur > 0 ? $cur : $def;
						$cl['limitIp'] = $base + $add;
						$cl['remark']  = $panel_remark;
						$cl['enable']  = true;
						$updated       = $cl;
						break;
					}
				}
				unset( $cl );
				if ( ! is_array( $updated ) ) {
					return array( 'ok' => false, 'message' => '⛔ این سرویس روی سرور پیدا نشد.' );
				}

				$old_key = SimpleVPBot_Xui_Client::resolve_client_key_for_update( (string) $svc->xui_client_id, $inbound, (string) $svc->email );
				if ( ! $old_key ) {
					return array( 'ok' => false, 'message' => '⛔ شناسه فنی سرویس پیدا نشد.' );
				}
				$path_ids = array( (string) $old_key );
				$em       = (string) $svc->email;
				if ( '' !== $em && $em !== (string) $old_key ) {
					$path_ids[] = $em;
				}

				$res = SimpleVPBot_Xui_Client::update_inbound_client_sequential( (int) $svc->inbound_id, $dec, $updated, $path_ids );
				if ( ! SimpleVPBot_Xui_Client::response_is_success( $res ) ) {
					SimpleVPBot_Logger::error(
						'add user slots updateClient failed',
						array(
							'svc_id'    => $sid,
							'res'       => $res,
							'panel_msg' => is_array( $res ) ? (string) ( $res['msg'] ?? '' ) : '',
						)
					);
					return array( 'ok' => false, 'message' => '⛔ به‌روزرسانی انجام نشد.' );
				}

				return array(
					'ok'      => true,
					'message' => '✅ به محدودیت کاربر هم‌زمان، ' . $add . ' نفر اضافه شد.',
				);
			}
		);
	}

	/**
	 * Reduce client concurrent-user cap (admin) with floor at zero.
	 *
	 * @param int $service_id svp_services.id.
	 * @param int $reduce_users Slots to reduce.
	 * @return array{ok:bool, message:string, applied_users?:int}
	 */
	public static function apply_reduce_user_slots_free( $service_id, $reduce_users ) {
		$sid = (int) $service_id;
		$sub = max( 1, min( 50, (int) $reduce_users ) );
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'message' => '⛔ سرویس پیدا نشد.' );
		}
		if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			return array( 'ok' => false, 'message' => '⛔ این بخش فقط برای سرویس‌های اتصال معمولی است.' );
		}
		return SimpleVPBot_Xui_Client::run_with_panel(
			self::svc_panel_id( $svc ),
			function () use ( $sid, $svc, $sub ) {
				if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
					return array( 'ok' => false, 'message' => '⛔ اتصال به سرور ناموفق بود.' );
				}
				$inbound = SimpleVPBot_Xui_Client::inbound_get( (int) $svc->inbound_id );
				if ( ! $inbound ) {
					return array( 'ok' => false, 'message' => '⛔ سرویس روی سرور پیدا نشد.' );
				}
				$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
				$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
				if ( ! is_array( $dec ) || empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
					return array( 'ok' => false, 'message' => '⛔ فهرست کاربران خالی است.' );
				}

				$updated = null;
				$applied = 0;
				$panel_remark = (string) $svc->remark;
				if ( class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
					$panel_remark = SimpleVPBot_Reseller_Branding::panel_client_name_for_user( (int) $svc->user_id, (string) $svc->remark );
				}
				foreach ( $dec['clients'] as &$cl ) {
					if ( isset( $cl['email'] ) && (string) $cl['email'] === (string) $svc->email ) {
						$cur         = max( 0, (int) ( $cl['limitIp'] ?? 0 ) );
						$next        = max( 0, $cur - $sub );
						$applied     = $cur - $next;
						$cl['limitIp'] = $next;
						$cl['remark']  = $panel_remark;
						$cl['enable']  = true;
						$updated       = $cl;
						break;
					}
				}
				unset( $cl );
				if ( ! is_array( $updated ) ) {
					return array( 'ok' => false, 'message' => '⛔ این سرویس روی سرور پیدا نشد.' );
				}
				$old_key = SimpleVPBot_Xui_Client::resolve_client_key_for_update( (string) $svc->xui_client_id, $inbound, (string) $svc->email );
				if ( ! $old_key ) {
					return array( 'ok' => false, 'message' => '⛔ شناسه فنی سرویس پیدا نشد.' );
				}
				$path_ids = array( (string) $old_key );
				$em       = (string) $svc->email;
				if ( '' !== $em && $em !== (string) $old_key ) {
					$path_ids[] = $em;
				}
				$res = SimpleVPBot_Xui_Client::update_inbound_client_sequential( (int) $svc->inbound_id, $dec, $updated, $path_ids );
				if ( ! SimpleVPBot_Xui_Client::response_is_success( $res ) ) {
					return array( 'ok' => false, 'message' => '⛔ به‌روزرسانی انجام نشد.' );
				}
				return array(
					'ok'            => true,
					'applied_users' => $applied,
					'message'       => '✅ به محدودیت کاربر هم‌زمان، ' . $applied . ' نفر کاهش داده شد.',
				);
			}
		);
	}

	/**
	 * Add calendar days to service expiry (admin / bulk); no payment.
	 *
	 * @param int $service_id Service id.
	 * @param int $days       Days to add (1–3650).
	 * @return array{ok:bool, message:string}
	 */
	public static function apply_extend_days_free( $service_id, $days ) {
		$sid = (int) $service_id;
		$d   = max( 1, min( 3650, (int) $days ) );
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'message' => '⛔ سرویس پیدا نشد.' );
		}
		$add_sec = $d * DAY_IN_SECONDS;
		if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			$base = time();
			if ( ! empty( $svc->expires_at ) ) {
				$cur = strtotime( (string) $svc->expires_at . ' UTC' );
				if ( false !== $cur ) {
					$base = max( $base, $cur );
				}
			}
			$new_ts = $base + $add_sec;
			SimpleVPBot_Model_Service::update( $sid, array( 'expires_at' => gmdate( 'Y-m-d H:i:s', $new_ts ) ) );
			return array( 'ok' => true, 'message' => '✅ ' . $d . ' روز به انقضا اضافه شد.' );
		}
		return SimpleVPBot_Xui_Client::run_with_panel(
			self::svc_panel_id( $svc ),
			function () use ( $sid, $svc, $add_sec, $d ) {
				if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
					return array( 'ok' => false, 'message' => '⛔ ورود به پنل ناموفق است.' );
				}
				$base_ts = time();
				if ( ! empty( $svc->expires_at ) ) {
					$cur = strtotime( (string) $svc->expires_at . ' UTC' );
					if ( false !== $cur ) {
						$base_ts = max( $base_ts, $cur );
					}
				}
				$new_ts    = $base_ts + $add_sec;
				$new_mysql = gmdate( 'Y-m-d H:i:s', $new_ts );
				$new_ms    = $new_ts * 1000;

				$inbound = SimpleVPBot_Xui_Client::inbound_get( (int) $svc->inbound_id );
				if ( ! $inbound ) {
					return array( 'ok' => false, 'message' => '⛔ اینباند پنل یافت نشد.' );
				}
				$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
				$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
				if ( ! is_array( $dec ) || empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
					return array( 'ok' => false, 'message' => '⛔ فهرست کلاینت خالی است.' );
				}
				$updated = null;
				$panel_remark = (string) $svc->remark;
				if ( class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
					$panel_remark = SimpleVPBot_Reseller_Branding::panel_client_name_for_user( (int) $svc->user_id, (string) $svc->remark );
				}
				foreach ( $dec['clients'] as &$cl ) {
					if ( isset( $cl['email'] ) && (string) $cl['email'] === (string) $svc->email ) {
						$cl['expiryTime'] = $new_ms;
						$cl['remark']     = $panel_remark;
						$cl['enable']     = true;
						$updated          = $cl;
						break;
					}
				}
				unset( $cl );
				if ( ! is_array( $updated ) ) {
					return array( 'ok' => false, 'message' => '⛔ کلاینت روی پنل پیدا نشد.' );
				}
				$old_key = SimpleVPBot_Xui_Client::resolve_client_key_for_update( (string) $svc->xui_client_id, $inbound, (string) $svc->email );
				if ( ! $old_key ) {
					return array( 'ok' => false, 'message' => '⛔ شناسه کلاینت روی پنل پیدا نشد.' );
				}
				$path_ids = array( (string) $old_key );
				$em       = (string) $svc->email;
				if ( '' !== $em && $em !== (string) $old_key ) {
					$path_ids[] = $em;
				}
				$res = SimpleVPBot_Xui_Client::update_inbound_client_sequential( (int) $svc->inbound_id, $dec, $updated, $path_ids );
				if ( ! SimpleVPBot_Xui_Client::response_is_success( $res ) ) {
					return array( 'ok' => false, 'message' => '⛔ بروزرسانی پنل انجام نشد.' );
				}
				SimpleVPBot_Model_Service::update( $sid, array( 'expires_at' => $new_mysql ) );
				return array( 'ok' => true, 'message' => '✅ ' . $d . ' روز به انقضا اضافه شد.' );
			}
		);
	}

	/**
	 * Reduce service expiry by calendar days with floor at now.
	 *
	 * @param int $service_id Service id.
	 * @param int $days       Days to reduce (1–3650).
	 * @return array{ok:bool, message:string, applied_days?:int}
	 */
	public static function apply_reduce_days_free( $service_id, $days ) {
		$sid = (int) $service_id;
		$d   = max( 1, min( 3650, (int) $days ) );
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'message' => '⛔ سرویس پیدا نشد.' );
		}
		$sub_sec = $d * DAY_IN_SECONDS;
		$cur_ts  = ! empty( $svc->expires_at ) ? strtotime( (string) $svc->expires_at . ' UTC' ) : false;
		if ( false === $cur_ts ) {
			$cur_ts = time();
		}
		$now_ts     = time();
		$new_ts     = max( $now_ts, $cur_ts - $sub_sec );
		$applied    = (int) floor( max( 0, $cur_ts - $new_ts ) / DAY_IN_SECONDS );
		$new_mysql  = gmdate( 'Y-m-d H:i:s', $new_ts );
		$new_ms     = $new_ts * 1000;
		if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			SimpleVPBot_Model_Service::update( $sid, array( 'expires_at' => $new_mysql ) );
			return array( 'ok' => true, 'applied_days' => $applied, 'message' => '✅ ' . $applied . ' روز از انقضا کسر شد.' );
		}
		return SimpleVPBot_Xui_Client::run_with_panel(
			self::svc_panel_id( $svc ),
			function () use ( $sid, $svc, $new_ms, $new_mysql, $applied ) {
				if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
					return array( 'ok' => false, 'message' => '⛔ ورود به پنل ناموفق است.' );
				}
				$inbound = SimpleVPBot_Xui_Client::inbound_get( (int) $svc->inbound_id );
				if ( ! $inbound ) {
					return array( 'ok' => false, 'message' => '⛔ اینباند پنل یافت نشد.' );
				}
				$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
				$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
				if ( ! is_array( $dec ) || empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
					return array( 'ok' => false, 'message' => '⛔ فهرست کلاینت خالی است.' );
				}
				$updated = null;
				$panel_remark = (string) $svc->remark;
				if ( class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
					$panel_remark = SimpleVPBot_Reseller_Branding::panel_client_name_for_user( (int) $svc->user_id, (string) $svc->remark );
				}
				foreach ( $dec['clients'] as &$cl ) {
					if ( isset( $cl['email'] ) && (string) $cl['email'] === (string) $svc->email ) {
						$cl['expiryTime'] = $new_ms;
						$cl['remark']     = $panel_remark;
						$cl['enable']     = true;
						$updated          = $cl;
						break;
					}
				}
				unset( $cl );
				if ( ! is_array( $updated ) ) {
					return array( 'ok' => false, 'message' => '⛔ کلاینت روی پنل پیدا نشد.' );
				}
				$old_key = SimpleVPBot_Xui_Client::resolve_client_key_for_update( (string) $svc->xui_client_id, $inbound, (string) $svc->email );
				if ( ! $old_key ) {
					return array( 'ok' => false, 'message' => '⛔ شناسه کلاینت روی پنل پیدا نشد.' );
				}
				$path_ids = array( (string) $old_key );
				$em       = (string) $svc->email;
				if ( '' !== $em && $em !== (string) $old_key ) {
					$path_ids[] = $em;
				}
				$res = SimpleVPBot_Xui_Client::update_inbound_client_sequential( (int) $svc->inbound_id, $dec, $updated, $path_ids );
				if ( ! SimpleVPBot_Xui_Client::response_is_success( $res ) ) {
					return array( 'ok' => false, 'message' => '⛔ بروزرسانی پنل انجام نشد.' );
				}
				SimpleVPBot_Model_Service::update( $sid, array( 'expires_at' => $new_mysql ) );
				return array( 'ok' => true, 'applied_days' => $applied, 'message' => '✅ ' . $applied . ' روز از انقضا کسر شد.' );
			}
		);
	}
}
