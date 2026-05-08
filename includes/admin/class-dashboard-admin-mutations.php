<?php
/**
 * Admin mutations for REST dashboard (logic moved from legacy WP admin forms).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Dashboard_Admin_Mutations
 */
class SimpleVPBot_Dashboard_Admin_Mutations {

	/**
	 * Log dashboard REST action affecting a bot user (best-effort).
	 *
	 * @param int                  $subject_svp_user_id svp_users.id.
	 * @param string               $event_type          Short event key.
	 * @param array<string, mixed> $payload             Extra JSON-safe fields.
	 */
	private static function log_rest_user( $subject_svp_user_id, $event_type, array $payload ) {
		if ( ! class_exists( 'SimpleVPBot_User_Activity_Log' ) ) {
			return;
		}
		$sid = (int) $subject_svp_user_id;
		if ( $sid < 1 ) {
			return;
		}
		$wp = get_current_user_id();
		SimpleVPBot_User_Activity_Log::append(
			array(
				'subject_svp_user_id' => $sid,
				'channel'             => 'rest',
				'actor_kind'          => 'wp_admin',
				'actor_wp_user_id'    => $wp > 0 ? $wp : 0,
				'actor_svp_user_id'   => 0,
				'platform_chat_id'    => 0,
				'event_type'          => sanitize_key( (string) $event_type ),
				'payload'             => array_merge( array( 'event' => (string) $event_type ), $payload ),
			)
		);
	}

	/**
	 * Refresh inbound-client DB cache used by /dashboard/configs/ after X-UI panel data changes.
	 *
	 * @param int $service_id svp_services.id.
	 */
	private static function configs_sync_after_service_panel_change( $service_id ) {
		$sid = (int) $service_id;
		if ( $sid < 1 || ! class_exists( 'SimpleVPBot_Model_Service' ) || ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return;
		}
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc || (int) $svc->inbound_id < 1 ) {
			return;
		}
		if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			return;
		}
		$panel_id = (int) ( $svc->panel_id ?? 0 );
		if ( $panel_id < 1 ) {
			$panel_id = 1;
		}
		SimpleVPBot_Service_Admin_Ops::configs_sync_inbounds_after_mutation(
			$panel_id,
			array( (int) $svc->inbound_id )
		);
	}

	/**
	 * Apply one mutation from JSON params (already unslashed by REST).
	 *
	 * @param string               $op     Operation key.
	 * @param array<string, mixed> $params Parameters.
	 * @return array{ok:bool, message?:string, code?:string, data?:mixed}
	 */
	public static function apply( $op, array $params ) {
		$op = sanitize_key( (string) $op );
		switch ( $op ) {
			case 'settings_tab':
				return self::op_settings_tab( $params );
			case 'plan':
				return self::op_plan( $params );
			case 'plan_category':
				return self::op_plan_category( $params );
			case 'panel_xp':
				return self::op_panel_xp( $params );
			case 'panel_test':
				return self::op_panel_test( $params );
			case 'crypto_settings':
				return self::op_crypto_settings( $params );
			case 'card_add':
				return self::op_card_add( $params );
			case 'card_update':
				return self::op_card_update( $params );
			case 'card_delete':
				return self::op_card_delete( $params );
			case 'l2tp_add':
				return self::op_l2tp_add( $params );
			case 'l2tp_update':
				return self::op_l2tp_update( $params );
			case 'l2tp_delete':
				return self::op_l2tp_delete( $params );
			case 'texts_save':
				return self::op_texts_save( $params );
			case 'text_reset_one':
				return self::op_text_reset_one( $params );
			case 'texts_reset':
				return self::op_texts_reset();
			case 'bot_ui_layout_save':
				return self::op_bot_ui_layout_save( $params );
			case 'bot_ui_layout_reset':
				return self::op_bot_ui_layout_reset();
			case 'membership':
				return self::op_membership( $params );
			case 'receipt_set_status':
				return self::op_receipt_set_status( $params );
			case 'receipt_action':
				return self::op_receipt_action( $params );
			case 'broadcast_send':
				return self::op_broadcast_send( $params );
			case 'broadcast_cancel':
				return self::op_broadcast_cancel( $params );
			case 'broadcast_run_worker':
				return self::op_broadcast_run_worker( $params );
			case 'discount_save':
				return self::op_discount_save( $params );
			case 'discount_delete':
				return self::op_discount_delete( $params );
			case 'link_wp_user':
				return self::op_link_wp_user( $params );
			case 'service_delete':
				return self::op_service_delete( $params );
			case 'user_status':
				return self::op_user_status( $params );
			case 'user_balance_delta':
				return self::op_user_balance_delta( $params );
			case 'user_create_service':
				return self::op_user_create_service( $params );
			case 'user_renew_service':
				return self::op_user_renew_service( $params );
			case 'user_add_volume':
				return self::op_user_add_volume( $params );
			case 'user_service_transfer':
				return self::op_user_service_transfer( $params );
			case 'user_manual_create':
				return self::op_user_manual_create( $params );
			case 'user_merge_preview':
				return self::op_user_merge_preview( $params );
			case 'user_merge':
				return self::op_user_merge( $params );
			case 'users_bulk_wallet':
				return self::op_users_bulk_wallet( $params );
			case 'users_bulk_volume':
				return self::op_users_bulk_volume( $params );
			case 'users_bulk_extend':
				return self::op_users_bulk_extend( $params );
			case 'users_bulk_alerts':
				return self::op_users_bulk_alerts( $params );
			case 'users_bulk_run_worker':
				return self::op_users_bulk_run_worker( $params );
			case 'users_bulk_job_cancel':
				return self::op_users_bulk_job_cancel( $params );
			case 'users_bulk_job_resume':
				return self::op_users_bulk_job_resume( $params );
			case 'reseller_wp_provision':
				return self::op_reseller_wp_provision( $params );
			case 'reseller_panel_prices_save':
				return self::op_reseller_panel_prices_save( $params );
			case 'reseller_permissions_save':
				return self::op_reseller_permissions_save( $params );
			case 'reseller_bot_tokens_save':
				return self::op_reseller_bot_tokens_save( $params );
			case 'reseller_bot_webhook_set':
				return self::op_reseller_bot_webhook_set( $params );
			case 'reseller_bot_secret_rotate':
				return self::op_reseller_bot_secret_rotate( $params );
			case 'inbound_link':
				return self::op_inbound_link( $params );
			case 'inbound_autolink':
				return self::op_inbound_autolink( $params );
			case 'user_admin_message':
				return self::op_user_admin_message( $params );
			case 'service_alerts_patch':
				return self::op_service_alerts_patch( $params );
			case 'service_panel_sync':
				return self::op_service_panel_sync( $params );
			case 'service_regen_key':
				return self::op_service_regen_key( $params );
			case 'service_panel_refresh':
				return self::op_service_panel_refresh( $params );
			case 'service_panel_delete_client':
				return self::op_service_panel_delete_client( $params );
			case 'user_service_add_slots':
				return self::op_user_service_add_slots( $params );
			case 'service_set_limit_ip':
				return self::op_service_set_limit_ip( $params );
			case 'configs_client_toggle_enable':
				return self::op_configs_client_toggle_enable( $params );
			case 'configs_client_reset_traffic':
				return self::op_configs_client_reset_traffic( $params );
			case 'configs_client_delete':
				return self::op_configs_client_delete( $params );
			case 'configs_delete_expired_linked':
				return self::op_configs_delete_expired_linked( $params );
			case 'configs_panel_client_patch':
				return self::op_configs_panel_client_patch( $params );
			case 'configs_clients_batch':
				return self::op_configs_clients_batch( $params );
			case 'configs_assign_plan':
				return self::op_configs_assign_plan( $params );
			case 'service_panel_transfer':
				return self::op_service_panel_transfer( $params );
			case 'bot_toggle_enabled':
				return self::op_bot_toggle_enabled( $params );
			case 'bot_test_telegram':
				return self::op_bot_test_telegram( $params );
			case 'bot_test_bale':
				return self::op_bot_test_bale( $params );
			case 'bot_set_webhook':
				return self::op_bot_set_webhook( $params );
			case 'bot_reseller_toggle_enabled':
				return self::op_bot_reseller_toggle_enabled( $params );
			case 'bot_reseller_secret_rotate':
				return self::op_reseller_bot_secret_rotate( $params );
			case 'bot_reseller_delete':
				return self::op_bot_reseller_delete( $params );
			case 'bot_reseller_save':
				return self::op_bot_reseller_save( $params );
			default:
				return array( 'ok' => false, 'message' => 'unknown_op' );
		}
	}

	/**
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_settings_tab( array $p ) {
		$tab = isset( $p['tab'] ) ? sanitize_key( (string) $p['tab'] ) : '';
		if ( '' === $tab ) {
			return array( 'ok' => false, 'message' => 'missing_tab' );
		}
		unset( $p['tab'] );
		$ok = SimpleVPBot_Admin_Actions::apply_settings_tab( $tab, $p );
		if ( $ok ) {
			SimpleVPBot_Admin_Actions::after_settings_tab_saved( $tab );
		}
		return array( 'ok' => (bool) $ok, 'message' => $ok ? 'saved' : 'invalid_tab' );
	}

	/**
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool, message?:string, code?:string}
	 */
	private static function op_plan( array $p ) {
		$action = isset( $p['plan_action'] ) ? sanitize_key( (string) $p['plan_action'] ) : '';
		$pid    = isset( $p['plan_id'] ) ? absint( $p['plan_id'] ) : 0;
		$res    = SimpleVPBot_Service_Admin_Catalog::apply_plan_action( $action, $pid, $p );
		if ( null === $res ) {
			return array( 'ok' => false, 'message' => 'noop' );
		}
		return array(
			'ok'      => ! empty( $res['ok'] ),
			'code'    => isset( $res['code'] ) ? (string) $res['code'] : '',
			'plan_id' => isset( $res['plan_id'] ) ? (int) $res['plan_id'] : 0,
		);
	}

	/**
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool, code?:string}
	 */
	private static function op_plan_category( array $p ) {
		$action = isset( $p['pc_action'] ) ? sanitize_key( (string) $p['pc_action'] ) : '';
		$rid    = isset( $p['pc_id'] ) ? absint( $p['pc_id'] ) : 0;
		$res    = SimpleVPBot_Service_Admin_Catalog::apply_plan_category_action( $action, $rid, $p );
		if ( null === $res ) {
			return array( 'ok' => false, 'message' => 'noop' );
		}
		return array(
			'ok'   => ! empty( $res['ok'] ),
			'code' => isset( $res['code'] ) ? (string) $res['code'] : '',
		);
	}

	/**
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool, code?:string}
	 */
	private static function op_panel_xp( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Model_Panel' ) ) {
			return array( 'ok' => false, 'message' => 'no_panel_model' );
		}
		$action = isset( $p['xp_action'] ) ? sanitize_key( (string) $p['xp_action'] ) : '';
		$rid    = isset( $p['xp_id'] ) ? absint( $p['xp_id'] ) : 0;
		if ( 'toggle' === $action && $rid > 0 ) {
			$row = SimpleVPBot_Model_Panel::find( $rid );
			if ( $row ) {
				SimpleVPBot_Model_Panel::update( $rid, array( 'active' => (int) ! (int) $row->active ) );
			}
			return array( 'ok' => true, 'code' => 'toggled' );
		}
		if ( 'delete' === $action && $rid > 0 ) {
			if ( SimpleVPBot_Model_Plan::count_by_panel_id( $rid ) > 0 || SimpleVPBot_Model_Service::count_for_panel( $rid ) > 0 ) {
				return array( 'ok' => false, 'code' => 'inuse' );
			}
			SimpleVPBot_Model_Panel::delete( $rid );
			return array( 'ok' => true, 'code' => 'deleted' );
		}
		$label   = sanitize_text_field( (string) ( $p['xp_label'] ?? '' ) );
		$purl    = esc_url_raw( (string) ( $p['xp_panel_url'] ?? '' ) );
		$user    = sanitize_text_field( (string) ( $p['xp_panel_username'] ?? '' ) );
		$api     = trim( (string) ( $p['xp_panel_api_base'] ?? 'panel/api' ), " \t\n\r\0\x0B/" );
		$api     = '' !== $api ? $api : 'panel/api';
		$sec     = sanitize_text_field( (string) ( $p['xp_panel_login_secret'] ?? '' ) );
		$subbase = trim( (string) ( $p['xp_subscription_public_base'] ?? '' ) );
		$subbase = '' !== $subbase ? esc_url_raw( $subbase ) : '';
		$sort    = (int) ( $p['xp_sort_order'] ?? 0 );
		$active  = ! empty( $p['xp_active'] ) ? 1 : 0;
		$pw_raw  = (string) ( $p['xp_panel_password'] ?? '' );
		if ( ( 'add' === $action && ( '' === $label || '' === $purl || '' === trim( $pw_raw ) ) ) || ( 'update' === $action && $rid > 0 && ( '' === $label || '' === $purl ) ) ) {
			return array( 'ok' => false, 'code' => 'invalid' );
		}
		$data = array(
			'label'                    => $label,
			'panel_url'                => $purl,
			'panel_username'           => $user,
			'panel_api_base'           => $api,
			'panel_login_secret'       => $sec,
			'subscription_public_base' => '' !== $subbase ? $subbase : '',
			'sort_order'               => $sort,
			'active'                   => $active,
		);
		if ( 'add' === $action ) {
			$data['panel_password'] = $pw_raw;
			SimpleVPBot_Model_Panel::insert( $data );
			return array( 'ok' => true, 'code' => 'added' );
		}
		if ( 'update' === $action && $rid > 0 ) {
			if ( '' !== trim( $pw_raw ) ) {
				$data['panel_password'] = $pw_raw;
			}
			SimpleVPBot_Model_Panel::update( $rid, $data );
			return array( 'ok' => true, 'code' => 'updated' );
		}
		return array( 'ok' => false, 'message' => 'bad_action' );
	}

	/**
	 * Test 3x-ui panel connectivity (read-only probes).
	 *
	 * @param array<string, mixed> $p Params (panel_id int, 0 = legacy settings).
	 * @return array{ok:bool, message?:string, data?:mixed}
	 */
	private static function op_panel_test( array $p ) {
		$pid = isset( $p['panel_id'] ) ? max( 0, (int) $p['panel_id'] ) : 0;
		$res = SimpleVPBot_Service_Admin_Ops::test_panel( $pid );
		return array(
			'ok'      => ! empty( $res['ok'] ),
			'message' => isset( $res['message'] ) ? (string) $res['message'] : '',
			'data'    => isset( $res['data'] ) ? $res['data'] : null,
		);
	}

	/**
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool}
	 */
	private static function op_crypto_settings( array $p ) {
		$all                                   = SimpleVPBot_Settings::all();
		$all['crypto_nowpayments_ipn_secret']  = sanitize_text_field( (string) ( $p['crypto_nowpayments_ipn_secret'] ?? '' ) );
		$all['crypto_nowpayments_api_key']     = sanitize_text_field( (string) ( $p['crypto_nowpayments_api_key'] ?? '' ) );
		$all['crypto_nowpayments_pay_currency'] = sanitize_key( (string) ( $p['crypto_nowpayments_pay_currency'] ?? 'usdttrc20' ) );
		$all['crypto_toman_per_usd']           = max( 1.0, (float) str_replace( ',', '.', (string) ( $p['crypto_toman_per_usd'] ?? '50000' ) ) );
		SimpleVPBot_Settings::update( $all );
		SimpleVPBot_Texts::clear_cache();
		return array( 'ok' => true );
	}

	/**
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool}
	 */
	private static function op_card_add( array $p ) {
		$owner = isset( $p['owner_svp_user_id'] ) ? max( 0, (int) $p['owner_svp_user_id'] ) : 0;
		SimpleVPBot_Model_Card::insert(
			array(
				'owner_svp_user_id' => $owner,
				'card_number' => sanitize_text_field( (string) ( $p['card_number'] ?? '' ) ),
				'holder_name' => sanitize_text_field( (string) ( $p['holder_name'] ?? '' ) ),
				'bank_name'   => sanitize_text_field( (string) ( $p['bank_name'] ?? '' ) ),
				'method_key'  => SimpleVPBot_Service_Admin_Catalog::sanitize_card_method_key( (string) ( $p['method_key'] ?? 'c2c' ) ),
				'daily_limit' => (float) ( $p['daily_limit'] ?? 0 ),
				'priority'    => (int) ( $p['priority'] ?? 0 ),
				'note'        => sanitize_textarea_field( (string) ( $p['note'] ?? '' ) ),
				'active'      => 1,
			)
		);
		return array( 'ok' => true );
	}

	/**
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_card_update( array $p ) {
		$eid = isset( $p['edit_id'] ) ? (int) $p['edit_id'] : 0;
		$row = $eid ? SimpleVPBot_Model_Card::find( $eid ) : null;
		if ( ! $row ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		SimpleVPBot_Model_Card::update(
			$eid,
			array(
				'owner_svp_user_id' => isset( $p['owner_svp_user_id'] ) ? max( 0, (int) $p['owner_svp_user_id'] ) : (int) ( $row->owner_svp_user_id ?? 0 ),
				'card_number' => sanitize_text_field( (string) ( $p['card_number'] ?? '' ) ),
				'holder_name' => sanitize_text_field( (string) ( $p['holder_name'] ?? '' ) ),
				'bank_name'   => sanitize_text_field( (string) ( $p['bank_name'] ?? '' ) ),
				'method_key'  => SimpleVPBot_Service_Admin_Catalog::sanitize_card_method_key( (string) ( $p['method_key'] ?? 'c2c' ) ),
				'daily_limit' => (float) ( $p['daily_limit'] ?? 0 ),
				'priority'    => (int) ( $p['priority'] ?? 0 ),
				'note'        => sanitize_textarea_field( (string) ( $p['note'] ?? '' ) ),
				'active'      => ! empty( $p['active'] ) ? 1 : 0,
			)
		);
		return array( 'ok' => true );
	}

	/**
	 * @param array<string, mixed> $p Params: edit_id or card_id.
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_card_delete( array $p ) {
		$eid = isset( $p['edit_id'] ) ? (int) $p['edit_id'] : ( isset( $p['card_id'] ) ? (int) $p['card_id'] : 0 );
		if ( $eid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_id' );
		}
		$row = SimpleVPBot_Model_Card::find( $eid );
		if ( ! $row ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		SimpleVPBot_Model_Card::delete( $eid );
		return array( 'ok' => true );
	}

	/**
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool}
	 */
	private static function op_l2tp_add( array $p ) {
		SimpleVPBot_Model_L2TP_Server::insert( SimpleVPBot_Service_Admin_Catalog::sanitize_l2tp_post( null, $p ) );
		return array( 'ok' => true );
	}

	/**
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool}
	 */
	private static function op_l2tp_update( array $p ) {
		$eid = isset( $p['edit_id'] ) ? (int) $p['edit_id'] : 0;
		if ( $eid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_id' );
		}
		SimpleVPBot_Model_L2TP_Server::update( $eid, SimpleVPBot_Service_Admin_Catalog::sanitize_l2tp_post( $eid, $p ) );
		return array( 'ok' => true );
	}

	/**
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool}
	 */
	private static function op_l2tp_delete( array $p ) {
		$eid = isset( $p['edit_id'] ) ? (int) $p['edit_id'] : 0;
		if ( $eid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_id' );
		}
		SimpleVPBot_Model_L2TP_Server::delete( $eid );
		return array( 'ok' => true );
	}

	/**
	 * Telegram/Bale-safe subset (HTML) + strip controls.
	 *
	 * @param string $text Raw.
	 * @return string
	 */
	public static function sanitize_bot_text_for_messages( $text ) {
		$t = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $text );
		return self::broadcast_sanitize_html( $t );
	}

	/**
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_text_reset_one( array $p ) {
		$key = isset( $p['text_key'] ) ? trim( (string) $p['text_key'] ) : '';
		if ( '' === $key || ! preg_match( '/^[a-zA-Z0-9._-]+$/', $key ) ) {
			return array( 'ok' => false, 'message' => 'bad_key' );
		}
		foreach ( array( 'fa', 'en' ) as $loc ) {
			$row = SimpleVPBot_Activator::default_row_for_text_key( $key, $loc );
			if ( ! $row ) {
				continue;
			}
			SimpleVPBot_Model_Text::set( $row['key_name'], $row['value'], $row['category'], $loc );
		}
		SimpleVPBot_Texts::clear_cache();
		return array( 'ok' => true );
	}

	/**
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool}
	 */
	private static function op_texts_save( array $p ) {
		$texts = isset( $p['texts'] ) && is_array( $p['texts'] ) ? $p['texts'] : array();
		foreach ( $texts as $key => $val ) {
			$k = trim( (string) $key );
			if ( '' === $k || ! preg_match( '/^[a-zA-Z0-9._-]+$/', $k ) ) {
				continue;
			}
			$cat = 'general';
			$def = SimpleVPBot_Activator::default_row_for_text_key( $k, 'fa' );
			if ( $def ) {
				$cat = $def['category'];
			}
			if ( is_array( $val ) ) {
				foreach ( array( 'fa', 'en' ) as $loc ) {
					if ( ! array_key_exists( $loc, $val ) ) {
						continue;
					}
					SimpleVPBot_Model_Text::set( $k, self::sanitize_bot_text_for_messages( (string) $val[ $loc ] ), $cat, $loc );
				}
			} else {
				SimpleVPBot_Model_Text::set( $k, self::sanitize_bot_text_for_messages( (string) $val ), $cat, 'fa' );
			}
		}
		SimpleVPBot_Texts::clear_cache();
		return array( 'ok' => true );
	}

	/**
	 * @return array{ok:bool}
	 */
	private static function op_texts_reset() {
		SimpleVPBot_Activator::reset_texts_to_defaults();
		SimpleVPBot_Texts::clear_cache();
		return array( 'ok' => true );
	}

	/**
	 * Save Bot UI Studio layouts (validated server-side).
	 *
	 * @param array<string, mixed> $p Params with `surfaces` map.
	 * @return array{ok:bool, message?:string, data?:array<string, mixed>}
	 */
	private static function op_bot_ui_layout_save( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_UI_Layout' ) ) {
			return array( 'ok' => false, 'message' => 'missing_ui' );
		}
		$surfaces_in = isset( $p['surfaces'] ) && is_array( $p['surfaces'] ) ? $p['surfaces'] : array();
		$v           = SimpleVPBot_UI_Layout::validate_surfaces_payload( $surfaces_in );
		if ( ! $v['ok'] ) {
			return array(
				'ok'      => false,
				'message' => 'validation_failed',
				'data'    => array( 'errors' => isset( $v['errors'] ) ? $v['errors'] : array() ),
			);
		}
		if ( ! empty( $v['surfaces'] ) ) {
			SimpleVPBot_UI_Layout::save_surfaces( $v['surfaces'] );
		}
		return array(
			'ok'   => true,
			'data' => array( 'uiLayout' => SimpleVPBot_UI_Layout::export_merged_for_dashboard() ),
		);
	}

	/**
	 * @return array{ok:bool, message?:string, data?:array<string, mixed>}
	 */
	private static function op_bot_ui_layout_reset() {
		if ( ! class_exists( 'SimpleVPBot_UI_Layout' ) ) {
			return array( 'ok' => false, 'message' => 'missing_ui' );
		}
		SimpleVPBot_UI_Layout::reset_all();
		return array(
			'ok'   => true,
			'data' => array( 'uiLayout' => SimpleVPBot_UI_Layout::export_merged_for_dashboard() ),
		);
	}

	/**
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool, reason?:string}
	 */
	private static function op_membership( array $p ) {
		$m_uid = (int) ( $p['membership_user_id'] ?? 0 );
		$m_act = sanitize_key( (string) ( $p['svp_user_membership_action'] ?? $p['action'] ?? '' ) );
		$label = (string) wp_get_current_user()->user_login;
		if ( $m_uid < 1 || ! class_exists( 'SimpleVPBot_User_Membership' ) ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		if ( 'approve' === $m_act ) {
			$res = SimpleVPBot_User_Membership::approve( $m_uid, $label );
			if ( ! empty( $res['ok'] ) ) {
				self::log_rest_user( $m_uid, 'membership_approve', array( 'by' => $label ) );
			}
			return array( 'ok' => ! empty( $res['ok'] ), 'reason' => isset( $res['reason'] ) ? (string) $res['reason'] : '' );
		}
		if ( 'reject' === $m_act ) {
			$res = SimpleVPBot_User_Membership::reject( $m_uid, $label );
			if ( ! empty( $res['ok'] ) ) {
				self::log_rest_user( $m_uid, 'membership_reject', array( 'by' => $label ) );
			}
			return array( 'ok' => ! empty( $res['ok'] ), 'reason' => isset( $res['reason'] ) ? (string) $res['reason'] : '' );
		}
		if ( 'reopen' === $m_act ) {
			$res = SimpleVPBot_User_Membership::reopen_rejected_to_pending( $m_uid );
			if ( ! empty( $res['ok'] ) ) {
				self::log_rest_user( $m_uid, 'membership_reopen', array( 'by' => $label ) );
			}
			return array( 'ok' => ! empty( $res['ok'] ), 'reason' => isset( $res['reason'] ) ? (string) $res['reason'] : '' );
		}
		return array( 'ok' => false, 'message' => 'bad_action' );
	}

	/**
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_receipt_set_status( array $p ) {
		$rid = (int) ( $p['receipt_id'] ?? 0 );
		$new = sanitize_key( (string) ( $p['receipt_new_status'] ?? '' ) );
		$lab = (string) wp_get_current_user()->user_login;
		if ( $rid < 1 || '' === $new ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		$res = SimpleVPBot_Receipt_Processor::admin_set_receipt_status( $rid, $new, $lab );
		return array( 'ok' => ! empty( $res['ok'] ), 'data' => $res );
	}

	/**
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool, data?:mixed}
	 */
	private static function op_receipt_action( array $p ) {
		$rid   = (int) ( $p['receipt_id'] ?? 0 );
		$act   = sanitize_key( (string) ( $p['svp_receipt_action'] ?? $p['action'] ?? '' ) );
		$label = (string) wp_get_current_user()->user_login;
		if ( $rid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		if ( 'approve' === $act ) {
			$res = SimpleVPBot_Receipt_Processor::approve( $rid, $label );
			return array( 'ok' => true, 'data' => $res );
		}
		if ( 'reject' === $act ) {
			SimpleVPBot_Receipt_Processor::reject( $rid, $label );
			return array( 'ok' => true );
		}
		return array( 'ok' => false, 'message' => 'bad_action' );
	}

	/**
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_broadcast_send( array $p ) {
		$owner = isset( $p['owner_svp_user_id'] ) ? max( 0, (int) $p['owner_svp_user_id'] ) : 0;
		$targets = sanitize_key( (string) ( $p['bc_targets'] ?? 'both' ) );
		if ( ! in_array( $targets, array( 'both', 'telegram', 'bale' ), true ) ) {
			$targets = 'both';
		}

		$urls = array();
		if ( ! empty( $p['bc_media_urls'] ) && is_array( $p['bc_media_urls'] ) ) {
			foreach ( $p['bc_media_urls'] as $u ) {
				if ( count( $urls ) >= 10 ) {
					break;
				}
				$u = esc_url_raw( trim( (string) $u ) );
				if ( '' !== $u && wp_http_validate_url( $u ) ) {
					$urls[] = $u;
				}
			}
		}
		if ( count( $urls ) < 10 && ! empty( $p['bc_photo_url'] ) ) {
			$u = esc_url_raw( trim( (string) $p['bc_photo_url'] ) );
			if ( '' !== $u && wp_http_validate_url( $u ) ) {
				$seen = array_flip( $urls );
				if ( ! isset( $seen[ $u ] ) ) {
					$urls[] = $u;
				}
			}
		}
		if ( count( $urls ) > 10 ) {
			$urls = array_slice( $urls, 0, 10 );
		}
		$urls = array_values( array_unique( $urls ) );

		$text_raw  = isset( $p['bc_text'] ) ? (string) $p['bc_text'] : '';
		$text_raw  = str_replace( "\0", '', $text_raw );
		$text_trim = trim( $text_raw );

		if ( '' === $text_trim && empty( $urls ) ) {
			return array( 'ok' => false, 'message' => 'empty' );
		}

		$parse_api = 'HTML';
		$text_safe = self::broadcast_sanitize_html( $text_trim );
		if ( ! empty( $urls ) ) {
			$text_safe = mb_substr( $text_safe, 0, 1024 );
		} else {
			$text_safe = mb_substr( $text_safe, 0, 4096 );
		}

		$photo_first = ! empty( $urls ) ? $urls[0] : '';
		$type        = count( $urls ) >= 2 ? 'album' : ( '' !== $photo_first ? 'photo' : 'text' );

		$content = wp_json_encode(
			array(
				'text'        => $text_safe,
				'parse_mode'  => $parse_api,
				'photo'       => ( 1 === count( $urls ) ) ? $photo_first : '',
				'media_urls'  => $urls,
				'targets'     => $targets,
			),
			JSON_UNESCAPED_UNICODE
		);
		$meta = wp_json_encode(
			array(
				'targets'     => $targets,
				'parse_mode'  => $parse_api,
				'has_photo'   => ! empty( $urls ),
				'media_count' => count( $urls ),
			),
			JSON_UNESCAPED_UNICODE
		);

		$bid = SimpleVPBot_Model_Broadcast::insert(
			array(
				'owner_svp_user_id' => $owner,
				'type'            => $type,
				'content'         => (string) $content,
				'status'          => 'sending',
				'meta_json'       => (string) $meta,
				'total_targets'   => 0,
				'blocked_count'   => 0,
			)
		);

		$include_tg = ( 'both' === $targets || 'telegram' === $targets );
		$include_bl = ( 'both' === $targets || 'bale' === $targets );

		$users = array();
		if ( $owner > 0 ) {
			$scope_ids = SimpleVPBot_Model_User::reseller_scope_user_ids( $owner );
			foreach ( (array) SimpleVPBot_Model_User::all_approved() as $u ) {
				$uid = (int) ( $u->id ?? 0 );
				if ( $uid > 0 && in_array( $uid, $scope_ids, true ) ) {
					$users[] = $u;
				}
			}
		} else {
			$users = SimpleVPBot_Model_User::all_approved();
		}
		$rows  = array();
		$base  = array(
			'text'         => $text_safe,
			'parse_mode'   => $parse_api,
			'photo'        => ( 1 === count( $urls ) ) ? $photo_first : '',
			'media_urls'   => $urls,
		);
		foreach ( $users as $u ) {
			if ( $include_tg && ! empty( $u->tg_user_id ) ) {
				$pl = array_merge(
					$base,
					array( 'chat_id' => (int) $u->tg_user_id )
				);
				$rows[] = array(
					'user_id'      => (int) $u->id,
					'bot'          => 'tg',
					'chat_id'      => (int) $u->tg_user_id,
					'payload_json' => wp_json_encode( $pl, JSON_UNESCAPED_UNICODE ),
					'status'       => 'pending',
				);
			}
			if ( $include_bl && ! empty( $u->bale_user_id ) ) {
				$pl = array_merge(
					$base,
					array( 'chat_id' => (int) $u->bale_user_id )
				);
				$rows[] = array(
					'user_id'      => (int) $u->id,
					'bot'          => 'bale',
					'chat_id'      => (int) $u->bale_user_id,
					'payload_json' => wp_json_encode( $pl, JSON_UNESCAPED_UNICODE ),
					'status'       => 'pending',
				);
			}
		}
		if ( empty( $rows ) ) {
			SimpleVPBot_Model_Broadcast::update( $bid, array( 'status' => 'done', 'total_targets' => 0 ) );
			return array( 'ok' => false, 'message' => 'no_recipients' );
		}
		SimpleVPBot_Model_Broadcast::enqueue_bulk( $bid, $rows );
		SimpleVPBot_Model_Broadcast::update( $bid, array( 'total_targets' => count( $rows ) ) );
		return array( 'ok' => true, 'broadcast_id' => $bid );
	}

	/**
	 * Cancel an in-progress broadcast (stops pending/sending queue rows).
	 *
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_broadcast_cancel( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Model_Broadcast' ) ) {
			return array( 'ok' => false, 'message' => 'no_model' );
		}
		$bid = isset( $p['broadcast_id'] ) ? absint( $p['broadcast_id'] ) : 0;
		$res = SimpleVPBot_Model_Broadcast::cancel_broadcast( $bid );
		return $res;
	}

	/**
	 * Run broadcast queue worker in-process (for hosts where WP-Cron does not fire).
	 *
	 * @param array<string, mixed> $p Params: max_iterations optional (1–80, default 30).
	 * @return array{ok:bool, iterations?:int, message?:string}
	 */
	private static function op_broadcast_run_worker( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Cron_Broadcast' ) ) {
			return array( 'ok' => false, 'message' => 'no_cron' );
		}
		$max_iter = isset( $p['max_iterations'] ) ? absint( $p['max_iterations'] ) : 30;
		$max_iter = max( 1, min( 80, $max_iter ) );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		$deadline = microtime( true ) + 28.0;
		$i        = 0;
		while ( $i < $max_iter && microtime( true ) < $deadline ) {
			SimpleVPBot_Cron_Broadcast::run();
			++$i;
		}
		return array( 'ok' => true, 'iterations' => $i );
	}

	/**
	 * Allow Telegram-safe HTML subset for broadcast body.
	 *
	 * @param string $text Raw.
	 * @return string
	 */
	private static function broadcast_sanitize_html( $text ) {
		$allowed = array(
			'p'        => array(),
			'b'        => array(),
			'strong'   => array(),
			'i'        => array(),
			'em'       => array(),
			'u'        => array(),
			's'        => array(),
			'strike'   => array(),
			'del'      => array(),
			'code'     => array(),
			'pre'      => array(),
			'a'        => array( 'href' => array() ),
			'br'       => array(),
		);
		return wp_kses( (string) $text, $allowed );
	}

	/**
	 * Strip dangerous chars for Markdown; admin is trusted for entity syntax.
	 *
	 * @param string $text Raw.
	 * @return string
	 */
	private static function broadcast_sanitize_markdown( $text ) {
		return preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $text );
	}

	/**
	 * @param array<string, mixed> $post Params (discount fields).
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_discount_save( array $post ) {
		$id   = isset( $post['svpc_id'] ) ? (int) $post['svpc_id'] : 0;
		$code = SimpleVPBot_Model_Discount_Code::normalize_code( isset( $post['svpc_code'] ) ? (string) $post['svpc_code'] : '' );
		if ( '' === $code ) {
			return array( 'ok' => false, 'message' => 'empty_code' );
		}
		$type = sanitize_key( isset( $post['svpc_type'] ) ? (string) $post['svpc_type'] : 'percent' );
		if ( 'fixed_toman' !== $type ) {
			$type = 'percent';
		}
		$val = (float) str_replace( ',', '.', (string) ( $post['svpc_value'] ?? '0' ) );
		if ( $val < 0 ) {
			$val = 0.0;
		}
		if ( 'percent' === $type ) {
			$val = min( 100.0, $val );
		}
		$maxu     = isset( $post['svpc_max_uses'] ) ? trim( (string) $post['svpc_max_uses'] ) : '';
		$max_uses = ( '' === $maxu || ! is_numeric( $maxu ) ) ? null : max( 0, (int) $maxu );
		$vf       = isset( $post['svpc_valid_from'] ) ? trim( (string) $post['svpc_valid_from'] ) : '';
		$vu       = isset( $post['svpc_valid_until'] ) ? trim( (string) $post['svpc_valid_until'] ) : '';
		$mo       = isset( $post['svpc_min_order'] ) ? trim( (string) $post['svpc_min_order'] ) : '';
		$min_order = ( '' === $mo || ! is_numeric( $mo ) ) ? null : max( 0.0, (float) str_replace( ',', '.', $mo ) );
		$row      = array(
			'owner_svp_user_id'    => isset( $post['owner_svp_user_id'] ) ? max( 0, (int) $post['owner_svp_user_id'] ) : 0,
			'code'                 => $code,
			'active'               => ! empty( $post['svpc_active'] ) ? 1 : 0,
			'discount_type'        => $type,
			'discount_value'       => $val,
			'max_uses'             => $max_uses,
			'valid_from'           => '' !== $vf ? $vf : null,
			'valid_until'          => '' !== $vu ? $vu : null,
			'min_order_toman'      => $min_order,
			'allow_new_purchase'   => ! empty( $post['svpc_allow_new'] ) ? 1 : 0,
			'allow_renew_same'     => ! empty( $post['svpc_allow_renew'] ) ? 1 : 0,
			'allow_add_volume'     => ! empty( $post['svpc_allow_vol'] ) ? 1 : 0,
			'allow_add_user_slots' => ! empty( $post['svpc_allow_users'] ) ? 1 : 0,
		);
		if ( $id > 0 ) {
			SimpleVPBot_Model_Discount_Code::update( $id, array_diff_key( $row, array( 'code' => true ) ) );
		} else {
			SimpleVPBot_Model_Discount_Code::insert( $row );
		}
		return array( 'ok' => true );
	}

	/**
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool}
	 */
	private static function op_discount_delete( array $p ) {
		$id = isset( $p['svpc_delete_id'] ) ? (int) $p['svpc_delete_id'] : 0;
		if ( $id > 0 ) {
			SimpleVPBot_Model_Discount_Code::delete( $id );
		}
		return array( 'ok' => true );
	}

	/**
	 * @param array<string, mixed> $p Params svp_user_id, wp_user_id (0 to clear).
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_link_wp_user( array $p ) {
		$sid = (int) ( $p['svp_user_id'] ?? 0 );
		$wid = isset( $p['wp_user_id'] ) ? (int) $p['wp_user_id'] : -1;
		if ( $sid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_svp' );
		}
		if ( $wid < 0 ) {
			return array( 'ok' => false, 'message' => 'invalid_wp' );
		}
		$r = SimpleVPBot_Model_User::set_linked_wp_user( $sid, $wid > 0 ? $wid : null );
		if ( ! empty( $r['ok'] ) ) {
			self::log_rest_user( $sid, 'link_wp_user', array( 'wp_user_id' => $wid ) );
		}
		return $r;
	}

	/**
	 * Link one 3x-ui inbound client to a bot user (creates svp_services row).
	 *
	 * @param array<string, mixed> $p inbound_id, email, user_id, panel_id.
	 * @return array{ok:bool, message?:string, data?:mixed}
	 */
	private static function op_inbound_link( array $p ) {
		$iid   = isset( $p['inbound_id'] ) ? (int) $p['inbound_id'] : 0;
		$uid   = isset( $p['user_id'] ) ? (int) $p['user_id'] : 0;
		$uq    = isset( $p['user_query'] ) ? trim( (string) $p['user_query'] ) : '';
		$email = isset( $p['email'] ) ? sanitize_text_field( (string) $p['email'] ) : '';
		$pid   = isset( $p['panel_id'] ) ? (int) $p['panel_id'] : 1;
		if ( $pid < 0 ) {
			$pid = 0;
		}
		if ( $uid < 1 && '' !== $uq ) {
			$res = SimpleVPBot_Model_User::resolve_unique_for_admin_link( $uq );
			if ( empty( $res['ok'] ) ) {
				return array(
					'ok'     => false,
					'message'=> 'user_resolve_failed',
					'reason' => (string) ( $res['reason'] ?? 'not_found' ),
				);
			}
			$uid = (int) $res['user_id'];
		}
		if ( $iid < 1 || $uid < 1 || '' === $email ) {
			return array( 'ok' => false, 'message' => 'bad_params' );
		}
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$r = SimpleVPBot_Service_Admin_Ops::inbound_link( $iid, $email, $uid, $pid );
		if ( empty( $r['ok'] ) ) {
			return array( 'ok' => false, 'message' => (string) ( $r['message'] ?? 'err' ) );
		}
		if ( class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			SimpleVPBot_Service_Admin_Ops::configs_sync_inbounds_after_mutation( $pid > 0 ? $pid : 1, array( $iid ) );
		}
		if ( ! empty( $r['data'] ) && is_array( $r['data'] ) && ! empty( $r['data']['service_id'] ) ) {
			self::log_rest_user( $uid, 'inbound_link', array( 'service_id' => (int) $r['data']['service_id'], 'inbound_id' => $iid, 'email' => $email ) );
		} else {
			self::log_rest_user( $uid, 'inbound_link', array( 'inbound_id' => $iid, 'email' => $email ) );
		}
		return $r;
	}

	/**
	 * Auto-link all clients in an inbound using heuristics (comment/remark).
	 *
	 * @param array<string, mixed> $p inbound_id, panel_id.
	 * @return array{ok:bool, message?:string, data?:mixed}
	 */
	private static function op_inbound_autolink( array $p ) {
		$iid = isset( $p['inbound_id'] ) ? (int) $p['inbound_id'] : 0;
		$pid = isset( $p['panel_id'] ) ? (int) $p['panel_id'] : 1;
		if ( $pid < 0 ) {
			$pid = 0;
		}
		if ( $iid < 1 ) {
			return array( 'ok' => false, 'message' => 'bad_params' );
		}
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$r = SimpleVPBot_Service_Admin_Ops::inbound_autolink( $iid, $pid );
		if ( empty( $r['ok'] ) ) {
			return array( 'ok' => false, 'message' => (string) ( $r['message'] ?? 'err' ) );
		}
		if ( class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			SimpleVPBot_Service_Admin_Ops::configs_sync_inbounds_after_mutation( $pid > 0 ? $pid : 1, array( $iid ) );
		}
		return $r;
	}

	/**
	 * Soft-delete a bot service (deleted_at + FK cleanup). Does not call 3x-ui delClient.
	 *
	 * @param array<string, mixed> $p Params: service_id (svp_services.id).
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_service_delete( array $p ) {
		$sid = isset( $p['service_id'] ) ? (int) $p['service_id'] : 0;
		if ( $sid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_service' );
		}
		$row = SimpleVPBot_Model_Service::find( $sid );
		$uid = $row ? (int) $row->user_id : 0;
		$em  = $row ? (string) ( $row->email ?? '' ) : '';
		$ok  = SimpleVPBot_Model_Service::soft_delete( $sid );
		if ( $ok && $uid > 0 ) {
			self::log_rest_user(
				$uid,
				'service_soft_delete',
				array(
					'service_id' => $sid,
					'email'      => $em,
				)
			);
		}
		return $ok
			? array( 'ok' => true, 'message' => 'deleted' )
			: array( 'ok' => false, 'message' => 'not_found' );
	}

	/**
	 * Block / unblock bot user (REST).
	 *
	 * @param array<string, mixed> $p svp_user_id, user_status_action ban|unban.
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_user_status( array $p ) {
		$uid = (int) ( $p['svp_user_id'] ?? 0 );
		$act = sanitize_key( (string) ( $p['user_status_action'] ?? '' ) );
		if ( $uid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_user' );
		}
		$user = SimpleVPBot_Model_User::find( $uid );
		if ( ! $user ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		$st = (string) $user->status;
		if ( 'ban' === $act ) {
			if ( 'blocked' === $st ) {
				return array( 'ok' => true, 'message' => 'noop' );
			}
			SimpleVPBot_Model_User::update( $uid, array( 'status' => 'blocked' ) );
			self::log_rest_user( $uid, 'user_ban', array() );
			return array( 'ok' => true );
		}
		if ( 'unban' === $act ) {
			if ( 'blocked' !== $st ) {
				return array( 'ok' => false, 'message' => 'not_blocked' );
			}
			SimpleVPBot_Model_User::update(
				$uid,
				array(
					'status'       => 'approved',
					'approved_by'  => (string) wp_get_current_user()->user_login,
					'approved_at'  => current_time( 'mysql' ),
				)
			);
			self::log_rest_user( $uid, 'user_unban', array() );
			return array( 'ok' => true );
		}
		return array( 'ok' => false, 'message' => 'bad_action' );
	}

	/**
	 * Adjust wallet balance (toman) for a bot user.
	 *
	 * @param array<string, mixed> $p svp_user_id, delta (float, may be negative).
	 * @return array{ok:bool, message?:string, balance?:float}
	 */
	private static function op_user_balance_delta( array $p ) {
		$uid   = (int) ( $p['svp_user_id'] ?? 0 );
		$delta = isset( $p['delta'] ) ? (float) $p['delta'] : 0.0;
		if ( $uid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_user' );
		}
		if ( ! is_finite( $delta ) || abs( $delta ) > 1e12 ) {
			return array( 'ok' => false, 'message' => 'invalid_delta' );
		}
		$user = SimpleVPBot_Model_User::find( $uid );
		if ( ! $user ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		$cur = round( (float) ( $user->balance ?? 0 ), 2 );
		$new = round( $cur + $delta, 2 );
		if ( $new < 0 ) {
			return array( 'ok' => false, 'message' => 'insufficient_balance' );
		}
		SimpleVPBot_Model_User::update( $uid, array( 'balance' => $new ) );
		self::log_rest_user(
			$uid,
			'balance_delta',
			array(
				'delta'         => $delta,
				'balance_after' => $new,
			)
		);
		$notify = ! array_key_exists( 'notify', $p ) ? true : (bool) $p['notify'];
		if ( $notify ) {
			self::notify_user_wallet_delta( $user, $delta, $new );
		}
		return array( 'ok' => true, 'balance' => $new );
	}

	/**
	 * Tell the bot user their wallet changed (Telegram/Bale, best-effort).
	 *
	 * @param object $user          svp_users row (tg_user_id / bale_user_id).
	 * @param float  $delta         Applied delta (positive = credit).
	 * @param float  $new_balance   Balance after update.
	 * @return void
	 */
	private static function notify_user_wallet_delta( $user, $delta, $new_balance ) {
		if ( abs( $delta ) < 0.0000001 ) {
			return;
		}
		$d_abs = abs( (float) $delta );
		$n     = (float) $new_balance;
		$dec_a = ( abs( $d_abs - round( $d_abs ) ) < 0.001 ) ? 0 : 2;
		$dec_n = ( abs( $n - round( $n ) ) < 0.001 ) ? 0 : 2;
		$amt   = number_format( $d_abs, $dec_a, '.', ',' );
		$bal   = number_format( $n, $dec_n, '.', ',' );
		if ( $delta > 0 ) {
			$tpl = SimpleVPBot_Texts::get(
				'msg.dashboard_wallet_credit',
				"💰 به موجودی کیف پول شما {amount} تومان افزوده شد.\n➖➖➖➖➖➖➖➖\nمانده فعلی: {balance} تومان."
			);
		} else {
			$tpl = SimpleVPBot_Texts::get(
				'msg.dashboard_wallet_debit',
				"💰 از موجودی کیف پول شما {amount} تومان کسر شد.\n➖➖➖➖➖➖➖➖\nمانده فعلی: {balance} تومان."
			);
		}
		$body = SimpleVPBot_Texts::format(
			$tpl,
			array(
				'amount'  => $amt,
				'balance' => $bal,
			)
		);
		if ( ! empty( $user->tg_user_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( 'telegram', (int) $user->tg_user_id, $body );
		}
		if ( ! empty( $user->bale_user_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( 'bale', (int) $user->bale_user_id, $body );
		}
	}

	/**
	 * @param array<string, mixed> $p target_user_id, plan_id, volume_gb?, mode.
	 * @return array{ok:bool, message?:string, reason?:string, service_id?:int, transaction_id?:int}
	 */
	private static function op_user_create_service( array $p ) {
		$tuid = (int) ( $p['target_user_id'] ?? $p['svp_user_id'] ?? 0 );
		$pid  = (int) ( $p['plan_id'] ?? 0 );
		$mode = sanitize_key( (string) ( $p['mode'] ?? 'free' ) );
		$vol  = isset( $p['volume_gb'] ) ? (int) $p['volume_gb'] : null;
		if ( $tuid < 1 || $pid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		$res = SimpleVPBot_Admin_User_Ops::admin_create_service( $tuid, $pid, $vol, $mode );
		if ( empty( $res['ok'] ) ) {
			return array( 'ok' => false, 'reason' => (string) ( $res['reason'] ?? 'failed' ) );
		}
		self::log_rest_user(
			$tuid,
			'service_create',
			array(
				'plan_id'        => $pid,
				'mode'           => $mode,
				'volume_gb'      => $vol,
				'service_id'     => (int) ( $res['service_id'] ?? 0 ),
				'transaction_id' => (int) ( $res['transaction_id'] ?? 0 ),
			)
		);
		$new_sid = (int) ( $res['service_id'] ?? 0 );
		if ( $new_sid > 0 ) {
			self::configs_sync_after_service_panel_change( $new_sid );
		}
		return array(
			'ok'             => true,
			'service_id'     => $new_sid,
			'transaction_id' => (int) ( $res['transaction_id'] ?? 0 ),
		);
	}

	/**
	 * @param array<string, mixed> $p service_id, mode.
	 * @return array{ok:bool, reason?:string, transaction_id?:int}
	 */
	private static function op_user_renew_service( array $p ) {
		$sid  = (int) ( $p['service_id'] ?? 0 );
		$mode = sanitize_key( (string) ( $p['mode'] ?? 'free' ) );
		if ( $sid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_service' );
		}
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		$uid = (int) $svc->user_id;
		$res = SimpleVPBot_Admin_User_Ops::admin_renew_service( $sid, $mode );
		if ( empty( $res['ok'] ) ) {
			return array( 'ok' => false, 'reason' => (string) ( $res['reason'] ?? 'failed' ) );
		}
		self::log_rest_user( $uid, 'service_renew', array( 'service_id' => $sid, 'mode' => $mode ) );
		// Invoice mode only enqueues checkout; panel is unchanged until payment.
		if ( (int) ( $res['transaction_id'] ?? 0 ) < 1 ) {
			self::configs_sync_after_service_panel_change( $sid );
		}
		return array(
			'ok'             => true,
			'transaction_id' => (int) ( $res['transaction_id'] ?? 0 ),
		);
	}

	/**
	 * @param array<string, mixed> $p service_id, extra_gb, mode.
	 * @return array{ok:bool, reason?:string, transaction_id?:int}
	 */
	private static function op_user_add_volume( array $p ) {
		$sid  = (int) ( $p['service_id'] ?? 0 );
		$gb   = (int) ( $p['extra_gb'] ?? $p['volume_gb'] ?? 0 );
		$mode = sanitize_key( (string) ( $p['mode'] ?? 'free' ) );
		if ( $sid < 1 || $gb < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		$uid = (int) $svc->user_id;
		$res = SimpleVPBot_Admin_User_Ops::admin_add_volume( $sid, $gb, $mode );
		if ( empty( $res['ok'] ) ) {
			return array( 'ok' => false, 'reason' => (string) ( $res['reason'] ?? 'failed' ) );
		}
		self::log_rest_user( $uid, 'service_add_volume', array( 'service_id' => $sid, 'extra_gb' => $gb, 'mode' => $mode ) );
		if ( (int) ( $res['transaction_id'] ?? 0 ) < 1 ) {
			self::configs_sync_after_service_panel_change( $sid );
		}
		return array(
			'ok'             => true,
			'transaction_id' => (int) ( $res['transaction_id'] ?? 0 ),
		);
	}

	/**
	 * @param array<string, mixed> $p service_id, target (same semantics as legacy admin-ajax).
	 * @return array{ok:bool, message?:string, data?:mixed}
	 */
	private static function op_user_service_transfer( array $p ) {
		$sid = (int) ( $p['service_id'] ?? 0 );
		$tgt = isset( $p['target'] ) ? trim( (string) $p['target'] ) : '';
		if ( $sid < 1 || '' === $tgt ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		$from_uid = (int) $svc->user_id;
		$label    = (string) wp_get_current_user()->user_login;
		$res      = SimpleVPBot_Service_Admin_Ops::service_transfer( $sid, $tgt, $label );
		if ( empty( $res['ok'] ) ) {
			return array( 'ok' => false, 'message' => isset( $res['message'] ) ? (string) $res['message'] : 'failed' );
		}
		$data = isset( $res['data'] ) && is_array( $res['data'] ) ? $res['data'] : array();
		$to   = isset( $data['target_id'] ) ? (int) $data['target_id'] : 0;
		self::log_rest_user(
			$from_uid,
			'service_transfer_out',
			array(
				'service_id' => $sid,
				'target_id'  => $to,
				'target_raw' => $tgt,
			)
		);
		if ( $to > 0 && $to !== $from_uid ) {
			self::log_rest_user(
				$to,
				'service_transfer_in',
				array(
					'service_id'    => $sid,
					'previous_user' => $from_uid,
				)
			);
		}
		return array( 'ok' => true, 'data' => $data );
	}

	/**
	 * Manual svp_users row: either tg/bale id, or (admin only) reseller with dashboard_password + username for /dashboard login.
	 *
	 * @param array<string, mixed> $p Fields.
	 * @return array{ok:bool, message?:string, user_id?:int}
	 */
	private static function op_user_manual_create( array $p ) {
		$tg = isset( $p['tg_user_id'] ) ? (int) $p['tg_user_id'] : 0;
		$bl = isset( $p['bale_user_id'] ) ? (int) $p['bale_user_id'] : 0;
		$st = sanitize_key( (string) ( $p['status'] ?? 'pending' ) );
		$role = sanitize_key( (string) ( $p['role'] ?? 'user' ) );
		if ( ! in_array( $role, array( 'user', 'reseller' ), true ) ) {
			$role = 'user';
		}
		$invited_by = isset( $p['invited_by'] ) ? (int) $p['invited_by'] : 0;
		if ( $invited_by < 1 ) {
			$invited_by = null;
		}
		if ( ! in_array( $st, array( 'pending', 'approved', 'rejected', 'blocked' ), true ) ) {
			$st = 'pending';
		}

		$dash_pwd  = (string) ( $p['dashboard_password'] ?? '' );
		$uname_raw = sanitize_text_field( (string) ( $p['username'] ?? '' ) );
		$log       = sanitize_user( $uname_raw, true );

		if ( 'reseller' === $role && strlen( $dash_pwd ) >= 6 && '' !== $log ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return array( 'ok' => false, 'message' => 'forbidden' );
			}
			if ( username_exists( $log ) ) {
				return array( 'ok' => false, 'message' => 'username_exists' );
			}
			if ( $tg > 0 && SimpleVPBot_Model_User::find_by_telegram( $tg ) ) {
				return array( 'ok' => false, 'message' => 'tg_taken' );
			}
			if ( $bl > 0 && SimpleVPBot_Model_User::find_by_bale( $bl ) ) {
				return array( 'ok' => false, 'message' => 'bale_taken' );
			}
			$email = sanitize_email( (string) ( $p['email'] ?? '' ) );
			$wp_id = wp_create_user( $log, $dash_pwd, $email );
			if ( is_wp_error( $wp_id ) ) {
				return array( 'ok' => false, 'message' => (string) $wp_id->get_error_code() );
			}
			$wp_id = (int) $wp_id;
			$wpuser = new WP_User( $wp_id );
			$wpuser->set_role( 'subscriber' );

			$row = array(
				'tg_user_id'   => $tg > 0 ? $tg : null,
				'bale_user_id' => $bl > 0 ? $bl : null,
				'first_name'   => sanitize_text_field( (string) ( $p['first_name'] ?? '' ) ),
				'last_name'    => sanitize_text_field( (string) ( $p['last_name'] ?? '' ) ),
				'username'     => $log,
				'phone'        => sanitize_text_field( (string) ( $p['phone'] ?? '' ) ),
				'role'         => 'reseller',
				'balance'      => 0,
				'status'       => $st,
				'admin_mode'   => 0,
				'invited_by'   => $invited_by,
				'wp_user_id'   => $wp_id,
			);
			if ( 'approved' === $st ) {
				$row['approved_by'] = (string) wp_get_current_user()->user_login;
				$row['approved_at'] = current_time( 'mysql' );
			}
			$new_id = SimpleVPBot_Model_User::insert( $row );
			if ( $new_id < 1 ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( $wp_id );
				return array( 'ok' => false, 'message' => 'insert_failed' );
			}
			self::log_rest_user( $new_id, 'user_manual_create', array( 'dashboard_login' => true, 'wp_user_id' => $wp_id ) );
			return array( 'ok' => true, 'user_id' => $new_id );
		}

		if ( strlen( $dash_pwd ) >= 6 && '' !== $log && 'reseller' !== $role ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}

		if ( $tg < 1 && $bl < 1 ) {
			return array( 'ok' => false, 'message' => 'need_platform_id' );
		}
		if ( $tg > 0 && SimpleVPBot_Model_User::find_by_telegram( $tg ) ) {
			return array( 'ok' => false, 'message' => 'tg_taken' );
		}
		if ( $bl > 0 && SimpleVPBot_Model_User::find_by_bale( $bl ) ) {
			return array( 'ok' => false, 'message' => 'bale_taken' );
		}
		$wp_user_id = isset( $p['wp_user_id'] ) ? (int) $p['wp_user_id'] : 0;
		if ( $wp_user_id < 1 ) {
			$wp_user_id = null;
		}
		$row = array(
			'tg_user_id'   => $tg > 0 ? $tg : null,
			'bale_user_id' => $bl > 0 ? $bl : null,
			'first_name'   => sanitize_text_field( (string) ( $p['first_name'] ?? '' ) ),
			'last_name'    => sanitize_text_field( (string) ( $p['last_name'] ?? '' ) ),
			'username'     => sanitize_text_field( (string) ( $p['username'] ?? '' ) ),
			'phone'        => sanitize_text_field( (string) ( $p['phone'] ?? '' ) ),
			'role'         => $role,
			'balance'      => 0,
			'status'       => $st,
			'admin_mode'   => 0,
			'invited_by'   => $invited_by,
			'wp_user_id'   => $wp_user_id,
		);
		if ( 'approved' === $st ) {
			$row['approved_by'] = (string) wp_get_current_user()->user_login;
			$row['approved_at'] = current_time( 'mysql' );
		}
		$new_id = SimpleVPBot_Model_User::insert( $row );
		if ( $new_id < 1 ) {
			return array( 'ok' => false, 'message' => 'insert_failed' );
		}
		self::log_rest_user( $new_id, 'user_manual_create', array( 'tg_user_id' => $tg, 'bale_user_id' => $bl ) );
		return array( 'ok' => true, 'user_id' => $new_id );
	}

	/**
	 * Preview merge (admin dashboard).
	 *
	 * @param array<string, mixed> $p keep_id, drop_id.
	 * @return array{ok:bool, message?:string, data?:mixed}
	 */
	private static function op_user_merge_preview( array $p ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$keep = (int) ( $p['keep_id'] ?? 0 );
		$drop = (int) ( $p['drop_id'] ?? 0 );
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		return SimpleVPBot_Service_Admin_Ops::user_merge_preview( $keep, $drop );
	}

	/**
	 * Execute merge (admin dashboard).
	 *
	 * @param array<string, mixed> $p keep_id, drop_id, confirm.
	 * @return array{ok:bool, message?:string, data?:mixed}
	 */
	private static function op_user_merge( array $p ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$keep    = (int) ( $p['keep_id'] ?? 0 );
		$drop    = (int) ( $p['drop_id'] ?? 0 );
		$confirm = ! empty( $p['confirm'] );
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$res = SimpleVPBot_Service_Admin_Ops::user_merge( $keep, $drop, $confirm );
		if ( ! empty( $res['ok'] ) ) {
			self::log_rest_user(
				$keep,
				'user_merge',
				array(
					'drop_id' => $drop,
				)
			);
		}
		return $res;
	}

	/**
	 * @param array<string, mixed> $p svp_user_id, text, channel: both|telegram|bale.
	 * @return array{ok:bool, message?:string, sent?:int}
	 */
	private static function op_user_admin_message( array $p ) {
		$uid = (int) ( $p['svp_user_id'] ?? 0 );
		$txt = isset( $p['text'] ) ? sanitize_textarea_field( (string) $p['text'] ) : '';
		$txt = trim( preg_replace( '/\s+/u', ' ', $txt ) );
		$ch  = sanitize_key( (string) ( $p['channel'] ?? 'both' ) );
		if ( $uid < 1 || '' === $txt ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		if ( strlen( $txt ) > 4000 ) {
			$txt = substr( $txt, 0, 4000 );
		}
		$user = SimpleVPBot_Model_User::find( $uid );
		if ( ! $user ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		$sent = 0;
		if ( ( 'both' === $ch || 'telegram' === $ch ) && ! empty( $user->tg_user_id ) ) {
			$r = SimpleVPBot_Bot_Runtime::send_message( 'telegram', (int) $user->tg_user_id, $txt );
			if ( null !== $r ) {
				++$sent;
			}
		}
		if ( ( 'both' === $ch || 'bale' === $ch ) && ! empty( $user->bale_user_id ) ) {
			$r = SimpleVPBot_Bot_Runtime::send_message( 'bale', (int) $user->bale_user_id, $txt );
			if ( null !== $r ) {
				++$sent;
			}
		}
		if ( $sent < 1 ) {
			return array( 'ok' => false, 'message' => 'no_channel' );
		}
		self::log_rest_user( $uid, 'admin_message', array( 'channel' => $ch, 'length' => strlen( $txt ) ) );
		return array( 'ok' => true, 'sent' => $sent );
	}

	/**
	 * @param array<string, mixed> $p service_id + optional alert toggles.
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_service_alerts_patch( array $p ) {
		$sid = (int) ( $p['service_id'] ?? 0 );
		if ( $sid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_service' );
		}
		$row = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $row ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		$uid = (int) $row->user_id;
		$patch = array();
		foreach ( array( 'alerts_enabled', 'alerts_volume', 'alerts_expiry', 'alerts_users' ) as $k ) {
			if ( array_key_exists( $k, $p ) ) {
				$v           = $p[ $k ];
				$patch[ $k ] = ( 1 === (int) $v || true === $v || '1' === (string) $v ) ? 1 : 0;
			}
		}
		if ( empty( $patch ) ) {
			return array( 'ok' => false, 'message' => 'noop' );
		}
		SimpleVPBot_Model_Service::update( $sid, $patch );
		self::log_rest_user( $uid, 'service_alerts_patch', array_merge( array( 'service_id' => $sid ), $patch ) );
		return array( 'ok' => true );
	}

	/**
	 * @param array<string, mixed> $p service_id.
	 * @return array{ok:bool, reason?:string, limit_ip?:int, panel_enabled?:int|null}
	 */
	private static function op_service_panel_sync( array $p ) {
		$sid = (int) ( $p['service_id'] ?? 0 );
		if ( $sid < 1 || ! class_exists( 'SimpleVPBot_Service_Dashboard_Panel' ) ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		$uid = (int) $svc->user_id;
		$r   = SimpleVPBot_Service_Dashboard_Panel::xray_sync_meta( $sid, true );
		if ( empty( $r['ok'] ) ) {
			return array( 'ok' => false, 'reason' => (string) ( $r['reason'] ?? 'failed' ) );
		}
		self::log_rest_user( $uid, 'service_panel_sync', array( 'service_id' => $sid ) );
		return array(
			'ok'            => true,
			'limit_ip'      => (int) ( $r['limit_ip'] ?? 0 ),
			'panel_enabled' => isset( $r['panel_enabled'] ) ? $r['panel_enabled'] : null,
		);
	}

	/**
	 * @param array<string, mixed> $p service_id.
	 * @return array{ok:bool, reason?:string}
	 */
	private static function op_service_regen_key( array $p ) {
		$sid = (int) ( $p['service_id'] ?? 0 );
		if ( $sid < 1 || ! class_exists( 'SimpleVPBot_Service_Dashboard_Panel' ) ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		$r = SimpleVPBot_Service_Dashboard_Panel::xray_regenerate_key( $sid );
		if ( empty( $r['ok'] ) ) {
			return array( 'ok' => false, 'reason' => (string) ( $r['reason'] ?? 'failed' ) );
		}
		self::log_rest_user( (int) $svc->user_id, 'service_regen_key', array( 'service_id' => $sid ) );
		return array( 'ok' => true );
	}

	/**
	 * @param array<string, mixed> $p service_id.
	 * @return array{ok:bool, reason?:string}
	 */
	private static function op_service_panel_refresh( array $p ) {
		$sid = (int) ( $p['service_id'] ?? 0 );
		if ( $sid < 1 || ! class_exists( 'SimpleVPBot_Service_Dashboard_Panel' ) ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		$r = SimpleVPBot_Service_Dashboard_Panel::xray_refresh_inbound( $sid );
		if ( empty( $r['ok'] ) ) {
			return array( 'ok' => false, 'reason' => (string) ( $r['reason'] ?? 'failed' ) );
		}
		self::log_rest_user( (int) $svc->user_id, 'service_panel_refresh', array( 'service_id' => $sid ) );
		return array( 'ok' => true );
	}

	/**
	 * @param array<string, mixed> $p service_id.
	 * @return array{ok:bool, reason?:string}
	 */
	private static function op_service_panel_delete_client( array $p ) {
		$sid = (int) ( $p['service_id'] ?? 0 );
		if ( $sid < 1 || ! class_exists( 'SimpleVPBot_Service_Dashboard_Panel' ) ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		$uid = (int) $svc->user_id;
		$r   = SimpleVPBot_Service_Dashboard_Panel::xray_delete_panel_client( $sid );
		if ( empty( $r['ok'] ) ) {
			return array( 'ok' => false, 'reason' => (string) ( $r['reason'] ?? 'failed' ) );
		}
		self::log_rest_user( $uid, 'service_panel_delete_client', array( 'service_id' => $sid ) );
		return array( 'ok' => true );
	}

	/**
	 * @param array<string, mixed> $p service_id, extra_users (1..50).
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_user_service_add_slots( array $p ) {
		$sid = (int) ( $p['service_id'] ?? 0 );
		$add = (int) ( $p['extra_users'] ?? $p['slots'] ?? 0 );
		if ( $sid < 1 || $add < 1 || ! class_exists( 'SimpleVPBot_Service_Renew' ) ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		$r = SimpleVPBot_Service_Renew::apply_add_user_slots_after_payment( $sid, $add );
		$ok = ! empty( $r['ok'] );
		if ( $ok ) {
			$svc = SimpleVPBot_Model_Service::find( $sid );
			if ( $svc ) {
				self::log_rest_user( (int) $svc->user_id, 'service_add_user_slots', array( 'service_id' => $sid, 'extra_users' => $add ) );
			}
		}
		if ( ! $ok ) {
			return array(
				'ok'      => false,
				'message' => (string) ( $r['message'] ?? 'failed' ),
			);
		}
		return array( 'ok' => true );
	}

	/**
	 * @param array<string, mixed> $p service_id, limit_ip.
	 * @return array{ok:bool, reason?:string}
	 */
	private static function op_service_set_limit_ip( array $p ) {
		$sid = (int) ( $p['service_id'] ?? 0 );
		$lip = (int) ( $p['limit_ip'] ?? 0 );
		if ( $sid < 1 || $lip < 1 || ! class_exists( 'SimpleVPBot_Service_Dashboard_Panel' ) ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		$r = SimpleVPBot_Service_Dashboard_Panel::xray_set_limit_ip( $sid, $lip );
		if ( empty( $r['ok'] ) ) {
			return array( 'ok' => false, 'reason' => (string) ( $r['reason'] ?? 'failed' ) );
		}
		self::log_rest_user( (int) $svc->user_id, 'service_set_limit_ip', array( 'service_id' => $sid, 'limit_ip' => $lip ) );
		return array( 'ok' => true );
	}

	/**
	 * @param array<string, mixed> $p panel_id, inbound_id, email, enable (0|1).
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_configs_client_toggle_enable( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$pid = (int) ( $p['panel_id'] ?? 0 );
		$iid = (int) ( $p['inbound_id'] ?? 0 );
		$em  = isset( $p['email'] ) ? sanitize_text_field( (string) $p['email'] ) : '';
		$en  = ! empty( $p['enable'] );
		$r   = SimpleVPBot_Service_Admin_Ops::configs_panel_client_toggle_enable( $pid, $iid, $em, $en ? 1 : 0 );
		return $r;
	}

	/**
	 * @param array<string, mixed> $p panel_id, inbound_id, email.
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_configs_client_reset_traffic( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$pid = (int) ( $p['panel_id'] ?? 0 );
		$iid = (int) ( $p['inbound_id'] ?? 0 );
		$em  = isset( $p['email'] ) ? sanitize_text_field( (string) $p['email'] ) : '';
		return SimpleVPBot_Service_Admin_Ops::configs_panel_client_reset_traffic( $pid, $iid, $em );
	}

	/**
	 * @param array<string, mixed> $p panel_id, inbound_id, email, linked_service_id.
	 * @return array{ok:bool, message?:string, reason?:string}
	 */
	private static function op_configs_client_delete( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$pid = (int) ( $p['panel_id'] ?? 0 );
		$iid = (int) ( $p['inbound_id'] ?? 0 );
		$em  = isset( $p['email'] ) ? sanitize_text_field( (string) $p['email'] ) : '';
		$ls  = (int) ( $p['linked_service_id'] ?? 0 );
		$r   = SimpleVPBot_Service_Admin_Ops::configs_panel_client_delete( $pid, $iid, $em, $ls );
		if ( ! empty( $r['ok'] ) && $ls > 0 ) {
			$svc = SimpleVPBot_Model_Service::find_any( $ls );
			if ( $svc ) {
				self::log_rest_user( (int) $svc->user_id, 'configs_client_delete', array( 'service_id' => $ls, 'inbound_id' => $iid, 'email' => $em ) );
			}
		}
		return $r;
	}

	/**
	 * @param array<string, mixed> $p panel_id, confirm_count.
	 * @return array{ok:bool, message?:string, data?:mixed}
	 */
	private static function op_configs_delete_expired_linked( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$pid    = (int) ( $p['panel_id'] ?? 0 );
		$expect = (int) ( $p['confirm_count'] ?? -1 );
		return SimpleVPBot_Service_Admin_Ops::configs_delete_expired_linked_batch( $pid, $expect );
	}

	/**
	 * @param array<string, mixed> $p panel_id, inbound_id, email; optional expiry_ms, total_gb, client_remark (omit to skip).
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_configs_panel_client_patch( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$pid = (int) ( $p['panel_id'] ?? 0 );
		$iid = (int) ( $p['inbound_id'] ?? 0 );
		$em  = isset( $p['email'] ) ? sanitize_text_field( (string) $p['email'] ) : '';
		$patch = array();
		if ( array_key_exists( 'expiry_ms', $p ) ) {
			$patch['expiry_ms'] = (int) $p['expiry_ms'];
		}
		if ( array_key_exists( 'total_gb', $p ) ) {
			$patch['total_gb'] = (int) $p['total_gb'];
		}
		if ( array_key_exists( 'client_remark', $p ) ) {
			$patch['client_remark'] = sanitize_text_field( (string) $p['client_remark'] );
		}
		if ( array_key_exists( 'limit_ip', $p ) ) {
			$patch['limit_ip'] = max( 0, (int) $p['limit_ip'] );
		}
		if ( array_key_exists( 'client_comment', $p ) ) {
			$patch['client_comment'] = sanitize_textarea_field( (string) $p['client_comment'] );
		}
		if ( array_key_exists( 'start_after_first_use', $p ) ) {
			$patch['start_after_first_use'] = ! empty( $p['start_after_first_use'] );
		}
		if ( empty( $patch ) ) {
			return array( 'ok' => false, 'message' => 'no_patch_fields' );
		}
		return SimpleVPBot_Service_Admin_Ops::configs_panel_client_patch( $pid, $iid, $em, $patch );
	}

	/**
	 * @param array<string, mixed> $p panel_id, batch_op (reset_traffic|set_enable), items: [{ inbound_id, email, enable? }].
	 * @return array{ok:bool, message?:string, data?:mixed}
	 */
	private static function op_configs_clients_batch( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$pid = (int) ( $p['panel_id'] ?? 0 );
		$op  = sanitize_key( (string) ( $p['batch_op'] ?? '' ) );
		$raw = isset( $p['items'] ) && is_array( $p['items'] ) ? $p['items'] : array();
		$items = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$it = array(
				'inbound_id' => (int) ( $row['inbound_id'] ?? 0 ),
				'email'      => sanitize_text_field( (string) ( $row['email'] ?? '' ) ),
			);
			if ( array_key_exists( 'enable', $row ) ) {
				$it['enable'] = ! empty( $row['enable'] ) ? 1 : 0;
			}
			$items[] = $it;
		}
		return SimpleVPBot_Service_Admin_Ops::configs_clients_batch( $pid, $op, $items );
	}

	/**
	 * @param array<string, mixed> $p panel_id, plan_id, items: [{ linked_service_id, inbound_id, email }].
	 * @return array{ok:bool, message?:string, data?:mixed}
	 */
	private static function op_configs_assign_plan( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$pid  = (int) ( $p['panel_id'] ?? 0 );
		$plid = (int) ( $p['plan_id'] ?? 0 );
		$raw  = isset( $p['items'] ) && is_array( $p['items'] ) ? $p['items'] : array();
		$items = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$items[] = array(
				'linked_service_id' => (int) ( $row['linked_service_id'] ?? 0 ),
				'inbound_id'        => (int) ( $row['inbound_id'] ?? 0 ),
				'email'             => sanitize_text_field( (string) ( $row['email'] ?? '' ) ),
			);
		}
		$r = SimpleVPBot_Service_Admin_Ops::configs_assign_plan( $pid, $plid, $items );
		if ( isset( $r['data']['succeeded'] ) && (int) $r['data']['succeeded'] > 0 ) {
			foreach ( $items as $row ) {
				$sid = (int) ( $row['linked_service_id'] ?? 0 );
				if ( $sid < 1 ) {
					continue;
				}
				$svc = SimpleVPBot_Model_Service::find_any( $sid );
				if ( $svc ) {
					self::log_rest_user(
						(int) $svc->user_id,
						'configs_assign_plan',
						array(
							'service_id' => $sid,
							'plan_id'    => $plid,
						)
					);
				}
			}
		}
		return $r;
	}

	/**
	 * @param array<string, mixed> $p service_id|service_ids[], target_panel_id, target_plan_id?.
	 * @return array{ok:bool, message?:string, data?:mixed}
	 */
	private static function op_service_panel_transfer( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Service_Panel_Transfer' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$tpid = (int) ( $p['target_panel_id'] ?? 0 );
		$tpln = (int) ( $p['target_plan_id'] ?? 0 );
		if ( $tpid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_target_panel' );
		}
		$service_ids = array();
		if ( isset( $p['service_ids'] ) && is_array( $p['service_ids'] ) ) {
			foreach ( $p['service_ids'] as $x ) {
				$n = (int) $x;
				if ( $n > 0 ) {
					$service_ids[] = $n;
				}
			}
		}
		$single = (int) ( $p['service_id'] ?? 0 );
		if ( $single > 0 ) {
			$service_ids[] = $single;
		}
		$service_ids = array_values( array_unique( $service_ids ) );
		$service_ids = array_slice( $service_ids, 0, 20 );
		if ( empty( $service_ids ) ) {
			return array( 'ok' => false, 'message' => 'empty_items' );
		}
		$failed = array();
		$okn    = 0;
		foreach ( $service_ids as $sid ) {
			$r = SimpleVPBot_Service_Panel_Transfer::transfer_service( $sid, $tpid, $tpln, (string) wp_get_current_user()->user_login );
			if ( ! empty( $r['ok'] ) ) {
				++$okn;
				$svc = SimpleVPBot_Model_Service::find_any( $sid );
				if ( $svc ) {
					self::log_rest_user(
						(int) $svc->user_id,
						'service_panel_transfer',
						array(
							'service_id'       => $sid,
							'target_panel_id'  => $tpid,
							'target_plan_id'   => $tpln,
						)
					);
				}
			} else {
				$failed[] = array(
					'service_id' => $sid,
					'reason'     => (string) ( $r['reason'] ?? 'failed' ),
					'message'    => (string) ( $r['message'] ?? '' ),
				);
			}
		}
		return array(
			'ok'      => empty( $failed ),
			'message' => empty( $failed ) ? 'ok' : 'partial',
			'data'    => array(
				'succeeded' => $okn,
				'failed'    => $failed,
			),
		);
	}

	/**
	 * Resolve user IDs for bulk ops (admin only).
	 *
	 * @param array<string, mixed> $p scope, user_ids?.
	 * @return array{ok:bool, ids?:array<int,int>, message?:string}
	 */
	private static function users_bulk_resolve_user_ids( array $p ) {
		global $wpdb;
		$scope = sanitize_key( (string) ( $p['scope'] ?? 'all_approved' ) );
		$t     = SimpleVPBot_Model_User::table();
		if ( 'custom_ids' === $scope ) {
			$raw = isset( $p['user_ids'] ) && is_array( $p['user_ids'] ) ? $p['user_ids'] : array();
			$ids = array();
			foreach ( $raw as $x ) {
				$n = (int) $x;
				if ( $n > 0 ) {
					$ids[] = $n;
				}
			}
			$ids = array_values( array_unique( $ids ) );
			if ( count( $ids ) > 500 ) {
				return array( 'ok' => false, 'message' => 'too_many_users' );
			}
			return array( 'ok' => true, 'ids' => $ids );
		}
		if ( 'all_approved' === $scope ) {
			$rows = $wpdb->get_col( "SELECT id FROM {$t} WHERE status = 'approved' AND role <> 'reseller' ORDER BY id ASC LIMIT 500" ); // phpcs:ignore
			return array( 'ok' => true, 'ids' => array_map( 'intval', (array) $rows ) );
		}
		if ( 'approved_with_active_service' === $scope ) {
			$s = SimpleVPBot_Model_Service::table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = "SELECT DISTINCT u.id FROM {$t} u INNER JOIN {$s} s ON s.user_id = u.id AND s.deleted_at IS NULL AND (s.expires_at IS NULL OR s.expires_at > UTC_TIMESTAMP()) WHERE u.status = 'approved' AND u.role <> 'reseller' ORDER BY u.id ASC LIMIT 500";
			$rows = $wpdb->get_col( $sql );
			return array( 'ok' => true, 'ids' => array_map( 'intval', (array) $rows ) );
		}
		return array( 'ok' => false, 'message' => 'bad_scope' );
	}

	/**
	 * Bulk wallet delta per user.
	 *
	 * @param array<string, mixed> $p scope, delta, dry_run?, notify? (default true).
	 * @return array{ok:bool, message?:string, data?:array<string,mixed>}
	 */
	private static function op_users_bulk_wallet( array $p ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$res_ids = self::users_bulk_resolve_user_ids( $p );
		if ( empty( $res_ids['ok'] ) ) {
			return array( 'ok' => false, 'message' => (string) ( $res_ids['message'] ?? 'bad_scope' ) );
		}
		$ids     = $res_ids['ids'];
		$delta   = isset( $p['delta'] ) ? (float) $p['delta'] : 0.0;
		$dry    = ! empty( $p['dry_run'] );
		$notify = array_key_exists( 'notify', $p )
			? (bool) $p['notify']
			: ! (bool) SimpleVPBot_Settings::get( 'suppress_bulk_user_notifications', false );
		if ( ! is_finite( $delta ) || abs( $delta ) > 1e12 ) {
			return array( 'ok' => false, 'message' => 'invalid_delta' );
		}
		if ( $dry ) {
			return array(
				'ok'   => true,
				'data' => array(
					'dry_run'       => true,
					'user_count'    => count( $ids ),
					'sample_user_ids' => array_slice( $ids, 0, 10 ),
				),
			);
		}
		$jid = SimpleVPBot_Model_Users_Bulk_Job::insert_job(
			array(
				'operation'      => 'wallet',
				'scope'          => sanitize_key( (string) ( $p['scope'] ?? 'all_approved' ) ),
				'payload_json'   => wp_json_encode(
					array(
						'delta'  => $delta,
						'notify' => $notify ? 1 : 0,
					),
					JSON_UNESCAPED_UNICODE
				),
				'status'         => 'pending',
				'created_by_wp'  => get_current_user_id(),
			)
		);
		SimpleVPBot_Model_Users_Bulk_Job::enqueue_users( $jid, $ids );
		return array(
			'ok'   => true,
			'data' => array(
				'job_id'   => $jid,
				'queued'   => count( $ids ),
			),
		);
	}

	/**
	 * Bulk add volume (free mode) on all active services per user in scope.
	 *
	 * @param array<string, mixed> $p scope, extra_gb, dry_run?, max_services? (default 200).
	 * @return array{ok:bool, message?:string, data?:array<string,mixed>}
	 */
	private static function op_users_bulk_volume( array $p ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$res_ids = self::users_bulk_resolve_user_ids( $p );
		if ( empty( $res_ids['ok'] ) ) {
			return array( 'ok' => false, 'message' => (string) ( $res_ids['message'] ?? 'bad_scope' ) );
		}
		$gb        = max( 1, (int) ( $p['extra_gb'] ?? $p['volume_gb'] ?? 0 ) );
		$dry       = ! empty( $p['dry_run'] );
		$max_svcs  = max( 1, min( 500, (int) ( $p['max_services'] ?? 200 ) ) );
		$user_ids  = $res_ids['ids'];
		if ( $gb < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_gb' );
		}
		global $wpdb;
		$s_tbl = SimpleVPBot_Model_Service::table();
		$in_ids = implode( ',', array_map( 'intval', $user_ids ) );
		if ( '' === $in_ids ) {
			return array( 'ok' => false, 'message' => 'empty_scope' );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$svc_rows = $wpdb->get_results( "SELECT id FROM {$s_tbl} WHERE deleted_at IS NULL AND user_id IN ({$in_ids}) AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP()) ORDER BY id ASC LIMIT {$max_svcs}" );
		$svc_ids  = array_map( 'intval', wp_list_pluck( $svc_rows, 'id' ) );
		if ( $dry ) {
			return array(
				'ok'   => true,
				'data' => array(
					'dry_run'        => true,
					'user_count'     => count( $user_ids ),
					'sample_user_ids' => array_slice( $user_ids, 0, 15 ),
				),
			);
		}
		$jid = SimpleVPBot_Model_Users_Bulk_Job::insert_job(
			array(
				'operation'      => 'volume',
				'scope'          => sanitize_key( (string) ( $p['scope'] ?? 'all_approved' ) ),
				'payload_json'   => wp_json_encode( array( 'extra_gb' => $gb ), JSON_UNESCAPED_UNICODE ),
				'status'         => 'pending',
				'created_by_wp'  => get_current_user_id(),
			)
		);
		SimpleVPBot_Model_Users_Bulk_Job::enqueue_users( $jid, $user_ids );
		return array(
			'ok'   => true,
			'data' => array(
				'job_id'            => $jid,
				'queued'            => count( $user_ids ),
			),
		);
	}

	/**
	 * Bulk extend active services by N days (free).
	 *
	 * @param array<string, mixed> $p scope, days, dry_run?, max_services?.
	 * @return array{ok:bool, message?:string, data?:array<string,mixed>}
	 */
	private static function op_users_bulk_extend( array $p ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$res_ids = self::users_bulk_resolve_user_ids( $p );
		if ( empty( $res_ids['ok'] ) ) {
			return array( 'ok' => false, 'message' => (string) ( $res_ids['message'] ?? 'bad_scope' ) );
		}
		$days      = max( 1, min( 3650, (int) ( $p['days'] ?? 0 ) ) );
		$dry       = ! empty( $p['dry_run'] );
		$max_svcs  = max( 1, min( 500, (int) ( $p['max_services'] ?? 100 ) ) );
		$user_ids  = $res_ids['ids'];
		if ( $days < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_days' );
		}
		global $wpdb;
		$s_tbl  = SimpleVPBot_Model_Service::table();
		$in_ids = implode( ',', array_map( 'intval', $user_ids ) );
		if ( '' === $in_ids ) {
			return array( 'ok' => false, 'message' => 'empty_scope' );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$svc_rows = $wpdb->get_results( "SELECT id FROM {$s_tbl} WHERE deleted_at IS NULL AND user_id IN ({$in_ids}) AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP()) ORDER BY id ASC LIMIT {$max_svcs}" );
		$svc_ids  = array_map( 'intval', wp_list_pluck( $svc_rows, 'id' ) );
		if ( $dry ) {
			return array(
				'ok'   => true,
				'data' => array(
					'dry_run'       => true,
					'user_count'    => count( $user_ids ),
					'days'          => $days,
				),
			);
		}
		$jid = SimpleVPBot_Model_Users_Bulk_Job::insert_job(
			array(
				'operation'      => 'extend',
				'scope'          => sanitize_key( (string) ( $p['scope'] ?? 'all_approved' ) ),
				'payload_json'   => wp_json_encode( array( 'days' => $days ), JSON_UNESCAPED_UNICODE ),
				'status'         => 'pending',
				'created_by_wp'  => get_current_user_id(),
			)
		);
		SimpleVPBot_Model_Users_Bulk_Job::enqueue_users( $jid, $user_ids );
		return array(
			'ok'   => true,
			'data' => array(
				'job_id'            => $jid,
				'queued'            => count( $user_ids ),
			),
		);
	}

	/**
	 * Bulk patch service alerts on active services in scope.
	 *
	 * @param array<string, mixed> $p scope + alert fields + dry_run + max_services.
	 * @return array{ok:bool, message?:string, data?:array<string,mixed>}
	 */
	private static function op_users_bulk_alerts( array $p ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$res_ids = self::users_bulk_resolve_user_ids( $p );
		if ( empty( $res_ids['ok'] ) ) {
			return array( 'ok' => false, 'message' => (string) ( $res_ids['message'] ?? 'bad_scope' ) );
		}
		$patch = array();
		foreach ( array( 'alerts_enabled', 'alerts_volume', 'alerts_expiry', 'alerts_users' ) as $k ) {
			if ( array_key_exists( $k, $p ) ) {
				$patch[ $k ] = ( 1 === (int) $p[ $k ] || true === $p[ $k ] || '1' === (string) $p[ $k ] ) ? 1 : 0;
			}
		}
		if ( empty( $patch ) ) {
			return array( 'ok' => false, 'message' => 'noop' );
		}
		$dry      = ! empty( $p['dry_run'] );
		$max_svcs = max( 1, min( 500, (int) ( $p['max_services'] ?? 200 ) ) );
		$user_ids = $res_ids['ids'];
		global $wpdb;
		$s_tbl  = SimpleVPBot_Model_Service::table();
		$in_ids = implode( ',', array_map( 'intval', $user_ids ) );
		if ( '' === $in_ids ) {
			return array( 'ok' => false, 'message' => 'empty_scope' );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$svc_rows = $wpdb->get_results( "SELECT id, user_id FROM {$s_tbl} WHERE deleted_at IS NULL AND user_id IN ({$in_ids}) ORDER BY id ASC LIMIT {$max_svcs}" );
		if ( $dry ) {
			return array(
				'ok'   => true,
				'data' => array(
					'dry_run'        => true,
					'service_count'  => count( $svc_rows ),
					'patch'          => $patch,
				),
			);
		}
		$jid = SimpleVPBot_Model_Users_Bulk_Job::insert_job(
			array(
				'operation'      => 'alerts',
				'scope'          => sanitize_key( (string) ( $p['scope'] ?? 'all_approved' ) ),
				'payload_json'   => wp_json_encode( $patch, JSON_UNESCAPED_UNICODE ),
				'status'         => 'pending',
				'created_by_wp'  => get_current_user_id(),
			)
		);
		SimpleVPBot_Model_Users_Bulk_Job::enqueue_users( $jid, $user_ids );
		return array(
			'ok'   => true,
			'data' => array(
				'job_id'  => $jid,
				'queued'  => count( $user_ids ),
			),
		);
	}

	private static function op_users_bulk_run_worker( array $p ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$max_iter = isset( $p['max_iterations'] ) ? absint( $p['max_iterations'] ) : 20;
		$max_iter = max( 1, min( 80, $max_iter ) );
		$i = 0;
		while ( $i < $max_iter ) {
			SimpleVPBot_Cron_Users_Bulk::run();
			++$i;
		}
		return array( 'ok' => true, 'iterations' => $i );
	}

	private static function op_users_bulk_job_cancel( array $p ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$jid = (int) ( $p['job_id'] ?? 0 );
		if ( $jid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		global $wpdb;
		$tj = SimpleVPBot_Model_Users_Bulk_Job::table();
		$ti = SimpleVPBot_Model_Users_Bulk_Job::items_table();
		$wpdb->update( $tj, array( 'status' => 'cancelled', 'finished_at' => current_time( 'mysql', true ) ), array( 'id' => $jid ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$ti} SET status = 'failed', last_error = 'cancelled_by_admin' WHERE job_id = %d AND status IN ('pending','processing')", $jid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array( 'ok' => true );
	}

	private static function op_users_bulk_job_resume( array $p ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$jid = (int) ( $p['job_id'] ?? 0 );
		if ( $jid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		global $wpdb;
		$tj = SimpleVPBot_Model_Users_Bulk_Job::table();
		$ti = SimpleVPBot_Model_Users_Bulk_Job::items_table();
		$wpdb->update( $tj, array( 'status' => 'processing', 'finished_at' => null ), array( 'id' => $jid ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$ti} SET status = 'pending', last_error = NULL WHERE job_id = %d AND status = 'failed' AND last_error = 'cancelled_by_admin'", $jid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array( 'ok' => true );
	}

	/**
	 * Create WordPress user and link to reseller svp_users row (or create reseller row).
	 *
	 * @param array<string, mixed> $p wp_username, wp_password, email?, svp_user_id? (0 = create new reseller), name fields.
	 * @return array{ok:bool, message?:string, user_id?:int, wp_user_id?:int}
	 */
	private static function op_reseller_wp_provision( array $p ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$log      = sanitize_user( (string) ( $p['wp_username'] ?? '' ), true );
		$pwd      = (string) ( $p['wp_password'] ?? '' );
		$email    = sanitize_email( (string) ( $p['email'] ?? '' ) );
		$svp_ex   = (int) ( $p['svp_user_id'] ?? 0 );
		if ( '' === $log || strlen( $pwd ) < 6 ) {
			return array( 'ok' => false, 'message' => 'bad_credentials' );
		}
		if ( username_exists( $log ) ) {
			return array( 'ok' => false, 'message' => 'username_exists' );
		}
		$wp_id = wp_create_user( $log, $pwd, $email );
		if ( is_wp_error( $wp_id ) ) {
			return array( 'ok' => false, 'message' => (string) $wp_id->get_error_code() );
		}
		$wp_id = (int) $wp_id;
		$user  = new WP_User( $wp_id );
		$user->set_role( 'subscriber' );

		if ( $svp_ex > 0 ) {
			$row = SimpleVPBot_Model_User::find( $svp_ex );
			if ( ! $row || ! SimpleVPBot_Model_User::is_reseller_row( $row ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( $wp_id );
				return array( 'ok' => false, 'message' => 'not_reseller_row' );
			}
			SimpleVPBot_Model_User::set_linked_wp_user( $svp_ex, $wp_id );
			self::log_rest_user( $svp_ex, 'reseller_wp_provision', array( 'wp_user_id' => $wp_id ) );
			return array( 'ok' => true, 'user_id' => $svp_ex, 'wp_user_id' => $wp_id );
		}

		$new_id = SimpleVPBot_Model_User::insert(
			array(
				'tg_user_id'   => null,
				'bale_user_id' => null,
				'first_name'   => sanitize_text_field( (string) ( $p['first_name'] ?? '' ) ),
				'last_name'    => sanitize_text_field( (string) ( $p['last_name'] ?? '' ) ),
				'username'     => $log,
				'phone'        => sanitize_text_field( (string) ( $p['phone'] ?? '' ) ),
				'role'         => 'reseller',
				'balance'      => 0,
				'status'       => 'approved',
				'admin_mode'   => 0,
				'invited_by'   => null,
				'wp_user_id'   => $wp_id,
				'approved_by'  => (string) wp_get_current_user()->user_login,
				'approved_at'  => current_time( 'mysql' ),
			)
		);
		if ( $new_id < 1 ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $wp_id );
			return array( 'ok' => false, 'message' => 'insert_failed' );
		}
		self::log_rest_user( $new_id, 'reseller_wp_provision', array( 'wp_user_id' => $wp_id, 'new' => true ) );
		return array( 'ok' => true, 'user_id' => $new_id, 'wp_user_id' => $wp_id );
	}

	/**
	 * Save per-reseller per-panel unit price (toman per GB).
	 *
	 * @param array<string, mixed> $p reseller_svp_user_id, rows: [{panel_id, price_per_gb}].
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_reseller_panel_prices_save( array $p ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$rid = (int) ( $p['reseller_svp_user_id'] ?? 0 );
		if ( $rid < 1 || ! class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' ) ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		$row = SimpleVPBot_Model_User::find( $rid );
		if ( ! $row || ! SimpleVPBot_Model_User::is_reseller_row( $row ) ) {
			return array( 'ok' => false, 'message' => 'not_reseller' );
		}
		$raw = isset( $p['rows'] ) && is_array( $p['rows'] ) ? $p['rows'] : array();
		SimpleVPBot_Model_Reseller_Panel_Price::replace_all_for_reseller( $rid, $raw );
		self::log_rest_user( $rid, 'reseller_panel_prices_save', array( 'count' => count( $raw ) ) );
		return array( 'ok' => true );
	}

	/**
	 * Save per-reseller permission map.
	 *
	 * @param array<string,mixed> $p reseller_svp_user_id, permissions.
	 * @return array{ok:bool,message?:string}
	 */
	private static function op_reseller_permissions_save( array $p ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$rid = (int) ( $p['reseller_svp_user_id'] ?? 0 );
		if ( $rid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		$row = SimpleVPBot_Model_User::find( $rid );
		if ( ! $row || ! SimpleVPBot_Model_User::is_reseller_row( $row ) ) {
			return array( 'ok' => false, 'message' => 'not_reseller' );
		}
		$permissions = isset( $p['permissions'] ) && is_array( $p['permissions'] ) ? $p['permissions'] : array();
		SimpleVPBot_Model_User::set_reseller_permissions( $rid, $permissions );
		self::log_rest_user( $rid, 'reseller_permissions_save', array( 'keys' => array_keys( $permissions ) ) );
		return array( 'ok' => true );
	}

	/**
	 * Store optional Telegram/Bale tokens + brand name for reseller bot profile.
	 *
	 * @param array<string, mixed> $p reseller_svp_user_id (admin), telegram_token, bale_token, brand_name?; reseller may omit id (uses self via REST injection).
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_reseller_bot_tokens_save( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		if ( current_user_can( 'manage_options' ) ) {
			$rid = (int) ( $p['reseller_svp_user_id'] ?? 0 );
			if ( $rid < 1 ) {
				return array( 'ok' => false, 'message' => 'invalid_reseller' );
			}
			$row = SimpleVPBot_Model_User::find( $rid );
			if ( ! $row || ! SimpleVPBot_Model_User::is_reseller_row( $row ) ) {
				return array( 'ok' => false, 'message' => 'not_reseller' );
			}
		} else {
			$wp = SimpleVPBot_Model_User::find_by_wp_user( get_current_user_id() );
			if ( ! $wp || ! SimpleVPBot_Model_User::is_reseller_row( $wp ) ) {
				return array( 'ok' => false, 'message' => 'forbidden' );
			}
			$rid = (int) $wp->id;
		}
		$tg = (string) ( $p['telegram_token'] ?? '' );
		$bl = (string) ( $p['bale_token'] ?? '' );
		$brand = array_key_exists( 'brand_name', $p ) ? (string) $p['brand_name'] : null;
		SimpleVPBot_Model_Reseller_Bot_Profile::upsert_tokens( $rid, $tg, $bl, $brand );
		self::log_rest_user( $rid, 'reseller_bot_tokens_save', array( 'has_tg' => strlen( $tg ) > 0, 'has_bl' => strlen( $bl ) > 0 ) );
		return array( 'ok' => true );
	}

	/**
	 * Register Telegram/Bale webhook URL for reseller bot (API call to platform).
	 *
	 * @param array<string, mixed> $p platform: telegram|bale; optional reseller_svp_user_id for admin.
	 * @return array{ok:bool, message?:string, data?:array<string,mixed>}
	 */
	private static function op_reseller_bot_webhook_set( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		if ( current_user_can( 'manage_options' ) ) {
			$rid = (int) ( $p['reseller_svp_user_id'] ?? 0 );
			if ( $rid < 1 ) {
				return array( 'ok' => false, 'message' => 'invalid_reseller' );
			}
			$row = SimpleVPBot_Model_User::find( $rid );
			if ( ! $row || ! SimpleVPBot_Model_User::is_reseller_row( $row ) ) {
				return array( 'ok' => false, 'message' => 'not_reseller' );
			}
		} else {
			$wp = SimpleVPBot_Model_User::find_by_wp_user( get_current_user_id() );
			if ( ! $wp || ! SimpleVPBot_Model_User::is_reseller_row( $wp ) ) {
				return array( 'ok' => false, 'message' => 'forbidden' );
			}
			$rid = (int) $wp->id;
		}
		$plat = sanitize_key( (string) ( $p['platform'] ?? '' ) );
		if ( 'telegram' === $plat ) {
			$r = SimpleVPBot_Service_Admin_Ops::set_webhook_telegram_for_reseller( $rid );
		} elseif ( 'bale' === $plat ) {
			$r = SimpleVPBot_Service_Admin_Ops::set_webhook_bale_for_reseller( $rid );
		} else {
			return array( 'ok' => false, 'message' => 'bad_platform' );
		}
		self::log_rest_user( $rid, 'reseller_bot_webhook_set', array( 'platform' => $plat, 'ok' => ! empty( $r['ok'] ) ) );
		return $r;
	}

	/**
	 * Rotate reseller webhook secret and invalidate previous URL.
	 *
	 * @param array<string, mixed> $p Optional reseller_svp_user_id for admin.
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_reseller_bot_secret_rotate( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		if ( current_user_can( 'manage_options' ) ) {
			$rid = (int) ( $p['reseller_svp_user_id'] ?? 0 );
			if ( $rid < 1 ) {
				return array( 'ok' => false, 'message' => 'invalid_reseller' );
			}
			$row = SimpleVPBot_Model_User::find( $rid );
			if ( ! $row || ! SimpleVPBot_Model_User::is_reseller_row( $row ) ) {
				return array( 'ok' => false, 'message' => 'not_reseller' );
			}
		} else {
			$wp = SimpleVPBot_Model_User::find_by_wp_user( get_current_user_id() );
			if ( ! $wp || ! SimpleVPBot_Model_User::is_reseller_row( $wp ) ) {
				return array( 'ok' => false, 'message' => 'forbidden' );
			}
			$rid = (int) $wp->id;
		}
		$new = (string) SimpleVPBot_Model_Reseller_Bot_Profile::rotate_webhook_secret( $rid );
		if ( '' === $new ) {
			return array( 'ok' => false, 'message' => 'rotate_failed' );
		}
		self::log_rest_user( $rid, 'reseller_bot_secret_rotate', array() );
		return array( 'ok' => true );
	}

	/**
	 * Toggle main bot enabled (settings).
	 *
	 * @param array<string, mixed> $p Unused.
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_bot_toggle_enabled( array $p ) {
		unset( $p );
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		if ( ! class_exists( 'SimpleVPBot_Admin_Actions' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		if ( ! SimpleVPBot_Admin_Actions::toggle_bool_setting( 'enabled' ) ) {
			return array( 'ok' => false, 'message' => 'toggle_failed' );
		}
		SimpleVPBot_Admin_Actions::after_settings_tab_saved( 'bots' );
		return array( 'ok' => true );
	}

	/**
	 * Telegram getMe (main bot or reseller bot).
	 *
	 * @param array<string, mixed> $p reseller_svp_user_id optional.
	 * @return array{ok:bool, message?:string, data?:mixed}
	 */
	private static function op_bot_test_telegram( array $p ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$rid = (int) ( $p['reseller_svp_user_id'] ?? 0 );
		if ( $rid > 0 ) {
			return SimpleVPBot_Service_Admin_Ops::test_telegram_for_reseller( $rid );
		}
		return SimpleVPBot_Service_Admin_Ops::test_telegram();
	}

	/**
	 * Bale getMe (main bot or reseller bot).
	 *
	 * @param array<string, mixed> $p reseller_svp_user_id optional.
	 * @return array{ok:bool, message?:string, data?:mixed}
	 */
	private static function op_bot_test_bale( array $p ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$rid = (int) ( $p['reseller_svp_user_id'] ?? 0 );
		if ( $rid > 0 ) {
			return SimpleVPBot_Service_Admin_Ops::test_bale_for_reseller( $rid );
		}
		return SimpleVPBot_Service_Admin_Ops::test_bale();
	}

	/**
	 * Set webhook: main bot (bot_id 0) or reseller (bot_id = reseller svp user id).
	 *
	 * @param array<string, mixed> $p bot_id, platform: telegram|bale.
	 * @return array{ok:bool, message?:string, data?:mixed}
	 */
	private static function op_bot_set_webhook( array $p ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$bid = (int) ( $p['bot_id'] ?? 0 );
		$plat = sanitize_key( (string) ( $p['platform'] ?? '' ) );
		if ( $bid < 1 ) {
			if ( 'telegram' === $plat ) {
				return SimpleVPBot_Service_Admin_Ops::set_webhook_telegram();
			}
			if ( 'bale' === $plat ) {
				return SimpleVPBot_Service_Admin_Ops::set_webhook_bale();
			}
			return array( 'ok' => false, 'message' => 'bad_platform' );
		}
		return self::op_reseller_bot_webhook_set(
			array(
				'reseller_svp_user_id' => $bid,
				'platform'             => $plat,
			)
		);
	}

	/**
	 * Toggle reseller bot profile enabled flag.
	 *
	 * @param array<string, mixed> $p reseller_svp_user_id.
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_bot_reseller_toggle_enabled( array $p ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		if ( ! class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$rid = (int) ( $p['reseller_svp_user_id'] ?? 0 );
		if ( $rid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_reseller' );
		}
		$row = SimpleVPBot_Model_User::find( $rid );
		if ( ! $row || ! SimpleVPBot_Model_User::is_reseller_row( $row ) ) {
			return array( 'ok' => false, 'message' => 'not_reseller' );
		}
		$prof = SimpleVPBot_Model_Reseller_Bot_Profile::find_by_reseller( $rid );
		if ( ! $prof ) {
			SimpleVPBot_Model_Reseller_Bot_Profile::ensure_webhook_secret( $rid );
			$prof = SimpleVPBot_Model_Reseller_Bot_Profile::find_by_reseller( $rid );
		}
		if ( ! $prof ) {
			return array( 'ok' => false, 'message' => 'no_profile' );
		}
		$en = ! empty( $prof->enabled );
		SimpleVPBot_Model_Reseller_Bot_Profile::set_enabled( $rid, ! $en );
		self::log_rest_user( $rid, 'bot_reseller_toggle_enabled', array( 'enabled' => ! $en ) );
		return array( 'ok' => true );
	}

	/**
	 * Delete reseller bot profile row.
	 *
	 * @param array<string, mixed> $p reseller_svp_user_id.
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_bot_reseller_delete( array $p ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		if ( ! class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$rid = (int) ( $p['reseller_svp_user_id'] ?? 0 );
		if ( $rid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_reseller' );
		}
		$row = SimpleVPBot_Model_User::find( $rid );
		if ( ! $row || ! SimpleVPBot_Model_User::is_reseller_row( $row ) ) {
			return array( 'ok' => false, 'message' => 'not_reseller' );
		}
		SimpleVPBot_Model_Reseller_Bot_Profile::delete_by_reseller( $rid );
		self::log_rest_user( $rid, 'bot_reseller_delete', array() );
		return array( 'ok' => true );
	}

	/**
	 * Save reseller bot tokens, brand, wallet token, admin ids, enabled (dashboard dialog).
	 *
	 * @param array<string, mixed> $p Dialog fields.
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_bot_reseller_save( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) || ! class_exists( 'SimpleVPBot_Admin_Actions' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		if ( current_user_can( 'manage_options' ) ) {
			$rid = (int) ( $p['reseller_svp_user_id'] ?? 0 );
			if ( $rid < 1 ) {
				return array( 'ok' => false, 'message' => 'invalid_reseller' );
			}
			$row = SimpleVPBot_Model_User::find( $rid );
			if ( ! $row || ! SimpleVPBot_Model_User::is_reseller_row( $row ) ) {
				return array( 'ok' => false, 'message' => 'not_reseller' );
			}
		} else {
			$wp = SimpleVPBot_Model_User::find_by_wp_user( get_current_user_id() );
			if ( ! $wp || ! SimpleVPBot_Model_User::is_reseller_row( $wp ) ) {
				return array( 'ok' => false, 'message' => 'forbidden' );
			}
			$rid = (int) $wp->id;
		}
		$tg  = (string) ( $p['telegram_token'] ?? '' );
		$bl  = (string) ( $p['bale_token'] ?? '' );
		$brand = array_key_exists( 'brand_name', $p ) ? (string) $p['brand_name'] : null;
		SimpleVPBot_Model_Reseller_Bot_Profile::upsert_tokens( $rid, $tg, $bl, $brand );
		if ( array_key_exists( 'bale_wallet_provider_token', $p ) ) {
			SimpleVPBot_Model_Reseller_Bot_Profile::save_bale_wallet_provider_token( $rid, (string) $p['bale_wallet_provider_token'] );
		}
		$tg_ids = SimpleVPBot_Admin_Actions::parse_id_lines( (string) ( $p['admin_telegram_ids'] ?? '' ) );
		$bl_ids = SimpleVPBot_Admin_Actions::parse_id_lines( (string) ( $p['admin_bale_ids'] ?? '' ) );
		SimpleVPBot_Model_Reseller_Bot_Profile::save_admin_ids( $rid, $tg_ids, $bl_ids );
		$en = ! isset( $p['enabled'] ) || ! empty( $p['enabled'] );
		SimpleVPBot_Model_Reseller_Bot_Profile::set_enabled( $rid, $en );
		self::log_rest_user( $rid, 'bot_reseller_save', array( 'enabled' => $en ) );
		return array( 'ok' => true );
	}
}
