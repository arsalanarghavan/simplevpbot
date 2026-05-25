<?php
/**
 * Mandatory channel membership gate (Telegram / Bale).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Required_Channel
 */
class SimpleVPBot_Required_Channel {

	/**
	 * Normalize platform key.
	 *
	 * @param string $platform Platform.
	 * @return string telegram|bale
	 */
	public static function normalize_platform( $platform ) {
		return ( 'bale' === $platform ) ? 'bale' : 'telegram';
	}

	/**
	 * Settings key prefix for platform.
	 *
	 * @param string $platform Platform.
	 * @return string force_join_telegram|force_join_bale
	 */
	private static function prefix( $platform ) {
		return 'telegram' === self::normalize_platform( $platform ) ? 'force_join_telegram' : 'force_join_bale';
	}

	/**
	 * Whether force-join is enabled and channel id is set.
	 *
	 * @param string $platform Platform.
	 * @return bool
	 */
	public static function is_enabled( $platform ) {
		$p = self::prefix( $platform );
		if ( ! SimpleVPBot_Settings::get( $p . '_enabled', false ) ) {
			return false;
		}
		return 0 !== (int) SimpleVPBot_Settings::get( $p . '_chat_id', 0 );
	}

	/**
	 * Whether gating should run (enabled + configured join link).
	 *
	 * @param string $platform Platform.
	 * @return bool
	 */
	public static function should_gate( $platform ) {
		if ( ! self::is_enabled( $platform ) ) {
			return false;
		}
		return '' !== self::join_url( $platform );
	}

	/**
	 * Channel config for platform.
	 *
	 * @param string $platform Platform.
	 * @return array{enabled:bool,chat_id:int,username:string,invite_link:string,prompt_text:string,announce_text:string}
	 */
	public static function config( $platform ) {
		$p = self::prefix( $platform );
		return array(
			'enabled'       => ! empty( SimpleVPBot_Settings::get( $p . '_enabled', false ) ),
			'chat_id'       => (int) SimpleVPBot_Settings::get( $p . '_chat_id', 0 ),
			'username'      => self::normalize_username( (string) SimpleVPBot_Settings::get( $p . '_username', '' ) ),
			'invite_link'   => esc_url_raw( trim( (string) SimpleVPBot_Settings::get( $p . '_invite_link', '' ) ) ),
			'prompt_text'   => (string) SimpleVPBot_Settings::get( $p . '_prompt_text', '' ),
			'announce_text' => (string) SimpleVPBot_Settings::get( $p . '_announce_text', '' ),
		);
	}

	/**
	 * @param string $username Raw username.
	 * @return string Without @.
	 */
	public static function normalize_username( $username ) {
		return sanitize_text_field( ltrim( trim( (string) $username ), '@' ) );
	}

	/**
	 * Public join URL for inline button.
	 *
	 * @param string $platform Platform.
	 * @return string Empty when not configured.
	 */
	public static function join_url( $platform ) {
		$cfg  = self::config( $platform );
		$link = (string) ( $cfg['invite_link'] ?? '' );
		if ( '' !== $link ) {
			return $link;
		}
		$user = (string) ( $cfg['username'] ?? '' );
		if ( '' === $user ) {
			return '';
		}
		if ( 'bale' === self::normalize_platform( $platform ) ) {
			return 'https://ble.ir/' . rawurlencode( $user );
		}
		return 'https://t.me/' . rawurlencode( $user );
	}

	/**
	 * Prompt body shown to users who must join.
	 *
	 * @param string     $platform Platform.
	 * @param object|null $user    svp_users row or null.
	 * @return string
	 */
	public static function prompt_message( $platform, $user = null ) {
		$cfg  = self::config( $platform );
		$text = trim( (string) ( $cfg['prompt_text'] ?? '' ) );
		if ( '' !== $text ) {
			return $text;
		}
		if ( $user && class_exists( 'SimpleVPBot_Texts' ) ) {
			return SimpleVPBot_Texts::get_for_user( 'msg.force_join.prompt', $user );
		}
		return SimpleVPBot_Texts::get( 'msg.force_join.prompt', '' );
	}

	/**
	 * Inline keyboard: join URL + verify callback.
	 *
	 * @param string $platform Platform.
	 * @param object|null $user User row.
	 * @return array{inline_keyboard:array<int, array<int, array<string, string>>>}|null
	 */
	public static function prompt_keyboard( $platform, $user = null ) {
		$url = self::join_url( $platform );
		if ( '' === $url ) {
			return null;
		}
		$join_lbl = $user && class_exists( 'SimpleVPBot_Texts' )
			? SimpleVPBot_Texts::get_for_user( 'btn.force_join.channel', $user )
			: SimpleVPBot_Texts::get( 'btn.force_join.channel', '' );
		$verify_lbl = $user && class_exists( 'SimpleVPBot_Texts' )
			? SimpleVPBot_Texts::get_for_user( 'btn.force_join.verify', $user )
			: SimpleVPBot_Texts::get( 'btn.force_join.verify', '' );
		return array(
			'inline_keyboard' => array(
				array(
					array(
						'text' => $join_lbl,
						'url'  => $url,
					),
				),
				array(
					array(
						'text'          => $verify_lbl,
						'callback_data' => 'chjoin:verify',
					),
				),
			),
		);
	}

	/**
	 * Send join prompt to private chat.
	 *
	 * @param string     $platform Platform.
	 * @param int        $chat_id  Private chat id.
	 * @param object|null $user    User row.
	 * @return void
	 */
	public static function send_prompt( $platform, $chat_id, $user = null ) {
		$plat = self::normalize_platform( $platform );
		if ( ! self::should_gate( $plat ) ) {
			$msg = $user && class_exists( 'SimpleVPBot_Texts' )
				? SimpleVPBot_Texts::get_for_user( 'msg.force_join.misconfigured', $user )
				: SimpleVPBot_Texts::get( 'msg.force_join.misconfigured', '' );
			if ( class_exists( 'SimpleVPBot_Bot_Runtime' ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $plat, (int) $chat_id, $msg );
			}
			if ( class_exists( 'SimpleVPBot_Logger' ) ) {
				SimpleVPBot_Logger::warning(
					'force_join misconfigured: missing join url',
					array( 'platform' => $plat )
				);
			}
			return;
		}
		$mk = self::prompt_keyboard( $plat, $user );
		$extra = array();
		if ( $mk ) {
			$extra['reply_markup'] = $mk;
		}
		if ( class_exists( 'SimpleVPBot_Bot_Runtime' ) ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$plat,
				(int) $chat_id,
				self::prompt_message( $plat, $user ),
				$extra
			);
		}
	}

	/**
	 * Check channel membership via Bot API.
	 *
	 * @param string $platform Platform.
	 * @param int    $user_id  Telegram/Bale user id.
	 * @return bool
	 */
	public static function user_passes( $platform, $user_id ) {
		$plat = self::normalize_platform( $platform );
		if ( ! self::is_enabled( $plat ) ) {
			return true;
		}
		$cfg = self::config( $plat );
		$cid = (int) ( $cfg['chat_id'] ?? 0 );
		$uid = (int) $user_id;
		if ( $cid === 0 || $uid < 1 ) {
			return true;
		}
		$client = class_exists( 'SimpleVPBot_Bot_Runtime' ) ? SimpleVPBot_Bot_Runtime::client( $plat ) : null;
		if ( ! $client ) {
			return true;
		}
		$res = $client->get_chat_member(
			array(
				'chat_id' => $cid,
				'user_id' => $uid,
			)
		);
		if ( empty( $res['ok'] ) ) {
			if ( class_exists( 'SimpleVPBot_Logger' ) ) {
				SimpleVPBot_Logger::warning(
					'getChatMember failed',
					array(
						'platform'    => $plat,
						'chat_id'     => $cid,
						'user_id'     => $uid,
						'description' => isset( $res['description'] ) ? (string) $res['description'] : '',
					)
				);
			}
			return false;
		}
		$member = isset( $res['result'] ) && is_array( $res['result'] ) ? $res['result'] : array();
		return self::member_status_ok( $member );
	}

	/**
	 * @param array<string, mixed> $member getChatMember result object.
	 * @return bool
	 */
	public static function member_status_ok( array $member ) {
		$status = isset( $member['status'] ) ? (string) $member['status'] : '';
		if ( in_array( $status, array( 'creator', 'administrator', 'member' ), true ) ) {
			return true;
		}
		if ( 'restricted' === $status && ! empty( $member['is_member'] ) ) {
			return true;
		}
		return false;
	}

	/**
	 * After successful verify: notify user and show main menu if approved.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User row.
	 * @return void
	 */
	public static function on_verify_success( $platform, $chat_id, $user ) {
		$plat = self::normalize_platform( $platform );
		$msg  = SimpleVPBot_Texts::get_for_user( 'msg.force_join.success', $user );
		SimpleVPBot_Bot_Runtime::send_message( $plat, (int) $chat_id, $msg );
		if ( 'approved' === (string) $user->status && class_exists( 'SimpleVPBot_Keyboards' ) ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$plat,
				(int) $chat_id,
				SimpleVPBot_Texts::get_for_user( 'msg.welcome', $user ),
				array(
					'reply_markup' => SimpleVPBot_Keyboards::user_main_reply( $user ),
				)
			);
		}
	}

	/**
	 * Send announcement to channel and pin it (dashboard action).
	 *
	 * @param string $platform telegram|bale.
	 * @return array{ok:bool, message?:string, message_id?:int}
	 */
	public static function publish_announcement( $platform ) {
		$plat = self::normalize_platform( $platform );
		$cfg  = self::config( $plat );
		$cid  = (int) ( $cfg['chat_id'] ?? 0 );
		$text = trim( (string) ( $cfg['announce_text'] ?? '' ) );
		if ( $cid === 0 ) {
			return array( 'ok' => false, 'message' => 'missing_chat_id' );
		}
		if ( '' === $text ) {
			return array( 'ok' => false, 'message' => 'missing_announce_text' );
		}
		$client = class_exists( 'SimpleVPBot_Bot_Runtime' ) ? SimpleVPBot_Bot_Runtime::client( $plat ) : null;
		if ( ! $client ) {
			return array( 'ok' => false, 'message' => 'no_bot_client' );
		}
		$send = $client->send_message(
			array(
				'chat_id' => $cid,
				'text'    => $text,
			)
		);
		if ( empty( $send['ok'] ) || ! is_array( $send['result'] ?? null ) ) {
			return array(
				'ok'      => false,
				'message' => isset( $send['description'] ) ? (string) $send['description'] : 'send_failed',
			);
		}
		$mid = (int) ( $send['result']['message_id'] ?? 0 );
		if ( $mid < 1 ) {
			return array( 'ok' => false, 'message' => 'no_message_id' );
		}
		$pin = $client->pin_chat_message(
			array(
				'chat_id'                => $cid,
				'message_id'             => $mid,
				'disable_notification'   => true,
			)
		);
		if ( empty( $pin['ok'] ) ) {
			return array(
				'ok'         => false,
				'message'    => isset( $pin['description'] ) ? (string) $pin['description'] : 'pin_failed',
				'message_id' => $mid,
			);
		}
		return array(
			'ok'         => true,
			'message'    => 'pinned',
			'message_id' => $mid,
		);
	}
}
