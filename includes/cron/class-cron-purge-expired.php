<?php
/**
 * Auto-purge Xray services N days after expiry (panel client + soft-delete) with user warnings.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Cron_Purge_Expired
 */
class SimpleVPBot_Cron_Purge_Expired {

	const OPTION_SENT_BUCKETS = 'simplevpbot_purge_expired_sent_buckets';

	const OPTION_LAST_RUN = 'simplevpbot_last_purge_expired_run';

	const BATCH_LIMIT = 30;

	/**
	 * Run hourly purge / warn pass.
	 */
	public static function run() {
		if ( ! SimpleVPBot_Settings::get( 'enabled', true ) ) {
			return;
		}
		if ( ! SimpleVPBot_Settings::get( 'purge_expired_enabled', false ) ) {
			return;
		}
		self::run_batch( self::BATCH_LIMIT, 'cron', false );
	}

	/**
	 * One purge/warn pass (cron or manual).
	 *
	 * @param int    $limit          Max services scanned.
	 * @param string $source         cron|manual.
	 * @param bool   $ignore_enabled Skip purge_expired_enabled check (admin manual).
	 * @return array{purged:int,warned:int,failed:int,grace:int,source:string}
	 */
	public static function run_batch( $limit = 30, $source = 'cron', $ignore_enabled = false ) {
		$stats = array(
			'purged' => 0,
			'warned' => 0,
			'failed' => 0,
			'grace'  => self::effective_grace_days(),
			'source' => (string) $source,
		);
		if ( ! SimpleVPBot_Settings::get( 'enabled', true ) ) {
			return $stats;
		}
		if ( ! $ignore_enabled && ! SimpleVPBot_Settings::get( 'purge_expired_enabled', false ) ) {
			return $stats;
		}

		$grace     = (int) $stats['grace'];
		$warn_days = self::effective_warn_days();
		$notify    = (bool) SimpleVPBot_Settings::get( 'purge_expired_notify_user', true );

		foreach ( self::expired_xray_service_rows( $limit ) as $svc ) {
			if ( ! is_object( $svc ) ) {
				continue;
			}
			$sid = (int) ( $svc->id ?? 0 );
			if ( $sid < 1 || empty( $svc->expires_at ) ) {
				continue;
			}
			if ( class_exists( 'SimpleVPBot_Model_Service' ) && SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
				continue;
			}
			$status = self::service_purge_status( $svc, $grace );
			if ( 'not_expired' === $status['status'] ) {
				continue;
			}
			$days_since_expiry = (int) $status['days_since_expiry'];
			$days_until_purge  = (int) $status['days_until_purge'];

			if ( $days_until_purge <= 0 ) {
				if ( $notify && self::maybe_notify_purge( $svc, 0, $grace, $days_since_expiry, true ) ) {
					++$stats['warned'];
				}
				if ( self::purge_service( $svc, $grace, $days_since_expiry, 'system' ) ) {
					++$stats['purged'];
					if ( class_exists( 'SimpleVPBot_Notification_Dedup' ) ) {
						SimpleVPBot_Notification_Dedup::mark_option( 'purge_expired', 'svc' . $sid . ':purged' );
					}
				} else {
					++$stats['failed'];
				}
				continue;
			}

			if ( ! $notify || ! in_array( $days_until_purge, $warn_days, true ) ) {
				continue;
			}
			if ( self::maybe_notify_purge( $svc, $days_until_purge, $grace, $days_since_expiry, false ) ) {
				++$stats['warned'];
			}
		}

		if ( 'cron' === $source ) {
			update_option(
				self::OPTION_LAST_RUN,
				array(
					'at'      => time(),
					'purged'  => $stats['purged'],
					'warned'  => $stats['warned'],
					'failed'  => $stats['failed'],
					'grace'   => $grace,
					'source'  => $source,
				),
				false
			);
		} else {
			$prev = self::last_run_stats();
			update_option(
				self::OPTION_LAST_RUN,
				array(
					'at'      => time(),
					'purged'  => (int) ( $prev['purged'] ?? 0 ) + $stats['purged'],
					'warned'  => (int) ( $prev['warned'] ?? 0 ) + $stats['warned'],
					'failed'  => (int) ( $prev['failed'] ?? 0 ) + $stats['failed'],
					'grace'   => $grace,
					'source'  => $source,
				),
				false
			);
		}

		return $stats;
	}

	/**
	 * Purge services past grace (no warn pass).
	 *
	 * @param int $limit Max rows.
	 * @return array{purged:int,failed:int,grace:int}
	 */
	public static function purge_ready_batch( $limit = 50 ) {
		$grace  = self::effective_grace_days();
		$stats  = array(
			'purged' => 0,
			'failed' => 0,
			'grace'  => $grace,
		);
		$lim    = max( 1, min( 100, (int) $limit ) );
		$scanned = 0;
		foreach ( self::expired_xray_service_rows( 100 ) as $svc ) {
			if ( $scanned >= $lim ) {
				break;
			}
			if ( ! is_object( $svc ) ) {
				continue;
			}
			++$scanned;
			$status = self::service_purge_status( $svc, $grace );
			if ( 'ready' !== $status['status'] ) {
				continue;
			}
			$sid = (int) ( $svc->id ?? 0 );
			if ( self::purge_service( $svc, $grace, (int) $status['days_since_expiry'], 'admin' ) ) {
				++$stats['purged'];
				if ( class_exists( 'SimpleVPBot_Notification_Dedup' ) ) {
					SimpleVPBot_Notification_Dedup::mark_option( 'purge_expired', 'svc' . $sid . ':purged' );
				}
			} else {
				++$stats['failed'];
			}
		}
		return $stats;
	}

	/**
	 * @return int
	 */
	public static function effective_grace_days() {
		return max( 1, min( 365, (int) SimpleVPBot_Settings::get( 'purge_expired_grace_days', 7 ) ) );
	}

	/**
	 * @param object|null $svc   Service row.
	 * @param int|null    $grace Grace days override.
	 * @return array{days_since_expiry:int,days_until_purge:int,status:string}
	 */
	public static function service_purge_status( $svc, $grace = null ) {
		if ( null === $grace ) {
			$grace = self::effective_grace_days();
		}
		$grace = max( 1, min( 365, (int) $grace ) );
		if ( ! is_object( $svc ) || empty( $svc->expires_at ) ) {
			return array(
				'days_since_expiry' => 0,
				'days_until_purge'  => $grace,
				'status'            => 'not_expired',
			);
		}
		$exp_ts = strtotime( (string) $svc->expires_at . ' UTC' );
		if ( ! $exp_ts || $exp_ts >= time() ) {
			return array(
				'days_since_expiry' => 0,
				'days_until_purge'  => $grace,
				'status'            => 'not_expired',
			);
		}
		$days_since       = (int) floor( ( time() - $exp_ts ) / DAY_IN_SECONDS );
		$days_until_purge = $grace - $days_since;
		$status           = $days_until_purge <= 0 ? 'ready' : 'in_grace';
		return array(
			'days_since_expiry' => $days_since,
			'days_until_purge'  => $days_until_purge,
			'status'            => $status,
		);
	}

	/**
	 * Paginated expired candidates for dashboard preview.
	 *
	 * @param array<string, mixed> $args page, per_page, status (all|in_grace|ready).
	 * @return array{items:array<int,array<string,mixed>>,totals:array<string,int>,pagination:array<string,int>}
	 */
	public static function list_expired_candidates( array $args = array() ) {
		global $wpdb;
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 25 ) ) );
		$filter   = sanitize_key( (string) ( $args['status'] ?? 'all' ) );
		if ( ! in_array( $filter, array( 'all', 'in_grace', 'ready' ), true ) ) {
			$filter = 'all';
		}
		$grace  = self::effective_grace_days();
		$t      = SimpleVPBot_Model_Service::table();
		$offset = ( $page - 1 ) * $per_page;
		$extra  = '';
		if ( 'ready' === $filter ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$extra = $wpdb->prepare( ' AND expires_at < DATE_SUB( UTC_TIMESTAMP(), INTERVAL %d DAY )', $grace );
		} elseif ( 'in_grace' === $filter ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$extra = $wpdb->prepare(
				' AND expires_at >= DATE_SUB( UTC_TIMESTAMP(), INTERVAL %d DAY ) AND expires_at < UTC_TIMESTAMP()',
				$grace
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$t}
				WHERE deleted_at IS NULL
				AND inbound_id > 0
				AND ( service_type IS NULL OR service_type = '' OR service_type = %s )
				AND service_type != %s
				AND expires_at IS NOT NULL
				AND expires_at < UTC_TIMESTAMP()
				{$extra}",
				'xray',
				'l2tp'
			)
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t}
				WHERE deleted_at IS NULL
				AND inbound_id > 0
				AND ( service_type IS NULL OR service_type = '' OR service_type = %s )
				AND service_type != %s
				AND expires_at IS NOT NULL
				AND expires_at < UTC_TIMESTAMP()
				{$extra}
				ORDER BY expires_at ASC
				LIMIT %d OFFSET %d",
				'xray',
				'l2tp',
				$per_page,
				$offset
			)
		);
		$rows = is_array( $rows ) ? $rows : array();

		$items = array();
		foreach ( $rows as $svc ) {
			if ( ! is_object( $svc ) ) {
				continue;
			}
			$st      = self::service_purge_status( $svc, $grace );
			$items[] = self::candidate_row( $svc, $st, $grace );
		}

		return array(
			'items'      => $items,
			'totals'     => self::count_expired_totals( $grace ),
			'settings'   => self::dashboard_settings_snapshot(),
			'pagination' => array(
				'page'        => $page,
				'per_page'    => $per_page,
				'total'       => $total,
				'total_pages' => max( 1, (int) ceil( $total / $per_page ) ),
			),
		);
	}

	/**
	 * @param int $grace Grace days.
	 * @return array{all:int,in_grace:int,ready:int}
	 */
	public static function count_expired_totals( $grace = null ) {
		global $wpdb;
		if ( null === $grace ) {
			$grace = self::effective_grace_days();
		}
		$grace = max( 1, min( 365, (int) $grace ) );
		$t     = SimpleVPBot_Model_Service::table();
		$base  = "deleted_at IS NULL
				AND inbound_id > 0
				AND ( service_type IS NULL OR service_type = '' OR service_type = %s )
				AND service_type != %s
				AND expires_at IS NOT NULL
				AND expires_at < UTC_TIMESTAMP()";
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$all = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE {$base}", 'xray', 'l2tp' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ready = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$t} WHERE {$base} AND expires_at < DATE_SUB( UTC_TIMESTAMP(), INTERVAL %d DAY )",
				'xray',
				'l2tp',
				$grace
			)
		);
		return array(
			'all'      => $all,
			'in_grace' => max( 0, $all - $ready ),
			'ready'    => $ready,
		);
	}

	/**
	 * @param object               $svc   Service row.
	 * @param array<string, mixed> $st    Status from service_purge_status.
	 * @param int                  $grace Grace days.
	 * @return array<string, mixed>
	 */
	private static function candidate_row( $svc, array $st, $grace ) {
		return array(
			'id'                => (int) ( $svc->id ?? 0 ),
			'user_id'           => (int) ( $svc->user_id ?? 0 ),
			'remark'            => (string) ( $svc->remark ?? '' ),
			'expires_at'        => (string) ( $svc->expires_at ?? '' ),
			'panel_id'          => (int) ( $svc->panel_id ?? 0 ),
			'days_since_expiry' => (int) $st['days_since_expiry'],
			'days_until_purge'  => (int) $st['days_until_purge'],
			'status'            => (string) $st['status'],
			'grace_days'        => (int) $grace,
		);
	}

	/**
	 * Purge one service by id (admin manual).
	 *
	 * @param int                  $service_id Service id.
	 * @param array<string, mixed> $context    force_early when in_grace.
	 * @return array{ok:bool,message?:string}
	 */
	public static function purge_service_by_id( $service_id, array $context = array() ) {
		$sid = (int) $service_id;
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc || ! empty( $svc->deleted_at ) ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			return array( 'ok' => false, 'message' => 'l2tp' );
		}
		if ( (int) ( $svc->inbound_id ?? 0 ) < 1 ) {
			return array( 'ok' => false, 'message' => 'not_linked' );
		}
		$grace  = self::effective_grace_days();
		$status = self::service_purge_status( $svc, $grace );
		if ( 'not_expired' === $status['status'] ) {
			return array( 'ok' => false, 'message' => 'not_expired' );
		}
		if ( 'in_grace' === $status['status'] && empty( $context['force_early'] ) ) {
			return array( 'ok' => false, 'message' => 'in_grace_confirm_required' );
		}
		$ok = self::purge_service( $svc, $grace, (int) $status['days_since_expiry'], 'admin' );
		if ( ! $ok ) {
			return array( 'ok' => false, 'message' => 'purge_failed' );
		}
		if ( class_exists( 'SimpleVPBot_Notification_Dedup' ) ) {
			SimpleVPBot_Notification_Dedup::mark_option( 'purge_expired', 'svc' . $sid . ':purged' );
		}
		return array( 'ok' => true, 'message' => 'ok' );
	}

	/**
	 * Dashboard settings snapshot for purge tab.
	 *
	 * @return array<string, mixed>
	 */
	public static function dashboard_settings_snapshot() {
		$warn = self::effective_warn_days();
		return array(
			'purge_expired_enabled'     => ! empty( SimpleVPBot_Settings::get( 'purge_expired_enabled', false ) ),
			'purge_expired_grace_days'  => self::effective_grace_days(),
			'purge_expired_warn_days'   => implode( ',', array_map( 'strval', $warn ) ),
			'purge_expired_notify_user' => ! empty( SimpleVPBot_Settings::get( 'purge_expired_notify_user', true ) ),
			'last_purge_expired_run'    => self::last_run_stats(),
		);
	}

	/**
	 * @return array<int, int>
	 */
	public static function effective_warn_days() {
		$raw = (array) SimpleVPBot_Settings::get( 'purge_expired_warn_days', array( 7, 3, 1, 0 ) );
		$out = array();
		foreach ( $raw as $d ) {
			$n = (int) $d;
			if ( $n >= 0 && $n <= 365 ) {
				$out[] = $n;
			}
		}
		$out = array_values( array_unique( $out ) );
		return ! empty( $out ) ? $out : array( 7, 3, 1, 0 );
	}

	/**
	 * Expired Xray-linked services (not soft-deleted).
	 *
	 * @param int $limit Max rows.
	 * @return array<int, object>
	 */
	public static function expired_xray_service_rows( $limit = 30 ) {
		global $wpdb;
		$t   = SimpleVPBot_Model_Service::table();
		$lim = max( 1, min( 100, (int) $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t}
				WHERE deleted_at IS NULL
				AND inbound_id > 0
				AND ( service_type IS NULL OR service_type = '' OR service_type = %s )
				AND service_type != %s
				AND expires_at IS NOT NULL
				AND expires_at < UTC_TIMESTAMP()
				ORDER BY expires_at ASC
				LIMIT %d",
				'xray',
				'l2tp',
				$lim
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete panel client and soft-delete DB row; audit + user activity.
	 *
	 * @param object $svc Service row.
	 * @param int    $grace_days Grace after expiry.
	 * @param int    $days_since_expiry Days since expires_at.
	 * @param string $actor_kind system|admin.
	 * @return bool True when soft-deleted.
	 */
	private static function purge_service( $svc, $grace_days, $days_since_expiry, $actor_kind = 'system' ) {
		$sid = (int) ( $svc->id ?? 0 );
		if ( $sid < 1 || ! class_exists( 'SimpleVPBot_Service_Dashboard_Panel' ) ) {
			return false;
		}
		$res = SimpleVPBot_Service_Dashboard_Panel::xray_delete_panel_client( $sid );
		$ok  = is_array( $res ) && ! empty( $res['ok'] );
		if ( ! $ok ) {
			SimpleVPBot_Logger::error(
				'purge_expired: delete failed',
				array(
					'service_id' => $sid,
					'reason'     => is_array( $res ) ? (string) ( $res['reason'] ?? '' ) : 'unknown',
				)
			);
			return false;
		}
		$uid        = (int) ( $svc->user_id ?? 0 );
		$actor_kind = in_array( $actor_kind, array( 'system', 'admin' ), true ) ? $actor_kind : 'system';
		if ( class_exists( 'SimpleVPBot_Audit_Log' ) ) {
			SimpleVPBot_Audit_Log::record(
				array(
					'domain'      => 'admin',
					'event_type'  => 'service.purge_expired',
					'actor_kind'  => $actor_kind,
					'target_type' => 'service',
					'target_id'   => $sid,
					'payload'     => array(
						'remark'            => (string) ( $svc->remark ?? '' ),
						'user_id'           => $uid,
						'grace_days'        => (int) $grace_days,
						'days_since_expiry' => (int) $days_since_expiry,
						'panel_ok'          => true,
					),
				)
			);
		}
		if ( $uid > 0 && class_exists( 'SimpleVPBot_User_Activity_Log' ) ) {
			SimpleVPBot_User_Activity_Log::append(
				array(
					'subject_svp_user_id' => $uid,
					'channel'             => 'rest',
					'actor_kind'          => $actor_kind,
					'event_type'          => 'service_purge_expired',
					'payload'             => array(
						'service_id'        => $sid,
						'remark'            => (string) ( $svc->remark ?? '' ),
						'days_since_expiry' => (int) $days_since_expiry,
					),
				)
			);
		}
		SimpleVPBot_Logger::info(
			'purge_expired: service removed',
			array(
				'service_id' => $sid,
				'user_id'    => $uid,
				'actor'      => $actor_kind,
			)
		);
		return true;
	}

	/**
	 * Send purge warning (deduped per service + day bucket).
	 *
	 * @param object $svc Service.
	 * @param int    $days_until_purge Days left before removal (0 = today).
	 * @param int    $grace_days Grace setting.
	 * @param int    $days_since_expiry Days past expiry.
	 * @param bool   $is_purge_day Whether this is the removal-day message (before delete).
	 * @return bool True if a message was sent.
	 */
	private static function maybe_notify_purge( $svc, $days_until_purge, $grace_days, $days_since_expiry, $is_purge_day ) {
		$sid = (int) ( $svc->id ?? 0 );
		if ( $sid < 1 ) {
			return false;
		}
		$bucket_key = $is_purge_day
			? 'svc' . $sid . ':purge_day'
			: 'svc' . $sid . ':purge_warn:' . (int) $days_until_purge;
		if ( class_exists( 'SimpleVPBot_Notification_Dedup' ) ) {
			if ( ! SimpleVPBot_Notification_Dedup::claim( 'purge_expired', $bucket_key, 90 ) ) {
				return false;
			}
		} elseif ( ! empty( (array) get_option( self::OPTION_SENT_BUCKETS, array() )[ $bucket_key ] ) ) {
			return false;
		}
		$user = class_exists( 'SimpleVPBot_Model_User' ) ? SimpleVPBot_Model_User::find( (int) ( $svc->user_id ?? 0 ) ) : null;
		if ( ! $user || 'approved' !== (string) ( $user->status ?? '' ) ) {
			return false;
		}
		$text = self::build_purge_notify_text( $user, $svc, (int) $days_until_purge, (int) $grace_days, $is_purge_day );
		self::notify_user( $user, $text );
		SimpleVPBot_Model_Service::update( $sid, array( 'last_warn_sent_at' => current_time( 'mysql', 1 ) ) );
		return true;
	}

	/**
	 * Render purge warning from editable text keys.
	 *
	 * @param object $user User row.
	 * @param object $svc  Service row.
	 * @param int    $days_until_purge Days until purge.
	 * @param int    $grace_days Grace days setting.
	 * @param bool   $is_purge_day Removal day flag.
	 * @return string
	 */
	private static function build_purge_notify_text( $user, $svc, $days_until_purge, $grace_days, $is_purge_day ) {
		$name   = class_exists( 'SimpleVPBot_User_Display' ) ? SimpleVPBot_User_Display::name( $user ) : 'کاربر';
		$remark = (string) ( $svc->remark ?? '' );
		if ( $is_purge_day || 0 === $days_until_purge ) {
			$key = 'msg.cron_purge_warn_today';
			$def = "{name} عزیز؛\n\nسرویس «{remark}» شما امروز به‌طور خودکار از ربات و پنل حذف می‌شود (پایان مهلت پس از انقضا).\n\n⏳ مهلت پس از انقضا: {grace_days} روز\n📅 اگر هنوز به این سرویس نیاز دارید، فوراً از منوی همان سرویس «تمدید» را بزنید.";
		} elseif ( 1 === $days_until_purge ) {
			$key = 'msg.cron_purge_warn_tomorrow';
			$def = "{name} عزیز؛\n\nسرویس «{remark}» شما فردا به‌طور خودکار از ربات و پنل حذف می‌شود.\n\n⏳ مهلت پس از انقضا: {grace_days} روز\n📅 برای ادامه استفاده، همین امروز از منوی همان سرویس «تمدید» را بزنید.";
		} else {
			$key = 'msg.cron_purge_warn';
			$def = "{name} عزیز؛\n\nسرویس «{remark}» شما تا {days} روز دیگر به‌طور خودکار از ربات و پنل حذف می‌شود.\n\n⏳ مهلت پس از انقضا: {grace_days} روز\n📅 برای ادامه استفاده، از منوی همان سرویس گزینه «تمدید» را انتخاب کنید.";
		}
		$tpl = class_exists( 'SimpleVPBot_Texts' ) ? SimpleVPBot_Texts::get( $key, $def ) : $def;
		return class_exists( 'SimpleVPBot_Texts' )
			? SimpleVPBot_Texts::format(
				$tpl,
				array(
					'name'       => $name,
					'remark'     => $remark,
					'days'       => (string) (int) $days_until_purge,
					'grace_days' => (string) (int) $grace_days,
				)
			)
			: str_replace(
				array( '{name}', '{remark}', '{days}', '{grace_days}' ),
				array( $name, $remark, (string) (int) $days_until_purge, (string) (int) $grace_days ),
				$tpl
			);
	}

	/**
	 * Whether auto-purge cron owns post-expiry notifications for this service.
	 *
	 * @param object $svc Service row.
	 * @return bool
	 */
	public static function covers_expired_service( $svc ) {
		if ( ! SimpleVPBot_Settings::get( 'purge_expired_enabled', false ) ) {
			return false;
		}
		if ( ! is_object( $svc ) || ! class_exists( 'SimpleVPBot_Model_Service' ) ) {
			return false;
		}
		if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			return false;
		}
		if ( (int) ( $svc->inbound_id ?? 0 ) < 1 || empty( $svc->expires_at ) ) {
			return false;
		}
		$exp_ts = strtotime( (string) $svc->expires_at . ' UTC' );
		if ( ! $exp_ts || $exp_ts >= time() ) {
			return false;
		}
		$grace      = self::effective_grace_days();
		$days_since = (int) floor( ( time() - $exp_ts ) / DAY_IN_SECONDS );
		return $days_since <= $grace;
	}

	/**
	 * @param object $user User row.
	 * @param string $text Message.
	 */
	private static function notify_user( $user, $text ) {
		if ( class_exists( 'SimpleVPBot_User_Notify' ) ) {
			SimpleVPBot_User_Notify::send_to_user( $user, $text );
			return;
		}
		if ( class_exists( 'SimpleVPBot_Bot_Runtime' ) && ! empty( $user->tg_user_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( 'telegram', (int) $user->tg_user_id, $text );
		}
		if ( class_exists( 'SimpleVPBot_Bot_Runtime' ) && ! empty( $user->bl_user_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( 'bale', (int) $user->bl_user_id, $text );
		}
	}

	/**
	 * Last run stats for dashboard settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function last_run_stats() {
		$raw = get_option( self::OPTION_LAST_RUN, array() );
		return is_array( $raw ) ? $raw : array();
	}
}
