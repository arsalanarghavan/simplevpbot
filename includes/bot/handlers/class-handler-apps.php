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
	public static function show( $platform, $chat_id, $user = null ) {
		$rows = array(
			array(
				array( 'text' => SimpleVPBot_Texts::label( 'btn.apps.v2rayng', $user ), 'url' => SimpleVPBot_Texts::get( 'app.v2rayng', '#', SimpleVPBot_Texts::locale_for_user( $user ) ) ),
				array( 'text' => SimpleVPBot_Texts::label( 'btn.apps.shadowrocket', $user ), 'url' => SimpleVPBot_Texts::get( 'app.shadowrocket', '#', SimpleVPBot_Texts::locale_for_user( $user ) ) ),
			),
			array(
				array( 'text' => SimpleVPBot_Texts::label( 'btn.apps.v2rayn', $user ), 'url' => SimpleVPBot_Texts::get( 'app.v2rayn', '#', SimpleVPBot_Texts::locale_for_user( $user ) ) ),
				array( 'text' => SimpleVPBot_Texts::label( 'btn.apps.v2rayu', $user ), 'url' => SimpleVPBot_Texts::get( 'app.v2rayu', '#', SimpleVPBot_Texts::locale_for_user( $user ) ) ),
			),
		);
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			SimpleVPBot_Texts::get_for_user( 'msg.apps.pick', $user ),
			array( 'reply_markup' => array( 'inline_keyboard' => $rows ) )
		);
	}
}
