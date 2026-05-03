<?php
/**
 * Referral /start deep-link events.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Referral_Event
 */
class SimpleVPBot_Model_Referral_Event {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_referral_events';
	}

	/**
	 * Insert one row.
	 *
	 * @param array<string, mixed> $data Row.
	 * @return int Insert id.
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$wpdb->insert( self::table(), $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Total rows.
	 *
	 * @return int
	 */
	public static function count_all() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() ); // phpcs:ignore
	}

	/**
	 * Count rows since datetime (inclusive).
	 *
	 * @param string $since_mysql MySQL datetime.
	 * @return int
	 */
	public static function count_since( $since_mysql ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table() . ' WHERE created_at >= %s',
				$since_mysql
			)
		); // phpcs:ignore
	}

	/**
	 * Recent rows newest first.
	 *
	 * @param int $limit Limit.
	 * @param int $offset Offset.
	 * @return array<int, object>
	 */
	public static function list_desc( $limit, $offset ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d OFFSET %d',
				$limit,
				$offset
			)
		); // phpcs:ignore
	}
}
