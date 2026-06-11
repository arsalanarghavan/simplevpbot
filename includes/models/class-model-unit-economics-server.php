<?php
/**
 * Unit economics infrastructure server cost rows.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Unit_Economics_Server
 */
class SimpleVPBot_Model_Unit_Economics_Server {

	/**
	 * Table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_unit_economics_servers';
	}

	/**
	 * All rows ordered for display/calculation.
	 *
	 * @return array<int, object>
	 */
	public static function all_ordered() {
		global $wpdb;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM {$t} ORDER BY sort_order ASC, id ASC" );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Servers as arrays for calculator/API.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function to_arrays() {
		$out = array();
		foreach ( self::all_ordered() as $row ) {
			$out[] = array(
				'id'            => (int) ( $row->id ?? 0 ),
				'name'          => (string) ( $row->name ?? '' ),
				'cost_amount'   => (float) ( $row->cost_amount ?? 0 ),
				'billing_cycle' => (string) ( $row->billing_cycle ?? 'monthly' ),
				'sort_order'    => (int) ( $row->sort_order ?? 0 ),
			);
		}
		return $out;
	}

	/**
	 * Replace all server rows (transactional delete + insert).
	 *
	 * @param array<int, array<string, mixed>> $rows Sanitized server rows.
	 */
	public static function replace_all( array $rows ) {
		global $wpdb;
		$t = self::table();
		$wpdb->query( "DELETE FROM {$t}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$order = 0;
		foreach ( $rows as $row ) {
			$name = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			$cycle = sanitize_key( (string) ( $row['billing_cycle'] ?? 'monthly' ) );
			if ( ! in_array( $cycle, array( 'hourly', 'daily', 'monthly' ), true ) ) {
				$cycle = 'monthly';
			}
			$wpdb->insert(
				$t,
				array(
					'name'          => $name,
					'cost_amount'   => max( 0.0, (float) ( $row['cost_amount'] ?? 0 ) ),
					'billing_cycle' => $cycle,
					'sort_order'    => $order,
				)
			);
			++$order;
		}
	}
}
