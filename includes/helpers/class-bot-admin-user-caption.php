<?php
/**
 * Structured admin captions: receipt + membership (shared identity block).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Bot_Admin_User_Caption
 */
class SimpleVPBot_Bot_Admin_User_Caption {

	const LINE_SEP = "➖➖➖➖➖➖➖➖";

	/**
	 * Optional brand line for reseller bot context.
	 *
	 * @return string
	 */
	private static function brand_line() {
		if ( ! class_exists( 'SimpleVPBot_Bot_Context' ) ) {
			return '';
		}
		$brand = trim( (string) SimpleVPBot_Bot_Context::active_brand_name() );
		if ( '' === $brand || ! class_exists( 'SimpleVPBot_Texts' ) ) {
			return '';
		}
		return SimpleVPBot_Texts::format( SimpleVPBot_Texts::get( 'msg.admin.caption.brand' ), array( 'brand' => $brand ) );
	}

	/**
	 * Display name (first + last).
	 *
	 * @param object $user svp_users row.
	 * @return string
	 */
	private static function display_name( $user ) {
		$n = trim( (string) ( $user->first_name ?? '' ) . ' ' . (string) ( $user->last_name ?? '' ) );
		return '' !== $n ? $n : '➖';
	}

	/**
	 * Label for referrer user (name, @username, or #id).
	 *
	 * @param object $referrer svp_users row.
	 * @return string
	 */
	public static function referrer_display_label( $referrer ) {
		if ( ! is_object( $referrer ) ) {
			return '';
		}
		$rn = trim( (string) ( $referrer->first_name ?? '' ) . ' ' . (string) ( $referrer->last_name ?? '' ) );
		if ( '' !== $rn ) {
			return $rn;
		}
		$un = trim( (string) ( $referrer->username ?? '' ) );
		if ( '' !== $un ) {
			return '@' . ltrim( $un, '@' );
		}
		return '#' . SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) (int) ( $referrer->id ?? 0 ) );
	}

	/**
	 * Referral / earn-link line for admin membership captions.
	 *
	 * @param object      $user   svp_users row.
	 * @param string|null $locale Optional locale override (fa|en).
	 * @return string Empty when no inviter.
	 */
	public static function invited_by_line( $user, $locale = null ) {
		if ( ! is_object( $user ) ) {
			return '';
		}
		$bid = (int) ( $user->invited_by ?? 0 );
		if ( $bid < 1 || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return '';
		}
		$ref = SimpleVPBot_Model_User::find( $bid );
		if ( ! $ref ) {
			return '';
		}
		$label = self::referrer_display_label( $ref );
		if ( '' === $label ) {
			return '';
		}
		$loc = null !== $locale ? $locale : ( class_exists( 'SimpleVPBot_Texts' ) ? SimpleVPBot_Texts::locale_for_user( $user ) : 'fa' );
		$key = 'en' === $loc ? 'msg.admin.invited_by_en' : 'msg.admin.invited_by';
		return class_exists( 'SimpleVPBot_Texts' )
			? SimpleVPBot_Texts::format( SimpleVPBot_Texts::get( $key, '', $loc ), array( 'label' => $label ) )
			: '';
	}

	/**
	 * Shared lines: 👤 / یوزرنیم / تلگرام / بله / ربات (no header).
	 *
	 * @param object $user svp_users row.
	 * @return string
	 */
	public static function identity_block_lines( $user ) {
		if ( ! is_object( $user ) ) {
			return '';
		}
		$un = trim( (string) ( $user->username ?? '' ) );
		$u  = '' !== $un ? '@' . $un : '➖';
		$tg = (int) ( $user->tg_user_id ?? 0 );
		$bl = (int) ( $user->bale_user_id ?? 0 );
		$tg_s = $tg > 0 ? SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $tg ) : '➖';
		$bl_s = $bl > 0 ? SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $bl ) : '➖';
		$rid  = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) (int) ( $user->id ?? 0 ) );
		if ( ! class_exists( 'SimpleVPBot_Texts' ) ) {
			return '';
		}
		$lines = array(
			SimpleVPBot_Texts::format( SimpleVPBot_Texts::get( 'msg.admin.caption.user_line' ), array( 'name' => self::display_name( $user ) ) ),
			SimpleVPBot_Texts::format( SimpleVPBot_Texts::get( 'msg.admin.caption.username_line' ), array( 'username' => $u ) ),
			SimpleVPBot_Texts::format( SimpleVPBot_Texts::get( 'msg.admin.caption.telegram_line' ), array( 'id' => $tg_s ) ),
			SimpleVPBot_Texts::format( SimpleVPBot_Texts::get( 'msg.admin.caption.bale_line' ), array( 'id' => $bl_s ) ),
			SimpleVPBot_Texts::format( SimpleVPBot_Texts::get( 'msg.admin.caption.bot_line' ), array( 'id' => $rid ) ),
		);
		return implode( "\n", $lines );
	}

	/**
	 * Append per-GB volume suffix when meta carries volume_gb.
	 *
	 * @param string     $label Plan or service label.
	 * @param array      $meta  Transaction meta.
	 * @param object|null $plan  Plan row when known.
	 * @return string
	 */
	private static function append_volume_suffix( $label, array $meta, $plan = null ) {
		$vol = isset( $meta['volume_gb'] ) ? (int) $meta['volume_gb'] : 0;
		if ( $vol < 1 ) {
			return $label;
		}
		$is_per_gb = $plan && class_exists( 'SimpleVPBot_Model_Plan' ) && SimpleVPBot_Model_Plan::is_per_gb( $plan );
		if ( ! $is_per_gb && 'add_volume' !== (string) ( $meta['intent'] ?? '' ) ) {
			return $label;
		}
		$vol_fa = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $vol );
		if ( '' === $label ) {
			return $vol_fa . ' گیگ';
		}
		return $label . ' · ' . $vol_fa . ' گیگ';
	}

	/**
	 * Human-readable selected-service label for a transaction (receipt captions, dashboard).
	 *
	 * @param object|null $tx Transaction row.
	 * @return string
	 */
	public static function transaction_selected_service_label( $tx ) {
		return self::selected_service_line( $tx );
	}

	/**
	 * Human-readable line for purchase meta (plan / service / intent).
	 *
	 * @param object|null $tx Transaction row.
	 * @return string Empty string = show blank after «سرویس انتخابی:» (per sample).
	 */
	private static function selected_service_line( $tx ) {
		if ( ! $tx ) {
			return '';
		}
		$meta = json_decode( (string) $tx->meta_json, true );
		if ( ! is_array( $meta ) ) {
			return '';
		}
		if ( 'topup' === (string) ( $tx->type ?? '' )
			|| ! empty( $meta['wallet_topup'] )
			|| ! empty( $meta['dashboard_reseller_topup'] ) ) {
			return SimpleVPBot_Texts::get( 'msg.wallet.topup_bale_title', 'شارژ کیف پول' );
		}
		$intent = isset( $meta['intent'] ) ? (string) $meta['intent'] : '';
		if ( 'renew_same' === $intent ) {
			return 'تمدید همان سرویس';
		}
		if ( 'add_volume' === $intent ) {
			$extra = max( 0, (int) ( $meta['extra_gb'] ?? 0 ) );
			if ( $extra < 1 && ! empty( $meta['volume_gb'] ) ) {
				$extra = (int) $meta['volume_gb'];
			}
			$base = 'افزایش حجم سرویس';
			if ( $extra >= 1 ) {
				$gb_fa = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $extra );
				return $base . ' · +' . $gb_fa . ' گیگ';
			}
			return $base;
		}
		if ( 'add_user_slots' === $intent ) {
			return 'افزایش کاربر هم‌زمان';
		}
		$pid = ! empty( $meta['plan_id'] ) ? (int) $meta['plan_id'] : 0;
		if ( $pid > 0 && class_exists( 'SimpleVPBot_Model_Plan' ) ) {
			$p = SimpleVPBot_Model_Plan::find( $pid );
			if ( $p && trim( (string) ( $p->name ?? '' ) ) !== '' ) {
				return self::append_volume_suffix( trim( (string) $p->name ), $meta, $p );
			}
		}
		$sid = ! empty( $meta['service_id'] ) ? (int) $meta['service_id'] : 0;
		if ( $sid > 0 && class_exists( 'SimpleVPBot_Model_Service' ) ) {
			$s = SimpleVPBot_Model_Service::find( $sid );
			if ( $s ) {
				$r = trim( (string) ( $s->remark ?? '' ) );
				$label = '' !== $r ? $r : ( 'سرویس #' . SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $sid ) );
				return self::append_volume_suffix( $label, $meta );
			}
		}
		return '';
	}

	/**
	 * Card or crypto wallet used for this receipt.
	 *
	 * @param int $receipt_id Receipt id.
	 * @return string Empty when unknown.
	 */
	public static function card_deposit_line( $receipt_id ) {
		$rid = (int) $receipt_id;
		if ( $rid < 1 || ! class_exists( 'SimpleVPBot_Model_Receipt' ) || ! class_exists( 'SimpleVPBot_Model_Card' ) ) {
			return '';
		}
		$rec = SimpleVPBot_Model_Receipt::find( $rid );
		if ( ! $rec || empty( $rec->card_id ) ) {
			return '';
		}
		$card = SimpleVPBot_Model_Card::find( (int) $rec->card_id );
		if ( ! $card ) {
			return '';
		}
		if ( SimpleVPBot_Model_Card::is_crypto_manual( $card ) || SimpleVPBot_Model_Card::is_crypto_auto( $card ) ) {
			$addr = trim( (string) ( $card->card_number ?? '' ) );
			$net  = trim( (string) ( $card->holder_name ?? '' ) );
			$bits = array( '💳 واریز به:' );
			if ( '' !== $net ) {
				$bits[] = $net;
			}
			if ( '' !== $addr ) {
				$bits[] = $addr;
			}
			return count( $bits ) > 1 ? implode( ' · ', $bits ) : '';
		}
		$pan    = preg_replace( '/\D+/', '', (string) ( $card->card_number ?? '' ) );
		$bank   = trim( (string) ( $card->bank_name ?? '' ) );
		$holder = trim( (string) ( $card->holder_name ?? '' ) );
		$bits   = array( '💳 کارت واریز:' );
		if ( '' !== $bank ) {
			$bits[] = $bank;
		}
		if ( '' !== $pan ) {
			$bits[] = SimpleVPBot_Bot_Persian_Text::digits_to_fa( $pan );
		}
		if ( '' !== $holder ) {
			$bits[] = $holder;
		}
		return count( $bits ) > 1 ? implode( ' · ', $bits ) : '';
	}

	/**
	 * Admin caption when user uploads a payment receipt photo.
	 *
	 * @param object $user svp_users row.
	 * @param object $tx   svp_transactions row.
	 * @param int    $receipt_id Receipt id.
	 * @return string
	 */
	public static function receipt_new_caption( $user, $tx, $receipt_id ) {
		$rid_fa     = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) (int) $receipt_id );
		$amount_raw = (float) ( $tx->amount ?? 0 );
		$svc        = self::selected_service_line( $tx );
		$meta   = array();
		if ( ! empty( $tx->meta_json ) ) {
			$decoded = json_decode( (string) $tx->meta_json, true );
			if ( is_array( $decoded ) ) {
				$meta = $decoded;
			}
		}
		$parts  = array(
			SimpleVPBot_Texts::get( 'msg.admin.receipt_new' ),
			self::LINE_SEP,
			self::brand_line(),
			self::identity_block_lines( $user ),
		);
		$code = isset( $meta['discount_code'] ) ? trim( (string) $meta['discount_code'] ) : '';
		if ( '' !== $code ) {
			$parts[] = SimpleVPBot_Texts::format( SimpleVPBot_Texts::get( 'msg.admin.caption.discount_line' ), array( 'code' => $code ) );
			$sub = isset( $meta['subtotal_toman'] ) ? (float) $meta['subtotal_toman'] : 0.0;
			$disc = isset( $meta['discount_toman'] ) ? (float) $meta['discount_toman'] : 0.0;
			if ( $sub > 0 || $disc > 0 ) {
				$parts[] = SimpleVPBot_Texts::format(
					SimpleVPBot_Texts::get( 'msg.admin.caption.discount_amounts' ),
					array(
						'subtotal' => SimpleVPBot_Bot_Persian_Text::format_toman_fa( $sub ),
						'discount' => SimpleVPBot_Bot_Persian_Text::format_toman_fa( $disc ),
					)
				);
			}
		}
		if ( SimpleVPBot_Bot_Persian_Text::is_zero_toman( $amount_raw ) ) {
			$parts[] = SimpleVPBot_Texts::get( 'msg.admin.caption.amount_line_free' );
		} else {
			$amt     = SimpleVPBot_Bot_Persian_Text::format_toman_fa( $amount_raw );
			$parts[] = SimpleVPBot_Texts::format( SimpleVPBot_Texts::get( 'msg.admin.caption.amount_line' ), array( 'amount' => $amt ) );
		}
		$card_line = self::card_deposit_line( (int) $receipt_id );
		if ( '' !== $card_line ) {
			$parts[] = $card_line;
		}
		$parts[] = SimpleVPBot_Texts::format( SimpleVPBot_Texts::get( 'msg.admin.caption.service_line' ), array( 'service' => $svc ) );
		$parts[] = SimpleVPBot_Texts::format( SimpleVPBot_Texts::get( 'msg.admin.caption.receipt_id_line' ), array( 'id' => $rid_fa ) );
		return implode( "\n", array_values( array_filter( $parts, static function ( $x ) { return '' !== (string) $x; } ) ) );
	}

	/**
	 * Receipt admin caption sized for sendPhoto (platform scrub + 1024 limit).
	 *
	 * @param object $user       svp_users row.
	 * @param object $tx         svp_transactions row.
	 * @param int    $receipt_id Receipt id.
	 * @param string $platform   telegram|bale.
	 * @return string
	 */
	public static function receipt_new_caption_for_platform( $user, $tx, $receipt_id, $platform = 'telegram' ) {
		return self::fit_receipt_caption_for_photo( (string) $platform, self::receipt_new_caption( $user, $tx, $receipt_id ) );
	}

	/**
	 * Compact and cap receipt caption for a single photo message.
	 *
	 * @param string $platform telegram|bale.
	 * @param string $caption  Raw caption.
	 * @return string
	 */
	public static function fit_receipt_caption_for_photo( $platform, $caption ) {
		$caption = (string) $caption;
		$caption = str_replace( self::LINE_SEP, '➖➖➖➖', $caption );
		$caption = preg_replace( "/\n{3,}/", "\n\n", $caption ) ?? $caption;
		if ( class_exists( 'SimpleVPBot_Bot_Runtime' ) ) {
			$caption = SimpleVPBot_Bot_Runtime::prepare_photo_caption( (string) $platform, $caption );
		}
		return trim( $caption );
	}

	/**
	 * Lighter caption variant for API retry when full caption is rejected.
	 *
	 * @param string $platform telegram|bale.
	 * @param string $caption  Raw caption.
	 * @return string
	 */
	public static function sanitize_receipt_caption_retry( $platform, $caption ) {
		$caption = wp_strip_all_tags( (string) $caption );
		$caption = preg_replace( "/[ \t]+/", ' ', $caption ) ?? $caption;
		$caption = preg_replace( "/\n{2,}/", "\n", $caption ) ?? $caption;
		return self::fit_receipt_caption_for_photo( (string) $platform, trim( $caption ) );
	}

	/**
	 * Admin caption for pending membership (no amount / service / receipt lines).
	 *
	 * @param object $user svp_users row.
	 * @param bool   $reopen True = بازبینی after reject reopen.
	 * @return string
	 */
	public static function membership_request_caption( $user, $reopen = false ) {
		$title = $reopen ? SimpleVPBot_Texts::get( 'msg.admin.signup_review' ) : SimpleVPBot_Texts::get( 'msg.admin.signup_new' );
		$parts = array(
			$title,
			self::LINE_SEP,
			self::brand_line(),
			self::identity_block_lines( $user ),
			self::invited_by_line( $user ),
			self::LINE_SEP,
			SimpleVPBot_Texts::get( 'msg.admin.confirm_question' ),
		);
		return implode( "\n", array_values( array_filter( $parts, static function ( $x ) { return '' !== (string) $x; } ) ) );
	}
}
