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
	 * Optional line for msg.welcome when user has invited_by set (referral link).
	 *
	 * @param object $user svp_users row.
	 * @return string Empty or "\n🔔 …" in user's locale.
	 */
	private static function welcome_referrer_line( $user ) {
		if ( ! class_exists( 'SimpleVPBot_Bot_Admin_User_Caption' ) ) {
			return '';
		}
		$loc  = SimpleVPBot_Texts::locale_for_user( $user );
		$line = SimpleVPBot_Bot_Admin_User_Caption::invited_by_line( $user, $loc );
		if ( '' === $line ) {
			return '';
		}
		if ( 'en' === $loc ) {
			return "\n" . str_replace( '🔗 ', '🔔 ', $line ) . '.';
		}
		return "\n" . str_replace( '🔗 ', '🔔 ', $line ) . '.';
	}

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
			$inviter_ok = class_exists( 'SimpleVPBot_Bot_Reseller_Scope' )
				? SimpleVPBot_Bot_Reseller_Scope::resolve_invited_by_for_signup( $inviter_candidate )
				: SimpleVPBot_Referral_Service::validate_inviter_id( $inviter_candidate, 0 );
			if ( $inviter_ok > 0 ) {
				$data['invited_by'] = $inviter_ok;
			}
			if ( class_exists( 'SimpleVPBot_Bot_Context' ) && SimpleVPBot_Bot_Context::is_reseller_bot() ) {
				$signup_rid = (int) SimpleVPBot_Bot_Context::reseller_svp_user_id();
				if ( $signup_rid > 0 ) {
					$data['signup_reseller_svp_id'] = $signup_rid;
				}
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
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.blocked', $user ) );
			return;
		}

		// /start exits admin panel → user menu (official toggle with /panel).
		if ( SimpleVPBot_Router::is_platform_admin( $platform, $from_id ) ) {
			SimpleVPBot_Model_User::update( (int) $user->id, array( 'admin_mode' => 0 ) );
			if ( class_exists( 'SimpleVPBot_State' ) ) {
				SimpleVPBot_State::clear( (int) $user->id );
			}
			$user = SimpleVPBot_Model_User::find( (int) $user->id );
			if ( ! $user ) {
				return;
			}
		}

		if ( class_exists( 'SimpleVPBot_Marketing_Automation' ) ) {
			$offer_code = SimpleVPBot_Marketing_Automation::parse_offer_code_from_start( $start_text );
			if ( '' !== $offer_code ) {
				$ctx_offer = array_merge(
					$ctx,
					array(
						'user' => $user,
					)
				);
				SimpleVPBot_Marketing_Automation::handle_start_offer( $ctx_offer, $offer_code );
			}
		}

		if ( 'approved' === $user->status ) {
			if ( class_exists( 'SimpleVPBot_Service_Reconcile' ) ) {
				SimpleVPBot_Service_Reconcile::reconcile_for_user( (int) $user->id );
			}
			$welcome_tpl = SimpleVPBot_Texts::get_for_user( 'msg.welcome', $user );
			$ref_line    = self::welcome_referrer_line( $user );
			$msg         = SimpleVPBot_Texts::format(
				$welcome_tpl,
				array(
					'name'           => $name,
					'referrer_line' => $ref_line,
				)
			);
			if ( '' !== $ref_line && false === strpos( $welcome_tpl, '{referrer_line}' ) ) {
				$msg .= $ref_line;
			}
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
				$us = SimpleVPBot_Settings::bot_admin_notify_usleep();
				if ( $us > 0 ) {
					usleep( $us );
				}
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
					$us = SimpleVPBot_Settings::bot_admin_notify_usleep();
					if ( $us > 0 ) {
						usleep( $us );
					}
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
					$us = SimpleVPBot_Settings::bot_admin_notify_usleep();
					if ( $us > 0 ) {
						usleep( $us );
					}
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
