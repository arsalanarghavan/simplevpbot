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
	 * Pending receipts for users in scope (reseller bot admin).
	 *
	 * @param int        $offset Offset.
	 * @param int        $limit  Limit.
	 * @param array<int> $user_ids svp_users.id values.
	 * @return array<int, object>
	 */
	public static function pending_paged_for_user_ids( $offset, $limit, array $user_ids ) {
		$ids = array_values(
			array_filter(
				array_map( 'intval', $user_ids ),
				static function ( $v ) {
					return $v > 0;
				}
			)
		);
		if ( empty( $ids ) ) {
			return array();
		}
		global $wpdb;
		$offset = max( 0, (int) $offset );
		$limit  = max( 1, min( 50, (int) $limit ) );
		$ph     = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . self::table() . " WHERE status IN ('pending', 'processing') AND user_id IN ({$ph}) ORDER BY id ASC LIMIT %d OFFSET %d",
				array_merge( $ids, array( $limit, $offset ) )
			)
		);
	}

	/**
	 * Count pending receipts for users in scope.
	 *
	 * @param array<int> $user_ids svp_users.id values.
	 * @return int
	 */
	public static function pending_count_for_user_ids( array $user_ids ) {
		$ids = array_values(
			array_filter(
				array_map( 'intval', $user_ids ),
				static function ( $v ) {
					return $v > 0;
				}
			)
		);
		if ( empty( $ids ) ) {
			return 0;
		}
		global $wpdb;
		$ph = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM " . self::table() . " WHERE status IN ('pending', 'processing') AND user_id IN ({$ph})",
				$ids
			)
		);
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
	/**
	 * Dashboard list filters: WHERE fragments and ORDER BY (alias `r` on receipts table).
	 *
	 * @param array<string, mixed> $params receipts_q, receipts_status, receipts_sort, receipts_date_from, receipts_date_to, receipts_amount_min, receipts_amount_max.
	 * @param string               $alias  Receipt table alias.
	 * @param string               $user_alias User table alias when join_users is true.
	 * @return array{join_users:bool,where_sql:string,where_values:array<int|float|string>,order_sql:string}
	 */
	public static function admin_list_query_parts( array $params, $alias = 'r', $user_alias = 'u' ) {
		global $wpdb;
		$a  = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $alias );
		$a  = '' !== $a ? $a : 'r';
		$ua = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $user_alias );
		$ua = '' !== $ua ? $ua : 'u';

		$parts  = array();
		$values = array();
		$join   = false;

		$status = sanitize_key( (string) ( $params['receipts_status'] ?? 'all' ) );
		if ( 'pending' === $status ) {
			$parts[] = " AND {$a}.status IN ('pending','processing')";
		} elseif ( in_array( $status, array( 'approved', 'rejected', 'processing' ), true ) ) {
			$parts[]  = " AND {$a}.status = %s";
			$values[] = $status;
		}

		$df = trim( (string) ( $params['receipts_date_from'] ?? '' ) );
		if ( '' !== $df && preg_match( '/^\d{4}-\d{2}-\d{2}/', $df ) ) {
			$parts[]  = " AND {$a}.created_at >= %s";
			$values[] = substr( $df, 0, 19 );
		}
		$dt = trim( (string) ( $params['receipts_date_to'] ?? '' ) );
		if ( '' !== $dt && preg_match( '/^\d{4}-\d{2}-\d{2}/', $dt ) ) {
			$parts[]  = " AND {$a}.created_at <= %s";
			$values[] = substr( $dt, 0, 19 );
		}

		$amin_raw = trim( (string) ( $params['receipts_amount_min'] ?? '' ) );
		if ( '' !== $amin_raw && is_numeric( str_replace( ',', '.', $amin_raw ) ) ) {
			$parts[]  = " AND {$a}.amount >= %f";
			$values[] = (float) str_replace( ',', '.', $amin_raw );
		}
		$amax_raw = trim( (string) ( $params['receipts_amount_max'] ?? '' ) );
		if ( '' !== $amax_raw && is_numeric( str_replace( ',', '.', $amax_raw ) ) ) {
			$parts[]  = " AND {$a}.amount <= %f";
			$values[] = (float) str_replace( ',', '.', $amax_raw );
		}

		$q = trim( (string) ( $params['receipts_q'] ?? '' ) );
		if ( strlen( $q ) > 128 ) {
			$q = substr( $q, 0, 128 );
		}
		if ( '' !== $q ) {
			if ( preg_match( '/^\d+$/', $q ) ) {
				$n        = (int) $q;
				$like_amt = '%' . $wpdb->esc_like( $q ) . '%';
				$parts[]  = " AND ( {$a}.id = %d OR {$a}.user_id = %d OR {$a}.transaction_id = %d OR CAST({$a}.amount AS CHAR) LIKE %s )";
				$values[] = $n;
				$values[] = $n;
				$values[] = $n;
				$values[] = $like_amt;
			} elseif ( preg_match( '/^\d+[\d.,]*$/', $q ) ) {
				$parts[]  = " AND {$a}.amount = %f";
				$values[] = (float) str_replace( ',', '.', $q );
			} else {
				$join     = true;
				$u        = ltrim( trim( $q ), '@' );
				$like_u   = '%' . $wpdb->esc_like( $u ) . '%';
				$parts[]  = " AND ( {$ua}.username LIKE %s OR CONCAT(COALESCE({$ua}.first_name,''),' ',COALESCE({$ua}.last_name,'')) LIKE %s )";
				$values[] = $like_u;
				$values[] = $like_u;
			}
		}

		$sort = sanitize_key( (string) ( $params['receipts_sort'] ?? 'created_desc' ) );
		$orders = array(
			'created_desc' => "{$a}.created_at DESC, {$a}.id DESC",
			'created_asc'  => "{$a}.created_at ASC, {$a}.id ASC",
			'amount_desc'  => "{$a}.amount DESC, {$a}.id DESC",
			'amount_asc'   => "{$a}.amount ASC, {$a}.id ASC",
			'id_desc'      => "{$a}.id DESC",
		);
		$order_sql = isset( $orders[ $sort ] ) ? $orders[ $sort ] : $orders['created_desc'];

		return array(
			'join_users'   => $join,
			'where_sql'    => implode( '', $parts ),
			'where_values' => $values,
			'order_sql'    => $order_sql,
		);
	}

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
