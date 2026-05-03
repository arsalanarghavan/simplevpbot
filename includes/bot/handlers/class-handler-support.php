<?php
/**
 * Support menu.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Support
 */
class SimpleVPBot_Handler_Support {

	/**
	 * Show support inline.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat id.
	 */
	public static function show( $platform, $chat_id ) {
		$kb = array(
			'inline_keyboard' => array(
				array(
					array( 'text' => SimpleVPBot_Texts::get( 'btn.support.contact', '📞 تماس با پشتیبانی' ), 'callback_data' => 'sup:c' ),
					array( 'text' => SimpleVPBot_Texts::get( 'btn.support.faq', '❓ سوالات متداول' ), 'callback_data' => 'sup:f' ),
				),
			),
		);
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			"🆘 پشتیبانی\n➖➖➖➖➖➖➖➖\nچه کمکی نیاز دارید؟",
			array( 'reply_markup' => $kb )
		);
	}
}
