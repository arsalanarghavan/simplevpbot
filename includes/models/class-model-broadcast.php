<?php
/**
 * Broadcast + queue.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Broadcast
 */
class SimpleVPBot_Model_Broadcast {

	/**
	 * Broadcasts table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_broadcasts';
	}

	/**
	 * Queue table.
	 *
	 * @return string
	 */
	public static function queue_table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_broadcast_queue';
	}

	/**
	 * Insert broadcast.
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
	 * Update broadcast.
	 *
	 * @param int                  $id Id.
	 * @param array<string, mixed> $data Data.
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$wpdb->update( self::table(), $data, array( 'id' => $id ) );
	}

	/**
	 * Load broadcast row by id.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function find( $id ) {
		global $wpdb;
		$t   = self::table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ) ); // phpcs:ignore
		return $row ? $row : null;
	}

	/**
	 * Stop a broadcast: mark broadcast cancelled and fail all pending/sending queue rows.
	 *
	 * @param int $broadcast_id Id.
	 * @return array{ok:bool, message?:string}
	 */
	public static function cancel_broadcast( $broadcast_id ) {
		global $wpdb;
		$bid = (int) $broadcast_id;
		if ( $bid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_id' );
		}
		$row = self::find( $bid );
		if ( ! $row ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		$st = (string) $row->status;
		if ( 'done' === $st || 'cancelled' === $st ) {
			return array( 'ok' => false, 'message' => 'not_cancellable' );
		}
		self::update( $bid, array( 'status' => 'cancelled' ) );
		$qt = self::queue_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$qt} SET status = %s, failure_kind = %s, last_error = %s WHERE broadcast_id = %d AND status IN ('pending','sending')",
				'failed',
				'cancelled',
				'admin_cancelled',
				$bid
			)
		);
		return array( 'ok' => true );
	}

	/**
	 * Current queue row status (for worker race with cancel).
	 *
	 * @param int $queue_id Queue row id.
	 * @return string|null
	 */
	public static function get_queue_status( $queue_id ) {
		global $wpdb;
		$qt = self::queue_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$s = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$qt} WHERE id = %d", (int) $queue_id ) );
		return is_string( $s ) ? $s : null;
	}

	/**
	 * Enqueue rows.
	 *
	 * @param int                  $broadcast_id Broadcast id.
	 * @param array<int, array<string, mixed>> $rows Rows.
	 */
	public static function enqueue_bulk( $broadcast_id, array $rows ) {
		global $wpdb;
		foreach ( $rows as $r ) {
			$wpdb->insert(
				self::queue_table(),
				array_merge(
					array( 'broadcast_id' => $broadcast_id ),
					$r
				)
			);
		}
	}

	/**
	 * Atomically claim up to $limit pending rows for this worker.
	 *
	 * Uses a random claim token to avoid racing with concurrent workers.
	 *
	 * @param int $limit Limit.
	 * @return array<int, object>
	 */
	public static function pop_queue( $limit = 25 ) {
		global $wpdb;
		$t     = self::queue_table();
		$limit = max( 1, (int) $limit );
		$token = 'c_' . wp_generate_password( 12, false, false );

		// Mark up to $limit pending rows as 'sending' with our token.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$t} SET status = %s WHERE status = 'pending' ORDER BY id ASC LIMIT %d",
				$token,
				$limit
			)
		); // phpcs:ignore

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t} WHERE status = %s ORDER BY id ASC",
				$token
			)
		); // phpcs:ignore
		if ( empty( $rows ) ) {
			return array();
		}
		// Switch claimed rows to 'sending' canonical state so status column stays consistent downstream.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$t} SET status = 'sending' WHERE status = %s",
				$token
			)
		); // phpcs:ignore
		foreach ( $rows as $r ) {
			$r->status = 'sending';
		}
		return $rows;
	}

	/**
	 * Update queue row.
	 *
	 * @param int                  $id Id.
	 * @param array<string, mixed> $data Data.
	 */
	public static function update_queue( $id, array $data ) {
		global $wpdb;
		$wpdb->update( self::queue_table(), $data, array( 'id' => $id ) );
	}

	/**
	 * Increment sent count.
	 *
	 * @param int $id Id.
	 */
	public static function increment_sent( $id ) {
		global $wpdb;
		$t = self::table();
		$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET sent_count = sent_count + 1 WHERE id = %d", $id ) ); // phpcs:ignore
	}

	/**
	 * Increment failed count.
	 *
	 * @param int $id Id.
	 */
	public static function increment_failed( $id ) {
		global $wpdb;
		$t = self::table();
		$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET failed_count = failed_count + 1 WHERE id = %d", $id ) ); // phpcs:ignore
	}

	/**
	 * Increment blocked count (user blocked bot / chat not reachable as block).
	 *
	 * @param int $id Broadcast id.
	 */
	public static function increment_blocked( $id ) {
		global $wpdb;
		$t = self::table();
		$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET blocked_count = blocked_count + 1 WHERE id = %d", $id ) ); // phpcs:ignore
	}

	/**
	 * Reclaim queue rows stuck in "sending" (e.g. PHP timeout mid-batch).
	 *
	 * @param int $older_than_seconds Age threshold.
	 * @return int Rows updated.
	 */
	public static function reclaim_stuck_sending( $older_than_seconds ) {
		global $wpdb;
		$t   = self::queue_table();
		$sec = max( 60, (int) $older_than_seconds );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$t} SET status = 'pending' WHERE status = 'sending' AND updated_at < DATE_SUB( NOW(), INTERVAL %d SECOND )",
				$sec
			)
		);
	}

	/**
	 * Queue aggregates for dashboard: one row per broadcast_id, bot, status, failure_kind.
	 *
	 * @param array<int> $broadcast_ids Ids.
	 * @return array<int, array<string, mixed>>
	 */
	public static function queue_stats_by_broadcast( array $broadcast_ids ) {
		global $wpdb;
		$ids = array_values(
			array_filter(
				array_map(
					static function ( $x ) {
						return (int) $x;
					},
					$broadcast_ids
				),
				static function ( $x ) {
					return $x > 0;
				}
			)
		);
		if ( empty( $ids ) ) {
			return array();
		}
		$t   = self::queue_table();
		$in  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql = "SELECT broadcast_id, bot, status, failure_kind, COUNT(*) AS cnt FROM {$t} WHERE broadcast_id IN ($in) GROUP BY broadcast_id, bot, status, failure_kind";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$prepared = $wpdb->prepare( $sql, $ids );
		$rows     = $wpdb->get_results( $prepared, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Mark broadcast completed when no pending/sending queue rows remain.
	 *
	 * @param int $broadcast_id Id.
	 */
	public static function maybe_mark_broadcast_done( $broadcast_id ) {
		global $wpdb;
		$bid = (int) $broadcast_id;
		if ( $bid < 1 ) {
			return;
		}
		$brow = self::find( $bid );
		if ( $brow && 'cancelled' === (string) $brow->status ) {
			return;
		}
		$qt = self::queue_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$qt} WHERE broadcast_id = %d AND status IN ('pending','sending')", $bid ) );
		if ( $n > 0 ) {
			return;
		}
		self::update( $bid, array( 'status' => 'done' ) );
	}

	/**
	 * Recent broadcast rows (admin history).
	 *
	 * @param int $limit  Max rows.
	 * @param int $offset Offset.
	 * @return array<int, object>
	 */
	public static function list_recent( $limit = 30, $offset = 0 ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} ORDER BY id DESC LIMIT %d OFFSET %d", max( 1, min( 100, (int) $limit ) ), max( 0, (int) $offset ) ) ); // phpcs:ignore
	}

	/**
	 * Count distinct users with queue rows for a broadcast.
	 *
	 * @param int $broadcast_id Id.
	 * @return int
	 */
	public static function count_queue_users_for_broadcast( $broadcast_id ) {
		global $wpdb;
		$qt = self::queue_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT user_id) FROM {$qt} WHERE broadcast_id = %d", (int) $broadcast_id ) );
	}

	/**
	 * Paginated recipients: distinct users per page, each with all bot queue rows.
	 *
	 * @param int $broadcast_id Id.
	 * @param int $page         1-based.
	 * @param int $per_page     Max 50.
	 * @return array{total:int, page:int, perPage:int, users: array<int, array<string, mixed>>}
	 */
	public static function list_queue_users_page( $broadcast_id, $page, $per_page ) {
		global $wpdb;
		$bid    = (int) $broadcast_id;
		$per    = max( 1, min( 50, (int) $per_page ) );
		$page_n = max( 1, (int) $page );
		$offset = ( $page_n - 1 ) * $per;
		$qt     = self::queue_table();
		$total  = self::count_queue_users_for_broadcast( $bid );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$uids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$qt} WHERE broadcast_id = %d ORDER BY user_id ASC LIMIT %d OFFSET %d", $bid, $per, $offset ) );
		if ( empty( $uids ) ) {
			return array(
				'total'   => $total,
				'page'    => $page_n,
				'perPage' => $per,
				'users'   => array(),
			);
		}
		$uids = array_map( 'absint', $uids );
		$ut   = class_exists( 'SimpleVPBot_Model_User' ) ? SimpleVPBot_Model_User::table() : $wpdb->prefix . 'svp_users';
		$in   = implode( ',', array_fill( 0, count( $uids ), '%d' ) );
		$sql  = "SELECT q.id AS q_id, q.user_id AS uid, q.bot, q.status, q.tries, q.failure_kind, q.last_error,
				u.first_name, u.last_name, u.username
				FROM {$qt} q
				LEFT JOIN {$ut} u ON u.id = q.user_id
				WHERE q.broadcast_id = %d AND q.user_id IN ({$in})
				ORDER BY q.user_id ASC, q.bot ASC";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$prepared = $wpdb->prepare( $sql, array_merge( array( $bid ), $uids ) );
		$rows     = $wpdb->get_results( $prepared, ARRAY_A );
		$rows     = is_array( $rows ) ? $rows : array();

		$by_user = array();
		foreach ( $rows as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$uid = (int) ( $r['uid'] ?? 0 );
			if ( $uid < 1 ) {
				continue;
			}
			if ( ! isset( $by_user[ $uid ] ) ) {
				$fn = isset( $r['first_name'] ) ? (string) $r['first_name'] : '';
				$ln = isset( $r['last_name'] ) ? (string) $r['last_name'] : '';
				$un = isset( $r['username'] ) ? (string) $r['username'] : '';
				$dn = trim( $fn . ' ' . $ln );
				if ( '' === $dn && '' !== $un ) {
					$dn = $un;
				}
				if ( '' === $dn ) {
					$dn = '#' . (string) $uid;
				}
				$by_user[ $uid ] = array(
					'userId'      => $uid,
					'displayName' => $dn,
					'username'    => $un,
					'rows'        => array(),
				);
			}
			$by_user[ $uid ]['rows'][] = array(
				'id'           => (int) ( $r['q_id'] ?? 0 ),
				'bot'          => (string) ( $r['bot'] ?? '' ),
				'status'       => (string) ( $r['status'] ?? '' ),
				'failureKind'  => isset( $r['failure_kind'] ) && null !== $r['failure_kind'] ? (string) $r['failure_kind'] : '',
				'lastError'    => isset( $r['last_error'] ) && null !== $r['last_error'] ? (string) $r['last_error'] : '',
				'tries'        => (int) ( $r['tries'] ?? 0 ),
			);
		}

		return array(
			'total'   => $total,
			'page'    => $page_n,
			'perPage' => $per,
			'users'   => array_values( $by_user ),
		);
	}
}
