<?php
/**
 * SPA dashboard at /dashboard (Shadcn UI, REST-backed).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Dashboard_Front
 */
class SimpleVPBot_Dashboard_Front {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_rewrite' ), 5 );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite' ), 20 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect_custom_domain' ), 1 );
		add_action( 'template_redirect', array( __CLASS__, 'render_page' ), 0 );
	}

	/**
	 * On a configured custom domain, send site root to the dashboard SPA.
	 */
	public static function maybe_redirect_custom_domain() {
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( ! class_exists( 'SimpleVPBot_Branding_Resolver' ) ) {
			return;
		}
		$host     = SimpleVPBot_Branding_Resolver::request_host();
		$branding = SimpleVPBot_Branding_Resolver::resolve_for_request();
		$custom   = (string) ( $branding['customDomain'] ?? '' );
		if ( '' === $custom || $host !== $custom ) {
			return;
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			$path = '/';
		}
		if ( preg_match( '#^/dashboard(?:/|$)#', $path ) ) {
			return;
		}
		if ( preg_match( '#^/(wp-admin|wp-content|wp-includes|wp-json)(?:/|$)#', $path ) ) {
			return;
		}
		$scheme = is_ssl() ? 'https://' : 'http://';
		wp_safe_redirect( $scheme . $host . '/dashboard/' );
		exit;
	}

	/**
	 * Branding array for dashboard boot (request host or logged-in actor).
	 *
	 * @param bool $logged_in Whether WP user is logged in.
	 * @return array<string, mixed>
	 */
	private static function branding_boot_payload( $logged_in ) {
		if ( ! class_exists( 'SimpleVPBot_Branding_Resolver' ) ) {
			return array();
		}
		$host     = SimpleVPBot_Branding_Resolver::request_host();
		$branding = SimpleVPBot_Branding_Resolver::resolve_for_request();
		$on_custom = '' !== (string) ( $branding['customDomain'] ?? '' )
			&& $host === (string) $branding['customDomain'];
		if ( $logged_in && ! $on_custom ) {
			$branding = SimpleVPBot_Branding_Resolver::resolve_for_dashboard_actor();
		}
		return $branding;
	}

	/**
	 * Merge branding into boot (siteName, icon, cssVariables).
	 *
	 * @param array<string, mixed> $boot Boot array.
	 * @param bool                 $logged_in Logged in.
	 * @return array<string, mixed>
	 */
	private static function apply_branding_to_boot( array $boot, $logged_in ) {
		$branding = self::branding_boot_payload( $logged_in );
		if ( empty( $branding ) ) {
			return $boot;
		}
		$boot['branding'] = $branding;
		if ( ! empty( $branding['siteName'] ) ) {
			$boot['siteName'] = (string) $branding['siteName'];
		}
		$logo = (string) ( $branding['logoUrl'] ?? '' );
		$fav  = (string) ( $branding['faviconUrl'] ?? '' );
		if ( '' !== $logo ) {
			$boot['siteIconUrl'] = $logo;
		} elseif ( '' !== $fav ) {
			$boot['siteIconUrl'] = $fav;
		}
		return $boot;
	}

	/**
	 * Flush once when dashboard rewrite is added or updated.
	 */
	public static function maybe_flush_rewrite() {
		$v = (int) get_option( 'simplevpbot_svp_dashboard_rw', 0 );
		if ( $v < 3 ) {
			update_option( 'simplevpbot_svp_dashboard_rw', 3 );
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Register /dashboard and nested paths for SPA refresh.
	 */
	public static function register_rewrite() {
		add_rewrite_rule( '^dashboard/?$', 'index.php?svp_dashboard=1', 'top' );
		add_rewrite_rule( '^dashboard/(.*)$', 'index.php?svp_dashboard=1&svp_dash_path=$matches[1]', 'top' );
	}

	/**
	 * @param array<int, string> $vars Vars.
	 * @return array<int, string>
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'svp_dashboard';
		$vars[] = 'svp_dash_path';
		return $vars;
	}

	/**
	 * Whether dash path starts with login (guest SPA shell).
	 *
	 * @param string $dash_path Normalized dash path.
	 * @return bool
	 */
	private static function is_login_dash_path( $dash_path ) {
		$s = trim( str_replace( '\\', '/', (string) $dash_path ), '/' );
		if ( '' === $s ) {
			return false;
		}
		$parts = explode( '/', $s );
		return isset( $parts[0] ) && 'login' === $parts[0];
	}

	/**
	 * Whether dash path is /dashboard/logout/ (SPA logout without wp-login).
	 *
	 * @param string $dash_path Normalized dash path.
	 * @return bool
	 */
	private static function is_logout_dash_path( $dash_path ) {
		$s = trim( str_replace( '\\', '/', (string) $dash_path ), '/' );
		if ( '' === $s ) {
			return false;
		}
		$parts = explode( '/', $s );
		return isset( $parts[0] ) && 'logout' === $parts[0];
	}

	/**
	 * Serve standalone HTML shell for the React app.
	 */
	public static function render_page() {
		if ( 1 !== (int) get_query_var( 'svp_dashboard' ) ) {
			return;
		}
		if ( ! function_exists( 'status_header' ) ) {
			return;
		}

		$dash_path = get_query_var( 'svp_dash_path' );
		$dash_path = is_string( $dash_path ) ? trim( str_replace( '\\', '/', $dash_path ), '/' ) : '';
		$is_login  = self::is_login_dash_path( $dash_path );

		if ( self::is_logout_dash_path( $dash_path ) ) {
			$login_slash = trailingslashit( home_url( '/dashboard/login' ) );
			if ( ! is_user_logged_in() ) {
				wp_safe_redirect( $login_slash );
				exit;
			}
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'simplevpbot_dash_logout' ) ) {
				wp_safe_redirect( home_url( '/dashboard/' ) );
				exit;
			}
			wp_logout();
			wp_safe_redirect( $login_slash );
			exit;
		}

		if ( ! is_user_logged_in() ) {
			if ( ! $is_login ) {
				$scheme = is_ssl() ? 'https://' : 'http://';
				$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_HOST'] ) ) : parse_url( home_url(), PHP_URL_HOST );
				$uri    = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/dashboard/';
				$wanted = $scheme . $host . $uri;
				$validated = wp_validate_redirect( $wanted, home_url( '/dashboard/' ) );
				$login_url = trailingslashit( home_url( '/dashboard/login' ) );
				$go        = add_query_arg( array( 'redirect_to' => $validated ), $login_url );
				wp_safe_redirect( $go );
				exit;
			}
		} elseif ( $is_login ) {
			$rt_raw = isset( $_GET['redirect_to'] ) ? wp_unslash( (string) $_GET['redirect_to'] ) : '';
			$go     = wp_validate_redirect( $rt_raw, home_url( '/dashboard/' ) );
			wp_safe_redirect( $go );
			exit;
		}

		status_header( 200 );
		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'Pragma: no-cache' );
		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );

		$locale = determine_locale();
		$lang   = ( 0 === strpos( $locale, 'fa' ) ) ? 'fa' : 'en';
		if ( is_user_logged_in() && class_exists( 'SimpleVPBot_Rest_Dashboard' ) ) {
			$saved_lang = SimpleVPBot_Rest_Dashboard::dashboard_ui_lang_for_user();
			if ( '' !== $saved_lang ) {
				$lang = $saved_lang;
			}
		}
		$rtl = ( 'fa' === $lang );

		$rest = esc_url_raw( rest_url( 'simplevpbot/v1' ) );
		$tz   = function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : '';

		if ( ! is_user_logged_in() && $is_login ) {
			$boot = array(
				'restUrl'             => $rest,
				'locale'              => $locale,
				'lang'                => $lang,
				'isRtl'               => $rtl,
				'isLoggedIn'          => false,
				'isAdmin'             => false,
				'isReseller'          => false,
				'svpUserId'           => 0,
				'loginNonce'          => wp_create_nonce( 'simplevpbot_dash_login' ),
				'dashboardUrl'        => home_url( '/dashboard/' ),
				'dashboardLoginUrl'   => trailingslashit( home_url( '/dashboard/login' ) ),
				'logoutUrl'           => class_exists( 'SimpleVPBot_Rest_Dashboard' ) ? SimpleVPBot_Rest_Dashboard::dashboard_logout_url() : wp_logout_url( home_url( '/dashboard/' ) ),
				'siteName'            => class_exists( 'SimpleVPBot_Settings' )
					? SimpleVPBot_Settings::dashboard_site_display_name()
					: get_bloginfo( 'name' ),
				'siteIconUrl'         => class_exists( 'SimpleVPBot_Settings' )
					? SimpleVPBot_Settings::dashboard_site_icon_url_resolved()
					: '',
				'pluginUrl'           => SIMPLEVPBOT_PLUGIN_URL,
				'dashPath'            => 'login',
				'siteTimeZone'        => is_string( $tz ) ? $tz : '',
			);
			$boot = self::apply_branding_to_boot( $boot, false );
		} else {
			$ctx         = class_exists( 'SimpleVPBot_Rest_Dashboard' )
				? SimpleVPBot_Rest_Dashboard::dashboard_actor_context()
				: array(
					'isAdmin'             => current_user_can( 'manage_options' ),
					'isReseller'          => false,
					'actorUserId'         => 0,
					'activePersona'       => 'user',
					'availablePersonas'   => array(),
				);
			$is_admin    = ! empty( $ctx['isAdmin'] );
			$is_reseller = ! empty( $ctx['isReseller'] );
			$svp_uid     = (int) ( $ctx['actorUserId'] ?? 0 );
			$actor_perms = null;
			if ( $is_reseller && $svp_uid > 0 && class_exists( 'SimpleVPBot_Model_User' ) ) {
				$actor_perms = SimpleVPBot_Model_User::reseller_permissions( $svp_uid );
			}
			$sidebar_user = null;
			if ( $svp_uid > 0 && class_exists( 'SimpleVPBot_Rest_Dashboard' ) ) {
				$sidebar_user = SimpleVPBot_Rest_Dashboard::sidebar_user_payload( $svp_uid );
			}
			$boot = array(
				'restUrl'                   => $rest,
				'nonce'                     => wp_create_nonce( 'wp_rest' ),
				'locale'                    => $locale,
				'lang'                      => $lang,
				'isRtl'                     => $rtl,
				'isLoggedIn'                => true,
				'isAdmin'                   => $is_admin,
				'isReseller'                => $is_reseller,
				'svpUserId'                 => $svp_uid,
				'user'                      => $sidebar_user,
				'actorPermissions'          => $actor_perms,
				'activePersona'             => isset( $ctx['activePersona'] ) ? (string) $ctx['activePersona'] : 'user',
				'availablePersonas'         => isset( $ctx['availablePersonas'] ) ? array_values( (array) $ctx['availablePersonas'] ) : array(),
				'impersonating'             => ! empty( $ctx['impersonating'] ),
				'impersonationTargetId'     => isset( $ctx['impersonationTargetId'] ) ? (int) $ctx['impersonationTargetId'] : 0,
				'impersonationTargetLabel'  => isset( $ctx['impersonationTargetLabel'] ) ? (string) $ctx['impersonationTargetLabel'] : '',
				'loginUrl'                  => wp_login_url( home_url( '/dashboard/' ) ),
				'dashboardUrl'              => home_url( '/dashboard/' ),
				'dashboardLoginUrl'         => trailingslashit( home_url( '/dashboard/login' ) ),
				'logoutUrl'                 => class_exists( 'SimpleVPBot_Rest_Dashboard' ) ? SimpleVPBot_Rest_Dashboard::dashboard_logout_url() : wp_logout_url( home_url( '/dashboard/' ) ),
				'siteName'                  => class_exists( 'SimpleVPBot_Settings' )
					? SimpleVPBot_Settings::dashboard_site_display_name()
					: get_bloginfo( 'name' ),
				'siteIconUrl'               => class_exists( 'SimpleVPBot_Settings' )
					? SimpleVPBot_Settings::dashboard_site_icon_url_resolved()
					: '',
				'pluginUrl'                 => SIMPLEVPBOT_PLUGIN_URL,
				'dashPath'                  => $dash_path,
				'siteTimeZone'              => is_string( $tz ) ? $tz : '',
				'uiAccent'                  => class_exists( 'SimpleVPBot_Rest_Dashboard' )
					? SimpleVPBot_Rest_Dashboard::dashboard_ui_accent_for_user()
					: 'default',
				'uiTheme'                   => class_exists( 'SimpleVPBot_Rest_Dashboard' )
					? SimpleVPBot_Rest_Dashboard::dashboard_ui_theme_for_user()
					: '',
				'uiSidebar'                 => class_exists( 'SimpleVPBot_Rest_Dashboard' )
					? SimpleVPBot_Rest_Dashboard::dashboard_ui_sidebar_for_user()
					: '',
			);
			$boot = self::apply_branding_to_boot( $boot, true );
		}

		$ui_accent = isset( $boot['uiAccent'] ) && class_exists( 'SimpleVPBot_Rest_Dashboard' )
			? SimpleVPBot_Rest_Dashboard::normalize_dashboard_accent( $boot['uiAccent'] )
			: 'default';
		$skip_accent_branding = 'default' !== $ui_accent;
		$accent_branding_keys = class_exists( 'SimpleVPBot_Rest_Dashboard' )
			? SimpleVPBot_Rest_Dashboard::dashboard_accent_branding_var_keys()
			: array();

		$base      = trailingslashit( SIMPLEVPBOT_PLUGIN_URL ) . 'assets/dashboard/dist/';
		$js        = $base . 'assets/index.js';
		$css       = $base . 'assets/index.css';
		$js_file   = SIMPLEVPBOT_PLUGIN_DIR . 'assets/dashboard/dist/assets/index.js';
		$css_file  = SIMPLEVPBOT_PLUGIN_DIR . 'assets/dashboard/dist/assets/index.css';
		$font_base = trailingslashit( SIMPLEVPBOT_PLUGIN_URL ) . 'assets/fonts/yekan-bakh/';
		$v_raw     = SIMPLEVPBOT_VERSION;
		if ( is_readable( $js_file ) && is_readable( $css_file ) ) {
			$v_raw = (string) max( (int) @filemtime( $js_file ), (int) @filemtime( $css_file ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		$v       = rawurlencode( $v_raw );
		$charset = get_bloginfo( 'charset' );
		$title   = __( 'Bot dashboard', 'simplevpbot' ) . ' | ' . get_bloginfo( 'name' );

		$boot_json = wp_json_encode( $boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );

		echo '<!DOCTYPE html>';
		echo '<html lang="' . esc_attr( $lang ) . '" dir="' . esc_attr( $rtl ? 'rtl' : 'ltr' ) . '" data-accent="' . esc_attr( $ui_accent ) . '">';
		echo '<head><meta charset="' . esc_attr( $charset ) . '"/>';
		echo '<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate" />';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1"/>';
		echo '<meta name="robots" content="noindex, nofollow"/>';
		echo '<title>' . esc_html( $title ) . '</title>';
		echo '<style>';
		echo '@font-face{font-family:"YekanBakh";font-style:normal;font-weight:400;src:url("' . esc_url( $font_base . 'woff2/YekanBakh-Regular.woff2' ) . '") format("woff2"),url("' . esc_url( $font_base . 'woff/YekanBakh-Regular.woff' ) . '") format("woff");}';
		echo '@font-face{font-family:"YekanBakh";font-style:normal;font-weight:600;src:url("' . esc_url( $font_base . 'woff2/YekanBakh-SemiBold.woff2' ) . '") format("woff2"),url("' . esc_url( $font_base . 'woff/YekanBakh-SemiBold.woff' ) . '") format("woff");}';
		echo '@font-face{font-family:"YekanBakh";font-style:normal;font-weight:700;src:url("' . esc_url( $font_base . 'woff2/YekanBakh-Bold.woff2' ) . '") format("woff2"),url("' . esc_url( $font_base . 'woff/YekanBakh-Bold.woff' ) . '") format("woff");}';
		echo 'body.svp-dashboard-body{font-family:"YekanBakh",Tahoma,Arial,sans-serif;}';
		echo '</style>';
		echo '<link rel="stylesheet" href="' . esc_url( $css ) . '?v=' . esc_attr( $v ) . '"/>';
		if ( ! empty( $boot['branding']['cssVariables'] ) && is_array( $boot['branding']['cssVariables'] ) ) {
			echo '<style>:root{';
			foreach ( $boot['branding']['cssVariables'] as $var_key => $var_val ) {
				if ( ! is_string( $var_key ) || ! is_string( $var_val ) || '' === $var_val ) {
					continue;
				}
				if ( $skip_accent_branding && in_array( $var_key, $accent_branding_keys, true ) ) {
					continue;
				}
				echo esc_html( $var_key ) . ':' . esc_html( $var_val ) . ';';
			}
			echo '}</style>';
		}
		echo '<script>window.__SIMPLEVPBOT_DASH__=' . $boot_json . ';</script>';
		echo '</head><body class="svp-dashboard-body">';
		echo '<div id="root"></div>';
		echo '<script type="module" src="' . esc_url( $js ) . '?v=' . esc_attr( $v ) . '"></script>';
		echo '</body></html>';
		exit;
	}
}
