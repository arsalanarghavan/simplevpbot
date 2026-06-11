<?php
/**
 * Async inbound webhook queue (fast ack to Telegram, process on separate request).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Webhook_Queue
 */
class SimpleVPBot_Webhook_Queue {

	const CRON_HOOK = 'simplevpbot_cron_inbound_queue';

	const KICK_LOCK_TRANSIENT = 'svp_inbound_kick_lock';

	/**
	 * Register REST drain route and cron hook.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'cron_drain' ) );
	}

	/**
	 * REST routes for internal async drain.
	 */
	public static function register_routes() {
		register_rest_route(
			'simplevpbot/v1',
			'/webhook-queue/drain',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_drain' ),
				'permission_callback' => array( __CLASS__, 'perm_drain' ),
			)
		);
	}

	/**
	 * @return bool
	 */
	public static function perm_drain() {
		$key = isset( $_SERVER['HTTP_X_SVP_QUEUE_KEY'] ) // phpcs:ignore
			? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_X_SVP_QUEUE_KEY'] ) ) // phpcs:ignore
			: '';
		$exp = self::internal_queue_key();
		return '' !== $exp && '' !== $key && hash_equals( $exp, $key );
	}

	/**
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function rest_drain( WP_REST_Request $req ) {
		unset( $req );
		$processed = self::drain_batch( self::batch_size() );
		return new WP_REST_Response(
			array(
				'ok'        => true,
				'processed' => (int) $processed,
			),
			200
		);
	}

	/**
	 * Cron fallback drain.
	 */
	public static function cron_drain() {
		self::drain_batch( self::batch_size() );
	}

	/**
	 * Items processed per drain call.
	 *
	 * @return int
	 */
	public static function batch_size() {
		$n = (int) apply_filters( 'simplevpbot_inbound_queue_batch_size', 5 );
		return max( 1, min( 20, $n ) );
	}

	/**
	 * Queue table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'svp_inbound_queue';
	}

	/**
	 * Count pending rows.
	 *
	 * @return int
	 */
	public static function pending_count() {
		global $wpdb;
		$t = self::table_name();
		if ( ! self::table_exists() ) {
			return 0;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE status = 'pending'" );
	}

	/**
	 * @return bool
	 */
	private static function table_exists() {
		global $wpdb;
		$t = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) );
	}

	/**
	 * HMAC key for internal drain requests.
	 *
	 * @return string
	 */
	public static function internal_queue_key() {
		$sec = (string) SimpleVPBot_Settings::get( 'telegram_webhook_secret', '' );
		if ( '' === $sec ) {
			$sec = (string) SimpleVPBot_Settings::get( 'bale_webhook_secret', '' );
		}
		if ( '' === $sec ) {
			return '';
		}
		return hash_hmac( 'sha256', 'svp_inbound_drain', $sec );
	}

	/**
	 * Enqueue a Telegram/Bale update for async processing.
	 *
	 * @param string                    $platform     telegram|bale.
	 * @param array<string, mixed>      $json         Update payload.
	 * @param array<string, mixed>|null $reseller_ctx Optional reseller context.
	 * @return int|false Insert id or false.
	 */
	public static function push( $platform, array $json, $reseller_ctx = null ) {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return false;
		}
		$rid = 0;
		if ( is_array( $reseller_ctx ) && ! empty( $reseller_ctx['rid'] ) ) {
			$rid = (int) $reseller_ctx['rid'];
		}
		$encoded = wp_json_encode( $json );
		if ( ! is_string( $encoded ) || '' === $encoded ) {
			return false;
		}
		$ok = $wpdb->insert(
			self::table_name(),
			array(
				'platform'             => sanitize_key( (string) $platform ),
				'reseller_svp_user_id' => $rid,
				'update_json'          => $encoded,
				'status'               => 'pending',
				'tries'                => 0,
				'created_at'           => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%s', '%d', '%s' )
		);
		if ( false === $ok ) {
			return false;
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Non-blocking loopback + cron fallback to process the queue.
	 */
	public static function kick_async() {
		if ( get_transient( self::KICK_LOCK_TRANSIENT ) ) {
			return;
		}
		set_transient( self::KICK_LOCK_TRANSIENT, '1', 5 );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 1, self::CRON_HOOK );
		}

		$key = self::internal_queue_key();
		if ( '' === $key ) {
			return;
		}
		$url = rest_url( 'simplevpbot/v1/webhook-queue/drain' );
		wp_remote_post(
			$url,
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'headers'   => array(
					'X-SVP-Queue-Key' => $key,
				),
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			)
		);
	}

	/**
	 * Process up to $limit pending updates.
	 *
	 * @param int $limit Max items.
	 * @return int Number processed.
	 */
	public static function drain_batch( $limit = 5 ) {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return 0;
		}
		$limit = max( 1, min( 20, (int) $limit ) );
		$t     = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, platform, reseller_svp_user_id, update_json, tries FROM {$t} WHERE status = %s ORDER BY id ASC LIMIT %d",
				'pending',
				$limit
			)
		);
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return 0;
		}
		$processed = 0;
		foreach ( $rows as $row ) {
			$id = (int) ( $row->id ?? 0 );
			if ( $id < 1 ) {
				continue;
			}
			$wpdb->update(
				$t,
				array(
					'status' => 'processing',
					'tries'  => (int) ( $row->tries ?? 0 ) + 1,
				),
				array( 'id' => $id ),
				array( '%s', '%d' ),
				array( '%d' )
			);
			$json = json_decode( (string) ( $row->update_json ?? '' ), true );
			if ( ! is_array( $json ) ) {
				$wpdb->update(
					$t,
					array(
						'status'     => 'failed',
						'last_error' => 'invalid_json',
						'processed_at' => current_time( 'mysql', true ),
					),
					array( 'id' => $id ),
					array( '%s', '%s', '%s' ),
					array( '%d' )
				);
				continue;
			}
			$platform     = (string) ( $row->platform ?? 'telegram' );
			$rid          = (int) ( $row->reseller_svp_user_id ?? 0 );
			$reseller_ctx = null;
			if ( $rid > 0 ) {
				$reseller_ctx = array( 'rid' => $rid );
				if ( class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
					$prof = SimpleVPBot_Model_Reseller_Bot_Profile::find_by_reseller( $rid );
					if ( $prof ) {
						$reseller_ctx['profile'] = $prof;
					}
				}
			}
			try {
				SimpleVPBot_Webhook::dispatch_queued_update( $platform, $json, $reseller_ctx );
				$wpdb->update(
					$t,
					array(
						'status'       => 'done',
						'processed_at' => current_time( 'mysql', true ),
					),
					array( 'id' => $id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
				++$processed;
			} catch ( Throwable $e ) { // phpcs:ignore
				$err = $e->getMessage();
				$wpdb->update(
					$t,
					array(
						'status'       => ( (int) ( $row->tries ?? 0 ) + 1 ) >= 3 ? 'failed' : 'pending',
						'last_error'   => substr( (string) $err, 0, 500 ),
						'processed_at' => current_time( 'mysql', true ),
					),
					array( 'id' => $id ),
					array( '%s', '%s', '%s' ),
					array( '%d' )
				);
				if ( class_exists( 'SimpleVPBot_Logger' ) ) {
					SimpleVPBot_Logger::error(
						'inbound queue dispatch failed',
						array(
							'id'       => $id,
							'platform' => $platform,
							'm'        => $err,
						)
					);
				}
			}
		}
		self::purge_old_rows();
		if ( self::pending_count() > 0 ) {
			self::kick_async();
		}
		return $processed;
	}

	/**
	 * Delete completed rows older than 7 days.
	 */
	private static function purge_old_rows() {
		global $wpdb;
		$t = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$t} WHERE status IN (%s, %s) AND processed_at IS NOT NULL AND processed_at < %s",
				'done',
				'failed',
				gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS )
			)
		);
	}

	/**
	 * CREATE TABLE SQL for activator.
	 *
	 * @param string $p               Table prefix.
	 * @param string $charset_collate Charset collate.
	 * @return string
	 */
	public static function sql_table( $p, $charset_collate ) {
		return "CREATE TABLE {$p}svp_inbound_queue (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			platform varchar(8) NOT NULL,
			reseller_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			update_json longtext NOT NULL,
			status varchar(16) NOT NULL DEFAULT 'pending',
			tries int NOT NULL DEFAULT 0,
			last_error text NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			processed_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY status_created (status, created_at)
		) $charset_collate;";
	}
}
