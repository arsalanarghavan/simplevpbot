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
					'job_id'   => $jid,
					'user_id'  => $uid,
					'status'   => 'pending',
					'tries'    => 0,
				)
			);
		}
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

