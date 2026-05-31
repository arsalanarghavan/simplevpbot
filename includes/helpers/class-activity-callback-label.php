<?php
/**
 * Resolve bot callback_data and activity rows into human-readable labels for dashboard activity log.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Activity_Callback_Label
 */
class SimpleVPBot_Activity_Callback_Label {

	/**
	 * Map svc:* action segment to bot text key.
	 *
	 * @var array<string, string>
	 */
	private static $svc_action_keys = array(
		'm'  => 'btn.svc.back_manage',
		'p'  => 'btn.svc.show_connection',
		'us' => 'btn.svc.show_usage',
		'k'  => 'btn.svc.change_password',
		'r'  => 'btn.svc.renew',
		'ar' => 'btn.svc.auto_renew',
		'al' => 'btn.svc.alerts',
		'rn' => 'btn.svc.rename',
		'f'  => 'btn.svc.faq',
		'su' => 'btn.svc.support',
		'tx' => 'btn.svc.transfer',
		'b'  => 'btn.common.back',
		'l'  => 'btn.svc.config_qr',
		'u'  => 'btn.svc.update_servers',
		'v'  => 'btn.svc.add_volume',
		'sl' => 'btn.svc.add_users',
		'n'  => 'btn.svc.panel_note',
		'ip' => 'btn.svc.active_connections',
		'w'  => 'btn.svc.config',
	);

	/**
	 * Resolve callback_data for activity summary.
	 *
	 * @param string      $callback_data Raw callback_data.
	 * @param string|null $locale        fa|en or null for site default.
	 * @return string Empty if unknown.
	 */
	public static function resolve( $callback_data, $locale = null ) {
		$cb = trim( (string) $callback_data );
		if ( '' === $cb ) {
			return '';
		}
		$loc = null === $locale ? SimpleVPBot_Texts::site_default_locale() : SimpleVPBot_Model_Text::normalize_locale( $locale );
		$parts = explode( ':', $cb );
		$prefix = isset( $parts[0] ) ? (string) $parts[0] : '';

		if ( 'buy' === $prefix ) {
			return self::resolve_buy( $parts, $loc );
		}
		if ( 'svc' === $prefix ) {
			return self::resolve_svc( $parts, $loc );
		}
		if ( 'reg' === $prefix && isset( $parts[1] ) && 'a' === $parts[1] ) {
			return self::text( 'btn.reg.approve', $loc, __( 'Approve registration', 'simplevpbot' ) );
		}
		if ( 'adm' === $prefix ) {
			return self::resolve_adm( $parts, $loc );
		}
		return '';
	}

	/**
	 * @param array<int, string> $parts Callback segments.
	 * @param string             $loc   Locale.
	 * @return string
	 */
	private static function resolve_buy( array $parts, $loc ) {
		$action = isset( $parts[1] ) ? (string) $parts[1] : '';
		if ( 'g' === $action && isset( $parts[2] ) && class_exists( 'SimpleVPBot_Model_Plan_Category' ) ) {
			$cat = SimpleVPBot_Model_Plan_Category::find( (int) $parts[2] );
			if ( $cat && ! empty( $cat->label ) ) {
				return (string) $cat->label;
			}
		}
		if ( 'p' === $action && isset( $parts[2] ) && class_exists( 'SimpleVPBot_Model_Plan' ) ) {
			$plan = SimpleVPBot_Model_Plan::find( (int) $parts[2] );
			if ( $plan && ! empty( $plan->name ) ) {
				return (string) $plan->name;
			}
		}
		if ( 'pm' === $action && isset( $parts[2], $parts[3] ) && class_exists( 'SimpleVPBot_Model_Card' ) ) {
			$card = SimpleVPBot_Model_Card::find( (int) $parts[3] );
			if ( $card ) {
				$suffix = trim( (string) ( $card->card_suffix ?? $card->suffix ?? '' ) );
				$holder = trim( (string) ( $card->holder_name ?? '' ) );
				$tpl    = self::text( 'btn.pay.card_label', $loc, '💳 {suffix} · {holder}' );
				return SimpleVPBot_Texts::format( $tpl, array( 'suffix' => $suffix, 'holder' => $holder ) );
			}
		}
		if ( 'cf' === $action ) {
			return self::text( 'btn.pay.confirm_buy', $loc, 'Confirm purchase' );
		}
		if ( 'sw' === $action ) {
			return self::text( 'btn.pay.site_wallet', $loc, 'Pay with wallet' );
		}
		if ( 'bw' === $action ) {
			return self::text( 'btn.pay.bale_wallet', $loc, 'Pay with Bale wallet' );
		}
		if ( 'cd' === $action ) {
			return self::text( 'btn.pay.discount_code', $loc, 'Discount code' );
		}
		if ( 'c' === $action ) {
			return self::text( 'btn.pay.cancel', $loc, 'Cancel' );
		}
		return '';
	}

	/**
	 * @param array<int, string> $parts Callback segments.
	 * @param string             $loc   Locale.
	 * @return string
	 */
	private static function resolve_svc( array $parts, $loc ) {
		$action = isset( $parts[1] ) ? (string) $parts[1] : '';
		if ( '' === $action ) {
			return '';
		}
		$key = isset( self::$svc_action_keys[ $action ] ) ? self::$svc_action_keys[ $action ] : '';
		if ( '' === $key ) {
			return '';
		}
		$label = self::text( $key, $loc, $action );
		if ( 'w' === $action && isset( $parts[3] ) ) {
			return SimpleVPBot_Texts::format( self::text( 'btn.svc.config_n', $loc, 'Config {n}' ), array( 'n' => (string) ( (int) $parts[3] + 1 ) ) );
		}
		if ( isset( $parts[2] ) && is_numeric( $parts[2] ) && (int) $parts[2] > 0 ) {
			return $label . ' #' . (int) $parts[2];
		}
		return $label;
	}

	/**
	 * @param array<int, string> $parts Callback segments.
	 * @param string             $loc   Locale.
	 * @return string
	 */
	private static function resolve_adm( array $parts, $loc ) {
		$action = isset( $parts[1] ) ? (string) $parts[1] : '';
		$map    = array(
			'ui'   => 'btn.adm.user_info',
			'umsg' => 'btn.adm.message_user',
			'wbp'  => 'btn.adm.wallet_plus',
			'wbm'  => 'btn.adm.wallet_minus',
			'rcp'  => 'btn.adm.receipts',
			'aq'   => 'btn.adm.approved_queue',
			'pq'   => 'btn.adm.pending_queue',
			'rq'   => 'btn.adm.rejected_queue',
			'rr'   => 'btn.adm.reject_user',
		);
		if ( ! isset( $map[ $action ] ) ) {
			return 'Admin: ' . $action;
		}
		return self::text( $map[ $action ], $loc, 'Admin action' );
	}

	/**
	 * @param string $key     Text key.
	 * @param string $loc     Locale.
	 * @param string $default Default label.
	 * @return string
	 */
	private static function text( $key, $loc, $default ) {
		if ( ! class_exists( 'SimpleVPBot_Texts' ) ) {
			return $default;
		}
		$v = trim( SimpleVPBot_Texts::get( $key, $default, $loc ) );
		return '' !== $v ? $v : $default;
	}

	/**
	 * @param string|null $locale fa|en.
	 * @return bool
	 */
	private static function is_fa( $locale ) {
		$loc = null === $locale ? ( class_exists( 'SimpleVPBot_Texts' ) ? SimpleVPBot_Texts::site_default_locale() : 'fa' ) : SimpleVPBot_Model_Text::normalize_locale( $locale );
		return 'fa' === $loc;
	}

	/**
	 * @param array<string, mixed> $row Activity row.
	 * @return string
	 */
	private static function actor_label( array $row ) {
		$channel = sanitize_key( (string) ( $row['channel'] ?? '' ) );
		$awp     = (int) ( $row['actor_wp_user_id'] ?? 0 );
		$asvp    = (int) ( $row['actor_svp_user_id'] ?? 0 );
		if ( 'rest' === $channel ) {
			if ( $awp > 0 ) {
				$u = get_userdata( $awp );
				if ( $u && ! empty( $u->display_name ) ) {
					return (string) $u->display_name;
				}
			}
			if ( $asvp > 0 && class_exists( 'SimpleVPBot_Model_User' ) ) {
				$ur = SimpleVPBot_Model_User::find( $asvp );
				if ( $ur ) {
					return SimpleVPBot_Model_User::label( $ur );
				}
			}
			return '';
		}
		if ( in_array( $channel, array( 'telegram', 'bale' ), true ) ) {
			return '';
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $pl Payload.
	 * @return string
	 */
	private static function service_ref( array $pl ) {
		$sid = (int) ( $pl['service_id'] ?? 0 );
		if ( $sid < 1 || ! class_exists( 'SimpleVPBot_Model_Service' ) ) {
			return $sid > 0 ? '#' . $sid : '';
		}
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc ) {
			return '#' . $sid;
		}
		$remark = trim( (string) ( $svc->remark ?? '' ) );
		if ( '' !== $remark ) {
			return $remark . ' #' . $sid;
		}
		$email = trim( (string) ( $svc->email ?? '' ) );
		if ( '' !== $email ) {
			return $email . ' #' . $sid;
		}
		return '#' . $sid;
	}

	/**
	 * @param string               $mode Payment mode key.
	 * @param string|null          $locale Locale.
	 * @return string
	 */
	private static function mode_label( $mode, $locale ) {
		$m = sanitize_key( (string) $mode );
		$fa = self::is_fa( $locale );
		if ( 'wallet' === $m ) {
			return $fa ? 'کیف پول' : 'wallet';
		}
		if ( 'invoice' === $m ) {
			return $fa ? 'فاکتور' : 'invoice';
		}
		if ( 'free' === $m ) {
			return $fa ? 'رایگان' : 'free';
		}
		return $m;
	}

	/**
	 * @param string               $role Role key.
	 * @param string|null          $locale Locale.
	 * @return string
	 */
	private static function role_label( $role, $locale ) {
		$r  = sanitize_key( (string) $role );
		$fa = self::is_fa( $locale );
		if ( 'admin' === $r ) {
			return $fa ? 'مدیر' : 'admin';
		}
		if ( 'reseller' === $r ) {
			return $fa ? 'نماینده' : 'reseller';
		}
		return $fa ? 'کاربر' : 'user';
	}

	/**
	 * Build summary_display for one activity row.
	 *
	 * @param array<string, mixed> $row    Activity row with event_type, payload, channel.
	 * @param string|null          $locale fa|en.
	 * @return string
	 */
	public static function activity_summary_display( array $row, $locale = null ) {
		$ev  = sanitize_key( (string) ( $row['event_type'] ?? '' ) );
		$pl  = isset( $row['payload'] ) && is_array( $row['payload'] ) ? $row['payload'] : array();
		$fa  = self::is_fa( $locale );
		$admin = self::actor_label( $row );
		$svc   = self::service_ref( $pl );

		if ( 'callback_query' === $ev ) {
			$cb       = isset( $pl['callback_data'] ) ? (string) $pl['callback_data'] : '';
			$resolved = self::resolve( $cb, $locale );
			if ( '' !== $resolved ) {
				return $fa
					? sprintf( 'کاربر دکمه «%s» را فشرد', $resolved )
					: sprintf( 'User tapped «%s»', $resolved );
			}
			if ( '' !== $cb ) {
				return $fa
					? sprintf( 'کاربر دکمه شیشه‌ای را فشرد: %s', $cb )
					: sprintf( 'User tapped inline button: %s', $cb );
			}
		}

		if ( 'command' === $ev ) {
			$cmd = isset( $pl['command'] ) ? (string) $pl['command'] : '';
			if ( '' !== $cmd ) {
				return $fa
					? sprintf( 'کاربر دستور %s را ارسال کرد', $cmd )
					: sprintf( 'User sent command %s', $cmd );
			}
		}

		if ( 'message' === $ev ) {
			$preview = isset( $pl['text_preview'] ) ? trim( (string) $pl['text_preview'] ) : '';
			if ( '' !== $preview ) {
				return $fa
					? sprintf( 'کاربر پیام فرستاد: %s', $preview )
					: sprintf( 'User sent message: %s', $preview );
			}
			return $fa ? 'کاربر پیام فرستاد' : 'User sent a message';
		}

		if ( 'rest' === sanitize_key( (string) ( $row['channel'] ?? '' ) ) && '' !== $admin ) {
			$prefix = $fa ? sprintf( 'مدیر %s', $admin ) : sprintf( 'Admin %s', $admin );

			switch ( $ev ) {
				case 'balance_delta':
					$delta = isset( $pl['delta'] ) ? (float) $pl['delta'] : 0.0;
					$after = isset( $pl['balance_after'] ) ? (float) $pl['balance_after'] : 0.0;
					return $fa
						? sprintf( '%s موجودی را %s تومان تغییر داد (مانده: %s)', $prefix, $delta, $after )
						: sprintf( '%s changed balance by %s (after: %s)', $prefix, $delta, $after );
				case 'service_create':
					$pid = (int) ( $pl['plan_id'] ?? 0 );
					$sid = (int) ( $pl['service_id'] ?? 0 );
					return $fa
						? sprintf( '%s سرویس #%d ایجاد کرد (پلن #%d، %s)', $prefix, $sid, $pid, self::mode_label( (string) ( $pl['mode'] ?? '' ), $locale ) )
						: sprintf( '%s created service #%d (plan #%d, %s)', $prefix, $sid, $pid, self::mode_label( (string) ( $pl['mode'] ?? '' ), $locale ) );
				case 'service_renew':
					return $fa
						? sprintf( '%s سرویس %s را تمدید کرد (%s)', $prefix, $svc, self::mode_label( (string) ( $pl['mode'] ?? '' ), $locale ) )
						: sprintf( '%s renewed service %s (%s)', $prefix, $svc, self::mode_label( (string) ( $pl['mode'] ?? '' ), $locale ) );
				case 'service_add_volume':
					return $fa
						? sprintf( '%s به سرویس %s %s گیگ اضافه کرد (%s)', $prefix, $svc, (string) ( $pl['extra_gb'] ?? '' ), self::mode_label( (string) ( $pl['mode'] ?? '' ), $locale ) )
						: sprintf( '%s added %s GB to service %s (%s)', $prefix, (string) ( $pl['extra_gb'] ?? '' ), $svc, self::mode_label( (string) ( $pl['mode'] ?? '' ), $locale ) );
				case 'service_reduce_volume':
					return $fa
						? sprintf( '%s از سرویس %s %s گیگ کم کرد', $prefix, $svc, (string) ( $pl['reduce_gb'] ?? '' ) )
						: sprintf( '%s reduced service %s by %s GB', $prefix, $svc, (string) ( $pl['reduce_gb'] ?? '' ) );
				case 'service_add_days':
					return $fa
						? sprintf( '%s به سرویس %s %s روز اضافه کرد', $prefix, $svc, (string) ( $pl['days'] ?? '' ) )
						: sprintf( '%s added %s days to service %s', $prefix, (string) ( $pl['days'] ?? '' ), $svc );
				case 'service_reduce_days':
					return $fa
						? sprintf( '%s از سرویس %s %s روز کم کرد', $prefix, $svc, (string) ( $pl['days'] ?? '' ) )
						: sprintf( '%s reduced service %s by %s days', $prefix, $svc, (string) ( $pl['days'] ?? '' ) );
				case 'service_transfer_out':
					return $fa
						? sprintf( '%s سرویس %s را منتقل کرد (به کاربر #%s)', $prefix, $svc, (string) ( $pl['target_id'] ?? $pl['target_raw'] ?? '' ) )
						: sprintf( '%s transferred out service %s (to user #%s)', $prefix, $svc, (string) ( $pl['target_id'] ?? $pl['target_raw'] ?? '' ) );
				case 'service_transfer_in':
					return $fa
						? sprintf( '%s سرویس %s را دریافت کرد (از کاربر #%s)', $prefix, $svc, (string) ( $pl['previous_user'] ?? '' ) )
						: sprintf( '%s received service %s (from user #%s)', $prefix, $svc, (string) ( $pl['previous_user'] ?? '' ) );
				case 'service_soft_delete':
					return $fa
						? sprintf( '%s سرویس %s را از لیست حذف نرم کرد', $prefix, $svc )
						: sprintf( '%s soft-deleted service %s', $prefix, $svc );
				case 'service_panel_sync':
					return $fa ? sprintf( '%s سرویس %s را با پنل همگام کرد', $prefix, $svc ) : sprintf( '%s synced panel for %s', $prefix, $svc );
				case 'service_regen_key':
					return $fa ? sprintf( '%s کلید سرویس %s را بازسازی کرد', $prefix, $svc ) : sprintf( '%s regenerated key for %s', $prefix, $svc );
				case 'service_panel_refresh':
					return $fa ? sprintf( '%s اینباند سرویس %s را تازه کرد', $prefix, $svc ) : sprintf( '%s refreshed inbound for %s', $prefix, $svc );
				case 'service_panel_delete_client':
					return $fa ? sprintf( '%s کلاینت پنل سرویس %s را حذف کرد', $prefix, $svc ) : sprintf( '%s removed panel client for %s', $prefix, $svc );
				case 'service_add_user_slots':
					return $fa
						? sprintf( '%s اسلات کاربر سرویس %s را +%s کرد', $prefix, $svc, (string) ( $pl['extra_users'] ?? '' ) )
						: sprintf( '%s added %s user slots on %s', $prefix, (string) ( $pl['extra_users'] ?? '' ), $svc );
				case 'service_reduce_user_slots':
					return $fa
						? sprintf( '%s اسلات کاربر سرویس %s را -%s کرد', $prefix, $svc, (string) ( $pl['reduce_users'] ?? '' ) )
						: sprintf( '%s reduced user slots on %s by %s', $prefix, $svc, (string) ( $pl['reduce_users'] ?? '' ) );
				case 'service_set_limit_ip':
					return $fa
						? sprintf( '%s سقف IP سرویس %s را روی %s تنظیم کرد', $prefix, $svc, (string) ( $pl['limit_ip'] ?? '' ) )
						: sprintf( '%s set IP limit on %s to %s', $prefix, $svc, (string) ( $pl['limit_ip'] ?? '' ) );
				case 'service_alerts_patch':
					return $fa ? sprintf( '%s هشدارهای سرویس %s را تغییر داد', $prefix, $svc ) : sprintf( '%s updated alerts for %s', $prefix, $svc );
				case 'service_toggle_enable':
					$on = ! empty( $pl['enable'] );
					return $fa
						? sprintf( '%s سرویس %s را روی پنل %s کرد', $prefix, $svc, $on ? 'فعال' : 'غیرفعال' )
						: sprintf( '%s %s service %s on panel', $prefix, $on ? 'enabled' : 'disabled', $svc );
				case 'user_role_change':
					return $fa
						? sprintf( '%s نقش کاربر را به «%s» تغییر داد', $prefix, self::role_label( (string) ( $pl['role'] ?? '' ), $locale ) )
						: sprintf( '%s set user role to %s', $prefix, self::role_label( (string) ( $pl['role'] ?? '' ), $locale ) );
				case 'user_set_referrer':
					$ref = (int) ( $pl['referrer_id'] ?? 0 );
					if ( $ref < 1 ) {
						return $fa ? sprintf( '%s معرف کاربر را حذف کرد', $prefix ) : sprintf( '%s cleared user referrer', $prefix );
					}
					$ref_lbl = '#' . $ref;
					if ( class_exists( 'SimpleVPBot_Model_User' ) ) {
						$ref_u = SimpleVPBot_Model_User::find( $ref );
						if ( $ref_u ) {
							$ref_lbl = SimpleVPBot_Model_User::label( $ref_u ) . ' #' . $ref;
						}
					}
					return $fa ? sprintf( '%s معرف کاربر را به %s تنظیم کرد', $prefix, $ref_lbl ) : sprintf( '%s set referrer to %s', $prefix, $ref_lbl );
				case 'admin_message':
					$ch = sanitize_key( (string) ( $pl['channel'] ?? 'both' ) );
					return $fa
						? sprintf( '%s پیام ادمین فرستاد (کانال: %s)', $prefix, $ch )
						: sprintf( '%s sent admin message (channel: %s)', $prefix, $ch );
				case 'user_ban':
					return $fa ? sprintf( '%s کاربر را مسدود کرد', $prefix ) : sprintf( '%s banned user', $prefix );
				case 'user_unban':
					return $fa ? sprintf( '%s مسدودیت کاربر را برداشت', $prefix ) : sprintf( '%s unbanned user', $prefix );
				case 'membership_approve':
					return $fa ? sprintf( '%s عضویت را تأیید کرد', $prefix ) : sprintf( '%s approved membership', $prefix );
				case 'membership_reject':
					return $fa ? sprintf( '%s عضویت را رد کرد', $prefix ) : sprintf( '%s rejected membership', $prefix );
				case 'membership_reopen':
					return $fa ? sprintf( '%s عضویت را بازگشایی کرد', $prefix ) : sprintf( '%s reopened membership', $prefix );
				case 'link_wp_user':
					return $fa
						? sprintf( '%s کاربر را به وردپرس #%s وصل کرد', $prefix, (string) ( $pl['wp_user_id'] ?? '' ) )
						: sprintf( '%s linked WP user #%s', $prefix, (string) ( $pl['wp_user_id'] ?? '' ) );
				case 'inbound_link':
					return $fa
						? sprintf( '%s اینباند #%s را به سرویس #%s وصل کرد', $prefix, (string) ( $pl['inbound_id'] ?? '' ), (string) ( $pl['service_id'] ?? '' ) )
						: sprintf( '%s linked inbound #%s to service #%s', $prefix, (string) ( $pl['inbound_id'] ?? '' ), (string) ( $pl['service_id'] ?? '' ) );
			}
		}

		return '';
	}
}
