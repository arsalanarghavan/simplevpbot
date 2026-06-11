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
		return self::rate_limit_ok_for_key(
			'ip:' . (string) $ip,
			(int) SimpleVPBot_Settings::get( 'webhook_rate_limit_per_min', 120 )
		);
	}

	/**
	 * Per-reseller webhook throttle (separate bucket from IP limit).
	 *
	 * @param int $reseller_id svp_users.id.
	 * @return bool True if allowed.
	 */
	private static function rate_limit_ok_for_reseller( $reseller_id ) {
		$rid = (int) $reseller_id;
		if ( $rid < 1 ) {
			return true;
		}
		$lim = (int) SimpleVPBot_Settings::get( 'webhook_reseller_rate_limit_per_min', 60 );
		return self::rate_limit_ok_for_key( 'reseller:' . $rid, $lim );
	}

	/**
	 * @param string $bucket_key Unique bucket id.
	 * @param int    $lim        Max requests per minute (0 = unlimited).
	 * @return bool
	 */
	private static function rate_limit_ok_for_key( $bucket_key, $lim ) {
		$lim = (int) $lim;
		if ( $lim <= 0 ) {
			return true;
		}
		$key    = 'svp_rl_' . md5( (string) $bucket_key ) . '_' . (string) floor( time() / 60 );
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
	 * Reseller webhook secret from path or optional header (header preferred for log hygiene).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return string
	 */
	private static function reseller_webhook_secret_candidate( WP_REST_Request $req ) {
		$hdr = isset( $_SERVER['HTTP_X_SVP_WEBHOOK_SECRET'] ) // phpcs:ignore
			? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_X_SVP_WEBHOOK_SECRET'] ) ) // phpcs:ignore
			: '';
		if ( '' !== trim( $hdr ) ) {
			return trim( $hdr );
		}
		return (string) $req['secret'];
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

		if ( class_exists( 'SimpleVPBot_Platforms' ) && ! SimpleVPBot_Platforms::is_enabled( $platform ) ) {
			self::log_webhook(
				'warning',
				'main webhook ignored (platform disabled)',
				array(
					'scope'    => 'main',
					'platform' => $platform,
				)
			);
			return new WP_REST_Response( array( 'ok' => true, 'disabled' => true ), 200 );
		}

		$json = $req->get_json_params();
		if ( ! is_array( $json ) ) {
			$body = $req->get_body();
			$json = json_decode( (string) $body, true );
		}
		if ( ! is_array( $json ) ) {
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		self::serve_webhook_update( $req, $platform, $json );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Webhook for a reseller-owned Telegram/Bale bot (path secret + optional per-bot Telegram secret header).
	 *
	 * Security: the path secret is required for platform routing; treat webhook URLs as credentials
	 * (HTTPS in transit, rotate via reseller_bot_secret_rotate, never log full URLs).
	 * Optional: send the same secret in header X-SVP-Webhook-Secret to avoid path secret in access logs.
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
		if ( ! self::rate_limit_ok_for_reseller( $rid ) ) {
			self::log_webhook(
				'warning',
				'webhook rate limited',
				array(
					'scope'                => 'reseller',
					'reason'               => 'reseller_bucket',
					'platform'             => $platform,
					'reseller_svp_user_id' => $rid,
				)
			);
			return new WP_REST_Response( array( 'ok' => false ), 429 );
		}

		$secret = self::reseller_webhook_secret_candidate( $req );
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
		if ( 'approved' !== (string) ( $row->status ?? '' ) ) {
			self::log_webhook(
				'warning',
				'webhook rejected',
				array(
					'scope'  => 'reseller',
					'reason' => 'reseller_not_approved',
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
		$expected_secret = class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' )
			? SimpleVPBot_Model_Reseller_Bot_Profile::webhook_secret_plaintext( $profile )
			: '';
		if ( ! $profile || '' === $expected_secret ) {
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
		if ( ! hash_equals( $expected_secret, $secret ) ) {
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

		if ( class_exists( 'SimpleVPBot_Platforms' ) && ! SimpleVPBot_Platforms::is_enabled( $platform, $rid ) ) {
			self::log_webhook(
				'warning',
				'reseller webhook ignored (platform disabled)',
				array(
					'scope'                 => 'reseller',
					'platform'              => $platform,
					'reseller_svp_user_id'  => $rid,
				)
			);
			return new WP_REST_Response( array( 'ok' => true, 'disabled' => true ), 200 );
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

		self::serve_webhook_update(
			$req,
			$platform,
			$json,
			array(
				'rid'     => $rid,
				'profile' => $profile,
			)
		);

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Dispatch webhook update; respond to Telegram/Bale early when fastcgi is available.
	 *
	 * @param WP_REST_Request              $req           Request.
	 * @param string                       $platform      telegram|bale.
	 * @param array<string, mixed>         $json          Update payload.
	 * @param array<string, mixed>|null    $reseller_ctx  Optional reseller context.
	 */
	private static function serve_webhook_update( WP_REST_Request $req, $platform, array $json, $reseller_ctx = null ) {
		$queued = class_exists( 'SimpleVPBot_Webhook_Queue' )
			? SimpleVPBot_Webhook_Queue::push( $platform, $json, $reseller_ctx )
			: false;

		if ( false === $queued ) {
			self::ack_then(
				$req,
				static function () use ( $platform, $json, $reseller_ctx ) {
					self::dispatch_queued_update( $platform, $json, $reseller_ctx );
				}
			);
			return;
		}

		self::ack_then(
			$req,
			static function () {
				if ( class_exists( 'SimpleVPBot_Webhook_Queue' ) ) {
					SimpleVPBot_Webhook_Queue::kick_async();
				}
			}
		);
	}

	/**
	 * Send HTTP 200 to Telegram/Bale early, then run follow-up work.
	 *
	 * @param WP_REST_Request $req      Request.
	 * @param callable        $after_ack Callback after response is flushed when possible.
	 */
	private static function ack_then( WP_REST_Request $req, callable $after_ack ) {
		$route = (string) $req->get_route();
		add_filter(
			'rest_pre_serve_request',
			static function ( $served, $result, $request, $server ) use ( $route, $after_ack ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				if ( $served || ! ( $request instanceof WP_REST_Request ) ) {
					return $served;
				}
				if ( (string) $request->get_route() !== $route ) {
					return $served;
				}
				if ( function_exists( 'nocache_headers' ) ) {
					nocache_headers();
				}
				status_header( 200 );
				header( 'Content-Type: application/json; charset=UTF-8' );
				echo wp_json_encode( array( 'ok' => true ) );
				if ( function_exists( 'fastcgi_finish_request' ) ) {
					fastcgi_finish_request();
				}
				call_user_func( $after_ack );
				return true;
			},
			999,
			4
		);
	}

	/**
	 * Run router dispatch for a queued webhook payload.
	 *
	 * @param string                    $platform     telegram|bale.
	 * @param array<string, mixed>      $json         Update payload.
	 * @param array<string, mixed>|null $reseller_ctx Optional reseller context.
	 */
	public static function dispatch_queued_update( $platform, array $json, $reseller_ctx = null ) {
		$reseller_started = false;
		if ( is_array( $reseller_ctx ) && ! empty( $reseller_ctx['rid'] ) && class_exists( 'SimpleVPBot_Bot_Context' ) ) {
			SimpleVPBot_Bot_Context::begin_reseller( (int) $reseller_ctx['rid'], $reseller_ctx['profile'] ?? null );
			$reseller_started = true;
		}
		try {
			SimpleVPBot_Router::dispatch( $platform, $json );
		} catch ( Throwable $e ) { // phpcs:ignore
			SimpleVPBot_Logger::error( 'router exception', array( 'm' => $e->getMessage() ) );
		} finally {
			if ( $reseller_started && class_exists( 'SimpleVPBot_Bot_Context' ) ) {
				SimpleVPBot_Bot_Context::reset();
			}
		}
	}
}
