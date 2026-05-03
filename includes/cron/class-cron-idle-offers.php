<?php
/**
 * Optional re-engagement ping for users with no recent approved purchase.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Cron_Idle_Offers
 */
class SimpleVPBot_Cron_Idle_Offers {

	/**
	 * Transient prefix per user id.
	 */
	const SENT_PREFIX = 'simplevpbot_idle_ping_u';

	/**
	 * Run hourly job (light batch).
	 */
	public static function run() {
		if ( ! SimpleVPBot_Settings::get( 'enabled', true ) ) {
			return;
		}
		if ( ! SimpleVPBot_Settings::get( 'notify_idle_enabled', false ) ) {
			return;
		}
		if ( ! class_exists( 'SimpleVPBot_Model_User' ) || ! class_exists( 'SimpleVPBot_Model_Transaction' ) ) {
			return;
		}
		$after_days = max( 7, (int) SimpleVPBot_Settings::get( 'notify_idle_after_days', 45 ) );
		$cool_days  = max( 7, (int) SimpleVPBot_Settings::get( 'notify_idle_cooldown_days', 90 ) );
		$cutoff     = time() - $after_days * DAY_IN_SECONDS;

		global $wpdb;
		$t = SimpleVPBot_Model_User::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$users = $wpdb->get_results( $wpdb->prepare( "SELECT id, tg_user_id, bale_user_id FROM {$t} WHERE status = %s LIMIT 80", 'approved' ) );
		if ( ! is_array( $users ) ) {
			return;
		}
		$codes_line = '';
		if ( class_exists( 'SimpleVPBot_Model_Discount_Code' ) ) {
			$codes = SimpleVPBot_Model_Discount_Code::all_ordered();
			$labels = array();
			foreach ( array_slice( $codes, 0, 5 ) as $row ) {
				if ( is_object( $row ) && ! empty( $row->active ) && ! empty( $row->code ) ) {
					$labels[] = (string) $row->code;
				}
			}
			if ( ! empty( $labels ) ) {
				$codes_line = "\n🏷 " . __( 'کدهای تخفیف فعال:', 'simplevpbot' ) . ' ' . implode( ', ', $labels );
			}
		}
		foreach ( $users as $u ) {
			$uid = (int) ( $u->id ?? 0 );
			if ( $uid < 1 ) {
				continue;
			}
			if ( get_transient( self::SENT_PREFIX . $uid ) ) {
				continue;
			}
			$last = SimpleVPBot_Model_Transaction::last_approved_timestamp( $uid );
			if ( $last < 1 || $last > $cutoff ) {
				continue;
			}
			$text = "👋 " . __( 'سلام! مدتی است خرید یا تمدیدی از حسابت ثبت نشده.', 'simplevpbot' )
				. "\n" . __( 'اگر هنوز به VPN نیاز داری، از منوی ربات سرویس‌ها را ببین.', 'simplevpbot' )
				. $codes_line;
			self::notify_user_row( $u, $text );
			set_transient( self::SENT_PREFIX . $uid, 1, $cool_days * DAY_IN_SECONDS );
		}
	}

	/**
	 * @param object $user Partial row with tg_user_id / bale_user_id.
	 * @param string $text   Message.
	 */
	private static function notify_user_row( $user, $text ) {
		$tg_tok = (string) SimpleVPBot_Settings::get( 'telegram_token', '' );
		$bl_tok = (string) SimpleVPBot_Settings::get( 'bale_token', '' );
		if ( $tg_tok && ! empty( $user->tg_user_id ) ) {
			( new SimpleVPBot_Telegram_Client( $tg_tok ) )->send_message( array( 'chat_id' => (int) $user->tg_user_id, 'text' => $text ) );
		}
		if ( $bl_tok && ! empty( $user->bale_user_id ) ) {
			( new SimpleVPBot_Bale_Client( $bl_tok ) )->send_message(
				array(
					'chat_id' => (int) $user->bale_user_id,
					'text'    => class_exists( 'SimpleVPBot_Bot_Runtime' ) ? SimpleVPBot_Bot_Runtime::scrub_bale_text( $text ) : $text,
				)
			);
		}
	}
}
