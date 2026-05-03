<?php
/**
 * Receipt model.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Receipt
 */
class SimpleVPBot_Model_Receipt {

	/**
	 * Table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_receipts';
	}

	/**
	 * Insert.
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
	 * Update.
	 *
	 * @param int                  $id Id.
	 * @param array<string, mixed> $data Data.
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$wpdb->update( self::table(), $data, array( 'id' => $id ) );
	}

	/**
	 * Find.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function find( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) ); // phpcs:ignore
	}

	/**
	 * Pending list.
	 *
	 * @param int $limit Limit.
	 * @return array<int, object>
	 */
	public static function pending( $limit = 50 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . " WHERE status = 'pending' ORDER BY id ASC LIMIT %d", (int) $limit ) ); // phpcs:ignore
	}

	/**
	 * Count pending receipts.
	 *
	 * @return int
	 */
	public static function pending_count() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table() . " WHERE status = 'pending'" ); // phpcs:ignore
	}

	/**
	 * Pending receipts page (oldest first, same order as pending()).
	 *
	 * @param int $offset Offset.
	 * @param int $limit  Limit.
	 * @return array<int, object>
	 */
	public static function pending_paged( $offset, $limit ) {
		global $wpdb;
		$offset = max( 0, (int) $offset );
		$limit  = max( 1, min( 50, (int) $limit ) );
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . " WHERE status = 'pending' ORDER BY id ASC LIMIT %d OFFSET %d", $limit, $offset ) ); // phpcs:ignore
	}

	/**
	 * List receipts with optional status filter (newest first).
	 *
	 * @param int         $offset Offset.
	 * @param int         $limit  Limit.
	 * @param string|null $status pending|approved|rejected or null for all.
	 * @return array<int, object>
	 */
	public static function list_paged( $offset, $limit, $status = null ) {
		global $wpdb;
		$t      = self::table();
		$offset = max( 0, (int) $offset );
		$limit  = max( 1, min( 100, (int) $limit ) );
		if ( $status && in_array( (string) $status, array( 'pending', 'approved', 'rejected' ), true ) ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE status = %s ORDER BY id DESC LIMIT %d OFFSET %d", $status, $limit, $offset ) ); // phpcs:ignore
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ) ); // phpcs:ignore
	}
}
