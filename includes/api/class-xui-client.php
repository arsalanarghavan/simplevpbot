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
	const CSRF_TRANSIENT_LEGACY   = 'simplevpbot_xui_csrf';

	const FLAVOR_UNKNOWN = 'unknown';
	const FLAVOR_LEGACY  = 'legacy_inbound';
	const FLAVOR_V3      = 'v3_clients';

	/**
	 * Per-panel cached API flavor for this request.
	 *
	 * @var array<string, string>
	 */
	private static $cached_api_flavor = array();

	/**
	 * Bound panel id for this request: 0 = legacy options (panel_url in settings); >=1 = row in svp_panels.
	 *
	 * @var int
	 */
	private static $bound_panel_id = 0;

	/**
	 * Auth webBasePath base URL (no trailing slash) that succeeded for CSRF/login this request.
	 *
	 * @var string
	 */
	private static $resolved_auth_base = '';

	/**
	 * Last auth flow used: bearer | modern_cookie | legacy_cookie.
	 *
	 * @var string
	 */
	private static $last_auth_flow = '';

	/**
	 * HTTP timeout for panel API requests (seconds); lowered during backup getDb.
	 *
	 * @var int
	 */
	private static $request_timeout_sec = 90;

	/**
	 * Last CSRF/login HTTP attempt metadata (for admin diag / alerts).
	 *
	 * @var array<string, mixed>
	 */
	private static $last_auth_diag = array(
		'auth_flow'        => '',
		'csrf_skipped'     => false,
		'csrf_url'         => '',
		'csrf_http_code'   => 0,
		'login_url'        => '',
		'login_http_code'  => 0,
	);

	/**
	 * Set bound panel; returns previous bound id for nesting.
	 *
	 * @param int $panel_id 0 for legacy settings-only.
	 * @return int
	 */
	public static function bind_panel( $panel_id ) {
		$prev                 = self::$bound_panel_id;
		$next                 = max( 0, (int) $panel_id );
		if ( $prev !== $next ) {
			unset( self::$cached_api_flavor[ 'p' . $prev ], self::$cached_api_flavor[ 'p' . $next ] );
			self::$resolved_auth_base = '';
			self::$last_auth_flow     = '';
			self::$last_auth_diag     = array(
				'auth_flow'       => '',
				'csrf_skipped'    => false,
				'csrf_url'        => '',
				'csrf_http_code'  => 0,
				'login_url'       => '',
				'login_http_code' => 0,
			);
		}
		self::$bound_panel_id = $next;
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
	 * Transient key for stored CSRF token (one per panel).
	 *
	 * @return string
	 */
	private static function csrf_transient_name() {
		if ( self::$bound_panel_id < 1 ) {
			return self::CSRF_TRANSIENT_LEGACY;
		}
		return 'simplevpbot_xui_csrf_p' . self::$bound_panel_id;
	}

	/**
	 * Transient: panel has no /csrf-token (3x-ui v2.x) — skip CSRF probe on later logins.
	 *
	 * @return string
	 */
	private static function no_csrf_transient_name() {
		if ( self::$bound_panel_id < 1 ) {
			return 'simplevpbot_xui_no_csrf';
		}
		return 'simplevpbot_xui_no_csrf_p' . self::$bound_panel_id;
	}

	/**
	 * Transient key for resolved auth webBasePath (per panel).
	 *
	 * @return string
	 */
	private static function auth_base_transient_name() {
		if ( self::$bound_panel_id < 1 ) {
			return 'simplevpbot_xui_authbase';
		}
		return 'simplevpbot_xui_authbase_p' . self::$bound_panel_id;
	}

	/**
	 * Normalize stored panel URL (trim, drop erroneous trailing /panel).
	 *
	 * @param string $url Raw panel URL.
	 * @return string Untrailingslashit normalized URL or empty.
	 */
	public static function normalize_panel_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		$url = untrailingslashit( $url );
		if ( preg_match( '#/panel$#i', $url ) ) {
			$url = untrailingslashit( (string) preg_replace( '#/panel$#i', '', $url ) );
		}
		return $url;
	}

	/**
	 * Browser-like headers for panel auth probes (filterable).
	 *
	 * @param array<string, string> $extra Extra headers.
	 * @return array<string, string>
	 */
	private static function browser_like_headers( array $extra = array() ) {
		$base = array(
			'Accept'          => 'application/json, text/html, */*',
			'User-Agent'      => 'SimpleVPBot/1.0 (+https://wordpress.org; 3x-ui-panel-client)',
			'Accept-Language' => 'en-US,en;q=0.9',
		);
		return array_merge(
			$base,
			(array) apply_filters( 'simplevpbot_xui_browser_headers', array(), self::$bound_panel_id ),
			$extra
		);
	}

	/**
	 * Ordered auth base URL candidates (untrailingslashit).
	 *
	 * @return array<int, string>
	 */
	private static function auth_base_candidates() {
		$root = untrailingslashit( self::panel_root() );
		if ( '' === $root ) {
			return array();
		}
		$out  = array();
		$seen = array();
		$add  = static function ( $base ) use ( &$out, &$seen ) {
			$base = untrailingslashit( (string) $base );
			if ( '' === $base || isset( $seen[ $base ] ) ) {
				return;
			}
			$seen[ $base ] = true;
			$out[]         = $base;
		};
		$cached = get_transient( self::auth_base_transient_name() );
		if ( is_string( $cached ) && '' !== $cached ) {
			$add( $cached );
		}
		$add( $root );
		foreach ( self::discover_auth_bases_from_index( $root ) as $base ) {
			$add( $base );
		}
		return $out;
	}

	/**
	 * Probe panel index HTML/redirects for webBasePath prefixes.
	 *
	 * @param string $root Panel root (untrailingslashit).
	 * @return array<int, string>
	 */
	private static function discover_auth_bases_from_index( $root ) {
		$root = untrailingslashit( (string) $root );
		if ( '' === $root ) {
			return array();
		}
		$found = array();
		$res   = wp_remote_get(
			trailingslashit( $root ),
			array(
				'timeout'     => 20,
				'redirection' => 5,
				'headers'     => self::browser_like_headers(),
			)
		);
		if ( is_wp_error( $res ) ) {
			return array();
		}
		$parsed_root = wp_parse_url( $root );
		$origin      = '';
		if ( ! empty( $parsed_root['scheme'] ) && ! empty( $parsed_root['host'] ) ) {
			$origin = $parsed_root['scheme'] . '://' . $parsed_root['host'];
			if ( ! empty( $parsed_root['port'] ) ) {
				$origin .= ':' . $parsed_root['port'];
			}
		}
		$body = (string) wp_remote_retrieve_body( $res );
		if ( '' !== $body && preg_match_all( '#(?:href|src)=["\']([^"\']+)/(login|assets|panel|csrf-token)["\']#i', $body, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				$prefix = (string) ( $m[1] ?? '' );
				if ( '' === $prefix ) {
					continue;
				}
				if ( 0 === strpos( $prefix, 'http://' ) || 0 === strpos( $prefix, 'https://' ) ) {
					$base = untrailingslashit( $prefix );
				} elseif ( '' !== $origin ) {
					$base = untrailingslashit( $origin . '/' . trim( $prefix, '/' ) );
				} else {
					continue;
				}
				if ( $base !== $root ) {
					$found[] = $base;
				}
			}
		}
		return array_values( array_unique( $found ) );
	}

	/**
	 * Merge Set-Cookie into an existing Cookie header value.
	 *
	 * @param string $existing Existing Cookie header.
	 * @param string $from_res New cookies from HTTP response.
	 * @return string
	 */
	private static function merge_cookie_headers( $existing, $from_res ) {
		$jar = array();
		foreach ( array_filter( array_map( 'trim', explode( ';', (string) $existing ) ) ) as $part ) {
			if ( preg_match( '/^([^=]+)=(.*)$/', $part, $m ) ) {
				$jar[ trim( $m[1] ) ] = trim( $m[2] );
			}
		}
		foreach ( array_filter( array_map( 'trim', explode( ';', (string) $from_res ) ) ) as $part ) {
			if ( preg_match( '/^([^=]+)=(.*)$/', $part, $m ) ) {
				$jar[ trim( $m[1] ) ] = trim( $m[2] );
			}
		}
		if ( empty( $jar ) ) {
			return (string) $existing;
		}
		$parts = array();
		foreach ( $jar as $k => $v ) {
			$parts[] = $k . '=' . $v;
		}
		return implode( '; ', $parts );
	}

	/**
	 * GET panel index to obtain session cookie before CSRF/login.
	 *
	 * @param string $base   Auth base (untrailingslashit).
	 * @param string $cookie In/out accumulated Cookie header.
	 */
	private static function warm_up_auth_session( $base, &$cookie ) {
		$base = untrailingslashit( (string) $base );
		if ( '' === $base ) {
			return;
		}
		$url     = trailingslashit( $base );
		$headers = self::browser_like_headers();
		if ( '' !== $cookie ) {
			$headers['Cookie'] = $cookie;
		}
		$res = wp_remote_get(
			$url,
			array(
				'timeout'     => 30,
				'redirection' => 3,
				'headers'     => $headers,
			)
		);
		if ( is_wp_error( $res ) ) {
			return;
		}
		$new = self::cookie_header_from_response( $res );
		if ( '' !== $new ) {
			$cookie = self::merge_cookie_headers( $cookie, $new );
		}
	}

	/**
	 * Resolved auth base for login/csrf (cached per request + transient).
	 *
	 * @return string Untrailingslashit base or empty.
	 */
	private static function active_auth_base() {
		if ( '' !== self::$resolved_auth_base ) {
			return self::$resolved_auth_base;
		}
		$cached = get_transient( self::auth_base_transient_name() );
		if ( is_string( $cached ) && '' !== $cached ) {
			self::$resolved_auth_base = untrailingslashit( $cached );
			return self::$resolved_auth_base;
		}
		$cands = self::auth_base_candidates();
		if ( ! empty( $cands ) ) {
			return untrailingslashit( (string) $cands[0] );
		}
		return '';
	}

	/**
	 * Last auth HTTP diag (csrf/login URLs, status codes, flow).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_last_auth_diag() {
		$flow = '' !== self::$last_auth_flow ? self::$last_auth_flow : (string) ( self::$last_auth_diag['auth_flow'] ?? '' );
		return array_merge(
			array(
				'auth_flow'       => $flow,
				'csrf_skipped'    => false,
				'csrf_url'        => '',
				'csrf_http_code'  => 0,
				'login_url'       => '',
				'login_http_code' => 0,
			),
			self::$last_auth_diag,
			array( 'auth_flow' => $flow )
		);
	}

	/**
	 * Whether this panel has a Bearer API token configured.
	 *
	 * @return bool
	 */
	public static function has_api_token() {
		$c = self::panel_credentials();
		return '' !== trim( (string) ( $c['panel_api_token'] ?? '' ) );
	}

	/**
	 * Whether username + password are configured for cookie session login.
	 *
	 * @return bool
	 */
	public static function has_cookie_credentials() {
		$c = self::panel_credentials();
		return '' !== trim( (string) ( $c['panel_username'] ?? '' ) )
			&& '' !== trim( (string) ( $c['panel_password'] ?? '' ) )
			&& '' !== self::panel_root();
	}

	/**
	 * Headers for probe/diagnostic HTTP calls (Bearer or session cookie).
	 *
	 * @return array<string, string>
	 */
	public static function auth_headers_for_requests() {
		$headers = array( 'Accept' => 'application/json' );
		$creds   = self::panel_credentials();
		$token   = trim( (string) ( $creds['panel_api_token'] ?? '' ) );
		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
			return $headers;
		}
		$cookie = self::cookie_header();
		if ( '' !== $cookie ) {
			$headers['Cookie'] = $cookie;
		}
		return $headers;
	}

	/**
	 * Resolved URL, user, password, api base, login secret, subscription base for the current binding.
	 *
	 * @return array{panel_url:string,panel_username:string,panel_password:string,panel_api_base:string,panel_login_secret:string,subscription_public_base:string}
	 */
	private static function panel_credentials() {
		$norm_url = static function ( $raw ) {
			$n = self::normalize_panel_url( $raw );
			return '' !== $n ? trailingslashit( $n ) : '';
		};
		if ( self::$bound_panel_id < 1 ) {
			$s = SimpleVPBot_Settings::all();
			return array(
				'panel_url'                 => $norm_url( $s['panel_url'] ?? '' ),
				'panel_username'            => (string) ( $s['panel_username'] ?? '' ),
				'panel_password'            => (string) ( $s['panel_password'] ?? '' ),
				'panel_api_base'            => (string) ( $s['panel_api_base'] ?? 'panel/api' ),
				'panel_login_secret'        => (string) ( $s['panel_login_secret'] ?? '' ),
				'panel_api_token'           => (string) ( $s['panel_api_token'] ?? '' ),
				'subscription_public_base' => (string) ( $s['subscription_public_base'] ?? '' ),
			);
		}
		if ( class_exists( 'SimpleVPBot_Model_Panel' ) ) {
			$row = SimpleVPBot_Model_Panel::find( self::$bound_panel_id );
			if ( $row && is_object( $row ) ) {
				return array(
					'panel_url'                 => $norm_url( $row->panel_url ?? '' ),
					'panel_username'            => (string) ( $row->panel_username ?? '' ),
					'panel_password'            => (string) ( $row->panel_password ?? '' ),
					'panel_api_base'            => (string) ( $row->panel_api_base ?? 'panel/api' ),
					'panel_login_secret'        => (string) ( $row->panel_login_secret ?? '' ),
					'panel_api_token'           => (string) ( $row->panel_api_token ?? '' ),
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
			'panel_api_token'           => '',
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
			'panel_url'      => untrailingslashit( (string) ( $c['panel_url'] ?? '' ) ),
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
		return (string) ( $c['panel_url'] ?? '' );
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
		$base = self::active_auth_base();
		if ( '' === $base ) {
			$base = untrailingslashit( self::panel_root() );
		}
		return '' !== $base ? $base . '/login' : '';
	}

	/**
	 * Diagnostic: URL used for CSRF token fetch.
	 *
	 * @return string
	 */
	public static function diag_csrf_url() {
		$base = self::active_auth_base();
		if ( '' === $base ) {
			$base = untrailingslashit( self::panel_root() );
		}
		return '' !== $base ? $base . '/csrf-token' : '';
	}

	/**
	 * Check if 2FA is enabled on the panel (no authentication required).
	 * Used by login to determine if twoFactorCode is needed.
	 *
	 * @return bool|null True if enabled, false if disabled, null if check failed.
	 */
	public static function is_2fa_enabled() {
		$base = self::active_auth_base();
		if ( '' === $base ) {
			$base = untrailingslashit( self::panel_root() );
		}
		if ( '' === $base ) {
			return null;
		}
		$url = $base . '/getTwoFactorEnable';
		$headers = array();
		if ( ! self::has_api_token() ) {
			$csrf = self::ensure_csrf_token();
			if ( is_array( $csrf ) ) {
				$headers['Cookie']       = (string) $csrf['cookie'];
				$headers['X-CSRF-Token'] = (string) $csrf['token'];
			}
		}
		$res = wp_remote_post( $url, array(
			'timeout'     => 30,
			'redirection' => 0,
			'headers'     => $headers,
		) );
		if ( is_wp_error( $res ) ) {
			SimpleVPBot_Logger::info( 'getTwoFactorEnable check failed', array( 'err' => $res->get_error_message(), 'url' => $url ) );
			return null;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$raw  = (string) wp_remote_retrieve_body( $res );
		$json = json_decode( $raw, true );
		if ( ! is_array( $json ) || empty( $json['success'] ) ) {
			SimpleVPBot_Logger::info( 'getTwoFactorEnable check returned non-success', array( 'code' => $code, 'response' => mb_substr( $raw, 0, 200 ) ) );
			return null;
		}
		return ! empty( $json['obj'] );
	}

	/**
	 * Drop stored session cookie so the next login is fresh.
	 */
	public static function clear_session() {
		delete_transient( self::cookie_transient_name() );
		delete_transient( self::csrf_transient_name() );
		delete_transient( self::auth_base_transient_name() );
		self::$resolved_auth_base = '';
		self::$last_auth_flow     = '';
	}

	/**
	 * Login with several attempts (clears cookie between tries). Use for flaky panels / networks.
	 *
	 * @param int $max_attempts Attempts.
	 * @param int $delay_us     Microseconds to wait before retry 2+ (e.g. 350000 = 0.35s).
	 * @return bool
	 */
	public static function login_with_retries( $max_attempts = 6, $delay_us = 350000 ) {
		if ( self::has_api_token() ) {
			return '' !== self::panel_root();
		}
		return self::login_with_cookie_session( $max_attempts, $delay_us );
	}

	/**
	 * Cookie/session login only (required for server/getDb even when API token is set).
	 *
	 * @param int $max_attempts Attempts.
	 * @param int $delay_us     Microseconds before retry 2+.
	 * @return bool
	 */
	public static function login_with_cookie_session( $max_attempts = 6, $delay_us = 350000 ) {
		$c    = self::panel_credentials();
		$user = trim( (string) ( $c['panel_username'] ?? '' ) );
		$pass = trim( (string) ( $c['panel_password'] ?? '' ) );
		if ( '' === $user || '' === $pass || '' === self::panel_root() ) {
			SimpleVPBot_Logger::error(
				'x-ui cookie login skipped: missing panel_url/user/pass',
				array( 'panel_id' => self::$bound_panel_id )
			);
			return false;
		}
		$max = max( 1, min( 12, (int) $max_attempts ) );
		for ( $i = 0; $i < $max; $i++ ) {
			if ( $i > 0 ) {
				self::clear_session();
				usleep( max( 50000, (int) $delay_us + ( $i - 1 ) * 100000 ) );
			}
			if ( self::login_via_cookie_session() ) {
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
	 * Extract Set-Cookie headers as a Cookie request header value.
	 *
	 * @param array|\WP_Error $res HTTP result.
	 * @return string
	 */
	private static function cookie_header_from_response( $res ) {
		$parts = array();
		if ( is_wp_error( $res ) ) {
			return '';
		}
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
		return implode( '; ', array_unique( $parts ) );
	}

	/**
	 * Fetch and store the v3 CSRF token and its matching session cookie.
	 *
	 * @return array{token:string,cookie:string}|false
	 */
	private static function ensure_csrf_token() {
		if ( get_transient( self::no_csrf_transient_name() ) ) {
			return false;
		}

		$token  = get_transient( self::csrf_transient_name() );
		$cookie = self::cookie_header();
		if ( is_string( $token ) && '' !== $token && '' !== $cookie ) {
			return array( 'token' => $token, 'cookie' => $cookie );
		}

		$bases = self::auth_base_candidates();
		$cached_base = get_transient( self::auth_base_transient_name() );
		if ( is_string( $cached_base ) && '' !== $cached_base ) {
			array_unshift( $bases, untrailingslashit( $cached_base ) );
		}
		$seen = array();
		$last_code = 0;
		$last_url  = '';

		foreach ( $bases as $base ) {
			$base = untrailingslashit( (string) $base );
			if ( '' === $base || isset( $seen[ $base ] ) ) {
				continue;
			}
			$seen[ $base ] = true;

			$cookie_try = $cookie;
			self::warm_up_auth_session( $base, $cookie_try );

			$url = $base . '/csrf-token';
			$headers = self::browser_like_headers();
			if ( '' !== $cookie_try ) {
				$headers['Cookie'] = $cookie_try;
			}

			$res = wp_remote_get(
				$url,
				array(
					'timeout'     => 30,
					'redirection' => 3,
					'headers'     => $headers,
				)
			);

			$last_url  = $url;
			$last_code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );

			if ( is_wp_error( $res ) ) {
				continue;
			}

			// 3x-ui v2.x has no /csrf-token — use legacy POST /login immediately on later attempts.
			if ( 404 === $last_code ) {
				set_transient( self::no_csrf_transient_name(), 1, 12 * HOUR_IN_SECONDS );
				self::$last_auth_diag['csrf_url']       = $url;
				self::$last_auth_diag['csrf_http_code'] = $last_code;
				return false;
			}

			$raw  = (string) wp_remote_retrieve_body( $res );
			$json = json_decode( $raw, true );
			if ( 200 !== $last_code || ! is_array( $json ) || empty( $json['success'] ) || empty( $json['obj'] ) || ! is_string( $json['obj'] ) ) {
				continue;
			}

			$new_cookie = self::cookie_header_from_response( $res );
			if ( '' !== $new_cookie ) {
				$cookie_try = self::merge_cookie_headers( $cookie_try, $new_cookie );
			}
			if ( '' === $cookie_try ) {
				continue;
			}

			$token = (string) $json['obj'];
			self::$resolved_auth_base = $base;
			set_transient( self::auth_base_transient_name(), $base, 12 * HOUR_IN_SECONDS );
			set_transient( self::cookie_transient_name(), $cookie_try, 12 * HOUR_IN_SECONDS );
			set_transient( self::csrf_transient_name(), $token, 12 * HOUR_IN_SECONDS );
			self::$last_auth_diag['csrf_url']       = $url;
			self::$last_auth_diag['csrf_http_code'] = $last_code;
			return array( 'token' => $token, 'cookie' => $cookie_try );
		}

		self::$last_auth_diag['csrf_url']       = $last_url;
		self::$last_auth_diag['csrf_http_code'] = $last_code;
		SimpleVPBot_Logger::info(
			'x-ui csrf-token unavailable (will try legacy login if configured)',
			array(
				'url'       => $last_url,
				'http_code' => $last_code,
				'panel_id'  => self::$bound_panel_id,
				'bases'     => array_keys( $seen ),
			)
		);
		return false;
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
		$auth_diag = self::get_last_auth_diag();
		$csrf_url  = '' !== (string) ( $auth_diag['csrf_url'] ?? '' ) ? (string) $auth_diag['csrf_url'] : self::diag_csrf_url();
		$login_url = '' !== (string) ( $auth_diag['login_url'] ?? '' ) ? (string) $auth_diag['login_url'] : self::diag_login_url();
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
		if ( '' !== $csrf_url ) {
			$csrf_code = (int) ( $auth_diag['csrf_http_code'] ?? 0 );
			$lines[] = '🔑 ' . __( 'CSRF:', 'simplevpbot' ) . ' GET ' . $csrf_url
				. ( $csrf_code > 0 ? ' → HTTP ' . $csrf_code : '' );
		}
		if ( '' !== $login_url ) {
			$login_code = (int) ( $auth_diag['login_http_code'] ?? 0 );
			$lines[] = '🔐 ' . __( 'ورود:', 'simplevpbot' ) . ' POST ' . $login_url
				. ( $login_code > 0 ? ' → HTTP ' . $login_code : '' );
		}

		return $lines;
	}

	/**
	 * Login and store cookie (Bearer → modern CSRF cookie → legacy loginSecret).
	 *
	 * @return bool
	 */
	public static function login() {
		// v2.x getDb requires cookie session; token-only panels use Bearer for inbounds, not getDb.
		if ( self::has_api_token() && ! self::has_cookie_credentials() ) {
			self::$last_auth_flow                 = 'bearer';
			self::$last_auth_diag['auth_flow']    = 'bearer';
			self::$last_auth_diag['csrf_skipped'] = true;
			return '' !== self::panel_root();
		}
		return self::login_via_cookie_session();
	}

	/**
	 * Establish panel session via CSRF/cookie (never Bearer token).
	 *
	 * @return bool
	 */
	private static function login_via_cookie_session() {
		$c = self::panel_credentials();
		$user = (string) ( $c['panel_username'] ?? '' );
		$pass = (string) ( $c['panel_password'] ?? '' );
		if ( '' === $user || '' === $pass || '' === self::panel_root() ) {
			SimpleVPBot_Logger::error( 'x-ui login skipped: missing panel_url/user/pass', array( 'panel_id' => self::$bound_panel_id ) );
			return false;
		}

		$csrf = false;
		if ( ! get_transient( self::no_csrf_transient_name() ) ) {
			$csrf = self::ensure_csrf_token();
		} else {
			self::$last_auth_diag['csrf_skipped'] = true;
		}
		if ( is_array( $csrf ) && self::login_modern_cookie( $csrf, $c ) ) {
			self::$last_auth_flow                 = 'modern_cookie';
			self::$last_auth_diag['auth_flow']    = 'modern_cookie';
			self::$last_auth_diag['csrf_skipped'] = false;
			return true;
		}

		delete_transient( self::csrf_transient_name() );
		if ( self::login_legacy_cookie( $c ) ) {
			self::$last_auth_flow                 = 'legacy_cookie';
			self::$last_auth_diag['auth_flow']    = 'legacy_cookie';
			self::$last_auth_diag['csrf_skipped'] = true;
			return true;
		}

		SimpleVPBot_Logger::error(
			'x-ui login rejected (modern + legacy)',
			array(
				'panel_id'   => self::$bound_panel_id,
				'csrf_code'  => (int) ( self::$last_auth_diag['csrf_http_code'] ?? 0 ),
				'login_code' => (int) ( self::$last_auth_diag['login_http_code'] ?? 0 ),
			)
		);
		return false;
	}

	/**
	 * Modern 3x-ui login with CSRF token (twoFactorCode).
	 *
	 * @param array{token:string,cookie:string} $csrf CSRF bundle.
	 * @param array<string, string>           $c    Panel credentials.
	 * @return bool
	 */
	private static function login_modern_cookie( array $csrf, array $c ) {
		$base = self::$resolved_auth_base;
		if ( '' === $base ) {
			$base = untrailingslashit( self::panel_root() );
		}
		$url  = $base . '/login';
		$body = array(
			'username'      => (string) ( $c['panel_username'] ?? '' ),
			'password'      => (string) ( $c['panel_password'] ?? '' ),
			'twoFactorCode' => (string) ( $c['panel_login_secret'] ?? '' ),
		);
		$ok = self::attempt_login_post(
			$url,
			$body,
			array(
				'Cookie'           => (string) $csrf['cookie'],
				'X-CSRF-Token'     => (string) $csrf['token'],
				'X-Requested-With' => 'XMLHttpRequest',
			),
			(string) $csrf['cookie'],
			true
		);
		if ( $ok ) {
			set_transient( self::auth_base_transient_name(), untrailingslashit( $base ), 12 * HOUR_IN_SECONDS );
		}
		return $ok;
	}

	/**
	 * Legacy panel login without CSRF (loginSecret + optional twoFactorCode).
	 *
	 * @param array<string, string> $c Panel credentials.
	 * @return bool
	 */
	private static function login_legacy_cookie( array $c ) {
		$user   = (string) ( $c['panel_username'] ?? '' );
		$pass   = (string) ( $c['panel_password'] ?? '' );
		$secret = (string) ( $c['panel_login_secret'] ?? '' );
		foreach ( self::auth_base_candidates() as $base ) {
			$base = untrailingslashit( (string) $base );
			if ( '' === $base ) {
				continue;
			}
			self::$resolved_auth_base = $base;
			$url                      = $base . '/login';
			$body                     = array(
				'username'    => $user,
				'password'    => $pass,
				'loginSecret' => $secret,
			);
			if ( '' !== $secret ) {
				$body['twoFactorCode'] = $secret;
			}
			if ( self::attempt_login_post( $url, $body, array(), '', false ) ) {
				set_transient( self::auth_base_transient_name(), $base, 12 * HOUR_IN_SECONDS );
				return true;
			}
		}
		return false;
	}

	/**
	 * POST /login (form then JSON) and persist session cookie on success.
	 *
	 * @param string               $url             Full login URL.
	 * @param array<string, mixed> $body            Request body fields.
	 * @param array<string, string> $extra_headers  Extra HTTP headers.
	 * @param string               $fallback_cookie Cookie if response omits Set-Cookie.
	 * @param bool                 $store_csrf      Store CSRF transient after success (modern flow only).
	 * @return bool
	 */
	private static function attempt_login_post( $url, array $body, array $extra_headers = array(), $fallback_cookie = '', $store_csrf = false ) {
		$auth_headers = self::browser_like_headers( $extra_headers );
		$res          = self::wp_remote_post_login(
			$url,
			array(
				'headers' => $auth_headers,
				'body'    => $body,
			)
		);
		if ( is_wp_error( $res ) ) {
			self::$last_auth_diag['login_url']       = $url;
			self::$last_auth_diag['login_http_code'] = 0;
			return false;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		self::$last_auth_diag['login_url']       = $url;
		self::$last_auth_diag['login_http_code'] = $code;
		$raw  = (string) wp_remote_retrieve_body( $res );
		$json = json_decode( $raw, true );
		$ok   = is_array( $json ) ? ! empty( $json['success'] ) : false;
		if ( ! $ok && 200 === $code ) {
			$ok = false;
		}
		if ( ! $ok ) {
			$res = self::wp_remote_post_login(
				$url,
				array(
					'headers' => array_merge( $auth_headers, array( 'Content-Type' => 'application/json' ) ),
					'body'    => wp_json_encode( $body ),
				)
			);
			if ( is_wp_error( $res ) ) {
				return false;
			}
			$code = (int) wp_remote_retrieve_response_code( $res );
			self::$last_auth_diag['login_http_code'] = $code;
			$raw  = (string) wp_remote_retrieve_body( $res );
			$json = json_decode( $raw, true );
			$ok   = is_array( $json ) ? ! empty( $json['success'] ) : false;
		}
		$cookie = self::cookie_header_from_response( $res );
		if ( '' === $cookie && '' !== $fallback_cookie ) {
			$cookie = $fallback_cookie;
		}
		if ( ! $ok || '' === $cookie ) {
			return false;
		}
		set_transient( self::cookie_transient_name(), $cookie, 12 * HOUR_IN_SECONDS );
		if ( $store_csrf && ! empty( $extra_headers['X-CSRF-Token'] ) ) {
			set_transient( self::csrf_transient_name(), (string) $extra_headers['X-CSRF-Token'], 12 * HOUR_IN_SECONDS );
		}
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
	 * @param bool                 $session_only Use cookie session only (no Bearer); for server/getDb.
	 * @return array{ok:bool, code:int, body:string|array|null, json:array|null, url:string}
	 */
	public static function request( $path, $method = 'GET', array $body = array(), $binary = false, $retry = 2, $scope = 'api', $session_only = false ) {
		$path = ltrim( (string) $path, '/' );
		$url  = self::resolve_url( $path, $scope );
		$args = array(
			'timeout' => max( 5, (int) self::$request_timeout_sec ),
			'headers' => array( 'Accept' => $binary ? 'application/octet-stream,*/*' : 'application/json' ),
		);
		$creds = self::panel_credentials();
		$token = $session_only ? '' : (string) ( $creds['panel_api_token'] ?? '' );
		if ( '' !== $token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $token;
		} else {
			$cookie = self::cookie_header();
			if ( $cookie ) {
				$args['headers']['Cookie'] = $cookie;
			}
			$csrf = get_transient( self::csrf_transient_name() );
			if ( is_string( $csrf ) && '' !== $csrf ) {
				$args['headers']['X-CSRF-Token'] = $csrf;
			}
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
		$json = json_decode( $raw, true );
		if ( in_array( $code, array( 401, 403 ), true ) && $retry > 0 ) {
			$creds = self::panel_credentials();
			$token = $session_only ? '' : (string) ( $creds['panel_api_token'] ?? '' );
			if ( '' !== $token && ! $session_only ) {
				return array( 'ok' => false, 'code' => $code, 'body' => $raw, 'json' => is_array( $json ) ? $json : null, 'url' => $url );
			}
			self::clear_session();
			if ( self::login_with_cookie_session( 4, 300000 ) ) {
				return self::request( $path, $method, $body, $binary, $retry - 1, $scope, $session_only );
			}
		}
		if ( $binary ) {
			return array( 'ok' => 200 === $code, 'code' => $code, 'body' => $raw, 'json' => null, 'url' => $url );
		}
		return array( 'ok' => $code >= 200 && $code < 300, 'code' => $code, 'body' => $raw, 'json' => is_array( $json ) ? $json : null, 'url' => $url );
	}

	/**
	 * Whether bound panel uses MHSanaei v3 clients/* API.
	 *
	 * @return bool
	 */
	public static function is_v3_clients_api() {
		return self::FLAVOR_V3 === self::get_api_flavor();
	}

	/**
	 * Whether bound panel uses legacy inbounds/* client API.
	 *
	 * @return bool
	 */
	public static function is_legacy_inbound_api() {
		return self::FLAVOR_LEGACY === self::get_api_flavor();
	}

	/**
	 * Cached or stored API flavor for bound panel; probes panel when unknown.
	 *
	 * @param bool $refresh Force re-detect and persist.
	 * @return string
	 */
	public static function get_api_flavor( $refresh = false ) {
		$pid = (int) self::$bound_panel_id;
		$key = 'p' . $pid;
		if ( ! $refresh && isset( self::$cached_api_flavor[ $key ] ) ) {
			return (string) self::$cached_api_flavor[ $key ];
		}
		$stored = self::FLAVOR_UNKNOWN;
		if ( $pid >= 1 && class_exists( 'SimpleVPBot_Model_Panel' ) ) {
			$row = SimpleVPBot_Model_Panel::find( $pid );
			if ( $row ) {
				$stored = SimpleVPBot_Model_Panel::api_flavor( $row );
			}
		} else {
			$stored = trim( (string) get_option( 'simplevpbot_legacy_panel_api_flavor', self::FLAVOR_UNKNOWN ) );
			if ( '' === $stored ) {
				$stored = self::FLAVOR_UNKNOWN;
			}
		}
		if ( ! $refresh && self::FLAVOR_UNKNOWN !== $stored ) {
			self::$cached_api_flavor[ $key ] = $stored;
			return $stored;
		}
		$detected = self::detect_api_flavor( true );
		self::$cached_api_flavor[ $key ] = $detected;
		return $detected;
	}

	/**
	 * Probe panel after login: GET clients/list → v3; else legacy when inbounds work.
	 *
	 * @param bool $persist Save flavor on panel row or legacy option.
	 * @return string
	 */
	public static function detect_api_flavor( $persist = true ) {
		$r_v3 = self::request( 'clients/list/paged?page=1&pageSize=1', 'GET' );
		if ( self::api_http_ok( $r_v3 ) ) {
			$flavor = self::FLAVOR_V3;
		} else {
			$code = (int) ( $r_v3['code'] ?? 0 );
			if ( 404 === $code ) {
				$flavor = self::FLAVOR_LEGACY;
			} else {
				$r_inb = self::request( 'inbounds/list', 'GET' );
				$flavor = self::api_http_ok( $r_inb ) ? self::FLAVOR_LEGACY : self::FLAVOR_UNKNOWN;
			}
		}
		if ( $persist ) {
			self::set_api_flavor_for_bound_panel( $flavor );
		}
		$key = 'p' . (int) self::$bound_panel_id;
		self::$cached_api_flavor[ $key ] = $flavor;
		return $flavor;
	}

	/**
	 * Persist flavor for currently bound panel.
	 *
	 * @param string $flavor Flavor key.
	 */
	public static function set_api_flavor_for_bound_panel( $flavor ) {
		$pid = (int) self::$bound_panel_id;
		$f   = trim( (string) $flavor );
		if ( $pid >= 1 && class_exists( 'SimpleVPBot_Model_Panel' ) ) {
			SimpleVPBot_Model_Panel::set_api_flavor( $pid, $f );
		} elseif ( $pid < 1 ) {
			update_option( 'simplevpbot_legacy_panel_api_flavor', $f, false );
		}
		$key = 'p' . $pid;
		self::$cached_api_flavor[ $key ] = $f;
	}

	/**
	 * Route request path by detected API flavor.
	 *
	 * @param string               $legacy_path Legacy API path.
	 * @param string               $v3_path     v3 clients API path.
	 * @param string               $method      HTTP method.
	 * @param array<string, mixed> $body        Body.
	 * @return array{ok:bool,code:int,json:array|null,body:string,url?:string}
	 */
	public static function request_routed( $legacy_path, $v3_path, $method = 'GET', array $body = array() ) {
		$path = self::is_v3_clients_api() ? (string) $v3_path : (string) $legacy_path;
		return self::request( $path, $method, $body );
	}

	/**
	 * Map legacy client row to v3 client body (remark → comment).
	 *
	 * @param array<string, mixed> $client Client row.
	 * @return array<string, mixed>
	 */
	public static function normalize_client_for_v3( array $client ) {
		$out = $client;
		if ( ! isset( $out['comment'] ) && isset( $out['remark'] ) && '' !== trim( (string) $out['remark'] ) ) {
			$out['comment'] = (string) $out['remark'];
		}
		unset( $out['remark'], $out['up'], $out['down'], $out['total'], $out['lastOnline'] );
		if ( isset( $out['id'] ) && ! isset( $out['uuid'] ) && self::is_likely_client_uuid( (string) $out['id'] ) ) {
			$out['uuid'] = (string) $out['id'];
		}
		return $out;
	}

	/**
	 * Extract first client from legacy addClient payload.
	 *
	 * @param array<string, mixed> $payload Legacy addClient body.
	 * @return array<string, mixed>
	 */
	private static function extract_client_from_legacy_add_payload( array $payload ) {
		$settings = $payload['settings'] ?? '';
		if ( is_string( $settings ) ) {
			$dec = json_decode( $settings, true );
		} else {
			$dec = is_array( $settings ) ? $settings : array();
		}
		$clients = is_array( $dec ) && isset( $dec['clients'] ) && is_array( $dec['clients'] ) ? $dec['clients'] : array();
		return is_array( $clients[0] ?? null ) ? $clients[0] : array();
	}

	/**
	 * Normalize v3 traffic JSON to legacy `{ obj: { up, down, total } }` shape.
	 *
	 * @param array<string, mixed>|null $json Panel JSON.
	 * @return array<string, mixed>|null
	 */
	private static function normalize_traffic_response_v3( $json ) {
		if ( ! is_array( $json ) ) {
			return null;
		}
		if ( isset( $json['obj'] ) && is_array( $json['obj'] ) ) {
			return $json;
		}
		$obj = isset( $json['obj'] ) ? $json['obj'] : $json;
		if ( ! is_array( $obj ) ) {
			return $json;
		}
		return array_merge(
			$json,
			array(
				'obj' => $obj,
			)
		);
	}

	/**
	 * v3: create client on one or more inbounds.
	 *
	 * @param array<string, mixed> $client      Client fields.
	 * @param array<int>           $inbound_ids Inbound ids.
	 * @return array|null JSON response.
	 */
	public static function client_create_v3( array $client, array $inbound_ids ) {
		$ids = array_values( array_filter( array_map( 'intval', $inbound_ids ), static function ( $v ) {
			return $v > 0;
		} ) );
		$r   = self::request(
			'clients/add',
			'POST',
			array(
				'client'     => self::normalize_client_for_v3( $client ),
				'inboundIds' => $ids,
			)
		);
		return $r['json'];
	}

	/**
	 * v3: update client by email.
	 *
	 * @param string               $email       Client email (API key).
	 * @param array<string, mixed> $client      Fields to update.
	 * @param array<int>           $inbound_ids Optional inbound scope.
	 * @return array|null JSON response.
	 */
	public static function client_update_v3( $email, array $client, array $inbound_ids = array() ) {
		$path = 'clients/update/' . rawurlencode( trim( (string) $email ) );
		$ids  = array_values( array_filter( array_map( 'intval', $inbound_ids ), static function ( $v ) {
			return $v > 0;
		} ) );
		if ( ! empty( $ids ) ) {
			$path .= '?inboundIds=' . implode( ',', $ids );
		}
		$r = self::request( $path, 'POST', self::normalize_client_for_v3( $client ) );
		return $r['json'];
	}

	/**
	 * v3: delete client by email.
	 *
	 * @param string $email       Client email.
	 * @param bool   $keep_traffic Keep traffic stats.
	 * @return array|null JSON response.
	 */
	public static function client_delete_v3( $email, $keep_traffic = false ) {
		$path = 'clients/del/' . rawurlencode( trim( (string) $email ) );
		if ( $keep_traffic ) {
			$path .= '?keepTraffic=1';
		}
		$r = self::request( $path, 'POST', array() );
		return $r['json'];
	}

	/**
	 * v3: get client by email.
	 *
	 * @param string $email Client email.
	 * @return array<string, mixed>|null Client row or null.
	 */
	public static function client_get_v3( $email ) {
		$em = trim( (string) $email );
		if ( '' === $em ) {
			return null;
		}
		$r = self::request( 'clients/get/' . rawurlencode( $em ), 'GET' );
		if ( ! self::api_http_ok( $r ) ) {
			return null;
		}
		$j = is_array( $r['json'] ?? null ) ? $r['json'] : null;
		if ( ! $j ) {
			return null;
		}
		if ( isset( $j['obj']['client'] ) && is_array( $j['obj']['client'] ) ) {
			return $j['obj']['client'];
		}
		if ( isset( $j['client'] ) && is_array( $j['client'] ) ) {
			return $j['client'];
		}
		if ( isset( $j['obj'] ) && is_array( $j['obj'] ) && isset( $j['obj']['email'] ) ) {
			return $j['obj'];
		}
		return null;
	}

	/**
	 * v3: client traffic by email.
	 *
	 * @param string $email Client email.
	 * @return array|null JSON (legacy-shaped obj).
	 */
	public static function client_traffic_v3( $email ) {
		$r = self::request( 'clients/traffic/' . rawurlencode( trim( (string) $email ) ), 'GET' );
		return self::normalize_traffic_response_v3( $r['json'] ?? null );
	}

	/**
	 * v3: client IPs.
	 *
	 * @param string $email Client email.
	 * @return array|null JSON response.
	 */
	public static function client_ips_v3( $email ) {
		$r = self::request( 'clients/ips/' . rawurlencode( trim( (string) $email ) ), 'POST', array() );
		return $r['json'];
	}

	/**
	 * v3: clear client IPs.
	 *
	 * @param string $email Client email.
	 * @return array|null JSON response.
	 */
	public static function client_clear_ips_v3( $email ) {
		$r = self::request( 'clients/clearIps/' . rawurlencode( trim( (string) $email ) ), 'POST', array() );
		return $r['json'];
	}

	/**
	 * v3: reset client traffic.
	 *
	 * @param string $email Client email.
	 * @return array|null JSON response.
	 */
	public static function client_reset_traffic_v3( $email ) {
		$r = self::request( 'clients/resetTraffic/' . rawurlencode( trim( (string) $email ) ), 'POST', array() );
		return $r['json'];
	}

	/**
	 * v3: online clients.
	 *
	 * @return array|null JSON response.
	 */
	public static function clients_onlines_v3() {
		$r = self::request( 'clients/onlines', 'POST', array() );
		return $r['json'];
	}

	/**
	 * Parse onlines API JSON to a flat list of client email/tag strings (legacy + v3).
	 *
	 * @param mixed $json Decoded panel response from onlines().
	 * @return array<int, string>
	 */
	public static function parse_onlines_response( $json ) {
		if ( ! is_array( $json ) ) {
			return array();
		}
		$arr = null;
		if ( isset( $json['obj'] ) && is_array( $json['obj'] ) ) {
			$arr = $json['obj'];
		} elseif ( isset( $json['obj'] ) && is_string( $json['obj'] ) && '' !== trim( $json['obj'] ) ) {
			$decoded = json_decode( $json['obj'], true );
			$arr     = is_array( $decoded ) ? $decoded : null;
		} elseif ( isset( $json['data'] ) && is_array( $json['data'] ) ) {
			$arr = $json['data'];
		} elseif ( array_values( $json ) === $json ) {
			$arr = $json;
		}
		if ( ! is_array( $arr ) ) {
			return array();
		}
		$out = array();
		foreach ( $arr as $v ) {
			if ( is_string( $v ) && '' !== trim( $v ) ) {
				$out[] = trim( $v );
			} elseif ( is_array( $v ) ) {
				if ( ! empty( $v['email'] ) ) {
					$out[] = trim( (string) $v['email'] );
				} elseif ( ! empty( $v['Email'] ) ) {
					$out[] = trim( (string) $v['Email'] );
				}
			}
		}
		return array_values(
			array_unique(
				array_filter(
					$out,
					static function ( $em ) {
						return '' !== $em;
					}
				)
			)
		);
	}

	/**
	 * Count online clients in onlines() API response.
	 *
	 * @param mixed $json Decoded JSON or array.
	 * @return int
	 */
	public static function count_onlines_response( $json ) {
		return count( self::parse_onlines_response( $json ) );
	}

	/**
	 * Fetch online clients with explicit success/error (for monitoring).
	 *
	 * @return array{ok:bool, json:?array, error:string}
	 */
	public static function fetch_onlines() {
		if ( self::is_v3_clients_api() ) {
			$r = self::request( 'clients/onlines', 'POST', array() );
			if ( self::api_http_ok( $r ) ) {
				$json = is_array( $r['json'] ?? null ) ? $r['json'] : null;
				return array(
					'ok'    => true,
					'json'  => $json,
					'error' => '',
				);
			}
			return array(
				'ok'    => false,
				'json'  => null,
				'error' => 'clients_onlines_failed',
			);
		}
		$r = self::request( 'inbounds/onlines', 'POST', array() );
		if ( self::api_http_ok( $r ) ) {
			$json = is_array( $r['json'] ?? null ) ? $r['json'] : null;
			return array(
				'ok'    => true,
				'json'  => $json,
				'error' => '',
			);
		}
		$code = (int) ( $r['code'] ?? 0 );
		if ( 404 === $code ) {
			$r_v3 = self::request( 'clients/onlines', 'POST', array() );
			if ( self::api_http_ok( $r_v3 ) ) {
				self::detect_api_flavor( true );
				$json = is_array( $r_v3['json'] ?? null ) ? $r_v3['json'] : null;
				return array(
					'ok'    => true,
					'json'  => $json,
					'error' => '',
				);
			}
		}
		return array(
			'ok'    => false,
			'json'  => null,
			'error' => 404 === $code ? 'inbounds_onlines_not_found' : 'onlines_failed',
		);
	}

	/**
	 * v3: bulk adjust expiry/traffic for multiple clients.
	 *
	 * @param array<int, string> $emails   Client emails.
	 * @param int                $add_days Days to add (0 = skip).
	 * @param int                $add_bytes Bytes to add (0 = skip).
	 * @return array|null JSON response.
	 */
	public static function clients_bulk_adjust_v3( array $emails, $add_days = 0, $add_bytes = 0 ) {
		$list = array_values(
			array_filter(
				array_map(
					static function ( $e ) {
						return trim( (string) $e );
					},
					$emails
				),
				static function ( $e ) {
					return '' !== $e;
				}
			)
		);
		if ( empty( $list ) ) {
			return null;
		}
		$body = array( 'emails' => $list );
		if ( (int) $add_days > 0 ) {
			$body['addDays'] = (int) $add_days;
		}
		if ( (int) $add_bytes > 0 ) {
			$body['addBytes'] = (int) $add_bytes;
		}
		$r = self::request( 'clients/bulkAdjust', 'POST', $body );
		return $r['json'];
	}

	/**
	 * v3: subscription links by subId.
	 *
	 * @param string $sub_id Subscription id.
	 * @return array<int, string> Link lines.
	 */
	public static function client_sub_links_v3( $sub_id ) {
		$sid = trim( (string) $sub_id );
		if ( '' === $sid ) {
			return array();
		}
		$r = self::request( 'clients/subLinks/' . rawurlencode( $sid ), 'GET' );
		if ( ! self::api_http_ok( $r ) ) {
			return array();
		}
		$j = is_array( $r['json'] ?? null ) ? $r['json'] : null;
		if ( ! $j ) {
			return array();
		}
		$obj = isset( $j['obj'] ) ? $j['obj'] : $j;
		if ( is_string( $obj ) && '' !== $obj ) {
			return array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $obj ) ) );
		}
		if ( is_array( $obj ) ) {
			$lines = array();
			foreach ( $obj as $line ) {
				if ( is_string( $line ) && '' !== trim( $line ) ) {
					$lines[] = trim( $line );
				}
			}
			return $lines;
		}
		return array();
	}

	/**
	 * v3: config links by email.
	 *
	 * @param string $email Client email.
	 * @return array<int, string> Link lines.
	 */
	public static function client_links_v3( $email ) {
		$em = trim( (string) $email );
		if ( '' === $em ) {
			return array();
		}
		$r = self::request( 'clients/links/' . rawurlencode( $em ), 'GET' );
		if ( ! self::api_http_ok( $r ) ) {
			return array();
		}
		$j = is_array( $r['json'] ?? null ) ? $r['json'] : null;
		if ( ! $j ) {
			return array();
		}
		$obj = isset( $j['obj'] ) ? $j['obj'] : $j;
		if ( is_string( $obj ) && '' !== $obj ) {
			return array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $obj ) ) );
		}
		if ( is_array( $obj ) ) {
			$lines = array();
			foreach ( $obj as $line ) {
				if ( is_string( $line ) && '' !== trim( $line ) ) {
					$lines[] = trim( $line );
				}
			}
			return $lines;
		}
		return array();
	}

	/**
	 * v3: paged client list.
	 *
	 * @param int $page     Page (1-based).
	 * @param int $page_size Page size.
	 * @return array{clients:array<int,array<string,mixed>>,total:int}|null
	 */
	public static function clients_list_paged_v3( $page = 1, $page_size = 500 ) {
		$p  = max( 1, (int) $page );
		$ps = max( 1, min( 1000, (int) $page_size ) );
		$r  = self::request( 'clients/list/paged?page=' . $p . '&pageSize=' . $ps, 'GET' );
		if ( ! self::api_http_ok( $r ) ) {
			return null;
		}
		$j = is_array( $r['json'] ?? null ) ? $r['json'] : null;
		if ( ! $j ) {
			return null;
		}
		$obj     = isset( $j['obj'] ) && is_array( $j['obj'] ) ? $j['obj'] : $j;
		$clients = array();
		if ( isset( $obj['clients'] ) && is_array( $obj['clients'] ) ) {
			$clients = $obj['clients'];
		} elseif ( isset( $obj['list'] ) && is_array( $obj['list'] ) ) {
			$clients = $obj['list'];
		} elseif ( is_array( $obj ) && isset( $obj[0] ) ) {
			$clients = $obj;
		}
		$total = isset( $obj['total'] ) ? (int) $obj['total'] : count( $clients );
		return array(
			'clients' => is_array( $clients ) ? $clients : array(),
			'total'   => $total,
		);
	}

	/**
	 * Inbounds list.
	 *
	 * @return array|null
	 */
	public static function inbounds_list() {
		$r = self::request( 'inbounds/list', 'GET' );
		if ( ! self::api_http_ok( $r ) ) {
			return null;
		}
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
		if ( ! self::api_http_ok( $r ) ) {
			return null;
		}
		if ( ! empty( $r['json']['obj'] ) && is_array( $r['json']['obj'] ) ) {
			return $r['json']['obj'];
		}
		return null;
	}

	/**
	 * Panel JSON message field when present.
	 *
	 * @param mixed $json Decoded panel response.
	 * @return string
	 */
	public static function panel_json_msg( $json ) {
		return is_array( $json ) ? trim( (string) ( $json['msg'] ?? '' ) ) : '';
	}

	/**
	 * Whether addClient HTTP + JSON indicate success.
	 *
	 * @param array{ok?:bool,code?:int,json?:array|null} $request_result From add_client_request().
	 * @return bool
	 */
	public static function add_client_request_ok( array $request_result ) {
		if ( empty( $request_result['ok'] ) ) {
			return false;
		}
		return self::response_is_success( $request_result['json'] ?? null );
	}

	/**
	 * Add client to inbound (full request result for callers that need HTTP + JSON).
	 *
	 * @param array<string, mixed> $payload Payload (id + settings string per panel).
	 * @return array{ok:bool,code:int,json:array|null,body:string}
	 */
	public static function add_client_request( array $payload ) {
		if ( self::is_v3_clients_api() ) {
			$client = self::extract_client_from_legacy_add_payload( $payload );
			$iid    = (int) ( $payload['id'] ?? 0 );
			$r      = self::request(
				'clients/add',
				'POST',
				array(
					'client'     => self::normalize_client_for_v3( $client ),
					'inboundIds' => $iid > 0 ? array( $iid ) : array(),
				)
			);
			return array(
				'ok'   => ! empty( $r['ok'] ) && self::response_is_success( $r['json'] ?? null ),
				'code' => (int) ( $r['code'] ?? 0 ),
				'json' => is_array( $r['json'] ?? null ) ? $r['json'] : null,
				'body' => (string) ( $r['body'] ?? '' ),
			);
		}
		$r = self::request( 'inbounds/addClient', 'POST', $payload );
		return array(
			'ok'   => ! empty( $r['ok'] ),
			'code' => (int) ( $r['code'] ?? 0 ),
			'json' => is_array( $r['json'] ?? null ) ? $r['json'] : null,
			'body' => (string) ( $r['body'] ?? '' ),
		);
	}

	/**
	 * Add client to inbound.
	 *
	 * @param array<string, mixed> $payload Payload (id + settings string per panel).
	 * @return array|null Response obj.
	 */
	public static function add_client( array $payload ) {
		$r = self::add_client_request( $payload );
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
	 * Merge desired client fields into fresh inbound settings (preserves sibling clients).
	 *
	 * @param array<string, mixed> $inbound       Inbound row from panel.
	 * @param string               $email         Target client email.
	 * @param array<string, mixed> $single_client Patched client row to apply.
	 * @return array<string, mixed>|null Decoded settings or null when client missing.
	 */
	private static function merge_client_into_inbound_settings( $inbound, $email, array $single_client ) {
		if ( ! is_array( $inbound ) ) {
			return null;
		}
		$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
		$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
		if ( ! is_array( $dec ) || empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
			return null;
		}
		$want    = (string) $email;
		$matched = false;
		foreach ( $dec['clients'] as &$cl ) {
			if ( ! is_array( $cl ) || ! isset( $cl['email'] ) || (string) $cl['email'] !== $want ) {
				continue;
			}
			$cl          = array_merge( $cl, $single_client );
			$cl['email'] = $want;
			self::ensure_client_panel_id( $cl );
			$matched = true;
			break;
		}
		unset( $cl );
		if ( $matched ) {
			self::ensure_client_panel_id( $cl );
		}
		return $matched ? $dec : null;
	}

	/**
	 * Lowercase inbound protocol string (3x-ui v2.9.4).
	 *
	 * @param array<string, mixed>|null $inbound Inbound row.
	 * @return string
	 */
	private static function normalize_inbound_protocol( $inbound ) {
		if ( ! is_array( $inbound ) ) {
			return 'vless';
		}
		return strtolower( trim( (string) ( $inbound['protocol'] ?? 'vless' ) ) );
	}

	/**
	 * 3x-ui v2.9.4 updateClient payload: settings with exactly one client in `clients`.
	 *
	 * @param array<string, mixed> $single_client Patched client row.
	 * @return array{clients:array<int, array<string, mixed>>}
	 */
	private static function build_update_client_settings_payload( array $single_client ) {
		return array( 'clients' => array( $single_client ) );
	}

	/**
	 * updateClient per 3x-ui v2.9.4: POST body must contain only the target client in settings.clients[0].
	 * Panel merges that row into stored inbound settings (sibling clients are not wiped).
	 *
	 * @param int                  $inbound_id          Inbound id.
	 * @param array<string, mixed> $full_settings_dec   Caller context (used to merge patched fields for target email).
	 * @param array<string, mixed> $single_client       Patched client row.
	 * @param array<int, string>   $path_id_candidates  Extra values for /updateClient/{id} URL.
	 * @return array|null Last JSON response from panel (for logging).
	 */
	public static function update_inbound_client_sequential( $inbound_id, array $full_settings_dec, array $single_client, array $path_id_candidates ) {
		$iid          = (int) $inbound_id;
		$target_email = isset( $single_client['email'] ) ? trim( (string) $single_client['email'] ) : '';
		$single_work  = $single_client;
		if ( self::is_v3_clients_api() ) {
			if ( '' !== $target_email ) {
				$single_work['email'] = $target_email;
			}
			if ( '' !== $target_email && ! empty( $full_settings_dec['clients'] ) && is_array( $full_settings_dec['clients'] ) ) {
				foreach ( $full_settings_dec['clients'] as $cl ) {
					if ( ! is_array( $cl ) || ! isset( $cl['email'] ) || (string) $cl['email'] !== $target_email ) {
						continue;
					}
					$single_work          = array_merge( $cl, $single_work );
					$single_work['email'] = $target_email;
					break;
				}
			}
			$panel_cl = self::client_get_v3( $target_email );
			if ( is_array( $panel_cl ) ) {
				$single_work          = array_merge( $panel_cl, $single_work );
				$single_work['email'] = $target_email;
			}
			return self::client_update_v3( $target_email, $single_work, $iid > 0 ? array( $iid ) : array() );
		}
		$ids = array();
		if ( '' !== $target_email ) {
			$single_work['email'] = $target_email;
		}
		if ( '' !== $target_email && ! empty( $full_settings_dec['clients'] ) && is_array( $full_settings_dec['clients'] ) ) {
			foreach ( $full_settings_dec['clients'] as $cl ) {
				if ( ! is_array( $cl ) || ! isset( $cl['email'] ) || (string) $cl['email'] !== $target_email ) {
					continue;
				}
				$single_work          = array_merge( $cl, $single_work );
				$single_work['email'] = $target_email;
				break;
			}
		}

		$inbound  = self::inbound_get( $iid );
		$protocol = self::normalize_inbound_protocol( $inbound );
		self::ensure_client_protocol_fields( $single_work, $protocol );

		$path_primary = is_array( $inbound ) ? self::resolve_client_path_id_for_update( '', $inbound, $target_email ) : null;
		if ( is_string( $path_primary ) && '' !== $path_primary ) {
			$ids[] = $path_primary;
		}
		foreach ( $path_id_candidates as $c ) {
			$t = trim( (string) $c );
			if ( '' !== $t && ! in_array( $t, $ids, true ) ) {
				$ids[] = $t;
			}
		}
		if ( empty( $ids ) ) {
			return null;
		}

		$last = null;
		for ( $attempt = 0; $attempt < 4; $attempt++ ) {
			if ( $attempt > 0 ) {
				usleep( 320000 + $attempt * 120000 );
				self::clear_session();
				self::login_with_retries( 4, 280000 );
				if ( '' !== $target_email && ! empty( $single_client ) ) {
					$fresh = self::inbound_get( $iid );
					if ( is_array( $fresh ) ) {
						$inbound  = $fresh;
						$protocol = self::normalize_inbound_protocol( $inbound );
						$panel_cl = self::inbound_client_by_email( $fresh, $target_email );
						if ( is_array( $panel_cl ) ) {
							$single_work          = array_merge( $panel_cl, $single_client );
							$single_work['email'] = $target_email;
						}
					}
				}
			}
			self::ensure_client_protocol_fields( $single_work, $protocol );
			$payload_dec = self::build_update_client_settings_payload( $single_work );
			foreach ( $ids as $pid ) {
				$last = self::update_client(
					$pid,
					array(
						'id'       => $iid,
						'settings' => wp_json_encode( $payload_dec ),
					)
				);
				if ( self::response_is_success( $last ) ) {
					return $last;
				}
			}
		}
		return $last;
	}

	/**
	 * Whether an API request result is HTTP-success and panel JSON reports success when present.
	 *
	 * @param array{ok?:bool,code?:int,json?:array|null} $r Request result from request().
	 * @return bool
	 */
	public static function api_http_ok( $r ) {
		if ( ! is_array( $r ) || empty( $r['ok'] ) ) {
			return false;
		}
		$j = $r['json'] ?? null;
		if ( is_array( $j ) && array_key_exists( 'success', $j ) ) {
			return ! empty( $j['success'] );
		}
		return true;
	}

	/**
	 * Delete client.
	 *
	 * @param int    $inbound_id     Inbound id.
	 * @param string $client_id      Client UUID/password for /delClient/{id}.
	 * @param string $email_fallback Optional email for /delClientByEmail/{email} when UUID path fails.
	 * @return array|null
	 */
	public static function del_client( $inbound_id, $client_id, $email_fallback = '' ) {
		if ( self::is_v3_clients_api() ) {
			$em = trim( (string) $email_fallback );
			if ( '' === $em ) {
				$em = trim( (string) $client_id );
			}
			return self::client_delete_v3( $em );
		}
		$r = self::request( 'inbounds/' . (int) $inbound_id . '/delClient/' . rawurlencode( (string) $client_id ), 'POST', array() );
		if ( self::response_is_success( $r['json'] ?? null ) ) {
			return $r['json'];
		}
		$em = trim( (string) $email_fallback );
		if ( '' === $em || $em === (string) $client_id ) {
			return $r['json'];
		}
		$r2 = self::request( 'inbounds/' . (int) $inbound_id . '/delClientByEmail/' . rawurlencode( $em ), 'POST', array() );
		return $r2['json'];
	}

	/**
	 * Client traffics by email.
	 *
	 * @param string $email Email tag.
	 * @return array|null
	 */
	public static function get_client_traffics( $email ) {
		if ( self::is_v3_clients_api() ) {
			return self::client_traffic_v3( $email );
		}
		$r = self::request( 'inbounds/getClientTraffics/' . rawurlencode( $email ), 'GET' );
		return $r['json'];
	}

	/**
	 * Parse client_ips API JSON to a flat list of IP strings (legacy + v3 timestamp objects).
	 *
	 * @param mixed $json Decoded panel response from client_ips().
	 * @param int   $max  Max IPs to return.
	 * @return array<int, string>
	 */
	public static function parse_client_ips_response( $json, $max = 30 ) {
		$lim = max( 1, min( 100, (int) $max ) );
		if ( ! is_array( $json ) ) {
			return array();
		}
		$obj = array_key_exists( 'obj', $json ) ? $json['obj'] : $json;
		$ips = array();
		if ( is_string( $obj ) && '' !== $obj && 'No IP Record' !== $obj ) {
			$decoded = json_decode( $obj, true );
			$ips     = is_array( $decoded ) ? $decoded : preg_split( '/[\s,]+/', $obj );
		} elseif ( is_array( $obj ) ) {
			foreach ( $obj as $item ) {
				if ( is_string( $item ) && '' !== trim( $item ) ) {
					$ips[] = trim( $item );
				} elseif ( is_array( $item ) ) {
					if ( ! empty( $item['ip'] ) ) {
						$ips[] = trim( (string) $item['ip'] );
					} elseif ( ! empty( $item['Ip'] ) ) {
						$ips[] = trim( (string) $item['Ip'] );
					}
				}
			}
			if ( empty( $ips ) ) {
				$ips = $obj;
			}
		}
		return array_slice(
			array_values(
				array_unique(
					array_filter(
						array_map( 'trim', array_map( 'strval', (array) $ips ) ),
						static function ( $ip ) {
							return '' !== $ip && 'No IP Record' !== $ip;
						}
					)
				)
			),
			0,
			$lim
		);
	}

	/**
	 * Client rows for one inbound (v3: clients/list paged; legacy: inbound settings JSON).
	 *
	 * @param int $inbound_id Inbound id.
	 * @return array<int, array<string, mixed>>
	 */
	public static function clients_for_inbound_id( $inbound_id ) {
		$iid = (int) $inbound_id;
		if ( $iid < 1 ) {
			return array();
		}
		if ( self::is_v3_clients_api() ) {
			$out  = array();
			$page = 1;
			while ( $page <= 20 ) {
				$batch = self::clients_list_paged_v3( $page, 500 );
				if ( ! is_array( $batch ) || empty( $batch['clients'] ) ) {
					break;
				}
				foreach ( $batch['clients'] as $c ) {
					if ( ! is_array( $c ) || empty( $c['email'] ) ) {
						continue;
					}
					$inbound_ids = $c['inboundIds'] ?? $c['inbound_ids'] ?? array();
					if ( ! is_array( $inbound_ids ) ) {
						$inbound_ids = array();
					}
					$match = false;
					foreach ( $inbound_ids as $ciid ) {
						if ( (int) $ciid === $iid ) {
							$match = true;
							break;
						}
					}
					if ( $match ) {
						$out[] = $c;
					}
				}
				if ( count( $batch['clients'] ) < 500 ) {
					break;
				}
				++$page;
			}
			return $out;
		}
		$inbound = self::inbound_get( $iid );
		if ( ! is_array( $inbound ) ) {
			return array();
		}
		$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
		$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
		if ( ! is_array( $dec ) || empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
			return array();
		}
		return $dec['clients'];
	}

	/**
	 * Client IPs.
	 *
	 * @param string $email Email.
	 * @return array|null
	 */
	public static function client_ips( $email ) {
		if ( self::is_v3_clients_api() ) {
			return self::client_ips_v3( $email );
		}
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
		if ( self::is_v3_clients_api() ) {
			return self::client_clear_ips_v3( $email );
		}
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
		if ( self::is_v3_clients_api() ) {
			return self::client_reset_traffic_v3( $email );
		}
		$r = self::request( 'inbounds/' . (int) $inbound_id . '/resetClientTraffic/' . rawurlencode( $email ), 'POST', array() );
		return $r['json'];
	}

	/**
	 * Online clients emails.
	 *
	 * @return array|null
	 */
	public static function onlines() {
		$fetch = self::fetch_onlines();
		return ! empty( $fetch['ok'] ) ? $fetch['json'] : null;
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

	/** Last getDb failure step for backup diagnostics. */
	private static $last_get_db_step = '';

	/** Last getDb HTTP status (0 if unknown). */
	private static $last_get_db_http = 0;

	/**
	 * Whether bytes look like a SQLite 3 database file.
	 *
	 * @param string $raw Raw body.
	 * @return bool
	 */
	public static function is_sqlite_bytes( $raw ) {
		$raw = (string) $raw;
		if ( strlen( $raw ) < 512 ) {
			return false;
		}
		return 0 === strncmp( $raw, 'SQLite format 3', 15 );
	}

	/**
	 * Last getDb diagnostic step (login, download, invalid_response, …).
	 *
	 * @return string
	 */
	public static function last_get_db_step() {
		return (string) self::$last_get_db_step;
	}

	/**
	 * Last getDb HTTP status code from the panel (0 if none).
	 *
	 * @return int
	 */
	public static function last_get_db_http() {
		return (int) self::$last_get_db_http;
	}

	/**
	 * Log getDb failure with response sample for debugging.
	 *
	 * @param array{ok?:bool, code?:int, body?:string, url?:string} $r       Request result.
	 * @param string                                                $reason Step key.
	 */
	private static function log_get_db_failure( $r, $reason ) {
		if ( ! class_exists( 'SimpleVPBot_Logger' ) ) {
			return;
		}
		$raw = is_string( $r['body'] ?? null ) ? (string) $r['body'] : '';
		SimpleVPBot_Logger::error(
			'x-ui getDb failed',
			array(
				'step'     => (string) $reason,
				'code'     => (int) ( $r['code'] ?? 0 ),
				'url'      => (string) ( $r['url'] ?? '' ),
				'panel_id' => (int) self::$bound_panel_id,
				'sample'   => mb_substr( $raw, 0, 128 ),
			)
		);
	}

	/**
	 * Parse getDb HTTP response into validated SQLite bytes or false.
	 *
	 * @param array{ok?:bool, code?:int, body?:string, url?:string} $r Request result.
	 * @return string|false
	 */
	private static function parse_db_binary_response( $r ) {
		$code = (int) ( $r['code'] ?? 0 );
		self::$last_get_db_http = $code;
		$raw  = is_string( $r['body'] ?? null ) ? (string) $r['body'] : '';
		if ( empty( $r['ok'] ) || '' === $raw ) {
			self::$last_get_db_step = 401 === $code || 403 === $code ? 'auth' : 'http_' . $code;
			self::log_get_db_failure( $r, self::$last_get_db_step );
			return false;
		}
		$trim = ltrim( $raw );
		if ( '' !== $trim && ( '{' === $trim[0] || '[' === $trim[0] ) ) {
			self::$last_get_db_step = 'invalid_response';
			self::log_get_db_failure( $r, 'invalid_response' );
			return false;
		}
		if ( ! self::is_sqlite_bytes( $raw ) ) {
			self::$last_get_db_step = 'invalid_response';
			self::log_get_db_failure( $r, 'invalid_response' );
			return false;
		}
		self::$last_get_db_step = '';
		return $raw;
	}

	/**
	 * Whether Bearer should be attempted for getDb (token-only panels).
	 *
	 * @return bool
	 */
	private static function should_try_bearer_for_getdb() {
		return self::has_api_token()
			&& ! self::has_cookie_credentials()
			&& '' === self::cookie_header();
	}

	/**
	 * Download DB bytes — optional Bearer first, then cookie GET (3x-ui v2.9.4+ is GET-only).
	 *
	 * @param bool $try_bearer_first Attempt Bearer when token is set.
	 * @return string|false
	 */
	private static function get_db_binary_inner( $try_bearer_first ) {
		if ( $try_bearer_first && self::has_api_token() ) {
			$r  = self::request( 'server/getDb', 'GET', array(), true, 2, 'api', false );
			$db = self::parse_db_binary_response( $r );
			if ( false !== $db ) {
				return $db;
			}
		}
		$r = self::request( 'server/getDb', 'GET', array(), true, 2, 'api', true );
		return self::parse_db_binary_response( $r );
	}

	/**
	 * Download DB file bytes for normal/diagnostic use (skips Bearer when cookie creds exist).
	 *
	 * @return string|false
	 */
	public static function get_db_binary() {
		return self::get_db_binary_inner( self::has_api_token() && ! self::has_cookie_credentials() );
	}

	/**
	 * Faster getDb path for scheduled/manual backup (shorter timeout, no wasted Bearer attempts).
	 *
	 * @return string|false
	 */
	public static function get_db_binary_for_backup() {
		$prev                     = self::$request_timeout_sec;
		self::$request_timeout_sec = 30;
		try {
			return self::get_db_binary_inner( self::should_try_bearer_for_getdb() );
		} finally {
			self::$request_timeout_sec = $prev;
		}
	}

	/**
	 * Probe getDb for panel test / diagnostics.
	 *
	 * @return array{ok:bool, step:string, url:string, http:int, bytes:int}
	 */
	public static function probe_get_db() {
		$url = self::diag_url( 'server/getDb', 'api' );
		if ( self::has_api_token() ) {
			$db = self::get_db_binary_inner( true );
			if ( false !== $db && '' !== $db ) {
				return array(
					'ok'    => true,
					'step'  => '',
					'url'   => $url,
					'http'  => 200,
					'bytes' => strlen( $db ),
				);
			}
		}
		$db  = self::get_db_binary_with_retries( 2, false );
		if ( false !== $db && '' !== $db ) {
			return array(
				'ok'    => true,
				'step'  => '',
				'url'   => $url,
				'http'  => 200,
				'bytes' => strlen( $db ),
			);
		}
		$step = self::last_get_db_step();
		if ( '' === $step ) {
			$step = 'download';
		}
		if ( self::has_cookie_credentials() ) {
			self::clear_session();
			if ( self::login_with_cookie_session( 4, 300000 ) ) {
				$db = self::get_db_binary_with_retries( 2 );
				if ( false !== $db && '' !== $db ) {
					return array(
						'ok'    => true,
						'step'  => '',
						'url'   => $url,
						'http'  => 200,
						'bytes' => strlen( $db ),
					);
				}
				$step = self::last_get_db_step();
				if ( '' === $step ) {
					$step = 'download';
				}
			} else {
				$step = 'login';
			}
		} elseif ( ! self::has_api_token() ) {
			$step = 'missing_cookie_creds';
		}
		$http = self::last_get_db_http();
		if ( 404 === $http && 'http_404' !== $step ) {
			$step = 'http_404';
		}
		return array(
			'ok'    => false,
			'step'  => (string) $step,
			'url'   => $url,
			'http'  => $http,
			'bytes' => 0,
		);
	}

	/**
	 * Download panel DB with retries (transient getDb failures).
	 *
	 * @param int  $attempts   Attempt count (minimum 1).
	 * @param bool $for_backup Use backup-fast getDb path when true.
	 * @return string|false
	 */
	public static function get_db_binary_with_retries( $attempts = 3, $for_backup = false ) {
		$max = max( 1, (int) $attempts );
		for ( $i = 0; $i < $max; $i++ ) {
			$db = $for_backup ? self::get_db_binary_for_backup() : self::get_db_binary();
			if ( false !== $db && '' !== $db ) {
				return $db;
			}
			if ( $i + 1 < $max ) {
				usleep( $for_backup ? 250000 + $i * 150000 : 400000 + $i * 300000 );
			}
		}
		return false;
	}

	/**
	 * Import panel SQLite via POST server/importDB (multipart field "db").
	 *
	 * @param string $db_path Absolute path to .db file.
	 * @return array{ok:bool, message?:string, code?:int}
	 */
	public static function import_db_from_path( $db_path ) {
		$db_path = (string) $db_path;
		if ( '' === $db_path || ! is_readable( $db_path ) ) {
			return array( 'ok' => false, 'message' => 'unreadable_db' );
		}
		$size = (int) @filesize( $db_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( $size < 512 ) {
			return array( 'ok' => false, 'message' => 'db_too_small' );
		}
		return self::import_db_multipart( $db_path, 'x-ui.db' );
	}

	/**
	 * Multipart POST server/importDB.
	 *
	 * @param string $db_path   Local .db path.
	 * @param string $filename  Filename sent to panel.
	 * @param int    $retry     Re-login retries on 401/403.
	 * @return array{ok:bool, message?:string, code?:int}
	 */
	private static function import_db_multipart( $db_path, $filename, $retry = 2 ) {
		$url      = self::resolve_url( 'server/importDB', 'api' );
		$boundary = '----' . wp_generate_password( 16, false, false );
		$data     = (string) file_get_contents( $db_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( '' === $data ) {
			return array( 'ok' => false, 'message' => 'read_failed' );
		}
		$fn   = preg_replace( '/[^a-zA-Z0-9._-]/', '', (string) $filename );
		if ( '' === $fn ) {
			$fn = 'x-ui.db';
		}
		$body  = "--{$boundary}\r\n";
		$body .= "Content-Disposition: form-data; name=\"db\"; filename=\"{$fn}\"\r\n";
		$body .= "Content-Type: application/octet-stream\r\n\r\n";
		$body .= $data . "\r\n";
		$body .= "--{$boundary}--\r\n";

		$headers = array(
			'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
			'Accept'       => 'application/json',
		);
		$creds = self::panel_credentials();
		$token = (string) ( $creds['panel_api_token'] ?? '' );
		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		} else {
			$cookie = self::cookie_header();
			if ( $cookie ) {
				$headers['Cookie'] = $cookie;
			}
			$csrf = get_transient( self::csrf_transient_name() );
			if ( is_string( $csrf ) && '' !== $csrf ) {
				$headers['X-CSRF-Token'] = $csrf;
			}
		}

		$res  = wp_remote_post(
			$url,
			array(
				'timeout' => 180,
				'method'  => 'POST',
				'headers' => $headers,
				'body'    => $body,
			)
		);
		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'message' => $res->get_error_message(), 'code' => 0 );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$raw  = (string) wp_remote_retrieve_body( $res );
		$json = json_decode( $raw, true );
		if ( in_array( $code, array( 401, 403 ), true ) && $retry > 0 && '' === $token ) {
			self::clear_session();
			if ( self::login_with_retries( 4, 300000 ) ) {
				return self::import_db_multipart( $db_path, $filename, $retry - 1 );
			}
		}
		if ( $code >= 200 && $code < 300 && self::response_is_success( $json ) ) {
			return array( 'ok' => true, 'code' => $code );
		}
		$msg = is_array( $json ) ? self::panel_json_msg( $json ) : '';
		if ( '' === $msg ) {
			$msg = trim( $raw );
		}
		if ( strlen( $msg ) > 200 ) {
			$msg = substr( $msg, 0, 197 ) . '…';
		}
		return array(
			'ok'      => false,
			'message' => '' !== $msg ? $msg : 'import_failed',
			'code'    => $code,
		);
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
		$want = trim( (string) $email );
		if ( '' === $want ) {
			return null;
		}
		if ( self::is_v3_clients_api() ) {
			$cl = self::client_get_v3( $want );
			return is_array( $cl ) ? $cl : null;
		}
		if ( ! is_array( $inbound ) ) {
			return null;
		}
		$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
		$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
		if ( ! is_array( $dec ) || empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
			return null;
		}
		foreach ( $dec['clients'] as $c ) {
			if ( is_array( $c ) && isset( $c['email'] ) && (string) $c['email'] === $want ) {
				return $c;
			}
		}
		return null;
	}

	/**
	 * Ensure a panel client row has a non-empty UUID in `id` (from id, password, subId).
	 *
	 * @param array<string, mixed> $client Client row (by reference).
	 * @return bool True when `id` is set to a likely UUID.
	 */
	public static function ensure_client_panel_id( array &$client ) {
		if ( ! is_array( $client ) ) {
			return false;
		}
		$parsed = self::parse_uuid_value( $client['id'] ?? null );
		if ( is_string( $parsed ) ) {
			$client['id'] = $parsed;
			return true;
		}
		$cur = trim( (string) ( $client['id'] ?? '' ) );
		if ( self::is_likely_client_uuid( $cur ) ) {
			$client['id'] = $cur;
			return true;
		}
		foreach ( array( 'password', 'subId' ) as $k ) {
			if ( ! array_key_exists( $k, $client ) ) {
				continue;
			}
			$pv = self::parse_uuid_value( $client[ $k ] );
			if ( is_string( $pv ) ) {
				$client['id'] = $pv;
				return true;
			}
			$t = trim( (string) $client[ $k ] );
			if ( self::is_likely_client_uuid( $t ) ) {
				$client['id'] = $t;
				return true;
			}
		}
		return false;
	}

	/**
	 * Normalize `id` on every client in a settings blob (legacy helper).
	 *
	 * @param array<string, mixed> $settings_dec Decoded inbound settings.
	 * @return void
	 */
	public static function sanitize_settings_clients_for_update( array &$settings_dec ) {
		if ( empty( $settings_dec['clients'] ) || ! is_array( $settings_dec['clients'] ) ) {
			return;
		}
		foreach ( $settings_dec['clients'] as &$cl ) {
			if ( is_array( $cl ) ) {
				self::ensure_client_panel_id( $cl );
			}
		}
		unset( $cl );
	}

	/**
	 * Fill protocol-required client fields for 3x-ui v2.9.4 updateClient (clients[0] validation).
	 *
	 * @param array<string, mixed> $client   Client row (by reference).
	 * @param string               $protocol Inbound protocol (lowercase).
	 * @return bool True when required primary field is non-empty.
	 */
	public static function ensure_client_protocol_fields( array &$client, $protocol ) {
		if ( ! is_array( $client ) ) {
			return false;
		}
		$protocol = strtolower( trim( (string) $protocol ) );
		self::ensure_client_panel_id( $client );
		$uuid = trim( (string) ( $client['id'] ?? '' ) );
		if ( ! self::is_likely_client_uuid( $uuid ) ) {
			$uuid = '';
		}
		switch ( $protocol ) {
			case 'trojan':
				$pw = trim( (string) ( $client['password'] ?? '' ) );
				if ( '' === $pw && '' !== $uuid ) {
					$client['password'] = $uuid;
					$pw                 = $uuid;
				}
				if ( '' === $pw ) {
					foreach ( array( 'password', 'subId' ) as $k ) {
						$t = trim( (string) ( $client[ $k ] ?? '' ) );
						if ( '' !== $t ) {
							$client['password'] = $t;
							$pw                 = $t;
							break;
						}
					}
				}
				return '' !== $pw;
			case 'shadowsocks':
				return '' !== trim( (string) ( $client['email'] ?? '' ) );
			case 'hysteria':
			case 'hysteria2':
				$auth = trim( (string) ( $client['auth'] ?? '' ) );
				if ( '' === $auth && '' !== $uuid ) {
					$client['auth'] = $uuid;
					$auth           = $uuid;
				}
				return '' !== $auth;
			default:
				if ( '' !== $uuid ) {
					$client['id'] = $uuid;
					return true;
				}
				return self::ensure_client_panel_id( $client );
		}
	}

	/**
	 * Path segment for /updateClient/{id} per 3x-ui v2.9.4 getClientPrimaryKey.
	 *
	 * @param string               $db_id   Stored xui id (optional hint).
	 * @param array<string, mixed> $inbound Inbound row.
	 * @param string               $email   Client email tag.
	 * @return string|null
	 */
	public static function resolve_client_path_id_for_update( $db_id, $inbound, $email ) {
		$protocol = self::normalize_inbound_protocol( $inbound );
		$sid      = trim( (string) $db_id );
		$cl       = self::inbound_client_by_email( $inbound, $email );
		if ( is_array( $cl ) ) {
			$row = $cl;
			self::ensure_client_protocol_fields( $row, $protocol );
			switch ( $protocol ) {
				case 'trojan':
					$key = trim( (string) ( $row['password'] ?? '' ) );
					break;
				case 'shadowsocks':
					$key = trim( (string) ( $row['email'] ?? $email ) );
					break;
				case 'hysteria':
				case 'hysteria2':
					$key = trim( (string) ( $row['auth'] ?? '' ) );
					break;
				default:
					$key = trim( (string) ( $row['id'] ?? '' ) );
			}
			if ( '' !== $key ) {
				return $key;
			}
		}
		if ( self::is_likely_client_uuid( $sid ) && 0 !== strcasecmp( $sid, 'array' ) ) {
			return $sid;
		}
		if ( 'shadowsocks' === $protocol ) {
			$em = trim( (string) $email );
			if ( '' !== $em ) {
				return $em;
			}
		}
		if ( 'trojan' === $protocol && '' !== $sid ) {
			return $sid;
		}
		$em = trim( (string) $email );
		return '' !== $em ? $em : null;
	}

	/**
	 * ID for /updateClient/{id} (alias of resolve_client_path_id_for_update).
	 *
	 * @param string               $db_id  Stored xui id.
	 * @param array<string, mixed> $inbound Inbound.
	 * @param string                $email Client email.
	 * @return string|null
	 */
	public static function resolve_client_key_for_update( $db_id, $inbound, $email ) {
		$path = self::resolve_client_path_id_for_update( $db_id, $inbound, $email );
		return ( is_string( $path ) && '' !== $path ) ? $path : null;
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
