<?php
/**
 * L2TP feature visibility (UI + purchase gates).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Feature_L2tp
 */
class SimpleVPBot_Feature_L2tp {

	/**
	 * Whether L2TP is exposed in dashboard, bot, and portal.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (bool) SimpleVPBot_Settings::get( 'l2tp_enabled', false );
	}

	/**
	 * @param object|null $svc Service row.
	 * @return bool
	 */
	public static function service_visible( $svc ) {
		if ( ! is_object( $svc ) ) {
			return false;
		}
		if ( self::enabled() ) {
			return true;
		}
		return ! SimpleVPBot_Model_Service::is_l2tp( $svc );
	}

	/**
	 * @param object|null $plan Plan row.
	 * @return bool
	 */
	public static function plan_visible( $plan ) {
		if ( ! is_object( $plan ) ) {
			return false;
		}
		if ( self::enabled() ) {
			return true;
		}
		return ! SimpleVPBot_Model_Plan::is_l2tp( $plan );
	}

	/**
	 * @param array<int, object> $list Services.
	 * @return array<int, object>
	 */
	public static function filter_services( array $list ) {
		if ( self::enabled() ) {
			return $list;
		}
		$out = array();
		foreach ( $list as $svc ) {
			if ( self::service_visible( $svc ) ) {
				$out[] = $svc;
			}
		}
		return $out;
	}

	/**
	 * @param array<int, object> $list Plans.
	 * @return array<int, object>
	 */
	public static function filter_plans( array $list ) {
		if ( self::enabled() ) {
			return $list;
		}
		$out = array();
		foreach ( $list as $plan ) {
			if ( self::plan_visible( $plan ) ) {
				$out[] = $plan;
			}
		}
		return $out;
	}

	/**
	 * Filter dashboard plan rows (assoc arrays from REST).
	 *
	 * @param array<int, array<string, mixed>> $list Plan rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function filter_plan_rows( array $list ) {
		if ( self::enabled() ) {
			return $list;
		}
		$out = array();
		foreach ( $list as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( 'l2tp' !== (string) ( $row['service_type'] ?? 'xray' ) ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	/**
	 * Filter dashboard service rows (assoc arrays from REST).
	 *
	 * @param array<int, array<string, mixed>> $list Service rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function filter_service_rows( array $list ) {
		if ( self::enabled() ) {
			return $list;
		}
		$out = array();
		foreach ( $list as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( 'l2tp' !== (string) ( $row['service_type'] ?? 'xray' ) ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	/**
	 * Bot UI action ids hidden when L2TP is disabled.
	 *
	 * @return array<int, string>
	 */
	public static function hidden_bot_action_ids() {
		$ids = array( 'admin.cat.l2tp' );
		foreach ( SimpleVPBot_UI_Action_Registry::surface_action_ids( 'svc_menu_l2tp' ) as $slot ) {
			$ids[] = $slot;
		}
		return $ids;
	}

	/**
	 * @param string $action_id Bot UI action id.
	 * @return bool
	 */
	public static function is_hidden_bot_action( $action_id ) {
		if ( self::enabled() ) {
			return false;
		}
		return in_array( (string) $action_id, self::hidden_bot_action_ids(), true );
	}
}
