<?php
/**
 * L2TP server model (one row per backend L2TP VPS).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_L2TP_Server
 */
class SimpleVPBot_Model_L2TP_Server {

	/**
	 * Encrypted columns.
	 *
	 * @var array<int, string>
	 */
	private static $enc_cols = array(
		'ssh_password_enc',
		'ssh_private_key_enc',
		'ssh_key_passphrase_enc',
		'l2tp_psk_enc',
	);

	/**
	 * Table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_l2tp_servers';
	}

	/**
	 * Find.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function find( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ) ); // phpcs:ignore
	}

	/**
	 * All rows ordered.
	 *
	 * @return array<int, object>
	 */
	public static function all() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY id DESC' ); // phpcs:ignore
	}

	/**
	 * Active rows.
	 *
	 * @return array<int, object>
	 */
	public static function active() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' WHERE active = 1 ORDER BY id ASC' ); // phpcs:ignore
	}

	/**
	 * Insert with automatic encryption of `_enc` columns (value given as plain text).
	 *
	 * @param array<string, mixed> $data Plain values for encrypted columns, raw for others.
	 * @return int
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$row = self::encrypt_row( $data );
		$wpdb->insert( self::table(), $row );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update: only touches fields that are present in $data. For encrypted fields,
	 * a value of NULL leaves the stored ciphertext unchanged (so UI can send "empty to keep").
	 *
	 * @param int                  $id Id.
	 * @param array<string, mixed> $data Data.
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$filtered = array();
		foreach ( $data as $k => $v ) {
			if ( in_array( $k, self::$enc_cols, true ) ) {
				if ( null === $v || '' === $v ) {
					continue;
				}
				$filtered[ $k ] = SimpleVPBot_Secret_Box::encrypt( (string) $v );
				continue;
			}
			$filtered[ $k ] = $v;
		}
		if ( ! empty( $filtered ) ) {
			$wpdb->update( self::table(), $filtered, array( 'id' => (int) $id ) );
		}
	}

	/**
	 * Delete.
	 *
	 * @param int $id Id.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $id ) );
	}

	/**
	 * Return a row with its encrypted fields decrypted into plaintext siblings
	 * (e.g. `ssh_password`, `ssh_private_key`, `ssh_key_passphrase`, `l2tp_psk`).
	 *
	 * @param object|null $row Row.
	 * @return object|null
	 */
	public static function decrypted( $row ) {
		if ( ! $row ) {
			return $row;
		}
		$r = is_object( $row ) ? clone $row : (object) $row;
		foreach ( self::$enc_cols as $col ) {
			$plain_key = preg_replace( '/_enc$/', '', $col );
			$cipher    = isset( $r->{$col} ) ? (string) $r->{$col} : '';
			$r->{$plain_key} = $cipher !== '' ? (string) SimpleVPBot_Secret_Box::decrypt( $cipher ) : '';
		}
		return $r;
	}

	/**
	 * Encrypt plain-text values for `_enc` columns in $data.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return array<string, mixed>
	 */
	private static function encrypt_row( array $data ) {
		foreach ( self::$enc_cols as $col ) {
			if ( array_key_exists( $col, $data ) ) {
				$v = (string) $data[ $col ];
				$data[ $col ] = $v !== '' ? SimpleVPBot_Secret_Box::encrypt( $v ) : '';
			}
		}
		return $data;
	}
}
