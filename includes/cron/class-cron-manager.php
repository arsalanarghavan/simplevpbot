<?php
/**
 * WP-Cron registration.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Cron_Manager
 */
class SimpleVPBot_Cron_Manager {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'simplevpbot_cron_backup', array( 'SimpleVPBot_Cron_Backup', 'run' ) );
		add_action( 'simplevpbot_cron_expiry', array( 'SimpleVPBot_Cron_Expiry', 'run' ) );
		add_action( 'simplevpbot_cron_autorenew', array( 'SimpleVPBot_Cron_Autorenew', 'run' ) );
		add_action( 'simplevpbot_cron_broadcast', array( 'SimpleVPBot_Cron_Broadcast', 'run' ) );
		add_action( 'simplevpbot_cron_panel_online', array( 'SimpleVPBot_Cron_Panel_Online', 'run' ) );
		add_action( 'simplevpbot_cron_panel_service_sync', array( 'SimpleVPBot_Cron_Panel_Service_Sync', 'run' ) );
		add_action( 'simplevpbot_cron_idle_offers', array( 'SimpleVPBot_Cron_Idle_Offers', 'run' ) );
		add_action( 'simplevpbot_cron_admin_alerts', array( 'SimpleVPBot_Cron_Admin_Alerts', 'run' ) );
		add_action( 'init', array( __CLASS__, 'ensure_panel_sync_scheduled' ), 30 );
		add_action( 'init', array( __CLASS__, 'ensure_broadcast_cron_scheduled' ), 32 );
		add_action( 'init', array( __CLASS__, 'ensure_aux_crons_scheduled' ), 35 );
	}

	/**
	 * Sites upgraded without re-activation still need the new cron registered once.
	 */
	public static function ensure_panel_sync_scheduled() {
		if ( wp_next_scheduled( 'simplevpbot_cron_panel_service_sync' ) ) {
			return;
		}
		$schedules = wp_get_schedules();
		$po        = isset( $schedules['simplevpbot_10min'] ) ? 'simplevpbot_10min' : 'hourly';
		wp_schedule_event( time() + 120, $po, 'simplevpbot_cron_panel_service_sync' );
	}

	/**
	 * Re-register broadcast worker if the scheduled event was lost (migration, etc.).
	 */
	public static function ensure_broadcast_cron_scheduled() {
		if ( wp_next_scheduled( 'simplevpbot_cron_broadcast' ) ) {
			return;
		}
		$schedules          = wp_get_schedules();
		$broadcast_interval = isset( $schedules['simplevpbot_minute'] ) ? 'simplevpbot_minute' : 'hourly';
		wp_schedule_event( time() + 120, $broadcast_interval, 'simplevpbot_cron_broadcast' );
	}

	/**
	 * Schedule all crons.
	 */
	public static function schedule_all() {
		self::clear_backup();
		$name = SimpleVPBot_Settings::backup_schedule_name();
		$schedules = wp_get_schedules();
		$interval = isset( $schedules[ $name ] ) ? $name : 'hourly';
		if ( ! wp_next_scheduled( 'simplevpbot_cron_backup' ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, $interval, 'simplevpbot_cron_backup' );
		}
		if ( ! wp_next_scheduled( 'simplevpbot_cron_expiry' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'simplevpbot_cron_expiry' );
		}
		if ( ! wp_next_scheduled( 'simplevpbot_cron_autorenew' ) ) {
			wp_schedule_event( time() + 600, 'hourly', 'simplevpbot_cron_autorenew' );
		}
		if ( ! wp_next_scheduled( 'simplevpbot_cron_broadcast' ) ) {
			// Prefer real system cron hitting wp-cron.php for large broadcast queues; WP schedules every minute when simplevpbot_minute is registered.
			$broadcast_interval = isset( $schedules['simplevpbot_minute'] ) ? 'simplevpbot_minute' : 'hourly';
			wp_schedule_event( time() + 120, $broadcast_interval, 'simplevpbot_cron_broadcast' );
		}
		if ( ! wp_next_scheduled( 'simplevpbot_cron_panel_online' ) ) {
			$po = isset( $schedules['simplevpbot_10min'] ) ? 'simplevpbot_10min' : 'hourly';
			wp_schedule_event( time() + 420, $po, 'simplevpbot_cron_panel_online' );
		}
		if ( ! wp_next_scheduled( 'simplevpbot_cron_panel_service_sync' ) ) {
			$po = isset( $schedules['simplevpbot_10min'] ) ? 'simplevpbot_10min' : 'hourly';
			wp_schedule_event( time() + 480, $po, 'simplevpbot_cron_panel_service_sync' );
		}
		if ( ! wp_next_scheduled( 'simplevpbot_cron_idle_offers' ) ) {
			wp_schedule_event( time() + 660, 'hourly', 'simplevpbot_cron_idle_offers' );
		}
		if ( ! wp_next_scheduled( 'simplevpbot_cron_admin_alerts' ) ) {
			$po = isset( $schedules['simplevpbot_10min'] ) ? 'simplevpbot_10min' : 'hourly';
			wp_schedule_event( time() + 500, $po, 'simplevpbot_cron_admin_alerts' );
		}
	}

	/**
	 * Register idle + admin alert crons on upgrades that never re-ran activation.
	 */
	public static function ensure_aux_crons_scheduled() {
		$schedules = wp_get_schedules();
		if ( ! wp_next_scheduled( 'simplevpbot_cron_idle_offers' ) ) {
			wp_schedule_event( time() + 660, 'hourly', 'simplevpbot_cron_idle_offers' );
		}
		if ( ! wp_next_scheduled( 'simplevpbot_cron_admin_alerts' ) ) {
			$po = isset( $schedules['simplevpbot_10min'] ) ? 'simplevpbot_10min' : 'hourly';
			wp_schedule_event( time() + 500, $po, 'simplevpbot_cron_admin_alerts' );
		}
	}

	/**
	 * Reschedule backup when interval changes.
	 */
	public static function clear_backup() {
		wp_clear_scheduled_hook( 'simplevpbot_cron_backup' );
	}
}
