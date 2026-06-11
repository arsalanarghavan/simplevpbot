<?php
/**
 * Bot admin logs facade.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Admin_Logs
 */
class SimpleVPBot_Handler_Admin_Logs {

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 * @param int    $offset   Offset.
	 */
	public static function open_tab( $platform, $chat_id, $user, $offset = 0 ) {
		self::send_page( $platform, $chat_id, $offset, $user );
	}

	/**
	 * Logs with pagination.
	 *
	 * @param string      $platform Platform.
	 * @param int         $chat_id  Chat id.
	 * @param int         $offset   Offset.
	 * @param object|null $user     Admin user for state.
	 */
	public static function send_page( $platform, $chat_id, $offset = 0, $user = null ) {
		global $wpdb;
		$lt  = $wpdb->prefix . 'svp_logs';
		$off = max( 0, (int) $offset );
		$lim = 8;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders
		$logs = $wpdb->get_results( $wpdb->prepare( "SELECT level, message, created_at FROM {$lt} ORDER BY id DESC LIMIT %d OFFSET %d", $lim, $off ) );
		$cnt  = count( (array) $logs );
		if ( $user && is_object( $user ) ) {
			$t = SimpleVPBot_Bot_Admin_Texts::msg(
				'msg.admin.logs.header',
				$user,
				array(
					'from' => (string) ( $off + 1 ),
					'to'   => (string) ( $off + $cnt ),
				)
			) . "\n➖\n";
			if ( empty( $logs ) ) {
				$t .= SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.logs.empty', $user );
			} else {
				foreach ( $logs as $lg ) {
					$t .= '[' . (string) $lg->level . '] ' . mb_substr( (string) $lg->message, 0, 70 ) . "\n";
				}
			}
			$prev_label = SimpleVPBot_Texts::get_for_user( 'btn.admin.logs_prev', $user );
			$next_label = SimpleVPBot_Texts::get_for_user( 'btn.admin.logs_next', $user );
		} else {
			$t = "📜 لاگ (" . ( $off + 1 ) . "–" . ( $off + $cnt ) . ")\n➖\n";
			if ( empty( $logs ) ) {
				$t .= 'رکوردی نیست.';
			} else {
				foreach ( $logs as $lg ) {
					$t .= '[' . (string) $lg->level . '] ' . mb_substr( (string) $lg->message, 0, 70 ) . "\n";
				}
			}
			$prev_label = '◀ لاگ قبلی';
			$next_label = 'لاگ بعدی ▶';
		}
		if ( $user && ! empty( $user->id ) ) {
			SimpleVPBot_State::set( (int) $user->id, 'admin_log_page', array( 'off' => $off ) );
		}
		$nav = array();
		if ( $off > 0 ) {
			$nav[] = array( 'text' => $prev_label );
		}
		if ( $cnt >= $lim ) {
			$nav[] = array( 'text' => $next_label );
		}
		$ik = array();
		if ( $nav ) {
			$ik[] = $nav;
		}
		$back = $user ? SimpleVPBot_Keyboards::admin_panel_section_reply( 'settings', $user ) : SimpleVPBot_Keyboards::admin_only_back_reply();
		if ( $ik && isset( $back['keyboard'] ) && is_array( $back['keyboard'] ) ) {
			$back = SimpleVPBot_Keyboards::admin_reply_wrap_rows( array_merge( $ik, $back['keyboard'] ) );
		} elseif ( $ik ) {
			$back = SimpleVPBot_Keyboards::admin_reply_wrap_rows( $ik );
		}
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t, array( 'reply_markup' => $back ) );
	}
}
