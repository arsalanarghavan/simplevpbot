<?php
/**
 * Per-service notification preferences (volume / expiry / concurrent user cap) + UI helpers.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Service_Alerts
 */
class SimpleVPBot_Service_Alerts {

	/**
	 * User-facing paragraph separator (emoji only, no dash characters).
	 *
	 * @return string
	 */
	public static function text_sep() {
		return "\n➖➖➖➖➖➖➖➖\n";
	}

	/**
	 * Decode optional per-service JSON overrides (keys: expiry_days, low_traffic_pct, ip_fill_pct).
	 *
	 * @param object $svc Service row.
	 * @return array<string, mixed>
	 */
	private static function parse_alert_schedule( $svc ) {
		if ( ! is_object( $svc ) || ! isset( $svc->alert_schedule_json ) ) {
			return array();
		}
		$raw = trim( (string) $svc->alert_schedule_json );
		if ( '' === $raw ) {
			return array();
		}
		$j = json_decode( $raw, true );
		return is_array( $j ) ? $j : array();
	}

	/**
	 * Global master switch: low-traffic alerts (still require per-service toggle).
	 *
	 * @return bool
	 */
	public static function global_notify_volume_on() {
		return (bool) SimpleVPBot_Settings::get( 'notify_user_volume', true );
	}

	/**
	 * Global master switch: expiry-day and post-expiry pings.
	 *
	 * @return bool
	 */
	public static function global_notify_expiry_on() {
		return (bool) SimpleVPBot_Settings::get( 'notify_user_expiry', true );
	}

	/**
	 * Global master switch: concurrent-user cap alerts.
	 *
	 * @return bool
	 */
	public static function global_notify_users_on() {
		return (bool) SimpleVPBot_Settings::get( 'notify_user_users', true );
	}

	/**
	 * Global master switch: message when service already expired.
	 *
	 * @return bool
	 */
	public static function global_notify_after_expire_on() {
		return (bool) SimpleVPBot_Settings::get( 'notify_user_after_expire', true );
	}

	/**
	 * Whether any alert channel is enabled for this service.
	 *
	 * @param object $svc Service row.
	 * @return bool
	 */
	public static function any_enabled( $svc ) {
		return self::volume_enabled( $svc ) || self::expiry_enabled( $svc ) || self::users_enabled( $svc );
	}

	/**
	 * Volume (low traffic) alerts on.
	 *
	 * @param object $svc Service row.
	 * @return bool
	 */
	public static function volume_enabled( $svc ) {
		if ( isset( $svc->alerts_volume ) ) {
			return (int) $svc->alerts_volume === 1;
		}
		return (int) $svc->alerts_enabled === 1;
	}

	/**
	 * Expiry-related alerts on.
	 *
	 * @param object $svc Service row.
	 * @return bool
	 */
	public static function expiry_enabled( $svc ) {
		if ( isset( $svc->alerts_expiry ) ) {
			return (int) $svc->alerts_expiry === 1;
		}
		return (int) $svc->alerts_enabled === 1;
	}

	/**
	 * Concurrent-user-cap alerts on (Xray services only in UI).
	 *
	 * @param object $svc Service row.
	 * @return bool
	 */
	public static function users_enabled( $svc ) {
		if ( isset( $svc->alerts_users ) ) {
			return (int) $svc->alerts_users === 1;
		}
		return (int) $svc->alerts_enabled === 1;
	}

	/**
	 * Remaining-volume percent threshold: warn when remaining % is at or below this value.
	 *
	 * @param object $svc Service row.
	 * @return int 1-99
	 */
	public static function effective_low_traffic_pct( $svc ) {
		$sched = self::parse_alert_schedule( $svc );
		if ( isset( $sched['low_traffic_pct'] ) ) {
			$p = (int) $sched['low_traffic_pct'];
			if ( $p >= 1 && $p <= 99 ) {
				return $p;
			}
		}
		if ( isset( $svc->alert_low_pct ) && null !== $svc->alert_low_pct && '' !== (string) $svc->alert_low_pct ) {
			$p = (int) $svc->alert_low_pct;
			if ( $p >= 1 && $p <= 99 ) {
				return $p;
			}
		}
		$g = (int) SimpleVPBot_Settings::get( 'notify_low_traffic_percent', 10 );
		return max( 1, min( 99, $g ) );
	}

	/**
	 * Days-before-expiry list to ping (integers, may include 0 for expiry day).
	 *
	 * @param object $svc Service row.
	 * @return array<int, int>
	 */
	public static function effective_expiry_days( $svc ) {
		$sched = self::parse_alert_schedule( $svc );
		if ( isset( $sched['expiry_days'] ) && is_array( $sched['expiry_days'] ) ) {
			$out = array();
			foreach ( $sched['expiry_days'] as $x ) {
				$d = (int) $x;
				if ( $d >= -3650 && $d <= 3650 ) {
					$out[] = $d;
				}
			}
			$out = array_values( array_unique( $out ) );
			if ( ! empty( $out ) ) {
				return $out;
			}
		}
		$raw = isset( $svc->alert_expiry_days ) ? trim( (string) $svc->alert_expiry_days ) : '';
		if ( '' !== $raw ) {
			$out = array();
			foreach ( explode( ',', $raw ) as $part ) {
				$d = (int) trim( $part );
				if ( $d >= -3650 && $d <= 3650 ) {
					$out[] = $d;
				}
			}
			$out = array_values( array_unique( $out ) );
			if ( ! empty( $out ) ) {
				return $out;
			}
		}
		$def = (array) SimpleVPBot_Settings::get( 'notify_expiry_days', array( 3, 1 ) );
		return array_values(
			array_filter(
				array_map(
					static function ( $x ) {
						$n = (int) $x;
						return ( $n >= -3650 && $n <= 3650 ) ? $n : null;
					},
					$def
				)
			)
		);
	}

	/**
	 * When active devices reach this percent of the user cap, send warning (Xray).
	 *
	 * @param object $svc Service row.
	 * @return int 50-100
	 */
	public static function effective_ip_fill_pct( $svc ) {
		$sched = self::parse_alert_schedule( $svc );
		if ( isset( $sched['ip_fill_pct'] ) ) {
			$p = (int) $sched['ip_fill_pct'];
			if ( $p >= 50 && $p <= 100 ) {
				return $p;
			}
		}
		if ( isset( $svc->alert_ip_fill_pct ) && null !== $svc->alert_ip_fill_pct && '' !== (string) $svc->alert_ip_fill_pct ) {
			$p = (int) $svc->alert_ip_fill_pct;
			if ( $p >= 50 && $p <= 100 ) {
				return $p;
			}
		}
		return 90;
	}

	/**
	 * Read concurrent user cap from remote client JSON (limitIp field).
	 *
	 * @param int    $inbound_id Inbound id.
	 * @param string $email      Client email.
	 * @return int
	 */
	public static function client_limit_ip( $inbound_id, $email, $panel_id = 1 ) {
		$pid = max( 1, (int) $panel_id );
		return SimpleVPBot_Xui_Client::run_with_panel(
			$pid,
			function () use ( $inbound_id, $email ) {
				if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
					return 0;
				}
				$inbound = SimpleVPBot_Xui_Client::inbound_get( (int) $inbound_id );
				if ( ! $inbound ) {
					return 0;
				}
				$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
				$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
				if ( ! is_array( $dec ) || empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
					return 0;
				}
				foreach ( $dec['clients'] as $cl ) {
					if ( is_array( $cl ) && isset( $cl['email'] ) && (string) $cl['email'] === (string) $email ) {
						return (int) ( $cl['limitIp'] ?? 0 );
					}
				}
				return 0;
			}
		);
	}

	/**
	 * Count distinct active connection endpoints for cap checks.
	 *
	 * @param string $email Client email.
	 * @return int
	 */
	public static function client_ip_count( $email, $panel_id = 1 ) {
		$pid = max( 1, (int) $panel_id );
		return SimpleVPBot_Xui_Client::run_with_panel(
			$pid,
			function () use ( $email ) {
				return self::client_ip_count_on_bound( (string) $email );
			}
		);
	}

	/**
	 * @param string $email Client email.
	 * @return int
	 */
	private static function client_ip_count_on_bound( $email ) {
		if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
			return 0;
		}
		$j = SimpleVPBot_Xui_Client::client_ips( (string) $email );
		$obj = is_array( $j ) && isset( $j['obj'] ) ? $j['obj'] : null;
		$ips = array();
		if ( is_string( $obj ) && '' !== $obj && 'No IP Record' !== $obj ) {
			$decoded = json_decode( $obj, true );
			$ips     = is_array( $decoded ) ? $decoded : preg_split( '/[\s,]+/', $obj );
		} elseif ( is_array( $obj ) ) {
			$ips = $obj;
		}
		$ips = array_filter( array_map( 'trim', (array) $ips ) );
		return count( array_unique( $ips ) );
	}

	/**
	 * Intro for main alerts panel (what + what to do).
	 *
	 * @param bool $is_l2tp L2TP service.
	 * @return string
	 */
	public static function main_panel_intro( $is_l2tp ) {
		$sep = self::text_sep();
		$t   = "🔔 هشدار یعنی چی؟\n";
		$t  .= "📣 یعنی ربات برای همین سرویس به شما در تلگرام یا بله یک پیام کوتاه می‌فرستد.\n";
		$t  .= $sep;
		$t  .= "📊 حجم\n";
		$t  .= "🧒 وقتی حجم باقی‌مانده‌ات کم می‌شود و به عددی که خودت تعیین کردی رسید، ربات بهت خبر می‌دهد.\n";
		$t  .= $sep;
		$t  .= "⏰ زمان\n";
		$t  .= "🧒 وقتی به روزهایی که گفتی نزدیک انقضا شدی، ربات یک بار خبر می‌دهد.\n";
		if ( ! $is_l2tp ) {
			$t .= $sep;
			$t .= "👥 محدودیت کاربر\n";
			$t .= "🧒 یعنی چند نفر هم‌زمان می‌توانند از این سرویس استفاده کنند. وقتی نزدیک سقف همان عدد شدی، ربات هشدار می‌دهد.\n";
		}
		$t .= $sep;
		$t .= "✋ کار تو چیه؟\n";
		$t .= "🔘 هر ردیف دو دکمه دارد. اگر هشدار روشن است روی دکمه «خاموش کردن» می‌زنی و برعکس.\n";
		$t .= "🔘 «آستانه‌ها» یعنی بگو دقیقا از کجا به بعد برایت پیام بفرستیم.";
		return $t;
	}

	/**
	 * Intro for thresholds submenu.
	 *
	 * @param bool $is_l2tp L2TP.
	 * @return string
	 */
	public static function thresholds_intro( $is_l2tp ) {
		$sep = self::text_sep();
		$t   = "⚙️ آستانه یعنی چی؟\n";
		$t  .= "🧒 یعنی از کجا به بعد ربات برایت پیام بفرستد.\n";
		$t  .= $sep;
		$t  .= "📉 حجم\n";
		$t  .= "🧒 یک عدد ۱ تا ۹۹ بده. مثلا ۲۰ یعنی وقتی حدود ۲۰ درصد از حجمت مانده بود بهت خبر بدهد.\n";
		$t  .= $sep;
		$t  .= "📅 انقضا\n";
		$t  .= "🧒 چند عدد با کامای انگلیسی بفرست مثل ۳,۱,۰ . یعنی سه روز قبل و یک روز قبل و روز خود انقضا. عدد منفی یعنی چند روز بعد از انقضا (مثلاً -۱ یعنی یک روز بعد).\n";
		if ( ! $is_l2tp ) {
			$t .= $sep;
			$t .= "👥 محدودیت کاربر\n";
			$t .= "🧒 یک عدد ۵۰ تا ۱۰۰ بده. یعنی وقتی تعداد استفاده‌کننده‌های هم‌زمان به این درصد از سقف عددی که برایت ثبت شده رسید، ربات هشدار بدهد.\n";
		}
		$t .= $sep;
		$t .= "✋ کار تو\n";
		$t .= "🔘 یک دکمه را بزن بعد فقط همان عدد یا اعداد را در چت بفرست.\n";
		$t .= "🔘 برای برگشت دکمه «بازگشت به هشدارها» را بزن.";
		return $t;
	}

	/**
	 * Summary lines for current thresholds (user-facing, emoji per line).
	 *
	 * @param object $svc Service.
	 * @return string
	 */
	public static function thresholds_summary_line( $svc ) {
		$pct  = self::effective_low_traffic_pct( $svc );
		$days = self::effective_expiry_days( $svc );
		$ip   = self::effective_ip_fill_pct( $svc );
		$out  = '📉 حجم: وقتی تا ' . $pct . "٪ از حجم مانده باشد\n";
		$out .= '📅 انقضا: روزهای ' . implode( '، ', $days ) . "\n";
		$out .= '👥 محدودیت کاربر: از ' . $ip . '٪ از سقف به بالا';
		return $out;
	}

	/**
	 * Inline rows: main alerts panel.
	 *
	 * @param object $svc Service row (fresh).
	 * @return array<int, array<int, array<string, string>>>
	 */
	public static function main_panel_rows( $svc ) {
		$sid   = (int) $svc->id;
		$vol   = self::volume_enabled( $svc );
		$exp   = self::expiry_enabled( $svc );
		$usr   = self::users_enabled( $svc );
		$is_l2 = SimpleVPBot_Model_Service::is_l2tp( $svc );
		$rows  = array();
		$rows[] = array(
			array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( '📊 حجم: ' . ( $vol ? 'خاموش کردن' : 'روشن کردن' ) ),
				'callback_data' => 'svc:a1:' . $sid,
			),
			array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( '⏰ زمان: ' . ( $exp ? 'خاموش کردن' : 'روشن کردن' ) ),
				'callback_data' => 'svc:a2:' . $sid,
			),
		);
		if ( ! $is_l2 ) {
			$rows[] = array(
				array(
					'text'          => SimpleVPBot_Keyboards::glass_button_text( '👥 محدودیت کاربر: ' . ( $usr ? 'خاموش کردن' : 'روشن کردن' ) ),
					'callback_data' => 'svc:a3:' . $sid,
				),
				array(
					'text'          => SimpleVPBot_Keyboards::glass_button_text( '⚙️ آستانه‌ها' ),
					'callback_data' => 'svc:a0:' . $sid,
				),
			);
		} else {
			$rows[] = array(
				array(
					'text'          => SimpleVPBot_Keyboards::glass_button_text( '⚙️ آستانه‌ها' ),
					'callback_data' => 'svc:a0:' . $sid,
				),
			);
		}
		$rows[] = array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '⬅️ بازگشت به سرویس' ), 'callback_data' => 'svc:m:' . $sid ) );
		return $rows;
	}

	/**
	 * Inline rows: thresholds submenu.
	 *
	 * @param int  $sid   Service id.
	 * @param bool $is_l2 L2TP.
	 * @return array<int, array<int, array<string, string>>>
	 */
	public static function thresholds_rows( $sid, $is_l2 ) {
		$rows   = array();
		$rows[] = array(
			array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '📉 آستانهٔ حجم' ), 'callback_data' => 'svc:a5:' . $sid ),
			array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '📅 روزهای انقضا' ), 'callback_data' => 'svc:a6:' . $sid ),
		);
		if ( ! $is_l2 ) {
			$rows[] = array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '👥 آستانهٔ محدودیت کاربر' ), 'callback_data' => 'svc:a7:' . $sid ) );
		}
		$rows[] = array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '↩️ بازگشت به هشدارها' ), 'callback_data' => 'svc:a8:' . $sid ) );
		return $rows;
	}
}
