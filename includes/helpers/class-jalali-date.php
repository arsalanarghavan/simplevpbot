<?php
/**
 * Gregorian → Jalali (Shamsi) for display. No external deps.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Jalali_Date
 */
class SimpleVPBot_Jalali_Date {

	/**
	 * Convert Gregorian date to Jalali (j_y, j_m, j_d).
	 *
	 * @param int $g_y Year.
	 * @param int $g_m Month 1–12.
	 * @param int $g_d Day 1–31.
	 * @return array{0: int, 1: int, 2: int} [ jy, jm, jd ]
	 */
	public static function gregorian_to_jalali( $g_y, $g_m, $g_d ) {
		$g_days_in_month = array( 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
		$j_days_in_month = array( 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29 );

		$gy = (int) $g_y - 1600;
		$gm = (int) $g_m - 1;
		$gd = (int) $g_d - 1;

		$g_day_no = 365 * $gy
			+ (int) ( ( $gy + 3 ) / 4 )
			- (int) ( ( $gy + 99 ) / 100 )
			+ (int) ( ( $gy + 399 ) / 400 );

		for ( $i = 0; $i < $gm; $i++ ) {
			$g_day_no += $g_days_in_month[ $i ];
		}
		if ( $gm > 1 && ( ( ( (int) $g_y % 4 === 0 ) && ( (int) $g_y % 100 !== 0 ) ) || ( (int) $g_y % 400 === 0 ) ) ) {
			$g_day_no++;
		}
		$g_day_no += $gd;

		$j_day_no = $g_day_no - 79;
		$j_np     = (int) ( $j_day_no / 12053 );
		$j_day_no = $j_day_no % 12053;
		$jy       = 979 + 33 * $j_np + 4 * (int) ( $j_day_no / 1461 );
		$j_day_no = $j_day_no % 1461;
		if ( $j_day_no >= 366 ) {
			$jy     += (int) ( ( $j_day_no - 1 ) / 365 );
			$j_day_no = ( $j_day_no - 1 ) % 365;
		}
		$j = 0;
		for ( ; $j < 11 && $j_day_no >= $j_days_in_month[ $j ]; $j++ ) {
			$j_day_no -= $j_days_in_month[ $j ];
		}
		$jm = $j + 1;
		$jd = $j_day_no + 1;
		return array( $jy, $jm, $jd );
	}

	/**
	 * Format Unix timestamp (site timezone) as Shamsi date + time: 1403/12/15 - 23:50
	 *
	 * @param int $timestamp Unix time in seconds.
	 * @return string
	 */
	public static function format_datetime( $timestamp ) {
		$ts = (int) $timestamp;
		if ( $ts <= 0 ) {
			return '—';
		}
		$gy = (int) wp_date( 'Y', $ts );
		$gm = (int) wp_date( 'n', $ts );
		$gd = (int) wp_date( 'j', $ts );
		$t  = wp_date( 'H:i', $ts );
		list( $jy, $jm, $jd ) = self::gregorian_to_jalali( $gy, $gm, $gd );
		return sprintf( '%d/%02d/%02d - %s', $jy, $jm, $jd, $t );
	}

	/**
	 * Like format_datetime but with seconds precision: 1403/12/15 23:50:45
	 *
	 * @param int $timestamp Unix time (s).
	 * @return string
	 */
	public static function format_datetime_precise( $timestamp ) {
		$ts = (int) $timestamp;
		if ( $ts <= 0 ) {
			return '—';
		}
		$gy = (int) wp_date( 'Y', $ts );
		$gm = (int) wp_date( 'n', $ts );
		$gd = (int) wp_date( 'j', $ts );
		$t  = wp_date( 'H:i:s', $ts );
		list( $jy, $jm, $jd ) = self::gregorian_to_jalali( $gy, $gm, $gd );
		return sprintf( '%d/%02d/%02d %s', $jy, $jm, $jd, $t );
	}

	/**
	 * Filename-safe Jalali datetime: 1403-12-15_23-50-45
	 *
	 * @param int $timestamp Unix time (s).
	 * @return string
	 */
	public static function format_datetime_filename( $timestamp ) {
		$ts = (int) $timestamp;
		if ( $ts <= 0 ) {
			return 'unknown';
		}
		$gy = (int) wp_date( 'Y', $ts );
		$gm = (int) wp_date( 'n', $ts );
		$gd = (int) wp_date( 'j', $ts );
		$t  = wp_date( 'H-i-s', $ts );
		list( $jy, $jm, $jd ) = self::gregorian_to_jalali( $gy, $gm, $gd );
		return sprintf( '%d-%02d-%02d_%s', $jy, $jm, $jd, $t );
	}
}
