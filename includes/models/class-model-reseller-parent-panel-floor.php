<?php
/**
 * Optional per-panel minimum unit price (toman/GB) set by a parent reseller for a direct child reseller.
 * Effective sales floor for the child is max(admin wholesale from {@see SimpleVPBot_Model_Reseller_Panel_Price}, parent row here).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Reseller_Parent_Panel_Floor
 */
class SimpleVPBot_Model_Reseller_Parent_Panel_Floor {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_reseller_parent_panel_floors';
	}

	/**
	 * Minimum unit price parent enforced for child on a panel, or 0 if none.
	 *
	 * @param int $parent_svp_user_id Parent reseller svp_users.id.
	 * @param int $child_svp_user_id  Child reseller svp_users.id.
	 * @param int $panel_id           Panel id.
	 * @return float
	 */
	public static function get_min_price( $parent_svp_user_id, $child_svp_user_id, $panel_id ) {
		global $wpdb;
		$a = (int) $parent_svp_user_id;
		$c = (int) $child_svp_user_id;
		$p = (int) $panel_id;
		if ( $a < 1 || $c < 1 || $p < 1 ) {
			return 0.0;
		}
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$v = $wpdb->get_var( $wpdb->prepare( "SELECT min_price_per_gb FROM {$t} WHERE parent_svp_user_id = %d AND child_svp_user_id = %d AND panel_id = %d LIMIT 1", $a, $c, $p ) );
		return $v !== null ? (float) $v : 0.0;
	}

	/**
	 * Rows for one parent + child pair.
	 *
	 * @param int $parent_svp_user_id Parent.
	 * @param int $child_svp_user_id  Child.
	 * @return array<int, object>
	 */
	public static function list_for_parent_child( $parent_svp_user_id, $child_svp_user_id ) {
		global $wpdb;
		$a = (int) $parent_svp_user_id;
		$c = (int) $child_svp_user_id;
		if ( $a < 1 || $c < 1 ) {
			return array();
		}
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE parent_svp_user_id = %d AND child_svp_user_id = %d ORDER BY panel_id ASC',
				$a,
				$c
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Batch-load parent floor rows for many direct child resellers.
	 *
	 * @param int           $parent_svp_user_id Parent reseller id.
	 * @param array<int,int> $child_ids         Child reseller ids.
	 * @return array<string, array<int, object>> Keyed by child id string.
	 */
	public static function map_for_parent_children( $parent_svp_user_id, array $child_ids ) {
		global $wpdb;
		$a = (int) $parent_svp_user_id;
		$ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $child_ids ),
					static function ( $v ) {
						return (int) $v > 0;
					}
				)
			)
		);
		$out = array();
		if ( $a < 1 || empty( $ids ) ) {
			return $out;
		}
		$ph = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . " WHERE parent_svp_user_id = %d AND child_svp_user_id IN ({$ph}) ORDER BY child_svp_user_id ASC, panel_id ASC",
				array_merge( array( $a ), $ids )
			)
		);
		foreach ( (array) $rows as $row ) {
			if ( ! is_object( $row ) ) {
				continue;
			}
			$key = (string) (int) ( $row->child_svp_user_id ?? 0 );
			if ( $key === '0' ) {
				continue;
			}
			if ( ! isset( $out[ $key ] ) ) {
				$out[ $key ] = array();
			}
			$out[ $key ][] = $row;
		}
		return $out;
	}

	/**
	 * All parent-imposed floors where child is the given reseller (for admin overview).
	 *
	 * @param int $child_svp_user_id Child reseller id.
	 * @return array<int, object>
	 */
	public static function list_all_for_child( $child_svp_user_id ) {
		global $wpdb;
		$c = (int) $child_svp_user_id;
		if ( $c < 1 ) {
			return array();
		}
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE child_svp_user_id = %d ORDER BY parent_svp_user_id ASC, panel_id ASC',
				$c
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Replace all rows for one parent/child pair (transactional).
	 *
	 * @param int                                $parent_svp_user_id Parent reseller id.
	 * @param int                                $child_svp_user_id  Child reseller id (direct invite).
	 * @param array<int, array<string, mixed>> $rows               Each: panel_id, min_price_per_gb.
	 * @return void
	 */
	public static function replace_all_for_parent_child( $parent_svp_user_id, $child_svp_user_id, array $rows ) {
		global $wpdb;
		$a = (int) $parent_svp_user_id;
		$c = (int) $child_svp_user_id;
		if ( $a < 1 || $c < 1 ) {
			return;
		}
		$t = self::table();
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		try {
			$wpdb->delete(
				$t,
				array(
					'parent_svp_user_id' => $a,
					'child_svp_user_id'  => $c,
				),
				array( '%d', '%d' )
			);
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$pid = (int) ( $row['panel_id'] ?? 0 );
				$mn  = isset( $row['min_price_per_gb'] ) ? (float) $row['min_price_per_gb'] : 0.0;
				if ( $pid < 1 || $mn < 0 ) {
					continue;
				}
				$wpdb->insert(
					$t,
					array(
						'parent_svp_user_id' => $a,
						'child_svp_user_id'  => $c,
						'panel_id'           => $pid,
						'min_price_per_gb'   => round( $mn, 4 ),
						'updated_at'         => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%d', '%f', '%s' )
				);
			}
			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		} catch ( Throwable $e ) { // phpcs:ignore
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}
	}
}
