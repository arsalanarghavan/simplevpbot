<?php
/**
 * /start and VIP registration flow.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Start
 */
class SimpleVPBot_Handler_Start {

	/**
	 * Handle /start.
	 *
	 * @param array<string, mixed> $ctx Context.
	 */
	public static function handle( array $ctx ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$from     = $ctx['from'];
		$user     = isset( $ctx['user'] ) ? $ctx['user'] : null;
		$from_id  = (int) ( $from['id'] ?? 0 );
		$name     = trim( (string) ( $from['first_name'] ?? '' ) . ' ' . (string) ( $from['last_name'] ?? '' ) );

		$tg_auto_approve = 'telegram' === $platform;
		$start_text      = isset( $ctx['text'] ) ? (string) $ctx['text'] : '';
		$inviter_candidate = SimpleVPBot_Referral_Service::parse_inviter_from_start_text( $start_text );
		$user_is_new      = false;

		if ( ! $user ) {
			if ( 'telegram' === $platform ) {
				$user = SimpleVPBot_Model_User::find_by_telegram( $from_id );
			} else {
				$user = SimpleVPBot_Model_User::find_by_bale( $from_id );
			}
		}

		if ( ! $user ) {
			$initial_status = $tg_auto_approve ? 'approved' : 'pending';
			$data           = array(
				'first_name' => (string) ( $from['first_name'] ?? '' ),
				'last_name'  => (string) ( $from['last_name'] ?? '' ),
				'username'   => (string) ( $from['username'] ?? '' ),
				'role'       => 'user',
				'balance'    => 0,
				'status'     => $initial_status,
				'admin_mode' => 0,
				'state'      => null,
				'state_data' => wp_json_encode( array() ),
			);
			if ( $tg_auto_approve ) {
				$data['tg_user_id']  = $from_id;
				$data['approved_by'] = 'auto:telegram';
				$data['approved_at'] = current_time( 'mysql' );
			} else {
				$data['bale_user_id'] = $from_id;
			}
			$inviter_ok = SimpleVPBot_Referral_Service::validate_inviter_id( $inviter_candidate, 0 );
			if ( $inviter_ok > 0 ) {
				$data['invited_by'] = $inviter_ok;
			}
			$uid  = SimpleVPBot_Model_User::insert( $data );
			$user = SimpleVPBot_Model_User::find( $uid );
			$user_is_new = true;
		} else {
			$upd = array();
			if ( 'telegram' === $platform && empty( $user->tg_user_id ) ) {
				$upd['tg_user_id'] = $from_id;
			}
			if ( 'bale' === $platform && empty( $user->bale_user_id ) ) {
				$upd['bale_user_id'] = $from_id;
			}
			if ( $tg_auto_approve && 'blocked' !== $user->status && 'approved' !== $user->status ) {
				$upd['status']      = 'approved';
				$upd['approved_by'] = (string) ( $user->approved_by ?: 'auto:telegram' );
				$upd['approved_at'] = (string) ( $user->approved_at ?: current_time( 'mysql' ) );
			}
			if ( $upd ) {
				SimpleVPBot_Model_User::update( (int) $user->id, $upd );
				$user = SimpleVPBot_Model_User::find( (int) $user->id );
			}
		}

		if ( ! $user ) {
			return;
		}

		if ( $inviter_candidate > 0 ) {
			$outcome = 'ignored_existing';
			if ( $user_is_new ) {
				if ( ! empty( $user->invited_by ) && (int) $user->invited_by === $inviter_candidate ) {
					$outcome = 'attached_new_user';
				} else {
					$outcome = 'ignored_invalid_inviter';
				}
			}
			SimpleVPBot_Referral_Service::log_start_event(
				array(
					'platform'                 => $platform,
					'visitor_chat_id'          => $chat_id,
					'visitor_platform_user_id' => $from_id,
					'inviter_svp_user_id'      => $inviter_candidate,
					'start_payload'            => SimpleVPBot_Referral_Service::start_payload_for_inviter( $inviter_candidate ),
					'outcome'                  => $outcome,
					'resulting_svp_user_id'    => (int) $user->id,
				)
			);
		}

		if ( 'blocked' === $user->status ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ دسترسی شما مسدود است.' );
			return;
		}

		if ( 'approved' === $user->status ) {
			$msg = SimpleVPBot_Texts::format(
				SimpleVPBot_Texts::get_for_user( 'msg.welcome', $user ),
				array( 'name' => $name )
			);
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				$msg,
				array( 'reply_markup' => SimpleVPBot_Keyboards::user_main_reply( $user ) )
			);
			return;
		}

		if ( 'rejected' === $user->status ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.approval_rejected', $user ) );
			return;
		}

		// Bale: admin approval flow. Telegram never reaches here (auto-approved above).
		$open = SimpleVPBot_Model_Pending::find_open_for_user( (int) $user->id );
		if ( $open ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.approval_wait', $user ) );
			return;
		}

		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.approval_wait', $user ) );

		$pid = SimpleVPBot_Model_Pending::insert(
			array(
				'user_id'             => (int) $user->id,
				'bot'                 => 'telegram' === $platform ? 'tg' : 'bale',
				'admin_messages_json' => wp_json_encode( array() ),
				'status'              => 'pending',
			)
		);

		$messages = array();
		$body   = SimpleVPBot_Bot_Admin_User_Caption::membership_request_caption( $user, false );
		$markup = SimpleVPBot_Keyboards::inline_registration( (int) $user->id );

		$is_reseller_ctx = class_exists( 'SimpleVPBot_Bot_Context' ) && SimpleVPBot_Bot_Context::is_reseller_bot();
		if ( $is_reseller_ctx && class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			$prof = SimpleVPBot_Bot_Context::reseller_profile();
			$ids  = array();
			if ( $prof ) {
				if ( 'telegram' === $platform ) {
					$ids = (array) SimpleVPBot_Model_Reseller_Bot_Profile::decode_admin_ids( $prof->admin_telegram_ids ?? '' );
				} else {
					$ids = (array) SimpleVPBot_Model_Reseller_Bot_Profile::decode_admin_ids( $prof->admin_bale_ids ?? '' );
				}
			}
			$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
			foreach ( $ids as $adm ) {
				$r = SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					(int) $adm,
					$body,
					array( 'reply_markup' => $markup )
				);
				if ( ! empty( $r['result']['message_id'] ) ) {
					$messages[] = array(
						'platform'   => $platform,
						'chat_id'    => (int) $adm,
						'message_id' => (int) $r['result']['message_id'],
					);
				}
				usleep( 350000 );
			}
		} else {
			$tg_ids = (array) SimpleVPBot_Settings::get( 'admin_telegram_ids', array() );
			$bl_ids = (array) SimpleVPBot_Settings::get( 'admin_bale_ids', array() );
			$tg_tok = (string) SimpleVPBot_Settings::get( 'telegram_token', '' );
			$bl_tok = (string) SimpleVPBot_Settings::get( 'bale_token', '' );

			if ( $tg_tok ) {
				$tg = new SimpleVPBot_Telegram_Client( $tg_tok );
				foreach ( $tg_ids as $adm ) {
					$r = $tg->send_message(
						array(
							'chat_id'      => (int) $adm,
							'text'         => $body,
							'reply_markup' => $markup,
						)
					);
					if ( ! empty( $r['result']['message_id'] ) ) {
						$messages[] = array(
							'platform'   => 'telegram',
							'chat_id'    => (int) $adm,
							'message_id' => (int) $r['result']['message_id'],
						);
					}
					usleep( 350000 );
				}
			}
			if ( $bl_tok ) {
				$bl = new SimpleVPBot_Bale_Client( $bl_tok );
				foreach ( $bl_ids as $adm ) {
					$r = $bl->send_message(
						array(
							'chat_id'      => (int) $adm,
							'text'         => $body,
							'reply_markup' => $markup,
						)
					);
					if ( ! empty( $r['result']['message_id'] ) ) {
						$messages[] = array(
							'platform'   => 'bale',
							'chat_id'    => (int) $adm,
							'message_id' => (int) $r['result']['message_id'],
						);
					}
					usleep( 350000 );
				}
			}
		}

		SimpleVPBot_Model_Pending::update(
			$pid,
			array(
				'admin_messages_json' => wp_json_encode( $messages ),
			)
		);
	}
}
