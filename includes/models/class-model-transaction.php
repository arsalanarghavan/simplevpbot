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
		$data = self::normalize_billing_reseller_column( $data );
		$wpdb->insert( self::table(), $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Whether billing_reseller_svp_id column exists (cached per request).
	 *
	 * @return bool
	 */
	public static function has_billing_reseller_column() {
		static $cached = null;
		if ( null !== $cached ) {
			return $cached;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$cached = (bool) $wpdb->get_var( 'SHOW COLUMNS FROM ' . self::table() . " LIKE 'billing_reseller_svp_id'" );
		return $cached;
	}

	/**
	 * SQL expression for billing reseller id (column + meta_json fallback).
	 *
	 * @param string $alias Table alias.
	 * @return string
	 */
	public static function billing_reseller_id_sql_expr( $alias = 't' ) {
		if ( self::has_billing_reseller_column() ) {
			return "{$alias}.billing_reseller_svp_id";
		}
		return "CAST(JSON_UNQUOTE(JSON_EXTRACT({$alias}.meta_json, '$.billing_reseller_svp_id')) AS UNSIGNED)";
	}

	/**
	 * SQL predicate: row has a billing reseller attribution.
	 *
	 * @param string $alias Table alias.
	 * @return string
	 */
	public static function billing_reseller_present_sql( $alias = 't' ) {
		if ( self::has_billing_reseller_column() ) {
			return "({$alias}.billing_reseller_svp_id IS NOT NULL AND {$alias}.billing_reseller_svp_id > 0)";
		}
		return "(JSON_EXTRACT({$alias}.meta_json, '$.billing_reseller_svp_id') IS NOT NULL AND JSON_EXTRACT({$alias}.meta_json, '$.billing_reseller_svp_id') != 'null')";
	}

	/**
	 * Sync billing_reseller_svp_id column from meta_json when inserting.
	 *
	 * @param array<string, mixed> $data Insert row.
	 * @return array<string, mixed>
	 */
	public static function normalize_billing_reseller_column( array $data ) {
		if ( ! self::has_billing_reseller_column() ) {
			return $data;
		}
		if ( ! isset( $data['billing_reseller_svp_id'] ) && ! empty( $data['meta_json'] ) ) {
			$meta = $data['meta_json'];
			if ( is_string( $meta ) ) {
				$meta = json_decode( $meta, true );
			}
			if ( is_array( $meta ) && ! empty( $meta['billing_reseller_svp_id'] ) ) {
				$data['billing_reseller_svp_id'] = (int) $meta['billing_reseller_svp_id'];
			}
		}
		return $data;
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
		$allowed = array( 'service_id', 'meta_json', 'billing_reseller_svp_id' );
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
