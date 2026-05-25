<?php
/**
 * Transaction model.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Transaction
 */
class SimpleVPBot_Model_Transaction {

	/**
	 * Table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_transactions';
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
	 * Update status.
	 *
	 * @param int    $id Id.
	 * @param string $status Status.
	 */
	public static function set_status( $id, $status ) {
		global $wpdb;
		$wpdb->update( self::table(), array( 'status' => $status ), array( 'id' => $id ) );
	}

	/**
	 * Approve only when still pending (idempotent guard).
	 *
	 * @param int                  $id    Transaction id.
	 * @param array<string, mixed> $extra Optional columns (service_id, meta_json, …).
	 * @return bool True if exactly one row moved pending → approved.
	 */
	public static function try_approve_from_pending( $id, array $extra = array() ) {
		global $wpdb;
		$tid = (int) $id;
		if ( $tid < 1 ) {
			return false;
		}
		$allowed = array( 'service_id', 'meta_json' );
		$data    = array( 'status' => 'approved' );
		foreach ( $extra as $col => $val ) {
			if ( in_array( (string) $col, $allowed, true ) ) {
				$data[ $col ] = $val;
			}
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$affected = $wpdb->update(
			self::table(),
			$data,
			array(
				'id'     => $tid,
				'status' => 'pending',
			)
		);
		return (int) $affected > 0;
	}

	/**
	 * Update row.
	 *
	 * @param int                  $id Id.
	 * @param array<string, mixed> $data Data.
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$wpdb->update( self::table(), $data, array( 'id' => $id ) );
	}

	/**
	 * History for user.
	 *
	 * @param int $user_id User id.
	 * @param int $limit Limit.
	 * @return array<int, object>
	 */
	public static function history( $user_id, $limit = 20 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE user_id = %d ORDER BY id DESC LIMIT %d', $user_id, $limit ) ); // phpcs:ignore
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
	 * Latest successful (approved) transaction time for a bot user.
	 *
	 * @param int $user_id svp_users.id.
	 * @return int Unix timestamp or 0 if none.
	 */
	public static function last_approved_timestamp( $user_id ) {
		global $wpdb;
		$uid = (int) $user_id;
		if ( $uid < 1 ) {
			return 0;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT created_at FROM ' . self::table() . " WHERE user_id = %d AND status = 'approved' ORDER BY id DESC LIMIT 1", $uid ) );
		if ( ! $row || empty( $row->created_at ) ) {
			return 0;
		}
		$t = strtotime( (string) $row->created_at . ' UTC' );
		return $t ? (int) $t : 0;
	}
}
