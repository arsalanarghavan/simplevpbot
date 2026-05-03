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
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Rate limit check (wp_cache based for atomic increments; transient fallback).
	 *
	 * @param string $ip IP.
	 * @return bool True if allowed.
	 */
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
			if ( $raw ) {
				return $raw;
			}
		}
		return '0';
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
		$ip       = self::client_ip();

		if ( ! self::rate_limit_ok( $ip ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 429 );
		}

		$expected = 'telegram' === $platform
			? (string) SimpleVPBot_Settings::get( 'telegram_webhook_secret', '' )
			: (string) SimpleVPBot_Settings::get( 'bale_webhook_secret', '' );
		if ( '' === $expected || '' === $secret || ! hash_equals( (string) $expected, $secret ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 403 );
		}

		if ( 'telegram' === $platform ) {
			$hdr = isset( $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ) // phpcs:ignore
				? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ) ) // phpcs:ignore
				: '';
			$exp2 = (string) SimpleVPBot_Settings::get( 'telegram_secret_header', '' );
			if ( $exp2 && ! hash_equals( $exp2, $hdr ) ) {
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
}
