<?php
/**
 * User-facing display labels (name, greeting).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_User_Display
 */
class SimpleVPBot_User_Display {

	/**
	 * Friendly display name for notifications and placeholders.
	 *
	 * @param object|null $user svp_users row.
	 * @return string
	 */
	public static function name( $user ) {
		if ( ! $user || ! is_object( $user ) ) {
			return __( 'کاربر', 'simplevpbot' );
		}
		$name = trim( (string) ( $user->first_name ?? '' ) . ' ' . (string) ( $user->last_name ?? '' ) );
		if ( '' !== $name ) {
			return $name;
		}
		$uname = trim( (string) ( $user->username ?? '' ) );
		return '' !== $uname ? $uname : __( 'کاربر', 'simplevpbot' );
	}
}
