<?php
/**
 * Telegram relay (Node middleware) integration.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Telegram_Relay
 */
class SimpleVPBot_Telegram_Relay {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Whether relay mode is enabled and configured.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		if ( SimpleVPBot_Settings::get( 'telegram_relay_force', false ) ) {
			return '' !== self::base_url();
		}
		if ( ! SimpleVPBot_Settings::get( 'telegram_relay_enabled', false ) ) {
			return false;
		}
		return '' !== self::base_url();
	}

	/**
	 * Tenant id assigned by relay on first sync.
	 *
	 * @return string
	 */
	public static function tenant_id() {
		return (string) SimpleVPBot_Settings::get( 'telegram_relay_tenant_id', '' );
	}

	/**
	 * Public webhook URL for a reseller bot (per-bot domain override).
	 *
	 * @param int $reseller_svp_user_id Reseller id.
	 * @return string
	 */
	public static function public_url_for_reseller( $reseller_svp_user_id ) {
		$rid = (int) $reseller_svp_user_id;
		if ( $rid > 0 && class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			$prof = SimpleVPBot_Model_Reseller_Bot_Profile::find_by_reseller( $rid );
			if ( $prof ) {
				$u = trim( (string) ( $prof->telegram_relay_public_url ?? '' ) );
				if ( '' !== $u ) {
					return untrailingslashit( esc_url_raw( $u ) );
				}
			}
		}
		return self::public_url();
	}

	/**
	 * Relay internal API base (no trailing slash).
	 *
	 * @return string
	 */
	public static function base_url() {
		$u = trim( (string) SimpleVPBot_Settings::get( 'telegram_relay_base_url', '' ) );
		if ( '' === $u ) {
			$u = trim( (string) SimpleVPBot_Settings::get( 'telegram_relay_public_url', '' ) );
		}
		return untrailingslashit( esc_url_raw( $u ) );
	}

	/**
	 * Public URL Telegram should call (webhook registration).
	 *
	 * @return string
	 */
	public static function public_url() {
		$u = trim( (string) SimpleVPBot_Settings::get( 'telegram_relay_public_url', '' ) );
		if ( '' === $u ) {
			$u = self::base_url();
		}
		return untrailingslashit( esc_url_raw( $u ) );
	}

	/**
	 * WordPress URL relay forwards updates to.
	 *
	 * @return string
	 */
	public static function wp_forward_base_url() {
		$u = trim( (string) SimpleVPBot_Settings::get( 'telegram_relay_wp_forward_url', '' ) );
		if ( '' === $u ) {
			return SimpleVPBot_Settings::public_site_url();
		}
		return untrailingslashit( esc_url_raw( $u ) );
	}

	/**
	 * Shared secret for relay internal API.
	 *
	 * @return string
	 */
	public static function shared_secret() {
		return (string) SimpleVPBot_Settings::get( 'telegram_relay_shared_secret', '' );
	}

	/**
	 * Bot API base for outbound when relay is on.
	 *
	 * @param string $token Bot token.
	 * @return string
	 */
	public static function bot_api_base_url( $token ) {
		$base = trailingslashit( self::base_url() );
		$tok  = rawurlencode( (string) $token );
		return $base . 'bot' . $tok . '/';
	}

	/**
	 * Expected webhook URL on relay (main bot).
	 *
	 * @param string $platform telegram|bale.
	 * @return string
	 */
	public static function expected_webhook_url_main( $platform ) {
		if ( 'telegram' !== sanitize_key( (string) $platform ) || ! self::is_enabled() ) {
			return '';
		}
		$sec = (string) SimpleVPBot_Settings::get( 'telegram_webhook_secret', '' );
		if ( '' === $sec ) {
			return '';
		}
		return self::public_url() . '/webhook/telegram/' . rawurlencode( $sec );
	}

	/**
	 * Expected webhook URL on relay (reseller).
	 *
	 * @param string $platform             telegram|bale.
	 * @param int    $reseller_svp_user_id Reseller id.
	 * @return string
	 */
	public static function expected_webhook_url_reseller( $platform, $reseller_svp_user_id ) {
		$plat = sanitize_key( (string) $platform );
		$rid  = (int) $reseller_svp_user_id;
		if ( 'telegram' !== $plat || $rid < 1 || ! self::is_enabled() ) {
			return '';
		}
		if ( ! class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			return '';
		}
		$prof = SimpleVPBot_Model_Reseller_Bot_Profile::find_by_reseller( $rid );
		if ( ! $prof ) {
			return '';
		}
		$sec = SimpleVPBot_Model_Reseller_Bot_Profile::webhook_secret_plaintext( $prof );
		if ( '' === $sec ) {
			return '';
		}
		return self::public_url_for_reseller( $rid ) . '/webhook/telegram/reseller/' . $rid . '/' . rawurlencode( $sec );
	}

	/**
	 * Domains to register on relay (default + per-reseller).
	 *
	 * @return array<int, string>
	 */
	public static function collect_domains() {
		$hosts = array();
		$add   = static function ( $url ) use ( &$hosts ) {
			$u = trim( (string) $url );
			if ( '' === $u ) {
				return;
			}
			if ( ! preg_match( '#^https?://#i', $u ) ) {
				$u = 'https://' . $u;
			}
			$parsed = wp_parse_url( $u );
			if ( ! empty( $parsed['host'] ) ) {
				$hosts[] = strtolower( (string) $parsed['host'] );
			}
		};
		$add( self::public_url() );
		$add( self::base_url() );
		if ( class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			global $wpdb;
			$table = SimpleVPBot_Model_Reseller_Bot_Profile::table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_col( "SELECT telegram_relay_public_url FROM {$table} WHERE telegram_relay_public_url <> ''" );
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$add( (string) $row );
				}
			}
		}
		return array_values( array_unique( array_filter( $hosts ) ) );
	}

	/**
	 * Build config snapshot for relay server.
	 *
	 * @return array<string, mixed>
	 */
	public static function build_config_snapshot() {
		$s = SimpleVPBot_Settings::all();
		$main_enabled = ! class_exists( 'SimpleVPBot_Platforms' ) || SimpleVPBot_Platforms::main_platform_flag( 'telegram', $s );

		$resellers = array();
		if ( class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			global $wpdb;
			$table = SimpleVPBot_Model_Reseller_Bot_Profile::table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A );
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$rid = (int) ( $row['reseller_svp_user_id'] ?? 0 );
					if ( $rid < 1 ) {
						continue;
					}
					$prof = (object) $row;
					$relay_pub = trim( (string) ( $row['telegram_relay_public_url'] ?? '' ) );
					$entry     = array(
						'reseller_svp_user_id'  => $rid,
						'telegram_token'        => SimpleVPBot_Model_Reseller_Bot_Profile::token_for_platform( $prof, 'telegram' ),
						'webhook_secret'          => SimpleVPBot_Model_Reseller_Bot_Profile::webhook_secret_plaintext( $prof ),
						'telegram_secret_token'   => trim( (string) ( $row['telegram_secret_token'] ?? '' ) ),
						'enabled'                 => ! isset( $row['enabled'] ) || (int) $row['enabled'] !== 0,
						'telegram_enabled'        => ! isset( $row['telegram_enabled'] ) || (int) $row['telegram_enabled'] !== 0,
						'admin_telegram_ids'      => SimpleVPBot_Model_Reseller_Bot_Profile::decode_admin_ids( (string) ( $row['admin_telegram_ids'] ?? '' ) ),
					);
					if ( '' !== $relay_pub ) {
						$entry['relay_public_url'] = untrailingslashit( esc_url_raw( $relay_pub ) );
					}
					$resellers[] = $entry;
				}
			}
		}

		$tid = self::tenant_id();
		return array(
			'tenant_id'        => $tid,
			'domains'          => self::collect_domains(),
			'config_version'   => (string) time(),
			'wp_base_url'      => self::wp_forward_base_url(),
			'relay_public_url' => self::public_url(),
			'main'             => array(
				'telegram_token'           => (string) ( $s['telegram_token'] ?? '' ),
				'telegram_webhook_secret'  => (string) ( $s['telegram_webhook_secret'] ?? '' ),
				'telegram_secret_header'   => (string) ( $s['telegram_secret_header'] ?? '' ),
				'telegram_enabled'         => $main_enabled,
				'enabled'                  => ! empty( $s['enabled'] ),
				'admin_telegram_ids'       => array_values( array_map( 'intval', (array) ( $s['admin_telegram_ids'] ?? array() ) ) ),
			),
			'resellers'        => $resellers,
		);
	}

	/**
	 * GET relay internal API.
	 *
	 * @param string $path    e.g. /internal/status.
	 * @param int    $timeout Timeout seconds.
	 * @return array{ok:bool, status?:int, data?:array<string,mixed>, message?:string}
	 */
	public static function internal_get( $path, $timeout = 15 ) {
		$base = self::base_url();
		$sec  = self::shared_secret();
		if ( '' === $base || '' === $sec ) {
			return array( 'ok' => false, 'message' => 'relay_not_configured' );
		}
		$url = $base . '/' . ltrim( (string) $path, '/' );
		$res = wp_remote_get(
			$url,
			array(
				'timeout' => max( 5, (int) $timeout ),
				'headers' => array(
					'X-SVP-Relay-Secret' => $sec,
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'message' => $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$raw  = (string) wp_remote_retrieve_body( $res );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		$ok = $code >= 200 && $code < 300 && ! empty( $data['ok'] );
		return array(
			'ok'      => $ok,
			'status'  => $code,
			'data'    => $data,
			'message' => isset( $data['error'] ) ? (string) $data['error'] : ( $ok ? '' : 'relay_http_' . $code ),
		);
	}

	/**
	 * POST JSON to relay internal API.
	 *
	 * @param string               $path    e.g. /internal/config.
	 * @param array<string, mixed> $body    JSON body.
	 * @param int                  $timeout Timeout seconds.
	 * @return array{ok:bool, status?:int, data?:array<string,mixed>, message?:string}
	 */
	public static function internal_request( $path, array $body = array(), $timeout = 25 ) {
		$base = self::base_url();
		$sec  = self::shared_secret();
		if ( '' === $base || '' === $sec ) {
			return array( 'ok' => false, 'message' => 'relay_not_configured' );
		}
		$url  = $base . '/' . ltrim( (string) $path, '/' );
		$args = array(
			'timeout' => max( 5, (int) $timeout ),
			'headers' => array(
				'Content-Type'       => 'application/json',
				'X-SVP-Relay-Secret' => $sec,
			),
			'body'    => wp_json_encode( $body ),
		);
		$res = wp_remote_post( $url, $args );
		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'message' => $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$raw  = (string) wp_remote_retrieve_body( $res );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		$ok = $code >= 200 && $code < 300 && ! empty( $data['ok'] );
		return array(
			'ok'      => $ok,
			'status'  => $code,
			'data'    => $data,
			'message' => isset( $data['error'] ) ? (string) $data['error'] : ( $ok ? '' : 'relay_http_' . $code ),
		);
	}

	/**
	 * GET relay health.
	 *
	 * @return array{ok:bool, data?:array<string,mixed>, message?:string}
	 */
	public static function health() {
		return self::internal_get( '/internal/health', 15 );
	}

	/**
	 * Extended relay status for dashboard.
	 *
	 * @return array{ok:bool, data?:array<string,mixed>, message?:string}
	 */
	public static function status_via_relay() {
		return self::internal_get( '/internal/status', 20 );
	}

	/**
	 * Push domain list to relay.
	 *
	 * @return array{ok:bool, data?:array<string,mixed>, message?:string}
	 */
	public static function domains_sync_via_relay() {
		if ( ! self::is_enabled() ) {
			return array( 'ok' => false, 'message' => 'relay_disabled' );
		}
		return self::internal_request(
			'/internal/domains/sync',
			array( 'domains' => self::collect_domains() ),
			25
		);
	}

	/**
	 * Delete webhook via relay internal API.
	 *
	 * @param string $scope                main|reseller.
	 * @param int    $reseller_svp_user_id Reseller id.
	 * @return array{ok:bool, data?:array<string,mixed>, message?:string}
	 */
	public static function delete_webhook_via_relay( $scope = 'main', $reseller_svp_user_id = 0 ) {
		if ( ! self::is_enabled() ) {
			return array( 'ok' => false, 'message' => 'relay_disabled' );
		}
		$body = array(
			'scope'                => sanitize_key( (string) $scope ),
			'reseller_svp_user_id' => (int) $reseller_svp_user_id,
		);
		return self::internal_request( '/internal/delete-webhook', $body, 30 );
	}

	/**
	 * Push config snapshot to relay.
	 *
	 * @return array{ok:bool, message?:string, data?:array<string,mixed>}
	 */
	public static function push_config_to_relay() {
		if ( ! self::is_enabled() ) {
			return array( 'ok' => false, 'message' => 'relay_disabled' );
		}
		$snap = self::build_config_snapshot();
		$res  = self::internal_request( '/internal/config', $snap, 30 );
		if ( empty( $res['ok'] ) ) {
			return array(
				'ok'      => false,
				'message' => isset( $res['message'] ) ? (string) $res['message'] : 'sync_failed',
			);
		}
		update_option( 'simplevpbot_relay_last_sync_at', time(), false );
		$data = isset( $res['data'] ) && is_array( $res['data'] ) ? $res['data'] : array();
		if ( ! empty( $data['tenant_id'] ) ) {
			$all = SimpleVPBot_Settings::all();
			if ( (string) ( $all['telegram_relay_tenant_id'] ?? '' ) !== (string) $data['tenant_id'] ) {
				$all['telegram_relay_tenant_id'] = sanitize_text_field( (string) $data['tenant_id'] );
				SimpleVPBot_Settings::update( $all );
			}
		}
		return array(
			'ok'   => true,
			'data' => $data,
		);
	}

	/**
	 * Register webhook via relay internal API.
	 *
	 * @param string $scope                main|reseller.
	 * @param int    $reseller_svp_user_id Reseller id when scope=reseller.
	 * @param bool   $drop_pending         Drop pending updates.
	 * @return array{ok:bool, data?:array<string,mixed>, message?:string}
	 */
	public static function set_webhook_via_relay( $scope = 'main', $reseller_svp_user_id = 0, $drop_pending = true ) {
		if ( ! self::is_enabled() ) {
			return array( 'ok' => false, 'message' => 'relay_disabled' );
		}
		$sync = self::push_config_to_relay();
		if ( empty( $sync['ok'] ) ) {
			return $sync;
		}
		$body = array(
			'scope'                => sanitize_key( (string) $scope ),
			'reseller_svp_user_id' => (int) $reseller_svp_user_id,
			'drop_pending_updates' => (bool) $drop_pending,
		);
		$res = self::internal_request( '/internal/set-webhook', $body, 35 );
		if ( empty( $res['ok'] ) ) {
			return array(
				'ok'      => false,
				'message' => isset( $res['message'] ) ? (string) $res['message'] : 'set_webhook_failed',
				'data'    => isset( $res['data'] ) ? $res['data'] : array(),
			);
		}
		return array(
			'ok'   => true,
			'data' => isset( $res['data'] ) && is_array( $res['data'] ) ? $res['data'] : array(),
		);
	}

	/**
	 * Relay diagnostics (getMe + getWebhookInfo).
	 *
	 * @param string $scope                main|reseller.
	 * @param int    $reseller_svp_user_id Reseller id.
	 * @return array{ok:bool, data?:array<string,mixed>, message?:string}
	 */
	public static function diagnostics_via_relay( $scope = 'main', $reseller_svp_user_id = 0 ) {
		if ( ! self::is_enabled() ) {
			return array( 'ok' => false, 'message' => 'relay_disabled' );
		}
		$body = array(
			'scope'                => sanitize_key( (string) $scope ),
			'reseller_svp_user_id' => (int) $reseller_svp_user_id,
		);
		$res = self::internal_request( '/internal/diagnostics', $body, 40 );
		if ( empty( $res['ok'] ) ) {
			return array(
				'ok'      => false,
				'message' => isset( $res['message'] ) ? (string) $res['message'] : 'diagnostics_failed',
			);
		}
		return array(
			'ok'   => true,
			'data' => isset( $res['data'] ) && is_array( $res['data'] ) ? $res['data'] : array(),
		);
	}

	/**
	 * Optional REST: relay pull config (HMAC).
	 */
	public static function register_routes() {
		register_rest_route(
			'simplevpbot/v1',
			'/relay/config',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_config' ),
				'permission_callback' => array( __CLASS__, 'perm_relay_config' ),
			)
		);
	}

	/**
	 * @param WP_REST_Request $req Request.
	 * @return bool
	 */
	public static function perm_relay_config( WP_REST_Request $req ) {
		unset( $req );
		$hdr = isset( $_SERVER['HTTP_X_SVP_RELAY_SECRET'] ) // phpcs:ignore
			? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_X_SVP_RELAY_SECRET'] ) ) // phpcs:ignore
			: '';
		$exp = self::shared_secret();
		return '' !== $exp && '' !== $hdr && hash_equals( $exp, $hdr );
	}

	/**
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function rest_config( WP_REST_Request $req ) {
		unset( $req );
		return new WP_REST_Response( self::build_config_snapshot(), 200 );
	}

	/**
	 * Ensure relay shared secret exists.
	 */
	public static function ensure_relay_secret() {
		$all = SimpleVPBot_Settings::all();
		if ( ! empty( $all['telegram_relay_shared_secret'] ) ) {
			return (string) $all['telegram_relay_shared_secret'];
		}
		$all['telegram_relay_shared_secret'] = wp_generate_password( 48, false, false );
		SimpleVPBot_Settings::update( $all );
		return (string) $all['telegram_relay_shared_secret'];
	}

	/**
	 * Rotate relay shared secret (dashboard action).
	 *
	 * @return string New secret.
	 */
	public static function rotate_relay_secret() {
		$all = SimpleVPBot_Settings::all();
		$all['telegram_relay_shared_secret'] = wp_generate_password( 48, false, false );
		SimpleVPBot_Settings::update( $all );
		return (string) $all['telegram_relay_shared_secret'];
	}

	/**
	 * After settings saved: sync if relay enabled.
	 */
	public static function maybe_sync_after_settings() {
		if ( ! self::is_enabled() ) {
			return;
		}
		self::push_config_to_relay();
	}
}
