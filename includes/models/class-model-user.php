<?php
/**
 * User model.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_User
 */
class SimpleVPBot_Model_User {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_users';
	}

	/**
	 * Find by WP id.
	 *
	 * @param int $id User id.
	 * @return object|null
	 */
	public static function find( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) ); // phpcs:ignore
	}

	/**
	 * Find by telegram id.
	 *
	 * @param int $tg_id Telegram user id.
	 * @return object|null
	 */
	public static function find_by_telegram( $tg_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE tg_user_id = %d', $tg_id ) ); // phpcs:ignore
	}

	/**
	 * Find by bale id.
	 *
	 * @param int $bale_id Bale user id.
	 * @return object|null
	 */
	public static function find_by_bale( $bale_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE bale_user_id = %d', $bale_id ) ); // phpcs:ignore
	}

	/**
	 * Resolve user from update context.
	 *
	 * @param string $bot 'telegram'|'bale'.
	 * @param int    $from_id From user id.
	 * @return object|null
	 */
	public static function find_by_bot( $bot, $from_id ) {
		return 'bale' === $bot ? self::find_by_bale( $from_id ) : self::find_by_telegram( $from_id );
	}

	/**
	 * Bot user row linked to a WordPress account (dashboard / scoped API).
	 *
	 * @param int $wp_user_id WP user ID.
	 * @return object|null
	 */
	public static function find_by_wp_user( $wp_user_id ) {
		global $wpdb;
		$uid = (int) $wp_user_id;
		if ( $uid < 1 ) {
			return null;
		}
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$col = $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'wp_user_id'" );
		if ( ! $col ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE wp_user_id = %d LIMIT 1', $uid ) ); // phpcs:ignore
	}

	/**
	 * Human-friendly label for admin/bot: name, username, platform ids, internal #id.
	 *
	 * @param object|null $u User row.
	 * @return string
	 */
	public static function label( $u ) {
		if ( ! $u ) {
			return '-';
		}
		$name = trim( (string) ( $u->first_name ?? '' ) . ' ' . (string) ( $u->last_name ?? '' ) );
		$un   = trim( (string) ( $u->username ?? '' ) );
		$tg   = (int) ( $u->tg_user_id ?? 0 );
		$bl   = (int) ( $u->bale_user_id ?? 0 );
		$bits = array();
		if ( $name !== '' ) {
			$bits[] = $name;
		}
		if ( $un !== '' ) {
			$bits[] = '@' . $un;
		}
		if ( $tg ) {
			$bits[] = 'tg:' . $tg;
		}
		if ( $bl ) {
			$bits[] = 'bale:' . $bl;
		}
		$bits[] = '#' . (int) ( $u->id ?? 0 );
		return implode( ' | ', $bits );
	}

	/**
	 * Create user row.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return int Insert id.
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
	 * Attach or detach WordPress user for dashboard scoping (unique wp_user_id).
	 *
	 * @param int      $svp_user_id svp_users.id.
	 * @param int|null $wp_user_id  WP user id or null/0 to clear.
	 * @return array{ok:bool, message?:string}
	 */
	public static function set_linked_wp_user( $svp_user_id, $wp_user_id ) {
		global $wpdb;
		$sid = (int) $svp_user_id;
		if ( $sid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_svp_user' );
		}
		$wp = null === $wp_user_id ? 0 : (int) $wp_user_id;
		if ( $wp > 0 ) {
			$wpdb->update( self::table(), array( 'wp_user_id' => null ), array( 'wp_user_id' => $wp ) ); // phpcs:ignore
		}
		self::update(
			$sid,
			array(
				'wp_user_id' => $wp > 0 ? $wp : null,
			)
		);
		return array( 'ok' => true );
	}

	/**
	 * All approved users for broadcast.
	 *
	 * @return array<int, object>
	 */
	public static function all_approved() {
		global $wpdb;
		return $wpdb->get_results( "SELECT * FROM " . self::table() . " WHERE status = 'approved'" ); // phpcs:ignore
	}

	/**
	 * Normalize Persian/Arabic digits to ASCII (same mapping as SimpleVPBot_Bot_Runtime::normalize_digits).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function normalize_query_digits( $text ) {
		$s = (string) $text;
		static $map = null;
		if ( null === $map ) {
			$map = array(
				'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			);
		}
		return strtr( $s, $map );
	}

	/**
	 * Search users: internal id, Telegram/Bale chat id, @username, name, phone (digits normalized).
	 *
	 * @param string $query Query string.
	 * @param int    $limit Max rows (1–20).
	 * @return array<int, object>
	 */
	public static function search( $query, $limit = 10 ) {
		global $wpdb;
		$limit = max( 1, min( 20, (int) $limit ) );
		$q     = trim( (string) $query );
		if ( '' === $q ) {
			return array();
		}
		$qn = self::normalize_query_digits( $q );
		$t  = self::table();

		if ( preg_match( '/^\d+$/', $qn ) ) {
			$n = (int) $qn;
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d OR tg_user_id = %d OR bale_user_id = %d ORDER BY id DESC LIMIT %d", $n, $n, $n, $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$u       = ltrim( trim( $qn ), '@' );
		$like_u  = '%' . $wpdb->esc_like( $u ) . '%';
		$digits  = preg_replace( '/\D+/', '', $qn );
		$conds   = array();
		$conds[] = $wpdb->prepare( 'username LIKE %s', $like_u );
		$conds[] = $wpdb->prepare( "CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')) LIKE %s", $like_u );
		if ( '' !== $digits ) {
			$like_phone = '%' . $wpdb->esc_like( $digits ) . '%';
			$conds[]    = $wpdb->prepare( 'phone LIKE %s', $like_phone );
		}
		$sql = 'SELECT * FROM ' . $t . ' WHERE (' . implode( ' OR ', $conds ) . ') ORDER BY id DESC LIMIT ' . (int) $limit;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql );
	}

	/**
	 * Resolve admin inbound-link target: exactly one user from free-text query.
	 *
	 * @param string $query Same rules as {@see search()} (id, tg/bale id, @username, name, phone).
	 * @return array{ok:true,user_id:int,user:object}|array{ok:false,reason:string}
	 */
	public static function resolve_unique_for_admin_link( $query ) {
		$q = trim( (string) $query );
		if ( '' === $q ) {
			return array( 'ok' => false, 'reason' => 'empty' );
		}
		$rows = self::search( $q, 2 );
		if ( ! is_array( $rows ) || count( $rows ) < 1 ) {
			return array( 'ok' => false, 'reason' => 'not_found' );
		}
		if ( count( $rows ) > 1 ) {
			return array( 'ok' => false, 'reason' => 'ambiguous' );
		}
		$u = $rows[0];
		return array(
			'ok'      => true,
			'user_id' => (int) ( $u->id ?? 0 ),
			'user'    => $u,
		);
	}

	/**
	 * SQL fragment and placeholder values for admin user list search (same rules as search()).
	 *
	 * @param string $query Search text.
	 * @param string $alias Table alias (letters, digits, underscore only).
	 * @return array{sql:string,values:array<int,int|float|string>}|null Null when query is empty.
	 */
	public static function admin_search_users_clause( $query, $alias = 'u' ) {
		global $wpdb;
		$q = trim( (string) $query );
		if ( '' === $q ) {
			return null;
		}
		if ( strlen( $q ) > 128 ) {
			$q = substr( $q, 0, 128 );
		}
		$a  = preg_replace( '/[^a-zA-Z0-9_]/', '', $alias );
		$a  = '' !== $a ? $a : 'u';
		$qn = self::normalize_query_digits( $q );

		if ( preg_match( '/^\d+$/', $qn ) ) {
			$n = (int) $qn;
			return array(
				'sql'    => " AND ( {$a}.id = %d OR {$a}.tg_user_id = %d OR {$a}.bale_user_id = %d ) ",
				'values' => array( $n, $n, $n ),
			);
		}

		$u      = ltrim( trim( $qn ), '@' );
		$like_u = '%' . $wpdb->esc_like( $u ) . '%';
		$parts  = array(
			$wpdb->prepare( "{$a}.username LIKE %s", $like_u ),
			$wpdb->prepare( "CONCAT(COALESCE({$a}.first_name,''),' ',COALESCE({$a}.last_name,'')) LIKE %s", $like_u ),
		);
		$digits = preg_replace( '/\D+/', '', $qn );
		if ( '' !== $digits ) {
			$like_phone = '%' . $wpdb->esc_like( $digits ) . '%';
			$parts[]    = $wpdb->prepare( "{$a}.phone LIKE %s", $like_phone );
		}
		return array(
			'sql'    => ' AND (' . implode( ' OR ', $parts ) . ') ',
			'values' => array(),
		);
	}

	/**
	 * Count by status.
	 *
	 * @param string $status Status.
	 * @return int
	 */
	public static function count_status( $status ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE status = %s', $status ) ); // phpcs:ignore
	}

	/**
	 * Count users referred by this id.
	 *
	 * @param int $referrer_id svp_users.id.
	 * @return int
	 */
	public static function count_invited_by( $referrer_id ) {
		global $wpdb;
		$rid = (int) $referrer_id;
		if ( $rid < 1 ) {
			return 0;
		}
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE invited_by = %d', $rid ) ); // phpcs:ignore
	}

	/**
	 * Users who registered with this referrer (newest first).
	 *
	 * @param int $referrer_id svp_users.id.
	 * @param int $limit       Max rows (cap 200).
	 * @return array<int, object>
	 */
	public static function list_invited_by( $referrer_id, $limit = 100 ) {
		global $wpdb;
		$rid = (int) $referrer_id;
		if ( $rid < 1 ) {
			return array();
		}
		$lim = max( 1, min( 200, (int) $limit ) );
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE invited_by = %d ORDER BY id DESC LIMIT %d',
				$rid,
				$lim
			)
		); // phpcs:ignore
	}

	/**
	 * Approved users for admin list (paged).
	 *
	 * @param int $offset Offset.
	 * @param int $limit  Limit (max 20).
	 * @return array<int, object>
	 */
	public static function list_approved_paged( $offset, $limit = 8 ) {
		global $wpdb;
		$off = max( 0, (int) $offset );
		$lim = max( 1, min( 20, (int) $limit ) );
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, first_name, last_name, username, tg_user_id, bale_user_id, balance, status FROM ' . self::table() . " WHERE status = %s ORDER BY id DESC LIMIT %d OFFSET %d",
				'approved',
				$lim,
				$off
			)
		); // phpcs:ignore
	}

	/**
	 * Users by status for admin queue (newest first).
	 *
	 * @param string $status pending|approved|rejected|blocked.
	 * @param int    $offset Offset.
	 * @param int    $limit  Max 20.
	 * @return array<int, object>
	 */
	public static function list_by_status_paged( $status, $offset, $limit = 5 ) {
		global $wpdb;
		$st = sanitize_key( (string) $status );
		if ( ! in_array( $st, array( 'pending', 'approved', 'rejected', 'blocked' ), true ) ) {
			return array();
		}
		$off = max( 0, (int) $offset );
		$lim = max( 1, min( 20, (int) $limit ) );
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE status = %s ORDER BY id DESC LIMIT %d OFFSET %d',
				$st,
				$lim,
				$off
			)
		); // phpcs:ignore
	}

	/**
	 * Find single approved user by username (case-insensitive).
	 *
	 * Returns null when not found or ambiguous.
	 *
	 * @param string $username Username with or without @.
	 * @return object|null
	 */
	public static function find_unique_approved_by_username( $username ) {
		global $wpdb;
		$u = ltrim( trim( (string) $username ), '@' );
		if ( '' === $u ) {
			return null;
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE status = %s AND LOWER(username) = LOWER(%s) LIMIT 2',
				'approved',
				$u
			)
		); // phpcs:ignore
		if ( ! is_array( $rows ) || 1 !== count( $rows ) ) {
			return null;
		}
		return $rows[0];
	}

	/**
	 * Find single approved user by Telegram/Bale chat id.
	 *
	 * Returns null when not found or ambiguous.
	 *
	 * @param int $chat_id Chat id.
	 * @return object|null
	 */
	public static function find_unique_approved_by_chat_id( $chat_id ) {
		global $wpdb;
		$id = (int) $chat_id;
		if ( $id <= 0 ) {
			return null;
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE status = %s AND (tg_user_id = %d OR bale_user_id = %d) LIMIT 2',
				'approved',
				$id,
				$id
			)
		); // phpcs:ignore
		if ( ! is_array( $rows ) || 1 !== count( $rows ) ) {
			return null;
		}
		return $rows[0];
	}

	/**
	 * Merge two user rows (sync): keep $keep_id, move data from $drop_id.
	 *
	 * @param int $keep_id Keep user id.
	 * @param int $drop_id Drop user id.
	 */
	public static function merge_users( $keep_id, $drop_id ) {
		global $wpdb;
		$keep = self::find( $keep_id );
		$drop = self::find( $drop_id );
		if ( ! $keep || ! $drop || (int) $keep_id === (int) $drop_id ) {
			return;
		}
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		try {
			$upd = array();
			if ( empty( $keep->tg_user_id ) && ! empty( $drop->tg_user_id ) ) {
				$upd['tg_user_id'] = $drop->tg_user_id;
			}
			if ( empty( $keep->bale_user_id ) && ! empty( $drop->bale_user_id ) ) {
				$upd['bale_user_id'] = $drop->bale_user_id;
			}
			$upd['balance'] = (float) $keep->balance + (float) $drop->balance;
			if ( $upd ) {
				self::update( $keep_id, $upd );
			}
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}svp_services SET user_id = %d WHERE user_id = %d AND deleted_at IS NULL",
					$keep_id,
					$drop_id
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->update( $wpdb->prefix . 'svp_transactions', array( 'user_id' => $keep_id ), array( 'user_id' => $drop_id ) );
			$wpdb->update( $wpdb->prefix . 'svp_receipts', array( 'user_id' => $keep_id ), array( 'user_id' => $drop_id ) );
			$wpdb->update( $wpdb->prefix . 'svp_pending_approvals', array( 'user_id' => $keep_id ), array( 'user_id' => $drop_id ) );
			$wpdb->update( $wpdb->prefix . 'svp_broadcast_queue', array( 'user_id' => $keep_id ), array( 'user_id' => $drop_id ) );
			$wpdb->update( $wpdb->prefix . 'svp_sync_codes', array( 'user_id' => $keep_id ), array( 'user_id' => $drop_id ) );
			$wpdb->update( self::table(), array( 'invited_by' => $keep_id ), array( 'invited_by' => $drop_id ) );
			$wpdb->delete( self::table(), array( 'id' => $drop_id ) );
			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		} catch ( Throwable $e ) { // phpcs:ignore
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			SimpleVPBot_Logger::error( 'merge_users failed', array( 'err' => $e->getMessage() ) );
		}
	}
}
