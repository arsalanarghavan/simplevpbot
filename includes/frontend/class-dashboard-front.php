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
		add_action( 'template_redirect', array( __CLASS__, 'render_page' ), 0 );
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
		$rtl    = ( 'fa' === $lang );

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
				'logoutUrl'           => wp_logout_url( home_url( '/dashboard/' ) ),
				'siteName'            => get_bloginfo( 'name' ),
				'pluginUrl'           => SIMPLEVPBOT_PLUGIN_URL,
				'dashPath'            => 'login',
				'siteTimeZone'        => is_string( $tz ) ? $tz : '',
			);
		} else {
			$user     = wp_get_current_user();
			$is_admin = current_user_can( 'manage_options' );
			$svp_uid  = 0;
			$is_reseller = false;
			if ( class_exists( 'SimpleVPBot_Model_User' ) ) {
				$row = SimpleVPBot_Model_User::find_by_wp_user( (int) $user->ID );
				if ( $row ) {
					$svp_uid = (int) $row->id;
					$is_reseller = ! $is_admin && SimpleVPBot_Model_User::is_reseller_row( $row );
				}
			}
			$boot = array(
				'restUrl'             => $rest,
				'nonce'               => wp_create_nonce( 'wp_rest' ),
				'locale'              => $locale,
				'lang'                => $lang,
				'isRtl'               => $rtl,
				'isLoggedIn'          => true,
				'isAdmin'             => $is_admin,
				'isReseller'          => $is_reseller,
				'svpUserId'           => $svp_uid,
				'loginUrl'            => wp_login_url( home_url( '/dashboard/' ) ),
				'dashboardUrl'        => home_url( '/dashboard/' ),
				'dashboardLoginUrl'   => trailingslashit( home_url( '/dashboard/login' ) ),
				'logoutUrl'           => wp_logout_url( home_url( '/dashboard/' ) ),
				'siteName'            => get_bloginfo( 'name' ),
				'pluginUrl'           => SIMPLEVPBOT_PLUGIN_URL,
				'dashPath'            => $dash_path,
				'siteTimeZone'        => is_string( $tz ) ? $tz : '',
			);
		}

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
		echo '<html lang="' . esc_attr( $lang ) . '" dir="' . esc_attr( $rtl ? 'rtl' : 'ltr' ) . '">';
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
		echo '<script>window.__SIMPLEVPBOT_DASH__=' . $boot_json . ';</script>';
		echo '</head><body class="svp-dashboard-body">';
		echo '<div id="root"></div>';
		echo '<script type="module" src="' . esc_url( $js ) . '?v=' . esc_attr( $v ) . '"></script>';
		echo '</body></html>';
		exit;
	}
}
