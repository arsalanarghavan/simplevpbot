<?php
/**
 * Public portal at /info (no shortcode required).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Portal_Front
 */
class SimpleVPBot_Portal_Front {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_rewrite' ), 5 );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite' ), 20 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( 'SimpleVPBot_Portal_Subscription', 'maybe_serve' ), -1 );
		add_action( 'template_redirect', array( __CLASS__, 'render_page' ), 0 );
	}

	/**
	 * One-time flush after adding /info so permalinks pick up the new rule.
	 */
	public static function maybe_flush_rewrite() {
		if ( (int) get_option( 'simplevpbot_svp_info_rw', 0 ) < 1 ) {
			update_option( 'simplevpbot_svp_info_rw', 1 );
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Register /info → query var svp_info.
	 */
	public static function register_rewrite() {
		add_rewrite_rule( '^info/?$', 'index.php?svp_info=1', 'top' );
	}

	/**
	 * @param array<int, string> $vars Vars.
	 * @return array<int, string>
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'svp_info';
		return $vars;
	}

	/**
	 * Full standalone HTML response for /info.
	 */
	public static function render_page() {
		if ( 1 !== (int) get_query_var( 'svp_info' ) ) {
			return;
		}
		if ( ! function_exists( 'status_header' ) ) {
			return;
		}
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );

		$body = class_exists( 'SimpleVPBot_Shortcode_Portal' )
			? SimpleVPBot_Shortcode_Portal::render_content()
			: '';

		$charset = get_bloginfo( 'charset' );
		$css_url = SIMPLEVPBOT_PLUGIN_URL . 'assets/portal.css?v=' . rawurlencode( SIMPLEVPBOT_VERSION );
		$js_url  = SIMPLEVPBOT_PLUGIN_URL . 'assets/portal.js?v=' . rawurlencode( SIMPLEVPBOT_VERSION );
		$title   = __( 'صفحهٔ اشتراک', 'simplevpbot' ) . ' | ' . get_bloginfo( 'name' );

		echo '<!DOCTYPE html>';
		echo '<html lang="fa-IR" dir="rtl">';
		echo '<head>';
		echo '<meta charset="' . esc_attr( $charset ) . '"/>';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>';
		echo '<meta name="robots" content="noindex, nofollow"/>';
		echo '<meta name="theme-color" content="#eef1f7" media="(prefers-color-scheme: light)"/>';
		echo '<meta name="theme-color" content="#0b0d17" media="(prefers-color-scheme: dark)"/>';
		echo '<title>' . esc_html( $title ) . '</title>';
		echo '<link rel="stylesheet" href="' . esc_url( $css_url ) . '"/>';
		echo '<script defer src="' . esc_url( $js_url ) . '"></script>';
		echo '</head>';
		echo '<body>';
		echo '<main class="svp-main">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $body;
		echo '</main>';
		echo '<div class="svp-toast"><div class="svp-toast__msg"></div></div>';
		echo '</body></html>';
		exit;
	}
}
