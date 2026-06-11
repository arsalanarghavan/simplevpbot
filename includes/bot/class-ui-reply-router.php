<?php
/**
 * Bot UI — match layout-enabled reply actions and dispatch hub / wizard / ops routes.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_UI_Reply_Router
 */
class SimpleVPBot_UI_Reply_Router {

	/**
	 * Whether surface is excluded from hub routing (handled elsewhere or not reply menus).
	 *
	 * @param string $surface Surface id.
	 * @return bool
	 */
	private static function skip_surface_for_hub_dispatch( $surface ) {
		return 'user_main' === $surface
			|| 'admin_main' === $surface
			|| 0 === strpos( $surface, 'svc_menu_' );
	}

	/**
	 * Match enabled layout cell to text for surfaces handled inside route_menu_text.
	 *
	 * @param string      $text Trimmed text.
	 * @param object|null $user User row.
	 * @return string|null Action id.
	 */
	public static function match_routed_action( $text, $user ) {
		foreach ( SimpleVPBot_UI_Layout::get_merged_surfaces() as $surface => $rows ) {
			if ( self::skip_surface_for_hub_dispatch( $surface ) ) {
				continue;
			}
			foreach ( $rows as $row ) {
				foreach ( $row as $cell ) {
					if ( empty( $cell['enabled'] ) ) {
						continue;
					}
					$aid = (string) ( $cell['id'] ?? '' );
					$def = SimpleVPBot_UI_Action_Registry::get( $aid );
					if ( ! $def || empty( $def['route'] ) || ! is_array( $def['route'] ) ) {
						continue;
					}
					$gl = ! empty( $cell['glass'] );
					if ( SimpleVPBot_UI_Action_Registry::text_matches_reply_action( $text, $user, $aid, $gl ) ) {
						return $aid;
					}
				}
			}
		}
		return null;
	}

	/**
	 * Dispatch registry route for hub submenu flows. Returns false if not handled.
	 *
	 * @param array<string, mixed> $ctx Context (platform, chat_id, user, text, from_id, …).
	 * @return bool
	 */
	public static function try_dispatch_hub_action( array $ctx ) {
		$user = isset( $ctx['user'] ) ? $ctx['user'] : null;
		$text = trim( (string) ( $ctx['text'] ?? '' ) );
		if ( '' === $text || ! $user ) {
			return false;
		}
		$aid = self::match_routed_action( $text, $user );
		if ( ! $aid ) {
			return false;
		}
		$def = SimpleVPBot_UI_Action_Registry::get( $aid );
		if ( ! $def || empty( $def['route'] ) || ! is_array( $def['route'] ) ) {
			return false;
		}
		$route   = $def['route'];
		$plat    = (string) $ctx['platform'];
		$chat_id = (int) $ctx['chat_id'];

		if ( isset( $route['hub'] ) || isset( $route['panel_tab'] ) ) {
			$tab = isset( $route['panel_tab'] ) ? (string) $route['panel_tab'] : self::hub_route_to_tab( (string) $route['hub'] );
			if ( '' !== $tab && class_exists( 'SimpleVPBot_Handler_Admin_Panel' ) ) {
				SimpleVPBot_Handler_Admin_Panel::open_tab( $plat, $chat_id, $user, $tab, (int) ( $ctx['from_id'] ?? 0 ) );
				return true;
			}
		}
		if ( isset( $route['pnl_submenu'] ) ) {
			SimpleVPBot_Handler_Admin_Pnl::send_submenu( $plat, $chat_id, (string) $route['pnl_submenu'], $ctx );
			return true;
		}
		if ( isset( $route['wizard'] ) ) {
			$w = (string) $route['wizard'];
			$p = explode( ':', $w, 2 );
			if ( count( $p ) === 2 ) {
				SimpleVPBot_Handler_Admin_Settings::start_wizard( $ctx, $p[0], $p[1] );
				return true;
			}
		}
		if ( isset( $route['settings_op'] ) ) {
			SimpleVPBot_Handler_Admin_Settings::handle_op( $ctx, (string) $route['settings_op'] );
			return true;
		}
		if ( isset( $route['hub_dispatch_cb'] ) ) {
			SimpleVPBot_Handler_Admin_Pnl::dispatch_reply_as_callback( $ctx, (string) $route['hub_dispatch_cb'] );
			return true;
		}
		if ( isset( $route['toggle_setting'] ) ) {
			$key = (string) $route['toggle_setting'];
			if ( in_array( $key, array( 'enabled', 'test_account_enabled' ), true ) ) {
				SimpleVPBot_Admin_Actions::toggle_bool_setting( $key );
				$msg = 'enabled' === $key ? '✅ enabled تغییر کرد.' : '✅ test_account_enabled تغییر کرد.';
				SimpleVPBot_Bot_Runtime::send_message( $plat, $chat_id, $msg );
				return true;
			}
		}
		if ( isset( $route['bulk_days'] ) ) {
			SimpleVPBot_Handler_Admin_Pnl::router_bulk_days_confirm( $plat, $chat_id, (int) $route['bulk_days'] );
			return true;
		}
		if ( isset( $route['bulk_gb'] ) ) {
			SimpleVPBot_Handler_Admin_Pnl::router_bulk_gb_confirm( $plat, $chat_id, (int) $route['bulk_gb'] );
			return true;
		}
		if ( isset( $route['admin_route'] ) && class_exists( 'SimpleVPBot_Handler_Admin' ) ) {
			return SimpleVPBot_Handler_Admin::dispatch_admin_route( $ctx, (string) $route['admin_route'] );
		}
		return false;
	}

	/**
	 * Map legacy hub submenu codes to panel tab keys.
	 *
	 * @param string $hub Hub code.
	 * @return string
	 */
	private static function hub_route_to_tab( $hub ) {
		$map = array(
			'blk' => 'users_bulk',
			'plc' => 'plan_cats',
			'pln' => 'plans',
			'crd' => 'cards',
			'pan' => 'xui_panels',
			'l2p' => 'l2tp_servers',
			'inl' => 'configs',
			'bot' => 'bots',
			'gen' => 'site_settings',
			'not' => 'notifications',
			'txt' => 'texts',
			'log' => 'logs',
			'brd' => 'broadcast',
		);
		$hub = sanitize_key( (string) $hub );
		return isset( $map[ $hub ] ) ? (string) $map[ $hub ] : '';
	}
}
