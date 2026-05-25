<?php
/**
 * Re-link panel clients to bot users after DB restore (on login / service menu).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Service_Reconcile
 */
class SimpleVPBot_Service_Reconcile {

	const THROTTLE_SEC = 300;

	/**
	 * Attempt to link unlinked panel clients to this user (best-effort, throttled).
	 *
	 * @param int                  $user_id svp_users.id.
	 * @param array<string, mixed> $opts    force?:bool — bypass throttle.
	 * @return array{ok:bool, linked:int, skipped:int, errors:int, throttled?:bool}
	 */
	public static function reconcile_for_user( $user_id, array $opts = array() ) {
		$uid = (int) $user_id;
		$out = array(
			'ok'      => true,
			'linked'  => 0,
			'skipped' => 0,
			'errors'  => 0,
		);
		if ( $uid < 1 || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return $out;
		}
		$user = SimpleVPBot_Model_User::find( $uid );
		if ( ! $user || 'approved' !== (string) ( $user->status ?? '' ) ) {
			return $out;
		}
		$force = ! empty( $opts['force'] );
		$tkey  = 'svp_reconcile_u_' . $uid;
		if ( ! $force && get_transient( $tkey ) ) {
			$out['throttled'] = true;
			return $out;
		}
		if ( ! $force ) {
			set_transient( $tkey, 1, self::THROTTLE_SEC );
		}

		self::maybe_sync_panel_caches();

		$seen = array();
		if ( class_exists( 'SimpleVPBot_Model_Panel_Inbound_Client' ) ) {
			$rows = SimpleVPBot_Model_Panel_Inbound_Client::candidates_for_user_reconcile( $uid );
			foreach ( $rows as $row ) {
				if ( ! is_object( $row ) ) {
					continue;
				}
				$pid = (int) ( $row->panel_id ?? 0 );
				$iid = (int) ( $row->inbound_id ?? 0 );
				$em  = trim( (string) ( $row->email ?? '' ) );
				if ( $pid < 1 || $iid < 1 || '' === $em ) {
					++$out['skipped'];
					continue;
				}
				$dedupe = $pid . ':' . $iid . ':' . $em;
				if ( isset( $seen[ $dedupe ] ) ) {
					continue;
				}
				$seen[ $dedupe ] = true;

				if ( SimpleVPBot_Model_Service::find_by_inbound_email( $iid, $em, $pid > 0 ? $pid : 1 ) ) {
					++$out['skipped'];
					continue;
				}

				$client = self::client_array_from_cache_row( $row );
				$match  = SimpleVPBot_Inbound_Linker::resolve_user_id_from_panel_client( $client );
				if ( $match !== $uid ) {
					++$out['skipped'];
					continue;
				}

				$link = SimpleVPBot_Inbound_Linker::link( $iid, $em, $uid, $pid );
				if ( ! empty( $link['ok'] ) ) {
					++$out['linked'];
				} else {
					++$out['errors'];
				}
			}
		}

		if ( $out['linked'] > 0 || $out['errors'] > 0 ) {
			SimpleVPBot_Logger::info(
				'service_reconcile_for_user',
				array(
					'user_id' => $uid,
					'linked'  => $out['linked'],
					'skipped' => $out['skipped'],
					'errors'  => $out['errors'],
				)
			);
		}

		return $out;
	}

	/**
	 * Sync panel caches when empty or older than six hours.
	 *
	 * @return void
	 */
	private static function maybe_sync_panel_caches() {
		if ( ! class_exists( 'SimpleVPBot_Model_Panel' ) || ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return;
		}
		$panels = SimpleVPBot_Model_Panel::all_active_ordered();
		foreach ( $panels as $pn ) {
			$pid = (int) ( $pn->id ?? 0 );
			if ( $pid < 1 ) {
				continue;
			}
			$needs = false;
			if ( class_exists( 'SimpleVPBot_Model_Panel_Inbound_Client' ) ) {
				if ( SimpleVPBot_Model_Panel_Inbound_Client::count_for_panel( $pid ) < 1 ) {
					$needs = true;
				} else {
					$last = SimpleVPBot_Model_Panel_Inbound_Client::max_synced_at_for_panel( $pid );
					if ( null === $last ) {
						$needs = true;
					} else {
						$ts = strtotime( $last . ' UTC' );
						if ( false === $ts || $ts < time() - 6 * HOUR_IN_SECONDS ) {
							$needs = true;
						}
					}
				}
			}
			if ( $needs ) {
				SimpleVPBot_Service_Admin_Ops::configs_sync_panel_to_db( $pid, false );
			}
		}
	}

	/**
	 * Build panel client array for linker resolution.
	 *
	 * @param object $row Cache row.
	 * @return array<string, mixed>
	 */
	private static function client_array_from_cache_row( $row ) {
		$client = array(
			'email'   => (string) ( $row->email ?? '' ),
			'remark'  => (string) ( $row->remark ?? '' ),
			'comment' => (string) ( $row->comment ?? '' ),
			'tgId'    => (string) ( $row->tg_id ?? '' ),
			'subId'   => (string) ( $row->sub_id ?? '' ),
		);
		if ( ! empty( $row->client_json ) ) {
			$dec = json_decode( (string) $row->client_json, true );
			if ( is_array( $dec ) ) {
				$client = array_merge( $dec, $client );
			}
		}
		return $client;
	}
}
