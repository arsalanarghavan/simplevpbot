<?php
/**
 * Referral commission: credit referrer wallet when referred user pays.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Referral_Service
 */
class SimpleVPBot_Referral_Service {

	/**
	 * Parse inviter id from /start payload (e.g. ref_42).
	 *
	 * @param string $text Full message text.
	 * @return int 0 if none.
	 */
	public static function parse_inviter_from_start_text( $text ) {
		$t = trim( (string) $text );
		if ( '' === $t ) {
			return 0;
		}
		if ( preg_match( '#^/start(?:@[A-Za-z0-9_]+)?\s+(\S+)#u', $t, $m ) ) {
			$payload = trim( (string) $m[1] );
			if ( preg_match( '/^ref_(\d+)$/i', $payload, $p ) ) {
				return (int) $p[1];
			}
		}
		return 0;
	}

	/**
	 * Short payload for DB (ref_N).
	 *
	 * @param int $inviter_id Inviter id from ref_*.
	 * @return string
	 */
	public static function start_payload_for_inviter( $inviter_id ) {
		$i = (int) $inviter_id;
		if ( $i < 1 ) {
			return '';
		}
		return 'ref_' . $i;
	}

	/**
	 * Log a /start with ref_* (even for existing users).
	 *
	 * @param array<string, mixed> $args Keys: platform, visitor_chat_id, visitor_platform_user_id, inviter_svp_user_id, start_payload, outcome, resulting_svp_user_id (optional).
	 * @return void
	 */
	public static function log_start_event( array $args ) {
		if ( ! class_exists( 'SimpleVPBot_Model_Referral_Event' ) ) {
			return;
		}
		$inv = (int) ( $args['inviter_svp_user_id'] ?? 0 );
		if ( $inv < 1 ) {
			return;
		}
		$payload = (string) ( $args['start_payload'] ?? '' );
		if ( strlen( $payload ) > 128 ) {
			$payload = substr( $payload, 0, 128 );
		}
		$res_uid = isset( $args['resulting_svp_user_id'] ) ? (int) $args['resulting_svp_user_id'] : 0;
		SimpleVPBot_Model_Referral_Event::insert(
			array(
				'inviter_svp_user_id'       => $inv,
				'platform'                  => (string) ( $args['platform'] ?? '' ),
				'visitor_chat_id'           => (int) ( $args['visitor_chat_id'] ?? 0 ),
				'visitor_platform_user_id'  => (int) ( $args['visitor_platform_user_id'] ?? 0 ),
				'start_payload'             => $payload,
				'outcome'                   => sanitize_key( (string) ( $args['outcome'] ?? 'logged' ) ),
				'resulting_svp_user_id'     => $res_uid > 0 ? $res_uid : null,
			)
		);
	}

	/**
	 * Whether inviter id is allowed for a new referral edge.
	 *
	 * @param int $inviter_id Candidate svp_users.id.
	 * @param int $new_user_id  New user id if already created (0 when not yet inserted).
	 * @return int Sanitized inviter id or 0.
	 */
	public static function validate_inviter_id( $inviter_id, $new_user_id = 0 ) {
		$rid = self::validate_bind_inviter_id( $inviter_id, $new_user_id );
		if ( $rid < 1 ) {
			return 0;
		}
		if ( ! SimpleVPBot_Settings::get( 'referral_enabled', false ) ) {
			return 0;
		}
		return $rid;
	}

	/**
	 * Whether inviter id is allowed for invited_by binding (ignores referral_enabled).
	 *
	 * @param int $inviter_id Candidate svp_users.id.
	 * @param int $new_user_id New user id if already created (0 when not yet inserted).
	 * @return int Sanitized inviter id or 0.
	 */
	public static function validate_bind_inviter_id( $inviter_id, $new_user_id = 0 ) {
		$rid = (int) $inviter_id;
		if ( $rid < 1 || $rid === (int) $new_user_id ) {
			return 0;
		}
		$u = SimpleVPBot_Model_User::find( $rid );
		if ( ! $u ) {
			return 0;
		}
		if ( SimpleVPBot_Settings::get( 'referral_require_approved_referrer', true ) && 'approved' !== (string) $u->status ) {
			return 0;
		}
		return $rid;
	}

	/**
	 * Build shareable start link for a platform.
	 *
	 * @param string $platform telegram|bale.
	 * @param int    $user_id    svp_users.id.
	 * @return string URL or empty if username not configured.
	 */
	public static function invite_link_for_platform( $platform, $user_id, $reseller_svp_user_id = 0 ) {
		$uid = (int) $user_id;
		if ( $uid < 1 ) {
			return '';
		}
		$payload = 'ref_' . $uid;
		$rid     = (int) $reseller_svp_user_id;
		if ( $rid < 1 && class_exists( 'SimpleVPBot_Bot_Context' ) && SimpleVPBot_Bot_Context::is_reseller_bot() ) {
			$rid = (int) SimpleVPBot_Bot_Context::reseller_svp_user_id();
		}
		if ( $rid < 1 && class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
			$rid = (int) SimpleVPBot_Reseller_Branding::nearest_reseller_id_for_user( $uid );
		}
		if ( $rid > 0 && class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			$prof = SimpleVPBot_Model_Reseller_Bot_Profile::find_by_reseller( $rid );
			$u    = SimpleVPBot_Model_Reseller_Bot_Profile::bot_username_for_platform( $prof, $platform );
			if ( '' !== $u ) {
				if ( 'bale' === $platform ) {
					return 'https://ble.ir/' . rawurlencode( $u ) . '?start=' . rawurlencode( $payload );
				}
				return 'https://t.me/' . rawurlencode( $u ) . '?start=' . rawurlencode( $payload );
			}
		}
		if ( 'bale' === $platform ) {
			$u = trim( (string) SimpleVPBot_Settings::get( 'bale_bot_username', '' ), "@ \t\n\r\0\x0B" );
			if ( '' === $u ) {
				return '';
			}
			return 'https://ble.ir/' . rawurlencode( $u ) . '?start=' . rawurlencode( $payload );
		}
		$u = trim( (string) SimpleVPBot_Settings::get( 'telegram_bot_username', '' ), "@ \t\n\r\0\x0B" );
		if ( '' === $u ) {
			return '';
		}
		return 'https://t.me/' . rawurlencode( $u ) . '?start=' . rawurlencode( $payload );
	}

	/**
	 * Human label for buyer (notify referrer).
	 *
	 * @param object $buyer User row.
	 * @return string
	 */
	private static function buyer_display_label( $buyer ) {
		$un = trim( (string) ( $buyer->username ?? '' ), "@ \t\n\r\0\x0B" );
		if ( '' !== $un ) {
			return '@' . $un;
		}
		$fn = trim( (string) ( $buyer->first_name ?? '' ) . ' ' . (string) ( $buyer->last_name ?? '' ) );
		if ( '' !== $fn ) {
			return $fn;
		}
		return '#' . (int) $buyer->id;
	}

	/**
	 * Notify referrer on wallet credit (Telegram and/or Bale).
	 *
	 * @param object $referrer Referrer user row.
	 * @param object $buyer    Buyer user row.
	 * @param float  $commission Toman.
	 * @return void
	 */
	private static function notify_referrer_wallet_bonus( $referrer, $buyer, $commission ) {
		$rf = trim( (string) ( $referrer->first_name ?? '' ) );
		if ( '' === $rf ) {
			$rf = trim( (string) ( $referrer->username ?? '' ), "@ \t\n\r\0\x0B" );
		}
		if ( '' === $rf ) {
			$rf = __( 'کاربر گرامی', 'simplevpbot' );
		}
		$amt_str = (string) (int) round( $commission );
		$tpl     = SimpleVPBot_Texts::get(
			'msg.referral_bonus_wallet',
			"💰 {amount_toman} تومان از خرید {buyer_label}\n{referrer_first}"
		);
		$body = SimpleVPBot_Texts::format(
			$tpl,
			array(
				'referrer_first' => $rf,
				'amount_toman'   => $amt_str,
				'buyer_label'    => self::buyer_display_label( $buyer ),
			)
		);
		if ( ! empty( $referrer->tg_user_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( 'telegram', (int) $referrer->tg_user_id, $body );
		}
		if ( ! empty( $referrer->bale_user_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( 'bale', (int) $referrer->bale_user_id, $body );
		}
	}

	/**
	 * Credit referrer once per paid transaction (idempotent).
	 *
	 * @param object $tx Transaction row (type purchase or renew, status approved).
	 */
	public static function maybe_credit_from_transaction( $tx ) {
		if ( ! $tx || 'approved' !== (string) $tx->status ) {
			return;
		}
		$type = (string) $tx->type;
		if ( 'purchase' !== $type && 'renew' !== $type ) {
			return;
		}
		$amount = (float) $tx->amount;
		if ( $amount <= 0 ) {
			return;
		}
		if ( ! SimpleVPBot_Settings::get( 'referral_enabled', false ) ) {
			return;
		}
		$meta = json_decode( (string) $tx->meta_json, true );
		$meta = is_array( $meta ) ? $meta : array();
		if ( ! empty( $meta['admin_gift'] ) ) {
			return;
		}
		if ( ! empty( $meta['referral_commission_paid'] ) ) {
			return;
		}
		$min_base = (float) SimpleVPBot_Settings::get( 'referral_min_payout_base', 0 );
		if ( $min_base > 0 && $amount < $min_base ) {
			return;
		}
		$pct = (float) SimpleVPBot_Settings::get( 'referral_percent', 0 );
		if ( $pct <= 0 ) {
			return;
		}
		$buyer = SimpleVPBot_Model_User::find( (int) $tx->user_id );
		if ( ! $buyer || empty( $buyer->invited_by ) ) {
			return;
		}
		$ref_id = (int) $buyer->invited_by;
		if ( $ref_id === (int) $buyer->id ) {
			return;
		}
		$referrer = SimpleVPBot_Model_User::find( $ref_id );
		if ( ! $referrer ) {
			return;
		}
		if ( SimpleVPBot_Settings::get( 'referral_require_approved_referrer', true ) && 'approved' !== (string) $referrer->status ) {
			return;
		}
		$commission = round( $amount * $pct / 100.0, 2 );
		if ( $commission <= 0 ) {
			return;
		}
		$nb = round( (float) $referrer->balance + $commission, 2 );
		SimpleVPBot_Model_User::update( $ref_id, array( 'balance' => $nb ) );
		SimpleVPBot_Model_Transaction::insert(
			array(
				'user_id'    => $ref_id,
				'service_id' => null,
				'amount'     => $commission,
				'type'       => 'referral_commission',
				'status'     => 'approved',
				'meta_json'  => wp_json_encode(
					array(
						'from_user_id' => (int) $buyer->id,
						'from_tx_id'   => (int) $tx->id,
						'percent'      => $pct,
						'base_toman'   => $amount,
					)
				),
			)
		);
		$meta['referral_commission_paid'] = true;
		$meta['referral_commission_to']   = $ref_id;
		$meta['referral_commission_amt']  = $commission;
		SimpleVPBot_Model_Transaction::update(
			(int) $tx->id,
			array(
				'meta_json'        => wp_json_encode( $meta ),
				'referral_amount'  => $commission,
			)
		);
		self::notify_referrer_wallet_bonus( $referrer, $buyer, $commission );
	}
}
