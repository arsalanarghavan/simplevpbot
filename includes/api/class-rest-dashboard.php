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
			'/dashboard/ui-preferences',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'route_ui_preferences' ),
				'permission_callback' => array( __CLASS__, 'perm_logged_in' ),
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
			'/dashboard/admin/inbound-display-catalog',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'route_inbound_display_catalog' ),
				'permission_callback' => array( __CLASS__, 'perm_admin_or_reseller' ),
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
			'/dashboard/admin/audit',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'route_admin_audit' ),
				'permission_callback' => array( __CLASS__, 'perm_manage' ),
				'args'                => array(
					'page'       => array(
						'default'           => 1,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'per_page'   => array(
						'default'           => 25,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'domain'     => array(
						'default'           => '',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'event_type' => array(
						'default'           => '',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'q'          => array(
						'default'           => '',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/backups',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'route_admin_backups' ),
				'permission_callback' => array( __CLASS__, 'perm_manage' ),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/backup/run',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'route_admin_backup_run' ),
				'permission_callback' => array( __CLASS__, 'perm_manage' ),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/backup/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'route_admin_backup_status' ),
				'permission_callback' => array( __CLASS__, 'perm_manage' ),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/backup/reset-stuck',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'route_admin_backup_reset_stuck' ),
				'permission_callback' => array( __CLASS__, 'perm_manage' ),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/backup/download',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'route_admin_backup_download' ),
				'permission_callback' => array( __CLASS__, 'perm_manage' ),
				'args'                => array(
					'filename' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_file_name',
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/backup/restore',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'route_admin_backup_restore' ),
				'permission_callback' => array( __CLASS__, 'perm_manage' ),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/backup/restore-upload',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'route_admin_backup_restore_upload' ),
				'permission_callback' => array( __CLASS__, 'perm_manage' ),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/panel/rebuild-from-db',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'route_admin_panel_rebuild_from_db' ),
				'permission_callback' => array( __CLASS__, 'perm_manage' ),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/panel/fix-51200-traffic',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'route_admin_panel_fix_51200_traffic' ),
				'permission_callback' => array( __CLASS__, 'perm_manage' ),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/panel/inbound-map',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'route_admin_panel_inbound_map_get' ),
					'permission_callback' => array( __CLASS__, 'perm_manage' ),
					'args'                => array(
						'panel_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'route_admin_panel_inbound_map_save' ),
					'permission_callback' => array( __CLASS__, 'perm_manage' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/purge-expired',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'route_admin_purge_expired' ),
				'permission_callback' => array( __CLASS__, 'perm_manage' ),
				'args'                => array(
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
					'status'   => array(
						'default'           => 'all',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'panel_id' => array(
						'default'           => 0,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard/admin/logs',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'route_admin_logs' ),
				'permission_callback' => array( __CLASS__, 'perm_manage' ),
				'args'                => array(
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
					'level'    => array(
						'default'           => '',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'q'        => array(
						'default'           => '',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
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
	 * Allowed dashboard accent presets.
	 *
	 * @return string[]
	 */
	public static function dashboard_accent_presets() {
		return array( 'default', 'red', 'rose', 'orange', 'green', 'blue', 'yellow', 'violet' );
	}

	/**
	 * Normalize stored accent value.
	 *
	 * @param mixed $accent Raw value.
	 * @return string
	 */
	public static function normalize_dashboard_accent( $accent ) {
		$a = sanitize_key( (string) $accent );
		if ( '' === $a || 'default' === $a ) {
			return 'default';
		}
		if ( 'amber' === $a ) {
			return 'orange';
		}
		return in_array( $a, self::dashboard_accent_presets(), true ) ? $a : 'default';
	}

	/**
	 * Current WP user's saved dashboard accent.
	 *
	 * @param int|null $wp_user_id Optional WP user id.
	 * @return string
	 */
	public static function dashboard_ui_accent_for_user( $wp_user_id = null ) {
		$uid = null !== $wp_user_id ? (int) $wp_user_id : (int) get_current_user_id();
		if ( $uid <= 0 ) {
			return 'default';
		}
		$raw = (string) get_user_meta( $uid, 'svp_dashboard_accent', true );
		return self::normalize_dashboard_accent( $raw );
	}

	/**
	 * @param mixed $lang Raw value.
	 * @return string fa|en
	 */
	public static function normalize_dashboard_lang( $lang ) {
		$l = sanitize_key( (string) $lang );
		return 'en' === $l ? 'en' : 'fa';
	}

	/**
	 * @param int|null $wp_user_id Optional WP user id.
	 * @return string fa|en Empty string when unset (use site locale).
	 */
	public static function dashboard_ui_lang_for_user( $wp_user_id = null ) {
		$uid = null !== $wp_user_id ? (int) $wp_user_id : (int) get_current_user_id();
		if ( $uid <= 0 ) {
			return '';
		}
		$raw = (string) get_user_meta( $uid, 'svp_dashboard_lang', true );
		if ( '' === $raw ) {
			return '';
		}
		return self::normalize_dashboard_lang( $raw );
	}

	/**
	 * @param mixed $theme Raw value.
	 * @return string light|dark|system
	 */
	public static function normalize_dashboard_theme( $theme ) {
		$t = sanitize_key( (string) $theme );
		if ( in_array( $t, array( 'light', 'dark', 'system' ), true ) ) {
			return $t;
		}
		return 'system';
	}

	/**
	 * @param int|null $wp_user_id Optional WP user id.
	 * @return string light|dark|system Empty when unset.
	 */
	public static function dashboard_ui_theme_for_user( $wp_user_id = null ) {
		$uid = null !== $wp_user_id ? (int) $wp_user_id : (int) get_current_user_id();
		if ( $uid <= 0 ) {
			return '';
		}
		$raw = (string) get_user_meta( $uid, 'svp_dashboard_theme', true );
		if ( '' === $raw ) {
			return '';
		}
		return self::normalize_dashboard_theme( $raw );
	}

	/**
	 * @param mixed $sidebar Raw value.
	 * @return string expanded|collapsed
	 */
	public static function normalize_dashboard_sidebar( $sidebar ) {
		$s = sanitize_key( (string) $sidebar );
		return 'collapsed' === $s ? 'collapsed' : 'expanded';
	}

	/**
	 * @param int|null $wp_user_id Optional WP user id.
	 * @return string expanded|collapsed Empty when unset.
	 */
	public static function dashboard_ui_sidebar_for_user( $wp_user_id = null ) {
		$uid = null !== $wp_user_id ? (int) $wp_user_id : (int) get_current_user_id();
		if ( $uid <= 0 ) {
			return '';
		}
		$raw = (string) get_user_meta( $uid, 'svp_dashboard_sidebar', true );
		if ( '' === $raw ) {
			return '';
		}
		return self::normalize_dashboard_sidebar( $raw );
	}

	/**
	 * CSS variable keys overridden by accent presets (skip whitelabel when accent active).
	 *
	 * @return string[]
	 */
	public static function dashboard_accent_branding_var_keys() {
		return array(
			'--primary',
			'--primary-foreground',
			'--ring',
			'--sidebar-primary',
			'--sidebar-primary-foreground',
			'--sidebar-ring',
		);
	}

	/**
	 * Save dashboard UI preferences (accent, lang, theme, sidebar).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function route_ui_preferences( WP_REST_Request $req ) {
		$uid = (int) get_current_user_id();
		if ( $uid <= 0 ) {
			return new WP_REST_Response( array( 'ok' => false ), 401 );
		}
		$params = $req->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$out = array( 'ok' => true );

		if ( array_key_exists( 'ui_accent', $params ) ) {
			$accent = self::normalize_dashboard_accent( $params['ui_accent'] ?? '' );
			update_user_meta( $uid, 'svp_dashboard_accent', $accent );
			$out['uiAccent'] = $accent;
		}
		if ( array_key_exists( 'ui_lang', $params ) ) {
			$lang = self::normalize_dashboard_lang( $params['ui_lang'] ?? '' );
			update_user_meta( $uid, 'svp_dashboard_lang', $lang );
			$out['uiLang'] = $lang;
		}
		if ( array_key_exists( 'ui_theme', $params ) ) {
			$theme = self::normalize_dashboard_theme( $params['ui_theme'] ?? '' );
			update_user_meta( $uid, 'svp_dashboard_theme', $theme );
			$out['uiTheme'] = $theme;
		}
		if ( array_key_exists( 'ui_sidebar', $params ) ) {
			$sidebar = self::normalize_dashboard_sidebar( $params['ui_sidebar'] ?? '' );
			update_user_meta( $uid, 'svp_dashboard_sidebar', $sidebar );
			$out['uiSidebar'] = $sidebar;
		}

		return new WP_REST_Response( $out, 200 );
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
	public static function dashboard_rest_is_unrestricted_site_admin() {
		return current_user_can( 'manage_options' ) && ! is_array( self::get_impersonation_payload() );
	}

	/**
	 * Validate admin workspace reseller context id (must be an approved reseller row).
	 *
	 * @param int $id svp_users.id from query/body.
	 * @return int|null Valid id, or null when id is positive but not a reseller.
	 */
	private static function validate_reseller_context_id( $id ) {
		$rid = (int) $id;
		if ( $rid < 1 ) {
			return 0;
		}
		$row = SimpleVPBot_Model_User::find( $rid );
		if ( ! $row || ! SimpleVPBot_Model_User::is_reseller_row( $row ) ) {
			return null;
		}
		return $rid;
	}

	/**
	 * Whether dashboard actor may read a target bot user (reseller mode + impersonation scope).
	 *
	 * @param array<string, mixed> $ctx            dashboard_actor_context().
	 * @param int                  $target_user_id Target svp_users.id.
	 * @return bool
	 */
	public static function dashboard_actor_may_read_user( array $ctx, $target_user_id ) {
		$uid = (int) $target_user_id;
		if ( $uid < 1 ) {
			return false;
		}
		if ( ! empty( $ctx['isReseller'] ) && class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return SimpleVPBot_Bot_Reseller_Scope::reseller_may_moderate_user_for( (int) ( $ctx['actorUserId'] ?? 0 ), $uid );
		}
		$imp_tid = (int) ( $ctx['impersonationTargetId'] ?? 0 );
		if ( $imp_tid > 0 && class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return SimpleVPBot_Bot_Reseller_Scope::reseller_may_moderate_user_for( $imp_tid, $uid );
		}
		return true;
	}

	/**
	 * SQL fragment limiting user list queries to moderatable ids (admin owner_ctx).
	 *
	 * @param array<int, int> $user_ids Moderatable svp_users.id list.
	 * @param string          $alias    SQL table alias.
	 * @return array{sql:string,values:array<int,int>}
	 */
	private static function users_moderatable_scope_clause( array $user_ids, $alias = 'u' ) {
		$ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $user_ids ),
					static function ( $v ) {
						return (int) $v > 0;
					}
				)
			)
		);
		if ( empty( $ids ) ) {
			return array(
				'sql'    => ' AND 1=0',
				'values' => array(),
			);
		}
		$a = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $alias );
		$a = '' !== $a ? $a : 'u';
		$ph  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return array(
			'sql'    => " AND {$a}.id IN ({$ph})",
			'values' => $ids,
		);
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
		if ( count( $parts ) !== 4 ) {
			return 0;
		}
		$id        = absint( $parts[0] );
		$exp       = absint( $parts[1] );
		$admin_uid = absint( $parts[2] );
		$sig       = $parts[3];
		if ( $id < 1 || $exp < time() || $admin_uid < 1 || strlen( $sig ) !== 64 || ! ctype_xdigit( $sig ) ) {
			return 0;
		}
		if ( ! is_user_logged_in() || get_current_user_id() !== $admin_uid ) {
			return 0;
		}
		$data   = $id . '|' . $exp . '|' . $admin_uid;
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
		$admin_uid = get_current_user_id();
		if ( $admin_uid < 1 ) {
			return;
		}
		$data = $id . '|' . $exp . '|' . $admin_uid;
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
				'siteName'                 => class_exists( 'SimpleVPBot_Settings' )
					? SimpleVPBot_Settings::dashboard_site_display_name()
					: get_bloginfo( 'name' ),
				'siteIconUrl'              => class_exists( 'SimpleVPBot_Settings' )
					? SimpleVPBot_Settings::dashboard_site_icon_url_resolved()
					: '',
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
		if ( ! is_ssl() && ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			return new WP_Error(
				'https_required',
				__( 'Impersonation requires HTTPS.', 'simplevpbot' ),
				array( 'status' => 403 )
			);
		}
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
		if ( class_exists( 'SimpleVPBot_Audit_Log' ) ) {
			SimpleVPBot_Audit_Log::record(
				array(
					'domain'            => 'security',
					'event_type'        => 'impersonation.start',
					'actor_kind'        => 'wp_admin',
					'actor_wp_user_id'  => (int) get_current_user_id(),
					'target_type'       => 'user',
					'target_id'         => $tid,
					'reseller_scope_id' => $tid,
				)
			);
		}
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Clear impersonation cookie.
	 *
	 * @return WP_REST_Response
	 */
	public static function route_impersonate_stop() {
		$tid = self::parse_impersonation_cookie_token();
		self::impersonation_cookie_clear();
		if ( $tid > 0 && class_exists( 'SimpleVPBot_Audit_Log' ) ) {
			SimpleVPBot_Audit_Log::record(
				array(
					'domain'            => 'security',
					'event_type'        => 'impersonation.stop',
					'actor_kind'        => 'wp_admin',
					'actor_wp_user_id'  => (int) get_current_user_id(),
					'target_type'       => 'user',
					'target_id'         => $tid,
					'reseller_scope_id' => $tid,
				)
			);
		}
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
			if ( class_exists( 'SimpleVPBot_Audit_Log' ) ) {
				SimpleVPBot_Audit_Log::record(
					array(
						'domain'           => 'security',
						'event_type'       => 'dashboard.login_fail',
						'actor_kind'       => 'system',
						'actor_wp_user_id' => 0,
						'payload'          => array( 'login' => sanitize_user( $log, true ) ),
					)
				);
			}
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
	 * Attach direct_users_count from batch map (avoids correlated subquery per row).
	 *
	 * @param array<int, object> $rows Reseller user rows.
	 * @return array<int, object>
	 */
	private static function attach_reseller_direct_user_counts( array $rows ) {
		if ( empty( $rows ) || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return $rows;
		}
		$ids = array();
		foreach ( $rows as $row ) {
			if ( is_object( $row ) && ! empty( $row->id ) ) {
				$ids[] = (int) $row->id;
			}
		}
		$map = SimpleVPBot_Model_User::direct_children_count_map_for_ids( $ids );
		foreach ( $rows as $row ) {
			if ( is_object( $row ) ) {
				$rid                        = (int) ( $row->id ?? 0 );
				$row->direct_users_count    = (int) ( $map[ $rid ] ?? 0 );
			}
		}
		return $rows;
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
	 * WordPress MySQL datetime string → Unix UTC timestamp.
	 *
	 * @param mixed $mysql Datetime column value.
	 * @return int|null
	 */
	private static function mysql_datetime_to_utc_ts( $mysql ) {
		$s = trim( (string) $mysql );
		if ( '' === $s || ! function_exists( 'wp_timezone' ) ) {
			return null;
		}
		try {
			$dt = new DateTimeImmutable( $s, wp_timezone() );
			return $dt->getTimestamp();
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			return null;
		}
	}

	/**
	 * Strip internal user fields for non-site-admin dashboard actors.
	 *
	 * @param array<string, mixed> $row User row array.
	 * @return array<string, mixed>
	 */
	private static function sanitize_user_row_for_dashboard( array $row ) {
		if ( self::dashboard_rest_is_unrestricted_site_admin() ) {
			return $row;
		}
		unset(
			$row['state_data'],
			$row['admin_mode'],
			$row['password_hash'],
			$row['dashboard_password'],
			$row['signup_reseller_svp_id']
		);
		return $row;
	}

	/**
	 * Reseller downline scope ids; always includes the reseller actor (never empty when actor valid).
	 *
	 * @param int        $reseller_svp_user_id Reseller svp_users.id.
	 * @param array|null $raw_scope_ids        Pre-fetched scope ids or null to load from model.
	 * @return array<int, int>
	 */
	private static function effective_reseller_scope_user_ids( $reseller_svp_user_id, $raw_scope_ids = null ) {
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return array();
		}
		return SimpleVPBot_Bot_Reseller_Scope::effective_downline_user_ids( $reseller_svp_user_id, $raw_scope_ids );
	}

	/**
	 * Build a nonce-protected admin image proxy URL for a receipt.
	 *
	 * @param object $receipt Receipt row.
	 * @return string
	 */
	private static function receipt_image_url( $receipt ) {
		$rid = (int) ( $receipt->id ?? 0 );
		if ( $rid < 1 ) {
			return '';
		}
		if ( class_exists( 'SimpleVPBot_Receipt_Image_Store' ) ) {
			if ( ! SimpleVPBot_Receipt_Image_Store::receipt_has_image( $receipt ) ) {
				return '';
			}
		} elseif ( empty( $receipt->tg_file_id ) && empty( $receipt->bale_file_id ) ) {
			return '';
		}
		return add_query_arg(
			array(
				'action' => 'simplevpbot_receipt_image',
				'rid'    => $rid,
				'nonce'  => wp_create_nonce( 'svp_recimg_' . $rid ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * Receipt list filters from dashboard state query params.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return array{join_users:bool,where_sql:string,where_values:array,order_sql:string}
	 */
	private static function receipt_admin_filter_from_request( WP_REST_Request $req ) {
		if ( ! class_exists( 'SimpleVPBot_Model_Receipt' ) ) {
			return array(
				'join_users'   => false,
				'where_sql'    => '',
				'where_values' => array(),
				'order_sql'    => 'r.created_at DESC, r.id DESC',
			);
		}
		return SimpleVPBot_Model_Receipt::admin_list_query_parts(
			array(
				'receipts_q'          => sanitize_text_field( (string) wp_unslash( (string) $req->get_param( 'receipts_q' ) ) ),
				'receipts_status'     => sanitize_key( (string) wp_unslash( (string) $req->get_param( 'receipts_status' ) ) ),
				'receipts_sort'       => sanitize_key( (string) wp_unslash( (string) $req->get_param( 'receipts_sort' ) ) ),
				'receipts_date_from'  => sanitize_text_field( (string) wp_unslash( (string) $req->get_param( 'receipts_date_from' ) ) ),
				'receipts_date_to'    => sanitize_text_field( (string) wp_unslash( (string) $req->get_param( 'receipts_date_to' ) ) ),
				'receipts_amount_min' => sanitize_text_field( (string) wp_unslash( (string) $req->get_param( 'receipts_amount_min' ) ) ),
				'receipts_amount_max' => sanitize_text_field( (string) wp_unslash( (string) $req->get_param( 'receipts_amount_max' ) ) ),
			)
		);
	}

	/**
	 * User list filters/sort from dashboard GET params.
	 *
	 * @param WP_REST_Request $req REST request.
	 * @param bool            $for_pending_list When true, omit status filter (pending query forces status=pending).
	 * @return array{where_sql:string,where_values:array<int|float|string>,order_sql:string,needs_svc_join:bool}
	 */
	/**
	 * Restrict users list to a marketing lifecycle segment (eligible ids).
	 *
	 * @param string               $segment_key Segment key.
	 * @param array<string, mixed> $filter Base filter from admin_list_query_parts.
	 * @param int                  $owner_svp_user_id 0 site, >0 reseller scope.
	 * @return array<string, mixed>
	 */
	private static function users_list_filter_with_marketing_segment( $segment_key, array $filter, $owner_svp_user_id = 0 ) {
		if ( '' === $segment_key || ! class_exists( 'SimpleVPBot_Marketing_Lifecycle_Analytics' ) ) {
			return $filter;
		}
		$fake = SimpleVPBot_Marketing_Lifecycle_Analytics::resolve_rule_for_segment(
			$segment_key,
			(int) $owner_svp_user_id
		);
		$ids = SimpleVPBot_Marketing_Lifecycle_Analytics::eligible_user_ids_for_rule(
			$fake,
			(int) $owner_svp_user_id,
			2000
		);
		if ( empty( $ids ) ) {
			$filter['where_sql'] .= ' AND 1=0';
			return $filter;
		}
		$in = implode( ',', array_map( 'absint', $ids ) );
		$filter['where_sql'] .= " AND u.id IN ({$in})";
		return $filter;
	}

	/**
	 * @param array<int, object> $users_list Raw user rows.
	 * @return array<int, array<string, mixed>>
	 */
	private static function users_list_rows_for_dashboard( array $users_list ) {
		$ids = array();
		foreach ( $users_list as $u ) {
			if ( is_object( $u ) && isset( $u->id ) ) {
				$ids[] = (int) $u->id;
			}
		}
		$flags = class_exists( 'SimpleVPBot_Model_Marketing_Offer' )
			? SimpleVPBot_Model_Marketing_Offer::open_offer_flags_for_users( $ids )
			: array();
		$out = array();
		foreach ( $users_list as $u ) {
			$row = self::row_array( $u );
			if ( ! is_array( $row ) ) {
				continue;
			}
			$row = self::sanitize_user_row_for_dashboard( $row );
			$uid = (int) ( $row['id'] ?? 0 );
			if ( $uid > 0 && ! empty( $flags[ $uid ] ) ) {
				$row['marketing_open_offer'] = true;
			}
			$out[] = $row;
		}
		return $out;
	}

	private static function users_admin_filter_from_request( WP_REST_Request $req, $for_pending_list = false ) {
		if ( ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return array(
				'where_sql'      => '',
				'where_values'   => array(),
				'order_sql'      => 'u.created_at DESC, u.id DESC',
				'needs_svc_join' => false,
			);
		}
		return SimpleVPBot_Model_User::admin_list_query_parts(
			array(
				'users_status'    => sanitize_key( (string) wp_unslash( (string) $req->get_param( 'users_status' ) ) ),
				'users_role'      => sanitize_key( (string) wp_unslash( (string) $req->get_param( 'users_role' ) ) ),
				'users_platform'  => sanitize_key( (string) wp_unslash( (string) $req->get_param( 'users_platform' ) ) ),
				'users_min_svc'   => sanitize_text_field( (string) wp_unslash( (string) $req->get_param( 'users_min_svc' ) ) ),
				'users_max_svc'   => sanitize_text_field( (string) wp_unslash( (string) $req->get_param( 'users_max_svc' ) ) ),
				'users_date_from' => sanitize_text_field( (string) wp_unslash( (string) $req->get_param( 'users_date_from' ) ) ),
				'users_date_to'   => sanitize_text_field( (string) wp_unslash( (string) $req->get_param( 'users_date_to' ) ) ),
				'users_sort'      => sanitize_key( (string) wp_unslash( (string) $req->get_param( 'users_sort' ) ) ),
				'skip_status'     => $for_pending_list,
			)
		);
	}

	/**
	 * Service-count subquery join for user list filters.
	 *
	 * @param string $s_tbl Services table name.
	 * @return string
	 */
	private static function users_svc_join_sql( $s_tbl ) {
		return " LEFT JOIN (SELECT user_id, COUNT(*) AS svc_count FROM {$s_tbl} WHERE deleted_at IS NULL GROUP BY user_id) s ON s.user_id = u.id ";
	}

	/**
	 * @param bool   $join_users Whether user table join is required.
	 * @param string $u_tbl Users table name.
	 * @return string
	 */
	private static function receipt_query_join_sql( $join_users, $u_tbl ) {
		return $join_users ? " LEFT JOIN {$u_tbl} u ON u.id = r.user_id " : '';
	}

	/**
	 * @param wpdb  $wpdb Db.
	 * @param string $rcpt_t Receipts table.
	 * @param string $u_tbl Users table.
	 * @param string $scope_sql Scope fragment (AND r.user_id IN …).
	 * @param array{join_users:bool,where_sql:string,where_values:array,order_sql:string} $filter Filter parts.
	 * @return int
	 */
	private static function receipt_admin_count( $wpdb, $rcpt_t, $u_tbl, $scope_sql, array $filter ) {
		$join = self::receipt_query_join_sql( ! empty( $filter['join_users'] ), $u_tbl );
		$sql  = "SELECT COUNT(*) FROM {$rcpt_t} r{$join} WHERE 1=1{$scope_sql}{$filter['where_sql']}";
		if ( ! empty( $filter['where_values'] ) ) {
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $filter['where_values'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * @param wpdb  $wpdb Db.
	 * @param string $rcpt_t Receipts table.
	 * @param string $u_tbl Users table.
	 * @param string $scope_sql Scope fragment.
	 * @param array{join_users:bool,where_sql:string,where_values:array,order_sql:string} $filter Filter parts.
	 * @param int   $limit Limit.
	 * @param int   $offset Offset.
	 * @return array<int, object>
	 */
	private static function receipt_admin_select( $wpdb, $rcpt_t, $u_tbl, $scope_sql, array $filter, $limit, $offset ) {
		$join = self::receipt_query_join_sql( ! empty( $filter['join_users'] ), $u_tbl );
		$sql  = "SELECT r.* FROM {$rcpt_t} r{$join} WHERE 1=1{$scope_sql}{$filter['where_sql']} ORDER BY {$filter['order_sql']} LIMIT %d OFFSET %d";
		$args = array_merge( (array) $filter['where_values'], array( (int) $limit, (int) $offset ) );
		return $wpdb->get_results( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * @param wpdb  $wpdb Db.
	 * @param string $rcpt_t Receipts table.
	 * @param string $u_tbl Users table.
	 * @param string $scope_sql Scope fragment.
	 * @param array{join_users:bool,where_sql:string,where_values:array,order_sql:string} $filter Filter parts.
	 * @return array<int, array<string, mixed>>
	 */
	private static function receipt_admin_aggregate_rows( $wpdb, $rcpt_t, $u_tbl, $scope_sql, array $filter ) {
		$join = self::receipt_query_join_sql( ! empty( $filter['join_users'] ), $u_tbl );
		$sql  = "SELECT r.status, COUNT(*) AS cnt, COALESCE(SUM(r.amount),0) AS sum_amount FROM {$rcpt_t} r{$join} WHERE 1=1{$scope_sql}{$filter['where_sql']} GROUP BY r.status";
		if ( ! empty( $filter['where_values'] ) ) {
			return $wpdb->get_results( $wpdb->prepare( $sql, $filter['where_values'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		return $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Format receipt row for dashboard review UI.
	 *
	 * @param object $receipt Receipt row.
	 * @return array<string, mixed>|null
	 */
	private static function format_receipt_for_dashboard( $receipt ) {
		$row = self::row_array( $receipt );
		if ( ! is_array( $row ) ) {
			return null;
		}
		$uid = (int) ( $receipt->user_id ?? 0 );
		if ( $uid > 0 && class_exists( 'SimpleVPBot_Model_User' ) ) {
			$user = SimpleVPBot_Model_User::find( $uid );
			if ( $user ) {
				$row['user_label']    = SimpleVPBot_Model_User::label( $user );
				$row['user_name']     = trim( (string) ( $user->first_name ?? '' ) . ' ' . (string) ( $user->last_name ?? '' ) );
				$row['username']      = (string) ( $user->username ?? '' );
				$row['tg_user_id']    = isset( $user->tg_user_id ) ? (int) $user->tg_user_id : 0;
				$row['bale_user_id']  = isset( $user->bale_user_id ) ? (int) $user->bale_user_id : 0;
			}
		}
		$txid = (int) ( $receipt->transaction_id ?? 0 );
		$row['selected_service'] = '';
		if ( $txid > 0 && class_exists( 'SimpleVPBot_Model_Transaction' ) ) {
			$tx = SimpleVPBot_Model_Transaction::find( $txid );
			if ( $tx ) {
				$row['transaction_amount'] = (float) ( $tx->amount ?? 0 );
				$row['transaction_status'] = (string) ( $tx->status ?? '' );
				$row['transaction_type']   = (string) ( $tx->type ?? '' );
				if ( class_exists( 'SimpleVPBot_Bot_Admin_User_Caption' ) ) {
					$row['selected_service'] = SimpleVPBot_Bot_Admin_User_Caption::transaction_selected_service_label( $tx );
				}
			}
		}
		$row['imageUrl']        = self::receipt_image_url( $receipt );
		$row['hasReceiptImage'] = '' !== (string) $row['imageUrl'];
		$created_ts = self::mysql_datetime_to_utc_ts( $receipt->created_at ?? '' );
		if ( null !== $created_ts ) {
			$row['created_at_ts'] = $created_ts;
		}
		$decided_ts = self::mysql_datetime_to_utc_ts( $receipt->decided_at ?? '' );
		if ( null !== $decided_ts ) {
			$row['decided_at_ts'] = $decided_ts;
		}
		return $row;
	}

	/**
	 * Strip panel secrets from dashboard payloads; expose auth metadata for UI.
	 *
	 * @param array<string, mixed> $ra Panel row.
	 * @return array<string, mixed>
	 */
	/**
	 * Add panel_access / can_sell_plan flags for reseller dashboard plan UI.
	 *
	 * @param array<int, array<string, mixed>> $panels         Panel rows.
	 * @param int                              $actor_uid      Reseller svp_users.id.
	 * @return array<int, array<string, mixed>>
	 */
	private static function annotate_reseller_panels_for_dashboard( array $panels, $actor_uid, array $panel_price_by_panel = array() ) {
		$actor_uid = (int) $actor_uid;
		if ( $actor_uid < 1 ) {
			return $panels;
		}
		$out = array();
		foreach ( $panels as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$pid  = (int) ( $row['id'] ?? 0 );
			$pacc = 0;
			$can_sell = false;
			if ( $pid > 0 ) {
				$rp = isset( $panel_price_by_panel[ $pid ] ) ? $panel_price_by_panel[ $pid ] : null;
				if ( $rp ) {
					$pacc = (int) ( is_object( $rp ) ? ( $rp->panel_access ?? 0 ) : ( $rp['panel_access'] ?? 0 ) );
					$price = (float) ( is_object( $rp ) ? ( $rp->price_per_gb ?? 0 ) : ( $rp['price_per_gb'] ?? 0 ) );
					$can_sell = $pacc > 0 || $price > 0;
				}
			}
			$row['panel_access']  = $pacc;
			if ( $can_sell ) {
				$row['can_sell_plan'] = true;
			} else {
				$row['can_sell_plan'] = class_exists( 'SimpleVPBot_Bot_Reseller_Scope' )
					? SimpleVPBot_Bot_Reseller_Scope::reseller_can_sell_on_panel_for( $actor_uid, $pid )
					: false;
			}
			$out[]                = $row;
		}
		return $out;
	}

	/**
	 * Build dashboard panel payload from a panel model row.
	 *
	 * @param object $pobj Panel row.
	 * @return array<string, mixed>|null
	 */
	private static function panel_object_to_dashboard_payload( $pobj ) {
		if ( ! $pobj || ! is_object( $pobj ) ) {
			return null;
		}
		$pid = (int) ( $pobj->id ?? 0 );
		if ( $pid < 1 ) {
			return null;
		}
		$_lbl = (string) ( $pobj->label ?? '' );
		return self::format_panel_for_dashboard(
			array(
				'id'                        => $pid,
				'label'                     => $_lbl,
				'name'                      => $_lbl,
				'panel_url'                 => (string) ( $pobj->panel_url ?? '' ),
				'panel_username'            => (string) ( $pobj->panel_username ?? '' ),
				'panel_password'            => (string) ( $pobj->panel_password ?? '' ),
				'panel_api_base'            => (string) ( $pobj->panel_api_base ?? 'panel/api' ),
				'panel_login_secret'        => (string) ( $pobj->panel_login_secret ?? '' ),
				'panel_api_token'           => (string) ( $pobj->panel_api_token ?? '' ),
				'subscription_public_base' => (string) ( $pobj->subscription_public_base ?? '' ),
				'panel_api_flavor'          => class_exists( 'SimpleVPBot_Model_Panel' )
					? SimpleVPBot_Model_Panel::api_flavor( $pobj )
					: 'unknown',
				'sort_order'                => (int) ( $pobj->sort_order ?? 0 ),
				'active'                    => (int) ( $pobj->active ?? 1 ),
			)
		);
	}

	/**
	 * Reseller customer charges with billing_reseller filter and pagination.
	 *
	 * @param int                  $actor_uid       Reseller id.
	 * @param array<int>           $scope_user_ids  Downline ids.
	 * @param WP_REST_Request|null $req             Request (pagination params).
	 * @return array{rows:array<int,array<string,mixed>>,pagination:array<string,int>|null}
	 */
	private static function build_reseller_customer_charges( $actor_uid, array $scope_user_ids, $req = null ) {
		global $wpdb;
		$empty = array(
			'rows'       => array(),
			'pagination' => null,
		);
		$actor_uid = (int) $actor_uid;
		if ( $actor_uid < 1 || empty( $scope_user_ids ) || ! class_exists( 'SimpleVPBot_Model_Transaction' ) || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return $empty;
		}
		$in_list = implode( ',', array_map( 'absint', $scope_user_ids ) );
		if ( '' === $in_list ) {
			return $empty;
		}
		$page = 1;
		$per  = 50;
		if ( $req instanceof WP_REST_Request ) {
			$page = max( 1, (int) $req->get_param( 'customerChargesPage' ) );
			$per  = max( 10, min( 120, (int) $req->get_param( 'customerChargesPerPage' ) ?: 50 ) );
		}
		$type_filter = '';
		$date_from   = '';
		$date_to     = '';
		if ( $req instanceof WP_REST_Request ) {
			$type_filter = sanitize_key( (string) $req->get_param( 'customerChargesType' ) );
			$date_from   = sanitize_text_field( (string) wp_unslash( (string) $req->get_param( 'customerChargesDateFrom' ) ) );
			$date_to     = sanitize_text_field( (string) wp_unslash( (string) $req->get_param( 'customerChargesDateTo' ) ) );
		}
		if ( ! in_array( $type_filter, array( 'purchase', 'renew', 'volume', 'topup' ), true ) ) {
			$type_filter = '';
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
			$date_from = '';
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
			$date_to = '';
		}
		$offset = ( $page - 1 ) * $per;
		$tx_t   = SimpleVPBot_Model_Transaction::table();
		$billing_expr = SimpleVPBot_Model_Transaction::billing_reseller_id_sql_expr( 't' );
		$type_sql = '' !== $type_filter ? ' AND t.type = %s' : '';
		$date_sql = '';
		$date_args = array();
		if ( '' !== $date_from ) {
			$date_sql   .= ' AND DATE(t.created_at) >= %s';
			$date_args[] = $date_from;
		}
		if ( '' !== $date_to ) {
			$date_sql   .= ' AND DATE(t.created_at) <= %s';
			$date_args[] = $date_to;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_sql = "SELECT COUNT(*) FROM {$tx_t} t
			WHERE t.user_id IN ({$in_list}) AND t.status = 'approved'
			AND {$billing_expr} = %d{$type_sql}{$date_sql}";
		$count_args = array_merge( array( $actor_uid ), $date_args );
		if ( '' !== $type_filter ) {
			$count_args[] = $type_filter;
		}
		$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $count_args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$list_sql = "SELECT t.* FROM {$tx_t} t
			WHERE t.user_id IN ({$in_list}) AND t.status = 'approved'
			AND {$billing_expr} = %d{$type_sql}{$date_sql}
			ORDER BY t.id DESC LIMIT %d OFFSET %d";
		$list_args = array_merge( $count_args, array( $per, $offset ) );
		$tx_rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$user_ids = array();
		foreach ( (array) $tx_rows as $txrow ) {
			if ( $txrow && is_object( $txrow ) ) {
				$user_ids[] = (int) ( $txrow->user_id ?? 0 );
			}
		}
		$labels = SimpleVPBot_Model_User::labels_by_ids( $user_ids );
		$plan_ids = array();
		foreach ( (array) $tx_rows as $txrow ) {
			if ( ! $txrow || ! is_object( $txrow ) ) {
				continue;
			}
			$meta = json_decode( (string) ( $txrow->meta_json ?? '{}' ), true );
			if ( is_array( $meta ) && ! empty( $meta['plan_id'] ) ) {
				$plan_ids[] = (int) $meta['plan_id'];
			}
		}
		$plan_labels = array();
		if ( ! empty( $plan_ids ) && class_exists( 'SimpleVPBot_Model_Plan' ) ) {
			$plan_labels = SimpleVPBot_Model_Plan::labels_by_ids( array_unique( $plan_ids ) );
		}
		$rows   = array();
		foreach ( (array) $tx_rows as $txrow ) {
			if ( ! $txrow || ! is_object( $txrow ) ) {
				continue;
			}
			$cid = (int) ( $txrow->user_id ?? 0 );
			$ra  = self::row_array( $txrow );
			if ( ! is_array( $ra ) ) {
				continue;
			}
			$meta = json_decode( (string) ( $txrow->meta_json ?? '{}' ), true );
			$meta = is_array( $meta ) ? $meta : array();
			$charge_type = sanitize_key( (string) ( $txrow->type ?? $meta['kind'] ?? 'purchase' ) );
			if ( ! in_array( $charge_type, array( 'purchase', 'renew', 'volume', 'topup' ), true ) ) {
				$charge_type = 'purchase';
			}
			$pid = (int) ( $meta['plan_id'] ?? 0 );
			$ra['customer_label']       = isset( $labels[ $cid ] ) ? (string) $labels[ $cid ] : ( '#' . $cid );
			$ra['customer_svp_user_id'] = $cid;
			$ra['charge_type']          = $charge_type;
			$ra['charge_created_at']    = (string) ( $txrow->created_at ?? $ra['created_at'] ?? '' );
			$ra['charge_plan_label']    = $pid > 0 && isset( $plan_labels[ $pid ] ) ? (string) $plan_labels[ $pid ] : '';
			$ra['charge_service_id']    = (int) ( $txrow->service_id ?? $meta['service_id'] ?? 0 );
			$rows[]                     = $ra;
		}
		return array(
			'rows'       => $rows,
			'pagination' => array(
				'page'    => $page,
				'perPage' => $per,
				'total'   => $total,
			),
		);
	}

	private static function format_panel_for_dashboard( array $ra ) {
		$has_token = '' !== trim( (string) ( $ra['panel_api_token'] ?? '' ) );
		$has_pass  = '' !== trim( (string) ( $ra['panel_password'] ?? '' ) );
		$has_user  = '' !== trim( (string) ( $ra['panel_username'] ?? '' ) );
		$has_sec   = '' !== trim( (string) ( $ra['panel_login_secret'] ?? '' ) );

		if ( $has_token ) {
			$auth_mode = 'bearer';
		} elseif ( $has_user && $has_pass ) {
			$auth_mode = 'cookie';
		} else {
			$auth_mode = 'incomplete';
		}

		unset( $ra['panel_password'], $ra['panel_api_token'], $ra['panel_login_secret'] );

		$ra['has_password']     = $has_pass;
		$ra['has_api_token']    = $has_token;
		$ra['has_login_secret'] = $has_sec;
		$ra['auth_mode']        = $auth_mode;

		if ( empty( $ra['panel_api_base'] ) ) {
			$ra['panel_api_base'] = 'panel/api';
		}

		return $ra;
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
		$norm = class_exists( 'SimpleVPBot_Xui_Client' )
			? SimpleVPBot_Xui_Client::normalize_panel_url( $url )
			: untrailingslashit( $url );
		if ( '' === $norm ) {
			$norm = untrailingslashit( $url );
		}
		$probe_urls = array(
			trailingslashit( $norm ),
			untrailingslashit( $norm ) . '/csrf-token',
		);
		$headers = array(
			'Accept'     => 'application/json, text/html, */*',
			'User-Agent' => 'SimpleVPBot/1.0 (panel-health)',
		);
		$t0                  = microtime( true );
		$last_code           = 0;
		$network_reachable   = false;
		$http_ok             = false;
		$auth_probe_url      = '';
		$auth_probe_status   = 0;
		$last_error          = '';
		foreach ( $probe_urls as $probe_url ) {
			$res = wp_remote_get(
				$probe_url,
				array(
					'timeout'     => 4,
					'redirection' => 3,
					'sslverify'   => true,
					'headers'     => $headers,
				)
			);
			$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
			if ( is_wp_error( $res ) ) {
				$last_error = $res->get_error_message();
				continue;
			}
			$last_code = $code;
			if ( $code >= 100 && $code <= 599 ) {
				$network_reachable = true;
			}
			if ( 200 === $code || 302 === $code || 301 === $code || ( $code >= 200 && $code < 400 ) ) {
				$http_ok           = true;
				$auth_probe_url    = $probe_url;
				$auth_probe_status = $code;
				break;
			}
		}
		$lat = (int) round( ( microtime( true ) - $t0 ) * 1000 );
		if ( ! $network_reachable && '' !== $last_error ) {
			$out = array(
				'panelId'            => $panel_id,
				'ok'                 => false,
				'httpOk'             => false,
				'networkReachable'   => false,
				'httpStatus'         => 0,
				'latencyMs'          => $lat,
				'checkedAt'          => gmdate( 'c' ),
				'error'              => $last_error,
			);
		} else {
			$out = array(
				'panelId'            => $panel_id,
				'ok'                 => $http_ok,
				'httpOk'             => $http_ok,
				'networkReachable'   => $network_reachable,
				'httpStatus'         => $last_code,
				'latencyMs'          => $lat,
				'checkedAt'          => gmdate( 'c' ),
				'authProbeUrl'       => $auth_probe_url,
				'authProbeStatus'    => $auth_probe_status,
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
	private static function reseller_dashboard_allowed_tabs_map( $actor_uid, array $perms = null ) {
		if ( class_exists( 'SimpleVPBot_Reseller_Permission_Gate' ) ) {
			return SimpleVPBot_Reseller_Permission_Gate::reseller_allowed_tabs_map( (int) $actor_uid, $perms );
		}
		$actor_uid = (int) $actor_uid;
		if ( null === $perms ) {
			$perms = $actor_uid > 0 ? SimpleVPBot_Model_User::reseller_permissions( $actor_uid ) : SimpleVPBot_Model_User::default_reseller_permissions();
		}
		$admin_only = array(
			'site_settings',
			'backup',
			'notifications',
			'logs',
			'xui_panels',
			'configs',
			'l2tp_servers',
			'texts',
			'bots',
			'unit_economics',
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
			'receipts'        => 'receipts.review',
			'reseller_charge' => 'plans.manage',
			'monitoring'      => 'services.manage',
			'referral'          => 'users.manage',
			'referral_reports'      => 'users.manage',
			'reseller_reports'      => 'users.manage',
			'marketing_lifecycle'   => 'marketing.lifecycle',
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
			'reseller_charge',
			'referral',
			'referral_reports',
			'reseller_reports',
			'marketing_lifecycle',
			'discounts',
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
			'reseller_settings',
		);
		$out = array();
		foreach ( $all_tabs as $tab ) {
			if ( in_array( $tab, $admin_only, true ) ) {
				$out[ $tab ] = false;
				continue;
			}
			if ( 'reseller_settings' === $tab ) {
				$out[ $tab ] = true;
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
	 * Whether a reseller dashboard actor may request admin/state for this tab key.
	 *
	 * @param int    $actor_uid   Reseller svp_users.id.
	 * @param string $active_tab  Sanitized tab key (empty allowed).
	 * @return bool
	 */
	private static function reseller_may_request_admin_tab( $actor_uid, $active_tab ) {
		$tab = sanitize_key( (string) $active_tab );
		if ( '' === $tab ) {
			return true;
		}
		$allowed = self::reseller_dashboard_allowed_tabs_map( (int) $actor_uid );
		return isset( $allowed[ $tab ] ) && true === $allowed[ $tab ];
	}

	/**
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function route_admin_state( WP_REST_Request $req ) {
		global $wpdb;
		$active_tab           = sanitize_key( (string) $req->get_param( 'activeTab' ) );
		$include_plans_detail = ( '1' === (string) $req->get_param( 'includePlansForUserDetail' ) );
		$dash_users_tab_light = ( 'users' === $active_tab );

		$p_panels = self::dash_list_pagination( $req, 'panels', 20 );
		$p_plans  = self::dash_list_pagination( $req, 'plans', 40 );
		$p_pc     = self::dash_list_pagination( $req, 'planCategories', 40 );
		$p_cards  = self::dash_list_pagination( $req, 'cards', 120 );
		$p_l2tp   = self::dash_list_pagination( $req, 'l2tp', 20 );
		$p_disc   = self::dash_list_pagination( $req, 'discounts', 120 );
		$p_users  = self::dash_list_pagination( $req, 'users', 50 );
		$p_pend   = self::dash_list_pagination( $req, 'pendingUsers', 30 );
		$p_res    = self::dash_list_pagination( $req, 'resellers', 30 );
		$p_rcpt   = self::dash_list_pagination( $req, 'receipts', 40 );
		$p_bc     = self::dash_list_pagination( $req, 'broadcasts', 20 );
		$p_ref_ev = self::dash_list_pagination( $req, 'referralEvents', 20 );
		$p_rep    = self::dash_list_pagination( $req, 'resellerReports', 25 );
		$p_mkt    = self::dash_list_pagination( $req, 'marketingOffers', 25 );
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
		$rcpt_filter = self::receipt_admin_filter_from_request( $req );

		$users_q = trim( sanitize_text_field( (string) wp_unslash( (string) $req->get_param( 'users_q' ) ) ) );
		if ( strlen( $users_q ) > 128 ) {
			$users_q = substr( $users_q, 0, 128 );
		}
		$resellers_q = trim( sanitize_text_field( (string) wp_unslash( (string) $req->get_param( 'resellers_q' ) ) ) );
		if ( strlen( $resellers_q ) > 128 ) {
			$resellers_q = substr( $resellers_q, 0, 128 );
		}
		$user_filter = SimpleVPBot_Model_User::admin_search_users_clause( $users_q );
		$resellers_user_filter = SimpleVPBot_Model_User::admin_search_users_clause( $resellers_q );
		$users_list_filter = self::users_admin_filter_from_request( $req );
		$users_pend_filter = self::users_admin_filter_from_request( $req, true );
		$users_segment_key = class_exists( 'SimpleVPBot_Model_Marketing_Rule' )
			? SimpleVPBot_Model_Marketing_Rule::sanitize_segment( (string) wp_unslash( (string) $req->get_param( 'users_segment' ) ) )
			: '';
		$users_svc_join      = self::users_svc_join_sql( $s_tbl );
		$resellers_status = sanitize_key( (string) wp_unslash( (string) $req->get_param( 'resellers_status' ) ) );
		$reseller_status_sql   = '';
		$reseller_status_vals  = array();
		if ( in_array( $resellers_status, array( 'pending', 'approved', 'rejected', 'blocked' ), true ) ) {
			$reseller_status_sql  = ' AND u.status = %s';
			$reseller_status_vals = array( $resellers_status );
		}
		$ctx           = self::dashboard_actor_context();
		$is_reseller   = ! empty( $ctx['isReseller'] );
		$actor_uid     = (int) ( $ctx['actorUserId'] ?? 0 );
		if ( $is_reseller && $actor_uid > 0 && ! self::reseller_may_request_admin_tab( $actor_uid, $active_tab ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden_tab' ), 403 );
		}
		$reseller_mode = $is_reseller;
		$owner_ctx     = (int) $req->get_param( 'resellerContextId' );
		$scope_user_ids = array();
		if ( $reseller_mode ) {
			$owner_ctx      = $actor_uid;
			$scope_user_ids = self::effective_reseller_scope_user_ids( $actor_uid );
		} elseif ( $owner_ctx > 0 ) {
			$validated = self::validate_reseller_context_id( $owner_ctx );
			if ( null === $validated ) {
				return new WP_REST_Response( array( 'ok' => false, 'message' => 'invalid_reseller_context' ), 400 );
			}
			$owner_ctx      = $validated;
			$scope_user_ids = self::effective_reseller_scope_user_ids( $owner_ctx );
		}
		$moderatable_user_ids = $scope_user_ids;
		if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			if ( $reseller_mode && $actor_uid > 0 ) {
				$moderatable_user_ids = SimpleVPBot_Bot_Reseller_Scope::effective_moderatable_user_ids( $actor_uid, $scope_user_ids );
			} elseif ( $owner_ctx > 0 ) {
				$moderatable_user_ids = SimpleVPBot_Bot_Reseller_Scope::effective_moderatable_user_ids( $owner_ctx, $scope_user_ids );
			}
		}
		$needs_reseller_plan_floors       = $reseller_mode && in_array( $active_tab, array( 'plans', 'reseller_panels' ), true );
		$needs_reseller_customer_charges  = $reseller_mode && 'reseller_charge' === $active_tab;
		$needs_reseller_wholesale_ladders = $needs_reseller_plan_floors;
		$needs_reseller_panel_prices      = $reseller_mode && $actor_uid > 0 && (
			$needs_reseller_plan_floors
			|| in_array( $active_tab, array( 'dashboard', 'monitoring', 'xui_panels', 'reseller_panels' ), true )
		);
		$needs_resellers_list_data        = ! $reseller_mode && in_array( $active_tab, array( 'resellers', 'reseller_xui_panels' ), true );
		$needs_resellers_tab_data         = $needs_resellers_list_data;
		$needs_resellers_preview          = ! $reseller_mode && 'dashboard' === $active_tab;
		$needs_child_reseller_maps        = $reseller_mode && 'resellers' === $active_tab && $actor_uid > 0;
		$needs_panel_health               = in_array( $active_tab, array( 'dashboard', 'monitoring', 'xui_panels' ), true );
		$owner_scoped_catalog = $owner_ctx > 0;
		if ( '' !== $users_segment_key ) {
			$seg_owner = $reseller_mode ? $actor_uid : $owner_ctx;
			$users_list_filter = self::users_list_filter_with_marketing_segment(
				$users_segment_key,
				$users_list_filter,
				$seg_owner
			);
		}

		$rcpt_scope_sql   = '';
		$rcpt_scope_empty = false;
		if ( $reseller_mode && $actor_uid > 0 ) {
			$rcpt_scope_ids = array_values(
				array_unique(
					array_map(
						'intval',
						(array) $moderatable_user_ids
					)
				)
			);
			$rcpt_scope_ids = array_values(
				array_filter(
					$rcpt_scope_ids,
					static function ( $v ) {
						return (int) $v > 0;
					}
				)
			);
			if ( empty( $rcpt_scope_ids ) ) {
				$rcpt_scope_empty = true;
			} else {
				$rcpt_scope_sql = ' AND r.user_id IN (' . implode( ',', array_map( 'absint', $rcpt_scope_ids ) ) . ')';
			}
		} elseif ( $owner_ctx > 0 && ! empty( $moderatable_user_ids ) ) {
			$rcpt_scope_sql = ' AND r.user_id IN (' . implode( ',', array_map( 'absint', $moderatable_user_ids ) ) . ')';
		} elseif ( $owner_ctx > 0 ) {
			$rcpt_scope_empty = true;
		}

		$reseller_actor_needs_panels = $reseller_mode && $actor_uid > 0;

		$reseller_allowed_panel_ids = array();
		if ( $reseller_actor_needs_panels && class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			$reseller_allowed_panel_ids = SimpleVPBot_Bot_Reseller_Scope::allowed_panel_ids_for( $actor_uid );
		}
		if ( $reseller_mode ) {
			$settings = SimpleVPBot_Settings::dashboard_slice_for_reseller_operator();
		} else {
			$settings = SimpleVPBot_Settings::settings_for_dashboard_admin();
		}
		$l2tp_enabled = class_exists( 'SimpleVPBot_Feature_L2tp' ) && SimpleVPBot_Feature_L2tp::enabled();
		if ( is_array( $settings ) ) {
			$settings['features'] = array(
				'l2tp' => $l2tp_enabled,
			);
		}

		$users_from_reseller_scope = false;
		$users_list                = array();
		$pending_users             = array();
		$resellers                 = array();
		if ( $reseller_mode ) {
			$scope = SimpleVPBot_Model_User::reseller_moderation_scope_clause( $actor_uid, 'u' );
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
			if ( '' !== $users_list_filter['where_sql'] ) {
				$where_sql .= $users_list_filter['where_sql'];
				if ( ! empty( $users_list_filter['where_values'] ) ) {
					$where_values = array_merge( $where_values, $users_list_filter['where_values'] );
				}
			}
			$list_cnt_join = $users_list_filter['needs_svc_join'] ? $users_svc_join : '';
			$tot_users_list = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$u_tbl} u{$list_cnt_join}{$where_sql}",
					$where_values
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$pend_where_sql    = ' WHERE 1=1' . $scope['sql'];
			$pend_where_values = $scope['values'];
			if ( $user_filter ) {
				$pend_where_sql .= $user_filter['sql'];
				if ( ! empty( $user_filter['values'] ) ) {
					$pend_where_values = array_merge( $pend_where_values, $user_filter['values'] );
				}
			}
			if ( '' !== $users_pend_filter['where_sql'] ) {
				$pend_where_sql .= $users_pend_filter['where_sql'];
				if ( ! empty( $users_pend_filter['where_values'] ) ) {
					$pend_where_values = array_merge( $pend_where_values, $users_pend_filter['where_values'] );
				}
			}
			$pend_cnt_join = $users_pend_filter['needs_svc_join'] ? $users_svc_join : '';
			$tot_pend_list = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$u_tbl} u{$pend_cnt_join}{$pend_where_sql} AND u.status = %s",
					array_merge( $pend_where_values, array( 'pending' ) )
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$resellers_direct_children_only = 'resellers' === $active_tab;
			$resellers_where_sql            = $where_sql;
			$resellers_where_values         = $where_values;
			if ( $resellers_direct_children_only ) {
				$resellers_where_sql    = ' WHERE u.invited_by = %d';
				$resellers_where_values = array( $actor_uid );
				if ( $user_filter ) {
					$resellers_where_sql .= $user_filter['sql'];
					if ( ! empty( $user_filter['values'] ) ) {
						$resellers_where_values = array_merge( $resellers_where_values, $user_filter['values'] );
					}
				}
			}
			$tot_res_list = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$u_tbl} u {$resellers_where_sql} AND u.role = %s{$reseller_status_sql}",
					array_merge( $resellers_where_values, array( 'reseller' ), $reseller_status_vals )
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$users_list = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT u.*, COALESCE(s.svc_count, 0) AS svc_count
					FROM {$u_tbl} u
					{$users_svc_join}
					{$where_sql}
					ORDER BY {$users_list_filter['order_sql']} LIMIT %d OFFSET %d",
					array_merge( $where_values, array( $p_users['per_page'], $p_users['offset'] ) )
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$pending_users = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT u.* FROM {$u_tbl} u{$pend_cnt_join}{$pend_where_sql} AND u.status = %s ORDER BY {$users_pend_filter['order_sql']} LIMIT %d OFFSET %d",
					array_merge( $pend_where_values, array( 'pending', $p_pend['per_page'], $p_pend['offset'] ) )
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$resellers = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT u.*, COALESCE(s.svc_count, 0) AS svc_count
					FROM {$u_tbl} u
					{$users_svc_join}
					{$resellers_where_sql} AND u.role = %s{$reseller_status_sql}
					ORDER BY {$users_list_filter['order_sql']} LIMIT %d OFFSET %d",
					array_merge( $resellers_where_values, array( 'reseller' ), $reseller_status_vals, array( $p_res['per_page'], $p_res['offset'] ) )
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$resellers = self::attach_reseller_direct_user_counts( (array) $resellers );

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
			if ( $owner_scoped_catalog ) {
				$tot_cards = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t_cards} WHERE owner_svp_user_id = %d", $owner_ctx ) ); // phpcs:ignore
			} else {
				$tot_cards = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_cards}" ); // phpcs:ignore
			}
			if ( $reseller_mode || ! $l2tp_enabled ) {
				$tot_l2tp       = 0;
				$texts_prebuilt = $reseller_mode ? array() : ( class_exists( 'SimpleVPBot_Model_Text' ) ? SimpleVPBot_Model_Text::all_grouped_for_dashboard() : array() );
			} else {
				$tot_l2tp       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_l2tp}" ); // phpcs:ignore
				$texts_prebuilt = class_exists( 'SimpleVPBot_Model_Text' ) ? SimpleVPBot_Model_Text::all_grouped_for_dashboard() : array();
			}
			$tot_texts      = count( $texts_prebuilt );
			$p_texts        = array(
				'page'     => 1,
				'per_page' => max( 1, $tot_texts ),
				'offset'   => 0,
			);
			if ( $owner_scoped_catalog ) {
				$tot_disc = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t_disc} WHERE owner_svp_user_id = %d", $owner_ctx ) ); // phpcs:ignore
			} else {
				$tot_disc = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_disc}" ); // phpcs:ignore
			}
			if ( $rcpt_scope_empty ) {
				$tot_rcpt = 0;
			} else {
				$tot_rcpt = self::receipt_admin_count( $wpdb, $rcpt_t, $u_tbl, $rcpt_scope_sql, $rcpt_filter );
			}
			if ( $owner_scoped_catalog ) {
				$tot_bc = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$bc_t} WHERE owner_svp_user_id = %d", $owner_ctx ) ); // phpcs:ignore
			} else {
				$tot_bc = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bc_t}" ); // phpcs:ignore
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
			$owner_users_scope = ( $owner_ctx > 0 && ! empty( $moderatable_user_ids ) )
				? self::users_moderatable_scope_clause( $moderatable_user_ids, 'u' )
				: null;
			$tot_users     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$u_tbl}" ); // phpcs:ignore
			$tot_pend      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$u_tbl} WHERE status = %s", 'pending' ) ); // phpcs:ignore
			$cnt_res_sql = "SELECT COUNT(*) FROM {$u_tbl} u WHERE u.role = 'reseller'{$reseller_status_sql}";
			if ( $resellers_user_filter ) {
				$cnt_res_sql .= $resellers_user_filter['sql'];
			}
			$cnt_res_vals = array_merge( $reseller_status_vals, $resellers_user_filter ? $resellers_user_filter['values'] : array() );
			if ( $owner_users_scope ) {
				$cnt_res_sql .= $owner_users_scope['sql'];
				$cnt_res_vals = array_merge( $cnt_res_vals, $owner_users_scope['values'] );
			}
			if ( ! empty( $cnt_res_vals ) ) {
				$tot_resellers = (int) $wpdb->get_var( $wpdb->prepare( $cnt_res_sql, $cnt_res_vals ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			} else {
				$tot_resellers = (int) $wpdb->get_var( $cnt_res_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}

			$has_users_list_filter = $user_filter || '' !== $users_list_filter['where_sql'];
			$has_users_pend_filter = $user_filter || '' !== $users_pend_filter['where_sql'];
			if ( $has_users_list_filter ) {
				$list_cnt_join = $users_list_filter['needs_svc_join'] ? $users_svc_join : '';
				$cnt_users_sql = "SELECT COUNT(*) FROM {$u_tbl} u{$list_cnt_join} WHERE 1=1";
				$cnt_users_vals = array();
				if ( $user_filter ) {
					$cnt_users_sql .= $user_filter['sql'];
					$cnt_users_vals = array_merge( $cnt_users_vals, $user_filter['values'] );
				}
				$cnt_users_sql .= $users_list_filter['where_sql'];
				$cnt_users_vals = array_merge( $cnt_users_vals, $users_list_filter['where_values'] );
				if ( $owner_users_scope ) {
					$cnt_users_sql .= $owner_users_scope['sql'];
					$cnt_users_vals = array_merge( $cnt_users_vals, $owner_users_scope['values'] );
				}
				if ( ! empty( $cnt_users_vals ) ) {
					$tot_users_list = (int) $wpdb->get_var( $wpdb->prepare( $cnt_users_sql, $cnt_users_vals ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				} else {
					$tot_users_list = (int) $wpdb->get_var( $cnt_users_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				}
			} else {
				$tot_users_list = $tot_users;
			}
			if ( $has_users_pend_filter ) {
				$pend_cnt_join = $users_pend_filter['needs_svc_join'] ? $users_svc_join : '';
				$cnt_pend_sql  = "SELECT COUNT(*) FROM {$u_tbl} u{$pend_cnt_join} WHERE u.status = 'pending'";
				$cnt_pend_vals = array();
				if ( $user_filter ) {
					$cnt_pend_sql .= $user_filter['sql'];
					$cnt_pend_vals = array_merge( $cnt_pend_vals, $user_filter['values'] );
				}
				$cnt_pend_sql .= $users_pend_filter['where_sql'];
				$cnt_pend_vals = array_merge( $cnt_pend_vals, $users_pend_filter['where_values'] );
				if ( $owner_users_scope ) {
					$cnt_pend_sql .= $owner_users_scope['sql'];
					$cnt_pend_vals = array_merge( $cnt_pend_vals, $owner_users_scope['values'] );
				}
				if ( ! empty( $cnt_pend_vals ) ) {
					$tot_pend_list = (int) $wpdb->get_var( $wpdb->prepare( $cnt_pend_sql, $cnt_pend_vals ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				} else {
					$tot_pend_list = (int) $wpdb->get_var( $cnt_pend_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				}
			} else {
				$tot_pend_list = $tot_pend;
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
			if ( $include_plans_detail ) {
				$p_plans_detail = array(
					'page'     => 1,
					'per_page' => 500,
					'offset'   => 0,
				);
				if ( $owner_ctx > 0 ) {
					$tot_plans = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t_plans} WHERE owner_svp_user_id = %d", $owner_ctx ) ); // phpcs:ignore
					$plans_raw = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT * FROM {$t_plans} WHERE owner_svp_user_id = %d ORDER BY sort_order ASC, id ASC LIMIT %d OFFSET %d",
							$owner_ctx,
							$p_plans_detail['per_page'],
							$p_plans_detail['offset']
						)
					);
				} else {
					$tot_plans = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_plans}" ); // phpcs:ignore
					$plans_raw = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT * FROM {$t_plans} ORDER BY sort_order ASC, id ASC LIMIT %d OFFSET %d",
							$p_plans_detail['per_page'],
							$p_plans_detail['offset']
						)
					);
				}
				$p_pc_detail = array(
					'per_page' => 500,
					'offset'   => 0,
				);
				if ( $reseller_mode ) {
					if ( ! empty( $reseller_allowed_panel_ids ) ) {
						$pc_in_ph = implode( ',', array_fill( 0, count( $reseller_allowed_panel_ids ), '%d' ) );
						$plan_cats_raw = $wpdb->get_results(
							$wpdb->prepare(
								"SELECT * FROM {$t_pc} WHERE panel_id IN ({$pc_in_ph}) ORDER BY panel_id ASC, sort_order ASC, id ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
								array_merge( $reseller_allowed_panel_ids, array( $p_pc_detail['per_page'], $p_pc_detail['offset'] ) )
							)
						);
					}
				} else {
					$plan_cats_raw = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT * FROM {$t_pc} ORDER BY panel_id ASC, sort_order ASC, id ASC LIMIT %d OFFSET %d",
							$p_pc_detail['per_page'],
							$p_pc_detail['offset']
						)
					);
				}
			}
		} else {
			if ( $reseller_mode ) {
				if ( $needs_child_reseller_maps && ! empty( $reseller_allowed_panel_ids ) ) {
					$pp_ph      = implode( ',', array_fill( 0, count( $reseller_allowed_panel_ids ), '%d' ) );
					$panels_raw = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT * FROM {$t_panels} WHERE id IN ({$pp_ph}) ORDER BY sort_order ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
							$reseller_allowed_panel_ids
						)
					);
				} else {
					$panels_raw = array();
				}
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
		if ( $owner_scoped_catalog ) {
			$cards_raw = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$t_cards} WHERE owner_svp_user_id = %d ORDER BY priority DESC, id ASC LIMIT %d OFFSET %d",
					$owner_ctx,
					$p_cards['per_page'],
					$p_cards['offset']
				)
			);
		} else {
			$cards_raw = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$t_cards} ORDER BY priority DESC, id ASC LIMIT %d OFFSET %d",
					$p_cards['per_page'],
					$p_cards['offset']
				)
			);
		}
		if ( $reseller_mode || ! $l2tp_enabled ) {
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
		if ( $owner_scoped_catalog ) {
			$discounts_raw = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$t_disc} WHERE owner_svp_user_id = %d ORDER BY active DESC, id DESC LIMIT %d OFFSET %d",
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
			$owner_users_scope = ( $owner_ctx > 0 && ! empty( $moderatable_user_ids ) )
				? self::users_moderatable_scope_clause( $moderatable_user_ids, 'u' )
				: null;
			$pend_cnt_join = $users_pend_filter['needs_svc_join'] ? $users_svc_join : '';
			$pend_sql      = "SELECT u.* FROM {$u_tbl} u{$pend_cnt_join} WHERE u.status = 'pending'";
			$pend_vals     = array();
			if ( $user_filter ) {
				$pend_sql .= $user_filter['sql'];
				$pend_vals = array_merge( $pend_vals, $user_filter['values'] );
			}
			$pend_sql .= $users_pend_filter['where_sql'];
			$pend_vals = array_merge( $pend_vals, $users_pend_filter['where_values'] );
			if ( $owner_users_scope ) {
				$pend_sql .= $owner_users_scope['sql'];
				$pend_vals = array_merge( $pend_vals, $owner_users_scope['values'] );
			}
			$pend_sql .= " ORDER BY {$users_pend_filter['order_sql']} LIMIT %d OFFSET %d";
			if ( ! empty( $pend_vals ) ) {
				$pending_users = $wpdb->get_results(
					$wpdb->prepare(
						$pend_sql,
						array_merge( $pend_vals, array( $p_pend['per_page'], $p_pend['offset'] ) )
					)
				);
			} else {
				$pending_users = $wpdb->get_results(
					$wpdb->prepare( $pend_sql, $p_pend['per_page'], $p_pend['offset'] )
				);
			}

			$users_sql = "SELECT u.*, COALESCE(s.svc_count, 0) AS svc_count
				FROM {$u_tbl} u
				{$users_svc_join}
				WHERE 1=1";
			$users_vals = array();
			if ( $user_filter ) {
				$users_sql .= $user_filter['sql'];
				$users_vals = array_merge( $users_vals, $user_filter['values'] );
			}
			$users_sql .= $users_list_filter['where_sql'];
			$users_vals = array_merge( $users_vals, $users_list_filter['where_values'] );
			if ( $owner_users_scope ) {
				$users_sql .= $owner_users_scope['sql'];
				$users_vals = array_merge( $users_vals, $owner_users_scope['values'] );
			}
			$users_sql .= " ORDER BY {$users_list_filter['order_sql']} LIMIT %d OFFSET %d";
			if ( ! empty( $users_vals ) ) {
				$users_list = $wpdb->get_results(
					$wpdb->prepare(
						$users_sql,
						array_merge( $users_vals, array( $p_users['per_page'], $p_users['offset'] ) )
					)
				);
			} else {
				$users_list = $wpdb->get_results(
					$wpdb->prepare( $users_sql, $p_users['per_page'], $p_users['offset'] )
				);
			}
			$resellers = array();
			if ( $needs_resellers_tab_data || $needs_resellers_preview ) {
				$res_limit  = $needs_resellers_preview ? 8 : $p_res['per_page'];
				$res_offset = $needs_resellers_preview ? 0 : $p_res['offset'];
				$res_sql = "SELECT u.*, COALESCE(s.svc_count, 0) AS svc_count
				FROM {$u_tbl} u
				{$users_svc_join}
				WHERE u.role = 'reseller'{$reseller_status_sql}";
				if ( $resellers_user_filter ) {
					$res_sql .= $resellers_user_filter['sql'];
				}
				if ( $owner_users_scope ) {
					$res_sql .= $owner_users_scope['sql'];
				}
				$res_sql .= ' ORDER BY u.id DESC LIMIT %d OFFSET %d';
				$res_vals = array_merge( $reseller_status_vals, $resellers_user_filter ? $resellers_user_filter['values'] : array(), $owner_users_scope ? $owner_users_scope['values'] : array(), array( $res_limit, $res_offset ) );
				$resellers = $wpdb->get_results(
					$wpdb->prepare( $res_sql, $res_vals )
				); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$resellers = self::attach_reseller_direct_user_counts( (array) $resellers );
			}
		}

		if ( $dash_users_tab_light ) {
			$receipts   = array();
			$broadcasts = array();
		} elseif ( $rcpt_scope_empty ) {
			$receipts = array();
		} else {
			$receipts = self::receipt_admin_select(
				$wpdb,
				$rcpt_t,
				$u_tbl,
				$rcpt_scope_sql,
				$rcpt_filter,
				$p_rcpt['per_page'],
				$p_rcpt['offset']
			);
		}
		if ( ! $dash_users_tab_light ) {
			if ( $owner_scoped_catalog ) {
				$broadcasts = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$bc_t} WHERE owner_svp_user_id = %d ORDER BY id DESC LIMIT %d OFFSET %d",
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

		$reseller_permissions_map            = array();
		$reseller_panel_prices_map           = array();
		$reseller_wholesale_line_ids_map     = array();
		$reseller_bot_map                    = array();
		if ( $needs_resellers_tab_data || $needs_child_reseller_maps || $needs_resellers_preview ) {
			$reseller_row_ids = array();
			foreach ( (array) $resellers as $rr ) {
				$rid = (int) ( is_object( $rr ) ? ( $rr->id ?? 0 ) : 0 );
				if ( $rid > 0 ) {
					$reseller_row_ids[] = $rid;
				}
			}
			if ( ! empty( $reseller_row_ids ) ) {
				$reseller_permissions_map = SimpleVPBot_Model_User::reseller_permissions_map_for_ids( $reseller_row_ids );
				if ( ( $needs_resellers_tab_data || $needs_resellers_preview ) && class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' ) ) {
					$panel_rows_by_rid = SimpleVPBot_Model_Reseller_Panel_Price::rows_map_for_resellers( $reseller_row_ids );
					foreach ( $panel_rows_by_rid as $rid_key => $prows ) {
						$reseller_panel_prices_map[ (string) $rid_key ] = array_map(
							array( __CLASS__, 'row_array' ),
							(array) $prows
						);
					}
				}
				if ( class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
					$reseller_bot_map = SimpleVPBot_Model_Reseller_Bot_Profile::summary_map_for_resellers( $reseller_row_ids );
				}
				if ( class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Assignment' ) ) {
					$reseller_wholesale_line_ids_map = SimpleVPBot_Model_Reseller_Wholesale_Assignment::line_ids_map_for_resellers( $reseller_row_ids );
				}
			}
		}
		if ( ! $needs_resellers_tab_data && ! empty( $ctx['isReseller'] ) && class_exists( 'SimpleVPBot_Model_Reseller_Parent_Panel_Floor' ) ) {
			$child_ids = array();
			foreach ( (array) $resellers as $rr ) {
				$rid = (int) ( is_object( $rr ) ? ( $rr->id ?? 0 ) : 0 );
				if ( $rid > 0 ) {
					$child_ids[] = $rid;
				}
			}
			$parent_id = (int) ( $ctx['actorUserId'] ?? 0 );
			$floor_map = ! empty( $child_ids )
				? SimpleVPBot_Model_Reseller_Parent_Panel_Floor::map_for_parent_children( $parent_id, $child_ids )
				: array();
			foreach ( $floor_map as $rid_key => $floor_rows ) {
				$reseller_panel_prices_map[ (string) $rid_key ] = array_map(
					static function ( $row ) {
						$ra = self::row_array( $row );
						if ( is_array( $ra ) ) {
							$ra['price_per_gb'] = (float) ( $ra['min_price_per_gb'] ?? 0 );
							$ra['panel_access'] = 1;
						}
						return $ra;
					},
					(array) $floor_rows
				);
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
				$panels[] = self::format_panel_for_dashboard( $ra );
			}
		}

		$reseller_plan_floors       = array();
		$reseller_panel_price_rows  = array();
		$reseller_wholesale_lines   = array();
		$reseller_panel_price_index = array();
		$reseller_actor_user_row    = null;
		if ( $reseller_mode && $actor_uid > 0 && class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' ) && $needs_reseller_panel_prices ) {
			$reseller_panel_price_rows  = (array) SimpleVPBot_Model_Reseller_Panel_Price::list_for_reseller( $actor_uid );
			$reseller_panel_price_index = SimpleVPBot_Model_Reseller_Panel_Price::index_rows_by_panel( $reseller_panel_price_rows );
			if ( class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Line' ) ) {
				$reseller_wholesale_lines = (array) SimpleVPBot_Model_Reseller_Wholesale_Line::lines_for_reseller( $actor_uid );
			}
			if ( $needs_reseller_plan_floors ) {
				if ( class_exists( 'SimpleVPBot_Model_User' ) ) {
					$reseller_actor_user_row = SimpleVPBot_Model_User::find( $actor_uid );
				}
				$floor_opts = array(
					'actor_user_row' => $reseller_actor_user_row,
				);
				$floor_panels = array();
				foreach ( $reseller_panel_price_rows as $rp ) {
					if ( ! SimpleVPBot_Model_Reseller_Panel_Price::row_allows_panel_use( $rp ) ) {
						continue;
					}
					$pid = (int) $rp->panel_id;
					if ( $pid < 1 ) {
						continue;
					}
					$floor_panels[ $pid ] = true;
					$floor_opts['panel_row'] = $rp;
					$eff                    = (float) SimpleVPBot_Model_Reseller_Panel_Price::effective_wholesale_floor( $actor_uid, $pid, $floor_opts );
					$dstype                 = isset( $rp->default_service_type ) ? sanitize_key( (string) $rp->default_service_type ) : 'xray';
					if ( ! in_array( $dstype, array( 'xray', 'l2tp' ), true ) ) {
						$dstype = 'xray';
					}
					if ( ! $l2tp_enabled ) {
						$dstype = 'xray';
					}
					$reseller_plan_floors[] = array(
						'panel_id'                   => $pid,
						'wholesale_line_id'          => 0,
						'min_price_per_gb_effective' => $eff,
						'default_service_type'       => $dstype,
						'default_inbound_id'         => (int) ( $rp->default_inbound_id ?? 0 ),
						'default_l2tp_server_id'     => $l2tp_enabled ? (int) ( $rp->default_l2tp_server_id ?? 0 ) : 0,
					);
				}
				if ( class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Line' ) ) {
					$wholesale_floor_line_ids  = array();
					$wholesale_floor_panel_ids = array();
					foreach ( $reseller_wholesale_lines as $_wl_pre ) {
						$wholesale_floor_line_ids[]  = (int) ( $_wl_pre->id ?? 0 );
						$wholesale_floor_panel_ids[] = (int) ( $_wl_pre->panel_id ?? 0 );
					}
					if ( class_exists( 'SimpleVPBot_Service_Reseller_Wholesale_Pricing' ) ) {
						SimpleVPBot_Service_Reseller_Wholesale_Pricing::begin_floor_batch( $actor_uid, $wholesale_floor_line_ids );
					}
					$catalog_defaults_map = SimpleVPBot_Model_Reseller_Panel_Price::resolve_catalog_defaults_map(
						$actor_uid,
						$wholesale_floor_panel_ids
					);
					foreach ( $reseller_wholesale_lines as $wl ) {
						$pid = (int) ( $wl->panel_id ?? 0 );
						$lid = (int) ( $wl->id ?? 0 );
						if ( $pid < 1 || $lid < 1 ) {
							continue;
						}
						$catalog = isset( $catalog_defaults_map[ $pid ] ) && is_array( $catalog_defaults_map[ $pid ] )
							? $catalog_defaults_map[ $pid ]
							: SimpleVPBot_Model_Reseller_Panel_Price::resolve_catalog_defaults( $actor_uid, $pid );
						$floor_opts['panel_row'] = isset( $reseller_panel_price_index[ $pid ] ) ? $reseller_panel_price_index[ $pid ] : null;
						if ( class_exists( 'SimpleVPBot_Service_Reseller_Wholesale_Pricing' ) ) {
							$eff = (float) SimpleVPBot_Service_Reseller_Wholesale_Pricing::wholesale_floor_unit( $actor_uid, $lid, $pid );
						} else {
							$eff = (float) SimpleVPBot_Model_Reseller_Panel_Price::effective_wholesale_floor( $actor_uid, $pid, $floor_opts );
						}
						$dstype = sanitize_key( (string) ( $catalog['default_service_type'] ?? 'xray' ) );
						if ( ! in_array( $dstype, array( 'xray', 'l2tp' ), true ) ) {
							$dstype = 'xray';
						}
						if ( ! $l2tp_enabled ) {
							$dstype = 'xray';
						}
						$reseller_plan_floors[] = array(
							'panel_id'                   => $pid,
							'wholesale_line_id'          => $lid,
							'min_price_per_gb_effective' => $eff,
							'default_service_type'       => $dstype,
							'default_inbound_id'         => (int) ( $catalog['default_inbound_id'] ?? 0 ),
							'default_l2tp_server_id'     => $l2tp_enabled ? (int) ( $catalog['default_l2tp_server_id'] ?? 0 ) : 0,
						);
						$floor_panels[ $pid ] = true;
					}
					if ( class_exists( 'SimpleVPBot_Service_Reseller_Wholesale_Pricing' ) ) {
						SimpleVPBot_Service_Reseller_Wholesale_Pricing::end_floor_batch();
					}
				}
			}
		}
		foreach ( (array) $plans_raw as $r ) {
			$ra = self::row_array( $r );
			if ( $ra ) {
				$plans[] = $ra;
			}
		}
		if ( ! $l2tp_enabled && class_exists( 'SimpleVPBot_Feature_L2tp' ) ) {
			$plans = SimpleVPBot_Feature_L2tp::filter_plan_rows( $plans );
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
		$discount_usage_summary = array(
			'total_redemptions'    => 0,
			'total_discount_toman' => 0.0,
			'active_codes'         => 0,
		);
		$discount_code_ids = array();
		foreach ( (array) $discounts_raw as $r ) {
			$ra = self::row_array( $r );
			if ( $ra && ! empty( $ra['id'] ) ) {
				$discount_code_ids[] = (int) $ra['id'];
			}
		}
		$discount_agg_map = array();
		$owner_id         = $reseller_mode ? $actor_uid : ( $owner_ctx > 0 ? $owner_ctx : 0 );
		if ( class_exists( 'SimpleVPBot_Model_Discount_Redemption' ) ) {
			$discount_usage_summary = SimpleVPBot_Model_Discount_Redemption::global_summary( $owner_id );
			if ( ! empty( $discount_code_ids ) ) {
				$discount_agg_map = SimpleVPBot_Model_Discount_Redemption::aggregates_by_code_ids( $discount_code_ids, $owner_id );
			}
		}
		foreach ( (array) $discounts_raw as $r ) {
			$ra = self::row_array( $r );
			if ( ! $ra ) {
				continue;
			}
			$did = (int) ( $ra['id'] ?? 0 );
			if ( $did > 0 && isset( $discount_agg_map[ $did ] ) ) {
				$ra['redemption_count']     = (int) ( $discount_agg_map[ $did ]['count'] ?? 0 );
				$ra['total_discount_toman'] = (float) ( $discount_agg_map[ $did ]['sum_discount'] ?? 0 );
			} else {
				$ra['redemption_count']     = 0;
				$ra['total_discount_toman'] = 0.0;
			}
			if ( ! empty( $ra['allowed_plan_ids'] ) && is_string( $ra['allowed_plan_ids'] ) ) {
				$ra['allowed_plan_ids'] = SimpleVPBot_Model_Discount_Code::parse_allowed_plan_ids( $ra['allowed_plan_ids'] );
			}
			$discounts[] = $ra;
		}

		$wholesale_lines_catalog            = array();
		$reseller_wholesale_lines_payload = array();
		if ( class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Line' ) && class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Tier' ) ) {
			if ( ! $reseller_mode ) {
				$all_line_rows = SimpleVPBot_Model_Reseller_Wholesale_Line::all_rows();
				$line_ids      = array();
				foreach ( (array) $all_line_rows as $_ln ) {
					$line_ids[] = (int) ( is_object( $_ln ) ? ( $_ln->id ?? 0 ) : 0 );
				}
				$tier_map = SimpleVPBot_Model_Reseller_Wholesale_Tier::by_line_ids( $line_ids );
				foreach ( (array) $all_line_rows as $_ln ) {
					$la = self::row_array( $_ln );
					if ( ! $la ) {
						continue;
					}
					$la['tiers'] = array();
					$lid         = (int) ( is_object( $_ln ) ? ( $_ln->id ?? 0 ) : 0 );
					foreach ( (array) ( $tier_map[ $lid ] ?? array() ) as $_t ) {
						$ta = self::row_array( $_t );
						if ( $ta ) {
							$la['tiers'][] = $ta;
						}
					}
					$wholesale_lines_catalog[] = $la;
				}
			} elseif ( $actor_uid > 0 && $needs_reseller_wholesale_ladders && class_exists( 'SimpleVPBot_Service_Reseller_Wholesale_Pricing' ) ) {
				$ladder_line_ids = array();
				foreach ( $reseller_wholesale_lines as $_ln_pre ) {
					$ladder_line_ids[] = (int) ( is_object( $_ln_pre ) ? ( $_ln_pre->id ?? 0 ) : 0 );
				}
				SimpleVPBot_Service_Reseller_Wholesale_Pricing::begin_floor_batch( $actor_uid, $ladder_line_ids );
				foreach ( $reseller_wholesale_lines as $_ln ) {
					$pub           = SimpleVPBot_Model_Reseller_Wholesale_Line::to_reseller_public_array( $_ln );
					$pub['ladder'] = SimpleVPBot_Service_Reseller_Wholesale_Pricing::ladder_snapshot( $actor_uid, (int) $_ln->id );
					$reseller_wholesale_lines_payload[] = $pub;
				}
			}
		}

		// Merge panels from reseller panel_prices when catalog rows grant access.
		if ( $reseller_mode && $actor_uid > 0 && class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' ) && class_exists( 'SimpleVPBot_Model_Panel' ) ) {
			$have_panel_ids = array();
			foreach ( $panels as $_pub ) {
				if ( is_array( $_pub ) && isset( $_pub['id'] ) ) {
					$have_panel_ids[ (int) $_pub['id'] ] = true;
				}
			}
			$missing_panel_ids = array();
			foreach ( $reseller_panel_price_rows as $_rp ) {
				if ( ! SimpleVPBot_Model_Reseller_Panel_Price::row_allows_panel_use( $_rp ) ) {
					continue;
				}
				$_pid = (int) ( $_rp->panel_id ?? 0 );
				if ( $_pid < 1 || ! empty( $have_panel_ids[ $_pid ] ) ) {
					continue;
				}
				$missing_panel_ids[] = $_pid;
			}
			if ( class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Line' ) ) {
				foreach ( $reseller_wholesale_lines as $_wl ) {
					$_pid = (int) ( $_wl->panel_id ?? 0 );
					if ( $_pid < 1 || ! empty( $have_panel_ids[ $_pid ] ) ) {
						continue;
					}
					$missing_panel_ids[] = $_pid;
				}
			}
			$panel_map = SimpleVPBot_Model_Panel::find_by_ids( $missing_panel_ids );
			foreach ( $reseller_panel_price_rows as $_rp ) {
				if ( ! SimpleVPBot_Model_Reseller_Panel_Price::row_allows_panel_use( $_rp ) ) {
					continue;
				}
				$_pid = (int) ( $_rp->panel_id ?? 0 );
				if ( $_pid < 1 || ! empty( $have_panel_ids[ $_pid ] ) ) {
					continue;
				}
				$pobj = isset( $panel_map[ $_pid ] ) ? $panel_map[ $_pid ] : null;
				$pub  = self::panel_object_to_dashboard_payload( $pobj );
				if ( ! $pub ) {
					continue;
				}
				$have_panel_ids[ $_pid ] = true;
				$panels[]                 = $pub;
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
			if ( class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Line' ) ) {
				foreach ( $reseller_wholesale_lines as $_wl ) {
					$_pid = (int) ( $_wl->panel_id ?? 0 );
					if ( $_pid < 1 || ! empty( $have_panel_ids[ $_pid ] ) ) {
						continue;
					}
					$pobj = isset( $panel_map[ $_pid ] ) ? $panel_map[ $_pid ] : null;
					$pub  = self::panel_object_to_dashboard_payload( $pobj );
					if ( ! $pub ) {
						continue;
					}
					$have_panel_ids[ $_pid ] = true;
					$panels[]                 = $pub;
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
		}

		if ( $reseller_mode && $actor_uid > 0 && ! empty( $panels ) ) {
			$panels = self::annotate_reseller_panels_for_dashboard( $panels, $actor_uid, $reseller_panel_price_index );
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
			if ( $reseller_mode ) {
				$scope_in = implode( ',', array_map( 'absint', $moderatable_user_ids ) );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cnt_rows = $wpdb->get_results(
					"SELECT plan_id, COUNT(DISTINCT user_id) AS user_count FROM {$s_tbl} WHERE deleted_at IS NULL AND plan_id IS NOT NULL AND plan_id > 0 AND plan_id IN ({$in_list}) AND user_id IN ({$scope_in}) GROUP BY plan_id",
					ARRAY_A
				);
			} elseif ( $owner_ctx > 0 && ! empty( $moderatable_user_ids ) ) {
				$scope_in = implode( ',', array_map( 'absint', $moderatable_user_ids ) );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cnt_rows = $wpdb->get_results(
					"SELECT plan_id, COUNT(DISTINCT user_id) AS user_count FROM {$s_tbl} WHERE deleted_at IS NULL AND plan_id IS NOT NULL AND plan_id > 0 AND plan_id IN ({$in_list}) AND user_id IN ({$scope_in}) GROUP BY plan_id",
					ARRAY_A
				);
			} else {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cnt_rows = $wpdb->get_results(
					"SELECT plan_id, COUNT(DISTINCT user_id) AS user_count FROM {$s_tbl} WHERE deleted_at IS NULL AND plan_id IS NOT NULL AND plan_id > 0 AND plan_id IN ({$in_list}) GROUP BY plan_id",
					ARRAY_A
				);
			}
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
		if ( ! $dash_users_tab_light && ! $rcpt_scope_empty ) {
			$agg_rows = self::receipt_admin_aggregate_rows( $wpdb, $rcpt_t, $u_tbl, $rcpt_scope_sql, $rcpt_filter );
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
		);
		if ( $l2tp_enabled ) {
			$nav_tabs[] = array( 'key' => 'l2tp_servers', 'label' => __( 'سرورهای L2TP', 'simplevpbot' ) );
		}
		$nav_tabs = array_merge(
			$nav_tabs,
			array(
			array( 'key' => 'receipts', 'label' => __( 'رسیدها', 'simplevpbot' ) ),
			array( 'key' => 'broadcast', 'label' => __( 'پیام همگانی', 'simplevpbot' ) ),
			array( 'key' => 'texts', 'label' => __( 'متن‌ها', 'simplevpbot' ) ),
			array( 'key' => 'users', 'label' => __( 'کاربران', 'simplevpbot' ) ),
			array( 'key' => 'backup', 'label' => __( 'بکاپ', 'simplevpbot' ) ),
			array( 'key' => 'notifications', 'label' => __( 'نوتیفیکیشن', 'simplevpbot' ) ),
			array( 'key' => 'referral', 'label' => __( 'ریفرال و لینک ربات', 'simplevpbot' ) ),
			array( 'key' => 'referral_reports', 'label' => __( 'گزارشات رفرال', 'simplevpbot' ) ),
			array( 'key' => 'reseller_reports', 'label' => __( 'گزارشات نمایندگان', 'simplevpbot' ) ),
			array( 'key' => 'marketing_lifecycle', 'label' => __( 'بازگشت مشتری', 'simplevpbot' ) ),
			array( 'key' => 'discounts', 'label' => __( 'کدهای تخفیف', 'simplevpbot' ) ),
			array( 'key' => 'logs', 'label' => __( 'لاگ‌ها', 'simplevpbot' ) ),
			)
		);
		$reseller_actor_perms = null;
		if ( $reseller_mode && $actor_uid > 0 ) {
			$reseller_actor_perms = SimpleVPBot_Model_User::reseller_permissions( $actor_uid );
			$reseller_tab_map     = self::reseller_dashboard_allowed_tabs_map( $actor_uid, $reseller_actor_perms );
			$nav_tabs             = array_values(
				array_filter(
					$nav_tabs,
					static function ( $tab ) use ( $reseller_tab_map ) {
						$key = is_array( $tab ) ? (string) ( $tab['key'] ?? '' ) : '';
						return '' !== $key && ! empty( $reseller_tab_map[ $key ] );
					}
				)
			);
		}

		$stats_payload = array();
		if ( ! $dash_users_tab_light && class_exists( 'SimpleVPBot_Admin_Dashboard_Stats' ) ) {
			$stats_day = max( 0, min( 7, (int) $req->get_param( 'statsDay' ) ) );
			if ( $reseller_mode ) {
				$stats_payload = SimpleVPBot_Admin_Dashboard_Stats::build_reseller_payload(
					$moderatable_user_ids,
					$reseller_allowed_panel_ids,
					$stats_day
				);
			} elseif ( $owner_ctx > 0 && ! empty( $moderatable_user_ids ) ) {
				$stats_payload = SimpleVPBot_Admin_Dashboard_Stats::build_reseller_payload(
					$moderatable_user_ids,
					array(),
					$stats_day
				);
			} else {
				$stats_payload = SimpleVPBot_Admin_Dashboard_Stats::build_payload( $stats_day );
			}
		}

		$text_defaults = ( $reseller_mode || ! class_exists( 'SimpleVPBot_Activator' ) )
			? array()
			: SimpleVPBot_Activator::default_text_values_map();

		$referral_stats     = null;
		$referral_events    = array();
		$tot_referral_ev    = 0;
		if ( 'referral_reports' === $active_tab && class_exists( 'SimpleVPBot_Model_Referral_Event' ) ) {
			$t_tx        = SimpleVPBot_Model_Transaction::table();
			$ref_ev_tbl  = SimpleVPBot_Model_Referral_Event::table();
			$since_ts    = strtotime( '-30 days', (int) current_time( 'timestamp' ) );
			$since_mysql = wp_date( 'Y-m-d H:i:s', $since_ts );
			$scope_sql   = '';
			if ( $reseller_mode ) {
				$scope_sql = implode( ',', array_map( 'absint', $moderatable_user_ids ) );
			} elseif ( $owner_ctx > 0 && ! empty( $moderatable_user_ids ) ) {
				$scope_sql = implode( ',', array_map( 'absint', $moderatable_user_ids ) );
			} elseif ( ! empty( $scope_user_ids ) ) {
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
			} elseif ( $reseller_mode ) {
				$tot_referral_ev = 0;
				$events_last_30  = 0;
				$invited_users   = 0;
				$commission_sum  = 0.0;
				$ref_amt_sum     = 0.0;
				$top_rows        = array();
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

		$reseller_reports_stats = null;
		$reseller_reports_rows  = array();
		$reseller_reports_daily = array();
		$tot_reseller_reports   = 0;
		if ( 'reseller_reports' === $active_tab && class_exists( 'SimpleVPBot_Admin_Reseller_Reports' ) ) {
			$scope_ancestor = $reseller_mode && $actor_uid > 0 ? $actor_uid : 0;
			$rep_built = SimpleVPBot_Admin_Reseller_Reports::build(
				$req,
				$p_rep,
				$scope_ancestor > 0 ? $scope_ancestor : null
			);
			$tot_reseller_reports   = (int) ( $rep_built['total'] ?? 0 );
			$reseller_reports_rows  = isset( $rep_built['rows'] ) && is_array( $rep_built['rows'] ) ? $rep_built['rows'] : array();
			$reseller_reports_daily = isset( $rep_built['daily'] ) && is_array( $rep_built['daily'] ) ? $rep_built['daily'] : array();
			$reseller_reports_stats = array(
				'window_days'   => (int) ( $rep_built['window_days'] ?? 30 ),
				'since'         => (string) ( $rep_built['since'] ?? '' ),
				'backfill_done' => ! empty( $rep_built['backfill_done'] ),
				'daily_scoped'  => ! empty( $rep_built['daily_scoped'] ),
				'summary'       => isset( $rep_built['summary'] ) && is_array( $rep_built['summary'] ) ? $rep_built['summary'] : array(),
			);
		}

		$marketing_lifecycle_stats  = null;
		$marketing_lifecycle_funnel = array();
		$marketing_rules            = array();
		$marketing_rule_stats       = array();
		$marketing_offers           = array();
		$tot_marketing_offers       = 0;
		if ( 'marketing_lifecycle' === $active_tab && class_exists( 'SimpleVPBot_Marketing_Lifecycle_Analytics' ) ) {
			$mkt_days   = SimpleVPBot_Marketing_Lifecycle_Analytics::normalize_window_days(
				(int) $req->get_param( 'marketing_lifecycle_days' )
			);
			$mkt_owner  = $reseller_mode ? $actor_uid : ( $owner_ctx > 0 ? $owner_ctx : 0 );
			$site_admin = ! $reseller_mode && current_user_can( 'manage_options' );
			$built      = SimpleVPBot_Marketing_Lifecycle_Analytics::build_dashboard_payload(
				$mkt_days,
				$mkt_owner,
				$site_admin
			);
			$marketing_lifecycle_stats  = array(
				'window_days' => (int) ( $built['window_days'] ?? $mkt_days ),
				'since'       => (string) ( $built['since'] ?? '' ),
				'summary'     => isset( $built['summary'] ) && is_array( $built['summary'] ) ? $built['summary'] : array(),
			);
			$marketing_lifecycle_funnel = isset( $built['funnel'] ) && is_array( $built['funnel'] ) ? $built['funnel'] : array();
			$marketing_rules            = isset( $built['rules'] ) && is_array( $built['rules'] ) ? $built['rules'] : array();
			$marketing_rule_stats       = isset( $built['rule_stats'] ) && is_array( $built['rule_stats'] ) ? $built['rule_stats'] : array();
			$mkt_offer_status           = sanitize_key( (string) wp_unslash( (string) $req->get_param( 'marketingOffers_status' ) ) );
			if ( class_exists( 'SimpleVPBot_Model_Marketing_Offer' ) ) {
				$off_built = SimpleVPBot_Model_Marketing_Offer::list_recent(
					$mkt_owner,
					$p_mkt['per_page'],
					$p_mkt['offset'],
					$site_admin,
					$mkt_offer_status
				);
				$tot_marketing_offers = (int) ( $off_built['total'] ?? 0 );
				foreach ( (array) ( $off_built['rows'] ?? array() ) as $orow ) {
					$marketing_offers[] = SimpleVPBot_Model_Marketing_Offer::to_payload( $orow );
				}
			}
		}

		$force_health = $req->get_param( 'refreshPanelHealth' ) === '1';
		$panel_health = array();
		// Health covers all panels (not only the paged list slice used elsewhere).
		if ( ( $dash_users_tab_light && ! $force_health ) || ! $needs_panel_health ) {
			$panels_for_health = array();
		} elseif ( $reseller_mode ) {
			if ( empty( $reseller_allowed_panel_ids ) ) {
				$panels_for_health = array();
			} else {
				$pc_hp = implode( ',', array_fill( 0, count( $reseller_allowed_panel_ids ), '%d' ) );
				$panels_for_health = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT id, panel_url FROM {$t_panels} WHERE id IN ({$pc_hp}) ORDER BY sort_order ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$reseller_allowed_panel_ids
					),
					ARRAY_A
				);
			}
		} else {
			$panels_for_health = $wpdb->get_results(
				"SELECT id, panel_url FROM {$t_panels} ORDER BY sort_order ASC, id ASC",
				ARRAY_A
			);
		}
		if ( ! ( $dash_users_tab_light && ! $force_health ) ) {
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
		$reseller_wants_live = $reseller_mode && ! empty( $panels_for_health )
			&& ( ( 'monitoring' === $active_tab ) || $force_live_metrics );
		$want_live_metrics = ( ! $reseller_mode && ( ( 'monitoring' === $active_tab ) || $force_live_metrics ) )
			|| $reseller_wants_live;
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
			if ( ! $reseller_mode && class_exists( 'SimpleVPBot_Model_Monitor_Host' ) ) {
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

		$online_daily_series = array();
		if ( ! $dash_users_tab_light && class_exists( 'SimpleVPBot_Model_Panel_Online_Daily' ) ) {
			$online_panel_ids = $reseller_mode ? $reseller_allowed_panel_ids : array();
			$online_daily_series = $reseller_mode
				? SimpleVPBot_Model_Panel_Online_Daily::daily_totals_last_days_for_panels( 7, $online_panel_ids )
				: SimpleVPBot_Model_Panel_Online_Daily::daily_totals_last_days( 7 );
			$online_daily_series = SimpleVPBot_Model_Panel_Online_Daily::merge_live_snapshots_into_series(
				$online_daily_series,
				$live_snapshots
			);
			if ( empty( $live_snapshots ) ) {
				$cache_panel_ids = $reseller_mode
					? $online_panel_ids
					: array_values(
						array_filter(
							array_map(
								static function ( $hrow ) {
									return is_array( $hrow ) && isset( $hrow['id'] ) ? (int) $hrow['id'] : 0;
								},
								(array) $panels_for_health
							),
							static function ( $v ) {
								return $v > 0;
							}
						)
					);
				$online_daily_series = SimpleVPBot_Model_Panel_Online_Daily::merge_cached_live_into_series(
					$online_daily_series,
					$cache_panel_ids
				);
			}
			$online_daily_series = SimpleVPBot_Model_Panel_Online_Daily::maybe_self_heal_today_series(
				$online_daily_series,
				7,
				$reseller_mode ? $online_panel_ids : array()
			);
		}

		$overview_bot_tg   = (string) ( $settings['telegram_bot_username'] ?? '' );
		$overview_bot_bale = (string) ( $settings['bale_bot_username'] ?? '' );
		$overview_tg_en    = class_exists( 'SimpleVPBot_Platforms' ) ? SimpleVPBot_Platforms::main_platform_flag( 'telegram', $settings ) : true;
		$overview_bl_en    = class_exists( 'SimpleVPBot_Platforms' ) ? SimpleVPBot_Platforms::main_platform_flag( 'bale', $settings ) : true;
		$reseller_prof     = null;
		if ( $reseller_mode && $actor_uid > 0 && class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			$reseller_prof = SimpleVPBot_Model_Reseller_Bot_Profile::find_by_reseller( $actor_uid );
			if ( $reseller_prof ) {
				$overview_bot_tg = (string) SimpleVPBot_Model_Reseller_Bot_Profile::bot_username_for_platform( $reseller_prof, 'telegram' );
				$overview_bot_bale = (string) SimpleVPBot_Model_Reseller_Bot_Profile::bot_username_for_platform( $reseller_prof, 'bale' );
				if ( class_exists( 'SimpleVPBot_Platforms' ) ) {
					$overview_tg_en = SimpleVPBot_Platforms::is_enabled( 'telegram', $actor_uid );
					$overview_bl_en = SimpleVPBot_Platforms::is_enabled( 'bale', $actor_uid );
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
				'telegram_enabled'      => $overview_tg_en,
				'bale_enabled'          => $overview_bl_en,
				'telegram_bot_username' => $overview_bot_tg,
				'bale_bot_username'     => $overview_bot_bale,
			),
			'host'          => $reseller_mode ? null : self::overview_host_metrics(),
			'onlineDailySeries' => $online_daily_series,
			'panelHealth'   => $panel_health,
			'livePanelSnapshots' => $live_snapshots,
			'externalHostSnapshots' => $external_snaps,
		);
		if ( ! $reseller_mode && 'overview' === $active_tab && class_exists( 'SimpleVPBot_Unit_Economics_Overview' ) ) {
			$overview['economics'] = SimpleVPBot_Unit_Economics_Overview::build();
		}

		$reseller_overview_metrics = null;
		if ( $reseller_mode && $actor_uid > 0 && 'dashboard' === $active_tab && class_exists( 'SimpleVPBot_Admin_Reseller_Reports' ) ) {
			$reseller_overview_metrics = SimpleVPBot_Admin_Reseller_Reports::build_actor_summary(
				$actor_uid,
				SimpleVPBot_Admin_Reseller_Reports::overview_metrics_days_from_request( $req )
			);
		}

		$bots_list_payload = array();
		$tot_bots_list     = 0;
		$load_reseller_bots_list = ! $dash_users_tab_light
			&& ( $reseller_mode || in_array( $active_tab, array( 'reseller_bots', 'reseller_settings' ), true ) );
		if ( $load_reseller_bots_list && class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			if ( $reseller_mode && $actor_uid > 0 ) {
				$p = SimpleVPBot_Model_Reseller_Bot_Profile::table();
				$u            = SimpleVPBot_Model_User::table();
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$bot_profiles = array(
					$wpdb->get_row(
						$wpdb->prepare(
							"SELECT u.id AS reseller_svp_user_id, u.first_name AS reseller_first_name, u.last_name AS reseller_last_name,
							u.username AS reseller_username, u.status AS reseller_status,
							p.brand_name, p.logo_url, p.favicon_url, p.theme_primary, p.theme_accent, p.custom_domain, p.telegram_relay_public_url,
							p.config_label_override, p.config_label_prefix, p.enabled, p.telegram_token, p.bale_token, p.telegram_secret_token,
							p.telegram_bot_username, p.bale_bot_username, p.text_overrides_json,
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
				$tg_tok = class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' )
					? SimpleVPBot_Model_Reseller_Bot_Profile::token_for_platform( $brow, 'telegram' )
					: '';
				$bl_tok = class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' )
					? SimpleVPBot_Model_Reseller_Bot_Profile::token_for_platform( $brow, 'bale' )
					: '';
				$rname  = trim( (string) ( $brow->reseller_first_name ?? '' ) . ' ' . (string) ( $brow->reseller_last_name ?? '' ) );
				if ( '' === $rname ) {
					$rname = (string) ( $brow->reseller_username ?? '' );
				}
				$text_ov = class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' )
					? SimpleVPBot_Model_Reseller_Bot_Profile::editable_text_overrides_for_api( $rid )
					: array();
				$bots_list_payload[] = array(
					'reseller_id'               => $rid,
					'reseller_name'             => trim( $rname ),
					'reseller_status'           => (string) ( $brow->reseller_status ?? '' ),
					'brand_name'                => (string) ( $brow->brand_name ?? '' ),
					'logo_url'                  => (string) ( $brow->logo_url ?? '' ),
					'favicon_url'               => (string) ( $brow->favicon_url ?? '' ),
					'theme_primary'             => (string) ( $brow->theme_primary ?? '' ),
					'theme_accent'              => (string) ( $brow->theme_accent ?? '' ),
					'custom_domain'             => (string) ( $brow->custom_domain ?? '' ),
					'telegram_relay_public_url' => (string) ( $brow->telegram_relay_public_url ?? '' ),
					'config_label_override'     => (string) ( $brow->config_label_override ?? '' ),
					'config_label_prefix'       => (string) ( $brow->config_label_prefix ?? '' ),
					'enabled'                   => ! empty( $brow->enabled ),
					'telegram_enabled'          => class_exists( 'SimpleVPBot_Platforms' ) ? SimpleVPBot_Platforms::reseller_platform_flag( $brow, 'telegram' ) : true,
					'bale_enabled'              => class_exists( 'SimpleVPBot_Platforms' ) ? SimpleVPBot_Platforms::reseller_platform_flag( $brow, 'bale' ) : true,
					'has_telegram_token'        => '' !== $tg_tok,
					'has_bale_token'            => '' !== $bl_tok,
					'telegram_bot_username'     => class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' )
						? SimpleVPBot_Model_Reseller_Bot_Profile::bot_username_for_platform( $brow, 'telegram' )
						: '',
					'bale_bot_username'         => class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' )
						? SimpleVPBot_Model_Reseller_Bot_Profile::bot_username_for_platform( $brow, 'bale' )
						: '',
					'text_overrides'            => $text_ov,
					'telegram_secret_token_set' => '' !== trim( (string) ( $brow->telegram_secret_token ?? '' ) ),
					'admin_telegram_ids'        => $tg_ids,
					'admin_bale_ids'            => $bl_ids,
					'inbound_display_names'     => class_exists( 'SimpleVPBot_Model_Reseller_Inbound_Display_Name' )
						? SimpleVPBot_Model_Reseller_Inbound_Display_Name::map_for_reseller( $rid )
						: array(),
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
			'referralEvents'   => self::dash_pagination_meta( $p_ref_ev['page'], $p_ref_ev['per_page'], $tot_referral_ev ),
			'resellerReports'  => self::dash_pagination_meta( $p_rep['page'], $p_rep['per_page'], $tot_reseller_reports ),
			'marketingOffers'  => self::dash_pagination_meta( $p_mkt['page'], $p_mkt['per_page'], $tot_marketing_offers ),
			'botsList'         => self::dash_pagination_meta( $p_bots['page'], $p_bots['per_page'], $tot_bots_list ),
		);

		$ui_layout    = ( $reseller_mode || ! class_exists( 'SimpleVPBot_UI_Layout' ) )
			? array( 'version' => 0, 'surfaces' => array() )
			: SimpleVPBot_UI_Layout::export_merged_for_dashboard();
		$ui_registry = ( $reseller_mode || ! class_exists( 'SimpleVPBot_UI_Action_Registry' ) )
			? array( 'version' => 0, 'surfaces' => array() )
			: SimpleVPBot_UI_Action_Registry::export_for_dashboard();

		$unit_economics      = null;
		$panel_economics_map = null;
		if ( ! $reseller_mode && class_exists( 'SimpleVPBot_Unit_Economics_Calculator' ) ) {
			if ( in_array( $active_tab, array( 'unit_economics', 'xui_panels' ), true ) ) {
				$panel_economics_map = SimpleVPBot_Unit_Economics_Calculator::panel_economics_map_for_rest();
			}
			if ( 'unit_economics' === $active_tab ) {
				$unit_economics = SimpleVPBot_Unit_Economics_Calculator::calculate_from_db();
			} elseif ( 'xui_panels' === $active_tab && class_exists( 'SimpleVPBot_Model_Unit_Economics_Config' ) ) {
				$global_in    = SimpleVPBot_Model_Unit_Economics_Config::global_inputs();
				$config_clean = SimpleVPBot_Unit_Economics_Calculator::sanitize_global_config( $global_in );
				$sales        = SimpleVPBot_Unit_Economics_Calculator::sales_volume_snapshot( $config_clean );
				$site_volume  = SimpleVPBot_Unit_Economics_Calculator::resolve_volume_for_panel( 0, $config_clean, $sales );
				$unit_economics = array(
					'inputs' => array(
						'total_sold_volume_gb'   => $site_volume,
						'effective_volume_gb'    => $site_volume,
						'selling_price_per_gb'   => $global_in['selling_price_per_gb'],
						'volume_mode'            => $global_in['volume_mode'],
						'volume_window_days'     => $global_in['volume_window_days'],
						'sales_volume_gb_30d'    => $sales['total_gb'] ?? 0,
					),
					'salesVolume' => $sales,
				);
			}
		}

		$payload = array(
			'settings'                 => $settings,
			'paymentMethods'           => class_exists( 'SimpleVPBot_Payment_Methods' )
				? SimpleVPBot_Payment_Methods::dashboard_payload( $reseller_mode ? $actor_uid : 0 )
				: null,
			'textDefaults'             => $text_defaults,
			'uiLayout'                 => $ui_layout,
			'uiRegistry'               => $ui_registry,
			'referralStats'            => $referral_stats,
			'referralEvents'           => $referral_events,
			'resellerReportsStats'       => $reseller_reports_stats,
			'resellerReportsRows'        => $reseller_reports_rows,
			'resellerReportsDaily'       => $reseller_reports_daily,
			'marketingLifecycleStats'    => $marketing_lifecycle_stats,
			'marketingLifecycleFunnel'   => $marketing_lifecycle_funnel,
			'marketingRules'             => $marketing_rules,
			'marketingRuleStats'         => $marketing_rule_stats,
			'marketingOffers'            => $marketing_offers,
			'panels'                   => $panels,
			'plans'                    => $plans,
			'planCategories'           => $plan_cats,
			'cards'                    => $cards,
			'l2tpServers'              => $l2tp,
			'texts'                    => $texts,
			'discountCodes'            => $discounts,
			'discountUsageSummary'     => $discount_usage_summary,
			'pendingUsers'             => array_map(
				static function ( $u ) {
					$row = self::row_array( $u );
					return is_array( $row ) ? self::sanitize_user_row_for_dashboard( $row ) : null;
				},
				(array) $pending_users
			),
			'usersList'                => self::users_list_rows_for_dashboard( (array) $users_list ),
			'resellers'                => array_map(
				static function ( $r ) {
					$row = self::row_array( $r );
					return is_array( $row ) ? self::sanitize_user_row_for_dashboard( $row ) : null;
				},
				(array) $resellers
			),
			'resellerPermissionsMap'   => $reseller_permissions_map,
			'resellerPanelPricesMap'   => $reseller_panel_prices_map,
			'resellerBotMap'           => $reseller_bot_map,
			'wholesaleCatalogByPanel'  => self::dashboard_rest_is_unrestricted_site_admin() && class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' )
				? SimpleVPBot_Model_Reseller_Panel_Price::site_wholesale_catalog_by_panel()
				: array(),
			'wholesaleLinesCatalog'    => $wholesale_lines_catalog,
			'wholesaleLines'           => $reseller_wholesale_lines_payload,
			'resellerWholesaleLineIdsMap' => $reseller_wholesale_line_ids_map,
			'botsList'                 => $bots_list_payload,
			'receipts'                 => array_values( array_filter( array_map( array( __CLASS__, 'format_receipt_for_dashboard' ), (array) $receipts ) ) ),
			'receiptAggregates'        => $receipt_aggregates,
			'broadcasts'               => array_map( array( __CLASS__, 'row_array' ), (array) $broadcasts ),
			'broadcastQueueAggregates' => $broadcast_queue_aggregates,
			'wpPages'                  => $page_choices,
			'navTabs'                  => $nav_tabs,
			'overview'                 => $overview,
			'monitorHosts'             => $monitor_hosts_pub,
			'unitEconomics'            => $unit_economics,
			'panelEconomicsMap'        => $panel_economics_map,
			'pagination'               => $pagination,
			'resellerContextId'        => $owner_ctx > 0 ? $owner_ctx : 0,
			'resellerOverviewMetrics'  => $reseller_overview_metrics,
		);
		if ( $reseller_mode ) {
			$payload['resellerAllowedTabs'] = self::reseller_dashboard_allowed_tabs_map(
				$actor_uid,
				is_array( $reseller_actor_perms ) ? $reseller_actor_perms : null
			);
			$payload['actorPermissions']    = is_array( $reseller_actor_perms )
				? $reseller_actor_perms
				: SimpleVPBot_Model_User::reseller_permissions( $actor_uid );
			$payload['resellerPlanFloors'] = $reseller_plan_floors;
			if ( $actor_uid > 0 && empty( $panels ) && class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' ) ) {
				$payload['resellerPanelAccessDiagnostics'] = SimpleVPBot_Model_Reseller_Panel_Price::access_diagnostics( $actor_uid );
			}
			if ( $actor_uid > 0 && class_exists( 'SimpleVPBot_Portal_Link' ) ) {
				$payload['portalAdminUrl'] = (string) SimpleVPBot_Portal_Link::build_admin_url( $actor_uid );
			}
			if ( $needs_reseller_customer_charges && $actor_uid > 0 ) {
				$reseller_customer_charges = self::build_reseller_customer_charges( $actor_uid, $moderatable_user_ids, $req );
				$payload['resellerCustomerCharges']           = $reseller_customer_charges['rows'];
				$payload['resellerCustomerChargesPagination'] = $reseller_customer_charges['pagination'];
			}
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
			if ( class_exists( 'SimpleVPBot_Model_User' ) && SimpleVPBot_Model_User::is_reseller_row( $row ) ) {
				$ua = self::sanitize_user_row_for_dashboard( $ua );
			}
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
		$scope_ids = null;
		if ( ! empty( $ctx['isReseller'] ) ) {
			$actor     = (int) $ctx['actorUserId'];
			$scope_ids = class_exists( 'SimpleVPBot_Bot_Reseller_Scope' )
				? SimpleVPBot_Bot_Reseller_Scope::effective_moderatable_user_ids( $actor )
				: SimpleVPBot_Model_User::reseller_scope_user_ids( $actor );
		} elseif ( (int) ( $ctx['impersonationTargetId'] ?? 0 ) > 0 && class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			$scope_ids = SimpleVPBot_Bot_Reseller_Scope::effective_moderatable_user_ids( (int) $ctx['impersonationTargetId'] );
		} else {
			$owner_ctx = (int) $req->get_param( 'resellerContextId' );
			if ( $owner_ctx > 0 ) {
				$validated = self::validate_reseller_context_id( $owner_ctx );
				if ( null === $validated ) {
					return new WP_REST_Response( array( 'ok' => false, 'message' => 'invalid_reseller_context' ), 400 );
				}
				$owner_ctx = $validated;
				if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
					$scope_ids = SimpleVPBot_Bot_Reseller_Scope::effective_moderatable_user_ids( $owner_ctx );
				}
			}
		}
		if ( is_array( $scope_ids ) ) {
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
				$users[] = self::sanitize_user_row_for_dashboard( $ra );
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
		if ( ! self::dashboard_actor_may_read_user( $ctx, $id ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden' ), 403 );
		}
		$svcs = SimpleVPBot_Model_Service::by_user( $id );
		if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) ) {
			$svcs = SimpleVPBot_Feature_L2tp::filter_services( (array) $svcs );
		}
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
			$lr['panel_remark'] = self::admin_user_service_panel_remark( $lr );
			if ( class_exists( 'SimpleVPBot_Service_Naming' ) ) {
				$lr['subscription_id']   = '';
				$lr['subscription_name'] = SimpleVPBot_Service_Naming::canonical_label_for_service( $lr );
				$lr['display_label']     = SimpleVPBot_Service_Naming::public_label_for_service( $lr );
			} else {
				$lr['subscription_id']   = '';
				$lr['subscription_name'] = trim( (string) ( $lr['remark'] ?? '' ) );
				$lr['display_label']     = '';
			}
		}
		unset( $lr );
		$referrals = array();
		if ( class_exists( 'SimpleVPBot_Model_User' ) ) {
			foreach ( SimpleVPBot_Model_User::list_invited_by( $id, 150 ) as $ref ) {
				$ra = self::row_array( $ref );
				if ( $ra && self::dashboard_actor_may_read_user( $ctx, (int) ( $ra['id'] ?? 0 ) ) ) {
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
		if ( is_array( $user_arr ) ) {
			$user_arr['effective_role'] = self::admin_user_effective_role( $user );
		}
		$dash_locale = sanitize_key( (string) $req->get_param( 'lang' ) );
		if ( ! in_array( $dash_locale, array( 'fa', 'en' ), true ) ) {
			$dash_locale = class_exists( 'SimpleVPBot_Texts' ) ? SimpleVPBot_Texts::site_default_locale() : 'fa';
		}
		if ( class_exists( 'SimpleVPBot_Activity_Callback_Label' ) && ! empty( $act['rows'] ) && is_array( $act['rows'] ) ) {
			foreach ( $act['rows'] as &$act_row ) {
				if ( ! is_array( $act_row ) ) {
					continue;
				}
				$display = SimpleVPBot_Activity_Callback_Label::activity_summary_display( $act_row, $dash_locale );
				if ( '' !== $display ) {
					$act_row['summary_display'] = $display;
				}
			}
			unset( $act_row );
		}
		$reseller_choices = array();
		if ( empty( $ctx['isReseller'] ) && current_user_can( 'manage_options' ) ) {
			global $wpdb;
			$u_tbl = SimpleVPBot_Model_User::table();
			$res_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, first_name, last_name, username FROM {$u_tbl} WHERE role = %s ORDER BY id DESC LIMIT %d",
					'reseller',
					100
				),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( (array) $res_rows as $rr ) {
				if ( ! is_array( $rr ) ) {
					continue;
				}
				$rid = (int) ( $rr['id'] ?? 0 );
				if ( $rid < 1 ) {
					continue;
				}
				$lbl = trim( (string) ( $rr['first_name'] ?? '' ) . ' ' . (string) ( $rr['last_name'] ?? '' ) );
				if ( '' === $lbl ) {
					$lbl = (string) ( $rr['username'] ?? '' );
				}
				if ( '' === $lbl ) {
					$lbl = '#' . $rid;
				}
				$reseller_choices[] = array(
					'id'    => $rid,
					'label' => $lbl,
				);
			}
			$ib = (int) ( $user->invited_by ?? 0 );
			if ( $ib > 0 && is_array( $user_arr ) ) {
				$parent = SimpleVPBot_Model_User::find( $ib );
				if ( $parent ) {
					$user_arr['invited_by_label'] = SimpleVPBot_Model_User::label( $parent );
				}
			}
		}
		$detail_plan_cats = array();
		if ( class_exists( 'SimpleVPBot_Model_Plan_Category' ) ) {
			global $wpdb;
			$t_pc_detail = SimpleVPBot_Model_Plan_Category::table();
			$pc_limit    = 500;
			if ( ! empty( $ctx['isReseller'] ) && (int) ( $ctx['actorUserId'] ?? 0 ) > 0 && class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' ) ) {
				$allowed = array();
				foreach ( (array) SimpleVPBot_Model_Reseller_Panel_Price::list_for_reseller( (int) $ctx['actorUserId'] ) as $rp ) {
					if ( SimpleVPBot_Model_Reseller_Panel_Price::row_allows_panel_use( $rp ) ) {
						$allowed[] = (int) ( $rp->panel_id ?? 0 );
					}
				}
				$allowed = array_values( array_unique( array_filter( $allowed ) ) );
				if ( ! empty( $allowed ) ) {
					$pc_in_ph = implode( ',', array_fill( 0, count( $allowed ), '%d' ) );
					$pc_rows  = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT * FROM {$t_pc_detail} WHERE panel_id IN ({$pc_in_ph}) ORDER BY panel_id ASC, sort_order ASC, id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
							array_merge( $allowed, array( $pc_limit ) )
						)
					);
				} else {
					$pc_rows = array();
				}
			} else {
				$pc_rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$t_pc_detail} ORDER BY panel_id ASC, sort_order ASC, id ASC LIMIT %d",
						$pc_limit
					)
				);
			}
			foreach ( (array) ( $pc_rows ?? array() ) as $pcr ) {
				$pra = self::row_array( $pcr );
				if ( $pra ) {
					$detail_plan_cats[] = $pra;
				}
			}
		}

		$user_receipts            = array();
		$user_receipt_aggregates  = array();
		$p_user_rcpt              = self::dash_list_pagination( $req, 'receipts', 40 );
		$tot_user_rcpt            = 0;
		if ( class_exists( 'SimpleVPBot_Model_Receipt' ) ) {
			global $wpdb;
			$rcpt_t         = SimpleVPBot_Model_Receipt::table();
			$u_tbl          = SimpleVPBot_Model_User::table();
			$rcpt_scope_sql = ' AND r.user_id = ' . absint( $id );
			$rcpt_filter    = self::receipt_admin_filter_from_request( $req );
			$tot_user_rcpt  = self::receipt_admin_count( $wpdb, $rcpt_t, $u_tbl, $rcpt_scope_sql, $rcpt_filter );
			$raw_user_rcpts = self::receipt_admin_select(
				$wpdb,
				$rcpt_t,
				$u_tbl,
				$rcpt_scope_sql,
				$rcpt_filter,
				$p_user_rcpt['per_page'],
				$p_user_rcpt['offset']
			);
			$user_receipts = array_values(
				array_filter(
					array_map(
						array( __CLASS__, 'format_receipt_for_dashboard' ),
						(array) $raw_user_rcpts
					)
				)
			);
			$agg_rows = self::receipt_admin_aggregate_rows( $wpdb, $rcpt_t, $u_tbl, $rcpt_scope_sql, $rcpt_filter );
			foreach ( (array) $agg_rows as $ar ) {
				if ( ! is_array( $ar ) ) {
					continue;
				}
				$user_receipt_aggregates[] = array(
					'status'    => (string) ( $ar['status'] ?? '' ),
					'count'     => (int) ( $ar['cnt'] ?? 0 ),
					'sumAmount' => (float) ( $ar['sum_amount'] ?? 0 ),
				);
			}
		}

		$marketing_offers_user = array();
		if ( class_exists( 'SimpleVPBot_Model_Marketing_Offer' ) ) {
			$can_mkt = current_user_can( 'manage_options' );
			if ( ! $can_mkt && ! empty( $ctx['isReseller'] ) ) {
				$rp = SimpleVPBot_Model_User::reseller_permissions( (int) ( $ctx['actorUserId'] ?? 0 ) );
				$can_mkt = ! empty( $rp['marketing.lifecycle'] );
			}
			if ( $can_mkt ) {
				$marketing_offers_user = SimpleVPBot_Model_Marketing_Offer::list_for_user( $id, 15 );
			}
		}

		if ( is_array( $user_arr ) ) {
			$user_arr = self::sanitize_user_row_for_dashboard( $user_arr );
		}
		$referrals = array_values(
			array_filter(
				array_map(
					static function ( $row ) {
						return is_array( $row ) ? self::sanitize_user_row_for_dashboard( $row ) : null;
					},
					$referrals
				)
			)
		);

		return new WP_REST_Response(
			array(
				'ok'                 => true,
				'user'               => $user_arr,
				'services'           => $list,
				'referrals'          => $referrals,
				'marketingOffers'    => $marketing_offers_user,
				'portalBaseUrl'      => $portal_base,
				'resellerChoices'    => $reseller_choices,
				'planCategories'     => $detail_plan_cats,
				'receipts'           => $user_receipts,
				'receiptAggregates'  => $user_receipt_aggregates,
				'receiptsPagination' => self::dash_pagination_meta( $p_user_rcpt['page'], $p_user_rcpt['per_page'], $tot_user_rcpt ),
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
	 * Effective dashboard role: user | reseller | admin.
	 *
	 * @param object $user svp_users row.
	 * @return string
	 */
	private static function admin_user_effective_role( $user ) {
		if ( ! is_object( $user ) ) {
			return 'user';
		}
		if ( SimpleVPBot_Model_User::is_reseller_row( $user ) ) {
			return 'reseller';
		}
		$tg = (int) ( $user->tg_user_id ?? 0 );
		$bl = (int) ( $user->bale_user_id ?? 0 );
		if ( class_exists( 'SimpleVPBot_Settings' ) ) {
			$all = SimpleVPBot_Settings::all();
			$tgs = array_map( 'intval', (array) ( $all['admin_telegram_ids'] ?? array() ) );
			$bls = array_map( 'intval', (array) ( $all['admin_bale_ids'] ?? array() ) );
			if ( ( $tg > 0 && in_array( $tg, $tgs, true ) ) || ( $bl > 0 && in_array( $bl, $bls, true ) ) ) {
				return 'admin';
			}
		}
		$wp_id = (int) ( $user->wp_user_id ?? 0 );
		if ( $wp_id > 0 && user_can( $wp_id, 'manage_options' ) ) {
			return 'admin';
		}
		return 'user';
	}

	/**
	 * Cached panel client remark for admin user service row.
	 *
	 * @param array<string, mixed> $svc Service row array.
	 * @return string
	 */
	private static function admin_user_service_panel_remark( array $svc ) {
		global $wpdb;
		$pid = (int) ( $svc['panel_id'] ?? 0 );
		$iid = (int) ( $svc['inbound_id'] ?? 0 );
		$em  = trim( (string) ( $svc['email'] ?? '' ) );
		if ( $pid < 1 || $iid < 1 || '' === $em ) {
			return '';
		}
		$t = $wpdb->prefix . 'svp_panel_inbound_clients';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT remark FROM {$t} WHERE panel_id = %d AND inbound_id = %d AND email = %s LIMIT 1",
				$pid,
				$iid,
				$em
			)
		);
		return is_string( $row ) ? trim( $row ) : '';
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
	 * Cached inbound list for display-name settings (no live panel login).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function route_inbound_display_catalog( WP_REST_Request $req ) {
		$panel_id = (int) $req->get_param( 'panel_id' );
		if ( $panel_id < 1 ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'invalid_panel' ), 400 );
		}
		if ( ! self::actor_can_read_panel_inbound_catalog( $panel_id ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden' ), 403 );
		}
		if ( ! class_exists( 'SimpleVPBot_Config_Inbound_Match' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'module_missing' ), 500 );
		}
		$list = SimpleVPBot_Config_Inbound_Match::inbound_catalog_for_panel( $panel_id );
		return new WP_REST_Response(
			array(
				'ok'   => true,
				'data' => array( 'inbounds' => $list ),
			),
			200
		);
	}

	/**
	 * Site admin or reseller allowed to sell on panel.
	 *
	 * @param int $panel_id Panel id.
	 * @return bool
	 */
	private static function actor_can_read_panel_inbound_catalog( $panel_id ) {
		$pid = (int) $panel_id;
		if ( $pid < 1 ) {
			return false;
		}
		if ( self::dashboard_rest_is_unrestricted_site_admin() ) {
			return true;
		}
		$ctx   = self::dashboard_actor_context();
		$actor = (int) ( $ctx['actorUserId'] ?? 0 );
		if ( $actor < 1 || ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return false;
		}
		return SimpleVPBot_Bot_Reseller_Scope::reseller_can_sell_on_panel_for( $actor, $pid );
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
	 * Paginated plugin logs (WP admin only).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	/**
	 * Paginated audit log (site admin only).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	/**
	 * Audit log entry after successful backup restore.
	 *
	 * @param string $source   site_file|upload.
	 * @param string $filename Optional basename.
	 */
	private static function audit_backup_restore( $source, $filename = '' ) {
		if ( ! class_exists( 'SimpleVPBot_Audit_Log' ) ) {
			return;
		}
		$actor = SimpleVPBot_Audit_Log::current_actor_fields();
		SimpleVPBot_Audit_Log::record(
			array_merge(
				$actor,
				array(
					'domain'      => 'admin',
					'event_type'  => 'backup.restore',
					'target_type' => 'backup',
					'payload'     => array(
						'source'   => sanitize_key( (string) $source ),
						'filename' => sanitize_file_name( (string) $filename ),
					),
				)
			)
		);
	}

	/**
	 * List on-site stored backup zips.
	 *
	 * @return WP_REST_Response
	 */
	public static function route_admin_backups() {
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'no_module' ), 500 );
		}
		$res = SimpleVPBot_Service_Admin_Ops::list_site_backups();
		return new WP_REST_Response( $res, 200 );
	}

	/**
	 * Run backup job now.
	 *
	 * @return WP_REST_Response
	 */
	public static function route_admin_backup_run() {
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'no_module' ), 500 );
		}
		try {
			$res = SimpleVPBot_Service_Admin_Ops::backup_now_start_async();
			// Always 200 for logical outcomes so proxies/WAFs do not replace JSON with HTML error pages.
			return new WP_REST_Response( $res, 200 );
		} catch ( Throwable $e ) { // phpcs:ignore
			return new WP_REST_Response(
				array(
					'ok'      => false,
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	/**
	 * Poll async manual backup status.
	 *
	 * @return WP_REST_Response
	 */
	public static function route_admin_backup_status() {
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'no_module' ), 500 );
		}
		$res = SimpleVPBot_Service_Admin_Ops::get_manual_backup_status_api();
		return new WP_REST_Response( $res, 200 );
	}

	/**
	 * Clear stuck manual backup locks (dashboard).
	 *
	 * @return WP_REST_Response
	 */
	public static function route_admin_backup_reset_stuck() {
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'no_module' ), 500 );
		}
		$res = SimpleVPBot_Service_Admin_Ops::reset_backup_stuck_locks();
		return new WP_REST_Response( $res, 200 );
	}

	/**
	 * Download a site-stored backup zip (streams file; large zips).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function route_admin_backup_download( WP_REST_Request $req ) {
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'no_module' ), 500 );
		}
		$filename = sanitize_file_name( (string) $req->get_param( 'filename' ) );
		if ( '' === $filename ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'missing_filename' ), 400 );
		}
		$path = SimpleVPBot_Service_Admin_Ops::resolve_site_backup_download_path( $filename );
		if ( '' === $path || ! is_readable( $path ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'not_found' ), 404 );
		}
		$size = (int) @filesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		add_filter(
			'rest_pre_serve_request',
			static function ( $served, $result, $request, $server ) use ( $path, $filename, $size ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				if ( ! $request instanceof WP_REST_Request ) {
					return $served;
				}
				$route = (string) $request->get_route();
				if ( false === strpos( $route, '/dashboard/admin/backup/download' ) ) {
					return $served;
				}
				if ( function_exists( 'nocache_headers' ) ) {
					nocache_headers();
				}
				header( 'Content-Type: application/zip' );
				header( 'Content-Disposition: attachment; filename="' . basename( $filename ) . '"' );
				if ( $size > 0 ) {
					header( 'Content-Length: ' . (string) $size );
				}
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
				readfile( $path );
				return true;
			},
			10,
			4
		);
		return new WP_REST_Response( null, 200 );
	}

	/**
	 * Restore from a site-stored backup file.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function route_admin_backup_restore( WP_REST_Request $req ) {
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'no_module' ), 500 );
		}
		$params = $req->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$filename         = isset( $params['filename'] ) ? (string) $params['filename'] : '';
		$confirm          = ! empty( $params['confirm'] );
		$restore_panel_db = ! empty( $params['restore_panel_db'] );
		$res              = SimpleVPBot_Service_Admin_Ops::restore_site_backup_file( $filename, $confirm, $restore_panel_db );
		if ( ! empty( $res['ok'] ) ) {
			self::audit_backup_restore( 'site_file', $filename );
		}
		$code = ! empty( $res['ok'] ) ? 200 : 400;
		return new WP_REST_Response( $res, $code );
	}

	/**
	 * Restore from uploaded backup zip.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function route_admin_backup_restore_upload( WP_REST_Request $req ) {
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) || ! class_exists( 'SimpleVPBot_Backup_Export' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'no_module' ), 500 );
		}
		$confirm = $req->get_param( 'confirm' );
		$confirm_ok = ! empty( $confirm ) && ( true === $confirm || 1 === $confirm || '1' === (string) $confirm );
		if ( ! $confirm_ok ) {
			return new WP_REST_Response(
				array( 'ok' => false, 'message' => __( 'برای ریستور باید تایید شود.', 'simplevpbot' ) ),
				400
			);
		}
		$restore_panel_param = $req->get_param( 'restore_panel_db' );
		$restore_panel_db    = ! empty( $restore_panel_param ) && ( true === $restore_panel_param || 1 === $restore_panel_param || '1' === (string) $restore_panel_param );
		$files = $req->get_file_params();
		if ( empty( $files['file'] ) || ! is_array( $files['file'] ) ) {
			return new WP_REST_Response(
				array( 'ok' => false, 'message' => __( 'فایلی ارسال نشده است.', 'simplevpbot' ) ),
				400
			);
		}
		$f = $files['file'];
		if ( ! empty( $f['error'] ) || UPLOAD_ERR_OK !== (int) $f['error'] ) {
			return new WP_REST_Response(
				array( 'ok' => false, 'message' => __( 'خطای آپلود فایل.', 'simplevpbot' ) ),
				400
			);
		}
		$name = isset( $f['name'] ) ? (string) $f['name'] : '';
		if ( 'zip' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			return new WP_REST_Response(
				array( 'ok' => false, 'message' => __( 'فقط فایل .zip مجاز است.', 'simplevpbot' ) ),
				400
			);
		}
		$tmp  = isset( $f['tmp_name'] ) ? (string) $f['tmp_name'] : '';
		$dest = SimpleVPBot_Backup_Export::base_tmp_dir() . 'restore-' . wp_generate_password( 12, false, false ) . '.zip';
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) || ! @move_uploaded_file( $tmp, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return new WP_REST_Response(
				array( 'ok' => false, 'message' => __( 'ذخیرهٔ موقت فایل ناموفق بود.', 'simplevpbot' ) ),
				500
			);
		}
		$res = SimpleVPBot_Service_Admin_Ops::restore_from_zip_path( $dest, true, $restore_panel_db );
		@unlink( $dest ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! empty( $res['ok'] ) ) {
			self::audit_backup_restore( 'upload', $name );
		}
		$code = ! empty( $res['ok'] ) ? 200 : 400;
		return new WP_REST_Response( $res, $code );
	}

	/**
	 * Bulk set panel client traffic quota.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function route_admin_panel_fix_51200_traffic( WP_REST_Request $req ) {
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'no_module' ), 500 );
		}
		$params = $req->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$res  = SimpleVPBot_Service_Admin_Ops::panel_fix_51200_traffic( $params );
		$code = ! empty( $res['ok'] ) ? 200 : 400;
		return new WP_REST_Response( $res, $code );
	}

	/**
	 * Inbound map context (DB inbounds vs live panel).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function route_admin_panel_inbound_map_get( WP_REST_Request $req ) {
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'no_module' ), 500 );
		}
		$res  = SimpleVPBot_Service_Admin_Ops::panel_inbound_map_context( (int) $req->get_param( 'panel_id' ) );
		$code = ! empty( $res['ok'] ) ? 200 : 400;
		return new WP_REST_Response( $res, $code );
	}

	/**
	 * Save inbound id map.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function route_admin_panel_inbound_map_save( WP_REST_Request $req ) {
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'no_module' ), 500 );
		}
		$params = $req->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$res  = SimpleVPBot_Service_Admin_Ops::panel_inbound_map_save( $params );
		$code = ! empty( $res['ok'] ) ? 200 : 400;
		return new WP_REST_Response( $res, $code );
	}

	/**
	 * Rebuild panel Xray clients from svp_services (batched).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function route_admin_panel_rebuild_from_db( WP_REST_Request $req ) {
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'no_module' ), 500 );
		}
		$params = $req->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$rebuild_args = array(
			'confirm'       => ! empty( $params['confirm'] ),
			'dry_run'       => ! empty( $params['dry_run'] ),
			'panel_id'      => isset( $params['panel_id'] ) ? (int) $params['panel_id'] : 0,
			'offset'        => isset( $params['offset'] ) ? (int) $params['offset'] : 0,
			'finalize_sync' => ! empty( $params['finalize_sync'] ),
		);
		if ( isset( $params['inbound_map'] ) && is_array( $params['inbound_map'] ) ) {
			$rebuild_args['inbound_map'] = $params['inbound_map'];
		}
		$res = SimpleVPBot_Service_Admin_Ops::panel_rebuild_from_db( $rebuild_args );
		if ( ! empty( $res['ok'] ) && empty( $res['dry_run'] ) && ! empty( $params['confirm'] ) ) {
			self::audit_panel_rebuild_from_db( $res );
		}
		$code = ! empty( $res['ok'] ) ? 200 : 400;
		return new WP_REST_Response( $res, $code );
	}

	/**
	 * @param array<string, mixed> $res Rebuild batch result.
	 */
	private static function audit_panel_rebuild_from_db( array $res ) {
		if ( ! class_exists( 'SimpleVPBot_Audit_Log' ) ) {
			return;
		}
		$totals = isset( $res['totals'] ) && is_array( $res['totals'] ) ? $res['totals'] : array();
		$actor  = SimpleVPBot_Audit_Log::current_actor_fields();
		SimpleVPBot_Audit_Log::record(
			array_merge(
				$actor,
				array(
					'domain'      => 'admin',
					'event_type'  => 'panel.rebuild_from_db',
					'target_type' => 'panel',
					'payload'     => array(
						'totals'      => $totals,
						'done'        => ! empty( $res['done'] ),
						'next_offset' => (int) ( $res['next_offset'] ?? 0 ),
						'total'       => (int) ( $res['total'] ?? 0 ),
					),
				)
			)
		);
	}

	public static function route_admin_audit( WP_REST_Request $req ) {
		if ( ! class_exists( 'SimpleVPBot_Audit_Log' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'no_model' ), 500 );
		}
		$page = max( 1, (int) $req->get_param( 'page' ) );
		$per  = max( 1, min( 100, (int) $req->get_param( 'per_page' ) ) );
		$res  = SimpleVPBot_Audit_Log::query(
			array(
				'domain'     => (string) $req->get_param( 'domain' ),
				'event_type' => (string) $req->get_param( 'event_type' ),
				'q'          => (string) $req->get_param( 'q' ),
			),
			$page,
			$per
		);
		return new WP_REST_Response(
			array(
				'ok'         => true,
				'rows'       => $res['rows'],
				'pagination' => array(
					'page'    => $page,
					'perPage' => $per,
					'total'   => (int) $res['total'],
				),
			),
			200
		);
	}

	public static function route_admin_purge_expired( WP_REST_Request $req ) {
		if ( ! class_exists( 'SimpleVPBot_Cron_Purge_Expired' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'no_module' ), 500 );
		}
		$data = SimpleVPBot_Cron_Purge_Expired::list_expired_candidates(
			array(
				'page'     => (int) $req->get_param( 'page' ),
				'per_page' => (int) $req->get_param( 'per_page' ),
				'status'   => (string) $req->get_param( 'status' ),
			)
		);
		$panel_id = (int) $req->get_param( 'panel_id' );
		$immediate = array(
			'count' => 0,
			'ids'   => array(),
		);
		if ( $panel_id > 0 && class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			$immediate = SimpleVPBot_Service_Admin_Ops::expired_linked_preview( $panel_id, 50 );
		}
		return new WP_REST_Response(
			array(
				'ok'                  => true,
				'items'               => $data['items'],
				'totals'              => $data['totals'],
				'pagination'          => $data['pagination'],
				'settings'            => $data['settings'],
				'immediate_batch'     => $immediate,
			),
			200
		);
	}

	public static function route_admin_logs( WP_REST_Request $req ) {
		if ( ! class_exists( 'SimpleVPBot_Model_Log' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => 'no_model' ), 500 );
		}
		$page = max( 1, (int) $req->get_param( 'page' ) );
		$per  = max( 1, min( 100, (int) $req->get_param( 'per_page' ) ) );
		$res  = SimpleVPBot_Model_Log::list(
			$page,
			$per,
			(string) $req->get_param( 'level' ),
			(string) $req->get_param( 'q' )
		);
		return new WP_REST_Response(
			array(
				'ok'         => true,
				'rows'       => $res['rows'],
				'pagination' => array(
					'page'    => $page,
					'perPage' => $per,
					'total'   => (int) $res['total'],
				),
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
		$item_aggregates = array();
		if ( ! empty( $rows ) && is_array( $rows ) ) {
			$job_ids = array();
			foreach ( $rows as $_jr ) {
				if ( is_array( $_jr ) && isset( $_jr['id'] ) ) {
					$job_ids[] = (int) $_jr['id'];
				} elseif ( is_object( $_jr ) && isset( $_jr->id ) ) {
					$job_ids[] = (int) $_jr->id;
				}
			}
			$stats_rows = SimpleVPBot_Model_Users_Bulk_Job::item_status_counts_by_jobs( $job_ids );
			foreach ( (array) $stats_rows as $sr ) {
				if ( ! is_array( $sr ) ) {
					continue;
				}
				$item_aggregates[] = array(
					'jobId'  => (int) ( $sr['job_id'] ?? 0 ),
					'status' => (string) ( $sr['status'] ?? '' ),
					'count'  => (int) ( $sr['cnt'] ?? 0 ),
				);
			}
		}
		return new WP_REST_Response(
			array(
				'ok'             => true,
				'jobs'           => is_array( $rows ) ? $rows : array(),
				'itemAggregates' => $item_aggregates,
				'pagination'     => array(
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
			if ( $target_uid > 0 && class_exists( 'SimpleVPBot_Bot_Reseller_Scope' )
				&& ! SimpleVPBot_Bot_Reseller_Scope::reseller_may_moderate_user_for( (int) $ctx['actorUserId'], $target_uid ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden_scope' ), 403 );
			}
			$service_id = isset( $params['service_id'] ) ? (int) $params['service_id'] : 0;
			$service_ids = array();
			if ( $service_id > 0 ) {
				$service_ids[] = $service_id;
			}
			if ( isset( $params['service_ids'] ) && is_array( $params['service_ids'] ) ) {
				foreach ( $params['service_ids'] as $raw_sid ) {
					$n = (int) $raw_sid;
					if ( $n > 0 ) {
						$service_ids[] = $n;
					}
				}
			}
			$service_ids = array_values( array_unique( $service_ids ) );
			foreach ( $service_ids as $check_sid ) {
				$svc = SimpleVPBot_Model_Service::find_any( $check_sid );
				$svc_uid = $svc ? (int) ( $svc->user_id ?? 0 ) : 0;
				if ( $svc_uid < 1 || ! SimpleVPBot_Bot_Reseller_Scope::reseller_may_moderate_user_for( (int) $ctx['actorUserId'], $svc_uid ) ) {
					return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden_scope' ), 403 );
				}
			}
			if ( 'user_service_transfer' === $op && class_exists( 'SimpleVPBot_Service_Transfer' ) ) {
				$tgt_raw = isset( $params['target'] ) ? trim( (string) $params['target'] ) : '';
				if ( '' !== $tgt_raw ) {
					$transfer_target = SimpleVPBot_Service_Transfer::resolve_user( $tgt_raw );
					if ( ! $transfer_target
						|| ! SimpleVPBot_Bot_Reseller_Scope::reseller_may_moderate_user_for( (int) $ctx['actorUserId'], (int) $transfer_target->id ) ) {
						return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden_scope' ), 403 );
					}
				}
			}
			$receipt_id = isset( $params['receipt_id'] ) ? (int) $params['receipt_id'] : 0;
			if ( $receipt_id < 1 && isset( $params['id'] ) && in_array( $op, array( 'receipt_approve', 'receipt_reject', 'receipt_update' ), true ) ) {
				$receipt_id = (int) $params['id'];
			}
			if ( $receipt_id > 0 && class_exists( 'SimpleVPBot_Model_Receipt' ) ) {
				$rec = SimpleVPBot_Model_Receipt::find( $receipt_id );
				$rec_uid = $rec ? (int) ( $rec->user_id ?? 0 ) : 0;
				if ( $rec_uid < 1 || ! SimpleVPBot_Bot_Reseller_Scope::reseller_may_moderate_user_for( (int) $ctx['actorUserId'], $rec_uid ) ) {
					return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden_scope' ), 403 );
				}
			}
			if ( 'user_manual_create' === $op ) {
				$params['invited_by'] = (int) $ctx['actorUserId'];
			}
			$params['__actor_svp_user_id'] = (int) $ctx['actorUserId'];
			$params['owner_svp_user_id']   = (int) $ctx['actorUserId'];
		}
		if ( self::dashboard_rest_is_unrestricted_site_admin() ) {
			$owner_ctx = isset( $params['reseller_context_svp_user_id'] ) ? (int) $params['reseller_context_svp_user_id'] : 0;
			if ( $owner_ctx > 0 ) {
				$validated = self::validate_reseller_context_id( $owner_ctx );
				if ( null === $validated ) {
					return new WP_REST_Response( array( 'ok' => false, 'message' => 'invalid_reseller_context' ), 400 );
				}
				$owner_ctx = $validated;
				$params['owner_svp_user_id']     = $owner_ctx;
				$params['__actor_svp_user_id']   = $owner_ctx;
				if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
					$target_uid = 0;
					if ( isset( $params['svp_user_id'] ) ) {
						$target_uid = (int) $params['svp_user_id'];
					} elseif ( isset( $params['target_user_id'] ) ) {
						$target_uid = (int) $params['target_user_id'];
					} elseif ( isset( $params['membership_user_id'] ) ) {
						$target_uid = (int) $params['membership_user_id'];
					}
					if ( $target_uid > 0 && ! SimpleVPBot_Bot_Reseller_Scope::reseller_may_moderate_user_for( $owner_ctx, $target_uid ) ) {
						return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden_scope' ), 403 );
					}
					$service_ids = array();
					if ( isset( $params['service_id'] ) ) {
						$service_ids[] = (int) $params['service_id'];
					}
					if ( isset( $params['service_ids'] ) && is_array( $params['service_ids'] ) ) {
						foreach ( $params['service_ids'] as $raw_sid ) {
							$n = (int) $raw_sid;
							if ( $n > 0 ) {
								$service_ids[] = $n;
							}
						}
					}
					$service_ids = array_values( array_unique( $service_ids ) );
					foreach ( $service_ids as $check_sid ) {
						$svc = SimpleVPBot_Model_Service::find_any( $check_sid );
						$svc_uid = $svc ? (int) ( $svc->user_id ?? 0 ) : 0;
						if ( $svc_uid < 1 || ! SimpleVPBot_Bot_Reseller_Scope::reseller_may_moderate_user_for( $owner_ctx, $svc_uid ) ) {
							return new WP_REST_Response( array( 'ok' => false, 'message' => 'forbidden_scope' ), 403 );
						}
					}
				}
			}
		}
		unset( $params['op'] );
		try {
			$res = SimpleVPBot_Dashboard_Admin_Mutations::apply( $op, $params );
		} catch ( \Throwable $e ) {
			if ( class_exists( 'SimpleVPBot_Logger' ) ) {
				SimpleVPBot_Logger::error(
					'dashboard admin mutate exception',
					array(
						'op'      => $op,
						'message' => $e->getMessage(),
					)
				);
			}
			return new WP_REST_Response(
				array(
					'ok'      => false,
					'message' => 'server_error',
				),
				500
			);
		}
		if ( ! is_array( $res ) ) {
			$res = array( 'ok' => false, 'message' => 'invalid_response' );
		}
		if ( false === wp_json_encode( $res ) ) {
			if ( class_exists( 'SimpleVPBot_Logger' ) ) {
				SimpleVPBot_Logger::error( 'dashboard admin mutate response not json encodable', array( 'op' => $op ) );
			}
			$res = array(
				'ok'      => false,
				'message' => 'response_encode_failed',
			);
		}
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
