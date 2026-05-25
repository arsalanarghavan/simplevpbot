<?php
/**
 * Dashboard log rows (wp_svp_logs).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Log
 */
class SimpleVPBot_Model_Log {

	/**
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_logs';
	}

	/**
	 * Paginated log list.
	 *
	 * @param int    $page     Page (1-based).
	 * @param int    $per_page Per page (max 100).
	 * @param string $level    Level filter or empty.
	 * @param string $search   Message search.
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public static function list( $page, $per_page, $level = '', $search = '' ) {
		global $wpdb;
		$table   = self::table();
		$page    = max( 1, (int) $page );
		$per     = max( 1, min( 100, (int) $per_page ) );
		$offset  = ( $page - 1 ) * $per;
		$where   = array( '1=1' );
		$values  = array();

		$level = sanitize_key( (string) $level );
		if ( '' !== $level ) {
			$where[]  = 'level = %s';
			$values[] = $level;
		}
		$search = trim( (string) $search );
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = 'message LIKE %s';
			$values[] = $like;
		}
		$where_sql = implode( ' AND ', $where );

		if ( ! empty( $values ) ) {
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$values
				)
			);
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$list_values = array_merge( $values, array( $per, $offset ) );
		$rows        = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, level, message, context_json, created_at FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$list_values
			),
			ARRAY_A
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$ctx = null;
			if ( ! empty( $row['context_json'] ) ) {
				$decoded = json_decode( (string) $row['context_json'], true );
				$ctx     = is_array( $decoded ) ? $decoded : (string) $row['context_json'];
			}
			$out[] = array(
				'id'         => (int) ( $row['id'] ?? 0 ),
				'level'      => (string) ( $row['level'] ?? '' ),
				'message'    => (string) ( $row['message'] ?? '' ),
				'context'    => $ctx,
				'created_at' => (string) ( $row['created_at'] ?? '' ),
			);
		}

		return array(
			'rows'  => $out,
			'total' => $total,
		);
	}

	/**
	 * Delete logs older than N days (0 = all rows).
	 *
	 * @param int $days Days; 0 truncates entire table.
	 * @return int Rows deleted.
	 */
	public static function delete_older_than_days( $days ) {
		global $wpdb;
		$table = self::table();
		$days  = (int) $days;
		if ( $days <= 0 ) {
			$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $count;
		}
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cutoff
			)
		);
	}
}
