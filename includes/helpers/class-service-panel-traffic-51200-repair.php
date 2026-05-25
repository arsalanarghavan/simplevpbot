<?php
/**
 * Fix the known 51200 GB traffic cap bug (DB + panel) for affected services only.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Service_Panel_Traffic_51200_Repair
 */
class SimpleVPBot_Service_Panel_Traffic_51200_Repair {

	const BATCH_SIZE = 30;

	/**
	 * @param array<string, mixed> $opts panel_id (required), dry_run, offset, limit, inbound_map.
	 * @return array<string, mixed>
	 */
	public static function run( array $opts = array() ) {
		$panel_id = max( 0, (int) ( $opts['panel_id'] ?? 0 ) );
		$dry_run  = ! empty( $opts['dry_run'] );
		$offset   = max( 0, (int) ( $opts['offset'] ?? 0 ) );
		$limit    = max( 1, min( 50, (int) ( $opts['limit'] ?? self::BATCH_SIZE ) ) );

		if ( $panel_id < 1 ) {
			return array( 'ok' => false, 'message' => __( 'پنل را انتخاب کنید.', 'simplevpbot' ) );
		}
		if ( ! class_exists( 'SimpleVPBot_Inbound_Linker' ) ) {
			return array( 'ok' => false, 'message' => 'no_linker' );
		}

		$inbound_map = null;
		if ( class_exists( 'SimpleVPBot_Service_Panel_Inbound_Map' ) ) {
			if ( isset( $opts['inbound_map'] ) && is_array( $opts['inbound_map'] ) ) {
				$inbound_map = SimpleVPBot_Service_Panel_Inbound_Map::normalize_map( $opts['inbound_map'] );
			} else {
				$inbound_map = SimpleVPBot_Service_Panel_Inbound_Map::get_map( $panel_id );
			}
		}

		$totals = array(
			'fixed'     => 0,
			'skipped'   => 0,
			'failed'    => 0,
			'no_source' => 0,
		);
		$errors = array();

		$rows  = self::fetch_batch( $panel_id, $offset, $limit );
		$total = self::count_rows( $panel_id );

		foreach ( $rows as $svc ) {
			if ( ! is_object( $svc ) ) {
				continue;
			}
			$cur       = (int) ( $svc->total_traffic ?? 0 );
			$is_bug    = SimpleVPBot_Inbound_Linker::is_51200_cap_bug_bytes( $cur );
			$is_wrong50 = SimpleVPBot_Inbound_Linker::is_wrong_50gb_fallback_bytes( $cur );

			if ( ! $is_bug && ! $is_wrong50 ) {
				++$totals['skipped'];
				continue;
			}

			$svc_work = class_exists( 'SimpleVPBot_Service_Panel_Inbound_Map' )
				? SimpleVPBot_Service_Panel_Inbound_Map::service_with_resolved_inbound( $svc, $inbound_map )
				: $svc;
			$email = trim( (string) ( $svc_work->email ?? '' ) );
			$iid   = (int) ( $svc_work->inbound_id ?? 0 );
			if ( '' === $email || $iid < 1 ) {
				++$totals['failed'];
				continue;
			}

			$resolved = SimpleVPBot_Inbound_Linker::resolve_quota_bytes_for_51200_repair(
				$svc,
				$panel_id,
				$iid,
				$email,
				! $dry_run
			);
			if ( false === $resolved || (int) ( $resolved['bytes'] ?? 0 ) < 1 ) {
				++$totals['no_source'];
				continue;
			}
			$fixed = (int) $resolved['bytes'];
			if ( ! $is_bug && abs( $fixed - $cur ) < (int) SimpleVPBot_Service_Renew::BYTES_PER_GB / 2 ) {
				++$totals['skipped'];
				continue;
			}

			if ( $dry_run ) {
				++$totals['fixed'];
				continue;
			}

			$fixed_gb = (int) max( 1, (int) round( $fixed / SimpleVPBot_Service_Renew::BYTES_PER_GB ) );
			$res      = SimpleVPBot_Service_Renew::set_panel_client_quota_gb(
				$panel_id,
				$iid,
				$email,
				$fixed_gb,
				array(
					'sync_db'       => true,
					'service_id'    => (int) ( $svc->id ?? 0 ),
					'xui_client_id' => (string) ( $svc->xui_client_uuid ?? $svc->xui_client_id ?? '' ),
					'user_id'       => (int) ( $svc->user_id ?? 0 ),
				)
			);
			if ( ! empty( $res['ok'] ) ) {
				++$totals['fixed'];
			} else {
				++$totals['failed'];
				if ( count( $errors ) < 15 ) {
					$errors[] = array(
						'service_id' => (int) ( $svc->id ?? 0 ),
						'email'      => $email,
						'from_bytes' => $cur,
						'to_bytes'   => $fixed,
						'source'     => (string) ( $resolved['source'] ?? '' ),
						'reason'     => (string) ( $res['message'] ?? 'failed' ),
					);
				}
			}
		}

		$next_offset = $offset + count( $rows );
		$done        = $next_offset >= $total || count( $rows ) < 1;

		return array(
			'ok'          => true,
			'dry_run'     => $dry_run,
			'totals'      => $totals,
			'errors'      => $errors,
			'done'        => $done,
			'next_offset' => $done ? $total : $next_offset,
			'total'       => $total,
			'processed'   => count( $rows ),
		);
	}

	/**
	 * WHERE clause for 51200 bug rows only.
	 *
	 * @param string $col_prefix e.g. "s." for joined queries.
	 * @return array{sql:string, args:array<int, mixed>}
	 */
	private static function where_51200_sql( $col_prefix = '' ) {
		$marker = (int) SimpleVPBot_Inbound_Linker::MISCALE_CAP_MARKER_GB;
		$gb     = (int) SimpleVPBot_Service_Renew::BYTES_PER_GB;
		$exact  = (int) ( $marker * $gb );
		$c      = '' !== $col_prefix ? $col_prefix : '';
		return array(
			'sql'  => "({$c}total_traffic = %d OR {$c}total_traffic = %d OR ({$c}total_traffic > %d AND {$c}total_traffic <= %d))",
			'args' => array( $marker, $exact, $exact, $exact + $gb - 1 ),
		);
	}

	/**
	 * Rows wrongly set to 50 GB by the old fallback (recoverable when plan/remark says otherwise).
	 *
	 * @param string $col_prefix e.g. "s.".
	 * @return array{sql:string, args:array<int, mixed>}
	 */
	private static function where_wrong_50gb_sql( $col_prefix = 's.' ) {
		$fifty = (int) ( SimpleVPBot_Inbound_Linker::WRONG_FALLBACK_GB * SimpleVPBot_Service_Renew::BYTES_PER_GB );
		$c     = $col_prefix;
		return array(
			'sql'  => "({$c}total_traffic = %d AND p.id IS NOT NULL AND p.traffic_gb >= 1 AND p.traffic_gb <= 2048 AND p.traffic_gb NOT IN (51200, 50) AND (p.pricing_type IS NULL OR p.pricing_type = '' OR p.pricing_type = 'fixed')) OR ({$c}total_traffic = %d AND {$c}remark REGEXP %s)",
			'args' => array( $fifty, $fifty, '· [0-9]{1,4} GB' ),
		);
	}

	/**
	 * Combined candidate filter (51200 bug OR recoverable wrong-50).
	 *
	 * @param string $col_prefix Prefix for service columns in joined queries.
	 * @return array{sql:string, args:array<int, mixed>}
	 */
	private static function where_candidates_sql( $col_prefix = 's.' ) {
		$w512 = self::where_51200_sql( $col_prefix );
		$w50  = self::where_wrong_50gb_sql( $col_prefix );
		return array(
			'sql'  => '(' . $w512['sql'] . ' OR ' . $w50['sql'] . ')',
			'args' => array_merge( $w512['args'], $w50['args'] ),
		);
	}

	/**
	 * @param int $panel_id Panel id.
	 * @param int $offset   Offset.
	 * @param int $limit    Limit.
	 * @return array<int, object>
	 */
	private static function fetch_batch( $panel_id, $offset, $limit ) {
		global $wpdb;
		$s_t  = SimpleVPBot_Model_Service::table();
		$p_t  = class_exists( 'SimpleVPBot_Model_Plan' ) ? SimpleVPBot_Model_Plan::table() : '';
		$wc   = self::where_candidates_sql( 's.' );
		$base = "s.deleted_at IS NULL AND s.panel_id = %d AND s.inbound_id > 0 AND TRIM(s.email) <> '' AND (s.service_type IS NULL OR s.service_type = '' OR s.service_type = 'xray') AND {$wc['sql']}";
		$args = array_merge( array( (int) $panel_id ), $wc['args'], array( (int) $limit, (int) $offset ) );
		if ( '' !== $p_t ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = "SELECT s.*, p.traffic_gb AS plan_traffic_gb, p.pricing_type AS plan_pricing_type, p.traffic_gb_min AS plan_traffic_gb_min, p.traffic_gb_max AS plan_traffic_gb_max FROM {$s_t} s LEFT JOIN {$p_t} p ON p.id = s.plan_id WHERE {$base} ORDER BY s.id ASC LIMIT %d OFFSET %d";
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = "SELECT s.*, 0 AS plan_traffic_gb, 'fixed' AS plan_pricing_type, 0 AS plan_traffic_gb_min, 0 AS plan_traffic_gb_max FROM {$s_t} s WHERE {$base} ORDER BY s.id ASC LIMIT %d OFFSET %d";
		}
		return $wpdb->get_results( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore
	}

	/**
	 * @param int $panel_id Panel id.
	 * @return int
	 */
	private static function count_rows( $panel_id ) {
		global $wpdb;
		$s_t  = SimpleVPBot_Model_Service::table();
		$p_t  = class_exists( 'SimpleVPBot_Model_Plan' ) ? SimpleVPBot_Model_Plan::table() : '';
		$wc   = self::where_candidates_sql( 's.' );
		$base = "s.deleted_at IS NULL AND s.panel_id = %d AND s.inbound_id > 0 AND TRIM(s.email) <> '' AND (s.service_type IS NULL OR s.service_type = '' OR s.service_type = 'xray') AND {$wc['sql']}";
		$args = array_merge( array( (int) $panel_id ), $wc['args'] );
		if ( '' !== $p_t ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = "SELECT COUNT(*) FROM {$s_t} s LEFT JOIN {$p_t} p ON p.id = s.plan_id WHERE {$base}";
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = "SELECT COUNT(*) FROM {$s_t} s WHERE {$base}";
		}
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore
	}
}
