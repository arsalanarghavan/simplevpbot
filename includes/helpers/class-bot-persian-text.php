<?php
/**
 * Persian digits and compact labels for bot messages and inline buttons.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Bot_Persian_Text
 */
class SimpleVPBot_Bot_Persian_Text {

	/**
	 * ASCII digits to Persian.
	 *
	 * @param string $s Input.
	 * @return string
	 */
	public static function digits_to_fa( $s ) {
		static $map = null;
		if ( null === $map ) {
			$map = array(
				'0' => '۰',
				'1' => '۱',
				'2' => '۲',
				'3' => '۳',
				'4' => '۴',
				'5' => '۵',
				'6' => '۶',
				'7' => '۷',
				'8' => '۸',
				'9' => '۹',
			);
		}
		return strtr( (string) $s, $map );
	}

	/**
	 * Integer or float with grouping, Persian digits (no unit).
	 *
	 * @param float $n        Number.
	 * @param int   $decimals Decimal places.
	 * @return string
	 */
	public static function format_number_fa( $n, $decimals = 0 ) {
		$s = number_format( (float) $n, (int) $decimals, '.', ',' );
		return self::digits_to_fa( $s );
	}

	/**
	 * Toman amount (integer display), Persian digits + grouping.
	 *
	 * @param float $amount Toman.
	 * @return string
	 */
	public static function format_toman_fa( $amount ) {
		return self::format_number_fa( (float) $amount, 0 );
	}

	/**
	 * Whether a toman amount should display as free (matches dashboard epsilon).
	 *
	 * @param float|int|string $amount Toman.
	 * @return bool
	 */
	public static function is_zero_toman( $amount ) {
		return abs( (float) $amount ) < 0.009;
	}

	/**
	 * Human-readable bytes with Persian units and digits.
	 *
	 * @param float|int $bytes Raw bytes.
	 * @return string
	 */
	public static function format_bytes_fa( $bytes ) {
		$b = (float) $bytes;
		if ( $b <= 0 ) {
			return self::digits_to_fa( '0' ) . ' بایت';
		}
		$units = array( 'بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت', 'ترابایت', 'پتابایت' );
		$i     = 0;
		while ( $b >= 1024 && $i < count( $units ) - 1 ) {
			$b /= 1024;
			$i++;
		}
		$decimals = ( $i <= 1 ) ? 0 : 2;
		if ( $i >= count( $units ) - 1 ) {
			$i        = count( $units ) - 2;
			$b        = (float) $bytes / pow( 1024, $i );
			$decimals = 2;
		}
		$s = number_format( $b, $decimals, '.', ',' );
		return self::digits_to_fa( $s ) . ' ' . $units[ $i ];
	}

	/**
	 * Inline glass button: plan name + price only (Telegram 64-char limit includes glass prefix).
	 *
	 * @param object $plan Plan row.
	 * @return string
	 */
	public static function plan_picker_glass_button( $plan ) {
		if ( ! is_object( $plan ) ) {
			return SimpleVPBot_Keyboards::glass_button_text( '📦 پلن', 64 );
		}
		$name = trim( (string) ( $plan->name ?? '' ) );
		$pfx  = '📦 ';
		$sep  = ' · ';
		$bud  = 64 - mb_strlen( SimpleVPBot_Keyboards::GLASS_PREFIX, 'UTF-8' );
		if ( SimpleVPBot_Model_Plan::is_per_gb( $plan ) ) {
			$price_fa = self::format_toman_fa( (float) ( $plan->price_per_gb ?? 0 ) );
			$suffix   = $price_fa . ' تومان به ازای هر گیگابایت';
		} else {
			$price_fa = self::format_toman_fa( (float) ( $plan->price ?? 0 ) );
			$suffix   = $price_fa . ' تومان';
		}
		$tail_len = mb_strlen( $pfx . $sep . $suffix, 'UTF-8' );
		$max_name = $bud - $tail_len;
		if ( $max_name < 2 ) {
			$max_name = 2;
		}
		$nm = '' !== $name ? mb_substr( $name, 0, $max_name, 'UTF-8' ) : 'پلن';
		$raw = $pfx . $nm . $sep . $suffix;
		return SimpleVPBot_Keyboards::glass_button_text( $raw, 64 );
	}
}
