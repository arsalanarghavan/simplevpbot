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
	 * Cache.
	 *
	 * @var array<string, string>
	 */
	private static $cache = array();

	/**
	 * Get text by key.
	 *
	 * @param string $key Key.
	 * @param string $default Default.
	 * @return string
	 */
	public static function get( $key, $default = '' ) {
		if ( array_key_exists( $key, self::$cache ) ) {
			$v = self::$cache[ $key ];
		} else {
			$v = SimpleVPBot_Model_Text::get( $key, '' );
			self::$cache[ $key ] = $v;
		}
		if ( '' === $v ) {
			return (string) $default;
		}
		return (string) $v;
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
