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
	 * Row for one reseller + panel (null if none).
	 *
	 * @param int $reseller_svp_user_id Reseller svp_users.id.
	 * @param int $panel_id             Panel id.
	 * @return object|null
	 */
	public static function get_panel_row( $reseller_svp_user_id, $panel_id ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		$p = (int) $panel_id;
		if ( $r < 1 || $p < 1 ) {
			return null;
		}
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE reseller_svp_user_id = %d AND panel_id = %d LIMIT 1", $r, $p ) );
	}

	/**
	 * Unit price (toman per GB) or 0 if unset.
	 *
	 * @param int $reseller_svp_user_id Reseller svp_users.id.
	 * @param int $panel_id             Panel id.
	 * @return float
	 */
	public static function get_unit_price( $reseller_svp_user_id, $panel_id ) {
		$row = self::get_panel_row( $reseller_svp_user_id, $panel_id );
		if ( ! $row ) {
			return 0.0;
		}
		return (float) ( $row->price_per_gb ?? 0 );
	}

	/**
	 * Whether a stored row grants use of the panel (explicit access or positive wholesale price).
	 *
	 * @param object|null $row Row from {@see get_panel_row()} or list_for_reseller.
	 * @return bool
	 */
	public static function row_allows_panel_use( $row ) {
		if ( ! $row || ! is_object( $row ) ) {
			return false;
		}
		$acc   = (int) ( $row->panel_access ?? 0 );
		$price = (float) ( $row->price_per_gb ?? 0 );
		return ( 1 === $acc || $price > 0 );
	}

	/**
	 * Whether reseller may use this panel (row exists and access or price > 0).
	 *
	 * @param int $reseller_svp_user_id Id.
	 * @param int $panel_id             Panel id.
	 * @return bool
	 */
	public static function has_panel_access( $reseller_svp_user_id, $panel_id ) {
		$row = self::get_panel_row( $reseller_svp_user_id, $panel_id );
		return self::row_allows_panel_use( $row );
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
	 * @param int                                $reseller_svp_user_id Reseller id.
	 * @param array<int, array<string, mixed>> $rows                 Each: panel_id, price_per_gb, panel_access?, default_*.
	 * @return array{ok:bool, message?:string}
	 */
	public static function replace_all_for_reseller( $reseller_svp_user_id, array $rows ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		if ( $r < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_reseller' );
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
				$pacc = array_key_exists( 'panel_access', $row ) ? (int) ( ! empty( $row['panel_access'] ) ) : 1;
				if ( $ppb > 0 ) {
					$pacc = 1;
				}
				if ( $pid < 1 || $ppb < 0 ) {
					continue;
				}
				$dstype = isset( $row['default_service_type'] ) ? sanitize_key( (string) $row['default_service_type'] ) : 'xray';
				if ( ! in_array( $dstype, array( 'xray', 'l2tp' ), true ) ) {
					$dstype = 'xray';
				}
				$d_inbound = max( 0, (int) ( $row['default_inbound_id'] ?? 0 ) );
				$l2id      = max( 0, (int) ( $row['default_l2tp_server_id'] ?? 0 ) );
				$ins       = $wpdb->insert(
					$t,
					array(
						'reseller_svp_user_id'   => $r,
						'panel_id'               => $pid,
						'price_per_gb'           => round( $ppb, 0 ),
						'panel_access'           => $pacc ? 1 : 0,
						'default_service_type'   => $dstype,
						'default_inbound_id'     => $d_inbound,
						'default_l2tp_server_id' => $l2id,
						'updated_at'             => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%f', '%d', '%s', '%d', '%d', '%s' )
				);
				if ( false === $ins ) {
					throw new RuntimeException( 'insert_failed' );
				}
			}
			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return array( 'ok' => true );
		} catch ( Throwable $e ) { // phpcs:ignore
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return array( 'ok' => false, 'message' => $e->getMessage() ?: 'db' );
		}
	}
}
