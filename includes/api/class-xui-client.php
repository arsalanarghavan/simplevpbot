<?php
/**
 * 3x-ui panel API client (documented endpoints only).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Xui_Client
 */
class SimpleVPBot_Xui_Client {

	const COOKIE_TRANSIENT_LEGACY = 'simplevpbot_xui_cookie';

	/**
	 * Bound panel id for this request: 0 = legacy options (panel_url in settings); >=1 = row in svp_panels.
	 *
	 * @var int
	 */
	private static $bound_panel_id = 0;

	/**
	 * Set bound panel; returns previous bound id for nesting.
	 *
	 * @param int $panel_id 0 for legacy settings-only.
	 * @return int
	 */
	public static function bind_panel( $panel_id ) {
		$prev                 = self::$bound_panel_id;
		self::$bound_panel_id = max( 0, (int) $panel_id );
		return (int) $prev;
	}

	/**
	 * Run callable with a panel bound (re-entrant safe).
	 *
	 * @param int      $panel_id 0 = use SimpleVPBot_Settings panel_* keys; else svp_panels.id.
	 * @param callable $fn Callable.
	 * @return mixed
	 */
	public static function run_with_panel( $panel_id, $fn ) {
		$prev = self::bind_panel( (int) $panel_id );
		try {
			return call_user_func( $fn );
		} finally {
			self::bind_panel( $prev );
		}
	}

	/**
	 * Transient key for stored session cookie (one per panel).
	 *
	 * @return string
	 */
	private static function cookie_transient_name() {
		if ( self::$bound_panel_id < 1 ) {
			return self::COOKIE_TRANSIENT_LEGACY;
		}
		return 'simplevpbot_xui_ck_p' . self::$bound_panel_id;
	}

	/**
	 * Resolved URL, user, password, api base, login secret, subscription base for the current binding.
	 *
	 * @return array{panel_url:string,panel_username:string,panel_password:string,panel_api_base:string,panel_login_secret:string,subscription_public_base:string}
	 */
	private static function panel_credentials() {
		if ( self::$bound_panel_id < 1 ) {
			$s = SimpleVPBot_Settings::all();
			return array(
				'panel_url'                 => (string) ( $s['panel_url'] ?? '' ),
				'panel_username'            => (string) ( $s['panel_username'] ?? '' ),
				'panel_password'            => (string) ( $s['panel_password'] ?? '' ),
				'panel_api_base'            => (string) ( $s['panel_api_base'] ?? 'panel/api' ),
				'panel_login_secret'        => (string) ( $s['panel_login_secret'] ?? '' ),
				'subscription_public_base' => (string) ( $s['subscription_public_base'] ?? '' ),
			);
		}
		if ( class_exists( 'SimpleVPBot_Model_Panel' ) ) {
			$row = SimpleVPBot_Model_Panel::find( self::$bound_panel_id );
			if ( $row && is_object( $row ) ) {
				return array(
					'panel_url'                 => (string) ( $row->panel_url ?? '' ),
					'panel_username'            => (string) ( $row->panel_username ?? '' ),
					'panel_password'            => (string) ( $row->panel_password ?? '' ),
					'panel_api_base'            => (string) ( $row->panel_api_base ?? 'panel/api' ),
					'panel_login_secret'        => (string) ( $row->panel_login_secret ?? '' ),
					'subscription_public_base' => (string) ( $row->subscription_public_base ?? '' ),
				);
			}
		}
		return array(
			'panel_url'                 => '',
			'panel_username'            => '',
			'panel_password'            => '',
			'panel_api_base'            => 'panel/api',
			'panel_login_secret'        => '',
			'subscription_public_base' => '',
		);
	}

	/**
	 * Trimmed panel URL and API base for diagnostics (uses current bind_panel id).
	 *
	 * @return array{panel_url:string,panel_api_base:string}
	 */
	public static function diag_binding_labels() {
		$c = self::panel_credentials();
		$api = trim( (string) ( $c['panel_api_base'] ?? 'panel/api' ), " \t\n\r\0\x0B/" );
		if ( '' === $api ) {
			$api = 'panel/api';
		}
		return array(
			'panel_url'      => untrailingslashit( trim( (string) ( $c['panel_url'] ?? '' ) ) ),
			'panel_api_base' => $api,
		);
	}

	/**
	 * Base panel URL (with trailing slash).
	 *
	 * @return string
	 */
	public static function panel_root() {
		$c = self::panel_credentials();
		return trailingslashit( trim( (string) ( $c['panel_url'] ?? '' ) ) );
	}

	/**
	 * API root: panel_root + panel_api_base + /
	 *
	 * @return string
	 */
	public static function api_root() {
		$c    = self::panel_credentials();
		$base = trim( (string) ( $c['panel_api_base'] ?? 'panel/api' ), " \t\n\r\0\x0B/" );
		if ( '' === $base ) {
			return self::panel_root();
		}
		return trailingslashit( self::panel_root() . $base );
	}

	/**
	 * Resolve absolute URL for a path.
	 * Scope "api"   → api_root() . path (inbounds/*, server/* — همه زیر /panel/api در 3x-ui اصلی)
	 * Scope "panel" → panel_root() . path (فقط موارد خارج از API؛ عملاً برای نمایش/سازگاری)
	 *
	 * @param string $path Relative path.
	 * @param string $scope "api" or "panel".
	 * @return string
	 */
	public static function resolve_url( $path, $scope = 'api' ) {
		$p = ltrim( (string) $path, '/' );
		if ( 'panel' === $scope ) {
			return self::panel_root() . $p;
		}
		return self::api_root() . $p;
	}

	/**
	 * Diagnostic: URL that will be called for a relative API path.
	 *
	 * @param string $path Path.
	 * @param string $scope Scope.
	 * @return string
	 */
	public static function diag_url( $path, $scope = 'api' ) {
		return self::resolve_url( $path, $scope );
	}

	/**
	 * Diagnostic: URL used for login.
	 *
	 * @return string
	 */
	public static function diag_login_url() {
		return untrailingslashit( self::panel_root() ) . '/login';
	}

	/**
	 * Drop stored session cookie so the next login is fresh.
	 */
	public static function clear_session() {
		delete_transient( self::cookie_transient_name() );
	}

	/**
	 * Login with several attempts (clears cookie between tries). Use for flaky panels / networks.
	 *
	 * @param int $max_attempts Attempts.
	 * @param int $delay_us     Microseconds to wait before retry 2+ (e.g. 350000 = 0.35s).
	 * @return bool
	 */
	public static function login_with_retries( $max_attempts = 6, $delay_us = 350000 ) {
		$max = max( 1, min( 12, (int) $max_attempts ) );
		for ( $i = 0; $i < $max; $i++ ) {
			if ( $i > 0 ) {
				self::clear_session();
				usleep( max( 50000, (int) $delay_us + ( $i - 1 ) * 100000 ) );
			}
			if ( self::login() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether HTTP response looks like a transient panel/network failure worth retrying.
	 *
	 * @param bool|\WP_Error $res wp_remote_* result.
	 * @param int            $code Response code (0 if error).
	 * @return bool
	 */
	private static function response_is_transient_failure( $res, $code ) {
		if ( is_wp_error( $res ) ) {
			return true;
		}
		return $code <= 0 || ( $code >= 500 && $code < 600 ) || 408 === $code || 429 === $code;
	}

	/**
	 * wp_remote_post for /login with transient network retries and filterable args.
	 *
	 * @param string               $url  Full login URL.
	 * @param array<string, mixed> $args wp_remote_post args (merged after defaults).
	 * @return \WP_Error|array
	 */
	private static function wp_remote_post_login( $url, array $args ) {
		$max         = 4;
		$last        = null;
		$filter_ctx  = 'login';
		for ( $i = 0; $i < $max; $i++ ) {
			$base = array_merge(
				array(
					'timeout'     => 50,
					'redirection' => 0,
				),
				(array) apply_filters( 'simplevpbot_xui_login_http_args', array(), $url, $filter_ctx )
			);
			$merged = array_merge( $base, $args );
			$last   = wp_remote_post( $url, $merged );
			$code   = is_wp_error( $last ) ? 0 : (int) wp_remote_retrieve_response_code( $last );
			if ( ! self::response_is_transient_failure( $last, $code ) ) {
				return $last;
			}
			if ( $i + 1 < $max ) {
				usleep( 200000 + $i * 120000 );
			}
		}
		return $last;
	}

	/**
	 * Lines describing which endpoint this binding uses (admin Telegram alerts).
	 *
	 * @return array<int, string>
	 */
	public static function probe_alert_detail_lines() {
		$d    = self::diag_binding_labels();
		$root = untrailingslashit( trim( (string) ( $d['panel_url'] ?? '' ) ) );
		$api  = trim( (string) ( $d['panel_api_base'] ?? 'panel/api' ), " \t\n\r\0\x0B/" );
		if ( '' === $api ) {
			$api = 'panel/api';
		}
		$login_url = self::diag_login_url();
		$bid       = (int) self::$bound_panel_id;
		$host      = $root ? (string) wp_parse_url( $root . '/', PHP_URL_HOST ) : '';

		$lines = array();
		if ( $bid > 0 ) {
			$lines[] = '🆔 ' . __( 'شناسهٔ رکورد پنل در ربات:', 'simplevpbot' ) . ' ' . $bid;
		} else {
			$lines[] = '📂 ' . __( 'منبع: «تنظیمات افزونه → پنل X-UI» (نه جدول پنل‌ها)', 'simplevpbot' );
		}
		if ( '' !== $host ) {
			$lines[] = '🌐 ' . __( 'میزبان:', 'simplevpbot' ) . ' ' . $host;
		}
		if ( '' !== $root ) {
			$lines[] = '🔗 ' . __( 'Panel URL ذخیره‌شده:', 'simplevpbot' ) . ' ' . $root;
		}
		$lines[] = '📡 ' . __( 'panel_api_base:', 'simplevpbot' ) . ' ' . $api;
		$lines[] = '🔐 ' . __( 'درخواست ورود ربات:', 'simplevpbot' ) . ' POST ' . $login_url;

		return $lines;
	}

	/**
	 * Login and store cookie.
	 *
	 * @return bool
	 */
	public static function login() {
		$c    = self::panel_credentials();
		$user = (string) ( $c['panel_username'] ?? '' );
		$pass = (string) ( $c['panel_password'] ?? '' );
		if ( '' === $user || '' === $pass || '' === self::panel_root() ) {
			SimpleVPBot_Logger::error( 'x-ui login skipped: missing panel_url/user/pass', array( 'panel_id' => self::$bound_panel_id ) );
			return false;
		}
		$url = untrailingslashit( self::panel_root() ) . '/login';
		$body = array(
			'username'    => $user,
			'password'    => $pass,
			'loginSecret' => (string) ( $c['panel_login_secret'] ?? '' ),
		);
		// 3x-ui accepts JSON or form; send form first (ShouldBind prefers form when CT is form-encoded).
		$res = self::wp_remote_post_login(
			$url,
			array(
				'body' => $body,
			)
		);
		if ( is_wp_error( $res ) ) {
			SimpleVPBot_Logger::error( 'x-ui login failed (form)', array( 'err' => $res->get_error_message(), 'url' => $url ) );
			return false;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$raw  = (string) wp_remote_retrieve_body( $res );
		$json = json_decode( $raw, true );
		$ok   = is_array( $json ) ? ! empty( $json['success'] ) : false;
		if ( ! $ok && 200 === $code ) {
			// Some 3x-ui forks return 200 with HTML login page (meaning auth fail); treat as fail.
			$ok = false;
		}
		if ( ! $ok ) {
			// Retry with JSON body (older forks).
			$res = self::wp_remote_post_login(
				$url,
				array(
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode( $body ),
				)
			);
			if ( is_wp_error( $res ) ) {
				SimpleVPBot_Logger::error( 'x-ui login failed (json)', array( 'err' => $res->get_error_message(), 'url' => $url ) );
				return false;
			}
			$code = (int) wp_remote_retrieve_response_code( $res );
			$raw  = (string) wp_remote_retrieve_body( $res );
			$json = json_decode( $raw, true );
			$ok   = is_array( $json ) ? ! empty( $json['success'] ) : false;
		}
		$parts   = array();
		$cookies = wp_remote_retrieve_cookies( $res );
		if ( ! empty( $cookies ) ) {
			foreach ( $cookies as $cobj ) {
				if ( $cobj instanceof WP_Http_Cookie && '' !== $cobj->value ) {
					$parts[] = $cobj->name . '=' . $cobj->value;
				}
			}
		}
		if ( empty( $parts ) ) {
			$headers = wp_remote_retrieve_headers( $res );
			$set     = array();
			if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
				$all = $headers->getAll();
				$set = isset( $all['set-cookie'] ) ? (array) $all['set-cookie'] : array();
			} elseif ( is_array( $headers ) && isset( $headers['set-cookie'] ) ) {
				$set = (array) $headers['set-cookie'];
			}
			foreach ( $set as $line ) {
				if ( preg_match( '/^([^=;]+)=([^;]+)/', (string) $line, $m ) ) {
					$parts[] = trim( $m[1] ) . '=' . trim( $m[2] );
				}
			}
		}
		if ( ! $ok || empty( $parts ) ) {
			SimpleVPBot_Logger::error(
				'x-ui login rejected',
				array(
					'url'          => $url,
					'http_code'    => $code,
					'response'     => mb_substr( $raw, 0, 400 ),
					'has_cookies'  => ! empty( $parts ),
					'panel_id'     => self::$bound_panel_id,
				)
			);
			return false;
		}
		set_transient( self::cookie_transient_name(), implode( '; ', $parts ), 12 * HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * Cookie header value.
	 *
	 * @return string
	 */
	public static function cookie_header() {
		$c = get_transient( self::cookie_transient_name() );
		return is_string( $c ) ? $c : '';
	}

	/**
	 * Request a panel endpoint.
	 *
	 * Scopes:
	 *   - "api"   : {panelRoot}{panel_api_base}/*  (inbounds/*, server/* — مطابق wiki 3x-ui)
	 *   - "panel" : {panelRoot}*                   (مسیرهای غیر API؛ لاگرین از diag_login_url)
	 *
	 * @param string               $path Relative path (no leading slash).
	 * @param string               $method GET|POST.
	 * @param array<string, mixed> $body Body for POST JSON.
	 * @param bool                 $binary Binary response.
	 * @param int                  $retry Retry budget (401 re-login).
	 * @param string               $scope "api"|"panel".
	 * @return array{ok:bool, code:int, body:string|array|null, json:array|null, url:string}
	 */
	public static function request( $path, $method = 'GET', array $body = array(), $binary = false, $retry = 2, $scope = 'api' ) {
		$path = ltrim( (string) $path, '/' );
		$url  = self::resolve_url( $path, $scope );
		$args = array(
			'timeout' => 90,
			'headers' => array(),
		);
		$cookie = self::cookie_header();
		if ( $cookie ) {
			$args['headers']['Cookie'] = $cookie;
		}
		if ( 'POST' === $method ) {
			$args['method']                  = 'POST';
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		} else {
			$args['method'] = 'GET';
		}

		$max_transient = 5;
		$res           = null;
		$code          = 0;
		$raw           = '';
		for ( $t = 0; $t < $max_transient; $t++ ) {
			if ( 'POST' === $method ) {
				$res = wp_remote_request( $url, $args );
			} else {
				$res = wp_remote_request( $url, $args );
			}
			if ( is_wp_error( $res ) ) {
				$code = 0;
				if ( $t + 1 < $max_transient && self::response_is_transient_failure( $res, 0 ) ) {
					usleep( 200000 + $t * 120000 );
					continue;
				}
				return array( 'ok' => false, 'code' => 0, 'body' => $res->get_error_message(), 'json' => null, 'url' => $url );
			}
			$code = (int) wp_remote_retrieve_response_code( $res );
			$raw  = (string) wp_remote_retrieve_body( $res );
			if ( self::response_is_transient_failure( $res, $code ) && $t + 1 < $max_transient ) {
				usleep( 250000 + $t * 150000 );
				continue;
			}
			break;
		}
		if ( 401 === $code && $retry > 0 ) {
			self::clear_session();
			if ( self::login_with_retries( 4, 300000 ) ) {
				return self::request( $path, $method, $body, $binary, $retry - 1, $scope );
			}
		}
		if ( $binary ) {
			return array( 'ok' => 200 === $code, 'code' => $code, 'body' => $raw, 'json' => null, 'url' => $url );
		}
		$json = json_decode( $raw, true );
		return array( 'ok' => $code >= 200 && $code < 300, 'code' => $code, 'body' => $raw, 'json' => is_array( $json ) ? $json : null, 'url' => $url );
	}

	/**
	 * Inbounds list.
	 *
	 * @return array|null
	 */
	public static function inbounds_list() {
		$r = self::request( 'inbounds/list', 'GET' );
		$j = ( is_array( $r['json'] ?? null ) ) ? $r['json'] : null;
		if ( ! $j ) {
			return null;
		}
		// Use isset + is_array (not !empty) so [] is a valid empty list.
		if ( isset( $j['obj'] ) && is_array( $j['obj'] ) ) {
			return $j['obj'];
		}
		if ( isset( $j['inbounds'] ) && is_array( $j['inbounds'] ) ) {
			return $j['inbounds'];
		}
		return null;
	}

	/**
	 * Get inbound by id.
	 *
	 * @param int $id Inbound id.
	 * @return array|null
	 */
	public static function inbound_get( $id ) {
		$r = self::request( 'inbounds/get/' . (int) $id, 'GET' );
		if ( ! empty( $r['json']['obj'] ) && is_array( $r['json']['obj'] ) ) {
			return $r['json']['obj'];
		}
		return null;
	}

	/**
	 * Add client to inbound.
	 *
	 * @param array<string, mixed> $payload Payload (id + settings string per panel).
	 * @return array|null Response obj.
	 */
	public static function add_client( array $payload ) {
		$r = self::request( 'inbounds/addClient', 'POST', $payload );
		return $r['json'];
	}

	/**
	 * Update client.
	 *
	 * @param string               $client_id Client id (uuid/password/email per protocol).
	 * @param array<string, mixed> $payload Payload.
	 * @return array|null
	 */
	public static function update_client( $client_id, array $payload ) {
		$r = self::request( 'inbounds/updateClient/' . rawurlencode( (string) $client_id ), 'POST', $payload );
		return $r['json'];
	}

	/**
	 * Try updateClient with single-client settings, then full inbound settings, for each path id (UUID then email, etc.).
	 *
	 * @param int                  $inbound_id          Inbound id.
	 * @param array<string, mixed> $full_settings_dec   Decoded inbound `settings` (e.g. clients + other keys).
	 * @param array<string, mixed> $single_client       One client object after edits.
	 * @param array<int, string>   $path_id_candidates  Values for /updateClient/{id} URL (UUID, email, …).
	 * @return array|null Last JSON response from panel (for logging).
	 */
	public static function update_inbound_client_sequential( $inbound_id, array $full_settings_dec, array $single_client, array $path_id_candidates ) {
		$iid = (int) $inbound_id;
		$ids = array();
		foreach ( $path_id_candidates as $c ) {
			$t = trim( (string) $c );
			if ( '' !== $t && ! in_array( $t, $ids, true ) ) {
				$ids[] = $t;
			}
		}
		$last = null;
		foreach ( $ids as $pid ) {
			$last = self::update_client(
				$pid,
				array(
					'id'       => $iid,
					'settings' => wp_json_encode( array( 'clients' => array( $single_client ) ) ),
				)
			);
			if ( self::response_is_success( $last ) ) {
				return $last;
			}
			$last = self::update_client(
				$pid,
				array(
					'id'       => $iid,
					'settings' => wp_json_encode( $full_settings_dec ),
				)
			);
			if ( self::response_is_success( $last ) ) {
				return $last;
			}
		}
		return $last;
	}

	/**
	 * Delete client.
	 *
	 * @param int    $inbound_id Inbound id.
	 * @param string $client_id Client id.
	 * @return array|null
	 */
	public static function del_client( $inbound_id, $client_id ) {
		$r = self::request( 'inbounds/' . (int) $inbound_id . '/delClient/' . rawurlencode( (string) $client_id ), 'POST', array() );
		return $r['json'];
	}

	/**
	 * Client traffics by email.
	 *
	 * @param string $email Email tag.
	 * @return array|null
	 */
	public static function get_client_traffics( $email ) {
		$r = self::request( 'inbounds/getClientTraffics/' . rawurlencode( $email ), 'GET' );
		return $r['json'];
	}

	/**
	 * Client IPs.
	 *
	 * @param string $email Email.
	 * @return array|null
	 */
	public static function client_ips( $email ) {
		$r = self::request( 'inbounds/clientIps/' . rawurlencode( $email ), 'POST', array() );
		return $r['json'];
	}

	/**
	 * Clear client IPs.
	 *
	 * @param string $email Email.
	 * @return array|null
	 */
	public static function clear_client_ips( $email ) {
		$r = self::request( 'inbounds/clearClientIps/' . rawurlencode( $email ), 'POST', array() );
		return $r['json'];
	}

	/**
	 * Reset client traffic.
	 *
	 * @param int    $inbound_id Inbound id.
	 * @param string $email Email.
	 * @return array|null
	 */
	public static function reset_client_traffic( $inbound_id, $email ) {
		$r = self::request( 'inbounds/' . (int) $inbound_id . '/resetClientTraffic/' . rawurlencode( $email ), 'POST', array() );
		return $r['json'];
	}

	/**
	 * Online clients emails.
	 *
	 * @return array|null
	 */
	public static function onlines() {
		$r = self::request( 'inbounds/onlines', 'POST', array() );
		return $r['json'];
	}

	/**
	 * Server status — GET {api_root}server/status (see 3x-ui web/controller/server.go).
	 *
	 * @return array|null
	 */
	public static function server_status() {
		$r = self::request( 'server/status', 'GET', array(), false, 1, 'api' );
		return $r['json'];
	}

	/**
	 * Download DB file bytes — GET {api_root}server/getDb.
	 *
	 * @return string|false
	 */
	public static function get_db_binary() {
		$r = self::request( 'server/getDb', 'GET', array(), true, 1, 'api' );
		if ( ! empty( $r['ok'] ) && is_string( $r['body'] ) && '' !== $r['body'] ) {
			return $r['body'];
		}
		$r2 = self::request( 'server/getDb', 'POST', array(), true, 1, 'api' );
		if ( ! empty( $r2['ok'] ) && is_string( $r2['body'] ) && '' !== $r2['body'] ) {
			return $r2['body'];
		}
		return false;
	}

	/**
	 * Xray config JSON for backup — GET {api_root}server/getConfigJson (پاسخ JSON؛ فیلد obj).
	 *
	 * @return string|false
	 */
	public static function get_config_json() {
		$r = self::request( 'server/getConfigJson', 'GET', array(), false, 1, 'api' );
		if ( empty( $r['ok'] ) || ! is_array( $r['json'] ) ) {
			return false;
		}
		if ( isset( $r['json']['obj'] ) ) {
			$obj = $r['json']['obj'];
			if ( is_string( $obj ) ) {
				return $obj;
			}
			if ( is_array( $obj ) || is_object( $obj ) ) {
				return wp_json_encode( $obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			}
		}
		return false;
	}

	/**
	 * Whether decoded panel JSON is a success.
	 *
	 * @param mixed $res json_decode result.
	 * @return bool
	 */
	public static function response_is_success( $res ) {
		if ( ! is_array( $res ) ) {
			return false;
		}
		if ( array_key_exists( 'success', $res ) && true === $res['success'] ) {
			return true;
		}
		if ( ! empty( $res['success'] ) ) {
			return true;
		}
		if ( ! empty( $res['obj'] ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Check string looks like a client UUID.
	 *
	 * @param string $s String.
	 * @return bool
	 */
	public static function is_likely_client_uuid( $s ) {
		$t = trim( (string) $s );
		return (bool) preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $t );
	}

	/**
	 * API may return a string, nested array, or a value that (string) casts to "Array".
	 *
	 * @param mixed $raw Raw `obj` from 3x-ui.
	 * @return string|false UUID or false.
	 */
	private static function parse_uuid_value( $raw ) {
		if ( is_string( $raw ) ) {
			$t = trim( $raw );
			if ( self::is_likely_client_uuid( $t ) ) {
				return $t;
			}
			return false;
		}
		if ( is_array( $raw ) || is_object( $raw ) ) {
			$a = (array) $raw;
			foreach ( array( 'uuid', 'id', 'value', 'obj' ) as $k ) {
				if ( ! empty( $a[ $k ] ) && ! is_array( $a[ $k ] ) ) {
					$v = self::parse_uuid_value( $a[ $k ] );
					if ( is_string( $v ) ) {
						return $v;
					}
				}
			}
			$json = is_array( $a ) ? wp_json_encode( $a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : '';
			if ( $json && preg_match( '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', (string) $json, $m ) ) {
				return $m[0];
			}
		}
		return false;
	}

	/**
	 * New UUID from panel — GET {api_root}server/getNewUUID.
	 *
	 * @return string|false
	 */
	public static function get_new_uuid() {
		$r = self::request( 'server/getNewUUID', 'GET', array(), false, 1, 'api' );
		if ( is_array( $r['json'] ?? null ) && ! empty( $r['json']['obj'] ) ) {
			$u = self::parse_uuid_value( $r['json']['obj'] );
			if ( is_string( $u ) ) {
				return $u;
			}
		}
		if ( ! empty( $r['body'] ) ) {
			$j = json_decode( (string) $r['body'], true );
			if ( is_array( $j ) && ! empty( $j['obj'] ) ) {
				$u = self::parse_uuid_value( $j['obj'] );
				if ( is_string( $u ) ) {
					return $u;
				}
			}
			$t = trim( (string) $r['body'] );
			if ( preg_match( '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $t, $m ) ) {
				return $m[0];
			}
		}
		// Fallback: local random UUID v4 (3x-ui accepts any unique UUID).
		return self::uuid_v4();
	}

	/**
	 * Inbound `settings` decoded: find client by email.
	 *
	 * @param array<string, mixed>|null $inbound Inbound row.
	 * @param string                     $email Client email.
	 * @return array<string, mixed>|null
	 */
	public static function inbound_client_by_email( $inbound, $email ) {
		if ( ! is_array( $inbound ) ) {
			return null;
		}
		$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
		$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
		if ( ! is_array( $dec ) || empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
			return null;
		}
		$want = (string) $email;
		foreach ( $dec['clients'] as $c ) {
			if ( is_array( $c ) && isset( $c['email'] ) && (string) $c['email'] === $want ) {
				return $c;
			}
		}
		return null;
	}

	/**
	 * ID for /updateClient/{id}: prefer DB, else first UUID in inbound for this email.
	 *
	 * @param string               $db_id  Stored xui id.
	 * @param array<string, mixed> $inbound Inbound.
	 * @param string                $email Client email.
	 * @return string|null
	 */
	public static function resolve_client_key_for_update( $db_id, $inbound, $email ) {
		$sid = trim( (string) $db_id );
		if ( self::is_likely_client_uuid( $sid ) && 0 !== strcasecmp( $sid, 'array' ) ) {
			return $sid;
		}
		$cl = self::inbound_client_by_email( $inbound, $email );
		if ( is_array( $cl ) && ! empty( $cl['id'] ) ) {
			$u = self::parse_uuid_value( $cl['id'] );
			return is_string( $u ) ? $u : (string) $cl['id'];
		}
		return null;
	}

	/**
	 * RFC 4122 v4 UUID fallback.
	 *
	 * @return string
	 */
	private static function uuid_v4() {
		$data    = function_exists( 'random_bytes' ) ? random_bytes( 16 ) : openssl_random_pseudo_bytes( 16 );
		$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 );
		$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 );
		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
	}
}
