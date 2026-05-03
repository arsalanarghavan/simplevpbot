<?php
/**
 * Optional external monitoring endpoints (JSON over HTTPS).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Monitor_Host
 */
class SimpleVPBot_Model_Monitor_Host {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_monitor_hosts';
	}

	/**
	 * All rows ordered.
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
	 * Active rows for polling.
	 *
	 * @return array<int, object>
	 */
	public static function active_ordered() {
		global $wpdb;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM {$t} WHERE active = 1 ORDER BY sort_order ASC, id ASC" );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Row without bearer token (for JSON responses).
	 *
	 * @param object $row DB row.
	 * @return array<string, mixed>
	 */
	public static function to_public_array( $row ) {
		if ( ! is_object( $row ) ) {
			return array();
		}
		return array(
			'id'          => (int) $row->id,
			'label'       => (string) ( $row->label ?? '' ),
			'metricsUrl'  => (string) ( $row->metrics_url ?? '' ),
			'sortOrder'   => (int) ( $row->sort_order ?? 0 ),
			'active'      => (int) ( $row->active ?? 0 ),
		);
	}
}
