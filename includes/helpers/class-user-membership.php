<?php
/**
 * Approve/reject bot user registration from WP admin (same semantics as Telegram/Bale callbacks).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_User_Membership
 */
class SimpleVPBot_User_Membership {

	/**
	 * Approve membership (pending user → approved).
	 *
	 * @param int    $user_id svp_users.id.
	 * @param string $decided_by Admin label (e.g. WP user_login).
	 * @return array{ok:bool, reason?:string}
	 */
	public static function approve( $user_id, $decided_by ) {
		$uid   = (int) $user_id;
		$label = (string) $decided_by;
		$user  = SimpleVPBot_Model_User::find( $uid );
		if ( ! $user ) {
			return array( 'ok' => false, 'reason' => 'no_user' );
		}
		if ( 'approved' === (string) $user->status ) {
			return array( 'ok' => true, 'reason' => 'already_approved' );
		}
		SimpleVPBot_Model_User::update(
			$uid,
			array(
				'status'      => 'approved',
				'approved_by' => $label,
				'approved_at' => current_time( 'mysql' ),
			)
		);
		$pending = SimpleVPBot_Model_Pending::find_open_for_user( $uid );
		if ( $pending && 'pending' === (string) $pending->status ) {
			SimpleVPBot_Model_Pending::update(
				(int) $pending->id,
				array(
					'status'     => 'approved',
					'decided_at' => current_time( 'mysql' ),
					'decided_by' => $label,
				)
			);
			self::finalize_pending_admin_buttons(
				$pending,
				SimpleVPBot_Texts::format(
					SimpleVPBot_Texts::get( 'btn.approved_by', '✅ تایید شد توسط {admin}' ),
					array( 'admin' => $label )
				)
			);
		}
		$user2 = SimpleVPBot_Model_User::find( $uid ) ?: $user;
		self::notify_user( $user2, SimpleVPBot_Texts::get( 'msg.approval_approved' ), true );
		return array( 'ok' => true, 'reason' => 'approved' );
	}

	/**
	 * Reject membership.
	 *
	 * @param int    $user_id svp_users.id.
	 * @param string $decided_by Admin label.
	 * @return array{ok:bool, reason?:string}
	 */
	public static function reject( $user_id, $decided_by ) {
		$uid   = (int) $user_id;
		$label = (string) $decided_by;
		$user  = SimpleVPBot_Model_User::find( $uid );
		if ( ! $user ) {
			return array( 'ok' => false, 'reason' => 'no_user' );
		}
		if ( 'rejected' === (string) $user->status ) {
			return array( 'ok' => true, 'reason' => 'already_rejected' );
		}
		SimpleVPBot_Model_User::update( $uid, array( 'status' => 'rejected' ) );
		$pending = SimpleVPBot_Model_Pending::find_open_for_user( $uid );
		if ( $pending && 'pending' === (string) $pending->status ) {
			SimpleVPBot_Model_Pending::update(
				(int) $pending->id,
				array(
					'status'     => 'rejected',
					'decided_at' => current_time( 'mysql' ),
					'decided_by' => $label,
				)
			);
			self::finalize_pending_admin_buttons(
				$pending,
				SimpleVPBot_Texts::format(
					SimpleVPBot_Texts::get( 'btn.rejected_by', '❌ رد شد توسط {admin}' ),
					array( 'admin' => $label )
				)
			);
		}
		$user2 = SimpleVPBot_Model_User::find( $uid ) ?: $user;
		self::notify_user( $user2, SimpleVPBot_Texts::get( 'msg.approval_rejected' ), false );
		return array( 'ok' => true, 'reason' => 'rejected' );
	}

	/**
	 * Move rejected user back to pending and notify admins (same pattern as /start Bale flow).
	 *
	 * @param int $user_id svp_users.id.
	 * @return array{ok:bool, reason?:string}
	 */
	public static function reopen_rejected_to_pending( $user_id ) {
		$uid  = (int) $user_id;
		$user = SimpleVPBot_Model_User::find( $uid );
		if ( ! $user ) {
			return array( 'ok' => false, 'reason' => 'no_user' );
		}
		if ( 'rejected' !== (string) $user->status ) {
			return array( 'ok' => false, 'reason' => 'not_rejected' );
		}
		if ( SimpleVPBot_Model_Pending::find_open_for_user( $uid ) ) {
			return array( 'ok' => false, 'reason' => 'pending_row_exists' );
		}
		SimpleVPBot_Model_User::update(
			$uid,
			array(
				'status'      => 'pending',
				'approved_by' => null,
				'approved_at' => null,
			)
		);
		$user = SimpleVPBot_Model_User::find( $uid );
		if ( ! $user ) {
			return array( 'ok' => false, 'reason' => 'no_user' );
		}
		$bot_col = ( ! empty( $user->bale_user_id ) && empty( $user->tg_user_id ) ) ? 'bale' : 'tg';
		$pid     = SimpleVPBot_Model_Pending::insert(
			array(
				'user_id'             => $uid,
				'bot'                 => $bot_col,
				'admin_messages_json' => wp_json_encode( array() ),
				'status'              => 'pending',
			)
		);
		$body    = SimpleVPBot_Bot_Admin_User_Caption::membership_request_caption( $user, true );
		$markup  = SimpleVPBot_Keyboards::inline_registration( $uid );
		$msgs    = array();
		$tg_ids  = (array) SimpleVPBot_Settings::get( 'admin_telegram_ids', array() );
		$bl_ids  = (array) SimpleVPBot_Settings::get( 'admin_bale_ids', array() );
		$tg_tok  = (string) SimpleVPBot_Settings::get( 'telegram_token', '' );
		$bl_tok  = (string) SimpleVPBot_Settings::get( 'bale_token', '' );
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
					$msgs[] = array(
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
					$msgs[] = array(
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
		SimpleVPBot_Model_Pending::update(
			$pid,
			array( 'admin_messages_json' => wp_json_encode( $msgs ) )
		);
		self::notify_user( $user, SimpleVPBot_Texts::get( 'msg.membership.requeued' ), false );
		return array( 'ok' => true, 'reason' => 'requeued' );
	}

	/**
	 * Replace inline keyboard on admin approval messages.
	 *
	 * @param object $pending Pending row.
	 * @param string $btn_text Button label.
	 */
	private static function finalize_pending_admin_buttons( $pending, $btn_text ) {
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
			if ( $cid && $mid ) {
				SimpleVPBot_Bot_Runtime::edit_reply_markup( $plat, $cid, $mid, $markup );
			}
		}
	}

	/**
	 * Notify user on Telegram/Bale.
	 *
	 * @param object $user User row.
	 * @param string $text Message.
	 * @param bool   $with_menu Send main reply keyboard.
	 */
	private static function notify_user( $user, $text, $with_menu ) {
		$extra = $with_menu ? array( 'reply_markup' => SimpleVPBot_Keyboards::user_main_reply( $user ) ) : array();
		if ( ! empty( $user->tg_user_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( 'telegram', (int) $user->tg_user_id, $text, $extra );
		}
		if ( ! empty( $user->bale_user_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( 'bale', (int) $user->bale_user_id, $text, $extra );
		}
	}
}
