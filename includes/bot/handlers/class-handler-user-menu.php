<?php
/**
 * Route main reply keyboard texts.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_User_Menu
 */
class SimpleVPBot_Handler_User_Menu {

	/**
	 * Route.
	 *
	 * @param array<string, mixed> $ctx Context.
	 */
	public static function route_text( array $ctx ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = $ctx['user'];
		$text     = trim( (string) $ctx['text'] );

		if ( $text === SimpleVPBot_Texts::get( 'btn.main.buy', '🛒 خرید سرویس' ) ) {
			SimpleVPBot_Handler_Buy::send_category_picker( $platform, $chat_id );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get( 'btn.main.manage', '🧰 مدیریت سرویس' ) ) {
			$list = SimpleVPBot_Model_Service::by_user( (int) $user->id );
			if ( empty( $list ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get( 'msg.no_active_services', '🧰 سرویس فعالی ندارید.' ) );
				return;
			}
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				'🧰 سرویس خود را انتخاب کنید:',
				array( 'reply_markup' => SimpleVPBot_Keyboards::inline_service_list( $list ) )
			);
			return;
		}
		if ( $text === SimpleVPBot_Texts::get( 'btn.main.wallet', '💰 کیف پول' ) ) {
			SimpleVPBot_Handler_Wallet::show( $platform, $chat_id, $user );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get( 'btn.main.apps', '📱 اپلیکیشن‌ها' ) ) {
			SimpleVPBot_Handler_Apps::show( $platform, $chat_id );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get( 'btn.main.support', '🆘 پشتیبانی' ) ) {
			SimpleVPBot_Handler_Support::show( $platform, $chat_id );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get( 'btn.main.account', '👤 اطلاعات حساب' ) ) {
			SimpleVPBot_Handler_Account::show( $platform, $chat_id, $user );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get( 'btn.main.referral', '💎 کسب درآمد' ) ) {
			SimpleVPBot_Handler_Referral::show( $platform, $chat_id, $user );
			return;
		}
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, 'ℹ️ از دکمه‌های پایین استفاده کنید.' );
	}
}
