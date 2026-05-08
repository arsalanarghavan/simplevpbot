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
			'default_bot_locale'             => 'fa',
			'bot_ui_layouts'                 => array(),
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
}

