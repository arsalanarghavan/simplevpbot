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
	 * Reseller actor for audit rows during apply() (0 = WP admin).
	 *
	 * @var int
	 */
	private static $audit_actor_svp_user_id = 0;

	/** @var bool Bot panel acting as site admin (no WP session). */
	private static $bot_acting_as_site_admin = false;

	/**
	 * Run mutation apply with site-admin privileges for bot panel actors.
	 *
	 * @param callable(): array{ok:bool, message?:string, code?:string, data?:mixed} $fn Callback.
	 * @return array{ok:bool, message?:string, code?:string, data?:mixed}
	 */
	public static function with_bot_site_admin( callable $fn ) {
		self::$bot_acting_as_site_admin = true;
		try {
			return $fn();
		} finally {
			self::$bot_acting_as_site_admin = false;
		}
	}

	/**
	 * Site admin acting as themselves (not dashboard impersonation).
	 *
	 * @return bool
	 */
	private static function mutate_is_unrestricted_site_admin() {
		if ( self::$bot_acting_as_site_admin ) {
			return true;
		}
		return class_exists( 'SimpleVPBot_Rest_Dashboard' )
			&& SimpleVPBot_Rest_Dashboard::dashboard_rest_is_unrestricted_site_admin();
	}

	/**
	 * Log dashboard REST action affecting a bot user (best-effort).
	 *
	 * @param int                  $subject_svp_user_id svp_users.id.
	 * @param string               $event_type          Short event key.
	 * @param array<string, mixed> $payload             Extra JSON-safe fields.
	 */
	/**
	 * @param string               $domain      admin|billing|reseller|security|bot.
	 * @param string               $event_type  Event key.
	 * @param string               $target_type Target entity.
	 * @param int                  $target_id   Target id.
	 * @param array<string, mixed> $payload     Extra data.
	 */
	private static function audit_rest( $domain, $event_type, $target_type, $target_id, array $payload = array() ) {
		if ( ! class_exists( 'SimpleVPBot_Audit_Log' ) ) {
			return;
		}
		$actor = SimpleVPBot_Audit_Log::current_actor_fields();
		SimpleVPBot_Audit_Log::record(
			array_merge(
				$actor,
				array(
					'domain'            => (string) $domain,
					'event_type'        => (string) $event_type,
					'target_type'       => (string) $target_type,
					'target_id'         => (int) $target_id,
					'reseller_scope_id' => (int) self::$audit_actor_svp_user_id,
					'payload'           => $payload,
				)
			)
		);
	}

	private static function log_rest_user( $subject_svp_user_id, $event_type, array $payload ) {
		if ( ! class_exists( 'SimpleVPBot_User_Activity_Log' ) ) {
			return;
		}
		$sid = (int) $subject_svp_user_id;
		if ( $sid < 1 ) {
			return;
		}
		$wp    = get_current_user_id();
		$actor = (int) self::$audit_actor_svp_user_id;
		SimpleVPBot_User_Activity_Log::append(
			array(
				'subject_svp_user_id' => $sid,
				'channel'             => 'rest',
				'actor_kind'          => $actor > 0 ? 'svp_user' : 'wp_admin',
				'actor_wp_user_id'    => $wp > 0 ? $wp : 0,
				'actor_svp_user_id'   => $actor,
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
		$svc = self::dashboard_find_service( $sid );
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
	 * Whether a receipt's user is in scope for a reseller dashboard mutation.
	 *
	 * @param array<string, mixed> $params      Mutation params (may include __actor_svp_user_id).
	 * @param int                  $svp_user_id Receipt owner svp_users.id.
	 * @return bool
	 */
	/**
	 * @return bool
	 */
	private static function l2tp_feature_disabled() {
		return class_exists( 'SimpleVPBot_Feature_L2tp' ) && ! SimpleVPBot_Feature_L2tp::enabled();
	}

	/**
	 * Service row for dashboard mutations (hidden L2TP treated as not found).
	 *
	 * @param int $service_id svp_services.id.
	 * @return object|null
	 */
	private static function dashboard_find_service( $service_id ) {
		$sid = (int) $service_id;
		if ( $sid < 1 || ! class_exists( 'SimpleVPBot_Model_Service' ) ) {
			return null;
		}
		$row = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $row ) {
			return null;
		}
		if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) && ! SimpleVPBot_Feature_L2tp::service_visible( $row ) ) {
			return null;
		}
		return $row;
	}

	/**
	 * @param array<string, mixed> $params      Mutation params.
	 * @param int                  $svp_user_id Receipt owner svp_users.id.
	 * @return bool
	 */
	private static function receipt_allowed_for_dashboard_actor( array $params, $svp_user_id ) {
		$actor = (int) ( $params['__actor_svp_user_id'] ?? 0 );
		if ( $actor < 1 ) {
			return true;
		}
		$uid = (int) $svp_user_id;
		if ( $actor < 1 ) {
			return true;
		}
		return $uid > 0 && class_exists( 'SimpleVPBot_Bot_Reseller_Scope' )
			&& SimpleVPBot_Bot_Reseller_Scope::reseller_may_moderate_user_for( $actor, $uid );
	}

	/**
	 * Strip DB rows and heavy panel payloads so REST responses always json_encode.
	 *
	 * @param array<string, mixed> $res Processor result.
	 * @return array<string, mixed>
	 */
	private static function sanitize_receipt_processor_result_for_json( array $res ) {
		$out = array(
			'ok' => ! empty( $res['ok'] ),
		);
		foreach ( array( 'reason', 'reason_code', 'message', 'provision_error', 'provision_reason' ) as $key ) {
			if ( isset( $res[ $key ] ) && is_scalar( $res[ $key ] ) ) {
				$out[ $key ] = (string) $res[ $key ];
			}
		}
		if ( ! empty( $res['purchase_failed'] ) ) {
			$out['purchase_failed'] = true;
		}
		if ( isset( $res['provision_info'] ) && is_array( $res['provision_info'] ) ) {
			$info = $res['provision_info'];
			$safe = array();
			if ( isset( $info['reason'] ) && is_scalar( $info['reason'] ) ) {
				$safe['reason'] = (string) $info['reason'];
			}
			if ( isset( $info['detail'] ) && is_scalar( $info['detail'] ) ) {
				$safe['detail'] = (string) $info['detail'];
			}
			$panel = $info['panel'] ?? null;
			if ( is_array( $panel ) && isset( $panel['msg'] ) && is_scalar( $panel['msg'] ) ) {
				$safe['panel_msg'] = (string) $panel['msg'];
			}
			if ( ! empty( $safe ) ) {
				$out['provision_info'] = $safe;
			}
		}
		return $out;
	}

	/**
	 * Map Receipt_Processor result to REST mutate shape (surface provision_error to UI).
	 *
	 * @param array<string, mixed> $res Processor result.
	 * @return array{ok:bool, message?:string, reason?:string, data?:array<string,mixed>}
	 */
	private static function receipt_mutate_rest_response( array $res ) {
		$ok   = ! empty( $res['ok'] );
		$data = self::sanitize_receipt_processor_result_for_json( $res );
		$out  = array(
			'ok'   => $ok,
			'data' => $data,
		);
		if ( ! $ok ) {
			$msg = '';
			if ( ! empty( $res['provision_error'] ) ) {
				$msg = (string) $res['provision_error'];
			} elseif ( ! empty( $res['message'] ) ) {
				$msg = (string) $res['message'];
			} elseif ( ! empty( $res['reason'] ) ) {
				$msg = (string) $res['reason'];
			}
			if ( '' !== $msg ) {
				$out['message'] = $msg;
				$out['reason']  = $msg;
			} elseif ( ! empty( $res['purchase_failed'] ) ) {
				$out['message'] = 'provision_failed';
				$out['reason']  = 'provision_failed';
			}
		}
		return $out;
	}

	/**
	 * Reseller dashboard actor id when REST injects __actor_svp_user_id; 0 for WP admin catalog scope.
	 *
	 * @param array<string, mixed> $p Params.
	 * @return int
	 */
	private static function dashboard_reseller_actor_id( array $p ) {
		return (int) ( $p['__actor_svp_user_id'] ?? 0 );
	}

	/**
	 * Whether dashboard reseller actor may moderate target user (defense-in-depth).
	 *
	 * @param array<string, mixed> $p           Params.
	 * @param int                  $target_user_id Target svp_users.id.
	 * @return bool
	 */
	private static function actor_may_moderate_user( array $p, $target_user_id ) {
		$actor = self::dashboard_reseller_actor_id( $p );
		$uid   = (int) $target_user_id;
		if ( $actor < 1 ) {
			return true;
		}
		return $uid > 0 && class_exists( 'SimpleVPBot_Bot_Reseller_Scope' )
			&& SimpleVPBot_Bot_Reseller_Scope::reseller_may_moderate_user_for( $actor, $uid );
	}

	/**
	 * Defense-in-depth: actor may mutate a service owned by a moderatable user.
	 *
	 * @param array<string, mixed> $p          Params.
	 * @param int                  $service_id Service id.
	 * @return array{ok:bool, message?:string}|null Null when allowed.
	 */
	private static function require_service_moderation_for_actor( array $p, $service_id ) {
		$sid = (int) $service_id;
		if ( $sid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_service' );
		}
		$row = self::dashboard_find_service( $sid );
		if ( ! $row ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		if ( ! self::actor_may_moderate_user( $p, (int) ( $row->user_id ?? 0 ) ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		return null;
	}

	/**
	 * Mutation ops that accept service_id and require moderation guard.
	 *
	 * @return array<int, string>
	 */
	private static function mutation_ops_with_service_id() {
		return array(
			'service_delete',
			'service_apply_canonical_panel_identity',
			'user_renew_service',
			'user_add_volume',
			'user_reduce_volume',
			'user_add_days',
			'user_reduce_days',
			'user_service_reduce_slots',
			'user_service_transfer',
			'user_service_toggle_enable',
			'service_alerts_patch',
			'service_set_note',
			'service_panel_sync',
			'service_regen_key',
			'service_regen_sub_id',
			'service_panel_refresh',
			'service_panel_delete_client',
			'user_service_add_slots',
			'service_set_limit_ip',
			'service_panel_transfer',
			'purge_expired_purge_one',
		);
	}

	/**
	 * @param string               $op     Operation key.
	 * @param array<string, mixed> $params Params.
	 * @return array{ok:bool, message?:string}|null
	 */
	private static function gate_service_moderation_for_op( $op, array $params ) {
		if ( ! in_array( $op, self::mutation_ops_with_service_id(), true ) ) {
			return null;
		}
		$sid = (int) ( $params['service_id'] ?? 0 );
		if ( $sid < 1 ) {
			return null;
		}
		return self::require_service_moderation_for_actor( $params, $sid );
	}

	/**
	 * Panel client configs: resolve owner user and enforce moderation scope.
	 *
	 * @param array<string, mixed> $p Params.
	 * @param bool                 $require_owner_resolved Configs batch: reject when owner cannot be resolved.
	 * @return array{ok:bool, message?:string}|null
	 */
	private static function require_panel_client_moderation_for_actor( array $p, $require_owner_resolved = false ) {
		$linked = (int) ( $p['linked_service_id'] ?? 0 );
		if ( $linked > 0 ) {
			return self::require_service_moderation_for_actor( $p, $linked );
		}
		$row = array(
			'panel_id'   => (int) ( $p['panel_id'] ?? 0 ),
			'inbound_id' => (int) ( $p['inbound_id'] ?? 0 ),
			'email'      => isset( $p['email'] ) ? (string) $p['email'] : '',
		);
		$uid = self::users_bulk_user_id_for_panel_row( $row );
		if ( $uid < 1 ) {
			if ( $require_owner_resolved ) {
				return array( 'ok' => false, 'message' => 'client_owner_unresolved' );
			}
			return null;
		}
		if ( ! self::actor_may_moderate_user( $p, $uid ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		return null;
	}

	/**
	 * Restrict service queries to panels a reseller actor may use (when panel_id unset).
	 *
	 * @param array<string, mixed> $payload panel_id?, __actor_svp_user_id?.
	 * @param string               $sql     SQL fragment (append-only).
	 * @param array<int, mixed>    $args    Prepared args (by reference).
	 */
	private static function users_bulk_append_panel_allowlist_sql( array $payload, &$sql, array &$args ) {
		$panel_id = (int) ( $payload['panel_id'] ?? 0 );
		if ( $panel_id > 0 ) {
			return;
		}
		$actor = (int) ( $payload['__actor_svp_user_id'] ?? 0 );
		if ( $actor < 1 ) {
			return;
		}
		$allowed = self::users_bulk_actor_panel_ids( $actor );
		if ( ! is_array( $allowed ) ) {
			return;
		}
		if ( empty( $allowed ) ) {
			$sql .= ' AND 1=0';
			return;
		}
		$ph   = implode( ',', array_fill( 0, count( $allowed ), '%d' ) );
		$sql .= " AND panel_id IN ({$ph})";
		$args = array_merge( $args, $allowed );
	}

	/**
	 * Whether an X-UI panel serves clients attributed to more than one reseller.
	 *
	 * @param int $panel_id Panel id.
	 * @return bool
	 */
	private static function panel_is_multi_reseller_shared( $panel_id ) {
		$panel_id = (int) $panel_id;
		if ( $panel_id < 1 || ! class_exists( 'SimpleVPBot_Model_Service' ) || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return false;
		}
		global $wpdb;
		$s_tbl = SimpleVPBot_Model_Service::table();
		$u_tbl = SimpleVPBot_Model_User::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$signup_cnt = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT u.signup_reseller_svp_id)
				FROM {$s_tbl} s
				INNER JOIN {$u_tbl} u ON u.id = s.user_id
				WHERE s.panel_id = %d AND s.deleted_at IS NULL AND u.signup_reseller_svp_id > 0",
				$panel_id
			)
		);
		if ( $signup_cnt > 1 ) {
			return true;
		}
		if ( class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' ) ) {
			$rpp = SimpleVPBot_Model_Reseller_Panel_Price::table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$price_cnt = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT reseller_svp_user_id) FROM {$rpp} WHERE panel_id = %d",
					$panel_id
				)
			);
			if ( $price_cnt > 1 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Site admin configs batch on shared panels requires reseller workspace context.
	 *
	 * @param array<string, mixed> $p        Mutate params.
	 * @param int                  $panel_id Panel id.
	 * @return array{ok:bool, message?:string}|null
	 */
	private static function require_configs_batch_workspace_context( array $p, $panel_id ) {
		if ( self::dashboard_reseller_actor_id( $p ) > 0 ) {
			return null;
		}
		if ( ! self::mutate_is_unrestricted_site_admin() ) {
			return null;
		}
		$ctx = isset( $p['reseller_context_svp_user_id'] ) ? (int) $p['reseller_context_svp_user_id'] : 0;
		if ( $ctx < 1 ) {
			$ctx = (int) ( $p['__actor_svp_user_id'] ?? 0 );
		}
		if ( $ctx > 0 ) {
			return null;
		}
		if ( ! self::panel_is_multi_reseller_shared( $panel_id ) ) {
			return null;
		}
		return array( 'ok' => false, 'message' => 'workspace_context_required' );
	}

	/**
	 * Resolve svp_users.id for a configs batch row (audit / logging).
	 *
	 * @param array<string, mixed> $row      Batch item.
	 * @param int                  $panel_id Panel id.
	 * @return int
	 */
	private static function configs_batch_item_subject_user_id( array $row, $panel_id ) {
		$linked = (int) ( $row['linked_service_id'] ?? 0 );
		if ( $linked > 0 ) {
			$svc = self::dashboard_find_service( $linked );
			return ( $svc && isset( $svc->user_id ) ) ? (int) $svc->user_id : 0;
		}
		return self::users_bulk_user_id_for_panel_row(
			array(
				'panel_id'   => (int) $panel_id,
				'inbound_id' => (int) ( $row['inbound_id'] ?? 0 ),
				'email'      => isset( $row['email'] ) ? (string) $row['email'] : '',
			)
		);
	}

	/**
	 * Audit each configs batch item after a successful mutation.
	 *
	 * @param array<string, mixed>             $p        Mutate params.
	 * @param int                                $panel_id Panel id.
	 * @param string                             $event    Audit event type.
	 * @param array<int, array<string, mixed>> $items    Client rows.
	 * @param array<string, mixed>             $extra    Extra payload fields.
	 */
	private static function audit_configs_batch_items( array $p, $panel_id, $event, array $items, array $extra = array() ) {
		foreach ( $items as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$uid = self::configs_batch_item_subject_user_id( $row, $panel_id );
			if ( $uid < 1 ) {
				continue;
			}
			self::log_rest_user(
				$uid,
				$event,
				array_merge(
					array(
						'panel_id'   => (int) $panel_id,
						'inbound_id' => (int) ( $row['inbound_id'] ?? 0 ),
						'email'      => isset( $row['email'] ) ? (string) $row['email'] : '',
					),
					$extra
				)
			);
		}
	}

	/**
	 * Defense-in-depth: every configs batch item must be moderatable by actor (when set).
	 *
	 * @param array<string, mixed>             $p       Mutate params.
	 * @param int                                $panel_id Panel id.
	 * @param array<int, array<string, mixed>> $items   Client rows.
	 * @return array{ok:bool, message?:string}|null
	 */
	private static function require_configs_batch_items_moderation( array $p, $panel_id, array $items ) {
		if ( empty( $items ) ) {
			return null;
		}
		$ws = self::require_configs_batch_workspace_context( $p, $panel_id );
		if ( is_array( $ws ) ) {
			return $ws;
		}
		foreach ( $items as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$check = array_merge(
				$p,
				array(
					'panel_id'          => (int) $panel_id,
					'inbound_id'        => (int) ( $row['inbound_id'] ?? 0 ),
					'email'             => isset( $row['email'] ) ? (string) $row['email'] : '',
					'linked_service_id' => (int) ( $row['linked_service_id'] ?? 0 ),
				)
			);
			$gate = self::require_panel_client_moderation_for_actor( $check, true );
			if ( is_array( $gate ) ) {
				return $gate;
			}
		}
		return null;
	}

	/**
	 * Single configs client op: workspace + resolvable owner + moderation scope.
	 *
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool, message?:string}|null
	 */
	private static function require_configs_single_client_moderation_for_actor( array $p ) {
		$panel_id = (int) ( $p['panel_id'] ?? 0 );
		$ws       = self::require_configs_batch_workspace_context( $p, $panel_id );
		if ( is_array( $ws ) ) {
			return $ws;
		}
		return self::require_panel_client_moderation_for_actor( $p, true );
	}

	/**
	 * Reseller actor for bot/reseller mutations: REST actor first, else WP-linked row.
	 *
	 * @param array<string, mixed> $p Params.
	 * @return int 0 when not a reseller actor.
	 */
	private static function mutate_reseller_actor_id( array $p ) {
		$actor = self::dashboard_reseller_actor_id( $p );
		if ( $actor > 0 ) {
			return $actor;
		}
		if ( self::mutate_is_unrestricted_site_admin() ) {
			return 0;
		}
		if ( ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return 0;
		}
		$wp = SimpleVPBot_Model_User::find_by_wp_user( get_current_user_id() );
		return ( $wp && SimpleVPBot_Model_User::is_reseller_row( $wp ) ) ? (int) $wp->id : 0;
	}

	/**
	 * Reseller may only mutate rows they own (owner_svp_user_id === actor).
	 *
	 * @param array<string, mixed> $p Params.
	 * @param int                  $owner_svp_user_id Row owner.
	 * @return bool
	 */
	private static function dashboard_reseller_owns_row_owner( array $p, $owner_svp_user_id ) {
		$actor = self::dashboard_reseller_actor_id( $p );
		if ( $actor < 1 ) {
			return true;
		}
		return (int) $owner_svp_user_id === $actor;
	}

	/**
	 * Dashboard reseller invoice checkouts: scope cards to site-global + this reseller (same as dashboard card list).
	 *
	 * @param array<string, mixed> $p Params (may include __actor_svp_user_id).
	 * @return int svp_users.id or 0 for default card resolution.
	 */
	private static function invoice_card_scope_reseller_from_mutate( array $p ) {
		$actor = self::dashboard_reseller_actor_id( $p );
		if ( $actor < 1 || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return 0;
		}
		$row = SimpleVPBot_Model_User::find( $actor );
		return ( $row && SimpleVPBot_Model_User::is_reseller_row( $row ) ) ? $actor : 0;
	}

	/**
	 * Apply one mutation from JSON params (already unslashed by REST).
	 *
	 * @param string               $op     Operation key.
	 * @param array<string, mixed> $params Parameters.
	 * @return array{ok:bool, message?:string, code?:string, data?:mixed}
	 */
	public static function apply( $op, array $params ) {
		self::$audit_actor_svp_user_id = (int) ( $params['__actor_svp_user_id'] ?? 0 );
		try {
			return self::apply_inner( $op, $params );
		} finally {
			self::$audit_actor_svp_user_id = 0;
		}
	}

	/**
	 * @param string               $op     Operation key.
	 * @param array<string, mixed> $params Parameters.
	 * @return array{ok:bool, message?:string, code?:string, data?:mixed}
	 */
	private static function apply_inner( $op, array $params ) {
		$op = sanitize_key( (string) $op );
		$svc_gate = self::gate_service_moderation_for_op( $op, $params );
		if ( is_array( $svc_gate ) ) {
			return $svc_gate;
		}
		$config_batch_ops = array(
			'configs_clients_batch',
			'configs_assign_plan',
		);
		$config_single_ops = array(
			'configs_client_toggle_enable',
			'configs_client_reset_traffic',
			'configs_client_delete',
			'configs_panel_client_patch',
		);
		if ( in_array( $op, $config_single_ops, true ) ) {
			$cfg_gate = self::require_configs_single_client_moderation_for_actor( $params );
			if ( is_array( $cfg_gate ) ) {
				return $cfg_gate;
			}
		} elseif ( in_array( $op, $config_batch_ops, true ) ) {
			$cfg_gate = self::require_configs_batch_workspace_context( $params, (int) ( $params['panel_id'] ?? 0 ) );
			if ( is_array( $cfg_gate ) ) {
				return $cfg_gate;
			}
		}
		switch ( $op ) {
			case 'settings_tab':
				return self::op_settings_tab( $params );
			case 'force_join_publish':
				return self::op_force_join_publish( $params );
			case 'receipt_reject_reasons_save':
				return self::op_receipt_reject_reasons_save( $params );
			case 'telegram_proxy_test':
				return self::op_telegram_proxy_test();
			case 'telegram_relay_test':
				return self::op_telegram_relay_test();
			case 'telegram_relay_sync':
				return self::op_telegram_relay_sync();
			case 'telegram_relay_set_webhook':
				return self::op_telegram_relay_set_webhook();
			case 'telegram_relay_rotate_secret':
				return self::op_telegram_relay_rotate_secret();
			case 'telegram_relay_status':
				return self::op_telegram_relay_status();
			case 'telegram_relay_domains_sync':
				return self::op_telegram_relay_domains_sync();
			case 'telegram_relay_set_webhook_reseller':
				return self::op_telegram_relay_set_webhook_reseller( $params );
			case 'logs_clear':
				return self::op_logs_clear( $params );
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
			case 'unit_economics_save':
				return self::op_unit_economics_save( $params );
			case 'unit_economics_config_save':
				return self::op_unit_economics_config_save( $params );
			case 'panel_economics_save':
				return self::op_panel_economics_save( $params );
			case 'shared_economics_save':
				return self::op_shared_economics_save( $params );
			case 'panel_economics_mark_paid':
				return self::op_panel_economics_mark_paid( $params );
			case 'card_add':
				return self::op_card_add( $params );
			case 'card_update':
				return self::op_card_update( $params );
			case 'card_delete':
				return self::op_card_delete( $params );
			case 'card_reorder':
				return self::op_card_reorder( $params );
			case 'reseller_payment_methods_save':
				return self::op_reseller_payment_methods_save( $params );
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
				return self::op_bot_ui_layout_reset( $params );
			case 'membership':
				return self::op_membership( $params );
			case 'receipt_set_status':
				return self::op_receipt_set_status( $params );
			case 'receipt_action':
				return self::op_receipt_action( $params );
			case 'receipt_update':
				return self::op_receipt_update( $params );
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
			case 'discount_redemptions':
				return self::op_discount_redemptions( $params );
			case 'marketing_rule_save':
				return self::op_marketing_rule_save( $params );
			case 'marketing_rule_delete':
				return self::op_marketing_rule_delete( $params );
			case 'marketing_send_manual':
				return self::op_marketing_send_manual( $params );
			case 'marketing_run_rule_now':
				return self::op_marketing_run_rule_now( $params );
			case 'link_wp_user':
				return self::op_link_wp_user( $params );
			case 'service_delete':
				return self::op_service_delete( $params );
			case 'service_apply_canonical_panel_identity':
				return self::op_service_apply_canonical_panel_identity( $params );
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
			case 'user_reduce_volume':
				return self::op_user_reduce_volume( $params );
			case 'user_add_days':
				return self::op_user_add_days( $params );
			case 'user_reduce_days':
				return self::op_user_reduce_days( $params );
			case 'user_service_reduce_slots':
				return self::op_user_service_reduce_slots( $params );
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
			case 'users_bulk_slots':
				return self::op_users_bulk_slots( $params );
			case 'users_bulk_run_worker':
				return self::op_users_bulk_run_worker( $params );
			case 'users_bulk_job_cancel':
				return self::op_users_bulk_job_cancel( $params );
			case 'users_bulk_job_resume':
				return self::op_users_bulk_job_resume( $params );
			case 'reseller_wallet_topup_checkout':
				return self::op_reseller_wallet_topup_checkout( $params );
			case 'reseller_wp_provision':
				return self::op_reseller_wp_provision( $params );
			case 'reseller_panel_prices_save':
				return self::op_reseller_panel_prices_save( $params );
			case 'wholesale_line_save':
				return self::op_wholesale_line_save( $params );
			case 'wholesale_line_delete':
				return self::op_wholesale_line_delete( $params );
			case 'reseller_wholesale_lines_assign':
				return self::op_reseller_wholesale_lines_assign( $params );
			case 'reseller_permissions_save':
				return self::op_reseller_permissions_save( $params );
			case 'reseller_bot_tokens_save':
				return self::op_reseller_bot_tokens_save( $params );
			case 'reseller_bot_webhook_set':
				return self::op_reseller_bot_webhook_set( $params );
			case 'reseller_bot_secret_rotate':
				return self::op_reseller_bot_secret_rotate( $params );
			case 'reseller_bind_users':
				return self::op_reseller_bind_users( $params );
			case 'user_set_role':
				return self::op_user_set_role( $params );
			case 'user_set_referrer':
				return self::op_user_set_referrer( $params );
			case 'user_service_toggle_enable':
				return self::op_user_service_toggle_enable( $params );
			case 'reseller_backfill_run':
				return self::op_reseller_backfill_run( $params );
			case 'inbound_link':
				return self::op_inbound_link( $params );
			case 'inbound_autolink':
				return self::op_inbound_autolink( $params );
			case 'user_admin_message':
				return self::op_user_admin_message( $params );
			case 'service_alerts_patch':
				return self::op_service_alerts_patch( $params );
			case 'service_set_note':
				return self::op_service_set_note( $params );
			case 'service_panel_sync':
				return self::op_service_panel_sync( $params );
			case 'service_regen_key':
				return self::op_service_regen_key( $params );
			case 'service_regen_sub_id':
				return self::op_service_regen_sub_id( $params );
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
			case 'purge_expired_run_cron':
				return self::op_purge_expired_run_cron( $params );
			case 'purge_expired_purge_ready':
				return self::op_purge_expired_purge_ready( $params );
			case 'purge_expired_purge_one':
				return self::op_purge_expired_purge_one( $params );
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
			case 'bot_toggle_platform_enabled':
				return self::op_bot_toggle_platform_enabled( $params );
			case 'bot_test_telegram':
				return self::op_bot_test_telegram( $params );
			case 'bot_test_bale':
				return self::op_bot_test_bale( $params );
			case 'bot_diagnostics':
				return self::op_bot_diagnostics( $params );
			case 'bot_set_webhook':
				return self::op_bot_set_webhook( $params );
			case 'bot_delete_webhook':
				return self::op_bot_delete_webhook( $params );
			case 'reseller_bot_webhook_delete':
				return self::op_reseller_bot_webhook_delete( $params );
			case 'bot_admin_id_add':
				return self::op_bot_admin_id_add( $params );
			case 'bot_admin_id_remove':
				return self::op_bot_admin_id_remove( $params );
			case 'bot_reseller_toggle_enabled':
				return self::op_bot_reseller_toggle_enabled( $params );
			case 'bot_reseller_secret_rotate':
				return self::op_reseller_bot_secret_rotate( $params );
			case 'bot_reseller_delete':
				return self::op_bot_reseller_delete( $params );
			case 'bot_reseller_save':
				return self::op_bot_reseller_save( $params );
			case 'reseller_inbound_labels_save':
				return self::op_reseller_inbound_labels_save( $params );
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
	 * Send and pin channel announcement for force-join (Telegram or Bale).
	 *
	 * @param array<string, mixed> $p Params (platform: telegram|bale).
	 * @return array{ok:bool, message?:string, message_id?:int}
	 */
	private static function op_force_join_publish( array $p ) {
		$plat = sanitize_key( (string) ( $p['platform'] ?? '' ) );
		if ( ! in_array( $plat, array( 'telegram', 'bale' ), true ) ) {
			return array( 'ok' => false, 'message' => 'invalid_platform' );
		}
		if ( ! class_exists( 'SimpleVPBot_Required_Channel' ) ) {
			return array( 'ok' => false, 'message' => 'unavailable' );
		}
		$res = SimpleVPBot_Required_Channel::publish_announcement( $plat );
		$out = array(
			'ok'      => ! empty( $res['ok'] ),
			'message' => isset( $res['message'] ) ? (string) $res['message'] : '',
		);
		if ( isset( $res['message_id'] ) ) {
			$out['message_id'] = (int) $res['message_id'];
		}
		return $out;
	}

	/**
	 * Save global receipt reject reason presets (resellers with receipts.review).
	 *
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_receipt_reject_reasons_save( array $p ) {
		$ok = SimpleVPBot_Admin_Actions::apply_settings_tab(
			'receipts',
			array(
				'receipt_reject_reasons' => $p['receipt_reject_reasons'] ?? array(),
			)
		);
		if ( $ok ) {
			SimpleVPBot_Admin_Actions::after_settings_tab_saved( 'receipts' );
		}
		return array( 'ok' => (bool) $ok, 'message' => $ok ? 'saved' : 'invalid_tab' );
	}

	/**
	 * @return array{ok:bool, message?:string, data?:array<string, mixed>}
	 */
	private static function op_telegram_proxy_test() {
		if ( ! class_exists( 'SimpleVPBot_Telegram_Http' ) ) {
			return array( 'ok' => false, 'message' => 'no_client' );
		}
		$res = SimpleVPBot_Telegram_Http::test_connection();
		return array(
			'ok'      => ! empty( $res['ok'] ),
			'message' => (string) ( $res['message'] ?? '' ),
			'data'    => $res,
		);
	}

	/**
	 * @return array{ok:bool, message?:string, data?:array<string,mixed>}
	 */
	private static function op_telegram_relay_test() {
		if ( ! class_exists( 'SimpleVPBot_Telegram_Relay' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$res = SimpleVPBot_Telegram_Relay::health();
		return array(
			'ok'      => ! empty( $res['ok'] ),
			'message' => (string) ( $res['message'] ?? '' ),
			'data'    => isset( $res['data'] ) && is_array( $res['data'] ) ? $res['data'] : array(),
		);
	}

	/**
	 * @return array{ok:bool, message?:string, data?:array<string,mixed>}
	 */
	private static function op_telegram_relay_sync() {
		if ( ! class_exists( 'SimpleVPBot_Telegram_Relay' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		return SimpleVPBot_Telegram_Relay::push_config_to_relay();
	}

	/**
	 * @return array{ok:bool, message?:string, data?:array<string,mixed>}
	 */
	private static function op_telegram_relay_set_webhook() {
		if ( ! class_exists( 'SimpleVPBot_Telegram_Relay' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		if ( ! SimpleVPBot_Telegram_Relay::is_enabled() ) {
			return array( 'ok' => false, 'message' => 'relay_disabled' );
		}
		return SimpleVPBot_Telegram_Relay::set_webhook_via_relay( 'main', 0, true );
	}

	/**
	 * @return array{ok:bool, message?:string, data?:array{secret:string}}
	 */
	private static function op_telegram_relay_rotate_secret() {
		if ( ! class_exists( 'SimpleVPBot_Telegram_Relay' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$sec = SimpleVPBot_Telegram_Relay::rotate_relay_secret();
		return array(
			'ok'   => true,
			'data' => array( 'secret' => $sec ),
		);
	}

	/**
	 * @return array{ok:bool, message?:string, data?:array<string,mixed>}
	 */
	private static function op_telegram_relay_status() {
		if ( ! class_exists( 'SimpleVPBot_Telegram_Relay' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$res = SimpleVPBot_Telegram_Relay::status_via_relay();
		return array(
			'ok'      => ! empty( $res['ok'] ),
			'message' => (string) ( $res['message'] ?? '' ),
			'data'    => isset( $res['data'] ) && is_array( $res['data'] ) ? $res['data'] : array(),
		);
	}

	/**
	 * @return array{ok:bool, message?:string, data?:array<string,mixed>}
	 */
	private static function op_telegram_relay_domains_sync() {
		if ( ! class_exists( 'SimpleVPBot_Telegram_Relay' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$sync = SimpleVPBot_Telegram_Relay::push_config_to_relay();
		if ( empty( $sync['ok'] ) ) {
			return $sync;
		}
		return SimpleVPBot_Telegram_Relay::domains_sync_via_relay();
	}

	/**
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool, message?:string, data?:array<string,mixed>}
	 */
	private static function op_telegram_relay_set_webhook_reseller( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Telegram_Relay' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$rid = (int) ( $p['reseller_svp_user_id'] ?? $p['bot_id'] ?? 0 );
		if ( $rid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_reseller' );
		}
		if ( ! SimpleVPBot_Telegram_Relay::is_enabled() ) {
			return array( 'ok' => false, 'message' => 'relay_disabled' );
		}
		return SimpleVPBot_Telegram_Relay::set_webhook_via_relay( 'reseller', $rid, true );
	}

	/**
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool, message?:string, data?:array{deleted:int}}
	 */
	private static function op_logs_clear( array $p ) {
		if ( empty( $p['confirm'] ) ) {
			return array( 'ok' => false, 'message' => 'confirm_required' );
		}
		if ( ! class_exists( 'SimpleVPBot_Model_Log' ) ) {
			return array( 'ok' => false, 'message' => 'no_model' );
		}
		$days = isset( $p['older_than_days'] ) ? (int) $p['older_than_days'] : 0;
		if ( $days < 0 ) {
			$days = 0;
		}
		$deleted = SimpleVPBot_Model_Log::delete_older_than_days( $days );
		return array(
			'ok'      => true,
			'message' => 'cleared',
			'data'    => array( 'deleted' => $deleted ),
		);
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
			if ( class_exists( 'SimpleVPBot_Model_Panel_Economics_Line' ) ) {
				SimpleVPBot_Model_Panel_Economics_Line::delete_for_panel( $rid );
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
		$token   = sanitize_text_field( (string) ( $p['xp_panel_api_token'] ?? '' ) );
		$subbase = trim( (string) ( $p['xp_subscription_public_base'] ?? '' ) );
		$subbase = '' !== $subbase ? esc_url_raw( $subbase ) : '';
		$sort    = (int) ( $p['xp_sort_order'] ?? 0 );
		$active  = ! empty( $p['xp_active'] ) ? 1 : 0;
		$pw_raw  = (string) ( $p['xp_panel_password'] ?? '' );
		$has_auth = '' !== trim( $token ) || ( '' !== trim( $user ) && '' !== trim( $pw_raw ) );
		if ( 'add' === $action && ( '' === $label || '' === $purl || ! $has_auth ) ) {
			return array( 'ok' => false, 'code' => 'invalid' );
		}
		if ( 'update' === $action && $rid > 0 && ( '' === $label || '' === $purl ) ) {
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
			$data['panel_api_token'] = $token;
			$data['panel_password']  = $pw_raw;
			SimpleVPBot_Model_Panel::insert( $data );
			return array( 'ok' => true, 'code' => 'added' );
		}
		if ( 'update' === $action && $rid > 0 ) {
			if ( '' !== trim( $pw_raw ) ) {
				$data['panel_password'] = $pw_raw;
			}
			if ( '' !== trim( $token ) ) {
				$data['panel_api_token'] = $token;
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
	 * Persist unit economics inputs and return recalculated metrics.
	 *
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool, unitEconomics?: array<string, mixed>, message?: string}
	 */
	private static function op_unit_economics_save( array $p ) {
		return self::op_unit_economics_config_save( $p );
	}

	/**
	 * Save global volume + selling price only (v2).
	 *
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool, unitEconomics?: array<string, mixed>, message?: string}
	 */
	private static function op_unit_economics_config_save( array $p ) {
		if ( ! self::mutate_is_unrestricted_site_admin() ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		if ( ! class_exists( 'SimpleVPBot_Unit_Economics_Calculator' )
			|| ! class_exists( 'SimpleVPBot_Model_Unit_Economics_Config' ) ) {
			return array( 'ok' => false, 'message' => 'unit_economics_unavailable' );
		}
		SimpleVPBot_Model_Unit_Economics_Config::upsert_global_inputs(
			array(
				'total_sold_volume_gb' => $p['total_sold_volume_gb'] ?? 0,
				'selling_price_per_gb' => $p['selling_price_per_gb'] ?? 0,
				'volume_mode'          => isset( $p['volume_mode'] ) ? (string) $p['volume_mode'] : '',
				'volume_window_days'   => $p['volume_window_days'] ?? null,
			)
		);
		$result = SimpleVPBot_Unit_Economics_Calculator::calculate_from_db();
		return array(
			'ok'                => true,
			'unitEconomics'     => $result,
			'panelEconomicsMap' => SimpleVPBot_Unit_Economics_Calculator::panel_economics_map_for_rest(),
		);
	}

	/**
	 * Save cost lines for one panel.
	 *
	 * @param array<string, mixed> $p panel_id, lines[].
	 * @return array{ok:bool, panelEconomics?: array<string, mixed>, message?: string}
	 */
	/**
	 * Save shared infrastructure lines (panel_id = 0).
	 *
	 * @param array<string, mixed> $p lines[].
	 * @return array<string, mixed>
	 */
	private static function op_shared_economics_save( array $p ) {
		if ( ! self::mutate_is_unrestricted_site_admin() ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		if ( ! class_exists( 'SimpleVPBot_Model_Panel_Economics_Line' )
			|| ! class_exists( 'SimpleVPBot_Unit_Economics_Calculator' ) ) {
			return array( 'ok' => false, 'message' => 'unit_economics_unavailable' );
		}
		$lines_raw = isset( $p['lines'] ) && is_array( $p['lines'] ) ? $p['lines'] : array();
		$clean     = array();
		foreach ( $lines_raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clean[] = SimpleVPBot_Model_Panel_Economics_Line::sanitize_line( $row );
		}
		SimpleVPBot_Model_Panel_Economics_Line::replace_for_shared( $clean );
		return array(
			'ok'                => true,
			'panelEconomicsMap' => SimpleVPBot_Unit_Economics_Calculator::panel_economics_map_for_rest(),
			'unitEconomics'     => SimpleVPBot_Unit_Economics_Calculator::calculate_from_db(),
		);
	}

	/**
	 * Mark a cost line as paid (updates paid_at / extends expires_at).
	 *
	 * @param array<string, mixed> $p line_id, extend_days?.
	 * @return array<string, mixed>
	 */
	private static function op_panel_economics_mark_paid( array $p ) {
		if ( ! self::mutate_is_unrestricted_site_admin() ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$line_id = isset( $p['line_id'] ) ? (int) $p['line_id'] : 0;
		if ( $line_id < 1 || ! class_exists( 'SimpleVPBot_Model_Panel_Economics_Line' ) ) {
			return array( 'ok' => false, 'message' => 'invalid_line' );
		}
		$extend = isset( $p['extend_days'] )
			? (int) $p['extend_days']
			: (int) SimpleVPBot_Settings::get( 'panel_cost_extend_days_on_paid', 30 );
		if ( ! SimpleVPBot_Model_Panel_Economics_Line::mark_paid( $line_id, $extend ) ) {
			return array( 'ok' => false, 'message' => 'mark_paid_failed' );
		}
		$out = array( 'ok' => true );
		if ( class_exists( 'SimpleVPBot_Unit_Economics_Overview' ) ) {
			$out['economics'] = SimpleVPBot_Unit_Economics_Overview::build();
		}
		if ( class_exists( 'SimpleVPBot_Unit_Economics_Calculator' ) ) {
			$out['panelEconomicsMap'] = SimpleVPBot_Unit_Economics_Calculator::panel_economics_map_for_rest();
		}
		return $out;
	}

	private static function op_panel_economics_save( array $p ) {
		if ( ! self::mutate_is_unrestricted_site_admin() ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$panel_id = isset( $p['panel_id'] ) ? (int) $p['panel_id'] : 0;
		if ( $panel_id < 1 || ! class_exists( 'SimpleVPBot_Model_Panel' ) || ! SimpleVPBot_Model_Panel::find( $panel_id ) ) {
			return array( 'ok' => false, 'message' => 'invalid_panel' );
		}
		if ( ! class_exists( 'SimpleVPBot_Model_Panel_Economics_Line' )
			|| ! class_exists( 'SimpleVPBot_Unit_Economics_Calculator' ) ) {
			return array( 'ok' => false, 'message' => 'unit_economics_unavailable' );
		}
		$lines_raw = isset( $p['lines'] ) && is_array( $p['lines'] ) ? $p['lines'] : array();
		$clean     = array();
		foreach ( $lines_raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clean[] = SimpleVPBot_Model_Panel_Economics_Line::sanitize_line( $row );
		}
		SimpleVPBot_Model_Panel_Economics_Line::replace_for_panel( $panel_id, $clean );

		$config = class_exists( 'SimpleVPBot_Model_Unit_Economics_Config' )
			? SimpleVPBot_Model_Unit_Economics_Config::global_inputs()
			: array(
				'total_sold_volume_gb' => 0.0,
				'selling_price_per_gb' => 0.0,
			);
		$active_for_panel = array();
		foreach ( SimpleVPBot_Model_Panel_Economics_Line::for_panel( $panel_id ) as $row ) {
			if ( empty( $row->active ) ) {
				continue;
			}
			$active_for_panel[] = SimpleVPBot_Model_Panel_Economics_Line::row_to_array( $row );
		}
		$shared_lines = array();
		foreach ( SimpleVPBot_Model_Panel_Economics_Line::for_shared() as $row ) {
			if ( empty( $row->active ) ) {
				continue;
			}
			$shared_lines[] = SimpleVPBot_Model_Panel_Economics_Line::row_to_array( $row );
		}
		$metrics = SimpleVPBot_Unit_Economics_Calculator::calculate_for_panel( $panel_id, $active_for_panel, $config, null, $shared_lines );
		$lines_edit = SimpleVPBot_Model_Panel_Economics_Line::map_by_panel_for_edit();
		return array(
			'ok'             => true,
			'panelEconomics' => array(
				'lines'   => isset( $lines_edit[ $panel_id ] ) ? $lines_edit[ $panel_id ] : array(),
				'metrics' => $metrics,
			),
			'unitEconomics'  => SimpleVPBot_Unit_Economics_Calculator::calculate_from_db(),
		);
	}

	/**
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool}
	 */
	private static function op_card_add( array $p ) {
		$actor = self::dashboard_reseller_actor_id( $p );
		$owner = isset( $p['owner_svp_user_id'] ) ? max( 0, (int) $p['owner_svp_user_id'] ) : 0;
		if ( $actor > 0 ) {
			$owner = $actor;
		}
		$priority = (int) ( $p['priority'] ?? 0 );
		if ( $priority < 1 ) {
			global $wpdb;
			$tbl = SimpleVPBot_Model_Card::table();
			$max = (int) $wpdb->get_var( "SELECT MAX(priority) FROM {$tbl}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$priority = $max + 10;
		}
		SimpleVPBot_Model_Card::insert(
			array(
				'owner_svp_user_id' => $owner,
				'card_number' => sanitize_text_field( (string) ( $p['card_number'] ?? '' ) ),
				'holder_name' => sanitize_text_field( (string) ( $p['holder_name'] ?? '' ) ),
				'bank_name'   => sanitize_text_field( (string) ( $p['bank_name'] ?? '' ) ),
				'method_key'  => SimpleVPBot_Service_Admin_Catalog::sanitize_card_method_key( (string) ( $p['method_key'] ?? 'c2c' ) ),
				'daily_limit' => (float) ( $p['daily_limit'] ?? 0 ),
				'priority'    => $priority,
				'note'        => sanitize_textarea_field( (string) ( $p['note'] ?? '' ) ),
				'active'      => 1,
			)
		);
		return array( 'ok' => true );
	}

	/**
	 * Reorder cards by priority (higher priority = shown first).
	 *
	 * @param array<string, mixed> $p ordered_ids: int[].
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_card_reorder( array $p ) {
		$ids = array();
		if ( isset( $p['ordered_ids'] ) && is_array( $p['ordered_ids'] ) ) {
			$ids = array_values( array_filter( array_map( 'intval', $p['ordered_ids'] ) ) );
		}
		if ( empty( $ids ) ) {
			return array( 'ok' => false, 'message' => 'invalid_ids' );
		}
		$n = count( $ids );
		foreach ( $ids as $idx => $eid ) {
			if ( $eid < 1 ) {
				continue;
			}
			$row = SimpleVPBot_Model_Card::find( $eid );
			if ( ! $row ) {
				return array( 'ok' => false, 'message' => 'not_found' );
			}
			if ( ! self::dashboard_reseller_owns_row_owner( $p, (int) ( $row->owner_svp_user_id ?? 0 ) ) ) {
				return array( 'ok' => false, 'message' => 'forbidden_scope' );
			}
			SimpleVPBot_Model_Card::update(
				$eid,
				array(
					'priority' => ( $n - (int) $idx ) * 10,
				)
			);
		}
		return array( 'ok' => true );
	}

	/**
	 * Save per-reseller payment method toggles.
	 *
	 * @param array<string, mixed> $p payment_methods map.
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_reseller_payment_methods_save( array $p ) {
		$actor = self::dashboard_reseller_actor_id( $p );
		if ( $actor < 1 ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		if ( ! class_exists( 'SimpleVPBot_Payment_Methods' ) || ! class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			return array( 'ok' => false, 'message' => 'unavailable' );
		}
		$raw = isset( $p['payment_methods'] ) ? $p['payment_methods'] : array();
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : array();
		}
		SimpleVPBot_Model_Reseller_Bot_Profile::save_payment_methods( $actor, is_array( $raw ) ? $raw : array() );
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
		if ( ! self::dashboard_reseller_owns_row_owner( $p, (int) ( $row->owner_svp_user_id ?? 0 ) ) ) {
			return array( 'ok' => false, 'message' => 'forbidden_scope' );
		}
		$actor = self::dashboard_reseller_actor_id( $p );
		$owner_keep = isset( $p['owner_svp_user_id'] ) ? max( 0, (int) $p['owner_svp_user_id'] ) : (int) ( $row->owner_svp_user_id ?? 0 );
		if ( $actor > 0 ) {
			$owner_keep = $actor;
		}
		$priority = array_key_exists( 'priority', $p )
			? (int) $p['priority']
			: (int) ( $row->priority ?? 0 );
		SimpleVPBot_Model_Card::update(
			$eid,
			array(
				'owner_svp_user_id' => $owner_keep,
				'card_number' => sanitize_text_field( (string) ( $p['card_number'] ?? '' ) ),
				'holder_name' => sanitize_text_field( (string) ( $p['holder_name'] ?? '' ) ),
				'bank_name'   => sanitize_text_field( (string) ( $p['bank_name'] ?? '' ) ),
				'method_key'  => SimpleVPBot_Service_Admin_Catalog::sanitize_card_method_key( (string) ( $p['method_key'] ?? 'c2c' ) ),
				'daily_limit' => (float) ( $p['daily_limit'] ?? 0 ),
				'priority'    => $priority,
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
		if ( ! self::dashboard_reseller_owns_row_owner( $p, (int) ( $row->owner_svp_user_id ?? 0 ) ) ) {
			return array( 'ok' => false, 'message' => 'forbidden_scope' );
		}
		SimpleVPBot_Model_Card::delete( $eid );
		return array( 'ok' => true );
	}

	/**
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool}
	 */
	private static function op_l2tp_add( array $p ) {
		if ( self::l2tp_feature_disabled() ) {
			return array( 'ok' => false, 'message' => 'l2tp_disabled', 'code' => 'l2tp_disabled' );
		}
		SimpleVPBot_Model_L2TP_Server::insert( SimpleVPBot_Service_Admin_Catalog::sanitize_l2tp_post( null, $p ) );
		return array( 'ok' => true );
	}

	/**
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool}
	 */
	private static function op_l2tp_update( array $p ) {
		if ( self::l2tp_feature_disabled() ) {
			return array( 'ok' => false, 'message' => 'l2tp_disabled', 'code' => 'l2tp_disabled' );
		}
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
		if ( self::l2tp_feature_disabled() ) {
			return array( 'ok' => false, 'message' => 'l2tp_disabled', 'code' => 'l2tp_disabled' );
		}
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
		if ( class_exists( 'SimpleVPBot_Broadcast_Format' ) ) {
			return SimpleVPBot_Broadcast_Format::sanitize_compose_html( $t );
		}
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
		if ( (int) ( $p['__actor_svp_user_id'] ?? 0 ) > 0 ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
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
	private static function op_bot_ui_layout_reset( array $p = array() ) {
		if ( ! class_exists( 'SimpleVPBot_UI_Layout' ) ) {
			return array( 'ok' => false, 'message' => 'missing_ui' );
		}
		if ( (int) ( $p['__actor_svp_user_id'] ?? 0 ) > 0 ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
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
		if ( ! self::actor_may_moderate_user( $p, $m_uid ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
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
		$rec = SimpleVPBot_Model_Receipt::find( $rid );
		if ( ! $rec ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		if ( ! self::receipt_allowed_for_dashboard_actor( $p, (int) ( $rec->user_id ?? 0 ) ) ) {
			return array( 'ok' => false, 'message' => 'forbidden_scope' );
		}
		$reason = isset( $p['reject_reason'] ) ? sanitize_textarea_field( (string) $p['reject_reason'] ) : '';
		$res = SimpleVPBot_Receipt_Processor::admin_set_receipt_status( $rid, $new, $lab, $reason );
		return self::receipt_mutate_rest_response( $res );
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
		$rec = SimpleVPBot_Model_Receipt::find( $rid );
		if ( ! $rec ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		if ( ! self::receipt_allowed_for_dashboard_actor( $p, (int) ( $rec->user_id ?? 0 ) ) ) {
			return array( 'ok' => false, 'message' => 'forbidden_scope' );
		}
		if ( 'approve' === $act ) {
			$res = SimpleVPBot_Receipt_Processor::approve_async_start( $rid, $label );
			return self::receipt_mutate_rest_response( $res );
		}
		if ( 'reject' === $act ) {
			$reason = isset( $p['reject_reason'] ) ? sanitize_textarea_field( (string) $p['reject_reason'] ) : '';
			$res    = SimpleVPBot_Receipt_Processor::reject( $rid, $label, $reason );
			return self::receipt_mutate_rest_response( $res );
		}
		return array( 'ok' => false, 'message' => 'bad_action' );
	}

	/**
	 * Update receipt/transaction amount (pending or approved) with accounting side-effects.
	 *
	 * @param object $rec Receipt row.
	 * @param object $tx  Transaction row.
	 * @param float  $new_amount New toman amount.
	 * @return array{ok:bool, message?:string, warnings?:array<int, string>}
	 */
	private static function adjust_receipt_amount( $rec, $tx, $new_amount ) {
		$old = round( (float) ( $rec->amount ?? 0 ), 2 );
		$new = round( (float) $new_amount, 2 );
		if ( $new < 0 ) {
			return array( 'ok' => false, 'message' => 'bad_amount' );
		}
		if ( abs( $new - $old ) < 0.009 ) {
			return array( 'ok' => true, 'message' => 'amount_unchanged' );
		}
		$warnings = array();
		$meta     = json_decode( (string) ( $tx->meta_json ?? '' ), true );
		if ( is_array( $meta ) && ! empty( $meta['referral_commission_paid'] ) ) {
			$warnings[] = 'commission_may_need_manual_review';
		}
		$status = (string) ( $rec->status ?? '' );
		$type   = (string) ( $tx->type ?? '' );
		if ( 'approved' === $status && 'topup' === $type ) {
			$delta = $new - $old;
			if ( ! SimpleVPBot_Model_User::increment_balance( (int) $rec->user_id, $delta ) ) {
				return array( 'ok' => false, 'message' => 'topup_balance_adjust_failed' );
			}
		}
		SimpleVPBot_Model_Receipt::update( (int) $rec->id, array( 'amount' => $new ) );
		SimpleVPBot_Model_Transaction::update( (int) $tx->id, array( 'amount' => $new ) );
		if ( class_exists( 'SimpleVPBot_Audit_Log' ) ) {
			$actor = SimpleVPBot_Audit_Log::current_actor_fields();
			SimpleVPBot_Audit_Log::record(
				array_merge(
					$actor,
					array(
						'domain'      => 'billing',
						'event_type'  => 'receipt.amount_adjust',
						'target_type' => 'receipt',
						'target_id'   => (int) $rec->id,
						'payload'     => array(
							'tx_id'      => (int) $tx->id,
							'user_id'    => (int) $rec->user_id,
							'tx_type'    => $type,
							'status'     => $status,
							'old_amount' => $old,
							'new_amount' => $new,
							'delta'      => round( $new - $old, 2 ),
						),
					)
				)
			);
		}
		$msg = ( 'approved' === $status && 'topup' === $type ) ? 'topup_delta_applied' : 'amount_updated';
		$out = array( 'ok' => true, 'message' => $msg );
		if ( ! empty( $warnings ) ) {
			$out['warnings'] = $warnings;
		}
		return $out;
	}

	/**
	 * Update receipt amount and/or status from the dashboard.
	 *
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool, message?:string, data?:mixed}
	 */
	private static function op_receipt_update( array $p ) {
		$rid = (int) ( $p['receipt_id'] ?? 0 );
		if ( $rid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		$rec = SimpleVPBot_Model_Receipt::find( $rid );
		if ( ! $rec ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		if ( ! self::receipt_allowed_for_dashboard_actor( $p, (int) ( $rec->user_id ?? 0 ) ) ) {
			return array( 'ok' => false, 'message' => 'forbidden_scope' );
		}
		$tx = SimpleVPBot_Model_Transaction::find( (int) $rec->transaction_id );
		if ( ! $tx ) {
			return array( 'ok' => false, 'message' => 'no_tx' );
		}
		$amount_adj_result = null;
		if ( array_key_exists( 'amount', $p ) ) {
			$amount = (float) str_replace( ',', '.', (string) $p['amount'] );
			$adj    = self::adjust_receipt_amount( $rec, $tx, $amount );
			if ( empty( $adj['ok'] ) ) {
				return $adj;
			}
			$amount_adj_result = $adj;
			$rec               = SimpleVPBot_Model_Receipt::find( $rid );
			$tx                = SimpleVPBot_Model_Transaction::find( (int) $rec->transaction_id );
			if ( ! $tx ) {
				return array( 'ok' => false, 'message' => 'no_tx' );
			}
		}
		$new = isset( $p['status'] ) ? sanitize_key( (string) $p['status'] ) : '';
		if ( '' !== $new ) {
			$reason = isset( $p['reject_reason'] ) ? sanitize_textarea_field( (string) $p['reject_reason'] ) : '';
			$label  = (string) wp_get_current_user()->user_login;
			$res    = SimpleVPBot_Receipt_Processor::admin_set_receipt_status( $rid, $new, $label, $reason );
			return self::receipt_mutate_rest_response( $res );
		}
		if ( is_array( $amount_adj_result ) ) {
			return $amount_adj_result;
		}
		return array( 'ok' => true );
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
		$text_safe = class_exists( 'SimpleVPBot_Broadcast_Format' )
			? SimpleVPBot_Broadcast_Format::sanitize_compose_html( $text_trim )
			: self::broadcast_sanitize_html( $text_trim );
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
		if ( class_exists( 'SimpleVPBot_Platforms' ) ) {
			if ( ! SimpleVPBot_Platforms::is_enabled( 'telegram', $owner ) ) {
				$include_tg = false;
			}
			if ( ! SimpleVPBot_Platforms::is_enabled( 'bale', $owner ) ) {
				$include_bl = false;
			}
		}
		if ( ! $include_tg && ! $include_bl ) {
			return array( 'ok' => false, 'message' => 'platform_disabled' );
		}

		$users = array();
		if ( $owner > 0 ) {
			$scope_ids = class_exists( 'SimpleVPBot_Bot_Reseller_Scope' )
				? SimpleVPBot_Bot_Reseller_Scope::effective_moderatable_user_ids( $owner )
				: SimpleVPBot_Model_User::reseller_scope_user_ids( $owner );
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
		$actor = self::dashboard_reseller_actor_id( $p );
		if ( $actor > 0 ) {
			$brow = SimpleVPBot_Model_Broadcast::find( $bid );
			if ( ! $brow ) {
				return array( 'ok' => false, 'message' => 'not_found' );
			}
			if ( ! self::dashboard_reseller_owns_row_owner( $p, (int) ( $brow->owner_svp_user_id ?? 0 ) ) ) {
				return array( 'ok' => false, 'message' => 'forbidden_scope' );
			}
		}
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
	 * Turn newlines outside <pre>/<code> into <br> for Telegram HTML.
	 *
	 * Splits by <pre> first so inner <code> inside a pre block stays untouched.
	/**
	 * Legacy fallback if broadcast formatter is unavailable.
	 *
	 * @param string $text Raw.
	 * @return string
	 */
	private static function broadcast_sanitize_html( $text ) {
		return (string) $text;
	}

	/**
	 * Portal / external entry for discount save (scoped by owner in $post).
	 *
	 * @param array<string, mixed> $post Fields (svpc_*).
	 * @return array{ok:bool, message?:string, data?:mixed}
	 */
	public static function discount_save_from_post( array $post ) {
		return self::op_discount_save( $post );
	}

	private static function op_discount_save( array $post ) {
		$actor = self::dashboard_reseller_actor_id( $post );
		$id   = isset( $post['svpc_id'] ) ? (int) $post['svpc_id'] : 0;
		if ( $id > 0 ) {
			$ex = SimpleVPBot_Model_Discount_Code::find( $id );
			if ( ! $ex ) {
				return array( 'ok' => false, 'message' => 'not_found' );
			}
			if ( ! self::dashboard_reseller_owns_row_owner( $post, (int) ( $ex->owner_svp_user_id ?? 0 ) ) ) {
				return array( 'ok' => false, 'message' => 'forbidden_scope' );
			}
		}
		$code = SimpleVPBot_Model_Discount_Code::normalize_code( isset( $post['svpc_code'] ) ? (string) $post['svpc_code'] : '' );
		if ( '' === $code ) {
			return array( 'ok' => false, 'message' => 'empty_code' );
		}
		$type = sanitize_key( isset( $post['svpc_type'] ) ? (string) $post['svpc_type'] : 'percent' );
		$allowed_types = array( 'percent', 'fixed_toman', 'percent_per_gb', 'fixed_per_gb' );
		if ( ! in_array( $type, $allowed_types, true ) ) {
			$type = 'percent';
		}
		$val = (float) str_replace( ',', '.', (string) ( $post['svpc_value'] ?? '0' ) );
		if ( $val < 0 ) {
			$val = 0.0;
		}
		if ( in_array( $type, array( 'percent', 'percent_per_gb' ), true ) ) {
			$val = min( 100.0, $val );
		}
		$maxu     = isset( $post['svpc_max_uses'] ) ? trim( (string) $post['svpc_max_uses'] ) : '';
		$max_uses = ( '' === $maxu || ! is_numeric( $maxu ) ) ? null : max( 0, (int) $maxu );
		$vf       = isset( $post['svpc_valid_from'] ) ? trim( (string) $post['svpc_valid_from'] ) : '';
		$vu       = isset( $post['svpc_valid_until'] ) ? trim( (string) $post['svpc_valid_until'] ) : '';
		$mo       = isset( $post['svpc_min_order'] ) ? trim( (string) $post['svpc_min_order'] ) : '';
		$min_order = ( '' === $mo || ! is_numeric( $mo ) ) ? null : max( 0.0, (float) str_replace( ',', '.', $mo ) );
		$mxo       = isset( $post['svpc_max_order'] ) ? trim( (string) $post['svpc_max_order'] ) : '';
		$max_order = ( '' === $mxo || ! is_numeric( $mxo ) ) ? null : max( 0.0, (float) str_replace( ',', '.', $mxo ) );
		$mdc       = isset( $post['svpc_max_discount'] ) ? trim( (string) $post['svpc_max_discount'] ) : '';
		$max_disc  = ( '' === $mdc || ! is_numeric( $mdc ) ) ? null : max( 0.0, (float) str_replace( ',', '.', $mdc ) );
		$restricted = isset( $post['svpc_restricted_user_id'] ) ? max( 0, (int) $post['svpc_restricted_user_id'] ) : 0;
		if ( $restricted > 0 && ! self::actor_may_moderate_user( $post, $restricted ) ) {
			return array( 'ok' => false, 'message' => 'forbidden_scope' );
		}
		$plan_ids_raw = isset( $post['svpc_allowed_plan_ids'] ) ? $post['svpc_allowed_plan_ids'] : array();
		if ( is_string( $plan_ids_raw ) ) {
			$dec = json_decode( $plan_ids_raw, true );
			$plan_ids_raw = is_array( $dec ) ? $dec : array();
		}
		$plan_ids = array();
		if ( is_array( $plan_ids_raw ) ) {
			foreach ( $plan_ids_raw as $pid ) {
				$n = (int) $pid;
				if ( $n > 0 ) {
					$plan_ids[] = $n;
				}
			}
		}
		$plan_ids = array_values( array_unique( $plan_ids ) );
		$owner_id = isset( $post['owner_svp_user_id'] ) ? max( 0, (int) $post['owner_svp_user_id'] ) : 0;
		if ( $actor > 0 ) {
			$owner_id = $actor;
			if ( ! empty( $plan_ids ) && class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) && class_exists( 'SimpleVPBot_Model_Plan' ) ) {
				foreach ( $plan_ids as $pid_check ) {
					$plan_row = SimpleVPBot_Model_Plan::find( (int) $pid_check );
					if ( ! $plan_row || ! SimpleVPBot_Bot_Reseller_Scope::plan_visible_for_reseller( $plan_row, $actor ) ) {
						return array( 'ok' => false, 'message' => 'forbidden_plan' );
					}
				}
			}
		}
		if ( ! empty( $post['svpc_active'] ) && ! empty( $plan_ids ) ) {
			$overlap = SimpleVPBot_Model_Discount_Code::active_with_plan_overlap( $owner_id, $plan_ids, $id );
			if ( ! empty( $overlap ) ) {
				return array( 'ok' => false, 'message' => 'plan_overlap' );
			}
		}
		$row      = array(
			'owner_svp_user_id'      => $owner_id,
			'code'                   => $code,
			'active'                 => ! empty( $post['svpc_active'] ) ? 1 : 0,
			'discount_type'          => $type,
			'discount_value'         => $val,
			'max_uses'               => $max_uses,
			'valid_from'             => '' !== $vf ? $vf : null,
			'valid_until'            => '' !== $vu ? $vu : null,
			'min_order_toman'        => $min_order,
			'max_order_toman'        => $max_order,
			'max_discount_toman'     => $max_disc,
			'restricted_svp_user_id' => $restricted > 0 ? $restricted : null,
			'allowed_plan_ids'       => SimpleVPBot_Model_Discount_Code::encode_allowed_plan_ids( $plan_ids ),
			'allow_new_purchase'     => ! empty( $post['svpc_allow_new'] ) ? 1 : 0,
			'allow_renew_same'       => ! empty( $post['svpc_allow_renew'] ) ? 1 : 0,
			'allow_add_volume'       => ! empty( $post['svpc_allow_vol'] ) ? 1 : 0,
			'allow_add_user_slots'   => ! empty( $post['svpc_allow_users'] ) ? 1 : 0,
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
			$ex = SimpleVPBot_Model_Discount_Code::find( $id );
			if ( ! $ex ) {
				return array( 'ok' => false, 'message' => 'not_found' );
			}
			if ( ! self::dashboard_reseller_owns_row_owner( $p, (int) ( $ex->owner_svp_user_id ?? 0 ) ) ) {
				return array( 'ok' => false, 'message' => 'forbidden_scope' );
			}
			SimpleVPBot_Model_Discount_Code::delete( $id );
		}
		return array( 'ok' => true );
	}

	/**
	 * Recent redemptions for one discount code (dashboard usage dialog).
	 *
	 * @param array<string, mixed> $p Params code_id, limit (optional).
	 * @return array{ok:bool, rows?:array<int, array<string, mixed>>, message?:string}
	 */
	private static function op_discount_redemptions( array $p ) {
		$id = isset( $p['code_id'] ) ? (int) $p['code_id'] : 0;
		if ( $id < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_id' );
		}
		$ex = SimpleVPBot_Model_Discount_Code::find( $id );
		if ( ! $ex ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		if ( ! self::dashboard_reseller_owns_row_owner( $p, (int) ( $ex->owner_svp_user_id ?? 0 ) ) ) {
			return array( 'ok' => false, 'message' => 'forbidden_scope' );
		}
		$limit = isset( $p['limit'] ) ? (int) $p['limit'] : 20;
		$rows  = class_exists( 'SimpleVPBot_Model_Discount_Redemption' )
			? SimpleVPBot_Model_Discount_Redemption::recent_for_code( $id, $limit )
			: array();
		$out = array();
		foreach ( (array) $rows as $row ) {
			if ( ! $row ) {
				continue;
			}
			$item = array(
				'id'              => (int) ( $row->id ?? 0 ),
				'svp_user_id'     => (int) ( $row->svp_user_id ?? 0 ),
				'transaction_id'  => (int) ( $row->transaction_id ?? 0 ),
				'subtotal_toman'  => (float) ( $row->subtotal_toman ?? 0 ),
				'discount_toman'  => (float) ( $row->discount_toman ?? 0 ),
				'volume_gb'       => isset( $row->volume_gb ) ? (float) $row->volume_gb : null,
				'created_at'      => (string) ( $row->created_at ?? '' ),
			);
			$uid = (int) ( $row->svp_user_id ?? 0 );
			if (
				! self::mutate_is_unrestricted_site_admin()
				&& $uid > 0
				&& ! self::actor_may_moderate_user( $p, $uid )
			) {
				continue;
			}
			if ( $uid > 0 && class_exists( 'SimpleVPBot_Model_User' ) ) {
				$u = SimpleVPBot_Model_User::find( $uid );
				if ( $u ) {
					$item['user_name'] = trim( (string) ( $u->first_name ?? '' ) . ' ' . (string) ( $u->last_name ?? '' ) );
					$item['user_username'] = (string) ( $u->username ?? '' );
				}
			}
			$out[] = $item;
		}
		return array( 'ok' => true, 'rows' => $out );
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
		$guard = self::require_service_moderation_for_actor( $p, $sid );
		if ( is_array( $guard ) ) {
			return $guard;
		}
		$row = self::dashboard_find_service( $sid );
		$uid = $row ? (int) $row->user_id : 0;
		$em  = $row ? (string) ( $row->email ?? '' ) : '';
		if ( ! $row ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
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
	 * @param array<string, mixed> $p service_id.
	 * @return array{ok:bool, message?:string, data?:array<string, mixed>}
	 */
	private static function op_service_apply_canonical_panel_identity( array $p ) {
		$sid = isset( $p['service_id'] ) ? (int) $p['service_id'] : 0;
		if ( $sid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_service' );
		}
		$guard = self::require_service_moderation_for_actor( $p, $sid );
		if ( is_array( $guard ) ) {
			return $guard;
		}
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return array( 'ok' => false, 'message' => 'unavailable' );
		}
		$out = SimpleVPBot_Service_Admin_Ops::service_apply_canonical_panel_identity( $sid );
		if ( empty( $out['ok'] ) ) {
			return array(
				'ok'      => false,
				'message' => (string) ( $out['message'] ?? 'failed' ),
			);
		}
		return array(
			'ok'      => true,
			'message' => 'ok',
			'data'    => array(
				'canonical' => (string) ( $out['canonical'] ?? '' ),
				'email'     => (string) ( $out['email'] ?? '' ),
			),
		);
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
		if ( ! self::actor_may_moderate_user( $p, $uid ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
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
		if ( ! self::actor_may_moderate_user( $p, $uid ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
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
		if ( class_exists( 'SimpleVPBot_User_Notify' ) ) {
			SimpleVPBot_User_Notify::send_to_user( $user, $body );
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
		if ( ! self::actor_may_moderate_user( $p, $tuid ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$actor = self::dashboard_reseller_actor_id( $p );
		if ( $actor > 0 && class_exists( 'SimpleVPBot_Model_User' ) ) {
			$actor_row = SimpleVPBot_Model_User::find( $actor );
			if ( $actor_row && SimpleVPBot_Model_User::is_reseller_row( $actor_row ) ) {
				$plan_row = SimpleVPBot_Model_Plan::find( $pid );
				if ( ! $plan_row || ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' )
					|| ! SimpleVPBot_Bot_Reseller_Scope::plan_visible_for_reseller( $plan_row, $actor ) ) {
					return array( 'ok' => false, 'reason' => 'forbidden_plan' );
				}
			}
		}
		$scope = self::invoice_card_scope_reseller_from_mutate( $p );
		$res   = SimpleVPBot_Admin_User_Ops::admin_create_service( $tuid, $pid, $vol, $mode, $scope );
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
		$guard = self::require_service_moderation_for_actor( $p, $sid );
		if ( is_array( $guard ) ) {
			return $guard;
		}
		$svc = self::dashboard_find_service( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		$uid = (int) $svc->user_id;
		$scope = self::invoice_card_scope_reseller_from_mutate( $p );
		$res   = SimpleVPBot_Admin_User_Ops::admin_renew_service( $sid, $mode, $scope );
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
		$svc = self::dashboard_find_service( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		$uid = (int) $svc->user_id;
		$scope = self::invoice_card_scope_reseller_from_mutate( $p );
		$res   = SimpleVPBot_Admin_User_Ops::admin_add_volume( $sid, $gb, $mode, $scope );
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
	 * @param array<string, mixed> $p service_id, reduce_gb.
	 * @return array{ok:bool, reason?:string}
	 */
	private static function op_user_reduce_volume( array $p ) {
		$sid = (int) ( $p['service_id'] ?? 0 );
		$gb  = (int) ( $p['reduce_gb'] ?? $p['extra_gb'] ?? 0 );
		if ( $sid < 1 || $gb < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		$svc = self::dashboard_find_service( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		$res = SimpleVPBot_Admin_User_Ops::admin_reduce_volume( $sid, $gb, 'free' );
		if ( empty( $res['ok'] ) ) {
			return array( 'ok' => false, 'reason' => (string) ( $res['reason'] ?? 'failed' ) );
		}
		self::log_rest_user( (int) $svc->user_id, 'service_reduce_volume', array( 'service_id' => $sid, 'reduce_gb' => $gb ) );
		self::configs_sync_after_service_panel_change( $sid );
		return array( 'ok' => true );
	}

	/**
	 * @param array<string, mixed> $p service_id, days.
	 * @return array{ok:bool, reason?:string}
	 */
	private static function op_user_add_days( array $p ) {
		$sid  = (int) ( $p['service_id'] ?? 0 );
		$days = (int) ( $p['days'] ?? 0 );
		if ( $sid < 1 || $days < 1 || ! class_exists( 'SimpleVPBot_Service_Renew' ) ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		$svc = self::dashboard_find_service( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		$r = SimpleVPBot_Service_Renew::apply_extend_days_free( $sid, $days );
		if ( empty( $r['ok'] ) ) {
			return array( 'ok' => false, 'reason' => (string) ( $r['message'] ?? 'failed' ) );
		}
		self::log_rest_user( (int) $svc->user_id, 'service_add_days', array( 'service_id' => $sid, 'days' => $days ) );
		self::configs_sync_after_service_panel_change( $sid );
		return array( 'ok' => true );
	}

	/**
	 * @param array<string, mixed> $p service_id, days.
	 * @return array{ok:bool, reason?:string}
	 */
	private static function op_user_reduce_days( array $p ) {
		$sid  = (int) ( $p['service_id'] ?? 0 );
		$days = (int) ( $p['days'] ?? 0 );
		if ( $sid < 1 || $days < 1 || ! class_exists( 'SimpleVPBot_Service_Renew' ) ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		$svc = self::dashboard_find_service( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		$r = SimpleVPBot_Service_Renew::apply_reduce_days_free( $sid, $days );
		if ( empty( $r['ok'] ) ) {
			return array( 'ok' => false, 'reason' => (string) ( $r['message'] ?? 'failed' ) );
		}
		self::log_rest_user( (int) $svc->user_id, 'service_reduce_days', array( 'service_id' => $sid, 'days' => $days ) );
		self::configs_sync_after_service_panel_change( $sid );
		return array( 'ok' => true );
	}

	/**
	 * @param array<string, mixed> $p service_id, reduce_users.
	 * @return array{ok:bool, reason?:string}
	 */
	private static function op_user_service_reduce_slots( array $p ) {
		$sid = (int) ( $p['service_id'] ?? 0 );
		$n   = (int) ( $p['reduce_users'] ?? $p['extra_users'] ?? 0 );
		if ( $sid < 1 || $n < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		$svc = self::dashboard_find_service( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		$res = SimpleVPBot_Admin_User_Ops::admin_reduce_user_slots( $sid, $n, 'free' );
		if ( empty( $res['ok'] ) ) {
			return array( 'ok' => false, 'reason' => (string) ( $res['reason'] ?? 'failed' ) );
		}
		self::log_rest_user( (int) $svc->user_id, 'service_reduce_user_slots', array( 'service_id' => $sid, 'reduce_users' => $n ) );
		self::configs_sync_after_service_panel_change( $sid );
		return array( 'ok' => true );
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
		$svc = self::dashboard_find_service( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		$actor = self::dashboard_reseller_actor_id( $p );
		if ( $actor > 0 && class_exists( 'SimpleVPBot_Service_Transfer' ) && class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			$owner_uid = (int) ( $svc->user_id ?? 0 );
			if ( $owner_uid < 1 || ! SimpleVPBot_Bot_Reseller_Scope::reseller_may_moderate_user_for( $actor, $owner_uid ) ) {
				return array( 'ok' => false, 'message' => 'forbidden_scope' );
			}
			$transfer_target = SimpleVPBot_Service_Transfer::resolve_user( $tgt );
			if ( ! $transfer_target || ! SimpleVPBot_Bot_Reseller_Scope::reseller_may_moderate_user_for( $actor, (int) $transfer_target->id ) ) {
				return array( 'ok' => false, 'message' => 'forbidden_scope' );
			}
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

		$parent_actor = 0;
		if ( ! self::mutate_is_unrestricted_site_admin() ) {
			$actor = self::dashboard_reseller_actor_id( $p );
			if ( $actor > 0 ) {
				$ar = SimpleVPBot_Model_User::find( $actor );
				if ( $ar && SimpleVPBot_Model_User::is_reseller_row( $ar ) ) {
					$parent_actor = $actor;
				}
			}
		}
		if ( 'reseller' === $role && $parent_actor > 0 ) {
			$invited_by = $parent_actor;
			if ( 'pending' === $st ) {
				$st = 'approved';
			}
		}

		$dash_pwd  = (string) ( $p['dashboard_password'] ?? '' );
		$uname_raw = sanitize_text_field( (string) ( $p['username'] ?? '' ) );
		$log       = sanitize_user( $uname_raw, true );

		if ( 'reseller' === $role && strlen( $dash_pwd ) >= 6 && '' !== $log ) {
			if ( ! self::mutate_is_unrestricted_site_admin() && $parent_actor < 1 ) {
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
			self::log_rest_user(
				$new_id,
				'user_manual_create',
				array(
					'dashboard_login'    => true,
					'wp_user_id'         => $wp_id,
					'parent_reseller_id' => $parent_actor > 0 ? $parent_actor : null,
				)
			);
			return array( 'ok' => true, 'user_id' => $new_id );
		}

		if ( strlen( $dash_pwd ) >= 6 && '' !== $log && 'reseller' !== $role ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}

		if ( 'reseller' === $role && ! self::mutate_is_unrestricted_site_admin() && $parent_actor < 1 ) {
			$role = 'user';
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
		self::log_rest_user(
			$new_id,
			'user_manual_create',
			array(
				'tg_user_id'         => $tg,
				'bale_user_id'       => $bl,
				'parent_reseller_id' => ( $parent_actor > 0 && 'reseller' === $role ) ? $parent_actor : null,
			)
		);
		return array( 'ok' => true, 'user_id' => $new_id );
	}

	/**
	 * Preview merge (admin dashboard).
	 *
	 * @param array<string, mixed> $p keep_id, drop_id.
	 * @return array{ok:bool, message?:string, data?:mixed}
	 */
	private static function op_user_merge_preview( array $p ) {
		if ( ! self::mutate_is_unrestricted_site_admin() ) {
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
		if ( ! self::mutate_is_unrestricted_site_admin() ) {
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
		$txt = str_replace( array( "\r\n", "\r" ), "\n", $txt );
		$txt = preg_replace( '/[ \t]+/u', ' ', $txt );
		$txt = trim( $txt );
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
		if ( ! self::actor_may_moderate_user( $p, $uid ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$owner_rid = (int) ( $p['owner_svp_user_id'] ?? 0 );
		if ( class_exists( 'SimpleVPBot_Platforms' ) ) {
			if ( ( 'both' === $ch || 'telegram' === $ch ) && ! SimpleVPBot_Platforms::is_enabled( 'telegram', $owner_rid ) ) {
				if ( 'telegram' === $ch ) {
					return array( 'ok' => false, 'message' => 'channel_disabled' );
				}
				$ch = 'bale';
			}
			if ( ( 'both' === $ch || 'bale' === $ch ) && ! SimpleVPBot_Platforms::is_enabled( 'bale', $owner_rid ) ) {
				if ( 'bale' === $ch ) {
					return array( 'ok' => false, 'message' => 'channel_disabled' );
				}
				$ch = 'telegram';
			}
		}
		$sent = 0;
		if ( class_exists( 'SimpleVPBot_User_Notify' ) ) {
			$sent = SimpleVPBot_User_Notify::send_to_user( $user, $txt, array(), null, $ch );
		} elseif ( class_exists( 'SimpleVPBot_Bot_Runtime' ) ) {
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
		$row = self::dashboard_find_service( $sid );
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
	 * @param array<string, mixed> $p service_id, service_note (max 512).
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_service_set_note( array $p ) {
		$sid = (int) ( $p['service_id'] ?? 0 );
		if ( $sid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_service' );
		}
		$row = self::dashboard_find_service( $sid );
		if ( ! $row ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		$note = isset( $p['service_note'] ) ? sanitize_text_field( (string) $p['service_note'] ) : '';
		if ( function_exists( 'mb_substr' ) ) {
			$note = mb_substr( $note, 0, 512 );
		} else {
			$note = substr( $note, 0, 512 );
		}
		$patch = class_exists( 'SimpleVPBot_Service_Naming' ) && SimpleVPBot_Service_Naming::is_platform_slug_service( $row )
			? array( 'service_note' => $note )
			: array( 'remark' => $note );
		SimpleVPBot_Model_Service::update( $sid, $patch );
		$uid = (int) $row->user_id;
		self::log_rest_user( $uid, 'service_set_note', array( 'service_id' => $sid ) );
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
		$svc = self::dashboard_find_service( $sid );
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
		$svc = self::dashboard_find_service( $sid );
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
	 * @return array{ok:bool, reason?:string, sub_id?:string}
	 */
	private static function op_service_regen_sub_id( array $p ) {
		$sid = (int) ( $p['service_id'] ?? 0 );
		if ( $sid < 1 || ! class_exists( 'SimpleVPBot_Service_Dashboard_Panel' ) ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		$svc = self::dashboard_find_service( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		$r = SimpleVPBot_Service_Dashboard_Panel::xray_regenerate_sub_id( $sid );
		if ( empty( $r['ok'] ) ) {
			return array( 'ok' => false, 'reason' => (string) ( $r['reason'] ?? 'failed' ) );
		}
		self::log_rest_user( (int) $svc->user_id, 'service_regen_sub_id', array( 'service_id' => $sid ) );
		return array(
			'ok'     => true,
			'sub_id' => (string) ( $r['sub_id'] ?? '' ),
		);
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
		$svc = self::dashboard_find_service( $sid );
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
		$svc = self::dashboard_find_service( $sid );
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
		$sid  = (int) ( $p['service_id'] ?? 0 );
		$add  = (int) ( $p['extra_users'] ?? $p['slots'] ?? 0 );
		$mode = sanitize_key( (string) ( $p['mode'] ?? 'free' ) );
		if ( $sid < 1 || $add < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		$svc = self::dashboard_find_service( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		$scope = self::invoice_card_scope_reseller_from_mutate( $p );
		$res   = SimpleVPBot_Admin_User_Ops::admin_add_user_slots( $sid, $add, $mode, $scope );
		if ( empty( $res['ok'] ) ) {
			return array( 'ok' => false, 'reason' => (string) ( $res['reason'] ?? 'failed' ) );
		}
		self::log_rest_user( (int) $svc->user_id, 'service_add_user_slots', array( 'service_id' => $sid, 'extra_users' => $add, 'mode' => $mode ) );
		if ( (int) ( $res['transaction_id'] ?? 0 ) < 1 ) {
			self::configs_sync_after_service_panel_change( $sid );
		}
		return array(
			'ok'             => true,
			'transaction_id' => (int) ( $res['transaction_id'] ?? 0 ),
		);
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
		$svc = self::dashboard_find_service( $sid );
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
		$actor = self::dashboard_reseller_actor_id( $p );
		if ( $actor > 0 ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$pid    = (int) ( $p['panel_id'] ?? 0 );
		$expect = (int) ( $p['confirm_count'] ?? -1 );
		return SimpleVPBot_Service_Admin_Ops::configs_delete_expired_linked_batch( $pid, $expect );
	}

	/**
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool, message?:string, data?:mixed}
	 */
	private static function op_purge_expired_run_cron( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Cron_Purge_Expired' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$limit = max( 1, min( 100, (int) ( $p['limit'] ?? SimpleVPBot_Cron_Purge_Expired::BATCH_LIMIT ) ) );
		$data  = SimpleVPBot_Cron_Purge_Expired::run_batch( $limit, 'manual', true );
		return array(
			'ok'      => true,
			'message' => 'ok',
			'data'    => $data,
		);
	}

	/**
	 * @param array<string, mixed> $p Params.
	 * @return array{ok:bool, message?:string, data?:mixed}
	 */
	private static function op_purge_expired_purge_ready( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Cron_Purge_Expired' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		if ( empty( $p['confirm'] ) ) {
			return array( 'ok' => false, 'message' => 'confirm_required' );
		}
		$limit = max( 1, min( 100, (int) ( $p['limit'] ?? 50 ) ) );
		$data  = SimpleVPBot_Cron_Purge_Expired::purge_ready_batch( $limit );
		return array(
			'ok'      => true,
			'message' => 'ok',
			'data'    => $data,
		);
	}

	/**
	 * @param array<string, mixed> $p service_id, optional force_early.
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_purge_expired_purge_one( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Cron_Purge_Expired' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$sid = (int) ( $p['service_id'] ?? 0 );
		if ( $sid < 1 ) {
			return array( 'ok' => false, 'message' => 'bad_params' );
		}
		return SimpleVPBot_Cron_Purge_Expired::purge_service_by_id(
			$sid,
			array(
				'force_early' => ! empty( $p['force_early'] ),
			)
		);
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
		$guard = self::require_panel_client_moderation_for_actor( $p );
		if ( is_array( $guard ) ) {
			return $guard;
		}
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
		$batch_guard = self::require_configs_batch_items_moderation( $p, $pid, $items );
		if ( is_array( $batch_guard ) ) {
			return $batch_guard;
		}
		$r = SimpleVPBot_Service_Admin_Ops::configs_clients_batch( $pid, $op, $items );
		if ( ! empty( $r['ok'] ) ) {
			self::audit_configs_batch_items(
				$p,
				$pid,
				'configs_clients_batch',
				$items,
				array( 'batch_op' => $op )
			);
		}
		return $r;
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
		$batch_guard = self::require_configs_batch_items_moderation( $p, $pid, $items );
		if ( is_array( $batch_guard ) ) {
			return $batch_guard;
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
	 * Dashboard reseller may queue bulk jobs only with users.bulk permission.
	 *
	 * @param array<string, mixed> $p Params (optional __actor_svp_user_id).
	 * @return bool
	 */
	private static function users_bulk_actor_may_use( array $p ) {
		if ( self::mutate_is_unrestricted_site_admin() ) {
			return true;
		}
		$actor = (int) ( $p['__actor_svp_user_id'] ?? 0 );
		if ( $actor < 1 ) {
			return false;
		}
		$row = SimpleVPBot_Model_User::find( $actor );
		if ( ! $row || ! SimpleVPBot_Model_User::is_reseller_row( $row ) ) {
			return false;
		}
		$perms = SimpleVPBot_Model_User::reseller_permissions( $actor );
		return ! empty( $perms['users.bulk'] );
	}

	/**
	 * Reseller may only cancel/resume own bulk jobs.
	 *
	 * @param array<string, mixed> $p Params.
	 * @param int                  $job_id Job id.
	 * @return bool
	 */
	private static function users_bulk_job_actor_must_own( array $p, $job_id ) {
		if ( self::mutate_is_unrestricted_site_admin() ) {
			return true;
		}
		$actor = (int) ( $p['__actor_svp_user_id'] ?? 0 );
		if ( $actor < 1 || ! self::users_bulk_actor_may_use( $p ) ) {
			return false;
		}
		return SimpleVPBot_Model_Users_Bulk_Job::job_visible_to_svp_actor( (int) $job_id, $actor );
	}

	/**
	 * @param array<string, mixed> $p Mutation params.
	 * @return int
	 */
	private static function users_bulk_created_by_svp( array $p ) {
		$a = (int) ( $p['__actor_svp_user_id'] ?? 0 );
		return $a > 0 ? $a : 0;
	}

	/**
	 * Panel/inbound filter from bulk mutation params.
	 *
	 * @param array<string, mixed> $p Params.
	 * @return array{panel_id:int, inbound_id:int}
	 */
	private static function users_bulk_panel_filter( array $p ) {
		$panel_id   = max( 0, (int) ( $p['panel_id'] ?? 0 ) );
		$inbound_id = max( 0, (int) ( $p['inbound_id'] ?? 0 ) );
		if ( $panel_id < 1 ) {
			$inbound_id = 0;
		}
		return array(
			'panel_id'   => $panel_id,
			'inbound_id' => $inbound_id,
		);
	}

	/**
	 * Base payload fields stored on every bulk job (panel filter).
	 *
	 * @param array<string, mixed> $p Params.
	 * @return array<string, int>
	 */
	private static function users_bulk_payload_base( array $p ) {
		$f = self::users_bulk_panel_filter( $p );
		$out = array(
			'panel_id'   => $f['panel_id'],
			'inbound_id' => $f['inbound_id'],
		);
		$actor = self::dashboard_reseller_actor_id( $p );
		if ( $actor > 0 ) {
			$out['__actor_svp_user_id'] = $actor;
		}
		return $out;
	}

	/**
	 * Notify flags stored on volume/extend/slots bulk jobs.
	 *
	 * @param array<string, mixed> $p Request params.
	 * @return array{notify:int, notify_message:string}
	 */
	private static function users_bulk_notify_fields( array $p ) {
		$notify = array_key_exists( 'notify', $p )
			? (bool) $p['notify']
			: ! (bool) SimpleVPBot_Settings::get( 'suppress_bulk_user_notifications', false );
		$msg    = isset( $p['notify_message'] ) ? sanitize_textarea_field( (string) $p['notify_message'] ) : '';
		if ( strlen( $msg ) > 3500 ) {
			$msg = function_exists( 'mb_substr' ) ? mb_substr( $msg, 0, 3500 ) : substr( $msg, 0, 3500 );
		}
		return array(
			'notify'         => $notify ? 1 : 0,
			'notify_message' => $msg,
		);
	}

	/**
	 * Display name for bulk notify placeholders.
	 *
	 * @param object|null $user svp_users row.
	 * @return string
	 */
	public static function users_bulk_user_display_name( $user ) {
		return class_exists( 'SimpleVPBot_User_Display' )
			? SimpleVPBot_User_Display::name( $user )
			: 'کاربر';
	}

	/**
	 * Render bulk service-op notify text (custom or default template).
	 *
	 * @param string               $op      volume|extend|slots.
	 * @param object|null          $user    svp_users row.
	 * @param array<string, mixed> $payload Job payload.
	 * @return string
	 */
	public static function users_bulk_render_notify_message( $op, $user, array $payload ) {
		$op = sanitize_key( (string) $op );
		$vars = array(
			'name'        => self::users_bulk_user_display_name( $user ),
			'extra_gb'    => (string) max( 1, (int) ( $payload['extra_gb'] ?? 1 ) ),
			'days'        => (string) max( 1, (int) ( $payload['days'] ?? 1 ) ),
			'extra_users' => (string) max( 1, (int) ( $payload['extra_users'] ?? 1 ) ),
		);
		$custom = trim( (string) ( $payload['notify_message'] ?? '' ) );
		if ( '' !== $custom ) {
			$tpl = $custom;
		} elseif ( 'volume' === $op ) {
			$tpl = SimpleVPBot_Texts::get(
				'msg.dashboard_bulk_volume',
				'{name} عزیز به اشتراک‌های فعال شما {extra_gb} گیگ اضافه شد.'
			);
		} elseif ( 'extend' === $op ) {
			$tpl = SimpleVPBot_Texts::get(
				'msg.dashboard_bulk_extend',
				'{name} عزیز به اشتراک‌های فعال شما {days} روز اضافه شد.'
			);
		} elseif ( 'slots' === $op ) {
			$tpl = SimpleVPBot_Texts::get(
				'msg.dashboard_bulk_slots',
				'{name} عزیز محدودیت کاربر هم‌زمان اشتراک‌های فعال شما {extra_users} نفر افزایش یافت.'
			);
		} else {
			return '';
		}
		return SimpleVPBot_Texts::format( $tpl, $vars );
	}

	/**
	 * Send one notify per user after successful bulk service op (best-effort).
	 *
	 * @param int                  $user_id     svp_users.id.
	 * @param string               $op          volume|extend|slots.
	 * @param array<string, mixed> $payload     Job payload.
	 * @param bool                 $had_success At least one service action succeeded.
	 * @return void
	 */
	public static function users_bulk_maybe_notify_service_op( $user_id, $op, array $payload, $had_success ) {
		if ( ! $had_success || empty( $payload['notify'] ) ) {
			return;
		}
		if ( ! empty( $payload['reduce'] ) ) {
			return;
		}
		$op = sanitize_key( (string) $op );
		if ( ! in_array( $op, array( 'volume', 'extend', 'slots' ), true ) ) {
			return;
		}
		$user = SimpleVPBot_Model_User::find( (int) $user_id );
		if ( ! $user ) {
			return;
		}
		$body = self::users_bulk_render_notify_message( $op, $user, $payload );
		if ( '' === trim( $body ) ) {
			return;
		}
		if ( class_exists( 'SimpleVPBot_User_Notify' ) ) {
			SimpleVPBot_User_Notify::send_to_user( $user, $body );
		}
	}

	/**
	 * Keep only users who have at least one service on panel (optional inbound).
	 *
	 * @param array<int, int> $user_ids User ids.
	 * @param int               $panel_id Panel id.
	 * @param int               $inbound_id Inbound id (0 = all on panel).
	 * @return array<int, int>
	 */
	private static function users_bulk_users_with_panel_services( array $user_ids, $panel_id, $inbound_id ) {
		global $wpdb;
		$user_ids = array_values( array_unique( array_map( 'intval', $user_ids ) ) );
		if ( $panel_id < 1 || empty( $user_ids ) ) {
			return $user_ids;
		}
		$s_tbl  = SimpleVPBot_Model_Service::table();
		$in_ids = implode( ',', $user_ids );
		$sql    = "SELECT DISTINCT user_id FROM {$s_tbl} WHERE deleted_at IS NULL AND user_id IN ({$in_ids}) AND panel_id = %d";
		$args   = array( $panel_id );
		if ( $inbound_id > 0 ) {
			$sql   .= ' AND inbound_id = %d';
			$args[] = $inbound_id;
		}
		$rows = $wpdb->get_col( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$hit  = array_flip( array_map( 'intval', (array) $rows ) );
		return array_values(
			array_filter(
				$user_ids,
				static function ( $uid ) use ( $hit ) {
					return isset( $hit[ (int) $uid ] );
				}
			)
		);
	}

	/**
	 * @param array<int, int>     $user_ids Users.
	 * @param array<string, int> $filter panel_id, inbound_id.
	 * @param bool                $active_only Only non-expired services.
	 * @return int
	 */
	private static function users_bulk_count_matching_services( array $user_ids, array $filter, $active_only = false, array $panel_payload = array() ) {
		global $wpdb;
		$user_ids = array_values( array_unique( array_map( 'intval', $user_ids ) ) );
		if ( empty( $user_ids ) ) {
			return 0;
		}
		$s_tbl  = SimpleVPBot_Model_Service::table();
		$in_ids = implode( ',', $user_ids );
		$sql    = "SELECT COUNT(*) FROM {$s_tbl} WHERE deleted_at IS NULL AND user_id IN ({$in_ids})";
		$args   = array();
		if ( $active_only ) {
			$sql .= ' AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())';
		}
		$panel_id = (int) ( $filter['panel_id'] ?? 0 );
		if ( $panel_id > 0 ) {
			$sql    .= ' AND panel_id = %d';
			$args[] = $panel_id;
			$inbound_id = (int) ( $filter['inbound_id'] ?? 0 );
			if ( $inbound_id > 0 ) {
				$sql    .= ' AND inbound_id = %d';
				$args[] = $inbound_id;
			}
		}
		if ( ! empty( $panel_payload ) ) {
			self::users_bulk_append_panel_allowlist_sql( $panel_payload, $sql, $args );
		}
		if ( empty( $args ) ) {
			return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Dry-run response fragment with user/service counts.
	 *
	 * @param array<int, int>     $user_ids Users.
	 * @param array<string, mixed> $p       Params.
	 * @param bool                 $active_only Count only active services.
	 * @return array<string, mixed>
	 */
	private static function users_bulk_dry_run_data( array $user_ids, array $p, $active_only = false ) {
		$f = self::users_bulk_panel_filter( $p );
		return array(
			'dry_run'         => true,
			'user_count'      => count( $user_ids ),
			'service_count'   => self::users_bulk_count_matching_services( $user_ids, $f, $active_only, self::users_bulk_payload_base( $p ) ),
			'panel_id'        => $f['panel_id'],
			'inbound_id'      => $f['inbound_id'],
			'sample_user_ids' => array_slice( $user_ids, 0, 15 ),
		);
	}

	/**
	 * Volume/extend bulk uses panel-active clients instead of DB service rows.
	 *
	 * @param string $scope Scope key.
	 * @param string $op    volume|extend.
	 * @return bool
	 */
	private static function users_bulk_uses_panel_targets( $scope, $op ) {
		$scope = sanitize_key( (string) $scope );
		$op    = sanitize_key( (string) $op );
		if ( ! in_array( $op, array( 'volume', 'extend' ), true ) ) {
			return false;
		}
		return in_array( $scope, array( 'panel_active_clients', 'approved_with_active_service' ), true );
	}

	/**
	 * Panel ids a reseller actor may touch (null = all panels for site admin).
	 *
	 * @param int $actor_uid svp_users.id or 0.
	 * @return array<int, int>|null
	 */
	private static function users_bulk_actor_panel_ids( $actor_uid ) {
		$actor_uid = (int) $actor_uid;
		if ( $actor_uid < 1 ) {
			return null;
		}
		$out = array();
		if ( class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' ) ) {
			foreach ( SimpleVPBot_Model_Reseller_Panel_Price::list_for_reseller( $actor_uid ) as $row ) {
				$pid = (int) ( $row->panel_id ?? 0 );
				if ( $pid > 0 && SimpleVPBot_Model_Reseller_Panel_Price::has_panel_access( $actor_uid, $pid ) ) {
					$out[] = $pid;
				}
			}
		}
		if ( class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Line' ) ) {
			foreach ( SimpleVPBot_Model_Reseller_Wholesale_Line::lines_for_reseller( $actor_uid ) as $wl ) {
				$pid = (int) ( $wl->panel_id ?? 0 );
				if ( $pid > 0 && SimpleVPBot_Model_Reseller_Wholesale_Line::reseller_can_use_panel( $actor_uid, $pid ) ) {
					$out[] = $pid;
				}
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Resolve bot user id for a cached panel client row.
	 *
	 * @param array<string, mixed> $row panel_id, inbound_id, email, tg_id?.
	 * @return int
	 */
	public static function users_bulk_user_id_for_panel_row( array $row ) {
		$pid = (int) ( $row['panel_id'] ?? 0 );
		$iid = (int) ( $row['inbound_id'] ?? 0 );
		$em  = trim( (string) ( $row['email'] ?? '' ) );
		if ( $iid > 0 && '' !== $em ) {
			$svc = SimpleVPBot_Model_Service::find_by_inbound_email( $iid, $em, $pid );
			if ( $svc && (int) ( $svc->user_id ?? 0 ) > 0 ) {
				return (int) $svc->user_id;
			}
		}
		$tg = trim( (string) ( $row['tg_id'] ?? '' ) );
		if ( '' !== $tg && ctype_digit( $tg ) ) {
			$u = SimpleVPBot_Model_User::find_by_telegram( (int) $tg );
			if ( $u ) {
				return (int) $u->id;
			}
		}
		return 0;
	}

	/**
	 * Active panel clients from cache (enable + not expired on panel).
	 *
	 * @param array<string, mixed> $p scope, panel_id?, inbound_id?, __actor_svp_user_id?.
	 * @return array{ok:bool, targets?:array<int,array<string,mixed>>, message?:string}
	 */
	public static function users_bulk_resolve_panel_targets( array $p ) {
		global $wpdb;
		if ( ! class_exists( 'SimpleVPBot_Model_Panel_Inbound_Client' ) ) {
			return array( 'ok' => true, 'targets' => array() );
		}
		$c_tbl  = SimpleVPBot_Model_Panel_Inbound_Client::table();
		$now_ms = (int) round( microtime( true ) * 1000 );
		$f      = self::users_bulk_panel_filter( $p );
		$actor  = (int) ( $p['__actor_svp_user_id'] ?? 0 );
		$allowed = self::users_bulk_actor_panel_ids( $actor );
		if ( is_array( $allowed ) && empty( $allowed ) ) {
			return array( 'ok' => true, 'targets' => array() );
		}
		if ( $f['panel_id'] > 0 && is_array( $allowed ) && ! in_array( $f['panel_id'], $allowed, true ) ) {
			return array( 'ok' => false, 'message' => 'forbidden_panel' );
		}
		$sql  = "SELECT panel_id, inbound_id, email, tg_id FROM {$c_tbl} WHERE enable = 1 AND (expiry_ms = 0 OR expiry_ms > %d)";
		$args = array( $now_ms );
		if ( $f['panel_id'] > 0 ) {
			$sql   .= ' AND panel_id = %d';
			$args[] = $f['panel_id'];
		} elseif ( is_array( $allowed ) ) {
			$ph   = implode( ',', array_fill( 0, count( $allowed ), '%d' ) );
			$sql .= " AND panel_id IN ({$ph})";
			$args = array_merge( $args, $allowed );
		}
		if ( $f['inbound_id'] > 0 ) {
			$sql   .= ' AND inbound_id = %d';
			$args[] = $f['inbound_id'];
		}
		$sql .= ' ORDER BY panel_id ASC, inbound_id ASC, email ASC LIMIT 500';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		$targets = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$targets[] = array(
				'panel_id'   => (int) ( $row['panel_id'] ?? 0 ),
				'inbound_id' => (int) ( $row['inbound_id'] ?? 0 ),
				'email'      => (string) ( $row['email'] ?? '' ),
				'user_id'    => self::users_bulk_user_id_for_panel_row( $row ),
			);
		}
		$actor = self::dashboard_reseller_actor_id( $p );
		if ( $actor > 0 && class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) && ! empty( $targets ) ) {
			$scope_ids  = SimpleVPBot_Bot_Reseller_Scope::effective_moderatable_user_ids( $actor );
			$scope_flip = array_flip( $scope_ids );
			$targets    = array_values(
				array_filter(
					$targets,
					static function ( $t ) use ( $scope_flip ) {
						$uid = (int) ( $t['user_id'] ?? 0 );
						return $uid > 0 && isset( $scope_flip[ $uid ] );
					}
				)
			);
		}
		return array( 'ok' => true, 'targets' => $targets );
	}

	/**
	 * Dry-run stats for panel-target bulk jobs.
	 *
	 * @param array<int, array<string, mixed>> $targets Panel targets.
	 * @param array<string, mixed>            $p       Params.
	 * @return array<string, mixed>
	 */
	private static function users_bulk_dry_run_panel_data( array $targets, array $p ) {
		$f = self::users_bulk_panel_filter( $p );
		$user_ids = array();
		foreach ( $targets as $t ) {
			$uid = (int) ( $t['user_id'] ?? 0 );
			if ( $uid > 0 ) {
				$user_ids[] = $uid;
			}
		}
		$user_ids = array_values( array_unique( $user_ids ) );
		return array(
			'dry_run'          => true,
			'target_mode'      => 'panel_client',
			'panel_target_count' => count( $targets ),
			'user_count'       => count( $user_ids ),
			'service_count'    => count( $targets ),
			'panel_id'         => $f['panel_id'],
			'inbound_id'       => $f['inbound_id'],
			'sample_targets'   => array_slice( $targets, 0, 10 ),
		);
	}

	/**
	 * Service ids for one user matching panel/inbound filter (used by bulk worker).
	 *
	 * @param int                  $user_id User id.
	 * @param array<string, mixed> $payload Job payload (panel_id, inbound_id).
	 * @param bool                 $active_only Only non-expired.
	 * @return array<int, int>
	 */
	public static function users_bulk_service_ids_for_user( $user_id, array $payload, $active_only = false ) {
		global $wpdb;
		$user_id = (int) $user_id;
		if ( $user_id < 1 ) {
			return array();
		}
		$s_tbl    = SimpleVPBot_Model_Service::table();
		$sql      = "SELECT id FROM {$s_tbl} WHERE deleted_at IS NULL AND user_id = %d";
		$args     = array( $user_id );
		if ( $active_only ) {
			$sql .= ' AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())';
		}
		$panel_id = (int) ( $payload['panel_id'] ?? 0 );
		if ( $panel_id > 0 ) {
			$sql    .= ' AND panel_id = %d';
			$args[] = $panel_id;
			$inbound_id = (int) ( $payload['inbound_id'] ?? 0 );
			if ( $inbound_id > 0 ) {
				$sql    .= ' AND inbound_id = %d';
				$args[] = $inbound_id;
			}
		} else {
			self::users_bulk_append_panel_allowlist_sql( $payload, $sql, $args );
		}
		$rows = $wpdb->get_col( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array_map( 'intval', (array) $rows );
	}

	/**
	 * @param array<int, int>     $ids User ids.
	 * @param array<string, mixed> $p  Params.
	 * @return array<int, int>
	 */
	private static function users_bulk_finalize_user_ids( array $ids, array $p ) {
		$f = self::users_bulk_panel_filter( $p );
		if ( $f['panel_id'] < 1 ) {
			return $ids;
		}
		return self::users_bulk_users_with_panel_services( $ids, $f['panel_id'], $f['inbound_id'] );
	}

	/**
	 * Resolve user IDs for bulk ops (global admin or reseller subtree).
	 *
	 * @param array<string, mixed> $p scope, user_ids?, __actor_svp_user_id?.
	 * @return array{ok:bool, ids?:array<int,int>, message?:string}
	 */
	private static function users_bulk_resolve_user_ids( array $p ) {
		global $wpdb;
		$scope = sanitize_key( (string) ( $p['scope'] ?? 'all_approved' ) );
		$t     = SimpleVPBot_Model_User::table();
		$actor = (int) ( $p['__actor_svp_user_id'] ?? 0 );
		$scope_frag = null;
		if ( $actor > 0 ) {
			$scope_frag = SimpleVPBot_Model_User::reseller_moderation_scope_clause( $actor, 'u' );
			if ( ! $scope_frag ) {
				return array( 'ok' => true, 'ids' => array() );
			}
		}

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
			if ( $actor > 0 && ! empty( $ids ) ) {
				$scope_ids = class_exists( 'SimpleVPBot_Bot_Reseller_Scope' )
					? SimpleVPBot_Bot_Reseller_Scope::effective_moderatable_user_ids( $actor )
					: SimpleVPBot_Model_User::reseller_scope_user_ids( $actor );
				$allowed   = array_flip( $scope_ids );
				$ids       = array_values(
					array_filter(
						$ids,
						static function ( $id ) use ( $allowed ) {
							return isset( $allowed[ (int) $id ] );
						}
					)
				);
			}
			if ( ! empty( $ids ) ) {
				$ph    = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
				$sql   = "SELECT id FROM {$t} WHERE id IN ({$ph}) AND status = 'approved' AND role <> %s";
				$args  = array_merge( $ids, array( 'reseller' ) );
				$ids   = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, $args ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
			return array( 'ok' => true, 'ids' => self::users_bulk_finalize_user_ids( $ids, $p ) );
		}
		if ( 'all_approved' === $scope ) {
			if ( $scope_frag ) {
				$sql  = "SELECT u.id FROM {$t} u WHERE u.status = 'approved' AND u.role <> 'reseller' {$scope_frag['sql']} ORDER BY u.id ASC LIMIT 500";
				$rows = $wpdb->get_col( $wpdb->prepare( $sql, $scope_frag['values'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			} else {
				$rows = $wpdb->get_col( "SELECT id FROM {$t} WHERE status = 'approved' AND role <> 'reseller' ORDER BY id ASC LIMIT 500" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			return array(
				'ok'  => true,
				'ids' => self::users_bulk_finalize_user_ids( array_map( 'intval', (array) $rows ), $p ),
			);
		}
		if ( 'approved_with_active_service' === $scope ) {
			$s = SimpleVPBot_Model_Service::table();
			if ( $scope_frag ) {
				$sql  = "SELECT DISTINCT u.id FROM {$t} u INNER JOIN {$s} s ON s.user_id = u.id AND s.deleted_at IS NULL AND (s.expires_at IS NULL OR s.expires_at > UTC_TIMESTAMP()) WHERE u.status = 'approved' AND u.role <> 'reseller' {$scope_frag['sql']} ORDER BY u.id ASC LIMIT 500";
				$rows = $wpdb->get_col( $wpdb->prepare( $sql, $scope_frag['values'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			} else {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$sql  = "SELECT DISTINCT u.id FROM {$t} u INNER JOIN {$s} s ON s.user_id = u.id AND s.deleted_at IS NULL AND (s.expires_at IS NULL OR s.expires_at > UTC_TIMESTAMP()) WHERE u.status = 'approved' AND u.role <> 'reseller' ORDER BY u.id ASC LIMIT 500";
				$rows = $wpdb->get_col( $sql );
			}
			return array(
				'ok'  => true,
				'ids' => self::users_bulk_finalize_user_ids( array_map( 'intval', (array) $rows ), $p ),
			);
		}
		if ( 'panel_active_clients' === $scope ) {
			return array( 'ok' => true, 'ids' => array() );
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
		if ( ! self::users_bulk_actor_may_use( $p ) ) {
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
			$dry_data = self::users_bulk_dry_run_data( $ids, $p, false );
			return array( 'ok' => true, 'data' => $dry_data );
		}
		$jid = SimpleVPBot_Model_Users_Bulk_Job::insert_job(
			array(
				'operation'               => 'wallet',
				'scope'                   => sanitize_key( (string) ( $p['scope'] ?? 'all_approved' ) ),
				'payload_json'            => wp_json_encode(
					array_merge(
						self::users_bulk_payload_base( $p ),
						array(
							'delta'  => $delta,
							'notify' => $notify ? 1 : 0,
						)
					),
					JSON_UNESCAPED_UNICODE
				),
				'status'                  => 'pending',
				'created_by_wp'         => get_current_user_id(),
				'created_by_svp_user_id' => self::users_bulk_created_by_svp( $p ),
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
		if ( ! self::users_bulk_actor_may_use( $p ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$scope  = sanitize_key( (string) ( $p['scope'] ?? 'all_approved' ) );
		$gb     = max( 1, (int) ( $p['extra_gb'] ?? $p['volume_gb'] ?? 0 ) );
		$reduce = ! empty( $p['reduce'] );
		$dry    = ! empty( $p['dry_run'] );
		if ( $gb < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_gb' );
		}
		if ( self::users_bulk_uses_panel_targets( $scope, 'volume' ) ) {
			$res_targets = self::users_bulk_resolve_panel_targets( $p );
			if ( empty( $res_targets['ok'] ) ) {
				return array( 'ok' => false, 'message' => (string) ( $res_targets['message'] ?? 'bad_scope' ) );
			}
			$targets = $res_targets['targets'] ?? array();
			if ( empty( $targets ) ) {
				return array( 'ok' => false, 'message' => 'empty_scope' );
			}
			if ( $dry ) {
				return array(
					'ok'   => true,
					'data' => self::users_bulk_dry_run_panel_data( $targets, $p ),
				);
			}
			$jid = SimpleVPBot_Model_Users_Bulk_Job::insert_job(
				array(
					'operation'                => 'volume',
					'scope'                    => $scope,
					'payload_json'             => wp_json_encode(
						array_merge(
							self::users_bulk_payload_base( $p ),
							self::users_bulk_notify_fields( $p ),
							array(
								'target_mode' => 'panel_client',
								'extra_gb'    => $gb,
								'reduce'      => $reduce ? 1 : 0,
							)
						),
						JSON_UNESCAPED_UNICODE
					),
					'status'                   => 'pending',
					'created_by_wp'            => get_current_user_id(),
					'created_by_svp_user_id'  => self::users_bulk_created_by_svp( $p ),
				)
			);
			$queued = SimpleVPBot_Model_Users_Bulk_Job::enqueue_panel_targets( $jid, $targets );
			return array(
				'ok'   => true,
				'data' => array(
					'job_id' => $jid,
					'queued' => $queued,
				),
			);
		}
		$res_ids = self::users_bulk_resolve_user_ids( $p );
		if ( empty( $res_ids['ok'] ) ) {
			return array( 'ok' => false, 'message' => (string) ( $res_ids['message'] ?? 'bad_scope' ) );
		}
		$user_ids = $res_ids['ids'];
		if ( empty( $user_ids ) ) {
			return array( 'ok' => false, 'message' => 'empty_scope' );
		}
		if ( $dry ) {
			return array(
				'ok'   => true,
				'data' => self::users_bulk_dry_run_data( $user_ids, $p, true ),
			);
		}
		$jid = SimpleVPBot_Model_Users_Bulk_Job::insert_job(
			array(
				'operation'               => 'volume',
				'scope'                   => $scope,
				'payload_json'            => wp_json_encode(
					array_merge(
						self::users_bulk_payload_base( $p ),
						self::users_bulk_notify_fields( $p ),
						array(
							'extra_gb' => $gb,
							'reduce'   => $reduce ? 1 : 0,
						)
					),
					JSON_UNESCAPED_UNICODE
				),
				'status'                  => 'pending',
				'created_by_wp'         => get_current_user_id(),
				'created_by_svp_user_id' => self::users_bulk_created_by_svp( $p ),
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
		if ( ! self::users_bulk_actor_may_use( $p ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$scope  = sanitize_key( (string) ( $p['scope'] ?? 'all_approved' ) );
		$days   = max( 1, min( 3650, (int) ( $p['days'] ?? 0 ) ) );
		$reduce = ! empty( $p['reduce'] );
		$dry    = ! empty( $p['dry_run'] );
		if ( $days < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_days' );
		}
		if ( self::users_bulk_uses_panel_targets( $scope, 'extend' ) ) {
			$res_targets = self::users_bulk_resolve_panel_targets( $p );
			if ( empty( $res_targets['ok'] ) ) {
				return array( 'ok' => false, 'message' => (string) ( $res_targets['message'] ?? 'bad_scope' ) );
			}
			$targets = $res_targets['targets'] ?? array();
			if ( empty( $targets ) ) {
				return array( 'ok' => false, 'message' => 'empty_scope' );
			}
			if ( $dry ) {
				$data = self::users_bulk_dry_run_panel_data( $targets, $p );
				$data['days'] = $days;
				return array( 'ok' => true, 'data' => $data );
			}
			$jid = SimpleVPBot_Model_Users_Bulk_Job::insert_job(
				array(
					'operation'                => 'extend',
					'scope'                    => $scope,
					'payload_json'             => wp_json_encode(
						array_merge(
							self::users_bulk_payload_base( $p ),
							self::users_bulk_notify_fields( $p ),
							array(
								'target_mode' => 'panel_client',
								'days'        => $days,
								'reduce'      => $reduce ? 1 : 0,
							)
						),
						JSON_UNESCAPED_UNICODE
					),
					'status'                   => 'pending',
					'created_by_wp'            => get_current_user_id(),
					'created_by_svp_user_id'  => self::users_bulk_created_by_svp( $p ),
				)
			);
			$queued = SimpleVPBot_Model_Users_Bulk_Job::enqueue_panel_targets( $jid, $targets );
			return array(
				'ok'   => true,
				'data' => array(
					'job_id' => $jid,
					'queued' => $queued,
				),
			);
		}
		$res_ids = self::users_bulk_resolve_user_ids( $p );
		if ( empty( $res_ids['ok'] ) ) {
			return array( 'ok' => false, 'message' => (string) ( $res_ids['message'] ?? 'bad_scope' ) );
		}
		$user_ids = $res_ids['ids'];
		if ( empty( $user_ids ) ) {
			return array( 'ok' => false, 'message' => 'empty_scope' );
		}
		if ( $dry ) {
			$data = self::users_bulk_dry_run_data( $user_ids, $p, true );
			$data['days'] = $days;
			return array( 'ok' => true, 'data' => $data );
		}
		$jid = SimpleVPBot_Model_Users_Bulk_Job::insert_job(
			array(
				'operation'               => 'extend',
				'scope'                   => $scope,
				'payload_json'            => wp_json_encode(
					array_merge(
						self::users_bulk_payload_base( $p ),
						self::users_bulk_notify_fields( $p ),
						array(
							'days'   => $days,
							'reduce' => $reduce ? 1 : 0,
						)
					),
					JSON_UNESCAPED_UNICODE
				),
				'status'                  => 'pending',
				'created_by_wp'         => get_current_user_id(),
				'created_by_svp_user_id' => self::users_bulk_created_by_svp( $p ),
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
		if ( ! self::users_bulk_actor_may_use( $p ) ) {
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
		$user_ids = $res_ids['ids'];
		if ( empty( $user_ids ) ) {
			return array( 'ok' => false, 'message' => 'empty_scope' );
		}
		if ( $dry ) {
			$data = self::users_bulk_dry_run_data( $user_ids, $p, false );
			$data['patch'] = $patch;
			return array( 'ok' => true, 'data' => $data );
		}
		$jid = SimpleVPBot_Model_Users_Bulk_Job::insert_job(
			array(
				'operation'               => 'alerts',
				'scope'                   => sanitize_key( (string) ( $p['scope'] ?? 'all_approved' ) ),
				'payload_json'            => wp_json_encode(
					array_merge( self::users_bulk_payload_base( $p ), $patch ),
					JSON_UNESCAPED_UNICODE
				),
				'status'                  => 'pending',
				'created_by_wp'         => get_current_user_id(),
				'created_by_svp_user_id' => self::users_bulk_created_by_svp( $p ),
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

	/**
	 * Bulk add/reduce concurrent user slots on matching services.
	 *
	 * @param array<string, mixed> $p scope, extra_users, reduce?, dry_run?.
	 * @return array{ok:bool, message?:string, data?:array<string,mixed>}
	 */
	private static function op_users_bulk_slots( array $p ) {
		if ( ! self::users_bulk_actor_may_use( $p ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$res_ids = self::users_bulk_resolve_user_ids( $p );
		if ( empty( $res_ids['ok'] ) ) {
			return array( 'ok' => false, 'message' => (string) ( $res_ids['message'] ?? 'bad_scope' ) );
		}
		$n        = max( 1, (int) ( $p['extra_users'] ?? $p['slots'] ?? 0 ) );
		$reduce   = ! empty( $p['reduce'] );
		$dry      = ! empty( $p['dry_run'] );
		$user_ids = $res_ids['ids'];
		if ( $n < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_slots' );
		}
		if ( empty( $user_ids ) ) {
			return array( 'ok' => false, 'message' => 'empty_scope' );
		}
		if ( $dry ) {
			return array(
				'ok'   => true,
				'data' => self::users_bulk_dry_run_data( $user_ids, $p, false ),
			);
		}
		$jid = SimpleVPBot_Model_Users_Bulk_Job::insert_job(
			array(
				'operation'               => 'slots',
				'scope'                   => sanitize_key( (string) ( $p['scope'] ?? 'all_approved' ) ),
				'payload_json'            => wp_json_encode(
					array_merge(
						self::users_bulk_payload_base( $p ),
						self::users_bulk_notify_fields( $p ),
						array(
							'extra_users' => $n,
							'reduce'      => $reduce ? 1 : 0,
						)
					),
					JSON_UNESCAPED_UNICODE
				),
				'status'                  => 'pending',
				'created_by_wp'         => get_current_user_id(),
				'created_by_svp_user_id' => self::users_bulk_created_by_svp( $p ),
			)
		);
		SimpleVPBot_Model_Users_Bulk_Job::enqueue_users( $jid, $user_ids );
		return array(
			'ok'   => true,
			'data' => array(
				'job_id' => $jid,
				'queued' => count( $user_ids ),
			),
		);
	}

	private static function op_users_bulk_run_worker( array $p ) {
		if ( ! self::mutate_is_unrestricted_site_admin() ) {
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
		if ( ! self::users_bulk_actor_may_use( $p ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$jid = (int) ( $p['job_id'] ?? 0 );
		if ( $jid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		if ( ! self::users_bulk_job_actor_must_own( $p, $jid ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		global $wpdb;
		$tj = SimpleVPBot_Model_Users_Bulk_Job::table();
		$ti = SimpleVPBot_Model_Users_Bulk_Job::items_table();
		$wpdb->update( $tj, array( 'status' => 'cancelled', 'finished_at' => current_time( 'mysql', true ) ), array( 'id' => $jid ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$ti} SET status = 'failed', last_error = 'cancelled_by_admin' WHERE job_id = %d AND status IN ('pending','processing')", $jid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array( 'ok' => true );
	}

	private static function op_users_bulk_job_resume( array $p ) {
		if ( ! self::users_bulk_actor_may_use( $p ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$jid = (int) ( $p['job_id'] ?? 0 );
		if ( $jid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		if ( ! self::users_bulk_job_actor_must_own( $p, $jid ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		global $wpdb;
		$tj = SimpleVPBot_Model_Users_Bulk_Job::table();
		$ti = SimpleVPBot_Model_Users_Bulk_Job::items_table();
		$wpdb->update( $tj, array( 'status' => 'processing', 'finished_at' => null ), array( 'id' => $jid ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$ti} SET status = 'pending', last_error = NULL WHERE job_id = %d AND status = 'failed' AND last_error = 'cancelled_by_admin'", $jid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array( 'ok' => true );
	}

	/**
	 * Pending wallet top-up for logged-in reseller (card-to-card + receipt like customers).
	 *
	 * @param array<string, mixed> $p amount (toman).
	 * @return array{ok:bool, message?:string, transaction_id?:int, notify_sent?:bool}
	 */
	private static function op_reseller_wallet_topup_checkout( array $p ) {
		$actor = self::dashboard_reseller_actor_id( $p );
		if ( $actor < 1 ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$row = SimpleVPBot_Model_User::find( $actor );
		if ( ! $row || ! SimpleVPBot_Model_User::is_reseller_row( $row ) ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$amt = isset( $p['amount'] ) ? round( (float) $p['amount'], 2 ) : 0.0;
		if ( ! is_finite( $amt ) || $amt <= 0 || $amt > 1e11 ) {
			return array( 'ok' => false, 'message' => 'invalid_amount' );
		}
		$meta = array(
			'dashboard_reseller_topup'        => true,
			'invoice_card_owner_scope_svp_id' => $actor,
			'billing_reseller_svp_id'         => $actor,
		);
		$tid = SimpleVPBot_Model_Transaction::insert(
			array(
				'user_id'    => $actor,
				'service_id' => null,
				'amount'     => $amt,
				'type'       => 'topup',
				'status'     => 'pending',
				'meta_json'  => wp_json_encode( $meta ),
			)
		);
		if ( $tid < 1 ) {
			return array( 'ok' => false, 'message' => 'insert_failed' );
		}
		$tx_row = SimpleVPBot_Model_Transaction::find( (int) $tid );
		if ( ! $tx_row ) {
			return array( 'ok' => false, 'message' => 'insert_failed' );
		}
		if ( class_exists( 'SimpleVPBot_Payment_Methods' ) && ! SimpleVPBot_Payment_Methods::checkout_has_any_method( 'telegram', $tx_row, $row, $actor ) ) {
			SimpleVPBot_Model_Transaction::set_status( (int) $tid, 'cancelled' );
			return array( 'ok' => false, 'message' => 'no_payment_methods' );
		}
		$sent = false;
		if ( class_exists( 'SimpleVPBot_Handler_Buy' ) && class_exists( 'SimpleVPBot_Bot_Runtime' ) ) {
			$title   = '🧾 شارژ کیف پول (داشبورد)';
			$text_tg = SimpleVPBot_Handler_Buy::checkout_message_for_tx( $tx_row, $title );
			$text_bl = $text_tg;
			$markup_tg = SimpleVPBot_Handler_Buy::checkout_reply_markup( 'telegram', (int) $tid );
			$markup_bl = SimpleVPBot_Handler_Buy::checkout_reply_markup( 'bale', (int) $tid );
			if ( ! empty( $row->tg_user_id ) ) {
				$r = SimpleVPBot_Bot_Runtime::send_message_for_reseller(
					'telegram',
					(int) $row->tg_user_id,
					$text_tg,
					$actor,
					array( 'reply_markup' => $markup_tg )
				);
				if ( null !== $r ) {
					$sent = true;
				}
			}
			if ( ! empty( $row->bale_user_id ) ) {
				$r = SimpleVPBot_Bot_Runtime::send_message_for_reseller(
					'bale',
					(int) $row->bale_user_id,
					$text_bl,
					$actor,
					array( 'reply_markup' => $markup_bl )
				);
				if ( null !== $r ) {
					$sent = true;
				}
			}
		}
		return array(
			'ok'              => true,
			'transaction_id' => (int) $tid,
			'notify_sent'     => $sent,
		);
	}

	/**
	 * Create WordPress user and link to reseller svp_users row (or create reseller row).
	 *
	 * @param array<string, mixed> $p wp_username, wp_password, email?, svp_user_id? (0 = create new reseller), name fields.
	 * @return array{ok:bool, message?:string, user_id?:int, wp_user_id?:int}
	 */
	private static function op_reseller_wp_provision( array $p ) {
		$is_admin = self::mutate_is_unrestricted_site_admin();
		$actor    = self::dashboard_reseller_actor_id( $p );
		if ( ! $is_admin && $actor < 1 ) {
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
			if ( ! $is_admin && (int) ( $row->invited_by ?? 0 ) !== $actor ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( $wp_id );
				return array( 'ok' => false, 'message' => 'forbidden_scope' );
			}
			SimpleVPBot_Model_User::set_linked_wp_user( $svp_ex, $wp_id );
			self::log_rest_user( $svp_ex, 'reseller_wp_provision', array( 'wp_user_id' => $wp_id ) );
			return array( 'ok' => true, 'user_id' => $svp_ex, 'wp_user_id' => $wp_id );
		}

		if ( ! $is_admin ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $wp_id );
			return array( 'ok' => false, 'message' => 'forbidden' );
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
		$is_admin = self::mutate_is_unrestricted_site_admin();
		$actor    = (int) ( $p['__actor_svp_user_id'] ?? 0 );
		$is_reseller_actor = false;
		if ( ! $is_admin ) {
			$ar = SimpleVPBot_Model_User::find( $actor );
			$is_reseller_actor = $ar && SimpleVPBot_Model_User::is_reseller_row( $ar );
			if ( ! $is_reseller_actor ) {
				return array( 'ok' => false, 'message' => 'forbidden' );
			}
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
		if ( $is_admin ) {
			$rep = SimpleVPBot_Model_Reseller_Panel_Price::replace_all_for_reseller( $rid, $raw );
			if ( empty( $rep['ok'] ) ) {
				$fail = array(
					'ok'      => false,
					'message' => isset( $rep['message'] ) ? (string) $rep['message'] : 'save_failed',
				);
				if ( ! empty( $rep['skipped_panel_ids'] ) ) {
					$fail['skipped_panel_ids'] = $rep['skipped_panel_ids'];
				}
				return $fail;
			}
			self::log_rest_user( $rid, 'reseller_panel_prices_save', array( 'count' => count( $raw ) ) );
			$ok_out = array( 'ok' => true );
			if ( ! empty( $rep['skipped_panel_ids'] ) ) {
				$ok_out['skipped_panel_ids'] = $rep['skipped_panel_ids'];
			}
			return $ok_out;
		}
		if ( (int) ( $row->invited_by ?? 0 ) !== $actor ) {
			return array( 'ok' => false, 'message' => 'forbidden_scope' );
		}
		if ( ! class_exists( 'SimpleVPBot_Model_Reseller_Parent_Panel_Floor' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$rows = array();
		foreach ( $raw as $rr ) {
			if ( ! is_array( $rr ) ) {
				continue;
			}
			$rows[] = array(
				'panel_id'         => (int) ( $rr['panel_id'] ?? 0 ),
				'min_price_per_gb' => max( 0.0, (float) ( $rr['price_per_gb'] ?? 0 ) ),
			);
		}
		SimpleVPBot_Model_Reseller_Parent_Panel_Floor::replace_all_for_parent_child( $actor, $rid, $rows );
		self::log_rest_user( $rid, 'reseller_parent_panel_floors_save', array( 'parent' => $actor, 'count' => count( $rows ) ) );
		return array( 'ok' => true );
	}

	/**
	 * Create/update wholesale catalog line + tiers (site admin).
	 *
	 * @param array<string, mixed> $p line_id, label, badge_color, panel_id, tiers[], ….
	 * @return array{ok:bool, message?:string, line_id?:int}
	 */
	private static function op_wholesale_line_save( array $p ) {
		if ( ! self::mutate_is_unrestricted_site_admin() ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		if ( ! class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Line' ) || ! class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Tier' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$lid = (int) ( $p['line_id'] ?? 0 );
		$pid = max( 1, (int) ( $p['panel_id'] ?? 1 ) );
		if ( ! SimpleVPBot_Model_Panel::find( $pid ) ) {
			return array( 'ok' => false, 'message' => 'bad_panel' );
		}
		$dstype = isset( $p['default_service_type'] ) ? sanitize_key( (string) $p['default_service_type'] ) : 'xray';
		if ( ! in_array( $dstype, array( 'xray', 'l2tp' ), true ) ) {
			$dstype = 'xray';
		}
		if ( self::l2tp_feature_disabled() ) {
			$dstype = 'xray';
		}
		$row = array(
			'label'                  => sanitize_text_field( (string) ( $p['label'] ?? '' ) ),
			'badge_color'            => sanitize_text_field( (string) ( $p['badge_color'] ?? '' ) ),
			'panel_id'               => $pid,
			'default_service_type'   => $dstype,
			'default_inbound_id'     => max( 0, (int) ( $p['default_inbound_id'] ?? 0 ) ),
			'default_l2tp_server_id' => self::l2tp_feature_disabled() ? 0 : max( 0, (int) ( $p['default_l2tp_server_id'] ?? 0 ) ),
			'active'                 => ! empty( $p['active'] ) ? 1 : 0,
			'sort_order'             => (int) ( $p['sort_order'] ?? 0 ),
		);
		if ( '' === trim( $row['label'] ) ) {
			return array( 'ok' => false, 'message' => 'invalid_label' );
		}
		$tiers_raw = isset( $p['tiers'] ) && is_array( $p['tiers'] ) ? $p['tiers'] : array();
		$tiers     = array();
		foreach ( $tiers_raw as $tr ) {
			if ( ! is_array( $tr ) ) {
				continue;
			}
			$tiers[] = array(
				'sort_order'      => (int) ( $tr['sort_order'] ?? 0 ),
				'price_per_gb'    => max( 0.0, (float) ( $tr['price_per_gb'] ?? 0 ) ),
				'min_total_gb'    => max( 0, (int) ( $tr['min_total_gb'] ?? 0 ) ),
				'min_total_toman' => max( 0.0, (float) ( $tr['min_total_toman'] ?? 0 ) ),
			);
		}
		usort(
			$tiers,
			static function ( $a, $b ) {
				return (int) ( $a['sort_order'] ?? 0 ) <=> (int) ( $b['sort_order'] ?? 0 );
			}
		);
		if ( empty( $tiers ) ) {
			return array( 'ok' => false, 'message' => 'tiers_required' );
		}
		if ( $lid > 0 ) {
			$ex = SimpleVPBot_Model_Reseller_Wholesale_Line::find( $lid );
			if ( ! $ex ) {
				return array( 'ok' => false, 'message' => 'not_found' );
			}
			SimpleVPBot_Model_Reseller_Wholesale_Line::update( $lid, $row );
			SimpleVPBot_Model_Reseller_Wholesale_Tier::replace_for_line( $lid, $tiers );
			return array( 'ok' => true, 'line_id' => $lid );
		}
		$new_id = SimpleVPBot_Model_Reseller_Wholesale_Line::insert( $row );
		if ( $new_id < 1 ) {
			return array( 'ok' => false, 'message' => 'insert_failed' );
		}
		SimpleVPBot_Model_Reseller_Wholesale_Tier::replace_for_line( $new_id, $tiers );
		return array( 'ok' => true, 'line_id' => $new_id );
	}

	/**
	 * Delete wholesale line (site admin).
	 *
	 * @param array<string, mixed> $p line_id.
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_wholesale_line_delete( array $p ) {
		if ( ! self::mutate_is_unrestricted_site_admin() ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$lid = (int) ( $p['line_id'] ?? 0 );
		if ( $lid < 1 || ! class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Line' ) ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		global $wpdb;
		$ta = SimpleVPBot_Model_Reseller_Wholesale_Assignment::table();
		$wpdb->delete( $ta, array( 'line_id' => $lid ), array( '%d' ) );
		SimpleVPBot_Model_Reseller_Wholesale_Tier::delete_all_for_line( $lid );
		SimpleVPBot_Model_Reseller_Wholesale_Line::delete( $lid );
		if ( class_exists( 'SimpleVPBot_Model_Plan' ) ) {
			$plans_t = SimpleVPBot_Model_Plan::table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare( "UPDATE {$plans_t} SET wholesale_line_id = NULL WHERE wholesale_line_id = %d", $lid ) );
		}
		return array( 'ok' => true );
	}

	/**
	 * Assign wholesale lines to a reseller (site admin).
	 *
	 * @param array<string, mixed> $p reseller_svp_user_id, line_ids int[].
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_reseller_wholesale_lines_assign( array $p ) {
		if ( ! self::mutate_is_unrestricted_site_admin() ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$rid = (int) ( $p['reseller_svp_user_id'] ?? 0 );
		if ( $rid < 1 || ! class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Assignment' ) ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		$row = SimpleVPBot_Model_User::find( $rid );
		if ( ! $row || ! SimpleVPBot_Model_User::is_reseller_row( $row ) ) {
			return array( 'ok' => false, 'message' => 'not_reseller' );
		}
		$ids = isset( $p['line_ids'] ) && is_array( $p['line_ids'] ) ? $p['line_ids'] : array();
		SimpleVPBot_Model_Reseller_Wholesale_Assignment::replace_for_reseller( $rid, array_map( 'intval', $ids ) );
		self::log_rest_user( $rid, 'reseller_wholesale_lines_assign', array( 'count' => count( $ids ) ) );
		return array( 'ok' => true );
	}

	/**
	 * Save per-reseller permission map.
	 *
	 * @param array<string,mixed> $p reseller_svp_user_id, permissions.
	 * @return array{ok:bool,message?:string}
	 */
	private static function op_reseller_permissions_save( array $p ) {
		if ( ! self::mutate_is_unrestricted_site_admin() ) {
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
	 * Shared token patch + optional text overrides for reseller bot profile ops.
	 *
	 * @param int                  $rid Reseller svp_users.id.
	 * @param array<string, mixed> $p   telegram_token?, bale_token?, brand_name?, text_overrides?.
	 * @return array{tg:string, bl:string, patched:array<int,string>}
	 */
	private static function patch_reseller_bot_profile_tokens( $rid, array $p ) {
		$tg    = array_key_exists( 'telegram_token', $p ) ? (string) $p['telegram_token'] : '';
		$bl    = array_key_exists( 'bale_token', $p ) ? (string) $p['bale_token'] : '';
		$brand = array_key_exists( 'brand_name', $p ) ? (string) $p['brand_name'] : null;
		$patched = SimpleVPBot_Model_Reseller_Bot_Profile::patch_tokens(
			(int) $rid,
			array_key_exists( 'telegram_token', $p ) ? $tg : null,
			array_key_exists( 'bale_token', $p ) ? $bl : null,
			$brand
		);
		if ( ! empty( $patched ) ) {
			SimpleVPBot_Model_Reseller_Bot_Profile::sync_reseller_bot_usernames( (int) $rid, $patched );
		}
		if ( isset( $p['text_overrides'] ) && is_array( $p['text_overrides'] ) ) {
			$loc = class_exists( 'SimpleVPBot_Texts' ) ? SimpleVPBot_Texts::site_default_locale() : 'fa';
			SimpleVPBot_Model_Reseller_Bot_Profile::save_text_overrides(
				(int) $rid,
				array(
					$loc => $p['text_overrides'],
				)
			);
		}
		return array(
			'tg'      => $tg,
			'bl'      => $bl,
			'patched' => is_array( $patched ) ? $patched : array(),
		);
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
		if ( self::mutate_is_unrestricted_site_admin() ) {
			$rid = (int) ( $p['reseller_svp_user_id'] ?? 0 );
			if ( $rid < 1 ) {
				return array( 'ok' => false, 'message' => 'invalid_reseller' );
			}
			$row = SimpleVPBot_Model_User::find( $rid );
			if ( ! $row || ! SimpleVPBot_Model_User::is_reseller_row( $row ) ) {
				return array( 'ok' => false, 'message' => 'not_reseller' );
			}
		} else {
			$rid = self::mutate_reseller_actor_id( $p );
			if ( $rid < 1 ) {
				return array( 'ok' => false, 'message' => 'forbidden' );
			}
		}
		$tok = self::patch_reseller_bot_profile_tokens( $rid, $p );
		self::log_rest_user( $rid, 'reseller_bot_tokens_save', array( 'has_tg' => strlen( $tok['tg'] ) > 0, 'has_bl' => strlen( $tok['bl'] ) > 0 ) );
		if ( class_exists( 'SimpleVPBot_Telegram_Relay' ) ) {
			SimpleVPBot_Telegram_Relay::maybe_sync_after_settings();
		}
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
		if ( self::mutate_is_unrestricted_site_admin() ) {
			$rid = (int) ( $p['reseller_svp_user_id'] ?? 0 );
			if ( $rid < 1 ) {
				return array( 'ok' => false, 'message' => 'invalid_reseller' );
			}
			$row = SimpleVPBot_Model_User::find( $rid );
			if ( ! $row || ! SimpleVPBot_Model_User::is_reseller_row( $row ) ) {
				return array( 'ok' => false, 'message' => 'not_reseller' );
			}
		} else {
			$rid = self::mutate_reseller_actor_id( $p );
			if ( $rid < 1 ) {
				return array( 'ok' => false, 'message' => 'forbidden' );
			}
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
		if ( self::mutate_is_unrestricted_site_admin() ) {
			$rid = (int) ( $p['reseller_svp_user_id'] ?? 0 );
			if ( $rid < 1 ) {
				return array( 'ok' => false, 'message' => 'invalid_reseller' );
			}
			$row = SimpleVPBot_Model_User::find( $rid );
			if ( ! $row || ! SimpleVPBot_Model_User::is_reseller_row( $row ) ) {
				return array( 'ok' => false, 'message' => 'not_reseller' );
			}
		} else {
			$rid = self::mutate_reseller_actor_id( $p );
			if ( $rid < 1 ) {
				return array( 'ok' => false, 'message' => 'forbidden' );
			}
		}
		$new = (string) SimpleVPBot_Model_Reseller_Bot_Profile::rotate_webhook_secret( $rid );
		if ( '' === $new ) {
			return array( 'ok' => false, 'message' => 'rotate_failed' );
		}
		self::log_rest_user( $rid, 'reseller_bot_secret_rotate', array() );
		if ( class_exists( 'SimpleVPBot_Telegram_Relay' ) ) {
			SimpleVPBot_Telegram_Relay::maybe_sync_after_settings();
		}
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
		if ( ! self::mutate_is_unrestricted_site_admin() ) {
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
	 * Toggle Telegram or Bale for main bot or reseller profile.
	 *
	 * @param array<string, mixed> $p platform, reseller_svp_user_id optional.
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_bot_toggle_platform_enabled( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Platforms' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$plat = SimpleVPBot_Platforms::normalize( (string) ( $p['platform'] ?? '' ) );
		$rid  = (int) ( $p['reseller_svp_user_id'] ?? $p['bot_id'] ?? 0 );
		if ( $rid > 0 ) {
			if ( ! self::mutate_is_unrestricted_site_admin() ) {
				$self = self::mutate_reseller_actor_id( $p );
				if ( $self < 1 || $self !== $rid ) {
					return array( 'ok' => false, 'message' => 'forbidden' );
				}
			} else {
				$row = SimpleVPBot_Model_User::find( $rid );
				if ( ! $row || ! SimpleVPBot_Model_User::is_reseller_row( $row ) ) {
					return array( 'ok' => false, 'message' => 'not_reseller' );
				}
			}
			$new = SimpleVPBot_Platforms::toggle_reseller_platform( $rid, $plat );
			if ( null === $new ) {
				return array( 'ok' => false, 'message' => 'toggle_failed' );
			}
			SimpleVPBot_Platforms::after_platform_toggle( $plat, $new, $rid );
			return array( 'ok' => true, 'enabled' => $new );
		}
		if ( ! self::mutate_is_unrestricted_site_admin() ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$new = SimpleVPBot_Platforms::toggle_main_platform( $plat );
		SimpleVPBot_Platforms::after_platform_toggle( $plat, $new, 0 );
		if ( class_exists( 'SimpleVPBot_Admin_Actions' ) ) {
			SimpleVPBot_Admin_Actions::after_settings_tab_saved( 'bots' );
		}
		return array( 'ok' => true, 'enabled' => $new );
	}

	/**
	 * Telegram getMe (main bot or reseller bot).
	 *
	 * @param array<string, mixed> $p reseller_svp_user_id optional.
	 * @return array{ok:bool, message?:string, data?:mixed}
	 */
	private static function op_bot_test_telegram( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$rid = (int) ( $p['reseller_svp_user_id'] ?? 0 );
		if ( ! self::mutate_is_unrestricted_site_admin() ) {
			$self = self::mutate_reseller_actor_id( $p );
			if ( $self < 1 ) {
				return array( 'ok' => false, 'message' => 'forbidden' );
			}
			if ( $rid < 1 || $rid !== $self ) {
				return array( 'ok' => false, 'message' => 'forbidden' );
			}
			return SimpleVPBot_Service_Admin_Ops::test_telegram_for_reseller( $rid );
		}
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
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$rid = (int) ( $p['reseller_svp_user_id'] ?? 0 );
		if ( ! self::mutate_is_unrestricted_site_admin() ) {
			$self = self::mutate_reseller_actor_id( $p );
			if ( $self < 1 ) {
				return array( 'ok' => false, 'message' => 'forbidden' );
			}
			if ( $rid < 1 || $rid !== $self ) {
				return array( 'ok' => false, 'message' => 'forbidden' );
			}
			return SimpleVPBot_Service_Admin_Ops::test_bale_for_reseller( $rid );
		}
		if ( $rid > 0 ) {
			return SimpleVPBot_Service_Admin_Ops::test_bale_for_reseller( $rid );
		}
		return SimpleVPBot_Service_Admin_Ops::test_bale();
	}

	/**
	 * Live bot diagnostics (getMe + getWebhookInfo).
	 *
	 * @param array<string, mixed> $p platform, reseller_svp_user_id?, reveal_token?, send_outbound_ping?.
	 * @return array{ok:bool, message?:string, data?:mixed}
	 */
	private static function op_bot_diagnostics( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$plat = sanitize_key( (string) ( $p['platform'] ?? '' ) );
		if ( ! in_array( $plat, array( 'telegram', 'bale' ), true ) ) {
			return array( 'ok' => false, 'message' => 'bad_platform' );
		}
		$rid       = (int) ( $p['reseller_svp_user_id'] ?? 0 );
		$reveal    = ! empty( $p['reveal_token'] );
		$send_ping = ! empty( $p['send_outbound_ping'] );

		if ( ! self::mutate_is_unrestricted_site_admin() ) {
			$self = self::mutate_reseller_actor_id( $p );
			if ( $self < 1 ) {
				return array( 'ok' => false, 'message' => 'forbidden' );
			}
			if ( $rid < 1 || $rid !== $self ) {
				return array( 'ok' => false, 'message' => 'forbidden' );
			}
			$reveal = false;
		} elseif ( $reveal && ! self::mutate_is_unrestricted_site_admin() ) {
			$reveal = false;
		}

		if ( $rid > 0 ) {
			$res = SimpleVPBot_Service_Admin_Ops::bot_diagnostics_reseller( $plat, $rid, $reveal, $send_ping );
		} else {
			if ( ! self::mutate_is_unrestricted_site_admin() ) {
				return array( 'ok' => false, 'message' => 'forbidden' );
			}
			$res = SimpleVPBot_Service_Admin_Ops::bot_diagnostics_main( $plat, $reveal, $send_ping );
		}

		$can_reveal = self::mutate_is_unrestricted_site_admin();
		if ( ! empty( $res['data'] ) && is_array( $res['data'] ) ) {
			$res['data']['can_reveal_token'] = $can_reveal;
		}

		return array(
			'ok'      => ! empty( $res['ok'] ),
			'message' => isset( $res['message'] ) ? (string) $res['message'] : '',
			'data'    => isset( $res['data'] ) ? $res['data'] : null,
		);
	}

	/**
	 * Set webhook: main bot (bot_id 0) or reseller (bot_id = reseller svp user id).
	 *
	 * @param array<string, mixed> $p bot_id, platform: telegram|bale.
	 * @return array{ok:bool, message?:string, data?:mixed}
	 */
	private static function op_bot_set_webhook( array $p ) {
		if ( ! self::mutate_is_unrestricted_site_admin() ) {
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
	 * Delete webhook: main bot (bot_id 0) or reseller.
	 *
	 * @param array<string, mixed> $p bot_id, platform: telegram|bale.
	 * @return array{ok:bool, message?:string, data?:mixed}
	 */
	private static function op_bot_delete_webhook( array $p ) {
		if ( ! self::mutate_is_unrestricted_site_admin() ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$bid  = (int) ( $p['bot_id'] ?? 0 );
		$plat = sanitize_key( (string) ( $p['platform'] ?? '' ) );
		if ( $bid < 1 ) {
			if ( 'telegram' === $plat ) {
				return SimpleVPBot_Service_Admin_Ops::delete_webhook_telegram();
			}
			if ( 'bale' === $plat ) {
				return SimpleVPBot_Service_Admin_Ops::delete_webhook_bale();
			}
			return array( 'ok' => false, 'message' => 'bad_platform' );
		}
		return self::op_reseller_bot_webhook_delete(
			array(
				'reseller_svp_user_id' => $bid,
				'platform'             => $plat,
			)
		);
	}

	/**
	 * Remove Telegram/Bale webhook for reseller bot.
	 *
	 * @param array<string, mixed> $p platform; optional reseller_svp_user_id for admin.
	 * @return array{ok:bool, message?:string, data?:array<string,mixed>}
	 */
	private static function op_reseller_bot_webhook_delete( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		if ( self::mutate_is_unrestricted_site_admin() ) {
			$rid = (int) ( $p['reseller_svp_user_id'] ?? 0 );
			if ( $rid < 1 ) {
				return array( 'ok' => false, 'message' => 'invalid_reseller' );
			}
			$row = SimpleVPBot_Model_User::find( $rid );
			if ( ! $row || ! SimpleVPBot_Model_User::is_reseller_row( $row ) ) {
				return array( 'ok' => false, 'message' => 'not_reseller' );
			}
		} else {
			$rid = self::mutate_reseller_actor_id( $p );
			if ( $rid < 1 ) {
				return array( 'ok' => false, 'message' => 'forbidden' );
			}
		}
		$plat = sanitize_key( (string) ( $p['platform'] ?? '' ) );
		if ( 'telegram' === $plat ) {
			$r = SimpleVPBot_Service_Admin_Ops::delete_webhook_telegram_for_reseller( $rid );
		} elseif ( 'bale' === $plat ) {
			$r = SimpleVPBot_Service_Admin_Ops::delete_webhook_bale_for_reseller( $rid );
		} else {
			return array( 'ok' => false, 'message' => 'bad_platform' );
		}
		self::log_rest_user( $rid, 'reseller_bot_webhook_delete', array( 'platform' => $plat, 'ok' => ! empty( $r['ok'] ) ) );
		return $r;
	}

	/**
	 * Add one admin chat id (main bot or reseller).
	 *
	 * @param array<string, mixed> $p platform, chat_id, reseller_svp_user_id (0 = main).
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_bot_admin_id_add( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Admin_Actions' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$plat = sanitize_key( (string) ( $p['platform'] ?? '' ) );
		$cid  = (int) ( $p['chat_id'] ?? 0 );
		$rid  = (int) ( $p['reseller_svp_user_id'] ?? 0 );
		if ( $cid < 1 || ! in_array( $plat, array( 'telegram', 'bale' ), true ) ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		if ( $rid < 1 ) {
			if ( ! self::mutate_is_unrestricted_site_admin() ) {
				return array( 'ok' => false, 'message' => 'forbidden' );
			}
			if ( ! SimpleVPBot_Admin_Actions::add_main_admin_id( $plat, $cid ) ) {
				return array( 'ok' => false, 'message' => 'failed' );
			}
			return array( 'ok' => true );
		}
		if ( self::mutate_is_unrestricted_site_admin() ) {
			$row = SimpleVPBot_Model_User::find( $rid );
			if ( ! $row || ! SimpleVPBot_Model_User::is_reseller_row( $row ) ) {
				return array( 'ok' => false, 'message' => 'not_reseller' );
			}
		} else {
			$self = self::mutate_reseller_actor_id( $p );
			if ( $self < 1 || $self !== $rid ) {
				return array( 'ok' => false, 'message' => 'forbidden' );
			}
		}
		if ( ! SimpleVPBot_Admin_Actions::add_reseller_admin_id( $rid, $plat, $cid ) ) {
			return array( 'ok' => false, 'message' => 'failed' );
		}
		self::log_rest_user( $rid, 'bot_admin_id_add', array( 'platform' => $plat, 'chat_id' => $cid ) );
		return array( 'ok' => true );
	}

	/**
	 * Remove one admin chat id (main bot or reseller).
	 *
	 * @param array<string, mixed> $p platform, chat_id, reseller_svp_user_id (0 = main).
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_bot_admin_id_remove( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Admin_Actions' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$plat = sanitize_key( (string) ( $p['platform'] ?? '' ) );
		$cid  = (int) ( $p['chat_id'] ?? 0 );
		$rid  = (int) ( $p['reseller_svp_user_id'] ?? 0 );
		if ( $cid < 1 || ! in_array( $plat, array( 'telegram', 'bale' ), true ) ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		if ( $rid < 1 ) {
			if ( ! self::mutate_is_unrestricted_site_admin() ) {
				return array( 'ok' => false, 'message' => 'forbidden' );
			}
			if ( ! SimpleVPBot_Admin_Actions::remove_main_admin_id( $plat, $cid ) ) {
				return array( 'ok' => false, 'message' => 'failed' );
			}
			return array( 'ok' => true );
		}
		if ( self::mutate_is_unrestricted_site_admin() ) {
			$row = SimpleVPBot_Model_User::find( $rid );
			if ( ! $row || ! SimpleVPBot_Model_User::is_reseller_row( $row ) ) {
				return array( 'ok' => false, 'message' => 'not_reseller' );
			}
		} else {
			$self = self::mutate_reseller_actor_id( $p );
			if ( $self < 1 || $self !== $rid ) {
				return array( 'ok' => false, 'message' => 'forbidden' );
			}
		}
		if ( ! SimpleVPBot_Admin_Actions::remove_reseller_admin_id( $rid, $plat, $cid ) ) {
			return array( 'ok' => false, 'message' => 'failed' );
		}
		self::log_rest_user( $rid, 'bot_admin_id_remove', array( 'platform' => $plat, 'chat_id' => $cid ) );
		return array( 'ok' => true );
	}

	/**
	 * Toggle reseller bot profile enabled flag.
	 *
	 * @param array<string, mixed> $p reseller_svp_user_id.
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_bot_reseller_toggle_enabled( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$rid = (int) ( $p['reseller_svp_user_id'] ?? 0 );
		if ( $rid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_reseller' );
		}
		if ( ! self::mutate_is_unrestricted_site_admin() ) {
			$self = self::mutate_reseller_actor_id( $p );
			if ( $self < 1 || $self !== $rid ) {
				return array( 'ok' => false, 'message' => 'forbidden' );
			}
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
		if ( ! self::mutate_is_unrestricted_site_admin() ) {
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
		if ( self::mutate_is_unrestricted_site_admin() ) {
			$rid = (int) ( $p['reseller_svp_user_id'] ?? 0 );
			if ( $rid < 1 ) {
				return array( 'ok' => false, 'message' => 'invalid_reseller' );
			}
			$row = SimpleVPBot_Model_User::find( $rid );
			if ( ! $row || ! SimpleVPBot_Model_User::is_reseller_row( $row ) ) {
				return array( 'ok' => false, 'message' => 'not_reseller' );
			}
		} else {
			$rid = self::mutate_reseller_actor_id( $p );
			if ( $rid < 1 ) {
				return array( 'ok' => false, 'message' => 'forbidden' );
			}
		}
		self::patch_reseller_bot_profile_tokens( $rid, $p );
		if ( array_key_exists( 'bale_wallet_provider_token', $p ) ) {
			SimpleVPBot_Model_Reseller_Bot_Profile::save_bale_wallet_provider_token( $rid, (string) $p['bale_wallet_provider_token'] );
		}
		$tg_ids = SimpleVPBot_Admin_Actions::parse_id_lines( (string) ( $p['admin_telegram_ids'] ?? '' ) );
		$bl_ids = SimpleVPBot_Admin_Actions::parse_id_lines( (string) ( $p['admin_bale_ids'] ?? '' ) );
		SimpleVPBot_Model_Reseller_Bot_Profile::save_admin_ids( $rid, $tg_ids, $bl_ids );
		$en = ! isset( $p['enabled'] ) || ! empty( $p['enabled'] );
		SimpleVPBot_Model_Reseller_Bot_Profile::set_enabled( $rid, $en );
		if (
			array_key_exists( 'logo_url', $p )
			|| array_key_exists( 'custom_domain', $p )
			|| array_key_exists( 'telegram_relay_public_url', $p )
			|| array_key_exists( 'config_label_override', $p )
			|| array_key_exists( 'config_label_prefix', $p )
		) {
			SimpleVPBot_Model_Reseller_Bot_Profile::save_branding_fields(
				$rid,
				array(
					'logo_url'                  => (string) ( $p['logo_url'] ?? '' ),
					'favicon_url'               => (string) ( $p['favicon_url'] ?? '' ),
					'theme_primary'             => (string) ( $p['theme_primary'] ?? '' ),
					'theme_accent'              => (string) ( $p['theme_accent'] ?? '' ),
					'custom_domain'             => (string) ( $p['custom_domain'] ?? '' ),
					'telegram_relay_public_url' => (string) ( $p['telegram_relay_public_url'] ?? '' ),
					'config_label_override'     => (string) ( $p['config_label_override'] ?? '' ),
					'config_label_prefix'       => (string) ( $p['config_label_prefix'] ?? '' ),
				)
			);
		}
		if ( class_exists( 'SimpleVPBot_Telegram_Relay' ) ) {
			SimpleVPBot_Telegram_Relay::maybe_sync_after_settings();
		}
		if ( isset( $p['inbound_display_names'] ) && class_exists( 'SimpleVPBot_Model_Reseller_Inbound_Display_Name' ) ) {
			$map = SimpleVPBot_Settings::sanitize_inbound_display_names_input( $p['inbound_display_names'] );
			SimpleVPBot_Model_Reseller_Inbound_Display_Name::replace_map_for_reseller( $rid, $map );
		}
		self::log_rest_user( $rid, 'bot_reseller_save', array( 'enabled' => $en ) );
		self::audit_rest( 'reseller', 'bot_reseller_save', 'user', $rid, array( 'enabled' => $en ) );
		return array( 'ok' => true );
	}

	/**
	 * Save per-inbound display names for a reseller.
	 *
	 * @param array<string, mixed> $p reseller_svp_user_id, inbound_display_names map.
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_reseller_inbound_labels_save( array $p ) {
		if ( ! class_exists( 'SimpleVPBot_Model_Reseller_Inbound_Display_Name' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		if ( self::mutate_is_unrestricted_site_admin() ) {
			$rid = (int) ( $p['reseller_svp_user_id'] ?? 0 );
			if ( $rid < 1 ) {
				return array( 'ok' => false, 'message' => 'invalid_reseller' );
			}
		} else {
			$rid = self::mutate_reseller_actor_id( $p );
			if ( $rid < 1 ) {
				return array( 'ok' => false, 'message' => 'forbidden' );
			}
		}
		$map = SimpleVPBot_Settings::sanitize_inbound_display_names_input( $p['inbound_display_names'] ?? array() );
		SimpleVPBot_Model_Reseller_Inbound_Display_Name::replace_map_for_reseller( $rid, $map );
		self::audit_rest( 'reseller', 'reseller_inbound_labels_save', 'user', $rid, array( 'count' => count( $map ) ) );
		return array( 'ok' => true );
	}

	/**
	 * Bind end users to a reseller (site admin): preview or set invited_by.
	 *
	 * @param array<string, mixed> $p reseller_svp_user_id, user_ids[], mode preview|set.
	 * @return array{ok:bool, message?:string, users?:array}
	 */
	private static function op_reseller_bind_users( array $p ) {
		if ( ! self::mutate_is_unrestricted_site_admin() ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		if ( ! class_exists( 'SimpleVPBot_Reseller_Backfill' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$rid = (int) ( $p['reseller_svp_user_id'] ?? 0 );
		$ids = array();
		if ( isset( $p['user_ids'] ) && is_array( $p['user_ids'] ) ) {
			$ids = array_map( 'intval', $p['user_ids'] );
		} elseif ( ! empty( $p['user_ids_text'] ) ) {
			$ids = array_map( 'intval', preg_split( '/[\s,]+/', (string) $p['user_ids_text'], -1, PREG_SPLIT_NO_EMPTY ) );
		}
		$mode = sanitize_key( (string) ( $p['mode'] ?? 'preview' ) );
		if ( ! in_array( $mode, array( 'preview', 'set', 'clear' ), true ) ) {
			$mode = 'preview';
		}
		$r = SimpleVPBot_Reseller_Backfill::bind_users_to_reseller( $rid, $ids, $mode );
		self::log_rest_user( $rid, 'reseller_bind_users', array( 'mode' => $mode, 'count' => count( (array) ( $r['users'] ?? array() ) ) ) );
		if ( ! empty( $r['ok'] ) && 'set' === $mode ) {
			self::audit_rest( 'reseller', 'reseller_bind_users', 'user', $rid, array( 'user_ids' => $ids ) );
		}
		return $r;
	}

	/**
	 * Site admin: set effective platform role (user / reseller / admin).
	 *
	 * @param array<string, mixed> $p target_user_id, role.
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_user_set_role( array $p ) {
		if ( ! self::mutate_is_unrestricted_site_admin() ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$uid  = (int) ( $p['target_user_id'] ?? $p['svp_user_id'] ?? 0 );
		$role = sanitize_key( (string) ( $p['role'] ?? '' ) );
		if ( $uid < 1 || ! in_array( $role, array( 'user', 'reseller', 'admin' ), true ) ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		$user = SimpleVPBot_Model_User::find( $uid );
		if ( ! $user ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		$tg = (int) ( $user->tg_user_id ?? 0 );
		$bl = (int) ( $user->bale_user_id ?? 0 );
		if ( 'user' === $role ) {
			SimpleVPBot_Model_User::update( $uid, array( 'role' => 'user' ) );
			if ( $tg > 0 ) {
				SimpleVPBot_Admin_Actions::remove_main_admin_id( 'telegram', $tg );
			}
			if ( $bl > 0 ) {
				SimpleVPBot_Admin_Actions::remove_main_admin_id( 'bale', $bl );
			}
			self::maybe_demote_linked_wp_user( $user );
		} elseif ( 'reseller' === $role ) {
			SimpleVPBot_Model_User::update( $uid, array( 'role' => 'reseller' ) );
			if ( $tg > 0 ) {
				SimpleVPBot_Admin_Actions::remove_main_admin_id( 'telegram', $tg );
			}
			if ( $bl > 0 ) {
				SimpleVPBot_Admin_Actions::remove_main_admin_id( 'bale', $bl );
			}
			if ( class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
				SimpleVPBot_Model_Reseller_Bot_Profile::ensure_webhook_secret( $uid );
			}
			self::maybe_demote_linked_wp_user( $user );
		} else {
			SimpleVPBot_Model_User::update( $uid, array( 'role' => 'user' ) );
			if ( $tg > 0 ) {
				SimpleVPBot_Admin_Actions::add_main_admin_id( 'telegram', $tg );
			}
			if ( $bl > 0 ) {
				SimpleVPBot_Admin_Actions::add_main_admin_id( 'bale', $bl );
			}
			self::maybe_promote_linked_wp_user( $user );
		}
		self::log_rest_user( $uid, 'user_role_change', array( 'role' => $role ) );
		return array( 'ok' => true );
	}

	/**
	 * Site admin: set or clear referral inviter (invited_by).
	 *
	 * @param array<string, mixed> $p target_user_id, referrer_id (0 clears).
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_user_set_referrer( array $p ) {
		if ( ! self::mutate_is_unrestricted_site_admin() ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		$uid = (int) ( $p['target_user_id'] ?? $p['svp_user_id'] ?? 0 );
		$ref = (int) ( $p['referrer_id'] ?? $p['invited_by'] ?? -1 );
		if ( $uid < 1 || $ref < 0 ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		$user = SimpleVPBot_Model_User::find( $uid );
		if ( ! $user ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		if ( $ref > 0 ) {
			if ( $ref === $uid ) {
				return array( 'ok' => false, 'message' => 'self_referrer' );
			}
			$parent = SimpleVPBot_Model_User::find( $ref );
			if ( ! $parent ) {
				return array( 'ok' => false, 'message' => 'referrer_not_found' );
			}
			if ( class_exists( 'SimpleVPBot_Reseller_Closure' ) && SimpleVPBot_Reseller_Closure::invited_by_would_cycle( $uid, $ref ) ) {
				return array( 'ok' => false, 'message' => 'referrer_cycle' );
			}
		}
		SimpleVPBot_Model_User::update(
			$uid,
			array(
				'invited_by' => $ref > 0 ? $ref : null,
			)
		);
		self::log_rest_user( $uid, 'user_set_referrer', array( 'referrer_id' => $ref ) );
		return array( 'ok' => true );
	}

	/**
	 * Toggle X-UI client enable for a user service row.
	 *
	 * @param array<string, mixed> $p service_id, enable (0|1).
	 * @return array{ok:bool, message?:string}
	 */
	private static function op_user_service_toggle_enable( array $p ) {
		$sid = (int) ( $p['service_id'] ?? 0 );
		$en  = ! empty( $p['enable'] ) ? 1 : 0;
		if ( $sid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid' );
		}
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		$r = self::op_configs_client_toggle_enable(
			array(
				'panel_id'   => (int) ( $svc->panel_id ?? 1 ),
				'inbound_id' => (int) ( $svc->inbound_id ?? 0 ),
				'email'      => (string) ( $svc->email ?? '' ),
				'enable'     => $en,
			)
		);
		if ( empty( $r['ok'] ) ) {
			return $r;
		}
		SimpleVPBot_Model_Service::update( $sid, array( 'panel_client_enabled' => $en ) );
		self::log_rest_user( (int) $svc->user_id, 'service_toggle_enable', array( 'service_id' => $sid, 'enable' => $en ) );
		return array( 'ok' => true );
	}

	/**
	 * Demote linked WP user to subscriber when stripping bot admin.
	 *
	 * @param object $user svp user row.
	 */
	private static function maybe_demote_linked_wp_user( $user ) {
		$wp_id = (int) ( $user->wp_user_id ?? 0 );
		if ( $wp_id < 1 ) {
			return;
		}
		$wpuser = new WP_User( $wp_id );
		if ( $wpuser->exists() ) {
			$wpuser->set_role( 'subscriber' );
		}
	}

	/**
	 * Promote linked WP user to administrator for dashboard admin role.
	 *
	 * @param object $user svp user row.
	 */
	private static function maybe_promote_linked_wp_user( $user ) {
		$wp_id = (int) ( $user->wp_user_id ?? 0 );
		if ( $wp_id < 1 ) {
			return;
		}
		$wpuser = new WP_User( $wp_id );
		if ( $wpuser->exists() ) {
			$wpuser->set_role( 'administrator' );
		}
	}

	/**
	 * Re-run reseller billing / invited_by backfill batches (site admin).
	 *
	 * @param array<string, mixed> $p Optional after_tx_id, after_user_id.
	 * @return array{ok:bool, billing?:array, invited?:array}
	 */
	private static function op_reseller_backfill_run( array $p ) {
		if ( ! self::mutate_is_unrestricted_site_admin() ) {
			return array( 'ok' => false, 'message' => 'forbidden' );
		}
		if ( ! class_exists( 'SimpleVPBot_Reseller_Backfill' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$billing = SimpleVPBot_Reseller_Backfill::backfill_billing_meta_batch(
			500,
			(int) ( $p['after_tx_id'] ?? 0 )
		);
		$invited = SimpleVPBot_Reseller_Backfill::backfill_invited_by_batch(
			500,
			(int) ( $p['after_user_id'] ?? 0 )
		);
		return array(
			'ok'      => true,
			'billing' => $billing,
			'invited' => $invited,
		);
	}

	/**
	 * Save marketing automation rule.
	 *
	 * @param array<string, mixed> $post Payload.
	 * @return array<string, mixed>
	 */
	private static function op_marketing_rule_save( array $post ) {
		if ( ! class_exists( 'SimpleVPBot_Model_Marketing_Rule' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$actor = self::dashboard_reseller_actor_id( $post );
		$id    = isset( $post['rule_id'] ) ? (int) $post['rule_id'] : 0;
		$seg   = SimpleVPBot_Model_Marketing_Rule::sanitize_segment( (string) ( $post['segment_key'] ?? '' ) );
		if ( '' === $seg ) {
			return array( 'ok' => false, 'message' => 'invalid_segment' );
		}
		$owner = isset( $post['owner_svp_user_id'] ) ? max( 0, (int) $post['owner_svp_user_id'] ) : 0;
		if ( $actor > 0 ) {
			$owner = $actor;
		}
		if ( $id > 0 ) {
			$ex = SimpleVPBot_Model_Marketing_Rule::find( $id );
			if ( ! $ex ) {
				return array( 'ok' => false, 'message' => 'not_found' );
			}
			if ( ! self::dashboard_reseller_owns_row_owner( $post, (int) ( $ex->owner_svp_user_id ?? 0 ) ) ) {
				return array( 'ok' => false, 'message' => 'forbidden_scope' );
			}
			$owner = (int) ( $ex->owner_svp_user_id ?? 0 );
		} elseif ( $actor > 0 ) {
			$owner = $actor;
		}
		$dtype = sanitize_key( (string) ( $post['discount_type'] ?? 'percent' ) );
		if ( ! in_array( $dtype, array( 'percent', 'fixed_toman' ), true ) ) {
			$dtype = 'percent';
		}
		$dval = (float) str_replace( ',', '.', (string) ( $post['discount_value'] ?? '0' ) );
		if ( $dval < 0 ) {
			$dval = 0.0;
		}
		if ( 'percent' === $dtype ) {
			$dval = min( 100.0, $dval );
		}
		$mdc = isset( $post['max_discount_toman'] ) ? trim( (string) $post['max_discount_toman'] ) : '';
		$max_disc = ( '' === $mdc || ! is_numeric( $mdc ) ) ? null : max( 0.0, (float) str_replace( ',', '.', $mdc ) );
		$now = current_time( 'mysql' );
		$row = array(
			'owner_svp_user_id'   => $owner,
			'segment_key'         => $seg,
			'enabled'             => ! empty( $post['enabled'] ) ? 1 : 0,
			'priority'            => max( 1, (int) ( $post['priority'] ?? 100 ) ),
			'cooldown_days'       => max( 1, (int) ( $post['cooldown_days'] ?? 90 ) ),
			'after_days'          => max( 0, (int) ( $post['after_days'] ?? 0 ) ),
			'pending_hours'       => max( 0, (int) ( $post['pending_hours'] ?? 0 ) ),
			'funnel_idle_hours'   => max( 0, (int) ( $post['funnel_idle_hours'] ?? 0 ) ),
			'expires_within_days' => max( 0, (int) ( $post['expires_within_days'] ?? 0 ) ),
			'discount_type'       => $dtype,
			'discount_value'      => $dval,
			'max_discount_toman'  => $max_disc,
			'code_valid_days'     => max( 1, (int) ( $post['code_valid_days'] ?? 7 ) ),
			'max_uses_per_user'   => max( 1, (int) ( $post['max_uses_per_user'] ?? 1 ) ),
			'message_body'        => isset( $post['message_body'] ) ? (string) $post['message_body'] : '',
			'channel_telegram'    => ! isset( $post['channel_telegram'] ) || ! empty( $post['channel_telegram'] ) ? 1 : 0,
			'channel_bale'        => ! isset( $post['channel_bale'] ) || ! empty( $post['channel_bale'] ) ? 1 : 0,
			'updated_at'          => $now,
		);
		if ( $id > 0 ) {
			SimpleVPBot_Model_Marketing_Rule::update( $id, $row );
			return array( 'ok' => true, 'rule_id' => $id );
		}
		$row['created_at'] = $now;
		$rid = SimpleVPBot_Model_Marketing_Rule::insert( $row );
		return array( 'ok' => true, 'rule_id' => $rid );
	}

	/**
	 * @param array<string, mixed> $post Payload.
	 * @return array<string, mixed>
	 */
	private static function op_marketing_rule_delete( array $post ) {
		if ( ! class_exists( 'SimpleVPBot_Model_Marketing_Rule' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$id = isset( $post['rule_id'] ) ? (int) $post['rule_id'] : 0;
		if ( $id < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_id' );
		}
		$ex = SimpleVPBot_Model_Marketing_Rule::find( $id );
		if ( ! $ex ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		if ( ! self::dashboard_reseller_owns_row_owner( $post, (int) ( $ex->owner_svp_user_id ?? 0 ) ) ) {
			return array( 'ok' => false, 'message' => 'forbidden_scope' );
		}
		SimpleVPBot_Model_Marketing_Rule::delete( $id );
		return array( 'ok' => true );
	}

	/**
	 * @param array<string, mixed> $post Payload.
	 * @return array<string, mixed>
	 */
	private static function op_marketing_send_manual( array $post ) {
		if ( ! class_exists( 'SimpleVPBot_Marketing_Automation' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$actor  = self::dashboard_reseller_actor_id( $post );
		$uid    = isset( $post['svp_user_id'] ) ? (int) $post['svp_user_id'] : 0;
		$rule_id = isset( $post['rule_id'] ) ? (int) $post['rule_id'] : 0;
		if ( $uid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_user' );
		}
		if ( $actor > 0 && class_exists( 'SimpleVPBot_Bot_Reseller_Scope' )
			&& ! SimpleVPBot_Bot_Reseller_Scope::reseller_may_moderate_user_for( $actor, $uid ) ) {
			return array( 'ok' => false, 'message' => 'forbidden_scope' );
		}
		if ( $rule_id > 0 ) {
			$rule = SimpleVPBot_Model_Marketing_Rule::find( $rule_id );
			if ( ! $rule || ! self::dashboard_reseller_owns_row_owner( $post, (int) ( $rule->owner_svp_user_id ?? 0 ) ) ) {
				return array( 'ok' => false, 'message' => 'forbidden_scope' );
			}
		}
		return SimpleVPBot_Marketing_Automation::send_manual( $uid, $rule_id, $actor );
	}

	/**
	 * @param array<string, mixed> $post Payload.
	 * @return array<string, mixed>
	 */
	private static function op_marketing_run_rule_now( array $post ) {
		if ( ! class_exists( 'SimpleVPBot_Marketing_Automation' ) ) {
			return array( 'ok' => false, 'message' => 'module_missing' );
		}
		$rule_id = isset( $post['rule_id'] ) ? (int) $post['rule_id'] : 0;
		if ( $rule_id < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_id' );
		}
		$rule = SimpleVPBot_Model_Marketing_Rule::find( $rule_id );
		if ( ! $rule ) {
			return array( 'ok' => false, 'message' => 'not_found' );
		}
		if ( ! self::dashboard_reseller_owns_row_owner( $post, (int) ( $rule->owner_svp_user_id ?? 0 ) ) ) {
			return array( 'ok' => false, 'message' => 'forbidden_scope' );
		}
		$limit = isset( $post['limit'] ) ? max( 1, min( 200, (int) $post['limit'] ) ) : 80;
		$stats = SimpleVPBot_Marketing_Automation::run_rule_now( $rule_id, $limit );
		return array_merge( array( 'ok' => true ), $stats );
	}
}
