<?php
/**
 * REST API for /dashboard SPA.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Rest_Dashboard
 */
class SimpleVPBot_Rest_Dashboard {

	const NS = 'simplevpbot/v1';

	/**
	 * Init routes.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register routes.
	 */
	public static function register() {
		register_rest_route(
			self::NS,
			'/dashboard/bootstrap',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'route_bootstrap' ),
				'permission_callback' => array( __CLASS__, 'perm_logged_in' ),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/login',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'route_dashboard_login' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/state',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'route_admin_state' ),
				'permission_callback' => array( __CLASS__, 'perm_admin_or_reseller' ),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/me/state',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'route_me_state' ),
				'permission_callback' => array( __CLASS__, 'perm_logged_in' ),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/persona',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'route_set_persona' ),
				'permission_callback' => array( __CLASS__, 'perm_logged_in' ),
				'args'                => array(
					'persona' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/impersonate/start',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'route_impersonate_start' ),
				'permission_callback' => array( __CLASS__, 'perm_impersonate_start' ),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/impersonate/stop',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'route_impersonate_stop' ),
				'permission_callback' => array( __CLASS__, 'perm_impersonate_stop' ),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/user/(?P<id>\\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'route_admin_user' ),
				'permission_callback' => array( __CLASS__, 'perm_admin_or_reseller' ),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/user-search',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'route_admin_user_search' ),
				'permission_callback' => array( __CLASS__, 'perm_admin_or_reseller' ),
				'args'                => array(
					'q' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/panel-inbounds',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'route_panel_inbounds' ),
				'permission_callback' => array( __CLASS__, 'perm_manage' ),
				'args'                => array(
					'panel_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/panel-inbound-clients',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'route_panel_inbound_clients' ),
				'permission_callback' => array( __CLASS__, 'perm_manage' ),
				'args'                => array(
					'panel_id'   => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'inbound_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/configs-snapshot',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'route_configs_snapshot' ),
				'permission_callback' => array( __CLASS__, 'perm_manage' ),
				'args'                => array(
					'panel_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/configs-portal-payload',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'route_configs_portal_payload' ),
				'permission_callback' => array( __CLASS__, 'perm_manage' ),
				'args'                => array(
					'service_id' => array(
						'default'           => 0,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'panel_id'   => array(
						'default'           => 0,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'inbound_id' => array(
						'default'           => 0,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'email'      => array(
						'default'           => '',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/configs-sync',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'route_configs_sync' ),
				'permission_callback' => array( __CLASS__, 'perm_manage' ),
				'args'                => array(
					'panel_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/mutate',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'route_admin_mutate' ),
				'permission_callback' => array( __CLASS__, 'perm_admin_or_reseller' ),
				'args'                => array(),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/media',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'route_admin_media_upload' ),
				'permission_callback' => array( __CLASS__, 'perm_manage_or_reseller_broadcast_send' ),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/broadcast-queue',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'route_admin_broadcast_queue' ),
				'permission_callback' => array( __CLASS__, 'perm_manage_or_reseller_broadcast_send' ),
				'args'                => array(
					'broadcast_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'page'         => array(
						'default'           => 1,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'per_page'     => array(
						'default'           => 25,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/users-bulk-jobs',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'route_admin_users_bulk_jobs' ),
				'permission_callback' => array( __CLASS__, 'perm_admin_or_reseller' ),
				'args'                => array(
					'page'     => array(
						'default'           => 1,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'default'           => 20,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/users-bulk-job-items',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'route_admin_users_bulk_job_items' ),
				'permission_callback' => array( __CLASS__, 'perm_admin_or_reseller' ),
				'args'                => array(
					'job_id'   => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'page'     => array(
						'default'           => 1,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'default'           => 25,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * @return bool
	 */
	public static function perm_logged_in() {
		return is_user_logged_in();
	}

	/**
	 * @return bool
	 */
	public static function perm_manage() {
		if ( is_array( self::get_impersonation_payload() ) ) {
			return false;
		}
		return current_user_can( 'manage_options' );
	}

	/**
	 * Start impersonation: real site admin only (still true while impersonating in WP session).
	 *
	 * @return bool
	 */
	public static function perm_impersonate_start() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Stop impersonation: site admin or anyone holding a valid (signed, unexpired) impersonation cookie.
	 *
	 * @return bool
	 */
	public static function perm_impersonate_stop() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return self::parse_impersonation_cookie_token() > 0;
	}

	/**
	 * Admin or linked reseller (dashboard role).
	 *
	 * @return bool
	 */
	public static function perm_admin_or_reseller() {
		$ctx = self::dashboard_actor_context();
		return ! empty( $ctx['isAdmin'] ) || ! empty( $ctx['isReseller'] );
	}

	/**
	 * Site admin (not impersonating another reseller) or reseller with broadcast.send (for broadcast media / queue helpers).
	 *
	 * @return bool
	 */
	public static function perm_manage_or_reseller_broadcast_send() {
		if ( self::perm_manage() ) {
			return true;
		}
		$ctx = self::dashboard_actor_context();
		if ( empty( $ctx['isReseller'] ) ) {
			return false;
		}
		$actor = (int) ( $ctx['actorUserId'] ?? 0 );
		if ( $actor < 1 || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return false;
		}
		$perms = SimpleVPBot_Model_User::reseller_permissions( $actor );
		return ! empty( $perms['broadcast.send'] );
	}

	/**
	 * Whether the current REST user acts as unrestricted site admin (manage_options, not dashboard impersonation).
	 *
	 * @return bool
	 */
	private static function dashboard_rest_is_unrestricted_site_admin() {
		return current_user_can( 'manage_options' ) && ! is_array( self::get_impersonation_payload() );
	}

	const DASH_PERSONA_COOKIE = 'svp_dash_persona';

	/** Signed cookie: target_svp_id|exp|hmac (admin viewing dashboard as a reseller). */
	const DASH_IMP_COOKIE = 'svp_dash_imp';

	/**
	 * Memoized valid impersonation payload for this request (or null).
	 *
	 * @var bool
	 */
	private static $impersonation_memoized = false;

	/**
	 * @var array{target_id:int,target_row:object}|null
	 */
	private static $impersonation_payload = null;

	/**
	 * Logout URL for SPA (avoids wp-login.php).
	 *
	 * @return string
	 */
	public static function dashboard_logout_url() {
		$url = trailingslashit( home_url( '/dashboard/logout' ) );
		return wp_nonce_url( $url, 'simplevpbot_dash_logout' );
	}

	/**
	 * Parse impersonation cookie; returns target svp user id or 0 if missing/invalid/expired.
	 *
	 * @return int
	 */
	private static function parse_impersonation_cookie_token() {
		if ( empty( $_COOKIE[ self::DASH_IMP_COOKIE ] ) ) {
			return 0;
		}
		$raw = sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::DASH_IMP_COOKIE ] ) );
		$parts = explode( '|', $raw );
		if ( count( $parts ) !== 3 ) {
			return 0;
		}
		$id  = absint( $parts[0] );
		$exp = absint( $parts[1] );
		$sig = $parts[2];
		if ( $id < 1 || $exp < time() || strlen( $sig ) !== 64 || ! ctype_xdigit( $sig ) ) {
			return 0;
		}
		$data   = $id . '|' . $exp;
		$expect = hash_hmac( 'sha256', $data, wp_salt( 'auth' ) );
		if ( ! hash_equals( $expect, $sig ) ) {
			return 0;
		}
		return $id;
	}

	/**
	 * Valid impersonation session: site admin + cookie + target exists and is a reseller.
	 *
	 * @return array{target_id:int,target_row:object}|null
	 */
	private static function get_impersonation_payload() {
		if ( self::$impersonation_memoized ) {
			return self::$impersonation_payload;
		}
		self::$impersonation_memoized = true;
		self::$impersonation_payload  = null;
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return null;
		}
		$tid = self::parse_impersonation_cookie_token();
		if ( $tid < 1 || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return null;
		}
		$trow = SimpleVPBot_Model_User::find( $tid );
		if ( ! $trow || ! SimpleVPBot_Model_User::is_reseller_row( $trow ) ) {
			return null;
		}
		self::$impersonation_payload = array(
			'target_id'  => $tid,
			'target_row' => $trow,
		);
		return self::$impersonation_payload;
	}

	/**
	 * @param object $row User row.
	 * @param int    $fallback_id Fallback id for label.
	 * @return string
	 */
	private static function dashboard_svp_row_display_label( $row, $fallback_id = 0 ) {
		$fn = isset( $row->first_name ) ? trim( (string) $row->first_name ) : '';
		$ln = isset( $row->last_name ) ? trim( (string) $row->last_name ) : '';
		$name = trim( $fn . ' ' . $ln );
		if ( '' !== $name ) {
			return $name;
		}
		$u = isset( $row->username ) ? trim( (string) $row->username ) : '';
		if ( '' !== $u ) {
			return $u;
		}
		return '#' . (string) (int) $fallback_id;
	}

	/**
	 * Sidebar footer profile (name + Telegram / Bale IDs) for the dashboard actor row.
	 *
	 * @param int $actor_svp_id SVP user id.
	 * @return array{label:string,svp_user_id:int,tg_user_id:int,bale_user_id:int,balance?:float}|null
	 */
	public static function sidebar_user_payload( $actor_svp_id ) {
		$actor_svp_id = (int) $actor_svp_id;
		if ( $actor_svp_id < 1 || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return null;
		}
		$r = SimpleVPBot_Model_User::find( $actor_svp_id );
		if ( ! $r ) {
			return null;
		}
		return array(
			'label'         => self::dashboard_svp_row_display_label( $r, $actor_svp_id ),
			'svp_user_id'   => $actor_svp_id,
			'tg_user_id'    => (int) ( $r->tg_user_id ?? 0 ),
			'bale_user_id'  => (int) ( $r->bale_user_id ?? 0 ),
			'balance'       => round( (float) ( $r->balance ?? 0 ), 2 ),
		);
	}

	/**
	 * @param int $target_svp_user_id Target.
	 */
	private static function impersonation_cookie_set( $target_svp_user_id ) {
		$ttl = (int) apply_filters( 'simplevpbot_dash_impersonate_ttl', HOUR_IN_SECONDS );
		$ttl = max( 60, $ttl );
		$exp = time() + $ttl;
		$id  = absint( $target_svp_user_id );
		$data = $id . '|' . $exp;
		$sig  = hash_hmac( 'sha256', $data, wp_salt( 'auth' ) );
		$val  = $data . '|' . $sig;
		$path   = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		$domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
		$secure = is_ssl();
		$opts   = array(
			'expires'  => $exp,
			'path'     => $path,
			'domain'   => $domain,
			'secure'   => $secure,
			'httponly' => true,
			'samesite' => 'Lax',
		);
		if ( PHP_VERSION_ID >= 70300 ) {
			setcookie( self::DASH_IMP_COOKIE, $val, $opts );
		} else {
			setcookie( self::DASH_IMP_COOKIE, $val, $opts['expires'], $path, $domain, $secure, true );
		}
	}

	private static function impersonation_cookie_clear() {
		$path   = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		$domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
		$secure = is_ssl();
		$past   = time() - HOUR_IN_SECONDS;
		$opts   = array(
			'expires'  => $past,
			'path'     => $path,
			'domain'   => $domain,
			'secure'   => $secure,
			'httponly' => true,
			'samesite' => 'Lax',
		);
		if ( PHP_VERSION_ID >= 70300 ) {
			setcookie( self::DASH_IMP_COOKIE, '', $opts );
		} else {
			setcookie( self::DASH_IMP_COOKIE, '', $past, $path, $domain, $secure, true );
		}
	}

	/**
	 * @return string|null admin|reseller|user
	 */
	private static function dashboard_persona_cookie_read() {
		if ( empty( $_COOKIE[ self::DASH_PERSONA_COOKIE ] ) ) {
			return null;
		}
		$p = sanitize_key( (string) wp_unslash( $_COOKIE[ self::DASH_PERSONA_COOKIE ] ) );
		if ( in_array( $p, array( 'admin', 'reseller', 'user' ), true ) ) {
			return $p;
		}
		return null;
	}

	/**
	 * @param string $persona admin|reseller|user
	 */
	private static function dashboard_persona_cookie_set( $persona ) {
		$path   = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		$domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
		$secure = is_ssl();
		$opts   = array(
			'expires'  => time() + YEAR_IN_SECONDS,
			'path'     => $path,
			'domain'   => $domain,
			'secure'   => $secure,
			'httponly' => true,
			'samesite' => 'Lax',
		);
		if ( PHP_VERSION_ID >= 70300 ) {
			setcookie( self::DASH_PERSONA_COOKIE, $persona, $opts );
		} else {
			setcookie( self::DASH_PERSONA_COOKIE, $persona, $opts['expires'], $path, $domain, $secure, true );
		}
	}

	/**
	 * @param bool $is_wp_admin   WP manage_options.
	 * @param bool $is_reseller_row Linked svp user is reseller role.
	 * @param bool $has_linked_row Linked svp user exists.
	 * @return string[]
	 */
	private static function dashboard_available_personas( $is_wp_admin, $is_reseller_row, $has_linked_row ) {
		$out = array();
		if ( $is_wp_admin ) {
			$out[] = 'admin';
		}
		if ( $is_reseller_row ) {
			$out[] = 'reseller';
		}
		if ( $has_linked_row ) {
			$out[] = 'user';
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param string[] $available Personas allowed for this account.
	 * @param string|null $cookie_persona From cookie.
	 * @param bool $is_wp_admin WP manage_options.
	 * @param bool $is_reseller_row Linked svp user is reseller.
	 * @return string
	 */
	private static function dashboard_pick_persona( $available, $cookie_persona, $is_wp_admin, $is_reseller_row ) {
		if ( $cookie_persona && in_array( $cookie_persona, $available, true ) ) {
			return $cookie_persona;
		}
		if ( $is_wp_admin ) {
			return 'admin';
		}
		if ( $is_reseller_row ) {
			return 'reseller';
		}
		return 'user';
	}

	/**
	 * Resolve current dashboard actor context (persona cookie can downgrade WP admin to reseller/user UI).
	 *
	 * @return array{isAdmin:bool,isReseller:bool,actorUserId:int,actorRow:object|null,activePersona:string,availablePersonas:string[]}
	 */
	public static function dashboard_actor_context() {
		$is_wp_admin     = current_user_can( 'manage_options' );
		$row             = null;
		$uid             = 0;
		$is_reseller_row = false;
		if ( class_exists( 'SimpleVPBot_Model_User' ) ) {
			$row = SimpleVPBot_Model_User::find_by_wp_user( get_current_user_id() );
			if ( $row ) {
				$uid             = (int) ( $row->id ?? 0 );
				$is_reseller_row = SimpleVPBot_Model_User::is_reseller_row( $row );
			}
		}
		$imp = self::get_impersonation_payload();
		if ( is_array( $imp ) ) {
			$trow = $imp['target_row'];
			$tid  = (int) $imp['target_id'];
			$has_linked = ( $tid > 0 );
			$available  = self::dashboard_available_personas( false, true, $has_linked );
			if ( empty( $available ) ) {
				$available = array( 'reseller' );
			}
			$cookie_p = self::dashboard_persona_cookie_read();
			$active   = self::dashboard_pick_persona( $available, $cookie_p, false, true );
			if ( ! in_array( $active, $available, true ) ) {
				$active = 'reseller';
			}
			$is_admin_eff    = ( 'admin' === $active );
			$is_reseller_eff = ( 'reseller' === $active );
			$label           = self::dashboard_svp_row_display_label( $trow, $tid );
			return array(
				'isAdmin'                  => $is_admin_eff,
				'isReseller'               => $is_reseller_eff,
				'actorUserId'              => $tid,
				'actorRow'                 => $trow,
				'activePersona'            => $active,
				'availablePersonas'        => $available,
				'impersonating'            => true,
				'impersonationTargetId'    => $tid,
				'impersonationTargetLabel' => $label,
			);
		}
		$has_linked = ( $uid > 0 );
		$available  = self::dashboard_available_personas( $is_wp_admin, $is_reseller_row, $has_linked );
		if ( empty( $available ) ) {
			return array(
				'isAdmin'                  => false,
				'isReseller'               => false,
				'actorUserId'              => 0,
				'actorRow'                 => null,
				'activePersona'            => 'user',
				'availablePersonas'      => array(),
				'impersonating'            => false,
				'impersonationTargetId'    => 0,
				'impersonationTargetLabel' => '',
			);
		}
		$cookie_p = self::dashboard_persona_cookie_read();
		$active   = self::dashboard_pick_persona( $available, $cookie_p, $is_wp_admin, $is_reseller_row );
		if ( ! in_array( $active, $available, true ) ) {
			$active = $available[0];
		}
		$is_admin_eff    = ( 'admin' === $active );
		$is_reseller_eff = ( 'reseller' === $active );
		return array(
			'isAdmin'                  => $is_admin_eff,
			'isReseller'               => $is_reseller_eff,
			'actorUserId'              => $uid,
			'actorRow'                 => $row,
			'activePersona'            => $active,
			'availablePersonas'        => $available,
			'impersonating'            => false,
			'impersonationTargetId'    => 0,
			'impersonationTargetLabel' => '',
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function route_set_persona( WP_REST_Request $req ) {
		$p = (string) $req->get_param( 'persona' );
		if ( ! in_array( $p, array( 'admin', 'reseller', 'user' ), true ) ) {
			return new WP_Error( 'invalid_persona', __( 'Invalid persona.', 'simplevpbot' ), array( 'status' => 400 ) );
		}
		if ( is_array( self::get_impersonation_payload() ) ) {
			return new WP_Error(
				'impersonation_active',
				__( 'Stop viewing as reseller before switching role.', 'simplevpbot' ),
				array( 'status' => 403 )
			);
		}
		$ctx       = self::dashboard_actor_context();
		$allowed   = isset( $ctx['availablePersonas'] ) ? $ctx['availablePersonas'] : array();
		if ( ! in_array( $p, $allowed, true ) ) {
			return new WP_Error( 'persona_not_allowed', __( 'This persona is not available for your account.', 'simplevpbot' ), array( 'status' => 403 ) );
		}
		self::dashboard_persona_cookie_set( $p );
		return new WP_REST_Response(
			array(
				'ok'      => true,
				'persona' => $p,
			),
			200
		);
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function route_bootstrap() {
		$ctx      = self::dashboard_actor_context();
		$is_admin = ! empty( $ctx['isAdmin'] );
		$svp_uid  = (int) ( $ctx['actorUserId'] ?? 0 );
		$locale = determine_locale();
		$lang   = ( 0 === strpos( $locale, 'fa' ) ) ? 'fa' : 'en';
		$tz = function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : '';
		$actor_perms = null;
		if ( ! empty( $ctx['isReseller'] ) && $svp_uid > 0 && class_exists( 'SimpleVPBot_Model_User' ) ) {
			$actor_perms = SimpleVPBot_Model_User::reseller_permissions( $svp_uid );
		}
		$sidebar_user = self::sidebar_user_payload( $svp_uid );
		return new WP_REST_Response(
			array(
				'restUrl'                  => rest_url( self::NS ),
				'nonce'                    => wp_create_nonce( 'wp_rest' ),
				'locale'                   => $locale,
				'lang'                     => $lang,
				'isRtl'                    => ( 'fa' === $lang ),
				'isLoggedIn'               => true,
				'isAdmin'                  => $is_admin,
				'isReseller'               => ! empty( $ctx['isReseller'] ),
				'svpUserId'                => $svp_uid,
				'user'                     => $sidebar_user,
				'actorPermissions'         => $actor_perms,
				'activePersona'            => isset( $ctx['activePersona'] ) ? (string) $ctx['activePersona'] : 'user',
				'availablePersonas'        => isset( $ctx['availablePersonas'] ) ? array_values( (array) $ctx['availablePersonas'] ) : array(),
				'impersonating'            => ! empty( $ctx['impersonating'] ),
				'impersonationTargetId'    => isset( $ctx['impersonationTargetId'] ) ? (int) $ctx['impersonationTargetId'] : 0,
				'impersonationTargetLabel' => isset( $ctx['impersonationTargetLabel'] ) ? (string) $ctx['impersonationTargetLabel'] : '',
				'loginUrl'                 => wp_login_url( home_url( '/dashboard/' ) ),
				'dashboardUrl'             => home_url( '/dashboard/' ),
				'dashboardLoginUrl'        => trailingslashit( home_url( '/dashboard/login' ) ),
				'logoutUrl'                => self::dashboard_logout_url(),
				'siteName'                 => get_bloginfo( 'name' ),
				'pluginUrl'                => SIMPLEVPBOT_PLUGIN_URL,
				'ajaxUrl'                  => admin_url( 'admin-ajax.php' ),
				'adminAjaxNonce'           => wp_create_nonce( 'simplevpbot_admin' ),
				'siteTimeZone'             => is_string( $tz ) ? $tz : '',
			)
		);
	}

	/**
	 * Begin dashboard impersonation (admin only; signed cookie).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function route_impersonate_start( WP_REST_Request $req ) {
		$params = $req->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$tid = isset( $params['targetSvpUserId'] ) ? absint( $params['targetSvpUserId'] ) : 0;
		if ( $tid < 1 || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return new WP_Error( 'invalid_target', __( 'Invalid reseller.', 'simplevpbot' ), array( 'status' => 400 ) );
		}
		$trow = SimpleVPBot_Model_User::find( $tid );
		if ( ! $trow || ! SimpleVPBot_Model_User::is_reseller_row( $trow ) ) {
			return new WP_Error( 'invalid_target', __( 'Invalid reseller.', 'simplevpbot' ), array( 'status' => 400 ) );
		}
		self::impersonation_cookie_set( $tid );
		self::dashboard_persona_cookie_set( 'reseller' );
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Clear impersonation cookie.
	 *
	 * @return WP_REST_Response
	 */
	public static function route_impersonate_stop() {
		self::impersonation_cookie_clear();
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * SPA dashboard login (sets auth cookies for same-origin fetch with credentials).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function route_dashboard_login( WP_REST_Request $req ) {
		$ip = '';
		if ( function_exists( 'rest_get_ip_address' ) ) {
			$ip = (string) rest_get_ip_address();
		}
		if ( '' === $ip && ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) );
		}
		$rl_key = 'svp_dashlogin_rl_' . md5( $ip ? $ip : 'unknown' );
		$hits   = (int) get_transient( $rl_key );
		if ( $hits >= 5 ) {
			return new WP_REST_Response(
				array(
					'ok'   => false,
					'code' => 'rate_limited',
				),
				429
			);
		}

		$params = $req->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$nonce = isset( $params['login_nonce'] ) ? (string) $params['login_nonce'] : '';
		if ( ! wp_verify_nonce( $nonce, 'simplevpbot_dash_login' ) ) {
			return new WP_REST_Response(
				array(
					'ok'   => false,
					'code' => 'invalid_credentials',
				),
				401
			);
		}
		$log      = isset( $params['log'] ) ? (string) $params['log'] : '';
		$pwd      = isset( $params['pwd'] ) ? (string) $params['pwd'] : '';
		$remember = ! empty( $params['remember'] );
		if ( '' === trim( $log ) || '' === $pwd ) {
			return new WP_REST_Response(
				array(
					'ok'   => false,
					'code' => 'invalid_credentials',
				),
				401
			);
		}
		$creds = array(
			'user_login'    => $log,
			'user_password' => $pwd,
			'remember'      => $remember,
		);
		$user = wp_signon( $creds, false );
		if ( is_wp_error( $user ) ) {
			set_transient( $rl_key, $hits + 1, 15 * MINUTE_IN_SECONDS );
			return new WP_REST_Response(
				array(
					'ok'   => false,
					'code' => 'invalid_credentials',
				),
				401
			);
		}
		delete_transient( $rl_key );
		$redirect_raw = isset( $params['redirect_to'] ) ? (string) $params['redirect_to'] : '';
		$redirect      = wp_validate_redirect( $redirect_raw, home_url( '/dashboard/' ) );
		return new WP_REST_Response(
			array(
				'ok'       => true,
				'redirect' => $redirect,
			),
			200
		);
	}

	/**
	 * @param mixed $row DB row.
	 * @return array<string, mixed>|null
	 */
	private static function row_array( $row ) {
		if ( null === $row ) {
			return null;
		}
		$j = wp_json_encode( $row );
		if ( false === $j ) {
			return null;
		}
		/** @var array<string, mixed>|null $out */
		$out = json_decode( $j, true );
		return is_array( $out ) ? $out : null;
	}

	/**
	 * WordPress host process metrics (this PHP runtime / server, not X-UI panels).
	 *
	 * @return array<string, mixed>
	 */
	private static function overview_host_metrics() {
		$load_avg = null;
		if ( function_exists( 'sys_get_loadavg' ) ) {
			$load = @sys_get_loadavg(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( is_array( $load ) && isset( $load[0], $load[1], $load[2] ) ) {
				$load_avg = array(
					(float) $load[0],
					(float) $load[1],
					(float) $load[2],
				);
			}
		}

		$mem_usage = @memory_get_usage( true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$mem_usage = ( false !== $mem_usage ) ? (int) $mem_usage : null;

		$mem_limit = null;
		$mem_lim_raw = (string) ini_get( 'memory_limit' );
		if ( '' !== $mem_lim_raw && function_exists( 'wp_convert_hr_to_bytes' ) ) {
			$mem_limit = (int) wp_convert_hr_to_bytes( $mem_lim_raw );
		} elseif ( '' !== $mem_lim_raw && is_numeric( $mem_lim_raw ) ) {
			$mem_limit = (int) $mem_lim_raw;
		}

		$disk_free  = @disk_free_space( ABSPATH ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$disk_total = @disk_total_space( ABSPATH ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$disk_free_bytes  = ( false !== $disk_free ) ? (int) $disk_free : null;
		$disk_total_bytes = ( false !== $disk_total ) ? (int) $disk_total : null;

		return array(
			'loadAvg'          => $load_avg,
			'memoryBytes'      => $mem_usage,
			'memoryLimitBytes' => $mem_limit,
			'diskFreeBytes'    => $disk_free_bytes,
			'diskTotalBytes'   => $disk_total_bytes,
			'checkedAt'        => gmdate( 'c' ),
		);
	}

	/**
	 * Ensure panel health arrays include httpOk / networkReachable (backward compat with older transients).
	 *
	 * @param array<string, mixed> $row Row.
	 * @return array<string, mixed>
	 */
	private static function normalize_panel_health_row( array $row ) {
		$code = isset( $row['httpStatus'] ) ? (int) $row['httpStatus'] : 0;
		if ( ! isset( $row['httpOk'] ) && array_key_exists( 'ok', $row ) ) {
			$row['httpOk'] = (bool) $row['ok'];
		}
		if ( ! isset( $row['networkReachable'] ) ) {
			$row['networkReachable'] = ( $code >= 100 && $code <= 599 );
		}
		return $row;
	}

	/**
	 * Lightweight HTTP reachability for a panel root URL (cached).
	 *
	 * `latencyMs` is round-trip time for the probe (HEAD/GET). `httpOk` means 2xx–3xx on the root URL.
	 * `networkReachable` means any HTTP status was received (host/TLS responded), e.g. 404 on 3x-ui root.
	 * Legacy field `ok` is kept equal to `httpOk`.
	 *
	 * @param int    $panel_id  Panel row id.
	 * @param string $panel_url Panel URL.
	 * @return array<string, mixed>
	 */
	private static function panel_health_for_panel( $panel_id, $panel_url ) {
		$panel_id = (int) $panel_id;
		$key      = 'svp_dash_ph_' . $panel_id;
		$cached   = get_transient( $key );
		if ( false !== $cached && is_array( $cached ) ) {
			return self::normalize_panel_health_row( $cached );
		}
		$url = trim( (string) $panel_url );
		if ( '' === $url ) {
			$out = array(
				'panelId'            => $panel_id,
				'ok'                 => false,
				'httpOk'             => false,
				'networkReachable'   => false,
				'httpStatus'         => 0,
				'latencyMs'          => null,
				'checkedAt'          => gmdate( 'c' ),
				'error'              => 'no_url',
			);
			set_transient( $key, $out, 30 );
			return $out;
		}
		$root = untrailingslashit( $url );
		$t0   = microtime( true );
		$res  = wp_remote_head(
			$root,
			array(
				'timeout'     => 3,
				'redirection' => 2,
				'sslverify'   => true,
			)
		);
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		if ( is_wp_error( $res ) || 405 === $code || 501 === $code ) {
			$res = wp_remote_get(
				$root,
				array(
					'timeout'     => 3,
					'redirection' => 2,
					'sslverify'   => true,
				)
			);
			$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		}
		$lat = (int) round( ( microtime( true ) - $t0 ) * 1000 );
		if ( is_wp_error( $res ) ) {
			$out = array(
				'panelId'            => $panel_id,
				'ok'                 => false,
				'httpOk'             => false,
				'networkReachable'   => false,
				'httpStatus'         => 0,
				'latencyMs'          => $lat,
				'checkedAt'          => gmdate( 'c' ),
				'error'              => $res->get_error_message(),
			);
		} else {
			$http_ok            = ( $code >= 200 && $code < 400 );
			$network_reachable = ( $code >= 100 && $code <= 599 );
			$out                = array(
				'panelId'            => $panel_id,
				'ok'                 => $http_ok,
				'httpOk'             => $http_ok,
				'networkReachable'   => $network_reachable,
				'httpStatus'         => $code,
				'latencyMs'          => $lat,
				'checkedAt'          => gmdate( 'c' ),
			);
		}
		set_transient( $key, $out, 45 );
		return $out;
	}

	/**
	 * Parse list pagination from REST query args.
	 *
	 * @param WP_REST_Request $req         Request.
	 * @param string          $param_prefix Prefix without underscore (e.g. "users").
	 * @param int             $default_per Default page size.
	 * @param int             $max_per     Max page size.
	 * @return array{page:int,per_page:int,offset:int}
	 */
	private static function dash_list_pagination( WP_REST_Request $req, $param_prefix, $default_per, $max_per = 100 ) {
		$page = $req->get_param( $param_prefix . '_page' );
		$pp   = $req->get_param( $param_prefix . '_per_page' );
		$page = max( 1, absint( $page ) ?: 1 );
		$pp   = max( 1, min( (int) $max_per, absint( $pp ) ?: (int) $default_per ) );
		$off  = ( $page - 1 ) * $pp;
		return array(
			'page'     => $page,
			'per_page' => $pp,
			'offset'   => $off,
		);
	}

	/**
	 * @param int $page Page.
	 * @param int $per_page Per page.
	 * @param int $total Total rows.
	 * @return array<string, int>
	 */
	private static function dash_pagination_meta( $page, $per_page, $total ) {
		return array(
			'page'    => (int) $page,
			'perPage' => (int) $per_page,
			'total'   => (int) $total,
		);
	}

	/**
	 * Tab visibility hints for reseller SPA (false = never show).
	 *
	 * @param int $actor_uid svp_users.id.
	 * @return array<string, bool>
	 */
	private static function reseller_dashboard_allowed_tabs_map( $actor_uid ) {
		$actor_uid = (int) $actor_uid;
		$perms     = $actor_uid > 0 ? SimpleVPBot_Model_User::reseller_permissions( $actor_uid ) : SimpleVPBot_Model_User::default_reseller_permissions();
		$admin_only = array(
			'monitoring',
			'site_settings',
			'backup',
			'notifications',
			'logs',
			'xui_panels',
			'configs',
			'l2tp_servers',
			'texts',
			'bots',
		);
		$tab_perm = array(
			'users'         => 'users.manage',
			'resellers'     => 'users.manage',
			'users_bulk'    => 'users.bulk',
			'plans'         => 'plans.manage',
			'plan_cats'     => 'plans.manage',
			'cards'         => 'plans.manage',
			'discounts'     => 'plans.manage',
			'bot_ui'        => 'services.manage',
			'reseller_bots' => 'services.manage',
			'broadcast'     => 'broadcast.send',
			'receipts'      => 'receipts.review',
		);
		$all_tabs = array(
			'dashboard',
			'monitoring',
			'users',
			'resellers',
			'users_bulk',
			'broadcast',
			'plans',
			'plan_cats',
			'cards',
			'receipts',
			'referral',
			'discounts',
			'reseller_finance',
			'reseller_bots',
			'bot_ui',
			'site_settings',
			'backup',
			'notifications',
			'logs',
			'xui_panels',
			'configs',
			'l2tp_servers',
			'texts',
			'reseller_workspace',
		);
		$out = array();
		foreach ( $all_tabs as $tab ) {
			if ( in_array( $tab, $admin_only, true ) ) {
				$out[ $tab ] = false;
				continue;
			}
			$pk = isset( $tab_perm[ $tab ] ) ? $tab_perm[ $tab ] : null;
			if ( null === $pk ) {
				$out[ $tab ] = true;
			} else {
				$out[ $tab ] = isset( $perms[ $pk ] ) && true === $perms[ $pk ];
			}
		}
		return $out;
	}

	/**
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function route_admin_state( WP_REST_Request $req ) {
		global $wpdb;
		$active_tab           = sanitize_key( (string) $req->get_param( 'activeTab' ) );
		$dash_users_tab_light = ( 'users' === $active_tab );

		$p_panels = self::dash_list_pagination( $req, 'panels', 20 );
		$p_plans  = self::dash_list_pagination( $req, 'plans', 40 );
		$p_pc     = self::dash_list_pagination( $req, 'planCategories', 40 );
		$p_cards  = self::dash_list_pagination( $req, 'cards', 40 );
		$p_l2tp   = self::dash_list_pagination( $req, 'l2tp', 20 );
		$p_disc   = self::dash_list_pagination( $req, 'discounts', 30 );
		$p_users  = self::dash_list_pagination( $req, 'users', 50 );
		$p_pend   = self::dash_list_pagination( $req, 'pendingUsers', 30 );
		$p_res    = self::dash_list_pagination( $req, 'resellers', 30 );
		$p_rcpt   = self::dash_list_pagination( $req, 'receipts', 40 );
		$p_bc     = self::dash_list_pagination( $req, 'broadcasts', 20 );
		$p_ref_ev = self::dash_list_pagination( $req, 'referralEvents', 20 );
		$p_bots   = self::dash_list_pagination( $req, 'bots', 25, 200 );

		$t_panels = SimpleVPBot_Model_Panel::table();
		$t_plans  = SimpleVPBot_Model_Plan::table();
		$t_pc     = SimpleVPBot_Model_Plan_Category::table();
		$t_cards  = SimpleVPBot_Model_Card::table();
		$t_l2tp   = SimpleVPBot_Model_L2TP_Server::table();
		$t_disc   = SimpleVPBot_Model_Discount_Code::table();
		$u_tbl    = SimpleVPBot_Model_User::table();
		$s_tbl    = SimpleVPBot_Model_Service::table();
		$rcpt_t   = $wpdb->prefix . 'svp_receipts';
		$bc_t     = $wpdb->prefix . 'svp_broadcasts';

		$users_q = trim( sanitize_text_field( (string) wp_unslash( (string) $req->get_param( 'users_q' ) ) ) );
		if ( strlen( $users_q ) > 128 ) {
			$users_q = substr( $users_q, 0, 128 );
		}
		$user_filter = SimpleVPBot_Model_User::admin_search_users_clause( $users_q );
		$ctx           = self::dashboard_actor_context();
		$is_reseller   = ! empty( $ctx['isReseller'] );
		$actor_uid     = (int) ( $ctx['actorUserId'] ?? 0 );
		$reseller_mode = $is_reseller;
		$owner_ctx     = (int) $req->get_param( 'resellerContextId' );
		$scope_user_ids = array();
		if ( $reseller_mode ) {
			$owner_ctx      = $actor_uid;
			$scope_user_ids = SimpleVPBot_Model_User::reseller_scope_user_ids( $actor_uid );
		} elseif ( $owner_ctx > 0 ) {
			$scope_user_ids = SimpleVPBot_Model_User::reseller_scope_user_ids( $owner_ctx );
		}

		$reseller_actor_needs_panels = $reseller_mode && $actor_uid > 0 && class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' );

		$reseller_allowed_panel_ids = array();
		if ( $reseller_actor_needs_panels ) {
			$t_rp_panel_scope = SimpleVPBot_Model_Reseller_Panel_Price::table();
			$reseller_allowed_panel_ids = array_map(
				'intval',
				(array) $wpdb->get_col(
					$wpdb->prepare(
						"SELECT p.id FROM {$t_panels} p INNER JOIN {$t_rp_panel_scope} r ON r.panel_id = p.id AND r.reseller_svp_user_id = %d AND ( r.panel_access = 1 OR r.price_per_gb > 0 ) ORDER BY p.sort_order ASC, p.id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$actor_uid
					)
				)
			);
			$reseller_allowed_panel_ids = array_values( array_unique( array_filter( $reseller_allowed_panel_ids ) ) );
		}
		if ( $reseller_mode && $actor_uid > 0 && class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Line' ) ) {
			foreach ( SimpleVPBot_Model_Reseller_Wholesale_Line::lines_for_reseller( $actor_uid ) as $_wl ) {
				$_pid = (int) ( $_wl->panel_id ?? 0 );
				if ( $_pid > 0 ) {
					$reseller_allowed_panel_ids[] = $_pid;
				}
			}
			$reseller_allowed_panel_ids = array_values( array_unique( array_filter( array_map( 'intval', $reseller_allowed_panel_ids ) ) ) );
		}

		if ( $reseller_mode ) {
			$settings = SimpleVPBot_Settings::dashboard_slice_for_reseller_operator();
		} else {
			$settings = SimpleVPBot_Settings::all();
			unset( $settings['telegram_webhook_secret'], $settings['bale_webhook_secret'] );
		}

		$users_from_reseller_scope = false;
		$users_list                = array();
		$pending_users             = array();
		$resellers                 = array();
		if ( $reseller_mode ) {
			$scope = SimpleVPBot_Model_User::reseller_scope_clause( $actor_uid, 'u' );
			if ( ! $scope ) {
				// No downline users yet — still load catalog (panels, plan categories, etc.) for reseller tools.
				$users_from_reseller_scope = true;
				$tot_users_list            = 0;
				$tot_pend_list             = 0;
				$tot_res_list              = 0;
				$tot_resellers             = 0;
				$tot_users                 = 0;
				$tot_pend                  = 0;
			} else {
			$where_sql    = ' WHERE 1=1' . $scope['sql'];
			$where_values = $scope['values'];
			if ( $user_filter ) {
				$where_sql .= $user_filter['sql'];
				if ( ! empty( $user_filter['values'] ) ) {
					$where_values = array_merge( $where_values, $user_filter['values'] );
				}
			}
			$tot_users_list = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$u_tbl} u {$where_sql}",
					$where_values
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$tot_pend_list = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$u_tbl} u {$where_sql} AND u.status = %s",
					array_merge( $where_values, array( 'pending' ) )
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$tot_res_list = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$u_tbl} u {$where_sql} AND u.role = %s",
					array_merge( $where_values, array( 'reseller' ) )
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$users_list = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT u.*, COALESCE(s.svc_count, 0) AS svc_count
					FROM {$u_tbl} u
					LEFT JOIN (SELECT user_id, COUNT(*) AS svc_count FROM {$s_tbl} WHERE deleted_at IS NULL GROUP BY user_id) s ON s.user_id = u.id
					{$where_sql}
					ORDER BY u.id DESC LIMIT %d OFFSET %d",
					array_merge( $where_values, array( $p_users['per_page'], $p_users['offset'] ) )
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$pending_users = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT u.* FROM {$u_tbl} u {$where_sql} AND u.status = %s ORDER BY u.id DESC LIMIT %d OFFSET %d",
					array_merge( $where_values, array( 'pending', $p_pend['per_page'], $p_pend['offset'] ) )
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$resellers = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT u.*, COALESCE(s.svc_count, 0) AS svc_count
					FROM {$u_tbl} u
					LEFT JOIN (SELECT user_id, COUNT(*) AS svc_count FROM {$s_tbl} WHERE deleted_at IS NULL GROUP BY user_id) s ON s.user_id = u.id
					{$where_sql} AND u.role = %s
					ORDER BY u.id DESC LIMIT %d OFFSET %d",
					array_merge( $where_values, array( 'reseller', $p_res['per_page'], $p_res['offset'] ) )
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$users_from_reseller_scope = true;
			$tot_resellers             = $tot_res_list;
			$tot_users                 = $tot_users_list;
			$tot_pend                  = $tot_pend_list;
			}
		}

		if ( ! $dash_users_tab_light ) {
			if ( $reseller_mode ) {
				$tot_panels = 0;
			} else {
				$tot_panels = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_panels}" ); // phpcs:ignore
			}
			if ( $owner_ctx > 0 ) {
				$tot_plans = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t_plans} WHERE owner_svp_user_id = %d", $owner_ctx ) ); // phpcs:ignore
			} else {
				$tot_plans = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_plans}" ); // phpcs:ignore
			}
			if ( $reseller_mode ) {
				if ( empty( $reseller_allowed_panel_ids ) ) {
					$tot_pc = 0;
				} else {
					$pc_ph  = implode( ',', array_fill( 0, count( $reseller_allowed_panel_ids ), '%d' ) );
					$tot_pc = (int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$t_pc} WHERE panel_id IN ({$pc_ph})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
							$reseller_allowed_panel_ids
						)
					);
				}
			} else {
				$tot_pc = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_pc}" ); // phpcs:ignore
			}
			if ( $owner_ctx > 0 ) {
				$tot_cards = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t_cards} WHERE owner_svp_user_id IN (%d,0)", $owner_ctx ) ); // phpcs:ignore
			} else {
				$tot_cards = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_cards}" ); // phpcs:ignore
			}
			if ( $reseller_mode ) {
				$tot_l2tp       = 0;
				$texts_prebuilt = array();
			} else {
				$tot_l2tp       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_l2tp}" ); // phpcs:ignore
				$texts_prebuilt = class_exists( 'SimpleVPBot_Model_Text' ) ? SimpleVPBot_Model_Text::all_grouped_by_key() : array();
			}
			$tot_texts      = count( $texts_prebuilt );
			$p_texts        = array(
				'page'     => 1,
				'per_page' => max( 1, $tot_texts ),
				'offset'   => 0,
			);
			if ( $owner_ctx > 0 ) {
				$tot_disc = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t_disc} WHERE owner_svp_user_id IN (%d,0)", $owner_ctx ) ); // phpcs:ignore
			} else {
				$tot_disc = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_disc}" ); // phpcs:ignore
			}
			if ( $owner_ctx > 0 && ! empty( $scope_user_ids ) ) {
				$ph       = implode( ',', array_map( 'absint', $scope_user_ids ) );
				$tot_rcpt = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$rcpt_t} WHERE user_id IN ({$ph})" ); // phpcs:ignore
				$tot_bc   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$bc_t} WHERE owner_svp_user_id IN (%d,0)", $owner_ctx ) ); // phpcs:ignore
			} elseif ( $owner_ctx > 0 ) {
				$tot_rcpt = 0;
				$tot_bc   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$bc_t} WHERE owner_svp_user_id IN (%d,0)", $owner_ctx ) ); // phpcs:ignore
			} else {
				$tot_rcpt = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$rcpt_t}" ); // phpcs:ignore
				$tot_bc   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bc_t}" ); // phpcs:ignore
			}
		} else {
			$tot_panels = $reseller_mode ? 0 : (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_panels}" ); // phpcs:ignore
			$tot_plans      = 0;
			$tot_pc         = 0;
			$tot_cards      = 0;
			$tot_l2tp       = 0;
			$tot_disc       = 0;
			$tot_texts      = 0;
			$texts_prebuilt = array();
			$p_texts        = array(
				'page'     => 1,
				'per_page' => 1,
				'offset'   => 0,
			);
			$tot_rcpt       = 0;
			$tot_bc         = 0;
		}
		if ( ! $users_from_reseller_scope ) {
			$tot_users     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$u_tbl}" ); // phpcs:ignore
			$tot_pend      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$u_tbl} WHERE status = %s", 'pending' ) ); // phpcs:ignore
			$tot_resellers = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$u_tbl} WHERE role = %s", 'reseller' ) ); // phpcs:ignore

			if ( $user_filter ) {
				$cnt_users_sql = "SELECT COUNT(*) FROM {$u_tbl} u WHERE 1=1" . $user_filter['sql'];
				if ( ! empty( $user_filter['values'] ) ) {
					$tot_users_list = (int) $wpdb->get_var( $wpdb->prepare( $cnt_users_sql, $user_filter['values'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				} else {
					$tot_users_list = (int) $wpdb->get_var( $cnt_users_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				}
				$cnt_pend_sql = "SELECT COUNT(*) FROM {$u_tbl} u WHERE u.status = 'pending'" . $user_filter['sql'];
				if ( ! empty( $user_filter['values'] ) ) {
					$tot_pend_list = (int) $wpdb->get_var( $wpdb->prepare( $cnt_pend_sql, $user_filter['values'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				} else {
					$tot_pend_list = (int) $wpdb->get_var( $cnt_pend_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				}
			} else {
				$tot_users_list = $tot_users;
				$tot_pend_list  = $tot_pend;
			}
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $dash_users_tab_light ) {
			$plans_raw       = array();
			$plan_cats_raw   = array();
			$cards_raw       = array();
			$l2tp_raw        = array();
			$discounts_raw   = array();
			$panels_raw      = array();
		} else {
			if ( $reseller_mode ) {
				$panels_raw = array();
			} else {
				$panels_raw = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$t_panels} ORDER BY sort_order ASC, id ASC LIMIT %d OFFSET %d",
						$p_panels['per_page'],
						$p_panels['offset']
					)
				);
			}
		if ( $owner_ctx > 0 ) {
			$plans_raw = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$t_plans} WHERE owner_svp_user_id = %d ORDER BY sort_order ASC, id ASC LIMIT %d OFFSET %d",
					$owner_ctx,
					$p_plans['per_page'],
					$p_plans['offset']
				)
			);
		} else {
			$plans_raw = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$t_plans} ORDER BY sort_order ASC, id ASC LIMIT %d OFFSET %d",
					$p_plans['per_page'],
					$p_plans['offset']
				)
			);
		}
		if ( $reseller_mode ) {
			if ( empty( $reseller_allowed_panel_ids ) ) {
				$plan_cats_raw = array();
			} else {
				$pc_in_ph = implode( ',', array_fill( 0, count( $reseller_allowed_panel_ids ), '%d' ) );
				$plan_cats_raw = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$t_pc} WHERE panel_id IN ({$pc_in_ph}) ORDER BY panel_id ASC, sort_order ASC, id ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
						array_merge( $reseller_allowed_panel_ids, array( $p_pc['per_page'], $p_pc['offset'] ) )
					)
				);
			}
		} else {
			$plan_cats_raw = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$t_pc} ORDER BY panel_id ASC, sort_order ASC, id ASC LIMIT %d OFFSET %d",
					$p_pc['per_page'],
					$p_pc['offset']
				)
			);
		}
		if ( $owner_ctx > 0 ) {
			$cards_raw = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$t_cards} WHERE owner_svp_user_id IN (%d,0) ORDER BY id DESC LIMIT %d OFFSET %d",
					$owner_ctx,
					$p_cards['per_page'],
					$p_cards['offset']
				)
			);
		} else {
			$cards_raw = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$t_cards} ORDER BY id DESC LIMIT %d OFFSET %d",
					$p_cards['per_page'],
					$p_cards['offset']
				)
			);
		}
		if ( $reseller_mode ) {
			$l2tp_raw = array();
		} else {
			$l2tp_raw = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$t_l2tp} ORDER BY id DESC LIMIT %d OFFSET %d",
					$p_l2tp['per_page'],
					$p_l2tp['offset']
				)
			);
		}
		if ( $owner_ctx > 0 ) {
			$discounts_raw = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$t_disc} WHERE owner_svp_user_id IN (%d,0) ORDER BY active DESC, id DESC LIMIT %d OFFSET %d",
					$owner_ctx,
					$p_disc['per_page'],
					$p_disc['offset']
				)
			);
		} else {
			$discounts_raw = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$t_disc} ORDER BY active DESC, id DESC LIMIT %d OFFSET %d",
					$p_disc['per_page'],
					$p_disc['offset']
				)
			);
		}
		}

		if ( ! $users_from_reseller_scope ) {
			$pend_sql = "SELECT u.* FROM {$u_tbl} u WHERE u.status = 'pending'";
			if ( $user_filter ) {
				$pend_sql .= $user_filter['sql'];
			}
			$pend_sql .= ' ORDER BY u.id DESC LIMIT %d OFFSET %d';
			if ( $user_filter && ! empty( $user_filter['values'] ) ) {
				$pending_users = $wpdb->get_results(
					$wpdb->prepare(
						$pend_sql,
						array_merge( $user_filter['values'], array( $p_pend['per_page'], $p_pend['offset'] ) )
					)
				);
			} else {
				$pending_users = $wpdb->get_results(
					$wpdb->prepare( $pend_sql, $p_pend['per_page'], $p_pend['offset'] )
				);
			}

			$users_sql = "SELECT u.*, COALESCE(s.svc_count, 0) AS svc_count
				FROM {$u_tbl} u
				LEFT JOIN (SELECT user_id, COUNT(*) AS svc_count FROM {$s_tbl} WHERE deleted_at IS NULL GROUP BY user_id) s ON s.user_id = u.id
				WHERE 1=1";
			if ( $user_filter ) {
				$users_sql .= $user_filter['sql'];
			}
			$users_sql .= ' ORDER BY u.id DESC LIMIT %d OFFSET %d';
			if ( $user_filter && ! empty( $user_filter['values'] ) ) {
				$users_list = $wpdb->get_results(
					$wpdb->prepare(
						$users_sql,
						array_merge( $user_filter['values'], array( $p_users['per_page'], $p_users['offset'] ) )
					)
				);
			} else {
				$users_list = $wpdb->get_results(
					$wpdb->prepare( $users_sql, $p_users['per_page'], $p_users['offset'] )
				);
			}
			$res_sql = "SELECT u.*, COALESCE(s.svc_count, 0) AS svc_count
				FROM {$u_tbl} u
				LEFT JOIN (SELECT user_id, COUNT(*) AS svc_count FROM {$s_tbl} WHERE deleted_at IS NULL GROUP BY user_id) s ON s.user_id = u.id
				WHERE u.role = 'reseller'";
			if ( $user_filter ) {
				$res_sql .= $user_filter['sql'];
			}
			$res_sql .= ' ORDER BY u.id DESC LIMIT %d OFFSET %d';
			if ( $user_filter && ! empty( $user_filter['values'] ) ) {
				$resellers = $wpdb->get_results(
					$wpdb->prepare(
						$res_sql,
						array_merge( $user_filter['values'], array( $p_res['per_page'], $p_res['offset'] ) )
					)
				);
			} else {
				$resellers = $wpdb->get_results(
					$wpdb->prepare( $res_sql, $p_res['per_page'], $p_res['offset'] )
				);
			}
		}

		if ( $dash_users_tab_light ) {
			$receipts   = array();
			$broadcasts = array();
		} elseif ( $owner_ctx > 0 && ! empty( $scope_user_ids ) ) {
			$ids_sql = implode( ',', array_map( 'absint', $scope_user_ids ) );
			$receipts = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$rcpt_t} WHERE user_id IN ({$ids_sql}) ORDER BY id DESC LIMIT %d OFFSET %d",
					$p_rcpt['per_page'],
					$p_rcpt['offset']
				)
			);
		} elseif ( $owner_ctx > 0 ) {
			$receipts = array();
		} else {
			$receipts = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$rcpt_t} ORDER BY id DESC LIMIT %d OFFSET %d",
					$p_rcpt['per_page'],
					$p_rcpt['offset']
				)
			);
		}
		if ( ! $dash_users_tab_light ) {
			if ( $owner_ctx > 0 ) {
				$broadcasts = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$bc_t} WHERE owner_svp_user_id IN (%d,0) ORDER BY id DESC LIMIT %d OFFSET %d",
						$owner_ctx,
						$p_bc['per_page'],
						$p_bc['offset']
					)
				);
			} else {
				$broadcasts = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$bc_t} ORDER BY id DESC LIMIT %d OFFSET %d",
						$p_bc['per_page'],
						$p_bc['offset']
					)
				);
			}
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$reseller_permissions_map          = array();
		$reseller_panel_prices_map         = array();
		$reseller_wholesale_line_ids_map   = array();
		$reseller_bot_map                  = array();
		foreach ( (array) $resellers as $rr ) {
			$rid = (int) ( is_object( $rr ) ? ( $rr->id ?? 0 ) : 0 );
			if ( $rid < 1 ) {
				continue;
			}
			$reseller_permissions_map[ (string) $rid ] = SimpleVPBot_Model_User::reseller_permissions( $rid );
			if ( ! empty( $ctx['isReseller'] ) && class_exists( 'SimpleVPBot_Model_Reseller_Parent_Panel_Floor' ) ) {
				$reseller_panel_prices_map[ (string) $rid ] = array_map(
					static function ( $row ) {
						$ra = self::row_array( $row );
						if ( is_array( $ra ) ) {
							$ra['price_per_gb'] = (float) ( $ra['min_price_per_gb'] ?? 0 );
							$ra['panel_access'] = 1;
						}
						return $ra;
					},
					(array) SimpleVPBot_Model_Reseller_Parent_Panel_Floor::list_for_parent_child( (int) $ctx['actorUserId'], $rid )
				);
			} elseif ( class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' ) ) {
				$reseller_panel_prices_map[ (string) $rid ] = array_map(
					array( __CLASS__, 'row_array' ),
					(array) SimpleVPBot_Model_Reseller_Panel_Price::list_for_reseller( $rid )
				);
			} else {
				$reseller_panel_prices_map[ (string) $rid ] = array();
			}
			if ( class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
				$bp = SimpleVPBot_Model_Reseller_Bot_Profile::find_by_reseller( $rid );
				$reseller_bot_map[ (string) $rid ] = array(
					'enabled' => $bp ? ! empty( $bp->enabled ) : false,
					'brand'   => $bp ? (string) ( $bp->brand_name ?? '' ) : '',
				);
			}
			if ( class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Assignment' ) ) {
				$reseller_wholesale_line_ids_map[ (string) $rid ] = SimpleVPBot_Model_Reseller_Wholesale_Assignment::line_ids_for_reseller( $rid );
			}
		}

		$panels   = array();
		$plans    = array();
		$plan_cats = array();
		$cards    = array();
		$l2tp     = array();
		$texts    = array();
		$discounts = array();
		foreach ( (array) $panels_raw as $r ) {
			$ra = self::row_array( $r );
			if ( $ra ) {
				if ( empty( $ra['label'] ) && isset( $ra['name'] ) ) {
					$ra['label'] = (string) $ra['name'];
				}
				$panels[] = $ra;
			}
		}

		$reseller_plan_floors = array();
		if ( $reseller_mode && class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' ) && $actor_uid > 0 ) {
			$ru_parent = SimpleVPBot_Model_User::find( $actor_uid );
			$parent_id = $ru_parent ? (int) ( $ru_parent->invited_by ?? 0 ) : 0;
			foreach ( (array) SimpleVPBot_Model_Reseller_Panel_Price::list_for_reseller( $actor_uid ) as $rp ) {
				if ( ! SimpleVPBot_Model_Reseller_Panel_Price::row_allows_panel_use( $rp ) ) {
					continue;
				}
				$pid          = (int) $rp->panel_id;
				$unit         = (float) ( $rp->price_per_gb ?? 0 );
				$parent_floor = 0.0;
				if ( $parent_id > 0 && class_exists( 'SimpleVPBot_Model_Reseller_Parent_Panel_Floor' ) ) {
					$parent_floor = SimpleVPBot_Model_Reseller_Parent_Panel_Floor::get_min_price( $parent_id, $actor_uid, $pid );
				}
				$eff          = max( $unit, (float) $parent_floor );
				$dstype       = isset( $rp->default_service_type ) ? sanitize_key( (string) $rp->default_service_type ) : 'xray';
				if ( ! in_array( $dstype, array( 'xray', 'l2tp' ), true ) ) {
					$dstype = 'xray';
				}
				$reseller_plan_floors[] = array(
					'panel_id'                   => $pid,
					'min_price_per_gb_effective' => $eff,
					'default_service_type'       => $dstype,
					'default_inbound_id'         => (int) ( $rp->default_inbound_id ?? 0 ),
					'default_l2tp_server_id'     => (int) ( $rp->default_l2tp_server_id ?? 0 ),
				);
			}
		}
		foreach ( (array) $plans_raw as $r ) {
			$ra = self::row_array( $r );
			if ( $ra ) {
				$plans[] = $ra;
			}
		}
		foreach ( (array) $plan_cats_raw as $r ) {
			$ra = self::row_array( $r );
			if ( $ra ) {
				$plan_cats[] = $ra;
			}
		}
		foreach ( (array) $cards_raw as $r ) {
			$ra = self::row_array( $r );
			if ( $ra ) {
				$cards[] = $ra;
			}
		}
		foreach ( (array) $l2tp_raw as $r ) {
			$ra = self::row_array( $r );
			if ( $ra ) {
				$l2tp[] = $ra;
			}
		}
		foreach ( (array) $texts_prebuilt as $tg ) {
			if ( is_array( $tg ) ) {
				$texts[] = $tg;
			}
		}
		foreach ( (array) $discounts_raw as $r ) {
			$ra = self::row_array( $r );
			if ( $ra ) {
				$discounts[] = $ra;
			}
		}

		$wholesale_lines_catalog          = array();
		$reseller_wholesale_lines_payload = array();
		if ( class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Line' ) ) {
			if ( ! $reseller_mode ) {
				foreach ( SimpleVPBot_Model_Reseller_Wholesale_Line::all_rows() as $_ln ) {
					$la = self::row_array( $_ln );
					if ( $la ) {
						$tier_rows = SimpleVPBot_Model_Reseller_Wholesale_Tier::by_line( (int) $_ln->id );
						$la['tiers'] = array();
						foreach ( (array) $tier_rows as $_t ) {
							$ta = self::row_array( $_t );
							if ( $ta ) {
								$la['tiers'][] = $ta;
							}
						}
						$wholesale_lines_catalog[] = $la;
					}
				}
			} elseif ( $actor_uid > 0 && class_exists( 'SimpleVPBot_Service_Reseller_Wholesale_Pricing' ) ) {
				foreach ( SimpleVPBot_Model_Reseller_Wholesale_Line::lines_for_reseller( $actor_uid ) as $_ln ) {
					$pub           = SimpleVPBot_Model_Reseller_Wholesale_Line::to_reseller_public_array( $_ln );
					$pub['ladder'] = SimpleVPBot_Service_Reseller_Wholesale_Pricing::ladder_snapshot( $actor_uid, (int) $_ln->id );
					$reseller_wholesale_lines_payload[] = $pub;
				}
			}
		}

		if ( $reseller_mode && empty( $panels ) && $actor_uid > 0 && class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Line' ) ) {
			$by_pid = array();
			foreach ( SimpleVPBot_Model_Reseller_Wholesale_Line::lines_for_reseller( $actor_uid ) as $_ln ) {
				$pid = (int) ( $_ln->panel_id ?? 0 );
				if ( $pid < 1 ) {
					continue;
				}
				$lbl = (string) ( $_ln->label ?? '' );
				if ( ! isset( $by_pid[ $pid ] ) ) {
					$by_pid[ $pid ] = array(
						'id'         => $pid,
						'label'      => $lbl,
						'name'       => $lbl,
						'sort_order' => (int) ( $_ln->sort_order ?? 0 ),
						'active'     => 1,
					);
				} elseif ( '' !== $lbl ) {
					$cur = (string) ( $by_pid[ $pid ]['label'] ?? '' );
					if ( '' === $cur ) {
						$by_pid[ $pid ]['label'] = $lbl;
						$by_pid[ $pid ]['name']  = $lbl;
					} elseif ( false === strpos( $cur, $lbl ) ) {
						$by_pid[ $pid ]['label'] = $cur . ' · ' . $lbl;
						$by_pid[ $pid ]['name']  = $by_pid[ $pid ]['label'];
					}
				}
			}
			ksort( $by_pid );
			foreach ( $by_pid as $pub ) {
				$panels[] = $pub;
			}
		}

		// Synthetic panels for wholesale-line assignment already handled above; still merge panels when only
		// «قیمت پنل‌ها» rows grant access (joinable panel_prices), without wholesale catalog assignments.
		if ( $reseller_mode && $actor_uid > 0 && class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' ) && class_exists( 'SimpleVPBot_Model_Panel' ) ) {
			$have_panel_ids = array();
			foreach ( $panels as $_pub ) {
				if ( is_array( $_pub ) && isset( $_pub['id'] ) ) {
					$have_panel_ids[ (int) $_pub['id'] ] = true;
				}
			}
			foreach ( (array) SimpleVPBot_Model_Reseller_Panel_Price::list_for_reseller( $actor_uid ) as $_rp ) {
				if ( ! SimpleVPBot_Model_Reseller_Panel_Price::row_allows_panel_use( $_rp ) ) {
					continue;
				}
				$_pid = (int) ( $_rp->panel_id ?? 0 );
				if ( $_pid < 1 || ! empty( $have_panel_ids[ $_pid ] ) ) {
					continue;
				}
				$pobj = SimpleVPBot_Model_Panel::find( $_pid );
				if ( ! $pobj ) {
					continue;
				}
				$have_panel_ids[ $_pid ] = true;
				$_lbl                     = (string) ( $pobj->label ?? '' );
				$panels[]                 = array(
					'id'         => $_pid,
					'label'      => $_lbl,
					'name'       => $_lbl,
					'sort_order' => (int) ( $pobj->sort_order ?? 0 ),
					'active'     => (int) ( $pobj->active ?? 1 ),
				);
			}
			usort(
				$panels,
				static function ( $a, $b ) {
					$sa = is_array( $a ) ? (int) ( $a['sort_order'] ?? 0 ) : 0;
					$sb = is_array( $b ) ? (int) ( $b['sort_order'] ?? 0 ) : 0;
					if ( $sa !== $sb ) {
						return $sa <=> $sb;
					}
					$ia = is_array( $a ) ? (int) ( $a['id'] ?? 0 ) : 0;
					$ib = is_array( $b ) ? (int) ( $b['id'] ?? 0 ) : 0;
					return $ia <=> $ib;
				}
			);
		}

		$plan_user_counts = array();
		$plan_ids_on_page = array();
		foreach ( $plans as $prow ) {
			if ( is_array( $prow ) && isset( $prow['id'] ) ) {
				$pid = (int) $prow['id'];
				if ( $pid > 0 ) {
					$plan_ids_on_page[] = $pid;
				}
			}
		}
		$plan_ids_on_page = array_values( array_unique( $plan_ids_on_page ) );
		if ( ! empty( $plan_ids_on_page ) ) {
			$in_list = implode( ',', array_map( 'absint', $plan_ids_on_page ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$cnt_rows = $wpdb->get_results(
				"SELECT plan_id, COUNT(DISTINCT user_id) AS user_count FROM {$s_tbl} WHERE deleted_at IS NULL AND plan_id IS NOT NULL AND plan_id > 0 AND plan_id IN ({$in_list}) GROUP BY plan_id",
				ARRAY_A
			);
			if ( is_array( $cnt_rows ) ) {
				foreach ( $cnt_rows as $cr ) {
					if ( is_array( $cr ) && isset( $cr['plan_id'] ) ) {
						$plan_user_counts[ (int) $cr['plan_id'] ] = isset( $cr['user_count'] ) ? (int) $cr['user_count'] : 0;
					}
				}
			}
		}
		foreach ( $plans as $pidx => $prow ) {
			if ( ! is_array( $prow ) ) {
				continue;
			}
			$plid = isset( $prow['id'] ) ? (int) $prow['id'] : 0;
			$plans[ $pidx ]['userCount'] = isset( $plan_user_counts[ $plid ] ) ? $plan_user_counts[ $plid ] : 0;
		}

		$receipt_aggregates = array();
		$agg_rows           = array();
		if ( ! $dash_users_tab_light ) {
			if ( $reseller_mode && ! empty( $scope_user_ids ) ) {
				$rcp_ids_sql = implode( ',', array_map( 'absint', $scope_user_ids ) );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$agg_rows = $wpdb->get_results(
					"SELECT status, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS sum_amount FROM {$rcpt_t} WHERE user_id IN ({$rcp_ids_sql}) GROUP BY status",
					ARRAY_A
				);
			} elseif ( ! $reseller_mode ) {
				$agg_rows = $wpdb->get_results(
					"SELECT status, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS sum_amount FROM {$rcpt_t} GROUP BY status",
					ARRAY_A
				);
			}
		}
		$receipt_by_status = array();
		if ( is_array( $agg_rows ) ) {
			foreach ( $agg_rows as $ar ) {
				if ( ! is_array( $ar ) ) {
					continue;
				}
				$st = (string) ( $ar['status'] ?? '' );
				if ( '' !== $st ) {
					$receipt_by_status[ $st ] = (int) ( $ar['cnt'] ?? 0 );
				}
				$receipt_aggregates[] = array(
					'status'    => (string) ( $ar['status'] ?? '' ),
					'count'     => (int) ( $ar['cnt'] ?? 0 ),
					'sumAmount' => (float) ( $ar['sum_amount'] ?? 0 ),
				);
			}
		}

		$broadcast_queue_aggregates = array();
		if ( class_exists( 'SimpleVPBot_Model_Broadcast' ) && ! empty( $broadcasts ) ) {
			$b_ids = array();
			foreach ( (array) $broadcasts as $brow ) {
				if ( is_object( $brow ) && isset( $brow->id ) ) {
					$b_ids[] = (int) $brow->id;
				}
			}
			$stats_rows = SimpleVPBot_Model_Broadcast::queue_stats_by_broadcast( $b_ids );
			foreach ( (array) $stats_rows as $sr ) {
				if ( ! is_array( $sr ) ) {
					continue;
				}
				$broadcast_queue_aggregates[] = array(
					'broadcastId' => (int) ( $sr['broadcast_id'] ?? 0 ),
					'bot'         => (string) ( $sr['bot'] ?? '' ),
					'status'      => (string) ( $sr['status'] ?? '' ),
					'failureKind' => isset( $sr['failure_kind'] ) && null !== $sr['failure_kind'] ? (string) $sr['failure_kind'] : '',
					'count'       => (int) ( $sr['cnt'] ?? 0 ),
				);
			}
		}

		if ( ! $dash_users_tab_light ) {
			if ( $reseller_mode ) {
				$page_choices = array();
			} else {
				$pages        = get_pages( array( 'sort_column' => 'post_title' ) );
				$page_choices = array_map(
					function ( $pg ) {
						return array(
							'id'    => (int) $pg->ID,
							'title' => (string) $pg->post_title,
						);
					},
					is_array( $pages ) ? $pages : array()
				);
			}
		} else {
			$page_choices = array();
		}
		$nav_tabs = array(
			array( 'key' => 'dashboard', 'label' => __( 'پیشخوان', 'simplevpbot' ) ),
			array( 'key' => 'monitoring', 'label' => __( 'مانیتورینگ', 'simplevpbot' ) ),
			array( 'key' => 'site_settings', 'label' => __( 'تنظیمات سایت', 'simplevpbot' ) ),
			array( 'key' => 'bots', 'label' => __( 'ربات‌ها', 'simplevpbot' ) ),
			array( 'key' => 'xui_panels', 'label' => __( 'پنل‌های 3x-ui', 'simplevpbot' ) ),
			array( 'key' => 'plan_cats', 'label' => __( 'دسته‌های خرید', 'simplevpbot' ) ),
			array( 'key' => 'plans', 'label' => __( 'پلن‌ها', 'simplevpbot' ) ),
			array( 'key' => 'cards', 'label' => __( 'کارت‌ها', 'simplevpbot' ) ),
			array( 'key' => 'l2tp_servers', 'label' => __( 'سرورهای L2TP', 'simplevpbot' ) ),
			array( 'key' => 'wholesale_lines', 'label' => __( 'خطوط عمده نماینده', 'simplevpbot' ) ),
			array( 'key' => 'receipts', 'label' => __( 'رسیدها', 'simplevpbot' ) ),
			array( 'key' => 'broadcast', 'label' => __( 'پیام همگانی', 'simplevpbot' ) ),
			array( 'key' => 'texts', 'label' => __( 'متن‌ها', 'simplevpbot' ) ),
			array( 'key' => 'users', 'label' => __( 'کاربران', 'simplevpbot' ) ),
			array( 'key' => 'backup', 'label' => __( 'بکاپ', 'simplevpbot' ) ),
			array( 'key' => 'notifications', 'label' => __( 'نوتیفیکیشن', 'simplevpbot' ) ),
			array( 'key' => 'referral', 'label' => __( 'ریفرال و لینک ربات', 'simplevpbot' ) ),
			array( 'key' => 'discounts', 'label' => __( 'کدهای تخفیف', 'simplevpbot' ) ),
			array( 'key' => 'logs', 'label' => __( 'لاگ‌ها', 'simplevpbot' ) ),
		);

		$stats_payload = array();
		if ( ! $dash_users_tab_light && class_exists( 'SimpleVPBot_Admin_Dashboard_Stats' ) && ! $reseller_mode ) {
			$stats_payload = SimpleVPBot_Admin_Dashboard_Stats::build_payload( 0 );
		}

		$text_defaults = ( $reseller_mode || ! class_exists( 'SimpleVPBot_Activator' ) )
			? array()
			: SimpleVPBot_Activator::default_text_values_map();

		$referral_stats     = null;
		$referral_events    = array();
		$tot_referral_ev    = 0;
		if ( 'referral' === $active_tab && class_exists( 'SimpleVPBot_Model_Referral_Event' ) ) {
			$t_tx        = SimpleVPBot_Model_Transaction::table();
			$ref_ev_tbl  = SimpleVPBot_Model_Referral_Event::table();
			$since_ts    = strtotime( '-30 days', (int) current_time( 'timestamp' ) );
			$since_mysql = wp_date( 'Y-m-d H:i:s', $since_ts );
			$scope_sql   = '';
			if ( $reseller_mode && ! empty( $scope_user_ids ) ) {
				$scope_sql = implode( ',', array_map( 'absint', $scope_user_ids ) );
			}

			if ( '' !== $scope_sql ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$tot_referral_ev = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$ref_ev_tbl} WHERE inviter_svp_user_id IN ({$scope_sql})" );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$events_last_30 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$ref_ev_tbl} WHERE created_at >= %s AND inviter_svp_user_id IN ({$scope_sql})", $since_mysql ) );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$invited_users = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$u_tbl} WHERE invited_by IS NOT NULL AND invited_by > 0 AND invited_by IN ({$scope_sql})" );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$commission_sum = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$t_tx} WHERE type = 'referral_commission' AND status = 'approved' AND user_id IN ({$scope_sql})" );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$ref_amt_sum = (float) $wpdb->get_var( "SELECT COALESCE(SUM(referral_amount),0) FROM {$t_tx} WHERE type IN ('purchase','renew') AND status = 'approved' AND user_id IN ({$scope_sql})" );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$top_rows = $wpdb->get_results(
					"SELECT t.user_id AS referrer_id,
					COUNT(*) AS commission_count,
					COALESCE(SUM(t.amount),0) AS commission_total,
					(SELECT COUNT(*) FROM {$u_tbl} u WHERE u.invited_by = t.user_id) AS direct_invites
					FROM {$t_tx} t
					WHERE t.type = 'referral_commission' AND t.status = 'approved' AND t.user_id IN ({$scope_sql})
					GROUP BY t.user_id
					ORDER BY commission_total DESC
					LIMIT 20",
					ARRAY_A
				);
			} else {
				$tot_referral_ev = SimpleVPBot_Model_Referral_Event::count_all();
				$events_last_30  = SimpleVPBot_Model_Referral_Event::count_since( $since_mysql );
				$invited_users   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$u_tbl} WHERE invited_by IS NOT NULL AND invited_by > 0" ); // phpcs:ignore
				$commission_sum  = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$t_tx} WHERE type = 'referral_commission' AND status = 'approved'" ); // phpcs:ignore
				$ref_amt_sum     = (float) $wpdb->get_var( "SELECT COALESCE(SUM(referral_amount),0) FROM {$t_tx} WHERE type IN ('purchase','renew') AND status = 'approved'" ); // phpcs:ignore
				$top_rows        = $wpdb->get_results(
					"SELECT t.user_id AS referrer_id,
					COUNT(*) AS commission_count,
					COALESCE(SUM(t.amount),0) AS commission_total,
					(SELECT COUNT(*) FROM {$u_tbl} u WHERE u.invited_by = t.user_id) AS direct_invites
					FROM {$t_tx} t
					WHERE t.type = 'referral_commission' AND t.status = 'approved'
					GROUP BY t.user_id
					ORDER BY commission_total DESC
					LIMIT 20",
					ARRAY_A
				);
			}
			$top_referrers = array();
			if ( is_array( $top_rows ) ) {
				foreach ( $top_rows as $tr ) {
					if ( ! is_array( $tr ) ) {
						continue;
					}
					$rid = (int) ( $tr['referrer_id'] ?? 0 );
					$ru  = $rid > 0 ? SimpleVPBot_Model_User::find( $rid ) : null;
					$top_referrers[] = array(
						'referrerId'       => $rid,
						'username'         => $ru ? (string) $ru->username : '',
						'firstName'        => $ru ? (string) $ru->first_name : '',
						'commissionCount'  => (int) ( $tr['commission_count'] ?? 0 ),
						'commissionTotal'  => (float) ( $tr['commission_total'] ?? 0 ),
						'directInvites'    => (int) ( $tr['direct_invites'] ?? 0 ),
					);
				}
			}

			if ( '' !== $scope_sql ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$ev_raw = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$ref_ev_tbl} WHERE inviter_svp_user_id IN ({$scope_sql}) ORDER BY id DESC LIMIT %d OFFSET %d",
						$p_ref_ev['per_page'],
						$p_ref_ev['offset']
					)
				);
			} else {
				$ev_raw = SimpleVPBot_Model_Referral_Event::list_desc( $p_ref_ev['per_page'], $p_ref_ev['offset'] );
			}
			foreach ( (array) $ev_raw as $er ) {
				$ra = self::row_array( $er );
				if ( $ra ) {
					$referral_events[] = $ra;
				}
			}

			$referral_stats = array(
				'summary' => array(
					'eventsLast30'                  => $events_last_30,
					'invitedUsersWithReferrer'      => $invited_users,
					'totalCommissionPaid'           => $commission_sum,
					'totalReferralAmountOnPurchases' => $ref_amt_sum,
				),
				'topReferrers' => $top_referrers,
			);
		}

		$force_health = $req->get_param( 'refreshPanelHealth' ) === '1';
		$panel_health = array();
		// Health covers all panels (not only the paged list slice used elsewhere).
		if ( $dash_users_tab_light && ! $force_health ) {
			$panels_for_health = array();
		} elseif ( $reseller_mode ) {
			$panels_for_health = array();
		} else {
			$panels_for_health = $wpdb->get_results(
				"SELECT id, panel_url FROM {$t_panels} ORDER BY sort_order ASC, id ASC",
				ARRAY_A
			);
			foreach ( (array) $panels_for_health as $hrow ) {
				if ( ! is_array( $hrow ) ) {
					continue;
				}
				$pid  = isset( $hrow['id'] ) ? (int) $hrow['id'] : 0;
				$purl = isset( $hrow['panel_url'] ) ? (string) $hrow['panel_url'] : '';
				if ( $force_health && $pid > 0 ) {
					delete_transient( 'svp_dash_ph_' . $pid );
				}
				if ( $pid > 0 ) {
					$panel_health[] = self::panel_health_for_panel( $pid, $purl );
				}
			}
		}

		$force_live_metrics = ( $req->get_param( 'refreshLivePanelMetrics' ) === '1' );
		$want_live_metrics  = ! $reseller_mode && ( ( 'monitoring' === $active_tab ) || $force_live_metrics );
		$live_snapshots     = array();
		$external_snaps     = array();
		$monitor_hosts_pub = array();
		if ( ! $reseller_mode && class_exists( 'SimpleVPBot_Model_Monitor_Host' ) ) {
			foreach ( SimpleVPBot_Model_Monitor_Host::all_ordered() as $mh_row ) {
				$monitor_hosts_pub[] = SimpleVPBot_Model_Monitor_Host::to_public_array( $mh_row );
			}
		}
		if ( $force_live_metrics && class_exists( 'SimpleVPBot_Dashboard_Panel_Live' ) ) {
			foreach ( (array) $panels_for_health as $hrow ) {
				if ( ! is_array( $hrow ) ) {
					continue;
				}
				$pid = isset( $hrow['id'] ) ? (int) $hrow['id'] : 0;
				if ( $pid > 0 ) {
					SimpleVPBot_Dashboard_Panel_Live::clear_cache( $pid );
				}
			}
			if ( class_exists( 'SimpleVPBot_Model_Monitor_Host' ) ) {
				foreach ( SimpleVPBot_Model_Monitor_Host::active_ordered() as $mh_row ) {
					delete_transient( 'svp_dash_live_ext_' . (int) $mh_row->id );
				}
			}
		}
		if ( $want_live_metrics && class_exists( 'SimpleVPBot_Dashboard_Panel_Live' ) ) {
			foreach ( (array) $panels_for_health as $hrow ) {
				if ( ! is_array( $hrow ) ) {
					continue;
				}
				$pid = isset( $hrow['id'] ) ? (int) $hrow['id'] : 0;
				if ( $pid > 0 ) {
					$live_snapshots[] = SimpleVPBot_Dashboard_Panel_Live::snapshot_for_panel( $pid, $force_live_metrics );
				}
			}
			if ( class_exists( 'SimpleVPBot_Model_Monitor_Host' ) ) {
				foreach ( SimpleVPBot_Model_Monitor_Host::active_ordered() as $mh_row ) {
					$hid = (int) $mh_row->id;
					$snap = SimpleVPBot_Dashboard_Panel_Live::snapshot_external_host(
						$hid,
						(string) ( $mh_row->metrics_url ?? '' ),
						(string) ( $mh_row->bearer_token ?? '' ),
						$force_live_metrics
					);
					$external_snaps[] = array_merge(
						array(
							'label'  => (string) ( $mh_row->label ?? '' ),
							'hostId' => $hid,
						),
						$snap
					);
				}
			}
		}

		$overview = array(
			'stats'         => $stats_payload,
			'counts'        => array(
				'plans'             => $tot_plans,
				'planCategories'    => $tot_pc,
				'cards'             => $tot_cards,
				'discountCodes'     => $tot_disc,
				'texts'             => $tot_texts,
				'l2tpServers'       => $tot_l2tp,
				'panels'            => $tot_panels,
				'pendingUsers'      => $tot_pend,
				'receiptsTotal'    => $tot_rcpt,
				'receiptsSample'   => $tot_rcpt,
				'receiptsByStatus'  => $receipt_by_status,
				'broadcasts'        => $tot_bc,
				'usersTotal'        => $tot_users,
			'resellers'         => $tot_resellers,
			),
			'bot'           => array(
				'enabled'               => ! empty( $settings['enabled'] ),
				'telegram_bot_username' => (string) ( $settings['telegram_bot_username'] ?? '' ),
				'bale_bot_username'     => (string) ( $settings['bale_bot_username'] ?? '' ),
			),
			'host'          => $reseller_mode ? null : self::overview_host_metrics(),
			'onlineDailySeries' => ( ! $reseller_mode && ! $dash_users_tab_light && class_exists( 'SimpleVPBot_Model_Panel_Online_Daily' ) )
				? SimpleVPBot_Model_Panel_Online_Daily::daily_totals_last_days( 7 )
				: array(),
			'panelHealth'   => $panel_health,
			'livePanelSnapshots' => $live_snapshots,
			'externalHostSnapshots' => $external_snaps,
		);

		$bots_list_payload = array();
		$tot_bots_list     = 0;
		if ( ! $dash_users_tab_light && class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			if ( $reseller_mode && $actor_uid > 0 ) {
				$p = SimpleVPBot_Model_Reseller_Bot_Profile::table();
				$u            = SimpleVPBot_Model_User::table();
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$bot_profiles = array(
					$wpdb->get_row(
						$wpdb->prepare(
							"SELECT u.id AS reseller_svp_user_id, u.first_name AS reseller_first_name, u.last_name AS reseller_last_name,
							u.username AS reseller_username, u.status AS reseller_status,
							p.brand_name, p.enabled, p.telegram_token, p.bale_token, p.telegram_secret_token,
							p.admin_telegram_ids, p.admin_bale_ids
							FROM {$u} u
							LEFT JOIN {$p} p ON p.reseller_svp_user_id = u.id
							WHERE u.role = %s AND u.id = %d LIMIT 1",
							'reseller',
							$actor_uid
						)
					),
				);
			} else {
				$tot_bots_list = SimpleVPBot_Model_Reseller_Bot_Profile::count_resellers_for_bot_admin();
				$bot_profiles  = SimpleVPBot_Model_Reseller_Bot_Profile::list_resellers_bot_admin_paginated( $p_bots['per_page'], $p_bots['offset'] );
			}
			foreach ( (array) $bot_profiles as $brow ) {
				if ( ! $brow || ! is_object( $brow ) ) {
					continue;
				}
				$rid    = (int) ( $brow->reseller_svp_user_id ?? 0 );
				$tg_ids = SimpleVPBot_Model_Reseller_Bot_Profile::decode_admin_ids( $brow->admin_telegram_ids ?? '' );
				$bl_ids = SimpleVPBot_Model_Reseller_Bot_Profile::decode_admin_ids( $brow->admin_bale_ids ?? '' );
				$tg_tok = trim( (string) ( $brow->telegram_token ?? '' ) );
				$bl_tok = trim( (string) ( $brow->bale_token ?? '' ) );
				$rname  = trim( (string) ( $brow->reseller_first_name ?? '' ) . ' ' . (string) ( $brow->reseller_last_name ?? '' ) );
				if ( '' === $rname ) {
					$rname = (string) ( $brow->reseller_username ?? '' );
				}
				$bots_list_payload[] = array(
					'reseller_id'               => $rid,
					'reseller_name'             => trim( $rname ),
					'reseller_status'           => (string) ( $brow->reseller_status ?? '' ),
					'brand_name'                => (string) ( $brow->brand_name ?? '' ),
					'enabled'                   => ! empty( $brow->enabled ),
					'has_telegram_token'        => '' !== $tg_tok,
					'has_bale_token'            => '' !== $bl_tok,
					'telegram_secret_token_set' => '' !== trim( (string) ( $brow->telegram_secret_token ?? '' ) ),
					'admin_telegram_ids'        => $tg_ids,
					'admin_bale_ids'            => $bl_ids,
				);
			}
		}
		if ( $reseller_mode ) {
			$tot_bots_list = count( $bots_list_payload );
		}

		$pagination = array(
			'panels'         => self::dash_pagination_meta( $p_panels['page'], $p_panels['per_page'], $tot_panels ),
			'plans'          => self::dash_pagination_meta( $p_plans['page'], $p_plans['per_page'], $tot_plans ),
			'planCategories' => self::dash_pagination_meta( $p_pc['page'], $p_pc['per_page'], $tot_pc ),
			'cards'          => self::dash_pagination_meta( $p_cards['page'], $p_cards['per_page'], $tot_cards ),
			'l2tpServers'    => self::dash_pagination_meta( $p_l2tp['page'], $p_l2tp['per_page'], $tot_l2tp ),
			'texts'          => self::dash_pagination_meta( $p_texts['page'], $p_texts['per_page'], $tot_texts ),
			'discountCodes'  => self::dash_pagination_meta( $p_disc['page'], $p_disc['per_page'], $tot_disc ),
			'usersList'      => self::dash_pagination_meta( $p_users['page'], $p_users['per_page'], $tot_users_list ),
			'pendingUsers'   => self::dash_pagination_meta( $p_pend['page'], $p_pend['per_page'], $tot_pend_list ),
			'resellers'      => self::dash_pagination_meta( $p_res['page'], $p_res['per_page'], $tot_resellers ),
			'receipts'       => self::dash_pagination_meta( $p_rcpt['page'], $p_rcpt['per_page'], $tot_rcpt ),
			'broadcasts'     => self::dash_pagination_meta( $p_bc['page'], $p_bc['per_page'], $tot_bc ),
			'referralEvents' => self::dash_pagination_meta( $p_ref_ev['page'], $p_ref_ev['per_page'], $tot_referral_ev ),
			'botsList'       => self::dash_pagination_meta( $p_bots['page'], $p_bots['per_page'], $tot_bots_list ),
		);

		$ui_layout    = class_exists( 'SimpleVPBot_UI_Layout' ) ? SimpleVPBot_UI_Layout::export_merged_for_dashboard() : array( 'version' => 0, 'surfaces' => array() );
		$ui_registry = class_exists( 'SimpleVPBot_UI_Action_Registry' ) ? SimpleVPBot_UI_Action_Registry::export_for_dashboard() : array( 'version' => 0, 'surfaces' => array() );

		$payload = array(
			'settings'                 => $settings,
			'textDefaults'             => $text_defaults,
			'uiLayout'                 => $ui_layout,
			'uiRegistry'               => $ui_registry,
			'referralStats'            => $referral_stats,
			'referralEvents'           => $referral_events,
			'panels'                   => $panels,
			'plans'                    => $plans,
			'planCategories'           => $plan_cats,
			'cards'                    => $cards,
			'l2tpServers'              => $l2tp,
			'texts'                    => $texts,
			'discountCodes'            => $discounts,
			'pendingUsers'             => array_map( array( __CLASS__, 'row_array' ), (array) $pending_users ),
			'usersList'                => array_map( array( __CLASS__, 'row_array' ), (array) $users_list ),
			'resellers'                => array_map( array( __CLASS__, 'row_array' ), (array) $resellers ),
			'resellerPermissionsMap'   => $reseller_permissions_map,
			'resellerPanelPricesMap'   => $reseller_panel_prices_map,
			'resellerWholesaleLineIdsMap' => $reseller_wholesale_line_ids_map,
			'wholesaleLinesCatalog'    => $wholesale_lines_catalog,
			'wholesaleLines'           => $reseller_wholesale_lines_payload,
			'resellerBotMap'           => $reseller_bot_map,
			'botsList'                 => $bots_list_payload,
			'receipts'                 => array_map( array( __CLASS__, 'row_array' ), (array) $receipts ),
			'receiptAggregates'        => $receipt_aggregates,
			'broadcasts'               => array_map( array( __CLASS__, 'row_array' ), (array) $broadcasts ),
			'broadcastQueueAggregates' => $broadcast_queue_aggregates,
			'wpPages'                  => $page_choices,
			'navTabs'                  => $nav_tabs,
			'overview'                 => $overview,
			'monitorHosts'             => $monitor_hosts_pub,
			'pagination'               => $pagination,
			'resellerContextId'        => $owner_ctx > 0 ? $owner_ctx : 0,
		);
		if ( $reseller_mode ) {
			$payload['resellerAllowedTabs']   = self::reseller_dashboard_allowed_tabs_map( $actor_uid );
			$payload['actorPermissions']    = SimpleVPBot_Model_User::reseller_permissions( $actor_uid );
			$payload['resellerPlanFloors'] = $reseller_plan_floors;
			if ( $actor_uid > 0 && empty( $panels ) && empty( $reseller_wholesale_lines_payload )
				&& class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' ) ) {
				$payload['resellerPanelAccessDiagnostics'] = SimpleVPBot_Model_Reseller_Panel_Price::access_diagnostics( $actor_uid );
			}
			$reseller_customer_charges = array();
			if ( $actor_uid > 0 && ! empty( $scope_user_ids ) && class_exists( 'SimpleVPBot_Model_Transaction' ) && class_exists( 'SimpleVPBot_Model_User' ) ) {
				$tx_t    = SimpleVPBot_Model_Transaction::table();
				$in_list = implode( ',', array_map( 'absint', $scope_user_ids ) );
				if ( '' !== $in_list ) {
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$tx_rows = $wpdb->get_results( "SELECT * FROM {$tx_t} WHERE user_id IN ({$in_list}) AND status = 'approved' ORDER BY id DESC LIMIT 120" );
					foreach ( (array) $tx_rows as $txrow ) {
						if ( ! $txrow || ! is_object( $txrow ) ) {
							continue;
						}
						$mj = json_decode( (string) ( $txrow->meta_json ?? '{}' ), true );
						if ( ! is_array( $mj ) || empty( $mj['billing_reseller_svp_id'] ) || (int) $mj['billing_reseller_svp_id'] !== $actor_uid ) {
							continue;
						}
						$cid = (int) $txrow->user_id;
						$cust = SimpleVPBot_Model_User::find( $cid );
						$lab  = $cust ? SimpleVPBot_Model_User::label( $cust ) : ( '#' . $cid );
						$ra   = self::row_array( $txrow );
						if ( is_array( $ra ) ) {
							$ra['customer_label']       = $lab;
							$ra['customer_svp_user_id'] = $cid;
							$reseller_customer_charges[] = $ra;
						}
					}
				}
			}
			$payload['resellerCustomerCharges'] = $reseller_customer_charges;
		}
		$sidebar_u = self::sidebar_user_payload( $actor_uid );
		if ( null !== $sidebar_u ) {
			$payload['user'] = $sidebar_u;
		}
		return new WP_REST_Response( $payload );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function route_me_state() {
		$actx = self::dashboard_actor_context();
		if ( ! empty( $actx['isAdmin'] ) ) {
			return new WP_REST_Response( array( 'ok' => true, 'isAdmin' => true ), 200 );
		}
		$row = SimpleVPBot_Model_User::find_by_wp_user( get_current_user_id() );
		if ( ! $row ) {
			return new WP_REST_Response(
				array(
					'ok'      => false,
					'message' => 'no_linked_bot_user',
					'hint'    => __( 'مدیر باید حساب ربات شما را به حساب سایت وصل کند.', 'simplevpbot' ),
				),
				200
			);
		}
		$uid  = (int) $row->id;
		$svcs = SimpleVPBot_Model_Service::by_user( $uid );
		$list = array();
		foreach ( (array) $svcs as $svc ) {
			$list[] = self::row_array( $svc );
		}
		$ua = self::row_array( $row );
		if ( is_array( $ua ) ) {
			$ua['label'] = self::dashboard_svp_row_display_label( $row, $uid );
		}
		return new WP_REST_Response(
			array(
				'ok'       => true,
				'isAdmin'  => false,
				'user'     => $ua,
				'services' => $list,
			)
		);
	}

	/**
	 * Quick user search for command palette (max 20 rows, same rules as Model_User::search).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function route_admin_user_search( WP_REST_Request $req ) {
		$ctx = self::dashboard_actor_context();
		$q = trim( (string) $req->get_param( 'q' ) );
		if ( strlen( $q ) > 128 ) {
			$q = substr( $q, 0, 128 );
		}
		$rows  = SimpleVPBot_Model_User::search( $q, 20 );
		if ( ! empty( $ctx['isReseller'] ) ) {
			$scope_ids = SimpleVPBot_Model_User::reseller_scope_user_ids( (int) $ctx['actorUserId'] );
			$rows      = array_values(
				array_filter(
					(array) $rows,
					function ( $row ) use ( $scope_ids ) {
						$rid = (int) ( is_object( $row ) ? ( $row->id ?? 0 ) : 0 );
						return $rid > 0 && in_array( $rid, $scope_ids, true );
					}
				)
			);
		}
		$users = array();
		foreach ( (array) $rows as $r ) {
			$ra = self::row_array( $r );
			if ( $ra ) {
				$users[] = $ra;
			}
		}
		return new WP_REST_Response(
			array(
				'ok'    => true,
				'users' => $users,
			),
			200
		);
	}

	/**
	 * GET admin user detail + services + paginated activity.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function route_admin_user( WP_REST_Request $req ) {
		$ctx = self::dashboard_actor_context();
		$id = (int) $req->get_param( 'id' );
		if ( $id < 1 ) {
			return new WP_Error( 'bad_request', 'invalid id', array( 'status' => 400 ) );
		}
		$user = SimpleVPBot_Model_User::find( $id );
		if ( ! $user ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'not_found' ), 404 );
		}
		if ( ! empty( $ctx['isReseller'] ) && ! SimpleVPBot_Model_User::reseller_can_access_user( (int) $ctx['actorUserId'], $id ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden' ), 403 );
		}
		$svcs = SimpleVPBot_Model_Service::by_user( $id );
		$list = array();
		$svc_ids = array();
		foreach ( (array) $svcs as $svc ) {
			$row = self::row_array( $svc );
			if ( ! is_array( $row ) ) {
				continue;
			}
			$sid = (int) ( $row['id'] ?? 0 );
			if ( $sid > 0 ) {
				$svc_ids[] = $sid;
			}
			$tt = isset( $row['total_traffic'] ) ? (int) $row['total_traffic'] : 0;
			$row['quota_gb'] = $tt > 0 ? round( $tt / ( 1024 * 1024 * 1024 ), 4 ) : 0.0;
			$ut = isset( $row['used_traffic'] ) ? (int) $row['used_traffic'] : 0;
			$row['used_gb']   = $ut > 0 ? round( $ut / ( 1024 * 1024 * 1024 ), 4 ) : 0.0;
			$row['subscription_state'] = self::admin_user_service_subscription_state( isset( $row['expires_at'] ) ? (string) $row['expires_at'] : '' );
			$pid = (int) ( $row['plan_id'] ?? 0 );
			if ( $pid > 0 && class_exists( 'SimpleVPBot_Model_Plan' ) ) {
				$pl = SimpleVPBot_Model_Plan::find( $pid );
				if ( $pl && is_object( $pl ) ) {
					$row['plan_name']          = (string) ( $pl->name ?? '' );
					$row['plan_pricing_type']  = (string) ( $pl->pricing_type ?? 'fixed' );
					$row['plan_price']       = (float) ( $pl->price ?? 0 );
					$row['plan_price_per_gb'] = (float) ( $pl->price_per_gb ?? 0 );
				}
			}
			$list[] = $row;
		}
		$ip_by_svc = array();
		if ( ! empty( $svc_ids ) && class_exists( 'SimpleVPBot_Model_Service_Ip_Log' ) ) {
			$ip_by_svc = SimpleVPBot_Model_Service_Ip_Log::latest_for_services( $svc_ids, 20 );
		}
		foreach ( $list as &$lr ) {
			$sid = (int) ( $lr['id'] ?? 0 );
			$lr['ip_log'] = isset( $ip_by_svc[ $sid ] ) ? $ip_by_svc[ $sid ] : array();
			if ( class_exists( 'SimpleVPBot_Portal_Link' ) && $sid > 0 ) {
				$lr['portal_service_url'] = SimpleVPBot_Portal_Link::build_service_url( $id, $sid );
			} else {
				$lr['portal_service_url'] = '';
			}
		}
		unset( $lr );
		$referrals = array();
		if ( class_exists( 'SimpleVPBot_Model_User' ) ) {
			foreach ( SimpleVPBot_Model_User::list_invited_by( $id, 150 ) as $ref ) {
				$ra = self::row_array( $ref );
				if ( $ra ) {
					$referrals[] = $ra;
				}
			}
		}
		$portal_user = '';
		$portal_base = '';
		if ( class_exists( 'SimpleVPBot_Portal_Link' ) ) {
			$portal_user = SimpleVPBot_Portal_Link::build_url( $id );
			$portal_base = SimpleVPBot_Portal_Link::base_url();
		}
		$page     = max( 1, (int) $req->get_param( 'activity_page' ) );
		$per_page = (int) $req->get_param( 'activity_per_page' );
		if ( $per_page < 1 ) {
			$per_page = 20;
		}
		$per_page = min( 100, $per_page );
		$act      = array( 'rows' => array(), 'total' => 0, 'page' => $page, 'per_page' => $per_page );
		if ( class_exists( 'SimpleVPBot_User_Activity_Log' ) ) {
			$act = SimpleVPBot_User_Activity_Log::fetch_for_subject( $id, $page, $per_page );
		}
		$user_arr = self::row_array( $user );
		if ( is_array( $user_arr ) && '' !== $portal_user ) {
			$user_arr['portal_url'] = $portal_user;
		}
		return new WP_REST_Response(
			array(
				'ok'                 => true,
				'user'               => $user_arr,
				'services'           => $list,
				'referrals'          => $referrals,
				'portalBaseUrl'      => $portal_base,
				'activity'           => isset( $act['rows'] ) ? $act['rows'] : array(),
				'activityPagination' => array(
					'page'    => (int) ( $act['page'] ?? $page ),
					'perPage' => (int) ( $act['per_page'] ?? $per_page ),
					'total'   => (int) ( $act['total'] ?? 0 ),
				),
			)
		);
	}

	/**
	 * Active / expired label helper for admin user services.
	 *
	 * @param string $expires_at MySQL datetime or empty.
	 * @return string active|expired|no_expiry
	 */
	private static function admin_user_service_subscription_state( $expires_at ) {
		$s = trim( (string) $expires_at );
		if ( '' === $s ) {
			return 'no_expiry';
		}
		$ts = strtotime( $s . ' UTC' );
		if ( false === $ts ) {
			return 'no_expiry';
		}
		return $ts > time() ? 'active' : 'expired';
	}

	/**
	 * List X-UI inbounds for a panel (proxies Service_Admin_Ops).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function route_panel_inbounds( WP_REST_Request $req ) {
		$panel_id = (int) $req->get_param( 'panel_id' );
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'module_missing' ), 500 );
		}
		$r = SimpleVPBot_Service_Admin_Ops::inbounds_list( $panel_id );
		$code = ! empty( $r['ok'] ) ? 200 : 400;
		return new WP_REST_Response( $r, $code );
	}

	/**
	 * List clients in one inbound with link status (proxies Service_Admin_Ops).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function route_panel_inbound_clients( WP_REST_Request $req ) {
		$panel_id   = (int) $req->get_param( 'panel_id' );
		$inbound_id = (int) $req->get_param( 'inbound_id' );
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'module_missing' ), 500 );
		}
		$r = SimpleVPBot_Service_Admin_Ops::inbound_clients( $inbound_id, $panel_id );
		$code = ! empty( $r['ok'] ) ? 200 : 400;
		return new WP_REST_Response( $r, $code );
	}

	/**
	 * Xray plans + inbound clients snapshot for dashboard configs tab.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function route_configs_snapshot( WP_REST_Request $req ) {
		$panel_id = (int) $req->get_param( 'panel_id' );
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'module_missing' ), 500 );
		}
		$r    = SimpleVPBot_Service_Admin_Ops::configs_snapshot( $panel_id );
		$code = ! empty( $r['ok'] ) ? 200 : 400;
		return new WP_REST_Response( $r, $code );
	}

	/**
	 * Bot-identical subscription URL + config lines for dashboard QR/copy (Handler_Service::get_portal_service_data).
	 *
	 * @param WP_REST_Request $req Query: service_id OR (panel_id + inbound_id + email).
	 * @return WP_REST_Response
	 */
	public static function route_configs_portal_payload( WP_REST_Request $req ) {
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'module_missing' ), 500 );
		}
		$service_id = (int) $req->get_param( 'service_id' );
		$panel_id   = (int) $req->get_param( 'panel_id' );
		$inbound_id = (int) $req->get_param( 'inbound_id' );
		$email      = sanitize_text_field( (string) $req->get_param( 'email' ) );
		$r          = SimpleVPBot_Service_Admin_Ops::configs_portal_payload( $service_id, $panel_id, $inbound_id, $email );
		$code       = ! empty( $r['ok'] ) ? 200 : 400;
		return new WP_REST_Response( $r, $code );
	}

	/**
	 * Force sync inbound client cache from X-UI panel to DB.
	 *
	 * @param WP_REST_Request $req Request (JSON body: panel_id).
	 * @return WP_REST_Response
	 */
	public static function route_configs_sync( WP_REST_Request $req ) {
		$params   = $req->get_json_params();
		$panel_id = 0;
		if ( is_array( $params ) && isset( $params['panel_id'] ) ) {
			$panel_id = absint( $params['panel_id'] );
		}
		if ( $panel_id < 1 ) {
			$panel_id = (int) $req->get_param( 'panel_id' );
		}
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'module_missing' ), 500 );
		}
		$r    = SimpleVPBot_Service_Admin_Ops::configs_sync_panel_to_db( $panel_id, true );
		$code = ! empty( $r['ok'] ) ? 200 : 400;
		return new WP_REST_Response( $r, $code );
	}

	/**
	 * Paginated broadcast queue recipients (per user, all bot rows).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function route_admin_broadcast_queue( WP_REST_Request $req ) {
		if ( ! class_exists( 'SimpleVPBot_Model_Broadcast' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'no_model' ), 500 );
		}
		$bid = (int) $req->get_param( 'broadcast_id' );
		if ( $bid < 1 ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'invalid_broadcast' ), 400 );
		}
		$brow = SimpleVPBot_Model_Broadcast::find( $bid );
		if ( ! $brow ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'not_found' ), 404 );
		}
		if ( ! self::dashboard_rest_is_unrestricted_site_admin() ) {
			$ctx = self::dashboard_actor_context();
			if ( empty( $ctx['isReseller'] ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden' ), 403 );
			}
			$actor = (int) ( $ctx['actorUserId'] ?? 0 );
			$perms = $actor > 0 && class_exists( 'SimpleVPBot_Model_User' ) ? SimpleVPBot_Model_User::reseller_permissions( $actor ) : array();
			if ( empty( $perms['broadcast.send'] ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden' ), 403 );
			}
			$owner = (int) ( $brow->owner_svp_user_id ?? 0 );
			if ( $owner !== $actor ) {
				return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden' ), 403 );
			}
		}
		$page = (int) $req->get_param( 'page' );
		if ( $page < 1 ) {
			$page = 1;
		}
		$per = (int) $req->get_param( 'per_page' );
		if ( $per < 1 ) {
			$per = 25;
		}
		$data = SimpleVPBot_Model_Broadcast::list_queue_users_page( $bid, $page, $per );
		return new WP_REST_Response(
			array(
				'ok'         => true,
				'pagination' => array(
					'page'    => (int) $data['page'],
					'perPage' => (int) $data['perPage'],
					'total'   => (int) $data['total'],
				),
				'users'      => $data['users'],
			),
			200
		);
	}

	/**
	 * List users bulk jobs.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function route_admin_users_bulk_jobs( WP_REST_Request $req ) {
		if ( ! class_exists( 'SimpleVPBot_Model_Users_Bulk_Job' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'no_model' ), 500 );
		}
		$ctx = self::dashboard_actor_context();
		$page = (int) $req->get_param( 'page' );
		if ( $page < 1 ) {
			$page = 1;
		}
		$per = (int) $req->get_param( 'per_page' );
		if ( $per < 1 ) {
			$per = 20;
		}
		$per    = min( 100, $per );
		$offset = ( $page - 1 ) * $per;
		if ( ! empty( $ctx['isReseller'] ) ) {
			$actor = (int) ( $ctx['actorUserId'] ?? 0 );
			if ( $actor < 1 ) {
				return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden' ), 403 );
			}
			$perms = SimpleVPBot_Model_User::reseller_permissions( $actor );
			if ( empty( $perms['users.bulk'] ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden' ), 403 );
			}
			$rows  = SimpleVPBot_Model_Users_Bulk_Job::list_jobs_for_svp_actor( $actor, $per, $offset );
			$total = SimpleVPBot_Model_Users_Bulk_Job::count_jobs_for_svp_actor( $actor );
		} else {
			if ( ! current_user_can( 'manage_options' ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden' ), 403 );
			}
			$rows  = SimpleVPBot_Model_Users_Bulk_Job::list_jobs( $per, $offset );
			$total = SimpleVPBot_Model_Users_Bulk_Job::count_jobs();
		}
		return new WP_REST_Response(
			array(
				'ok'         => true,
				'jobs'        => is_array( $rows ) ? $rows : array(),
				'pagination' => array(
					'page'    => $page,
					'perPage' => $per,
					'total'   => $total,
				),
			),
			200
		);
	}

	/**
	 * List one users bulk job items.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function route_admin_users_bulk_job_items( WP_REST_Request $req ) {
		if ( ! class_exists( 'SimpleVPBot_Model_Users_Bulk_Job' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'no_model' ), 500 );
		}
		$ctx = self::dashboard_actor_context();
		$job_id = (int) $req->get_param( 'job_id' );
		if ( $job_id < 1 ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'invalid_job' ), 400 );
		}
		if ( ! empty( $ctx['isReseller'] ) ) {
			$actor = (int) ( $ctx['actorUserId'] ?? 0 );
			$perms = $actor > 0 ? SimpleVPBot_Model_User::reseller_permissions( $actor ) : array();
			if ( $actor < 1 || empty( $perms['users.bulk'] ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden' ), 403 );
			}
			if ( ! SimpleVPBot_Model_Users_Bulk_Job::job_visible_to_svp_actor( $job_id, $actor ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden' ), 403 );
			}
		} elseif ( ! current_user_can( 'manage_options' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden' ), 403 );
		}
		$page = (int) $req->get_param( 'page' );
		if ( $page < 1 ) {
			$page = 1;
		}
		$per = (int) $req->get_param( 'per_page' );
		if ( $per < 1 ) {
			$per = 25;
		}
		$per = min( 100, $per );
		$r   = SimpleVPBot_Model_Users_Bulk_Job::list_job_items( $job_id, $page, $per );
		return new WP_REST_Response(
			array(
				'ok'         => true,
				'rows'        => isset( $r['rows'] ) ? $r['rows'] : array(),
				'pagination' => array(
					'page'    => (int) ( $r['page'] ?? $page ),
					'perPage' => (int) ( $r['perPage'] ?? $per ),
					'total'   => (int) ( $r['total'] ?? 0 ),
				),
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function route_admin_mutate( WP_REST_Request $req ) {
		$ctx = self::dashboard_actor_context();
		$params = $req->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$op = isset( $params['op'] ) ? sanitize_key( (string) $params['op'] ) : '';
		if ( '' === $op ) {
			return new WP_Error( 'bad_request', 'missing op', array( 'status' => 400 ) );
		}
		if ( ! empty( $ctx['isReseller'] ) ) {
			if ( ! class_exists( 'SimpleVPBot_Dashboard_Mutate_Policy' ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'message' => 'policy_missing' ), 500 );
			}
			$req_perm = SimpleVPBot_Dashboard_Mutate_Policy::reseller_mutate_required_permission( $op );
			if ( null === $req_perm ) {
				return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden_op' ), 403 );
			}
			$actor_uid = (int) $ctx['actorUserId'];
			$rperms    = $actor_uid > 0 ? SimpleVPBot_Model_User::reseller_permissions( $actor_uid ) : array();
			if ( '' !== $req_perm && ( ! isset( $rperms[ $req_perm ] ) || empty( $rperms[ $req_perm ] ) ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden_perm' ), 403 );
			}
			$target_uid = 0;
			if ( isset( $params['svp_user_id'] ) ) {
				$target_uid = (int) $params['svp_user_id'];
			} elseif ( isset( $params['target_user_id'] ) ) {
				$target_uid = (int) $params['target_user_id'];
			} elseif ( isset( $params['membership_user_id'] ) ) {
				$target_uid = (int) $params['membership_user_id'];
			}
			if ( $target_uid > 0 && ! SimpleVPBot_Model_User::reseller_can_access_user( (int) $ctx['actorUserId'], $target_uid ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden_scope' ), 403 );
			}
			$service_id = isset( $params['service_id'] ) ? (int) $params['service_id'] : 0;
			if ( $service_id > 0 ) {
				$svc = SimpleVPBot_Model_Service::find_any( $service_id );
				$svc_uid = $svc ? (int) ( $svc->user_id ?? 0 ) : 0;
				if ( $svc_uid < 1 || ! SimpleVPBot_Model_User::reseller_can_access_user( (int) $ctx['actorUserId'], $svc_uid ) ) {
					return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden_scope' ), 403 );
				}
			}
			if ( 'user_manual_create' === $op ) {
				$params['invited_by'] = (int) $ctx['actorUserId'];
			}
			$params['__actor_svp_user_id'] = (int) $ctx['actorUserId'];
			$params['owner_svp_user_id']   = (int) $ctx['actorUserId'];
		}
		if ( current_user_can( 'manage_options' ) ) {
			$owner_ctx = isset( $params['reseller_context_svp_user_id'] ) ? (int) $params['reseller_context_svp_user_id'] : 0;
			if ( $owner_ctx > 0 ) {
				$params['owner_svp_user_id'] = $owner_ctx;
			}
		}
		unset( $params['op'] );
		$res = SimpleVPBot_Dashboard_Admin_Mutations::apply( $op, $params );
		if ( empty( $res['ok'] ) ) {
			return new WP_REST_Response( $res, 400 );
		}
		return new WP_REST_Response( $res, 200 );
	}

	/**
	 * Multipart image upload for dashboard broadcast (no media library UI).
	 *
	 * @param WP_REST_Request $req Request with multipart field `file`.
	 * @return WP_REST_Response
	 */
	public static function route_admin_media_upload( WP_REST_Request $req ) {
		$files = $req->get_file_params();
		if ( empty( $files['file'] ) || ! is_array( $files['file'] ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'no_file' ), 400 );
		}
		$f = $files['file'];
		if ( ! empty( $f['error'] ) || empty( $f['tmp_name'] ) || ! is_uploaded_file( $f['tmp_name'] ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'upload_err' ), 400 );
		}
		$max = 8 * 1024 * 1024;
		if ( isset( $f['size'] ) && (int) $f['size'] > $max ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'file_too_large' ), 400 );
		}
		$check = wp_check_filetype_and_ext( $f['tmp_name'], isset( $f['name'] ) ? (string) $f['name'] : '' );
		$allowed_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
		if ( empty( $check['type'] ) || ! in_array( $check['type'], $allowed_types, true ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'bad_type' ), 400 );
		}
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'upload_dir' ), 500 );
		}
		$subdir = '/simplevpbot/broadcast';
		$dir    = $upload['basedir'] . $subdir;
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'mkdir' ), 500 );
		}
		$ext = isset( $check['ext'] ) ? strtolower( (string) $check['ext'] ) : '';
		if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
			$ext = 'jpg';
		}
		if ( 'jpeg' === $ext ) {
			$ext = 'jpg';
		}
		$name = wp_generate_password( 18, false, false ) . '.' . $ext;
		$dest = $dir . '/' . $name;
		if ( ! @move_uploaded_file( $f['tmp_name'], $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'move_failed' ), 500 );
		}
		@chmod( $dest, 0644 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$url = $upload['baseurl'] . $subdir . '/' . $name;
		return new WP_REST_Response(
			array(
				'ok'  => true,
				'url' => esc_url_raw( $url ),
			),
			200
		);
	}
}
