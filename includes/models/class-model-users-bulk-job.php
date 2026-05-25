<?php
/**
 * Users bulk jobs queue model.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SimpleVPBot_Model_Users_Bulk_Job {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_users_bulk_jobs';
	}

	public static function items_table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_users_bulk_job_items';
	}

	public static function insert_job( array $data ) {
		global $wpdb;
		$wpdb->insert( self::table(), $data );
		return (int) $wpdb->insert_id;
	}

	public static function update_job( $id, array $data ) {
		global $wpdb;
		$wpdb->update( self::table(), $data, array( 'id' => (int) $id ) );
	}

	public static function enqueue_users( $job_id, array $user_ids ) {
		global $wpdb;
		$t = self::items_table();
		$jid = (int) $job_id;
		foreach ( $user_ids as $uid ) {
			$uid = (int) $uid;
			if ( $uid < 1 ) {
				continue;
			}
			$wpdb->insert(
				$t,
				array(
					'job_id'       => $jid,
					'user_id'      => $uid,
					'panel_id'     => 0,
					'inbound_id'   => 0,
					'client_email' => '',
					'status'       => 'pending',
					'tries'        => 0,
				)
			);
		}
	}

	/**
	 * Enqueue one item per active panel client (panel-first bulk ops).
	 *
	 * @param int                             $job_id  Job id.
	 * @param array<int, array<string,mixed>> $targets Each: panel_id, inbound_id, email, user_id?.
	 * @return int Number queued.
	 */
	public static function enqueue_panel_targets( $job_id, array $targets ) {
		global $wpdb;
		$t   = self::items_table();
		$jid = (int) $job_id;
		$seen = array();
		$n    = 0;
		foreach ( $targets as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$pid = (int) ( $row['panel_id'] ?? 0 );
			$iid = (int) ( $row['inbound_id'] ?? 0 );
			$em  = trim( (string) ( $row['email'] ?? $row['client_email'] ?? '' ) );
			if ( $pid < 1 || $iid < 1 || '' === $em ) {
				continue;
			}
			$key = $pid . ':' . $iid . ':' . $em;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$uid = (int) ( $row['user_id'] ?? 0 );
			$wpdb->insert(
				$t,
				array(
					'job_id'       => $jid,
					'user_id'      => max( 0, $uid ),
					'panel_id'     => $pid,
					'inbound_id'   => $iid,
					'client_email' => $em,
					'status'       => 'pending',
					'tries'        => 0,
				)
			);
			++$n;
		}
		return $n;
	}

	public static function list_jobs( $limit = 20, $offset = 0 ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t} ORDER BY id DESC LIMIT %d OFFSET %d",
				max( 1, min( 100, (int) $limit ) ),
				max( 0, (int) $offset )
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function count_jobs() {
		global $wpdb;
		$t = self::table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Jobs created by a reseller dashboard actor (svp_users.id). Admin uses 0 / omit filter via {@see list_jobs()}.
	 *
	 * @param int $created_by_svp_user_id Reseller svp_users.id.
	 * @param int $limit                Page size.
	 * @param int $offset               Offset.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_jobs_for_svp_actor( $created_by_svp_user_id, $limit = 20, $offset = 0 ) {
		global $wpdb;
		$t   = self::table();
		$cid = (int) $created_by_svp_user_id;
		if ( $cid < 1 ) {
			return array();
		}
		$lim = max( 1, min( 100, (int) $limit ) );
		$off = max( 0, (int) $offset );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t} WHERE created_by_svp_user_id = %d ORDER BY id DESC LIMIT %d OFFSET %d",
				$cid,
				$lim,
				$off
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param int $created_by_svp_user_id Reseller svp_users.id.
	 * @return int
	 */
	public static function count_jobs_for_svp_actor( $created_by_svp_user_id ) {
		global $wpdb;
		$t   = self::table();
		$cid = (int) $created_by_svp_user_id;
		if ( $cid < 1 ) {
			return 0;
		}
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE created_by_svp_user_id = %d", $cid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Whether the job row belongs to this reseller actor (or caller is admin bypass via $actor_uid 0).
	 *
	 * @param int $job_id      Job id.
	 * @param int $actor_uid   Reseller svp_users.id or 0 when admin lists any job.
	 * @return bool
	 */
	public static function job_visible_to_svp_actor( $job_id, $actor_uid ) {
		$row = self::get_job( $job_id );
		if ( ! is_array( $row ) || empty( $row ) ) {
			return false;
		}
		$actor_uid = (int) $actor_uid;
		if ( $actor_uid < 1 ) {
			return true;
		}
		return (int) ( $row['created_by_svp_user_id'] ?? 0 ) === $actor_uid;
	}

	public static function list_job_items( $job_id, $page = 1, $per_page = 25, $status = '' ) {
		global $wpdb;
		$t      = self::items_table();
		$jid    = (int) $job_id;
		$page   = max( 1, (int) $page );
		$per    = max( 1, min( 100, (int) $per_page ) );
		$offset = ( $page - 1 ) * $per;
		$where  = ' WHERE job_id = %d ';
		$args   = array( $jid );
		if ( '' !== $status ) {
			$where .= ' AND status = %s ';
			$args[] = sanitize_key( $status );
		}
		$sql_total = "SELECT COUNT(*) FROM {$t} {$where}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare( $sql_total, $args ) );
		$sql_rows = "SELECT * FROM {$t} {$where} ORDER BY id ASC LIMIT %d OFFSET %d";
		$args_rows = array_merge( $args, array( $per, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql_rows, $args_rows ), ARRAY_A );
		return array(
			'total'   => $total,
			'page'    => $page,
			'perPage' => $per,
			'rows'    => is_array( $rows ) ? $rows : array(),
		);
	}

	public static function get_job( $job_id ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $job_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Item status counts per job for dashboard history cards.
	 *
	 * @param array<int> $job_ids Job ids.
	 * @return array<int, array<string, mixed>>
	 */
	public static function item_status_counts_by_jobs( array $job_ids ) {
		global $wpdb;
		$ids = array_values(
			array_filter(
				array_map(
					static function ( $x ) {
						return (int) $x;
					},
					$job_ids
				),
				static function ( $x ) {
					return $x > 0;
				}
			)
		);
		if ( empty( $ids ) ) {
			return array();
		}
		$t   = self::items_table();
		$in  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql = "SELECT job_id, status, COUNT(*) AS cnt FROM {$t} WHERE job_id IN ($in) GROUP BY job_id, status";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$prepared = $wpdb->prepare( $sql, $ids );
		$rows     = $wpdb->get_results( $prepared, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	public static function pop_pending_items( $limit = 20 ) {
		global $wpdb;
		$t     = self::items_table();
		$limit = max( 1, min( 100, (int) $limit ) );
		$token = 'u_' . wp_generate_password( 12, false, false );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$t} SET status = %s WHERE status = 'pending' ORDER BY id ASC LIMIT %d",
				$token,
				$limit
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$t} WHERE status = %s ORDER BY id ASC", $token ),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( empty( $rows ) ) {
			return array();
		}
		$wpdb->query(
			$wpdb->prepare( "UPDATE {$t} SET status = 'processing' WHERE status = %s", $token )
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $rows;
	}

	public static function update_item( $item_id, array $data ) {
		global $wpdb;
		$wpdb->update( self::items_table(), $data, array( 'id' => (int) $item_id ) );
	}

	public static function maybe_mark_job_done( $job_id ) {
		global $wpdb;
		$jid = (int) $job_id;
		$t_items = self::items_table();
		$t_jobs  = self::table();
		$pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t_items} WHERE job_id = %d AND status IN ('pending','processing')", $jid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $pending > 0 ) {
			$wpdb->update( $t_jobs, array( 'status' => 'processing' ), array( 'id' => $jid ) );
			return;
		}
		$failed = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t_items} WHERE job_id = %d AND status = 'failed'", $jid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$status = $failed > 0 ? 'failed' : 'done';
		$wpdb->update( $t_jobs, array( 'status' => $status, 'finished_at' => current_time( 'mysql', true ) ), array( 'id' => $jid ) );
	}
}

