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
		$rid = 0;
		if ( $user && class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			$rid = SimpleVPBot_Bot_Reseller_Scope::resolve_reseller_id_for_notify( $user, null );
		}
		if ( $rid < 1 && class_exists( 'SimpleVPBot_Bot_Context' ) && SimpleVPBot_Bot_Context::is_reseller_bot() ) {
			$rid = (int) SimpleVPBot_Bot_Context::reseller_svp_user_id();
		}
		if ( $rid > 0 && class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			$ov = SimpleVPBot_Model_Reseller_Bot_Profile::get_text_override( $rid, $key, $loc );
			if ( '' !== $ov ) {
				return $ov;
			}
		}
		return self::get( $key, $default, $loc );
	}

	/**
	 * Text for current webhook bot (reseller overrides when in reseller context).
	 *
	 * @param string $key     Key.
	 * @param string $default Fallback.
	 * @return string
	 */
	public static function get_in_bot_context( $key, $default = '' ) {
		if ( class_exists( 'SimpleVPBot_Bot_Context' ) && SimpleVPBot_Bot_Context::is_reseller_bot() ) {
			$rid = (int) SimpleVPBot_Bot_Context::reseller_svp_user_id();
			if ( $rid > 0 && class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
				$loc = self::site_default_locale();
				$ov  = SimpleVPBot_Model_Reseller_Bot_Profile::get_text_override( $rid, $key, $loc );
				if ( '' !== $ov ) {
					return $ov;
				}
			}
		}
		return self::get( $key, $default );
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

	/**
	 * Localized label for keyboards/handlers.
	 *
	 * @param string      $key     Text key.
	 * @param object|null $user    Bot user row.
	 * @param string      $default Fallback.
	 * @return string
	 */
	public static function label( $key, $user = null, $default = '' ) {
		return ( $user && is_object( $user ) )
			? self::get_for_user( $key, $user, $default )
			: self::get( $key, $default );
	}
}
