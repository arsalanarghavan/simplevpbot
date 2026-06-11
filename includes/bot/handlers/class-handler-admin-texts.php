<?php
/**
 * Bot admin texts facade.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Admin_Texts
 */
class SimpleVPBot_Handler_Admin_Texts {

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 * @param int    $offset   Offset.
	 */
	public static function open_tab( $platform, $chat_id, $user, $offset = 0 ) {
		SimpleVPBot_Handler_Admin_Pnl::open_texts_tab( $platform, $chat_id, $user, $offset );
	}
}
