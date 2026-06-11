<?php
/**
 * Bot admin permission/scope helpers shared by hub, callbacks, and handlers.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Bot_Admin_Guard
 */
class SimpleVPBot_Bot_Admin_Guard {

	/**
	 * Set acting admin from handler context for dual-role scope on main bot.
	 *
	 * @param array<string, mixed> $ctx Context with optional user row.
	 */
	public static function bootstrap_acting_admin_from_ctx( array $ctx ) {
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return;
		}
		$user = isset( $ctx['user'] ) ? $ctx['user'] : null;
		if ( $user && is_object( $user ) && ! empty( $user->id ) ) {
			SimpleVPBot_Bot_Reseller_Scope::set_acting_admin_user( (int) $user->id );
			return;
		}
		$platform = (string) ( $ctx['platform'] ?? '' );
		$from_id  = 0;
		if ( ! empty( $ctx['from'] ) && is_array( $ctx['from'] ) ) {
			$from_id = (int) ( $ctx['from']['id'] ?? 0 );
		}
		if ( $from_id < 1 && isset( $ctx['chat_id'] ) ) {
			$from_id = (int) $ctx['chat_id'];
		}
		if ( $from_id > 0 ) {
			$admin = self::resolve_admin_by_platform_id( $platform, $from_id );
			if ( $admin && ! empty( $admin->id ) ) {
				SimpleVPBot_Bot_Reseller_Scope::set_acting_admin_user( (int) $admin->id );
			}
		}
	}

	/**
	 * Resolve admin svp_users row from platform chat/user id.
	 *
	 * @param string $platform telegram|bale.
	 * @param int    $platform_user_id Telegram/Bale user id.
	 * @return object|null
	 */
	public static function resolve_admin_by_platform_id( $platform, $platform_user_id ) {
		if ( ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return null;
		}
		$pid = (int) $platform_user_id;
		if ( $pid < 1 ) {
			return null;
		}
		return 'bale' === $platform
			? SimpleVPBot_Model_User::find_by_bale( $pid )
			: SimpleVPBot_Model_User::find_by_telegram( $pid );
	}

	/**
	 * Whether acting admin may invoke an operation.
	 *
	 * @param object|null $admin_user Admin svp_users row.
	 * @param string      $op         Operation key.
	 * @return bool
	 */
	public static function may_call_op( $admin_user, $op ) {
		if ( ! $admin_user || empty( $admin_user->id ) || ! class_exists( 'SimpleVPBot_Reseller_Permission_Gate' ) ) {
			return true;
		}
		return SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $admin_user->id, $op );
	}

	/**
	 * Deny message text for admin user locale.
	 *
	 * @param object|null $admin_user Admin row.
	 * @return string
	 */
	public static function denied_message( $admin_user = null ) {
		if ( class_exists( 'SimpleVPBot_Bot_Admin_Texts' ) ) {
			return SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $admin_user );
		}
		return SimpleVPBot_Texts::get( 'msg.admin.denied_permission', '⛔ دسترسی مجاز نیست.' );
	}

	/**
	 * Approved users eligible for broadcast for this admin actor.
	 *
	 * @param int $admin_svp_user_id Acting admin svp_users.id.
	 * @return array<int, object>
	 */
	public static function broadcast_recipients( $admin_svp_user_id ) {
		if ( ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return array();
		}
		$actor = 0;
		if ( class_exists( 'SimpleVPBot_Reseller_Permission_Gate' ) ) {
			$actor = SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $admin_svp_user_id );
		}
		if ( $actor < 1 || ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return SimpleVPBot_Model_User::all_approved();
		}
		$scope_ids = SimpleVPBot_Bot_Reseller_Scope::effective_moderatable_user_ids( $actor );
		if ( ! is_array( $scope_ids ) || empty( $scope_ids ) ) {
			return array();
		}
		global $wpdb;
		$tbl          = SimpleVPBot_Model_User::table();
		$scope_ids    = array_map( 'intval', $scope_ids );
		$placeholders = implode( ',', array_fill( 0, count( $scope_ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$tbl} WHERE status = 'approved' AND id IN ({$placeholders})",
				$scope_ids
			)
		);
		return is_array( $rows ) ? $rows : array();
	}
}
