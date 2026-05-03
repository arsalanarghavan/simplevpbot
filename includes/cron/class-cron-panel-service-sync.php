<?php
/**
 * Panel snapshot checks for Xray services (logging only — never auto-delete local rows).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Cron_Panel_Service_Sync
 */
class SimpleVPBot_Cron_Panel_Service_Sync {

	/**
	 * Run: one inbound_get per (panel_id, inbound_id), log if email missing from a non-empty client list.
	 */
	public static function run() {
		if ( ! SimpleVPBot_Settings::get( 'enabled', true ) ) {
			return;
		}
		if ( ! class_exists( 'SimpleVPBot_Model_Service' ) || ! class_exists( 'SimpleVPBot_Xui_Client' ) ) {
			return;
		}
		$services = SimpleVPBot_Model_Service::all();
		$groups   = array();
		foreach ( $services as $svc ) {
			if ( ! is_object( $svc ) || SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
				continue;
			}
			$pid = max( 1, (int) ( $svc->panel_id ?? 1 ) );
			$iid = (int) $svc->inbound_id;
			if ( $iid < 1 ) {
				continue;
			}
			$key              = $pid . '|' . $iid;
			$groups[ $key ]   = isset( $groups[ $key ] ) ? $groups[ $key ] : array();
			$groups[ $key ][] = $svc;
		}
		foreach ( $groups as $group ) {
			if ( empty( $group ) || ! is_object( $group[0] ) ) {
				continue;
			}
			$first = $group[0];
			$pid   = max( 1, (int) ( $first->panel_id ?? 1 ) );
			$iid   = (int) $first->inbound_id;
			SimpleVPBot_Xui_Client::run_with_panel(
				$pid,
				function () use ( $group, $iid ) {
					if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
						return;
					}
					$inb = SimpleVPBot_Xui_Client::inbound_get( $iid );
					if ( ! is_array( $inb ) ) {
						return;
					}
					$settings = isset( $inb['settings'] ) ? $inb['settings'] : '';
					$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
					$clients_nonempty = is_array( $dec )
						&& ! empty( $dec['clients'] )
						&& is_array( $dec['clients'] )
						&& count( $dec['clients'] ) > 0;

					foreach ( $group as $svc ) {
						if ( ! is_object( $svc ) ) {
							continue;
						}
						$em = trim( (string) $svc->email );
						if ( '' === $em ) {
							continue;
						}
						$cl = SimpleVPBot_Xui_Client::inbound_client_by_email( $inb, $em );
						if ( is_array( $cl ) ) {
							continue;
						}
						if ( ! $clients_nonempty ) {
							continue;
						}
						if ( class_exists( 'SimpleVPBot_Logger' ) ) {
							SimpleVPBot_Logger::warning(
								'panel sync cron: email not in inbound snapshot (no auto-delete)',
								array(
									'service_id' => (int) $svc->id,
									'email'      => $em,
									'inbound_id' => $iid,
								)
							);
						}
					}
				}
			);
		}
	}
}
