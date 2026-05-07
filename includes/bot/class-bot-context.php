<?php
/**
 * Per-request context for reseller webhook (token routing).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Bot_Context
 */
class SimpleVPBot_Bot_Context {

	/**
	 * @var int
	 */
	private static $reseller_svp_user_id = 0;

	/**
	 * @var object|null
	 */
	private static $reseller_profile = null;

	/**
	 * Begin reseller-scoped handling (webhook matched reseller URL).
	 *
	 * @param int                   $reseller_svp_user_id svp_users.id.
	 * @param object|null           $profile              Row from svp_reseller_bot_profiles or null.
	 */
	public static function begin_reseller( $reseller_svp_user_id, $profile ) {
		self::$reseller_svp_user_id = max( 0, (int) $reseller_svp_user_id );
		self::$reseller_profile     = is_object( $profile ) ? $profile : null;
	}

	/**
	 * Clear context after request (best-effort).
	 */
	public static function reset() {
		self::$reseller_svp_user_id = 0;
		self::$reseller_profile     = null;
	}

	/**
	 * Active reseller id from webhook path (0 = main bot).
	 *
	 * @return int
	 */
	public static function reseller_svp_user_id() {
		return (int) self::$reseller_svp_user_id;
	}

	/**
	 * Cached profile row when context is reseller.
	 *
	 * @return object|null
	 */
	public static function reseller_profile() {
		return self::$reseller_profile;
	}

	/**
	 * Whether this request is served via a reseller bot webhook.
	 *
	 * @return bool
	 */
	public static function is_reseller_bot() {
		return self::$reseller_svp_user_id > 0;
	}

	/**
	 * Brand title for current bot request (reseller bot only).
	 *
	 * @return string
	 */
	public static function active_brand_name() {
		if ( ! self::is_reseller_bot() ) {
			return '';
		}
		$bn = is_object( self::$reseller_profile ) ? trim( (string) ( self::$reseller_profile->brand_name ?? '' ) ) : '';
		if ( '' !== $bn ) {
			return $bn;
		}
		if ( class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
			return trim( (string) SimpleVPBot_Reseller_Branding::display_brand_for_reseller( (int) self::$reseller_svp_user_id ) );
		}
		return '';
	}
}
