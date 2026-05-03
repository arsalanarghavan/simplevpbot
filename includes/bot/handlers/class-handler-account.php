<?php
/**
 * Account info + sync entry.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Account
 */
class SimpleVPBot_Handler_Account {

	/**
	 * Show account card.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat id.
	 * @param object $user User.
	 */
	public static function show( $platform, $chat_id, $user ) {
		$n = SimpleVPBot_Model_Service::count_active( (int) $user->id );
		$kb = array(
			'inline_keyboard' => array(
				array(
					array( 'text' => SimpleVPBot_Texts::get( 'btn.account.sync', '🔗 سینک' ), 'callback_data' => 'sync:g' ),
					array( 'text' => '🔑 ورود کد', 'callback_data' => 'sync:i' ),
				),
			),
		);
		$txt = SimpleVPBot_Texts::format(
			"👤 اطلاعات حساب\n➖➖➖➖➖➖➖➖\n🆔 شناسه: {id}\n👑 نقش: {role}\n💰 موجودی: {bal}\n📡 سرویس فعال: {n}\n\n🌐 صفحهٔ شما برای دیدن سرویس و لینک:\n{portal}\n",
			array(
				'id'   => (string) $user->id,
				'role' => (string) $user->role,
				'bal'  => number_format( (float) $user->balance ),
				'n'    => (string) $n,
				'portal' => SimpleVPBot_Portal_Link::build_url( (int) $user->id ),
			)
		);
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $txt, array( 'reply_markup' => $kb ) );
	}
}
