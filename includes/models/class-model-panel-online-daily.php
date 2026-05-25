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
}
