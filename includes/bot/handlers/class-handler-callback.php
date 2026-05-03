<?php
/**
 * Inline callback router.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Callback
 */
class SimpleVPBot_Handler_Callback {

	/**
	 * Handle callback query.
	 *
	 * @param array<string, mixed> $ctx Context.
	 */
	public static function handle( array $ctx ) {
		$platform = (string) $ctx['platform'];
		$cb       = is_array( $ctx['cb'] ?? null ) ? $ctx['cb'] : array();
		$user     = $ctx['user'];
		$from     = isset( $cb['from'] ) && is_array( $cb['from'] ) ? $cb['from'] : array();
		$from_id  = (int) ( $from['id'] ?? 0 );
		$data     = isset( $cb['data'] ) ? (string) $cb['data'] : '';
		if ( 'noop' === $data || 0 === strpos( $data, 'alnoop:' ) ) {
			SimpleVPBot_Bot_Runtime::answer_callback_query(
				$platform,
				array(
					'callback_query_id' => isset( $cb['id'] ) ? (string) $cb['id'] : '',
				)
			);
			return;
		}
		$cb_id     = isset( $cb['id'] ) ? (string) $cb['id'] : '';
		$msg       = isset( $cb['message'] ) && is_array( $cb['message'] ) ? $cb['message'] : array();
		$chat_id   = isset( $msg['chat']['id'] ) ? (int) $msg['chat']['id'] : 0;
		$msg_id    = isset( $msg['message_id'] ) ? (int) $msg['message_id'] : 0;

		SimpleVPBot_Bot_Runtime::answer_callback_query(
			$platform,
			array(
				'callback_query_id' => $cb_id,
			)
		);

		if ( $user && 'noop' !== $data ) {
			$is_adm = SimpleVPBot_Router::is_platform_admin( $platform, $from_id );
			$skip_clear = ( (int) $user->admin_mode && $is_adm )
				|| 0 === strpos( $data, 'adm:' )
				|| 0 === strpos( $data, 'reg:' )
				|| 0 === strpos( $data, 'rc:' )
				|| ( 'buy_discount' === (string) $user->state && 0 === strpos( $data, 'buy:' ) );
			if ( ! $skip_clear && SimpleVPBot_State::clear_blocking_state_on_callback( $platform, $from_id, $user, $chat_id, $data ) ) {
				$user = SimpleVPBot_Model_User::find( (int) $user->id );
			}
		}

		$is_admin_side = SimpleVPBot_Router::is_platform_admin( $platform, $from_id );
		if ( ! $is_admin_side ) {
			if ( 0 === strpos( $data, 'reg:' ) || 0 === strpos( $data, 'rc:' ) || 0 === strpos( $data, 'adm:' ) ) {
				return;
			}
			if ( $user && in_array( (string) $user->status, array( 'pending', 'rejected', 'blocked' ), true ) ) {
				return;
			}
		}

		$parts = explode( ':', $data );
		$head0 = $parts[0] ?? '';
		if ( 'wal' === $head0 && isset( $parts[1] ) && 'h' === $parts[1] && $user ) {
			$hist = SimpleVPBot_Model_Transaction::history( (int) $user->id, 10 );
			$t    = "📜 تاریخچه\n➖➖➖➖➖➖➖➖\n";
			foreach ( $hist as $h ) {
				$t .= '📌 ' . (string) $h->type . ' · ' . number_format( (float) $h->amount ) . ' · ' . (string) $h->status . "\n";
			}
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t );
			return;
		}
		if ( 'sup' === $head0 && isset( $parts[1] ) ) {
			if ( 'c' === $parts[1] ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '📞 لطفاً با ادمین از طریق سایت تماس بگیرید.' );
			} else {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get( 'faq.connection', 'FAQ' ) );
			}
			return;
		}
		if ( 'sync' === $head0 && isset( $parts[1] ) && $user ) {
			if ( 'g' === $parts[1] ) {
				SimpleVPBot_Handler_Sync::generate_code( $platform, $chat_id, $user );
			} elseif ( 'i' === $parts[1] ) {
				SimpleVPBot_Handler_Sync::prompt_code( $user );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '🔑 کد ۶ رقمی را که در ربات دیگر ساخته‌اید ارسال کنید.' );
			}
			return;
		}

		$head  = $parts[0] ?? '';

		if ( 'reg' === $head && isset( $parts[1], $parts[2] ) ) {
			self::handle_registration( $platform, $parts[1], (int) $parts[2], $from, $chat_id, $msg_id );
			return;
		}
		if ( 'rc' === $head && isset( $parts[1], $parts[2] ) ) {
			self::handle_receipt( $platform, $parts[1], (int) $parts[2], $from, $chat_id, $msg_id );
			return;
		}
		if ( 'buy' === $head ) {
			if ( ! $user ) {
				return;
			}
			SimpleVPBot_Handler_Buy::handle_callback(
				array(
					'platform' => $platform,
					'user'     => $user,
					'parts'    => $parts,
					'chat_id'  => $chat_id,
					'msg_id'   => $msg_id,
				)
			);
			return;
		}
		if ( 'svc' === $head && isset( $parts[1], $parts[2] ) ) {
			if ( ! $user ) {
				return;
			}
			if ( 'w' === (string) $parts[1] && isset( $parts[3] ) ) {
				SimpleVPBot_Handler_Service::handle_config_wire(
					array(
						'platform' => $platform,
						'user'     => $user,
						'svc_id'   => (int) $parts[2],
						'uri_idx'  => (int) $parts[3],
						'chat_id'  => $chat_id,
						'from_id'  => $from_id,
					)
				);
				return;
			}
			SimpleVPBot_Handler_Service::handle_callback(
				array(
					'platform' => $platform,
					'user'     => $user,
					'action'   => (string) $parts[1],
					'svc_id'   => (int) $parts[2],
					'chat_id'  => $chat_id,
					'msg_id'   => $msg_id,
					'from_id'  => $from_id,
				)
			);
			return;
		}
		// Legacy inline hub (adm:*). Admin UI prefers Reply routing via Handler_Admin::route_text.
		if ( 'adm' === $head && SimpleVPBot_Router::is_platform_admin( $platform, $from_id ) ) {
			SimpleVPBot_Handler_Admin_Hub::handle(
				array(
					'platform' => $platform,
					'chat_id'  => $chat_id,
					'parts'    => $parts,
					'user'     => $user,
					'msg_id'   => $msg_id,
				)
			);
			return;
		}
	}

	/**
	 * Approve/reject registration from Reply keyboard (no callback message id).
	 *
	 * @param string               $platform Platform.
	 * @param string               $action a|r.
	 * @param int                  $uid User id.
	 * @param array<string, mixed> $from Telegram/Bale from.
	 * @param int                  $admin_chat Admin chat id.
	 * @param int                  $admin_msg_id Legacy inline message id (0 for Reply).
	 */
	public static function admin_apply_registration( $platform, $action, $uid, array $from, $admin_chat, $admin_msg_id = 0 ) {
		self::handle_registration( $platform, $action, $uid, $from, $admin_chat, (int) $admin_msg_id );
	}

	/**
	 * Approve/reject receipt from Reply keyboard.
	 *
	 * @param string               $platform Platform.
	 * @param string               $action a|r.
	 * @param int                  $rid Receipt id.
	 * @param array<string, mixed> $from Telegram/Bale from.
	 * @param int                  $admin_chat Admin chat id.
	 * @param int                  $admin_msg_id Legacy (0).
	 */
	public static function admin_apply_receipt( $platform, $action, $rid, array $from, $admin_chat, $admin_msg_id = 0 ) {
		self::handle_receipt( $platform, $action, $rid, $from, $admin_chat, (int) $admin_msg_id );
	}

	private static function handle_registration( $platform, $action, $uid, array $from, $admin_chat, $admin_msg_id ) {
		$uname = (string) ( $from['username'] ?? '' );
		$label = $uname ? '@' . $uname : (string) ( $from['first_name'] ?? '' );
		$user  = SimpleVPBot_Model_User::find( $uid );
		if ( ! $user ) {
			return;
		}
		$pending = SimpleVPBot_Model_Pending::find_open_for_user( $uid );
		if ( ! $pending || 'pending' !== $pending->status ) {
			return;
		}
		if ( 'a' === $action ) {
			SimpleVPBot_Model_User::update( $uid, array( 'status' => 'approved', 'approved_by' => $label, 'approved_at' => current_time( 'mysql' ) ) );
			SimpleVPBot_Model_Pending::update(
				(int) $pending->id,
				array(
					'status'      => 'approved',
					'decided_at'  => current_time( 'mysql' ),
					'decided_by'  => $label,
				)
			);
			$btn_text = SimpleVPBot_Texts::format(
				SimpleVPBot_Texts::get( 'btn.approved_by', '✅ تایید شد توسط {admin}' ),
				array( 'admin' => $label )
			);
			self::finalize_admin_messages( $pending, $platform, $btn_text, true );
			self::notify_user_status( $user, SimpleVPBot_Texts::get( 'msg.approval_approved', '✅ تایید شد.' ), true );
		} elseif ( 'r' === $action ) {
			SimpleVPBot_Model_User::update( $uid, array( 'status' => 'rejected' ) );
			SimpleVPBot_Model_Pending::update(
				(int) $pending->id,
				array(
					'status'     => 'rejected',
					'decided_at' => current_time( 'mysql' ),
					'decided_by' => $label,
				)
			);
			$btn_text = SimpleVPBot_Texts::format(
				SimpleVPBot_Texts::get( 'btn.rejected_by', '❌ رد شد توسط {admin}' ),
				array( 'admin' => $label )
			);
			self::finalize_admin_messages( $pending, $platform, $btn_text, false );
			self::notify_user_status( $user, SimpleVPBot_Texts::get( 'msg.approval_rejected', '⛔ رد شدید.' ), false );
		}
	}

	/**
	 * Update all admin messages to static button.
	 *
	 * @param object $pending Pending row.
	 * @param string $platform Current platform (for same-bot instant feedback).
	 * @param string $btn_text Button text.
	 * @param bool   $approved Approved flag.
	 */
	private static function finalize_admin_messages( $pending, $platform, $btn_text, $approved ) {
		$list = json_decode( (string) $pending->admin_messages_json, true );
		if ( ! is_array( $list ) ) {
			return;
		}
		$markup = array(
			'inline_keyboard' => array(
				array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( $btn_text, 64 ), 'callback_data' => 'noop' ) ),
			),
		);
		foreach ( $list as $m ) {
			$plat = isset( $m['platform'] ) ? (string) $m['platform'] : 'telegram';
			$cid  = (int) ( $m['chat_id'] ?? 0 );
			$mid  = (int) ( $m['message_id'] ?? 0 );
			if ( ! $cid || ! $mid ) {
				continue;
			}
			$res = SimpleVPBot_Bot_Runtime::edit_reply_markup( $plat, $cid, $mid, $markup );
			if ( ! is_array( $res ) || empty( $res['ok'] ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $plat, $cid, $btn_text );
			}
		}
	}

	/**
	 * Notify user on both bots.
	 *
	 * @param object $user User.
	 * @param string $text Text.
	 * @param bool   $with_menu With main menu.
	 */
	private static function notify_user_status( $user, $text, $with_menu ) {
		$extra = $with_menu ? array( 'reply_markup' => SimpleVPBot_Keyboards::user_main_reply() ) : array();
		if ( ! empty( $user->tg_user_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( 'telegram', (int) $user->tg_user_id, $text, $extra );
		}
		if ( ! empty( $user->bale_user_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( 'bale', (int) $user->bale_user_id, $text, $extra );
		}
	}

	/**
	 * Receipt approve/reject.
	 *
	 * @param string               $platform Platform.
	 * @param string               $action a|r.
	 * @param int                  $rid Receipt id.
	 * @param array<string, mixed> $from From.
	 * @param int                  $admin_chat Chat.
	 * @param int                  $admin_msg_id Msg id.
	 */
	private static function handle_receipt( $platform, $action, $rid, array $from, $admin_chat, $admin_msg_id ) {
		$uname = (string) ( $from['username'] ?? '' );
		$label = $uname ? '@' . $uname : (string) ( $from['first_name'] ?? '' );
		if ( 'a' === $action ) {
			$res = SimpleVPBot_Receipt_Processor::approve( (int) $rid, $label );
			if ( ! empty( $res['purchase_failed'] ) ) {
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					(int) $admin_chat,
					'⚠️ رسید #' . (int) $rid . ' تایید شد اما ساخت سرویس روی پنل ناموفق بود. رسید در حالت «در دست بررسی» باقی ماند؛ پس از رفع مشکل پنل دوباره تلاش کنید.'
				);
			}
			return;
		}
		if ( 'r' === $action ) {
			SimpleVPBot_Receipt_Processor::reject( (int) $rid, $label );
		}
	}
}
