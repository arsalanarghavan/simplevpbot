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
	 * @param int      $user_id   svp user id.
	 * @param int      $plan_id   Plan id.
	 * @param int|null $volume_gb Chosen volume for per-GB plans.
	 * @return array{ok:bool, service_id?:int, reason:string, panel?:mixed, detail?:string}
	 */
	public static function create_from_plan_detailed( $user_id, $plan_id, $volume_gb = null ) {
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
			function () use ( $user_id, $plan_id, $plan, $volume_gb ) {
				return self::create_xray_service_on_bound_panel( $user_id, $plan_id, $plan, $volume_gb );
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
	private static function create_xray_service_on_bound_panel( $user_id, $plan_id, $plan, $volume_gb = null ) {
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
		$email = 'u' . (int) $user_id . '_' . wp_generate_password( 6, false, false ) . '@svp.local';

		$total_gb    = SimpleVPBot_Model_Plan::is_per_gb( $plan ) ? (int) $volume_gb : (int) $plan->traffic_gb;
		$total_bytes = $total_gb > 0 ? $total_gb * 1073741824 : 0;
		$total_bytes = SimpleVPBot_Inbound_Linker::cap_traffic_bytes( (int) $total_bytes );
		$panel_quota = SimpleVPBot_Inbound_Linker::panel_client_totalgb_json_value( $total_bytes );
		$expiry_ms   = 0;
		if ( (int) $plan->duration_days > 0 ) {
			$expiry_ms = ( time() + (int) $plan->duration_days * DAY_IN_SECONDS ) * 1000;
		}
		$subid_gen   = substr( md5( $email . microtime( true ) ), 0, 16 );
		$wp_user     = SimpleVPBot_Model_User::find( (int) $user_id );
		$service_remark = (string) $plan->name;
		if ( SimpleVPBot_Model_Plan::is_per_gb( $plan ) && null !== $volume_gb ) {
			$service_remark .= ' · ' . (int) $volume_gb . ' GB';
		}
		$panel_label = self::panel_client_label( (int) $user_id, $wp_user, $service_remark );
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
		$res = null;
		$ok  = false;
		for ( $ac = 0; $ac < 4; $ac++ ) {
			if ( $ac > 0 ) {
				usleep( 320000 + $ac * 120000 );
				SimpleVPBot_Xui_Client::clear_session();
				SimpleVPBot_Xui_Client::login_with_retries( 4, 280000 );
			}
			$res = SimpleVPBot_Xui_Client::add_client( $payload );
			$ok  = SimpleVPBot_Xui_Client::response_is_success( $res );
			if ( $ok ) {
				break;
			}
		}
		if ( ! $ok ) {
			$panel_url = (string) SimpleVPBot_Xui_Client::panel_root();
			SimpleVPBot_Logger::error(
				'addClient failed',
				array(
					'panel_url'  => $panel_url,
					'res'        => $res,
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
			$msg = '';
			if ( is_array( $res ) && ! empty( $res['msg'] ) ) {
				$msg = (string) $res['msg'];
			}
			return array(
				'ok'     => false,
				'reason' => 'addclient_panel',
				'panel'  => $res,
				'detail' => $msg,
			);
		}
		self::apply_panel_client_quota_and_label( (int) $plan->inbound_id, $email, (string) $uuid, $total_bytes, $panel_label );

		$rollback_uuid = (string) $uuid;
		$verified        = null;
		for ( $vr = 0; $vr < 6; $vr++ ) {
			if ( $vr > 0 ) {
				usleep( 180000 + $vr * 90000 );
				if ( 1 === $vr % 2 ) {
					SimpleVPBot_Xui_Client::clear_session();
					SimpleVPBot_Xui_Client::login_with_retries( 4, 260000 );
				}
			}
			$in2 = SimpleVPBot_Xui_Client::inbound_get( (int) $plan->inbound_id );
			$verified = $in2 ? SimpleVPBot_Xui_Client::inbound_client_by_email( $in2, $email ) : null;
			if ( is_array( $verified ) ) {
				break;
			}
		}
		if ( ! is_array( $verified ) ) {
			SimpleVPBot_Logger::error(
				'provision verify failed: client not visible on panel after addClient',
				array(
					'email'      => $email,
					'inbound_id' => (int) $plan->inbound_id,
					'uuid'       => $rollback_uuid,
				)
			);
			SimpleVPBot_Xui_Client::del_client( (int) $plan->inbound_id, $rollback_uuid );
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
		$service_id = SimpleVPBot_Model_Service::insert(
			array(
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
			)
		);
		if ( ! $service_id ) {
			SimpleVPBot_Logger::error( 'service insert failed; rolling back panel client', array( 'email' => $email ) );
			SimpleVPBot_Xui_Client::del_client( (int) $plan->inbound_id, $uuid );
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
	 * Force totalGB + remark on panel after addClient (some panels ignore fields on add).
	 *
	 * @param int    $inbound_id Inbound id.
	 * @param string $email      Client email tag.
	 * @param string $uuid       Client UUID.
	 * @param int    $total_traffic_bytes Quota in bytes (0 = unlimited), same as DB `total_traffic`.
	 * @param string $panel_label Remark text.
	 */
	private static function apply_panel_client_quota_and_label( $inbound_id, $email, $uuid, $total_traffic_bytes, $panel_label ) {
		$inbound = SimpleVPBot_Xui_Client::inbound_get( (int) $inbound_id );
		if ( ! $inbound ) {
			return;
		}
		$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
		$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
		if ( ! is_array( $dec ) || empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
			return;
		}
		$updated = null;
		foreach ( $dec['clients'] as &$cl ) {
			if ( isset( $cl['email'] ) && (string) $cl['email'] === (string) $email ) {
				$cl['totalGB'] = SimpleVPBot_Inbound_Linker::panel_client_totalgb_json_value( (int) $total_traffic_bytes );
				$cl['remark']  = (string) $panel_label;
				$cl['enable']  = true;
				$updated       = $cl;
				break;
			}
		}
		unset( $cl );
		if ( ! is_array( $updated ) ) {
			SimpleVPBot_Logger::error( 'apply_panel_client_quota: client not found', array( 'email' => $email ) );
			return;
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
			SimpleVPBot_Logger::error(
				'apply_panel_client_quota_after_add failed',
				array( 'res' => $res, 'email' => $email, 'inbound_id' => (int) $inbound_id )
			);
		}
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
}
