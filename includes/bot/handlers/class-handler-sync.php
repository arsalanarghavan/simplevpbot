<?php
/**
 * Cross-bot account sync.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Sync
 */
class SimpleVPBot_Handler_Sync {

	/**
	 * Generate 6-digit code.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat id.
	 * @param object $user User.
	 */
	public static function generate_code( $platform, $chat_id, $user ) {
		$bot = 'bale' === $platform ? 'bale' : 'tg';
		$code = SimpleVPBot_Model_Sync_Code::create( (int) $user->id, $bot );
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			SimpleVPBot_Texts::format(
				"🔗 کد سینک شما:\n➖➖➖➖➖➖➖➖\n🔑 `{code}`\n➖➖➖➖➖➖➖➖\nدر ربات دیگر روی «ورود کد» بزنید و این کد را ارسال کنید.",
				array( 'code' => $code )
			)
		);
	}

	/**
	 * Set state waiting for code.
	 *
	 * @param object $user User.
	 */
	public static function prompt_code( $user ) {
		SimpleVPBot_State::set( (int) $user->id, 'awaiting_sync_code', array() );
	}

	/**
	 * Process entered code.
	 *
	 * @param array<string, mixed> $ctx Context.
	 */
	public static function handle_code( array $ctx ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = $ctx['user'];
		$code     = preg_replace( '/\D+/', '', SimpleVPBot_Bot_Runtime::normalize_digits( (string) $ctx['text'] ) );
		if ( strlen( $code ) < 6 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ کد نامعتبر است.' );
			return;
		}
		$row = SimpleVPBot_Model_Sync_Code::find_valid( $code );
		if ( $row ) {
			$primary_id = (int) $row->user_id;
			$cur_id     = (int) $user->id;
			if ( $primary_id === $cur_id ) {
				SimpleVPBot_State::clear( $cur_id );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, 'ℹ️ این کد متعلق به خودتان است.' );
				return;
			}
			SimpleVPBot_Model_User::merge_users( $primary_id, $cur_id, 'internal' );
			SimpleVPBot_Model_Sync_Code::consume( (int) $row->id );
			SimpleVPBot_State::clear( $primary_id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '✅ اکانت‌ها با موفقیت سینک شدند.' );
			return;
		}

		if ( class_exists( 'SimpleVPBot_Service_Transfer' ) ) {
			$res = SimpleVPBot_Service_Transfer::consume_code_and_transfer( $code, (int) $user->id, 'code' );
			if ( ! empty( $res['ok'] ) ) {
				SimpleVPBot_State::clear( (int) $user->id );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '✅ سرویس با موفقیت به شما منتقل شد.' );
				return;
			}
			if ( 'invalid_or_expired' !== (string) ( $res['reason'] ?? '' ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ انتقال سرویس انجام نشد: ' . (string) $res['reason'] );
				return;
			}
		}

		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ کد منقضی یا اشتباه است.' );
	}
}
