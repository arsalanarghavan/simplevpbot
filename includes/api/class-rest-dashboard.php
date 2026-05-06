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
				'permission_callback' => array( __CLASS__, 'perm_manage' ),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/broadcast-queue',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'route_admin_broadcast_queue' ),
				'permission_callback' => array( __CLASS__, 'perm_manage' ),
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
				'permission_callback' => array( __CLASS__, 'perm_manage' ),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/users-bulk-job-items',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'route_admin_users_bulk_job_items' ),
				'permission_callback' => array( __CLASS__, 'perm_manage' ),
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
		return current_user_can( 'manage_options' );
	}

	/**
	 * Admin or linked reseller (dashboard role).
	 *
	 * @return bool
	 */
	public static function perm_admin_or_reseller() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$ctx = self::dashboard_actor_context();
		return ! empty( $ctx['isReseller'] );
	}

	/**
	 * Resolve current dashboard actor context.
	 *
	 * @return array{isAdmin:bool,isReseller:bool,actorUserId:int,actorRow:object|null}
	 */
	private static function dashboard_actor_context() {
		$is_admin = current_user_can( 'manage_options' );
		$row      = null;
		$uid      = 0;
		$is_reseller = false;
		if ( class_exists( 'SimpleVPBot_Model_User' ) ) {
			$row = SimpleVPBot_Model_User::find_by_wp_user( get_current_user_id() );
			if ( $row ) {
				$uid         = (int) ( $row->id ?? 0 );
				$is_reseller = SimpleVPBot_Model_User::is_reseller_row( $row );
			}
		}
		return array(
			'isAdmin'    => (bool) $is_admin,
			'isReseller' => (bool) ( ! $is_admin && $is_reseller ),
			'actorUserId'=> $uid,
			'actorRow'   => $row,
		);
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function route_bootstrap() {
		$user     = wp_get_current_user();
		$ctx      = self::dashboard_actor_context();
		$is_admin = ! empty( $ctx['isAdmin'] );
		$svp_uid  = (int) ( $ctx['actorUserId'] ?? 0 );
		$locale = determine_locale();
		$lang   = ( 0 === strpos( $locale, 'fa' ) ) ? 'fa' : 'en';
		$tz = function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : '';
		return new WP_REST_Response(
			array(
				'restUrl'        => rest_url( self::NS ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'locale'         => $locale,
				'lang'           => $lang,
				'isRtl'          => ( 'fa' === $lang ),
				'isLoggedIn'     => true,
				'isAdmin'        => $is_admin,
				'isReseller'     => ! empty( $ctx['isReseller'] ),
				'svpUserId'      => $svp_uid,
				'loginUrl'       => wp_login_url( home_url( '/dashboard/' ) ),
				'dashboardUrl'   => home_url( '/dashboard/' ),
				'dashboardLoginUrl' => trailingslashit( home_url( '/dashboard/login' ) ),
				'logoutUrl'      => wp_logout_url( home_url( '/dashboard/' ) ),
				'siteName'       => get_bloginfo( 'name' ),
				'pluginUrl'      => SIMPLEVPBOT_PLUGIN_URL,
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'adminAjaxNonce' => wp_create_nonce( 'simplevpbot_admin' ),
				'siteTimeZone'   => is_string( $tz ) ? $tz : '',
			)
		);
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
	 * Minimal dashboard settings for reseller view (no sensitive/global internals).
	 *
	 * @param array<string,mixed> $settings Raw global settings.
	 * @return array<string,mixed>
	 */
	private static function reseller_safe_settings( array $settings ) {
		$allow = array(
			'site_name',
			'site_url',
			'support_url',
			'support_username',
			'brand_default',
			'enabled',
			'lang',
			'timezone',
			'currency',
			'dashboard_title',
		);
		$out = array();
		foreach ( $allow as $k ) {
			if ( array_key_exists( $k, $settings ) ) {
				$out[ $k ] = $settings[ $k ];
			}
		}
		return $out;
	}

	/**
	 * Permission needed for each reseller-allowed mutate op.
	 *
	 * @param string $op Operation.
	 * @return string Empty means no extra permission gate.
	 */
	private static function reseller_permission_for_op( $op ) {
		$map = array(
			'membership'               => 'users.manage',
			'user_status'              => 'users.manage',
			'user_balance_delta'       => 'users.manage',
			'user_manual_create'       => 'users.manage',
			'user_merge'               => 'users.merge',
			'user_merge_preview'       => 'users.merge',
			'users_bulk_wallet'        => 'users.bulk',
			'users_bulk_volume'        => 'users.bulk',
			'users_bulk_extend'        => 'users.bulk',
			'users_bulk_alerts'        => 'users.bulk',
			'broadcast_send'           => 'broadcast.send',
			'receipt_action'           => 'receipts.review',
			'receipt_set_status'       => 'receipts.review',
			'plan'                     => 'plans.manage',
			'user_create_service'      => 'services.manage',
			'user_renew_service'       => 'services.manage',
			'user_add_volume'          => 'services.manage',
			'user_service_transfer'    => 'services.manage',
			'service_delete'           => 'services.manage',
			'user_admin_message'       => 'services.manage',
			'service_alerts_patch'     => 'services.manage',
			'service_panel_sync'       => 'services.manage',
			'service_regen_key'        => 'services.manage',
			'service_panel_refresh'    => 'services.manage',
			'service_panel_delete_client' => 'services.manage',
			'user_service_add_slots'   => 'services.manage',
			'service_set_limit_ip'     => 'services.manage',
			'reseller_bot_tokens_save' => 'services.manage',
			'reseller_bot_webhook_set' => 'services.manage',
			'reseller_bot_secret_rotate' => 'services.manage',
		);
		return isset( $map[ $op ] ) ? (string) $map[ $op ] : '';
	}

	/**
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function route_admin_state( WP_REST_Request $req ) {
		global $wpdb;
		$settings   = SimpleVPBot_Settings::all();
		$active_tab = sanitize_key( (string) $req->get_param( 'activeTab' ) );

		$p_panels = self::dash_list_pagination( $req, 'panels', 20 );
		$p_plans  = self::dash_list_pagination( $req, 'plans', 40 );
		$p_pc     = self::dash_list_pagination( $req, 'planCategories', 40 );
		$p_cards  = self::dash_list_pagination( $req, 'cards', 40 );
		$p_l2tp   = self::dash_list_pagination( $req, 'l2tp', 20 );
		$p_texts  = self::dash_list_pagination( $req, 'texts', 40 );
		$p_disc   = self::dash_list_pagination( $req, 'discounts', 30 );
		$p_users  = self::dash_list_pagination( $req, 'users', 50 );
		$p_pend   = self::dash_list_pagination( $req, 'pendingUsers', 30 );
		$p_res    = self::dash_list_pagination( $req, 'resellers', 30 );
		$p_rcpt   = self::dash_list_pagination( $req, 'receipts', 40 );
		$p_bc     = self::dash_list_pagination( $req, 'broadcasts', 20 );
		$p_ref_ev = self::dash_list_pagination( $req, 'referralEvents', 20 );

		$t_panels = SimpleVPBot_Model_Panel::table();
		$t_plans  = SimpleVPBot_Model_Plan::table();
		$t_pc     = SimpleVPBot_Model_Plan_Category::table();
		$t_cards  = SimpleVPBot_Model_Card::table();
		$t_l2tp   = SimpleVPBot_Model_L2TP_Server::table();
		$t_texts  = SimpleVPBot_Model_Text::table();
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
		$ctx         = self::dashboard_actor_context();
		$is_reseller = ! empty( $ctx['isReseller'] );
		$actor_uid   = (int) ( $ctx['actorUserId'] ?? 0 );
		$actor_permissions = $is_reseller ? SimpleVPBot_Model_User::reseller_permissions( $actor_uid ) : SimpleVPBot_Model_User::default_reseller_permissions();
		$daily_online_series = array();
		if ( class_exists( 'SimpleVPBot_Model_Panel_Online_Daily' ) ) {
			$daily_online_series = (array) SimpleVPBot_Model_Panel_Online_Daily::daily_totals_last_days( 7 );
		}
		if ( empty( $daily_online_series ) ) {
			try {
				$today = new DateTimeImmutable( 'today', wp_timezone() );
			} catch ( \Exception $e ) {
				$today = new DateTimeImmutable( 'today' );
			}
			for ( $i = 6; $i >= 0; $i-- ) {
				$daily_online_series[] = array(
					'date'           => $today->modify( '-' . $i . ' days' )->format( 'Y-m-d' ),
					'totalMaxOnline' => 0,
				);
			}
		}

		if ( $is_reseller ) {
			$scope = SimpleVPBot_Model_User::reseller_scope_clause( $actor_uid, 'u' );
			$reseller_settings = self::reseller_safe_settings( $settings );
			if ( ! $scope ) {
				return new WP_REST_Response(
					array(
						'settings'     => $reseller_settings,
						'usersList'    => array(),
						'pendingUsers' => array(),
						'resellers'    => array(),
						'pagination'   => array(
							'usersList'    => self::dash_pagination_meta( 1, $p_users['per_page'], 0 ),
							'pendingUsers' => self::dash_pagination_meta( 1, $p_pend['per_page'], 0 ),
							'resellers'    => self::dash_pagination_meta( 1, $p_res['per_page'], 0 ),
						),
					)
				);
			}
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

			$tot_plans_res = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$t_plans} WHERE owner_svp_user_id = %d",
					$actor_uid
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$plans_res = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$t_plans} WHERE owner_svp_user_id = %d ORDER BY sort_order ASC, id ASC LIMIT %d OFFSET %d",
					$actor_uid,
					$p_plans['per_page'],
					$p_plans['offset']
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$tot_pc_all = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_pc}" ); // phpcs:ignore
			$plan_cats_res = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$t_pc} ORDER BY panel_id ASC, sort_order ASC, id ASC LIMIT %d OFFSET %d",
					$p_pc['per_page'],
					$p_pc['offset']
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$tot_panels_all = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_panels}" ); // phpcs:ignore
			$panels_res = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, label, active, sort_order FROM {$t_panels} ORDER BY sort_order ASC, id ASC LIMIT %d OFFSET %d",
					$p_panels['per_page'],
					$p_panels['offset']
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$reseller_prices = array();
			if ( class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' ) ) {
				$reseller_prices = array_map(
					array( __CLASS__, 'row_array' ),
					(array) SimpleVPBot_Model_Reseller_Panel_Price::list_for_reseller( $actor_uid )
				);
			}
			$bot_prof = null;
			if ( class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
				$bp   = SimpleVPBot_Model_Reseller_Bot_Profile::find_by_reseller( $actor_uid );
				$wsec = $bp ? trim( (string) ( $bp->webhook_secret ?? '' ) ) : '';
				$base = SimpleVPBot_Settings::public_site_url() . '/wp-json/simplevpbot/v1/webhook';
				$bot_prof = array(
					'has_telegram_token'  => $bp ? strlen( (string) ( $bp->telegram_token ?? '' ) ) > 0 : false,
					'has_bale_token'      => $bp ? strlen( (string) ( $bp->bale_token ?? '' ) ) > 0 : false,
					'brand_name'          => $bp ? (string) ( $bp->brand_name ?? '' ) : '',
					'has_webhook_secret'  => '' !== $wsec,
					'webhook_telegram_url' => ( '' !== $wsec )
						? $base . '/telegram/reseller/' . (int) $actor_uid . '/' . rawurlencode( $wsec )
						: '',
					'webhook_bale_url'    => ( '' !== $wsec )
						? $base . '/bale/reseller/' . (int) $actor_uid . '/' . rawurlencode( $wsec )
						: '',
				);
			}

			return new WP_REST_Response(
				array(
					'settings'              => $reseller_settings,
					'usersList'             => array_map( array( __CLASS__, 'row_array' ), (array) $users_list ),
					'pendingUsers'          => array_map( array( __CLASS__, 'row_array' ), (array) $pending_users ),
					'resellers'             => array_map( array( __CLASS__, 'row_array' ), (array) $resellers ),
					'plans'                 => array_map( array( __CLASS__, 'row_array' ), (array) $plans_res ),
					'planCategories'        => array_map( array( __CLASS__, 'row_array' ), (array) $plan_cats_res ),
					'panels'                => array_map( array( __CLASS__, 'row_array' ), (array) $panels_res ),
					'resellerPanelPrices'   => $reseller_prices,
					'resellerBotProfile'    => $bot_prof,
					'actorPermissions'      => $actor_permissions,
					'l2tpServers'           => array(),
					'overview'              => array(
						'counts' => array(
							'usersTotal'     => $tot_users_list,
							'pendingUsers'   => $tot_pend_list,
							'resellers'      => $tot_res_list,
							'resellerPlans'  => $tot_plans_res,
						),
						'host'                  => self::overview_host_metrics(),
						'onlineDailySeries'     => $daily_online_series,
						'panelHealth'           => array(),
						'livePanelSnapshots'    => array(),
						'externalHostSnapshots' => array(),
					),
					'monitorHosts'          => array(),
					'pagination'            => array(
						'usersList'       => self::dash_pagination_meta( $p_users['page'], $p_users['per_page'], $tot_users_list ),
						'pendingUsers'    => self::dash_pagination_meta( $p_pend['page'], $p_pend['per_page'], $tot_pend_list ),
						'resellers'       => self::dash_pagination_meta( $p_res['page'], $p_res['per_page'], $tot_res_list ),
						'plans'           => self::dash_pagination_meta( $p_plans['page'], $p_plans['per_page'], $tot_plans_res ),
						'planCategories'  => self::dash_pagination_meta( $p_pc['page'], $p_pc['per_page'], $tot_pc_all ),
						'panels'          => self::dash_pagination_meta( $p_panels['page'], $p_panels['per_page'], $tot_panels_all ),
					),
				)
			);
		}

		$tot_panels = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_panels}" ); // phpcs:ignore
		$tot_plans  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_plans}" ); // phpcs:ignore
		$tot_pc     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_pc}" ); // phpcs:ignore
		$tot_cards  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_cards}" ); // phpcs:ignore
		$tot_l2tp   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_l2tp}" ); // phpcs:ignore
		$tot_texts  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_texts}" ); // phpcs:ignore
		$tot_disc   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_disc}" ); // phpcs:ignore
		$tot_users  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$u_tbl}" ); // phpcs:ignore
		$tot_pend   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$u_tbl} WHERE status = %s", 'pending' ) ); // phpcs:ignore
		$tot_resellers = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$u_tbl} WHERE role = %s", 'reseller' ) ); // phpcs:ignore
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
		$tot_rcpt   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$rcpt_t}" ); // phpcs:ignore
		$tot_bc     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bc_t}" ); // phpcs:ignore

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$panels_raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t_panels} ORDER BY sort_order ASC, id ASC LIMIT %d OFFSET %d",
				$p_panels['per_page'],
				$p_panels['offset']
			)
		);
		$plans_raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t_plans} ORDER BY sort_order ASC, id ASC LIMIT %d OFFSET %d",
				$p_plans['per_page'],
				$p_plans['offset']
			)
		);
		$plan_cats_raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t_pc} ORDER BY panel_id ASC, sort_order ASC, id ASC LIMIT %d OFFSET %d",
				$p_pc['per_page'],
				$p_pc['offset']
			)
		);
		$cards_raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t_cards} ORDER BY id DESC LIMIT %d OFFSET %d",
				$p_cards['per_page'],
				$p_cards['offset']
			)
		);
		$l2tp_raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t_l2tp} ORDER BY id DESC LIMIT %d OFFSET %d",
				$p_l2tp['per_page'],
				$p_l2tp['offset']
			)
		);
		$texts_raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t_texts} ORDER BY id ASC LIMIT %d OFFSET %d",
				$p_texts['per_page'],
				$p_texts['offset']
			)
		);
		$discounts_raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t_disc} ORDER BY active DESC, id DESC LIMIT %d OFFSET %d",
				$p_disc['per_page'],
				$p_disc['offset']
			)
		);
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
		$receipts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$rcpt_t} ORDER BY id DESC LIMIT %d OFFSET %d",
				$p_rcpt['per_page'],
				$p_rcpt['offset']
			)
		);
		$broadcasts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$bc_t} ORDER BY id DESC LIMIT %d OFFSET %d",
				$p_bc['per_page'],
				$p_bc['offset']
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

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
				$panels[] = $ra;
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
		foreach ( (array) $texts_raw as $r ) {
			$ra = self::row_array( $r );
			if ( $ra ) {
				$texts[] = $ra;
			}
		}
		foreach ( (array) $discounts_raw as $r ) {
			$ra = self::row_array( $r );
			if ( $ra ) {
				$discounts[] = $ra;
			}
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
		$agg_rows         = $wpdb->get_results(
			"SELECT status, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS sum_amount FROM {$rcpt_t} GROUP BY status",
			ARRAY_A
		);
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
		$receipt_rows = array();
		foreach ( (array) $receipts as $rr ) {
			$ra = self::row_array( $rr );
			if ( ! $ra ) {
				continue;
			}
			$rid = isset( $ra['id'] ) ? (int) $ra['id'] : 0;
			if ( $rid > 0 ) {
				$ra['imageUrl'] = add_query_arg(
					array(
						'action' => 'simplevpbot_receipt_image',
						'rid'    => $rid,
						'nonce'  => wp_create_nonce( 'svp_recimg_' . $rid ),
					),
					admin_url( 'admin-ajax.php' )
				);
			} else {
				$ra['imageUrl'] = '';
			}
			$receipt_rows[] = $ra;
		}

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
		$nav_tabs = array(
			array( 'key' => 'dashboard', 'label' => __( 'پیشخوان', 'simplevpbot' ) ),
			array( 'key' => 'monitoring', 'label' => __( 'مانیتورینگ', 'simplevpbot' ) ),
			array( 'key' => 'site_settings', 'label' => __( 'تنظیمات سایت', 'simplevpbot' ) ),
			array( 'key' => 'bots', 'label' => __( 'ربات‌ها', 'simplevpbot' ) ),
			array( 'key' => 'xui_panels', 'label' => __( 'پنل‌های 3x-ui', 'simplevpbot' ) ),
			array( 'key' => 'panel_inbounds', 'label' => __( 'اتصال کانفیگ پنل', 'simplevpbot' ) ),
			array( 'key' => 'plan_cats', 'label' => __( 'دسته‌های خرید', 'simplevpbot' ) ),
			array( 'key' => 'plans', 'label' => __( 'پلن‌ها', 'simplevpbot' ) ),
			array( 'key' => 'cards', 'label' => __( 'کارت‌ها', 'simplevpbot' ) ),
			array( 'key' => 'l2tp_servers', 'label' => __( 'سرورهای L2TP', 'simplevpbot' ) ),
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
		if ( class_exists( 'SimpleVPBot_Admin_Dashboard_Stats' ) ) {
			$stats_payload = SimpleVPBot_Admin_Dashboard_Stats::build_payload( 0 );
		}

		$text_defaults = class_exists( 'SimpleVPBot_Activator' ) ? SimpleVPBot_Activator::default_text_values_map() : array();

		$referral_stats     = null;
		$referral_events    = array();
		$tot_referral_ev    = 0;
		if ( 'referral' === $active_tab && class_exists( 'SimpleVPBot_Model_Referral_Event' ) ) {
			$t_tx             = SimpleVPBot_Model_Transaction::table();
			$ref_days         = max( 7, min( 90, (int) $req->get_param( 'referral_days' ) ?: 30 ) );
			$tot_referral_ev = SimpleVPBot_Model_Referral_Event::count_all();
			$since_ts    = strtotime( '-' . $ref_days . ' days', (int) current_time( 'timestamp' ) );
			$since_mysql = wp_date( 'Y-m-d H:i:s', $since_ts );

			$events_last_30 = SimpleVPBot_Model_Referral_Event::count_since( $since_mysql );
			$invited_users  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$u_tbl} WHERE invited_by IS NOT NULL AND invited_by > 0" ); // phpcs:ignore
			$commission_sum = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$t_tx} WHERE type = 'referral_commission' AND status = 'approved'" ); // phpcs:ignore
			$ref_amt_sum    = (float) $wpdb->get_var( "SELECT COALESCE(SUM(referral_amount),0) FROM {$t_tx} WHERE type IN ('purchase','renew') AND status = 'approved'" ); // phpcs:ignore
			$converted_last_window = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM " . SimpleVPBot_Model_Referral_Event::table() . " WHERE created_at >= %s AND resulting_svp_user_id IS NOT NULL AND resulting_svp_user_id > 0",
					$since_mysql
				)
			); // phpcs:ignore
			$window_conversion_rate = $events_last_30 > 0 ? round( ( $converted_last_window * 100 ) / $events_last_30, 2 ) : 0.0;

			$platform_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT platform, COUNT(*) AS cnt
					FROM " . SimpleVPBot_Model_Referral_Event::table() . "
					WHERE created_at >= %s
					GROUP BY platform
					ORDER BY cnt DESC",
					$since_mysql
				),
				ARRAY_A
			);
			$platform_breakdown = array();
			if ( is_array( $platform_rows ) ) {
				foreach ( $platform_rows as $pr ) {
					if ( ! is_array( $pr ) ) {
						continue;
					}
					$platform_breakdown[] = array(
						'platform' => (string) ( $pr['platform'] ?? '' ),
						'count'    => (int) ( $pr['cnt'] ?? 0 ),
					);
				}
			}

			$outcome_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT outcome, COUNT(*) AS cnt
					FROM " . SimpleVPBot_Model_Referral_Event::table() . "
					WHERE created_at >= %s
					GROUP BY outcome
					ORDER BY cnt DESC",
					$since_mysql
				),
				ARRAY_A
			);
			$outcome_breakdown = array();
			if ( is_array( $outcome_rows ) ) {
				foreach ( $outcome_rows as $orow ) {
					if ( ! is_array( $orow ) ) {
						continue;
					}
					$outcome_breakdown[] = array(
						'outcome' => (string) ( $orow['outcome'] ?? '' ),
						'count'   => (int) ( $orow['cnt'] ?? 0 ),
					);
				}
			}

			$trend_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DATE(created_at) AS d, COUNT(*) AS cnt
					FROM " . SimpleVPBot_Model_Referral_Event::table() . "
					WHERE created_at >= %s
					GROUP BY DATE(created_at)
					ORDER BY d ASC",
					$since_mysql
				),
				ARRAY_A
			);
			$trend_map = array();
			if ( is_array( $trend_rows ) ) {
				foreach ( $trend_rows as $trn ) {
					if ( ! is_array( $trn ) ) {
						continue;
					}
					$key = (string) ( $trn['d'] ?? '' );
					if ( '' === $key ) {
						continue;
					}
					$trend_map[ $key ] = (int) ( $trn['cnt'] ?? 0 );
				}
			}
			$trend_series = array();
			for ( $i = $ref_days - 1; $i >= 0; $i-- ) {
				$d = wp_date( 'Y-m-d', strtotime( '-' . $i . ' days', (int) current_time( 'timestamp' ) ) );
				$trend_series[] = array(
					'date'  => $d,
					'count' => isset( $trend_map[ $d ] ) ? (int) $trend_map[ $d ] : 0,
				);
			}

			$top_rows = $wpdb->get_results(
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

			$ev_raw = SimpleVPBot_Model_Referral_Event::list_desc( $p_ref_ev['per_page'], $p_ref_ev['offset'] );
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
					'windowDays'                    => $ref_days,
					'convertedEventsInWindow'       => $converted_last_window,
					'conversionRateInWindow'        => $window_conversion_rate,
				),
				'topReferrers'        => $top_referrers,
				'platformBreakdown'   => $platform_breakdown,
				'outcomeBreakdown'    => $outcome_breakdown,
				'dailyTrend'          => $trend_series,
			);
		}

		$force_health = $req->get_param( 'refreshPanelHealth' ) === '1';
		$panel_health = array();
		// Health covers all panels (not only the paged list slice used elsewhere).
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

		$force_live_metrics = ( $req->get_param( 'refreshLivePanelMetrics' ) === '1' );
		$want_live_metrics  = ( 'monitoring' === $active_tab ) || $force_live_metrics;
		$live_snapshots     = array();
		$external_snaps     = array();
		$monitor_hosts_pub  = array();
		if ( class_exists( 'SimpleVPBot_Model_Monitor_Host' ) ) {
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

		// Live snapshots upsert today's max_online; refresh stats + chart series for this response.
		if ( $want_live_metrics && class_exists( 'SimpleVPBot_Admin_Dashboard_Stats' ) ) {
			$stats_payload = SimpleVPBot_Admin_Dashboard_Stats::build_payload( 0 );
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
			'host'          => self::overview_host_metrics(),
			'onlineDailySeries' => $daily_online_series,
			'panelHealth'   => $panel_health,
			'livePanelSnapshots' => $live_snapshots,
			'externalHostSnapshots' => $external_snaps,
		);

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
		);

		$reseller_permissions_map = array();
		$reseller_panel_prices_map = array();
		foreach ( (array) $resellers as $r ) {
			if ( is_object( $r ) && isset( $r->id ) ) {
				$rid = (int) $r->id;
				$reseller_permissions_map[ $rid ] = SimpleVPBot_Model_User::reseller_permissions( $rid );
				if ( class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' ) ) {
					$reseller_panel_prices_map[ $rid ] = array_map(
						array( __CLASS__, 'row_array' ),
						(array) SimpleVPBot_Model_Reseller_Panel_Price::list_for_reseller( $rid )
					);
				}
			}
		}

		return new WP_REST_Response(
			array(
				'settings'                 => $settings,
				'textDefaults'             => $text_defaults,
				'referralStats'            => $referral_stats,
				'referralEvents'          => $referral_events,
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
				'actorPermissions'         => $actor_permissions,
				'receipts'                 => $receipt_rows,
				'receiptAggregates'        => $receipt_aggregates,
				'broadcasts'               => array_map( array( __CLASS__, 'row_array' ), (array) $broadcasts ),
				'broadcastQueueAggregates' => $broadcast_queue_aggregates,
				'wpPages'                  => $page_choices,
				'navTabs'                  => $nav_tabs,
				'overview'                 => $overview,
				'monitorHosts'             => $monitor_hosts_pub,
				'pagination'               => $pagination,
			)
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function route_me_state() {
		if ( current_user_can( 'manage_options' ) ) {
			return new WP_REST_Response( array( 'ok' => true, 'isAdmin' => true ), 200 );
		}
		$row = SimpleVPBot_Model_User::find_by_wp_user( get_current_user_id() );
		if ( ! $row ) {
			return new WP_REST_Response(
				array(
					'ok'      => false,
					'message' => 'no_linked_bot_user',
					'hint'    => __( 'مدیر باید حساب ربات شما را به کاربر وردپرس وصل کند.', 'simplevpbot' ),
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
		return new WP_REST_Response(
			array(
				'ok'       => true,
				'isAdmin'  => false,
				'user'     => self::row_array( $row ),
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

	public static function route_admin_users_bulk_jobs( WP_REST_Request $req ) {
		$page = max( 1, (int) $req->get_param( 'page' ) );
		$per  = max( 1, min( 50, (int) $req->get_param( 'per_page' ) ?: 20 ) );
		$offset = ( $page - 1 ) * $per;
		$rows = SimpleVPBot_Model_Users_Bulk_Job::list_jobs( $per, $offset );
		$total = SimpleVPBot_Model_Users_Bulk_Job::count_jobs();
		return new WP_REST_Response(
			array(
				'ok' => true,
				'jobs' => $rows,
				'pagination' => array(
					'page'    => $page,
					'perPage' => $per,
					'total'   => $total,
				),
			),
			200
		);
	}

	public static function route_admin_users_bulk_job_items( WP_REST_Request $req ) {
		$job_id = (int) $req->get_param( 'job_id' );
		if ( $job_id < 1 ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'invalid_job' ), 400 );
		}
		$page = max( 1, (int) $req->get_param( 'page' ) );
		$per  = max( 1, min( 100, (int) $req->get_param( 'per_page' ) ?: 25 ) );
		$status = sanitize_key( (string) $req->get_param( 'status' ) );
		$list = SimpleVPBot_Model_Users_Bulk_Job::list_job_items( $job_id, $page, $per, $status );
		return new WP_REST_Response(
			array(
				'ok' => true,
				'rows' => $list['rows'],
				'pagination' => array(
					'page'    => $list['page'],
					'perPage' => $list['perPage'],
					'total'   => $list['total'],
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
			$perms = SimpleVPBot_Model_User::reseller_permissions( (int) $ctx['actorUserId'] );
			$allow = array(
				'membership',
				'user_status',
				'user_balance_delta',
				'user_create_service',
				'user_renew_service',
				'user_add_volume',
				'user_service_transfer',
				'service_delete',
				'user_admin_message',
				'service_alerts_patch',
				'service_panel_sync',
				'service_regen_key',
				'service_panel_refresh',
				'service_panel_delete_client',
				'user_service_add_slots',
				'service_set_limit_ip',
				'user_manual_create',
				'plan',
				'reseller_bot_tokens_save',
				'reseller_bot_webhook_set',
				'reseller_bot_secret_rotate',
			);
			if ( ! in_array( $op, $allow, true ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden_op' ), 403 );
			}
			$needed_perm = self::reseller_permission_for_op( $op );
			if ( '' !== $needed_perm && empty( $perms[ $needed_perm ] ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden_permission' ), 403 );
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
			if ( 'plan' === $op ) {
				$pact = isset( $params['plan_action'] ) ? sanitize_key( (string) $params['plan_action'] ) : '';
				$pid  = isset( $params['plan_id'] ) ? absint( $params['plan_id'] ) : 0;
				if ( $pid > 0 && in_array( $pact, array( 'update', 'toggle', 'delete' ), true ) ) {
					$pl = SimpleVPBot_Model_Plan::find( $pid );
					$own = $pl ? (int) ( $pl->owner_svp_user_id ?? 0 ) : 0;
					if ( $own !== (int) $ctx['actorUserId'] ) {
						return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden_scope' ), 403 );
					}
				}
			}
			$params['__actor_svp_user_id'] = (int) $ctx['actorUserId'];
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
