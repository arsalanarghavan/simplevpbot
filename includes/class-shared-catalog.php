<?php
/**
 * Loads shared JSON locale files (same source as dashboard-ui export).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Shared_Catalog
 */
class SimpleVPBot_Shared_Catalog {

	/**
	 * Loaded trees per locale.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static $trees = array();

	/**
	 * Normalize locale code.
	 *
	 * @param string $locale Locale.
	 * @return string
	 */
	private static function norm_locale( $locale ) {
		$locale = strtolower( (string) $locale );
		return in_array( $locale, array( 'fa', 'en' ), true ) ? $locale : 'en';
	}

	/**
	 * Load JSON tree for locale.
	 *
	 * @param string $locale Locale.
	 * @return array<string, mixed>
	 */
	private static function tree( $locale ) {
		$locale = self::norm_locale( $locale );
		if ( isset( self::$trees[ $locale ] ) ) {
			return self::$trees[ $locale ];
		}
		$path = SIMPLEVPBOT_PLUGIN_DIR . 'shared/locales/' . $locale . '.json';
		if ( ! is_readable( $path ) ) {
			self::$trees[ $locale ] = array();
			return self::$trees[ $locale ];
		}
		$raw = file_get_contents( $path );
		$dec = is_string( $raw ) ? json_decode( $raw, true ) : null;
		self::$trees[ $locale ] = is_array( $dec ) ? $dec : array();
		return self::$trees[ $locale ];
	}

	/**
	 * Get nested value by dot path.
	 *
	 * @param array<string, mixed> $data Data.
	 * @param array<int, string>   $parts Key parts.
	 * @return string|null
	 */
	private static function dig( array $data, array $parts ) {
		$cur = $data;
		foreach ( $parts as $p ) {
			if ( ! is_array( $cur ) || ! array_key_exists( $p, $cur ) ) {
				return null;
			}
			$cur = $cur[ $p ];
		}
		return is_string( $cur ) ? $cur : null;
	}

	/**
	 * Translate dashboard catalog key (e.g. dashboardLogin.title).
	 *
	 * @param string               $key Dot key relative to locale JSON root.
	 * @param string               $locale en|fa.
	 * @param array<string, string> $vars Interpolation {{name}} and {name}.
	 * @return string
	 */
	public static function t( $key, $locale = 'en', array $vars = array() ) {
		$parts = explode( '.', (string) $key );
		$parts = array_values( array_filter( $parts, 'strlen' ) );
		if ( empty( $parts ) ) {
			return '';
		}
		$locale = self::norm_locale( $locale );
		$val    = self::dig( self::tree( $locale ), $parts );
		if ( null === $val && 'en' !== $locale ) {
			$val = self::dig( self::tree( 'en' ), $parts );
		}
		if ( null === $val ) {
			return (string) $key;
		}
		foreach ( $vars as $k => $v ) {
			$val = str_replace( '{{' . $k . '}}', (string) $v, $val );
			$val = str_replace( '{' . $k . '}', (string) $v, $val );
		}
		return (string) $val;
	}

	/**
	 * Clear in-memory cache (tests).
	 */
	public static function clear_cache() {
		self::$trees = array();
	}
}
