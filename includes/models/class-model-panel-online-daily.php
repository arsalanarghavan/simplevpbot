<?php
/**
 * Daily max online count per X-UI panel (sampled by cron).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Panel_Online_Daily
 */
class SimpleVPBot_Model_Panel_Online_Daily {

	/**
	 * Table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_panel_online_daily';
	}

	/**
	 * Record a sample: max_online = GREATEST(existing, current).
	 *
	 * @param int    $panel_id svp_panels.id.
	 * @param string $stat_date Y-m-d (site calendar day).
	 * @param int    $current_online Current count from panel API.
	 */
	public static function upsert_max( $panel_id, $stat_date, $current_online ) {
		global $wpdb;
		$pid = (int) $panel_id;
		$d   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $stat_date ) ? (string) $stat_date : gmdate( 'Y-m-d' );
		$n   = max( 0, (int) $current_online );
		$now = current_time( 'mysql' );
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$t} (panel_id, stat_date, max_online, updated_at) VALUES (%d, %s, %d, %s)
				ON DUPLICATE KEY UPDATE max_online = GREATEST(max_online, %d), updated_at = %s",
				$pid,
				$d,
				$n,
				$now,
				$n,
				$now
			)
		);
	}

	/**
	 * Max online for one panel on a date (0 if none).
	 *
	 * @param int    $panel_id Panel id.
	 * @param string $stat_date Y-m-d.
	 * @return int
	 */
	public static function max_for_panel_date( $panel_id, $stat_date ) {
		global $wpdb;
		$d = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $stat_date ) ? (string) $stat_date : gmdate( 'Y-m-d' );
		$v = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT max_online FROM ' . self::table() . ' WHERE panel_id = %d AND stat_date = %s LIMIT 1',
				(int) $panel_id,
				$d
			)
		); // phpcs:ignore
		return null !== $v ? (int) $v : 0;
	}

	/**
	 * All rows for a calendar date keyed by panel_id.
	 *
	 * @param string $stat_date Y-m-d.
	 * @return array<int, int> panel_id => max_online
	 */
	public static function map_for_date( $stat_date ) {
		global $wpdb;
		$d = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $stat_date ) ? (string) $stat_date : gmdate( 'Y-m-d' );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT panel_id, max_online FROM ' . self::table() . ' WHERE stat_date = %s',
				$d
			),
			ARRAY_A
		); // phpcs:ignore
		$out = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $r ) {
				$out[ (int) $r['panel_id'] ] = (int) $r['max_online'];
			}
		}
		return $out;
	}

	/**
	 * Sum of max_online per calendar day for the last N days (site timezone), including days with no rows as 0.
	 *
	 * @param int $days Number of calendar days including today (1–90).
	 * @return array<int, array{date:string,totalMaxOnline:int}>
	 */
	public static function daily_totals_last_days( $days = 7 ) {
		$days = max( 1, min( 90, (int) $days ) );
		global $wpdb;
		$t = self::table();
		try {
			$end = new DateTimeImmutable( 'today', wp_timezone() );
		} catch ( \Exception $e ) {
			$end = new DateTimeImmutable( 'today' );
		}
		$start = $end->modify( '-' . ( $days - 1 ) . ' days' );
		$from  = $start->format( 'Y-m-d' );
		$to    = $end->format( 'Y-m-d' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT stat_date, SUM(max_online) AS total FROM {$t} WHERE stat_date >= %s AND stat_date <= %s GROUP BY stat_date ORDER BY stat_date ASC",
				$from,
				$to
			),
			ARRAY_A
		);
		$by_date = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $r ) {
				if ( ! is_array( $r ) || ! isset( $r['stat_date'] ) ) {
					continue;
				}
				$by_date[ (string) $r['stat_date'] ] = isset( $r['total'] ) ? (int) $r['total'] : 0;
			}
		}
		$series = array();
		for ( $d = $start; $d <= $end; $d = $d->modify( '+1 day' ) ) {
			$key = $d->format( 'Y-m-d' );
			$series[] = array(
				'date'             => $key,
				'totalMaxOnline'   => isset( $by_date[ $key ] ) ? $by_date[ $key ] : 0,
			);
		}
		return $series;
	}

	/**
	 * Sum of max_online per calendar day for the last N days, limited to given panel ids.
	 *
	 * @param int   $days Number of calendar days including today (1–90).
	 * @param int[] $panel_ids Panel ids (empty = empty series with dates filled).
	 * @return array<int, array{date:string,totalMaxOnline:int}>
	 */
	public static function daily_totals_last_days_for_panels( $days = 7, $panel_ids = array() ) {
		$days = max( 1, min( 90, (int) $days ) );
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
		try {
			$end = new DateTimeImmutable( 'today', wp_timezone() );
		} catch ( \Exception $e ) {
			$end = new DateTimeImmutable( 'today' );
		}
		$start = $end->modify( '-' . ( $days - 1 ) . ' days' );
		$from  = $start->format( 'Y-m-d' );
		$to    = $end->format( 'Y-m-d' );
		$by_date = array();
		if ( ! empty( $pids ) ) {
			global $wpdb;
			$t       = self::table();
			$in_list = implode( ',', array_map( 'absint', $pids ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT stat_date, SUM(max_online) AS total FROM {$t} WHERE panel_id IN ({$in_list}) AND stat_date >= %s AND stat_date <= %s GROUP BY stat_date ORDER BY stat_date ASC",
					$from,
					$to
				),
				ARRAY_A
			);
			if ( is_array( $rows ) ) {
				foreach ( $rows as $r ) {
					if ( ! is_array( $r ) || ! isset( $r['stat_date'] ) ) {
						continue;
					}
					$by_date[ (string) $r['stat_date'] ] = isset( $r['total'] ) ? (int) $r['total'] : 0;
				}
			}
		}
		$series = array();
		for ( $d = $start; $d <= $end; $d = $d->modify( '+1 day' ) ) {
			$key      = $d->format( 'Y-m-d' );
			$series[] = array(
				'date'           => $key,
				'totalMaxOnline' => isset( $by_date[ $key ] ) ? $by_date[ $key ] : 0,
			);
		}
		return $series;
	}

	/**
	 * Site calendar date for today (Y-m-d).
	 *
	 * @return string
	 */
	public static function today_stat_date() {
		if ( class_exists( 'SimpleVPBot_Admin_Dashboard_Stats' ) ) {
			return SimpleVPBot_Admin_Dashboard_Stats::stat_date_for_offset( 0 );
		}
		try {
			return ( new DateTimeImmutable( 'today', wp_timezone() ) )->format( 'Y-m-d' );
		} catch ( \Exception $e ) {
			return gmdate( 'Y-m-d' );
		}
	}

	/**
	 * totalMaxOnline for today in a daily series (0 if missing).
	 *
	 * @param array<int, array{date:string,totalMaxOnline:int}> $series Series.
	 * @return int
	 */
	public static function today_total_from_series( array $series ) {
		$today = self::today_stat_date();
		foreach ( $series as $pt ) {
			if ( is_array( $pt ) && isset( $pt['date'] ) && (string) $pt['date'] === $today ) {
				return isset( $pt['totalMaxOnline'] ) ? (int) $pt['totalMaxOnline'] : 0;
			}
		}
		return 0;
	}

	/**
	 * Set totalMaxOnline on the today point in a series.
	 *
	 * @param array<int, array{date:string,totalMaxOnline:int}> $series Series.
	 * @param int                                               $total New total for today.
	 * @return array<int, array{date:string,totalMaxOnline:int}>
	 */
	public static function set_today_total_in_series( array $series, $total ) {
		$today = self::today_stat_date();
		$n     = max( 0, (int) $total );
		foreach ( $series as $i => $pt ) {
			if ( ! is_array( $pt ) || ! isset( $pt['date'] ) ) {
				continue;
			}
			if ( (string) $pt['date'] === $today ) {
				$series[ $i ]['totalMaxOnline'] = $n;
				return $series;
			}
		}
		return $series;
	}

	/**
	 * Upsert live counts and raise today's aggregate in the series.
	 *
	 * @param array<int, array{date:string,totalMaxOnline:int}> $series Series from daily_totals_*.
	 * @param array<int, array<string, mixed>>                  $samples panelId + onlineNow (+ ok).
	 * @return array<int, array{date:string,totalMaxOnline:int}>
	 */
	public static function merge_live_samples_into_series( array $series, array $samples ) {
		if ( empty( $samples ) ) {
			return $series;
		}
		$today    = self::today_stat_date();
		$live_sum = 0;
		foreach ( $samples as $snap ) {
			if ( ! is_array( $snap ) || empty( $snap['ok'] ) || ! isset( $snap['onlineNow'], $snap['panelId'] ) ) {
				continue;
			}
			$pid = (int) $snap['panelId'];
			if ( $pid < 1 ) {
				continue;
			}
			$n = max( 0, (int) $snap['onlineNow'] );
			self::upsert_max( $pid, $today, $n );
			$live_sum += $n;
		}
		if ( $live_sum < 1 ) {
			return $series;
		}
		$db_today = self::today_total_from_series( $series );
		return self::set_today_total_in_series( $series, max( $db_today, $live_sum ) );
	}

	/**
	 * Merge live panel snapshots into today's chart point.
	 *
	 * @param array<int, array{date:string,totalMaxOnline:int}> $series Series.
	 * @param array<int, array<string, mixed>>                  $live_snapshots REST livePanelSnapshots.
	 * @return array<int, array{date:string,totalMaxOnline:int}>
	 */
	public static function merge_live_snapshots_into_series( array $series, array $live_snapshots ) {
		return self::merge_live_samples_into_series( $series, $live_snapshots );
	}

	/**
	 * Merge cached live transients (overview tab) into today's chart point.
	 *
	 * @param array<int, array{date:string,totalMaxOnline:int}> $series Series.
	 * @param int[]                                               $panel_ids Panel ids.
	 * @return array<int, array{date:string,totalMaxOnline:int}>
	 */
	public static function merge_cached_live_into_series( array $series, array $panel_ids ) {
		if ( ! class_exists( 'SimpleVPBot_Dashboard_Panel_Live' ) ) {
			return $series;
		}
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
		if ( empty( $pids ) ) {
			return $series;
		}
		$samples = array();
		foreach ( $pids as $pid ) {
			$cached = get_transient( SimpleVPBot_Dashboard_Panel_Live::transient_key( $pid ) );
			if ( ! is_array( $cached ) || empty( $cached['ok'] ) || ! isset( $cached['onlineNow'] ) ) {
				continue;
			}
			$samples[] = array(
				'panelId'   => $pid,
				'onlineNow' => (int) $cached['onlineNow'],
				'ok'        => true,
			);
		}
		return self::merge_live_samples_into_series( $series, $samples );
	}

	/**
	 * When today's aggregate is still zero, run panel-online cron once (throttled) and reload series.
	 *
	 * @param array<int, array{date:string,totalMaxOnline:int}> $series Current series.
	 * @param int                                                 $days Days window for reload.
	 * @param int[]                                               $panel_ids Empty = all panels; else scoped ids.
	 * @return array<int, array{date:string,totalMaxOnline:int}>
	 */
	public static function maybe_self_heal_today_series( array $series, $days = 7, array $panel_ids = array() ) {
		if ( self::today_total_from_series( $series ) > 0 ) {
			return $series;
		}
		if ( get_transient( 'svp_dash_online_sample_lock' ) ) {
			return $series;
		}
		if ( ! class_exists( 'SimpleVPBot_Cron_Panel_Online' ) ) {
			return $series;
		}
		set_transient( 'svp_dash_online_sample_lock', 1, 120 );
		SimpleVPBot_Cron_Panel_Online::run();
		$scoped = array_values(
			array_filter(
				array_map( 'intval', $panel_ids ),
				static function ( $v ) {
					return $v > 0;
				}
			)
		);
		if ( ! empty( $scoped ) ) {
			return self::daily_totals_last_days_for_panels( $days, $scoped );
		}
		return self::daily_totals_last_days( $days );
	}
}
