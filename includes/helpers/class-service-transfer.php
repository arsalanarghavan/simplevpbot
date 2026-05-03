<?php
/**
 * Transfer service ownership (admin, bot callback, owner code flow).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Service_Transfer
 */
class SimpleVPBot_Service_Transfer {

	/**
	 * Transfer codes table.
	 *
	 * @return string
	 */
	public static function codes_table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_service_transfer_codes';
	}

	/**
	 * Ensure the transfer codes table exists (idempotent).
	 */
	public static function ensure_table() {
		global $wpdb;
		$t = self::codes_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = (string) $wpdb->get_var( "SHOW TABLES LIKE '{$t}'" );
		if ( $exists === $t ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$t} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			service_id bigint(20) unsigned NOT NULL,
			owner_id bigint(20) unsigned NOT NULL,
			code varchar(16) NOT NULL,
			expires_at datetime NOT NULL,
			consumed tinyint(1) NOT NULL DEFAULT 0,
			consumed_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY code (code),
			KEY service_id (service_id),
			KEY owner_id (owner_id)
		) $charset_collate;";
		dbDelta( $sql );
	}

	/**
	 * Resolve a target user by a free-text input.
	 *
	 * Accepts: `svp:<id>`, `<svp_users.id>`, `@username`, or bare chat id.
	 *
	 * @param string $input Raw input.
	 * @return object|null
	 */
	public static function resolve_user( $input ) {
		$raw = trim( SimpleVPBot_Bot_Runtime::normalize_digits( (string) $input ) );
		if ( '' === $raw ) {
			return null;
		}
		if ( preg_match( '/^svp:(\d+)$/i', $raw, $m ) ) {
			return SimpleVPBot_Model_User::find( (int) $m[1] );
		}
		if ( preg_match( '/^@?([A-Za-z0-9_]{3,64})$/', $raw, $m ) && ! ctype_digit( $m[1] ) ) {
			$u = SimpleVPBot_Model_User::find_unique_approved_by_username( $m[1] );
			return $u;
		}
		if ( ctype_digit( $raw ) ) {
			$num = (int) $raw;
			if ( $num <= 0 ) {
				return null;
			}
			$by_id = SimpleVPBot_Model_User::find( $num );
			if ( $by_id ) {
				return $by_id;
			}
			$by_tg = SimpleVPBot_Model_User::find_by_telegram( $num );
			if ( $by_tg ) {
				return $by_tg;
			}
			$by_bale = SimpleVPBot_Model_User::find_by_bale( $num );
			if ( $by_bale ) {
				return $by_bale;
			}
		}
		return null;
	}

	/**
	 * Run the transfer and notify both sides.
	 *
	 * @param int    $service_id  Service id.
	 * @param int    $target_uid  Target svp_users.id.
	 * @param string $admin_label Label used in notifications.
	 * @return array{ok:bool, reason:string, previous_user_id?:int}
	 */
	public static function transfer( $service_id, $target_uid, $admin_label = '' ) {
		$res = SimpleVPBot_Model_Service::transfer_to( (int) $service_id, (int) $target_uid );
		if ( empty( $res['ok'] ) || 'noop' === (string) ( $res['reason'] ?? '' ) ) {
			return $res;
		}
		$svc    = SimpleVPBot_Model_Service::find( (int) $service_id );
		$prev   = isset( $res['previous_user_id'] ) ? (int) $res['previous_user_id'] : 0;
		$target = SimpleVPBot_Model_User::find( (int) $target_uid );
		$prev_u = $prev ? SimpleVPBot_Model_User::find( $prev ) : null;

		$label = (string) $admin_label;
		$svc_label = $svc ? ( '#' . (int) $svc->id . ' · ' . (string) $svc->remark ) : ( '#' . (int) $service_id );
		if ( $prev_u ) {
			self::notify_user_both_bots(
				$prev_u,
				"🔁 سرویس «{$svc_label}» به کاربر دیگری منتقل شد." . ( $label ? "\nتوسط: {$label}" : '' )
			);
		}
		if ( $target ) {
			$extra = $svc ? array(
				'reply_markup' => array(
					'inline_keyboard' => array(
						array(
							array(
								'text'          => SimpleVPBot_Texts::get( 'btn.service.show_panel', '🖥 جزئیات سرویس' ),
								'callback_data' => 'svc:p:' . (int) $svc->id,
							),
						),
					),
				),
			) : array();
			self::notify_user_both_bots(
				$target,
				"🎁 یک سرویس برای شما منتقل شد: «{$svc_label}»" . ( $label ? "\nتوسط: {$label}" : '' ),
				$extra
			);
		}
		return $res;
	}

	/**
	 * Generate a short-lived transfer code for an owner to hand over their service.
	 *
	 * @param int $service_id Service id.
	 * @param int $owner_id   Current owner svp_users.id.
	 * @return array{ok:bool, reason?:string, code?:string, expires_at?:string}
	 */
	public static function create_code( $service_id, $owner_id ) {
		self::ensure_table();
		global $wpdb;
		$sid = (int) $service_id;
		$oid = (int) $owner_id;
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc || (int) $svc->user_id !== $oid ) {
			return array( 'ok' => false, 'reason' => 'not_owner' );
		}
		$t = self::codes_table();
		$tries = 0;
		do {
			$code = str_pad( (string) wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );
			$busy = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$t} WHERE code = %s AND consumed = 0 AND expires_at > UTC_TIMESTAMP()",
					$code
				)
			); // phpcs:ignore
			$tries++;
		} while ( $busy > 0 && $tries < 10 );
		$expires = gmdate( 'Y-m-d H:i:s', time() + 600 );
		$wpdb->insert(
			$t,
			array(
				'service_id' => $sid,
				'owner_id'   => $oid,
				'code'       => $code,
				'expires_at' => $expires,
				'consumed'   => 0,
			)
		);
		return array( 'ok' => true, 'code' => $code, 'expires_at' => $expires );
	}

	/**
	 * Find valid pending code row.
	 *
	 * @param string $code 6-digit.
	 * @return object|null
	 */
	public static function find_valid_code( $code ) {
		self::ensure_table();
		global $wpdb;
		$t = self::codes_table();
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$t} WHERE code = %s AND consumed = 0 AND expires_at > UTC_TIMESTAMP() LIMIT 1",
				(string) $code
			)
		); // phpcs:ignore
	}

	/**
	 * Consume code + perform transfer to caller.
	 *
	 * @param string $code      Code.
	 * @param int    $recipient Recipient svp_users.id.
	 * @param string $label     Label (for notifications).
	 * @return array{ok:bool, reason:string, service_id?:int}
	 */
	public static function consume_code_and_transfer( $code, $recipient, $label = '' ) {
		$row = self::find_valid_code( (string) $code );
		if ( ! $row ) {
			return array( 'ok' => false, 'reason' => 'invalid_or_expired' );
		}
		$rid  = (int) $recipient;
		$oid  = (int) $row->owner_id;
		$sid  = (int) $row->service_id;
		if ( $rid < 1 || $rid === $oid ) {
			return array( 'ok' => false, 'reason' => 'same_user' );
		}
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc || (int) $svc->user_id !== $oid ) {
			return array( 'ok' => false, 'reason' => 'owner_changed' );
		}
		$res = self::transfer( $sid, $rid, $label );
		if ( empty( $res['ok'] ) ) {
			return array( 'ok' => false, 'reason' => (string) ( $res['reason'] ?? 'err' ) );
		}
		global $wpdb;
		$wpdb->update(
			self::codes_table(),
			array( 'consumed' => 1, 'consumed_by' => $rid ),
			array( 'id' => (int) $row->id )
		);
		return array( 'ok' => true, 'reason' => 'ok', 'service_id' => $sid );
	}

	/**
	 * Notify on both bots.
	 *
	 * @param object               $user User.
	 * @param string               $text Text.
	 * @param array<string, mixed> $extra Extra.
	 */
	private static function notify_user_both_bots( $user, $text, array $extra = array() ) {
		if ( ! empty( $user->tg_user_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( 'telegram', (int) $user->tg_user_id, $text, $extra );
		}
		if ( ! empty( $user->bale_user_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( 'bale', (int) $user->bale_user_id, $text, $extra );
		}
	}
}
