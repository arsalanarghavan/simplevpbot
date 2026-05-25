<?php
/**
 * Support contact settings for bot messages.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Support_Contacts
 */
class SimpleVPBot_Support_Contacts {

	/**
	 * Normalize username (strip leading @).
	 *
	 * @param string|mixed $raw Raw username.
	 * @return string
	 */
	public static function normalize_username( $raw ) {
		$u = trim( (string) $raw );
		if ( '' === $u ) {
			return '';
		}
		if ( '@' === $u[0] ) {
			$u = substr( $u, 1 );
		}
		return sanitize_text_field( $u );
	}

	/**
	 * Support info text from settings.
	 *
	 * @return string
	 */
	public static function info_text() {
		return trim( (string) SimpleVPBot_Settings::get( 'support_info', '' ) );
	}

	/**
	 * Telegram username from settings.
	 *
	 * @return string
	 */
	public static function telegram_username() {
		return self::normalize_username( SimpleVPBot_Settings::get( 'support_telegram_username', '' ) );
	}

	/**
	 * Bale username from settings.
	 *
	 * @return string
	 */
	public static function bale_username() {
		return self::normalize_username( SimpleVPBot_Settings::get( 'support_bale_username', '' ) );
	}

	/**
	 * Whether any contact detail is configured.
	 *
	 * @return bool
	 */
	public static function has_contacts() {
		return '' !== self::info_text()
			|| '' !== self::telegram_username()
			|| '' !== self::bale_username();
	}

	/**
	 * Format one username line.
	 *
	 * @param string $label Label prefix.
	 * @param string $username Username without @.
	 * @return string
	 */
	private static function username_line( $label, $username ) {
		if ( '' === $username ) {
			return '';
		}
		return $label . ' @' . $username;
	}

	/**
	 * Build contact block for bot messages.
	 *
	 * @param string|null $platform telegram|bale|null for platform-first ordering.
	 * @return string Empty when nothing configured.
	 */
	public static function contact_block( $platform = null ) {
		$info = self::info_text();
		$tg   = self::telegram_username();
		$bl   = self::bale_username();

		if ( '' === $info && '' === $tg && '' === $bl ) {
			return '';
		}

		$lines = array();
		if ( '' !== $info ) {
			$lines[] = $info;
		}

		$plat = ( 'bale' === $platform ) ? 'bale' : ( ( 'telegram' === $platform ) ? 'telegram' : '' );
		$tg_line = self::username_line( '📱 تلگرام:', $tg );
		$bl_line = self::username_line( '💬 بله:', $bl );

		if ( 'bale' === $plat ) {
			if ( '' !== $bl_line ) {
				$lines[] = $bl_line;
			}
			if ( '' !== $tg_line ) {
				$lines[] = $tg_line;
			}
		} else {
			if ( '' !== $tg_line ) {
				$lines[] = $tg_line;
			}
			if ( '' !== $bl_line ) {
				$lines[] = $bl_line;
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Append support footer when message mentions support and contacts exist.
	 *
	 * @param string      $text Message body.
	 * @param string|null $platform telegram|bale|null.
	 * @return string
	 */
	public static function append_to_message( $text, $platform = null ) {
		$text = (string) $text;
		if ( ! self::has_contacts() ) {
			return $text;
		}
		if ( false === mb_stripos( $text, 'پشتیبانی', 0, 'UTF-8' ) ) {
			return $text;
		}
		$block = self::contact_block( $platform );
		if ( '' === $block ) {
			return $text;
		}
		return rtrim( $text ) . "\n\n" . $block;
	}
}
