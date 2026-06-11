<?php
/**
 * Bot admin inbound/config facade.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Admin_Inbound
 */
class SimpleVPBot_Handler_Admin_Inbound {

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 */
	public static function open_configs_tab( $platform, $chat_id, $user ) {
		SimpleVPBot_Handler_Admin_Pnl::send_submenu( $platform, $chat_id, 'inl', array( 'user' => $user ) );
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 */
	public static function open_xui_panels_tab( $platform, $chat_id, $user ) {
		SimpleVPBot_Handler_Admin_Pnl::send_submenu( $platform, $chat_id, 'pan', array( 'user' => $user ) );
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 */
	public static function open_l2tp_tab( $platform, $chat_id, $user ) {
		SimpleVPBot_Handler_Admin_Pnl::send_submenu( $platform, $chat_id, 'l2p', array( 'user' => $user ) );
	}

	/**
	 * @param string               $platform Platform.
	 * @param int                  $chat_id  Chat id.
	 * @param array<string, mixed> $ctx      Context with user.
	 */
	public static function send_inbounds_list( $platform, $chat_id, $ctx ) {
		$panels = class_exists( 'SimpleVPBot_Model_Panel' ) ? SimpleVPBot_Model_Panel::all_active_ordered() : array();
		if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			$allowed = SimpleVPBot_Bot_Reseller_Scope::bot_admin_allowed_panel_ids();
			if ( is_array( $allowed ) ) {
				$flip = array_flip( array_map( 'intval', $allowed ) );
				$panels = array_values(
					array_filter(
						$panels,
						static function ( $pw ) use ( $flip ) {
							return $pw && isset( $flip[ (int) ( $pw->id ?? 0 ) ] );
						}
					)
				);
				if ( empty( $panels ) ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::msg( $platform, $chat_id, 'msg.admin.panel_inactive' ) );
					return;
				}
			}
		}
		if ( count( $panels ) > 1 ) {
			$rows = array();
			foreach ( $panels as $pw ) {
				$pid = (int) $pw->id;
				$lbl = trim( (string) ( $pw->label ?? '' ) );
				if ( '' === $lbl ) {
					$lbl = 'پنل #' . $pid;
				}
				$rows[] = array( array( 'text' => '📡 پنل #' . $pid . ' · ' . mb_substr( $lbl, 0, 24 ) ) );
			}
			if ( empty( $rows ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::msg( $platform, $chat_id, 'msg.admin.panel_inactive' ) );
				return;
			}
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				self::msg( $platform, $chat_id, 'msg.admin.inbound_pick_panel' ),
				array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $rows ) )
			);
			return;
		}
		$pid = 0;
		if ( count( $panels ) === 1 ) {
			$pid = (int) $panels[0]->id;
		}
		self::send_inbounds_list_for_panel( $platform, $chat_id, $ctx, $pid );
	}

	/**
	 * List inbounds for one 3x-ui panel (sets svp_ibctx_{user} for follow-up callbacks).
	 *
	 * @param string               $platform Platform.
	 * @param int                  $chat_id  Chat id.
	 * @param array<string, mixed> $ctx      Context.
	 * @param int                  $panel_id 0 = legacy settings panel; else svp_panels.id.
	 */
	public static function send_inbounds_list_for_panel( $platform, $chat_id, $ctx, $panel_id ) {
		$panel_id = (int) $panel_id;
		if ( $panel_id < 0 ) {
			$panel_id = 0;
		}
		if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) && $panel_id > 0 ) {
			$allowed = SimpleVPBot_Bot_Reseller_Scope::bot_admin_allowed_panel_ids();
			if ( is_array( $allowed ) && ! in_array( $panel_id, array_map( 'intval', $allowed ), true ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::msg( $platform, $chat_id, 'msg.admin.panel_inactive' ) );
				return;
			}
		}
		$user = isset( $ctx['user'] ) ? $ctx['user'] : null;
		if ( $user && ! empty( $user->id ) ) {
			set_transient( 'svp_ibctx_' . (int) $user->id, array( 'panel_id' => $panel_id ), 600 );
		}
		$r = SimpleVPBot_Service_Admin_Ops::inbounds_list( $panel_id );
		if ( empty( $r['ok'] ) || empty( $r['data']['inbounds'] ) || ! is_array( $r['data']['inbounds'] ) ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				self::msg(
					$platform,
					$chat_id,
					'msg.admin.inbound_list_empty',
					array( 'message' => (string) ( $r['message'] ?? self::msg( $platform, $chat_id, 'msg.admin.fallback.inbound_list_empty' ) ) )
				)
			);
			return;
		}
		$rows = array();
		foreach ( array_slice( $r['data']['inbounds'], 0, 20 ) as $inb ) {
			$ii = (int) ( $inb['id'] ?? 0 );
			if ( $ii < 1 ) {
				continue;
			}
			$rem = mb_substr( (string) ( $inb['remark'] ?? '' ), 0, 18 );
			$lab = '📌 Inbound #' . $ii . ' ' . (string) ( $inb['protocol'] ?? '?' ) . ' ' . $rem;
			if ( mb_strlen( $lab ) > 64 ) {
				continue;
			}
			$rows[] = array( array( 'text' => $lab ) );
		}
		if ( empty( $rows ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::msg( $platform, $chat_id, 'msg.admin.inbound_none' ) );
			return;
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			self::msg( $platform, $chat_id, 'msg.admin.inbound_pick_one' ),
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $rows ) )
		);
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @return object|null
	 */
	private static function resolve_admin_user( $platform, $chat_id ) {
		if ( ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return null;
		}
		return 'bale' === $platform
			? SimpleVPBot_Model_User::find_by_bale( (int) $chat_id )
			: SimpleVPBot_Model_User::find_by_telegram( (int) $chat_id );
	}

	/**
	 * @param string               $platform Platform.
	 * @param int                  $chat_id  Chat id.
	 * @param string               $key      Text key.
	 * @param array<string,string> $vars     Placeholders.
	 * @return string
	 */
	private static function msg( $platform, $chat_id, $key, array $vars = array() ) {
		$u = self::resolve_admin_user( $platform, $chat_id );
		$t = SimpleVPBot_Texts::get_for_user( $key, $u );
		return empty( $vars ) ? $t : SimpleVPBot_Texts::format( $t, $vars );
	}

	/**
	 * @param string               $platform   Platform.
	 * @param int                  $chat_id    Chat id.
	 * @param int                  $inbound_id Inbound id.
	 * @param array<string, mixed> $ctx        Context with user.
	 */
	public static function send_clients( $platform, $chat_id, $inbound_id, $ctx ) {
		$user = isset( $ctx['user'] ) ? $ctx['user'] : null;
		$iid  = (int) $inbound_id;
		$pid  = 1;
		if ( $user && ! empty( $user->id ) ) {
			$ibx = get_transient( 'svp_ibctx_' . (int) $user->id );
			if ( is_array( $ibx ) && isset( $ibx['panel_id'] ) ) {
				$pid = (int) $ibx['panel_id'];
				if ( $pid < 0 ) {
					$pid = 0;
				}
			}
		}
		if ( ! self::guard_panel( $platform, $chat_id, $pid ) ) {
			return;
		}
		$clients = SimpleVPBot_Service_Admin_Ops::inbound_clients( $iid, $pid );
		if ( empty( $clients['ok'] ) || empty( $clients['data']['clients'] ) || ! is_array( $clients['data']['clients'] ) ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				self::msg(
					$platform,
					$chat_id,
					'msg.admin.inbound_clients_empty',
					array( 'message' => (string) ( $clients['message'] ?? self::msg( $platform, $chat_id, 'msg.admin.fallback.no_clients' ) ) )
				)
			);
			return;
		}
		$list = $clients['data']['clients'];
		$em   = array();
		foreach ( $list as $c ) {
			if ( is_array( $c ) && ! empty( $c['email'] ) ) {
				$em[] = (string) $c['email'];
			}
		}
		if ( $user && $user->id ) {
			set_transient( 'svp_inbcl_' . (int) $user->id, array( 'iid' => $iid, 'em' => $em, 'panel_id' => $pid ), 600 );
		}
		$rows = array();
		foreach ( array_values( array_slice( $em, 0, 12 ) ) as $ix => $e ) {
			$rows[] = array( array( 'text' => '📧' . (int) $ix . '·' . mb_substr( $e, 0, 28 ) ) );
		}
		if ( empty( $rows ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::msg( $platform, $chat_id, 'msg.admin.inbound_email_missing' ) );
			return;
		}
		$rows[] = array(
			array(
				'text' => SimpleVPBot_Texts::format(
					SimpleVPBot_Texts::get_for_user( 'btn.admin.inbound_autolink', self::resolve_admin_user( $platform, $chat_id ), '⚡ autolink #{id}' ),
					array( 'id' => (string) $iid )
				),
			),
		);
		$rows[] = array(
			array(
				'text' => SimpleVPBot_Texts::get_for_user( 'btn.admin.inbound_back_list', self::resolve_admin_user( $platform, $chat_id ), '↩ لیست Inbound' ),
			),
		);
		$t = self::msg( $platform, $chat_id, 'msg.admin.inbound_clients_prompt', array( 'id' => (string) $iid ) );
		$t .= (string) ( $clients['data']['inb_remark'] ?? '' );
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$t,
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $rows ) )
		);
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @param int                  $idx Index in stored emails.
	 */
	public static function start_link( array $ctx, $idx ) {
		$user = isset( $ctx['user'] ) ? $ctx['user'] : null;
		if ( ! $user || ! $user->id ) {
			return;
		}
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$st       = get_transient( 'svp_inbcl_' . (int) $user->id );
		if ( ! is_array( $st ) || empty( $st['iid'] ) || empty( $st['em'] ) || ! is_array( $st['em'] ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::msg( $platform, $chat_id, 'msg.admin.inbound_session_expired' ) );
			return;
		}
		$em = (string) ( $st['em'][ (int) $idx ] ?? '' );
		if ( '' === $em ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::msg( $platform, $chat_id, 'msg.admin.inbound_row_invalid' ) );
			return;
		}
		$pn = isset( $st['panel_id'] ) ? (int) $st['panel_id'] : 1;
		if ( $pn < 0 ) {
			$pn = 0;
		}
		SimpleVPBot_State::set( (int) $user->id, 'admin_inb_uid', array( 'iid' => (int) $st['iid'], 'em' => $em, 'panel_id' => $pn ) );
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			self::msg( $platform, $chat_id, 'msg.admin.inbound_link_user_prompt', array( 'email' => $em ) )
		);
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param int    $panel_id Panel id.
	 * @return bool
	 */
	private static function guard_panel( $platform, $chat_id, $panel_id ) {
		$pid = (int) $panel_id;
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return true;
		}
		$admin_u = self::resolve_admin_user( $platform, $chat_id );
		if ( $admin_u ) {
			SimpleVPBot_Bot_Reseller_Scope::set_acting_admin_user( (int) $admin_u->id );
		}
		if ( ! SimpleVPBot_Bot_Reseller_Scope::is_scoped_bot_admin_context() ) {
			return true;
		}
		if ( $pid < 1 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::msg( $platform, $chat_id, 'msg.admin.panel_inactive' ) );
			return false;
		}
		$allowed = SimpleVPBot_Bot_Reseller_Scope::bot_admin_allowed_panel_ids();
		if ( is_array( $allowed ) && ! in_array( $pid, array_map( 'intval', $allowed ), true ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::msg( $platform, $chat_id, 'msg.admin.panel_inactive' ) );
			return false;
		}
		return true;
	}
}
