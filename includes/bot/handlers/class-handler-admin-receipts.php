<?php
/**
 * Bot admin receipts facade.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Admin_Receipts
 */
class SimpleVPBot_Handler_Admin_Receipts {

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param int    $offset   Offset.
	 */
	public static function send_pending_review_paged( $platform, $chat_id, $offset = 0 ) {
		$admin_u = self::resolve_admin_user( $platform, $chat_id );
		if ( $admin_u && class_exists( 'SimpleVPBot_Reseller_Permission_Gate' )
			&& ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $admin_u->id, 'receipt_review' ) ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				self::msg( $platform, $chat_id, 'msg.admin.denied_permission' )
			);
			return;
		}
		$lock_key = self::review_lock_key( $platform, $chat_id );
		if ( get_transient( $lock_key ) ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				self::msg( $platform, $chat_id, 'msg.admin.receipt_review_busy' )
			);
			return;
		}
		set_transient( $lock_key, '1', 55 );

		$per = (int) apply_filters( 'simplevpbot_receipt_review_per_page', 3 );
		$per = max( 1, min( 10, $per ) );
		$off = max( 0, (int) $offset );

		$scope = self::scope_user_ids();
		if ( is_array( $scope ) ) {
			$total = SimpleVPBot_Model_Receipt::pending_count_for_user_ids( $scope );
		} else {
			$total = SimpleVPBot_Model_Receipt::pending_count();
		}
		if ( $total < 1 ) {
			delete_transient( $lock_key );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				self::msg( $platform, $chat_id, 'msg.admin.receipt_none' ),
				array( 'reply_markup' => SimpleVPBot_Keyboards::admin_finance_submenu_reply() )
			);
			return;
		}
		$list = is_array( $scope )
			? SimpleVPBot_Model_Receipt::pending_paged_for_user_ids( $off, $per, $scope )
			: SimpleVPBot_Model_Receipt::pending_paged( $off, $per );
		if ( empty( $list ) ) {
			delete_transient( $lock_key );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				self::msg( $platform, $chat_id, 'msg.admin.receipt_page_empty' ),
				array( 'reply_markup' => SimpleVPBot_Keyboards::admin_finance_submenu_reply() )
			);
			return;
		}

		$n_send = count( $list );
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			self::msg( $platform, $chat_id, 'msg.admin.receipt_sending', array( 'n' => SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $n_send ) ) )
		);

		foreach ( $list as $r ) {
			self::send_one_pending_review( $platform, $chat_id, $r );
			usleep( 180000 );
		}

		$next = $off + count( $list );
		$rows = array();
		if ( $next < $total ) {
			$cb = 'pnl:rcp:p:' . $next;
			if ( strlen( $cb ) <= 64 ) {
				$rows[] = array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '📄 صفحه بعد', 64 ), 'callback_data' => $cb ) );
			}
		}
		$sum = '🧾 نمایش ' . SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) ( $off + 1 ) )
			. '–' . SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) min( $next, $total ) )
			. ' از ' . SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $total ) . ' رسید معلق.';
		$sum .= "\n⬅️ برای بازگشت «" . SimpleVPBot_Keyboards::admin_back_main_label() . '» یا زیرمنوی مالی.';
		$sum_args = array( 'reply_markup' => SimpleVPBot_Keyboards::admin_finance_submenu_reply() );
		if ( ! empty( $rows ) ) {
			$sum_args['reply_markup'] = array( 'inline_keyboard' => $rows );
		}
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $sum, $sum_args );
		delete_transient( $lock_key );
	}

	/**
	 * @param string $platform telegram|bale.
	 * @param int    $chat_id  Admin destination.
	 * @param object $rec      Receipt row.
	 */
	public static function send_one_pending_review( $platform, $chat_id, $rec ) {
		$rid = (int) $rec->id;
		$ru  = SimpleVPBot_Model_User::find( (int) $rec->user_id );
		$tx  = SimpleVPBot_Model_Transaction::find( (int) $rec->transaction_id );
		if ( ! $ru || ! $tx ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				self::msg( $platform, $chat_id, 'msg.admin.receipt_incomplete', array( 'id' => $rid ) ),
				array( 'reply_markup' => SimpleVPBot_Keyboards::inline_receipt( $rid ) )
			);
			return;
		}
		$body       = SimpleVPBot_Bot_Admin_User_Caption::receipt_new_caption_for_platform( $ru, $tx, $rid, (string) $platform );
		$photo_args = array( 'reply_markup' => SimpleVPBot_Keyboards::inline_receipt( $rid ) );
		$tg_id      = (string) ( $rec->tg_file_id ?? '' );
		$bl_id      = (string) ( $rec->bale_file_id ?? '' );
		$r          = SimpleVPBot_Handler_Buy::send_admin_receipt_photo_review(
			$platform,
			(int) $chat_id,
			$rec,
			$tg_id,
			$bl_id,
			$body,
			$photo_args,
			$rid
		);
		$admin_msgs = array();
		if ( is_array( $r ) && ! empty( $r['result']['message_id'] ) ) {
			$admin_msgs[] = array(
				'platform'   => (string) $platform,
				'chat_id'    => (int) $chat_id,
				'message_id' => (int) $r['result']['message_id'],
				'kind'       => 'photo',
			);
		} else {
			SimpleVPBot_Handler_Buy::notify_admin_receipt_photo_fallback( $platform, (int) $chat_id, $rid );
		}
		if ( ! empty( $admin_msgs ) ) {
			SimpleVPBot_Handler_Buy::merge_admin_message_entries( $rid, $admin_msgs );
		}
	}

	/**
	 * @param string $platform telegram|bale.
	 * @param int    $chat_id  Admin chat.
	 * @return string
	 */
	private static function review_lock_key( $platform, $chat_id ) {
		return 'svp_rcp_review_' . sanitize_key( (string) $platform ) . '_' . max( 0, (int) $chat_id );
	}

	/**
	 * @return array<int, int>|null
	 */
	private static function scope_user_ids() {
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return null;
		}
		return SimpleVPBot_Bot_Reseller_Scope::bot_admin_scope_user_ids();
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @return object|null
	 */
	private static function resolve_admin_user( $platform, $chat_id ) {
		if ( ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return null;
		}
		return 'bale' === $platform
			? SimpleVPBot_Model_User::find_by_bale( (int) $chat_id )
			: SimpleVPBot_Model_User::find_by_telegram( (int) $chat_id );
	}

	/**
	 * @param string               $platform Platform.
	 * @param int                  $chat_id  Chat id.
	 * @param string               $key      Text key.
	 * @param array<string,string> $vars     Placeholders.
	 * @return string
	 */
	private static function msg( $platform, $chat_id, $key, array $vars = array() ) {
		$u = self::resolve_admin_user( $platform, $chat_id );
		$t = SimpleVPBot_Texts::get_for_user( $key, $u );
		return empty( $vars ) ? $t : SimpleVPBot_Texts::format( $t, $vars );
	}
}
