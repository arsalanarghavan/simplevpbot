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
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(delta_gb),0) AS gb, COALESCE(SUM(delta_wholesale_toman),0) AS toman FROM ' . self::table() . ' WHERE reseller_svp_user_id = %d AND line_id = %d',
				$r,
				$l
			),
			ARRAY_A
		); // phpcs:ignore
		return array(
			'gb'    => isset( $row['gb'] ) ? (float) $row['gb'] : 0.0,
			'toman' => isset( $row['toman'] ) ? (float) $row['toman'] : 0.0,
		);
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
		return (int) $wpdb->insert_id > 0;
	}
}
