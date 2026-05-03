<?php
/**
 * Symmetric encryption for stored secrets (SSH keys, PSK, passwords).
 *
 * Key source: settings.portal_link_secret || wp_salt('auth').
 * Format: base64( IV(16) || ciphertext ) over AES-256-CBC.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Secret_Box
 */
class SimpleVPBot_Secret_Box {

	const CIPHER = 'aes-256-cbc';

	/**
	 * Derive 32-byte key.
	 *
	 * @return string
	 */
	private static function key() {
		$base = (string) SimpleVPBot_Settings::get( 'portal_link_secret', '' );
		if ( '' === $base && function_exists( 'wp_salt' ) ) {
			$base = (string) wp_salt( 'auth' );
		}
		if ( '' === $base ) {
			$base = 'simplevpbot-fallback-key';
		}
		return hash( 'sha256', 'svpbox:' . $base, true );
	}

	/**
	 * Encrypt plaintext; return base64( iv . ciphertext ) with "v1:" prefix.
	 *
	 * @param string $plain Plain.
	 * @return string
	 */
	public static function encrypt( $plain ) {
		$s = (string) $plain;
		if ( '' === $s ) {
			return '';
		}
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			return 'plain:' . base64_encode( $s );
		}
		$iv  = random_bytes( 16 );
		$ct  = openssl_encrypt( $s, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv );
		if ( false === $ct ) {
			return '';
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return 'v1:' . base64_encode( $iv . $ct );
	}

	/**
	 * Decrypt.
	 *
	 * @param string $cipher Cipher.
	 * @return string
	 */
	public static function decrypt( $cipher ) {
		$s = (string) $cipher;
		if ( '' === $s ) {
			return '';
		}
		if ( 0 === strpos( $s, 'plain:' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			return (string) base64_decode( substr( $s, 6 ) );
		}
		if ( 0 !== strpos( $s, 'v1:' ) ) {
			return '';
		}
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$raw = base64_decode( substr( $s, 3 ), true );
		if ( false === $raw || strlen( $raw ) < 17 ) {
			return '';
		}
		$iv = substr( $raw, 0, 16 );
		$ct = substr( $raw, 16 );
		$pt = openssl_decrypt( $ct, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv );
		return false === $pt ? '' : (string) $pt;
	}
}
