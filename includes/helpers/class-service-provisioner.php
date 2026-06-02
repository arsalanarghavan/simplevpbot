<?php
/**
 * Create X-UI client + local service row after successful payment.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Service_Provisioner
 */
class SimpleVPBot_Service_Provisioner {

	/**
	 * Provision new service for user from plan.
	 *
	 * @param int      $user_id User id.
	 * @param int      $plan_id Plan id.
	 * @param int|null $volume_gb Chosen GB for per-GB plans; null for fixed.
	 * @return int|false Service id or false.
	 */
	public static function create_from_plan( $user_id, $plan_id, $volume_gb = null ) {
		$out = self::create_from_plan_detailed( $user_id, $plan_id, $volume_gb );
		return ! empty( $out['ok'] ) ? (int) $out['service_id'] : false;
	}

	/**
	 * Structured provisioner: returns reason on failure for admin surface.
	 *
	 * @param int      $user_id User id.
	 * @param int      $plan_id Plan id.
	 * @param int|null $volume_gb Chosen GB for per-GB plans; null for fixed.
	 * @param string|null $platform telegram|bale for platform_slug naming; null auto-detect.
	 * @return array{ok:bool, service_id?:int, reason:string, panel?:mixed, detail?:string}
	 */
	public static function create_from_plan_detailed( $user_id, $plan_id, $volume_gb = null, $platform = null ) {
		$plan = SimpleVPBot_Model_Plan::find( $plan_id );
		if ( ! $plan || ! (int) $plan->active ) {
			return array( 'ok' => false, 'reason' => 'plan_missing_or_inactive' );
		}
		if ( SimpleVPBot_Model_Plan::is_per_gb( $plan ) ) {
			if ( null === $volume_gb || (int) $volume_gb < 1 || ! SimpleVPBot_Model_Plan::is_volume_in_range( $plan, (int) $volume_gb ) ) {
				return array( 'ok' => false, 'reason' => 'volume_out_of_range' );
			}
		}
		if ( 'l2tp' === (string) ( $plan->service_type ?? 'xray' ) ) {
			if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) && ! SimpleVPBot_Feature_L2tp::enabled() ) {
				return array( 'ok' => false, 'reason' => 'l2tp_disabled' );
			}
			$sid = SimpleVPBot_L2TP_Provisioner::create_user( (int) $user_id, (int) $plan_id );
			if ( $sid ) {
				return array( 'ok' => true, 'service_id' => (int) $sid, 'reason' => 'ok' );
			}
			return array( 'ok' => false, 'reason' => 'l2tp_create_failed' );
		}
		if ( (int) $plan->inbound_id < 1 ) {
			return array( 'ok' => false, 'reason' => 'inbound_missing' );
		}
		$panel_id = max( 1, (int) ( $plan->panel_id ?? 1 ) );
		return SimpleVPBot_Xui_Client::run_with_panel(
			$panel_id,
			function () use ( $user_id, $plan_id, $plan, $volume_gb, $platform ) {
				return self::create_xray_service_on_bound_panel( $user_id, $plan_id, $plan, $volume_gb, $platform );
			}
		);
	}

	/**
	 * Xray branch: assumes SimpleVPBot_Xui_Client is already bound to the plan's panel.
	 *
	 * @param int      $user_id   User id.
	 * @param int      $plan_id   Plan id.
	 * @param object   $plan      Plan row.
	 * @param int|null $volume_gb Volume for per-GB.
	 * @return array{ok:bool, service_id?:int, reason:string, panel?:mixed, detail?:string}
	 */
	private static function create_xray_service_on_bound_panel( $user_id, $plan_id, $plan, $volume_gb = null, $platform = null ) {
		if ( ! SimpleVPBot_Xui_Client::login_with_retries( 7, 320000 ) ) {
			return array( 'ok' => false, 'reason' => 'login_fail' );
		}
		$inbound = SimpleVPBot_Xui_Client::inbound_get( (int) $plan->inbound_id );
		if ( ! $inbound ) {
			for ( $ig = 0; $ig < 3; $ig++ ) {
				SimpleVPBot_Xui_Client::clear_session();
				if ( ! SimpleVPBot_Xui_Client::login_with_retries( 4, 280000 ) ) {
					break;
				}
				$inbound = SimpleVPBot_Xui_Client::inbound_get( (int) $plan->inbound_id );
				if ( $inbound ) {
					break;
				}
				usleep( 250000 + $ig * 100000 );
			}
		}
		if ( ! $inbound ) {
			return array( 'ok' => false, 'reason' => 'inbound_not_found', 'detail' => 'id=' . (int) $plan->inbound_id );
		}
		$uuid = SimpleVPBot_Xui_Client::get_new_uuid();
		if ( ! $uuid || ! SimpleVPBot_Xui_Client::is_likely_client_uuid( (string) $uuid ) ) {
			return array( 'ok' => false, 'reason' => 'uuid_missing' );
		}
		$wp_user      = SimpleVPBot_Model_User::find( (int) $user_id );
		$canonical    = SimpleVPBot_Service_Naming::provision_canonical_label( $wp_user, $platform, 1 );
		$email        = SimpleVPBot_Service_Naming::provision_panel_email( $wp_user, $canonical, $platform );
		$service_remark = $canonical;
		$panel_label    = $canonical;
		$service_note   = '';
		if ( SimpleVPBot_Service_Naming::uses_platform_slug_for_new() ) {
			$service_note = SimpleVPBot_Service_Naming::build_auto_service_note( $wp_user );
		}

		$total_gb    = SimpleVPBot_Model_Plan::is_per_gb( $plan ) ? (int) $volume_gb : (int) $plan->traffic_gb;
		$total_bytes = $total_gb > 0 ? $total_gb * 1073741824 : 0;
		$total_bytes = SimpleVPBot_Inbound_Linker::cap_traffic_bytes( (int) $total_bytes );
		$panel_quota = SimpleVPBot_Inbound_Linker::panel_client_totalgb_json_value( $total_bytes );
		$expiry_ms   = 0;
		if ( (int) $plan->duration_days > 0 ) {
			$expiry_ms = ( time() + (int) $plan->duration_days * DAY_IN_SECONDS ) * 1000;
		}
		$subid_gen = substr( md5( $email . microtime( true ) ), 0, 16 );
		$def_users   = max( 0, (int) SimpleVPBot_Settings::get( 'default_concurrent_users', 2 ) );
		$overrides   = array(
			'id'         => (string) $uuid,
			'email'      => $email,
			'enable'     => true,
			'flow'       => '',
			'limitIp'    => $def_users,
			'totalGB'    => $panel_quota,
			'expiryTime' => $expiry_ms,
			'subId'      => $subid_gen,
			'remark'     => $panel_label,
		);
		$template = self::inbound_template_client( $inbound );
		$new_client = is_array( $template ) && ! empty( $template ) ? array_merge( $template, $overrides ) : array(
			'id'         => (string) $uuid,
			'email'      => $email,
			'enable'     => true,
			'flow'       => '',
			'limitIp'    => $def_users,
			'totalGB'    => $panel_quota,
			'expiryTime' => $expiry_ms,
			'subId'      => $subid_gen,
			'remark'     => $panel_label,
		);
		// Identity fields after merge must be ours; strip usage fields from template that can zero quota on some panels.
		$new_client['id']     = (string) $uuid;
		$new_client['email']  = $email;
		$new_client['subId']  = (string) ( $new_client['subId'] ?? $subid_gen );
		$new_client['remark'] = $panel_label;
		if ( '' !== $service_note ) {
			$new_client['comment'] = $service_note;
		}
		$new_client['totalGB'] = $panel_quota;
		$new_client['expiryTime'] = (int) $new_client['expiryTime'];
		foreach ( array( 'up', 'down', 'total', 'lastOnline' ) as $_strip ) {
			if ( array_key_exists( $_strip, $new_client ) ) {
				unset( $new_client[ $_strip ] );
			}
		}
		$settings_for_panel = array(
			'clients' => array( $new_client ),
		);
		$payload            = array(
			'id'       => (int) $plan->inbound_id,
			'settings' => wp_json_encode( $settings_for_panel ),
		);
		$add_req = null;
		$add_json = null;
		$ok       = false;
		for ( $ac = 0; $ac < 4; $ac++ ) {
			if ( $ac > 0 ) {
				usleep( 320000 + $ac * 120000 );
				SimpleVPBot_Xui_Client::clear_session();
				SimpleVPBot_Xui_Client::login_with_retries( 4, 280000 );
			}
			$add_req  = SimpleVPBot_Xui_Client::add_client_request( $payload );
			$add_json = $add_req['json'];
			$ok       = SimpleVPBot_Xui_Client::add_client_request_ok( $add_req );
			if ( $ok ) {
				break;
			}
		}
		if ( ! $ok ) {
			$panel_url = (string) SimpleVPBot_Xui_Client::panel_root();
			$msg       = SimpleVPBot_Xui_Client::panel_json_msg( $add_json );
			SimpleVPBot_Logger::error(
				'addClient failed',
				array(
					'panel_url'  => $panel_url,
					'http_code'  => (int) ( $add_req['code'] ?? 0 ),
					'res'        => $add_json,
					'panel_msg'  => $msg,
					'inbound_id' => (int) $plan->inbound_id,
					'email'      => $email,
					'payload'    => array(
						'id'      => (int) $plan->inbound_id,
						'clients' => array(
							array(
								'id'    => (string) $new_client['id'],
								'email' => (string) $new_client['email'],
							),
						),
					),
				)
			);
			return array(
				'ok'     => false,
				'reason' => 'addclient_panel',
				'panel'  => $add_json,
				'detail' => $msg,
			);
		}

		$iid = (int) $plan->inbound_id;
		$quota_patch = self::apply_panel_client_quota_and_label( $iid, $email, (string) $uuid, $total_bytes, $panel_label );
		if ( ! empty( $quota_patch['ok'] ) && ! empty( $quota_patch['uuid'] ) && SimpleVPBot_Xui_Client::is_likely_client_uuid( (string) $quota_patch['uuid'] ) ) {
			$uuid = (string) $quota_patch['uuid'];
		}
		if ( empty( $quota_patch['ok'] ) ) {
			$still = self::wait_for_client_in_inbound( $iid, $email, 6 );
			if ( is_array( $still ) ) {
				SimpleVPBot_Logger::warning(
					'apply_panel_client_quota_after_add failed; client exists — continuing',
					array(
						'email'      => $email,
						'inbound_id' => $iid,
						'detail'     => (string) ( $quota_patch['detail'] ?? '' ),
					)
				);
			} else {
				SimpleVPBot_Xui_Client::del_client( $iid, (string) $uuid, $email );
				$msg = (string) ( $quota_patch['detail'] ?? '' );
				return array(
					'ok'     => false,
					'reason' => (string) ( $quota_patch['reason'] ?? 'panel_quota_patch_failed' ),
					'panel'  => $quota_patch['panel'] ?? null,
					'detail' => $msg,
				);
			}
		}

		$rollback_uuid = (string) $uuid;
		$verified      = self::wait_for_client_in_inbound( $iid, $email, 10 );
		if ( ! is_array( $verified ) ) {
			SimpleVPBot_Logger::error(
				'provision verify failed: client not visible on panel after addClient',
				array(
					'email'      => $email,
					'inbound_id' => $iid,
					'uuid'       => $rollback_uuid,
				)
			);
			SimpleVPBot_Xui_Client::del_client( $iid, $rollback_uuid, $email );
			return array(
				'ok'     => false,
				'reason' => 'panel_verify_failed',
				'detail' => 'client missing on inbound after success response',
			);
		}
		if ( ! empty( $verified['id'] ) && SimpleVPBot_Xui_Client::is_likely_client_uuid( (string) $verified['id'] ) ) {
			$uuid = (string) $verified['id'];
		}
		if ( ! empty( $verified['subId'] ) ) {
			$new_client['subId'] = (string) $verified['subId'];
		}

		$expires_at = (int) $plan->duration_days > 0 ? gmdate( 'Y-m-d H:i:s', time() + (int) $plan->duration_days * DAY_IN_SECONDS ) : null;
		$remark     = $service_remark;
		$insert_row = array(
			'user_id'         => (int) $user_id,
			'panel_id'        => max( 1, (int) ( $plan->panel_id ?? 1 ) ),
			'inbound_id'      => (int) $plan->inbound_id,
			'xui_client_id'   => $uuid,
			'xui_client_uuid' => $uuid,
			'email'           => $email,
			'remark'          => $remark,
			'plan_id'         => (int) $plan_id,
			'expires_at'      => $expires_at,
			'total_traffic'   => $total_bytes,
			'sub_id'          => $new_client['subId'],
			'provision_type'  => 'plan',
		);
		if ( '' !== $service_note ) {
			$insert_row['service_note'] = $service_note;
		}
		$service_id = SimpleVPBot_Model_Service::insert( $insert_row );
		if ( ! $service_id ) {
			SimpleVPBot_Logger::error( 'service insert failed; rolling back panel client', array( 'email' => $email ) );
			SimpleVPBot_Xui_Client::del_client( (int) $plan->inbound_id, $uuid, $email );
			return array( 'ok' => false, 'reason' => 'db_insert' );
		}
		return array( 'ok' => true, 'service_id' => (int) $service_id, 'reason' => 'ok' );
	}

	/**
	 * Short label for 3x-ui client remark: internal user id + username / tg id.
	 *
	 * @param int                   $user_id svp_users.id.
	 * @param object|null           $wp_user User row.
	 * @return string
	 */
	private static function panel_client_label( $user_id, $wp_user, $service_remark = '' ) {
		if ( class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
			$branded = SimpleVPBot_Reseller_Branding::panel_client_name_for_user( (int) $user_id, (string) $service_remark );
			if ( '' !== trim( $branded ) ) {
				return (string) $branded;
			}
		}
		$slug = '';
		if ( is_object( $wp_user ) ) {
			$raw = trim( (string) ( $wp_user->username ?? '' ) );
			if ( $raw !== '' ) {
				$slug = strtolower( preg_replace( '/[^a-z0-9_]/', '', ltrim( $raw, '@' ) ) );
			}
			if ( $slug === '' ) {
				$tg = (int) ( $wp_user->tg_user_id ?? 0 );
				if ( $tg > 0 ) {
					$slug = 'tg' . $tg;
				} else {
					$bl = (int) ( $wp_user->bale_user_id ?? 0 );
					if ( $bl > 0 ) {
						$slug = 'bl' . $bl;
					}
				}
			}
		}
		if ( $slug === '' ) {
			$slug = 'u' . (int) $user_id;
		}
		$label = '#' . (int) $user_id . '_' . $slug;
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) && mb_strlen( $label, 'UTF-8' ) > 50 ) {
			return mb_substr( $label, 0, 50, 'UTF-8' );
		}
		if ( strlen( $label ) > 50 ) {
			return substr( $label, 0, 50 );
		}
		return $label;
	}

	/**
	 * Poll inbound until client email appears (panel eventual consistency after addClient).
	 *
	 * @param int    $inbound_id Inbound id.
	 * @param string $email      Client email.
	 * @param int    $max_attempts Attempts.
	 * @return array<string, mixed>|null Client row.
	 */
	private static function wait_for_client_in_inbound( $inbound_id, $email, $max_attempts = 8 ) {
		$iid  = (int) $inbound_id;
		$want = trim( (string) $email );
		if ( $iid < 1 || '' === $want ) {
			return null;
		}
		$max = max( 1, min( 15, (int) $max_attempts ) );
		for ( $i = 0; $i < $max; $i++ ) {
			if ( $i > 0 ) {
				usleep( 200000 + $i * 100000 );
				if ( 1 === $i % 2 ) {
					SimpleVPBot_Xui_Client::clear_session();
					SimpleVPBot_Xui_Client::login_with_retries( 4, 260000 );
				}
			}
			$inbound = SimpleVPBot_Xui_Client::inbound_get( $iid );
			$cl      = $inbound ? SimpleVPBot_Xui_Client::inbound_client_by_email( $inbound, $want ) : null;
			if ( is_array( $cl ) ) {
				return $cl;
			}
		}
		return null;
	}

	/**
	 * Force totalGB + remark on panel after addClient (some panels ignore fields on add).
	 *
	 * @param int    $inbound_id Inbound id.
	 * @param string $email      Client email tag.
	 * @param string $uuid       Client UUID.
	 * @param int    $total_traffic_bytes Quota in bytes (0 = unlimited), same as DB `total_traffic`.
	 * @param string $panel_label Remark text.
	 * @return array{ok:bool, reason?:string, panel?:mixed, detail?:string}
	 */
	private static function apply_panel_client_quota_and_label( $inbound_id, $email, $uuid, $total_traffic_bytes, $panel_label ) {
		$iid = (int) $inbound_id;
		$cl  = self::wait_for_client_in_inbound( $iid, $email, 5 );
		if ( ! is_array( $cl ) ) {
			return array( 'ok' => false, 'reason' => 'panel_quota_patch_failed', 'detail' => 'client not found after addClient' );
		}
		$inbound = SimpleVPBot_Xui_Client::inbound_get( $iid );
		if ( ! $inbound ) {
			return array( 'ok' => false, 'reason' => 'inbound_not_found' );
		}
		$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
		$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
		if ( ! is_array( $dec ) || empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
			return array( 'ok' => false, 'reason' => 'panel_quota_patch_failed', 'detail' => 'empty clients list' );
		}
		$updated = null;
		foreach ( $dec['clients'] as &$cl_row ) {
			if ( isset( $cl_row['email'] ) && (string) $cl_row['email'] === (string) $email ) {
				$cl_row['totalGB'] = SimpleVPBot_Inbound_Linker::panel_client_totalgb_json_value( (int) $total_traffic_bytes );
				$cl_row['remark']  = (string) $panel_label;
				$cl_row['enable']  = true;
				$updated           = $cl_row;
				break;
			}
		}
		unset( $cl_row );
		if ( ! is_array( $updated ) ) {
			SimpleVPBot_Logger::error( 'apply_panel_client_quota: client not found', array( 'email' => $email ) );
			return array( 'ok' => false, 'reason' => 'panel_quota_patch_failed', 'detail' => 'client not found in inbound settings' );
		}
		$old_key = SimpleVPBot_Xui_Client::resolve_client_key_for_update( (string) $uuid, $inbound, (string) $email );
		if ( ! $old_key ) {
			$old_key = (string) $uuid;
		}
		$path_ids = array( (string) $old_key );
		if ( (string) $email !== (string) $old_key ) {
			$path_ids[] = (string) $email;
		}
		$res = SimpleVPBot_Xui_Client::update_inbound_client_sequential( (int) $inbound_id, $dec, $updated, $path_ids );
		if ( ! SimpleVPBot_Xui_Client::response_is_success( $res ) ) {
			$pm = is_array( $res ) ? trim( (string) ( $res['msg'] ?? '' ) ) : '';
			SimpleVPBot_Logger::error(
				'apply_panel_client_quota_after_add failed',
				array(
					'res'        => $res,
					'email'      => $email,
					'inbound_id' => (int) $inbound_id,
					'panel_msg'  => $pm,
				)
			);
			return array(
				'ok'     => false,
				'reason' => 'panel_quota_patch_failed',
				'panel'  => $res,
				'detail' => $pm,
			);
		}
		$path_uuid = SimpleVPBot_Xui_Client::resolve_client_path_id_for_update( (string) $uuid, $inbound, (string) $email );
		if ( is_string( $path_uuid ) && '' !== $path_uuid ) {
			$uuid = $path_uuid;
		} elseif ( SimpleVPBot_Xui_Client::ensure_client_panel_id( $updated ) ) {
			$uuid = (string) ( $updated['id'] ?? $uuid );
		}
		return array( 'ok' => true, 'uuid' => $uuid );
	}

	/**
	 * One existing client in inbound to mirror field shape (totalGB, flow, etc.).
	 *
	 * @param array<string, mixed> $inbound Inbound.
	 * @return array<string, mixed>
	 */
	private static function inbound_template_client( $inbound ) {
		$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
		$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
		if ( ! is_array( $dec ) || empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
			return array();
		}
		$one = $dec['clients'][0] ?? null;
		return is_array( $one ) ? $one : array();
	}

	/**
	 * AddClient on panel for an existing svp_services row (rebuild / disaster recovery).
	 *
	 * @param object $svc Service row from DB.
	 * @return array{ok:bool, action?:string, reason?:string, detail?:string, panel?:mixed}
	 */
	public static function add_panel_client_from_service_row( $svc ) {
		$panel_id = max( 1, (int) ( $svc->panel_id ?? 1 ) );
		return SimpleVPBot_Xui_Client::run_with_panel(
			$panel_id,
			function () use ( $svc ) {
				return self::add_panel_client_from_service_row_on_bound_panel( $svc );
			}
		);
	}

	/**
	 * @param object $svc Service row.
	 * @return array{ok:bool, action?:string, reason?:string, detail?:string, panel?:mixed}
	 */
	private static function add_panel_client_from_service_row_on_bound_panel( $svc ) {
		if ( class_exists( 'SimpleVPBot_Service_Panel_Inbound_Map' ) ) {
			$svc = SimpleVPBot_Service_Panel_Inbound_Map::service_with_resolved_inbound( $svc );
		}
		$email = trim( (string) ( $svc->email ?? '' ) );
		$iid   = (int) ( $svc->inbound_id ?? 0 );
		if ( '' === $email || $iid < 1 ) {
			return array( 'ok' => false, 'reason' => 'bad_service_row' );
		}
		if ( ! SimpleVPBot_Xui_Client::login_with_retries( 7, 320000 ) ) {
			return array( 'ok' => false, 'reason' => 'login_fail' );
		}
		$inbound = SimpleVPBot_Xui_Client::inbound_get( $iid );
		if ( ! $inbound ) {
			return array( 'ok' => false, 'reason' => 'inbound_not_found', 'detail' => 'id=' . $iid );
		}
		if ( SimpleVPBot_Xui_Client::inbound_client_by_email( $inbound, $email ) ) {
			return array( 'ok' => true, 'action' => 'already_on_panel' );
		}

		$uuid = trim( (string) ( $svc->xui_client_uuid ?? $svc->xui_client_id ?? '' ) );
		if ( ! $uuid || ! SimpleVPBot_Xui_Client::is_likely_client_uuid( $uuid ) ) {
			$uuid = SimpleVPBot_Xui_Client::get_new_uuid();
		}
		if ( ! $uuid || ! SimpleVPBot_Xui_Client::is_likely_client_uuid( (string) $uuid ) ) {
			return array( 'ok' => false, 'reason' => 'uuid_missing' );
		}

		$total_bytes = SimpleVPBot_Inbound_Linker::cap_traffic_bytes( (int) ( $svc->total_traffic ?? 0 ) );
		$panel_quota = SimpleVPBot_Inbound_Linker::panel_client_totalgb_json_value( $total_bytes );
		$expiry_ms   = 0;
		if ( ! empty( $svc->expires_at ) ) {
			$ts = strtotime( (string) $svc->expires_at . ' UTC' );
			if ( $ts > 0 ) {
				$expiry_ms = (int) $ts * 1000;
			}
		}
		$subid = trim( (string) ( $svc->sub_id ?? '' ) );
		if ( '' === $subid ) {
			$subid = substr( md5( $email . microtime( true ) ), 0, 16 );
		}
		$panel_label = class_exists( 'SimpleVPBot_Service_Naming' )
			? SimpleVPBot_Service_Naming::panel_remark_for_service( (int) ( $svc->user_id ?? 0 ), $svc )
			: trim( (string) ( $svc->remark ?? '' ) );
		if ( '' === $panel_label ) {
			$panel_label = trim( (string) ( $svc->remark ?? '' ) );
		}
		$limit_ip    = (int) ( $svc->panel_limit_ip ?? 0 );
		if ( $limit_ip < 1 ) {
			$limit_ip = max( 0, (int) SimpleVPBot_Settings::get( 'default_concurrent_users', 2 ) );
		}
		$enable = ! isset( $svc->panel_client_enabled ) || (int) $svc->panel_client_enabled !== 0;

		$overrides = array(
			'id'         => (string) $uuid,
			'email'      => $email,
			'enable'     => $enable,
			'flow'       => '',
			'limitIp'    => $limit_ip,
			'totalGB'    => $panel_quota,
			'expiryTime' => $expiry_ms,
			'subId'      => $subid,
			'remark'     => $panel_label,
		);
		$template   = self::inbound_template_client( $inbound );
		$new_client = is_array( $template ) && ! empty( $template ) ? array_merge( $template, $overrides ) : $overrides;
		$new_client['id']         = (string) $uuid;
		$new_client['email']      = $email;
		$new_client['subId']      = (string) ( $new_client['subId'] ?? $subid );
		$new_client['remark']     = $panel_label;
		$new_client['totalGB']    = $panel_quota;
		$new_client['expiryTime'] = (int) $new_client['expiryTime'];
		foreach ( array( 'up', 'down', 'total', 'lastOnline' ) as $_strip ) {
			if ( array_key_exists( $_strip, $new_client ) ) {
				unset( $new_client[ $_strip ] );
			}
		}

		$payload = array(
			'id'       => $iid,
			'settings' => wp_json_encode( array( 'clients' => array( $new_client ) ) ),
		);
		$ok = false;
		for ( $ac = 0; $ac < 4; $ac++ ) {
			if ( $ac > 0 ) {
				usleep( 320000 + $ac * 120000 );
				SimpleVPBot_Xui_Client::clear_session();
				SimpleVPBot_Xui_Client::login_with_retries( 4, 280000 );
			}
			$add_req  = SimpleVPBot_Xui_Client::add_client_request( $payload );
			$add_json = $add_req['json'];
			$ok       = SimpleVPBot_Xui_Client::add_client_request_ok( $add_req );
			if ( $ok ) {
				break;
			}
		}
		if ( ! $ok ) {
			$msg = SimpleVPBot_Xui_Client::panel_json_msg( $add_json );
			return array(
				'ok'     => false,
				'reason' => 'addclient_panel',
				'panel'  => $add_json,
				'detail' => $msg,
			);
		}

		$quota_patch = self::apply_panel_client_quota_and_label( $iid, $email, (string) $uuid, $total_bytes, $panel_label );
		if ( ! empty( $quota_patch['ok'] ) && ! empty( $quota_patch['uuid'] ) && SimpleVPBot_Xui_Client::is_likely_client_uuid( (string) $quota_patch['uuid'] ) ) {
			$uuid = (string) $quota_patch['uuid'];
		}
		$verified = self::wait_for_client_in_inbound( $iid, $email, 8 );
		if ( ! is_array( $verified ) ) {
			SimpleVPBot_Xui_Client::del_client( $iid, (string) $uuid, $email );
			return array( 'ok' => false, 'reason' => 'panel_verify_failed' );
		}
		if ( ! empty( $verified['id'] ) && SimpleVPBot_Xui_Client::is_likely_client_uuid( (string) $verified['id'] ) ) {
			$uuid = (string) $verified['id'];
		}
		if ( ! empty( $verified['subId'] ) ) {
			$subid = (string) $verified['subId'];
		}

		$db_up = array();
		if ( $uuid !== trim( (string) ( $svc->xui_client_uuid ?? $svc->xui_client_id ?? '' ) ) ) {
			$db_up['xui_client_id']   = $uuid;
			$db_up['xui_client_uuid'] = $uuid;
		}
		if ( $subid !== trim( (string) ( $svc->sub_id ?? '' ) ) ) {
			$db_up['sub_id'] = $subid;
		}
		if ( ! empty( $db_up ) ) {
			SimpleVPBot_Model_Service::update( (int) $svc->id, $db_up );
		}

		return array( 'ok' => true, 'action' => 'created' );
	}
}
