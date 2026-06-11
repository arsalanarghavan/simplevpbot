<?php
/**
 * Per-platform bot enable flags (Telegram / Bale).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Platforms
 */
class SimpleVPBot_Platforms {

	/**
	 * @return array<int, string>
	 */
	public static function all_ids() {
		return array( 'telegram', 'bale' );
	}

	/**
	 * Normalize platform id.
	 *
	 * @param string $platform Platform.
	 * @return string telegram|bale
	 */
	public static function normalize( $platform ) {
		return 'bale' === sanitize_key( (string) $platform ) ? 'bale' : 'telegram';
	}

	/**
	 * Settings key for platform enable flag.
	 *
	 * @param string $platform Platform.
	 * @return string
	 */
	public static function settings_key( $platform ) {
		$plat = self::normalize( $platform );
		return 'telegram' === $plat ? 'telegram_enabled' : 'bale_enabled';
	}

	/**
	 * Whether raw flag value is on (missing => true for backward compat).
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public static function flag_is_on( $value ) {
		if ( null === $value ) {
			return true;
		}
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_numeric( $value ) ) {
			return (int) $value !== 0;
		}
		$s = strtolower( trim( (string) $value ) );
		if ( '' === $s ) {
			return true;
		}
		return ! in_array( $s, array( '0', 'false', 'off', 'no' ), true );
	}

	/**
	 * Main bot: read platform flag from settings array or option.
	 *
	 * @param string                    $platform Platform.
	 * @param array<string, mixed>|null $settings Optional settings slice.
	 * @return bool
	 */
	public static function main_platform_flag( $platform, array $settings = null ) {
		$key = self::settings_key( $platform );
		if ( is_array( $settings ) && array_key_exists( $key, $settings ) ) {
			return self::flag_is_on( $settings[ $key ] );
		}
		return self::flag_is_on( SimpleVPBot_Settings::get( $key, true ) );
	}

	/**
	 * Reseller profile: read platform flag (missing => true).
	 *
	 * @param object|null $profile Profile row.
	 * @param string      $platform Platform.
	 * @return bool
	 */
	public static function reseller_platform_flag( $profile, $platform ) {
		if ( ! $profile || ! is_object( $profile ) ) {
			return true;
		}
		$key = self::settings_key( $platform );
		if ( ! property_exists( $profile, $key ) ) {
			return true;
		}
		return self::flag_is_on( $profile->{$key} );
	}

	/**
	 * Whether bot processing is enabled for a platform (main or reseller context).
	 *
	 * @param string $platform Platform.
	 * @param int    $reseller_svp_user_id Reseller id (0 = main bot).
	 * @return bool
	 */
	public static function is_enabled( $platform, $reseller_svp_user_id = 0 ) {
		$plat = self::normalize( $platform );
		$rid  = (int) $reseller_svp_user_id;

		if ( $rid < 1 && class_exists( 'SimpleVPBot_Bot_Context' ) && SimpleVPBot_Bot_Context::is_reseller_bot() ) {
			$rid = (int) SimpleVPBot_Bot_Context::reseller_svp_user_id();
		}

		if ( ! SimpleVPBot_Settings::get( 'enabled', true ) ) {
			return false;
		}

		if ( ! self::main_platform_flag( $plat ) ) {
			return false;
		}

		if ( $rid > 0 ) {
			if ( ! class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
				return false;
			}
			$profile = SimpleVPBot_Model_Reseller_Bot_Profile::find_by_reseller( $rid );
			if ( ! $profile || empty( $profile->enabled ) ) {
				return false;
			}
			return self::reseller_platform_flag( $profile, $plat );
		}

		return true;
	}

	/**
	 * Enabled platform ids for main bot or a reseller profile.
	 *
	 * @param int $reseller_svp_user_id Reseller id (0 = main).
	 * @return array<int, string>
	 */
	public static function enabled_list( $reseller_svp_user_id = 0 ) {
		$out = array();
		foreach ( self::all_ids() as $plat ) {
			if ( self::is_enabled( $plat, $reseller_svp_user_id ) ) {
				$out[] = $plat;
			}
		}
		return $out;
	}

	/**
	 * Sync main settings.enabled = OR of per-platform flags.
	 */
	public static function sync_main_enabled_from_platforms() {
		$all            = SimpleVPBot_Settings::all();
		$all['enabled'] = self::main_platform_flag( 'telegram', $all ) || self::main_platform_flag( 'bale', $all );
		SimpleVPBot_Settings::update( $all );
	}

	/**
	 * Toggle main bot platform flag.
	 *
	 * @param string $platform Platform.
	 * @return bool New platform enabled state.
	 */
	public static function toggle_main_platform( $platform ) {
		$key = self::settings_key( $platform );
		$all = SimpleVPBot_Settings::all();
		$cur = self::main_platform_flag( $platform, $all );
		$all[ $key ]    = ! $cur;
		$all['enabled'] = self::main_platform_flag( 'telegram', $all ) || self::main_platform_flag( 'bale', $all );
		SimpleVPBot_Settings::update( $all );
		if ( class_exists( 'SimpleVPBot_Texts' ) ) {
			SimpleVPBot_Texts::clear_cache();
		}
		return ! $cur;
	}

	/**
	 * Toggle reseller profile platform flag.
	 *
	 * @param int    $reseller_svp_user_id Reseller id.
	 * @param string $platform Platform.
	 * @return bool|null New state or null on failure.
	 */
	public static function toggle_reseller_platform( $reseller_svp_user_id, $platform ) {
		if ( ! class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			return null;
		}
		return SimpleVPBot_Model_Reseller_Bot_Profile::toggle_platform_enabled( $reseller_svp_user_id, $platform );
	}

	/**
	 * Apply webhook side effects after platform toggle.
	 *
	 * @param string $platform Platform.
	 * @param bool   $enabled New enabled state.
	 * @param int    $reseller_svp_user_id Reseller id (0 = main).
	 */
	public static function after_platform_toggle( $platform, $enabled, $reseller_svp_user_id = 0 ) {
		if ( ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return;
		}
		$plat = self::normalize( $platform );
		$rid  = (int) $reseller_svp_user_id;
		if ( $rid > 0 ) {
			if ( $enabled ) {
				if ( 'telegram' === $plat ) {
					SimpleVPBot_Service_Admin_Ops::set_webhook_telegram_for_reseller( $rid );
				} else {
					SimpleVPBot_Service_Admin_Ops::set_webhook_bale_for_reseller( $rid );
				}
			} elseif ( 'telegram' === $plat ) {
				SimpleVPBot_Service_Admin_Ops::delete_webhook_telegram_for_reseller( $rid );
			} else {
				SimpleVPBot_Service_Admin_Ops::delete_webhook_bale_for_reseller( $rid );
			}
			return;
		}
		if ( $enabled ) {
			if ( 'telegram' === $plat ) {
				SimpleVPBot_Service_Admin_Ops::set_webhook_telegram();
			} else {
				SimpleVPBot_Service_Admin_Ops::set_webhook_bale();
			}
		} elseif ( 'telegram' === $plat ) {
			SimpleVPBot_Service_Admin_Ops::delete_webhook_telegram();
		} else {
			SimpleVPBot_Service_Admin_Ops::delete_webhook_bale();
		}
	}
}
