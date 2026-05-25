<?php
/**
 * Map DB inbound ids (svp_services/plans) to live 3x-ui inbound ids after panel restore.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Service_Panel_Inbound_Map
 */
class SimpleVPBot_Service_Panel_Inbound_Map {

	const OPTION_PREFIX = 'simplevpbot_inbound_map_p';

	/**
	 * Option key for stored map on a panel.
	 *
	 * @param int $panel_id Panel id.
	 * @return string
	 */
	public static function option_key( $panel_id ) {
		return self::OPTION_PREFIX . max( 1, (int) $panel_id );
	}

	/**
	 * Normalize map keys/values to int old => int new.
	 *
	 * @param array<string|int, mixed> $map Raw map.
	 * @return array<int, int>
	 */
	public static function normalize_map( array $map ) {
		$out = array();
		foreach ( $map as $old => $new ) {
			$o = (int) $old;
			$n = (int) $new;
			if ( $o > 0 && $n > 0 ) {
				$out[ $o ] = $n;
			}
		}
		return $out;
	}

	/**
	 * Stored map for panel (db_inbound_id => live_panel_inbound_id).
	 *
	 * @param int $panel_id Panel id.
	 * @return array<int, int>
	 */
	public static function get_map( $panel_id ) {
		$pid = max( 1, (int) $panel_id );
		$raw = get_option( self::option_key( $pid ), array() );
		return is_array( $raw ) ? self::normalize_map( $raw ) : array();
	}

	/**
	 * Map for rebuild: request body overrides stored option.
	 *
	 * @param int                  $panel_id Panel id (0 = per-service panel_id).
	 * @param array<string, mixed> $opts     inbound_map key optional.
	 * @return array<int, int>
	 */
	public static function get_map_for_rebuild( $panel_id, array $opts = array() ) {
		if ( isset( $opts['inbound_map'] ) && is_array( $opts['inbound_map'] ) ) {
			return self::normalize_map( $opts['inbound_map'] );
		}
		if ( (int) $panel_id > 0 ) {
			return self::get_map( (int) $panel_id );
		}
		return array();
	}

	/**
	 * Persist map.
	 *
	 * @param int                  $panel_id Panel id.
	 * @param array<string|int, mixed> $map  old => new.
	 * @return bool
	 */
	public static function save_map( $panel_id, array $map ) {
		$pid = max( 1, (int) $panel_id );
		$norm = self::normalize_map( $map );
		return update_option( self::option_key( $pid ), $norm, false );
	}

	/**
	 * Resolve DB inbound id to live panel inbound id.
	 *
	 * @param int              $panel_id      Panel id.
	 * @param int              $db_inbound_id Inbound id stored in DB.
	 * @param array<int, int>|null $map       Optional map; null = load stored.
	 * @return int Live inbound id (0 if invalid).
	 */
	public static function resolve_inbound_id( $panel_id, $db_inbound_id, $map = null ) {
		$db = (int) $db_inbound_id;
		if ( $db < 1 ) {
			return 0;
		}
		if ( null === $map ) {
			$map = self::get_map( $panel_id );
		}
		if ( isset( $map[ $db ] ) && (int) $map[ $db ] > 0 ) {
			return (int) $map[ $db ];
		}
		return $db;
	}

	/**
	 * Service row with resolved inbound_id for panel API calls.
	 *
	 * @param object               $svc  Service row.
	 * @param array<int, int>|null $map  Inbound map.
	 * @return object
	 */
	public static function service_with_resolved_inbound( $svc, $map = null ) {
		if ( ! is_object( $svc ) ) {
			return $svc;
		}
		$panel_id = max( 1, (int) ( $svc->panel_id ?? 1 ) );
		$db_iid   = (int) ( $svc->inbound_id ?? 0 );
		$live     = self::resolve_inbound_id( $panel_id, $db_iid, $map );
		if ( $live === $db_iid ) {
			return $svc;
		}
		$copy = clone $svc;
		$copy->inbound_id = $live;
		return $copy;
	}

	/**
	 * Compare DB vs live panel inbounds + suggestions.
	 *
	 * @param int $panel_id Panel id.
	 * @return array<string, mixed>
	 */
	public static function compare_context( $panel_id ) {
		$pid = max( 1, (int) $panel_id );
		$db  = self::db_inbounds_for_panel( $pid );
		$live_res = class_exists( 'SimpleVPBot_Service_Admin_Ops' )
			? SimpleVPBot_Service_Admin_Ops::inbounds_list( $pid )
			: array( 'ok' => false, 'message' => 'no_module' );
		$live = array();
		if ( ! empty( $live_res['ok'] ) && is_array( $live_res['data']['inbounds'] ?? null ) ) {
			$live = $live_res['data']['inbounds'];
		}
		$stored   = self::get_map( $pid );
		$suggest  = self::suggest_map( $db, $live );
		$missing  = array();
		$live_ids = array();
		foreach ( $live as $row ) {
			if ( is_array( $row ) && (int) ( $row['id'] ?? 0 ) > 0 ) {
				$live_ids[ (int) $row['id'] ] = true;
			}
		}
		foreach ( $db as $idx => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$old = (int) ( $row['id'] ?? 0 );
			if ( $old < 1 ) {
				continue;
			}
			$target = isset( $stored[ $old ] ) ? (int) $stored[ $old ] : ( isset( $suggest[ $old ] ) ? (int) $suggest[ $old ] : $old );
			$db[ $idx ]['on_panel_now'] = isset( $live_ids[ $target ] ) || isset( $live_ids[ $old ] );
			if ( ! $db[ $idx ]['on_panel_now'] ) {
				$missing[] = $old;
			}
		}
		return array(
			'ok'              => ! empty( $live_res['ok'] ),
			'message'         => (string) ( $live_res['message'] ?? '' ),
			'panel_id'        => $pid,
			'db_inbounds'     => $db,
			'panel_inbounds'  => $live,
			'map'             => $stored,
			'suggested_map'   => $suggest,
			'missing_on_panel' => $missing,
		);
	}

	/**
	 * Inbounds referenced in DB for this panel (services + plans + cache).
	 *
	 * @param int $panel_id Panel id.
	 * @return array<int, array<string, mixed>>
	 */
	public static function db_inbounds_for_panel( $panel_id ) {
		global $wpdb;
		$pid = max( 1, (int) $panel_id );
		$s_t = class_exists( 'SimpleVPBot_Model_Service' ) ? SimpleVPBot_Model_Service::table() : '';
		$p_t = class_exists( 'SimpleVPBot_Model_Plan' ) ? SimpleVPBot_Model_Plan::table() : '';
		$counts = array();
		if ( '' !== $s_t ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT inbound_id, COUNT(*) AS service_count FROM {$s_t} WHERE panel_id = %d AND deleted_at IS NULL AND inbound_id > 0 GROUP BY inbound_id",
					$pid
				),
				ARRAY_A
			);
			if ( is_array( $rows ) ) {
				foreach ( $rows as $r ) {
					$iid = (int) ( $r['inbound_id'] ?? 0 );
					if ( $iid > 0 ) {
						$counts[ $iid ] = (int) ( $r['service_count'] ?? 0 );
					}
				}
			}
		}
		if ( '' !== $p_t ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$prows = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT inbound_id FROM {$p_t} WHERE panel_id = %d AND inbound_id > 0",
					$pid
				)
			);
			if ( is_array( $prows ) ) {
				foreach ( $prows as $iid ) {
					$iid = (int) $iid;
					if ( $iid > 0 && ! isset( $counts[ $iid ] ) ) {
						$counts[ $iid ] = 0;
					}
				}
			}
		}
		$api_snap = class_exists( 'SimpleVPBot_Model_Panel_Inbound_Api' )
			? SimpleVPBot_Model_Panel_Inbound_Api::inbound_map_for_panel( $pid )
			: array();
		$cache_meta = self::cache_inbound_meta_for_panel( $pid );
		$out        = array();
		ksort( $counts );
		foreach ( $counts as $iid => $svc_n ) {
			$remark   = '';
			$port     = 0;
			$protocol = '';
			if ( isset( $api_snap[ $iid ] ) && is_array( $api_snap[ $iid ] ) ) {
				$remark   = (string) ( $api_snap[ $iid ]['remark'] ?? '' );
				$port     = (int) ( $api_snap[ $iid ]['port'] ?? 0 );
				$protocol = (string) ( $api_snap[ $iid ]['protocol'] ?? '' );
			}
			if ( isset( $cache_meta[ $iid ] ) ) {
				if ( '' === $remark ) {
					$remark = (string) ( $cache_meta[ $iid ]['remark'] ?? '' );
				}
				if ( $port < 1 ) {
					$port = (int) ( $cache_meta[ $iid ]['port'] ?? 0 );
				}
				if ( '' === $protocol ) {
					$protocol = (string) ( $cache_meta[ $iid ]['protocol'] ?? '' );
				}
			}
			$out[] = array(
				'id'             => $iid,
				'remark'         => $remark,
				'port'           => $port,
				'protocol'       => $protocol,
				'service_count'  => (int) $svc_n,
				'on_panel_now'   => null,
			);
		}
		return $out;
	}

	/**
	 * @param int $panel_id Panel id.
	 * @return array<int, array{remark:string,port:int,protocol:string}>
	 */
	private static function cache_inbound_meta_for_panel( $panel_id ) {
		global $wpdb;
		if ( ! class_exists( 'SimpleVPBot_Model_Panel_Inbound_Client' ) ) {
			return array();
		}
		$t = SimpleVPBot_Model_Panel_Inbound_Client::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT inbound_id, MAX(inbound_remark) AS inbound_remark, MAX(protocol) AS protocol, MAX(port) AS port
				FROM {$t} WHERE panel_id = %d GROUP BY inbound_id",
				(int) $panel_id
			),
			ARRAY_A
		);
		$out = array();
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $r ) {
			$iid = (int) ( $r['inbound_id'] ?? 0 );
			if ( $iid > 0 ) {
				$out[ $iid ] = array(
					'remark'   => (string) ( $r['inbound_remark'] ?? '' ),
					'port'     => (int) ( $r['port'] ?? 0 ),
					'protocol' => (string) ( $r['protocol'] ?? '' ),
				);
			}
		}
		return $out;
	}

	/**
	 * Fingerprint for auto-match.
	 *
	 * @param array<string, mixed> $row Inbound row.
	 * @return string
	 */
	private static function fingerprint( array $row ) {
		$proto = strtolower( trim( (string) ( $row['protocol'] ?? '' ) ) );
		$port  = (int) ( $row['port'] ?? 0 );
		$rem   = strtolower( trim( (string) ( $row['remark'] ?? '' ) ) );
		return $proto . '|' . $port . '|' . $rem;
	}

	/**
	 * Suggest old_id => new_id by remark+port+protocol, then remark alone.
	 *
	 * @param array<int, array<string, mixed>> $db_inbounds   DB rows.
	 * @param array<int, array<string, mixed>> $panel_inbounds Live rows.
	 * @return array<int, int>
	 */
	public static function suggest_map( array $db_inbounds, array $panel_inbounds ) {
		$by_fp   = array();
		$by_rem  = array();
		$live_by_id = array();
		foreach ( $panel_inbounds as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = (int) ( $row['id'] ?? 0 );
			if ( $id < 1 ) {
				continue;
			}
			$live_by_id[ $id ] = $row;
			$fp = self::fingerprint( $row );
			if ( $fp !== '||' && $fp !== '|0|' && ! isset( $by_fp[ $fp ] ) ) {
				$by_fp[ $fp ] = $id;
			}
			$rem = strtolower( trim( (string) ( $row['remark'] ?? '' ) ) );
			if ( '' !== $rem ) {
				if ( ! isset( $by_rem[ $rem ] ) ) {
					$by_rem[ $rem ] = array();
				}
				$by_rem[ $rem ][] = $id;
			}
		}
		$suggest = array();
		foreach ( $db_inbounds as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$old = (int) ( $row['id'] ?? 0 );
			if ( $old < 1 ) {
				continue;
			}
			if ( isset( $live_by_id[ $old ] ) ) {
				$suggest[ $old ] = $old;
				continue;
			}
			$fp = self::fingerprint( $row );
			if ( isset( $by_fp[ $fp ] ) ) {
				$suggest[ $old ] = (int) $by_fp[ $fp ];
				continue;
			}
			$rem = strtolower( trim( (string) ( $row['remark'] ?? '' ) ) );
			if ( '' !== $rem && isset( $by_rem[ $rem ] ) && 1 === count( $by_rem[ $rem ] ) ) {
				$suggest[ $old ] = (int) $by_rem[ $rem ][0];
			}
		}
		return $suggest;
	}

	/**
	 * Rewrite inbound_id in services, plans, and client cache tables.
	 *
	 * @param int              $panel_id Panel id.
	 * @param array<int, int>  $map      old => new (only where old !== new).
	 * @return array{services:int, plans:int, cache_clients:int}
	 */
	public static function apply_map_to_database( $panel_id, array $map ) {
		global $wpdb;
		$pid    = max( 1, (int) $panel_id );
		$norm   = self::normalize_map( $map );
		$counts = array(
			'services'       => 0,
			'plans'          => 0,
			'cache_clients'  => 0,
		);
		foreach ( $norm as $old => $new ) {
			if ( $old === $new ) {
				continue;
			}
			if ( class_exists( 'SimpleVPBot_Model_Service' ) ) {
				$t = SimpleVPBot_Model_Service::table();
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$counts['services'] += (int) $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$t} SET inbound_id = %d WHERE panel_id = %d AND inbound_id = %d",
						$new,
						$pid,
						$old
					)
				);
			}
			if ( class_exists( 'SimpleVPBot_Model_Plan' ) ) {
				$t = SimpleVPBot_Model_Plan::table();
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$counts['plans'] += (int) $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$t} SET inbound_id = %d WHERE panel_id = %d AND inbound_id = %d",
						$new,
						$pid,
						$old
					)
				);
			}
			if ( class_exists( 'SimpleVPBot_Model_Panel_Inbound_Client' ) ) {
				$t = SimpleVPBot_Model_Panel_Inbound_Client::table();
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$counts['cache_clients'] += (int) $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$t} SET inbound_id = %d WHERE panel_id = %d AND inbound_id = %d",
						$new,
						$pid,
						$old
					)
				);
			}
			if ( class_exists( 'SimpleVPBot_Model_Panel_Inbound_Api' ) ) {
				$api_t = SimpleVPBot_Model_Panel_Inbound_Api::table();
				$row   = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT inbound_json FROM {$api_t} WHERE panel_id = %d AND inbound_id = %d",
						$pid,
						$old
					)
				);
				if ( $row && ! empty( $row->inbound_json ) ) {
					SimpleVPBot_Model_Panel_Inbound_Api::upsert( $pid, $new, (string) $row->inbound_json );
					SimpleVPBot_Model_Panel_Inbound_Api::delete_inbound( $pid, $old );
				}
			}
		}
		return $counts;
	}
}
