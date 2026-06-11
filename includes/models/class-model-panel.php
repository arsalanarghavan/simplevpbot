<?php
/**
 * Multiple 3x-ui panel credentials (per-panel plans/categories/pricing).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Panel
 */
class SimpleVPBot_Model_Panel {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_panels';
	}

	/**
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function find( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id < 1 ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) ); // phpcs:ignore
	}

	/**
	 * Batch fetch panels keyed by id.
	 *
	 * @param array<int> $ids Panel ids.
	 * @return array<int, object>
	 */
	public static function find_by_ids( array $ids ) {
		$ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $ids ),
					static function ( $v ) {
						return $v > 0;
					}
				)
			)
		);
		if ( empty( $ids ) ) {
			return array();
		}
		global $wpdb;
		$ph = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE id IN ({$ph})", $ids ) );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			if ( is_object( $row ) && isset( $row->id ) ) {
				$out[ (int) $row->id ] = $row;
			}
		}
		return $out;
	}

	/**
	 * Active panels ordered.
	 *
	 * @return array<int, object>
	 */
	public static function all_active_ordered() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' WHERE active = 1 ORDER BY sort_order ASC, id ASC' ); // phpcs:ignore
	}

	/**
	 * All rows (admin).
	 *
	 * @return array<int, object>
	 */
	public static function all_ordered() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY sort_order ASC, id ASC' ); // phpcs:ignore
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @return int Insert id.
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$wpdb->insert( self::table(), $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int                  $id Id.
	 * @param array<string, mixed> $data Data.
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$wpdb->update( self::table(), $data, array( 'id' => (int) $id ) );
	}

	/**
	 * @param int $id Id.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $id ) );
	}

	/**
	 * Stored API flavor for a panel row.
	 *
	 * @param object|null $panel Panel row.
	 * @return string unknown | legacy_inbound | v3_clients
	 */
	public static function api_flavor( $panel ) {
		if ( ! is_object( $panel ) ) {
			return 'unknown';
		}
		$f = trim( (string) ( $panel->panel_api_flavor ?? '' ) );
		return '' !== $f ? $f : 'unknown';
	}

	/**
	 * Persist detected API flavor.
	 *
	 * @param int    $id     Panel id.
	 * @param string $flavor Flavor key.
	 */
	public static function set_api_flavor( $id, $flavor ) {
		$id = (int) $id;
		if ( $id < 1 ) {
			return;
		}
		$allowed = array( 'unknown', 'legacy_inbound', 'v3_clients' );
		$f       = trim( (string) $flavor );
		if ( ! in_array( $f, $allowed, true ) ) {
			$f = 'unknown';
		}
		self::update( $id, array( 'panel_api_flavor' => $f ) );
	}

	/**
	 * Count rows (for UI).
	 *
	 * @return int
	 */
	public static function count_all() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() ); // phpcs:ignore
	}
}
