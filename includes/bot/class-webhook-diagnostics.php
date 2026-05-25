<?php
/**
 * Admin-only REST diagnostics for webhook URLs and bot configuration (no secrets exposed).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Webhook_Diagnostics
 */
class SimpleVPBot_Webhook_Diagnostics {

	/**
	 * Register routes.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * REST routes.
	 */
	public static function register_routes() {
		register_rest_route(
			'simplevpbot/v1',
			'/diagnostics/bot-webhooks',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle' ),
				'permission_callback' => array( __CLASS__, 'perm_admin' ),
			)
		);
	}

	/**
	 * @return bool
	 */
	public static function perm_admin() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET diagnostics payload.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function handle( WP_REST_Request $req ) {
		$base = SimpleVPBot_Settings::public_site_url();

		$tg_sec_main = (string) SimpleVPBot_Settings::get( 'telegram_webhook_secret', '' );
		$bl_sec_main = (string) SimpleVPBot_Settings::get( 'bale_webhook_secret', '' );
		$tg_hdr      = trim( (string) SimpleVPBot_Settings::get( 'telegram_secret_header', '' ) );

		$main = array(
			'plugin_bot_processing_enabled' => (bool) SimpleVPBot_Settings::get( 'enabled', true ),
			'telegram_token_configured'       => '' !== trim( (string) SimpleVPBot_Settings::get( 'telegram_token', '' ) ),
			'bale_token_configured'           => '' !== trim( (string) SimpleVPBot_Settings::get( 'bale_token', '' ) ),
			'telegram_webhook_secret_set'   => '' !== $tg_sec_main,
			'bale_webhook_secret_set'         => '' !== $bl_sec_main,
			'telegram_secret_header_required' => '' !== $tg_hdr,
			'paths'                           => array(
				'main_telegram' => '/wp-json/simplevpbot/v1/webhook/telegram/{telegram_webhook_secret}',
				'main_bale'     => '/wp-json/simplevpbot/v1/webhook/bale/{bale_webhook_secret}',
				'reseller'      => '/wp-json/simplevpbot/v1/webhook/{telegram|bale}/reseller/{reseller_svp_user_id}/{webhook_secret}',
			),
			'curl_note'                       => 'POST JSON body = Telegram update. If telegram_secret_header is set in settings, send header X-Telegram-Bot-Api-Secret-Token with the same value.',
		);

		if ( '' !== $tg_sec_main ) {
			$main['telegram_webhook_url_example'] = $base . '/wp-json/simplevpbot/v1/webhook/telegram/' . rawurlencode( $tg_sec_main );
		}
		if ( '' !== $bl_sec_main ) {
			$main['bale_webhook_url_example'] = $base . '/wp-json/simplevpbot/v1/webhook/bale/' . rawurlencode( $bl_sec_main );
		}

		$resellers_out = array();
		if ( class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			$rows = SimpleVPBot_Model_Reseller_Bot_Profile::list_paginated( 500, 0 );
			foreach ( $rows as $row ) {
				$rid = (int) ( $row->reseller_svp_user_id ?? 0 );
				if ( $rid < 1 ) {
					continue;
				}
				$wsec = trim( (string) ( $row->webhook_secret ?? '' ) );
				$tg_t = class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' )
					? SimpleVPBot_Model_Reseller_Bot_Profile::token_for_platform( $row, 'telegram' )
					: '';
				$bl_t = class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' )
					? SimpleVPBot_Model_Reseller_Bot_Profile::token_for_platform( $row, 'bale' )
					: '';
				$rhdr = trim( (string) ( $row->telegram_secret_token ?? '' ) );
				$item = array(
					'reseller_svp_user_id'          => $rid,
					'profile_enabled'               => ! isset( $row->enabled ) || (int) $row->enabled !== 0,
					'telegram_token_configured'     => '' !== $tg_t,
					'bale_token_configured'          => '' !== $bl_t,
					'webhook_secret_set'             => '' !== $wsec,
					'telegram_secret_token_required' => '' !== $rhdr,
				);
				if ( '' !== $wsec ) {
					$item['telegram_reseller_webhook_url_example'] = $base . '/wp-json/simplevpbot/v1/webhook/telegram/reseller/' . $rid . '/' . rawurlencode( $wsec );
					$item['bale_reseller_webhook_url_example']    = $base . '/wp-json/simplevpbot/v1/webhook/bale/reseller/' . $rid . '/' . rawurlencode( $wsec );
				}
				$resellers_out[] = $item;
			}
		}

		return new WP_REST_Response(
			array(
				'public_site_url' => $base,
				'main'            => $main,
				'resellers'       => $resellers_out,
				'http_hints'      => array(
					'403_on_webhook' => 'Wrong path secret, or missing/wrong X-Telegram-Bot-Api-Secret-Token when a secret token is configured.',
					'429_on_webhook' => 'Webhook rate limit (webhook_rate_limit_per_min).',
					'200_no_reply'   => 'See logs: plugin disabled, reseller profile disabled, empty bot token for current context, or router ignored update type.',
				),
			),
			200
		);
	}
}
