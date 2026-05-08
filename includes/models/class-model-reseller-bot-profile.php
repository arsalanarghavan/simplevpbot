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
	 * Encode admin id list as JSON for DB.
	 *
	 * @param array<int, int> $ids Ids.
	 * @return string
	 */
	public static function encode_admin_ids( array $ids ) {
		$clean = array_values( array_unique( array_map( 'intval', $ids ) ) );
		return (string) wp_json_encode( $clean );
	}

	/**
	 * Decode admin ids from JSON or newline text.
	 *
	 * @param mixed $raw Stored value.
	 * @return array<int, int>
	 */
	public static function decode_admin_ids( $raw ) {
		if ( null === $raw || '' === trim( (string) $raw ) ) {
			return array();
		}
		$s = (string) $raw;
		$j = json_decode( $s, true );
		if ( is_array( $j ) ) {
			return array_values( array_unique( array_map( 'intval', $j ) ) );
		}
		if ( class_exists( 'SimpleVPBot_Admin_Actions' ) ) {
			return SimpleVPBot_Admin_Actions::parse_id_lines( $s );
		}
		return array();
	}

	/**
	 * Total profiles joined to reseller users.
	 *
	 * @return int
	 */
	public static function count_all() {
		global $wpdb;
		$p = self::table();
		$u = SimpleVPBot_Model_User::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$p} p INNER JOIN {$u} u ON u.id = p.reseller_svp_user_id AND u.role = 'reseller'"
		);
	}

	/**
	 * Paginated list with reseller user row fields.
	 *
	 * @param int $per_page Per page.
	 * @param int $offset Offset.
	 * @return array<int, object>
	 */
	public static function list_paginated( $per_page, $offset ) {
		global $wpdb;
		$p   = self::table();
		$u   = SimpleVPBot_Model_User::table();
		$lim = max( 1, min( 100, (int) $per_page ) );
		$off = max( 0, (int) $offset );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.*, u.first_name AS reseller_first_name, u.last_name AS reseller_last_name, u.username AS reseller_username,
					u.status AS reseller_status, u.tg_user_id AS reseller_tg_user_id, u.bale_user_id AS reseller_bale_user_id
				FROM {$p} p
				INNER JOIN {$u} u ON u.id = p.reseller_svp_user_id AND u.role = 'reseller'
				ORDER BY p.reseller_svp_user_id DESC
				LIMIT %d OFFSET %d",
				$lim,
				$off
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete profile row for reseller.
	 *
	 * @param int $reseller_svp_user_id Id.
	 * @return bool
	 */
	public static function delete_by_reseller( $reseller_svp_user_id ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		if ( $r < 1 ) {
			return false;
		}
		$wpdb->delete( self::table(), array( 'reseller_svp_user_id' => $r ), array( '%d' ) );
		return true;
	}

	/**
	 * @param int  $reseller_svp_user_id Id.
	 * @param bool $enabled Enabled.
	 */
	public static function set_enabled( $reseller_svp_user_id, $enabled ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		if ( $r < 1 ) {
			return;
		}
		$row = self::find_by_reseller( $r );
		if ( ! $row ) {
			self::ensure_webhook_secret( $r );
			$row = self::find_by_reseller( $r );
		}
		if ( ! $row ) {
			return;
		}
		$wpdb->update(
			self::table(),
			array(
				'enabled'    => ! empty( $enabled ) ? 1 : 0,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'reseller_svp_user_id' => $r ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * @param int   $reseller_svp_user_id Id.
	 * @param array $tg_ids Telegram admin chat ids.
	 * @param array $bale_ids Bale admin ids.
	 */
	public static function save_admin_ids( $reseller_svp_user_id, array $tg_ids, array $bale_ids ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		if ( $r < 1 ) {
			return;
		}
		$row = self::find_by_reseller( $r );
		if ( ! $row ) {
			self::ensure_webhook_secret( $r );
			$row = self::find_by_reseller( $r );
		}
		if ( ! $row ) {
			return;
		}
		$wpdb->update(
			self::table(),
			array(
				'admin_telegram_ids' => self::encode_admin_ids( $tg_ids ),
				'admin_bale_ids'     => self::encode_admin_ids( $bale_ids ),
				'updated_at'         => current_time( 'mysql' ),
			),
			array( 'reseller_svp_user_id' => $r ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * @param int    $reseller_svp_user_id Id.
	 * @param string $token Token (empty clears).
	 */
	public static function save_bale_wallet_provider_token( $reseller_svp_user_id, $token ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		if ( $r < 1 ) {
			return;
		}
		$tok = sanitize_text_field( (string) $token );
		$row = self::find_by_reseller( $r );
		if ( ! $row ) {
			self::ensure_webhook_secret( $r );
			$row = self::find_by_reseller( $r );
		}
		if ( ! $row ) {
			return;
		}
		$wpdb->update(
			self::table(),
			array(
				'bale_wallet_provider_token' => mb_substr( $tok, 0, 255, 'UTF-8' ),
				'updated_at'                   => current_time( 'mysql' ),
			),
			array( 'reseller_svp_user_id' => $r ),
			array( '%s', '%s' ),
			array( '%d' )
		);
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
				'reseller_svp_user_id'   => $r,
				'telegram_token'         => '',
				'bale_token'             => '',
				'webhook_secret'         => $sec,
				'brand_name'             => '',
				'telegram_secret_token'  => '',
				'enabled'                => 1,
				'admin_telegram_ids'     => self::encode_admin_ids( array() ),
				'admin_bale_ids'         => self::encode_admin_ids( array() ),
				'bale_wallet_provider_token' => '',
				'updated_at'             => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
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
	 * @param int         $reseller_svp_user_id Id.
	 * @param string      $tg_token             Telegram bot token.
	 * @param string      $bale_token           Bale token.
	 * @param string|null $brand_name           Display brand for config fragment (optional).
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
			'reseller_svp_user_id'       => $r,
			'telegram_token'             => $tg,
			'bale_token'                 => $bl,
			'webhook_secret'             => $wh_sec,
			'brand_name'                 => null !== $brand_upd ? $brand_upd : '',
			'telegram_secret_token'      => '',
			'enabled'                    => 1,
			'admin_telegram_ids'         => self::encode_admin_ids( array() ),
			'admin_bale_ids'             => self::encode_admin_ids( array() ),
			'bale_wallet_provider_token' => '',
			'updated_at'                 => $now,
		);
		$wpdb->insert(
			$t,
			$insert,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
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

	/**
	 * Count all reseller users (for admin bots list pagination total).
	 *
	 * @return int
	 */
	public static function count_resellers_for_bot_admin() {
		global $wpdb;
		$u = SimpleVPBot_Model_User::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$u} WHERE role = %s", 'reseller' ) );
	}

	/**
	 * Paginated resellers with optional bot profile (LEFT JOIN).
	 *
	 * @param int $per_page Per page (capped 200).
	 * @param int $offset Offset.
	 * @return array<int, object>
	 */
	public static function list_resellers_bot_admin_paginated( $per_page, $offset ) {
		global $wpdb;
		$p   = self::table();
		$u   = SimpleVPBot_Model_User::table();
		$lim = max( 1, min( 200, (int) $per_page ) );
		$off = max( 0, (int) $offset );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT u.id AS reseller_svp_user_id, u.first_name AS reseller_first_name, u.last_name AS reseller_last_name,
					u.username AS reseller_username, u.status AS reseller_status,
					p.brand_name, p.enabled, p.telegram_token, p.bale_token, p.telegram_secret_token,
					p.admin_telegram_ids, p.admin_bale_ids
				FROM {$u} u
				LEFT JOIN {$p} p ON p.reseller_svp_user_id = u.id
				WHERE u.role = %s
				ORDER BY u.id DESC
				LIMIT %d OFFSET %d",
				'reseller',
				$lim,
				$off
			)
		);
		return is_array( $rows ) ? $rows : array();
	}
}
