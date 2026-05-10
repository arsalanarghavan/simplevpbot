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
	 * Why the dashboard may show no panels: stored rows vs JOINable rows (same rules as REST).
	 *
	 * @param int $reseller_svp_user_id svp_users.id.
	 * @return array{stored_rows:int,joinable_rows:int,orphan_panel_ids:int[],inactive_row_count:int}|null
	 */
	public static function access_diagnostics( $reseller_svp_user_id ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		if ( $r < 1 || ! class_exists( 'SimpleVPBot_Model_Panel' ) ) {
			return null;
		}
		$t  = self::table();
		$tp = SimpleVPBot_Model_Panel::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE reseller_svp_user_id = %d", $r ) );
		$orphan = array();
		$inactive = 0;
		foreach ( (array) $rows as $row ) {
			if ( ! is_object( $row ) ) {
				continue;
			}
			$pid = (int) ( $row->panel_id ?? 0 );
			if ( $pid > 0 && ! SimpleVPBot_Model_Panel::find( $pid ) ) {
				$orphan[] = $pid;
			}
			if ( ! self::row_allows_panel_use( $row ) ) {
				++$inactive;
			}
		}
		$joinable = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$t} r INNER JOIN {$tp} p ON p.id = r.panel_id WHERE r.reseller_svp_user_id = %d AND ( r.panel_access = 1 OR r.price_per_gb > 0 )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array(
			'stored_rows'          => count( (array) $rows ),
			'joinable_rows'        => $joinable,
			'orphan_panel_ids'     => array_values( array_unique( array_map( 'intval', $orphan ) ) ),
			'inactive_row_count'   => $inactive,
		);
	}

	/**
	 * Replace all price rows for a reseller (transactional).
	 *
	 * @param int                                $reseller_svp_user_id Reseller id.
	 * @param array<int, array<string, mixed>> $rows                 Each: panel_id, price_per_gb, panel_access?, default_*.
	 * @return array{ok:bool, message?:string, skipped_panel_ids?:int[]}
	 */
	public static function replace_all_for_reseller( $reseller_svp_user_id, array $rows ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		if ( $r < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_reseller' );
		}
		$t                   = self::table();
		$skipped_panel_ids   = array();
		$prepared            = array();
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
			if ( ! class_exists( 'SimpleVPBot_Model_Panel' ) || ! SimpleVPBot_Model_Panel::find( $pid ) ) {
				$skipped_panel_ids[] = $pid;
				continue;
			}
			$dstype = isset( $row['default_service_type'] ) ? sanitize_key( (string) $row['default_service_type'] ) : 'xray';
			if ( ! in_array( $dstype, array( 'xray', 'l2tp' ), true ) ) {
				$dstype = 'xray';
			}
			$prepared[] = array(
				'panel_id'               => $pid,
				'price_per_gb'           => round( $ppb, 0 ),
				'panel_access'           => $pacc ? 1 : 0,
				'default_service_type'   => $dstype,
				'default_inbound_id'     => max( 0, (int) ( $row['default_inbound_id'] ?? 0 ) ),
				'default_l2tp_server_id' => max( 0, (int) ( $row['default_l2tp_server_id'] ?? 0 ) ),
			);
		}
		$skipped_panel_ids = array_values( array_unique( array_map( 'intval', $skipped_panel_ids ) ) );

		if ( ! empty( $rows ) && empty( $prepared ) ) {
			return array(
				'ok'                => false,
				'message'           => 'no_valid_panels',
				'skipped_panel_ids' => $skipped_panel_ids,
			);
		}

		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		try {
			$wpdb->delete( $t, array( 'reseller_svp_user_id' => $r ) );
			foreach ( $prepared as $row ) {
				$ins = $wpdb->insert(
					$t,
					array(
						'reseller_svp_user_id'   => $r,
						'panel_id'               => $row['panel_id'],
						'price_per_gb'           => $row['price_per_gb'],
						'panel_access'           => $row['panel_access'],
						'default_service_type'   => $row['default_service_type'],
						'default_inbound_id'     => $row['default_inbound_id'],
						'default_l2tp_server_id' => $row['default_l2tp_server_id'],
						'updated_at'             => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%f', '%d', '%s', '%d', '%d', '%s' )
				);
				if ( false === $ins ) {
					throw new RuntimeException( 'insert_failed' );
				}
			}
			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$out = array( 'ok' => true );
			if ( ! empty( $skipped_panel_ids ) ) {
				$out['skipped_panel_ids'] = $skipped_panel_ids;
			}
			return $out;
		} catch ( Throwable $e ) { // phpcs:ignore
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return array( 'ok' => false, 'message' => $e->getMessage() ?: 'db' );
		}
	}
}
