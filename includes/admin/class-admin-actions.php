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
				'cards_display_mode'         => in_array( (string) ( $s['cards_display_mode'] ?? 'list' ), array( 'list', 'sequential' ), true ) ? (string) $s['cards_display_mode'] : 'list',
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
			return array(
				'cards_display_mode' => sanitize_key( (string) ( $s['cards_display_mode'] ?? 'list' ) ),
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
		if ( empty( $base ) && ! in_array( $tab, array( 'general', 'bots', 'panel', 'notifications', 'referral', 'plans_catalog', 'backup', 'cards' ), true ) ) {
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
				$mode = isset( $post['cards_display_mode'] ) ? sanitize_key( (string) $post['cards_display_mode'] ) : 'list';
				$all['cards_display_mode'] = in_array( $mode, array( 'list', 'sequential' ), true ) ? $mode : 'list';
				break;
			case 'bots':
				$all['telegram_token']           = sanitize_text_field( (string) ( $post['telegram_token'] ?? '' ) );
				$all['bale_token']               = sanitize_text_field( (string) ( $post['bale_token'] ?? '' ) );
				// Optional path secrets: dashboard omits keys so DB values stay; bot merge sends keys explicitly.
				if ( array_key_exists( 'telegram_webhook_secret', $post ) ) {
					$all['telegram_webhook_secret'] = sanitize_text_field( (string) $post['telegram_webhook_secret'] );
				}
				if ( array_key_exists( 'bale_webhook_secret', $post ) ) {
					$all['bale_webhook_secret'] = sanitize_text_field( (string) $post['bale_webhook_secret'] );
				}
				$all['telegram_secret_header']   = sanitize_text_field( (string) ( $post['telegram_secret_header'] ?? '' ) );
				$all['bale_wallet_provider_token'] = sanitize_text_field( (string) ( $post['bale_wallet_provider_token'] ?? '' ) );
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
				$mode = sanitize_key( (string) ( $post['cards_display_mode'] ?? 'list' ) );
				if ( 'sequential' !== $mode ) {
					$mode = 'list';
				}
				$all['cards_display_mode'] = $mode;
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
			SimpleVPBot_Cron_Manager::clear_backup();
			SimpleVPBot_Cron_Manager::schedule_all();
		}
		if ( 'bots' === $tab ) {
			SimpleVPBot_Settings::ensure_secrets();
			$s = SimpleVPBot_Settings::all();
			if ( ! empty( $s['enabled'] ) ) {
				if ( '' !== trim( (string) ( $s['telegram_token'] ?? '' ) ) && class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
					SimpleVPBot_Service_Admin_Ops::set_webhook_telegram();
				}
				if ( '' !== trim( (string) ( $s['bale_token'] ?? '' ) ) && class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
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
