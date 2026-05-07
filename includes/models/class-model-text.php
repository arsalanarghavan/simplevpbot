<?php
/**
 * Editable texts (per locale).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Text
 */
class SimpleVPBot_Model_Text {

	/**
	 * Table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_texts';
	}

	/**
	 * Whether locale column exists (post–2.0.6).
	 *
	 * @return bool
	 */
	public static function has_locale_column() {
		global $wpdb;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'locale'" );
	}

	/**
	 * Normalize locale to fa|en.
	 *
	 * @param string $locale Locale.
	 * @return string
	 */
	public static function normalize_locale( $locale ) {
		$l = strtolower( trim( (string) $locale ) );
		return ( 'en' === $l ) ? 'en' : 'fa';
	}

	/**
	 * Get value by key and locale.
	 *
	 * @param string $key      Key.
	 * @param string $default  Default.
	 * @param string $locale   fa|en (ignored when locale column missing — reads legacy row).
	 * @return string
	 */
	public static function get( $key, $default = '', $locale = 'fa' ) {
		global $wpdb;
		$loc = self::normalize_locale( $locale );
		if ( self::has_locale_column() ) {
			$v = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT value FROM ' . self::table() . ' WHERE key_name = %s AND locale = %s',
					$key,
					$loc
				)
			); // phpcs:ignore
		} else {
			$v = $wpdb->get_var( $wpdb->prepare( 'SELECT value FROM ' . self::table() . ' WHERE key_name = %s', $key ) ); // phpcs:ignore
		}
		if ( null === $v || '' === $v ) {
			return $default;
		}
		return (string) $v;
	}

	/**
	 * Set value (upsert by key + locale when supported).
	 *
	 * @param string $key      Key.
	 * @param string $value    Value.
	 * @param string $category Category.
	 * @param string $locale   fa|en.
	 */
	public static function set( $key, $value, $category = 'general', $locale = 'fa' ) {
		global $wpdb;
		$loc = self::normalize_locale( $locale );
		if ( self::has_locale_column() ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM ' . self::table() . ' WHERE key_name = %s AND locale = %s',
					$key,
					$loc
				)
			); // phpcs:ignore
			if ( $exists ) {
				$wpdb->update(
					self::table(),
					array( 'value' => $value ),
					array(
						'key_name' => $key,
						'locale'   => $loc,
					)
				);
			} else {
				$wpdb->insert(
					self::table(),
					array(
						'key_name' => $key,
						'category' => $category,
						'locale'   => $loc,
						'value'    => $value,
					)
				);
			}
		} else {
			$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table() . ' WHERE key_name = %s', $key ) ); // phpcs:ignore
			if ( $exists ) {
				$wpdb->update( self::table(), array( 'value' => $value ), array( 'key_name' => $key ) );
			} else {
				$wpdb->insert(
					self::table(),
					array(
						'key_name' => $key,
						'category' => $category,
						'value'    => $value,
					)
				);
			}
		}
	}

	/**
	 * All rows grouped by key_name with valueFa / valueEn for dashboard.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all_grouped_by_key() {
		global $wpdb;
		if ( ! self::has_locale_column() ) {
			$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY category ASC, key_name ASC' ); // phpcs:ignore
			$out  = array();
			foreach ( (array) $rows as $r ) {
				$out[] = array(
					'id'         => (int) ( $r->id ?? 0 ),
					'key_name'   => (string) ( $r->key_name ?? '' ),
					'category'   => (string) ( $r->category ?? 'general' ),
					'value_fa'   => (string) ( $r->value ?? '' ),
					'value_en'   => '',
					'updated_at' => (string) ( $r->updated_at ?? '' ),
				);
			}
			return $out;
		}
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY category ASC, key_name ASC, locale ASC' ); // phpcs:ignore
		/** @var array<string, array<string, mixed>> $by */
		$by = array();
		foreach ( (array) $rows as $r ) {
			$kn = (string) ( $r->key_name ?? '' );
			if ( '' === $kn ) {
				continue;
			}
			if ( ! isset( $by[ $kn ] ) ) {
				$by[ $kn ] = array(
					'id'         => (int) ( $r->id ?? 0 ),
					'key_name'   => $kn,
					'category'   => (string) ( $r->category ?? 'general' ),
					'value_fa'   => '',
					'value_en'   => '',
					'updated_at' => (string) ( $r->updated_at ?? '' ),
				);
			}
			$loc = (string) ( $r->locale ?? 'fa' );
			if ( 'en' === $loc ) {
				$by[ $kn ]['value_en'] = (string) ( $r->value ?? '' );
			} else {
				$by[ $kn ]['value_fa'] = (string) ( $r->value ?? '' );
			}
			$t = (string) ( $r->updated_at ?? '' );
			if ( $t !== '' && $t > (string) ( $by[ $kn ]['updated_at'] ?? '' ) ) {
				$by[ $kn ]['updated_at'] = $t;
			}
		}
		return array_values( $by );
	}

	/**
	 * All by category.
	 *
	 * @param string $category Category.
	 * @return array<int, object>
	 */
	public static function by_category( $category ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE category = %s ORDER BY key_name ASC', $category ) ); // phpcs:ignore
	}

	/**
	 * All keys (raw rows).
	 *
	 * @return array<int, object>
	 */
	public static function all() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY category ASC, key_name ASC, locale ASC' ); // phpcs:ignore
	}
}
