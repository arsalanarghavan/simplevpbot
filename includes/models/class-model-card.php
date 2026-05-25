<?php
/**
 * Bank card model.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Card
 */
class SimpleVPBot_Model_Card {
	/**
	 * Normalize legacy method aliases.
	 *
	 * @param string $raw Raw method key.
	 * @return string
	 */
	public static function normalize_method_key( $raw ) {
		$key = sanitize_key( (string) $raw );
		if ( 'mehr' === $key ) {
			return 'c2c';
		}
		if ( in_array( $key, array( 'c2c', 'crypto', 'crypto_auto' ), true ) ) {
			return $key;
		}
		return 'c2c';
	}

	/**
	 * Table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_cards';
	}

	/**
	 * Active cards ordered.
	 *
	 * @return array<int, object>
	 */
	public static function active_ordered() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' WHERE active = 1 ORDER BY priority DESC, id ASC' ); // phpcs:ignore
	}

	/**
	 * Active cards for specific owner scope.
	 *
	 * @param array<int> $owner_ids Owner ids (0 = global/site).
	 * @return array<int, object>
	 */
	public static function active_ordered_for_owners( array $owner_ids ) {
		global $wpdb;
		$owners = array_values( array_unique( array_map( 'intval', $owner_ids ) ) );
		if ( empty( $owners ) ) {
			return array();
		}
		$ph = implode( ',', array_fill( 0, count( $owners ), '%d' ) );
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . " WHERE active = 1 AND owner_svp_user_id IN ({$ph}) ORDER BY priority DESC, id ASC",
				$owners
			)
		); // phpcs:ignore
	}

	/**
	 * All.
	 *
	 * @return array<int, object>
	 */
	public static function all() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY priority DESC, id ASC' ); // phpcs:ignore
	}

	/**
	 * All cards for specific owner scope.
	 *
	 * @param array<int> $owner_ids Owner ids.
	 * @return array<int, object>
	 */
	public static function all_for_owners( array $owner_ids ) {
		global $wpdb;
		$owners = array_values( array_unique( array_map( 'intval', $owner_ids ) ) );
		if ( empty( $owners ) ) {
			return array();
		}
		$ph = implode( ',', array_fill( 0, count( $owners ), '%d' ) );
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . " WHERE owner_svp_user_id IN ({$ph}) ORDER BY priority DESC, id ASC",
				$owners
			)
		); // phpcs:ignore
	}

	/**
	 * Find.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function find( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) ); // phpcs:ignore
	}

	/**
	 * Insert.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return int
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$wpdb->insert( self::table(), $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update.
	 *
	 * @param int                  $id Id.
	 * @param array<string, mixed> $data Data.
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$wpdb->update( self::table(), $data, array( 'id' => $id ) );
	}

	/**
	 * Delete.
	 *
	 * @param int $id Id.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => $id ) );
	}

	/**
	 * Localized label for method_key (display only).
	 *
	 * @param object|array<string, mixed> $row Card row.
	 * @return string
	 */
	public static function method_label( $row ) {
		$key = is_object( $row ) ? (string) ( $row->method_key ?? 'c2c' ) : (string) ( $row['method_key'] ?? 'c2c' );
		$key = self::normalize_method_key( $key );
		$map = array(
			'c2c'         => 'کارت به کارت',
			'crypto'      => 'کریپتو (دستی)',
			'crypto_auto' => 'کریپتو (NOWPayments)',
		);
		if ( isset( $map[ $key ] ) ) {
			return $map[ $key ];
		}
		return '—';
	}

	/**
	 * Manual crypto: static wallet + receipt upload.
	 *
	 * @param object|array<string, mixed> $row Card row.
	 * @return bool
	 */
	public static function is_crypto_manual( $row ) {
		$key = is_object( $row ) ? (string) ( $row->method_key ?? '' ) : (string) ( $row['method_key'] ?? '' );
		$key = self::normalize_method_key( $key );
		return 'crypto' === $key;
	}

	/**
	 * Auto crypto via NOWPayments IPN.
	 *
	 * @param object|array<string, mixed> $row Card row.
	 * @return bool
	 */
	public static function is_crypto_auto( $row ) {
		$key = is_object( $row ) ? (string) ( $row->method_key ?? '' ) : (string) ( $row['method_key'] ?? '' );
		$key = self::normalize_method_key( $key );
		return 'crypto_auto' === $key;
	}

	/**
	 * Active cards for one checkout transaction.
	 * In sequential mode it returns one eligible card based on approved daily usage.
	 *
	 * @param int $transaction_id Transaction id.
	 * @return array<int, object>
	 */
	public static function active_for_transaction( $transaction_id ) {
		$tid   = (int) $transaction_id;
		$cards = array();
		$tx    = class_exists( 'SimpleVPBot_Model_Transaction' ) ? SimpleVPBot_Model_Transaction::find( $tid ) : null;
		if ( $tx ) {
			$meta = json_decode( (string) ( $tx->meta_json ?? '{}' ), true );
			if ( is_array( $meta ) && ! empty( $meta['invoice_card_owner_scope_svp_id'] ) ) {
				$scope_rid = (int) $meta['invoice_card_owner_scope_svp_id'];
				if ( $scope_rid > 0 ) {
					$cards = self::active_ordered_for_owners( array( 0, $scope_rid ) );
				}
			}
		}
		if ( empty( $cards ) && $tx && class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
			$uid = (int) ( $tx->user_id ?? 0 );
			$rid = $uid > 0 ? (int) SimpleVPBot_Reseller_Branding::nearest_reseller_id_for_user( $uid ) : 0;
			$cards = $rid > 0 ? self::active_ordered_for_owners( array( $rid, 0 ) ) : self::active_ordered_for_owners( array( 0 ) );
		}
		if ( empty( $cards ) ) {
			$cards = self::active_ordered();
		}
		if ( empty( $cards ) ) {
			return array();
		}
		$mode = class_exists( 'SimpleVPBot_Card_Rotation' )
			? SimpleVPBot_Card_Rotation::sanitize_display_mode( SimpleVPBot_Settings::get( 'cards_display_mode', 'list' ) )
			: 'list';
		if ( 'list' === $mode || ! class_exists( 'SimpleVPBot_Card_Rotation' ) ) {
			return $cards;
		}
		$scope_key = SimpleVPBot_Card_Rotation::resolve_owner_scope_key( $tid );
		return SimpleVPBot_Card_Rotation::pick_for_checkout( $cards, $mode, $scope_key, $tid );
	}

	/**
	 * Inline button text: bank + method.
	 *
	 * @param object|array<string, mixed> $row Card row.
	 * @return string
	 */
	public static function payment_button_label( $row ) {
		if ( self::is_crypto_manual( $row ) || self::is_crypto_auto( $row ) ) {
			$bn = trim( (string) ( is_object( $row ) ? ( $row->bank_name ?? '' ) : ( $row['bank_name'] ?? '' ) ) );
			if ( $bn === '' ) {
				$bn = self::is_crypto_auto( $row ) ? 'NOWPayments' : 'Crypto';
			}
			return '₿ ' . $bn . ' · ' . self::method_label( $row );
		}
		$bn = trim( (string) ( is_object( $row ) ? ( $row->bank_name ?? '' ) : ( $row['bank_name'] ?? '' ) ) );
		if ( $bn === '' ) {
			$raw  = (string) ( is_object( $row ) ? $row->card_number : $row['card_number'] );
			$pan  = preg_replace( '/\D+/', '', $raw );
			$bn  = '•••' . ( strlen( $pan ) >= 4 ? mb_substr( $pan, -4 ) : '____' );
		}
		return '🏦 ' . $bn . ' · ' . self::method_label( $row );
	}

	/**
	 * PAN digits grouped as 4-4-4-4 for copy/display.
	 *
	 * @param string $raw Raw card.
	 * @return string
	 */
	public static function card_number_grouped( $raw ) {
		$pan = preg_replace( '/\D+/', '', (string) $raw );
		$out = array();
		for ( $i = 0, $l = strlen( $pan ); $i < $l; $i += 4 ) {
			$out[] = substr( $pan, $i, 4 );
		}
		return $out ? implode( ' ', $out ) : '';
	}
}
