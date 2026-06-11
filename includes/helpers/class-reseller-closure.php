<?php
/**
 * Closure table for invited_by tree (reseller scope at scale).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Reseller_Closure
 */
class SimpleVPBot_Reseller_Closure {

	const LARGE_IN_THRESHOLD = 2000;

	/**
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_reseller_closure';
	}

	/**
	 * Full rebuild from svp_users.invited_by (migration / repair).
	 */
	public static function rebuild_all() {
		global $wpdb;
		$ct = self::table();
		$ut = SimpleVPBot_Model_User::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE {$ct}" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col( "SELECT id FROM {$ut} ORDER BY id ASC" );
		if ( ! is_array( $ids ) ) {
			return;
		}
		foreach ( $ids as $raw_id ) {
			$uid = (int) $raw_id;
			if ( $uid > 0 ) {
				self::rebuild_for_user( $uid );
			}
		}
	}

	/**
	 * Rebuild closure rows for one user and their descendants.
	 *
	 * @param int $user_id svp_users.id.
	 */
	public static function rebuild_for_user( $user_id ) {
		$uid = (int) $user_id;
		if ( $uid < 1 ) {
			return;
		}
		self::delete_descendant_paths( $uid );
		self::insert_self_and_ancestors( $uid );
		$children = self::direct_children( $uid );
		foreach ( $children as $cid ) {
			self::rebuild_for_user( (int) $cid );
		}
	}

	/**
	 * After invited_by change on a user row.
	 *
	 * @param int $user_id     User id.
	 * @param int $old_parent  Previous invited_by (0 if none).
	 * @param int $new_parent  New invited_by (0 if none).
	 */
	public static function on_invited_by_changed( $user_id, $old_parent, $new_parent ) {
		$uid = (int) $user_id;
		$old = (int) $old_parent;
		$new = (int) $new_parent;
		if ( $uid < 1 || $old === $new ) {
			return;
		}
		if ( $new > 0 && self::would_create_cycle( $uid, $new ) ) {
			return;
		}
		self::rebuild_for_user( $uid );
		if ( $old > 0 ) {
			SimpleVPBot_Model_User::invalidate_reseller_scope_cache( $old );
		}
		if ( $new > 0 ) {
			SimpleVPBot_Model_User::invalidate_reseller_scope_cache( $new );
		}
	}

	/**
	 * Descendant user ids for ancestor (includes self at depth 0).
	 *
	 * @param int $ancestor_id Ancestor svp_users.id.
	 * @return array<int, int>
	 */
	public static function descendant_ids_for_ancestor( $ancestor_id ) {
		global $wpdb;
		$aid = (int) $ancestor_id;
		if ( $aid < 1 ) {
			return array();
		}
		$ct = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$cols = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT descendant_id FROM {$ct} WHERE ancestor_id = %d ORDER BY descendant_id ASC",
				$aid
			)
		);
		$out = array();
		foreach ( (array) $cols as $c ) {
			$id = (int) $c;
			if ( $id > 0 ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/**
	 * Whether descendant is in ancestor's subtree.
	 *
	 * @param int $ancestor_id   Ancestor id.
	 * @param int $descendant_id Descendant id.
	 * @return bool
	 */
	public static function is_descendant_of( $ancestor_id, $descendant_id ) {
		global $wpdb;
		$a = (int) $ancestor_id;
		$d = (int) $descendant_id;
		if ( $a < 1 || $d < 1 ) {
			return false;
		}
		if ( $a === $d ) {
			return true;
		}
		$ct = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$found = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$ct} WHERE ancestor_id = %d AND descendant_id = %d LIMIT 1",
				$a,
				$d
			)
		);
		return 1 === $found;
	}

	/**
	 * SQL scope: alias.id IN (subquery) — avoids huge IN lists.
	 *
	 * @param int    $reseller_id Reseller svp_users.id.
	 * @param string $alias       User table alias.
	 * @return array{sql:string,values:array<int,int|float|string>}|null
	 */
	public static function reseller_scope_clause( $reseller_id, $alias = 'u' ) {
		$rid = (int) $reseller_id;
		if ( $rid < 1 ) {
			return null;
		}
		$a  = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $alias );
		$a  = '' !== $a ? $a : 'u';
		$ct = self::table();
		return array(
			'sql'    => " AND {$a}.id IN (SELECT descendant_id FROM {$ct} WHERE ancestor_id = %d) ",
			'values' => array( $rid ),
		);
	}

	/**
	 * Whether assigning invited_by would create a referral cycle.
	 *
	 * @param int $user_id     User id.
	 * @param int $new_parent  Proposed invited_by.
	 * @return bool
	 */
	public static function invited_by_would_cycle( $user_id, $new_parent ) {
		return self::would_create_cycle( (int) $user_id, (int) $new_parent );
	}

	/**
	 * @param int $user_id User id.
	 * @param int $new_parent Proposed invited_by.
	 * @return bool True if assigning new_parent would create a cycle.
	 */
	private static function would_create_cycle( $user_id, $new_parent ) {
		return self::is_descendant_of( (int) $user_id, (int) $new_parent );
	}

	/**
	 * @param int $user_id User id.
	 * @return array<int, int>
	 */
	private static function direct_children( $user_id ) {
		global $wpdb;
		$ut = SimpleVPBot_Model_User::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$cols = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$ut} WHERE invited_by = %d",
				(int) $user_id
			)
		);
		$out = array();
		foreach ( (array) $cols as $c ) {
			$id = (int) $c;
			if ( $id > 0 ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/**
	 * Remove all closure rows where this user is a non-self descendant.
	 *
	 * @param int $user_id User id.
	 */
	private static function delete_descendant_paths( $user_id ) {
		global $wpdb;
		$ct = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$ct} WHERE descendant_id = %d",
				(int) $user_id
			)
		);
	}

	/**
	 * Insert (user,user,0) and (ancestor,user,d) for each ancestor of parent.
	 *
	 * @param int $user_id User id.
	 */
	private static function insert_self_and_ancestors( $user_id ) {
		global $wpdb;
		$uid = (int) $user_id;
		$u   = SimpleVPBot_Model_User::find( $uid );
		if ( ! $u ) {
			return;
		}
		$ct = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			$ct,
			array(
				'ancestor_id'   => $uid,
				'descendant_id' => $uid,
				'depth'         => 0,
			),
			array( '%d', '%d', '%d' )
		);
		$parent = (int) ( $u->invited_by ?? 0 );
		if ( $parent < 1 ) {
			return;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ancestor_id, depth FROM {$ct} WHERE descendant_id = %d",
				$parent
			),
			ARRAY_A
		);
		foreach ( (array) $rows as $row ) {
			$anc = (int) ( $row['ancestor_id'] ?? 0 );
			$dep = (int) ( $row['depth'] ?? 0 );
			if ( $anc < 1 ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert(
				$ct,
				array(
					'ancestor_id'   => $anc,
					'descendant_id' => $uid,
					'depth'         => $dep + 1,
				),
				array( '%d', '%d', '%d' )
			);
		}
	}
}
