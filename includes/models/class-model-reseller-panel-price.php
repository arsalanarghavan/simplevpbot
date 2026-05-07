<?php
/**
 * Admin-set wholesale price per GB for a reseller on a panel.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Reseller_Panel_Price
 */
class SimpleVPBot_Model_Reseller_Panel_Price {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_reseller_panel_prices';
	}

	/**
	 * Unit price (toman per GB) or 0 if unset.
	 *
	 * @param int $reseller_svp_user_id Reseller svp_users.id.
	 * @param int $panel_id             Panel id.
	 * @return float
	 */
	public static function get_unit_price( $reseller_svp_user_id, $panel_id ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		$p = (int) $panel_id;
		if ( $r < 1 || $p < 1 ) {
			return 0.0;
		}
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$v = $wpdb->get_var( $wpdb->prepare( "SELECT price_per_gb FROM {$t} WHERE reseller_svp_user_id = %d AND panel_id = %d LIMIT 1", $r, $p ) );
		return null === $v ? 0.0 : (float) $v;
	}

	/**
	 * All rows for one reseller.
	 *
	 * @param int $reseller_svp_user_id Id.
	 * @return array<int, object>
	 */
	public static function list_for_reseller( $reseller_svp_user_id ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		if ( $r < 1 ) {
			return array();
		}
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE reseller_svp_user_id = %d ORDER BY panel_id ASC',
				$r
			)
		); // phpcs:ignore
	}

	/**
	 * Replace all price rows for a reseller (transactional).
	 *
	 * @param int                      $reseller_svp_user_id Reseller id.
	 * @param array<int, array<string, mixed>> $rows          Each: panel_id, price_per_gb.
	 * @return void
	 */
	public static function replace_all_for_reseller( $reseller_svp_user_id, array $rows ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		if ( $r < 1 ) {
			return;
		}
		$t = self::table();
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		try {
			$wpdb->delete( $t, array( 'reseller_svp_user_id' => $r ) );
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$pid = (int) ( $row['panel_id'] ?? 0 );
				$ppb = isset( $row['price_per_gb'] ) ? (float) $row['price_per_gb'] : 0.0;
				if ( $pid < 1 || $ppb < 0 ) {
					continue;
				}
				$wpdb->insert(
					$t,
					array(
						'reseller_svp_user_id' => $r,
						'panel_id'             => $pid,
						'price_per_gb'         => round( $ppb, 4 ),
						'updated_at'           => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%f', '%s' )
				);
			}
			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		} catch ( Throwable $e ) { // phpcs:ignore
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}
	}
}
