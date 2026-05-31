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
			'notify_idle_enabled'        => false,
			'notify_idle_after_days'    => 45,
			'notify_idle_cooldown_days' => 90,
			'notify_admin_panel_down'    => true,
			'notify_admin_panel_down_cooldown' => 30,
			'webhook_rate_limit_per_min' => 120,
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
			'receipt_reject_reasons'         => array(
				'مبلغ واریزی با مبلغ سفارش مطابقت ندارد.',
				'تصویر رسید واضح نیست.',
				'رسید تکراری یا نامعتبر است.',
				'پرداخت در حساب مقصد پیدا نشد.',
			),
			'default_bot_locale'             => 'fa',
			'service_naming_mode'            => 'legacy',
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
			'notify_idle_enabled',
			'notify_idle_after_days',
			'notify_idle_cooldown_days',
			'webhook_rate_limit_per_min',
			'rate_limit_trust_forwarded_for',
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

