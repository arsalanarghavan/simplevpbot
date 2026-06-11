<?php
/**
 * Options / settings API.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Settings
 */
class SimpleVPBot_Settings {

	const OPTION_KEY = 'simplevpbot_settings';

	/**
	 * Defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'enabled'                    => true,
			'telegram_enabled'           => true,
			'bale_enabled'               => true,
			'test_account_enabled'       => false,
			'admin_telegram_ids'         => array(),
			'admin_bale_ids'             => array(),
			'telegram_token'             => '',
			'telegram_webhook_secret'    => '',
			'telegram_secret_header'     => '',
			'bale_token'                 => '',
			'bale_webhook_secret'        => '',
			'panel_url'                  => '',
			'panel_username'             => '',
			'panel_password'             => '',
			'panel_api_base'             => 'panel/api',
			'panel_login_secret'         => '',
			'panel_api_token'            => '',
			'subscription_public_base'   => '',
			'portal_page_id'             => 0,
			// پلن Xray فعال برای قیمت وقتی سرویس plan_id ندارد یا پلنش غیرفعال است.
			'default_service_plan_id'    => 0,
			'portal_link_secret'         => '',
			'backup_interval_minutes'         => 60,
			'backup_telegram_chat_id'         => 0,
			'backup_bale_chat_id'             => 0,
			'backup_send_telegram_admins'     => true,
			'backup_send_bale_admins'         => true,
			'backup_send_telegram_channel'    => true,
			'backup_send_bale_channel'        => true,
			'backup_store_on_site'            => false,
			'backup_site_retention_count'     => 14,
			'backup_max_zip_mb'               => 0,
			'crypto_ipn_path_secret'    => '',
			'crypto_nowpayments_api_key' => '',
			'crypto_nowpayments_ipn_secret' => '',
			'crypto_nowpayments_pay_currency' => 'usdttrc20',
			'crypto_toman_per_usd'       => 50000.0,
			'notify_expiry_days'         => array( 3, 1 ),
			'notify_low_traffic_percent' => 10,
			'default_concurrent_users'   => 2,
			'price_per_extra_user'       => 0.0,
			'notify_user_volume'         => true,
			'notify_user_expiry'         => true,
			'notify_user_users'          => true,
			'notify_user_after_expire'   => true,
			'purge_expired_enabled'      => false,
			'purge_expired_grace_days'   => 7,
			'purge_expired_warn_days'    => array( 7, 3, 1, 0 ),
			'purge_expired_notify_user'  => true,
			'notify_idle_enabled'        => false,
			'notify_idle_after_days'    => 45,
			'notify_idle_cooldown_days' => 90,
			'notify_admin_panel_down'    => true,
			'notify_admin_panel_down_cooldown' => 30,
			'notify_panel_cost_expiry'   => true,
			'panel_cost_reminder_days'   => '7,1,0',
			'panel_cost_extend_days_on_paid' => 30,
			'webhook_rate_limit_per_min' => 120,
			'webhook_reseller_rate_limit_per_min' => 60,
			/** When false (default), webhook/dashboard RL uses REMOTE_ADDR only (avoid forged X-Forwarded-For). Enable behind a trusted reverse proxy. */
			'rate_limit_trust_forwarded_for' => false,
			'bale_wallet_provider_token' => '',
			'referral_enabled'               => false,
			'referral_percent'               => 0.0,
			'referral_min_payout_base'       => 0.0,
			'referral_example_base_toman'    => 170000.0,
			'referral_example_invite_count' => 10,
			'referral_require_approved_referrer' => true,
			'telegram_bot_username'          => '',
			'bale_bot_username'              => '',
			'force_join_telegram_enabled'    => false,
			'force_join_telegram_chat_id'    => 0,
			'force_join_telegram_username'     => '',
			'force_join_telegram_invite_link'  => '',
			'force_join_telegram_prompt_text'  => '',
			'force_join_telegram_announce_text' => '',
			'force_join_bale_enabled'          => false,
			'force_join_bale_chat_id'          => 0,
			'force_join_bale_username'         => '',
			'force_join_bale_invite_link'      => '',
			'force_join_bale_prompt_text'      => '',
			'force_join_bale_announce_text'    => '',
			'force_join_cache_ttl_sec'         => 180,
			'force_join_negative_cache_ttl_sec' => 45,
			'bot_admin_notify_usleep_us'       => 80000,
			'bot_interactive_timeout_sec'    => 8,
			'buy_catalog_cache_ttl_sec'      => 90,
			'crypto_invoice_timeout_sec'     => 12,
			'broadcast_batch_size'           => 20,
			'broadcast_usleep_us'            => 280000,
			'broadcast_max_retries'          => 8,
			'broadcast_sending_timeout_sec'  => 600,
			'broadcast_api_timeout_sec'      => 35,
			// IP-cap alert tuning (distinct client IPs from panel vs plan slots).
			'alert_ip_warn_min_distinct'     => 3,
			'alert_ip_warn_hysteresis'       => true,
			'alert_ip_warn_cooldown_minutes' => 0,
			'crisis_mode'                    => false,
			'suppress_bulk_user_notifications' => false,
			'cards_display_mode'             => 'list',
			'cards_rotation_cursors'         => array(),
			'payment_methods'                => array(
				'c2c'          => true,
				'crypto'       => true,
				'crypto_auto'  => true,
				'bale_wallet'  => true,
				'site_wallet'  => true,
				'wallet_topup' => true,
			),
			'receipt_reject_reasons'         => array(
				'مبلغ واریزی با مبلغ سفارش مطابقت ندارد.',
				'تصویر رسید واضح نیست.',
				'رسید تکراری یا نامعتبر است.',
				'پرداخت در حساب مقصد پیدا نشد.',
			),
			'default_bot_locale'             => 'fa',
			'service_naming_mode'            => 'legacy',
			/** When set, replaces panel subscription #fragment labels in config lists (bot/portal/dashboard). */
			'subscription_config_label_override' => '',
			/** Prefix for prefix_numbered mode labels, e.g. GoatVPN-1001. */
			'config_label_prefix'              => '',
			/** First parenthesized number per service when prefix_numbered mode is active. */
			'config_label_number_start'        => 1001,
			/** JSON map panelId:inboundId => custom inbound display name (admin). */
			'inbound_display_names'            => array(),
			/** When true, config #fragment labels use explicit inbound alias + suffix (no panel remark fallback). */
			'config_label_prepend_inbound'     => false,
			'bot_ui_layouts'                 => array(),
			/** When false, L2TP is hidden from dashboard, bot, and portal (data/cron unchanged). */
			'l2tp_enabled'                   => false,
			'dashboard_site_name'            => '',
			'dashboard_site_icon_url'        => '',
			'branding_logo_url'              => '',
			'branding_favicon_url'           => '',
			'branding_theme_primary'         => '',
			'branding_theme_accent'          => '',
			'branding_custom_domain'         => '',
			'support_info'                   => '',
			'support_telegram_username'      => '',
			'support_bale_username'          => '',
			'telegram_proxy_enabled'         => false,
			'telegram_proxy_type'            => 'http',
			'telegram_proxy_host'            => '',
			'telegram_proxy_port'            => 0,
			'telegram_proxy_username'        => '',
			'telegram_proxy_password'        => '',
			'telegram_api_base_url'          => '',
			'telegram_relay_enabled'         => false,
			'telegram_relay_base_url'        => '',
			'telegram_relay_public_url'      => '',
			'telegram_relay_shared_secret'     => '',
			'telegram_relay_wp_forward_url'  => '',
			'telegram_relay_tenant_id'         => '',
			'telegram_relay_allowed_ips'     => '',
			'telegram_relay_force'           => false,
			'default_reseller_permissions'   => array(),
		);
	}

	/**
	 * Init.
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedules' ) );
	}

	/**
	 * Add dynamic every-N-minutes schedule for backup.
	 *
	 * @param array<string, array<string, int|string>> $schedules Schedules.
	 * @return array<string, array<string, int|string>>
	 */
	public static function add_cron_schedules( $schedules ) {
		$mins = max( 5, (int) self::get( 'backup_interval_minutes', 60 ) );
		$key   = 'simplevpbot_every_' . $mins . '_minutes';
		if ( ! isset( $schedules[ $key ] ) ) {
			$schedules[ $key ] = array(
				'interval' => $mins * MINUTE_IN_SECONDS,
				'display'  => sprintf( /* translators: %d minutes */ __( 'Every %d minutes (SimpleVPBot backup)', 'simplevpbot' ), $mins ),
			);
		}
		if ( ! isset( $schedules['simplevpbot_minute'] ) ) {
			$schedules['simplevpbot_minute'] = array(
				'interval' => MINUTE_IN_SECONDS,
				'display'  => __( 'Every minute (SimpleVPBot)', 'simplevpbot' ),
			);
		}
		if ( ! isset( $schedules['simplevpbot_10min'] ) ) {
			$schedules['simplevpbot_10min'] = array(
				'interval' => 10 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 10 minutes (SimpleVPBot)', 'simplevpbot' ),
			);
		}
		return $schedules;
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all() {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( self::defaults(), $saved );
	}

	/**
	 * Get one key.
	 *
	 * @param string $key Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Microseconds to sleep between sequential admin bot API calls.
	 *
	 * @return int
	 */
	public static function bot_admin_notify_usleep() {
		return max( 0, min( 2000000, (int) self::get( 'bot_admin_notify_usleep_us', 80000 ) ) );
	}

	/**
	 * HTTP timeout for interactive bot API calls (buy flow, callbacks).
	 *
	 * @return int
	 */
	public static function bot_interactive_timeout_sec() {
		return max( 3, min( 30, (int) self::get( 'bot_interactive_timeout_sec', 8 ) ) );
	}

	/**
	 * Buy catalog transient cache TTL (seconds).
	 *
	 * @return int
	 */
	public static function buy_catalog_cache_ttl_sec() {
		return max( 15, min( 600, (int) self::get( 'buy_catalog_cache_ttl_sec', 90 ) ) );
	}

	/**
	 * NOWPayments invoice HTTP timeout (seconds).
	 *
	 * @return int
	 */
	public static function crypto_invoice_timeout_sec() {
		return max( 5, min( 30, (int) self::get( 'crypto_invoice_timeout_sec', 12 ) ) );
	}

	/**
	 * Update settings (merge).
	 *
	 * @param array<string, mixed> $data Data.
	 */
	public static function update( array $data ) {
		$merged = array_merge( self::all(), $data );
		update_option( self::OPTION_KEY, $merged );
	}

	/**
	 * Ensure webhook secrets exist.
	 */
	public static function ensure_secrets() {
		$all = self::all();
		$changed = false;
		if ( empty( $all['telegram_webhook_secret'] ) ) {
			$all['telegram_webhook_secret'] = wp_generate_password( 32, false, false );
			$changed = true;
		}
		if ( empty( $all['bale_webhook_secret'] ) ) {
			$all['bale_webhook_secret'] = wp_generate_password( 32, false, false );
			$changed = true;
		}
		if ( empty( $all['telegram_secret_header'] ) ) {
			$all['telegram_secret_header'] = wp_generate_password( 32, false, false );
			$changed = true;
		}
		if ( empty( $all['portal_link_secret'] ) ) {
			$all['portal_link_secret'] = wp_generate_password( 48, false, false );
			$changed = true;
		}
		if ( empty( $all['crypto_ipn_path_secret'] ) ) {
			$all['crypto_ipn_path_secret'] = wp_generate_password( 32, false, false );
			$changed = true;
		}
		if ( class_exists( 'SimpleVPBot_Telegram_Relay' ) ) {
			SimpleVPBot_Telegram_Relay::ensure_relay_secret();
		} elseif ( empty( $all['telegram_relay_shared_secret'] ) ) {
			$all['telegram_relay_shared_secret'] = wp_generate_password( 48, false, false );
			$changed = true;
		}
		if ( $changed ) {
			update_option( self::OPTION_KEY, $all );
		}
	}

	/**
	 * Site URL for webhooks.
	 *
	 * @return string
	 */
	public static function public_site_url() {
		$url = home_url( '/' );
		if ( is_ssl() ) {
			$url = set_url_scheme( $url, 'https' );
		}
		return untrailingslashit( $url );
	}

	/**
	 * Dynamic backup cron schedule name.
	 *
	 * @return string
	 */
	public static function backup_schedule_name() {
		$mins = max( 5, (int) self::get( 'backup_interval_minutes', 60 ) );
		return 'simplevpbot_every_' . $mins . '_minutes';
	}

	/**
	 * Non-sensitive keys for reseller dashboard (no tokens, payment secrets, or panel credentials).
	 *
	 * @return array<string, mixed>
	 */
	public static function dashboard_slice_for_reseller_operator() {
		$all  = self::all();
		$keys = array(
			'enabled',
			'test_account_enabled',
			'default_concurrent_users',
			'price_per_extra_user',
			'cards_display_mode',
			'payment_methods',
			'default_bot_locale',
			'telegram_bot_username',
			'bale_bot_username',
			'referral_enabled',
			'referral_percent',
			'referral_min_payout_base',
			'referral_example_base_toman',
			'referral_example_invite_count',
			'referral_require_approved_referrer',
			'broadcast_batch_size',
			'broadcast_usleep_us',
			'broadcast_max_retries',
			'broadcast_sending_timeout_sec',
			'broadcast_api_timeout_sec',
			'crisis_mode',
			'suppress_bulk_user_notifications',
			'alert_ip_warn_min_distinct',
			'alert_ip_warn_hysteresis',
			'alert_ip_warn_cooldown_minutes',
			'notify_expiry_days',
			'notify_low_traffic_percent',
			'notify_user_volume',
			'notify_user_expiry',
			'notify_user_users',
			'notify_user_after_expire',
			'purge_expired_enabled',
			'purge_expired_grace_days',
			'purge_expired_warn_days',
			'purge_expired_notify_user',
			'notify_idle_enabled',
			'notify_idle_after_days',
			'notify_idle_cooldown_days',
			'webhook_rate_limit_per_min',
			'webhook_reseller_rate_limit_per_min',
			'rate_limit_trust_forwarded_for',
			'service_naming_mode',
			'config_label_number_start',
		);
		$out = array();
		foreach ( $keys as $k ) {
			if ( array_key_exists( $k, $all ) ) {
				$out[ $k ] = $all[ $k ];
			}
		}
		return $out;
	}

	/**
	 * Dashboard sidebar display name (falls back to WP site name).
	 *
	 * @return string
	 */
	public static function dashboard_site_display_name() {
		$name = trim( (string) self::get( 'dashboard_site_name', '' ) );
		return '' !== $name ? $name : (string) get_bloginfo( 'name' );
	}

	/**
	 * Dashboard sidebar icon URL (empty if unset).
	 *
	 * @return string
	 */
	public static function dashboard_site_icon_url_resolved() {
		return esc_url_raw( trim( (string) self::get( 'dashboard_site_icon_url', '' ) ) );
	}

	/**
	 * Whether config list labels prepend an inbound alias before the service suffix.
	 *
	 * @return bool
	 */
	public static function config_label_prepend_inbound() {
		return (bool) self::get( 'config_label_prepend_inbound', false );
	}

	/**
	 * Admin inbound display aliases: keys "panelId:inboundId".
	 *
	 * @return array<string, string>
	 */
	public static function inbound_display_names_map() {
		$raw = self::get( 'inbound_display_names', array() );
		if ( is_string( $raw ) ) {
			$dec = json_decode( $raw, true );
			$raw = is_array( $dec ) ? $dec : array();
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $key => $val ) {
			$k = preg_replace( '/[^0-9:]/', '', (string) $key );
			$v = trim( sanitize_text_field( (string) $val ) );
			if ( '' !== $k && '' !== $v ) {
				$out[ $k ] = $v;
			}
		}
		return $out;
	}

	/**
	 * Parse inbound alias map from settings_tab POST (object or JSON string).
	 *
	 * @param mixed $raw POST value.
	 * @return array<string, string>
	 */
	public static function sanitize_inbound_display_names_input( $raw ) {
		if ( is_string( $raw ) ) {
			$dec = json_decode( $raw, true );
			$raw = is_array( $dec ) ? $dec : array();
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $key => $val ) {
			$k = preg_replace( '/[^0-9:]/', '', (string) $key );
			$v = trim( sanitize_text_field( (string) $val ) );
			if ( '' !== $k && '' !== $v ) {
				$out[ $k ] = $v;
			}
		}
		return $out;
	}

	/**
	 * Settings payload for super-admin dashboard (secrets masked).
	 *
	 * @return array<string, mixed>
	 */
	public static function settings_for_dashboard_admin() {
		$all = self::all();
		unset( $all['telegram_webhook_secret'], $all['bale_webhook_secret'] );
		$secret_keys = array(
			'telegram_token',
			'bale_token',
			'panel_password',
			'panel_api_token',
			'portal_link_secret',
			'crypto_nowpayments_api_key',
			'crypto_nowpayments_ipn_secret',
			'crypto_ipn_path_secret',
			'bale_wallet_provider_token',
			'telegram_proxy_password',
			'telegram_relay_shared_secret',
		);
		foreach ( $secret_keys as $k ) {
			if ( ! empty( $all[ $k ] ) ) {
				$all[ $k . '_set' ] = true;
				if ( 'telegram_proxy_password' === $k ) {
					$all[ $k ] = '';
				} else {
					unset( $all[ $k ] );
				}
			}
		}
		if ( ! is_array( $all['default_reseller_permissions'] ?? null ) || empty( $all['default_reseller_permissions'] ) ) {
			$all['default_reseller_permissions'] = class_exists( 'SimpleVPBot_Model_User' )
				? SimpleVPBot_Model_User::default_reseller_permissions_template()
				: array();
		}
		$all['last_purge_expired_run'] = class_exists( 'SimpleVPBot_Cron_Purge_Expired' )
			? SimpleVPBot_Cron_Purge_Expired::last_run_stats()
			: array();
		$all['telegram_relay_last_sync_at'] = (int) get_option( 'simplevpbot_relay_last_sync_at', 0 );
		if ( class_exists( 'SimpleVPBot_Telegram_Relay' ) && SimpleVPBot_Telegram_Relay::is_enabled() ) {
			$all['telegram_relay_domains'] = SimpleVPBot_Telegram_Relay::collect_domains();
		}
		return $all;
	}

	/**
	 * Normalized default reseller permission map for storage/UI.
	 *
	 * @param array<string, mixed>|null $raw Raw from POST.
	 * @return array<string, bool>
	 */
	public static function normalize_default_reseller_permissions( $raw ) {
		$keys = class_exists( 'SimpleVPBot_Model_User' )
			? SimpleVPBot_Model_User::RESELLER_PERMISSION_KEYS
			: array();
		$out  = array();
		foreach ( $keys as $k ) {
			$out[ $k ] = is_array( $raw ) && array_key_exists( $k, $raw ) ? ! empty( $raw[ $k ] ) : true;
		}
		return $out;
	}
}

