<?php
/**
 * Tier row for a wholesale line (cumulative thresholds + unit price).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Reseller_Wholesale_Tier
 */
class SimpleVPBot_Model_Reseller_Wholesale_Tier {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_reseller_wholesale_tiers';
	}

	/**
	 * @param int $line_id Line id.
	 * @return array<int, object>
	 */
	public static function by_line( $line_id ) {
		global $wpdb;
		$lid = (int) $line_id;
		if ( $lid < 1 ) {
			return array();
		}
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE line_id = %d ORDER BY sort_order ASC, id ASC',
				$lid
			)
		); // phpcs:ignore
	}

	/**
	 * Batch-load tiers grouped by line id.
	 *
	 * @param array<int, int> $line_ids Line ids.
	 * @return array<int, array<int, object>>
	 */
	public static function by_line_ids( array $line_ids ) {
		global $wpdb;
		$ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $line_ids ),
					static function ( $v ) {
						return (int) $v > 0;
					}
				)
			)
		);
		if ( empty( $ids ) ) {
			return array();
		}
		$ph   = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . self::table() . " WHERE line_id IN ({$ph}) ORDER BY line_id ASC, sort_order ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
				$ids
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$out = array();
		foreach ( (array) $rows as $row ) {
			if ( ! is_object( $row ) ) {
				continue;
			}
			$lid = (int) ( $row->line_id ?? 0 );
			if ( $lid < 1 ) {
				continue;
			}
			if ( ! isset( $out[ $lid ] ) ) {
				$out[ $lid ] = array();
			}
			$out[ $lid ][] = $row;
		}
		return $out;
	}

	/**
	 * Replace all tiers for a line (transactional).
	 *
	 * @param int                                $line_id Line id.
	 * @param array<int, array<string, mixed>> $tiers   Rows with sort_order, price_per_gb, min_total_gb, min_total_toman.
	 */
	public static function replace_for_line( $line_id, array $tiers ) {
		global $wpdb;
		$lid = (int) $line_id;
		if ( $lid < 1 ) {
			return;
		}
		$t = self::table();
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		try {
			$wpdb->delete( $t, array( 'line_id' => $lid ), array( '%d' ) );
			foreach ( $tiers as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$wpdb->insert(
					$t,
					array(
						'line_id'          => $lid,
						'sort_order'       => (int) ( $row['sort_order'] ?? 0 ),
						'price_per_gb'     => round( (float) ( $row['price_per_gb'] ?? 0 ), 4 ),
						'min_total_gb'     => max( 0, (int) ( $row['min_total_gb'] ?? 0 ) ),
						'min_total_toman'  => round( max( 0, (float) ( $row['min_total_toman'] ?? 0 ) ), 2 ),
					),
					array( '%d', '%d', '%f', '%d', '%f' )
				);
			}
			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		} catch ( \Throwable $e ) { // phpcs:ignore
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}
	}

	/**
	 * Delete all tiers for line (before deleting line).
	 *
	 * @param int $line_id Line id.
	 */
	public static function delete_all_for_line( $line_id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'line_id' => (int) $line_id ), array( '%d' ) );
	}
}
