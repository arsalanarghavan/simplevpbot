<?php
/**
 * Cached text/button loader from DB.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Texts
 */
class SimpleVPBot_Texts {

	/**
	 * Cache key => value.
	 *
	 * @var array<string, string>
	 */
	private static $cache = array();

	/**
	 * Cache composite key.
	 *
	 * @param string $key    Key.
	 * @param string $locale Locale.
	 * @return string
	 */
	private static function cache_key( $key, $locale ) {
		return (string) $key . "\x1e" . SimpleVPBot_Model_Text::normalize_locale( $locale );
	}

	/**
	 * Effective locale for a bot user row (empty bot_locale => site default).
	 *
	 * @param object|null $user User row.
	 * @return string fa|en
	 */
	public static function locale_for_user( $user ) {
		if ( ! $user || ! is_object( $user ) ) {
			return self::site_default_locale();
		}
		$bl = isset( $user->bot_locale ) ? trim( (string) $user->bot_locale ) : '';
		if ( 'en' === $bl || 'fa' === $bl ) {
			return $bl;
		}
		return self::site_default_locale();
	}

	/**
	 * Site-wide default bot UI language.
	 *
	 * @return string fa|en
	 */
	public static function site_default_locale() {
		if ( class_exists( 'SimpleVPBot_Settings' ) ) {
			$v = trim( (string) SimpleVPBot_Settings::get( 'default_bot_locale', 'fa' ) );
			return ( 'en' === $v ) ? 'en' : 'fa';
		}
		return 'fa';
	}

	/**
	 * Get text by key. When $locale is null, uses site default (not per-user).
	 *
	 * @param string      $key      Key.
	 * @param string      $default  Default if empty in DB.
	 * @param string|null $locale   fa|en or null for site default.
	 * @return string
	 */
	public static function get( $key, $default = '', $locale = null ) {
		$loc = null === $locale ? self::site_default_locale() : SimpleVPBot_Model_Text::normalize_locale( $locale );
		$ck  = self::cache_key( $key, $loc );
		if ( array_key_exists( $ck, self::$cache ) ) {
			$v = self::$cache[ $ck ];
		} else {
			$v = SimpleVPBot_Model_Text::get( $key, '', $loc );
			self::$cache[ $ck ] = $v;
		}
		if ( '' === $v ) {
			if ( '' !== $default ) {
				return (string) $default;
			}
			$def_row = class_exists( 'SimpleVPBot_Activator' ) ? SimpleVPBot_Activator::default_row_for_text_key( $key, $loc ) : null;
			if ( $def_row && isset( $def_row['value'] ) && '' !== (string) $def_row['value'] ) {
				return (string) $def_row['value'];
			}
			return '';
		}
		return (string) $v;
	}

	/**
	 * Get text for a specific user's locale.
	 *
	 * @param string      $key     Key.
	 * @param object|null $user    svp_users row.
	 * @param string      $default Fallback when DB and seed empty.
	 * @return string
	 */
	public static function get_for_user( $key, $user, $default = '' ) {
		$loc = self::locale_for_user( $user );
		return self::get( $key, $default, $loc );
	}

	/**
	 * Clear cache (after admin edits).
	 */
	public static function clear_cache() {
		self::$cache = array();
	}

	/**
	 * Replace placeholders in template.
	 *
	 * @param string               $tpl Template.
	 * @param array<string, string> $vars Vars.
	 * @return string
	 */
	public static function format( $tpl, array $vars ) {
		foreach ( $vars as $k => $v ) {
			$tpl = str_replace( '{' . $k . '}', (string) $v, $tpl );
		}
		return $tpl;
	}
}
