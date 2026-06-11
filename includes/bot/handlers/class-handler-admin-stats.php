<?php
/**
 * Bot admin stats facade.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Admin_Stats
 */
class SimpleVPBot_Handler_Admin_Stats {

	/**
	 * @param int $day_offset Day offset.
	 * @param int $admin_id   Admin user id.
	 * @return string
	 */
	public static function text_for_chat( $day_offset, $admin_id = 0 ) {
		return SimpleVPBot_Handler_Admin_Pnl::admin_stats_text_for_chat( $day_offset, $admin_id );
	}
}
