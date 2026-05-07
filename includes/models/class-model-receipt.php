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
	 * Atomically move pending -> processing so only one approver proceeds.
	 *
	 * @param int $id Receipt id.
	 * @return bool True if this request claimed the row.
	 */
	public static function claim_pending( $id ) {
		global $wpdb;
		$rid = (int) $id;
		if ( $rid < 1 ) {
			return false;
		}
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$t} SET status = %s WHERE id = %d AND status = %s",
				'processing',
				$rid,
				'pending'
			)
		);
		return (int) $wpdb->rows_affected > 0;
	}

	/**
	 * Undo claim after a failed approval (e.g. provisioning error).
	 *
	 * @param int $id Receipt id.
	 * @return bool Whether a processing row was released.
	 */
	public static function release_to_pending( $id ) {
		global $wpdb;
		$rid = (int) $id;
		if ( $rid < 1 ) {
			return false;
		}
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$t} SET status = %s, decided_at = NULL WHERE id = %d AND status = %s",
				'pending',
				$rid,
				'processing'
			)
		);
		return (int) $wpdb->rows_affected > 0;
	}

	/**
	 * Finalize approval: processing -> approved (idempotent for single winner).
	 *
	 * @param int $id Receipt id.
	 * @return bool Whether the row was updated.
	 */
	public static function try_finalize_approved( $id ) {
		global $wpdb;
		$rid = (int) $id;
		if ( $rid < 1 ) {
			return false;
		}
		$t = self::table();
		$decided = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$t} SET status = %s, decided_at = %s WHERE id = %d AND status = %s",
				'approved',
				$decided,
				$rid,
				'processing'
			)
		);
		return (int) $wpdb->rows_affected > 0;
	}

	/**
	 * Reject from pending or processing (second wins race returns false).
	 *
	 * @param int $id Receipt id.
	 * @return bool Whether the row was updated.
	 */
	public static function try_set_rejected( $id ) {
		global $wpdb;
		$rid = (int) $id;
		if ( $rid < 1 ) {
			return false;
		}
		$t       = self::table();
		$decided = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$t} SET status = %s, decided_at = %s WHERE id = %d AND status IN ('pending', 'processing')",
				'rejected',
				$decided,
				$rid
			)
		);
		return (int) $wpdb->rows_affected > 0;
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
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . " WHERE status IN ('pending', 'processing') ORDER BY id ASC LIMIT %d", (int) $limit ) ); // phpcs:ignore
	}

	/**
	 * Count pending receipts.
	 *
	 * @return int
	 */
	public static function pending_count() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table() . " WHERE status IN ('pending', 'processing')" ); // phpcs:ignore
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
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . " WHERE status IN ('pending', 'processing') ORDER BY id ASC LIMIT %d OFFSET %d", $limit, $offset ) ); // phpcs:ignore
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
		if ( $status && in_array( (string) $status, array( 'pending', 'approved', 'rejected', 'processing' ), true ) ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE status = %s ORDER BY id DESC LIMIT %d OFFSET %d", $status, $limit, $offset ) ); // phpcs:ignore
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ) ); // phpcs:ignore
	}

	/**
	 * Sum approved amounts for a card in current UTC day.
	 *
	 * @param int $card_id Card id.
	 * @param int $exclude_transaction_id Optional pending transaction id to ignore.
	 * @return float
	 */
	public static function approved_sum_for_card_today( $card_id, $exclude_transaction_id = 0 ) {
		global $wpdb;
		$t   = self::table();
		$cid = (int) $card_id;
		$txe = (int) $exclude_transaction_id;
		if ( $cid < 1 ) {
			return 0.0;
		}
		if ( $txe > 0 ) {
			$sql = "SELECT COALESCE(SUM(amount),0) FROM {$t} WHERE card_id = %d AND status = 'approved' AND transaction_id <> %d AND DATE(created_at) = UTC_DATE()";
			$sum = $wpdb->get_var( $wpdb->prepare( $sql, $cid, $txe ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (float) $sum;
		}
		$sql = "SELECT COALESCE(SUM(amount),0) FROM {$t} WHERE card_id = %d AND status = 'approved' AND DATE(created_at) = UTC_DATE()";
		$sum = $wpdb->get_var( $wpdb->prepare( $sql, $cid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (float) $sum;
	}
}
