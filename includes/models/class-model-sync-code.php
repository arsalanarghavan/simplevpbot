<?php
/**
 * Sync code model.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Sync_Code
 */
class SimpleVPBot_Model_Sync_Code {

	/**
	 * Table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_sync_codes';
	}

	/**
	 * Create code for user.
	 *
	 * @param int    $user_id User id.
	 * @param string $bot tg|bale.
	 * @return string Code.
	 */
	public static function create( $user_id, $bot ) {
		global $wpdb;
		$t     = self::table();
		$tries = 0;
		do {
			$code = str_pad( (string) wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );
			$busy = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$t} WHERE code = %s AND consumed = 0 AND expires_at > UTC_TIMESTAMP()",
					$code
				)
			); // phpcs:ignore
			$tries++;
		} while ( $busy > 0 && $tries < 10 );

		$wpdb->insert(
			$t,
			array(
				'user_id'       => $user_id,
				'code'          => $code,
				'generated_bot' => $bot,
				'consumed'      => 0,
				'expires_at'    => gmdate( 'Y-m-d H:i:s', time() + 600 ),
			)
		);
		return $code;
	}

	/**
	 * Find valid code.
	 *
	 * @param string $code Code.
	 * @return object|null
	 */
	public static function find_valid( $code ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . " WHERE code = %s AND consumed = 0 AND expires_at > UTC_TIMESTAMP()",
				$code
			)
		); // phpcs:ignore
	}

	/**
	 * Mark consumed.
	 *
	 * @param int $id Id.
	 */
	public static function consume( $id ) {
		global $wpdb;
		$wpdb->update( self::table(), array( 'consumed' => 1 ), array( 'id' => $id ) );
	}
}
