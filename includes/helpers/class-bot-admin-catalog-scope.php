<?php
/**
 * Bot admin catalog scope helpers (shared; replaces hub-private filters).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Bot_Admin_Catalog_Scope
 */
class SimpleVPBot_Bot_Admin_Catalog_Scope {

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 */
	public static function bootstrap( $platform, $chat_id ) {
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return;
		}
		$admin_u = 'bale' === $platform
			? SimpleVPBot_Model_User::find_by_bale( (int) $chat_id )
			: SimpleVPBot_Model_User::find_by_telegram( (int) $chat_id );
		if ( $admin_u && ! empty( $admin_u->id ) ) {
			SimpleVPBot_Bot_Reseller_Scope::set_acting_admin_user( (int) $admin_u->id );
		}
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @return array<string, mixed>
	 */
	public static function mutate_context( $platform, $chat_id ) {
		self::bootstrap( $platform, $chat_id );
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return array();
		}
		$admin_u = 'bale' === $platform
			? SimpleVPBot_Model_User::find_by_bale( (int) $chat_id )
			: SimpleVPBot_Model_User::find_by_telegram( (int) $chat_id );
		if ( $admin_u && ! empty( $admin_u->id ) ) {
			return array( '__actor_svp_user_id' => (int) $admin_u->id );
		}
		$rid = SimpleVPBot_Bot_Reseller_Scope::resolve_scope_reseller_id();
		return $rid > 0 ? array( '__actor_svp_user_id' => $rid ) : array();
	}

	/**
	 * @param array<int, object> $list Plan rows.
	 * @param string             $platform Platform.
	 * @param int                $chat_id  Chat id.
	 * @return array<int, object>
	 */
	public static function filter_plans( array $list, $platform = '', $chat_id = 0 ) {
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return $list;
		}
		if ( '' !== (string) $platform && (int) $chat_id > 0 ) {
			self::bootstrap( $platform, $chat_id );
		}
		if ( ! SimpleVPBot_Bot_Reseller_Scope::is_scoped_bot_admin_context() ) {
			return $list;
		}
		return array_values(
			array_filter(
				$list,
				static function ( $p ) {
					return SimpleVPBot_Bot_Reseller_Scope::plan_visible_in_context( $p );
				}
			)
		);
	}

	/**
	 * @param array<int, object> $list Category rows.
	 * @param string             $platform Platform.
	 * @param int                $chat_id  Chat id.
	 * @return array<int, object>
	 */
	public static function filter_categories( array $list, $platform = '', $chat_id = 0 ) {
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return $list;
		}
		if ( '' !== (string) $platform && (int) $chat_id > 0 ) {
			self::bootstrap( $platform, $chat_id );
		}
		if ( ! SimpleVPBot_Bot_Reseller_Scope::is_scoped_bot_admin_context() ) {
			return $list;
		}
		return array_values(
			array_filter(
				$list,
				static function ( $c ) {
					$pid = (int) ( $c->panel_id ?? 0 );
					return $pid < 1 || SimpleVPBot_Bot_Reseller_Scope::panel_allowed_in_context( $pid );
				}
			)
		);
	}

	/**
	 * @param array<int, object> $list Card rows.
	 * @param string             $platform Platform.
	 * @param int                $chat_id  Chat id.
	 * @return array<int, object>
	 */
	public static function filter_cards( array $list, $platform = '', $chat_id = 0 ) {
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return $list;
		}
		if ( '' !== (string) $platform && (int) $chat_id > 0 ) {
			self::bootstrap( $platform, $chat_id );
		}
		if ( ! SimpleVPBot_Bot_Reseller_Scope::is_scoped_bot_admin_context() ) {
			return $list;
		}
		$rid = SimpleVPBot_Bot_Reseller_Scope::resolve_scope_reseller_id();
		if ( $rid < 1 ) {
			return array();
		}
		return array_values(
			array_filter(
				$list,
				static function ( $c ) use ( $rid ) {
					return (int) ( $c->owner_svp_user_id ?? 0 ) === $rid;
				}
			)
		);
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param int    $card_id  Card id.
	 * @return bool
	 */
	public static function guard_card( $platform, $chat_id, $card_id ) {
		$cid = (int) $card_id;
		if ( $cid < 1 || ! class_exists( 'SimpleVPBot_Model_Card' ) ) {
			return false;
		}
		self::bootstrap( $platform, $chat_id );
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) || ! SimpleVPBot_Bot_Reseller_Scope::is_scoped_bot_admin_context() ) {
			return true;
		}
		$card = SimpleVPBot_Model_Card::find( $cid );
		if ( ! $card ) {
			return false;
		}
		$rid   = SimpleVPBot_Bot_Reseller_Scope::resolve_scope_reseller_id();
		$owner = (int) ( $card->owner_svp_user_id ?? 0 );
		return $rid > 0 && $owner === $rid;
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param int    $plan_id  Plan id.
	 * @return bool
	 */
	public static function guard_plan( $platform, $chat_id, $plan_id ) {
		$pid = (int) $plan_id;
		if ( $pid < 1 || ! class_exists( 'SimpleVPBot_Model_Plan' ) ) {
			return false;
		}
		self::bootstrap( $platform, $chat_id );
		$row = SimpleVPBot_Model_Plan::find( $pid );
		if ( ! $row ) {
			return false;
		}
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) || ! SimpleVPBot_Bot_Reseller_Scope::is_scoped_bot_admin_context() ) {
			return true;
		}
		return SimpleVPBot_Bot_Reseller_Scope::plan_visible_in_context( $row );
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param int    $cat_id   Category id.
	 * @return bool
	 */
	public static function guard_category( $platform, $chat_id, $cat_id ) {
		$cid = (int) $cat_id;
		if ( $cid < 1 || ! class_exists( 'SimpleVPBot_Model_Plan_Category' ) ) {
			return false;
		}
		self::bootstrap( $platform, $chat_id );
		$row = SimpleVPBot_Model_Plan_Category::find( $cid );
		if ( ! $row ) {
			return false;
		}
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) || ! SimpleVPBot_Bot_Reseller_Scope::is_scoped_bot_admin_context() ) {
			return true;
		}
		$panel_id = (int) ( $row->panel_id ?? 0 );
		return $panel_id < 1 || SimpleVPBot_Bot_Reseller_Scope::panel_allowed_in_context( $panel_id );
	}
}
