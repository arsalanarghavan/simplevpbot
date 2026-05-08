<?php
/**
 * Dispatch updates to handlers.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Router
 */
class SimpleVPBot_Router {

	/**
	 * Dispatch.
	 *
	 * @param string              $platform telegram|bale (REST param).
	 * @param array<string, mixed> $update Update array.
	 */
	public static function dispatch( $platform, array $update ) {
		if ( ! SimpleVPBot_Settings::get( 'enabled', true ) ) {
			if ( class_exists( 'SimpleVPBot_Logger' ) ) {
				SimpleVPBot_Logger::warning(
					'router skipped: bot processing disabled in settings',
					array(
						'scope' => class_exists( 'SimpleVPBot_Bot_Context' ) && SimpleVPBot_Bot_Context::is_reseller_bot() ? 'reseller' : 'main',
						'reseller_svp_user_id' => class_exists( 'SimpleVPBot_Bot_Context' ) ? (int) SimpleVPBot_Bot_Context::reseller_svp_user_id() : 0,
						'platform' => $platform,
					)
				);
			}
			return;
		}
		$plat = ( 'bale' === $platform ) ? 'bale' : 'telegram';
		if ( 'bale' === $plat && ! empty( $update['pre_checkout_query'] ) && is_array( $update['pre_checkout_query'] ) ) {
			SimpleVPBot_Handler_Buy::handle_bale_pre_checkout( $update['pre_checkout_query'] );
			return;
		}
		$from = null;
		$chat = null;
		$text = null;
		$cb   = null;
		if ( ! empty( $update['callback_query'] ) && is_array( $update['callback_query'] ) ) {
			$cb   = $update['callback_query'];
			$from = isset( $cb['from'] ) && is_array( $cb['from'] ) ? $cb['from'] : null;
			$msg  = isset( $cb['message'] ) && is_array( $cb['message'] ) ? $cb['message'] : array();
			$chat = isset( $msg['chat'] ) && is_array( $msg['chat'] ) ? $msg['chat'] : null;
		} elseif ( ! empty( $update['message'] ) && is_array( $update['message'] ) ) {
			$m = $update['message'];
			if ( 'bale' === $plat && ! empty( $m['successful_payment'] ) && is_array( $m['successful_payment'] ) ) {
				$from = isset( $m['from'] ) && is_array( $m['from'] ) ? $m['from'] : null;
				$chat = isset( $m['chat'] ) && is_array( $m['chat'] ) ? $m['chat'] : null;
				if ( $from && $chat ) {
					$sp_from = (int) ( $from['id'] ?? 0 );
					$sp_chat = (int) ( $chat['id'] ?? 0 );
					if ( $sp_from && $sp_chat ) {
						$user = self::resolve_user( $plat, $sp_from, $from );
						self::log_incoming_bot_update( $plat, $update, $user, $sp_from, $sp_chat, null, '' );
						SimpleVPBot_Handler_Buy::handle_successful_payment(
							array(
								'platform' => $plat,
								'user'     => $user,
								'chat_id'  => $sp_chat,
								'message'  => $m,
							)
						);
					}
				}
				return;
			}
			$from = isset( $m['from'] ) && is_array( $m['from'] ) ? $m['from'] : null;
			$chat = isset( $m['chat'] ) && is_array( $m['chat'] ) ? $m['chat'] : null;
			$text = isset( $m['text'] ) ? (string) $m['text'] : '';
		}
		if ( ! $from || ! $chat ) {
			return;
		}
		$from_id = (int) ( $from['id'] ?? 0 );
		$chat_id = (int) ( $chat['id'] ?? 0 );
		if ( ! $from_id || ! $chat_id ) {
			return;
		}

		$user = self::resolve_user( $plat, $from_id, $from );
		self::log_incoming_bot_update( $plat, $update, $user, $from_id, $chat_id, $cb, $text );
		if ( $cb ) {
			SimpleVPBot_Handler_Callback::handle(
				array(
					'platform' => $plat,
					'cb'       => $cb,
					'user'     => $user,
					'chat_id'  => $chat_id,
					'from'     => $from,
				)
			);
			return;
		}

		$cmd = '';
		if ( $text && preg_match( '#^/([a-zA-Z0-9_]+)(?:@[a-zA-Z0-9_]+)?(\s|$)#u', $text, $m ) ) {
			$cmd = strtolower( $m[1] );
		}

		if ( 'start' === $cmd ) {
			SimpleVPBot_Handler_Start::handle(
				array(
					'platform' => $plat,
					'chat_id'  => $chat_id,
					'from'     => $from,
					'user'     => $user,
					'text'     => $text,
				)
			);
			return;
		}

		if ( 'admin' === $cmd && self::is_platform_admin( $plat, $from_id ) ) {
			SimpleVPBot_Model_User::update( (int) $user->id, array( 'admin_mode' => 1 ) );
			$u2 = SimpleVPBot_Model_User::find( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message(
				$plat,
				$chat_id,
				$u2 ? SimpleVPBot_Texts::get_for_user( 'msg.admin_panel_enabled', $u2 ) : '🔐 Admin panel enabled.',
				array( 'reply_markup' => SimpleVPBot_Keyboards::admin_main_reply_for_chat( $plat, $chat_id ) )
			);
			return;
		}

		if ( 'lang' === $cmd && $user ) {
			$parts = preg_split( '/\s+/u', trim( (string) $text ), 2 );
			$sub   = isset( $parts[1] ) ? strtolower( trim( (string) $parts[1] ) ) : '';
			if ( ! in_array( $sub, array( 'fa', 'en', 'persian', 'english' ), true ) ) {
				SimpleVPBot_Bot_Runtime::send_message(
					$plat,
					$chat_id,
					SimpleVPBot_Texts::get_for_user( 'msg.lang_usage', $user )
				);
				return;
			}
			$loc = ( 'en' === $sub || 'english' === $sub ) ? 'en' : 'fa';
			SimpleVPBot_Model_User::update( (int) $user->id, array( 'bot_locale' => $loc ) );
			$user = SimpleVPBot_Model_User::find( (int) $user->id );
			if ( $user ) {
				SimpleVPBot_Bot_Runtime::send_message(
					$plat,
					$chat_id,
					SimpleVPBot_Texts::get_for_user( 'msg.lang_changed', $user )
				);
			}
			return;
		}

		if ( ! $user ) {
			SimpleVPBot_Bot_Runtime::send_message( $plat, $chat_id, SimpleVPBot_Texts::get( 'msg.start_first', '' ) );
			return;
		}

		if ( 'blocked' === $user->status ) {
			SimpleVPBot_Bot_Runtime::send_message( $plat, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.blocked', $user ) );
			return;
		}
		// Telegram users are auto-approved; only Bale goes through admin approval flow.
		if ( 'telegram' === $plat && in_array( (string) $user->status, array( 'pending', 'rejected' ), true ) ) {
			SimpleVPBot_Model_User::update(
				(int) $user->id,
				array(
					'status'      => 'approved',
					'approved_by' => (string) ( $user->approved_by ?: 'auto:telegram' ),
					'approved_at' => (string) ( $user->approved_at ?: current_time( 'mysql' ) ),
				)
			);
			$user->status = 'approved';
		}
		if ( 'pending' === $user->status || 'rejected' === $user->status ) {
			SimpleVPBot_Bot_Runtime::send_message( $plat, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.approval_wait', $user ) );
			return;
		}

		$text_trim_mod = trim( (string) $text );
		if ( '' !== $text_trim_mod && self::is_platform_admin( $plat, $from_id ) && 'approved' === (string) $user->status ) {
			$from_arr = is_array( $from ) ? $from : array();
			if ( SimpleVPBot_Handler_Admin_Hub::route_moderation_reply_text( $plat, $chat_id, $from_id, $user, $text_trim_mod, $from_arr ) ) {
				return;
			}
		}

		$text_trim = trim( (string) $text );
		if ( $text_trim !== '' && SimpleVPBot_State::interrupt_blocking_state_on_main_menu_text( $plat, $from_id, $user, $text_trim ) ) {
			$user = SimpleVPBot_Model_User::find( (int) $user->id );
			if ( ! $user ) {
				return;
			}
		}

		$state = (string) $user->state;
		if ( 'awaiting_sync_code' === $state ) {
			SimpleVPBot_Handler_Sync::handle_code(
				array(
					'platform' => $plat,
					'chat_id'  => $chat_id,
					'user'     => $user,
					'text'     => trim( (string) $text ),
				)
			);
			return;
		}
		if ( 'buy_discount' === $state || 0 === strpos( $state, 'buy_' ) ) {
			SimpleVPBot_Handler_Buy::handle_state(
				array(
					'platform' => $plat,
					'chat_id'  => $chat_id,
					'user'     => $user,
					'text'     => (string) $text,
				)
			);
			return;
		}
		if ( preg_match( '/^svc_addvol_\d+$/', $state ) || preg_match( '/^svc_addusers_\d+$/', $state ) ) {
			SimpleVPBot_Handler_Buy::handle_state(
				array(
					'platform' => $plat,
					'chat_id'  => $chat_id,
					'user'     => $user,
					'text'     => (string) $text,
					'from_id'  => $from_id,
				)
			);
			return;
		}
		if ( 0 === strpos( $state, 'adm_service_transfer_' ) && self::is_platform_admin( $plat, $from_id ) ) {
			SimpleVPBot_Handler_Admin::handle_transfer_target_text(
				array(
					'platform' => $plat,
					'chat_id'  => $chat_id,
					'user'     => $user,
					'text'     => (string) $text,
				)
			);
			return;
		}
		if ( preg_match( '/^svc_al_(pct|exp|ip)_\d+$/', $state ) ) {
			SimpleVPBot_Handler_Service::handle_alert_threshold_text(
				array(
					'platform' => $plat,
					'chat_id'  => $chat_id,
					'user'     => $user,
					'text'     => (string) $text,
					'from_id'  => $from_id,
				)
			);
			return;
		}
		if ( 0 === strpos( $state, 'svc_note_' ) || 0 === strpos( $state, 'svc_rename_' ) ) {
			SimpleVPBot_Handler_Service::handle_note_text(
				array(
					'platform' => $plat,
					'chat_id'  => $chat_id,
					'user'     => $user,
					'text'     => (string) $text,
					'from_id'  => $from_id,
				)
			);
			return;
		}

		if ( ! empty( $update['message']['photo'] ) && is_array( $update['message']['photo'] ) ) {
			SimpleVPBot_Handler_Buy::handle_receipt_photo(
				array(
					'platform' => $plat,
					'chat_id'  => $chat_id,
					'user'     => $user,
					'message'  => $update['message'],
				)
			);
			return;
		}

		if ( (int) $user->admin_mode && self::is_platform_admin( $plat, $from_id ) ) {
			$admin_msg = isset( $update['message'] ) && is_array( $update['message'] ) ? $update['message'] : array();
			if ( ! empty( $admin_msg['document'] ) && is_array( $admin_msg['document'] ) ) {
				$doc_done = SimpleVPBot_Handler_Admin::route_message(
					array(
						'platform' => $plat,
						'chat_id'  => $chat_id,
						'user'     => $user,
						'message'  => $admin_msg,
					)
				);
				if ( $doc_done ) {
					return;
				}
			}
			SimpleVPBot_Handler_Admin::route_text(
				array(
					'platform' => $plat,
					'chat_id'  => $chat_id,
					'user'     => $user,
					'text'     => (string) $text,
					'message'  => $admin_msg,
					'from'     => is_array( $from ) ? $from : array(),
				)
			);
			return;
		}

		SimpleVPBot_Handler_User_Menu::route_text(
			array(
				'platform' => $plat,
				'chat_id'  => $chat_id,
				'user'     => $user,
				'text'     => (string) $text,
			)
		);
	}

	/**
	 * Is admin on this platform.
	 *
	 * @param string $platform telegram|bale.
	 * @param int    $from_id From id.
	 * @return bool
	 */
	public static function is_platform_admin( $platform, $from_id ) {
		if ( class_exists( 'SimpleVPBot_Bot_Context' ) && SimpleVPBot_Bot_Context::is_reseller_bot() ) {
			$admin_ids = array();
			$prof      = class_exists( 'SimpleVPBot_Bot_Context' ) ? SimpleVPBot_Bot_Context::reseller_profile() : null;
			if ( $prof && class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
				$raw_ids   = 'bale' === $platform ? ( $prof->admin_bale_ids ?? '' ) : ( $prof->admin_telegram_ids ?? '' );
				$admin_ids = array_map( 'intval', (array) SimpleVPBot_Model_Reseller_Bot_Profile::decode_admin_ids( $raw_ids ) );
			}
			if ( in_array( (int) $from_id, $admin_ids, true ) ) {
				return true;
			}
			$reseller_id = (int) SimpleVPBot_Bot_Context::reseller_svp_user_id();
			if ( $reseller_id > 0 && class_exists( 'SimpleVPBot_Model_User' ) ) {
				$reseller = SimpleVPBot_Model_User::find( $reseller_id );
				if ( $reseller ) {
					$owner_id = 'bale' === $platform ? (int) ( $reseller->bale_user_id ?? 0 ) : (int) ( $reseller->tg_user_id ?? 0 );
					if ( $owner_id > 0 && $owner_id === (int) $from_id ) {
						return true;
					}
				}
			}
			// In reseller context, do not fall back to global admin id lists.
			return false;
		}
		$ids = 'bale' === $platform
			? (array) SimpleVPBot_Settings::get( 'admin_bale_ids', array() )
			: (array) SimpleVPBot_Settings::get( 'admin_telegram_ids', array() );
		$ids = array_map( 'intval', $ids );
		return in_array( (int) $from_id, $ids, true );
	}

	/**
	 * Whether this svp_users row is linked to a platform admin id (Telegram or Bale).
	 *
	 * @param object|null $user User row.
	 * @return bool
	 */
	public static function is_svp_user_bot_admin( $user ) {
		if ( ! $user || empty( $user->id ) ) {
			return false;
		}
		$tg = (int) ( $user->tg_user_id ?? 0 );
		$bl = (int) ( $user->bale_user_id ?? 0 );
		$tg_admins = array_map( 'intval', (array) SimpleVPBot_Settings::get( 'admin_telegram_ids', array() ) );
		$bl_admins = array_map( 'intval', (array) SimpleVPBot_Settings::get( 'admin_bale_ids', array() ) );
		return ( $tg > 0 && in_array( $tg, $tg_admins, true ) ) || ( $bl > 0 && in_array( $bl, $bl_admins, true ) );
	}

	/**
	 * Resolve or create user stub.
	 *
	 * @param string               $platform telegram|bale.
	 * @param int                  $from_id From id.
	 * @param array<string, mixed> $from From object.
	 * @return object|null
	 */
	private static function resolve_user( $platform, $from_id, array $from ) {
		if ( 'bale' === $platform ) {
			$user = SimpleVPBot_Model_User::find_by_bale( $from_id );
		} else {
			$user = SimpleVPBot_Model_User::find_by_telegram( $from_id );
		}
		return $user;
	}

	/**
	 * Persist one inbound update row (best-effort).
	 *
	 * @param string                    $plat   telegram|bale.
	 * @param array<string, mixed>      $update Update.
	 * @param object|null               $user   Resolved svp user.
	 * @param int                       $from_id From id.
	 * @param int                       $chat_id Chat id.
	 * @param array<string, mixed>|null $cb     Callback or null.
	 * @param string|null               $text   Message text.
	 */
	private static function log_incoming_bot_update( $plat, array $update, $user, $from_id, $chat_id, $cb, $text ) {
		if ( ! class_exists( 'SimpleVPBot_User_Activity_Log' ) ) {
			return;
		}
		SimpleVPBot_User_Activity_Log::log_bot_update(
			$plat,
			$update,
			$user,
			(int) $from_id,
			(int) $chat_id,
			is_array( $cb ) ? $cb : null,
			is_string( $text ) ? $text : ''
		);
	}
}
