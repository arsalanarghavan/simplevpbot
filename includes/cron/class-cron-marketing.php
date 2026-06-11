<?php
/**
 * Marketing lifecycle automation cron.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Cron_Marketing
 */
class SimpleVPBot_Cron_Marketing {

	/**
	 * Hourly marketing offers.
	 */
	public static function run() {
		if ( ! class_exists( 'SimpleVPBot_Marketing_Automation' ) ) {
			return;
		}
		SimpleVPBot_Marketing_Automation::run_cron();
	}
}
