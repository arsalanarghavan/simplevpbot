<?php
/**
 * Unit economics calculator singleton config.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Unit_Economics_Config
 */
class SimpleVPBot_Model_Unit_Economics_Config {

	const ROW_ID = 1;

	/**
	 * Table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_unit_economics_config';
	}

	/**
	 * Ensure singleton row exists.
	 */
	public static function ensure_default_row() {
		global $wpdb;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE id = %d", self::ROW_ID ) );
		if ( $exists > 0 ) {
			return;
		}
		$wpdb->insert(
			$t,
			array(
				'id'                   => self::ROW_ID,
				'dev_ops_costs'        => 0,
				'outbound_cost_per_gb' => 0,
				'cdn_cost_per_gb'      => 0,
				'total_sold_volume_gb' => 0,
				'selling_price_per_gb' => 0,
				'volume_mode'          => 'auto_sales',
				'volume_window_days'   => 30,
				'updated_at'           => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Get singleton config row.
	 *
	 * @return object|null
	 */
	public static function get() {
		global $wpdb;
		self::ensure_default_row();
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', self::ROW_ID ) ); // phpcs:ignore
	}

	/**
	 * Config as associative array for calculator/API.
	 *
	 * @return array<string, mixed>
	 */
	public static function to_array() {
		$row = self::get();
		if ( ! $row ) {
			return array(
				'dev_ops_costs'        => 0.0,
				'outbound_cost_per_gb' => 0.0,
				'cdn_cost_per_gb'      => 0.0,
				'total_sold_volume_gb' => 0.0,
				'selling_price_per_gb' => 0.0,
				'volume_mode'          => 'auto_sales',
				'volume_window_days'   => 30,
			);
		}
		$mode = sanitize_key( (string) ( $row->volume_mode ?? 'auto_sales' ) );
		if ( ! in_array( $mode, array( 'manual', 'auto_sales' ), true ) ) {
			$mode = 'auto_sales';
		}
		return array(
			'dev_ops_costs'        => (float) ( $row->dev_ops_costs ?? 0 ),
			'outbound_cost_per_gb' => (float) ( $row->outbound_cost_per_gb ?? 0 ),
			'cdn_cost_per_gb'      => (float) ( $row->cdn_cost_per_gb ?? 0 ),
			'total_sold_volume_gb' => (float) ( $row->total_sold_volume_gb ?? 0 ),
			'selling_price_per_gb' => (float) ( $row->selling_price_per_gb ?? 0 ),
			'volume_mode'          => $mode,
			'volume_window_days'   => max( 1, min( 365, (int) ( $row->volume_window_days ?? 30 ) ) ),
		);
	}

	/**
	 * Upsert scalar fields on singleton row.
	 *
	 * @param array<string, mixed> $data Fields to update.
	 */
	public static function upsert( array $data ) {
		global $wpdb;
		self::ensure_default_row();
		$mode = sanitize_key( (string) ( $data['volume_mode'] ?? 'auto_sales' ) );
		if ( ! in_array( $mode, array( 'manual', 'auto_sales' ), true ) ) {
			$mode = 'auto_sales';
		}
		$row = array(
			'dev_ops_costs'        => max( 0.0, (float) ( $data['dev_ops_costs'] ?? 0 ) ),
			'outbound_cost_per_gb' => max( 0.0, (float) ( $data['outbound_cost_per_gb'] ?? 0 ) ),
			'cdn_cost_per_gb'      => max( 0.0, (float) ( $data['cdn_cost_per_gb'] ?? 0 ) ),
			'total_sold_volume_gb' => max( 0.0, (float) ( $data['total_sold_volume_gb'] ?? 0 ) ),
			'selling_price_per_gb' => max( 0.0, (float) ( $data['selling_price_per_gb'] ?? 0 ) ),
			'volume_mode'          => $mode,
			'volume_window_days'   => max( 1, min( 365, (int) ( $data['volume_window_days'] ?? 30 ) ) ),
			'updated_at'           => current_time( 'mysql' ),
		);
		$wpdb->update( self::table(), $row, array( 'id' => self::ROW_ID ) );
		if ( class_exists( 'SimpleVPBot_Unit_Economics_Sales_Volume' ) ) {
			SimpleVPBot_Unit_Economics_Sales_Volume::bust_cache();
		}
	}

	/**
	 * Upsert global calculator inputs only (v2/v3).
	 *
	 * @param array<string, mixed> $data volume + selling price + mode.
	 */
	public static function upsert_global_inputs( array $data ) {
		global $wpdb;
		self::ensure_default_row();
		$mode = sanitize_key( (string) ( $data['volume_mode'] ?? '' ) );
		$update = array(
			'total_sold_volume_gb' => max( 0.0, (float) ( $data['total_sold_volume_gb'] ?? 0 ) ),
			'selling_price_per_gb' => max( 0.0, (float) ( $data['selling_price_per_gb'] ?? 0 ) ),
			'updated_at'           => current_time( 'mysql' ),
		);
		if ( in_array( $mode, array( 'manual', 'auto_sales' ), true ) ) {
			$update['volume_mode'] = $mode;
		}
		if ( isset( $data['volume_window_days'] ) ) {
			$update['volume_window_days'] = max( 1, min( 365, (int) $data['volume_window_days'] ) );
		}
		$wpdb->update( self::table(), $update, array( 'id' => self::ROW_ID ) );
		if ( class_exists( 'SimpleVPBot_Unit_Economics_Sales_Volume' ) ) {
			SimpleVPBot_Unit_Economics_Sales_Volume::bust_cache();
		}
	}

	/**
	 * Global inputs for calculator (v2/v3).
	 *
	 * @return array<string, mixed>
	 */
	public static function global_inputs() {
		$row = self::to_array();
		return array(
			'total_sold_volume_gb' => (float) ( $row['total_sold_volume_gb'] ?? 0 ),
			'selling_price_per_gb' => (float) ( $row['selling_price_per_gb'] ?? 0 ),
			'volume_mode'          => (string) ( $row['volume_mode'] ?? 'auto_sales' ),
			'volume_window_days'   => (int) ( $row['volume_window_days'] ?? 30 ),
		);
	}
}
