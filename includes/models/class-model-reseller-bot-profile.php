<?php
/**
 * Per-reseller bot tokens, webhook secret, white-label brand name.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Reseller_Bot_Profile
 */
class SimpleVPBot_Model_Reseller_Bot_Profile {

	/**
	 * Table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_reseller_bot_profiles';
	}

	/**
	 * @param int $reseller_svp_user_id Id.
	 * @return object|null
	 */
	public static function find_by_reseller( $reseller_svp_user_id ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		if ( $r < 1 ) {
			return null;
		}
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE reseller_svp_user_id = %d LIMIT 1', $r )
		); // phpcs:ignore
	}

	/**
	 * Ensure non-empty webhook secret for reseller (generates and persists).
	 *
	 * @param int $reseller_svp_user_id Id.
	 * @return string Secret for URL path.
	 */
	public static function ensure_webhook_secret( $reseller_svp_user_id ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		if ( $r < 1 ) {
			return '';
		}
		$row = self::find_by_reseller( $r );
		if ( $row && '' !== trim( (string) ( $row->webhook_secret ?? '' ) ) ) {
			return (string) $row->webhook_secret;
		}
		$sec = self::generate_webhook_secret_value();
		$t   = self::table();
		$now = current_time( 'mysql' );
		if ( $row ) {
			$wpdb->update(
				$t,
				array(
					'webhook_secret' => $sec,
					'updated_at'     => $now,
				),
				array( 'reseller_svp_user_id' => $r ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			return $sec;
		}
		$wpdb->insert(
			$t,
			array(
				'reseller_svp_user_id' => $r,
				'telegram_token'       => '',
				'bale_token'             => '',
				'webhook_secret'       => $sec,
				'brand_name'           => '',
				'telegram_secret_token' => '',
				'updated_at'             => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return $sec;
	}

	/**
	 * @return string
	 */
	private static function generate_webhook_secret_value() {
		if ( function_exists( 'wp_generate_password' ) ) {
			return (string) wp_generate_password( 32, false, false );
		}
		return bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Rotate webhook secret (invalidates old webhook URL).
	 *
	 * @param int $reseller_svp_user_id Id.
	 * @return string New secret.
	 */
	public static function rotate_webhook_secret( $reseller_svp_user_id ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		if ( $r < 1 ) {
			return '';
		}
		$sec = self::generate_webhook_secret_value();
		$row = self::find_by_reseller( $r );
		$t   = self::table();
		$now = current_time( 'mysql' );
		if ( $row ) {
			$wpdb->update(
				$t,
				array(
					'webhook_secret' => $sec,
					'updated_at'     => $now,
				),
				array( 'reseller_svp_user_id' => $r ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			return $sec;
		}
		return self::ensure_webhook_secret( $r );
	}

	/**
	 * Upsert tokens (empty string clears). Optionally updates brand_name.
	 * Ensures webhook_secret exists when any token is saved.
	 *
	 * @param int    $reseller_svp_user_id Id.
	 * @param string $tg_token             Telegram bot token.
	 * @param string $bale_token             Bale token.
	 * @param string $brand_name           Display brand for config fragment (optional).
	 * @return void
	 */
	public static function upsert_tokens( $reseller_svp_user_id, $tg_token, $bale_token, $brand_name = null ) {
		global $wpdb;
		$r   = (int) $reseller_svp_user_id;
		$tg  = sanitize_text_field( (string) $tg_token );
		$bl  = sanitize_text_field( (string) $bale_token );
		$t   = self::table();
		$now = current_time( 'mysql' );
		$ex  = self::find_by_reseller( $r );
		$need_secret = ( strlen( $tg ) > 0 || strlen( $bl ) > 0 );
		$wh_sec      = '';
		if ( $ex && '' !== trim( (string) ( $ex->webhook_secret ?? '' ) ) ) {
			$wh_sec = (string) $ex->webhook_secret;
		} elseif ( $need_secret ) {
			$wh_sec = self::generate_webhook_secret_value();
		}
		$brand_upd = null !== $brand_name ? mb_substr( sanitize_text_field( (string) $brand_name ), 0, 255, 'UTF-8' ) : null;
		if ( $ex ) {
			$data = array(
				'telegram_token' => $tg,
				'bale_token'     => $bl,
				'updated_at'     => $now,
			);
			$format = array( '%s', '%s', '%s' );
			if ( '' !== $wh_sec && ( ! isset( $ex->webhook_secret ) || '' === trim( (string) ( $ex->webhook_secret ?? '' ) ) ) ) {
				$data['webhook_secret'] = $wh_sec;
				$format[]               = '%s';
			}
			if ( null !== $brand_upd ) {
				$data['brand_name'] = $brand_upd;
				$format[]           = '%s';
			}
			$wpdb->update(
				$t,
				$data,
				array( 'reseller_svp_user_id' => $r ),
				$format,
				array( '%d' )
			);
			return;
		}
		if ( $need_secret && '' === $wh_sec ) {
			$wh_sec = self::generate_webhook_secret_value();
		}
		$insert = array(
			'reseller_svp_user_id' => $r,
			'telegram_token'       => $tg,
			'bale_token'           => $bl,
			'webhook_secret'       => $wh_sec,
			'brand_name'           => null !== $brand_upd ? $brand_upd : '',
			'telegram_secret_token' => '',
			'updated_at'           => $now,
		);
		$wpdb->insert(
			$t,
			$insert,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Persist Telegram secret token for setWebhook (optional API validation header).
	 *
	 * @param int    $reseller_svp_user_id Id.
	 * @param string $token                  Empty clears.
	 */
	public static function save_telegram_secret_token( $reseller_svp_user_id, $token ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		if ( $r < 1 ) {
			return;
		}
		$tok = sanitize_text_field( (string) $token );
		$row = self::find_by_reseller( $r );
		$t   = self::table();
		$now = current_time( 'mysql' );
		if ( ! $row ) {
			self::ensure_webhook_secret( $r );
			$row = self::find_by_reseller( $r );
		}
		if ( ! $row ) {
			return;
		}
		$wpdb->update(
			$t,
			array(
				'telegram_secret_token' => $tok,
				'updated_at'            => $now,
			),
			array( 'reseller_svp_user_id' => $r ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}
}
