<?php
/**
 * Plugin deactivation.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Deactivator
 */
class SimpleVPBot_Deactivator {

	/**
	 * Deactivate.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'simplevpbot_cron_backup' );
		wp_clear_scheduled_hook( 'simplevpbot_cron_expiry' );
		wp_clear_scheduled_hook( 'simplevpbot_cron_autorenew' );
		wp_clear_scheduled_hook( 'simplevpbot_cron_broadcast' );
		wp_clear_scheduled_hook( 'simplevpbot_cron_panel_online' );
		wp_clear_scheduled_hook( 'simplevpbot_cron_panel_service_sync' );
	}
}
