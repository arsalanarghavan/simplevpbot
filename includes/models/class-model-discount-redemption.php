<?php
/**
 * Discount code redemption history.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Discount_Redemption
 */
class SimpleVPBot_Model_Discount_Redemption {

	/**
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_discount_redemptions';
	}

	/**
	 * @param array<string, mixed> $data Row.
	 * @return int
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$wpdb->insert( self::table(), $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int $transaction_id Tx id.
	 * @return object|null
	 */
	public static function find_by_transaction( $transaction_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE transaction_id = %d',
				(int) $transaction_id
			)
		); // phpcs:ignore
	}

	/**
	 * Per-code aggregates for dashboard.
	 *
	 * @param array<int> $code_ids Code ids.
	 * @param int        $owner_svp_user_id Reseller scope (0 = site).
	 * @return array<int, array{count:int,sum_discount:float}>
	 */
	public static function aggregates_by_code_ids( array $code_ids, $owner_svp_user_id = 0 ) {
		$code_ids = array_values( array_filter( array_map( 'intval', $code_ids ) ) );
		if ( empty( $code_ids ) ) {
			return array();
		}
		global $wpdb;
		$disc_t = SimpleVPBot_Model_Discount_Code::table();
		$red_t  = self::table();
		$in     = implode( ',', array_map( 'absint', $code_ids ) );
		$scope  = (int) $owner_svp_user_id > 0
			? $wpdb->prepare( ' AND c.owner_svp_user_id IN (%d, 0)', (int) $owner_svp_user_id )
			: '';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT r.discount_code_id AS cid, COUNT(*) AS cnt, COALESCE(SUM(r.discount_toman),0) AS sdisc
			FROM {$red_t} r
			INNER JOIN {$disc_t} c ON c.id = r.discount_code_id
			WHERE r.discount_code_id IN ({$in}){$scope}
			GROUP BY r.discount_code_id";
		$rows = $wpdb->get_results( $sql ); // phpcs:ignore
		$out  = array();
		foreach ( (array) $rows as $row ) {
			if ( ! $row ) {
				continue;
			}
			$cid = (int) ( $row->cid ?? 0 );
			if ( $cid < 1 ) {
				continue;
			}
			$out[ $cid ] = array(
				'count'         => (int) ( $row->cnt ?? 0 ),
				'sum_discount'  => (float) ( $row->sdisc ?? 0 ),
			);
		}
		return $out;
	}

	/**
	 * Global usage summary for discounts tab.
	 *
	 * @param int $owner_svp_user_id Reseller scope.
	 * @return array{total_redemptions:int,total_discount_toman:float,active_codes:int}
	 */
	public static function global_summary( $owner_svp_user_id = 0 ) {
		global $wpdb;
		$disc_t = SimpleVPBot_Model_Discount_Code::table();
		$red_t  = self::table();
		$scope  = (int) $owner_svp_user_id > 0
			? $wpdb->prepare( ' WHERE c.owner_svp_user_id IN (%d, 0)', (int) $owner_svp_user_id )
			: '';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			"SELECT COUNT(*) AS cnt, COALESCE(SUM(r.discount_toman),0) AS sdisc
			FROM {$red_t} r
			INNER JOIN {$disc_t} c ON c.id = r.discount_code_id{$scope}"
		);
		$active_scope = (int) $owner_svp_user_id > 0
			? $wpdb->prepare( ' WHERE owner_svp_user_id IN (%d, 0) AND active = 1', (int) $owner_svp_user_id )
			: ' WHERE active = 1';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$active_codes = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$disc_t}{$active_scope}" );
		return array(
			'total_redemptions'    => (int) ( $row->cnt ?? 0 ),
			'total_discount_toman' => (float) ( $row->sdisc ?? 0 ),
			'active_codes'         => $active_codes,
		);
	}

	/**
	 * Per-code aggregates (single code).
	 *
	 * @param int $code_id Code id.
	 * @param int $owner_svp_user_id Reseller scope.
	 * @return array{redemption_count:int,total_discount_toman:float}
	 */
	public static function aggregates_for_code( $code_id, $owner_svp_user_id = 0 ) {
		$map = self::aggregates_by_code_ids( array( (int) $code_id ), $owner_svp_user_id );
		$cid = (int) $code_id;
		if ( isset( $map[ $cid ] ) ) {
			return array(
				'redemption_count'     => (int) ( $map[ $cid ]['count'] ?? 0 ),
				'total_discount_toman' => (float) ( $map[ $cid ]['sum_discount'] ?? 0 ),
			);
		}
		return array(
			'redemption_count'     => 0,
			'total_discount_toman' => 0.0,
		);
	}

	/**
	 * Recent redemptions for one code.
	 *
	 * @param int $code_id Code id.
	 * @param int $limit Max rows.
	 * @return array<int, object>
	 */
	public static function recent_for_code( $code_id, $limit = 50 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE discount_code_id = %d ORDER BY id DESC LIMIT %d',
				(int) $code_id,
				max( 1, min( 200, (int) $limit ) )
			)
		); // phpcs:ignore
	}
}
