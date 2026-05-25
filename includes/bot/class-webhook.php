<?php
/**
 * REST API webhooks for Telegram & Bale.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Webhook
 */
class SimpleVPBot_Webhook {

	/**
	 * Register routes.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public static function register_routes() {
		register_rest_route(
			'simplevpbot/v1',
			'/webhook/(?P<platform>telegram|bale)/(?P<secret>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle' ),
				'permission_callback' => array( __CLASS__, 'perm_webhook' ),
			)
		);
		register_rest_route(
			'simplevpbot/v1',
			'/webhook/(?P<platform>telegram|bale)/reseller/(?P<reseller_id>\d+)/(?P<secret>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_reseller' ),
				'permission_callback' => array( __CLASS__, 'perm_webhook' ),
			)
		);
	}

	/**
	 * Webhooks are unauthenticated; lock down to POST only (auth is path secret + optional header).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return bool
	 */
	public static function perm_webhook( WP_REST_Request $req ) {
		return 'POST' === $req->get_method();
	}

	/**
	 * Rate limit check (wp_cache based for atomic increments; transient fallback).
	 *
	 * @param string $ip IP.
	 * @return bool True if allowed.
	 */
	/**
	 * IP for rate limiting. Defaults to REMOTE_ADDR unless rate_limit_trust_forwarded_for is enabled (trusted proxy).
	 *
	 * @return string
	 */
	public static function rate_limit_client_ip() {
		if ( SimpleVPBot_Settings::get( 'rate_limit_trust_forwarded_for', false ) ) {
			return self::client_ip();
		}
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		}
		return '0';
	}

	private static function rate_limit_ok( $ip ) {
		$lim = (int) SimpleVPBot_Settings::get( 'webhook_rate_limit_per_min', 120 );
		if ( $lim <= 0 ) {
			return true;
		}
		$key    = 'svp_rl_' . md5( (string) $ip ) . '_' . (string) floor( time() / 60 );
		$group  = 'simplevpbot';
		$added  = wp_cache_add( $key, 0, $group, 90 );
		$count  = (int) wp_cache_incr( $key, 1, $group );
		if ( 0 === $count && false === $added ) {
			// Object cache miss: fall back to transient pair (best effort).
			$tn = (int) get_transient( $key );
			if ( $tn >= $lim ) {
				return false;
			}
			set_transient( $key, $tn + 1, 90 );
			return true;
		}
		return $count <= $lim;
	}

	/**
	 * Resolve client IP, honouring optional trusted proxy headers (Cloudflare / X-Real-IP).
	 *
	 * @return string
	 */
	private static function client_ip() {
		$candidates = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_REAL_IP',
			'HTTP_X_FORWARDED_FOR',
			'REMOTE_ADDR',
		);
		foreach ( $candidates as $h ) {
			if ( empty( $_SERVER[ $h ] ) ) {
				continue;
			}
			$raw = sanitize_text_field( wp_unslash( (string) $_SERVER[ $h ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			$raw = trim( explode( ',', $raw )[0] );
			if ( $raw && filter_var( $raw, FILTER_VALIDATE_IP ) ) {
				return $raw;
			}
		}
		return '0';
	}

	/**
	 * Best-effort DB log for webhook troubleshooting (no secrets).
	 *
	 * @param string               $level   info|warning|error.
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 */
	private static function log_webhook( $level, $message, array $context = array() ) {
		if ( ! class_exists( 'SimpleVPBot_Logger' ) ) {
			return;
		}
		SimpleVPBot_Logger::log( $level, $message, $context );
	}

	/**
	 * REST handler.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle( WP_REST_Request $req ) {
		$platform = (string) $req['platform'];
		$secret   = (string) $req['secret'];
		$ip       = self::rate_limit_client_ip();

		if ( ! self::rate_limit_ok( $ip ) ) {
			self::log_webhook(
				'warning',
				'webhook rate limited',
				array(
					'scope'    => 'main',
					'platform' => $platform,
				)
			);
			return new WP_REST_Response( array( 'ok' => false ), 429 );
		}

		$expected = 'telegram' === $platform
			? (string) SimpleVPBot_Settings::get( 'telegram_webhook_secret', '' )
			: (string) SimpleVPBot_Settings::get( 'bale_webhook_secret', '' );
		if ( '' === $expected || '' === $secret || ! hash_equals( (string) $expected, $secret ) ) {
			self::log_webhook(
				'warning',
				'webhook rejected',
				array(
					'scope'  => 'main',
					'reason' => 'bad_path_secret',
					'platform' => $platform,
				)
			);
			return new WP_REST_Response( array( 'ok' => false ), 403 );
		}

		if ( 'telegram' === $platform ) {
			$hdr = isset( $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ) // phpcs:ignore
				? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ) ) // phpcs:ignore
				: '';
			$exp2 = (string) SimpleVPBot_Settings::get( 'telegram_secret_header', '' );
			if ( $exp2 && ! hash_equals( $exp2, $hdr ) ) {
				self::log_webhook(
					'warning',
					'webhook rejected',
					array(
						'scope'  => 'main',
						'reason' => 'bad_telegram_secret_header',
						'platform' => $platform,
					)
				);
				return new WP_REST_Response( array( 'ok' => false ), 403 );
			}
		}

		$json = $req->get_json_params();
		if ( ! is_array( $json ) ) {
			$body = $req->get_body();
			$json = json_decode( (string) $body, true );
		}
		if ( ! is_array( $json ) ) {
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		try {
			SimpleVPBot_Router::dispatch( $platform, $json );
		} catch ( Throwable $e ) { // phpcs:ignore
			SimpleVPBot_Logger::error( 'router exception', array( 'm' => $e->getMessage() ) );
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Webhook for a reseller-owned Telegram/Bale bot (path secret + optional per-bot Telegram secret header).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_reseller( WP_REST_Request $req ) {
		$platform = (string) $req['platform'];
		$secret   = (string) $req['secret'];
		$rid      = (int) $req['reseller_id'];
		$ip       = self::rate_limit_client_ip();

		if ( ! self::rate_limit_ok( $ip ) ) {
			self::log_webhook(
				'warning',
				'webhook rate limited',
				array(
					'scope'             => 'reseller',
					'platform'          => $platform,
					'reseller_svp_user_id' => $rid,
				)
			);
			return new WP_REST_Response( array( 'ok' => false ), 429 );
		}

		if ( $rid < 1 || '' === $secret ) {
			self::log_webhook(
				'warning',
				'webhook rejected',
				array(
					'scope'  => 'reseller',
					'reason' => 'bad_path_params',
					'platform' => $platform,
					'reseller_svp_user_id' => $rid,
				)
			);
			return new WP_REST_Response( array( 'ok' => false ), 403 );
		}

		$row = class_exists( 'SimpleVPBot_Model_User' ) ? SimpleVPBot_Model_User::find( $rid ) : null;
		if ( ! $row || ! SimpleVPBot_Model_User::is_reseller_row( $row ) ) {
			self::log_webhook(
				'warning',
				'webhook rejected',
				array(
					'scope'  => 'reseller',
					'reason' => 'invalid_reseller',
					'platform' => $platform,
					'reseller_svp_user_id' => $rid,
				)
			);
			return new WP_REST_Response( array( 'ok' => false ), 403 );
		}

		if ( ! class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			self::log_webhook(
				'warning',
				'webhook rejected',
				array(
					'scope'  => 'reseller',
					'reason' => 'profile_model_missing',
					'platform' => $platform,
					'reseller_svp_user_id' => $rid,
				)
			);
			return new WP_REST_Response( array( 'ok' => false ), 403 );
		}

		$profile = SimpleVPBot_Model_Reseller_Bot_Profile::find_by_reseller( $rid );
		if ( ! $profile || '' === trim( (string) ( $profile->webhook_secret ?? '' ) ) ) {
			self::log_webhook(
				'warning',
				'webhook rejected',
				array(
					'scope'  => 'reseller',
					'reason' => 'profile_or_webhook_secret_missing',
					'platform' => $platform,
					'reseller_svp_user_id' => $rid,
				)
			);
			return new WP_REST_Response( array( 'ok' => false ), 403 );
		}
		if ( isset( $profile->enabled ) && ! (int) $profile->enabled ) {
			self::log_webhook(
				'warning',
				'reseller webhook ignored (profile disabled)',
				array(
					'scope' => 'reseller',
					'platform' => $platform,
					'reseller_svp_user_id' => $rid,
				)
			);
			return new WP_REST_Response( array( 'ok' => true, 'disabled' => true ), 200 );
		}
		if ( ! hash_equals( (string) $profile->webhook_secret, $secret ) ) {
			self::log_webhook(
				'warning',
				'webhook rejected',
				array(
					'scope'  => 'reseller',
					'reason' => 'bad_path_secret',
					'platform' => $platform,
					'reseller_svp_user_id' => $rid,
				)
			);
			return new WP_REST_Response( array( 'ok' => false ), 403 );
		}

		if ( 'telegram' === $platform ) {
			$hdr = isset( $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ) // phpcs:ignore
				? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ) ) // phpcs:ignore
				: '';
			$rtok = trim( (string) ( $profile->telegram_secret_token ?? '' ) );
			if ( '' !== $rtok && ! hash_equals( $rtok, $hdr ) ) {
				self::log_webhook(
					'warning',
					'webhook rejected',
					array(
						'scope'  => 'reseller',
						'reason' => 'bad_telegram_secret_header',
						'platform' => $platform,
						'reseller_svp_user_id' => $rid,
					)
				);
				return new WP_REST_Response( array( 'ok' => false ), 403 );
			}
		}

		$json = $req->get_json_params();
		if ( ! is_array( $json ) ) {
			$body = $req->get_body();
			$json = json_decode( (string) $body, true );
		}
		if ( ! is_array( $json ) ) {
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		SimpleVPBot_Bot_Context::begin_reseller( $rid, $profile );
		try {
			SimpleVPBot_Router::dispatch( $platform, $json );
		} catch ( Throwable $e ) { // phpcs:ignore
			SimpleVPBot_Logger::error( 'router exception', array( 'm' => $e->getMessage() ) );
		} finally {
			SimpleVPBot_Bot_Context::reset();
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
}
