<?php
/**
 * Bot admin bulk operations facade.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Admin_Bulk
 */
class SimpleVPBot_Handler_Admin_Bulk {

	/**
	 * @param string               $platform Platform.
	 * @param int                  $chat_id  Chat id.
	 * @param object               $user     User.
	 * @param array<string, mixed> $ctx      Optional context.
	 */
	public static function open_tab( $platform, $chat_id, $user, array $ctx = array() ) {
		$body = SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.tutorial.bulk', $user );
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$body,
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_bulk_submenu_reply( $user ) )
		);
	}

	/**
	 * Step 1 of 2: bulk +days — ask inline confirmation.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param int    $days     Days.
	 */
	public static function days_confirm( $platform, $chat_id, $days ) {
		if ( SimpleVPBot_Handler_Admin_Pnl::bot_admin_deny_site_bulk( $platform, $chat_id ) ) {
			return;
		}
		$d = max( 1, (int) $days );
		$me = ( 'bale' === $platform ) ? SimpleVPBot_Model_User::find_by_bale( $chat_id ) : SimpleVPBot_Model_User::find_by_telegram( $chat_id );
		$t  = $me
			? SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.bulk_confirm_days_prompt', $me, array( 'days' => (string) $d ) )
			: "⚠️ تأیید عملیات گروهی\n➖\nافزودن «{$d}» روز به سرویس‌های Xray (حداکثر ۲۰۰ سرویس در هر اجرا).\nادامه؟";
		$confirm = $me
			? SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.bulk_confirm_days', $me, array( 'n' => (string) $d ) )
			: SimpleVPBot_Keyboards::glass_button_text( '✅ تأیید +' . $d . ' روز', 256 );
		$cancel = $me
			? SimpleVPBot_Texts::get_for_user( 'msg.admin.bulk_cancel', $me )
			: SimpleVPBot_Keyboards::glass_button_text( '❌ لغو گروهی', 256 );
		$rows = array(
			array(
				array( 'text' => $confirm ),
				array( 'text' => $cancel ),
			),
		);
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t, array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $rows ) ) );
	}

	/**
	 * Step 1 of 2: bulk +GB — ask inline confirmation.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param int    $gb       Gigabytes.
	 */
	public static function gb_confirm( $platform, $chat_id, $gb ) {
		if ( SimpleVPBot_Handler_Admin_Pnl::bot_admin_deny_site_bulk( $platform, $chat_id ) ) {
			return;
		}
		$g  = max( 1, (int) $gb );
		$me = ( 'bale' === $platform ) ? SimpleVPBot_Model_User::find_by_bale( $chat_id ) : SimpleVPBot_Model_User::find_by_telegram( $chat_id );
		$t  = $me
			? SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.bulk_confirm_gb_prompt', $me, array( 'gb' => (string) $g ) )
			: "⚠️ تأیید عملیات گروهی\n➖\nافزودن «{$g}» گیگ به هر سرویس Xray (حداکثر ۲۰۰ سرویس).\nادامه؟";
		$confirm = $me
			? SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.bulk_confirm_gb', $me, array( 'n' => (string) $g ) )
			: SimpleVPBot_Keyboards::glass_button_text( '✅ تأیید +' . $g . ' GB', 256 );
		$cancel = $me
			? SimpleVPBot_Texts::get_for_user( 'msg.admin.bulk_cancel', $me )
			: SimpleVPBot_Keyboards::glass_button_text( '❌ لغو گروهی', 256 );
		$rows = array(
			array(
				array( 'text' => $confirm ),
				array( 'text' => $cancel ),
			),
		);
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t, array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $rows ) ) );
	}

	/**
	 * Step 2 of 2: bulk +days — execute after confirmation.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param int    $days     Days.
	 */
	public static function execute_extend_days( $platform, $chat_id, $days ) {
		if ( SimpleVPBot_Handler_Admin_Pnl::bot_admin_deny_site_bulk( $platform, $chat_id ) ) {
			return;
		}
		if ( ! class_exists( 'SimpleVPBot_Admin_User_Ops' ) ) {
			return;
		}
		$d = max( 1, (int) $days );
		$r = SimpleVPBot_Admin_User_Ops::bulk_extend_days( $d, true, 200 );
		$me = ( 'bale' === $platform ) ? SimpleVPBot_Model_User::find_by_bale( $chat_id ) : SimpleVPBot_Model_User::find_by_telegram( $chat_id );
		$msg = $me && class_exists( 'SimpleVPBot_Bot_Admin_Texts' )
			? SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.bulk_days_done', $me, array( 'days' => (string) $d, 'done' => (string) (int) $r['done'], 'errors' => (string) (int) $r['errors'] ) )
			: "✅ +{$d} روز · انجام: " . (int) $r['done'] . ' · خطا: ' . (int) $r['errors'];
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$msg,
			array( 'reply_markup' => SimpleVPBot_Handler_Admin_Pnl::inline_hub_root_for_admin_chat( $platform, $chat_id ) )
		);
	}

	/**
	 * Step 2 of 2: bulk +GB — execute after confirmation.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param int    $gb       Gigabytes.
	 */
	public static function execute_add_volume( $platform, $chat_id, $gb ) {
		if ( SimpleVPBot_Handler_Admin_Pnl::bot_admin_deny_site_bulk( $platform, $chat_id ) ) {
			return;
		}
		if ( ! class_exists( 'SimpleVPBot_Admin_User_Ops' ) ) {
			return;
		}
		$g = max( 1, (int) $gb );
		$r = SimpleVPBot_Admin_User_Ops::bulk_add_volume( $g, 200 );
		$me = ( 'bale' === $platform ) ? SimpleVPBot_Model_User::find_by_bale( $chat_id ) : SimpleVPBot_Model_User::find_by_telegram( $chat_id );
		$msg = $me && class_exists( 'SimpleVPBot_Bot_Admin_Texts' )
			? SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.bulk_gb_done', $me, array( 'gb' => (string) $g, 'done' => (string) (int) $r['done'], 'errors' => (string) (int) $r['errors'] ) )
			: "✅ +{$g} GB · انجام: " . (int) $r['done'] . ' · خطا: ' . (int) $r['errors'];
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$msg,
			array( 'reply_markup' => SimpleVPBot_Handler_Admin_Pnl::inline_hub_root_for_admin_chat( $platform, $chat_id ) )
		);
	}
}
