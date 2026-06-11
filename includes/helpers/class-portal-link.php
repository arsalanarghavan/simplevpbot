<?php
/**
 * HMAC-signed portal URLs (no WP login).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Portal_Link
 */
class SimpleVPBot_Portal_Link {

	/**
	 * Secret for HMAC.
	 *
	 * @return string
	 */
	private static function key() {
		$k = (string) SimpleVPBot_Settings::get( 'portal_link_secret', '' );
		if ( '' !== $k && strlen( $k ) >= 20 ) {
			return $k;
		}
		if ( function_exists( 'wp_salt' ) ) {
			return (string) wp_salt( 'auth' ) . 'simplevpbot_portal_v1';
		}
		return (string) AUTH_KEY . 'simplevpbot_portal_v1';
	}

	/**
	 * Legacy alias; customer portal links use {@see SimpleVPBot_Portal_Link::CUSTOMER_TTL}.
	 */
	const DEFAULT_TTL = 604800;

	/**
	 * Customer portal / unified subscription link TTL (365 days).
	 */
	const CUSTOMER_TTL = 31536000;

	/**
	 * Shorter TTL (24 hours) for bot-admin portal links (write-capable ops).
	 */
	const ADMIN_TTL = 86400;

	/**
	 * Build signed URL for a bot user to open subscription portal.
	 *
	 * @param int $user_id svp_users.id.
	 * @return string
	 */
	public static function build_url( $user_id ) {
		$uid = (int) $user_id;
		$exp   = time() + self::CUSTOMER_TTL;
		$sig   = hash_hmac( 'sha256', $uid . '|' . $exp, self::key() );
		$base  = self::base_url();
		$args  = array(
			'svp_p'  => '1',
			'svp_u'  => (string) $uid,
			'svp_e'  => (string) $exp,
			'svp_s'  => $sig,
		);
		return add_query_arg( $args, $base );
	}

	/**
	 * Build signed URL bound to a specific service.
	 *
	 * @param int $user_id svp_users.id.
	 * @param int $service_id svp_services.id.
	 * @return string
	 */
	public static function build_service_url( $user_id, $service_id ) {
		$uid = (int) $user_id;
		$sid = (int) $service_id;
		$exp = time() + self::CUSTOMER_TTL;
		$sig = hash_hmac( 'sha256', $uid . '|' . $sid . '|' . $exp, self::key() );
		$base = self::base_url();
		$args = array(
			'svp_p'  => '1',
			'svp_u'  => (string) $uid,
			'svp_sid'=> (string) $sid,
			'svp_e'  => (string) $exp,
			'svp_s'  => $sig,
		);
		return add_query_arg( $args, $base );
	}

	/**
	 * Whether user may open signed admin portal (site admin id or reseller operator).
	 *
	 * @param object|null $user User row.
	 * @return bool
	 */
	public static function is_svp_user_portal_eligible( $user ) {
		if ( ! $user || empty( $user->id ) ) {
			return false;
		}
		if ( class_exists( 'SimpleVPBot_Router' ) && SimpleVPBot_Router::is_svp_user_bot_admin( $user ) ) {
			return true;
		}
		if ( class_exists( 'SimpleVPBot_Model_User' ) && SimpleVPBot_Model_User::is_reseller_row( $user ) ) {
			return true;
		}
		if ( class_exists( 'SimpleVPBot_Reseller_Permission_Gate' )
			&& SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id ) > 0 ) {
			return true;
		}
		return false;
	}

	/**
	 * Signed URL for bot-linked admin to open web admin shell (same base as portal).
	 *
	 * @param int $svp_user_id svp_users.id (must be linked Telegram/Bale admin id).
	 * @return string Empty if user is not a bot admin.
	 */
	public static function build_admin_url( $svp_user_id ) {
		$uid = (int) $svp_user_id;
		if ( $uid < 1 ) {
			return '';
		}
		$user = SimpleVPBot_Model_User::find( $uid );
		if ( ! $user || ! self::is_svp_user_portal_eligible( $user ) ) {
			return '';
		}
		$exp = time() + self::ADMIN_TTL;
		$sig = hash_hmac( 'sha256', 'admin|' . $uid . '|' . $exp, self::key() . '|svp_admin_v1' );
		$base = self::base_url();
		$args = array(
			'svp_adm' => '1',
			'svp_u'   => (string) $uid,
			'svp_e'   => (string) $exp,
			'svp_s'   => $sig,
		);
		return add_query_arg( $args, $base );
	}

	/**
	 * Validate admin portal HMAC + bot-admin linkage (for AJAX).
	 *
	 * @param int    $user_id svp_users.id.
	 * @param int    $exp     Unix expiry.
	 * @param string $sig     HMAC hex.
	 * @return object|null User row or null.
	 */
	public static function verify_admin_signature( $user_id, $exp, $sig ) {
		$u = (int) $user_id;
		$e = (int) $exp;
		$s = (string) $sig;
		if ( $u < 1 || $e < time() || strlen( $s ) < 8 ) {
			return null;
		}
		$check = hash_hmac( 'sha256', 'admin|' . $u . '|' . $e, self::key() . '|svp_admin_v1' );
		if ( ! hash_equals( $check, $s ) ) {
			return null;
		}
		$user = SimpleVPBot_Model_User::find( $u );
		if ( ! $user || ! self::is_svp_user_portal_eligible( $user ) ) {
			return null;
		}
		return $user;
	}

	/**
	 * Base URL: optional portal page (WP) or /info.
	 *
	 * @return string
	 */
	public static function base_url() {
		$page_id = (int) SimpleVPBot_Settings::get( 'portal_page_id', 0 );
		if ( $page_id > 0 && get_post_status( $page_id ) ) {
			return (string) get_permalink( $page_id );
		}
		return (string) user_trailingslashit( home_url( 'info' ) );
	}

	/**
	 * Read user id from current request (GET) if signature valid and not expired.
	 *
	 * @return int 0 if invalid.
	 */
	public static function current_user_id() {
		$ctx = self::current_context();
		return (int) ( $ctx['user_id'] ?? 0 );
	}

	/**
	 * Validated svp user id for admin portal request (0 if not admin token).
	 *
	 * @return int
	 */
	public static function current_admin_user_id() {
		$ctx = self::current_admin_context();
		return (int) ( $ctx['user_id'] ?? 0 );
	}

	/**
	 * Read validated service id from request.
	 *
	 * @return int 0 when absent/invalid/legacy link.
	 */
	public static function current_service_id() {
		$ctx = self::current_context();
		return (int) ( $ctx['service_id'] ?? 0 );
	}

	/**
	 * Admin portal context from GET (svp_adm=1 + valid HMAC).
	 *
	 * @return array{user_id:int}
	 */
	private static function current_admin_context() {
		if ( empty( $_GET['svp_adm'] ) || '1' !== (string) wp_unslash( $_GET['svp_adm'] ) ) { // phpcs:ignore
			return array( 'user_id' => 0 );
		}
		$u = isset( $_GET['svp_u'] ) ? (int) $_GET['svp_u'] : 0; // phpcs:ignore
		$e = isset( $_GET['svp_e'] ) ? (int) $_GET['svp_e'] : 0; // phpcs:ignore
		$s = isset( $_GET['svp_s'] ) ? (string) wp_unslash( $_GET['svp_s'] ) : ''; // phpcs:ignore
		$user = self::verify_admin_signature( $u, $e, $s );
		return array( 'user_id' => $user ? (int) $user->id : 0 );
	}

	/**
	 * User portal context from GET (svp_p=1 + valid HMAC). Admin links (svp_adm) yield zeros here.
	 *
	 * @return array{user_id:int,service_id:int}
	 */
	private static function current_context() {
		if ( ! empty( $_GET['svp_adm'] ) && '1' === (string) wp_unslash( $_GET['svp_adm'] ) ) { // phpcs:ignore
			return array( 'user_id' => 0, 'service_id' => 0 );
		}
		if ( empty( $_GET['svp_p'] ) || '1' !== (string) wp_unslash( $_GET['svp_p'] ) ) { // phpcs:ignore
			return array( 'user_id' => 0, 'service_id' => 0 );
		}
		$u   = isset( $_GET['svp_u'] ) ? (int) trim( (string) wp_unslash( $_GET['svp_u'] ) ) : 0; // phpcs:ignore
		$sid = isset( $_GET['svp_sid'] ) ? (int) trim( (string) wp_unslash( $_GET['svp_sid'] ) ) : 0; // phpcs:ignore
		$e   = isset( $_GET['svp_e'] ) ? (int) trim( (string) wp_unslash( $_GET['svp_e'] ) ) : 0; // phpcs:ignore
		$s = isset( $_GET['svp_s'] ) ? (string) wp_unslash( $_GET['svp_s'] ) : ''; // phpcs:ignore
		if ( $u < 1 || $e < time() || ! is_string( $s ) || strlen( $s ) < 8 ) {
			return array( 'user_id' => 0, 'service_id' => 0 );
		}
		$ok = false;
		if ( $sid > 0 ) {
			$check = hash_hmac( 'sha256', $u . '|' . $sid . '|' . $e, self::key() );
			$ok    = hash_equals( $check, $s );
		} else {
			$check = hash_hmac( 'sha256', $u . '|' . $e, self::key() );
			$ok    = hash_equals( $check, $s );
		}
		if ( ! $ok ) {
			return array( 'user_id' => 0, 'service_id' => 0 );
		}
		return array( 'user_id' => $u, 'service_id' => max( 0, $sid ) );
	}
}
