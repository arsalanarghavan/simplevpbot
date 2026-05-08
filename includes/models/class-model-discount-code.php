<?php
/**
 * Discount code model.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Discount_Code
 */
class SimpleVPBot_Model_Discount_Code {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_discount_codes';
	}

	/**
	 * Normalize code for storage/lookup.
	 *
	 * @param string $code Raw.
	 * @return string
	 */
	public static function normalize_code( $code ) {
		return strtoupper( preg_replace( '/\s+/', '', trim( (string) $code ) ) );
	}

	/**
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function find( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ) ); // phpcs:ignore
	}

	/**
	 * @param string $code Normalized or raw.
	 * @return object|null
	 */
	public static function find_by_code( $code, $owner_svp_user_id = null ) {
		$c = self::normalize_code( $code );
		if ( '' === $c ) {
			return null;
		}
		global $wpdb;
		if ( null !== $owner_svp_user_id ) {
			return $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM ' . self::table() . ' WHERE code = %s AND owner_svp_user_id = %d',
					$c,
					(int) $owner_svp_user_id
				)
			); // phpcs:ignore
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE code = %s', $c ) ); // phpcs:ignore
	}

	/**
	 * Locate code by owner priority.
	 *
	 * @param string     $code Normalized/raw code.
	 * @param array<int> $owner_candidates Ordered owner ids.
	 * @return object|null
	 */
	public static function find_by_code_for_owners( $code, array $owner_candidates ) {
		$c = self::normalize_code( $code );
		if ( '' === $c ) {
			return null;
		}
		$owners = array_values( array_unique( array_map( 'intval', $owner_candidates ) ) );
		foreach ( $owners as $oid ) {
			$row = self::find_by_code( $c, $oid );
			if ( $row ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * @return array<int, object>
	 */
	public static function all_ordered() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY id DESC' ); // phpcs:ignore
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @return int Insert id.
	 */
	public static function insert( array $data ) {
		global $wpdb;
		if ( isset( $data['code'] ) ) {
			$data['code'] = self::normalize_code( (string) $data['code'] );
		}
		$wpdb->insert( self::table(), $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int                  $id Id.
	 * @param array<string, mixed> $data Data.
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		if ( isset( $data['code'] ) ) {
			$data['code'] = self::normalize_code( (string) $data['code'] );
		}
		$wpdb->update( self::table(), $data, array( 'id' => (int) $id ) );
	}

	/**
	 * Increment uses_count (called once per successful payment).
	 *
	 * @param int $id Code id.
	 */
	public static function increment_uses( $id ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . ' SET uses_count = uses_count + 1 WHERE id = %d', (int) $id ) ); // phpcs:ignore
	}

	/**
	 * @param int $id Id.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $id ) );
	}
}
