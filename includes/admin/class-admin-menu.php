<?php
/**
 * Legacy WP admin entry: redirects to /dashboard SPA.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Admin_Menu
 */
class SimpleVPBot_Admin_Menu {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_old_url' ) );
	}

	/**
	 * Redirect direct hits to admin.php?page=simplevpbot to the front-end dashboard.
	 */
	public static function maybe_redirect_old_url() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'] ) && 'simplevpbot' === (string) wp_unslash( $_GET['page'] ) ) {
			wp_safe_redirect( home_url( '/dashboard/' ) );
			exit;
		}
	}

	/**
	 * Top-level menu opens external dashboard (same tab).
	 */
	public static function register_menu() {
		add_menu_page(
			'VIP BOT',
			'VIP BOT',
			'manage_options',
			'simplevpbot',
			array( __CLASS__, 'render_redirect' ),
			'dashicons-shield',
			58
		);
	}

	/**
	 * Callback: immediate redirect to SPA.
	 */
	public static function render_redirect() {
		wp_safe_redirect( home_url( '/dashboard/' ) );
		exit;
	}
}
