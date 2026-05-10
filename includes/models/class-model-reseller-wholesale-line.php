<?php
/**
 * Admin-defined wholesale catalog line (maps to a panel server-side; hidden from reseller UI).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Reseller_Wholesale_Line
 */
class SimpleVPBot_Model_Reseller_Wholesale_Line {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_reseller_wholesale_lines';
	}

	/**
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function find( $id ) {
		global $wpdb;
		$i = (int) $id;
		if ( $i < 1 ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $i ) ); // phpcs:ignore
	}

	/**
	 * All rows for admin list.
	 *
	 * @return array<int, object>
	 */
	public static function all_rows() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY sort_order ASC, id ASC' ); // phpcs:ignore
	}

	/**
	 * Active lines sorted.
	 *
	 * @return array<int, object>
	 */
	public static function all_active() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' WHERE active = 1 ORDER BY sort_order ASC, id ASC' ); // phpcs:ignore
	}

	/**
	 * Insert row.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return int
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$wpdb->insert( self::table(), $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update row.
	 *
	 * @param int                  $id Id.
	 * @param array<string, mixed> $data Data.
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$wpdb->update( self::table(), $data, array( 'id' => (int) $id ) );
	}

	/**
	 * Delete line (tiers should be deleted first).
	 *
	 * @param int $id Id.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $id ) );
	}

	/**
	 * Lines assigned to a reseller.
	 *
	 * @param int $reseller_svp_user_id Reseller id.
	 * @return array<int, object>
	 */
	public static function lines_for_reseller( $reseller_svp_user_id ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		if ( $r < 1 ) {
			return array();
		}
		$t   = self::table();
		$ta  = SimpleVPBot_Model_Reseller_Wholesale_Assignment::table();
		$sql = "SELECT l.* FROM {$t} l INNER JOIN {$ta} a ON a.line_id = l.id AND a.reseller_svp_user_id = %d WHERE l.active = 1 ORDER BY l.sort_order ASC, l.id ASC";
		return $wpdb->get_results( $wpdb->prepare( $sql, $r ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Safe payload for reseller dashboard (no panel ids).
	 *
	 * @param object $row Line row.
	 * @return array<string, mixed>
	 */
	public static function to_reseller_public_array( $row ) {
		if ( ! $row || ! is_object( $row ) ) {
			return array();
		}
		return array(
			'id'          => (int) $row->id,
			'label'       => (string) ( $row->label ?? '' ),
			'badge_color' => (string) ( $row->badge_color ?? '' ),
			'panel_id'    => (int) ( $row->panel_id ?? 0 ),
		);
	}

	/**
	 * Whether an assigned line for this panel uses L2TP defaults (for reseller plan type checks).
	 *
	 * @param int $reseller_svp_user_id Id.
	 * @param int $panel_id             Panel id.
	 * @return bool
	 */
	public static function reseller_panel_default_is_l2tp( $reseller_svp_user_id, $panel_id ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		$p = (int) $panel_id;
		if ( $r < 1 || $p < 1 ) {
			return false;
		}
		$t  = self::table();
		$ta = SimpleVPBot_Model_Reseller_Wholesale_Assignment::table();
		$n  = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$t} l INNER JOIN {$ta} a ON a.line_id = l.id AND a.reseller_svp_user_id = %d WHERE l.panel_id = %d AND l.active = 1 AND l.default_service_type = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r,
				$p,
				'l2tp'
			)
		); // phpcs:ignore
		return $n > 0;
	}

	/**
	 * @param int $reseller_svp_user_id Id.
	 * @param int $panel_id             Panel id.
	 * @return bool
	 */
	public static function reseller_can_use_panel( $reseller_svp_user_id, $panel_id ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		$p = (int) $panel_id;
		if ( $r < 1 || $p < 1 ) {
			return false;
		}
		$t  = self::table();
		$ta = SimpleVPBot_Model_Reseller_Wholesale_Assignment::table();
		$n  = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$t} l INNER JOIN {$ta} a ON a.line_id = l.id AND a.reseller_svp_user_id = %d WHERE l.panel_id = %d AND l.active = 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r,
				$p
			)
		); // phpcs:ignore
		return $n > 0;
	}
}
