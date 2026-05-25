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
	 * Expiry unix timestamp from panel client expiryTime (ms) or 0 when unlimited.
	 *
	 * @param array<string, mixed> $client Inbound client row.
	 * @return int
	 */
	private static function panel_client_expiry_ts( array $client ) {
		$ms = isset( $client['expiryTime'] ) ? (int) $client['expiryTime'] : 0;
		if ( $ms < 1 ) {
			return 0;
		}
		return (int) floor( $ms / 1000 );
	}

	/**
	 * Sync linked svp_services row from panel values after a successful mutation.
	 *
	 * @param int    $panel_id   Panel id.
	 * @param int    $inbound_id Inbound id.
	 * @param string $email      Client email.
	 * @param int    $total_bytes Traffic cap bytes (optional).
	 * @param string $expires_mysql UTC datetime or empty to skip.
	 * @return void
	 */
	private static function sync_service_row_from_panel( $panel_id, $inbound_id, $email, $total_bytes = 0, $expires_mysql = '' ) {
		$svc = SimpleVPBot_Model_Service::find_by_inbound_email( (int) $inbound_id, (string) $email, (int) $panel_id );
		if ( ! $svc ) {
			return;
		}
		$up = array();
		if ( $total_bytes > 0 ) {
			$up['total_traffic'] = (int) $total_bytes;
		}
		if ( '' !== $expires_mysql ) {
			$up['expires_at'] = $expires_mysql;
		}
		if ( ! empty( $up ) ) {
			SimpleVPBot_Model_Service::update( (int) $svc->id, $up );
		}
	}

	/**
	 * Persist panel client UUID to svp_services when DB id was empty or invalid.
	 *
	 * @param int                   $service_id     svp_services.id (0 = resolve by inbound+email).
	 * @param array<string, mixed>  $client_row     Patched panel client row.
	 * @param string                $current_db_id  Existing xui_client_id.
	 * @param int                   $panel_id       Panel id when resolving service.
	 * @param int                   $inbound_id     Inbound id when resolving service.
	 * @param string                     $email          Client email when resolving service.
	 * @param array<string, mixed>|null  $inbound        Inbound row for protocol-aware path id.
	 * @return void
	 */
	private static function maybe_sync_service_xui_client_id( $service_id, array $client_row, $current_db_id = '', $panel_id = 0, $inbound_id = 0, $email = '', $inbound = null ) {
		$row    = $client_row;
		$new_id = '';
		if ( is_array( $inbound ) ) {
			$path = SimpleVPBot_Xui_Client::resolve_client_path_id_for_update( (string) $current_db_id, $inbound, (string) $email );
			if ( is_string( $path ) && '' !== $path ) {
				$new_id = $path;
			}
		}
		if ( '' === $new_id ) {
			if ( ! SimpleVPBot_Xui_Client::ensure_client_panel_id( $row ) ) {
				return;
			}
			$new_id = trim( (string) ( $row['id'] ?? '' ) );
		}
		if ( '' === $new_id ) {
			return;
		}
		$cur = trim( (string) $current_db_id );
		if ( $new_id === $cur && '' !== $cur ) {
			return;
		}
		$sid = (int) $service_id;
		if ( $sid < 1 ) {
			$svc = SimpleVPBot_Model_Service::find_by_inbound_email( (int) $inbound_id, (string) $email, max( 1, (int) $panel_id ) );
			if ( ! $svc ) {
				return;
			}
			$sid = (int) $svc->id;
			$cur = trim( (string) ( $svc->xui_client_id ?? '' ) );
			if ( $new_id === $cur && '' !== $cur ) {
				return;
			}
		}
		SimpleVPBot_Model_Service::update(
			$sid,
			array(
				'xui_client_id'   => $new_id,
				'xui_client_uuid' => $new_id,
			)
		);
	}

	/**
	 * User-facing message when updateClient fails, optionally including panel msg.
	 *
	 * @param array<string, mixed>|null $res     Panel JSON response.
	 * @param string                    $default Default Persian message.
	 * @return string
	 */
	private static function panel_update_fail_message( $res, $default = '⛔ بروزرسانی پنل انجام نشد.' ) {
		if ( ! is_array( $res ) || empty( $res['msg'] ) ) {
			return (string) $default;
		}
		$hint = trim( (string) $res['msg'] );
		if ( '' === $hint ) {
			return (string) $default;
		}
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $hint, 'UTF-8' ) > 100 ) {
			$hint = function_exists( 'mb_substr' ) ? mb_substr( $hint, 0, 100, 'UTF-8' ) . '…' : substr( $hint, 0, 100 ) . '…';
		} elseif ( strlen( $hint ) > 100 ) {
			$hint = substr( $hint, 0, 100 ) . '…';
		}
		return (string) $default . ' (' . $hint . ')';
	}

	/**
	 * Patch one client inside inbound settings and push full settings to panel.
	 *
	 * @param int                  $panel_id   Panel id.
	 * @param int                  $inbound_id Inbound id.
	 * @param string               $email      Client email.
	 * @param callable             $mutator    function( array &$client ): void
	 * @param array<string, mixed> $opts       force_enable, touch_remark, user_id.
	 * @return array{ok:bool, message:string, client?:array<string,mixed>}
	 */
	private static function patch_panel_client( $panel_id, $inbound_id, $email, callable $mutator, array $opts = array() ) {
		$em = trim( (string) $email );
		if ( '' === $em ) {
			return array( 'ok' => false, 'message' => '⛔ ایمیل کلاینت نامعتبر است.' );
		}
		return SimpleVPBot_Xui_Client::run_with_panel(
			(int) $panel_id,
			function () use ( $inbound_id, $em, $mutator, $opts ) {
				if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
					return array( 'ok' => false, 'message' => '⛔ ورود به پنل ناموفق است.' );
				}
				$inbound = SimpleVPBot_Xui_Client::inbound_get( (int) $inbound_id );
				if ( ! $inbound ) {
					return array( 'ok' => false, 'message' => '⛔ اینباند پنل یافت نشد.' );
				}
				$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
				$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
				if ( ! is_array( $dec ) || empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
					return array( 'ok' => false, 'message' => '⛔ فهرست کلاینت خالی است.' );
				}
				$updated = null;
				foreach ( $dec['clients'] as &$cl ) {
					if ( ! is_array( $cl ) || ! isset( $cl['email'] ) || (string) $cl['email'] !== $em ) {
						continue;
					}
					$mutator( $cl );
					if ( ! empty( $opts['force_enable'] ) ) {
						$cl['enable'] = true;
					}
					if ( ! empty( $opts['touch_remark'] ) ) {
						$remark = isset( $opts['remark'] ) ? (string) $opts['remark'] : (string) ( $cl['remark'] ?? '' );
						$uid    = (int) ( $opts['user_id'] ?? 0 );
						if ( $uid > 0 && class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
							$remark = SimpleVPBot_Reseller_Branding::panel_client_name_for_user( $uid, $remark );
						}
						$cl['remark'] = $remark;
					}
					$updated = $cl;
					break;
				}
				unset( $cl );
				if ( ! is_array( $updated ) ) {
					return array( 'ok' => false, 'message' => '⛔ کلاینت روی پنل پیدا نشد.' );
				}
				$old_key = SimpleVPBot_Xui_Client::resolve_client_key_for_update( (string) ( $opts['xui_client_id'] ?? '' ), $inbound, $em );
				if ( ! $old_key ) {
					return array( 'ok' => false, 'message' => '⛔ شناسه کلاینت روی پنل پیدا نشد.' );
				}
				$path_ids = array( (string) $old_key );
				if ( $em !== (string) $old_key ) {
					$path_ids[] = $em;
				}
				$res = SimpleVPBot_Xui_Client::update_inbound_client_sequential( (int) $inbound_id, $dec, $updated, $path_ids );
				if ( ! SimpleVPBot_Xui_Client::response_is_success( $res ) ) {
					SimpleVPBot_Logger::error(
						'patch_panel_client updateClient failed',
						array(
							'inbound_id'   => (int) $inbound_id,
							'email'        => $em,
							'protocol'     => strtolower( (string) ( $inbound['protocol'] ?? '' ) ),
							'path_id'      => (string) ( $path_ids[0] ?? '' ),
							'has_password' => ! empty( $updated['password'] ),
							'has_id'       => ! empty( $updated['id'] ),
							'has_auth'     => ! empty( $updated['auth'] ),
							'res'          => $res,
							'panel_msg'    => is_array( $res ) ? (string) ( $res['msg'] ?? '' ) : '',
						)
					);
					return array( 'ok' => false, 'message' => self::panel_update_fail_message( $res ) );
				}
				self::maybe_sync_service_xui_client_id(
					(int) ( $opts['service_id'] ?? 0 ),
					$updated,
					(string) ( $opts['xui_client_id'] ?? '' ),
					(int) $panel_id,
					(int) $inbound_id,
					$em,
					$inbound
				);
				return array( 'ok' => true, 'message' => 'ok', 'client' => $updated );
			}
		);
	}

	/**
	 * Set absolute traffic quota (GB) on panel client and sync DB.
	 *
	 * @param int    $panel_id   Panel id.
	 * @param int    $inbound_id Inbound id (live / mapped).
	 * @param string $email      Client email.
	 * @param int    $total_gb   Target cap in gigabytes (0 = unlimited on panel).
	 * @param array<string, mixed> $opts sync_db, service_id, xui_client_id, user_id, force_enable.
	 * @return array{ok:bool, message:string, total_bytes?:int}
	 */
	public static function set_panel_client_quota_gb( $panel_id, $inbound_id, $email, $total_gb, array $opts = array() ) {
		$gb          = max( 0, (int) $total_gb );
		$target_bytes = $gb > 0 ? SimpleVPBot_Inbound_Linker::cap_traffic_bytes( $gb * self::BYTES_PER_GB ) : 0;
		$em          = trim( (string) $email );
		$patch       = self::patch_panel_client(
			(int) $panel_id,
			(int) $inbound_id,
			$em,
			static function ( array &$cl ) use ( $target_bytes ) {
				$cl['totalGB'] = SimpleVPBot_Inbound_Linker::panel_client_totalgb_json_value( $target_bytes );
			},
			$opts
		);
		if ( empty( $patch['ok'] ) ) {
			return array( 'ok' => false, 'message' => (string) ( $patch['message'] ?? '⛔ بروزرسانی پنل انجام نشد.' ) );
		}
		$sync_db = ! array_key_exists( 'sync_db', $opts ) || ! empty( $opts['sync_db'] );
		if ( $sync_db ) {
			self::sync_service_row_from_panel( (int) $panel_id, (int) $inbound_id, $em, (int) $target_bytes );
			$sid = (int) ( $opts['service_id'] ?? 0 );
			if ( $sid > 0 && class_exists( 'SimpleVPBot_Model_Service' ) ) {
				SimpleVPBot_Model_Service::update(
					$sid,
					array( 'total_traffic' => (int) $target_bytes )
				);
			}
		}
		if ( class_exists( 'SimpleVPBot_Model_Panel_Inbound_Client' ) ) {
			SimpleVPBot_Model_Panel_Inbound_Client::patch_cached_client(
				(int) $panel_id,
				(int) $inbound_id,
				$em,
				array(
					'total_gb'    => (int) $target_bytes,
					'limit_bytes' => (int) $target_bytes,
				)
			);
		}
		return array(
			'ok'           => true,
			'message'      => $gb > 0
				? sprintf(
					/* translators: %d: gigabytes */
					__( 'سقف حجم روی %d گیگابایت تنظیم شد.', 'simplevpbot' ),
					$gb
				)
				: __( 'سقف حجم نامحدود شد.', 'simplevpbot' ),
			'total_bytes'  => (int) $target_bytes,
		);
	}

	/**
	 * Add or reduce traffic on one panel client (panel quota is source of truth).
	 *
	 * @param int    $panel_id   Panel id.
	 * @param int    $inbound_id Inbound id.
	 * @param string $email      Client email.
	 * @param int    $delta_gb   Gigabytes to add or reduce.
	 * @param bool   $reduce     True to subtract.
	 * @param array<string, mixed> $opts force_enable, touch_remark, user_id, remark, xui_client_id, sync_db.
	 * @return array{ok:bool, message:string, applied_gb?:int}
	 */
	public static function apply_panel_volume_delta( $panel_id, $inbound_id, $email, $delta_gb, $reduce = false, array $opts = array() ) {
		$g             = max( 1, (int) $delta_gb );
		$delta_bytes   = $g * self::BYTES_PER_GB;
		$em            = trim( (string) $email );
		$applied_gb    = 0;
		$new_total_bytes = 0;
		$patch         = self::patch_panel_client(
			(int) $panel_id,
			(int) $inbound_id,
			$em,
			static function ( array &$cl ) use ( $em, $delta_bytes, $reduce, $g, &$applied_gb, &$new_total_bytes ) {
				$raw = isset( $cl['totalGB'] ) ? $cl['totalGB'] : 0;
				$cur = SimpleVPBot_Inbound_Linker::resolve_quota_bytes( $raw, $em );
				if ( $reduce ) {
					$new_total_bytes = max( 0, $cur - $delta_bytes );
					$applied_gb      = (int) floor( max( 0, $cur - $new_total_bytes ) / self::BYTES_PER_GB );
				} else {
					$new_total_bytes = SimpleVPBot_Inbound_Linker::cap_traffic_bytes( $cur + $delta_bytes );
					$applied_gb      = $g;
				}
				$cl['totalGB'] = SimpleVPBot_Inbound_Linker::panel_client_totalgb_json_value( $new_total_bytes );
			},
			$opts
		);
		if ( empty( $patch['ok'] ) ) {
			return array( 'ok' => false, 'message' => (string) ( $patch['message'] ?? '⛔ بروزرسانی پنل انجام نشد.' ) );
		}
		if ( ! isset( $new_total_bytes ) ) {
			return array( 'ok' => false, 'message' => '⛔ بروزرسانی پنل انجام نشد.' );
		}
		$sync_db = ! array_key_exists( 'sync_db', $opts ) || ! empty( $opts['sync_db'] );
		if ( $sync_db ) {
			self::sync_service_row_from_panel( (int) $panel_id, (int) $inbound_id, $em, (int) $new_total_bytes );
		}
		if ( class_exists( 'SimpleVPBot_Model_Panel_Inbound_Client' ) ) {
			SimpleVPBot_Model_Panel_Inbound_Client::patch_cached_client(
				(int) $panel_id,
				(int) $inbound_id,
				$em,
				array(
					'total_gb'    => (int) $new_total_bytes,
					'limit_bytes' => (int) $new_total_bytes,
				)
			);
		}
		if ( $reduce ) {
			$applied = isset( $applied_gb ) ? (int) $applied_gb : $g;
			return array(
				'ok'         => true,
				'applied_gb' => $applied,
				'message'    => '✅ ' . $applied . ' گیگ از سقف حجم سرویس کسر شد.',
			);
		}
		return array(
			'ok'      => true,
			'message' => '✅ ' . $g . ' گیگ به سقف حجم سرویس اضافه شد.',
		);
	}

	/**
	 * Add or reduce expiry days on one panel client (panel expiry is source of truth).
	 *
	 * @param int    $panel_id   Panel id.
	 * @param int    $inbound_id Inbound id.
	 * @param string $email      Client email.
	 * @param int    $days       Calendar days.
	 * @param bool   $reduce     True to subtract.
	 * @param array<string, mixed> $opts force_enable, touch_remark, user_id, remark, xui_client_id, sync_db.
	 * @return array{ok:bool, message:string, applied_days?:int}
	 */
	public static function apply_panel_extend_days( $panel_id, $inbound_id, $email, $days, $reduce = false, array $opts = array() ) {
		$d       = max( 1, min( 3650, (int) $days ) );
		$add_sec = $d * DAY_IN_SECONDS;
		$em      = trim( (string) $email );
		$new_ts  = 0;
		$applied = 0;
		$patch   = self::patch_panel_client(
			(int) $panel_id,
			(int) $inbound_id,
			$em,
			static function ( array &$cl ) use ( $add_sec, $reduce, $d, &$new_ts, &$applied ) {
				$exp_ts = self::panel_client_expiry_ts( $cl );
				$base   = time();
				if ( $exp_ts > $base ) {
					$base = $exp_ts;
				}
				if ( $reduce ) {
					$cur_ts = $exp_ts > 0 ? $exp_ts : time();
					$now_ts = time();
					$new_ts = max( $now_ts, $cur_ts - $add_sec );
					$applied = (int) floor( max( 0, $cur_ts - $new_ts ) / DAY_IN_SECONDS );
				} else {
					$new_ts = $base + $add_sec;
					$applied = $d;
				}
				$cl['expiryTime'] = $new_ts * 1000;
			},
			$opts
		);
		if ( empty( $patch['ok'] ) ) {
			return array( 'ok' => false, 'message' => (string) ( $patch['message'] ?? '⛔ بروزرسانی پنل انجام نشد.' ) );
		}
		if ( $new_ts < 1 ) {
			return array( 'ok' => false, 'message' => '⛔ بروزرسانی پنل انجام نشد.' );
		}
		$new_mysql = gmdate( 'Y-m-d H:i:s', $new_ts );
		$sync_db   = ! array_key_exists( 'sync_db', $opts ) || ! empty( $opts['sync_db'] );
		if ( $sync_db ) {
			self::sync_service_row_from_panel( (int) $panel_id, (int) $inbound_id, $em, 0, $new_mysql );
		}
		if ( class_exists( 'SimpleVPBot_Model_Panel_Inbound_Client' ) ) {
			SimpleVPBot_Model_Panel_Inbound_Client::patch_cached_client(
				(int) $panel_id,
				(int) $inbound_id,
				$em,
				array( 'expiry_ms' => $new_ts * 1000 )
			);
		}
		if ( $reduce ) {
			return array(
				'ok'           => true,
				'applied_days' => $applied,
				'message'      => '✅ ' . $applied . ' روز از انقضا کسر شد.',
			);
		}
		return array(
			'ok'      => true,
			'message' => '✅ ' . $d . ' روز به انقضا اضافه شد.',
		);
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
					return array( 'ok' => false, 'message' => self::panel_update_fail_message( $res ) );
				}

				self::maybe_sync_service_xui_client_id( $sid, $updated, (string) ( $svc->xui_client_id ?? '' ), 0, 0, '', $inbound );

				if ( $do_reset ) {
					$reset_res = SimpleVPBot_Xui_Client::reset_client_traffic( (int) $svc->inbound_id, (string) $svc->email );
					if ( ! SimpleVPBot_Xui_Client::response_is_success( $reset_res ) ) {
						SimpleVPBot_Logger::error(
							'renew apply resetClientTraffic failed',
							array(
								'svc_id' => $sid,
								'res'    => $reset_res,
							)
						);
						return array( 'ok' => false, 'message' => '⛔ ریست مصرف روی پنل انجام نشد.' );
					}
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
		$r = self::apply_panel_volume_delta(
			self::svc_panel_id( $svc ),
			(int) $svc->inbound_id,
			(string) $svc->email,
			$g,
			false,
			array(
				'force_enable'   => true,
				'touch_remark'   => true,
				'user_id'        => (int) $svc->user_id,
				'remark'         => (string) $svc->remark,
				'xui_client_id'  => (string) $svc->xui_client_id,
			)
		);
		if ( empty( $r['ok'] ) ) {
			SimpleVPBot_Logger::error(
				'add volume updateClient failed',
				array(
					'svc_id' => $sid,
					'msg'    => (string) ( $r['message'] ?? '' ),
				)
			);
			return $r;
		}
		$up = array();
		if ( (int) ( $svc->plan_id ?? 0 ) < 1 ) {
			$pfb = SimpleVPBot_Model_Service::effective_plan_for_pricing( $svc );
			if ( $pfb ) {
				$up['plan_id'] = (int) $pfb->id;
			}
		}
		if ( ! empty( $up ) ) {
			SimpleVPBot_Model_Service::update( $sid, $up );
		}
		return $r;
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
		return self::apply_panel_volume_delta(
			self::svc_panel_id( $svc ),
			(int) $svc->inbound_id,
			(string) $svc->email,
			$g,
			true,
			array(
				'force_enable'  => true,
				'touch_remark'  => true,
				'user_id'       => (int) $svc->user_id,
				'remark'        => (string) $svc->remark,
				'xui_client_id' => (string) $svc->xui_client_id,
			)
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
		if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			$add_sec = $d * DAY_IN_SECONDS;
			$base    = time();
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
		return self::apply_panel_extend_days(
			self::svc_panel_id( $svc ),
			(int) $svc->inbound_id,
			(string) $svc->email,
			$d,
			false,
			array(
				'force_enable'  => true,
				'touch_remark'  => true,
				'user_id'       => (int) $svc->user_id,
				'remark'        => (string) $svc->remark,
				'xui_client_id' => (string) $svc->xui_client_id,
			)
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
		return self::apply_panel_extend_days(
			self::svc_panel_id( $svc ),
			(int) $svc->inbound_id,
			(string) $svc->email,
			$d,
			true,
			array(
				'force_enable'  => true,
				'touch_remark'  => true,
				'user_id'       => (int) $svc->user_id,
				'remark'        => (string) $svc->remark,
				'xui_client_id' => (string) $svc->xui_client_id,
			)
		);
	}

	/**
	 * Push svp_services quota/expiry/enable/limitIp to an existing panel client.
	 *
	 * @param object $svc Service row.
	 * @return array{ok:bool, message:string, action?:string}
	 */
	public static function sync_service_row_to_panel( $svc ) {
		if ( class_exists( 'SimpleVPBot_Service_Panel_Inbound_Map' ) ) {
			$svc = SimpleVPBot_Service_Panel_Inbound_Map::service_with_resolved_inbound( $svc );
		}
		$email = trim( (string) ( $svc->email ?? '' ) );
		$iid   = (int) ( $svc->inbound_id ?? 0 );
		if ( '' === $email || $iid < 1 ) {
			return array( 'ok' => false, 'message' => 'bad_service_row', 'action' => 'failed' );
		}
		$total_bytes = SimpleVPBot_Inbound_Linker::cap_traffic_bytes( (int) ( $svc->total_traffic ?? 0 ) );
		$expiry_ms   = 0;
		if ( ! empty( $svc->expires_at ) ) {
			$ts = strtotime( (string) $svc->expires_at . ' UTC' );
			if ( $ts > 0 ) {
				$expiry_ms = (int) $ts * 1000;
			}
		}
		$limit_ip = (int) ( $svc->panel_limit_ip ?? 0 );
		if ( $limit_ip < 1 ) {
			$limit_ip = max( 0, (int) SimpleVPBot_Settings::get( 'default_concurrent_users', 2 ) );
		}
		$enable = ! isset( $svc->panel_client_enabled ) || (int) $svc->panel_client_enabled !== 0;
		$remark = trim( (string) ( $svc->remark ?? '' ) );
		$uid    = (int) ( $svc->user_id ?? 0 );
		$uuid   = trim( (string) ( $svc->xui_client_uuid ?? $svc->xui_client_id ?? '' ) );

		$res = self::patch_panel_client(
			self::svc_panel_id( $svc ),
			$iid,
			$email,
			function ( array &$cl ) use ( $total_bytes, $expiry_ms, $limit_ip, $enable, $remark ) {
				$cl['totalGB']    = SimpleVPBot_Inbound_Linker::panel_client_totalgb_json_value( $total_bytes );
				$cl['expiryTime'] = (int) $expiry_ms;
				$cl['limitIp']    = $limit_ip;
				$cl['enable']     = $enable;
				if ( '' !== $remark ) {
					$cl['remark'] = $remark;
				}
			},
			array(
				'force_enable'  => $enable,
				'touch_remark'  => true,
				'user_id'       => $uid,
				'remark'        => $remark,
				'xui_client_id' => $uuid,
				'service_id'    => (int) ( $svc->id ?? 0 ),
			)
		);
		if ( ! empty( $res['ok'] ) ) {
			$res['action'] = 'patched';
		} else {
			$res['action'] = 'failed';
		}
		return $res;
	}
}
