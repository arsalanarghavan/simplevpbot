<?php
/**
 * Expiry / low traffic notifications (with per-bucket dedupe).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Cron_Expiry
 */
class SimpleVPBot_Cron_Expiry {

	const OPTION_SENT_BUCKETS = 'simplevpbot_expiry_sent_buckets';

	/**
	 * Run.
	 */
	public static function run() {
		if ( ! SimpleVPBot_Settings::get( 'enabled', true ) ) {
			return;
		}
		$services = SimpleVPBot_Model_Service::all();
		$cleaned_l2tp_expired = array();
		foreach ( $services as $svc ) {
			if ( ! SimpleVPBot_Service_Alerts::any_enabled( $svc ) ) {
				continue;
			}
			$user = SimpleVPBot_Model_User::find( (int) $svc->user_id );
			if ( ! $user || 'approved' !== $user->status ) {
				continue;
			}
			if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
				self::sync_l2tp_usage( $svc );
				$svc = SimpleVPBot_Model_Service::find( (int) $svc->id ) ?: $svc;
				if ( $svc->expires_at ) {
					$exp_ts = strtotime( (string) $svc->expires_at . ' UTC' );
					if ( $exp_ts && $exp_ts < time() && empty( $cleaned_l2tp_expired[ (int) $svc->id ] ) ) {
						SimpleVPBot_L2TP_Provisioner::delete_user( $svc );
						$cleaned_l2tp_expired[ (int) $svc->id ] = 1;
					}
				}
			} else {
				self::sync_traffic_if_stale( $svc );
				$svc = SimpleVPBot_Model_Service::find( (int) $svc->id ) ?: $svc;
			}

			$purge_covers = class_exists( 'SimpleVPBot_Cron_Purge_Expired' )
				&& SimpleVPBot_Cron_Purge_Expired::covers_expired_service( $svc );

			$total = (int) $svc->total_traffic;
			$used  = (int) $svc->used_traffic;
			$pct_th = SimpleVPBot_Service_Alerts::effective_low_traffic_pct( $svc );
			if ( SimpleVPBot_Service_Alerts::global_notify_volume_on() && SimpleVPBot_Service_Alerts::volume_enabled( $svc ) && $total > 0 ) {
				$remaining_pct = (int) floor( ( ( $total - $used ) * 100 ) / $total );
				if ( $remaining_pct <= $pct_th && $remaining_pct >= 0 ) {
					$bucket_key = 'svc' . (int) $svc->id . ':low:' . $pct_th;
					if ( self::claim_and_notify(
						$bucket_key,
						$user,
						self::build_low_traffic_text( $user, $svc, $remaining_pct ),
						(int) $svc->id
					) ) {
						// Sent.
					}
				}
			}

			if ( ! SimpleVPBot_Model_Service::is_l2tp( $svc ) && SimpleVPBot_Service_Alerts::global_notify_users_on() && SimpleVPBot_Service_Alerts::users_enabled( $svc ) ) {
				$pid = max( 1, (int) ( $svc->panel_id ?? 1 ) );
				$lim = SimpleVPBot_Service_Alerts::client_limit_ip( (int) $svc->inbound_id, (string) $svc->email, $pid );
				if ( $lim > 0 ) {
					$n_ip   = SimpleVPBot_Service_Alerts::client_ip_count( (string) $svc->email, $pid );
					$ip_th  = SimpleVPBot_Service_Alerts::effective_ip_fill_pct( $svc );
					$need   = (int) max( 1, (int) ceil( $lim * $ip_th / 100 ) );
					$min_d  = max( 1, (int) SimpleVPBot_Settings::get( 'alert_ip_warn_min_distinct', 3 ) );
					$need_e = max( $need, $min_d );
					if ( $n_ip >= $need_e ) {
						$h_ok = self::ip_alert_hysteresis_allow_send( (int) $svc->id, $n_ip, $need_e, $lim );
						$cd_ok = ! self::ip_alert_cooldown_active( (int) $svc->id );
						if ( $h_ok && $cd_ok ) {
							$bucket_key = 'svc' . (int) $svc->id . ':ip:' . $lim . ':' . $ip_th . ':m' . $min_d;
							if ( self::claim_and_notify(
								$bucket_key,
								$user,
								self::build_ip_distinct_text( $user, $svc, $n_ip, $lim, $need_e ),
								(int) $svc->id
							) ) {
								self::ip_alert_cooldown_mark( (int) $svc->id );
							}
						}
					} else {
						delete_transient( 'svp_ip_hyst_' . (int) $svc->id );
					}
				}
			}

			if ( $svc->expires_at && SimpleVPBot_Service_Alerts::global_notify_expiry_on() && SimpleVPBot_Service_Alerts::expiry_enabled( $svc ) ) {
				$exp = strtotime( (string) $svc->expires_at . ' UTC' );
				if ( false !== $exp ) {
					$days      = (int) floor( ( $exp - time() ) / DAY_IN_SECONDS );
					$warn_days = SimpleVPBot_Service_Alerts::effective_expiry_days( $svc );
					if ( $days >= 0 && in_array( $days, $warn_days, true ) ) {
						$bucket_key = 'svc' . (int) $svc->id . ':expd:' . $days;
						self::claim_and_notify(
							$bucket_key,
							$user,
							self::build_expiry_text( $user, $svc, $days ),
							(int) $svc->id
						);
					}
					if ( $days < 0 && ! $purge_covers && in_array( $days, $warn_days, true ) ) {
						$bucket_key = 'svc' . (int) $svc->id . ':expd:' . $days;
						self::claim_and_notify(
							$bucket_key,
							$user,
							self::build_expiry_text( $user, $svc, $days ),
							(int) $svc->id
						);
					}
					if ( $days < 0 && ! $purge_covers && SimpleVPBot_Service_Alerts::global_notify_after_expire_on() ) {
						$bucket_key = 'svc' . (int) $svc->id . ':expired:' . gmdate( 'Y-m-d', $exp );
						self::claim_and_notify(
							$bucket_key,
							$user,
							self::build_after_expired_text( $user, $svc ),
							(int) $svc->id
						);
					}
				}
			}
		}
	}

	/**
	 * Claim dedupe bucket and send when allowed.
	 *
	 * @param string $bucket_key Bucket id.
	 * @param object $user       User row.
	 * @param string $text       Message body.
	 * @param int    $service_id Service id for last_warn_sent_at.
	 * @return bool True when sent.
	 */
	private static function claim_and_notify( $bucket_key, $user, $text, $service_id ) {
		if ( class_exists( 'SimpleVPBot_Notification_Dedup' ) ) {
			if ( ! SimpleVPBot_Notification_Dedup::claim( 'expiry', (string) $bucket_key, 45 ) ) {
				return false;
			}
		} else {
			$sent = (array) get_option( self::OPTION_SENT_BUCKETS, array() );
			if ( ! empty( $sent[ $bucket_key ] ) ) {
				return false;
			}
			$sent[ $bucket_key ] = time();
			update_option( self::OPTION_SENT_BUCKETS, $sent, false );
		}
		self::notify_user( $user, $text );
		if ( $service_id > 0 ) {
			SimpleVPBot_Model_Service::update( (int) $service_id, array( 'last_warn_sent_at' => current_time( 'mysql', 1 ) ) );
		}
		return true;
	}

	/**
	 * @param object $user User.
	 * @param object $svc  Service.
	 * @param int    $remaining_pct Remaining percent.
	 * @return string
	 */
	private static function build_low_traffic_text( $user, $svc, $remaining_pct ) {
		$name = class_exists( 'SimpleVPBot_User_Display' ) ? SimpleVPBot_User_Display::name( $user ) : 'کاربر';
		$def  = "{name} عزیز؛\n\nحجم باقی‌مانده سرویس «{remark}» کم شده است.\n\n📊 حدود {remaining_pct}٪ از حجم کل هنوز مانده است.\n📅 در صورت نیاز، از منوی همان سرویس «افزودن حجم» یا «تمدید» را انتخاب کنید.";
		$tpl  = SimpleVPBot_Texts::get( 'msg.cron_low_traffic', $def );
		return SimpleVPBot_Texts::format(
			$tpl,
			array(
				'name'           => $name,
				'remark'         => (string) ( $svc->remark ?? '' ),
				'remaining_pct'  => (string) (int) $remaining_pct,
			)
		);
	}

	/**
	 * @param object $user User.
	 * @param object $svc  Service.
	 * @param int    $n_ip IP count.
	 * @param int    $lim  Limit.
	 * @param int    $need Threshold.
	 * @return string
	 */
	private static function build_ip_distinct_text( $user, $svc, $n_ip, $lim, $need ) {
		$name = class_exists( 'SimpleVPBot_User_Display' ) ? SimpleVPBot_User_Display::name( $user ) : 'کاربر';
		$tpl  = SimpleVPBot_Texts::get(
			'msg.cron_ip_distinct_warn',
			"⚠️ سرویس «{remark}»\n\n{name} عزیز؛\n\nتعداد آدرس/IP متفاوت ثبت‌شده برای این اشتراک از حد معمول بالاتر رفته است.\n\n📌 حدود {n_ip} IP متمایز ثبت شده\n📌 سقف اسلات: {lim} (آستانه هشدار: حداقل {need} IP)\n\n✋ در صورت نیاز از منوی همان سرویس «افزایش کاربر» را انتخاب کنید."
		);
		return SimpleVPBot_Texts::format(
			$tpl,
			array(
				'name'   => $name,
				'remark' => (string) ( $svc->remark ?? '' ),
				'n_ip'   => (string) (int) $n_ip,
				'lim'    => (string) (int) $lim,
				'need'   => (string) (int) $need,
			)
		);
	}

	/**
	 * @param object $user User.
	 * @param object $svc  Service.
	 * @param int    $days Day offset (negative = after expiry).
	 * @return string
	 */
	private static function build_expiry_text( $user, $svc, $days ) {
		$name = class_exists( 'SimpleVPBot_User_Display' ) ? SimpleVPBot_User_Display::name( $user ) : 'کاربر';
		if ( $days > 0 ) {
			$key = 'msg.cron_expiry_before';
			$def = "{name} عزیز؛\n\nسرویس «{remark}» شما تا {days} روز دیگر منقضی می‌شود.\n\n📅 برای جلوگیری از قطع سرویس، از منوی همان سرویس «تمدید» را بزنید.";
		} elseif ( 0 === $days ) {
			$key = 'msg.cron_expiry_today';
			$def = "{name} عزیز؛\n\nامروز آخرین روز اعتبار سرویس «{remark}» شماست.\n\n📅 برای ادامه بدون وقفه، همین الان «تمدید» را از منوی همان سرویس انتخاب کنید.";
		} else {
			$key = 'msg.cron_expiry_after';
			$def = "{name} عزیز؛\n\nاعتبار سرویس «{remark}» شما {days} روز پیش به پایان رسیده است.\n\n📅 برای فعال‌سازی مجدد، از منوی همان سرویس «تمدید» را بزنید یا از بخش خرید اقدام کنید.";
		}
		$tpl = SimpleVPBot_Texts::get( $key, $def );
		return SimpleVPBot_Texts::format(
			$tpl,
			array(
				'name'   => $name,
				'remark' => (string) ( $svc->remark ?? '' ),
				'days'   => (string) abs( (int) $days ),
			)
		);
	}

	/**
	 * @param object $user User.
	 * @param object $svc  Service.
	 * @return string
	 */
	private static function build_after_expired_text( $user, $svc ) {
		$name = class_exists( 'SimpleVPBot_User_Display' ) ? SimpleVPBot_User_Display::name( $user ) : 'کاربر';
		$tpl  = SimpleVPBot_Texts::get(
			'msg.cron_after_expired',
			"{name} عزیز؛\n\nسرویس «{remark}» منقضی شده و دیگر قابل استفاده نیست.\n\n📅 برای ادامه، از بخش خرید سرویس جدید بگیرید یا با پشتیبانی تماس بگیرید."
		);
		return SimpleVPBot_Texts::format(
			$tpl,
			array(
				'name'   => $name,
				'remark' => (string) ( $svc->remark ?? '' ),
			)
		);
	}

	/**
	 * Two-run hysteresis: same (n_ip, threshold, lim) on consecutive cron passes before sending.
	 *
	 * @param int $service_id Service id.
	 * @param int $n_ip Distinct IP count.
	 * @param int $need_e Effective threshold.
	 * @param int $lim Client limit.
	 * @return bool True when notification may be sent.
	 */
	private static function ip_alert_hysteresis_allow_send( $service_id, $n_ip, $need_e, $lim ) {
		if ( ! (bool) SimpleVPBot_Settings::get( 'alert_ip_warn_hysteresis', true ) ) {
			return true;
		}
		$sid   = (int) $service_id;
		$state = (string) (int) $n_ip . '|' . (string) (int) $need_e . '|' . (string) (int) $lim;
		$key   = 'svp_ip_hyst_' . $sid;
		$prev  = get_transient( $key );
		if ( is_string( $prev ) && $prev === $state ) {
			delete_transient( $key );
			return true;
		}
		set_transient( $key, $state, 45 * MINUTE_IN_SECONDS );
		return false;
	}

	/**
	 * Optional per-service cooldown after an IP-cap warning was sent.
	 *
	 * @param int $service_id Service id.
	 * @return bool True when still in cooldown (skip send).
	 */
	private static function ip_alert_cooldown_active( $service_id ) {
		$cd = max( 0, (int) SimpleVPBot_Settings::get( 'alert_ip_warn_cooldown_minutes', 0 ) );
		if ( $cd < 1 ) {
			return false;
		}
		return (bool) get_transient( 'svp_ip_cdwarn_' . (int) $service_id );
	}

	/**
	 * Start cooldown transient after a send.
	 *
	 * @param int $service_id Service id.
	 */
	private static function ip_alert_cooldown_mark( $service_id ) {
		$cd = max( 0, (int) SimpleVPBot_Settings::get( 'alert_ip_warn_cooldown_minutes', 0 ) );
		if ( $cd >= 1 ) {
			set_transient( 'svp_ip_cdwarn_' . (int) $service_id, time(), $cd * MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Pull latest traffics from panel if local is zero/stale.
	 *
	 * @param object $svc Service.
	 */
	private static function sync_traffic_if_stale( $svc ) {
		SimpleVPBot_Xui_Client::run_with_panel(
			max( 1, (int) ( $svc->panel_id ?? 1 ) ),
			function () use ( $svc ) {
				if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
					return;
				}
				$tr  = SimpleVPBot_Xui_Client::get_client_traffics( (string) $svc->email );
				$obj = is_array( $tr ) && isset( $tr['obj'] ) && is_array( $tr['obj'] ) ? $tr['obj'] : null;
				if ( ! $obj ) {
					return;
				}
				$up   = isset( $obj['up'] ) ? (int) $obj['up'] : 0;
				$down = isset( $obj['down'] ) ? (int) $obj['down'] : 0;
				SimpleVPBot_Model_Service::update( (int) $svc->id, array( 'used_traffic' => $up + $down ) );
			}
		);
	}

	/**
	 * Pull usage from L2TP server if template is configured.
	 *
	 * @param object $svc Service row.
	 */
	private static function sync_l2tp_usage( $svc ) {
		if ( empty( $svc->l2tp_server_id ) ) {
			return;
		}
		SimpleVPBot_L2TP_Provisioner::refresh_usage( $svc );
	}

	/**
	 * Send to both bots if linked.
	 *
	 * @param object $user User row.
	 * @param string $text Text.
	 */
	private static function notify_user( $user, $text ) {
		if ( class_exists( 'SimpleVPBot_User_Notify' ) ) {
			SimpleVPBot_User_Notify::send_to_user( $user, $text );
			return;
		}
		if ( class_exists( 'SimpleVPBot_Bot_Runtime' ) && ! empty( $user->tg_user_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( 'telegram', (int) $user->tg_user_id, $text );
		}
		if ( class_exists( 'SimpleVPBot_Bot_Runtime' ) && ! empty( $user->bale_user_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( 'bale', (int) $user->bale_user_id, $text );
		}
	}
}
