<?php
/**
 * Paginated inline plan picker for discount wizards.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Bot_Admin_Plan_Picker
 */
class SimpleVPBot_Bot_Admin_Plan_Picker {

	const PAGE_SIZE = 6;

	/**
	 * @param string               $platform Platform.
	 * @param int                  $chat_id  Chat id.
	 * @param object               $user     User.
	 * @param string               $state    State key.
	 * @param array<string, mixed> $data     Wizard data (must include selected_ids[]).
	 * @param int                  $offset   Page offset.
	 */
	public static function send( $platform, $chat_id, $user, $state, array $data, $offset = 0 ) {
		$off = max( 0, (int) $offset );
		$plans = self::visible_plans( $platform, $chat_id );
		$selected = isset( $data['selected_ids'] ) && is_array( $data['selected_ids'] )
			? array_map( 'intval', $data['selected_ids'] )
			: array();
		$data['selected_ids'] = $selected;
		$data['pick_offset']  = $off;
		$data['step']         = 'plan_pick';
		SimpleVPBot_State::set( (int) $user->id, $state, $data );

		$total = count( $plans );
		$slice = array_slice( $plans, $off, self::PAGE_SIZE );
		$body  = SimpleVPBot_Bot_Admin_Texts::msg(
			'msg.admin.discount_plan_pick_header',
			$user,
			array(
				'selected' => (string) count( $selected ),
				'total'    => (string) $total,
			),
			'☑ پلن‌ها (' . count( $selected ) . ' انتخاب / ' . $total . ')'
		);
		$rows = array();
		foreach ( $slice as $p ) {
			if ( ! is_object( $p ) ) {
				continue;
			}
			$pid = (int) ( $p->id ?? 0 );
			if ( $pid < 1 ) {
				continue;
			}
			$mark = in_array( $pid, $selected, true ) ? '☑' : '☐';
			$lab  = mb_substr( (string) ( $p->name ?? '#' . $pid ), 0, 18 );
			$rows[] = array(
				array(
					'text'          => $mark . ' #' . $pid . ' ' . $lab,
					'callback_data' => 'pnl:pick:t:' . $pid,
				),
			);
		}
		$nav = array();
		if ( $off > 0 ) {
			$nav[] = array(
				'text'          => '◀',
				'callback_data' => 'pnl:pick:p:' . max( 0, $off - self::PAGE_SIZE ),
			);
		}
		$nav[] = array(
			'text'          => SimpleVPBot_Texts::get_for_user( 'btn.admin.discount_plans_all', $user, '🌐 همه' ),
			'callback_data' => 'pnl:pick:all',
		);
		$nav[] = array(
			'text'          => SimpleVPBot_Texts::get_for_user( 'btn.admin.discount_plans_done', $user, '✅ تأیید' ),
			'callback_data' => 'pnl:pick:ok',
		);
		if ( $total > $off + self::PAGE_SIZE ) {
			$nav[] = array(
				'text'          => '▶',
				'callback_data' => 'pnl:pick:p:' . ( $off + self::PAGE_SIZE ),
			);
		}
		if ( $nav ) {
			$rows[] = $nav;
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$body,
			array( 'reply_markup' => array( 'inline_keyboard' => $rows ) )
		);
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool True when handled.
	 */
	public static function handle_callback( array $ctx ) {
		$user     = $ctx['user'] ?? null;
		$platform = (string) ( $ctx['platform'] ?? '' );
		$chat_id  = (int) ( $ctx['chat_id'] ?? 0 );
		$parts    = is_array( $ctx['parts'] ?? null ) ? $ctx['parts'] : array();
		if ( ! $user || empty( $user->id ) || count( $parts ) < 4 || 'pnl' !== ( $parts[0] ?? '' ) || 'pick' !== ( $parts[1] ?? '' ) ) {
			return false;
		}
		$st = (string) $user->state;
		if ( ! in_array( $st, array( 'admin_discount_code', 'admin_discount_edit', 'admin_discount_plan_pick' ), true ) ) {
			return false;
		}
		$parent_st = ( 'admin_discount_plan_pick' === $st ) ? (string) ( SimpleVPBot_State::data( $user )['parent_state'] ?? 'admin_discount_code' ) : $st;
		$data      = SimpleVPBot_State::get( (int) $user->id );
		$data      = is_array( $data ) ? $data : array();
		if ( 'admin_discount_plan_pick' === $st ) {
			$data = isset( $data['wizard'] ) && is_array( $data['wizard'] ) ? $data['wizard'] : $data;
		}
		$op = (string) ( $parts[2] ?? '' );
		if ( 'p' === $op && isset( $parts[3] ) ) {
			self::send( $platform, $chat_id, $user, $parent_st, $data, (int) $parts[3] );
			return true;
		}
		if ( 't' === $op && isset( $parts[3] ) ) {
			$pid = (int) $parts[3];
			$sel = isset( $data['selected_ids'] ) && is_array( $data['selected_ids'] ) ? array_map( 'intval', $data['selected_ids'] ) : array();
			if ( in_array( $pid, $sel, true ) ) {
				$sel = array_values( array_diff( $sel, array( $pid ) ) );
			} else {
				$sel[] = $pid;
			}
			$data['selected_ids'] = $sel;
			$off = isset( $data['pick_offset'] ) ? (int) $data['pick_offset'] : 0;
			self::send( $platform, $chat_id, $user, $parent_st, $data, $off );
			return true;
		}
		if ( 'all' === $op ) {
			$data['selected_ids'] = array();
			$data['all_plans']    = 1;
			return self::finish_pick( $platform, $chat_id, $user, $parent_st, $data );
		}
		if ( 'ok' === $op ) {
			unset( $data['all_plans'] );
			$data['allowed_plan_ids'] = isset( $data['selected_ids'] ) ? $data['selected_ids'] : array();
			return self::finish_pick( $platform, $chat_id, $user, $parent_st, $data );
		}
		return false;
	}

	/**
	 * Begin picker after valid_until step.
	 *
	 * @param string               $platform Platform.
	 * @param int                  $chat_id  Chat id.
	 * @param object               $user     User.
	 * @param string               $state    admin_discount_code|admin_discount_edit.
	 * @param array<string, mixed> $data     Wizard data.
	 */
	public static function begin( $platform, $chat_id, $user, $state, array $data ) {
		if ( ! isset( $data['selected_ids'] ) && isset( $data['allowed_plan_ids'] ) ) {
			$data['selected_ids'] = array_map( 'intval', (array) $data['allowed_plan_ids'] );
		} elseif ( ! isset( $data['selected_ids'] ) ) {
			$data['selected_ids'] = array();
		}
		self::send( $platform, $chat_id, $user, $state, $data, 0 );
	}

	/**
	 * @param string               $platform Platform.
	 * @param int                  $chat_id  Chat id.
	 * @param object               $user     User.
	 * @param string               $state    Parent state.
	 * @param array<string, mixed> $data     Wizard data.
	 * @return bool
	 */
	private static function finish_pick( $platform, $chat_id, $user, $state, array $data ) {
		SimpleVPBot_State::set( (int) $user->id, $state, $data );
		if ( 'admin_discount_edit' === $state && class_exists( 'SimpleVPBot_Handler_Admin_Marketing' ) ) {
			return SimpleVPBot_Handler_Admin_Marketing::finish_discount_from_picker( $platform, $chat_id, $user, $data, 'msg.admin.discount_updated' );
		}
		if ( class_exists( 'SimpleVPBot_Handler_Admin_Marketing' ) ) {
			return SimpleVPBot_Handler_Admin_Marketing::finish_discount_from_picker( $platform, $chat_id, $user, $data, 'msg.admin.discount_created' );
		}
		return true;
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @return array<int, object>
	 */
	private static function visible_plans( $platform, $chat_id ) {
		$list = class_exists( 'SimpleVPBot_Model_Plan' ) ? SimpleVPBot_Model_Plan::all_rows() : array();
		if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) ) {
			$list = SimpleVPBot_Feature_L2tp::filter_plans( (array) $list );
		}
		return class_exists( 'SimpleVPBot_Bot_Admin_Catalog_Scope' )
			? SimpleVPBot_Bot_Admin_Catalog_Scope::filter_plans( (array) $list, $platform, $chat_id )
			: (array) $list;
	}
}
