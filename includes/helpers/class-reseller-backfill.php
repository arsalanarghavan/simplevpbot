<?php
/**
 * Reseller data backfill: billing meta on transactions, invited_by from billing chain.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Reseller_Backfill
 */
class SimpleVPBot_Reseller_Backfill {

	const BACKFILL_DONE_OPTION = 'simplevpbot_reseller_backfill_v1_done';

	/**
	 * @param object|null $tx Transaction row.
	 * @return array<string, mixed>
	 */
	public static function parse_tx_meta( $tx ) {
		if ( ! $tx || ! is_object( $tx ) ) {
			return array();
		}
		$meta = json_decode( (string) ( $tx->meta_json ?? '{}' ), true );
		return is_array( $meta ) ? $meta : array();
	}

	/**
	 * Resolve billing reseller for a transaction (stored meta or inference).
	 *
	 * @param object|null $tx Transaction row.
	 * @return int Reseller svp_users.id or 0.
	 */
	public static function infer_billing_reseller_for_tx( $tx ) {
		$meta = self::parse_tx_meta( $tx );
		if ( ! empty( $meta['billing_reseller_svp_id'] ) ) {
			$rid = (int) $meta['billing_reseller_svp_id'];
			return $rid > 0 ? $rid : 0;
		}
		if ( ! empty( $meta['invoice_card_owner_scope_svp_id'] ) ) {
			$rid = (int) $meta['invoice_card_owner_scope_svp_id'];
			if ( $rid > 0 ) {
				return $rid;
			}
		}
		$uid = $tx && is_object( $tx ) ? (int) ( $tx->user_id ?? 0 ) : 0;
		if ( $uid < 1 || ! class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
			return 0;
		}
		return (int) SimpleVPBot_Reseller_Branding::nearest_reseller_id_for_user( $uid );
	}

	/**
	 * Whether a transaction should appear in a reseller's financial scope.
	 *
	 * @param object|null $tx           Transaction row.
	 * @param int         $reseller_id  Reseller svp_users.id.
	 * @return bool
	 */
	public static function tx_belongs_to_reseller( $tx, $reseller_id ) {
		$rid = (int) $reseller_id;
		if ( $rid < 1 ) {
			return false;
		}
		return self::infer_billing_reseller_for_tx( $tx ) === $rid;
	}

	/**
	 * @param array<string, mixed> $meta Existing meta.
	 * @param int                  $reseller_id Reseller id.
	 * @return array<string, mixed>
	 */
	public static function merge_billing_into_meta( array $meta, $reseller_id ) {
		$rid = (int) $reseller_id;
		if ( $rid < 1 ) {
			return $meta;
		}
		if ( empty( $meta['billing_reseller_svp_id'] ) ) {
			$meta['billing_reseller_svp_id'] = $rid;
		}
		if ( empty( $meta['invoice_card_owner_scope_svp_id'] ) ) {
			$meta['invoice_card_owner_scope_svp_id'] = $rid;
		}
		return $meta;
	}

	/**
	 * Persist billing meta on a transaction when inferrable and missing.
	 *
	 * @param object $tx Transaction row.
	 * @return bool True if row was updated.
	 */
	public static function maybe_persist_billing_meta_on_tx( $tx ) {
		if ( ! $tx || ! is_object( $tx ) || ! class_exists( 'SimpleVPBot_Model_Transaction' ) ) {
			return false;
		}
		$meta = self::parse_tx_meta( $tx );
		if ( ! empty( $meta['billing_reseller_svp_id'] ) ) {
			return false;
		}
		$rid = self::infer_billing_reseller_for_tx( $tx );
		if ( $rid < 1 ) {
			return false;
		}
		$meta = self::merge_billing_into_meta( $meta, $rid );
		SimpleVPBot_Model_Transaction::update(
			(int) $tx->id,
			array( 'meta_json' => (string) wp_json_encode( $meta ) )
		);
		return true;
	}

	/**
	 * Batch backfill billing meta on approved purchase/topup transactions.
	 *
	 * @param int $limit  Max rows per batch.
	 * @param int $after_id Resume after transaction id.
	 * @return array{updated:int, scanned:int, last_id:int}
	 */
	public static function backfill_billing_meta_batch( $limit = 500, $after_id = 0 ) {
		global $wpdb;
		$lim = max( 1, min( 2000, (int) $limit ) );
		$aid = max( 0, (int) $after_id );
		$t   = SimpleVPBot_Model_Transaction::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t} WHERE id > %d AND status = %s AND type IN ('purchase','topup') ORDER BY id ASC LIMIT %d",
				$aid,
				'approved',
				$lim
			)
		);
		$updated = 0;
		$last    = $aid;
		foreach ( (array) $rows as $row ) {
			if ( ! $row || ! is_object( $row ) ) {
				continue;
			}
			$last = (int) $row->id;
			if ( self::maybe_persist_billing_meta_on_tx( $row ) ) {
				++$updated;
			}
		}
		return array(
			'updated' => $updated,
			'scanned' => is_array( $rows ) ? count( $rows ) : 0,
			'last_id' => $last,
		);
	}

	/**
	 * Set invited_by from billing reseller when user has no/wrong inviter.
	 *
	 * @param int $limit  Max users per batch.
	 * @param int $after_id Resume after user id.
	 * @return array{updated:int, scanned:int, last_id:int}
	 */
	public static function backfill_invited_by_batch( $limit = 500, $after_id = 0 ) {
		global $wpdb;
		if ( ! class_exists( 'SimpleVPBot_Model_User' ) || ! class_exists( 'SimpleVPBot_Model_Transaction' ) ) {
			return array( 'updated' => 0, 'scanned' => 0, 'last_id' => 0 );
		}
		$lim = max( 1, min( 2000, (int) $limit ) );
		$aid = max( 0, (int) $after_id );
		$u   = SimpleVPBot_Model_User::table();
		$tx  = SimpleVPBot_Model_Transaction::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$users = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT u.* FROM {$u} u
				WHERE u.id > %d AND u.role = %s
				ORDER BY u.id ASC
				LIMIT %d",
				$aid,
				'user',
				$lim
			)
		);
		$updated = 0;
		$last    = $aid;
		foreach ( (array) $users as $user ) {
			if ( ! $user || ! is_object( $user ) ) {
				continue;
			}
			$last = (int) $user->id;
			$uid  = (int) $user->id;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$txrow = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$tx} WHERE user_id = %d AND status = %s ORDER BY id DESC LIMIT 1",
					$uid,
					'approved'
				)
			);
			if ( ! $txrow ) {
				continue;
			}
			$rid = self::infer_billing_reseller_for_tx( $txrow );
			if ( $rid < 1 || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
				continue;
			}
			$reseller = SimpleVPBot_Model_User::find( $rid );
			if ( ! $reseller || ! SimpleVPBot_Model_User::is_reseller_row( $reseller ) ) {
				continue;
			}
			$cur = (int) ( $user->invited_by ?? 0 );
			if ( $cur === $rid ) {
				continue;
			}
			if ( $cur > 0 && $cur !== $rid ) {
				continue;
			}
			SimpleVPBot_Model_User::update(
				$uid,
				array( 'invited_by' => $rid )
			);
			if ( class_exists( 'SimpleVPBot_Model_User' ) ) {
				SimpleVPBot_Model_User::invalidate_reseller_scope_cache( $rid );
				if ( $cur > 0 ) {
					SimpleVPBot_Model_User::invalidate_reseller_scope_cache( $cur );
				}
			}
			++$updated;
		}
		return array(
			'updated' => $updated,
			'scanned' => is_array( $users ) ? count( $users ) : 0,
			'last_id' => $last,
		);
	}

	/**
	 * One-time migration after DB upgrade (idempotent).
	 */
	public static function run_one_time_migrations() {
		if ( get_option( self::BACKFILL_DONE_OPTION, false ) ) {
			return;
		}
		$after_tx = 0;
		do {
			$r       = self::backfill_billing_meta_batch( 500, $after_tx );
			$after_tx = (int) $r['last_id'];
		} while ( (int) $r['scanned'] >= 500 );

		$after_u = 0;
		do {
			$r      = self::backfill_invited_by_batch( 500, $after_u );
			$after_u = (int) $r['last_id'];
		} while ( (int) $r['scanned'] >= 500 );

		update_option( self::BACKFILL_DONE_OPTION, 1, false );
		if ( class_exists( 'SimpleVPBot_Logger' ) ) {
			SimpleVPBot_Logger::info( 'reseller backfill v1 completed' );
		}
	}

	/**
	 * Preview or apply manual bind of users to a reseller (site admin).
	 *
	 * @param int                  $reseller_id Reseller svp_users.id.
	 * @param array<int, int>      $user_ids    Target user ids.
	 * @param string               $mode        preview|set.
	 * @return array{ok:bool, message?:string, users?:array<int, array<string, mixed>>}
	 */
	public static function bind_users_to_reseller( $reseller_id, array $user_ids, $mode = 'preview' ) {
		if ( ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return array( 'ok' => false, 'message' => 'no_model' );
		}
		$mode = sanitize_key( (string) $mode );
		$rid  = (int) $reseller_id;

		if ( 'clear' === $mode ) {
			$out = array();
			foreach ( array_unique( array_map( 'intval', $user_ids ) ) as $uid ) {
				if ( $uid < 1 ) {
					continue;
				}
				$u = SimpleVPBot_Model_User::find( $uid );
				if ( ! $u || SimpleVPBot_Model_User::is_reseller_row( $u ) ) {
					continue;
				}
				$prev = (int) ( $u->invited_by ?? 0 );
				$out[] = array(
					'user_id'             => $uid,
					'label'               => SimpleVPBot_Model_User::label( $u ),
					'previous_invited_by' => $prev,
					'new_invited_by'      => 0,
				);
				SimpleVPBot_Model_User::update( $uid, array( 'invited_by' => null ) );
				if ( $prev > 0 ) {
					SimpleVPBot_Model_User::invalidate_reseller_scope_cache( $prev );
				}
			}
			return array(
				'ok'    => true,
				'users' => $out,
			);
		}

		if ( $rid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_reseller' );
		}
		$reseller = SimpleVPBot_Model_User::find( $rid );
		if ( ! $reseller || ! SimpleVPBot_Model_User::is_reseller_row( $reseller ) ) {
			return array( 'ok' => false, 'message' => 'not_reseller' );
		}
		$out = array();
		foreach ( array_unique( array_map( 'intval', $user_ids ) ) as $uid ) {
			if ( $uid < 1 || $uid === $rid ) {
				continue;
			}
			$u = SimpleVPBot_Model_User::find( $uid );
			if ( ! $u || SimpleVPBot_Model_User::is_reseller_row( $u ) ) {
				continue;
			}
			$prev = (int) ( $u->invited_by ?? 0 );
			$out[] = array(
				'user_id'             => $uid,
				'label'               => SimpleVPBot_Model_User::label( $u ),
				'previous_invited_by' => $prev,
				'new_invited_by'      => $rid,
			);
			if ( 'set' === $mode ) {
				SimpleVPBot_Model_User::update( $uid, array( 'invited_by' => $rid ) );
				SimpleVPBot_Model_User::invalidate_reseller_scope_cache( $rid );
				if ( $prev > 0 ) {
					SimpleVPBot_Model_User::invalidate_reseller_scope_cache( $prev );
				}
			}
		}
		return array(
			'ok'    => true,
			'users' => $out,
		);
	}
}
