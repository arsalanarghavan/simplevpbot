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
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.zip_only', $user ) );
			return true;
		}
		$dir  = SimpleVPBot_Backup_Export::base_tmp_dir();
		$dest = $dir . 'restore-' . wp_generate_password( 8, false ) . '.zip';
		$down = SimpleVPBot_Bot_Runtime::download_bot_file_to_path( $platform, $file_id, $dest );
		if ( is_wp_error( $down ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.download_fail', $user, array( 'error' => $down->get_error_message() ) ) );
			return true;
		}
		$res = SimpleVPBot_Backup_Restore::restore_from_zip_path( $dest );
		@unlink( $dest );
		SimpleVPBot_State::clear( (int) $user->id );
		if ( is_wp_error( $res ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.restore_fail', $user, array( 'error' => $res->get_error_message() ) ) );
		} else {
			$matched  = is_array( $res ) ? (int) ( $res['users_matched'] ?? 0 ) : 0;
			$inserted = is_array( $res ) ? (int) ( $res['users_inserted'] ?? 0 ) : 0;
			$skipped  = is_array( $res ) ? (int) ( $res['users_skipped'] ?? 0 ) : 0;
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.restore_ok', $user, array( 'matched' => (string) $matched, 'inserted' => (string) $inserted, 'skipped' => (string) $skipped ) )
			);
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

		if ( '' !== $text && $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.exit', $user ) ) {
			SimpleVPBot_Model_User::update( (int) $user->id, array( 'admin_mode' => 0 ) );
			$user = SimpleVPBot_Model_User::find( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				$user ? SimpleVPBot_Texts::get_for_user( 'msg.admin_exit_to_user_menu', $user ) : '👋',
				array( 'reply_markup' => SimpleVPBot_Keyboards::user_main_reply( $user ) )
			);
			return;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.dashboard', $user ) ) {
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
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.stats_unavailable', $user ) );
			}
			return;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.users', $user ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.users_submenu', $user ),
				array( 'reply_markup' => SimpleVPBot_Keyboards::admin_users_submenu_reply( $user ) )
			);
			return;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.finance', $user ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.finance_submenu', $user ),
				array( 'reply_markup' => SimpleVPBot_Keyboards::admin_finance_submenu_reply() )
			);
			return;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.users_search', $user ) ) {
			SimpleVPBot_State::set( (int) $user->id, 'admin_find_user', array() );
			$find_prompt = SimpleVPBot_Texts::get_for_user( 'msg.admin_find_user_prompt', $user );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $find_prompt );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.users_queue', $user ) ) {
			SimpleVPBot_Handler_Admin_Hub::send_submenu( $platform, $chat_id, 'usr', array( 'user' => $user ) );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.full_hub', $user ) ) {
			SimpleVPBot_Handler_Admin_Hub::send_hub( $platform, $chat_id );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.transfer', $user ) ) {
			SimpleVPBot_State::set( (int) $user->id, 'admin_find_service_to_transfer', array() );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_service_id', $user ) );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.broadcast', $user ) ) {
			SimpleVPBot_State::set( (int) $user->id, 'admin_broadcast', array() );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_broadcast', $user ) );
			return;
		}
		if ( class_exists( 'SimpleVPBot_UI_Action_Registry' )
			&& SimpleVPBot_UI_Action_Registry::text_matches_reply_action( $text, $user, 'admin.finance.receipts', false ) ) {
			SimpleVPBot_Handler_Admin_Hub::send_pending_receipts_review_paged( $platform, $chat_id, 0 );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.receipts', $user ) ) {
			SimpleVPBot_Handler_Admin_Hub::send_pending_receipts_review_paged( $platform, $chat_id, 0 );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.backup', $user ) || '💾 بکاپ' === $text ) {
			SimpleVPBot_Handler_Admin_Hub::send_backup_panel( $platform, $chat_id );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.settings', $user ) || '⚙️ تنظیمات ربات' === $text ) {
			SimpleVPBot_Handler_Admin_Hub::send_submenu( $platform, $chat_id, 'set', array( 'user' => $user ) );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.advanced', $user ) ) {
			SimpleVPBot_Handler_Admin_Hub::send_submenu( $platform, $chat_id, 'adv', array( 'user' => $user ) );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.bulk_short', $user ) || '➕ گروهی' === $text ) {
			SimpleVPBot_Handler_Admin_Hub::send_submenu( $platform, $chat_id, 'blk', array( 'user' => $user ) );
			return;
		}

		$st   = (string) $user->state;
		$ntex = SimpleVPBot_Bot_Runtime::normalize_digits( $text );

		if ( preg_match( '/^\/cancel(?:@\w+)?/i', $text ) || 'لغو' === $text ) {
			$can_cancel = in_array( $st, array( 'admin_bak_interval', 'admin_bak_tg_chat', 'admin_bak_bl_chat', 'admin_bak_restore', 'admin_find_user', 'admin_dm', 'admin_broadcast', 'admin_w_balance' ), true )
				|| ( class_exists( 'SimpleVPBot_Handler_Admin_Settings' ) && SimpleVPBot_Handler_Admin_Settings::is_cancelable_settings_state( $st ) )
				|| ( 0 === strpos( $st, 'admin_line_' ) )
				|| ( 0 === strpos( $st, 'admin_ns_' ) );
			if ( $can_cancel ) {
				SimpleVPBot_State::clear( (int) $user->id );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.cancelled', $user ) );
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
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.interval_saved', $user, array( 'minutes' => (string) $m ) ) );
				return;
			}
		}
		if ( 'admin_bak_tg_chat' === $st && '' !== $ntex ) {
			$trimn = trim( (string) $ntex );
			if ( is_numeric( $trimn ) ) {
				SimpleVPBot_Admin_Actions::patch_backup_settings( array( 'backup_telegram_chat_id' => (int) $trimn ) );
				SimpleVPBot_State::clear( (int) $user->id );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.tg_chat_saved', $user, array( 'id' => (string) (int) $trimn ) ) );
				return;
			}
		}
		if ( 'admin_bak_bl_chat' === $st && '' !== $ntex ) {
			$trimn = trim( (string) $ntex );
			if ( is_numeric( $trimn ) ) {
				SimpleVPBot_Admin_Actions::patch_backup_settings( array( 'backup_bale_chat_id' => (int) $trimn ) );
				SimpleVPBot_State::clear( (int) $user->id );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.bl_chat_saved', $user, array( 'id' => (string) (int) $trimn ) ) );
				return;
			}
		}
		if ( 'admin_bak_restore' === $st && '' !== $text ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.send_zip', $user ) );
			return;
		}
		if ( in_array( $st, array( 'admin_bak_interval', 'admin_bak_tg_chat', 'admin_bak_bl_chat' ), true ) && '' !== $text ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.send_number', $user ) );
			return;
		}
		if ( $raw_msg && ! empty( $raw_msg['document'] ) && is_array( $raw_msg['document'] ) && 'admin_bak_restore' !== $st ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.restore_hint', $user )
			);
			return;
		}

		if ( 'admin_find_service_to_transfer' === $st && is_numeric( $ntex ) ) {
			$sid = (int) $ntex;
			$svc = SimpleVPBot_Model_Service::find( $sid );
			if ( ! $svc ) {
				SimpleVPBot_State::clear( (int) $user->id );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.not_found', $user ) );
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
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.target_invalid', $user ) );
				return;
			}
			$body = SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.dm_body_prefix', $user, array( 'body' => (string) $text ) );
			if ( ! empty( $target->tg_user_id ) ) {
				SimpleVPBot_Bot_Runtime::send_message( 'telegram', (int) $target->tg_user_id, $body );
			}
			if ( ! empty( $target->bale_user_id ) ) {
				SimpleVPBot_Bot_Runtime::send_message( 'bale', (int) $target->bale_user_id, $body );
			}
			if ( empty( $target->tg_user_id ) && empty( $target->bale_user_id ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.user_no_chat', $user ) );
				return;
			}
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.message_sent', $user ) );
			return;
		}
		if ( 'admin_w_balance' === $st && '' !== trim( (string) $text ) ) {
			$sd    = SimpleVPBot_State::data( $user );
			$tuid  = (int) ( $sd['target_uid'] ?? 0 );
			$sign  = (int) ( $sd['sign'] ?? 1 );
			$ntex2 = SimpleVPBot_Bot_Runtime::normalize_digits( trim( (string) $text ) );
			if ( $tuid < 1 ) {
				SimpleVPBot_State::clear( (int) $user->id );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.destination_invalid', $user ) );
				return;
			}
			$target = SimpleVPBot_Model_User::find( $tuid );
			if ( ! $target ) {
				SimpleVPBot_State::clear( (int) $user->id );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.user_not_found', $user ) );
				return;
			}
			if ( ! preg_match( '/^\d+(?:\.\d{1,2})?$/', (string) $ntex2 ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.wallet_toman_only', $user ) );
				return;
			}
			$amt = round( (float) $ntex2, 2 );
			if ( $amt <= 0 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.wallet_amount_positive', $user ) );
				return;
			}
			SimpleVPBot_State::clear( (int) $user->id );
			$bal = (float) $target->balance;
			if ( $sign < 0 ) {
				if ( $bal < $amt ) {
					SimpleVPBot_Bot_Runtime::send_message(
						$platform,
						$chat_id,
						SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.wallet_insufficient', $user, array( 'balance' => number_format( $bal ) ) )
					);
					return;
				}
				SimpleVPBot_Model_User::update( $tuid, array( 'balance' => round( $bal - $amt, 2 ) ) );
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.wallet_debited', $user, array( 'amount' => number_format( $amt ), 'id' => (string) $tuid ) )
				);
				return;
			}
			SimpleVPBot_Model_User::update( $tuid, array( 'balance' => round( $bal + $amt, 2 ) ) );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.wallet_credited', $user, array( 'amount' => number_format( $amt ), 'id' => (string) $tuid ) )
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
					SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.find_user_none', $user ),
					array( 'reply_markup' => SimpleVPBot_Keyboards::admin_users_submenu_reply( $user ) )
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
				SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.find_user_pick', $user ),
				array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $pick_rows ) )
			);
			return;
		}
		if ( 'admin_broadcast' === $st && $text ) {
			SimpleVPBot_State::clear( (int) $user->id );
			$text_trim = trim( (string) $text );
			$text_safe = class_exists( 'SimpleVPBot_Dashboard_Admin_Mutations' )
				? SimpleVPBot_Dashboard_Admin_Mutations::sanitize_bot_text_for_messages( $text_trim )
				: $text_trim;
			$text_safe = mb_substr( $text_safe, 0, 4096 );
			$parse_api = 'HTML';
			$bid       = SimpleVPBot_Model_Broadcast::insert(
				array(
					'type'    => 'text',
					'content' => wp_json_encode(
						array(
							'text'       => $text_safe,
							'parse_mode' => $parse_api,
						),
						JSON_UNESCAPED_UNICODE
					),
					'status'  => 'sending',
				)
			);
			$users = SimpleVPBot_Model_User::all_approved();
			$rows  = array();
			$base  = array(
				'text'       => $text_safe,
				'parse_mode' => $parse_api,
			);
			foreach ( $users as $u ) {
				if ( ! empty( $u->tg_user_id ) ) {
					$rows[] = array(
						'broadcast_id' => $bid,
						'user_id'      => (int) $u->id,
						'bot'          => 'tg',
						'chat_id'      => (int) $u->tg_user_id,
						'payload_json' => wp_json_encode(
							array_merge( $base, array( 'chat_id' => (int) $u->tg_user_id ) ),
							JSON_UNESCAPED_UNICODE
						),
						'status'       => 'pending',
					);
				}
				if ( ! empty( $u->bale_user_id ) ) {
					$rows[] = array(
						'broadcast_id' => $bid,
						'user_id'      => (int) $u->id,
						'bot'          => 'bale',
						'chat_id'      => (int) $u->bale_user_id,
						'payload_json' => wp_json_encode(
							array_merge( $base, array( 'chat_id' => (int) $u->bale_user_id ) ),
							JSON_UNESCAPED_UNICODE
						),
						'status'       => 'pending',
					);
				}
			}
			SimpleVPBot_Model_Broadcast::enqueue_bulk( $bid, $rows );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.broadcast_queued', $user ) );
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

		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.menu_pick_option', $user ) );
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
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.not_found', $user ) );
			return;
		}
		$target = class_exists( 'SimpleVPBot_Service_Transfer' ) ? SimpleVPBot_Service_Transfer::resolve_user( $raw ) : null;
		if ( ! $target ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.transfer_target_ambiguous', $user ) );
			return;
		}
		$label = 'admin:' . (int) $user->id;
		$res   = SimpleVPBot_Service_Transfer::transfer( $sid, (int) $target->id, $label );
		SimpleVPBot_State::clear( (int) $user->id );
		if ( empty( $res['ok'] ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.error_generic', $user, array( 'reason' => (string) ( $res['reason'] ?? 'err' ) ) ) );
			return;
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.transfer_ok', $user, array( 'sid' => (string) $sid, 'uid' => (string) (int) $target->id ) )
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
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.session_invalid', $user ) );
			return true;
		}
		$plan = SimpleVPBot_Model_Plan::find( $pid );
		if ( ! $plan || ! SimpleVPBot_Model_Plan::is_per_gb( $plan ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.plan_invalid', $user ) );
			return true;
		}
		$raw = trim( SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
		if ( ! preg_match( '/^\d+$/u', $raw ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.ns_volume_integer', $user ) );
			return true;
		}
		$gb = (int) $raw;
		if ( ! SimpleVPBot_Model_Plan::is_volume_in_range( $plan, $gb ) ) {
			$min = (int) ( $plan->traffic_gb_min ?? 0 );
			$max = (int) ( $plan->traffic_gb_max ?? 0 );
			$min_f = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $min );
			$max_f = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $max );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.ns_volume_range', $user, array( 'min' => $min_f, 'max' => $max_f ) ) );
			return true;
		}
		$mk = SimpleVPBot_Handler_Admin_Hub::admin_create_service_mode_keyboard( $tuid, $pid, $gb );
		if ( empty( $mk ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.internal_button_error', $user ) );
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
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.ns_parts', $user ) );
				return true;
			}
			$pid  = (int) $tok[0];
			$vol  = (int) $tok[1];
			$mode = strtolower( (string) $tok[2] );
			$mode = in_array( $mode, array( 'w', 'f', 'i' ), true ) ? ( 'w' === $mode ? 'wallet' : ( 'f' === $mode ? 'free' : 'invoice' ) ) : '';
			if ( '' === $mode ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.mode_wfi', $user ) );
				return true;
			}
			$vol_arg = $vol > 0 ? $vol : null;
			$r       = SimpleVPBot_Admin_User_Ops::admin_create_service( (int) $data['target_uid'], $pid, $vol_arg, $mode );
			SimpleVPBot_State::clear( (int) $user->id );
			if ( empty( $r['ok'] ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::format( SimpleVPBot_Texts::get_for_user( 'msg.admin.error_generic', $user ), array( 'reason' => (string) ( $r['reason'] ?? 'خطا' ) ) ) );
				return true;
			}
			$msg = isset( $r['service_id'] )
				? SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.service_created', $user, array( 'id' => (string) (int) $r['service_id'] ) )
				: SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.invoice_sent', $user, array( 'id' => (string) (int) ( $r['transaction_id'] ?? 0 ) ) );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $msg );
			return true;
		}
		if ( 'admin_line_nr' === $st && isset( $data['service_id'] ) ) {
			$mode = strtolower( $text );
			$mode = in_array( $mode, array( 'w', 'f', 'i' ), true ) ? ( 'w' === $mode ? 'wallet' : ( 'f' === $mode ? 'free' : 'invoice' ) ) : '';
			if ( '' === $mode ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.renew_one_char', $user ) );
				return true;
			}
			$r = SimpleVPBot_Admin_User_Ops::admin_renew_service( (int) $data['service_id'], $mode );
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, ! empty( $r['ok'] ) ? SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.renew_ok', $user ) : SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.error_generic', $user, array( 'reason' => (string) ( $r['reason'] ?? '' ) ) ) );
			return true;
		}
		if ( 'admin_line_nv' === $st && isset( $data['service_id'] ) ) {
			$tok = preg_split( '/\s+/', $text );
			if ( count( $tok ) < 2 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.two_parts', $user ) );
				return true;
			}
			$gb   = (int) $tok[0];
			$mode = strtolower( (string) $tok[1] );
			$mode = in_array( $mode, array( 'w', 'f', 'i' ), true ) ? ( 'w' === $mode ? 'wallet' : ( 'f' === $mode ? 'free' : 'invoice' ) ) : '';
			if ( '' === $mode ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.mode_invalid', $user ) );
				return true;
			}
			$r = SimpleVPBot_Admin_User_Ops::admin_add_volume( (int) $data['service_id'], $gb, $mode );
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, ! empty( $r['ok'] ) ? SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.volume_ok', $user ) : SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.error_generic', $user, array( 'reason' => (string) ( $r['reason'] ?? '' ) ) ) );
			return true;
		}
		if ( 'admin_line_bl' === $st ) {
			$tok = preg_split( '/\s+/', $text );
			if ( count( $tok ) < 2 || 'ok' !== strtolower( (string) $tok[0] ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.bulk_confirm', $user ) );
				return true;
			}
			$days = (int) $tok[1];
			$gb   = isset( $tok[2] ) ? (int) $tok[2] : 0;
			SimpleVPBot_State::clear( (int) $user->id );
			$out = '';
			if ( $days > 0 ) {
				$rd = SimpleVPBot_Admin_User_Ops::bulk_extend_days( $days, true, 200 );
				$out .= SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.bulk_days_result', $user, array( 'done' => (string) (int) $rd['done'], 'errors' => (string) (int) $rd['errors'] ) ) . "\n";
			}
			if ( $gb > 0 ) {
				$rg = SimpleVPBot_Admin_User_Ops::bulk_add_volume( $gb, 200 );
				$out .= SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.bulk_gb_result', $user, array( 'done' => (string) (int) $rg['done'], 'errors' => (string) (int) $rg['errors'] ) ) . "\n";
			}
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $out !== '' ? $out : SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.bulk_invalid', $user ) );
			return true;
		}
		return false;
	}
}
