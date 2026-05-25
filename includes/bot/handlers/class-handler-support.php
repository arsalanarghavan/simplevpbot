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
	public static function show( $platform, $chat_id, $user = null ) {
		$body = SimpleVPBot_Texts::get_for_user( 'msg.support.intro', $user );
		if ( class_exists( 'SimpleVPBot_Support_Contacts' ) ) {
			$info = SimpleVPBot_Support_Contacts::info_text();
			if ( '' !== $info ) {
				$body .= "\n\n" . $info;
			}
		}
		$kb = array(
			'inline_keyboard' => array(
				array(
					array( 'text' => SimpleVPBot_Texts::get_in_bot_context( 'btn.support.contact', '📞 تماس با پشتیبانی' ), 'callback_data' => 'sup:c' ),
					array( 'text' => SimpleVPBot_Texts::get_in_bot_context( 'btn.support.faq', '❓ سوالات متداول' ), 'callback_data' => 'sup:f' ),
				),
			),
		);
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$body,
			array( 'reply_markup' => $kb )
		);
	}
}
