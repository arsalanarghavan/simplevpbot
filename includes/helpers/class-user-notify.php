<?php
/**
 * Central user notifications (Telegram/Bale) with reseller bot token resolution.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_User_Notify
 */
class SimpleVPBot_User_Notify {

	/**
	 * Platforms to notify for this user row.
	 *
	 * @param object|null $user svp_users row.
	 * @return array<int, string> telegram|bale
	 */
	public static function platforms_for_user( $user, $channel = 'both' ) {
		if ( ! $user || ! is_object( $user ) ) {
			return array();
		}
		$tg = ! empty( $user->tg_user_id );
		$bl = ! empty( $user->bale_user_id );
		$out = array();
		if ( $tg && ! $bl ) {
			$out = array( 'telegram' );
		} elseif ( $bl && ! $tg ) {
			$out = array( 'bale' );
		} elseif ( $tg && $bl ) {
			$out = array( 'telegram', 'bale' );
		}
		$ch = sanitize_key( (string) $channel );
		if ( 'telegram' === $ch ) {
			return in_array( 'telegram', $out, true ) ? array( 'telegram' ) : array();
		}
		if ( 'bale' === $ch ) {
			return in_array( 'bale', $out, true ) ? array( 'bale' ) : array();
		}
		return $out;
	}

	/**
	 * Reseller id for outbound messages.
	 *
	 * @param object|null          $user       User row.
	 * @param object|null          $context_tx Transaction (optional).
	 * @return int
	 */
	public static function resolve_reseller_id( $user, $context_tx = null ) {
		if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return SimpleVPBot_Bot_Reseller_Scope::resolve_reseller_id_for_notify( $user, $context_tx );
		}
		return 0;
	}

	/**
	 * Send text to user on linked platform(s) using reseller bot when applicable.
	 *
	 * @param object|null          $user       svp_users row.
	 * @param string               $text       Message text.
	 * @param array<string, mixed> $extra      reply_markup, etc.
	 * @param object|null          $context_tx Transaction for billing scope (optional).
	 * @param string               $channel    both|telegram|bale.
	 * @return int Messages sent (0 if none).
	 */
	public static function send_to_user( $user, $text, array $extra = array(), $context_tx = null, $channel = 'both' ) {
		if ( ! $user || ! is_object( $user ) || '' === trim( (string) $text ) ) {
			return 0;
		}
		if ( ! class_exists( 'SimpleVPBot_Bot_Runtime' ) ) {
			return 0;
		}
		$rid  = self::resolve_reseller_id( $user, $context_tx );
		$sent = 0;
		foreach ( self::platforms_for_user( $user, $channel ) as $plat ) {
			$chat_id = 'bale' === $plat ? (int) $user->bale_user_id : (int) $user->tg_user_id;
			if ( $chat_id < 1 ) {
				continue;
			}
			$r = SimpleVPBot_Bot_Runtime::send_message_for_reseller( $plat, $chat_id, $text, $rid, $extra );
			if ( null !== $r ) {
				++$sent;
			}
		}
		return $sent;
	}
}
