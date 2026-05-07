<?php
/**
 * Periodically sync X-UI inbound client rows into DB cache for dashboard configs.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Cron_Inbound_Clients_Cache
 */
class SimpleVPBot_Cron_Inbound_Clients_Cache {

	/**
	 * Run: one non-forced sync per active panel (respects lock / recent skip in service).
	 */
	public static function run() {
		if ( ! class_exists( 'SimpleVPBot_Model_Panel' ) || ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return;
		}
		$panels = SimpleVPBot_Model_Panel::all_active_ordered();
		foreach ( $panels as $pn ) {
			$pid = (int) $pn->id;
			if ( $pid < 1 ) {
				continue;
			}
			SimpleVPBot_Service_Admin_Ops::configs_sync_panel_to_db( $pid, false );
		}
	}
}
