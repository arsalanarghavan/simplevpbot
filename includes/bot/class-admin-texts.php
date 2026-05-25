<?php
/**
 * Admin bot message helper (locale from svp_users row).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Bot_Admin_Texts
 */
class SimpleVPBot_Bot_Admin_Texts {

	/**
	 * Localized string for an admin user row.
	 *
	 * @param string               $key     Text key.
	 * @param object|null          $user    svp_users admin row.
	 * @param array<string,string> $vars    Placeholders.
	 * @param string               $default Fallback when DB/seed empty.
	 * @return string
	 */
	public static function msg( $key, $user, array $vars = array(), $default = '' ) {
		$t = SimpleVPBot_Texts::get_for_user( $key, $user, $default );
		return empty( $vars ) ? $t : SimpleVPBot_Texts::format( $t, $vars );
	}
}
