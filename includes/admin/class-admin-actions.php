<?php
/**
 * Shared settings merge/save for WordPress admin and bot admin hub.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Admin_Actions
 */
class SimpleVPBot_Admin_Actions {

	/**
	 * Main-bot platforms whose token was patched in the current bots-tab save (telegram|bale).
	 *
	 * @var array<int, string>
	 */
	private static $bots_tab_tokens_updated = array();

	/**
	 * Parse newline-separated numeric ids.
	 *
	 * @param string $raw Raw.
	 * @return array<int, int>
	 */
	public static function parse_id_lines( $raw ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
			$line = trim( $line );
			if ( is_numeric( $line ) ) {
				$out[] = (int) $line;
			}
		}
		return $out;
	}

	/**
	 * Settings key for admin chat ids by platform.
	 *
	 * @param string $platform telegram|bale.
	 * @return string|null
	 */
	private static function admin_ids_settings_key( $platform ) {
		$platform = sanitize_key( (string) $platform );
		if ( 'bale' === $platform ) {
			return 'admin_bale_ids';
		}
		if ( 'telegram' === $platform ) {
			return 'admin_telegram_ids';
		}
		return null;
	}

	/**
	 * Add one admin chat id to main bot settings (no webhook re-register).
	 *
	 * @param string $platform telegram|bale.
	 * @param int    $chat_id  Numeric user/chat id.
	 * @return bool
	 */
	public static function add_main_admin_id( $platform, $chat_id ) {
		$key = self::admin_ids_settings_key( $platform );
		$chat_id = (int) $chat_id;
		if ( null === $key || $chat_id < 1 ) {
			return false;
		}
		$all = SimpleVPBot_Settings::all();
		$ids = array_values( array_unique( array_map( 'intval', (array) ( $all[ $key ] ?? array() ) ) ) );
		if ( in_array( $chat_id, $ids, true ) ) {
			return true;
		}
		$ids[] = $chat_id;
		$all[ $key ] = $ids;
		SimpleVPBot_Settings::update( $all );
		if ( class_exists( 'SimpleVPBot_Texts' ) ) {
			SimpleVPBot_Texts::clear_cache();
		}
		return true;
	}

	/**
	 * Remove one admin chat id from main bot settings.
	 *
	 * @param string $platform telegram|bale.
	 * @param int    $chat_id  Numeric user/chat id.
	 * @return bool
	 */
	public static function remove_main_admin_id( $platform, $chat_id ) {
		$key = self::admin_ids_settings_key( $platform );
		$chat_id = (int) $chat_id;
		if ( null === $key || $chat_id < 1 ) {
			return false;
		}
		$all = SimpleVPBot_Settings::all();
		$ids = array_values( array_unique( array_map( 'intval', (array) ( $all[ $key ] ?? array() ) ) ) );
		$ids = array_values( array_filter( $ids, static function ( $id ) use ( $chat_id ) {
			return (int) $id !== $chat_id;
		} ) );
		$all[ $key ] = $ids;
		SimpleVPBot_Settings::update( $all );
		if ( class_exists( 'SimpleVPBot_Texts' ) ) {
			SimpleVPBot_Texts::clear_cache();
		}
		return true;
	}

	/**
	 * Add one admin chat id to reseller bot profile.
	 *
	 * @param int    $reseller_svp_user_id Reseller svp_users.id.
	 * @param string $platform             telegram|bale.
	 * @param int    $chat_id              Numeric user/chat id.
	 * @return bool
	 */
	public static function add_reseller_admin_id( $reseller_svp_user_id, $platform, $chat_id ) {
		if ( ! class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			return false;
		}
		$r = (int) $reseller_svp_user_id;
		$chat_id = (int) $chat_id;
		$platform = sanitize_key( (string) $platform );
		if ( $r < 1 || $chat_id < 1 || ! in_array( $platform, array( 'telegram', 'bale' ), true ) ) {
			return false;
		}
		$prof = SimpleVPBot_Model_Reseller_Bot_Profile::find_by_reseller( $r );
		if ( ! $prof ) {
			SimpleVPBot_Model_Reseller_Bot_Profile::ensure_webhook_secret( $r );
			$prof = SimpleVPBot_Model_Reseller_Bot_Profile::find_by_reseller( $r );
		}
		if ( ! $prof ) {
			return false;
		}
		$tg_ids = SimpleVPBot_Model_Reseller_Bot_Profile::decode_admin_ids( $prof->admin_telegram_ids ?? '' );
		$bl_ids = SimpleVPBot_Model_Reseller_Bot_Profile::decode_admin_ids( $prof->admin_bale_ids ?? '' );
		if ( 'bale' === $platform ) {
			if ( ! in_array( $chat_id, $bl_ids, true ) ) {
				$bl_ids[] = $chat_id;
			}
		} else {
			if ( ! in_array( $chat_id, $tg_ids, true ) ) {
				$tg_ids[] = $chat_id;
			}
		}
		SimpleVPBot_Model_Reseller_Bot_Profile::save_admin_ids( $r, $tg_ids, $bl_ids );
		return true;
	}

	/**
	 * Remove one admin chat id from reseller bot profile.
	 *
	 * @param int    $reseller_svp_user_id Reseller svp_users.id.
	 * @param string $platform             telegram|bale.
	 * @param int    $chat_id              Numeric user/chat id.
	 * @return bool
	 */
	public static function remove_reseller_admin_id( $reseller_svp_user_id, $platform, $chat_id ) {
		if ( ! class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			return false;
		}
		$r = (int) $reseller_svp_user_id;
		$chat_id = (int) $chat_id;
		$platform = sanitize_key( (string) $platform );
		if ( $r < 1 || $chat_id < 1 || ! in_array( $platform, array( 'telegram', 'bale' ), true ) ) {
			return false;
		}
		$prof = SimpleVPBot_Model_Reseller_Bot_Profile::find_by_reseller( $r );
		if ( ! $prof ) {
			return false;
		}
		$tg_ids = SimpleVPBot_Model_Reseller_Bot_Profile::decode_admin_ids( $prof->admin_telegram_ids ?? '' );
		$bl_ids = SimpleVPBot_Model_Reseller_Bot_Profile::decode_admin_ids( $prof->admin_bale_ids ?? '' );
		if ( 'bale' === $platform ) {
			$bl_ids = array_values( array_filter( $bl_ids, static function ( $id ) use ( $chat_id ) {
				return (int) $id !== $chat_id;
			} ) );
		} else {
			$tg_ids = array_values( array_filter( $tg_ids, static function ( $id ) use ( $chat_id ) {
				return (int) $id !== $chat_id;
			} ) );
		}
		SimpleVPBot_Model_Reseller_Bot_Profile::save_admin_ids( $r, $tg_ids, $bl_ids );
		return true;
	}

	/**
	 * Merge backup-related settings (used by WP form and bot admin).
	 *
	 * @param array<string, mixed> $patch Keys: backup_interval_minutes, backup_*_chat_id, backup_send_*, backup_store_on_site, backup_site_retention_count, backup_max_zip_mb.
	 * @return bool True if something was written.
	 */
	public static function patch_backup_settings( array $patch ) {
		$all          = SimpleVPBot_Settings::all();
		$old_interval = (int) ( $all['backup_interval_minutes'] ?? 60 );
		$changed      = false;
		if ( array_key_exists( 'backup_interval_minutes', $patch ) ) {
			$all['backup_interval_minutes'] = max( 5, (int) $patch['backup_interval_minutes'] );
			$changed                        = true;
		}
		if ( array_key_exists( 'backup_telegram_chat_id', $patch ) ) {
			$all['backup_telegram_chat_id'] = (int) $patch['backup_telegram_chat_id'];
			$changed                        = true;
		}
		if ( array_key_exists( 'backup_bale_chat_id', $patch ) ) {
			$all['backup_bale_chat_id'] = (int) $patch['backup_bale_chat_id'];
			$changed                    = true;
		}
		$flags = array(
			'backup_send_telegram_admins',
			'backup_send_bale_admins',
			'backup_send_telegram_channel',
			'backup_send_bale_channel',
		);
		foreach ( $flags as $fk ) {
			if ( array_key_exists( $fk, $patch ) ) {
				$all[ $fk ] = ! empty( $patch[ $fk ] );
				$changed    = true;
			}
		}
		if ( array_key_exists( 'backup_store_on_site', $patch ) ) {
			$all['backup_store_on_site'] = ! empty( $patch['backup_store_on_site'] );
			$changed                     = true;
		}
		if ( array_key_exists( 'backup_site_retention_count', $patch ) ) {
			$all['backup_site_retention_count'] = max( 1, min( 500, (int) $patch['backup_site_retention_count'] ) );
			$changed                            = true;
		}
		if ( array_key_exists( 'backup_max_zip_mb', $patch ) ) {
			$all['backup_max_zip_mb'] = max( 0, (int) $patch['backup_max_zip_mb'] );
			$changed                  = true;
		}
		if ( ! $changed ) {
			return false;
		}
		SimpleVPBot_Settings::update( $all );
		SimpleVPBot_Texts::clear_cache();
		$new_interval = (int) ( $all['backup_interval_minutes'] ?? 60 );
		if ( $old_interval !== $new_interval ) {
			self::after_settings_tab_saved( 'backup' );
		}
		return true;
	}

	/**
	 * Toggle one backup destination flag (bot inline).
	 *
	 * @param string $key One of the four backup_send_* keys.
	 * @return bool
	 */
	public static function toggle_backup_send_key( $key ) {
		$allowed = array(
			'backup_send_telegram_admins',
			'backup_send_bale_admins',
			'backup_send_telegram_channel',
			'backup_send_bale_channel',
		);
		$key = (string) $key;
		if ( ! in_array( $key, $allowed, true ) ) {
			return false;
		}
		$all         = SimpleVPBot_Settings::all();
		$all[ $key ] = empty( $all[ $key ] );
		SimpleVPBot_Settings::update( $all );
		SimpleVPBot_Texts::clear_cache();
		return true;
	}

	/**
	 * Current settings as a POST-like array (same keys as the WP form) for merge/patch from bot.
	 *
	 * @param string $tab general|bots|panel|notifications.
	 * @return array<string, mixed>
	 */
	public static function settings_post_for_tab( $tab ) {
		$tab = sanitize_key( (string) $tab );
		$s   = SimpleVPBot_Settings::all();
		if ( 'general' === $tab ) {
			return array(
				'enabled'                => ! empty( $s['enabled'] ),
				'test_account_enabled'     => ! empty( $s['test_account_enabled'] ),
				'admin_telegram_ids'     => implode( "\n", array_map( 'strval', (array) ( $s['admin_telegram_ids'] ?? array() ) ) ),
				'admin_bale_ids'         => implode( "\n", array_map( 'strval', (array) ( $s['admin_bale_ids'] ?? array() ) ) ),
				'portal_page_id'             => max( 0, (int) ( $s['portal_page_id'] ?? 0 ) ),
				'default_service_plan_id'    => max( 0, (int) ( $s['default_service_plan_id'] ?? 0 ) ),
				'crisis_mode'                => ! empty( $s['crisis_mode'] ),
				'suppress_bulk_user_notifications' => ! empty( $s['suppress_bulk_user_notifications'] ),
				'cards_display_mode'         => class_exists( 'SimpleVPBot_Card_Rotation' )
					? SimpleVPBot_Card_Rotation::sanitize_display_mode( $s['cards_display_mode'] ?? 'list' )
					: ( in_array( (string) ( $s['cards_display_mode'] ?? 'list' ), array( 'list', 'sequential' ), true ) ? (string) $s['cards_display_mode'] : 'list' ),
			);
		}
		if ( 'bots' === $tab ) {
			return array(
				'telegram_token'             => (string) ( $s['telegram_token'] ?? '' ),
				'bale_token'                 => (string) ( $s['bale_token'] ?? '' ),
				'telegram_webhook_secret'   => (string) ( $s['telegram_webhook_secret'] ?? '' ),
				'bale_webhook_secret'       => (string) ( $s['bale_webhook_secret'] ?? '' ),
				'telegram_secret_header'   => (string) ( $s['telegram_secret_header'] ?? '' ),
				'bale_wallet_provider_token' => (string) ( $s['bale_wallet_provider_token'] ?? '' ),
			);
		}
		if ( 'referral' === $tab ) {
			return array(
				'referral_enabled'                  => ! empty( $s['referral_enabled'] ),
				'referral_percent'                  => (float) ( $s['referral_percent'] ?? 0 ),
				'referral_min_payout_base'          => (float) ( $s['referral_min_payout_base'] ?? 0 ),
				'referral_example_base_toman'      => (float) ( $s['referral_example_base_toman'] ?? 170000 ),
				'referral_example_invite_count'     => (int) ( $s['referral_example_invite_count'] ?? 10 ),
				'referral_require_approved_referrer' => ! empty( $s['referral_require_approved_referrer'] ),
				'telegram_bot_username'            => (string) ( $s['telegram_bot_username'] ?? '' ),
				'bale_bot_username'                 => (string) ( $s['bale_bot_username'] ?? '' ),
			);
		}
		if ( 'panel' === $tab ) {
			return array(
				'panel_url'                 => (string) ( $s['panel_url'] ?? '' ),
				'panel_username'            => (string) ( $s['panel_username'] ?? '' ),
				'panel_password'            => (string) ( $s['panel_password'] ?? '' ),
				'panel_api_base'            => (string) ( $s['panel_api_base'] ?? 'panel/api' ),
				'panel_login_secret'        => (string) ( $s['panel_login_secret'] ?? '' ),
				'panel_api_token'           => (string) ( $s['panel_api_token'] ?? '' ),
				'subscription_public_base' => (string) ( $s['subscription_public_base'] ?? '' ),
			);
		}
		if ( 'notifications' === $tab ) {
			$days = (array) ( $s['notify_expiry_days'] ?? array( 3, 1 ) );
			return array(
				'notify_low_traffic_percent' => (int) ( $s['notify_low_traffic_percent'] ?? 10 ),
				'notify_expiry_days'        => implode( ',', array_map( 'strval', $days ) ),
				'notify_user_volume'        => ! empty( $s['notify_user_volume'] ),
				'notify_user_expiry'        => ! empty( $s['notify_user_expiry'] ),
				'notify_user_users'         => ! empty( $s['notify_user_users'] ),
				'notify_user_after_expire'  => ! empty( $s['notify_user_after_expire'] ),
				'notify_idle_enabled'       => ! empty( $s['notify_idle_enabled'] ),
				'notify_idle_after_days'    => (int) ( $s['notify_idle_after_days'] ?? 45 ),
				'notify_idle_cooldown_days' => (int) ( $s['notify_idle_cooldown_days'] ?? 90 ),
				'notify_admin_panel_down'   => ! empty( $s['notify_admin_panel_down'] ),
				'notify_admin_panel_down_cooldown' => (int) ( $s['notify_admin_panel_down_cooldown'] ?? 30 ),
				'notify_panel_cost_expiry'  => ! empty( $s['notify_panel_cost_expiry'] ),
				'alert_ip_warn_min_distinct'     => max( 1, (int) ( $s['alert_ip_warn_min_distinct'] ?? 3 ) ),
				'alert_ip_warn_hysteresis'       => ! empty( $s['alert_ip_warn_hysteresis'] ),
				'alert_ip_warn_cooldown_minutes' => max( 0, (int) ( $s['alert_ip_warn_cooldown_minutes'] ?? 0 ) ),
			);
		}
		if ( 'purge_expired' === $tab ) {
			return class_exists( 'SimpleVPBot_Cron_Purge_Expired' )
				? SimpleVPBot_Cron_Purge_Expired::dashboard_settings_snapshot()
				: array();
		}
		if ( 'finance' === $tab ) {
			$days = (string) ( $s['panel_cost_reminder_days'] ?? '7,1,0' );
			return array(
				'notify_panel_cost_expiry'       => ! empty( $s['notify_panel_cost_expiry'] ),
				'panel_cost_reminder_days'       => $days,
				'panel_cost_extend_days_on_paid' => (int) ( $s['panel_cost_extend_days_on_paid'] ?? 30 ),
			);
		}
		if ( 'plans_catalog' === $tab ) {
			return array(
				'default_concurrent_users' => (int) ( $s['default_concurrent_users'] ?? 2 ),
				'price_per_extra_user'     => (string) ( $s['price_per_extra_user'] ?? '0' ),
			);
		}
		if ( 'backup' === $tab ) {
			return array(
				'backup_interval_minutes'         => max( 5, (int) ( $s['backup_interval_minutes'] ?? 60 ) ),
				'backup_telegram_chat_id'         => (int) ( $s['backup_telegram_chat_id'] ?? 0 ),
				'backup_bale_chat_id'             => (int) ( $s['backup_bale_chat_id'] ?? 0 ),
				'backup_send_telegram_admins'     => ! empty( $s['backup_send_telegram_admins'] ),
				'backup_send_bale_admins'         => ! empty( $s['backup_send_bale_admins'] ),
				'backup_send_telegram_channel'    => ! empty( $s['backup_send_telegram_channel'] ),
				'backup_send_bale_channel'        => ! empty( $s['backup_send_bale_channel'] ),
				'backup_store_on_site'            => ! empty( $s['backup_store_on_site'] ),
				'backup_site_retention_count'     => max( 1, min( 500, (int) ( $s['backup_site_retention_count'] ?? 14 ) ) ),
				'backup_max_zip_mb'               => max( 0, (int) ( $s['backup_max_zip_mb'] ?? 0 ) ),
			);
		}
		if ( 'cards' === $tab ) {
			$out = array(
				'cards_display_mode' => class_exists( 'SimpleVPBot_Card_Rotation' )
					? SimpleVPBot_Card_Rotation::sanitize_display_mode( $s['cards_display_mode'] ?? 'list' )
					: sanitize_key( (string) ( $s['cards_display_mode'] ?? 'list' ) ),
			);
			if ( class_exists( 'SimpleVPBot_Payment_Methods' ) ) {
				$out['payment_methods'] = SimpleVPBot_Payment_Methods::site_map();
			}
			return $out;
		}
		if ( 'force_join' === $tab ) {
			return array(
				'force_join_telegram_enabled'       => ! empty( $s['force_join_telegram_enabled'] ),
				'force_join_telegram_chat_id'       => (int) ( $s['force_join_telegram_chat_id'] ?? 0 ),
				'force_join_telegram_username'      => (string) ( $s['force_join_telegram_username'] ?? '' ),
				'force_join_telegram_invite_link'   => (string) ( $s['force_join_telegram_invite_link'] ?? '' ),
				'force_join_telegram_prompt_text'   => (string) ( $s['force_join_telegram_prompt_text'] ?? '' ),
				'force_join_telegram_announce_text' => (string) ( $s['force_join_telegram_announce_text'] ?? '' ),
				'force_join_bale_enabled'           => ! empty( $s['force_join_bale_enabled'] ),
				'force_join_bale_chat_id'           => (int) ( $s['force_join_bale_chat_id'] ?? 0 ),
				'force_join_bale_username'          => (string) ( $s['force_join_bale_username'] ?? '' ),
				'force_join_bale_invite_link'       => (string) ( $s['force_join_bale_invite_link'] ?? '' ),
				'force_join_bale_prompt_text'       => (string) ( $s['force_join_bale_prompt_text'] ?? '' ),
				'force_join_bale_announce_text'     => (string) ( $s['force_join_bale_announce_text'] ?? '' ),
			);
		}
		return array();
	}

	/**
	 * Merge a partial POST-like array into a tab and save.
	 *
	 * @param string               $tab   Tab.
	 * @param array<string, mixed> $patch Partial keys to override.
	 * @return bool
	 */
	public static function apply_settings_merge( $tab, array $patch ) {
		$tab  = sanitize_key( (string) $tab );
		$base = self::settings_post_for_tab( $tab );
		if ( empty( $base ) && ! in_array( $tab, array( 'general', 'bots', 'panel', 'notifications', 'purge_expired', 'referral', 'plans_catalog', 'backup', 'cards', 'force_join' ), true ) ) {
			return false;
		}
		$merged = array_merge( $base, $patch );
		$ok     = self::apply_settings_tab( $tab, $merged );
		if ( $ok ) {
			self::after_settings_tab_saved( $tab );
		}
		return $ok;
	}

	/**
	 * Merge one settings tab from POST-like array into stored settings (same rules as WP admin form).
	 *
	 * @param string               $tab  Tab key (general|bots|panel|backup|notifications|cards).
	 * @param array<string, mixed> $post Raw input; caller should wp_unslash() when from $_POST.
	 * @return bool Whether $tab was recognized and applied.
	 */
	public static function apply_settings_tab( $tab, array $post ) {
		$tab = sanitize_key( $tab );
		$all = SimpleVPBot_Settings::all();
		switch ( $tab ) {
			case 'general':
				$all['enabled']              = ! empty( $post['enabled'] );
				$all['test_account_enabled'] = ! empty( $post['test_account_enabled'] );
				$all['admin_telegram_ids']   = self::parse_id_lines( isset( $post['admin_telegram_ids'] ) ? (string) $post['admin_telegram_ids'] : '' );
				$all['admin_bale_ids']       = self::parse_id_lines( isset( $post['admin_bale_ids'] ) ? (string) $post['admin_bale_ids'] : '' );
				$all['portal_page_id']            = max( 0, (int) ( $post['portal_page_id'] ?? 0 ) );
				$all['default_service_plan_id']   = max( 0, (int) ( $post['default_service_plan_id'] ?? 0 ) );
				$all['crisis_mode']               = ! empty( $post['crisis_mode'] );
				$all['suppress_bulk_user_notifications'] = ! empty( $post['suppress_bulk_user_notifications'] );
				if ( isset( $post['cards_display_mode'] ) ) {
					$mode = (string) $post['cards_display_mode'];
					$all['cards_display_mode'] = class_exists( 'SimpleVPBot_Card_Rotation' )
						? SimpleVPBot_Card_Rotation::sanitize_display_mode( $mode )
						: ( in_array( sanitize_key( $mode ), array( 'list', 'sequential' ), true ) ? sanitize_key( $mode ) : 'list' );
				}
				break;
			case 'bots':
				self::$bots_tab_tokens_updated = array();
				if ( array_key_exists( 'telegram_token', $post ) ) {
					$tg = sanitize_text_field( (string) $post['telegram_token'] );
					if ( '' !== trim( $tg ) ) {
						$all['telegram_token']           = $tg;
						self::$bots_tab_tokens_updated[] = 'telegram';
					}
				}
				if ( array_key_exists( 'bale_token', $post ) ) {
					$bl = sanitize_text_field( (string) $post['bale_token'] );
					if ( '' !== trim( $bl ) ) {
						$all['bale_token']               = $bl;
						self::$bots_tab_tokens_updated[] = 'bale';
					}
				}
				// Optional path secrets: dashboard omits keys so DB values stay; bot merge sends keys explicitly.
				if ( array_key_exists( 'telegram_webhook_secret', $post ) ) {
					$all['telegram_webhook_secret'] = sanitize_text_field( (string) $post['telegram_webhook_secret'] );
				}
				if ( array_key_exists( 'bale_webhook_secret', $post ) ) {
					$all['bale_webhook_secret'] = sanitize_text_field( (string) $post['bale_webhook_secret'] );
				}
				if ( array_key_exists( 'telegram_secret_header', $post ) ) {
					$all['telegram_secret_header'] = sanitize_text_field( (string) $post['telegram_secret_header'] );
				}
				if ( array_key_exists( 'bale_wallet_provider_token', $post ) ) {
					$bw = sanitize_text_field( (string) $post['bale_wallet_provider_token'] );
					if ( '' !== trim( $bw ) ) {
						$all['bale_wallet_provider_token'] = $bw;
					}
				}
				if ( isset( $post['admin_telegram_ids'] ) ) {
					$all['admin_telegram_ids'] = self::parse_id_lines( (string) $post['admin_telegram_ids'] );
				}
				if ( isset( $post['admin_bale_ids'] ) ) {
					$all['admin_bale_ids'] = self::parse_id_lines( (string) $post['admin_bale_ids'] );
				}
				break;
			case 'panel':
				$all['panel_url']                = esc_url_raw( (string) ( $post['panel_url'] ?? '' ) );
				$all['panel_username']           = sanitize_text_field( (string) ( $post['panel_username'] ?? '' ) );
				$all['panel_password']           = (string) ( $post['panel_password'] ?? '' );
				$all['panel_api_base']           = sanitize_text_field( (string) ( $post['panel_api_base'] ?? 'panel/api' ) );
				$all['panel_login_secret']       = sanitize_text_field( (string) ( $post['panel_login_secret'] ?? '' ) );
				$all['panel_api_token']          = sanitize_text_field( (string) ( $post['panel_api_token'] ?? '' ) );
				$all['subscription_public_base'] = esc_url_raw( (string) ( $post['subscription_public_base'] ?? '' ) );
				break;
			case 'backup':
				self::patch_backup_settings(
					array(
						'backup_interval_minutes'         => max( 5, (int) ( $post['backup_interval_minutes'] ?? 60 ) ),
						'backup_telegram_chat_id'         => (int) ( $post['backup_telegram_chat_id'] ?? 0 ),
						'backup_bale_chat_id'             => (int) ( $post['backup_bale_chat_id'] ?? 0 ),
						'backup_send_telegram_admins'     => ! empty( $post['backup_send_telegram_admins'] ),
						'backup_send_bale_admins'         => ! empty( $post['backup_send_bale_admins'] ),
						'backup_send_telegram_channel'   => ! empty( $post['backup_send_telegram_channel'] ),
						'backup_send_bale_channel'        => ! empty( $post['backup_send_bale_channel'] ),
						'backup_store_on_site'            => ! empty( $post['backup_store_on_site'] ),
						'backup_site_retention_count'     => max( 1, min( 500, (int) ( $post['backup_site_retention_count'] ?? 14 ) ) ),
						'backup_max_zip_mb'               => max( 0, (int) ( $post['backup_max_zip_mb'] ?? 0 ) ),
					)
				);
				return true;
			case 'whitelabel':
				$all['enabled']              = ! empty( $post['enabled'] );
				$all['test_account_enabled'] = ! empty( $post['test_account_enabled'] );
				$all['admin_telegram_ids']   = self::parse_id_lines( isset( $post['admin_telegram_ids'] ) ? (string) $post['admin_telegram_ids'] : '' );
				$all['admin_bale_ids']       = self::parse_id_lines( isset( $post['admin_bale_ids'] ) ? (string) $post['admin_bale_ids'] : '' );
				$all['portal_page_id']            = max( 0, (int) ( $post['portal_page_id'] ?? 0 ) );
				$all['default_service_plan_id']   = max( 0, (int) ( $post['default_service_plan_id'] ?? 0 ) );
				$all['crisis_mode']               = ! empty( $post['crisis_mode'] );
				$all['suppress_bulk_user_notifications'] = ! empty( $post['suppress_bulk_user_notifications'] );
				if ( isset( $post['cards_display_mode'] ) ) {
					$mode = (string) $post['cards_display_mode'];
					$all['cards_display_mode'] = class_exists( 'SimpleVPBot_Card_Rotation' )
						? SimpleVPBot_Card_Rotation::sanitize_display_mode( $mode )
						: ( in_array( sanitize_key( $mode ), array( 'list', 'sequential' ), true ) ? sanitize_key( $mode ) : 'list' );
				}
				$all['dashboard_site_name']     = sanitize_text_field( (string) ( $post['dashboard_site_name'] ?? '' ) );
				$all['dashboard_site_icon_url'] = esc_url_raw( (string) ( $post['dashboard_site_icon_url'] ?? '' ) );
				$all['branding_logo_url']       = esc_url_raw( (string) ( $post['branding_logo_url'] ?? '' ) );
				$all['branding_favicon_url']    = esc_url_raw( (string) ( $post['branding_favicon_url'] ?? '' ) );
				$all['branding_theme_primary']  = sanitize_text_field( (string) ( $post['branding_theme_primary'] ?? '' ) );
				$all['branding_theme_accent']   = sanitize_text_field( (string) ( $post['branding_theme_accent'] ?? '' ) );
				$all['branding_custom_domain']  = sanitize_text_field( (string) ( $post['branding_custom_domain'] ?? '' ) );
				$all['support_info']            = sanitize_textarea_field( (string) ( $post['support_info'] ?? '' ) );
				$all['support_telegram_username'] = class_exists( 'SimpleVPBot_Support_Contacts' )
					? SimpleVPBot_Support_Contacts::normalize_username( $post['support_telegram_username'] ?? '' )
					: sanitize_text_field( ltrim( trim( (string) ( $post['support_telegram_username'] ?? '' ) ), '@' ) );
				$all['support_bale_username'] = class_exists( 'SimpleVPBot_Support_Contacts' )
					? SimpleVPBot_Support_Contacts::normalize_username( $post['support_bale_username'] ?? '' )
					: sanitize_text_field( ltrim( trim( (string) ( $post['support_bale_username'] ?? '' ) ), '@' ) );
				$loc = sanitize_key( (string) ( $post['default_bot_locale'] ?? 'fa' ) );
				$all['default_bot_locale'] = in_array( $loc, array( 'fa', 'en' ), true ) ? $loc : 'fa';
				$raw = $post['receipt_reject_reasons'] ?? array();
				if ( is_string( $raw ) ) {
					$raw = preg_split( '/\r\n|\r|\n/', $raw );
				}
				$reasons = array();
				foreach ( (array) $raw as $reason ) {
					$text = trim( sanitize_textarea_field( (string) $reason ) );
					if ( '' !== $text ) {
						$reasons[] = $text;
					}
				}
				$all['receipt_reject_reasons'] = ! empty( $reasons )
					? array_values( array_unique( $reasons ) )
					: SimpleVPBot_Settings::defaults()['receipt_reject_reasons'];
				break;
			case 'service_naming':
				$nmode = sanitize_key( (string) ( $post['service_naming_mode'] ?? 'legacy' ) );
				$all['service_naming_mode'] = in_array( $nmode, array( 'legacy', 'platform_slug', 'prefix_numbered', 'numbered' ), true ) ? $nmode : 'legacy';
				$all['subscription_config_label_override'] = sanitize_text_field( (string) ( $post['subscription_config_label_override'] ?? '' ) );
				$all['config_label_prefix']                = sanitize_text_field( (string) ( $post['config_label_prefix'] ?? '' ) );
				$all['config_label_number_start']          = max( 1, (int) ( $post['config_label_number_start'] ?? 1001 ) );
				$all['inbound_display_names']              = SimpleVPBot_Settings::sanitize_inbound_display_names_input(
					$post['inbound_display_names'] ?? array()
				);
				$all['config_label_prepend_inbound']       = ! empty( $post['config_label_prepend_inbound'] );
				break;
			case 'relay':
				$all['telegram_relay_enabled'] = ! empty( $post['telegram_relay_enabled'] );
				$all['telegram_relay_force'] = ! empty( $post['telegram_relay_force'] );
				$all['telegram_relay_base_url'] = esc_url_raw( trim( (string) ( $post['telegram_relay_base_url'] ?? '' ) ) );
				$all['telegram_relay_public_url'] = esc_url_raw( trim( (string) ( $post['telegram_relay_public_url'] ?? '' ) ) );
				$all['telegram_relay_wp_forward_url'] = esc_url_raw( trim( (string) ( $post['telegram_relay_wp_forward_url'] ?? '' ) ) );
				$all['telegram_relay_allowed_ips'] = sanitize_text_field( (string) ( $post['telegram_relay_allowed_ips'] ?? '' ) );
				if ( array_key_exists( 'telegram_relay_shared_secret', $post ) ) {
					$rsec = trim( (string) $post['telegram_relay_shared_secret'] );
					if ( '' !== $rsec ) {
						$all['telegram_relay_shared_secret'] = $rsec;
					}
				}
				if ( class_exists( 'SimpleVPBot_Telegram_Relay' ) ) {
					SimpleVPBot_Telegram_Relay::ensure_relay_secret();
				}
				break;
			case 'proxy':
				$all['telegram_proxy_enabled']  = ! empty( $post['telegram_proxy_enabled'] );
				$ptype = sanitize_key( (string) ( $post['telegram_proxy_type'] ?? 'http' ) );
				$all['telegram_proxy_type']     = in_array( $ptype, array( 'http', 'socks5' ), true ) ? $ptype : 'http';
				$all['telegram_proxy_host']     = sanitize_text_field( (string) ( $post['telegram_proxy_host'] ?? '' ) );
				$all['telegram_proxy_port']     = max( 0, min( 65535, (int) ( $post['telegram_proxy_port'] ?? 0 ) ) );
				$all['telegram_proxy_username'] = sanitize_text_field( (string) ( $post['telegram_proxy_username'] ?? '' ) );
				if ( array_key_exists( 'telegram_proxy_password', $post ) ) {
					$pw = (string) $post['telegram_proxy_password'];
					if ( '' !== trim( $pw ) ) {
						$all['telegram_proxy_password'] = $pw;
					}
				}
				$all['telegram_api_base_url'] = esc_url_raw( trim( (string) ( $post['telegram_api_base_url'] ?? '' ) ) );
				break;
			case 'resellers_defaults':
				$all['default_reseller_permissions'] = SimpleVPBot_Settings::normalize_default_reseller_permissions(
					isset( $post['default_reseller_permissions'] ) && is_array( $post['default_reseller_permissions'] )
						? $post['default_reseller_permissions']
						: array()
				);
				break;
			case 'notifications':
				$all['notify_low_traffic_percent'] = max( 1, (int) ( $post['notify_low_traffic_percent'] ?? 10 ) );
				$days                              = isset( $post['notify_expiry_days'] ) ? sanitize_text_field( (string) $post['notify_expiry_days'] ) : '3,1';
				$parsed_days                       = array();
				foreach ( array_filter( array_map( 'trim', explode( ',', $days ) ) ) as $part ) {
					if ( is_numeric( $part ) ) {
						$d = (int) $part;
						if ( $d >= -3650 && $d <= 3650 ) {
							$parsed_days[] = $d;
						}
					}
				}
				$all['notify_expiry_days'] = ! empty( $parsed_days ) ? array_values( array_unique( $parsed_days ) ) : array( 3, 1 );
				$all['notify_user_volume']        = ! empty( $post['notify_user_volume'] );
				$all['notify_user_expiry']        = ! empty( $post['notify_user_expiry'] );
				$all['notify_user_users']         = ! empty( $post['notify_user_users'] );
				$all['notify_user_after_expire']  = ! empty( $post['notify_user_after_expire'] );
				$all['notify_idle_enabled']       = ! empty( $post['notify_idle_enabled'] );
				$all['notify_idle_after_days']    = max( 7, (int) ( $post['notify_idle_after_days'] ?? 45 ) );
				$all['notify_idle_cooldown_days'] = max( 7, (int) ( $post['notify_idle_cooldown_days'] ?? 90 ) );
				$all['notify_admin_panel_down']   = ! empty( $post['notify_admin_panel_down'] );
				$all['notify_admin_panel_down_cooldown'] = max( 5, (int) ( $post['notify_admin_panel_down_cooldown'] ?? 30 ) );
				$all['notify_panel_cost_expiry']  = ! empty( $post['notify_panel_cost_expiry'] );
				$all['alert_ip_warn_min_distinct']     = max( 1, (int) ( $post['alert_ip_warn_min_distinct'] ?? 3 ) );
				$all['alert_ip_warn_hysteresis']       = ! empty( $post['alert_ip_warn_hysteresis'] );
				$all['alert_ip_warn_cooldown_minutes'] = max( 0, (int) ( $post['alert_ip_warn_cooldown_minutes'] ?? 0 ) );
				break;
			case 'purge_expired':
				$all['purge_expired_enabled']      = ! empty( $post['purge_expired_enabled'] );
				$all['purge_expired_grace_days']   = max( 1, min( 365, (int) ( $post['purge_expired_grace_days'] ?? 7 ) ) );
				$warn_raw                          = isset( $post['purge_expired_warn_days'] ) ? sanitize_text_field( (string) $post['purge_expired_warn_days'] ) : '7,3,1,0';
				$parsed_warn                       = array();
				foreach ( array_filter( array_map( 'trim', explode( ',', $warn_raw ) ) ) as $part ) {
					if ( is_numeric( $part ) ) {
						$d = (int) $part;
						if ( $d >= 0 && $d <= 365 ) {
							$parsed_warn[] = $d;
						}
					}
				}
				$all['purge_expired_warn_days']    = ! empty( $parsed_warn ) ? array_values( array_unique( $parsed_warn ) ) : array( 7, 3, 1, 0 );
				$all['purge_expired_notify_user']  = ! empty( $post['purge_expired_notify_user'] );
				break;
			case 'finance':
				$all['notify_panel_cost_expiry'] = ! empty( $post['notify_panel_cost_expiry'] );
				$days                            = isset( $post['panel_cost_reminder_days'] ) ? sanitize_text_field( (string) $post['panel_cost_reminder_days'] ) : '7,1,0';
				$parsed                          = array();
				foreach ( array_filter( array_map( 'trim', explode( ',', $days ) ) ) as $part ) {
					if ( is_numeric( $part ) ) {
						$d = (int) $part;
						if ( $d >= 0 && $d <= 365 ) {
							$parsed[] = $d;
						}
					}
				}
				$all['panel_cost_reminder_days']       = ! empty( $parsed ) ? implode( ',', array_unique( $parsed ) ) : '7,1,0';
				$all['panel_cost_extend_days_on_paid'] = max( 1, min( 365, (int) ( $post['panel_cost_extend_days_on_paid'] ?? 30 ) ) );
				break;
			case 'plans_catalog':
				$all['default_concurrent_users'] = max( 0, (int) ( $post['default_concurrent_users'] ?? 2 ) );
				$all['price_per_extra_user']     = max( 0, (float) str_replace( ',', '.', (string) ( $post['price_per_extra_user'] ?? '0' ) ) );
				break;
			case 'referral':
				$all['referral_enabled']                  = ! empty( $post['referral_enabled'] );
				$all['referral_percent']                  = max( 0.0, min( 100.0, (float) str_replace( ',', '.', (string) ( $post['referral_percent'] ?? '0' ) ) ) );
				$all['referral_min_payout_base']          = max( 0.0, (float) str_replace( ',', '.', (string) ( $post['referral_min_payout_base'] ?? '0' ) ) );
				$all['referral_example_base_toman']       = max( 0.0, (float) str_replace( ',', '.', (string) ( $post['referral_example_base_toman'] ?? '170000' ) ) );
				$all['referral_example_invite_count']     = max( 1, (int) ( $post['referral_example_invite_count'] ?? 10 ) );
				$all['referral_require_approved_referrer'] = ! empty( $post['referral_require_approved_referrer'] );
				$all['telegram_bot_username']            = sanitize_text_field( (string) ( $post['telegram_bot_username'] ?? '' ) );
				$all['bale_bot_username']                 = sanitize_text_field( (string) ( $post['bale_bot_username'] ?? '' ) );
				break;
			case 'cards':
				if ( isset( $post['cards_display_mode'] ) ) {
					$mode = (string) $post['cards_display_mode'];
					$all['cards_display_mode'] = class_exists( 'SimpleVPBot_Card_Rotation' )
						? SimpleVPBot_Card_Rotation::sanitize_display_mode( $mode )
						: ( 'sequential' === sanitize_key( $mode ) ? 'sequential' : 'list' );
				}
				if ( isset( $post['payment_methods'] ) && class_exists( 'SimpleVPBot_Payment_Methods' ) ) {
					$raw = $post['payment_methods'];
					if ( is_string( $raw ) ) {
						$decoded = json_decode( $raw, true );
						$raw     = is_array( $decoded ) ? $decoded : array();
					}
					$all['payment_methods'] = SimpleVPBot_Payment_Methods::sanitize_map( is_array( $raw ) ? $raw : array() );
				}
				break;
			case 'force_join':
				$all['force_join_telegram_enabled'] = ! empty( $post['force_join_telegram_enabled'] );
				$all['force_join_telegram_chat_id'] = (int) ( $post['force_join_telegram_chat_id'] ?? 0 );
				$all['force_join_telegram_username'] = class_exists( 'SimpleVPBot_Required_Channel' )
					? SimpleVPBot_Required_Channel::normalize_username( $post['force_join_telegram_username'] ?? '' )
					: sanitize_text_field( ltrim( trim( (string) ( $post['force_join_telegram_username'] ?? '' ) ), '@' ) );
				$all['force_join_telegram_invite_link'] = esc_url_raw( trim( (string) ( $post['force_join_telegram_invite_link'] ?? '' ) ) );
				$all['force_join_telegram_prompt_text']   = sanitize_textarea_field( (string) ( $post['force_join_telegram_prompt_text'] ?? '' ) );
				$all['force_join_telegram_announce_text'] = sanitize_textarea_field( (string) ( $post['force_join_telegram_announce_text'] ?? '' ) );
				$all['force_join_bale_enabled'] = ! empty( $post['force_join_bale_enabled'] );
				$all['force_join_bale_chat_id'] = (int) ( $post['force_join_bale_chat_id'] ?? 0 );
				$all['force_join_bale_username'] = class_exists( 'SimpleVPBot_Required_Channel' )
					? SimpleVPBot_Required_Channel::normalize_username( $post['force_join_bale_username'] ?? '' )
					: sanitize_text_field( ltrim( trim( (string) ( $post['force_join_bale_username'] ?? '' ) ), '@' ) );
				$all['force_join_bale_invite_link'] = esc_url_raw( trim( (string) ( $post['force_join_bale_invite_link'] ?? '' ) ) );
				$all['force_join_bale_prompt_text']   = sanitize_textarea_field( (string) ( $post['force_join_bale_prompt_text'] ?? '' ) );
				$all['force_join_bale_announce_text'] = sanitize_textarea_field( (string) ( $post['force_join_bale_announce_text'] ?? '' ) );
				break;
			case 'receipts':
				$raw = $post['receipt_reject_reasons'] ?? array();
				if ( is_string( $raw ) ) {
					$raw = preg_split( '/\r\n|\r|\n/', $raw );
				}
				$reasons = array();
				foreach ( (array) $raw as $reason ) {
					$text = trim( sanitize_textarea_field( (string) $reason ) );
					if ( '' !== $text ) {
						$reasons[] = $text;
					}
				}
				$all['receipt_reject_reasons'] = ! empty( $reasons )
					? array_values( array_unique( $reasons ) )
					: SimpleVPBot_Settings::defaults()['receipt_reject_reasons'];
				break;
			default:
				return false;
		}
		SimpleVPBot_Settings::update( $all );
		SimpleVPBot_Texts::clear_cache();
		return true;
	}

	/**
	 * Side effects after a tab was saved (cron reschedule, etc.).
	 *
	 * @param string $tab Tab key.
	 */
	public static function after_settings_tab_saved( $tab ) {
		if ( 'backup' === $tab ) {
			$name      = SimpleVPBot_Settings::backup_schedule_name();
			$schedules = wp_get_schedules();
			$interval  = isset( $schedules[ $name ] ) ? $name : 'hourly';
			SimpleVPBot_Cron_Manager::schedule_backup_event( $interval );
		}
		if ( 'bots' === $tab || 'relay' === $tab ) {
			if ( 'bots' === $tab ) {
				SimpleVPBot_Settings::ensure_secrets();
			}
			$s = SimpleVPBot_Settings::all();
			if ( 'bots' === $tab && ! empty( self::$bots_tab_tokens_updated ) && class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
				SimpleVPBot_Service_Admin_Ops::sync_main_bot_usernames( self::$bots_tab_tokens_updated );
				self::$bots_tab_tokens_updated = array();
			}
			if ( class_exists( 'SimpleVPBot_Telegram_Relay' ) ) {
				SimpleVPBot_Telegram_Relay::maybe_sync_after_settings();
			}
			if ( 'bots' === $tab && class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
				$tg_on = ! class_exists( 'SimpleVPBot_Platforms' ) || SimpleVPBot_Platforms::main_platform_flag( 'telegram', $s );
				$bl_on = ! class_exists( 'SimpleVPBot_Platforms' ) || SimpleVPBot_Platforms::main_platform_flag( 'bale', $s );
				if ( $tg_on && '' !== trim( (string) ( $s['telegram_token'] ?? '' ) ) ) {
					SimpleVPBot_Service_Admin_Ops::set_webhook_telegram();
				}
				if ( $bl_on && '' !== trim( (string) ( $s['bale_token'] ?? '' ) ) ) {
					SimpleVPBot_Service_Admin_Ops::set_webhook_bale();
				}
			}
		}
	}

	/**
	 * Toggle a boolean setting key (for bot inline toggles).
	 *
	 * @param string $key Settings key (subset).
	 * @return bool Whether key was toggled.
	 */
	public static function toggle_bool_setting( $key ) {
		$key = sanitize_key( (string) $key );
		$allowed = array( 'enabled', 'test_account_enabled' );
		if ( ! in_array( $key, $allowed, true ) ) {
			return false;
		}
		$all         = SimpleVPBot_Settings::all();
		$all[ $key ] = empty( $all[ $key ] );
		SimpleVPBot_Settings::update( $all );
		SimpleVPBot_Texts::clear_cache();
		return true;
	}
}
