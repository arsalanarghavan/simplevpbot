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

	/**
	 * Parse allowed_plan_ids JSON column.
	 *
	 * @param object|array<string, mixed>|null $row Row.
	 * @return array<int>
	 */
	public static function parse_allowed_plan_ids( $row ) {
		if ( ! $row ) {
			return array();
		}
		if ( is_string( $row ) ) {
			$raw = $row;
		} else {
			$raw = is_object( $row ) ? ( $row->allowed_plan_ids ?? '' ) : ( $row['allowed_plan_ids'] ?? '' );
		}
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return array();
		}
		$dec = json_decode( $raw, true );
		if ( ! is_array( $dec ) ) {
			return array();
		}
		$out = array();
		foreach ( $dec as $pid ) {
			$n = (int) $pid;
			if ( $n > 0 ) {
				$out[] = $n;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Encode plan id list for storage.
	 *
	 * @param array<int> $plan_ids Plan ids.
	 * @return string|null
	 */
	public static function encode_allowed_plan_ids( array $plan_ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $plan_ids ) ) ) );
		if ( empty( $ids ) ) {
			return null;
		}
		return wp_json_encode( $ids );
	}

	/**
	 * Active codes for owner that share any plan id with candidate list.
	 *
	 * @param int        $owner_svp_user_id Owner.
	 * @param array<int> $plan_ids Plan ids.
	 * @param int        $exclude_id Code id to skip (edit).
	 * @return array<int, object>
	 */
	public static function active_with_plan_overlap( $owner_svp_user_id, array $plan_ids, $exclude_id = 0 ) {
		$plan_ids = array_values( array_unique( array_filter( array_map( 'intval', $plan_ids ) ) ) );
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE active = 1 AND owner_svp_user_id = %d AND id <> %d',
				(int) $owner_svp_user_id,
				(int) $exclude_id
			)
		); // phpcs:ignore
		$out = array();
		foreach ( (array) $rows as $row ) {
			if ( ! $row ) {
				continue;
			}
			$allowed = self::parse_allowed_plan_ids( $row );
			if ( empty( $plan_ids ) || empty( $allowed ) ) {
				$out[] = $row;
				continue;
			}
			foreach ( $plan_ids as $pid ) {
				if ( in_array( (int) $pid, $allowed, true ) ) {
					$out[] = $row;
					break;
				}
			}
		}
		return $out;
	}
}

