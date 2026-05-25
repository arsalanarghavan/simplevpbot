<?php
/**
 * Referral / earn screen for users.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Referral
 */
class SimpleVPBot_Handler_Referral {

	/**
	 * Format percent for labels (no trailing .00).
	 *
	 * @param float $pct Percent value.
	 * @return string ASCII digits (then pass through SimpleVPBot_Bot_Persian_Text::digits_to_fa if needed).
	 */
	private static function format_percent_ascii( $pct ) {
		$p = (float) $pct;
		if ( $p <= 0 ) {
			return '0';
		}
		if ( abs( $p - round( $p ) ) < 0.005 ) {
			return (string) (int) round( $p );
		}
		return rtrim( rtrim( number_format( $p, 2, '.', '' ), '0' ), '.' );
	}

	/**
	 * Show referral stats and links (reply keyboard entry).
	 *
	 * @param string $platform telegram|bale.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     svp_users row.
	 */
	public static function show( $platform, $chat_id, $user ) {
		$enabled = (bool) SimpleVPBot_Settings::get( 'referral_enabled', false );
		$pct     = (float) SimpleVPBot_Settings::get( 'referral_percent', 0 );
		$base    = max( 0.0, (float) SimpleVPBot_Settings::get( 'referral_example_base_toman', 170000 ) );
		$ex_n    = max( 1, (int) SimpleVPBot_Settings::get( 'referral_example_invite_count', 10 ) );

		$pct_ascii = self::format_percent_ascii( $pct );
		$pct_fa    = SimpleVPBot_Bot_Persian_Text::digits_to_fa( $pct_ascii );
		$comm      = (int) round( $base * $pct / 100.0 );
		$total_ex  = $comm * $ex_n;

		$reseller_ctx = class_exists( 'SimpleVPBot_Bot_Reseller_Scope' )
			? SimpleVPBot_Bot_Reseller_Scope::active_reseller_id()
			: 0;
		$tg_link = SimpleVPBot_Referral_Service::invite_link_for_platform( 'telegram', (int) $user->id, $reseller_ctx );
		$bl_link = SimpleVPBot_Referral_Service::invite_link_for_platform( 'bale', (int) $user->id, $reseller_ctx );

		$uid = (int) $user->id;
		if ( $tg_link !== '' ) {
			$tg_block = SimpleVPBot_Texts::format( SimpleVPBot_Texts::get_for_user( 'msg.referral.tg_link', $user ), array( 'link' => $tg_link ) );
		} else {
			$tg_block = SimpleVPBot_Texts::format( SimpleVPBot_Texts::get_for_user( 'msg.referral.tg_fallback', $user ), array( 'id' => $uid ) );
		}
		if ( $bl_link !== '' ) {
			$bl_block = SimpleVPBot_Texts::format( SimpleVPBot_Texts::get_for_user( 'msg.referral.bl_link', $user ), array( 'link' => $bl_link ) );
		} else {
			$bl_block = SimpleVPBot_Texts::format( SimpleVPBot_Texts::get_for_user( 'msg.referral.bl_fallback', $user ), array( 'id' => $uid ) );
		}

		$disabled_note = $enabled ? '' : SimpleVPBot_Texts::get_for_user( 'msg.referral.disabled_note', $user );

		$t = SimpleVPBot_Texts::format(
			SimpleVPBot_Texts::get_for_user( 'msg.referral.screen', $user ),
			array(
				'disabled_note' => $disabled_note,
				'pct'           => $pct_fa,
				'base'          => SimpleVPBot_Bot_Persian_Text::format_toman_fa( $base ),
				'comm'          => SimpleVPBot_Bot_Persian_Text::format_toman_fa( (float) $comm ),
				'ex_n'          => SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $ex_n ),
				'total'         => SimpleVPBot_Bot_Persian_Text::format_toman_fa( (float) $total_ex ),
				'user_id'       => $uid,
				'tg_block'      => $tg_block,
				'bl_block'      => $bl_block,
			)
		);

		SimpleVPBot_Bot_Runtime::send_message( $platform, (int) $chat_id, $t );
	}
}
