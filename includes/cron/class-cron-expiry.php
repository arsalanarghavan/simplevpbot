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
		$sent = (array) get_option( self::OPTION_SENT_BUCKETS, array() );
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

			$total = (int) $svc->total_traffic;
			$used  = (int) $svc->used_traffic;
			$pct_th = SimpleVPBot_Service_Alerts::effective_low_traffic_pct( $svc );
			if ( SimpleVPBot_Service_Alerts::global_notify_volume_on() && SimpleVPBot_Service_Alerts::volume_enabled( $svc ) && $total > 0 ) {
				$remaining_pct = (int) floor( ( ( $total - $used ) * 100 ) / $total );
				if ( $remaining_pct <= $pct_th && $remaining_pct >= 0 ) {
					$bucket_key = 'svc' . (int) $svc->id . ':low:' . $pct_th;
					if ( empty( $sent[ $bucket_key ] ) ) {
						self::notify_user(
							$user,
							"⚠️ حجم سرویس «" . (string) $svc->remark . "»\n"
							. "🧒 یعنی از حجمت خیلی کم مانده.\n"
							. "📊 حدود {$remaining_pct}٪ از حجم هنوز مانده."
						);
						$sent[ $bucket_key ] = time();
						SimpleVPBot_Model_Service::update( (int) $svc->id, array( 'last_warn_sent_at' => current_time( 'mysql', 1 ) ) );
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
							if ( empty( $sent[ $bucket_key ] ) ) {
								$tpl = SimpleVPBot_Texts::get(
									'msg.cron_ip_distinct_warn',
									"⚠️ سرویس «{remark}»\n🧒 یعنی چی؟ تعداد آدرس/IP متفاوتی که پنل برای این اشتراک ثبت کرده بالا رفته است.\n📌 الان حدود {n_ip} IP متمایز ثبت شده است.\n📌 سقف اسلات این اشتراک {lim} است (آستانهٔ هشدار حداقل {need} IP).\n✋ اگر لازم دارید از منوی همان سرویس «افزایش کاربر» را بزنید."
								);
								$body = SimpleVPBot_Texts::format(
									$tpl,
									array(
										'remark' => (string) $svc->remark,
										'n_ip'   => (string) $n_ip,
										'lim'    => (string) $lim,
										'need'   => (string) $need_e,
									)
								);
								self::notify_user( $user, $body );
								$sent[ $bucket_key ] = time();
								SimpleVPBot_Model_Service::update( (int) $svc->id, array( 'last_warn_sent_at' => current_time( 'mysql', 1 ) ) );
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
					if ( in_array( $days, $warn_days, true ) ) {
						$bucket_key = 'svc' . (int) $svc->id . ':expd:' . $days;
						if ( empty( $sent[ $bucket_key ] ) ) {
							$when = $days > 0
								? sprintf(
									/* translators: %d: days until expiry */
									__( '🧒 یعنی چی؟ تا تمام شدن وقتش %d روز دیگر مانده.', 'simplevpbot' ),
									$days
								)
								: ( 0 === $days
									? __( '🧒 یعنی چی؟ امروز آخرین روز اعتبار این سرویس است.', 'simplevpbot' )
									: sprintf(
										/* translators: %d: days after expiry (positive English count) */
										__( '🧒 یعنی چی؟ از تاریخ انقضا %d روز گذشته است.', 'simplevpbot' ),
										abs( $days )
									)
								);
							self::notify_user(
								$user,
								"⏳ سرویس «" . (string) $svc->remark . "»\n"
								. $when . "\n"
								. __( '✋ اگر خواستی زودتر تمدید کن از منوی همان سرویس دکمه تمدید را بزن.', 'simplevpbot' )
							);
							$sent[ $bucket_key ] = time();
							SimpleVPBot_Model_Service::update( (int) $svc->id, array( 'last_warn_sent_at' => current_time( 'mysql', 1 ) ) );
						}
					}
					if ( $days < 0 && SimpleVPBot_Service_Alerts::global_notify_after_expire_on() ) {
						$bucket_key = 'svc' . (int) $svc->id . ':expired:' . gmdate( 'Y-m-d', $exp );
						if ( empty( $sent[ $bucket_key ] ) ) {
							self::notify_user(
								$user,
								"⛔ سرویس «" . (string) $svc->remark . "»\n"
								. "🧒 یعنی چی؟ وقت استفاده‌اش تمام شده.\n"
								. "✋ برای ادامه از منوی خرید یا پشتیبانی کمک بگیر."
							);
							$sent[ $bucket_key ] = time();
						}
					}
				}
			}
		}

		$sent = self::prune_sent_buckets( $sent );
		update_option( self::OPTION_SENT_BUCKETS, $sent, false );
	}

	/**
	 * Remove bucket entries older than 45 days to keep option bounded.
	 *
	 * @param array<string, int> $sent Sent buckets.
	 * @return array<string, int>
	 */
	private static function prune_sent_buckets( array $sent ) {
		$cutoff = time() - 45 * DAY_IN_SECONDS;
		foreach ( $sent as $k => $ts ) {
			if ( (int) $ts < $cutoff ) {
				unset( $sent[ $k ] );
			}
		}
		return $sent;
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
		if ( ! (int) $svc->total_traffic ) {
			return;
		}
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
