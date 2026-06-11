<?php
/**
 * Reseller tab/op permission gate — shared by REST dashboard, bot admin panel, portal.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Reseller_Permission_Gate
 */
class SimpleVPBot_Reseller_Permission_Gate {

	/** @var array<string, string> Tab key => permission key. */
	const TAB_PERMISSION_MAP = array(
		'users'               => 'users.manage',
		'resellers'           => 'users.manage',
		'users_bulk'          => 'users.bulk',
		'plans'               => 'plans.manage',
		'plan_cats'           => 'plans.manage',
		'cards'               => 'plans.manage',
		'discounts'           => 'plans.manage',
		'bot_ui'              => 'services.manage',
		'reseller_bots'       => 'services.manage',
		'broadcast'           => 'broadcast.send',
		'receipts'            => 'receipts.review',
		'reseller_charge'     => 'plans.manage',
		'monitoring'          => 'services.manage',
		'referral'            => 'users.manage',
		'referral_reports'    => 'users.manage',
		'reseller_reports'    => 'users.manage',
		'marketing_lifecycle' => 'marketing.lifecycle',
	);

	/** @var array<int, string> Admin-only tabs for reseller actors. */
	const RESELLER_ADMIN_ONLY_TABS = array(
		'site_settings',
		'backup',
		'notifications',
		'logs',
		'xui_panels',
		'configs',
		'l2tp_servers',
		'texts',
		'bots',
		'unit_economics',
		'reseller_xui_panels',
		'audit',
	);

	/** @var array<int, string> All tab keys tracked by dashboard/bot nav. */
	const ALL_TAB_KEYS = array(
		'dashboard',
		'monitoring',
		'users',
		'resellers',
		'users_bulk',
		'broadcast',
		'plans',
		'plan_cats',
		'cards',
		'receipts',
		'reseller_charge',
		'referral',
		'referral_reports',
		'reseller_reports',
		'marketing_lifecycle',
		'discounts',
		'reseller_bots',
		'bot_ui',
		'site_settings',
		'backup',
		'notifications',
		'logs',
		'xui_panels',
		'configs',
		'l2tp_servers',
		'texts',
		'reseller_workspace',
		'reseller_settings',
		'reseller_xui_panels',
		'unit_economics',
		'audit',
		'bots',
	);

	/**
	 * Reseller actor tab map (mirrors REST reseller_dashboard_allowed_tabs_map).
	 *
	 * @param int        $actor_uid Reseller svp_users.id.
	 * @param array|null $perms     Optional preloaded permissions.
	 * @return array<string, bool>
	 */
	public static function reseller_allowed_tabs_map( $actor_uid, array $perms = null ) {
		$actor_uid = (int) $actor_uid;
		if ( null === $perms ) {
			$perms = $actor_uid > 0 && class_exists( 'SimpleVPBot_Model_User' )
				? SimpleVPBot_Model_User::reseller_permissions( $actor_uid )
				: SimpleVPBot_Model_User::default_reseller_permissions();
		}
		$out = array();
		foreach ( self::ALL_TAB_KEYS as $tab ) {
			if ( in_array( $tab, self::RESELLER_ADMIN_ONLY_TABS, true ) ) {
				$out[ $tab ] = false;
				continue;
			}
			if ( 'reseller_settings' === $tab ) {
				$out[ $tab ] = true;
				continue;
			}
			$pk = isset( self::TAB_PERMISSION_MAP[ $tab ] ) ? self::TAB_PERMISSION_MAP[ $tab ] : null;
			if ( null === $pk ) {
				$out[ $tab ] = true;
			} else {
				$out[ $tab ] = isset( $perms[ $pk ] ) && true === $perms[ $pk ];
			}
		}
		return $out;
	}

	/**
	 * Site admin tab map — all tabs allowed except reseller-only leaves.
	 *
	 * @return array<string, bool>
	 */
	public static function site_admin_allowed_tabs_map() {
		$out = array();
		foreach ( self::ALL_TAB_KEYS as $tab ) {
			if ( in_array( $tab, array( 'reseller_settings', 'reseller_charge' ), true ) ) {
				$out[ $tab ] = false;
				continue;
			}
			$out[ $tab ] = true;
		}
		return $out;
	}

	/**
	 * Resolve permission actor (reseller owner id on reseller bot).
	 *
	 * @param int $admin_svp_user_id Acting admin svp_users.id.
	 * @return int 0 = site admin actor.
	 */
	public static function permission_actor_id( $admin_svp_user_id ) {
		if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			$rid = (int) SimpleVPBot_Bot_Reseller_Scope::active_reseller_id();
			if ( $rid > 0 ) {
				return $rid;
			}
		}
		$uid = (int) $admin_svp_user_id;
		if ( $uid > 0 && class_exists( 'SimpleVPBot_Model_User' ) ) {
			$row = SimpleVPBot_Model_User::find( $uid );
			if ( $row && SimpleVPBot_Model_User::is_reseller_row( $row ) ) {
				return $uid;
			}
		}
		return 0;
	}

	/**
	 * Allowed tabs for bot admin actor.
	 *
	 * @param int $admin_svp_user_id Acting admin.
	 * @return array<string, bool>
	 */
	public static function allowed_tabs_for_actor( $admin_svp_user_id ) {
		$perm_actor = self::permission_actor_id( $admin_svp_user_id );
		if ( $perm_actor > 0 ) {
			return self::reseller_allowed_tabs_map( $perm_actor );
		}
		return self::site_admin_allowed_tabs_map();
	}

	/**
	 * @param int    $admin_svp_user_id Acting admin.
	 * @param string $tab_key           Tab key.
	 * @return bool
	 */
	public static function may_access_tab( $admin_svp_user_id, $tab_key ) {
		$tab = sanitize_key( (string) $tab_key );
		if ( '' === $tab ) {
			return false;
		}
		$map = self::allowed_tabs_for_actor( (int) $admin_svp_user_id );
		return isset( $map[ $tab ] ) && true === $map[ $tab ];
	}

	/**
	 * Bot/portal operation → permission key (null = site-only or forbidden for reseller).
	 *
	 * @param string $op Operation key.
	 * @return string|null
	 */
	public static function required_permission_for_op( $op ) {
		$map = array(
			'user_search'              => 'users.manage',
			'user_approve'             => 'users.manage',
			'user_reject'              => 'users.manage',
			'users_bulk'               => 'users.bulk',
			'broadcast'                => 'broadcast.send',
			'receipt_review'           => 'receipts.review',
			'receipt_approve'          => 'receipts.review',
			'receipt_reject'           => 'receipts.review',
			'plan_manage'              => 'plans.manage',
			'card_manage'              => 'plans.manage',
			'discount_manage'          => 'plans.manage',
			'referral_manage'          => 'users.manage',
			'marketing_lifecycle'      => 'marketing.lifecycle',
			'reseller_list'            => 'users.manage',
			'reseller_reports'         => 'users.manage',
			'reseller_bot_manage'      => 'services.manage',
			'monitoring'               => 'services.manage',
			'service_manage'           => 'services.manage',
		);
		$op = sanitize_key( (string) $op );
		return isset( $map[ $op ] ) ? $map[ $op ] : null;
	}

	/**
	 * @param int    $admin_svp_user_id Acting admin.
	 * @param string $op                Operation key.
	 * @return bool
	 */
	public static function may_call_op( $admin_svp_user_id, $op ) {
		$perm_actor = self::permission_actor_id( (int) $admin_svp_user_id );
		if ( $perm_actor < 1 ) {
			return true;
		}
		$pk = self::required_permission_for_op( $op );
		if ( null === $pk || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return false;
		}
		$perms = SimpleVPBot_Model_User::reseller_permissions( $perm_actor );
		return ! empty( $perms[ $pk ] );
	}

	/**
	 * Check permission key directly (portal AJAX delegate).
	 *
	 * @param int    $admin_svp_user_id Acting admin.
	 * @param string $permission_key    e.g. plans.manage.
	 * @return bool
	 */
	public static function may_call_op_by_permission( $admin_svp_user_id, $permission_key ) {
		$perm_actor = self::permission_actor_id( (int) $admin_svp_user_id );
		if ( $perm_actor < 1 ) {
			return true;
		}
		$pk = trim( (string) $permission_key );
		if ( '' === $pk || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return false;
		}
		$perms = SimpleVPBot_Model_User::reseller_permissions( $perm_actor );
		return ! empty( $perms[ $pk ] );
	}
}
