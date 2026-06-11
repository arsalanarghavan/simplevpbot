<?php
/**
 * Bot admin → dashboard mutation bridge (shared validation/scope).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Bot_Admin_Mutate
 */
class SimpleVPBot_Bot_Admin_Mutate {

	/** @var array<string, string|null> Op → permission; null = site admin only. */
	const BOT_OP_PERMISSION = array(
		'discount_save'                  => 'plans.manage',
		'discount_delete'                => 'plans.manage',
		'marketing_rule_save'            => null,
		'marketing_rule_delete'          => null,
		'marketing_run_rule_now'         => null,
		'reseller_panel_prices_save'     => null,
		'reseller_wallet_topup_checkout' => 'plans.manage',
		'plan'                           => 'plans.manage',
		'plan_category'                  => 'plans.manage',
		'card_add'                       => 'plans.manage',
		'card_update'                    => 'plans.manage',
		'card_delete'                    => 'plans.manage',
		'unit_economics_config_save'     => null,
		'unit_economics_save'            => null,
		'panel_economics_save'           => null,
		'shared_economics_save'          => null,
		'panel_economics_mark_paid'      => null,
	);

	/**
	 * Apply dashboard mutation on behalf of a bot admin user.
	 *
	 * @param int                  $actor_svp_user_id Acting admin svp_users.id.
	 * @param string               $op                Operation key.
	 * @param array<string, mixed> $params            Mutation params.
	 * @return array{ok:bool, message?:string, code?:string, data?:mixed}
	 */
	public static function apply_for_user( $actor_svp_user_id, $op, array $params ) {
		$actor = (int) $actor_svp_user_id;
		$op    = sanitize_key( (string) $op );
		if ( $actor < 1 || '' === $op ) {
			return array( 'ok' => false, 'message' => 'invalid_request', 'code' => 'invalid_request' );
		}
		if ( ! class_exists( 'SimpleVPBot_Dashboard_Admin_Mutations' ) ) {
			return array( 'ok' => false, 'message' => 'mutations_unavailable', 'code' => 'mutations_unavailable' );
		}
		$gate = self::authorize( $actor, $op, $params );
		if ( ! $gate['ok'] ) {
			return $gate;
		}
		$params['__actor_svp_user_id'] = $actor;
		$perm_actor                    = class_exists( 'SimpleVPBot_Reseller_Permission_Gate' )
			? SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( $actor )
			: 0;
		if ( $perm_actor > 0 ) {
			$params['owner_svp_user_id'] = $perm_actor;
		}
		$scope_err = self::enforce_scope( $actor, $op, $params );
		if ( null !== $scope_err ) {
			return $scope_err;
		}
		$perm_actor = class_exists( 'SimpleVPBot_Reseller_Permission_Gate' )
			? SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( $actor )
			: 0;
		if ( $perm_actor < 1 && method_exists( 'SimpleVPBot_Dashboard_Admin_Mutations', 'with_bot_site_admin' ) ) {
			return SimpleVPBot_Dashboard_Admin_Mutations::with_bot_site_admin(
				static function () use ( $op, $params ) {
					return SimpleVPBot_Dashboard_Admin_Mutations::apply( $op, $params );
				}
			);
		}
		$result = SimpleVPBot_Dashboard_Admin_Mutations::apply( $op, $params );
		if ( ! is_array( $result ) ) {
			return array( 'ok' => false, 'message' => 'unknown_error', 'code' => 'unknown_error' );
		}
		return $result;
	}

	/**
	 * @param int                  $actor Admin id.
	 * @param string               $op    Op.
	 * @param array<string, mixed> $params Params.
	 * @return array{ok:bool, message?:string, code?:string}
	 */
	private static function authorize( $actor, $op, array $params ) {
		$perm_actor = class_exists( 'SimpleVPBot_Reseller_Permission_Gate' )
			? SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $actor )
			: 0;
		if ( $perm_actor < 1 ) {
			if ( 'reseller_wallet_topup_checkout' === $op ) {
				return array( 'ok' => false, 'message' => 'forbidden', 'code' => 'forbidden' );
			}
			return array( 'ok' => true );
		}
		if ( isset( self::BOT_OP_PERMISSION[ $op ] ) ) {
			$pk = self::BOT_OP_PERMISSION[ $op ];
			if ( null === $pk ) {
				return array( 'ok' => false, 'message' => 'forbidden_op', 'code' => 'forbidden_op' );
			}
			if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op_by_permission( $actor, $pk ) ) {
				return array( 'ok' => false, 'message' => 'forbidden_perm', 'code' => 'forbidden_perm' );
			}
			return array( 'ok' => true );
		}
		if ( class_exists( 'SimpleVPBot_Dashboard_Mutate_Policy' ) ) {
			$req = SimpleVPBot_Dashboard_Mutate_Policy::reseller_mutate_required_permission( $op );
			if ( null === $req ) {
				return array( 'ok' => false, 'message' => 'forbidden_op', 'code' => 'forbidden_op' );
			}
			if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op_by_permission( $actor, $req ) ) {
				return array( 'ok' => false, 'message' => 'forbidden_perm', 'code' => 'forbidden_perm' );
			}
			return array( 'ok' => true );
		}
		return array( 'ok' => false, 'message' => 'policy_missing', 'code' => 'policy_missing' );
	}

	/**
	 * @param int                  $actor  Admin id.
	 * @param string               $op     Op.
	 * @param array<string, mixed> $params Params.
	 * @return array{ok:bool, message?:string, code?:string}|null
	 */
	private static function enforce_scope( $actor, $op, array $params ) {
		if ( ! class_exists( 'SimpleVPBot_Reseller_Permission_Gate' )
			|| ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return null;
		}
		$perm_actor = SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $actor );
		if ( $perm_actor < 1 ) {
			if ( 'reseller_panel_prices_save' === $op ) {
				$rid = isset( $params['reseller_svp_user_id'] ) ? (int) $params['reseller_svp_user_id'] : 0;
				if ( $rid < 1 ) {
					return array( 'ok' => false, 'message' => 'invalid_reseller', 'code' => 'invalid_reseller' );
				}
			}
			return null;
		}
		$target_uid = 0;
		if ( isset( $params['svp_user_id'] ) ) {
			$target_uid = (int) $params['svp_user_id'];
		} elseif ( isset( $params['target_user_id'] ) ) {
			$target_uid = (int) $params['target_user_id'];
		}
		if ( $target_uid > 0 && ! SimpleVPBot_Bot_Reseller_Scope::reseller_may_moderate_user_for( $perm_actor, $target_uid ) ) {
			return array( 'ok' => false, 'message' => 'forbidden_scope', 'code' => 'forbidden_scope' );
		}
		$cat_err = self::enforce_catalog_entity_scope( $perm_actor, $op, $params );
		if ( null !== $cat_err ) {
			return $cat_err;
		}
		return null;
	}

	/**
	 * Defense-in-depth: block cross-tenant catalog mutations at mutate bridge.
	 *
	 * @param int                  $perm_actor Reseller permission actor id.
	 * @param string               $op         Operation key.
	 * @param array<string, mixed> $params     Params.
	 * @return array{ok:bool, message?:string, code?:string}|null
	 */
	private static function enforce_catalog_entity_scope( $perm_actor, $op, array $params ) {
		$perm_actor = (int) $perm_actor;
		if ( $perm_actor < 1 || ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return null;
		}
		SimpleVPBot_Bot_Reseller_Scope::set_acting_admin_user( $perm_actor );

		if ( 'plan' === $op && class_exists( 'SimpleVPBot_Model_Plan' ) ) {
			$pid = isset( $params['plan_id'] ) ? (int) $params['plan_id'] : 0;
			if ( $pid > 0 ) {
				$row = SimpleVPBot_Model_Plan::find( $pid );
				if ( ! $row || (int) ( $row->owner_svp_user_id ?? 0 ) !== $perm_actor ) {
					return array( 'ok' => false, 'message' => 'forbidden_scope', 'code' => 'forbidden' );
				}
			}
		}

		if ( in_array( $op, array( 'card_update', 'card_delete' ), true ) && class_exists( 'SimpleVPBot_Model_Card' ) ) {
			$cid = isset( $params['edit_id'] ) ? (int) $params['edit_id'] : ( isset( $params['card_id'] ) ? (int) $params['card_id'] : 0 );
			if ( $cid > 0 ) {
				$row = SimpleVPBot_Model_Card::find( $cid );
				if ( ! $row || (int) ( $row->owner_svp_user_id ?? 0 ) !== $perm_actor ) {
					return array( 'ok' => false, 'message' => 'forbidden_scope', 'code' => 'forbidden_scope' );
				}
			}
		}

		if ( 'plan_category' === $op && class_exists( 'SimpleVPBot_Model_Plan_Category' )
			&& class_exists( 'SimpleVPBot_Service_Admin_Catalog' ) ) {
			$pc_id = isset( $params['pc_id'] ) ? (int) $params['pc_id'] : 0;
			if ( $pc_id > 0 ) {
				$row = SimpleVPBot_Model_Plan_Category::find( $pc_id );
				if ( ! $row ) {
					return array( 'ok' => false, 'message' => 'not_found', 'code' => 'not_found' );
				}
				$panel_id = max( 1, (int) ( $row->panel_id ?? 1 ) );
				if ( ! SimpleVPBot_Service_Admin_Catalog::reseller_may_use_panel_catalog( $perm_actor, $panel_id ) ) {
					return array( 'ok' => false, 'message' => 'forbidden_scope', 'code' => 'panel_not_allowed' );
				}
				if ( SimpleVPBot_Service_Admin_Catalog::reseller_plan_category_blocked_by_foreign_plans(
					$perm_actor,
					(string) ( $row->slug ?? '' ),
					$panel_id
				) ) {
					return array( 'ok' => false, 'message' => 'forbidden_scope', 'code' => 'category_foreign_plans' );
				}
			}
		}

		return null;
	}

	/**
	 * Map mutation result message to localized bot text.
	 *
	 * @param object|null $user   Admin user.
	 * @param array       $result Mutation result.
	 * @return string
	 */
	public static function result_message( $user, array $result ) {
		if ( ! empty( $result['ok'] ) ) {
			return class_exists( 'SimpleVPBot_Bot_Admin_Texts' )
				? SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.mutate_ok', $user )
				: '✅ انجام شد.';
		}
		$code = (string) ( $result['message'] ?? $result['code'] ?? 'error' );
		$key  = 'msg.admin.mutate.' . sanitize_key( $code );
		if ( class_exists( 'SimpleVPBot_Bot_Admin_Texts' ) ) {
			$msg = SimpleVPBot_Bot_Admin_Texts::msg( $key, $user, array(), '' );
			if ( '' !== trim( $msg ) ) {
				return $msg;
			}
			if ( in_array( $code, array( 'forbidden', 'forbidden_op', 'forbidden_perm', 'forbidden_scope' ), true ) ) {
				return SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user );
			}
		}
		return '⛔ ' . $code;
	}

	/**
	 * Build discount_save post array from wizard data.
	 *
	 * @param array<string, mixed> $data Wizard state.
	 * @return array<string, mixed>
	 */
	public static function discount_post_from_wizard( array $data ) {
		$post = array(
			'svpc_code'  => (string) ( $data['code'] ?? '' ),
			'svpc_type'  => (string) ( $data['type'] ?? 'percent' ),
			'svpc_value' => (string) ( $data['value'] ?? '0' ),
			'svpc_active' => ! empty( $data['active'] ) ? 1 : 0,
		);
		if ( ! empty( $data['id'] ) ) {
			$post['svpc_id'] = (int) $data['id'];
		}
		if ( array_key_exists( 'max_uses', $data ) ) {
			$post['svpc_max_uses'] = '' === (string) $data['max_uses'] ? '' : (string) (int) $data['max_uses'];
		}
		if ( ! array_key_exists( 'active', $data ) || ! empty( $data['active'] ) ) {
			$post['svpc_active'] = 1;
		}
		$post['svpc_allow_new']   = ! isset( $data['allow_new'] ) || ! empty( $data['allow_new'] ) ? 1 : 0;
		$post['svpc_allow_renew'] = ! isset( $data['allow_renew'] ) || ! empty( $data['allow_renew'] ) ? 1 : 0;
		$post['svpc_allow_vol']   = ! isset( $data['allow_vol'] ) || ! empty( $data['allow_vol'] ) ? 1 : 0;
		$post['svpc_allow_users'] = ! isset( $data['allow_users'] ) || ! empty( $data['allow_users'] ) ? 1 : 0;
		if ( ! empty( $data['valid_until'] ) ) {
			$post['svpc_valid_until'] = (string) $data['valid_until'];
		}
		if ( ! empty( $data['valid_from'] ) ) {
			$post['svpc_valid_from'] = (string) $data['valid_from'];
		}
		if ( isset( $data['allowed_plan_ids'] ) && is_array( $data['allowed_plan_ids'] ) ) {
			$post['svpc_allowed_plan_ids'] = array_map( 'intval', $data['allowed_plan_ids'] );
		}
		if ( array_key_exists( 'min_order', $data ) ) {
			$post['svpc_min_order'] = (string) $data['min_order'];
		}
		if ( array_key_exists( 'max_order', $data ) ) {
			$post['svpc_max_order'] = (string) $data['max_order'];
		}
		if ( array_key_exists( 'max_discount', $data ) ) {
			$post['svpc_max_discount'] = (string) $data['max_discount'];
		}
		return $post;
	}
}
