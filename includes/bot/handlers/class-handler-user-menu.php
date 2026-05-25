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

		if ( class_exists( 'SimpleVPBot_UI_Layout' ) ) {
			$aid = SimpleVPBot_UI_Layout::match_user_main_action( $text, $user );
			if ( $aid ) {
				switch ( $aid ) {
					case 'user.main.buy':
						SimpleVPBot_Handler_Buy::send_category_picker( $platform, $chat_id );
						return;
					case 'user.main.manage':
						if ( class_exists( 'SimpleVPBot_Service_Reconcile' ) && 'approved' === (string) ( $user->status ?? '' ) ) {
							SimpleVPBot_Service_Reconcile::reconcile_for_user( (int) $user->id );
						}
						$list = SimpleVPBot_Model_Service::by_user( (int) $user->id );
						if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) ) {
							$list = SimpleVPBot_Feature_L2tp::filter_services( (array) $list );
						}
						if ( empty( $list ) ) {
							SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.no_active_services', $user ) );
							return;
						}
						SimpleVPBot_Bot_Runtime::send_message(
							$platform,
							$chat_id,
							SimpleVPBot_Texts::get_for_user( 'msg.pick_service_inline', $user ),
							array( 'reply_markup' => SimpleVPBot_Keyboards::inline_service_list( $list, $user ) )
						);
						return;
					case 'user.main.wallet':
						SimpleVPBot_Handler_Wallet::show( $platform, $chat_id, $user );
						return;
					case 'user.main.apps':
						SimpleVPBot_Handler_Apps::show( $platform, $chat_id, $user );
						return;
					case 'user.main.support':
						SimpleVPBot_Handler_Support::show( $platform, $chat_id, $user );
						return;
					case 'user.main.account':
						SimpleVPBot_Handler_Account::show( $platform, $chat_id, $user );
						return;
					case 'user.main.referral':
						SimpleVPBot_Handler_Referral::show( $platform, $chat_id, $user );
						return;
					default:
						break;
				}
			}
		}

		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.main.buy', $user ) ) {
			SimpleVPBot_Handler_Buy::send_category_picker( $platform, $chat_id );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.main.manage', $user ) ) {
			if ( class_exists( 'SimpleVPBot_Service_Reconcile' ) && 'approved' === (string) ( $user->status ?? '' ) ) {
				SimpleVPBot_Service_Reconcile::reconcile_for_user( (int) $user->id );
			}
			$list = SimpleVPBot_Model_Service::by_user( (int) $user->id );
			if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) ) {
				$list = SimpleVPBot_Feature_L2tp::filter_services( (array) $list );
			}
			if ( empty( $list ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.no_active_services', $user ) );
				return;
			}
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Texts::get_for_user( 'msg.pick_service_inline', $user ),
				array( 'reply_markup' => SimpleVPBot_Keyboards::inline_service_list( $list, $user ) )
			);
			return;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.main.wallet', $user ) ) {
			SimpleVPBot_Handler_Wallet::show( $platform, $chat_id, $user );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.main.apps', $user ) ) {
			SimpleVPBot_Handler_Apps::show( $platform, $chat_id, $user );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.main.support', $user ) ) {
			SimpleVPBot_Handler_Support::show( $platform, $chat_id, $user );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.main.account', $user ) ) {
			SimpleVPBot_Handler_Account::show( $platform, $chat_id, $user );
			return;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.main.referral', $user ) ) {
			SimpleVPBot_Handler_Referral::show( $platform, $chat_id, $user );
			return;
		}
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.use_reply_buttons', $user ) );
	}
}
