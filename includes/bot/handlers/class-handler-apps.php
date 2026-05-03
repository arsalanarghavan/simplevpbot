<?php
/**
 * App download links.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Apps
 */
class SimpleVPBot_Handler_Apps {

	/**
	 * Show inline app buttons.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat id.
	 */
	public static function show( $platform, $chat_id ) {
		$rows = array(
			array(
				array( 'text' => '🤖 v2rayNG', 'url' => SimpleVPBot_Texts::get( 'app.v2rayng', '#' ) ),
				array( 'text' => '🍎 Shadowrocket', 'url' => SimpleVPBot_Texts::get( 'app.shadowrocket', '#' ) ),
			),
			array(
				array( 'text' => '🪟 v2rayN', 'url' => SimpleVPBot_Texts::get( 'app.v2rayn', '#' ) ),
				array( 'text' => '🖥 V2rayU', 'url' => SimpleVPBot_Texts::get( 'app.v2rayu', '#' ) ),
			),
		);
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			"📱 دانلود اپلیکیشن‌ها\n➖➖➖➖➖➖➖➖\nیکی را انتخاب کنید:",
			array( 'reply_markup' => array( 'inline_keyboard' => $rows ) )
		);
	}
}
