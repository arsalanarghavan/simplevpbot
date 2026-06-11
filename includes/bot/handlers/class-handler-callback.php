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

		if ( 0 === strpos( $data, 'chjoin:' ) ) {
			self::handle_channel_join( $platform, $data, $user, $from_id, $chat_id, $cb_id );
			return;
		}

		$defer_cb_answer = 0 === strpos( $data, 'rc:' )
			|| 0 === strpos( $data, 'buy:cf:' )
			|| 0 === strpos( $data, 'buy:pm:' )
			|| 0 === strpos( $data, 'buy:sw:' )
			|| 0 === strpos( $data, 'buy:swy:' )
			|| 0 === strpos( $data, 'buy:bw:' )
			|| 0 === strpos( $data, 'svc:p:' )
			|| 0 === strpos( $data, 'svc:l:' )
			|| 0 === strpos( $data, 'svc:w:' );
		if ( ! $defer_cb_answer ) {
			SimpleVPBot_Bot_Runtime::answer_callback_query(
				$platform,
				array(
					'callback_query_id' => $cb_id,
				)
			);
		}

		if ( $user && 'noop' !== $data ) {
			$is_adm = SimpleVPBot_Router::is_platform_admin( $platform, $from_id );
			$skip_clear = ( (int) $user->admin_mode && $is_adm )
				|| 0 === strpos( $data, 'pnl:' )
				|| 0 === strpos( $data, 'reg:' )
				|| 0 === strpos( $data, 'rc:' )
				|| 0 === strpos( $data, 'chjoin:' )
				|| 0 === strpos( $data, 'buy:pm:' )
				|| 0 === strpos( $data, 'buy:bw:' )
				|| 0 === strpos( $data, 'buy:sw:' )
				|| 0 === strpos( $data, 'buy:cd:' )
				|| ( 'buy_discount' === (string) $user->state && 0 === strpos( $data, 'buy:' ) );
			if ( ! $skip_clear && SimpleVPBot_State::clear_blocking_state_on_callback( $platform, $from_id, $user, $chat_id, $data ) ) {
				$user = SimpleVPBot_Model_User::find( (int) $user->id );
			}
		}

		$is_admin_side = SimpleVPBot_Router::is_platform_admin( $platform, $from_id );
		if ( $is_admin_side && class_exists( 'SimpleVPBot_Bot_Admin_Guard' ) ) {
			SimpleVPBot_Bot_Admin_Guard::bootstrap_acting_admin_from_ctx(
				array(
					'platform' => $platform,
					'from'     => $from,
					'user'     => $user,
					'chat_id'  => $chat_id,
				)
			);
		}
		if ( ! $is_admin_side ) {
			if ( 0 === strpos( $data, 'reg:' ) || 0 === strpos( $data, 'rc:' ) || 0 === strpos( $data, 'pnl:' ) ) {
				if ( $defer_cb_answer ) {
					SimpleVPBot_Bot_Runtime::answer_callback_query(
						$platform,
						array(
							'callback_query_id' => $cb_id,
						)
					);
				}
				return;
			}
			if ( $user && in_array( (string) $user->status, array( 'pending', 'rejected', 'blocked' ), true ) ) {
				if ( 0 !== strpos( $data, 'chjoin:' ) ) {
					return;
				}
			}
		}

		$parts = explode( ':', $data );
		$head0 = $parts[0] ?? '';
		if ( 'wal' === $head0 && isset( $parts[1] ) && 'h' === $parts[1] && $user ) {
			SimpleVPBot_Handler_Wallet::show_history( $platform, $chat_id, $user );
			return;
		}
		if ( 'wal' === $head0 && isset( $parts[1] ) && 'tu' === $parts[1] && $user ) {
			SimpleVPBot_Handler_Wallet::begin_topup( $platform, $chat_id, $user );
			return;
		}
		if ( 'sup' === $head0 && isset( $parts[1] ) ) {
			if ( 'c' === $parts[1] ) {
				$msg = class_exists( 'SimpleVPBot_Support_Contacts' )
					? SimpleVPBot_Support_Contacts::contact_block( $platform )
					: '';
				if ( '' === $msg ) {
					$msg = '📞 لطفاً با ادمین از طریق سایت تماس بگیرید.';
				} else {
					$msg = "📞 تماس با پشتیبانی\n➖➖➖➖➖➖➖➖\n" . $msg;
				}
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $msg );
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
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.sync.prompt_code', $user ) );
			}
			return;
		}

		$head  = $parts[0] ?? '';

		if ( 'reg' === $head && isset( $parts[1], $parts[2] ) ) {
			self::handle_registration( $platform, $parts[1], (int) $parts[2], $from, $chat_id, $msg_id, $cb_id );
			return;
		}
		if ( 'rc' === $head && isset( $parts[1], $parts[2] ) ) {
			self::handle_receipt_callback( $platform, $parts, $from, $chat_id, $msg_id, $cb_id );
			return;
		}
		if ( 0 === strpos( $data, 'mkt_offer_apply:' ) ) {
			if ( ! $user || ! class_exists( 'SimpleVPBot_Marketing_Automation' ) ) {
				return;
			}
			$oid = (int) substr( $data, strlen( 'mkt_offer_apply:' ) );
			SimpleVPBot_Marketing_Automation::handle_callback_apply(
				array(
					'platform' => $platform,
					'chat_id'  => $chat_id,
					'user'     => $user,
				),
				$oid
			);
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
					'cb_id'    => $cb_id,
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
						'cb_id'    => $cb_id,
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
					'cb_id'    => $cb_id,
				)
			);
			return;
		}
		if ( 'pnl' === $head && SimpleVPBot_Router::is_platform_admin( $platform, $from_id ) ) {
			if ( isset( $parts[1] ) && 'pick' === $parts[1] && class_exists( 'SimpleVPBot_Bot_Admin_Plan_Picker' ) ) {
				SimpleVPBot_Bot_Admin_Plan_Picker::handle_callback(
					array(
						'platform' => $platform,
						'chat_id'  => $chat_id,
						'parts'    => $parts,
						'user'     => $user,
					)
				);
				return;
			}
			if ( isset( $parts[1] ) && 'cat' === $parts[1] && class_exists( 'SimpleVPBot_Handler_Admin_Catalog' ) ) {
				SimpleVPBot_Handler_Admin_Catalog::handle_callback(
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
			if ( class_exists( 'SimpleVPBot_Handler_Admin_Pnl' ) ) {
				SimpleVPBot_Handler_Admin_Pnl::handle(
					array(
						'platform' => $platform,
						'chat_id'  => $chat_id,
						'parts'    => $parts,
						'user'     => $user,
						'msg_id'   => $msg_id,
						'from_id'  => $from_id,
					)
				);
				return;
			}
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
		if ( 'r' === $action ) {
			self::show_receipt_reject_reasons( $platform, (int) $rid, $from, $admin_chat, (int) $admin_msg_id, '' );
			return;
		}
		self::handle_receipt_callback(
			$platform,
			array( 'rc', (string) $action, (string) (int) $rid ),
			$from,
			$admin_chat,
			(int) $admin_msg_id,
			''
		);
	}

	private static function handle_registration( $platform, $action, $uid, array $from, $admin_chat, $admin_msg_id, $cb_id = '' ) {
		$uname   = (string) ( $from['username'] ?? '' );
		$label   = $uname ? '@' . $uname : (string) ( $from['first_name'] ?? '' );
		$from_id = (int) ( $from['id'] ?? 0 );
		$admin_u = class_exists( 'SimpleVPBot_Bot_Admin_Guard' )
			? SimpleVPBot_Bot_Admin_Guard::resolve_admin_by_platform_id( $platform, $from_id > 0 ? $from_id : (int) $admin_chat )
			: null;
		if ( $admin_u && class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			SimpleVPBot_Bot_Reseller_Scope::set_acting_admin_user( (int) $admin_u->id );
		}
		$deny_alert = function () use ( $platform, $cb_id, $admin_u ) {
			if ( '' === (string) $cb_id ) {
				return;
			}
			$txt = class_exists( 'SimpleVPBot_Bot_Admin_Guard' )
				? SimpleVPBot_Bot_Admin_Guard::denied_message( $admin_u )
				: '⛔ دسترسی مجاز نیست.';
			SimpleVPBot_Bot_Runtime::answer_callback_query(
				$platform,
				array(
					'callback_query_id' => (string) $cb_id,
					'text'              => $txt,
					'show_alert'        => true,
				)
			);
		};
		$op = 'a' === $action ? 'user_approve' : 'user_reject';
		if ( $admin_u && class_exists( 'SimpleVPBot_Bot_Admin_Guard' )
			&& ! SimpleVPBot_Bot_Admin_Guard::may_call_op( $admin_u, $op ) ) {
			$deny_alert();
			return;
		}
		$user  = SimpleVPBot_Model_User::find( $uid );
		if ( ! $user ) {
			return;
		}
		if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			if ( ! SimpleVPBot_Bot_Reseller_Scope::bot_admin_may_moderate_user( (int) $uid ) ) {
				$deny_alert();
				return;
			}
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
		$extra = $with_menu ? array( 'reply_markup' => SimpleVPBot_Keyboards::user_main_reply( $user ) ) : array();
		if ( ! empty( $user->tg_user_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( 'telegram', (int) $user->tg_user_id, $text, $extra );
		}
		if ( ! empty( $user->bale_user_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( 'bale', (int) $user->bale_user_id, $text, $extra );
		}
	}

	/**
	 * Show inline reject-reason keyboard on all admin receipt messages.
	 *
	 * @param string               $platform Platform.
	 * @param int                  $rid Receipt id.
	 * @param array<string, mixed> $from From.
	 * @param int                  $admin_chat Chat.
	 * @param int                  $admin_msg_id Message id (0 = reply-keyboard path).
	 * @param string               $cb_id Callback query id.
	 */
	public static function show_receipt_reject_reasons( $platform, $rid, array $from, $admin_chat, $admin_msg_id = 0, $cb_id = '' ) {
		$rid = (int) $rid;
		$rec = SimpleVPBot_Model_Receipt::find( $rid );
		if ( ! $rec || ! in_array( (string) $rec->status, array( 'pending', 'processing' ), true ) ) {
			if ( '' !== (string) $cb_id ) {
				SimpleVPBot_Bot_Runtime::answer_callback_query(
					$platform,
					array(
						'callback_query_id' => (string) $cb_id,
						'text'              => '⛔ رسید در انتظار نیست.',
						'show_alert'        => true,
					)
				);
			}
			return;
		}
		$markup = SimpleVPBot_Keyboards::inline_receipt_reject_reasons( $rid );
		if ( (int) $admin_msg_id > 0 ) {
			SimpleVPBot_Receipt_Processor::finalize_clicked_admin_message(
				$platform,
				(int) $admin_chat,
				(int) $admin_msg_id,
				$markup
			);
		} else {
			SimpleVPBot_Receipt_Processor::edit_admin_messages( $rec, $markup );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				(int) $admin_chat,
				SimpleVPBot_Texts::get( 'msg.receipt.reject_pick_reason' ),
				array( 'reply_markup' => $markup )
			);
		}
		if ( '' !== (string) $cb_id ) {
			SimpleVPBot_Bot_Runtime::answer_callback_query(
				$platform,
				array(
					'callback_query_id' => (string) $cb_id,
					'text'              => SimpleVPBot_Texts::get( 'msg.receipt.reject_pick_reason' ),
				)
			);
		}
	}

	/**
	 * Receipt callbacks: rc:a / rc:r / rc:rr / rc:rb.
	 *
	 * @param string               $platform Platform.
	 * @param array<int, string>   $parts Callback parts.
	 * @param array<string, mixed> $from From.
	 * @param int                  $admin_chat Chat.
	 * @param int                  $admin_msg_id Msg id.
	 * @param string               $cb_id Callback query id.
	 */
	private static function handle_receipt_callback( $platform, array $parts, array $from, $admin_chat, $admin_msg_id, $cb_id = '' ) {
		$act = (string) ( $parts[1] ?? '' );
		$rid = (int) ( $parts[2] ?? 0 );
		if ( $rid < 1 ) {
			return;
		}

		$from_id = (int) ( $from['id'] ?? 0 );
		$admin_u = class_exists( 'SimpleVPBot_Bot_Admin_Guard' )
			? SimpleVPBot_Bot_Admin_Guard::resolve_admin_by_platform_id( $platform, $from_id > 0 ? $from_id : (int) $admin_chat )
			: null;
		if ( $admin_u && class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			SimpleVPBot_Bot_Reseller_Scope::set_acting_admin_user( (int) $admin_u->id );
		}
		if ( 'rb' !== $act && $admin_u && class_exists( 'SimpleVPBot_Bot_Admin_Guard' ) ) {
			$op = 'a' === $act ? 'receipt_approve' : 'receipt_reject';
			if ( ! SimpleVPBot_Bot_Admin_Guard::may_call_op( $admin_u, $op ) ) {
				if ( '' !== (string) $cb_id ) {
					SimpleVPBot_Bot_Runtime::answer_callback_query(
						$platform,
						array(
							'callback_query_id' => (string) $cb_id,
							'text'              => '⛔ دسترسی مجاز نیست.',
							'show_alert'        => true,
						)
					);
				}
				return;
			}
		}

		if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			$scope_ok = 'rb' === $act || SimpleVPBot_Bot_Reseller_Scope::bot_admin_may_access_receipt( $rid );
			if ( ! $scope_ok ) {
				if ( '' !== (string) $cb_id ) {
					SimpleVPBot_Bot_Runtime::answer_callback_query(
						$platform,
						array(
							'callback_query_id' => (string) $cb_id,
							'text'              => '⛔ دسترسی مجاز نیست.',
							'show_alert'        => true,
						)
					);
				}
				return;
			}
		}

		if ( 'rb' === $act ) {
			$rec = SimpleVPBot_Model_Receipt::find( $rid );
			if ( $rec && in_array( (string) $rec->status, array( 'pending', 'processing' ), true ) ) {
				SimpleVPBot_Receipt_Processor::edit_admin_messages( $rec, SimpleVPBot_Keyboards::inline_receipt( $rid ) );
			}
			if ( '' !== (string) $cb_id ) {
				SimpleVPBot_Bot_Runtime::answer_callback_query(
					$platform,
					array( 'callback_query_id' => (string) $cb_id )
				);
			}
			return;
		}

		if ( 'r' === $act ) {
			self::show_receipt_reject_reasons( $platform, $rid, $from, $admin_chat, $admin_msg_id, $cb_id );
			return;
		}

		$uname = (string) ( $from['username'] ?? '' );
		$label = $uname ? '@' . $uname : (string) ( $from['first_name'] ?? '' );
		$res   = null;

		if ( 'a' === $act ) {
			$rec_claim = SimpleVPBot_Model_Receipt::find( $rid );
			if ( ! $rec_claim ) {
				if ( '' !== (string) $cb_id ) {
					SimpleVPBot_Bot_Runtime::answer_callback_query(
						$platform,
						array(
							'callback_query_id' => (string) $cb_id,
							'text'              => 'رسید یافت نشد.',
							'show_alert'        => true,
						)
					);
				}
				return;
			}
			if ( 'approved' === (string) $rec_claim->status ) {
				$res = array( 'ok' => true, 'reason' => 'already_approved' );
			} elseif ( 'processing' === (string) $rec_claim->status ) {
				if ( '' !== (string) $cb_id ) {
					SimpleVPBot_Bot_Runtime::answer_callback_query(
						$platform,
						array(
							'callback_query_id' => (string) $cb_id,
							'text'              => '⏳ در حال پردازش…',
						)
					);
				}
				return;
			} elseif ( 'pending' !== (string) $rec_claim->status ) {
				if ( '' !== (string) $cb_id ) {
					SimpleVPBot_Bot_Runtime::answer_callback_query(
						$platform,
						array(
							'callback_query_id' => (string) $cb_id,
							'text'              => 'این رسید قابل تایید نیست.',
							'show_alert'        => true,
						)
					);
				}
				return;
			} elseif ( ! SimpleVPBot_Model_Receipt::claim_pending( $rid ) ) {
				$rec_race = SimpleVPBot_Model_Receipt::find( $rid );
				if ( $rec_race && 'approved' === (string) $rec_race->status ) {
					$res = array( 'ok' => true, 'reason' => 'already_approved' );
				} else {
					if ( '' !== (string) $cb_id ) {
						SimpleVPBot_Bot_Runtime::answer_callback_query(
							$platform,
							array(
								'callback_query_id' => (string) $cb_id,
								'text'              => '⏳ در حال پردازش…',
							)
						);
					}
					return;
				}
			} else {
				$clicked = null;
				if ( (int) $admin_msg_id > 0 ) {
					$clicked = array(
						'platform'   => (string) $platform,
						'chat_id'    => (int) $admin_chat,
						'message_id' => (int) $admin_msg_id,
					);
				}
				$res = SimpleVPBot_Receipt_Processor::approve_continue( $rid, $label, $clicked );
			}
		} elseif ( 'rr' === $act ) {
			$reason = SimpleVPBot_Receipt_Processor::reject_reason_by_index( (int) ( $parts[3] ?? -1 ) );
			$res      = SimpleVPBot_Receipt_Processor::reject( $rid, $label, $reason );
		} else {
			return;
		}

		if ( ! is_array( $res ) ) {
			return;
		}

		$toast      = SimpleVPBot_Receipt_Processor::admin_feedback_text( $res, 'a' === $act );
		$show_alert = ! empty( $res['purchase_failed'] )
			|| ( empty( $res['ok'] ) && 'already_approved' !== (string) ( $res['reason'] ?? '' ) && 'rejected' !== (string) ( $res['reason'] ?? '' ) );
		if ( '' !== (string) $cb_id ) {
			SimpleVPBot_Bot_Runtime::answer_callback_query(
				$platform,
				array(
					'callback_query_id' => (string) $cb_id,
					'text'              => $toast,
					'show_alert'        => $show_alert,
				)
			);
		}

		if ( (int) $admin_msg_id > 0 && ! empty( $res['ok'] ) && 'a' === $act ) {
			$btn = SimpleVPBot_Keyboards::glass_button_text( '✅ رسید تایید شد · ' . $label );
			SimpleVPBot_Receipt_Processor::finalize_clicked_admin_message(
				$platform,
				(int) $admin_chat,
				(int) $admin_msg_id,
				array(
					'inline_keyboard' => array(
						array( array( 'text' => $btn, 'callback_data' => 'noop' ) ),
					),
				)
			);
		}
	}

	/**
	 * Verify mandatory channel membership (chjoin:verify).
	 *
	 * @param string      $platform Platform.
	 * @param string      $data     Callback data.
	 * @param object|null $user     User row.
	 * @param int         $from_id  From id.
	 * @param int         $chat_id  Chat id.
	 * @param string      $cb_id    Callback query id.
	 */
	private static function handle_channel_join( $platform, $data, $user, $from_id, $chat_id, $cb_id ) {
		if ( 'chjoin:verify' !== $data ) {
			if ( '' !== $cb_id ) {
				SimpleVPBot_Bot_Runtime::answer_callback_query(
					$platform,
					array( 'callback_query_id' => $cb_id )
				);
			}
			return;
		}
		if ( ! class_exists( 'SimpleVPBot_Required_Channel' ) ) {
			return;
		}
		$ok = SimpleVPBot_Required_Channel::user_passes( $platform, $from_id, true );
		if ( ! $ok ) {
			usleep( 300000 );
			$ok = SimpleVPBot_Required_Channel::user_passes( $platform, $from_id, true );
		}
		if ( '' !== $cb_id ) {
			SimpleVPBot_Bot_Runtime::answer_callback_query(
				$platform,
				array(
					'callback_query_id' => $cb_id,
					'text'              => $ok
						? ( $user ? SimpleVPBot_Texts::get_for_user( 'msg.force_join.success', $user ) : SimpleVPBot_Texts::get( 'msg.force_join.success', '' ) )
						: ( $user ? SimpleVPBot_Texts::get_for_user( 'msg.force_join.fail', $user ) : SimpleVPBot_Texts::get( 'msg.force_join.fail', '' ) ),
					'show_alert'        => ! $ok,
				)
			);
		}
		if ( ! $ok ) {
			SimpleVPBot_Required_Channel::send_prompt( $platform, $chat_id, $user );
			return;
		}
		if ( $user ) {
			SimpleVPBot_Required_Channel::on_verify_success( $platform, $chat_id, $user );
		} else {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Texts::get( 'msg.force_join.success', '' ) . "\n" . SimpleVPBot_Texts::get( 'msg.start_first', '' )
			);
		}
	}
}
