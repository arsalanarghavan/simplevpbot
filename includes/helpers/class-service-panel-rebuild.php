<?php
/**
 * Rebuild Xray panel clients from svp_services rows (DB → panel).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Service_Panel_Rebuild
 */
class SimpleVPBot_Service_Panel_Rebuild {

	const BATCH_SIZE = 40;

	/**
	 * Rebuild panel clients for active Xray services (batched).
	 *
	 * @param array<string, mixed> $opts panel_id (0=all), dry_run, offset, limit, finalize_sync.
	 * @return array<string, mixed>
	 */
	public static function rebuild_all( array $opts = array() ) {
		$panel_id = max( 0, (int) ( $opts['panel_id'] ?? 0 ) );
		$dry_run  = ! empty( $opts['dry_run'] );
		$offset   = max( 0, (int) ( $opts['offset'] ?? 0 ) );
		$limit    = max( 1, min( 50, (int) ( $opts['limit'] ?? self::BATCH_SIZE ) ) );

		$totals = array(
			'created' => 0,
			'patched' => 0,
			'skipped' => 0,
			'failed'  => 0,
		);
		$errors = array();
		$touched_panels = array();
		$inbound_map = null;
		if ( class_exists( 'SimpleVPBot_Service_Panel_Inbound_Map' ) ) {
			if ( isset( $opts['inbound_map'] ) && is_array( $opts['inbound_map'] ) ) {
				$inbound_map = SimpleVPBot_Service_Panel_Inbound_Map::normalize_map( $opts['inbound_map'] );
			} elseif ( $panel_id > 0 ) {
				$inbound_map = SimpleVPBot_Service_Panel_Inbound_Map::get_map( $panel_id );
			}
		}

		$rows  = self::fetch_service_batch( $panel_id, $offset, $limit );
		$total = self::count_services( $panel_id );

		foreach ( $rows as $svc ) {
			if ( ! is_object( $svc ) ) {
				continue;
			}
			$one = self::rebuild_one( $svc, $dry_run, $inbound_map );
			$act = (string) ( $one['action'] ?? 'failed' );
			if ( isset( $totals[ $act ] ) ) {
				++$totals[ $act ];
			} else {
				++$totals['failed'];
			}
			if ( 'failed' === $act && count( $errors ) < 20 ) {
				$errors[] = array(
					'service_id' => (int) ( $svc->id ?? 0 ),
					'email'      => (string) ( $svc->email ?? '' ),
					'panel_id'   => (int) ( $svc->panel_id ?? 0 ),
					'reason'     => (string) ( $one['reason'] ?? $one['message'] ?? 'unknown' ),
				);
			}
			if ( ! $dry_run && in_array( $act, array( 'created', 'patched' ), true ) ) {
				$touched_panels[ max( 1, (int) ( $svc->panel_id ?? 1 ) ) ] = true;
			}
		}

		$next_offset = $offset + count( $rows );
		$done        = $next_offset >= $total || count( $rows ) < 1;

		if ( $done && ! $dry_run && ! empty( $touched_panels ) && class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			foreach ( array_keys( $touched_panels ) as $pid ) {
				SimpleVPBot_Service_Admin_Ops::configs_sync_panel_to_db( (int) $pid, true );
			}
		}

		return array(
			'ok'           => true,
			'dry_run'      => $dry_run,
			'totals'       => $totals,
			'errors'       => $errors,
			'done'         => $done,
			'next_offset'  => $done ? $total : $next_offset,
			'total'        => $total,
			'processed'    => count( $rows ),
		);
	}

	/**
	 * @param object $svc   Service row.
	 * @param bool   $dry_run Dry run only.
	 * @return array{action:string, ok?:bool, reason?:string, message?:string}
	 */
	public static function rebuild_one( $svc, $dry_run = false, $inbound_map = null ) {
		if ( ! self::is_rebuildable_xray_service( $svc ) ) {
			return array( 'action' => 'skipped', 'reason' => 'not_xray' );
		}

		$panel_id = max( 1, (int) ( $svc->panel_id ?? 1 ) );
		$email    = trim( (string) ( $svc->email ?? '' ) );
		$db_iid   = (int) ( $svc->inbound_id ?? 0 );
		$map_use  = array();
		if ( class_exists( 'SimpleVPBot_Service_Panel_Inbound_Map' ) ) {
			if ( is_array( $inbound_map ) ) {
				$map_use = $inbound_map;
			} else {
				$map_use = SimpleVPBot_Service_Panel_Inbound_Map::get_map( $panel_id );
			}
		}
		$iid = class_exists( 'SimpleVPBot_Service_Panel_Inbound_Map' )
			? SimpleVPBot_Service_Panel_Inbound_Map::resolve_inbound_id( $panel_id, $db_iid, $map_use )
			: $db_iid;
		if ( $iid < 1 ) {
			return array( 'action' => 'failed', 'reason' => 'inbound_unmapped' );
		}
		$svc_work = class_exists( 'SimpleVPBot_Service_Panel_Inbound_Map' )
			? SimpleVPBot_Service_Panel_Inbound_Map::service_with_resolved_inbound( $svc, $map_use )
			: $svc;

		if ( $dry_run ) {
			$exists = self::panel_client_exists( $panel_id, $iid, $email );
			if ( $exists ) {
				return array( 'action' => 'patched', 'ok' => true, 'reason' => 'dry_run_would_patch' );
			}
			return array( 'action' => 'created', 'ok' => true, 'reason' => 'dry_run_would_create' );
		}

		if ( self::panel_client_exists( $panel_id, $iid, $email ) ) {
			if ( ! class_exists( 'SimpleVPBot_Service_Renew' ) ) {
				return array( 'action' => 'failed', 'reason' => 'no_renew_module' );
			}
			$res = SimpleVPBot_Service_Renew::sync_service_row_to_panel( $svc_work );
			if ( ! empty( $res['ok'] ) ) {
				return array( 'action' => 'patched', 'ok' => true );
			}
			return array(
				'action'  => 'failed',
				'reason'  => 'patch_failed',
				'message' => (string) ( $res['message'] ?? '' ),
			);
		}

		if ( ! class_exists( 'SimpleVPBot_Service_Provisioner' ) ) {
			return array( 'action' => 'failed', 'reason' => 'no_provisioner_module' );
		}
		$add = SimpleVPBot_Service_Provisioner::add_panel_client_from_service_row( $svc_work );
		if ( ! empty( $add['ok'] ) ) {
			$act = (string) ( $add['action'] ?? 'created' );
			if ( 'already_on_panel' === $act ) {
				$res = SimpleVPBot_Service_Renew::sync_service_row_to_panel( $svc_work );
				if ( ! empty( $res['ok'] ) ) {
					return array( 'action' => 'patched', 'ok' => true );
				}
				return array(
					'action'  => 'failed',
					'reason'  => 'patch_after_exists',
					'message' => (string) ( $res['message'] ?? '' ),
				);
			}
			return array( 'action' => 'created', 'ok' => true );
		}
		return array(
			'action' => 'failed',
			'reason' => (string) ( $add['reason'] ?? 'add_failed' ),
			'detail' => (string) ( $add['detail'] ?? '' ),
		);
	}

	/**
	 * @param object|null $svc Service row.
	 * @return bool
	 */
	public static function is_rebuildable_xray_service( $svc ) {
		if ( ! is_object( $svc ) ) {
			return false;
		}
		if ( class_exists( 'SimpleVPBot_Model_Service' ) && SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			return false;
		}
		$stype = strtolower( trim( (string) ( $svc->service_type ?? 'xray' ) ) );
		if ( 'l2tp' === $stype ) {
			return false;
		}
		$email = trim( (string) ( $svc->email ?? '' ) );
		return $email !== '' && (int) ( $svc->inbound_id ?? 0 ) > 0;
	}

	/**
	 * @param int    $panel_id Panel filter (0 = all).
	 * @param int    $offset   Offset.
	 * @param int    $limit    Limit.
	 * @return array<int, object>
	 */
	private static function fetch_service_batch( $panel_id, $offset, $limit ) {
		global $wpdb;
		$t    = SimpleVPBot_Model_Service::table();
		$w    = "deleted_at IS NULL AND inbound_id > 0 AND TRIM(email) <> '' AND (service_type IS NULL OR service_type = '' OR service_type = 'xray')";
		$args = array();
		if ( $panel_id > 0 ) {
			$w     .= ' AND panel_id = %d';
			$args[] = $panel_id;
		}
		$args[] = (int) $limit;
		$args[] = (int) $offset;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT * FROM {$t} WHERE {$w} ORDER BY id ASC LIMIT %d OFFSET %d";
		return $wpdb->get_results( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore
	}

	/**
	 * @param int $panel_id Panel filter (0 = all).
	 * @return int
	 */
	private static function count_services( $panel_id ) {
		global $wpdb;
		$t = SimpleVPBot_Model_Service::table();
		$w = "deleted_at IS NULL AND inbound_id > 0 AND TRIM(email) <> '' AND (service_type IS NULL OR service_type = '' OR service_type = 'xray')";
		if ( $panel_id > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE {$w} AND panel_id = %d", $panel_id ) );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE {$w}" );
	}

	/**
	 * @param int    $panel_id Panel id.
	 * @param int    $inbound_id Inbound id.
	 * @param string $email Client email.
	 * @return bool
	 */
	private static function panel_client_exists( $panel_id, $inbound_id, $email ) {
		if ( ! class_exists( 'SimpleVPBot_Xui_Client' ) ) {
			return false;
		}
		$found = false;
		SimpleVPBot_Xui_Client::run_with_panel(
			(int) $panel_id,
			function () use ( $inbound_id, $email, &$found ) {
				if ( ! SimpleVPBot_Xui_Client::login_with_retries( 4, 280000 ) ) {
					return null;
				}
				$inbound = SimpleVPBot_Xui_Client::inbound_get( (int) $inbound_id );
				$found   = (bool) ( $inbound && SimpleVPBot_Xui_Client::inbound_client_by_email( $inbound, (string) $email ) );
				return null;
			}
		);
		return $found;
	}
}
