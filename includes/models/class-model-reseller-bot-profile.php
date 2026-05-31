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
	 * Decrypt stored bot token (legacy plaintext still accepted).
	 *
	 * @param string $stored Raw DB value.
	 * @return string
	 */
	public static function decrypt_token_field( $stored ) {
		$s = trim( (string) $stored );
		if ( '' === $s ) {
			return '';
		}
		if ( class_exists( 'SimpleVPBot_Secret_Box' ) && 0 === strpos( $s, 'v1:' ) ) {
			return trim( (string) SimpleVPBot_Secret_Box::decrypt( $s ) );
		}
		return $s;
	}

	/**
	 * Encrypt token for DB storage.
	 *
	 * @param string $plain Plain token.
	 * @return string
	 */
	public static function encrypt_token_field( $plain ) {
		$s = trim( (string) $plain );
		if ( '' === $s ) {
			return '';
		}
		if ( class_exists( 'SimpleVPBot_Secret_Box' ) ) {
			$enc = SimpleVPBot_Secret_Box::encrypt( $s );
			return '' !== $enc ? $enc : $s;
		}
		return $s;
	}

	/**
	 * @param object|null $prof Profile row.
	 * @param string      $platform telegram|bale.
	 * @return string
	 */
	public static function token_for_platform( $prof, $platform ) {
		if ( ! $prof || ! is_object( $prof ) ) {
			return '';
		}
		$raw = 'bale' === $platform
			? ( $prof->bale_token ?? '' )
			: ( $prof->telegram_token ?? '' );
		return self::decrypt_token_field( $raw );
	}

	/**
	 * @param object|null $prof Profile row.
	 * @param string      $platform telegram|bale.
	 * @return string Username without @.
	 */
	public static function bot_username_for_platform( $prof, $platform ) {
		if ( ! $prof || ! is_object( $prof ) ) {
			return '';
		}
		$col = 'bale' === $platform ? 'bale_bot_username' : 'telegram_bot_username';
		return trim( (string) ( $prof->{$col} ?? '' ), "@ \t\n\r\0\x0B" );
	}

	/**
	 * Persist @username from getMe after webhook setup.
	 *
	 * @param int    $reseller_svp_user_id Id.
	 * @param string $platform             telegram|bale.
	 * @param string $username             Bot username.
	 */
	public static function save_bot_username( $reseller_svp_user_id, $platform, $username ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		if ( $r < 1 ) {
			return;
		}
		$uname = trim( (string) $username, "@ \t\n\r\0\x0B" );
		$col   = 'bale' === $platform ? 'bale_bot_username' : 'telegram_bot_username';
		$row   = self::find_by_reseller( $r );
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
				$col         => mb_substr( $uname, 0, 128, 'UTF-8' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'reseller_svp_user_id' => $r ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Text keys resellers may override via dashboard (whitelist).
	 *
	 * @return array<int, string>
	 */
	public static function allowed_text_override_keys() {
		return array(
			'msg.welcome',
			'btn.support.contact',
			'btn.support.faq',
		);
	}

	/**
	 * Current overrides for API / dashboard form (site default locale).
	 *
	 * @param int $reseller_svp_user_id Id.
	 * @return array<string, string> key => value
	 */
	public static function editable_text_overrides_for_api( $reseller_svp_user_id ) {
		$rid = (int) $reseller_svp_user_id;
		if ( $rid < 1 ) {
			return array();
		}
		$loc = class_exists( 'SimpleVPBot_Texts' ) ? SimpleVPBot_Texts::site_default_locale() : 'fa';
		$out = array();
		foreach ( self::allowed_text_override_keys() as $key ) {
			$out[ $key ] = self::get_text_override( $rid, $key, $loc );
		}
		return $out;
	}

	/**
	 * @param int    $reseller_svp_user_id Id.
	 * @return array<string, array<string, string>>
	 */
	public static function get_text_overrides( $reseller_svp_user_id ) {
		$row = self::find_by_reseller( (int) $reseller_svp_user_id );
		if ( ! $row || empty( $row->text_overrides_json ) ) {
			return array();
		}
		$j = json_decode( (string) $row->text_overrides_json, true );
		return is_array( $j ) ? $j : array();
	}

	/**
	 * @param int    $reseller_svp_user_id Id.
	 * @param string $key                  Text key.
	 * @param string $locale               fa|en.
	 * @return string
	 */
	public static function get_text_override( $reseller_svp_user_id, $key, $locale = 'fa' ) {
		$all = self::get_text_overrides( $reseller_svp_user_id );
		$loc = class_exists( 'SimpleVPBot_Model_Text' )
			? SimpleVPBot_Model_Text::normalize_locale( $locale )
			: ( ( 'en' === $locale ) ? 'en' : 'fa' );
		$k   = (string) $key;
		if ( isset( $all[ $loc ][ $k ] ) && '' !== trim( (string) $all[ $loc ][ $k ] ) ) {
			return (string) $all[ $loc ][ $k ];
		}
		if ( isset( $all[ $k ] ) && is_string( $all[ $k ] ) && '' !== trim( $all[ $k ] ) ) {
			return (string) $all[ $k ];
		}
		return '';
	}

	/**
	 * Merge text overrides (per locale map).
	 *
	 * @param int                  $reseller_svp_user_id Id.
	 * @param array<string, mixed> $overrides            locale => { key => value } or key => value.
	 */
	public static function save_text_overrides( $reseller_svp_user_id, array $overrides ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		if ( $r < 1 ) {
			return;
		}
		$allowed = array_flip( self::allowed_text_override_keys() );
		$cur     = self::get_text_overrides( $r );
		foreach ( $overrides as $lk => $lv ) {
			if ( is_array( $lv ) ) {
				$loc = class_exists( 'SimpleVPBot_Model_Text' )
					? SimpleVPBot_Model_Text::normalize_locale( (string) $lk )
					: ( ( 'en' === $lk ) ? 'en' : 'fa' );
				if ( ! isset( $cur[ $loc ] ) || ! is_array( $cur[ $loc ] ) ) {
					$cur[ $loc ] = array();
				}
				foreach ( $lv as $tk => $tv ) {
					$k = (string) $tk;
					if ( ! isset( $allowed[ $k ] ) ) {
						continue;
					}
					$cur[ $loc ][ $k ] = (string) $tv;
				}
			} else {
				$k = (string) $lk;
				if ( ! isset( $allowed[ $k ] ) ) {
					continue;
				}
				$cur[ $k ] = (string) $lv;
			}
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
				'text_overrides_json' => (string) wp_json_encode( $cur ),
				'updated_at'          => current_time( 'mysql' ),
			),
			array( 'reseller_svp_user_id' => $r ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		if ( class_exists( 'SimpleVPBot_Texts' ) ) {
			SimpleVPBot_Texts::clear_cache();
		}
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
		if ( '' === trim( $tok ) ) {
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
	 * Patch tokens: only non-empty strings replace stored values (dashboard masked fields).
	 *
	 * @param int         $reseller_svp_user_id Id.
	 * @param string|null $tg_token             Telegram token or empty to skip.
	 * @param string|null $bale_token           Bale token or empty to skip.
	 * @param string|null $brand_name           Display brand (optional).
	 * @return array<int, string> Platforms patched (telegram|bale).
	 */
	public static function patch_tokens( $reseller_svp_user_id, $tg_token, $bale_token, $brand_name = null ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		if ( $r < 1 ) {
			return array();
		}
		$tg_raw = null !== $tg_token ? sanitize_text_field( (string) $tg_token ) : '';
		$bl_raw = null !== $bale_token ? sanitize_text_field( (string) $bale_token ) : '';
		$patch_tg = '' !== trim( $tg_raw );
		$patch_bl = '' !== trim( $bl_raw );
		if ( ! $patch_tg && ! $patch_bl && null === $brand_name ) {
			return array();
		}
		$ex = self::find_by_reseller( $r );
		if ( ! $ex && ! $patch_tg && ! $patch_bl ) {
			if ( null !== $brand_name ) {
				self::upsert_tokens( $r, '', '', $brand_name );
			}
			return array();
		}
		if ( ! $ex ) {
			self::upsert_tokens( $r, $patch_tg ? $tg_raw : '', $patch_bl ? $bl_raw : '', $brand_name );
			$out = array();
			if ( $patch_tg ) {
				$out[] = 'telegram';
			}
			if ( $patch_bl ) {
				$out[] = 'bale';
			}
			return $out;
		}
		$data   = array( 'updated_at' => current_time( 'mysql' ) );
		$format = array( '%s' );
		$out    = array();
		if ( $patch_tg ) {
			$data['telegram_token'] = self::encrypt_token_field( $tg_raw );
			$format[]               = '%s';
			$out[]                  = 'telegram';
		}
		if ( $patch_bl ) {
			$data['bale_token'] = self::encrypt_token_field( $bl_raw );
			$format[]           = '%s';
			$out[]              = 'bale';
		}
		$need_secret = $patch_tg || $patch_bl || strlen( (string) ( $ex->telegram_token ?? '' ) ) > 0 || strlen( (string) ( $ex->bale_token ?? '' ) ) > 0;
		if ( $need_secret && ( ! isset( $ex->webhook_secret ) || '' === trim( (string) ( $ex->webhook_secret ?? '' ) ) ) ) {
			$data['webhook_secret'] = self::generate_webhook_secret_value();
			$format[]               = '%s';
		}
		if ( null !== $brand_name ) {
			$data['brand_name'] = mb_substr( sanitize_text_field( (string) $brand_name ), 0, 255, 'UTF-8' );
			$format[]           = '%s';
		}
		$wpdb->update(
			self::table(),
			$data,
			array( 'reseller_svp_user_id' => $r ),
			$format,
			array( '%d' )
		);
		return $out;
	}

	/**
	 * Sync @username from getMe after reseller token patch.
	 *
	 * @param int                  $reseller_svp_user_id Id.
	 * @param array<int, string>   $platforms            telegram and/or bale.
	 */
	public static function sync_reseller_bot_usernames( $reseller_svp_user_id, array $platforms ) {
		$r = (int) $reseller_svp_user_id;
		if ( $r < 1 || ! class_exists( 'SimpleVPBot_Service_Admin_Ops' ) ) {
			return;
		}
		$platforms = array_values( array_unique( array_map( 'sanitize_key', $platforms ) ) );
		foreach ( $platforms as $plat ) {
			if ( 'telegram' === $plat ) {
				$res = SimpleVPBot_Service_Admin_Ops::test_telegram_for_reseller( $r );
				if ( ! empty( $res['ok'] ) && ! empty( $res['data']['result']['username'] ) ) {
					self::save_bot_username( $r, 'telegram', (string) $res['data']['result']['username'] );
				}
			} elseif ( 'bale' === $plat ) {
				$res = SimpleVPBot_Service_Admin_Ops::test_bale_for_reseller( $r );
				if ( ! empty( $res['ok'] ) && ! empty( $res['data']['result']['username'] ) ) {
					self::save_bot_username( $r, 'bale', (string) $res['data']['result']['username'] );
				}
			}
		}
	}

	/**
	 * Upsert tokens (empty string clears). Optionally updates brand_name.
	 * Ensures webhook_secret exists when any token is saved.
	 *
	 * @param int         $reseller_svp_user_id Id.
	 * @param string      $tg_token             Telegram bot token.
	 * @param string      $bale_token           Bale token.
	 * @param string|null $brand_name           Display brand for config fragment (optional).
	 */
	public static function upsert_tokens( $reseller_svp_user_id, $tg_token, $bale_token, $brand_name = null ) {
		global $wpdb;
		$r   = (int) $reseller_svp_user_id;
		if ( $r < 1 ) {
			return;
		}
		$tg  = self::encrypt_token_field( sanitize_text_field( (string) $tg_token ) );
		$bl  = self::encrypt_token_field( sanitize_text_field( (string) $bale_token ) );
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
	 * Save branding fields (logo, theme, custom domain).
	 *
	 * @param int                  $reseller_svp_user_id Reseller id.
	 * @param array<string, mixed> $fields               logo_url?, favicon_url?, theme_primary?, theme_accent?, custom_domain?.
	 */
	public static function save_branding_fields( $reseller_svp_user_id, array $fields ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		if ( $r < 1 ) {
			return;
		}
		self::ensure_webhook_secret( $r );
		$ex = self::find_by_reseller( $r );
		if ( ! $ex ) {
			self::upsert_tokens( $r, '', '', null );
			$ex = self::find_by_reseller( $r );
		}
		if ( ! $ex ) {
			return;
		}
		$data = array( 'updated_at' => current_time( 'mysql' ) );
		$fmt  = array( '%s' );
		if ( array_key_exists( 'logo_url', $fields ) ) {
			$data['logo_url'] = esc_url_raw( (string) $fields['logo_url'] );
			$fmt[]            = '%s';
		}
		if ( array_key_exists( 'favicon_url', $fields ) ) {
			$data['favicon_url'] = esc_url_raw( (string) $fields['favicon_url'] );
			$fmt[]               = '%s';
		}
		if ( array_key_exists( 'theme_primary', $fields ) ) {
			$data['theme_primary'] = sanitize_text_field( (string) $fields['theme_primary'] );
			$fmt[]                 = '%s';
		}
		if ( array_key_exists( 'theme_accent', $fields ) ) {
			$data['theme_accent'] = sanitize_text_field( (string) $fields['theme_accent'] );
			$fmt[]                = '%s';
		}
		if ( array_key_exists( 'custom_domain', $fields ) ) {
			$host = strtolower( trim( (string) $fields['custom_domain'] ) );
			$host = preg_replace( '#^https?://#', '', $host );
			$host = preg_replace( '#/.*$#', '', $host );
			$data['custom_domain'] = sanitize_text_field( (string) $host );
			$fmt[]                 = '%s';
		}
		if ( count( $data ) < 2 ) {
			return;
		}
		$wpdb->update(
			self::table(),
			$data,
			array( 'reseller_svp_user_id' => $r ),
			$fmt,
			array( '%d' )
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
					p.telegram_bot_username, p.bale_bot_username, p.text_overrides_json,
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
