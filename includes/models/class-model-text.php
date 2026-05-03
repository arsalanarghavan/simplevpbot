<?php
/**
 * Editable texts.
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
	 * Get value by key.
	 *
	 * @param string $key Key.
	 * @param string $default Default.
	 * @return string
	 */
	public static function get( $key, $default = '' ) {
		global $wpdb;
		$v = $wpdb->get_var( $wpdb->prepare( 'SELECT value FROM ' . self::table() . ' WHERE key_name = %s', $key ) ); // phpcs:ignore
		if ( null === $v || '' === $v ) {
			return $default;
		}
		return (string) $v;
	}

	/**
	 * Set value.
	 *
	 * @param string $key Key.
	 * @param string $value Value.
	 * @param string $category Category.
	 */
	public static function set( $key, $value, $category = 'general' ) {
		global $wpdb;
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
	 * All keys.
	 *
	 * @return array<int, object>
	 */
	public static function all() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY category ASC, key_name ASC' ); // phpcs:ignore
	}
}
