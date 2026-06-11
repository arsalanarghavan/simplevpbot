<?php
/**
 * Cumulative wholesale usage events per reseller + line (for tier ladders).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Reseller_Wholesale_Accrual
 */
class SimpleVPBot_Model_Reseller_Wholesale_Accrual {

	/**
	 * Request-level memo for totals() (REST bootstrap may query same line repeatedly).
	 *
	 * @var array<string, array{gb:float,toman:float}>
	 */
	private static $totals_memo = array();

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_reseller_wholesale_accruals';
	}

	/**
	 * Sum gb and wholesale tomans for reseller+line.
	 *
	 * @param int $reseller_svp_user_id Id.
	 * @param int $line_id              Line id.
	 * @return array{gb:float,toman:float}
	 */
	public static function totals( $reseller_svp_user_id, $line_id ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		$l = (int) $line_id;
		if ( $r < 1 || $l < 1 ) {
			return array( 'gb' => 0.0, 'toman' => 0.0 );
		}
		$memo_key = $r . ':' . $l;
		if ( isset( self::$totals_memo[ $memo_key ] ) ) {
			return self::$totals_memo[ $memo_key ];
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(delta_gb),0) AS gb, COALESCE(SUM(delta_wholesale_toman),0) AS toman FROM ' . self::table() . ' WHERE reseller_svp_user_id = %d AND line_id = %d',
				$r,
				$l
			),
			ARRAY_A
		); // phpcs:ignore
		$result = array(
			'gb'    => isset( $row['gb'] ) ? (float) $row['gb'] : 0.0,
			'toman' => isset( $row['toman'] ) ? (float) $row['toman'] : 0.0,
		);
		self::$totals_memo[ $memo_key ] = $result;
		return $result;
	}

	/**
	 * Batch cumulative totals for one reseller across multiple wholesale lines.
	 *
	 * @param int   $reseller_svp_user_id Reseller id.
	 * @param int[] $line_ids             Line ids.
	 * @return array<int, array{gb:float,toman:float}>
	 */
	public static function totals_for_lines( $reseller_svp_user_id, array $line_ids ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		$out = array();
		$need = array();
		foreach ( $line_ids as $lid_raw ) {
			$lid = (int) $lid_raw;
			if ( $lid < 1 ) {
				continue;
			}
			$memo_key = $r . ':' . $lid;
			if ( isset( self::$totals_memo[ $memo_key ] ) ) {
				$out[ $lid ] = self::$totals_memo[ $memo_key ];
			} else {
				$need[] = $lid;
			}
		}
		$need = array_values( array_unique( $need ) );
		if ( $r < 1 || empty( $need ) ) {
			return $out;
		}
		$in = implode( ',', array_map( 'absint', $need ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT line_id,
					COALESCE(SUM(delta_gb),0) AS gb,
					COALESCE(SUM(delta_wholesale_toman),0) AS toman
				FROM " . self::table() . "
				WHERE reseller_svp_user_id = %d AND line_id IN ({$in})
				GROUP BY line_id",
				$r
			),
			ARRAY_A
		);
		$found = array();
		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$lid = (int) ( $row['line_id'] ?? 0 );
			if ( $lid < 1 ) {
				continue;
			}
			$found[ $lid ] = true;
			$result = array(
				'gb'    => isset( $row['gb'] ) ? (float) $row['gb'] : 0.0,
				'toman' => isset( $row['toman'] ) ? (float) $row['toman'] : 0.0,
			);
			self::$totals_memo[ $r . ':' . $lid ] = $result;
			$out[ $lid ] = $result;
		}
		foreach ( $need as $lid ) {
			if ( ! empty( $found[ $lid ] ) ) {
				continue;
			}
			$result = array( 'gb' => 0.0, 'toman' => 0.0 );
			self::$totals_memo[ $r . ':' . $lid ] = $result;
			$out[ $lid ] = $result;
		}
		return $out;
	}

	/**
	 * Insert accrual if transaction_id not already recorded.
	 *
	 * @param array<string, mixed> $data Row.
	 * @return bool True if inserted.
	 */
	public static function insert_if_new_tx( array $data ) {
		global $wpdb;
		$tx = isset( $data['transaction_id'] ) ? (int) $data['transaction_id'] : 0;
		if ( $tx > 0 ) {
			$exists = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM ' . self::table() . ' WHERE transaction_id = %d',
					$tx
				)
			); // phpcs:ignore
			if ( $exists > 0 ) {
				return false;
			}
		}
		$row = array(
			'reseller_svp_user_id'  => (int) ( $data['reseller_svp_user_id'] ?? 0 ),
			'line_id'               => (int) ( $data['line_id'] ?? 0 ),
			'delta_gb'              => (int) ( $data['delta_gb'] ?? 0 ),
			'delta_wholesale_toman' => round( (float) ( $data['delta_wholesale_toman'] ?? 0 ), 2 ),
			'unit_price_applied'    => round( (float) ( $data['unit_price_applied'] ?? 0 ), 4 ),
		);
		$fmt = array( '%d', '%d', '%d', '%f', '%f' );
		if ( $tx > 0 ) {
			$row['transaction_id'] = $tx;
			$fmt[]               = '%d';
		}
		if ( isset( $data['service_id'] ) && (int) $data['service_id'] > 0 ) {
			$row['service_id'] = (int) $data['service_id'];
			$fmt[]             = '%d';
		}
		$wpdb->insert( self::table(), $row, $fmt );
		$r = (int) $wpdb->insert_id;
		$memo_rid = (int) ( $data['reseller_svp_user_id'] ?? 0 );
		$memo_lid = (int) ( $data['line_id'] ?? 0 );
		if ( $r > 0 && $memo_rid > 0 && $memo_lid > 0 ) {
			unset( self::$totals_memo[ $memo_rid . ':' . $memo_lid ] );
		}
		return $r > 0;
	}
}
