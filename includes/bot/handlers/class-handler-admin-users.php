<?php
/**
 * Bot admin users facade.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Admin_Users
 */
class SimpleVPBot_Handler_Admin_Users {

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param int    $uid      User id.
	 */
	public static function send_user_admin_card( $platform, $chat_id, $uid ) {
		if ( ! self::guard_user( $platform, $chat_id, $uid ) ) {
			return;
		}
		$u = SimpleVPBot_Model_User::find( (int) $uid );
		if ( ! $u ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::msg( $platform, $chat_id, 'msg.admin.user_not_found' ) );
			return;
		}
		$admin_u = self::resolve_admin_user( $platform, $chat_id );
		$uidn    = (int) $u->id;
		$rows    = array();
		$portal  = SimpleVPBot_Portal_Link::build_url( $uidn );
		if ( '' !== $portal ) {
			$rows[] = array(
				array(
					'text' => SimpleVPBot_Keyboards::glass_button_text(
						SimpleVPBot_Texts::format(
							SimpleVPBot_Texts::get_for_user( 'btn.admin.user_portal_link', $admin_u, '🌐 لینک پورتال کاربر #{id}' ),
							array( 'id' => (string) $uidn )
						),
						256
					),
				),
			);
		}
		$rows[] = array(
			array(
				'text' => SimpleVPBot_Keyboards::glass_button_text(
					SimpleVPBot_Texts::format(
						SimpleVPBot_Texts::get_for_user( 'btn.admin.user_block', $admin_u, '⛔ بلاک #{id}' ),
						array( 'id' => (string) $uidn )
					),
					256
				),
			),
			array(
				'text' => SimpleVPBot_Keyboards::glass_button_text(
					SimpleVPBot_Texts::format(
						SimpleVPBot_Texts::get_for_user( 'btn.admin.user_unblock', $admin_u, '✅ آنبلاک #{id}' ),
						array( 'id' => (string) $uidn )
					),
					256
				),
			),
		);
		$rows[] = array(
			array(
				'text' => SimpleVPBot_Keyboards::glass_button_text(
					SimpleVPBot_Texts::format(
						SimpleVPBot_Texts::get_for_user( 'btn.admin.user_create_service', $admin_u, '➕ ساخت سرویس برای #{id}' ),
						array( 'id' => (string) $uidn )
					),
					256
				),
			),
		);
		$svcs = SimpleVPBot_Model_Service::by_user( $uidn );
		$n_sv = count( $svcs );
		$ul   = SimpleVPBot_Model_User::label( $u );
		$txt  = '👤 ' . $ul . "\n";
		$txt .= SimpleVPBot_Texts::format(
			SimpleVPBot_Texts::get_for_user( 'msg.admin.user_card_status', $admin_u, 'وضعیت: {status}' ),
			array( 'status' => (string) $u->status )
		) . "\n";
		$txt .= SimpleVPBot_Texts::format(
			SimpleVPBot_Texts::get_for_user( 'msg.admin.user_card_balance', $admin_u, 'موجودی: {balance}' ),
			array( 'balance' => number_format( (float) $u->balance ) )
		) . "\n";
		$txt .= SimpleVPBot_Texts::format(
			SimpleVPBot_Texts::get_for_user( 'msg.admin.user_card_services', $admin_u, 'سرویس‌ها: {count}' ),
			array( 'count' => (string) $n_sv )
		);
		if ( $n_sv > 0 ) {
			$txt .= "\n➖➖➖➖➖➖➖➖\n";
			$txt .= SimpleVPBot_Texts::get_for_user( 'msg.admin.user_card_manage_hint', $admin_u, '🧰 برای مدیریت کامل (مثل کاربر)، یک سرویس را از دکمه‌های اینلاین زیر انتخاب کنید:' );
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$txt,
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $rows ) )
		);
		$admin_u = self::resolve_admin_user( $platform, $chat_id );
		$dm_lbl  = SimpleVPBot_Texts::get_for_user( 'btn.admin.dm_user', $admin_u, '✉️ پیام به کاربر' );
		$dm_cb   = 'pnl:umsg:' . $uidn;
		$cbp     = 'pnl:wbp:' . $uidn;
		$cbm     = 'pnl:wbm:' . $uidn;
		$inline  = array();
		if ( strlen( $dm_cb ) <= 64 ) {
			$inline[] = array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( $dm_lbl, 64 ), 'callback_data' => $dm_cb ) );
		}
		if ( strlen( $cbp ) <= 64 && strlen( $cbm ) <= 64 ) {
			$credit_lbl = SimpleVPBot_Texts::get_for_user( 'msg.admin.wallet_credit', $admin_u, '💰 شارژ کیف پول' );
			$debit_lbl  = SimpleVPBot_Texts::get_for_user( 'msg.admin.wallet_debit', $admin_u, '📉 کاهش کیف پول' );
			$inline[] = array(
				array( 'text' => SimpleVPBot_Keyboards::glass_button_text( $credit_lbl, 64 ), 'callback_data' => $cbp ),
				array( 'text' => SimpleVPBot_Keyboards::glass_button_text( $debit_lbl, 64 ), 'callback_data' => $cbm ),
			);
		}
		if ( ! empty( $inline ) ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				self::msg( $platform, $chat_id, 'msg.admin.user_actions', array( 'id' => $uidn ) ),
				array(
					'reply_markup' => array(
						'inline_keyboard' => $inline,
					),
				)
			);
		}
		if ( $n_sv > 0 ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				self::msg( $platform, $chat_id, 'msg.admin.user_services', array( 'id' => $uidn ) ),
				array( 'reply_markup' => SimpleVPBot_Keyboards::inline_service_list( $svcs, $admin_u ) )
			);
		}
	}

	/**
	 * @param string               $platform Platform.
	 * @param int                  $chat_id  Chat id.
	 * @param int                  $from_id  From id.
	 * @param object               $user     User.
	 * @param string               $text     Text.
	 * @param array<string, mixed> $from    From array.
	 * @return bool
	 */
	public static function route_moderation_reply_text( $platform, $chat_id, $from_id, $user, $text, array $from ) {
		if ( ! SimpleVPBot_Router::is_platform_admin( $platform, $from_id ) ) {
			return false;
		}
		if ( $user && ! empty( $user->id ) && class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			SimpleVPBot_Bot_Reseller_Scope::set_acting_admin_user( (int) $user->id );
		}
		$tn = SimpleVPBot_Bot_Runtime::normalize_digits( SimpleVPBot_Keyboards::strip_glass_prefix( trim( (string) $text ) ) );
		$uid = self::match_id_button( $tn, $user, 'btn.admin.reg_approve', '✅ ثبت‌نام #{id}' );
		if ( null === $uid && preg_match( '/^✅ ثبت‌نام #(\d+)$/u', $tn, $m ) ) {
			$uid = (int) $m[1];
		}
		if ( null !== $uid && $uid > 0 ) {
			if ( ! self::guard_op( $platform, $chat_id, 'user_approve' ) ) {
				return true;
			}
			if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) && ! SimpleVPBot_Bot_Reseller_Scope::bot_admin_may_moderate_user( $uid ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::msg( $platform, $chat_id, 'msg.admin.user_not_found' ) );
				return true;
			}
			SimpleVPBot_Handler_Callback::admin_apply_registration( $platform, 'a', $uid, $from, $chat_id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::msg( $platform, $chat_id, 'msg.admin.signup_processed' ) );
			return true;
		}
		$uid = self::match_id_button( $tn, $user, 'btn.admin.reg_reject', '❌ رد ثبت‌نام #{id}' );
		if ( null === $uid && preg_match( '/^❌ رد ثبت‌نام #(\d+)$/u', $tn, $m ) ) {
			$uid = (int) $m[1];
		}
		if ( null !== $uid && $uid > 0 ) {
			if ( ! self::guard_op( $platform, $chat_id, 'user_reject' ) ) {
				return true;
			}
			if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) && ! SimpleVPBot_Bot_Reseller_Scope::bot_admin_may_moderate_user( $uid ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::msg( $platform, $chat_id, 'msg.admin.user_not_found' ) );
				return true;
			}
			SimpleVPBot_Handler_Callback::admin_apply_registration( $platform, 'r', $uid, $from, $chat_id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::msg( $platform, $chat_id, 'msg.admin.signup_rejected_recorded' ) );
			return true;
		}
		$rid = self::match_id_button( $tn, $user, 'btn.admin.receipt_approve', '✅ رسید {id}' );
		if ( null === $rid && preg_match( '/^✅ رسید (\d+)$/u', $tn, $m ) ) {
			$rid = (int) $m[1];
		}
		if ( null !== $rid && $rid > 0 ) {
			if ( ! self::guard_op( $platform, $chat_id, 'receipt_approve' ) ) {
				return true;
			}
			if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) && ! SimpleVPBot_Bot_Reseller_Scope::bot_admin_may_access_receipt( $rid ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::msg( $platform, $chat_id, 'msg.admin.user_not_found' ) );
				return true;
			}
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⏳ در حال پردازش…' );
			SimpleVPBot_Receipt_Processor::approve_async_start( $rid, self::moderation_admin_label( $from ) );
			return true;
		}
		$rid = self::match_id_button( $tn, $user, 'btn.admin.receipt_reject', '❌ رد رسید {id}' );
		if ( null === $rid && preg_match( '/^❌ رد رسید (\d+)$/u', $tn, $m ) ) {
			$rid = (int) $m[1];
		}
		if ( null !== $rid && $rid > 0 ) {
			if ( ! self::guard_op( $platform, $chat_id, 'receipt_reject' ) ) {
				return true;
			}
			if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) && ! SimpleVPBot_Bot_Reseller_Scope::bot_admin_may_access_receipt( $rid ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::msg( $platform, $chat_id, 'msg.admin.user_not_found' ) );
				return true;
			}
			SimpleVPBot_Handler_Callback::show_receipt_reject_reasons(
				$platform,
				$rid,
				$from,
				$chat_id,
				0,
				''
			);
			return true;
		}
		return false;
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
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param int    $uid      Target user id.
	 * @return bool
	 */
	private static function guard_user( $platform, $chat_id, $uid ) {
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return true;
		}
		$admin_u = self::resolve_admin_user( $platform, $chat_id );
		if ( $admin_u ) {
			SimpleVPBot_Bot_Reseller_Scope::set_acting_admin_user( (int) $admin_u->id );
		}
		if ( SimpleVPBot_Bot_Reseller_Scope::bot_admin_may_moderate_user( (int) $uid ) ) {
			return true;
		}
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::msg( $platform, $chat_id, 'msg.admin.user_not_found' ) );
		return false;
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param string $op       Operation key.
	 * @return bool
	 */
	private static function guard_op( $platform, $chat_id, $op ) {
		$admin_u = self::resolve_admin_user( $platform, $chat_id );
		if ( $admin_u && class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			SimpleVPBot_Bot_Reseller_Scope::set_acting_admin_user( (int) $admin_u->id );
		}
		if ( ! $admin_u || ! class_exists( 'SimpleVPBot_Bot_Admin_Guard' ) ) {
			return true;
		}
		if ( SimpleVPBot_Bot_Admin_Guard::may_call_op( $admin_u, $op ) ) {
			return true;
		}
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Guard::denied_message( $admin_u ) );
		return false;
	}

	/**
	 * @param string      $text    Text.
	 * @param object|null $user    User.
	 * @param string      $key     i18n key.
	 * @param string      $default Default template.
	 * @return int|null
	 */
	private static function match_id_button( $text, $user, $key, $default ) {
		$labels = array( $default );
		if ( $user && is_object( $user ) ) {
			$labels[] = SimpleVPBot_Texts::get_for_user( $key, $user, $default );
		}
		foreach ( array_unique( $labels ) as $tmpl ) {
			foreach ( array( '{id}', '#{id}' ) as $ph ) {
				if ( false === strpos( $tmpl, $ph ) ) {
					continue;
				}
				$parts = explode( $ph, $tmpl, 2 );
				if ( 2 !== count( $parts ) ) {
					continue;
				}
				$re = '/^' . preg_quote( $parts[0], '/' ) . '(\d+)' . preg_quote( $parts[1], '/' ) . '$/u';
				if ( preg_match( $re, $text, $m ) ) {
					return (int) $m[1];
				}
			}
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $from From payload.
	 * @return string
	 */
	private static function moderation_admin_label( array $from ) {
		$uname = (string) ( $from['username'] ?? '' );
		return '' !== $uname ? '@' . $uname : (string) ( $from['first_name'] ?? 'admin' );
	}

	/**
	 * Approved users (paged, inline).
	 *
	 * @param string $platform    Platform.
	 * @param int    $chat_id     Chat.
	 * @param int    $offset      Offset.
	 * @param int    $edit_msg_id Edit existing message id (optional).
	 */
	public static function send_approved_page( $platform, $chat_id, $offset = 0, $edit_msg_id = 0 ) {
		$admin_u = self::resolve_admin_user( $platform, $chat_id );
		$off     = max( 0, (int) $offset );
		$lim     = 5;
		$bundle  = self::users_by_status( 'approved', $off, $lim );
		$list    = $bundle['list'];
		$tot     = (int) $bundle['tot'];
		$t       = SimpleVPBot_Texts::format(
			SimpleVPBot_Texts::get_for_user( 'msg.admin.users_approved_header', $admin_u, "✅ کاربران تأییدشده ({total})\nصفحه offset {offset}\n➖" ),
			array(
				'total'  => (string) $tot,
				'offset' => (string) $off,
			)
		);
		if ( empty( $list ) ) {
			$t .= '—';
		} else {
			foreach ( $list as $row ) {
				$t .= '#' . (int) $row->id . ' · ' . SimpleVPBot_Model_User::label( $row ) . ' · ' . number_format( (float) $row->balance ) . "\n";
			}
		}
		$ik = array();
		foreach ( $list as $row ) {
			$uid = (int) $row->id;
			$lbl = mb_substr( SimpleVPBot_Model_User::label( $row ), 0, 28 );
			if ( '' === trim( $lbl ) ) {
				$lbl = '#' . $uid;
			}
			$ik[] = array(
				array(
					'text'          => SimpleVPBot_Keyboards::glass_button_text( '👤 ' . $lbl, 40 ),
					'callback_data' => 'pnl:ui:' . $uid,
				),
			);
		}
		$nav = array();
		if ( $off + $lim < $tot ) {
			$nav[] = array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( '▶ بعدی', 16 ),
				'callback_data' => 'pnl:aq:' . ( $off + $lim ),
			);
		}
		if ( $off > 0 ) {
			$nav[] = array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( '◀ قبلی', 16 ),
				'callback_data' => 'pnl:aq:' . max( 0, $off - $lim ),
			);
		}
		if ( $nav ) {
			$ik[] = $nav;
		}
		$ik[] = array(
			array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( '📋 صف انتظار', 20 ),
				'callback_data' => 'pnl:pq:0',
			),
			array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( '❌ ردشده', 16 ),
				'callback_data' => 'pnl:rq:0',
			),
		);
		self::push_queue_message( $platform, $chat_id, $t, array( 'inline_keyboard' => $ik ), (int) $edit_msg_id );
	}

	/**
	 * Pending users (inline glass, 5 per page, newest first).
	 *
	 * @param string $platform    Platform.
	 * @param int    $chat_id     Chat.
	 * @param int    $offset      Offset.
	 * @param int    $edit_msg_id Edit this message (0 = new).
	 */
	public static function send_pending_page( $platform, $chat_id, $offset = 0, $edit_msg_id = 0 ) {
		$admin_u = self::resolve_admin_user( $platform, $chat_id );
		$off     = max( 0, (int) $offset );
		$lim     = 5;
		$bundle  = self::users_by_status( 'pending', $off, $lim );
		$total   = (int) $bundle['tot'];
		$list    = $bundle['list'];
		if ( empty( $list ) && 0 === $off ) {
			$ik = array(
				array(
					array(
						'text'          => SimpleVPBot_Keyboards::glass_button_text( '✅ تأییدشده', 16 ),
						'callback_data' => 'pnl:aq:0',
					),
					array(
						'text'          => SimpleVPBot_Keyboards::glass_button_text( '❌ ردشده', 16 ),
						'callback_data' => 'pnl:rq:0',
					),
				),
			);
			self::push_queue_message(
				$platform,
				$chat_id,
				SimpleVPBot_Texts::get_for_user( 'msg.admin.users_queue_empty', $admin_u, '👥 کاربری در انتظار تایید نیست.' ),
				array( 'inline_keyboard' => $ik ),
				(int) $edit_msg_id
			);
			return;
		}
		if ( empty( $list ) ) {
			self::send_pending_page( $platform, $chat_id, 0, (int) $edit_msg_id );
			return;
		}
		$t = SimpleVPBot_Texts::format(
			SimpleVPBot_Texts::get_for_user(
				'msg.admin.users_pending_header',
				$admin_u,
				"👥 در انتظار تایید: {total}\n🔎 «{search}»\nصفحه offset {offset}\n➖"
			),
			array(
				'total'  => (string) $total,
				'search' => SimpleVPBot_Texts::get_for_user( 'btn.admin.users_search', $admin_u, '🔎 جستجوی کاربر' ),
				'offset' => (string) $off,
			)
		);
		foreach ( $list as $pu ) {
			$t .= '#' . (int) $pu->id . ' · ' . SimpleVPBot_Model_User::label( $pu ) . "\n";
		}
		$ik = array();
		foreach ( $list as $pu ) {
			$uid = (int) $pu->id;
			$lbl = mb_substr( SimpleVPBot_Model_User::label( $pu ), 0, 22 );
			if ( '' === trim( $lbl ) ) {
				$lbl = '#' . $uid;
			}
			$ik[] = array(
				array(
					'text'          => SimpleVPBot_Keyboards::glass_button_text( '👤 ' . $lbl, 36 ),
					'callback_data' => 'pnl:ui:' . $uid,
				),
				array(
					'text'          => SimpleVPBot_Keyboards::glass_button_text( '✅', 8 ),
					'callback_data' => 'reg:a:' . $uid,
				),
				array(
					'text'          => SimpleVPBot_Keyboards::glass_button_text( '❌', 8 ),
					'callback_data' => 'reg:r:' . $uid,
				),
			);
		}
		$nav = array();
		if ( $off + $lim < $total ) {
			$nav[] = array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( '▶ بعدی', 16 ),
				'callback_data' => 'pnl:pq:' . ( $off + $lim ),
			);
		}
		if ( $off > 0 ) {
			$nav[] = array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( '◀ قبلی', 16 ),
				'callback_data' => 'pnl:pq:' . max( 0, $off - $lim ),
			);
		}
		if ( $nav ) {
			$ik[] = $nav;
		}
		$ik[] = array(
			array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( '✅ تأییدشده', 16 ),
				'callback_data' => 'pnl:aq:0',
			),
			array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( '❌ ردشده', 16 ),
				'callback_data' => 'pnl:rq:0',
			),
		);
		self::push_queue_message( $platform, $chat_id, $t, array( 'inline_keyboard' => $ik ), (int) $edit_msg_id );
	}

	/**
	 * Rejected users with reopen-to-queue (inline).
	 *
	 * @param string $platform    Platform.
	 * @param int    $chat_id     Chat.
	 * @param int    $offset      Offset.
	 * @param int    $edit_msg_id Edit message id.
	 */
	public static function send_rejected_page( $platform, $chat_id, $offset = 0, $edit_msg_id = 0 ) {
		$admin_u = self::resolve_admin_user( $platform, $chat_id );
		$off     = max( 0, (int) $offset );
		$lim     = 5;
		$bundle  = self::users_by_status( 'rejected', $off, $lim );
		$list    = $bundle['list'];
		$tot     = (int) $bundle['tot'];
		$t       = SimpleVPBot_Texts::format(
			SimpleVPBot_Texts::get_for_user( 'msg.admin.users_rejected_header', $admin_u, "❌ کاربران رد شده ({total})\noffset {offset}\n➖" ),
			array(
				'total'  => (string) $tot,
				'offset' => (string) $off,
			)
		);
		if ( empty( $list ) ) {
			$t .= '—';
		} else {
			foreach ( $list as $row ) {
				$t .= '#' . (int) $row->id . ' · ' . SimpleVPBot_Model_User::label( $row ) . "\n";
			}
		}
		$ik = array();
		foreach ( $list as $row ) {
			$uid = (int) $row->id;
			$lbl = mb_substr( SimpleVPBot_Model_User::label( $row ), 0, 22 );
			if ( '' === trim( $lbl ) ) {
				$lbl = '#' . $uid;
			}
			$ik[] = array(
				array(
					'text'          => SimpleVPBot_Keyboards::glass_button_text( '👤 ' . $lbl, 36 ),
					'callback_data' => 'pnl:ui:' . $uid,
				),
				array(
					'text'          => SimpleVPBot_Keyboards::glass_button_text( '↩ صف', 12 ),
					'callback_data' => 'pnl:rr:' . $uid,
				),
			);
		}
		$nav = array();
		if ( $off + $lim < $tot ) {
			$nav[] = array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( '▶ بعدی', 16 ),
				'callback_data' => 'pnl:rq:' . ( $off + $lim ),
			);
		}
		if ( $off > 0 ) {
			$nav[] = array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( '◀ قبلی', 16 ),
				'callback_data' => 'pnl:rq:' . max( 0, $off - $lim ),
			);
		}
		if ( $nav ) {
			$ik[] = $nav;
		}
		$ik[] = array(
			array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( '📋 صف انتظار', 20 ),
				'callback_data' => 'pnl:pq:0',
			),
			array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( '✅ تأییدشده', 16 ),
				'callback_data' => 'pnl:aq:0',
			),
		);
		self::push_queue_message( $platform, $chat_id, $t, array( 'inline_keyboard' => $ik ), (int) $edit_msg_id );
	}

	/**
	 * @param string $status Status.
	 * @param int    $offset Offset.
	 * @param int    $limit  Limit.
	 * @return array{list:array<int,object>,tot:int}
	 */
	private static function users_by_status( $status, $offset, $limit ) {
		$scope = null;
		if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			$scope = SimpleVPBot_Bot_Reseller_Scope::bot_admin_scope_user_ids();
		}
		if ( is_array( $scope ) ) {
			return array(
				'list' => SimpleVPBot_Model_User::list_by_status_paged_for_ids( $status, $offset, $limit, $scope ),
				'tot'  => SimpleVPBot_Model_User::count_by_status_for_ids( $status, $scope ),
			);
		}
		return array(
			'list' => SimpleVPBot_Model_User::list_by_status_paged( $status, $offset, $limit ),
			'tot'  => SimpleVPBot_Model_User::count_status( $status ),
		);
	}

	/**
	 * Edit or send queue message body + inline keyboard.
	 *
	 * @param string               $platform    Platform.
	 * @param int                  $chat_id     Chat.
	 * @param string               $text        Body.
	 * @param array<string, mixed> $markup      Inline keyboard.
	 * @param int                  $edit_msg_id Message to edit (0 = send new).
	 */
	private static function push_queue_message( $platform, $chat_id, $text, array $markup, $edit_msg_id = 0 ) {
		$mid = (int) $edit_msg_id;
		if ( $mid > 0 ) {
			$res = SimpleVPBot_Bot_Runtime::edit_message_text(
				$platform,
				$chat_id,
				$mid,
				$text,
				array( 'reply_markup' => $markup )
			);
			if ( ! is_array( $res ) || empty( $res['ok'] ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $text, array( 'reply_markup' => $markup ) );
			}
		} else {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $text, array( 'reply_markup' => $markup ) );
		}
	}
}
