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

	const CRON_PING_LOCK_TRANSIENT = 'simplevpbot_cron_ping_lock';

	const LAST_CRON_PING_OPTION = 'simplevpbot_last_cron_ping_at';

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'simplevpbot_cron_backup', array( 'SimpleVPBot_Cron_Backup', 'run' ) );
		add_action( 'simplevpbot_manual_backup', array( 'SimpleVPBot_Service_Admin_Ops', 'run_manual_backup_job' ) );
		add_action( 'simplevpbot_cron_expiry', array( 'SimpleVPBot_Cron_Expiry', 'run' ) );
		add_action( 'simplevpbot_cron_purge_expired', array( 'SimpleVPBot_Cron_Purge_Expired', 'run' ) );
		add_action( 'simplevpbot_cron_autorenew', array( 'SimpleVPBot_Cron_Autorenew', 'run' ) );
		add_action( 'simplevpbot_cron_broadcast', array( 'SimpleVPBot_Cron_Broadcast', 'run' ) );
		add_action( 'simplevpbot_cron_users_bulk', array( 'SimpleVPBot_Cron_Users_Bulk', 'run' ) );
		add_action( 'simplevpbot_cron_panel_online', array( 'SimpleVPBot_Cron_Panel_Online', 'run' ) );
		add_action( 'simplevpbot_cron_panel_service_sync', array( 'SimpleVPBot_Cron_Panel_Service_Sync', 'run' ) );
		add_action( 'simplevpbot_cron_inbound_clients_cache', array( 'SimpleVPBot_Cron_Inbound_Clients_Cache', 'run' ) );
		add_action( 'simplevpbot_cron_idle_offers', array( 'SimpleVPBot_Cron_Idle_Offers', 'run' ) );
		add_action( 'simplevpbot_cron_marketing', array( 'SimpleVPBot_Cron_Marketing', 'run' ) );
		add_action( 'simplevpbot_cron_admin_alerts', array( 'SimpleVPBot_Cron_Admin_Alerts', 'run' ) );
		add_action( 'simplevpbot_cron_panel_economics_renewal', array( 'SimpleVPBot_Cron_Panel_Economics_Renewal', 'run' ) );
		add_action( 'init', array( __CLASS__, 'ensure_panel_sync_scheduled' ), 30 );
		add_action( 'init', array( __CLASS__, 'ensure_broadcast_cron_scheduled' ), 32 );
		add_action( 'init', array( __CLASS__, 'ensure_users_bulk_cron_scheduled' ), 33 );
		add_action( 'init', array( __CLASS__, 'ensure_aux_crons_scheduled' ), 35 );
		add_action( 'init', array( __CLASS__, 'ensure_inbound_queue_cron_scheduled' ), 37 );
		add_action( 'init', array( __CLASS__, 'ensure_backup_cron_scheduled' ), 34 );
		add_action( 'init', array( __CLASS__, 'ensure_inbound_clients_cache_scheduled' ), 36 );
		add_action( 'init', array( __CLASS__, 'maybe_ping_wp_cron_throttled' ), 40 );
	}

	/**
	 * Seconds between automatic wp-cron.php loopback pings (filterable).
	 *
	 * @return int
	 */
	public static function cron_ping_interval_seconds() {
		$secs = (int) apply_filters( 'simplevpbot_cron_ping_interval_seconds', 120 );
		return max( 60, $secs );
	}

	/**
	 * Suggested server crontab line for this site (external wp-cron when DISABLE_WP_CRON).
	 *
	 * @return string
	 */
	public static function server_crontab_line() {
		$url = add_query_arg( 'doing_wp_cron', '1', site_url( 'wp-cron.php' ) );
		return '*/5 * * * * curl -fsS -m 30 -o /dev/null "' . $url . '"';
	}

	/**
	 * Non-blocking loopback to wp-cron.php so due jobs run without waiting for unrelated traffic.
	 *
	 * Works when DISABLE_WP_CRON is true (external ping is required in that setup).
	 *
	 * @return bool True if a request was dispatched.
	 */
	public static function ping_wp_cron_loopback() {
		if ( apply_filters( 'simplevpbot_skip_cron_ping', false ) ) {
			return false;
		}
		if ( defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON ) {
			return false;
		}
		$url = add_query_arg( 'doing_wp_cron', wp_generate_password( 12, false, false ), site_url( 'wp-cron.php' ) );
		wp_remote_post(
			$url,
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			)
		);
		update_option( self::LAST_CRON_PING_OPTION, time(), false );
		return true;
	}

	/**
	 * Rate-limited cron ping (init, webhook shutdown, etc.).
	 */
	public static function maybe_ping_wp_cron_throttled() {
		if ( ! class_exists( 'SimpleVPBot_Settings' ) || ! SimpleVPBot_Settings::get( 'enabled', true ) ) {
			return;
		}
		if ( get_transient( self::CRON_PING_LOCK_TRANSIENT ) ) {
			return;
		}
		$interval = self::cron_ping_interval_seconds();
		set_transient( self::CRON_PING_LOCK_TRANSIENT, 1, $interval );
		self::ping_wp_cron_loopback();
	}

	/**
	 * Ping wp-cron once after the current HTTP request finishes (e.g. Telegram webhook).
	 */
	public static function schedule_ping_on_shutdown() {
		static $registered = false;
		if ( $registered ) {
			return;
		}
		$registered = true;
		add_action( 'shutdown', array( __CLASS__, 'maybe_ping_wp_cron_throttled' ), 5 );
	}

	/**
	 * Schedule name currently used by the backup WP-Cron event (empty if not scheduled).
	 *
	 * @return string
	 */
	public static function get_backup_cron_schedule() {
		$crons = _get_cron_array();
		if ( ! is_array( $crons ) ) {
			return '';
		}
		foreach ( $crons as $hooks ) {
			if ( ! is_array( $hooks ) || ! isset( $hooks['simplevpbot_cron_backup'] ) ) {
				continue;
			}
			$events = $hooks['simplevpbot_cron_backup'];
			if ( ! is_array( $events ) ) {
				continue;
			}
			foreach ( $events as $event ) {
				if ( is_array( $event ) && isset( $event['schedule'] ) ) {
					return (string) $event['schedule'];
				}
			}
		}
		return '';
	}

	/**
	 * Backup cron diagnostics for dashboard.
	 *
	 * @return array{registered:bool, next_at:int, schedule:string, wanted_schedule:string, interval_minutes:int}
	 */
	public static function backup_cron_diagnostics() {
		$wanted = class_exists( 'SimpleVPBot_Settings' ) ? SimpleVPBot_Settings::backup_schedule_name() : '';
		$next   = (int) wp_next_scheduled( 'simplevpbot_cron_backup' );
		return array(
			'registered'      => $next > 0,
			'next_at'         => $next,
			'schedule'        => self::get_backup_cron_schedule(),
			'wanted_schedule' => $wanted,
			'interval_minutes' => class_exists( 'SimpleVPBot_Settings' )
				? max( 5, (int) SimpleVPBot_Settings::get( 'backup_interval_minutes', 60 ) )
				: 60,
		);
	}

	/**
	 * Register or fix backup cron interval (lost events, interval drift after upgrades).
	 */
	public static function ensure_backup_cron_scheduled() {
		if ( ! class_exists( 'SimpleVPBot_Settings' ) ) {
			return;
		}
		$wanted    = SimpleVPBot_Settings::backup_schedule_name();
		$schedules = wp_get_schedules();
		$interval  = isset( $schedules[ $wanted ] ) ? $wanted : 'hourly';
		$current   = self::get_backup_cron_schedule();
		$next      = wp_next_scheduled( 'simplevpbot_cron_backup' );
		if ( ! $next || $current !== $interval ) {
			self::schedule_backup_event( $interval );
		}
	}

	/**
	 * (Re)schedule backup cron with the given schedule key.
	 *
	 * @param string $interval Schedule key from wp_get_schedules().
	 */
	public static function schedule_backup_event( $interval ) {
		self::clear_backup();
		wp_schedule_event( time() + MINUTE_IN_SECONDS, $interval, 'simplevpbot_cron_backup' );
	}

	/**
	 * Register inbound clients DB cache cron if missing (upgrades without re-activation).
	 */
	public static function ensure_inbound_clients_cache_scheduled() {
		if ( wp_next_scheduled( 'simplevpbot_cron_inbound_clients_cache' ) ) {
			return;
		}
		$schedules = wp_get_schedules();
		$iv        = isset( $schedules['simplevpbot_10min'] ) ? 'simplevpbot_10min' : 'hourly';
		wp_schedule_event( time() + 600, $iv, 'simplevpbot_cron_inbound_clients_cache' );
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

	public static function ensure_users_bulk_cron_scheduled() {
		if ( wp_next_scheduled( 'simplevpbot_cron_users_bulk' ) ) {
			return;
		}
		$schedules = wp_get_schedules();
		$interval  = isset( $schedules['simplevpbot_minute'] ) ? 'simplevpbot_minute' : 'hourly';
		wp_schedule_event( time() + 90, $interval, 'simplevpbot_cron_users_bulk' );
	}

	/**
	 * Schedule all crons.
	 */
	public static function schedule_all() {
		$name      = SimpleVPBot_Settings::backup_schedule_name();
		$schedules = wp_get_schedules();
		$interval  = isset( $schedules[ $name ] ) ? $name : 'hourly';
		if ( ! wp_next_scheduled( 'simplevpbot_cron_backup' ) ) {
			self::schedule_backup_event( $interval );
		}
		if ( ! wp_next_scheduled( 'simplevpbot_cron_expiry' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'simplevpbot_cron_expiry' );
		}
		if ( ! wp_next_scheduled( 'simplevpbot_cron_purge_expired' ) ) {
			wp_schedule_event( time() + 360, 'hourly', 'simplevpbot_cron_purge_expired' );
		}
		if ( ! wp_next_scheduled( 'simplevpbot_cron_autorenew' ) ) {
			wp_schedule_event( time() + 600, 'hourly', 'simplevpbot_cron_autorenew' );
		}
		if ( ! wp_next_scheduled( 'simplevpbot_cron_broadcast' ) ) {
			// Prefer real system cron hitting wp-cron.php for large broadcast queues; WP schedules every minute when simplevpbot_minute is registered.
			$broadcast_interval = isset( $schedules['simplevpbot_minute'] ) ? 'simplevpbot_minute' : 'hourly';
			wp_schedule_event( time() + 120, $broadcast_interval, 'simplevpbot_cron_broadcast' );
		}
		if ( ! wp_next_scheduled( 'simplevpbot_cron_users_bulk' ) ) {
			$users_bulk_interval = isset( $schedules['simplevpbot_minute'] ) ? 'simplevpbot_minute' : 'hourly';
			wp_schedule_event( time() + 90, $users_bulk_interval, 'simplevpbot_cron_users_bulk' );
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
		if ( ! wp_next_scheduled( 'simplevpbot_cron_marketing' ) ) {
			wp_schedule_event( time() + 700, 'hourly', 'simplevpbot_cron_marketing' );
		}
		if ( ! wp_next_scheduled( 'simplevpbot_cron_admin_alerts' ) ) {
			$po = isset( $schedules['simplevpbot_10min'] ) ? 'simplevpbot_10min' : 'hourly';
			wp_schedule_event( time() + 500, $po, 'simplevpbot_cron_admin_alerts' );
		}
		if ( ! wp_next_scheduled( 'simplevpbot_cron_panel_economics_renewal' ) ) {
			wp_schedule_event( time() + 720, 'hourly', 'simplevpbot_cron_panel_economics_renewal' );
		}
		if ( ! wp_next_scheduled( 'simplevpbot_cron_inbound_clients_cache' ) ) {
			$iv = isset( $schedules['simplevpbot_10min'] ) ? 'simplevpbot_10min' : 'hourly';
			wp_schedule_event( time() + 540, $iv, 'simplevpbot_cron_inbound_clients_cache' );
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
		if ( ! wp_next_scheduled( 'simplevpbot_cron_marketing' ) ) {
			wp_schedule_event( time() + 700, 'hourly', 'simplevpbot_cron_marketing' );
		}
		if ( ! wp_next_scheduled( 'simplevpbot_cron_admin_alerts' ) ) {
			$po = isset( $schedules['simplevpbot_10min'] ) ? 'simplevpbot_10min' : 'hourly';
			wp_schedule_event( time() + 500, $po, 'simplevpbot_cron_admin_alerts' );
		}
		if ( ! wp_next_scheduled( 'simplevpbot_cron_panel_online' ) ) {
			$po = isset( $schedules['simplevpbot_10min'] ) ? 'simplevpbot_10min' : 'hourly';
			wp_schedule_event( time() + 420, $po, 'simplevpbot_cron_panel_online' );
		}
		if ( ! wp_next_scheduled( 'simplevpbot_cron_panel_economics_renewal' ) ) {
			wp_schedule_event( time() + 720, 'hourly', 'simplevpbot_cron_panel_economics_renewal' );
		}
		if ( ! wp_next_scheduled( 'simplevpbot_cron_inbound_clients_cache' ) ) {
			$iv = isset( $schedules['simplevpbot_10min'] ) ? 'simplevpbot_10min' : 'hourly';
			wp_schedule_event( time() + 540, $iv, 'simplevpbot_cron_inbound_clients_cache' );
		}
		if ( ! wp_next_scheduled( 'simplevpbot_cron_purge_expired' ) ) {
			wp_schedule_event( time() + 360, 'hourly', 'simplevpbot_cron_purge_expired' );
		}
	}

	/**
	 * Drain async inbound webhook queue (fallback when loopback drain misses).
	 */
	public static function ensure_inbound_queue_cron_scheduled() {
		if ( ! class_exists( 'SimpleVPBot_Webhook_Queue' ) ) {
			return;
		}
		$hook = SimpleVPBot_Webhook_Queue::CRON_HOOK;
		if ( wp_next_scheduled( $hook ) ) {
			return;
		}
		$schedules = wp_get_schedules();
		$interval  = isset( $schedules['simplevpbot_minute'] ) ? 'simplevpbot_minute' : 'hourly';
		wp_schedule_event( time() + 45, $interval, $hook );
	}

	/**
	 * Reschedule backup when interval changes.
	 */
	public static function clear_backup() {
		wp_clear_scheduled_hook( 'simplevpbot_cron_backup' );
	}
}
