<?php
/**
 * Admin reply-keyboard routes (minimal full set).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Admin
 */
class SimpleVPBot_Handler_Admin {

	/**
	 * Admin: document (e.g. restore zip). Call before route_text when a document is present.
	 *
	 * @param array<string, mixed> $ctx message, user, platform, chat_id.
	 * @return bool True if handled (restore flow or bad zip with active state).
	 */
	public static function route_message( array $ctx ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = $ctx['user'];
		$message  = isset( $ctx['message'] ) && is_array( $ctx['message'] ) ? $ctx['message'] : array();
		if ( empty( $message['document'] ) || ! is_array( $message['document'] ) ) {
			return false;
		}
		$st = (string) $user->state;
		if ( 'admin_bak_restore' !== $st ) {
			return false;
		}
		$doc     = $message['document'];
		$file_id = (string) ( $doc['file_id'] ?? '' );
		$fname   = isset( $doc['file_name'] ) ? (string) $doc['file_name'] : '';
		$mime    = isset( $doc['mime_type'] ) ? (string) $doc['mime_type'] : '';
		if ( '' === $file_id ) {
			return false;
		}
		$ok_zip = ( preg_match( '/\.zip$/i', $fname ) || 'application/zip' === $mime || 'application/x-zip-compressed' === $mime );
		if ( ! $ok_zip ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ فقط فایل .zip بکاپ SimpleVPBot.' );
			return true;
		}
		$dir  = SimpleVPBot_Backup_Export::base_tmp_dir();
		$dest = $dir . 'restore-' . wp_generate_password( 8, false ) . '.zip';
		$down = SimpleVPBot_Bot_Runtime::download_bot_file_to_path( $platform, $file_id, $dest );
		if ( is_wp_error( $down ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ دانلود فایل ناموفق: ' . $down->get_error_message() );
			return true;
		}
		$res = SimpleVPBot_Backup_Restore::restore_from_zip_path( $dest );
		@unlink( $dest );
		SimpleVPBot_State::clear( (int) $user->id );
		if ( is_wp_error( $res ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ ریستور ناموفق: ' . $res->get_error_message() );
		} else {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '✅ ریستور انجام شد.' );
		}
		return true;
	}

	/**
	 * Route admin menu texts.
	 *
	 * @param array<string, mixed> $ctx Context. Optional: message (full message array).
	 */
	public static function route_text( array $ctx ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = $ctx['user'];
		$text     = trim( (string) $ctx['text'] );
		$raw_msg  = isset( $ctx['message'] ) && is_array( $ctx['message'] ) ? $ctx['message'] : null;
		$from     = isset( $ctx['from'] ) && is_array( $ctx['from'] ) ? $ctx['from'] : array();
		$from_id  = (int) ( $from['id'] ?? 0 );

		if ( '' !== $text && $text === SimpleVPBot_Texts::get( 'btn.admin.exit', '🚪 خروج از پنل مدیریت' ) ) {
			SimpleVPBot_Model_User::update( (int) $user->id, array( 'admin_mode' => 0 ) );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				'👋 به منوی کاربر بازگشتید.',
				array( 'reply_markup' => SimpleVPBot_Keyboards::user_main_reply() )
			);
			return;
		}
		if ( $text === SimpleVPBot_Texts::get( 'btn.admin.dashboard', '📊 آمار' ) ) {
			if ( class_exists( 'SimpleVPBot_Admin_Dashboard_Stats' ) ) {
				$body = SimpleVPBot_Admin_Dashboard_Stats::format_text( 0 );
				$mk   = SimpleVPBot_Admin_Dashboard_Stats::inline_day_picker( 0 );
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					$body,
					array( 'reply_markup' => $mk )
				);
			} else {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '📊 آمار در دسترس نیست.' );
			}
			return;
		}
		if ( $text === SimpleVPBot_Texts::get( 'btn.admin.users', '👥 مدیریت کاربران' ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				'👥 مدیریت کاربران — یکی را انتخاب کنید:',
				array( 'reply_markup' => SimpleVPBot_Keyboards::admin_users_submenu_reply() )
			);
			return;
		}
		if ( $text === SimpleVPBot_Texts::get( 'btn.admin.finance', '💰 مالی' ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				'💰 مالی — یکی را انتخاب کنید:',
				array( 'reply_markup' => SimpleVPBot_Keyboards::admin_finance_submenu_reply() )
			);
			return;
		}
		if ( $text === SimpleVPBot_Texts::get( 'btn.admin.users_search', '🔎 جستجوی کاربر' ) ) {
			SimpleVPBot_State::set( (int) $user->id, 'admin_find_user', array() );
			$find_prompt = SimpleVPBot_Texts::get(
				'msg.admin_find_user_prompt',
				"🔎 جستجوی کاربر\nشناسهٔ داخلی در ربات (عدد)، chat id تلگرام یا بله، @username، یا نام / بخشی از شمارهٔ تلفن را ارسال کنید."
			);
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $find_prompt );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get( 'btn.admin.users_queue', '📋 صف ثبت‌نام' ) ) {
			SimpleVPBot_Handler_Admin_Hub::send_submenu( $platform, $chat_id, 'usr', array( 'user' => $user ) );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get( 'btn.admin.full_hub', '🧩 پنل کامل' ) ) {
			SimpleVPBot_Handler_Admin_Hub::send_hub( $platform, $chat_id );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get( 'btn.admin.transfer', '🎁 انتقال سرویس' ) ) {
			SimpleVPBot_State::set( (int) $user->id, 'admin_find_service_to_transfer', array() );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '🆔 شناسه سرویس (svp_services.id) را ارسال کنید:' );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get( 'btn.admin.broadcast', '📣 پیام همگانی' ) ) {
			SimpleVPBot_State::set( (int) $user->id, 'admin_broadcast', array() );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '📣 متن پیام همگانی را ارسال کنید:' );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get( 'btn.admin.receipts', '🧾 تایید رسیدها' ) ) {
			SimpleVPBot_Handler_Admin_Hub::send_submenu( $platform, $chat_id, 'rcp', array( 'user' => $user ) );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get( 'btn.admin.backup', '💾 پشتیبان‌گیری' ) || '💾 بکاپ' === $text ) {
			SimpleVPBot_Handler_Admin_Hub::send_backup_panel( $platform, $chat_id );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get( 'btn.admin.settings', '⚙️ تنظیمات' ) || '⚙️ تنظیمات ربات' === $text ) {
			SimpleVPBot_Handler_Admin_Hub::send_submenu( $platform, $chat_id, 'set', array( 'user' => $user ) );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get( 'btn.admin.advanced', '🔧 تنظیمات پیشرفته' ) ) {
			SimpleVPBot_Handler_Admin_Hub::send_submenu( $platform, $chat_id, 'adv', array( 'user' => $user ) );
			return;
		}
		if ( '➕ گروهی' === $text ) {
			SimpleVPBot_Handler_Admin_Hub::send_submenu( $platform, $chat_id, 'blk', array( 'user' => $user ) );
			return;
		}

		$st   = (string) $user->state;
		$ntex = SimpleVPBot_Bot_Runtime::normalize_digits( $text );

		if ( preg_match( '/^\/cancel(?:@\w+)?/i', $text ) || 'لغو' === $text ) {
			$can_cancel = in_array( $st, array( 'admin_bak_interval', 'admin_bak_tg_chat', 'admin_bak_bl_chat', 'admin_bak_restore', 'admin_find_user', 'admin_dm', 'admin_w_balance' ), true )
				|| ( class_exists( 'SimpleVPBot_Handler_Admin_Settings' ) && SimpleVPBot_Handler_Admin_Settings::is_cancelable_settings_state( $st ) )
				|| ( 0 === strpos( $st, 'admin_line_' ) )
				|| ( 0 === strpos( $st, 'admin_ns_' ) );
			if ( $can_cancel ) {
				SimpleVPBot_State::clear( (int) $user->id );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, 'ℹ️ لغو شد.' );
				return;
			}
		}

		$text_ctx            = $ctx;
		$text_ctx['from']    = $from;
		$text_ctx['from_id'] = $from_id;
		if ( $raw_msg && ( empty( $text_ctx['message'] ) || ! is_array( $text_ctx['message'] ) ) ) {
			$text_ctx['message'] = $raw_msg;
		}
		if ( class_exists( 'SimpleVPBot_Handler_Admin_Settings' ) && SimpleVPBot_Handler_Admin_Settings::route_wizard_text( $text_ctx ) ) {
			return;
		}
		if ( self::route_admin_ns_vol_state( $text_ctx ) ) {
			return;
		}
		if ( self::route_admin_line_states( $text_ctx ) ) {
			return;
		}
		if ( 'admin_bak_interval' === $st && '' !== $ntex ) {
			$trimn = trim( (string) $ntex );
			if ( is_numeric( $trimn ) ) {
				$m = max( 5, (int) $trimn );
				SimpleVPBot_Admin_Actions::patch_backup_settings( array( 'backup_interval_minutes' => $m ) );
				SimpleVPBot_State::clear( (int) $user->id );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '✅ فاصله بکاپ: ' . $m . ' دقیقه' );
				return;
			}
		}
		if ( 'admin_bak_tg_chat' === $st && '' !== $ntex ) {
			$trimn = trim( (string) $ntex );
			if ( is_numeric( $trimn ) ) {
				SimpleVPBot_Admin_Actions::patch_backup_settings( array( 'backup_telegram_chat_id' => (int) $trimn ) );
				SimpleVPBot_State::clear( (int) $user->id );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '✅ chat id تلگرام ذخیره شد: ' . (int) $trimn );
				return;
			}
		}
		if ( 'admin_bak_bl_chat' === $st && '' !== $ntex ) {
			$trimn = trim( (string) $ntex );
			if ( is_numeric( $trimn ) ) {
				SimpleVPBot_Admin_Actions::patch_backup_settings( array( 'backup_bale_chat_id' => (int) $trimn ) );
				SimpleVPBot_State::clear( (int) $user->id );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '✅ chat id بله ذخیره شد: ' . (int) $trimn );
				return;
			}
		}
		if ( 'admin_bak_restore' === $st && '' !== $text ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⏳ فقط فایل .zip بکاپ را بفرستید (/cancel — لغو).' );
			return;
		}
		if ( in_array( $st, array( 'admin_bak_interval', 'admin_bak_tg_chat', 'admin_bak_bl_chat' ), true ) && '' !== $text ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⏳ فقط یک عدد معتبر ارسال کنید یا /cancel' );
			return;
		}
		if ( $raw_msg && ! empty( $raw_msg['document'] ) && is_array( $raw_msg['document'] ) && 'admin_bak_restore' !== $st ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				'ℹ️ برای ریستور: از «🔧 تنظیمات پیشرفته» → «💾 پشتیبان‌گیری» → 📥 ریستور (۲ مرحله) اقدام کنید و سپس فایل .zip بفرستید.'
			);
			return;
		}

		if ( 'admin_find_service_to_transfer' === $st && is_numeric( $ntex ) ) {
			$sid = (int) $ntex;
			$svc = SimpleVPBot_Model_Service::find( $sid );
			if ( ! $svc ) {
				SimpleVPBot_State::clear( (int) $user->id );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ سرویس یافت نشد.' );
				return;
			}
			SimpleVPBot_State::set( (int) $user->id, 'adm_service_transfer_' . $sid, array( 'service_id' => $sid ) );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				'🎁 انتقال سرویس #' . $sid . ' (مالک فعلی: ' . (int) $svc->user_id . ")\nشناسه مقصد را ارسال کنید:\n- svp_users.id\n- یا @username\n- یا عدد chat id (تلگرام/بله)"
			);
			return;
		}
		if ( 'admin_dm' === $st && '' !== trim( (string) $text ) ) {
			$sd     = SimpleVPBot_State::data( $user );
			$tuid   = (int) ( $sd['target_user_id'] ?? 0 );
			$target = $tuid > 0 ? SimpleVPBot_Model_User::find( $tuid ) : null;
			SimpleVPBot_State::clear( (int) $user->id );
			if ( ! $target ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ کاربر مقصد نامعتبر بود.' );
				return;
			}
			$body = "📩 پیام از پشتیبانی\n➖➖➖➖➖➖➖➖\n" . (string) $text;
			if ( ! empty( $target->tg_user_id ) ) {
				SimpleVPBot_Bot_Runtime::send_message( 'telegram', (int) $target->tg_user_id, $body );
			}
			if ( ! empty( $target->bale_user_id ) ) {
				SimpleVPBot_Bot_Runtime::send_message( 'bale', (int) $target->bale_user_id, $body );
			}
			if ( empty( $target->tg_user_id ) && empty( $target->bale_user_id ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ کاربر چت تلگرام/بله ندارد.' );
				return;
			}
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '✅ پیام ارسال شد.' );
			return;
		}
		if ( 'admin_w_balance' === $st && '' !== trim( (string) $text ) ) {
			$sd    = SimpleVPBot_State::data( $user );
			$tuid  = (int) ( $sd['target_uid'] ?? 0 );
			$sign  = (int) ( $sd['sign'] ?? 1 );
			$ntex2 = SimpleVPBot_Bot_Runtime::normalize_digits( trim( (string) $text ) );
			if ( $tuid < 1 ) {
				SimpleVPBot_State::clear( (int) $user->id );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ مقصد نامعتبر بود.' );
				return;
			}
			$target = SimpleVPBot_Model_User::find( $tuid );
			if ( ! $target ) {
				SimpleVPBot_State::clear( (int) $user->id );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ کاربر یافت نشد.' );
				return;
			}
			if ( ! preg_match( '/^\d+(?:\.\d{1,2})?$/', (string) $ntex2 ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ فقط عدد تومان بفرستید (مثلاً 50000).' );
				return;
			}
			$amt = round( (float) $ntex2, 2 );
			if ( $amt <= 0 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ مبلغ باید بزرگ‌تر از صفر باشد.' );
				return;
			}
			SimpleVPBot_State::clear( (int) $user->id );
			$bal = (float) $target->balance;
			if ( $sign < 0 ) {
				if ( $bal < $amt ) {
					SimpleVPBot_Bot_Runtime::send_message(
						$platform,
						$chat_id,
						'⛔ موجودی کافی نیست. موجودی فعلی: ' . number_format( $bal ) . ' تومان.'
					);
					return;
				}
				SimpleVPBot_Model_User::update( $tuid, array( 'balance' => round( $bal - $amt, 2 ) ) );
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					'✅ ' . number_format( $amt ) . ' تومان از کیف پول کاربر #' . $tuid . ' کسر شد.'
				);
				return;
			}
			SimpleVPBot_Model_User::update( $tuid, array( 'balance' => round( $bal + $amt, 2 ) ) );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				'✅ ' . number_format( $amt ) . ' تومان به کیف پول کاربر #' . $tuid . ' اضافه شد.'
			);
			return;
		}
		if ( 'admin_find_user' === $st && '' !== trim( (string) $text ) ) {
			$qtrim = trim( SimpleVPBot_Bot_Runtime::normalize_digits( (string) $text ) );
			$found = SimpleVPBot_Model_User::search( $qtrim, 10 );
			if ( empty( $found ) ) {
				SimpleVPBot_State::set( (int) $user->id, 'admin_find_user', array() );
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					'⛔ کاربری یافت نشد. عبارت دیگری بفرستید یا از منو دوباره جستجو کنید.',
					array( 'reply_markup' => SimpleVPBot_Keyboards::admin_users_submenu_reply() )
				);
				return;
			}
			SimpleVPBot_State::clear( (int) $user->id );
			if ( 1 === count( $found ) ) {
				SimpleVPBot_Handler_Admin_Hub::send_user_admin_card( $platform, $chat_id, (int) $found[0]->id );
				return;
			}
			$pick_rows = array();
			foreach ( $found as $fu ) {
				$pick_rows[] = array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '👤 pick ' . (int) $fu->id, 256 ) ) );
			}
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				'🔎 چند کاربر پیدا شد؛ یکی را انتخاب کنید:',
				array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $pick_rows ) )
			);
			return;
		}
		if ( 'admin_broadcast' === $st && $text ) {
			SimpleVPBot_State::clear( (int) $user->id );
			$bid = SimpleVPBot_Model_Broadcast::insert(
				array(
					'type'    => 'text',
					'content' => wp_json_encode( array( 'text' => $text ) ),
					'status'  => 'sending',
				)
			);
			$users = SimpleVPBot_Model_User::all_approved();
			$rows  = array();
			foreach ( $users as $u ) {
				if ( ! empty( $u->tg_user_id ) ) {
					$rows[] = array(
						'broadcast_id' => $bid,
						'user_id'      => (int) $u->id,
						'bot'          => 'tg',
						'chat_id'      => (int) $u->tg_user_id,
						'payload_json' => wp_json_encode( array( 'chat_id' => (int) $u->tg_user_id, 'text' => $text ) ),
						'status'       => 'pending',
					);
				}
				if ( ! empty( $u->bale_user_id ) ) {
					$rows[] = array(
						'broadcast_id' => $bid,
						'user_id'      => (int) $u->id,
						'bot'          => 'bale',
						'chat_id'      => (int) $u->bale_user_id,
						'payload_json' => wp_json_encode( array( 'chat_id' => (int) $u->bale_user_id, 'text' => $text ) ),
						'status'       => 'pending',
					);
				}
			}
			SimpleVPBot_Model_Broadcast::enqueue_bulk( $bid, $rows );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '📣 پیام در صف ارسال قرار گرفت.' );
			return;
		}

		$hub_ctx = array(
			'platform' => $platform,
			'chat_id'  => $chat_id,
			'user'     => $user,
			'text'     => $text,
			'from_id'  => $from_id,
			'from'     => $from,
		);
		if ( $raw_msg && ( empty( $hub_ctx['message'] ) || ! is_array( $hub_ctx['message'] ) ) ) {
			$hub_ctx['message'] = $raw_msg;
		}
		if ( SimpleVPBot_Handler_Admin_Hub::route_menu_text( $hub_ctx ) ) {
			return;
		}

		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, 'ℹ️ گزینه را از منوی ادمین انتخاب کنید.' );
	}

	/**
	 * Admin bot: read target and execute service transfer.
	 *
	 * @param array<string, mixed> $ctx Context.
	 */
	public static function handle_transfer_target_text( array $ctx ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = $ctx['user'];
		$raw      = trim( SimpleVPBot_Bot_Runtime::normalize_digits( (string) $ctx['text'] ) );
		$state    = (string) $user->state;
		if ( ! preg_match( '/^adm_service_transfer_(\d+)$/', $state, $m ) ) {
			return;
		}
		$sid = (int) $m[1];
		if ( '' === $raw ) {
			return;
		}
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ سرویس یافت نشد.' );
			return;
		}
		$target = class_exists( 'SimpleVPBot_Service_Transfer' ) ? SimpleVPBot_Service_Transfer::resolve_user( $raw ) : null;
		if ( ! $target ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ کاربر مقصد یافت نشد یا مبهم است. دوباره بفرستید یا /start را بزنید.' );
			return;
		}
		$label = 'admin:' . (int) $user->id;
		$res   = SimpleVPBot_Service_Transfer::transfer( $sid, (int) $target->id, $label );
		SimpleVPBot_State::clear( (int) $user->id );
		if ( empty( $res['ok'] ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ انتقال انجام نشد: ' . (string) ( $res['reason'] ?? 'err' ) );
			return;
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			'✅ سرویس #' . $sid . ' به کاربر #' . (int) $target->id . ' منتقل شد.'
		);
	}

	/**
	 * Admin create service: per-GB volume typed after plan pick.
	 *
	 * @param array<string, mixed> $ctx route_text context.
	 * @return bool Handled.
	 */
	private static function route_admin_ns_vol_state( array $ctx ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = $ctx['user'];
		$text     = trim( (string) $ctx['text'] );
		$st       = (string) $user->state;
		if ( 'admin_ns_vol' !== $st ) {
			return false;
		}
		if ( '' === $text ) {
			return false;
		}
		$data = SimpleVPBot_State::data( $user );
		$tuid = (int) ( $data['target_uid'] ?? 0 );
		$pid  = (int) ( $data['plan_id'] ?? 0 );
		if ( $tuid < 1 || $pid < 1 ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ جلسه نامعتبر بود.' );
			return true;
		}
		$plan = SimpleVPBot_Model_Plan::find( $pid );
		if ( ! $plan || ! SimpleVPBot_Model_Plan::is_per_gb( $plan ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ پلن نامعتبر است.' );
			return true;
		}
		$raw = trim( SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
		if ( ! preg_match( '/^\d+$/u', $raw ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ فقط یک عدد صحیح (گیگابایت) بفرستید.' );
			return true;
		}
		$gb = (int) $raw;
		if ( ! SimpleVPBot_Model_Plan::is_volume_in_range( $plan, $gb ) ) {
			$min = (int) ( $plan->traffic_gb_min ?? 0 );
			$max = (int) ( $plan->traffic_gb_max ?? 0 );
			$min_f = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $min );
			$max_f = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $max );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, "⛔ حجم باید بین {$min_f} و {$max_f} گیگابایت باشد." );
			return true;
		}
		$mk = SimpleVPBot_Handler_Admin_Hub::admin_create_service_mode_keyboard( $tuid, $pid, $gb );
		if ( empty( $mk ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ خطای داخلی دکمه‌ها (شناسه‌ها بزرگند).' );
			return true;
		}
		SimpleVPBot_State::clear( (int) $user->id );
		$tuid_f = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $tuid );
		$gb_f    = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $gb );
		$txt     = "➕ ساخت سرویس برای #{$tuid_f}\n📦 حجم: {$gb_f} گیگابایت\n۳) روش اعمال را انتخاب کنید:";
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$txt,
			array( 'reply_markup' => array( 'inline_keyboard' => $mk ) )
		);
		return true;
	}

	/**
	 * Typed admin commands: new service / renew / add volume / bulk (when state admin_line_*).
	 *
	 * @param array<string, mixed> $ctx route_text context.
	 * @return bool Handled.
	 */
	private static function route_admin_line_states( array $ctx ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = $ctx['user'];
		$text     = trim( (string) $ctx['text'] );
		$st       = (string) $user->state;
		if ( '' === $text || 0 !== strpos( $st, 'admin_line_' ) ) {
			return false;
		}
		if ( ! class_exists( 'SimpleVPBot_Admin_User_Ops' ) ) {
			return false;
		}
		$data = SimpleVPBot_State::data( $user );
		if ( 'admin_line_ns' === $st && isset( $data['target_uid'] ) ) {
			$tok = preg_split( '/\s+/', $text );
			if ( count( $tok ) < 3 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ سه بخش لازم است: plan_id حجم mode (w|f|i)' );
				return true;
			}
			$pid  = (int) $tok[0];
			$vol  = (int) $tok[1];
			$mode = strtolower( (string) $tok[2] );
			$mode = in_array( $mode, array( 'w', 'f', 'i' ), true ) ? ( 'w' === $mode ? 'wallet' : ( 'f' === $mode ? 'free' : 'invoice' ) ) : '';
			if ( '' === $mode ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ mode باید w یا f یا i باشد.' );
				return true;
			}
			$vol_arg = $vol > 0 ? $vol : null;
			$r       = SimpleVPBot_Admin_User_Ops::admin_create_service( (int) $data['target_uid'], $pid, $vol_arg, $mode );
			SimpleVPBot_State::clear( (int) $user->id );
			if ( empty( $r['ok'] ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ ' . (string) ( $r['reason'] ?? 'خطا' ) );
				return true;
			}
			$msg = isset( $r['service_id'] ) ? '✅ سرویس #' . (int) $r['service_id'] : '✅ فاکتور ارسال شد (سفارش #' . (int) ( $r['transaction_id'] ?? 0 ) . ').';
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $msg );
			return true;
		}
		if ( 'admin_line_nr' === $st && isset( $data['service_id'] ) ) {
			$mode = strtolower( $text );
			$mode = in_array( $mode, array( 'w', 'f', 'i' ), true ) ? ( 'w' === $mode ? 'wallet' : ( 'f' === $mode ? 'free' : 'invoice' ) ) : '';
			if ( '' === $mode ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ فقط یک حرف: w یا f یا i' );
				return true;
			}
			$r = SimpleVPBot_Admin_User_Ops::admin_renew_service( (int) $data['service_id'], $mode );
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, ! empty( $r['ok'] ) ? '✅ تمدید انجام شد.' : ( '⛔ ' . (string) ( $r['reason'] ?? '' ) ) );
			return true;
		}
		if ( 'admin_line_nv' === $st && isset( $data['service_id'] ) ) {
			$tok = preg_split( '/\s+/', $text );
			if ( count( $tok ) < 2 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ دو بخش: گیگ mode(w|f|i)' );
				return true;
			}
			$gb   = (int) $tok[0];
			$mode = strtolower( (string) $tok[1] );
			$mode = in_array( $mode, array( 'w', 'f', 'i' ), true ) ? ( 'w' === $mode ? 'wallet' : ( 'f' === $mode ? 'free' : 'invoice' ) ) : '';
			if ( '' === $mode ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ mode نامعتبر.' );
				return true;
			}
			$r = SimpleVPBot_Admin_User_Ops::admin_add_volume( (int) $data['service_id'], $gb, $mode );
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, ! empty( $r['ok'] ) ? '✅ حجم اعمال شد.' : ( '⛔ ' . (string) ( $r['reason'] ?? '' ) ) );
			return true;
		}
		if ( 'admin_line_bl' === $st ) {
			$tok = preg_split( '/\s+/', $text );
			if ( count( $tok ) < 2 || 'ok' !== strtolower( (string) $tok[0] ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ برای تأیید دقیقا بفرستید: ok days [gb]' );
				return true;
			}
			$days = (int) $tok[1];
			$gb   = isset( $tok[2] ) ? (int) $tok[2] : 0;
			SimpleVPBot_State::clear( (int) $user->id );
			$out = '';
			if ( $days > 0 ) {
				$rd = SimpleVPBot_Admin_User_Ops::bulk_extend_days( $days, true, 200 );
				$out .= 'روز: ok=' . (int) $rd['done'] . ' err=' . (int) $rd['errors'] . "\n";
			}
			if ( $gb > 0 ) {
				$rg = SimpleVPBot_Admin_User_Ops::bulk_add_volume( $gb, 200 );
				$out .= 'حجم: ok=' . (int) $rg['done'] . ' err=' . (int) $rg['errors'] . "\n";
			}
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $out !== '' ? $out : '⛔ عدد نامعتبر.' );
			return true;
		}
		return false;
	}
}
