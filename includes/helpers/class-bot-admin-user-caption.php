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
		$lines = array(
			'👤 کاربر: ' . self::display_name( $user ),
			'یوزرنیم: ' . $u,
			'تلگرام: ' . $tg_s,
			'بله: ' . $bl_s,
			'ربات: #' . $rid,
		);
		return implode( "\n", $lines );
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
		$intent = isset( $meta['intent'] ) ? (string) $meta['intent'] : '';
		if ( 'renew_same' === $intent ) {
			return 'تمدید همان سرویس';
		}
		if ( 'add_volume' === $intent ) {
			return 'افزایش حجم سرویس';
		}
		if ( 'add_user_slots' === $intent ) {
			return 'افزایش کاربر هم‌زمان';
		}
		$pid = ! empty( $meta['plan_id'] ) ? (int) $meta['plan_id'] : 0;
		if ( $pid > 0 && class_exists( 'SimpleVPBot_Model_Plan' ) ) {
			$p = SimpleVPBot_Model_Plan::find( $pid );
			if ( $p && trim( (string) ( $p->name ?? '' ) ) !== '' ) {
				return trim( (string) $p->name );
			}
		}
		$sid = ! empty( $meta['service_id'] ) ? (int) $meta['service_id'] : 0;
		if ( $sid > 0 && class_exists( 'SimpleVPBot_Model_Service' ) ) {
			$s = SimpleVPBot_Model_Service::find( $sid );
			if ( $s ) {
				$r = trim( (string) ( $s->remark ?? '' ) );
				return '' !== $r ? $r : ( 'سرویس #' . SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $sid ) );
			}
		}
		return '';
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
		$rid_fa = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) (int) $receipt_id );
		$amt    = SimpleVPBot_Bot_Persian_Text::format_toman_fa( (float) ( $tx->amount ?? 0 ) );
		$svc    = self::selected_service_line( $tx );
		$parts  = array(
			'🧾 رسید جدید',
			self::LINE_SEP,
			self::identity_block_lines( $user ),
			'💰 مبلغ: ' . $amt . ' تومان',
			'سرویس انتخابی: ' . $svc,
			'🆔 رسید: ' . $rid_fa,
		);
		return implode( "\n", $parts );
	}

	/**
	 * Admin caption for pending membership (no amount / service / receipt lines).
	 *
	 * @param object $user svp_users row.
	 * @param bool   $reopen True = بازبینی after reject reopen.
	 * @return string
	 */
	public static function membership_request_caption( $user, $reopen = false ) {
		$title = $reopen ? '🔔 درخواست ثبت‌نام (بازبینی)' : '🔔 درخواست ثبت‌نام جدید';
		$parts = array(
			$title,
			self::LINE_SEP,
			self::identity_block_lines( $user ),
			self::LINE_SEP,
			'آیا تایید می‌کنید؟',
		);
		return implode( "\n", $parts );
	}
}
