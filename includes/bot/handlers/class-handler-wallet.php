<?php
/**
 * Wallet UI.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Wallet
 */
class SimpleVPBot_Handler_Wallet {

	/**
	 * Show balance.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat id.
	 * @param object $user User.
	 */
	public static function show( $platform, $chat_id, $user ) {
		$bal = number_format( (float) $user->balance, 0, '.', ',' );
		$kb  = array(
			'inline_keyboard' => array(
				array(
					array( 'text' => SimpleVPBot_Texts::get( 'btn.wallet.history', '📜 تاریخچه' ), 'callback_data' => 'wal:h' ),
				),
			),
		);
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			"💰 کیف پول شما\n➖➖➖➖➖➖➖➖\n💵 موجودی: {$bal} تومان\n➖➖➖➖➖➖➖➖\nبرای شارژ از بخش خرید/پشتیبانی اقدام کنید.",
			array( 'reply_markup' => $kb )
		);
	}
}
