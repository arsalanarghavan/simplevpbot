<?php
/**
 * Move one Xray service from one panel to another.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Service_Panel_Transfer
 */
class SimpleVPBot_Service_Panel_Transfer {

	/**
	 * Transfer service between panels while preserving remaining quota/time.
	 *
	 * @param int    $service_id       Service id.
	 * @param int    $target_panel_id  Target panel id.
	 * @param int    $target_plan_id   Optional target plan id.
	 * @param string $actor_label      Admin label.
	 * @return array{ok:bool, reason?:string, message?:string}
	 */
	public static function transfer_service( $service_id, $target_panel_id, $target_plan_id = 0, $actor_label = '' ) {
		$sid  = (int) $service_id;
		$tpid = (int) $target_panel_id;
		$tpln = (int) $target_plan_id;
		if ( $sid < 1 || $tpid < 1 ) {
			return array( 'ok' => false, 'reason' => 'bad_params' );
		}
		$svc = SimpleVPBot_Model_Service::find_any( $sid );
		if ( ! $svc || SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			return array( 'ok' => false, 'reason' => 'bad_service' );
		}
		$spid = max( 1, (int) ( $svc->panel_id ?? 1 ) );
		$siid = (int) ( $svc->inbound_id ?? 0 );
		if ( $siid < 1 ) {
			return array( 'ok' => false, 'reason' => 'bad_inbound' );
		}
		$plan = self::resolve_target_plan( $tpid, $tpln );
		if ( ! $plan ) {
			return array( 'ok' => false, 'reason' => 'target_plan_not_found' );
		}
		$tiid = (int) ( $plan->inbound_id ?? 0 );
		if ( $tiid < 1 ) {
			return array( 'ok' => false, 'reason' => 'target_inbound_missing' );
		}

		$remaining_bytes = self::remaining_quota_bytes( $svc );
		$remaining_secs  = self::remaining_seconds( $svc );
		$expiry_ms       = $remaining_secs > 0 ? ( ( time() + $remaining_secs ) * 1000 ) : 0;
		$totalgb         = SimpleVPBot_Inbound_Linker::panel_client_totalgb_json_value( $remaining_bytes );
		$new_email       = self::new_client_email( (int) ( $svc->user_id ?? 0 ) );
		$new_uuid        = '';
		$new_subid       = '';

		$create = SimpleVPBot_Xui_Client::run_with_panel(
			$tpid,
			function () use ( $tiid, $totalgb, $expiry_ms, $svc, &$new_email, &$new_uuid, &$new_subid ) {
				if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
					return array( 'ok' => false, 'reason' => 'target_login' );
				}
				$inb = SimpleVPBot_Xui_Client::inbound_get( $tiid );
				if ( ! is_array( $inb ) ) {
					return array( 'ok' => false, 'reason' => 'target_inbound' );
				}
				$new_uuid = (string) SimpleVPBot_Xui_Client::get_new_uuid();
				if ( '' === $new_uuid ) {
					return array( 'ok' => false, 'reason' => 'new_uuid' );
				}
				$new_subid = substr( md5( $new_email . microtime( true ) ), 0, 16 );
				$template  = self::inbound_template_client( $inb );
				$client    = is_array( $template ) ? $template : array();
				$client['id']         = $new_uuid;
				$client['email']      = $new_email;
				$client['enable']     = true;
				$client['subId']      = $new_subid;
				$client['remark']     = (string) ( $svc->remark ?? $new_email );
				$client['totalGB']    = $totalgb;
				$client['expiryTime'] = (int) $expiry_ms;
				foreach ( array( 'up', 'down', 'total', 'lastOnline' ) as $drop_key ) {
					if ( array_key_exists( $drop_key, $client ) ) {
						unset( $client[ $drop_key ] );
					}
				}
				$payload = array(
					'id'       => $tiid,
					'settings' => wp_json_encode( array( 'clients' => array( $client ) ) ),
				);
				$res = SimpleVPBot_Xui_Client::add_client( $payload );
				if ( ! SimpleVPBot_Xui_Client::response_is_success( $res ) ) {
					return array( 'ok' => false, 'reason' => 'target_add_failed' );
				}
				$inb2 = SimpleVPBot_Xui_Client::inbound_get( $tiid );
				$cl2  = is_array( $inb2 ) ? SimpleVPBot_Xui_Client::inbound_client_by_email( $inb2, $new_email ) : null;
				if ( ! is_array( $cl2 ) ) {
					return array( 'ok' => false, 'reason' => 'target_verify_failed' );
				}
				return array( 'ok' => true );
			}
		);
		if ( empty( $create['ok'] ) ) {
			return is_array( $create ) ? $create : array( 'ok' => false, 'reason' => 'target_unknown' );
		}

		$del = self::delete_source_client( $spid, $siid, (string) ( $svc->xui_client_id ?? '' ), (string) ( $svc->email ?? '' ) );
		if ( empty( $del['ok'] ) ) {
			self::delete_target_client( $tpid, $tiid, $new_email );
			return array( 'ok' => false, 'reason' => (string) ( $del['reason'] ?? 'source_delete_failed' ) );
		}

		$expires_at = $remaining_secs > 0 ? gmdate( 'Y-m-d H:i:s', time() + $remaining_secs ) : null;
		SimpleVPBot_Model_Service::update(
			$sid,
			array(
				'panel_id'        => $tpid,
				'inbound_id'      => $tiid,
				'plan_id'         => (int) $plan->id,
				'xui_client_id'   => $new_uuid,
				'xui_client_uuid' => $new_uuid,
				'email'           => $new_email,
				'sub_id'          => $new_subid,
				'expires_at'      => $expires_at,
				'total_traffic'   => (int) $remaining_bytes,
				'remark'          => (string) ( $plan->name ?? $svc->remark ?? '' ),
			)
		);
		$verify = SimpleVPBot_Model_Service::find( $sid );
		if (
			! $verify
			|| (int) ( $verify->panel_id ?? 0 ) !== $tpid
			|| (int) ( $verify->inbound_id ?? 0 ) !== $tiid
			|| (string) ( $verify->email ?? '' ) !== $new_email
		) {
			self::delete_target_client( $tpid, $tiid, $new_email );
			SimpleVPBot_Logger::error(
				'panel transfer: DB update failed after panel steps',
				array(
					'service_id'      => $sid,
					'target_panel_id' => $tpid,
					'target_email'    => $new_email,
				)
			);
			return array( 'ok' => false, 'reason' => 'transfer_db_failed' );
		}
		if ( class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			SimpleVPBot_Service_Admin_Ops::configs_sync_inbounds_after_mutation( $spid, array( $siid ) );
			SimpleVPBot_Service_Admin_Ops::configs_sync_inbounds_after_mutation( $tpid, array( $tiid ) );
		}
		self::notify_after_transfer( $svc, $actor_label, $plan );
		return array( 'ok' => true );
	}

	private static function resolve_target_plan( $panel_id, $target_plan_id ) {
		$pid = (int) $panel_id;
		$pln = (int) $target_plan_id;
		if ( $pln > 0 ) {
			$plan = SimpleVPBot_Model_Plan::find( $pln );
			if ( $plan && (int) ( $plan->panel_id ?? 0 ) === $pid ) {
				return $plan;
			}
			return null;
		}
		global $wpdb;
		$t = SimpleVPBot_Model_Plan::table();
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$t} WHERE panel_id = %d AND active = 1 AND inbound_id > 0 AND (service_type IS NULL OR service_type = '' OR service_type = 'xray') ORDER BY sort_order ASC, id ASC LIMIT 1",
				$pid
			)
		); // phpcs:ignore
	}

	private static function remaining_quota_bytes( $svc ) {
		$total = (int) ( $svc->total_traffic ?? 0 );
		$used  = (int) ( $svc->used_traffic ?? 0 );
		if ( class_exists( 'SimpleVPBot_Model_Panel_Inbound_Client' ) ) {
			global $wpdb;
			$t = SimpleVPBot_Model_Panel_Inbound_Client::table();
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT used_bytes, limit_bytes FROM {$t} WHERE panel_id = %d AND inbound_id = %d AND email = %s LIMIT 1",
					(int) ( $svc->panel_id ?? 1 ),
					(int) ( $svc->inbound_id ?? 0 ),
					(string) ( $svc->email ?? '' )
				)
			); // phpcs:ignore
			if ( $row ) {
				$total = (int) ( $row->limit_bytes ?? $total );
				$used  = (int) ( $row->used_bytes ?? $used );
			}
		}
		if ( $total < 1 ) {
			return 0;
		}
		return max( 0, $total - $used );
	}

	private static function remaining_seconds( $svc ) {
		$exp = isset( $svc->expires_at ) ? (string) $svc->expires_at : '';
		if ( '' === $exp ) {
			return 0;
		}
		$ts = strtotime( $exp . ' UTC' );
		if ( ! $ts ) {
			return 0;
		}
		return max( 0, $ts - time() );
	}

	private static function new_client_email( $user_id ) {
		return 'u' . max( 1, (int) $user_id ) . '_' . wp_generate_password( 6, false, false ) . '@svp.local';
	}

	private static function inbound_template_client( $inbound ) {
		$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
		$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
		if ( ! is_array( $dec ) || empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
			return array();
		}
		return is_array( $dec['clients'][0] ?? null ) ? $dec['clients'][0] : array();
	}

	private static function delete_source_client( $panel_id, $inbound_id, $xui_client_id, $email ) {
		return SimpleVPBot_Xui_Client::run_with_panel(
			(int) $panel_id,
			function () use ( $inbound_id, $xui_client_id, $email ) {
				if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
					return array( 'ok' => false, 'reason' => 'source_login' );
				}
				$inb = SimpleVPBot_Xui_Client::inbound_get( (int) $inbound_id );
				$cid = SimpleVPBot_Xui_Client::resolve_client_key_for_update( (string) $xui_client_id, $inb, (string) $email );
				if ( '' === (string) $cid ) {
					$cid = (string) $email;
				}
				$res = SimpleVPBot_Xui_Client::del_client( (int) $inbound_id, (string) $cid, (string) $email );
				return SimpleVPBot_Xui_Client::response_is_success( $res )
					? array( 'ok' => true )
					: array( 'ok' => false, 'reason' => 'source_delete' );
			}
		);
	}

	private static function delete_target_client( $panel_id, $inbound_id, $email ) {
		SimpleVPBot_Xui_Client::run_with_panel(
			(int) $panel_id,
			function () use ( $inbound_id, $email ) {
				if ( ! SimpleVPBot_Xui_Client::login_with_retries( 4, 220000 ) ) {
					return null;
				}
				SimpleVPBot_Xui_Client::del_client( (int) $inbound_id, (string) $email, (string) $email );
				return null;
			}
		);
	}

	private static function notify_after_transfer( $svc, $actor_label, $plan ) {
		$uid = (int) ( $svc->user_id ?? 0 );
		if ( $uid < 1 ) {
			return;
		}
		$user = SimpleVPBot_Model_User::find( $uid );
		if ( ! $user ) {
			return;
		}
		$msg = '🔁 سرویس شما به سرور جدید منتقل شد.';
		if ( $plan ) {
			$msg .= "\nپلن جدید: " . (string) ( $plan->name ?? '' );
		}
		if ( '' !== trim( (string) $actor_label ) ) {
			$msg .= "\nتوسط: " . (string) $actor_label;
		}
		if ( ! empty( $user->tg_user_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( 'telegram', (int) $user->tg_user_id, $msg );
		}
		if ( ! empty( $user->bale_user_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( 'bale', (int) $user->bale_user_id, $msg );
		}
	}
}

