<?php
/**
 * User conversation state (DB-backed).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_State
 */
class SimpleVPBot_State {

	/**
	 * Set state.
	 *
	 * @param int                  $user_id User id.
	 * @param string|null          $state State key.
	 * @param array<string, mixed> $data State data.
	 */
	public static function set( $user_id, $state, array $data = array() ) {
		SimpleVPBot_Model_User::update(
			(int) $user_id,
			array(
				'state'      => $state,
				'state_data' => wp_json_encode( $data ),
			)
		);
	}

	/**
	 * Get decoded state data.
	 *
	 * @param object $user User row.
	 * @return array<string, mixed>
	 */
	public static function data( $user ) {
		if ( empty( $user->state_data ) ) {
			return array();
		}
		$j = json_decode( (string) $user->state_data, true );
		return is_array( $j ) ? $j : array();
	}

	/**
	 * Clear state.
	 *
	 * @param int $user_id User id.
	 */
	public static function clear( $user_id ) {
		self::set( $user_id, null, array() );
	}

	/**
	 * Whether this state waits for typed/photo input and should yield to main menu or callbacks.
	 *
	 * @param string $state      User state string.
	 * @param string $platform   telegram|bale (for admin transfer exception).
	 * @param int    $from_id    Chat user id on platform.
	 * @return bool
	 */
	public static function is_blocking_state( $state, $platform = '', $from_id = 0 ) {
		$st = (string) $state;
		if ( '' === $st ) {
			return false;
		}
		if ( 'awaiting_sync_code' === $st ) {
			return true;
		}
		if ( 'receipt_upload' === $st ) {
			return true;
		}
		if ( 'buy_choose_traffic' === $st ) {
			return true;
		}
		if ( 'buy_discount' === $st ) {
			return true;
		}
		if ( 0 === strpos( $st, 'buy_' ) ) {
			return true;
		}
		if ( 0 === strpos( $st, 'svc_note_' ) || 0 === strpos( $st, 'svc_rename_' ) || 0 === strpos( $st, 'svc_addvol_' ) || 0 === strpos( $st, 'svc_addusers_' ) || 0 === strpos( $st, 'svc_al_' ) ) {
			return true;
		}
		if ( 0 === strpos( $st, 'adm_service_transfer_' ) ) {
			if ( $platform && $from_id && SimpleVPBot_Router::is_platform_admin( $platform, (int) $from_id ) ) {
				return false;
			}
			return true;
		}
		if ( 0 === strpos( $st, 'admin_bak_' ) ) {
			return true;
		}
		if ( 'admin_find_user' === $st || 'admin_dm' === $st ) {
			return true;
		}
		if ( 0 === strpos( $st, 'admin_w_' ) || 0 === strpos( $st, 'admin_set_' ) || 0 === strpos( $st, 'admin_line_' ) || 0 === strpos( $st, 'admin_ns_' ) || in_array( $st, array( 'admin_txt_edit', 'admin_inb_uid' ), true ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Reply keyboard labels that open the main menu (must match user_main_reply / Texts).
	 *
	 * @param string      $text Trimmed message text.
	 * @param object|null $user Optional user row for locale-aware labels.
	 * @return bool
	 */
	public static function is_main_menu_reply_text( $text, $user = null ) {
		$t = trim( (string) $text );
		if ( '' === $t ) {
			return false;
		}
		if ( class_exists( 'SimpleVPBot_UI_Layout' ) ) {
			foreach ( SimpleVPBot_UI_Layout::user_main_visible_labels( $user ) as $lab ) {
				if ( $t === $lab ) {
					return true;
				}
			}
			return false;
		}
		$pairs = array(
			array( 'btn.main.buy', '🛒 خرید سرویس' ),
			array( 'btn.main.manage', '🧰 مدیریت سرویس' ),
			array( 'btn.main.apps', '📱 اپلیکیشن‌ها' ),
			array( 'btn.main.support', '🆘 پشتیبانی' ),
			array( 'btn.main.account', '👤 اطلاعات حساب' ),
			array( 'btn.main.wallet', '💰 کیف پول' ),
			array( 'btn.main.referral', '💎 کسب درآمد' ),
		);
		foreach ( $pairs as $pair ) {
			if ( $t === SimpleVPBot_Texts::get( $pair[0], $pair[1] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * If user is in a blocking state and sent a main-menu label, clear state so routing continues normally.
	 *
	 * @param string               $platform telegram|bale.
	 * @param int                  $from_id  User id on platform.
	 * @param object               $user     User row.
	 * @param string               $text_trim Trimmed text.
	 * @return bool Whether state was cleared.
	 */
	public static function interrupt_blocking_state_on_main_menu_text( $platform, $from_id, $user, $text_trim ) {
		if ( ! $user || ! self::is_main_menu_reply_text( $text_trim, $user ) ) {
			return false;
		}
		if ( (int) $user->admin_mode && SimpleVPBot_Router::is_platform_admin( $platform, (int) $from_id ) ) {
			return false;
		}
		$st = (string) $user->state;
		if ( ! self::is_blocking_state( $st, $platform, (int) $from_id ) ) {
			return false;
		}
		self::clear( (int) $user->id );
		return true;
	}

	/**
	 * Clear blocking state when user taps any inline callback (except noop / admin paths handled by caller).
	 *
	 * @param string               $platform telegram|bale.
	 * @param int                  $from_id  User id on platform.
	 * @param object               $user     User row.
	 * @param int                  $chat_id  Chat id.
	 * @param string               $callback_data Callback data.
	 * @return bool Whether state was cleared.
	 */
	public static function clear_blocking_state_on_callback( $platform, $from_id, $user, $chat_id, $callback_data ) {
		if ( ! $user || 'noop' === (string) $callback_data ) {
			return false;
		}
		if ( (int) $user->admin_mode && SimpleVPBot_Router::is_platform_admin( $platform, (int) $from_id ) ) {
			return false;
		}
		$st = (string) $user->state;
		if ( ! self::is_blocking_state( $st, $platform, (int) $from_id ) ) {
			return false;
		}
		self::clear( (int) $user->id );
		$notify = ( 0 === strpos( $st, 'svc_' ) ) || 'receipt_upload' === $st || 'buy_choose_traffic' === $st || 'buy_discount' === $st;
		if ( $notify && $chat_id > 0 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, (int) $chat_id, 'ℹ️ درخواست قبلی لغو شد.' );
		}
		return true;
	}
}
