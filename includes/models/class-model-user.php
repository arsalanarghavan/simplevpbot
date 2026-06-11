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
	const RESELLER_PERMISSION_KEYS = array(
		'users.manage',
		'users.bulk',
		'broadcast.send',
		'receipts.review',
		'plans.manage',
		'services.manage',
		'marketing.lifecycle',
	);

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
		$id = (int) $wpdb->insert_id;
		if ( $id > 0 && class_exists( 'SimpleVPBot_Reseller_Closure' ) ) {
			SimpleVPBot_Reseller_Closure::rebuild_for_user( $id );
		}
		return $id;
	}

	/**
	 * Update.
	 *
	 * @param int                  $id Id.
	 * @param array<string, mixed> $data Data.
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$uid = (int) $id;
		$prev = null;
		if ( $uid > 0 && array_key_exists( 'invited_by', $data ) ) {
			$prev    = self::find( $uid );
			$new_inv = isset( $data['invited_by'] ) ? (int) $data['invited_by'] : 0;
			if ( $new_inv > 0 && class_exists( 'SimpleVPBot_Reseller_Closure' )
				&& SimpleVPBot_Reseller_Closure::invited_by_would_cycle( $uid, $new_inv ) ) {
				unset( $data['invited_by'] );
			}
		}
		$wpdb->update( self::table(), $data, array( 'id' => $uid ) );
		if ( $prev && array_key_exists( 'invited_by', $data ) ) {
			$old_inv = (int) ( $prev->invited_by ?? 0 );
			$new_inv = isset( $data['invited_by'] ) ? (int) $data['invited_by'] : 0;
			if ( $old_inv !== $new_inv ) {
				if ( class_exists( 'SimpleVPBot_Reseller_Closure' ) ) {
					SimpleVPBot_Reseller_Closure::on_invited_by_changed( $uid, $old_inv, $new_inv );
				} else {
					if ( $old_inv > 0 ) {
						self::invalidate_reseller_scope_cache( $old_inv );
					}
					if ( $new_inv > 0 ) {
						self::invalidate_reseller_scope_cache( $new_inv );
					}
				}
			}
		}
	}

	/**
	 * Atomic balance increment (avoids read-modify-write races).
	 *
	 * @param int   $user_id svp_users.id.
	 * @param float $delta   Amount to add (may be negative).
	 * @return bool True if a row was updated.
	 */
	public static function increment_balance( $user_id, $delta ) {
		global $wpdb;
		$uid = (int) $user_id;
		$d   = (float) $delta;
		if ( $uid < 1 ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . ' SET balance = balance + %f WHERE id = %d', $d, $uid ) );
		return (int) $wpdb->rows_affected > 0;
	}

	/**
	 * Atomically subtract balance only if current balance is sufficient (purchase from wallet).
	 *
	 * @param int   $user_id svp_users.id.
	 * @param float $amount  Toman (positive).
	 * @return bool True if one row was updated.
	 */
	public static function decrement_balance_if_sufficient( $user_id, $amount ) {
		global $wpdb;
		$uid = (int) $user_id;
		$amt = round( (float) $amount, 2 );
		if ( $uid < 1 || $amt <= 0 ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::table() . ' SET balance = balance - %f WHERE id = %d AND balance >= %f',
				$amt,
				$uid,
				$amt
			)
		);
		return (int) $wpdb->rows_affected > 0;
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
	 * Dashboard user list filters and sort (alias `u`).
	 *
	 * @param array<string, mixed> $params users_status, users_role, users_platform, users_min_svc, users_max_svc, users_date_from, users_date_to, users_sort.
	 * @param string               $alias  User table alias.
	 * @return array{where_sql:string,where_values:array<int|float|string>,order_sql:string,needs_svc_join:bool}
	 */
	public static function admin_list_query_parts( array $params, $alias = 'u' ) {
		$a = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $alias );
		$a = '' !== $a ? $a : 'u';

		$parts          = array();
		$values         = array();
		$needs_svc_join = false;
		$skip_status    = ! empty( $params['skip_status'] );

		$status = sanitize_key( (string) ( $params['users_status'] ?? 'all' ) );
		if ( ! $skip_status && 'all' !== $status && in_array( $status, array( 'pending', 'approved', 'rejected', 'blocked' ), true ) ) {
			$parts[]  = " AND {$a}.status = %s";
			$values[] = $status;
		}

		$role = sanitize_key( (string) ( $params['users_role'] ?? 'all' ) );
		if ( 'all' !== $role && in_array( $role, array( 'user', 'reseller', 'admin' ), true ) ) {
			$parts[]  = " AND {$a}.role = %s";
			$values[] = $role;
		}

		$platform = sanitize_key( (string) ( $params['users_platform'] ?? 'all' ) );
		if ( 'telegram' === $platform ) {
			$parts[] = " AND {$a}.tg_user_id > 0";
		} elseif ( 'bale' === $platform ) {
			$parts[] = " AND {$a}.bale_user_id > 0";
		} elseif ( 'both' === $platform ) {
			$parts[] = " AND {$a}.tg_user_id > 0 AND {$a}.bale_user_id > 0";
		} elseif ( 'none' === $platform ) {
			$parts[] = " AND COALESCE({$a}.tg_user_id, 0) = 0 AND COALESCE({$a}.bale_user_id, 0) = 0";
		}

		$df = trim( (string) ( $params['users_date_from'] ?? '' ) );
		if ( '' !== $df && preg_match( '/^\d{4}-\d{2}-\d{2}/', $df ) ) {
			$parts[]  = " AND {$a}.created_at >= %s";
			$values[] = substr( $df, 0, 10 ) . ' 00:00:00';
		}
		$dt = trim( (string) ( $params['users_date_to'] ?? '' ) );
		if ( '' !== $dt && preg_match( '/^\d{4}-\d{2}-\d{2}/', $dt ) ) {
			$parts[]  = " AND {$a}.created_at <= %s";
			$values[] = substr( $dt, 0, 10 ) . ' 23:59:59';
		}

		$min_svc = trim( (string) ( $params['users_min_svc'] ?? '' ) );
		if ( '' !== $min_svc && is_numeric( $min_svc ) ) {
			$needs_svc_join = true;
			$parts[]        = ' AND COALESCE(s.svc_count, 0) >= %d';
			$values[]       = max( 0, (int) $min_svc );
		}
		$max_svc = trim( (string) ( $params['users_max_svc'] ?? '' ) );
		if ( '' !== $max_svc && is_numeric( $max_svc ) ) {
			$needs_svc_join = true;
			$parts[]        = ' AND COALESCE(s.svc_count, 0) <= %d';
			$values[]       = max( 0, (int) $max_svc );
		}

		$sort = sanitize_key( (string) ( $params['users_sort'] ?? 'created_desc' ) );
		if ( in_array( $sort, array( 'services_desc', 'services_asc' ), true ) ) {
			$needs_svc_join = true;
		}
		$orders = array(
			'created_desc'  => "{$a}.created_at DESC, {$a}.id DESC",
			'created_asc'   => "{$a}.created_at ASC, {$a}.id ASC",
			'id_desc'       => "{$a}.id DESC",
			'id_asc'        => "{$a}.id ASC",
			'services_desc' => 'COALESCE(s.svc_count, 0) DESC, ' . $a . '.id DESC',
			'services_asc'  => 'COALESCE(s.svc_count, 0) ASC, ' . $a . '.id ASC',
			'status_asc'    => "{$a}.status ASC, {$a}.id DESC",
			'status_desc'   => "{$a}.status DESC, {$a}.id DESC",
			'name_asc'      => "CONCAT(COALESCE({$a}.first_name,''), ' ', COALESCE({$a}.last_name,'')) ASC, {$a}.id DESC",
			'name_desc'     => "CONCAT(COALESCE({$a}.first_name,''), ' ', COALESCE({$a}.last_name,'')) DESC, {$a}.id DESC",
		);
		$order_sql = isset( $orders[ $sort ] ) ? $orders[ $sort ] : $orders['created_desc'];

		return array(
			'where_sql'      => implode( '', $parts ),
			'where_values'   => $values,
			'order_sql'      => $order_sql,
			'needs_svc_join' => $needs_svc_join,
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
	 * Users by status limited to given ids (bot admin reseller scope).
	 *
	 * @param string       $status pending|approved|rejected|blocked.
	 * @param int          $offset Offset.
	 * @param int          $limit  Max 20.
	 * @param array<int>   $user_ids Allowed svp_users.id values.
	 * @return array<int, object>
	 */
	public static function list_by_status_paged_for_ids( $status, $offset, $limit, array $user_ids ) {
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
		$st = sanitize_key( (string) $status );
		if ( ! in_array( $st, array( 'pending', 'approved', 'rejected', 'blocked' ), true ) ) {
			return array();
		}
		$off = max( 0, (int) $offset );
		$lim = max( 1, min( 20, (int) $limit ) );
		$ph  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . self::table() . " WHERE status = %s AND id IN ({$ph}) ORDER BY id DESC LIMIT %d OFFSET %d",
				array_merge( array( $st ), $ids, array( $lim, $off ) )
			)
		);
	}

	/**
	 * Count users by status within id set.
	 *
	 * @param string     $status Status.
	 * @param array<int> $user_ids Ids.
	 * @return int
	 */
	public static function count_by_status_for_ids( $status, array $user_ids ) {
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
		$st = sanitize_key( (string) $status );
		if ( ! in_array( $st, array( 'pending', 'approved', 'rejected', 'blocked' ), true ) ) {
			return 0;
		}
		$ph = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM " . self::table() . " WHERE status = %s AND id IN ({$ph})",
				array_merge( array( $st ), $ids )
			)
		);
	}

	/**
	 * Display labels keyed by svp_users.id (batch).
	 *
	 * @param array<int> $user_ids User ids.
	 * @return array<int, string>
	 */
	public static function labels_by_ids( array $user_ids ) {
		$ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $user_ids ),
					static function ( $v ) {
						return $v > 0;
					}
				)
			)
		);
		if ( empty( $ids ) ) {
			return array();
		}
		global $wpdb;
		$ph   = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE id IN ({$ph})", $ids )
		);
		$out = array();
		foreach ( (array) $rows as $row ) {
			if ( is_object( $row ) && isset( $row->id ) ) {
				$out[ (int) $row->id ] = self::label( $row );
			}
		}
		return $out;
	}

	/**
	 * Count direct invitees (invited_by) keyed by reseller svp_users.id.
	 *
	 * @param array<int> $reseller_ids Reseller ids.
	 * @return array<int, int>
	 */
	public static function direct_children_count_map_for_ids( array $reseller_ids ) {
		$ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $reseller_ids ),
					static function ( $v ) {
						return $v > 0;
					}
				)
			)
		);
		if ( empty( $ids ) ) {
			return array();
		}
		global $wpdb;
		$ph   = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT invited_by AS rid, COUNT(*) AS cnt FROM " . self::table() . " WHERE invited_by IN ({$ph}) GROUP BY invited_by",
				$ids
			),
			ARRAY_A
		);
		$out = array();
		foreach ( (array) $rows as $row ) {
			$rid = (int) ( $row['rid'] ?? 0 );
			if ( $rid > 0 ) {
				$out[ $rid ] = (int) ( $row['cnt'] ?? 0 );
			}
		}
		return $out;
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
	 * Whether two users may be merged without losing platform ids (Telegram/Bale).
	 *
	 * @param object $keep Keep row.
	 * @param object $drop Drop row.
	 * @param string $policy `strict` (dashboard): approved users, no reseller, no conflicting TG/Bale ids. `internal`: only block when both sides have different non-zero TG or different non-zero Bale (allows DB dedupe of duplicate rows).
	 * @return array{ok:bool, code?:string}
	 */
	public static function merge_users_allowed( $keep, $drop, $policy = 'strict' ) {
		if ( ! $keep || ! $drop || ! is_object( $keep ) || ! is_object( $drop ) ) {
			return array( 'ok' => false, 'code' => 'missing_user' );
		}
		$k_tg = (int) ( $keep->tg_user_id ?? 0 );
		$d_tg = (int) ( $drop->tg_user_id ?? 0 );
		$k_bl = (int) ( $keep->bale_user_id ?? 0 );
		$d_bl = (int) ( $drop->bale_user_id ?? 0 );
		if ( $k_tg > 0 && $d_tg > 0 && $k_tg !== $d_tg ) {
			return array( 'ok' => false, 'code' => 'both_telegram' );
		}
		if ( $k_bl > 0 && $d_bl > 0 && $k_bl !== $d_bl ) {
			return array( 'ok' => false, 'code' => 'both_bale' );
		}
		if ( 'strict' !== $policy ) {
			return array( 'ok' => true );
		}
		$rk = sanitize_key( (string) ( $keep->role ?? 'user' ) );
		$rd = sanitize_key( (string) ( $drop->role ?? 'user' ) );
		if ( 'reseller' === $rk || 'reseller' === $rd ) {
			return array( 'ok' => false, 'code' => 'reseller' );
		}
		$sk = sanitize_key( (string) ( $keep->status ?? '' ) );
		$sd = sanitize_key( (string) ( $drop->status ?? '' ) );
		if ( 'approved' !== $sk || 'approved' !== $sd ) {
			return array( 'ok' => false, 'code' => 'not_approved' );
		}
		return array( 'ok' => true );
	}

	/**
	 * Merge two user rows (sync): keep $keep_id, move data from $drop_id.
	 *
	 * @param int    $keep_id Keep user id.
	 * @param int    $drop_id Drop user id.
	 * @param string $policy  See {@see merge_users_allowed()}.
	 * @return bool True when merge ran; false when skipped (validation or missing rows).
	 */
	public static function merge_users( $keep_id, $drop_id, $policy = 'strict' ) {
		global $wpdb;
		$keep = self::find( $keep_id );
		$drop = self::find( $drop_id );
		if ( ! $keep || ! $drop || (int) $keep_id === (int) $drop_id ) {
			return false;
		}
		$gate = self::merge_users_allowed( $keep, $drop, $policy );
		if ( empty( $gate['ok'] ) ) {
			SimpleVPBot_Logger::warning(
				'merge_users blocked',
				array(
					'keep_id' => (int) $keep_id,
					'drop_id' => (int) $drop_id,
					'code'    => isset( $gate['code'] ) ? (string) $gate['code'] : '',
				)
			);
			return false;
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
			return true;
		} catch ( Throwable $e ) { // phpcs:ignore
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			SimpleVPBot_Logger::error( 'merge_users failed', array( 'err' => $e->getMessage() ) );
		}
		return false;
	}

	/**
	 * Whether a user row is reseller role.
	 *
	 * @param object|null $row User row.
	 * @return bool
	 */
	public static function is_reseller_row( $row ) {
		if ( ! $row || ! is_object( $row ) ) {
			return false;
		}
		return 'reseller' === sanitize_key( (string) ( $row->role ?? '' ) );
	}

	/**
	 * Default reseller permissions.
	 *
	 * @return array<string,bool>
	 */
	public static function default_reseller_permissions_template() {
		$out = array();
		foreach ( self::RESELLER_PERMISSION_KEYS as $k ) {
			$out[ $k ] = true;
		}
		return $out;
	}

	/**
	 * Default reseller permissions (from settings or all-true template).
	 *
	 * @return array<string,bool>
	 */
	public static function default_reseller_permissions() {
		$stored = SimpleVPBot_Settings::get( 'default_reseller_permissions', array() );
		$out    = self::default_reseller_permissions_template();
		if ( is_array( $stored ) ) {
			foreach ( self::RESELLER_PERMISSION_KEYS as $k ) {
				if ( array_key_exists( $k, $stored ) ) {
					$out[ $k ] = (bool) $stored[ $k ];
				}
			}
		}
		return $out;
	}

	/**
	 * Load reseller permissions from option storage.
	 *
	 * @param int $reseller_id svp_users.id.
	 * @return array<string,bool>
	 */
	public static function reseller_permissions( $reseller_id ) {
		$rid = (int) $reseller_id;
		if ( $rid < 1 ) {
			return self::default_reseller_permissions();
		}
		$raw = get_option( 'simplevpbot_reseller_perms_' . $rid, null );
		if ( ! is_array( $raw ) ) {
			return self::default_reseller_permissions();
		}
		$cur = self::default_reseller_permissions();
		foreach ( self::RESELLER_PERMISSION_KEYS as $k ) {
			if ( array_key_exists( $k, $raw ) ) {
				$cur[ $k ] = (bool) $raw[ $k ];
			}
		}
		return $cur;
	}

	/**
	 * Save reseller permissions.
	 *
	 * @param int                  $reseller_id svp_users.id.
	 * @param array<string,mixed>  $permissions permission map.
	 * @return bool
	 */
	public static function set_reseller_permissions( $reseller_id, array $permissions ) {
		$rid = (int) $reseller_id;
		if ( $rid < 1 ) {
			return false;
		}
		$out = self::default_reseller_permissions();
		foreach ( self::RESELLER_PERMISSION_KEYS as $k ) {
			if ( array_key_exists( $k, $permissions ) ) {
				$out[ $k ] = (bool) $permissions[ $k ];
			}
		}
		return (bool) update_option( 'simplevpbot_reseller_perms_' . $rid, $out, false );
	}

	/**
	 * Direct children of one owner (invited_by = owner id).
	 *
	 * @param int $owner_id Owner svp_users.id.
	 * @return array<int, object>
	 */
	public static function list_direct_children( $owner_id ) {
		global $wpdb;
		$oid = (int) $owner_id;
		if ( $oid < 1 ) {
			return array();
		}
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE invited_by = %d ORDER BY id DESC',
				$oid
			)
		); // phpcs:ignore
	}

	/**
	 * Transient cache key for reseller scope ids.
	 *
	 * @param int $reseller_id Reseller id.
	 * @return string
	 */
	private static function reseller_scope_cache_key( $reseller_id ) {
		return 'svp_rscope_' . (int) $reseller_id;
	}

	/**
	 * Clear cached scope ids for a reseller (after invited_by changes).
	 *
	 * @param int $reseller_id Reseller id.
	 */
	public static function invalidate_reseller_scope_cache( $reseller_id ) {
		$rid = (int) $reseller_id;
		if ( $rid < 1 ) {
			return;
		}
		delete_transient( self::reseller_scope_cache_key( $rid ) );
	}

	/**
	 * IDs a reseller can manage: self plus every descendant in the invited_by tree (unbounded depth).
	 *
	 * @param int $reseller_id Reseller svp_users.id.
	 * @return array<int, int>
	 */
	public static function reseller_scope_user_ids( $reseller_id ) {
		$rid = (int) $reseller_id;
		if ( $rid < 1 ) {
			return array();
		}
		if ( class_exists( 'SimpleVPBot_Reseller_Closure' ) ) {
			$cached = get_transient( self::reseller_scope_cache_key( $rid ) );
			if ( is_array( $cached ) ) {
				return array_map( 'intval', $cached );
			}
			$ids = SimpleVPBot_Reseller_Closure::descendant_ids_for_ancestor( $rid );
			set_transient( self::reseller_scope_cache_key( $rid ), $ids, 10 * MINUTE_IN_SECONDS );
			return $ids;
		}
		return self::reseller_scope_user_ids_legacy_bfs( $rid );
	}

	/**
	 * Legacy BFS fallback when closure table is unavailable.
	 *
	 * @param int $reseller_id Reseller id.
	 * @return array<int, int>
	 */
	private static function reseller_scope_user_ids_legacy_bfs( $reseller_id ) {
		global $wpdb;
		$rid = (int) $reseller_id;
		$t       = self::table();
		$id_set  = array( $rid => true );
		$frontier = array( $rid );
		while ( ! empty( $frontier ) ) {
			$frontier = array_values( array_unique( array_map( 'intval', $frontier ) ) );
			$frontier = array_filter(
				$frontier,
				static function ( $x ) {
					return $x > 0;
				}
			);
			if ( empty( $frontier ) ) {
				break;
			}
			$ph   = implode( ',', array_fill( 0, count( $frontier ), '%d' ) );
			$sql  = "SELECT id FROM {$t} WHERE invited_by IN ({$ph})";
			$cols = $wpdb->get_col( $wpdb->prepare( $sql, $frontier ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$next = array();
			foreach ( (array) $cols as $cid ) {
				$uid = (int) $cid;
				if ( $uid < 1 || isset( $id_set[ $uid ] ) ) {
					continue;
				}
				$id_set[ $uid ] = true;
				$next[]        = $uid;
			}
			$frontier = $next;
		}
		$ids = array_map( 'intval', array_keys( $id_set ) );
		sort( $ids );
		return $ids;
	}

	/**
	 * SQL fragment for `u.id IN (...)` using reseller scope ids.
	 *
	 * @param int    $reseller_id Reseller svp_users.id.
	 * @param string $alias       SQL alias.
	 * @return array{sql:string,values:array<int,int|float|string>}|null
	 */
	public static function reseller_scope_clause( $reseller_id, $alias = 'u' ) {
		if ( class_exists( 'SimpleVPBot_Reseller_Closure' ) ) {
			$clause = SimpleVPBot_Reseller_Closure::reseller_scope_clause( $reseller_id, $alias );
			if ( is_array( $clause ) ) {
				return $clause;
			}
			return null;
		}
		$ids = self::reseller_scope_user_ids( $reseller_id );
		if ( empty( $ids ) ) {
			return null;
		}
		if ( count( $ids ) > SimpleVPBot_Reseller_Closure::LARGE_IN_THRESHOLD ) {
			return SimpleVPBot_Reseller_Closure::reseller_scope_clause( $reseller_id, $alias );
		}
		$a = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $alias );
		$a = '' !== $a ? $a : 'u';
		$ph = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return array(
			'sql'    => " AND {$a}.id IN ({$ph}) ",
			'values' => $ids,
		);
	}

	/**
	 * SQL fragment for users a reseller may moderate (downline + signup_reseller attribution).
	 *
	 * @param int    $reseller_id Reseller svp_users.id.
	 * @param string $alias       SQL alias.
	 * @return array{sql:string,values:array<int,int>}|null
	 */
	public static function reseller_moderation_scope_clause( $reseller_id, $alias = 'u' ) {
		$rid = (int) $reseller_id;
		if ( $rid < 1 ) {
			return null;
		}
		$a = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $alias );
		$a = '' !== $a ? $a : 'u';
		$base = self::reseller_scope_clause( $rid, $alias );
		if ( null === $base ) {
			return array(
				'sql'    => " AND ( {$a}.id = %d OR {$a}.signup_reseller_svp_id = %d ) ",
				'values' => array( $rid, $rid ),
			);
		}
		$inner = trim( preg_replace( '/^\s*AND\s+/i', '', $base['sql'] ) );
		return array(
			'sql'    => " AND ( {$inner} OR {$a}.signup_reseller_svp_id = %d ) ",
			'values' => array_merge( $base['values'], array( $rid ) ),
		);
	}

	/**
	 * Batch-load reseller permission maps for admin tables.
	 *
	 * @param array<int, int> $reseller_ids Reseller svp_users.id list.
	 * @return array<string, array<string, bool>>
	 */
	public static function reseller_permissions_map_for_ids( array $reseller_ids ) {
		$ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $reseller_ids ),
					static function ( $v ) {
						return (int) $v > 0;
					}
				)
			)
		);
		$out = array();
		if ( empty( $ids ) ) {
			return $out;
		}
		global $wpdb;
		$option_by_rid = array();
		foreach ( $ids as $rid ) {
			$option_by_rid[ 'simplevpbot_reseller_perms_' . $rid ] = $rid;
		}
		$names        = array_keys( $option_by_rid );
		$placeholders = implode( ', ', array_fill( 0, count( $names ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
		$sql  = "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ({$placeholders})";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $names ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$raw_by_rid = array();
		foreach ( (array) $rows as $row ) {
			$name = isset( $row['option_name'] ) ? (string) $row['option_name'] : '';
			if ( '' === $name || ! isset( $option_by_rid[ $name ] ) ) {
				continue;
			}
			$rid = (int) $option_by_rid[ $name ];
			$val = isset( $row['option_value'] ) ? maybe_unserialize( $row['option_value'] ) : null;
			if ( is_array( $val ) ) {
				$raw_by_rid[ $rid ] = $val;
			}
		}
		foreach ( $ids as $rid ) {
			$raw = isset( $raw_by_rid[ $rid ] ) ? $raw_by_rid[ $rid ] : null;
			if ( ! is_array( $raw ) ) {
				$out[ (string) $rid ] = self::default_reseller_permissions();
				continue;
			}
			$cur = self::default_reseller_permissions();
			foreach ( self::RESELLER_PERMISSION_KEYS as $k ) {
				if ( array_key_exists( $k, $raw ) ) {
					$cur[ $k ] = (bool) $raw[ $k ];
				}
			}
			$out[ (string) $rid ] = $cur;
		}
		return $out;
	}

	/**
	 * Export all per-reseller permission options for backup zip.
	 *
	 * @return array<string, array<string, bool>> Map of reseller id string → permission map.
	 */
	public static function export_all_reseller_permissions() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'simplevpbot_reseller_perms_%'",
			ARRAY_A
		);
		$out = array();
		foreach ( (array) $rows as $row ) {
			$name = isset( $row['option_name'] ) ? (string) $row['option_name'] : '';
			if ( ! preg_match( '/^simplevpbot_reseller_perms_(\d+)$/', $name, $m ) ) {
				continue;
			}
			$rid = (int) $m[1];
			if ( $rid < 1 ) {
				continue;
			}
			$raw = isset( $row['option_value'] ) ? maybe_unserialize( $row['option_value'] ) : null;
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$cur = self::default_reseller_permissions();
			foreach ( self::RESELLER_PERMISSION_KEYS as $k ) {
				if ( array_key_exists( $k, $raw ) ) {
					$cur[ $k ] = (bool) $raw[ $k ];
				}
			}
			$out[ (string) $rid ] = $cur;
		}
		return $out;
	}

	/**
	 * Restore per-reseller permissions from backup export map.
	 *
	 * @param array<string, mixed> $map reseller id string → permission map.
	 * @return int Options written.
	 */
	public static function restore_reseller_permissions_from_export( array $map ) {
		$written = 0;
		foreach ( $map as $rid_key => $perms ) {
			if ( ! is_array( $perms ) ) {
				continue;
			}
			$rid = (int) $rid_key;
			if ( $rid < 1 ) {
				continue;
			}
			if ( self::set_reseller_permissions( $rid, $perms ) ) {
				++$written;
			}
		}
		return $written;
	}

	/**
	 * Check if target user id is inside reseller scope.
	 *
	 * @param int $reseller_id Reseller svp_users.id.
	 * @param int $target_user_id Target svp_users.id.
	 * @return bool
	 */
	public static function reseller_can_access_user( $reseller_id, $target_user_id ) {
		$tid = (int) $target_user_id;
		if ( $tid < 1 ) {
			return false;
		}
		if ( class_exists( 'SimpleVPBot_Reseller_Closure' ) ) {
			return SimpleVPBot_Reseller_Closure::is_descendant_of( (int) $reseller_id, $tid );
		}
		$ids = self::reseller_scope_user_ids( $reseller_id );
		return in_array( $tid, $ids, true );
	}
}
