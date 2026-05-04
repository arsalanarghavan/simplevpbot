<?php
/**
 * X-UI panel actions for dashboard admin (regen UUID, refresh inbound, delete client, sync meta).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Service_Dashboard_Panel
 */
class SimpleVPBot_Service_Dashboard_Panel {

	/**
	 * @param object $svc Service row.
	 * @return int
	 */
	private static function panel_id( $svc ) {
		return max( 1, (int) ( is_object( $svc ) ? ( $svc->panel_id ?? 1 ) : 1 ) );
	}

	/**
	 * Regenerate X-UI client UUID (same logic as bot handler `k`).
	 *
	 * @param int $service_id svp_services.id.
	 * @return array{ok:bool, reason?:string}
	 */
	public static function xray_regenerate_key( $service_id ) {
		$svc_id = (int) $service_id;
		$svc    = SimpleVPBot_Model_Service::find( $svc_id );
		if ( ! $svc || SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			return array( 'ok' => false, 'reason' => 'bad_service' );
		}
		$result = SimpleVPBot_Xui_Client::run_with_panel(
			self::panel_id( $svc ),
			function () use ( $svc, $svc_id ) {
				if ( ! SimpleVPBot_Xui_Client::login_with_retries( 5, 300000 ) ) {
					return array( 'ok' => false, 'reason' => 'panel_login' );
				}
				$new = SimpleVPBot_Xui_Client::get_new_uuid();
				if ( ! $new || ! SimpleVPBot_Xui_Client::is_likely_client_uuid( (string) $new ) ) {
					return array( 'ok' => false, 'reason' => 'no_uuid' );
				}
				$inbound = SimpleVPBot_Xui_Client::inbound_get( (int) $svc->inbound_id );
				if ( ! $inbound ) {
					return array( 'ok' => false, 'reason' => 'no_inbound' );
				}
				$old_key = SimpleVPBot_Xui_Client::resolve_client_key_for_update( (string) $svc->xui_client_id, $inbound, (string) $svc->email );
				if ( ! $old_key || ! SimpleVPBot_Xui_Client::is_likely_client_uuid( (string) $old_key ) ) {
					return array( 'ok' => false, 'reason' => 'no_client_key' );
				}
				$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
				$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
				if ( empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
					return array( 'ok' => false, 'reason' => 'empty_clients' );
				}
				$found          = false;
				$updated_client = null;
				foreach ( $dec['clients'] as &$cl ) {
					if ( isset( $cl['email'] ) && (string) $cl['email'] === (string) $svc->email ) {
						$cl['id']         = $new;
						$updated_client   = $cl;
						$found            = true;
						break;
					}
				}
				unset( $cl );
				if ( ! $found || ! is_array( $updated_client ) ) {
					return array( 'ok' => false, 'reason' => 'client_not_found' );
				}
				$path_ids = array( (string) $old_key );
				$em       = (string) $svc->email;
				if ( '' !== $em && $em !== (string) $old_key ) {
					$path_ids[] = $em;
				}
				$res = SimpleVPBot_Xui_Client::update_inbound_client_sequential( (int) $svc->inbound_id, $dec, $updated_client, $path_ids );
				if ( ! SimpleVPBot_Xui_Client::response_is_success( $res ) ) {
					return array( 'ok' => false, 'reason' => 'update_failed' );
				}
				SimpleVPBot_Model_Service::update( $svc_id, array( 'xui_client_id' => $new, 'xui_client_uuid' => $new ) );
				return array( 'ok' => true );
			}
		);
		return is_array( $result ) ? $result : array( 'ok' => false, 'reason' => 'unknown' );
	}

	/**
	 * Warm inbound cache (same as bot `u`).
	 *
	 * @param int $service_id Id.
	 * @return array{ok:bool, reason?:string}
	 */
	public static function xray_refresh_inbound( $service_id ) {
		$svc_id = (int) $service_id;
		$svc    = SimpleVPBot_Model_Service::find( $svc_id );
		if ( ! $svc || SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			return array( 'ok' => false, 'reason' => 'bad_service' );
		}
		SimpleVPBot_Xui_Client::run_with_panel(
			self::panel_id( $svc ),
			function () use ( $svc ) {
				SimpleVPBot_Xui_Client::login_with_retries( 5, 300000 );
				SimpleVPBot_Xui_Client::inbound_get( (int) $svc->inbound_id );
			}
		);
		return array( 'ok' => true );
	}

	/**
	 * Delete client from panel then soft-delete DB row.
	 *
	 * @param int $service_id Id.
	 * @return array{ok:bool, reason?:string}
	 */
	public static function xray_delete_panel_client( $service_id ) {
		$svc_id = (int) $service_id;
		$svc    = SimpleVPBot_Model_Service::find( $svc_id );
		if ( ! $svc || SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			return array( 'ok' => false, 'reason' => 'bad_service' );
		}
		$panel_res = SimpleVPBot_Xui_Client::run_with_panel(
			self::panel_id( $svc ),
			function () use ( $svc ) {
				if ( ! SimpleVPBot_Xui_Client::login_with_retries( 5, 300000 ) ) {
					return array( 'ok' => false, 'reason' => 'panel_login' );
				}
				$inbound = SimpleVPBot_Xui_Client::inbound_get( (int) $svc->inbound_id );
				if ( ! $inbound ) {
					return array( 'ok' => false, 'reason' => 'no_inbound' );
				}
				$old_key = SimpleVPBot_Xui_Client::resolve_client_key_for_update( (string) $svc->xui_client_id, $inbound, (string) $svc->email );
				if ( ! $old_key ) {
					return array( 'ok' => false, 'reason' => 'no_client_key' );
				}
				$res = SimpleVPBot_Xui_Client::del_client( (int) $svc->inbound_id, (string) $old_key );
				if ( ! SimpleVPBot_Xui_Client::response_is_success( $res ) ) {
					$em = (string) $svc->email;
					if ( '' !== $em && $em !== (string) $old_key ) {
						$res = SimpleVPBot_Xui_Client::del_client( (int) $svc->inbound_id, $em );
					}
				}
				if ( ! SimpleVPBot_Xui_Client::response_is_success( $res ) ) {
					return array( 'ok' => false, 'reason' => 'del_failed' );
				}
				return array( 'ok' => true );
			}
		);
		if ( ! is_array( $panel_res ) || empty( $panel_res['ok'] ) ) {
			return is_array( $panel_res ) ? $panel_res : array( 'ok' => false, 'reason' => 'unknown' );
		}
		SimpleVPBot_Model_Service::soft_delete( $svc_id );
		return array( 'ok' => true );
	}

	/**
	 * Read limitIp + enable from inbound JSON, optionally record IPs from panel API.
	 *
	 * @param int  $service_id Service id.
	 * @param bool $record_ips When true, append IPs to ip log table.
	 * @return array{ok:bool, reason?:string, limit_ip?:int, panel_enabled?:int|null, ips?:array<int,string>}
	 */
	public static function xray_sync_meta( $service_id, $record_ips = true ) {
		$svc_id = (int) $service_id;
		$svc    = SimpleVPBot_Model_Service::find( $svc_id );
		if ( ! $svc || SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			return array( 'ok' => false, 'reason' => 'bad_service' );
		}
		$out = SimpleVPBot_Xui_Client::run_with_panel(
			self::panel_id( $svc ),
			function () use ( $svc, $svc_id, $record_ips ) {
				if ( ! SimpleVPBot_Xui_Client::login_with_retries( 5, 300000 ) ) {
					return array( 'ok' => false, 'reason' => 'panel_login' );
				}
				$inbound = SimpleVPBot_Xui_Client::inbound_get( (int) $svc->inbound_id );
				if ( ! $inbound ) {
					return array( 'ok' => false, 'reason' => 'no_inbound' );
				}
				$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
				$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
				$limit    = 0;
				$en       = null;
				if ( is_array( $dec ) && ! empty( $dec['clients'] ) && is_array( $dec['clients'] ) ) {
					foreach ( $dec['clients'] as $cl ) {
						if ( is_array( $cl ) && isset( $cl['email'] ) && (string) $cl['email'] === (string) $svc->email ) {
							$limit = (int) ( $cl['limitIp'] ?? 0 );
							if ( array_key_exists( 'enable', $cl ) ) {
								$en = (int) ( ! empty( $cl['enable'] ) );
							}
							break;
						}
					}
				}
				$ips = array();
				if ( $record_ips && class_exists( 'SimpleVPBot_Model_Service_Ip_Log' ) ) {
					$j   = SimpleVPBot_Xui_Client::client_ips( (string) $svc->email );
					$obj = is_array( $j ) && isset( $j['obj'] ) ? $j['obj'] : null;
					if ( is_string( $obj ) && '' !== $obj && 'No IP Record' !== $obj ) {
						$decoded = json_decode( $obj, true );
						$ips     = is_array( $decoded ) ? $decoded : preg_split( '/[\s,]+/', $obj );
					} elseif ( is_array( $obj ) ) {
						$ips = $obj;
					}
					$ips = array_slice( array_filter( array_map( 'trim', array_map( 'strval', (array) $ips ) ) ), 0, 50 );
					SimpleVPBot_Model_Service_Ip_Log::touch_many( $svc_id, $ips );
				}
				$upd = array(
					'panel_limit_ip'         => $limit > 0 ? $limit : null,
					'panel_client_enabled'   => null !== $en ? $en : null,
				);
				SimpleVPBot_Model_Service::update( $svc_id, $upd );
				return array(
					'ok'             => true,
					'limit_ip'       => $limit,
					'panel_enabled'  => $en,
					'ips'            => $ips,
				);
			}
		);
		return is_array( $out ) ? $out : array( 'ok' => false, 'reason' => 'unknown' );
	}

	/**
	 * Set absolute concurrent user cap (limitIp).
	 *
	 * @param int $service_id Id.
	 * @param int $limit_ip   New cap (>=1).
	 * @return array{ok:bool, reason?:string}
	 */
	public static function xray_set_limit_ip( $service_id, $limit_ip ) {
		$svc_id = (int) $service_id;
		$cap    = max( 1, min( 500, (int) $limit_ip ) );
		$svc    = SimpleVPBot_Model_Service::find( $svc_id );
		if ( ! $svc || SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			return array( 'ok' => false, 'reason' => 'bad_service' );
		}
		$result = SimpleVPBot_Xui_Client::run_with_panel(
			self::panel_id( $svc ),
			function () use ( $svc, $svc_id, $cap ) {
				if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
					return array( 'ok' => false, 'reason' => 'panel_login' );
				}
				$inbound = SimpleVPBot_Xui_Client::inbound_get( (int) $svc->inbound_id );
				if ( ! $inbound ) {
					return array( 'ok' => false, 'reason' => 'no_inbound' );
				}
				$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
				$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
				if ( ! is_array( $dec ) || empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
					return array( 'ok' => false, 'reason' => 'empty_clients' );
				}
				$updated = null;
				foreach ( $dec['clients'] as &$cl ) {
					if ( isset( $cl['email'] ) && (string) $cl['email'] === (string) $svc->email ) {
						$cl['limitIp'] = $cap;
						$cl['enable']  = true;
						$updated       = $cl;
						break;
					}
				}
				unset( $cl );
				if ( ! is_array( $updated ) ) {
					return array( 'ok' => false, 'reason' => 'client_not_found' );
				}
				$old_key = SimpleVPBot_Xui_Client::resolve_client_key_for_update( (string) $svc->xui_client_id, $inbound, (string) $svc->email );
				if ( ! $old_key ) {
					return array( 'ok' => false, 'reason' => 'no_client_key' );
				}
				$path_ids = array( (string) $old_key );
				$em       = (string) $svc->email;
				if ( '' !== $em && $em !== (string) $old_key ) {
					$path_ids[] = $em;
				}
				$res = SimpleVPBot_Xui_Client::update_inbound_client_sequential( (int) $svc->inbound_id, $dec, $updated, $path_ids );
				if ( ! SimpleVPBot_Xui_Client::response_is_success( $res ) ) {
					return array( 'ok' => false, 'reason' => 'update_failed' );
				}
				SimpleVPBot_Model_Service::update(
					$svc_id,
					array(
						'panel_limit_ip'       => $cap,
						'panel_client_enabled' => 1,
					)
				);
				return array( 'ok' => true );
			}
		);
		return is_array( $result ) ? $result : array( 'ok' => false, 'reason' => 'unknown' );
	}
}
