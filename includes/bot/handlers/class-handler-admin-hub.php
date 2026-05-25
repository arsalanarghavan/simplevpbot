<?php
/**
 * Admin hub: Reply keyboards + legacy adm:* callbacks (deprecated).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Admin_Hub
 */
class SimpleVPBot_Handler_Admin_Hub {

	/**
	 * Send root hub message (reply or callback follow-up).
	 *
	 * @param string $platform telegram|bale.
	 * @param int    $chat_id Chat id.
	 */
	public static function send_hub( $platform, $chat_id ) {
		$admin_user = self::resolve_admin_user_for_chat( $platform, $chat_id );
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			SimpleVPBot_Texts::get_for_user( 'msg.admin.hub_menu', $admin_user ),
			array( 'reply_markup' => self::reply_markup_main_for_chat( $platform, $chat_id ) )
		);
	}

	/**
	 * Resolve svp_users row for admin chat (locale for hub messages).
	 *
	 * @param string $platform telegram|bale.
	 * @param int    $chat_id  Chat id.
	 * @return object|null
	 */
	private static function resolve_admin_user_for_chat( $platform, $chat_id ) {
		if ( ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return null;
		}
		return 'bale' === $platform
			? SimpleVPBot_Model_User::find_by_bale( (int) $chat_id )
			: SimpleVPBot_Model_User::find_by_telegram( (int) $chat_id );
	}

	/**
	 * Localized admin message for current chat locale.
	 *
	 * @param string               $key      Text key.
	 * @param string               $platform telegram|bale.
	 * @param int                  $chat_id  Admin chat id.
	 * @param array<string,string> $vars     Placeholders.
	 * @param string               $default  Fallback.
	 * @return string
	 */
	private static function admin_msg( $key, $platform, $chat_id, array $vars = array(), $default = '' ) {
		$u = self::resolve_admin_user_for_chat( $platform, $chat_id );
		$t = SimpleVPBot_Texts::get_for_user( $key, $u, $default );
		return empty( $vars ) ? $t : SimpleVPBot_Texts::format( $t, $vars );
	}

	/**
	 * Main admin Reply keyboard (hub + shortcuts + portal triggers).
	 *
	 * @param string $platform telegram|bale.
	 * @param int    $chat_id  Admin chat id.
	 * @return array<string, mixed>
	 */
	public static function reply_markup_main_for_chat( $platform, $chat_id ) {
		return SimpleVPBot_Keyboards::admin_main_reply_for_chat( $platform, $chat_id );
	}

	/**
	 * @deprecated Legacy inline hub; callbacks still answered with main Reply keyboard.
	 * @return array<string, mixed>
	 */
	public static function inline_hub_root_for_admin_chat( $platform, $chat_id ) {
		return self::reply_markup_main_for_chat( $platform, $chat_id );
	}

	/**
	 * @deprecated
	 * @return array<string, mixed>
	 */
	public static function inline_hub_root() {
		return array( 'inline_keyboard' => array() );
	}

	/**
	 * Show one user card: portal, block/unblock, then same service UI as the user (list or full menu).
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat id.
	 * @param int    $uid User id.
	 */
	public static function send_user_admin_card( $platform, $chat_id, $uid ) {
		$u = SimpleVPBot_Model_User::find( (int) $uid );
		if ( ! $u ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.user_not_found', $platform, $chat_id ) );
			return;
		}
		$uidn = (int) $u->id;
		$rows = array();
		$portal = SimpleVPBot_Portal_Link::build_url( $uidn );
		if ( '' !== $portal ) {
			$rows[] = array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '🌐 لینک پورتال کاربر #' . $uidn, 256 ) ) );
		}
		$rows[] = array(
			array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '⛔ بلاک #' . $uidn, 256 ) ),
			array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '✅ آنبلاک #' . $uidn, 256 ) ),
		);
		$rows[] = array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '➕ ساخت سرویس برای #' . $uidn, 256 ) ) );
		$svcs = SimpleVPBot_Model_Service::by_user( $uidn );
		$n_sv = count( $svcs );
		$ul   = SimpleVPBot_Model_User::label( $u );
		$txt  = '👤 ' . $ul . "\nوضعیت: " . $u->status . "\nموجودی: " . number_format( (float) $u->balance ) . "\nسرویس‌ها: " . $n_sv;
		if ( $n_sv > 0 ) {
			$txt .= "\n➖➖➖➖➖➖➖➖\n🧰 برای مدیریت کامل (مثل کاربر)، یک سرویس را از دکمه‌های اینلاین زیر انتخاب کنید:";
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$txt,
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $rows ) )
		);
		$dm_lbl = SimpleVPBot_Texts::get( 'btn.admin.dm_user', '✉️ پیام به کاربر' );
		$dm_cb  = 'adm:umsg:' . $uidn;
		$cbp    = 'adm:wbp:' . $uidn;
		$cbm    = 'adm:wbm:' . $uidn;
		$inline = array();
		if ( strlen( $dm_cb ) <= 64 ) {
			$inline[] = array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( $dm_lbl, 64 ), 'callback_data' => $dm_cb ) );
		}
		if ( strlen( $cbp ) <= 64 && strlen( $cbm ) <= 64 ) {
			$inline[] = array(
				array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '💰 شارژ کیف پول', 64 ), 'callback_data' => $cbp ),
				array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '📉 کاهش کیف پول', 64 ), 'callback_data' => $cbm ),
			);
		}
		if ( ! empty( $inline ) ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				'💬 اقدام برای کاربر #' . $uidn,
				array(
					'reply_markup' => array(
						'inline_keyboard' => $inline,
					),
				)
			);
		}
		if ( $n_sv > 0 ) {
			$admin_u = self::resolve_admin_user_for_chat( $platform, $chat_id );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				'📡 سرویس‌های کاربر #' . $uidn,
				array( 'reply_markup' => SimpleVPBot_Keyboards::inline_service_list( $svcs, $admin_u ) )
			);
		}
	}

	/**
	 * Transient key to prevent duplicate bulk receipt review runs per admin chat.
	 *
	 * @param string $platform telegram|bale.
	 * @param int    $chat_id  Admin chat.
	 * @return string
	 */
	private static function receipt_review_lock_key( $platform, $chat_id ) {
		return 'svp_rcp_review_' . sanitize_key( (string) $platform ) . '_' . max( 0, (int) $chat_id );
	}

	/**
	 * Send pending receipts to admin with same caption/inline as live uploads; paginated.
	 *
	 * @param string $platform telegram|bale.
	 * @param int    $chat_id  Admin chat.
	 * @param int    $offset   Offset into pending list (oldest first).
	 */
	public static function send_pending_receipts_review_paged( $platform, $chat_id, $offset = 0 ) {
		$lock_key = self::receipt_review_lock_key( $platform, $chat_id );
		if ( get_transient( $lock_key ) ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				'⏳ ارسال رسیدهای معلق در حال انجام است. چند ثانیه صبر کنید.'
			);
			return;
		}
		set_transient( $lock_key, '1', 55 );

		$per = (int) apply_filters( 'simplevpbot_receipt_review_per_page', 3 );
		$per = max( 1, min( 10, $per ) );
		$off = max( 0, (int) $offset );

		$total = SimpleVPBot_Model_Receipt::pending_count();
		if ( $total < 1 ) {
			delete_transient( $lock_key );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				'🧾 رسید معلقی نیست.',
				array( 'reply_markup' => SimpleVPBot_Keyboards::admin_finance_submenu_reply() )
			);
			return;
		}
		$list = SimpleVPBot_Model_Receipt::pending_paged( $off, $per );
		if ( empty( $list ) ) {
			delete_transient( $lock_key );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				'🧾 رسید دیگری در این صفحه نیست.',
				array( 'reply_markup' => SimpleVPBot_Keyboards::admin_finance_submenu_reply() )
			);
			return;
		}

		$n_send = count( $list );
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			'🧾 در حال ارسال ' . SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $n_send ) . ' رسید معلق…'
		);

		foreach ( $list as $r ) {
			self::send_one_pending_receipt_review( $platform, $chat_id, $r );
			usleep( 180000 );
		}

		$next = $off + count( $list );
		$rows = array();
		if ( $next < $total ) {
			$cb = 'adm:rcp:p:' . $next;
			if ( strlen( $cb ) <= 64 ) {
				$rows[] = array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '📄 صفحه بعد', 64 ), 'callback_data' => $cb ) );
			}
		}
		$sum = '🧾 نمایش ' . SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) ( $off + 1 ) )
			. '–' . SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) min( $next, $total ) )
			. ' از ' . SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $total ) . ' رسید معلق.';
		$sum .= "\n⬅️ برای بازگشت «" . SimpleVPBot_Keyboards::admin_back_main_label() . '» یا زیرمنوی مالی.';
		$sum_args = array( 'reply_markup' => SimpleVPBot_Keyboards::admin_finance_submenu_reply() );
		if ( ! empty( $rows ) ) {
			$sum_args['reply_markup'] = array( 'inline_keyboard' => $rows );
		}
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $sum, $sum_args );
		delete_transient( $lock_key );
	}

	/**
	 * One pending receipt: photo + caption like user upload notify, or text fallback.
	 *
	 * @param string $platform telegram|bale.
	 * @param int    $chat_id  Admin destination.
	 * @param object $rec      Receipt row.
	 */
	private static function send_one_pending_receipt_review( $platform, $chat_id, $rec ) {
		$rid = (int) $rec->id;
		$ru  = SimpleVPBot_Model_User::find( (int) $rec->user_id );
		$tx  = SimpleVPBot_Model_Transaction::find( (int) $rec->transaction_id );
		if ( ! $ru || ! $tx ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				'🧾 رسید #' . $rid . ' (داده ناقص)',
				array( 'reply_markup' => SimpleVPBot_Keyboards::inline_receipt( $rid ) )
			);
			return;
		}
		$body       = SimpleVPBot_Bot_Admin_User_Caption::receipt_new_caption( $ru, $tx, $rid );
		$photo_args = array( 'reply_markup' => SimpleVPBot_Keyboards::inline_receipt( $rid ) );
		$tg_id      = (string) ( $rec->tg_file_id ?? '' );
		$bl_id      = (string) ( $rec->bale_file_id ?? '' );
		$r          = SimpleVPBot_Handler_Buy::send_admin_receipt_photo_review(
			$platform,
			(int) $chat_id,
			$rec,
			$tg_id,
			$bl_id,
			$body,
			$photo_args,
			$rid
		);
		$admin_msgs = array();
		if ( is_array( $r ) && ! empty( $r['result']['message_id'] ) ) {
			$admin_msgs[] = array(
				'platform'   => (string) $platform,
				'chat_id'    => (int) $chat_id,
				'message_id' => (int) $r['result']['message_id'],
			);
		} else {
			SimpleVPBot_Handler_Buy::notify_admin_receipt_photo_fallback( $platform, (int) $chat_id, $rid, $body, $admin_msgs );
		}
		if ( ! empty( $admin_msgs ) ) {
			SimpleVPBot_Handler_Buy::merge_admin_message_entries( $rid, $admin_msgs );
		}
	}

	/**
	 * Route adm:* callbacks (platform admin only; caller checks).
	 *
	 * @param array<string, mixed> $ctx platform, chat_id, parts (explode of callback_data).
	 */
	public static function handle( array $ctx ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$parts    = isset( $ctx['parts'] ) && is_array( $ctx['parts'] ) ? $ctx['parts'] : array();
		$sub      = isset( $parts[1] ) ? (string) $parts[1] : '';
		$user     = isset( $ctx['user'] ) ? $ctx['user'] : null;
		$uid      = $user && ! empty( $user->id ) ? (int) $user->id : 0;

		if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) && SimpleVPBot_Bot_Reseller_Scope::reseller_blocks_global_settings() ) {
			$blocked_cb = array( 'bk', 'crx', 'sw', 'op', 'wz' );
			if ( in_array( $sub, $blocked_cb, true )
				&& SimpleVPBot_Bot_Reseller_Scope::deny_global_settings_bot_action( $platform, $chat_id, $uid ) ) {
				return;
			}
		}

		if ( 'h' === $sub ) {
			self::send_hub( $platform, $chat_id );
			return;
		}
		if ( 'svc_del' === $sub && isset( $parts[2] ) ) {
			$sid = (int) $parts[2];
			if ( $sid < 1 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.service_id_invalid', $platform, $chat_id ) );
				return;
			}
			$svc = SimpleVPBot_Model_Service::find( $sid );
			if ( ! $svc ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.service_soft_delete_fail', $platform, $chat_id ) );
				return;
			}
			$owner_uid = (int) $svc->user_id;
			$em        = (string) ( $svc->email ?? '' );
			$ok        = SimpleVPBot_Model_Service::soft_delete( $sid );
			if ( $ok ) {
				if ( class_exists( 'SimpleVPBot_User_Activity_Log' ) && ! empty( $ctx['user'] ) && is_object( $ctx['user'] ) ) {
					$actor = (int) $ctx['user']->id;
					$ch    = 'telegram' === $platform ? 'telegram' : 'bale';
					SimpleVPBot_User_Activity_Log::append(
						array(
							'subject_svp_user_id' => $owner_uid > 0 ? $owner_uid : 0,
							'channel'             => $ch,
							'actor_kind'          => 'svp_user',
							'actor_wp_user_id'    => 0,
							'actor_svp_user_id'   => $actor,
							'platform_chat_id'    => (int) $chat_id,
							'event_type'          => 'service_soft_delete',
							'payload'             => array(
								'service_id' => $sid,
								'email'      => $em,
								'source'     => 'admin_bot_callback',
							),
						)
					);
				}
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					'✅ سرویس #' . $sid . ' از لیست فعال کاربر حذف شد (غیرفعال‌سازی نرم). کلاینت روی پنل دست‌نخورده مانده است.'
				);
			} else {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.soft_delete_fail', $platform, $chat_id ) );
			}
			return;
		}
		if ( 'st' === $sub && isset( $parts[2] ) && class_exists( 'SimpleVPBot_Admin_Dashboard_Stats' ) ) {
			$off    = max( 0, min( 7, (int) $parts[2] ) );
			$msg_id = isset( $ctx['msg_id'] ) ? (int) $ctx['msg_id'] : 0;
			$text   = SimpleVPBot_Admin_Dashboard_Stats::format_text( $off );
			$mk     = SimpleVPBot_Admin_Dashboard_Stats::inline_day_picker( $off );
			if ( $msg_id > 0 ) {
				$res = SimpleVPBot_Bot_Runtime::edit_message_text(
					$platform,
					$chat_id,
					$msg_id,
					$text,
					array( 'reply_markup' => $mk )
				);
				if ( ! is_array( $res ) || empty( $res['ok'] ) ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $text, array( 'reply_markup' => $mk ) );
				}
			} else {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $text, array( 'reply_markup' => $mk ) );
			}
			return;
		}
		if ( 'bdy' === $sub && isset( $parts[2] ) && class_exists( 'SimpleVPBot_Admin_User_Ops' ) ) {
			$d = max( 1, (int) $parts[2] );
			$r = SimpleVPBot_Admin_User_Ops::bulk_extend_days( $d, true, 200 );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				'📊 +روز ' . $d . " (Xray)\n✅ موفق: " . (int) $r['done'] . "\n⛔ خطا: " . (int) $r['errors'],
				array( 'reply_markup' => self::inline_hub_root_for_admin_chat( $platform, $chat_id ) )
			);
			return;
		}
		if ( 'bd' === $sub && isset( $parts[2] ) ) {
			self::send_bulk_days_confirm( $platform, $chat_id, max( 1, (int) $parts[2] ) );
			return;
		}
		if ( 'bgy' === $sub && isset( $parts[2] ) && class_exists( 'SimpleVPBot_Admin_User_Ops' ) ) {
			$g = max( 1, (int) $parts[2] );
			$r = SimpleVPBot_Admin_User_Ops::bulk_add_volume( $g, 200 );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				'📊 +' . $g . " GB (Xray)\n✅ موفق: " . (int) $r['done'] . "\n⛔ خطا: " . (int) $r['errors'],
				array( 'reply_markup' => self::inline_hub_root_for_admin_chat( $platform, $chat_id ) )
			);
			return;
		}
		if ( 'bg' === $sub && isset( $parts[2] ) ) {
			self::send_bulk_gb_confirm( $platform, $chat_id, max( 1, (int) $parts[2] ) );
			return;
		}
		if ( 'ua' === $sub ) {
			$off = isset( $parts[2] ) ? (int) $parts[2] : 0;
			$mid = isset( $ctx['msg_id'] ) ? (int) $ctx['msg_id'] : 0;
			self::send_approved_users_page( $platform, $chat_id, $off, $mid );
			return;
		}
		if ( 'hcs' === $sub && isset( $parts[2] ) && ! empty( $ctx['user'] ) ) {
			$tuid = (int) $parts[2];
			SimpleVPBot_State::clear( (int) $ctx['user']->id );
			self::send_admin_create_service_plan_picker( $platform, $chat_id, $tuid );
			return;
		}
		if ( 'nsp' === $sub && isset( $parts[2], $parts[3] ) && ! empty( $ctx['user'] ) ) {
			self::handle_admin_create_service_plan_pick( $ctx, (int) $parts[2], (int) $parts[3] );
			return;
		}
		if ( 'nsx' === $sub && isset( $parts[2], $parts[3], $parts[4] ) && ! empty( $ctx['user'] ) ) {
			self::handle_admin_create_service_execute(
				$ctx,
				(int) $parts[2],
				(int) $parts[3],
				null,
				strtolower( (string) $parts[4] )
			);
			return;
		}
		if ( 'nsm' === $sub && isset( $parts[2], $parts[3], $parts[4], $parts[5] ) && ! empty( $ctx['user'] ) ) {
			self::handle_admin_create_service_execute(
				$ctx,
				(int) $parts[2],
				(int) $parts[3],
				(int) $parts[4],
				strtolower( (string) $parts[5] )
			);
			return;
		}
		if ( 'nrr' === $sub && isset( $parts[2], $parts[3] ) && ! empty( $ctx['user'] ) ) {
			self::handle_admin_service_payment_execute( $ctx, 'renew', (int) $parts[2], null, strtolower( (string) $parts[3] ) );
			return;
		}
		if ( 'nva' === $sub && isset( $parts[2], $parts[3], $parts[4] ) && ! empty( $ctx['user'] ) ) {
			self::handle_admin_service_payment_execute( $ctx, 'vol', (int) $parts[2], (int) $parts[3], strtolower( (string) $parts[4] ) );
			return;
		}
		if ( 'nus' === $sub && isset( $parts[2], $parts[3], $parts[4] ) && ! empty( $ctx['user'] ) ) {
			self::handle_admin_service_payment_execute( $ctx, 'slots', (int) $parts[2], (int) $parts[3], strtolower( (string) $parts[4] ) );
			return;
		}
		if ( 'wbp' === $sub && isset( $parts[2] ) && ! empty( $ctx['user'] ) ) {
			$tuid = (int) $parts[2];
			if ( $tuid > 0 ) {
				SimpleVPBot_State::set( (int) $ctx['user']->id, 'admin_w_balance', array( 'target_uid' => $tuid, 'sign' => 1 ) );
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					self::admin_msg( 'msg.admin.prompt_wallet_credit', $platform, $chat_id, array( 'id' => $tuid ) )
				);
			}
			return;
		}
		if ( 'wbm' === $sub && isset( $parts[2] ) && ! empty( $ctx['user'] ) ) {
			$tuid = (int) $parts[2];
			if ( $tuid > 0 ) {
				SimpleVPBot_State::set( (int) $ctx['user']->id, 'admin_w_balance', array( 'target_uid' => $tuid, 'sign' => -1 ) );
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					self::admin_msg( 'msg.admin.prompt_wallet_debit', $platform, $chat_id, array( 'id' => $tuid ) )
				);
			}
			return;
		}
		if ( 'ar' === $sub && isset( $parts[2] ) && ! empty( $ctx['user'] ) ) {
			$sid = (int) $parts[2];
			SimpleVPBot_State::set( (int) $ctx['user']->id, 'admin_line_nr', array( 'service_id' => $sid ) );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.prompt_renew_line', $platform, $chat_id, array( 'id' => $sid ) ) );
			return;
		}
		if ( 'av' === $sub && isset( $parts[2] ) && ! empty( $ctx['user'] ) ) {
			$sid = (int) $parts[2];
			SimpleVPBot_State::set( (int) $ctx['user']->id, 'admin_line_nv', array( 'service_id' => $sid ) );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.prompt_add_volume_line', $platform, $chat_id, array( 'id' => $sid ) ), array( 'parse_mode' => 'HTML' ) );
			return;
		}
		if ( 'hcb' === $sub && ! empty( $ctx['user'] ) ) {
			SimpleVPBot_State::set( (int) $ctx['user']->id, 'admin_line_bl', array() );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				self::admin_msg( 'msg.admin.prompt_bulk_xray', $platform, $chat_id ),
				array( 'parse_mode' => 'HTML' )
			);
			return;
		}
		if ( 'crx' === $sub ) {
			$all = SimpleVPBot_Settings::all();
			$all['crypto_ipn_path_secret'] = wp_generate_password( 32, false, false );
			SimpleVPBot_Settings::update( $all );
			SimpleVPBot_Texts::clear_cache();
			$uipn = SimpleVPBot_Crypto_Payment::ipn_callback_url();
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				self::admin_msg( 'msg.admin.ipn_saved', $platform, $chat_id ) . ( $uipn ? "\n🔗 " . $uipn : '' ),
				array( 'reply_markup' => self::inline_hub_root_for_admin_chat( $platform, $chat_id ) )
			);
			return;
		}
		if ( 'l2' === $sub && isset( $parts[2], $parts[3] ) ) {
			$act = (string) $parts[2];
			$lid = (int) $parts[3];
			if ( 'g' === $act && $lid > 0 ) {
				$row = SimpleVPBot_Model_L2TP_Server::find( $lid );
				if ( $row ) {
					$new = empty( $row->active ) ? 1 : 0;
					SimpleVPBot_Model_L2TP_Server::update( $lid, array( 'active' => $new ) );
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.server_active', $platform, $chat_id, array( 'id' => $lid, 'state' => $new ) ) );
				}
				return;
			}
			if ( 'd' === $act && $lid > 0 ) {
				SimpleVPBot_Model_L2TP_Server::delete( $lid );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.server_deleted', $platform, $chat_id, array( 'id' => $lid ) ) );
				return;
			}
		}
		if ( 'bk' === $sub ) {
			self::handle_backup_callback( $ctx, $parts );
			return;
		}
		if ( 'op' === $sub && isset( $parts[2] ) ) {
			SimpleVPBot_Handler_Admin_Settings::handle_op( $ctx, (string) $parts[2] );
			return;
		}
		if ( 'wz' === $sub && isset( $parts[2], $parts[3] ) ) {
			SimpleVPBot_Handler_Admin_Settings::start_wizard( $ctx, (string) $parts[2], (string) $parts[3] );
			return;
		}
		if ( 'w' === $sub && isset( $parts[2] ) ) {
			SimpleVPBot_Handler_Admin_Settings::start_catalog_wizard( $ctx, (string) $parts[2] );
			return;
		}
		if ( 'pe' === $sub && isset( $parts[2], $parts[3] ) ) {
			$act = (string) $parts[2];
			$uid = (int) $parts[3];
			if ( 'a' === $act && $uid > 0 ) {
				SimpleVPBot_Model_User::update(
					$uid,
					array(
						'status'      => 'approved',
						'approved_by' => 'bot',
						'approved_at' => current_time( 'mysql' ),
					)
				);
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.user_approved', $platform, $chat_id, array( 'id' => $uid ) ) );
			} elseif ( 'r' === $act && $uid > 0 ) {
				SimpleVPBot_Model_User::update( $uid, array( 'status' => 'rejected' ) );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.user_rejected', $platform, $chat_id, array( 'id' => $uid ) ) );
			}
			return;
		}
		if ( 'up' === $sub && 'n' === ( $parts[2] ?? '' ) && isset( $parts[3] ) ) {
			$mid = isset( $ctx['msg_id'] ) ? (int) $ctx['msg_id'] : 0;
			self::send_pending_users_page( $platform, $chat_id, (int) $parts[3], $mid );
			return;
		}
		if ( 'pq' === $sub && isset( $parts[2] ) ) {
			$mid = isset( $ctx['msg_id'] ) ? (int) $ctx['msg_id'] : 0;
			self::send_pending_users_page( $platform, $chat_id, (int) $parts[2], $mid );
			return;
		}
		if ( 'aq' === $sub && isset( $parts[2] ) ) {
			$mid = isset( $ctx['msg_id'] ) ? (int) $ctx['msg_id'] : 0;
			self::send_approved_users_page( $platform, $chat_id, (int) $parts[2], $mid );
			return;
		}
		if ( 'rq' === $sub && isset( $parts[2] ) ) {
			$mid = isset( $ctx['msg_id'] ) ? (int) $ctx['msg_id'] : 0;
			self::send_rejected_users_page( $platform, $chat_id, (int) $parts[2], $mid );
			return;
		}
		if ( 'ui' === $sub && isset( $parts[2] ) ) {
			self::send_user_admin_preview( $platform, $chat_id, (int) $parts[2] );
			return;
		}
		if ( 'rr' === $sub && isset( $parts[2] ) && class_exists( 'SimpleVPBot_User_Membership' ) ) {
			$uid = (int) $parts[2];
			$r   = SimpleVPBot_User_Membership::reopen_rejected_to_pending( $uid );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				! empty( $r['ok'] ) ? '✅ کاربر #' . $uid . ' به صف برگردانده شد.' : ( '⛔ نشد: ' . (string) ( $r['reason'] ?? '—' ) )
			);
			return;
		}
		if ( 'lg' === $sub && isset( $parts[2] ) ) {
			self::send_logs_page( $platform, $chat_id, (int) $parts[2] );
			return;
		}
		if ( 'ib' === $sub && isset( $parts[2] ) ) {
			$op = (string) $parts[2];
			if ( 'p' === $op && isset( $parts[3] ) ) {
				self::send_inbounds_list_for_panel( $platform, $chat_id, $ctx, max( 1, (int) $parts[3] ) );
			} elseif ( 'l' === $op ) {
				self::send_inbounds_list( $platform, $chat_id, $ctx );
			} elseif ( 'i' === $op && isset( $parts[3] ) ) {
				self::send_inbound_clients( $platform, $chat_id, (int) $parts[3], $ctx );
			} elseif ( 'k' === $op && isset( $parts[3] ) ) {
				$iid = (int) $parts[3];
				$pid = 1;
				if ( ! empty( $ctx['user'] ) && $ctx['user']->id ) {
					$ibx = get_transient( 'svp_ibctx_' . (int) $ctx['user']->id );
					if ( is_array( $ibx ) && isset( $ibx['panel_id'] ) ) {
						$pid = (int) $ibx['panel_id'];
						if ( $pid < 0 ) {
							$pid = 0;
						}
					}
				}
				$r   = SimpleVPBot_Service_Admin_Ops::inbound_autolink( $iid, $pid );
				$msg = ! empty( $r['ok'] ) ? ( '✅ ' . mb_substr( wp_json_encode( $r['data'] ?? array() ), 0, 3000 ) ) : ( '⛔ ' . (string) ( $r['message'] ?? '' ) );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $msg );
			}
			return;
		}
		if ( 'il' === $sub && isset( $parts[2] ) && ! empty( $ctx['user'] ) ) {
			self::start_inbound_link( $ctx, (int) $parts[2] );
			return;
		}
		if ( 'th' === $sub && isset( $parts[2] ) && ! empty( $ctx['user'] ) ) {
			$h   = (string) $parts[2];
			$key = get_transient( 'svp_txh_' . $h );
			if ( is_string( $key ) && '' !== $key ) {
				SimpleVPBot_State::set( (int) $ctx['user']->id, 'admin_txt_edit', array( 'key' => $key ) );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.prompt_new_value', $platform, $chat_id, array( 'key' => $key ) ) );
			}
			return;
		}
		if ( 'tv' === $sub && isset( $parts[2] ) ) {
			$h   = (string) $parts[2];
			$key = get_transient( 'svp_txv_' . $h );
			if ( is_string( $key ) && '' !== $key ) {
				$val = SimpleVPBot_Model_Text::get( $key, '—' );
				$val = mb_substr( wp_strip_all_tags( $val ), 0, 500 );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.text_preview', $platform, $chat_id, array( 'key' => $key, 'value' => $val ) ) );
			}
			return;
		}
		if ( 'll' === $sub && isset( $parts[2] ) ) {
			$sid = (int) $parts[2];
			$r   = SimpleVPBot_Service_Admin_Ops::l2tp_test( $sid );
			$msg = ! empty( $r['ok'] ) ? ( '✅ ' . (string) ( $r['message'] ?? 'OK' ) . "\n" . mb_substr( wp_json_encode( $r['data'] ?? array() ), 0, 2500 ) ) : ( '⛔ ' . (string) ( $r['message'] ?? '' ) );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $msg );
			return;
		}
		if ( 'dl' === $sub && isset( $parts[2], $parts[3] ) ) {
			$ent = (string) $parts[2];
			$eid = (int) $parts[3];
			if ( 'pl' === $ent && $eid > 0 ) {
				$out = SimpleVPBot_Service_Admin_Catalog::apply_plan_action( 'delete', $eid, array() );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, ! empty( $out['ok'] ) ? self::admin_msg( 'msg.admin.plan_deleted_ok', $platform, $chat_id ) : self::admin_msg( 'msg.admin.plan_delete_fail', $platform, $chat_id ) );
			} elseif ( 'pc' === $ent && $eid > 0 ) {
				$out = SimpleVPBot_Service_Admin_Catalog::apply_plan_category_action( 'delete', $eid, array() );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, ! empty( $out['ok'] ) ? self::admin_msg( 'msg.admin.category_deleted_ok', $platform, $chat_id ) : self::admin_msg( 'msg.admin.category_delete_rejected', $platform, $chat_id, array( 'code' => (string) ( $out['code'] ?? 'رد' ) ) ) );
			} elseif ( 'cd' === $ent && $eid > 0 ) {
				SimpleVPBot_Model_Card::delete( $eid );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.card_deleted', $platform, $chat_id ) );
			}
			return;
		}
		if ( 'picku' === $sub && isset( $parts[2] ) ) {
			self::send_user_admin_card( $platform, $chat_id, (int) $parts[2] );
			return;
		}
		if ( 'umsg' === $sub && isset( $parts[2] ) && ! empty( $ctx['user'] ) ) {
			$tuid = (int) $parts[2];
			if ( $tuid > 0 ) {
				SimpleVPBot_State::set( (int) $ctx['user']->id, 'admin_dm', array( 'target_user_id' => $tuid ) );
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					'✉️ پیام خود را برای کاربر #' . $tuid . " بفرستید.\n/cancel برای لغو."
				);
			}
			return;
		}
		if ( 'rcp' === $sub ) {
			$off = 0;
			if ( isset( $parts[2], $parts[3] ) && 'p' === (string) $parts[2] ) {
				$off = max( 0, (int) $parts[3] );
			}
			self::send_pending_receipts_review_paged( $platform, $chat_id, $off );
			return;
		}
		if ( 'blk' === $sub && isset( $parts[2] ) ) {
			SimpleVPBot_Model_User::update( (int) $parts[2], array( 'status' => 'blocked' ) );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.user_status_updated', $platform, $chat_id ) );
			return;
		}
		if ( 'ub' === $sub && isset( $parts[2] ) ) {
			SimpleVPBot_Model_User::update( (int) $parts[2], array( 'status' => 'approved' ) );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.user_status_updated', $platform, $chat_id ) );
			return;
		}
		if ( 'stx' === $sub && isset( $parts[2] ) ) {
			$sid = (int) $parts[2];
			if ( $sid > 0 ) {
				$user = isset( $ctx['user'] ) ? $ctx['user'] : null;
				if ( $user ) {
					SimpleVPBot_State::set( (int) $user->id, 'adm_service_transfer_' . $sid, array( 'service_id' => $sid ) );
				}
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					'🎁 انتقال سرویس #' . $sid . "\nشناسه مقصد را ارسال کنید:\n- svp_users.id\n- یا @username\n- یا عدد chat id (تلگرام/بله)"
				);
			}
			return;
		}
		if ( 'tx' === $sub ) {
			$op = isset( $parts[2] ) ? (string) $parts[2] : '';
			if ( 'p' === $op && isset( $parts[3] ) ) {
				$u = null;
				if ( isset( $ctx['user'] ) ) {
					$u = $ctx['user'];
				} elseif ( isset( $parts[4] ) && (int) $parts[4] > 0 ) {
					$u = SimpleVPBot_Model_User::find( (int) $parts[4] );
				}
				self::send_text_keys_page( $platform, $chat_id, (int) $parts[3], $u );
				return;
			}
			if ( 'v' === $op ) {
				$key = implode( ':', array_slice( $parts, 3 ) );
				$key = trim( $key );
				if ( '' !== $key ) {
					$val = SimpleVPBot_Model_Text::get( $key, '—' );
					$val = mb_substr( wp_strip_all_tags( $val ), 0, 500 );
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.text_preview', $platform, $chat_id, array( 'key' => $key, 'value' => $val ) ) );
				}
				return;
			}
		}
		if ( 'sw' === $sub && isset( $parts[2] ) ) {
			$key = sanitize_key( (string) $parts[2] );
			if ( SimpleVPBot_Admin_Actions::toggle_bool_setting( $key ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.setting_changed', $platform, $chat_id, array( 'key' => $key ) ) );
			} else {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.setting_not_switchable', $platform, $chat_id ) );
			}
			return;
		}
		if ( 'pl' === $sub && isset( $parts[2], $parts[3] ) && 'a' === $parts[2] ) {
			$pid = (int) $parts[3];
			$row = SimpleVPBot_Model_Plan::find( $pid );
			if ( $row ) {
				$new = empty( $row->active ) ? 1 : 0;
				SimpleVPBot_Model_Plan::update( $pid, array( 'active' => $new ) );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.plan_active', $platform, $chat_id, array( 'id' => $pid, 'state' => $new ) ) );
			}
			return;
		}
		if ( 'pc' === $sub && isset( $parts[2], $parts[3] ) && 'a' === $parts[2] ) {
			$cid = (int) $parts[3];
			$row = SimpleVPBot_Model_Plan_Category::find( $cid );
			if ( $row ) {
				$new = empty( $row->active ) ? 1 : 0;
				SimpleVPBot_Model_Plan_Category::update( $cid, array( 'active' => $new ) );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.category_active', $platform, $chat_id, array( 'id' => $cid, 'state' => $new ) ) );
			}
			return;
		}
		if ( 'cd' === $sub && isset( $parts[2], $parts[3] ) && 'a' === $parts[2] ) {
			$cid = (int) $parts[3];
			$row = SimpleVPBot_Model_Card::find( $cid );
			if ( $row ) {
				$new = empty( $row->active ) ? 1 : 0;
				SimpleVPBot_Model_Card::update( $cid, array( 'active' => $new ) );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.card_active', $platform, $chat_id, array( 'id' => $cid, 'state' => $new ) ) );
			}
			return;
		}
		if ( 'm' === $sub && isset( $parts[2] ) ) {
			self::send_submenu( $platform, $chat_id, (string) $parts[2], $ctx );
			return;
		}
		if ( '' !== $sub ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				'ℹ️ این دکمه شناخته نشد.',
				array( 'reply_markup' => self::inline_hub_root_for_admin_chat( $platform, $chat_id ) )
			);
		}
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat.
	 * @param string $code Short tab code.
	 * @param array<string, mixed> $ctx Optional: user for per-admin flows (e.g. text keys edit).
	 */
	public static function send_submenu( $platform, $chat_id, $code, $ctx = null ) {
		$tu = ( is_array( $ctx ) && ! empty( $ctx['user'] ) ) ? $ctx['user'] : null;
		if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' )
			&& SimpleVPBot_Bot_Reseller_Scope::reseller_hub_submenu_blocked( (string) $code ) ) {
			$uid = $tu && ! empty( $tu->id ) ? (int) $tu->id : 0;
			SimpleVPBot_Bot_Reseller_Scope::deny_global_settings_bot_action( $platform, $chat_id, $uid );
			return;
		}
		$s  = SimpleVPBot_Settings::all();
		switch ( $code ) {
			case 'gen':
				$tg_n = is_array( $s['admin_telegram_ids'] ?? null ) ? count( (array) $s['admin_telegram_ids'] ) : 0;
				$bl_n = is_array( $s['admin_bale_ids'] ?? null ) ? count( (array) $s['admin_bale_ids'] ) : 0;
				$t    = "⚙️ عمومی\n";
				$t   .= 'فعال: ' . ( ! empty( $s['enabled'] ) ? 'بله' : 'خیر' ) . ' · تست: ' . ( ! empty( $s['test_account_enabled'] ) ? 'بله' : 'خیر' ) . "\n";
				$dfp = (int) ( $s['default_service_plan_id'] ?? 0 );
				$t   .= "ادمین TG: {$tg_n} · ادمین Bale: {$bl_n} · صفحه: " . (int) ( $s['portal_page_id'] ?? 0 ) . " · پلن پیش‌فرض سرویس: {$dfp}\n➖";
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t, array( 'reply_markup' => SimpleVPBot_Keyboards::admin_general_submenu_reply( $tu ) ) );
				return;
			case 'set':
				$t = "⚙️ تنظیمات\nپلن، کارت، پنل ۳x-ui";
				if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) && SimpleVPBot_Feature_L2tp::enabled() ) {
					$t .= '، L2TP';
				}
				$t .= "، کانفیگ، کریپتو، ربات.\n➖";
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t, array( 'reply_markup' => SimpleVPBot_Keyboards::admin_settings_catalog_reply( $tu ) ) );
				return;
			case 'adv':
				$t = "🔧 تنظیمات پیشرفته\nعمومی، نوتیف، متن‌ها، لاگ، گزارش همگانی.\n➖";
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t, array( 'reply_markup' => SimpleVPBot_Keyboards::admin_settings_advanced_reply( $tu ) ) );
				return;
			case 'bot':
				$tl = strlen( (string) ( $s['telegram_token'] ?? '' ) );
				$bl = strlen( (string) ( $s['bale_token'] ?? '' ) );
				$t  = "🤖 ربات‌ها\nطول token TG: {$tl} · Bale: {$bl}\n➖";
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t, array( 'reply_markup' => SimpleVPBot_Keyboards::admin_bot_submenu_reply( $tu ) ) );
				return;
			case 'pan':
				$t = "🖥 پنل 3x-ui\n" . ( '' !== (string) ( $s['panel_url'] ?? '' ) ? 'URL: دارد' : 'URL: خالی' ) . "\n➖";
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t, array( 'reply_markup' => SimpleVPBot_Keyboards::admin_panel_submenu_reply( $tu ) ) );
				return;
			case 'bak':
				self::send_backup_panel( $platform, $chat_id );
				return;
			case 'not':
				$t  = "🔔 نوتیف\n";
				$t .= '٪ کم: ' . (int) ( $s['notify_low_traffic_percent'] ?? 10 ) . ' · هم‌زمان: ' . (int) ( $s['default_concurrent_users'] ?? 2 ) . "\n";
				$t .= 'هشدار روز: ' . esc_html( implode( ',', (array) ( $s['notify_expiry_days'] ?? array( 3, 1 ) ) ) );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t, array( 'reply_markup' => SimpleVPBot_Keyboards::admin_notif_submenu_reply( $tu ) ) );
				return;
			case 'plc':
				self::send_plan_categories_list( $platform, $chat_id );
				return;
			case 'pln':
				self::send_plans_list( $platform, $chat_id );
				return;
			case 'crd':
				self::send_cards_list( $platform, $chat_id );
				return;
			case 'usr':
				self::send_pending_users_page( $platform, $chat_id, 0, 0 );
				return;
			case 'rcp':
				self::send_pending_receipts_review_paged( $platform, $chat_id, 0 );
				return;
			case 'txt':
				$tu = ( is_array( $ctx ) && ! empty( $ctx['user'] ) ) ? $ctx['user'] : null;
				self::send_text_keys_page( $platform, $chat_id, 0, $tu );
				return;
			case 'l2p':
				if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) && ! SimpleVPBot_Feature_L2tp::enabled() ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.unavailable', $platform, $chat_id ) );
					return;
				}
				self::send_l2tp_admin_panel( $platform, $chat_id );
				return;
			case 'pay':
				self::send_crypto_pay_panel( $platform, $chat_id );
				return;
			case 'blk':
				$t  = "➕ عملیات گروهی (Xray)\n";
				$t .= "⚠️ بار زیاد روی پنل؛ حداکثر ۲۰۰ سرویس در هر اجرا.\n➖\n";
				$t .= "۱) از «🔎 جستجوی کاربر» در منوی مدیریت کاربران یک کاربر را باز کنید.\n";
				$t .= "۲) دکمهٔ سریع → یک مرحلهٔ تأیید با دکمهٔ بعدی؛ یا «📝 تأیید متنی گروهی».\n";
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t, array( 'reply_markup' => SimpleVPBot_Keyboards::admin_bulk_submenu_reply( $tu ) ) );
				return;
			case 'log':
				self::send_logs_page( $platform, $chat_id, 0 );
				return;
			case 'inl':
				$t = "🔗 Inbound (پنل ۳x-ui)\nلیست → کلاینت‌ها → لینک به کاربر svp";
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t, array( 'reply_markup' => SimpleVPBot_Keyboards::admin_inbound_submenu_reply( $tu ) ) );
				return;
			case 'brd':
				$br = SimpleVPBot_Model_Broadcast::list_recent( 5, 0 );
				$t  = "📣 آخرین همگانی\n➖\n";
				if ( empty( $br ) ) {
					$t .= 'رکوردی نیست. از دکمه «پیام همگانی» متن ارسال کنید.';
				} else {
					foreach ( $br as $b ) {
						$t .= '#' . (int) $b->id . ' ' . (string) $b->status . ' · ' . (string) $b->created_at . "\n";
					}
				}
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					$t,
					array( 'reply_markup' => SimpleVPBot_Keyboards::admin_only_back_reply() )
				);
				return;
			default:
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.unknown', $platform, $chat_id ), array( 'reply_markup' => self::reply_markup_main_for_chat( $platform, $chat_id ) ) );
		}
	}

	/**
	 * Plan categories with toggle.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat.
	 */
	private static function send_plan_categories_list( $platform, $chat_id ) {
		$list = SimpleVPBot_Model_Plan_Category::all_ordered();
		$rows = array(
			array( array( 'text' => '➕ دسته جدید' ) ),
		);
		foreach ( $list as $c ) {
			$lab = '#' . (int) $c->id . ' ' . mb_substr( (string) $c->label, 0, 14 );
			$on  = ! empty( $c->active ) ? '✓' : '✗';
			$cid = (int) $c->id;
			$rows[] = array(
				array( 'text' => $on . ' ' . $lab ),
				array( 'text' => '🗑 دسته ' . $cid ),
			);
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			'📂 دسته‌های پلن — ➕ جدید؛ ردیف اول فعال/غیر، 🗑 حذف',
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $rows ) )
		);
	}

	/**
	 * Plans list with toggle.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat.
	 */
	private static function send_plans_list( $platform, $chat_id ) {
		$list = SimpleVPBot_Model_Plan::all_rows();
		if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) ) {
			$list = SimpleVPBot_Feature_L2tp::filter_plans( (array) $list );
		}
		$rows = array(
			array( array( 'text' => '➕ پلن جدید (Xray)' ) ),
		);
		foreach ( array_slice( $list, 0, 20 ) as $p ) {
			$lab = '#' . (int) $p->id . ' ' . mb_substr( (string) $p->name, 0, 12 );
			$on  = ! empty( $p->active ) ? '✓' : '✗';
			$pid = (int) $p->id;
			$rows[] = array(
				array( 'text' => $on . ' ' . $lab ),
				array( 'text' => '🗑 پلن ' . $pid ),
			);
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			'📋 پلن‌ها (حداکثر ۲۰) — ➕ جدید؛ ردیف فعال/غیر؛ 🗑 حذف',
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $rows ) )
		);
	}

	/**
	 * Cards list with toggle.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat.
	 */
	private static function send_cards_list( $platform, $chat_id ) {
		$list = SimpleVPBot_Model_Card::all();
		$rows = array(
			array( array( 'text' => '➕ کارت جدید' ) ),
		);
		foreach ( array_slice( $list, 0, 15 ) as $c ) {
			$lab = '#' . (int) $c->id . ' ' . mb_substr( SimpleVPBot_Model_Card::method_label( $c ), 0, 10 );
			$on  = ! empty( $c->active ) ? '✓' : '✗';
			$cid = (int) $c->id;
			$rows[] = array(
				array( 'text' => $on . ' ' . $lab ),
				array( 'text' => '🗑 کارت ' . $cid ),
			);
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			'💳 کارت‌ها — ➕ جدید؛ ردیف فعال/غیر؛ 🗑 حذف',
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $rows ) )
		);
	}

	/**
	 * Paginated text keys (view + optional edit from callback).
	 *
	 * @param string    $platform Platform.
	 * @param int       $chat_id  Chat.
	 * @param int       $offset   Offset.
	 * @param object|null $user   Admin user (for edit hash / pagination).
	 */
	private static function send_text_keys_page( $platform, $chat_id, $offset, $user = null ) {
		$all   = SimpleVPBot_Model_Text::all();
		$off   = max( 0, (int) $offset );
		$slice = array_slice( $all, $off, 8 );
		if ( empty( $slice ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.no_text_saved', $platform, $chat_id ), array( 'reply_markup' => self::reply_markup_main_for_chat( $platform, $chat_id ) ) );
			return;
		}
		$uid  = ( $user && ! empty( $user->id ) ) ? (int) $user->id : 0;
		$rows = array();
		foreach ( $slice as $row ) {
			$key = (string) $row->key_name;
			$h8v = substr( md5( $key . 'v' ), 0, 8 );
			set_transient( 'svp_txv_' . $h8v, $key, 3600 );
			$row_btns = array( array( 'text' => '👁 ' . $h8v . ' ' . mb_substr( $key, 0, 20 ) ) );
			if ( $user && $uid ) {
				$h8e = substr( md5( $key . 'u' . $uid ), 0, 8 );
				set_transient( 'svp_txh_' . $h8e, $key, 3600 );
				$row_btns[] = array( 'text' => '✏ ' . $h8e );
			}
			$rows[] = $row_btns;
		}
		$nav = array();
		if ( $off > 0 ) {
			$nav[] = array( 'text' => '◀ متن قبلی' );
		}
		if ( $off + 8 < count( $all ) ) {
			$nav[] = array( 'text' => 'متن بعدی ▶' );
		}
		if ( $nav ) {
			$rows[] = $nav;
		}
		if ( $user && $uid ) {
			$rows[] = array( array( 'text' => '🔄 همه به پیش‌فرض' ) );
		}
		if ( $user && ! empty( $user->id ) ) {
			SimpleVPBot_State::set( (int) $user->id, 'admin_txt_page', array( 'off' => $off ) );
		}
		$hint = '📝 کلیدهای متن — 👁 مشاهده (کد ۸ کاراکتری)؛ ✏ ویرایش سپس متن جدید بفرستید.' . "\n"
			. '🔄 «همه به پیش‌فرض» همهٔ کلیدها را به مقادیر پیش‌فرض نسخهٔ فعلی پلاگین برمی‌گرداند.';
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$hint,
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $rows ) )
		);
	}

	/**
	 * Send full backup control panel (inline).
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat.
	 */
	public static function send_backup_panel( $platform, $chat_id ) {
		$s = SimpleVPBot_Settings::all();
		$t = self::backup_panel_caption( $s );
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$t,
			array( 'reply_markup' => self::backup_panel_reply_markup( $s ) )
		);
	}

	/**
	 * @param array<string, mixed> $s Settings.
	 * @return string
	 */
	public static function backup_panel_caption( $s ) {
		$iv = (int) ( $s['backup_interval_minutes'] ?? 60 );
		$t  = "💾 بکاپ و ریستور\n➖➖➖➖\n";
		$t .= '⏱ فاصله: ' . $iv . " دقیقه\n";
		$t .= '📢 TG chat id: ' . (int) ( $s['backup_telegram_chat_id'] ?? 0 ) . "\n";
		$t .= '💬 Bale chat id: ' . (int) ( $s['backup_bale_chat_id'] ?? 0 ) . "\n";
		$sta  = ! empty( $s['backup_send_telegram_admins'] ) ? '✓' : '✗';
		$sba  = ! empty( $s['backup_send_bale_admins'] ) ? '✓' : '✗';
		$stc  = ! empty( $s['backup_send_telegram_channel'] ) ? '✓' : '✗';
		$sbc  = ! empty( $s['backup_send_bale_channel'] ) ? '✓' : '✗';
		$t   .= "ارسال: TG ادمین {$sta} · Bale ادمین {$sba} · TG کانال {$stc} · Bale کانال {$sbc}\n";
		$lbat = (int) get_option( 'simplevpbot_last_backup_at', 0 );
		$lbui = (int) get_option( 'simplevpbot_last_backup_built_at', 0 );
		$t   .= 'آخرین ارسال موفق: ' . self::fmt_backup_ts( $lbat ) . "\n";
		$t   .= 'آخرین ساخت زیپ: ' . self::fmt_backup_ts( $lbui ) . "\n";
		$t   .= "➖\nدکمه‌ها: بکاپ الان، تیک‌ها، ویرایش مقدار، ریستور (۲ مرحله).";
		return $t;
	}

	/**
	 * @param int $ts Unix.
	 * @return string
	 */
	private static function fmt_backup_ts( $ts ) {
		$ts = (int) $ts;
		if ( $ts <= 0 ) {
			return '—';
		}
		return gmdate( 'Y-m-d H:i', $ts ) . ' UTC';
	}

	/**
	 * @param array<string, mixed> $s Settings.
	 * @return array<string, mixed>
	 */
	public static function backup_panel_reply_markup( $s ) {
		return SimpleVPBot_Keyboards::admin_backup_panel_reply( $s );
	}

	/**
	 * Step 1 of 2: bulk +days — ask inline confirmation.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat.
	 * @param int    $days Days.
	 */
	private static function send_bulk_days_confirm( $platform, $chat_id, $days ) {
		$d = max( 1, (int) $days );
		$t = "⚠️ تأیید عملیات گروهی\n➖\nافزودن «{$d}» روز به سرویس‌های Xray (حداکثر ۲۰۰ سرویس در هر اجرا).\nادامه؟";
		$rows = array(
			array(
				array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '✅ تأیید +' . $d . ' روز', 256 ) ),
				array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '❌ لغو گروهی', 256 ) ),
			),
		);
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t, array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $rows ) ) );
	}

	/**
	 * Step 1 of 2: bulk +GB — ask inline confirmation.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat.
	 * @param int    $gb Gigabytes.
	 */
	private static function send_bulk_gb_confirm( $platform, $chat_id, $gb ) {
		$g = max( 1, (int) $gb );
		$t = "⚠️ تأیید عملیات گروهی\n➖\nافزودن «{$g}» گیگ به هر سرویس Xray (حداکثر ۲۰۰ سرویس).\nادامه؟";
		$rows = array(
			array(
				array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '✅ تأیید +' . $g . ' GB', 256 ) ),
				array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '❌ لغو گروهی', 256 ) ),
			),
		);
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t, array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $rows ) ) );
	}

	/**
	 * Public wrappers for Bot UI router (bulk confirm step 1).
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat id.
	 * @param int    $days Days.
	 */
	public static function router_bulk_days_confirm( $platform, $chat_id, $days ) {
		self::send_bulk_days_confirm( $platform, $chat_id, $days );
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat id.
	 * @param int    $gb GB.
	 */
	public static function router_bulk_gb_confirm( $platform, $chat_id, $gb ) {
		self::send_bulk_gb_confirm( $platform, $chat_id, $gb );
	}

	/**
	 * Edit or send queue message body + inline keyboard.
	 *
	 * @param string               $platform Platform.
	 * @param int                  $chat_id Chat.
	 * @param string               $text Body.
	 * @param array<string, mixed> $markup Inline keyboard.
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

	/**
	 * Approved users (paged, inline).
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat.
	 * @param int    $offset Offset.
	 * @param int    $edit_msg_id Edit existing message id (optional).
	 */
	private static function send_approved_users_page( $platform, $chat_id, $offset = 0, $edit_msg_id = 0 ) {
		$off  = max( 0, (int) $offset );
		$lim  = 5;
		$list = SimpleVPBot_Model_User::list_by_status_paged( 'approved', $off, $lim );
		$tot  = SimpleVPBot_Model_User::count_status( 'approved' );
		$t    = "✅ کاربران تأییدشده ({$tot})\nصفحه offset {$off}\n➖\n";
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
					'callback_data' => 'adm:ui:' . $uid,
				),
			);
		}
		$nav = array();
		if ( $off + $lim < $tot ) {
			$nav[] = array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( '▶ بعدی', 16 ),
				'callback_data' => 'adm:aq:' . ( $off + $lim ),
			);
		}
		if ( $off > 0 ) {
			$nav[] = array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( '◀ قبلی', 16 ),
				'callback_data' => 'adm:aq:' . max( 0, $off - $lim ),
			);
		}
		if ( $nav ) {
			$ik[] = $nav;
		}
		$ik[] = array(
			array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( '📋 صف انتظار', 20 ),
				'callback_data' => 'adm:pq:0',
			),
			array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( '❌ ردشده', 16 ),
				'callback_data' => 'adm:rq:0',
			),
		);
		self::push_queue_message( $platform, $chat_id, $t, array( 'inline_keyboard' => $ik ), (int) $edit_msg_id );
	}

	/**
	 * Rejected users with reopen-to-queue (inline).
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat.
	 * @param int    $offset Offset.
	 * @param int    $edit_msg_id Edit message id.
	 */
	private static function send_rejected_users_page( $platform, $chat_id, $offset = 0, $edit_msg_id = 0 ) {
		$off  = max( 0, (int) $offset );
		$lim  = 5;
		$list = SimpleVPBot_Model_User::list_by_status_paged( 'rejected', $off, $lim );
		$tot  = SimpleVPBot_Model_User::count_status( 'rejected' );
		$t    = "❌ کاربران رد شده ({$tot})\noffset {$off}\n➖\n";
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
					'callback_data' => 'adm:ui:' . $uid,
				),
				array(
					'text'          => SimpleVPBot_Keyboards::glass_button_text( '↩ صف', 12 ),
					'callback_data' => 'adm:rr:' . $uid,
				),
			);
		}
		$nav = array();
		if ( $off + $lim < $tot ) {
			$nav[] = array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( '▶ بعدی', 16 ),
				'callback_data' => 'adm:rq:' . ( $off + $lim ),
			);
		}
		if ( $off > 0 ) {
			$nav[] = array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( '◀ قبلی', 16 ),
				'callback_data' => 'adm:rq:' . max( 0, $off - $lim ),
			);
		}
		if ( $nav ) {
			$ik[] = $nav;
		}
		$ik[] = array(
			array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( '📋 صف انتظار', 20 ),
				'callback_data' => 'adm:pq:0',
			),
			array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( '✅ تأییدشده', 16 ),
				'callback_data' => 'adm:aq:0',
			),
		);
		self::push_queue_message( $platform, $chat_id, $t, array( 'inline_keyboard' => $ik ), (int) $edit_msg_id );
	}

	/**
	 * L2TP servers: test, toggle, delete, add wizard.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat.
	 */
	private static function send_l2tp_admin_panel( $platform, $chat_id ) {
		if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) && ! SimpleVPBot_Feature_L2tp::enabled() ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.unavailable', $platform, $chat_id ) );
			return;
		}
		$lrows = SimpleVPBot_Model_L2TP_Server::all();
		$t     = '🔌 L2TP (' . count( $lrows ) . ")\n➖\n";
		$rows  = array(
			array( array( 'text' => '➕ سرور جدید (خطی)' ) ),
		);
		foreach ( array_slice( $lrows, 0, 6 ) as $srv ) {
			$id = (int) $srv->id;
			$sl = trim( (string) ( $srv->label ?? '' ) );
			if ( '' === $sl ) {
				$sl = '#' . $id;
			}
			$rows[] = array(
				array( 'text' => 'L2 تست ' . $id ),
				array( 'text' => 'L2 سوییچ ' . $id ),
				array( 'text' => 'L2 حذف ' . $id ),
			);
		}
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t, array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $rows ) ) );
	}

	/**
	 * NOWPayments / IPN summary + wizards.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat.
	 */
	private static function send_crypto_pay_panel( $platform, $chat_id ) {
		$s   = SimpleVPBot_Settings::all();
		$ipn = SimpleVPBot_Crypto_Payment::ipn_callback_url();
		$ak  = (string) ( $s['crypto_nowpayments_api_key'] ?? '' );
		$t   = "₿ کریپتو (NOWPayments)\n➖\n";
		$t  .= 'API key: ' . ( '' !== $ak ? '✓ (' . strlen( $ak ) . ')' : '—' ) . "\n";
		$t  .= 'IPN: ' . ( '' !== $ipn ? $ipn : '—' ) . "\n";
		$t  .= 'pay_currency: ' . (string) ( $s['crypto_nowpayments_pay_currency'] ?? '' );
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t, array( 'reply_markup' => SimpleVPBot_Keyboards::admin_crypto_submenu_reply( null ) ) );
	}

	/**
	 * Map w|f|i to admin_create_service mode.
	 *
	 * @param string $letter w|f|i.
	 * @return string wallet|free|invoice or ''.
	 */
	private static function admin_create_service_mode_from_letter( $letter ) {
		$l = strtolower( (string) $letter );
		if ( 'w' === $l ) {
			return 'wallet';
		}
		if ( 'f' === $l ) {
			return 'free';
		}
		if ( 'i' === $l ) {
			return 'invoice';
		}
		return '';
	}

	/**
	 * Inline rows: payment mode for admin create service (used from Hub + Handler_Admin).
	 *
	 * @param int      $target_uid svp_users.id.
	 * @param int      $plan_id    Plan id.
	 * @param int|null $volume_gb  null = fixed plan.
	 * @return array<int, array<int, array<string, string>>>
	 */
	public static function admin_create_service_mode_keyboard( $target_uid, $plan_id, $volume_gb ) {
		$t = (int) $target_uid;
		$p = (int) $plan_id;
		$v = null === $volume_gb ? '' : (string) (int) $volume_gb;
		$rows = array();
		if ( '' === $v ) {
			foreach ( array( 'w' => '💳 کیف پول', 'f' => '🎁 رایگان', 'i' => '🧾 فاکتور' ) as $k => $lab ) {
				$cb = 'adm:nsx:' . $t . ':' . $p . ':' . $k;
				if ( strlen( $cb ) <= 64 ) {
					$rows[] = array(
						array(
							'text'          => SimpleVPBot_Keyboards::glass_button_text( $lab, 64 ),
							'callback_data' => $cb,
						),
					);
				}
			}
			return $rows;
		}
		foreach ( array( 'w' => '💳 کیف پول', 'f' => '🎁 رایگان', 'i' => '🧾 فاکتور' ) as $k => $lab ) {
			$cb = 'adm:nsm:' . $t . ':' . $p . ':' . $v . ':' . $k;
			if ( strlen( $cb ) <= 64 ) {
				$rows[] = array(
					array(
						'text'          => SimpleVPBot_Keyboards::glass_button_text( $lab, 64 ),
						'callback_data' => $cb,
					),
				);
			}
		}
		return $rows;
	}

	/**
	 * Inline rows: payment mode for admin renew / add volume / add user slots (labels match create-service flow).
	 *
	 * @param string   $kind       renew|vol|slots.
	 * @param int      $service_id Service id.
	 * @param int|null $extra      GB for vol, slot count for slots, omit for renew.
	 * @return array<int, array<int, array<string, string>>>
	 */
	public static function admin_service_payment_mode_inline_rows( $kind, $service_id, $extra = null ) {
		$sid = (int) $service_id;
		$rows = array();
		foreach ( array( 'w' => '💳 کیف پول', 'f' => '🎁 رایگان', 'i' => '🧾 فاکتور' ) as $k => $lab ) {
			if ( 'renew' === $kind ) {
				$cb = 'adm:nrr:' . $sid . ':' . $k;
			} elseif ( 'vol' === $kind ) {
				$cb = 'adm:nva:' . $sid . ':' . (int) $extra . ':' . $k;
			} else {
				$cb = 'adm:nus:' . $sid . ':' . (int) $extra . ':' . $k;
			}
			if ( strlen( $cb ) <= 64 ) {
				$rows[] = array(
					array(
						'text'          => SimpleVPBot_Keyboards::glass_button_text( $lab, 64 ),
						'callback_data' => $cb,
					),
				);
			}
		}
		return $rows;
	}

	/**
	 * Execute adm:nrr / adm:nva / adm:nus after admin picked payment mode.
	 *
	 * @param array<string, mixed> $ctx          Callback context.
	 * @param string               $kind         renew|vol|slots.
	 * @param int                  $service_id   Service id.
	 * @param int|null             $extra_gb_or_n Extra GB or extra users; null for renew.
	 * @param string               $letter       w|f|i.
	 */
	private static function handle_admin_service_payment_execute( array $ctx, $kind, $service_id, $extra_gb_or_n, $letter ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$mode     = self::admin_create_service_mode_from_letter( $letter );
		if ( '' === $mode || ! class_exists( 'SimpleVPBot_Admin_User_Ops' ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.method_invalid', $platform, $chat_id ) );
			return;
		}
		$sid = (int) $service_id;
		if ( $sid < 1 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.service_invalid', $platform, $chat_id ) );
			return;
		}
		if ( 'renew' === $kind ) {
			$r = SimpleVPBot_Admin_User_Ops::admin_renew_service( $sid, $mode );
		} elseif ( 'vol' === $kind ) {
			$g = null === $extra_gb_or_n ? 0 : (int) $extra_gb_or_n;
			$r = SimpleVPBot_Admin_User_Ops::admin_add_volume( $sid, $g, $mode );
		} else {
			$n = null === $extra_gb_or_n ? 0 : (int) $extra_gb_or_n;
			$r = SimpleVPBot_Admin_User_Ops::admin_add_user_slots( $sid, $n, $mode );
		}
		if ( ! empty( $r['ok'] ) ) {
			$msg = isset( $r['transaction_id'] )
				? '✅ فاکتور ارسال شد (سفارش #' . (int) $r['transaction_id'] . ').'
				: '✅ انجام شد.';
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $msg );
			return;
		}
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.error_generic', $platform, $chat_id, array( 'reason' => (string) ( $r['reason'] ?? 'خطا' ) ) ) );
	}

	/**
	 * Step 1: pick plan for target user (admin create service).
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat.
	 * @param int    $target_uid svp_users.id.
	 */
	private static function send_admin_create_service_plan_picker( $platform, $chat_id, $target_uid ) {
		$tuid = (int) $target_uid;
		if ( $tuid < 1 || ! SimpleVPBot_Model_User::find( $tuid ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.target_user_not_found', $platform, $chat_id ) );
			return;
		}
		if ( ! class_exists( 'SimpleVPBot_Admin_User_Ops' ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.ops_unavailable', $platform, $chat_id ) );
			return;
		}
		$plans = SimpleVPBot_Model_Plan::all_active();
		if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) ) {
			$plans = SimpleVPBot_Feature_L2tp::filter_plans( (array) $plans );
		}
		if ( empty( $plans ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.no_active_plans', $platform, $chat_id ) );
			return;
		}
		$tuid_fa = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $tuid );
		$intro   = array();
		$intro[] = '➕ ساخت سرویس برای کاربر #' . $tuid_fa;
		$intro[] = 'پلن را از دکمه‌های زیر انتخاب کنید. برای لغو /cancel بفرستید.';
		$rows = array();
		foreach ( $plans as $pl ) {
			if ( ! $pl || ! (int) $pl->active ) {
				continue;
			}
			if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) && ! SimpleVPBot_Feature_L2tp::plan_visible( $pl ) ) {
				continue;
			}
			$pid = (int) $pl->id;
			$cb  = 'adm:nsp:' . $tuid . ':' . $pid;
			if ( strlen( $cb ) > 64 ) {
				continue;
			}
			$rows[] = array(
				array(
					'text'          => SimpleVPBot_Bot_Persian_Text::plan_picker_glass_button( $pl ),
					'callback_data' => $cb,
				),
			);
		}
		if ( empty( $rows ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.plan_ids_too_large', $platform, $chat_id ) );
			return;
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			implode( "\n", $intro ),
			array( 'reply_markup' => array( 'inline_keyboard' => $rows ) )
		);
	}

	/**
	 * After admin picked a plan: per-GB ask volume, else show payment mode.
	 *
	 * @param array<string, mixed> $ctx        Callback context.
	 * @param int                  $target_uid Target user.
	 * @param int                  $plan_id    Plan id.
	 */
	private static function handle_admin_create_service_plan_pick( array $ctx, $target_uid, $plan_id ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$admin    = $ctx['user'];
		$tuid     = (int) $target_uid;
		$pid      = (int) $plan_id;
		if ( $tuid < 1 || ! SimpleVPBot_Model_User::find( $tuid ) || $pid < 1 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.invalid_data', $platform, $chat_id ) );
			return;
		}
		$plan = SimpleVPBot_Model_Plan::find( $pid );
		if ( ! $plan || ! (int) $plan->active ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.plan_unavailable', $platform, $chat_id ) );
			return;
		}
		if ( SimpleVPBot_Model_Plan::is_per_gb( $plan ) ) {
			$min = (int) ( $plan->traffic_gb_min ?? 0 );
			$max = (int) ( $plan->traffic_gb_max ?? 0 );
			if ( $min < 1 || $max < 1 || $min > $max || (float) ( $plan->price_per_gb ?? 0 ) <= 0 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.plan_pergb_misconfigured', $platform, $chat_id ) );
				return;
			}
			SimpleVPBot_State::set(
				(int) $admin->id,
				'admin_ns_vol',
				array(
					'target_uid' => $tuid,
					'plan_id'    => $pid,
				)
			);
			$ppg    = SimpleVPBot_Bot_Persian_Text::format_toman_fa( (float) ( $plan->price_per_gb ?? 0 ) );
			$tuid_f = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $tuid );
			$min_f  = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $min );
			$max_f  = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $max );
			$d_fa   = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) (int) $plan->duration_days );
			$txt    = "➕ ساخت سرویس برای #{$tuid_f}\n📦 پلن: " . (string) $plan->name . "\n";
			$txt   .= '💰 ' . $ppg . ' تومان به ازای هر گیگابایت' . "\n";
			$txt   .= '⏳ مدت: ' . $d_fa . " روز\n";
			$txt   .= "۲) حجم را فقط به صورت عدد (گیگابایت) بین {$min_f} و {$max_f} بفرستید.\n/cancel برای لغو.";
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $txt );
			return;
		}
		$mk = self::admin_create_service_mode_keyboard( $tuid, $pid, null );
		if ( empty( $mk ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.internal_button_error', $platform, $chat_id ) );
			return;
		}
		$tuid_f = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $tuid );
		$txt    = "➕ ساخت سرویس برای #{$tuid_f}\n📦 پلن: " . (string) $plan->name . "\n۳) روش اعمال را انتخاب کنید:";
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$txt,
			array( 'reply_markup' => array( 'inline_keyboard' => $mk ) )
		);
	}

	/**
	 * Run admin_create_service and clear state.
	 *
	 * @param array<string, mixed> $ctx        Context.
	 * @param int                  $target_uid Target user.
	 * @param int                  $plan_id    Plan id.
	 * @param int|null             $volume_gb  null for fixed plan.
	 * @param string               $letter     w|f|i.
	 */
	private static function handle_admin_create_service_execute( array $ctx, $target_uid, $plan_id, $volume_gb, $letter ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$admin    = $ctx['user'];
		$mode     = self::admin_create_service_mode_from_letter( $letter );
		if ( '' === $mode ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.pay_method_invalid', $platform, $chat_id ) );
			return;
		}
		$plan = SimpleVPBot_Model_Plan::find( (int) $plan_id );
		if ( ! $plan || ! (int) $plan->active ) {
			SimpleVPBot_State::clear( (int) $admin->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.plan_invalid', $platform, $chat_id ) );
			return;
		}
		$vol = null;
		if ( SimpleVPBot_Model_Plan::is_per_gb( $plan ) ) {
			$g = null === $volume_gb ? 0 : (int) $volume_gb;
			if ( $g < 1 || ! SimpleVPBot_Model_Plan::is_volume_in_range( $plan, $g ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.volume_invalid_for_plan', $platform, $chat_id ) );
				return;
			}
			$vol = $g;
		} elseif ( null !== $volume_gb && (int) $volume_gb > 0 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.fixed_plan_no_volume', $platform, $chat_id ) );
			return;
		}
		if ( ! class_exists( 'SimpleVPBot_Admin_User_Ops' ) ) {
			SimpleVPBot_State::clear( (int) $admin->id );
			return;
		}
		$r = SimpleVPBot_Admin_User_Ops::admin_create_service( (int) $target_uid, (int) $plan_id, $vol, $mode );
		SimpleVPBot_State::clear( (int) $admin->id );
		if ( empty( $r['ok'] ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.error_generic', $platform, $chat_id, array( 'reason' => (string) ( $r['reason'] ?? 'خطا' ) ) ) );
			return;
		}
		if ( isset( $r['service_id'] ) ) {
			$msg = '✅ سرویس #' . SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) (int) $r['service_id'] );
		} else {
			$txid = (int) ( $r['transaction_id'] ?? 0 );
			$msg  = '✅ فاکتور ارسال شد (سفارش #' . SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $txid ) . ').';
		}
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $msg );
	}

	/**
	 * Full user row + optional Telegram profile photo for admins.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Admin chat.
	 * @param int    $uid svp_users.id.
	 */
	private static function send_user_admin_preview( $platform, $chat_id, $uid ) {
		$uid = (int) $uid;
		$u   = SimpleVPBot_Model_User::find( $uid );
		if ( ! $u ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.user_not_found', $platform, $chat_id ) );
			return;
		}
		$t  = "👤 کاربر #{$uid}\n➖➖➖➖➖➖➖➖\n";
		$t .= 'وضعیت: ' . (string) $u->status . "\n";
		$t .= 'نام: ' . trim( (string) $u->first_name . ' ' . (string) $u->last_name ) . "\n";
		$t .= 'یوزرنیم: ' . ( $u->username ? '@' . (string) $u->username : '—' ) . "\n";
		$t .= 'TG id: ' . ( $u->tg_user_id ? (string) (int) $u->tg_user_id : '—' ) . "\n";
		$t .= 'Bale id: ' . ( $u->bale_user_id ? (string) (int) $u->bale_user_id : '—' ) . "\n";
		$t .= 'تلفن: ' . (string) ( $u->phone ?? '' ) . "\n";
		$t .= 'موجودی: ' . number_format( (float) $u->balance ) . "\n";
		$t .= 'ساخته: ' . (string) $u->created_at . "\n";
		if ( 'telegram' === $platform && ! empty( $u->tg_user_id ) ) {
			$tmp = SimpleVPBot_Bot_Runtime::telegram_user_profile_photo_temp( (int) $u->tg_user_id );
			if ( '' !== $tmp && is_readable( $tmp ) ) {
				SimpleVPBot_Bot_Runtime::send_photo_file( $platform, $chat_id, $tmp, $t, array() );
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				return;
			}
		}
		if ( 'bale' === $platform ) {
			$t .= "\nℹ️ پیش‌نمایش عکس پروفایل در بله در این نسخه پشتیبانی نمی‌شود.";
		}
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t );
	}

	/**
	 * Pending users (inline glass, 5 per page, newest first).
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat.
	 * @param int    $offset Offset.
	 * @param int    $edit_msg_id Edit this message (0 = new).
	 */
	private static function send_pending_users_page( $platform, $chat_id, $offset = 0, $edit_msg_id = 0 ) {
		$off   = max( 0, (int) $offset );
		$lim   = 5;
		$total = SimpleVPBot_Model_User::count_status( 'pending' );
		$list  = SimpleVPBot_Model_User::list_by_status_paged( 'pending', $off, $lim );
		if ( empty( $list ) && 0 === $off ) {
			$ik = array(
				array(
					array(
						'text'          => SimpleVPBot_Keyboards::glass_button_text( '✅ تأییدشده', 16 ),
						'callback_data' => 'adm:aq:0',
					),
					array(
						'text'          => SimpleVPBot_Keyboards::glass_button_text( '❌ ردشده', 16 ),
						'callback_data' => 'adm:rq:0',
					),
				),
			);
			self::push_queue_message(
				$platform,
				$chat_id,
				'👥 کاربری در انتظار تایید نیست.',
				array( 'inline_keyboard' => $ik ),
				(int) $edit_msg_id
			);
			return;
		}
		if ( empty( $list ) ) {
			self::send_pending_users_page( $platform, $chat_id, 0, (int) $edit_msg_id );
			return;
		}
		$t = "👥 در انتظار تایید: {$total}\n🔎 «" . SimpleVPBot_Texts::get( 'btn.admin.users_search', '🔎 جستجوی کاربر' ) . "»\nصفحه offset {$off}\n➖\n";
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
					'callback_data' => 'adm:ui:' . $uid,
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
				'callback_data' => 'adm:pq:' . ( $off + $lim ),
			);
		}
		if ( $off > 0 ) {
			$nav[] = array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( '◀ قبلی', 16 ),
				'callback_data' => 'adm:pq:' . max( 0, $off - $lim ),
			);
		}
		if ( $nav ) {
			$ik[] = $nav;
		}
		$ik[] = array(
			array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( '✅ تأییدشده', 16 ),
				'callback_data' => 'adm:aq:0',
			),
			array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( '❌ ردشده', 16 ),
				'callback_data' => 'adm:rq:0',
			),
		);
		self::push_queue_message( $platform, $chat_id, $t, array( 'inline_keyboard' => $ik ), (int) $edit_msg_id );
	}

	/**
	 * Logs with pagination.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat.
	 * @param int    $offset Offset.
	 */
	private static function send_logs_page( $platform, $chat_id, $offset = 0 ) {
		global $wpdb;
		$lt  = $wpdb->prefix . 'svp_logs';
		$off = max( 0, (int) $offset );
		$lim = 6;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders
		$logs = $wpdb->get_results( $wpdb->prepare( "SELECT level, message, created_at FROM {$lt} ORDER BY id DESC LIMIT %d OFFSET %d", $lim, $off ) );
		$t    = "📜 لاگ (offset {$off})\n➖\n";
		if ( empty( $logs ) ) {
			$t .= 'رکوردی نیست.';
		} else {
			foreach ( $logs as $lg ) {
				$t .= '[' . (string) $lg->level . '] ' . mb_substr( (string) $lg->message, 0, 70 ) . "\n";
			}
		}
		$nav = array();
		if ( $off > 0 ) {
			$nav[] = array( 'text' => '◀ لاگ قبلی' );
		}
		if ( count( $logs ) >= $lim ) {
			$nav[] = array( 'text' => 'لاگ بعدی ▶' );
		}
		$ik = array();
		if ( $nav ) {
			$ik[] = $nav;
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$t,
			array( 'reply_markup' => $ik ? SimpleVPBot_Keyboards::admin_reply_wrap_rows( $ik ) : SimpleVPBot_Keyboards::admin_only_back_reply() )
		);
	}

	/**
	 * @param array<string, mixed> $ctx With user.
	 */
	private static function send_inbounds_list( $platform, $chat_id, $ctx ) {
		$panels = class_exists( 'SimpleVPBot_Model_Panel' ) ? SimpleVPBot_Model_Panel::all_active_ordered() : array();
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
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.panel_inactive', $platform, $chat_id ) );
				return;
			}
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				'📡 ابتدا پنل را برای لیست Inbound انتخاب کنید:',
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
	private static function send_inbounds_list_for_panel( $platform, $chat_id, $ctx, $panel_id ) {
		$panel_id = (int) $panel_id;
		if ( $panel_id < 0 ) {
			$panel_id = 0;
		}
		$user = isset( $ctx['user'] ) ? $ctx['user'] : null;
		if ( $user && ! empty( $user->id ) ) {
			set_transient( 'svp_ibctx_' . (int) $user->id, array( 'panel_id' => $panel_id ), 600 );
		}
		$r = SimpleVPBot_Service_Admin_Ops::inbounds_list( $panel_id );
		if ( empty( $r['ok'] ) || empty( $r['data']['inbounds'] ) || ! is_array( $r['data']['inbounds'] ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.inbound_list_empty', $platform, $chat_id, array( 'message' => (string) ( $r['message'] ?? 'لیست inbounds خالی' ) ) ) );
			return;
		}
		$rows = array();
		$ii   = 0;
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
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.inbound_none', $platform, $chat_id ) );
			return;
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			'📡 Inboundها — یکی را انتخاب کنید',
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $rows ) )
		);
	}

	/**
	 * @param array<string, mixed> $ctx Context with user.
	 */
	private static function send_inbound_clients( $platform, $chat_id, $inbound_id, $ctx ) {
		$user    = isset( $ctx['user'] ) ? $ctx['user'] : null;
		$iid     = (int) $inbound_id;
		$pid     = 1;
		if ( $user && ! empty( $user->id ) ) {
			$ibx = get_transient( 'svp_ibctx_' . (int) $user->id );
			if ( is_array( $ibx ) && isset( $ibx['panel_id'] ) ) {
				$pid = (int) $ibx['panel_id'];
				if ( $pid < 0 ) {
					$pid = 0;
				}
			}
		}
		$clients = SimpleVPBot_Service_Admin_Ops::inbound_clients( $iid, $pid );
		if ( empty( $clients['ok'] ) || empty( $clients['data']['clients'] ) || ! is_array( $clients['data']['clients'] ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.inbound_clients_empty', $platform, $chat_id, array( 'message' => (string) ( $clients['message'] ?? 'کلاینتی نیست' ) ) ) );
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
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.inbound_email_missing', $platform, $chat_id ) );
			return;
		}
		$rows[] = array( array( 'text' => '⚡ autolink #' . $iid ) );
		$rows[] = array( array( 'text' => '↩ لیست Inbound' ) );
		$t  = "📎 Inbound #{$iid} — لینک: svp user id بفرستید (بعد از انتخاب ایمیل)\n";
		$t .= (string) ( $clients['data']['inb_remark'] ?? '' );
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$t,
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $rows ) )
		);
	}

	/**
	 * @param array<string, mixed> $ctx With user.
	 * @param int                $idx Index in stored emails.
	 */
	private static function start_inbound_link( array $ctx, $idx ) {
		$user = isset( $ctx['user'] ) ? $ctx['user'] : null;
		if ( ! $user || ! $user->id ) {
			return;
		}
		$st = get_transient( 'svp_inbcl_' . (int) $user->id );
		if ( ! is_array( $st ) || empty( $st['iid'] ) || empty( $st['em'] ) || ! is_array( $st['em'] ) ) {
			SimpleVPBot_Bot_Runtime::send_message( (string) $ctx['platform'], (int) $ctx['chat_id'], self::admin_msg( 'msg.admin.inbound_session_expired', (string) $ctx['platform'], (int) $ctx['chat_id'] ) );
			return;
		}
		$em = (string) ( $st['em'][ (int) $idx ] ?? '' );
		if ( '' === $em ) {
			SimpleVPBot_Bot_Runtime::send_message( (string) $ctx['platform'], (int) $ctx['chat_id'], self::admin_msg( 'msg.admin.inbound_row_invalid', (string) $ctx['platform'], (int) $ctx['chat_id'] ) );
			return;
		}
		$pn = isset( $st['panel_id'] ) ? (int) $st['panel_id'] : 1;
		if ( $pn < 0 ) {
			$pn = 0;
		}
		SimpleVPBot_State::set( (int) $user->id, 'admin_inb_uid', array( 'iid' => (int) $st['iid'], 'em' => $em, 'panel_id' => $pn ) );
		SimpleVPBot_Bot_Runtime::send_message( (string) $ctx['platform'], (int) $ctx['chat_id'], self::admin_msg( 'msg.admin.inbound_link_user_prompt', (string) $ctx['platform'], (int) $ctx['chat_id'], array( 'email' => $em ) ) );
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @param array<int, string>  $parts parts.
	 */
	private static function handle_backup_callback( array $ctx, array $parts ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = isset( $ctx['user'] ) ? $ctx['user'] : null;
		$msg_id   = isset( $ctx['msg_id'] ) ? (int) $ctx['msg_id'] : 0;
		$op       = isset( $parts[2] ) ? (string) $parts[2] : '';

		$refresh_panel = function () use ( $platform, $chat_id ) {
			self::send_backup_panel( $platform, $chat_id );
		};

		switch ( $op ) {
			case 'run':
				$r = SimpleVPBot_Cron_Backup::run();
				$line = "💾 نتیجه بکاپ:\n";
				$line .= 'ساخت زیپ: ' . ( ! empty( $r['built'] ) ? 'بله' : 'خیر' ) . "\n";
				$line .= 'ارسال موفق: ' . (int) ( $r['sent'] ?? 0 ) . "\n";
				$line .= 'ارسال ناموفق: ' . (int) ( $r['failed'] ?? 0 );
				if ( ! empty( $r['zip'] ) ) {
					$line .= "\nفایل: " . (string) $r['zip'];
				}
				if ( ! empty( $r['built'] ) && 0 === (int) ( $r['sent'] ?? 0 ) && 0 === (int) ( $r['failed'] ?? 0 ) ) {
					$line .= "\n\nℹ️ اگر مقصدها را روشن کرده‌اید ولی ارسالی نیست: در تنظیمات عمومی شناسهٔ ادمین‌های تلگرام/بله را پر کنید؛ برای کانال، chat id تلگرام/بله را در بکاپ (داشبورد یا ربات) ذخیره کنید و ربات را ادمین کانال کنید.";
				}
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $line );
				$refresh_panel();
				return;
			case 'sw':
				$code = isset( $parts[3] ) ? (string) $parts[3] : '';
				$map  = array(
					'tga' => 'backup_send_telegram_admins',
					'bla' => 'backup_send_bale_admins',
					'tgc' => 'backup_send_telegram_channel',
					'blc' => 'backup_send_bale_channel',
				);
				if ( isset( $map[ $code ] ) ) {
					SimpleVPBot_Admin_Actions::toggle_backup_send_key( $map[ $code ] );
					$refresh_panel();
				}
				return;
			case 'int':
				if ( $user ) {
					SimpleVPBot_State::set( (int) $user->id, 'admin_bak_interval', array() );
					SimpleVPBot_Bot_Runtime::send_message(
						$platform,
						$chat_id,
						'⏱ تعداد دقیقه (حداقل ۵) را عدد ارسال کنید. /cancel'
					);
				}
				return;
			case 'xtg':
				if ( $user ) {
					SimpleVPBot_State::set( (int) $user->id, 'admin_bak_tg_chat', array() );
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.prompt_tg_chat_id', $platform, $chat_id ) );
				}
				return;
			case 'xbl':
				if ( $user ) {
					SimpleVPBot_State::set( (int) $user->id, 'admin_bak_bl_chat', array() );
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.prompt_bale_chat_id', $platform, $chat_id ) );
				}
				return;
			case 'r1':
				$r1rows = array(
					array(
						array( 'text' => '✅ ادامهٔ ریستور' ),
						array( 'text' => '❌ لغو ریستور' ),
					),
				);
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					"⚠️ ریستور، جداول پلاگین svp_* و گزینه‌های پلاگین SimpleVPBot را از فایل زیپ جایگزین می‌کند.\nفقط اگر بکاپ معتبر و آگاهانه است ادامه دهید.",
					array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $r1rows ) )
				);
				return;
			case 'r2':
				if ( $user ) {
					SimpleVPBot_State::set( (int) $user->id, 'admin_bak_restore', array() );
					$r2rows = array( array( array( 'text' => '❌ لغو ریستور' ) ) );
					SimpleVPBot_Bot_Runtime::send_message(
						$platform,
						$chat_id,
						'📎 فقط فایل .zip بکاپ SimpleVPBot را بفرستید. /cancel',
						array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $r2rows ) )
					);
				}
				return;
			case 'ca':
				if ( $user ) {
					SimpleVPBot_State::clear( (int) $user->id );
				}
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.wizard_cancelled', $platform, $chat_id ) );
				return;
		}
	}

	/**
	 * Run legacy adm:* handler from a synthetic callback string (Reply UI).
	 *
	 * @param array<string, mixed> $ctx platform, chat_id, user, from_id?.
	 * @param string               $data e.g. adm:bk:run.
	 */
	public static function dispatch_reply_as_callback( array $ctx, $data ) {
		$subctx           = $ctx;
		$subctx['parts']  = explode( ':', (string) $data );
		$subctx['msg_id'] = 0;
		self::handle( $subctx );
	}

	/**
	 * @param array<string, mixed> $s Settings.
	 * @return array<string, string>
	 */
	private static function backup_reply_label_to_callback( array $s ) {
		$sta = ! empty( $s['backup_send_telegram_admins'] ) ? '✓' : '✗';
		$sba = ! empty( $s['backup_send_bale_admins'] ) ? '✓' : '✗';
		$stc = ! empty( $s['backup_send_telegram_channel'] ) ? '✓' : '✗';
		$sbc = ! empty( $s['backup_send_bale_channel'] ) ? '✓' : '✗';
		return array(
			'▶️ بکاپ الان'         => 'adm:bk:run',
			'TG ad ' . $sta       => 'adm:bk:sw:tga',
			'Bl ad ' . $sba       => 'adm:bk:sw:bla',
			'TG ch ' . $stc       => 'adm:bk:sw:tgc',
			'Bl ch ' . $sbc       => 'adm:bk:sw:blc',
			'⏱ فاصله (دقیقه)'     => 'adm:bk:int',
			'📢 TG ch id'         => 'adm:bk:xtg',
			'💬 Bale ch id'       => 'adm:bk:xbl',
			'📥 ریستور (۲ مرحله)' => 'adm:bk:r1',
			'❌ لغو حالت'         => 'adm:bk:ca',
			'✅ ادامهٔ ریستور'    => 'adm:bk:r2',
			'❌ لغو ریستور'       => 'adm:bk:ca',
		);
	}

	/**
	 * Admin Reply routes (admin_mode). Return true if handled.
	 *
	 * @param array<string, mixed> $ctx platform, chat_id, user, text, from_id?.
	 */
	public static function route_menu_text( array $ctx ): bool {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = isset( $ctx['user'] ) ? $ctx['user'] : null;
		$text     = trim( (string) $ctx['text'] );
		$from_id = isset( $ctx['from_id'] ) ? (int) $ctx['from_id'] : 0;
		if ( ! $from_id && ! empty( $ctx['from'] ) && is_array( $ctx['from'] ) ) {
			$from_id = (int) ( $ctx['from']['id'] ?? 0 );
		}
		if ( '' === $text || ! $user ) {
			return false;
		}
		if ( $text === SimpleVPBot_Keyboards::admin_back_main_label() ) {
			self::send_hub( $platform, $chat_id );
			return true;
		}
		$pt = SimpleVPBot_Texts::get( 'btn.admin.send_my_portal', '🌐 ارسال لینک پنل وب من' );
		if ( $text === $pt ) {
			$me = ( 'bale' === $platform ) ? SimpleVPBot_Model_User::find_by_bale( $chat_id ) : SimpleVPBot_Model_User::find_by_telegram( $chat_id );
			if ( $me && (int) $me->id > 0 ) {
				$u = SimpleVPBot_Portal_Link::build_url( (int) $me->id );
				if ( '' !== $u ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '🌐 ' . $u );
					return true;
				}
			}
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.portal_link_unset', $platform, $chat_id ) );
			return true;
		}
		$at = SimpleVPBot_Texts::get( 'btn.admin.send_admin_portal', '🖥 ارسال لینک پنل ادمین وب' );
		if ( $text === $at ) {
			$me = ( 'bale' === $platform ) ? SimpleVPBot_Model_User::find_by_bale( $chat_id ) : SimpleVPBot_Model_User::find_by_telegram( $chat_id );
			if ( $me && (int) $me->id > 0 ) {
				$u = SimpleVPBot_Portal_Link::build_admin_url( (int) $me->id );
				if ( '' !== $u ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '🖥 ' . $u );
					return true;
				}
			}
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.admin_panel_unset', $platform, $chat_id ) );
			return true;
		}
		if ( class_exists( 'SimpleVPBot_UI_Reply_Router' ) && SimpleVPBot_UI_Reply_Router::try_dispatch_hub_action( $ctx ) ) {
			return true;
		}
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) || ! SimpleVPBot_Bot_Reseller_Scope::reseller_blocks_global_settings() ) {
			$s  = SimpleVPBot_Settings::all();
			$bk = self::backup_reply_label_to_callback( $s );
			if ( isset( $bk[ $text ] ) ) {
				self::dispatch_reply_as_callback( $ctx, $bk[ $text ] );
				return true;
			}
		}
		$tn = SimpleVPBot_Keyboards::strip_glass_prefix( SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
		if ( preg_match( '/^\+(\d+) روز$/u', $tn, $m ) ) {
			self::send_bulk_days_confirm( $platform, $chat_id, (int) $m[1] );
			return true;
		}
		if ( preg_match( '/^\+(\d+) GB$/u', $tn, $m ) ) {
			self::send_bulk_gb_confirm( $platform, $chat_id, (int) $m[1] );
			return true;
		}
		if ( preg_match( '/^✅ تأیید\+(\d+) روز$/u', $tn, $m ) && class_exists( 'SimpleVPBot_Admin_User_Ops' ) ) {
			$d = max( 1, (int) $m[1] );
			$r = SimpleVPBot_Admin_User_Ops::bulk_extend_days( $d, true, 200 );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				'📊 +روز ' . $d . " (Xray)\n✅ موفق: " . (int) $r['done'] . "\n⛔ خطا: " . (int) $r['errors'],
				array( 'reply_markup' => self::reply_markup_main_for_chat( $platform, $chat_id ) )
			);
			return true;
		}
		if ( preg_match( '/^✅ تأیید\+(\d+) GB$/u', $tn, $m ) && class_exists( 'SimpleVPBot_Admin_User_Ops' ) ) {
			$g = max( 1, (int) $m[1] );
			$r = SimpleVPBot_Admin_User_Ops::bulk_add_volume( $g, 200 );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				'📊 +' . $g . " GB (Xray)\n✅ موفق: " . (int) $r['done'] . "\n⛔ خطا: " . (int) $r['errors'],
				array( 'reply_markup' => self::reply_markup_main_for_chat( $platform, $chat_id ) )
			);
			return true;
		}
		if ( '❌ لغو گروهی' === $text ) {
			self::send_hub( $platform, $chat_id );
			return true;
		}
		if ( '➕ دسته جدید' === $text ) {
			SimpleVPBot_Handler_Admin_Settings::start_catalog_wizard( $ctx, 'pc' );
			return true;
		}
		if ( '➕ پلن جدید (Xray)' === $text ) {
			SimpleVPBot_Handler_Admin_Settings::start_catalog_wizard( $ctx, 'pl' );
			return true;
		}
		if ( '➕ کارت جدید' === $text ) {
			SimpleVPBot_Handler_Admin_Settings::start_catalog_wizard( $ctx, 'cd' );
			return true;
		}
		if ( '➕ سرور جدید (خطی)' === $text ) {
			SimpleVPBot_Handler_Admin_Settings::start_catalog_wizard( $ctx, 'l2' );
			return true;
		}
		if ( preg_match( '/^🗑 دسته (\d+)$/u', $tn, $m ) ) {
			self::dispatch_reply_as_callback( $ctx, 'adm:dl:pc:' . (int) $m[1] );
			return true;
		}
		if ( preg_match( '/^🗑 پلن (\d+)$/u', $tn, $m ) ) {
			self::dispatch_reply_as_callback( $ctx, 'adm:dl:pl:' . (int) $m[1] );
			return true;
		}
		if ( preg_match( '/^🗑 کارت (\d+)$/u', $tn, $m ) ) {
			self::dispatch_reply_as_callback( $ctx, 'adm:dl:cd:' . (int) $m[1] );
			return true;
		}
		if ( preg_match( '/^([✓✗])\s+#(\d+)\s+/u', $text, $m ) ) {
			$id = (int) $m[2];
			if ( SimpleVPBot_Model_Plan_Category::find( $id ) ) {
				self::dispatch_reply_as_callback( $ctx, 'adm:pc:a:' . $id );
				return true;
			}
			if ( SimpleVPBot_Model_Plan::find( $id ) ) {
				self::dispatch_reply_as_callback( $ctx, 'adm:pl:a:' . $id );
				return true;
			}
			if ( SimpleVPBot_Model_Card::find( $id ) ) {
				self::dispatch_reply_as_callback( $ctx, 'adm:cd:a:' . $id );
				return true;
			}
		}
		if ( preg_match( '/^✅ کاربر (\d+)$/u', $tn, $m ) ) {
			self::dispatch_reply_as_callback( $ctx, 'adm:pe:a:' . (int) $m[1] );
			return true;
		}
		if ( preg_match( '/^❌ کاربر (\d+)$/u', $tn, $m ) ) {
			self::dispatch_reply_as_callback( $ctx, 'adm:pe:r:' . (int) $m[1] );
			return true;
		}
		if ( '✅ لیست تأییدشده‌ها' === $text ) {
			self::send_approved_users_page( $platform, $chat_id, 0, 0 );
			return true;
		}
		if ( 'تأییدشده بعدی ▶' === $text ) {
			self::send_approved_users_page( $platform, $chat_id, 5, 0 );
			return true;
		}
		if ( '◀ تأییدشده قبلی' === $text ) {
			self::send_approved_users_page( $platform, $chat_id, 0, 0 );
			return true;
		}
		if ( 'انتظار بعدی ▶' === $text ) {
			self::send_pending_users_page( $platform, $chat_id, 5, 0 );
			return true;
		}
		if ( '◀ انتظار قبلی' === $text ) {
			self::send_pending_users_page( $platform, $chat_id, 0, 0 );
			return true;
		}
		if ( 'لاگ بعدی ▶' === $text ) {
			self::send_logs_page( $platform, $chat_id, 6 );
			return true;
		}
		if ( '◀ لاگ قبلی' === $text ) {
			self::send_logs_page( $platform, $chat_id, 0 );
			return true;
		}
		if ( 'متن بعدی ▶' === $text && $user ) {
			$d = SimpleVPBot_State::data( $user );
			$off = isset( $d['off'] ) ? (int) $d['off'] + 8 : 8;
			self::send_text_keys_page( $platform, $chat_id, $off, $user );
			return true;
		}
		if ( '◀ متن قبلی' === $text && $user ) {
			$d   = SimpleVPBot_State::data( $user );
			$off = max( 0, ( isset( $d['off'] ) ? (int) $d['off'] : 8 ) - 8 );
			self::send_text_keys_page( $platform, $chat_id, $off, $user );
			return true;
		}
		$reset_all_texts = '🔄 همه به پیش‌فرض';
		if ( $user && ( $reset_all_texts === $text || $reset_all_texts === $tn ) ) {
			if ( ! SimpleVPBot_Router::is_platform_admin( $platform, $from_id ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.texts_reset_denied', $platform, $chat_id ) );
				return true;
			}
			SimpleVPBot_Activator::reset_texts_to_defaults();
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.texts_reset_ok', $platform, $chat_id ) );
			self::send_text_keys_page( $platform, $chat_id, 0, $user );
			return true;
		}
		if ( preg_match( '/^👁 ([a-f0-9]{8})\s/u', $text, $m ) || preg_match( '/^👁 ([a-f0-9]{8})\s/u', $tn, $m ) ) {
			self::dispatch_reply_as_callback( $ctx, 'adm:tv:' . $m[1] );
			return true;
		}
		if ( preg_match( '/^✏ ([a-f0-9]{8})$/u', $text, $m ) || preg_match( '/^✏ ([a-f0-9]{8})$/u', $tn, $m ) ) {
			self::dispatch_reply_as_callback( $ctx, 'adm:th:' . $m[1] );
			return true;
		}
		if ( preg_match( '/^📡 پنل #(\d+)/u', $tn, $m ) ) {
			self::send_inbounds_list_for_panel( $platform, $chat_id, $ctx, (int) $m[1] );
			return true;
		}
		if ( preg_match( '/^📌 Inbound #(\d+)/u', $tn, $m ) ) {
			self::send_inbound_clients( $platform, $chat_id, (int) $m[1], $ctx );
			return true;
		}
		if ( preg_match( '/^📧(\d+)·/u', $tn, $m ) ) {
			self::start_inbound_link( $ctx, (int) $m[1] );
			return true;
		}
		if ( preg_match( '/^⚡ autolink #(\d+)$/u', $tn, $m ) ) {
			self::dispatch_reply_as_callback( $ctx, 'adm:ib:k:' . (int) $m[1] );
			return true;
		}
		if ( '↩ لیست Inbound' === $text ) {
			self::dispatch_reply_as_callback( $ctx, 'adm:ib:l' );
			return true;
		}
		if ( preg_match( '/^L2 تست (\d+)$/u', $tn, $m ) ) {
			self::dispatch_reply_as_callback( $ctx, 'adm:ll:' . (int) $m[1] );
			return true;
		}
		if ( preg_match( '/^L2 سوییچ (\d+)$/u', $tn, $m ) ) {
			self::dispatch_reply_as_callback( $ctx, 'adm:l2:g:' . (int) $m[1] );
			return true;
		}
		if ( preg_match( '/^L2 حذف (\d+)$/u', $tn, $m ) ) {
			self::dispatch_reply_as_callback( $ctx, 'adm:l2:d:' . (int) $m[1] );
			return true;
		}
		if ( preg_match( '/^🌐 لینک پورتال کاربر #(\d+)$/u', $tn, $m ) ) {
			$url = SimpleVPBot_Portal_Link::build_url( (int) $m[1] );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '' !== $url ? $url : self::admin_msg( 'msg.admin.link_empty', $platform, $chat_id ) );
			return true;
		}
		if ( preg_match( '/^⛔ بلاک #(\d+)$/u', $tn, $m ) ) {
			self::dispatch_reply_as_callback( $ctx, 'adm:blk:' . (int) $m[1] );
			return true;
		}
		if ( preg_match( '/^✅ آنبلاک #(\d+)$/u', $tn, $m ) ) {
			self::dispatch_reply_as_callback( $ctx, 'adm:ub:' . (int) $m[1] );
			return true;
		}
		if ( preg_match( '/^➕ ساخت سرویس برای #(\d+)$/u', $tn, $m ) ) {
			self::dispatch_reply_as_callback( $ctx, 'adm:hcs:' . (int) $m[1] );
			return true;
		}
		if ( preg_match( '/^♻️ تمدید سرویس #(\d+)$/u', $tn, $m ) ) {
			self::dispatch_reply_as_callback( $ctx, 'adm:ar:' . (int) $m[1] );
			return true;
		}
		if ( preg_match( '/^➕ حجم سرویس #(\d+)$/u', $tn, $m ) ) {
			self::dispatch_reply_as_callback( $ctx, 'adm:av:' . (int) $m[1] );
			return true;
		}
		if ( preg_match( '/^🖥 جزئیات #(\d+)$/u', $tn, $m ) ) {
			$sid = (int) $m[1];
			$svc = SimpleVPBot_Model_Service::find( $sid );
			if ( $svc ) {
				$owner = SimpleVPBot_Model_User::find( (int) $svc->user_id );
				if ( $owner ) {
					SimpleVPBot_Handler_Service::handle_callback(
						array(
							'platform' => $platform,
							'user'     => $owner,
							'action'   => 'm',
							'svc_id'   => $sid,
							'chat_id'  => $chat_id,
							'msg_id'   => 0,
							'from_id'  => $from_id,
						)
					);
				}
			}
			return true;
		}
		if ( preg_match( '/^📊 مصرف #(\d+)$/u', $tn, $m ) ) {
			$sid = (int) $m[1];
			$svc = SimpleVPBot_Model_Service::find( $sid );
			if ( $svc ) {
				$owner = SimpleVPBot_Model_User::find( (int) $svc->user_id );
				if ( $owner ) {
					SimpleVPBot_Handler_Service::handle_callback(
						array(
							'platform' => $platform,
							'user'     => $owner,
							'action'   => 'us',
							'svc_id'   => $sid,
							'chat_id'  => $chat_id,
							'msg_id'   => 0,
							'from_id'  => $from_id,
						)
					);
				}
			}
			return true;
		}
		if ( preg_match( '/^🔗 کانفیگ #(\d+)$/u', $tn, $m ) ) {
			$sid = (int) $m[1];
			$svc = SimpleVPBot_Model_Service::find( $sid );
			if ( $svc ) {
				$owner = SimpleVPBot_Model_User::find( (int) $svc->user_id );
				if ( $owner ) {
					SimpleVPBot_Handler_Service::handle_callback(
						array(
							'platform' => $platform,
							'user'     => $owner,
							'action'   => 'l',
							'svc_id'   => $sid,
							'chat_id'  => $chat_id,
							'msg_id'   => 0,
							'from_id'  => $from_id,
						)
					);
				}
			}
			return true;
		}
		if ( preg_match( '/^🔑 کلید #(\d+)$/u', $tn, $m ) ) {
			$sid = (int) $m[1];
			$svc = SimpleVPBot_Model_Service::find( $sid );
			if ( $svc ) {
				$owner = SimpleVPBot_Model_User::find( (int) $svc->user_id );
				if ( $owner ) {
					SimpleVPBot_Handler_Service::handle_callback(
						array(
							'platform' => $platform,
							'user'     => $owner,
							'action'   => 'k',
							'svc_id'   => $sid,
							'chat_id'  => $chat_id,
							'msg_id'   => 0,
							'from_id'  => $from_id,
						)
					);
				}
			}
			return true;
		}
		if ( preg_match( '/^🔄 سرورها #(\d+)$/u', $tn, $m ) ) {
			$sid = (int) $m[1];
			$svc = SimpleVPBot_Model_Service::find( $sid );
			if ( $svc ) {
				$owner = SimpleVPBot_Model_User::find( (int) $svc->user_id );
				if ( $owner ) {
					SimpleVPBot_Handler_Service::handle_callback(
						array(
							'platform' => $platform,
							'user'     => $owner,
							'action'   => 'u',
							'svc_id'   => $sid,
							'chat_id'  => $chat_id,
							'msg_id'   => 0,
							'from_id'  => $from_id,
						)
					);
				}
			}
			return true;
		}
		if ( preg_match( '/^✏️ نام #(\d+)$/u', $tn, $m ) ) {
			$act = 'rn';
		} elseif ( preg_match( '/^📝 یادداشت #(\d+)$/u', $tn, $m ) ) {
			$act = 'n';
		} else {
			$act = '';
		}
		if ( '' !== $act && isset( $m[1] ) ) {
			$sid = (int) $m[1];
			$svc = SimpleVPBot_Model_Service::find( $sid );
			if ( $svc ) {
				$owner = SimpleVPBot_Model_User::find( (int) $svc->user_id );
				if ( $owner ) {
					SimpleVPBot_Handler_Service::handle_callback(
						array(
							'platform' => $platform,
							'user'     => $owner,
							'action'   => $act,
							'svc_id'   => $sid,
							'chat_id'  => $chat_id,
							'msg_id'   => 0,
							'from_id'  => $from_id,
						)
					);
				}
			}
			return true;
		}
		if ( preg_match( '/^🔔 هشدار #(\d+)$/u', $tn, $m ) ) {
			$sid = (int) $m[1];
			$svc = SimpleVPBot_Model_Service::find( $sid );
			if ( $svc ) {
				$owner = SimpleVPBot_Model_User::find( (int) $svc->user_id );
				if ( $owner ) {
					SimpleVPBot_Handler_Service::handle_callback(
						array(
							'platform' => $platform,
							'user'     => $owner,
							'action'   => 'al',
							'svc_id'   => $sid,
							'chat_id'  => $chat_id,
							'msg_id'   => 0,
							'from_id'  => $from_id,
						)
					);
				}
			}
			return true;
		}
		if ( preg_match( '/^🎁 انتقال سرویس #(\d+)$/u', $tn, $m ) ) {
			self::dispatch_reply_as_callback( $ctx, 'adm:stx:' . (int) $m[1] );
			return true;
		}
		if ( preg_match( '/^📡 سرویس #(\d+)/u', $tn, $m ) ) {
			$sid = (int) $m[1];
			$svc = SimpleVPBot_Model_Service::find( $sid );
			if ( $svc ) {
				$owner = SimpleVPBot_Model_User::find( (int) $svc->user_id );
				if ( $owner ) {
					SimpleVPBot_Handler_Service::handle_callback(
						array(
							'platform' => $platform,
							'user'     => $owner,
							'action'   => 'm',
							'svc_id'   => $sid,
							'chat_id'  => $chat_id,
							'msg_id'   => 0,
							'from_id'  => $from_id,
						)
					);
				}
			}
			return true;
		}
		if ( preg_match( '/^👤 pick (\d+)$/u', $tn, $m ) ) {
			self::send_user_admin_card( $platform, $chat_id, (int) $m[1] );
			return true;
		}
		return false;
	}

	/**
	 * Registration / receipt Reply buttons for platform admins (any mode).
	 *
	 * @param string               $platform Platform.
	 * @param int                  $chat_id Chat.
	 * @param int                  $from_id Platform user id.
	 * @param object               $user svp user row.
	 * @param string               $text Message text.
	 * @param array<string, mixed> $from Telegram from array.
	 * @return bool Handled.
	 */
	public static function route_moderation_reply_text( $platform, $chat_id, $from_id, $user, $text, array $from ) {
		if ( ! SimpleVPBot_Router::is_platform_admin( $platform, $from_id ) ) {
			return false;
		}
		$tn = SimpleVPBot_Bot_Runtime::normalize_digits( SimpleVPBot_Keyboards::strip_glass_prefix( trim( (string) $text ) ) );
		if ( preg_match( '/^✅ ثبت‌نام #(\d+)$/u', $tn, $m ) ) {
			SimpleVPBot_Handler_Callback::admin_apply_registration( $platform, 'a', (int) $m[1], $from, $chat_id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.signup_processed', $platform, $chat_id ) );
			return true;
		}
		if ( preg_match( '/^❌ رد ثبت‌نام #(\d+)$/u', $tn, $m ) ) {
			SimpleVPBot_Handler_Callback::admin_apply_registration( $platform, 'r', (int) $m[1], $from, $chat_id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.signup_rejected_recorded', $platform, $chat_id ) );
			return true;
		}
		if ( preg_match( '/^✅ رسید (\d+)$/u', $tn, $m ) ) {
			SimpleVPBot_Receipt_Processor::approve( (int) $m[1], self::moderation_admin_label( $from ) );
			return true;
		}
		if ( preg_match( '/^❌ رد رسید (\d+)$/u', $tn, $m ) ) {
			SimpleVPBot_Handler_Callback::show_receipt_reject_reasons(
				$platform,
				(int) $m[1],
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
	 * Admin display label from Telegram/Bale from array.
	 *
	 * @param array<string, mixed> $from From payload.
	 * @return string
	 */
	private static function moderation_admin_label( array $from ) {
		$uname = (string) ( $from['username'] ?? '' );
		return $uname ? '@' . $uname : (string) ( $from['first_name'] ?? '' );
	}
}
