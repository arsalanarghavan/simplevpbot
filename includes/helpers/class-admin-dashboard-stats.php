<?php
/**
 * Aggregated stats for bot dashboard, web portal, and WP admin.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Admin_Dashboard_Stats
 */
class SimpleVPBot_Admin_Dashboard_Stats {

	/**
	 * Calendar date (Y-m-d) in site timezone for "today" minus offset.
	 *
	 * @param int $day_offset 0 = today, 1 = yesterday, ...
	 * @return string
	 */
	public static function stat_date_for_offset( $day_offset ) {
		$off = max( 0, min( 31, (int) $day_offset ) );
		try {
			$today = new DateTimeImmutable( 'today', wp_timezone() );
			return $today->modify( '-' . $off . ' days' )->format( 'Y-m-d' );
		} catch ( Exception $e ) {
			return gmdate( 'Y-m-d' );
		}
	}

	/**
	 * User + service headline counts.
	 *
	 * @return array<string, int>
	 */
	public static function user_service_counts() {
		global $wpdb;
		$u = SimpleVPBot_Model_User::table();
		$s = SimpleVPBot_Model_Service::table();
		$out = array(
			'users_approved'    => 0,
			'users_pending'     => 0,
			'users_rejected'    => 0,
			'users_blocked'     => 0,
			'users_total'       => 0,
			'users_with_telegram' => 0,
			'users_with_bale'   => 0,
			'users_today'       => 0,
			'services_total'    => 0,
			'services_l2tp'     => 0,
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out['users_approved'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$u} WHERE status='approved'" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out['users_pending'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$u} WHERE status='pending'" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out['users_rejected'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$u} WHERE status='rejected'" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out['users_blocked'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$u} WHERE status='blocked'" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out['users_total'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$u}" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out['users_with_telegram'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$u} WHERE tg_user_id IS NOT NULL AND tg_user_id != 0" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out['users_with_bale'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$u} WHERE bale_user_id IS NOT NULL AND bale_user_id != 0" );
		$today = current_time( 'Y-m-d' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out['users_today'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$u} WHERE DATE(created_at) = %s", $today ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out['services_total'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$s} WHERE deleted_at IS NULL" );
		if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) && ! SimpleVPBot_Feature_L2tp::enabled() ) {
			$out['services_l2tp'] = 0;
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$out['services_l2tp'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$s} WHERE service_type = 'l2tp' AND deleted_at IS NULL" );
		}
		return $out;
	}

	/**
	 * User + service headline counts restricted to given SVP user ids (downline scope).
	 *
	 * @param int[] $user_ids SVP user ids.
	 * @return array<string, int>
	 */
	public static function user_service_counts_for_user_ids( $user_ids ) {
		global $wpdb;
		$ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', is_array( $user_ids ) ? $user_ids : array() ),
					static function ( $v ) {
						return $v > 0;
					}
				)
			)
		);
		$u = SimpleVPBot_Model_User::table();
		$s = SimpleVPBot_Model_Service::table();
		$empty = array(
			'users_approved'      => 0,
			'users_pending'       => 0,
			'users_rejected'      => 0,
			'users_blocked'       => 0,
			'users_total'         => 0,
			'users_with_telegram' => 0,
			'users_with_bale'     => 0,
			'users_today'         => 0,
			'services_total'      => 0,
			'services_l2tp'       => 0,
		);
		if ( empty( $ids ) ) {
			return $empty;
		}
		$in_list = implode( ',', array_map( 'absint', $ids ) );
		$today   = current_time( 'Y-m-d' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$empty['users_approved'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$u} WHERE id IN ({$in_list}) AND status='approved'" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$empty['users_pending'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$u} WHERE id IN ({$in_list}) AND status='pending'" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$empty['users_rejected'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$u} WHERE id IN ({$in_list}) AND status='rejected'" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$empty['users_blocked'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$u} WHERE id IN ({$in_list}) AND status='blocked'" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$empty['users_total'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$u} WHERE id IN ({$in_list})" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$empty['users_with_telegram'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$u} WHERE id IN ({$in_list}) AND tg_user_id IS NOT NULL AND tg_user_id != 0" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$empty['users_with_bale'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$u} WHERE id IN ({$in_list}) AND bale_user_id IS NOT NULL AND bale_user_id != 0" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$empty['users_today'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$u} WHERE id IN ({$in_list}) AND DATE(created_at) = %s", $today ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$empty['services_total'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$s} WHERE deleted_at IS NULL AND user_id IN ({$in_list})" );
		if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) && ! SimpleVPBot_Feature_L2tp::enabled() ) {
			$empty['services_l2tp'] = 0;
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$empty['services_l2tp'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$s} WHERE service_type = 'l2tp' AND deleted_at IS NULL AND user_id IN ({$in_list})" );
		}
		return $empty;
	}

	/**
	 * Per-panel Xray service active / expired counts.
	 *
	 * @return array<int, array{active:int,inactive:int,label:string}>
	 */
	public static function panel_xray_service_counts() {
		global $wpdb;
		$s = SimpleVPBot_Model_Service::table();
		$p = SimpleVPBot_Model_Panel::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT s.panel_id,
				SUM(CASE WHEN (s.expires_at IS NULL OR s.expires_at > UTC_TIMESTAMP()) THEN 1 ELSE 0 END) AS active_n,
				SUM(CASE WHEN (s.expires_at IS NOT NULL AND s.expires_at <= UTC_TIMESTAMP()) THEN 1 ELSE 0 END) AS inactive_n
			FROM {$s} s WHERE s.service_type = 'xray' AND s.deleted_at IS NULL GROUP BY s.panel_id",
			ARRAY_A
		);
		$by_panel = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $r ) {
				$pid = (int) $r['panel_id'];
				$by_panel[ $pid ] = array(
					'active'   => (int) $r['active_n'],
					'inactive' => (int) $r['inactive_n'],
					'label'    => '#' . $pid,
				);
			}
		}
		if ( class_exists( 'SimpleVPBot_Model_Panel' ) ) {
			foreach ( SimpleVPBot_Model_Panel::all_ordered() as $pn ) {
				$pid = (int) $pn->id;
				if ( ! isset( $by_panel[ $pid ] ) ) {
					$by_panel[ $pid ] = array( 'active' => 0, 'inactive' => 0, 'label' => 'پنل #' . $pid );
				}
				$lb = trim( (string) ( $pn->label ?? '' ) );
				if ( '' !== $lb ) {
					$by_panel[ $pid ]['label'] = $lb;
				}
			}
		}
		ksort( $by_panel );
		return $by_panel;
	}

	/**
	 * Per-panel Xray counts scoped to panels and downline user ids.
	 *
	 * @param int[] $panel_ids Allowed panel ids.
	 * @param int[] $scope_user_ids Downline SVP user ids.
	 * @return array<int, array{active:int,inactive:int,label:string}>
	 */
	public static function panel_xray_service_counts_scoped( $panel_ids, $scope_user_ids ) {
		$pids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', is_array( $panel_ids ) ? $panel_ids : array() ),
					static function ( $v ) {
						return $v > 0;
					}
				)
			)
		);
		$uids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', is_array( $scope_user_ids ) ? $scope_user_ids : array() ),
					static function ( $v ) {
						return $v > 0;
					}
				)
			)
		);
		$by_panel = array();
		global $wpdb;
		$s = SimpleVPBot_Model_Service::table();
		if ( ! empty( $pids ) && ! empty( $uids ) ) {
			$pi = implode( ',', array_map( 'absint', $pids ) );
			$ui = implode( ',', array_map( 'absint', $uids ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				"SELECT s.panel_id,
					SUM(CASE WHEN (s.expires_at IS NULL OR s.expires_at > UTC_TIMESTAMP()) THEN 1 ELSE 0 END) AS active_n,
					SUM(CASE WHEN (s.expires_at IS NOT NULL AND s.expires_at <= UTC_TIMESTAMP()) THEN 1 ELSE 0 END) AS inactive_n
				FROM {$s} s WHERE s.service_type = 'xray' AND s.deleted_at IS NULL
				AND s.panel_id IN ({$pi}) AND s.user_id IN ({$ui}) GROUP BY s.panel_id",
				ARRAY_A
			);
			if ( is_array( $rows ) ) {
				foreach ( $rows as $r ) {
					$pid              = (int) $r['panel_id'];
					$by_panel[ $pid ] = array(
						'active'   => (int) $r['active_n'],
						'inactive' => (int) $r['inactive_n'],
						'label'    => '#' . $pid,
					);
				}
			}
		}
		if ( class_exists( 'SimpleVPBot_Model_Panel' ) ) {
			foreach ( SimpleVPBot_Model_Panel::all_ordered() as $pn ) {
				$pid = (int) $pn->id;
				if ( ! in_array( $pid, $pids, true ) ) {
					continue;
				}
				if ( ! isset( $by_panel[ $pid ] ) ) {
					$by_panel[ $pid ] = array( 'active' => 0, 'inactive' => 0, 'label' => 'پنل #' . $pid );
				}
				$lb = trim( (string) ( $pn->label ?? '' ) );
				if ( '' !== $lb ) {
					$by_panel[ $pid ]['label'] = $lb;
				}
			}
		}
		ksort( $by_panel );
		return $by_panel;
	}

	/**
	 * Dashboard stats payload scoped for a reseller (downline users + allowed panels).
	 *
	 * @param int[] $scope_user_ids Downline SVP user ids.
	 * @param int[] $panel_ids Panel ids the reseller may use.
	 * @param int   $day_offset 0..7.
	 * @return array<string, mixed>
	 */
	public static function build_reseller_payload( $scope_user_ids, $panel_ids, $day_offset = 0 ) {
		$off    = max( 0, min( 7, (int) $day_offset ) );
		$stat_d = self::stat_date_for_offset( $off );
		$counts = self::user_service_counts_for_user_ids( $scope_user_ids );
		$panels = self::panel_xray_service_counts_scoped( $panel_ids, $scope_user_ids );
		$maxmap = self::panel_max_online_map( $stat_d );
		$lines  = array();
		foreach ( $panels as $pid => $row ) {
			$maxo    = isset( $maxmap[ $pid ] ) ? (int) $maxmap[ $pid ] : 0;
			$lines[] = array(
				'panel_id'       => $pid,
				'label'          => $row['label'],
				'xray_active'    => $row['active'],
				'xray_inactive'  => $row['inactive'],
				'max_online_day' => $maxo,
			);
		}
		return array(
			'stat_date'     => $stat_d,
			'day_offset'    => $off,
			'users'         => $counts,
			'panels'        => $lines,
			'l2tp_services' => $counts['services_l2tp'],
		);
	}

	/**
	 * Max online map for stat_date (panel_id => max).
	 *
	 * @param string $stat_date Y-m-d.
	 * @return array<int, int>
	 */
	public static function panel_max_online_map( $stat_date ) {
		if ( ! class_exists( 'SimpleVPBot_Model_Panel_Online_Daily' ) ) {
			return array();
		}
		return SimpleVPBot_Model_Panel_Online_Daily::map_for_date( $stat_date );
	}

	/**
	 * Structured payload for JSON APIs.
	 *
	 * @param int $day_offset 0..7.
	 * @return array<string, mixed>
	 */
	public static function build_payload( $day_offset = 0 ) {
		$off     = max( 0, min( 7, (int) $day_offset ) );
		$stat_d = self::stat_date_for_offset( $off );
		$counts  = self::user_service_counts();
		$panels  = self::panel_xray_service_counts();
		$maxmap  = self::panel_max_online_map( $stat_d );
		$lines   = array();
		foreach ( $panels as $pid => $row ) {
			$maxo = isset( $maxmap[ $pid ] ) ? (int) $maxmap[ $pid ] : 0;
			$lines[] = array(
				'panel_id'       => $pid,
				'label'          => $row['label'],
				'xray_active'    => $row['active'],
				'xray_inactive'  => $row['inactive'],
				'max_online_day' => $maxo,
			);
		}
		return array(
			'stat_date'       => $stat_d,
			'day_offset'      => $off,
			'users'           => $counts,
			'panels'          => $lines,
			'l2tp_services'   => $counts['services_l2tp'],
		);
	}

	/**
	 * Persian-friendly multi-line text for bot message.
	 *
	 * @param int $day_offset 0..7.
	 * @return string
	 */
	public static function format_text( $day_offset = 0 ) {
		return self::format_payload_text( self::build_payload( $day_offset ) );
	}

	/**
	 * Persian-friendly stats text scoped to reseller downline + panels.
	 *
	 * @param int[] $scope_user_ids Downline user ids.
	 * @param int[] $panel_ids      Allowed panel ids.
	 * @param int   $day_offset     0..7.
	 * @return string
	 */
	public static function format_reseller_text( $scope_user_ids, $panel_ids, $day_offset = 0 ) {
		return self::format_payload_text(
			self::build_reseller_payload( $scope_user_ids, $panel_ids, $day_offset )
		);
	}

	/**
	 * Format stats payload as bot message text.
	 *
	 * @param array<string, mixed> $data From build_payload / build_reseller_payload.
	 * @return string
	 */
	public static function format_payload_text( array $data ) {
		$u   = $data['users'];
		$d   = (string) $data['stat_date'];
		$lbl = 0 === (int) $data['day_offset'] ? 'امروز (' . $d . ')' : ( 'روز ' . (int) $data['day_offset'] . ' قبل — ' . $d );
		$t   = "📊 آمار\n➖➖➖➖➖➖➖➖\n";
		$t  .= '📅 ' . $lbl . "\n\n";
		$t  .= '✅ تأییدشده: ' . (int) $u['users_approved'] . "\n";
		$t  .= '⏳ در انتظار: ' . (int) $u['users_pending'] . "\n";
		$t  .= '❌ رد شده: ' . (int) $u['users_rejected'] . "\n";
		$t  .= '🚫 مسدود: ' . (int) $u['users_blocked'] . "\n";
		$t  .= '👥 کل ربات: ' . (int) $u['users_total'] . "\n";
		$t  .= '📱 با تلگرام: ' . (int) $u['users_with_telegram'] . "\n";
		$t  .= '💬 با بله: ' . (int) $u['users_with_bale'] . "\n";
		$t  .= '🆕 ثبت امروز: ' . (int) $u['users_today'] . "\n\n";
		$t  .= '📡 سرویس‌ها (کل): ' . (int) $u['services_total'];
		if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) && SimpleVPBot_Feature_L2tp::enabled() ) {
			$t .= ' · L2TP: ' . (int) $u['services_l2tp'];
		}
		$t .= "\n\n";
		$t  .= "➖ پنل‌ها (Xray فعال / منقضی / حداکثر آنلاین روز)\n";
		foreach ( $data['panels'] as $pl ) {
			$mx = (int) $pl['max_online_day'] > 0 ? (string) (int) $pl['max_online_day'] : '—';
			$t .= '· ' . (string) $pl['label'] . ': ';
			$t .= (int) $pl['xray_active'] . ' / ' . (int) $pl['xray_inactive'] . ' / ' . $mx . "\n";
		}
		if ( empty( $data['panels'] ) ) {
			$t .= "—\n";
		}
		return $t;
	}

	/**
	 * Inline keyboard rows: day picker (glass).
	 *
	 * @param int $current_offset Currently displayed offset.
	 * @return array<string, mixed>
	 */
	public static function inline_day_picker( $current_offset = 0 ) {
		$cur = max( 0, min( 7, (int) $current_offset ) );
		$glass = class_exists( 'SimpleVPBot_Keyboards' )
			? array( 'SimpleVPBot_Keyboards', 'glass_button_text' )
			: function ( $t, $max = 64 ) {
				return (string) $t;
			};
		$row = array();
		for ( $i = 0; $i <= 7; $i++ ) {
			$label = 0 === $i ? 'امروز' : ( '-' . $i );
			if ( $i === $cur ) {
				$label = '·' . $label . '·';
			}
			$txt = is_callable( $glass ) ? call_user_func( $glass, $label, 12 ) : $label;
			$row[] = array(
				'text'          => $txt,
				'callback_data' => 'pnl:st:' . $i,
			);
		}
		$chunks = array_chunk( $row, 4 );
		$ik      = array();
		foreach ( $chunks as $ch ) {
			$ik[] = $ch;
		}
		return array( 'inline_keyboard' => $ik );
	}
}
