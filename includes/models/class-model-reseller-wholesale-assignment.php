<?php
/**
 * Which wholesale lines a reseller may sell against.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Reseller_Wholesale_Assignment
 */
class SimpleVPBot_Model_Reseller_Wholesale_Assignment {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_reseller_wholesale_line_assignments';
	}

	/**
	 * Line ids assigned to reseller.
	 *
	 * @param int $reseller_svp_user_id Id.
	 * @return array<int, int>
	 */
	public static function line_ids_for_reseller( $reseller_svp_user_id ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		if ( $r < 1 ) {
			return array();
		}
		$cols = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT line_id FROM ' . self::table() . ' WHERE reseller_svp_user_id = %d ORDER BY line_id ASC',
				$r
			)
		); // phpcs:ignore
		return array_map( 'intval', (array) $cols );
	}

	/**
	 * @param int $reseller_svp_user_id Reseller.
	 * @param int $line_id              Line.
	 * @return bool
	 */
	public static function is_assigned( $reseller_svp_user_id, $line_id ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		$l = (int) $line_id;
		if ( $r < 1 || $l < 1 ) {
			return false;
		}
		$n = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table() . ' WHERE reseller_svp_user_id = %d AND line_id = %d',
				$r,
				$l
			)
		); // phpcs:ignore
		return $n > 0;
	}

	/**
	 * Replace assignments for one reseller.
	 *
	 * @param int               $reseller_svp_user_id Id.
	 * @param array<int, int> $line_ids             Unique line ids.
	 */
	public static function replace_for_reseller( $reseller_svp_user_id, array $line_ids ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		if ( $r < 1 ) {
			return;
		}
		$t = self::table();
		$ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $line_ids ),
					static function ( $x ) {
						return $x > 0;
					}
				)
			)
		);
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		try {
			$wpdb->delete( $t, array( 'reseller_svp_user_id' => $r ), array( '%d' ) );
			foreach ( $ids as $lid ) {
				if ( ! class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Line' ) || ! SimpleVPBot_Model_Reseller_Wholesale_Line::find( $lid ) ) {
					continue;
				}
				$wpdb->insert(
					$t,
					array(
						'reseller_svp_user_id' => $r,
						'line_id'              => $lid,
					),
					array( '%d', '%d' )
				);
			}
			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		} catch ( \Throwable $e ) { // phpcs:ignore
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}
	}
}
