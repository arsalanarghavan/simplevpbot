<?php
/**
 * Sample X-UI online client count per panel and store daily max.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Cron_Panel_Online
 */
class SimpleVPBot_Cron_Panel_Online {

	/**
	 * Count emails in onlines() API response.
	 *
	 * @param mixed $json Decoded JSON or array.
	 * @return int
	 */
	public static function count_onlines_response( $json ) {
		if ( ! is_array( $json ) ) {
			return 0;
		}
		if ( isset( $json['obj'] ) && is_array( $json['obj'] ) ) {
			return count( $json['obj'] );
		}
		if ( isset( $json['data'] ) && is_array( $json['data'] ) ) {
			return count( $json['data'] );
		}
		if ( array_values( $json ) === $json && ! empty( $json ) && is_string( $json[0] ?? null ) ) {
			return count( $json );
		}
		return 0;
	}

	/**
	 * Run cron: one sample per active panel.
	 */
	public static function run() {
		if ( ! class_exists( 'SimpleVPBot_Model_Panel' ) || ! class_exists( 'SimpleVPBot_Xui_Client' ) || ! class_exists( 'SimpleVPBot_Model_Panel_Online_Daily' ) ) {
			return;
		}
		$stat_date = SimpleVPBot_Admin_Dashboard_Stats::stat_date_for_offset( 0 );
		$panels    = SimpleVPBot_Model_Panel::all_active_ordered();
		foreach ( $panels as $pn ) {
			$pid = (int) $pn->id;
			if ( $pid < 1 ) {
				continue;
			}
			$n = (int) SimpleVPBot_Xui_Client::run_with_panel(
				$pid,
				function () {
					if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
						return 0;
					}
					$j = SimpleVPBot_Xui_Client::onlines();
					return self::count_onlines_response( $j );
				}
			);
			SimpleVPBot_Model_Panel_Online_Daily::upsert_max( $pid, $stat_date, $n );
		}
	}
}
