<?php
/**
 * Bot admin — plans/cards/plan_cats catalog (full CRUD via mutate bridge).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Admin_Catalog
 */
class SimpleVPBot_Handler_Admin_Catalog {

	const PAGE_SIZE = 8;

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 * @param string $kind     plans|cards|plan_cats.
	 * @param int    $offset   Offset.
	 */
	public static function send_list( $platform, $chat_id, $user, $kind, $offset = 0, $prefix = '' ) {
		$kind = sanitize_key( (string) $kind );
		if ( ! in_array( $kind, array( 'plans', 'cards', 'plan_cats' ), true ) ) {
			$kind = 'plans';
		}
		$off  = max( 0, (int) $offset );
		$lim  = self::PAGE_SIZE;
		$rows = self::rows_for_kind( $kind, $platform, $chat_id );
		$body = self::header_for_kind( $kind, $user );
		$total = count( $rows );
		$slice = array_slice( $rows, $off, $lim );
		$body .= "\n(" . ( $total > 0 ? ( $off + 1 ) : 0 ) . '–' . min( $off + $lim, $total ) . " / {$total})\n";
		if ( empty( $slice ) ) {
			$body .= "\n" . SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.catalog.empty', $user );
		} else {
			foreach ( $slice as $row ) {
				if ( ! is_object( $row ) ) {
					continue;
				}
				$id    = (int) ( $row->id ?? 0 );
				$label = self::row_label( $row, $kind );
				$act   = ! empty( $row->active ) ? '✅' : '⏸';
				$body .= "\n• #{$id} {$label} {$act}";
			}
		}
		if ( '' !== trim( (string) $prefix ) ) {
			$body = trim( (string) $prefix ) . "\n\n" . $body;
		}
		SimpleVPBot_State::set( (int) $user->id, 'admin_catalog_list', array( 'kind' => $kind, 'offset' => $off ) );
		$inline = self::list_inline_keyboard( $kind, $slice, $off, $total, $lim, $user );
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$body,
			array( 'reply_markup' => array( 'inline_keyboard' => $inline ) )
		);
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 */
	public static function handle_callback( array $ctx ) {
		$platform = (string) ( $ctx['platform'] ?? '' );
		$chat_id  = (int) ( $ctx['chat_id'] ?? 0 );
		$parts    = is_array( $ctx['parts'] ?? null ) ? $ctx['parts'] : array();
		$user     = $ctx['user'] ?? null;
		if ( count( $parts ) < 3 || 'pnl' !== ( $parts[0] ?? '' ) || 'cat' !== ( $parts[1] ?? '' ) ) {
			return;
		}
		$op = (string) ( $parts[2] ?? '' );
		if ( 'l' === $op && isset( $parts[3], $parts[4] ) && $user ) {
			self::send_list( $platform, $chat_id, $user, (string) $parts[3], (int) $parts[4] );
			return;
		}
		if ( 't' === $op && isset( $parts[3], $parts[4] ) && $user ) {
			self::toggle_item( $platform, $chat_id, $user, (string) $parts[3], (int) $parts[4] );
			return;
		}
		if ( 'd' === $op && isset( $parts[3], $parts[4] ) && $user ) {
			self::confirm_delete( $platform, $chat_id, $user, (string) $parts[3], (int) $parts[4] );
			return;
		}
		if ( 'dy' === $op && isset( $parts[3], $parts[4] ) && $user ) {
			self::delete_item( $platform, $chat_id, $user, (string) $parts[3], (int) $parts[4] );
			return;
		}
		if ( 'n' === $op && isset( $parts[3] ) && $user && class_exists( 'SimpleVPBot_Handler_Admin_Settings' ) ) {
			SimpleVPBot_Handler_Admin_Settings::start_catalog_wizard(
				array(
					'platform' => $platform,
					'chat_id'  => $chat_id,
					'user'     => $user,
				),
				self::wizard_code_from_ent( (string) $parts[3] )
			);
			return;
		}
		if ( 'e' === $op && isset( $parts[3], $parts[4] ) && $user ) {
			$ent = sanitize_key( (string) $parts[3] );
			$eid = (int) $parts[4];
			if ( 'pl' === $ent ) {
				self::start_plan_edit_wizard( $platform, $chat_id, $user, $eid );
			} elseif ( 'cd' === $ent ) {
				self::start_card_edit_wizard( $platform, $chat_id, $user, $eid );
			} elseif ( 'pc' === $ent ) {
				self::start_category_edit_wizard( $platform, $chat_id, $user, $eid );
			}
			return;
		}
	}

	/**
	 * Legacy pnl:dl/pl/pc/cd callbacks → mutate bridge (SEC-1).
	 *
	 * @param array<string, mixed> $ctx  Context.
	 * @param string               $op   t|d|dy.
	 * @param string               $ent  pl|pc|cd.
	 * @param int                  $id   Entity id.
	 */
	public static function dispatch_legacy( array $ctx, $op, $ent, $id ) {
		$op  = sanitize_key( (string) $op );
		$ent = sanitize_key( (string) $ent );
		if ( ! in_array( $op, array( 't', 'd', 'dy' ), true ) || ! in_array( $ent, array( 'pl', 'pc', 'cd' ), true ) ) {
			return;
		}
		$id = (int) $id;
		if ( $id < 1 ) {
			return;
		}
		self::handle_callback(
			array_merge(
				$ctx,
				array(
					'parts' => array( 'pnl', 'cat', $op, $ent, (string) $id ),
				)
			)
		);
	}

	/**
	 * @param string $ent pl|pc|cd.
	 * @return string pl|pc|cd wizard code.
	 */
	private static function wizard_code_from_ent( $ent ) {
		$ent = sanitize_key( (string) $ent );
		if ( 'pc' === $ent ) {
			return 'pc';
		}
		if ( 'cd' === $ent ) {
			return 'cd';
		}
		return 'pl';
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	public static function route_text( array $ctx ) {
		$user     = $ctx['user'];
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$text     = trim( (string) $ctx['text'] );
		if ( ! $user || empty( $user->id ) ) {
			return false;
		}
		$prev = SimpleVPBot_Texts::get_for_user( 'btn.admin.catalog_prev', $user );
		$next = SimpleVPBot_Texts::get_for_user( 'btn.admin.catalog_next', $user );
		if ( $text === $prev || $text === $next ) {
			$d    = ( 'admin_catalog_list' === (string) $user->state ) ? SimpleVPBot_State::data( $user ) : array();
			$kind = (string) ( $d['kind'] ?? 'plans' );
			$off  = isset( $d['offset'] ) ? (int) $d['offset'] : 0;
			if ( $text === $next ) {
				$off += self::PAGE_SIZE;
			} else {
				$off = max( 0, $off - self::PAGE_SIZE );
			}
			self::send_list( $platform, $chat_id, $user, $kind, $off );
			return true;
		}
		$create_map = array(
			SimpleVPBot_Texts::get_for_user( 'btn.admin.catalog_add_plan', $user, '➕ پلن' )     => 'pl',
			SimpleVPBot_Texts::get_for_user( 'btn.admin.catalog_add_card', $user, '➕ کارت' )     => 'cd',
			SimpleVPBot_Texts::get_for_user( 'btn.admin.catalog_add_category', $user, '➕ دسته' ) => 'pc',
		);
		if ( isset( $create_map[ $text ] ) && class_exists( 'SimpleVPBot_Handler_Admin_Settings' ) ) {
			SimpleVPBot_Handler_Admin_Settings::start_catalog_wizard( $ctx, $create_map[ $text ] );
			return true;
		}
		return false;
	}

	/**
	 * @param string $kind Kind.
	 * @param object $user User.
	 * @return string
	 */
	private static function header_for_kind( $kind, $user ) {
		if ( 'plans' === $kind ) {
			return SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.catalog.plans_header', $user );
		}
		if ( 'cards' === $kind ) {
			return SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.catalog.cards_header', $user );
		}
		return SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.catalog.plan_cats_header', $user );
	}

	/**
	 * @param string $kind     Kind.
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @return array<int, object>
	 */
	private static function rows_for_kind( $kind, $platform, $chat_id ) {
		if ( 'plans' === $kind ) {
			$list = class_exists( 'SimpleVPBot_Model_Plan' ) ? SimpleVPBot_Model_Plan::all_rows() : array();
			if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) ) {
				$list = SimpleVPBot_Feature_L2tp::filter_plans( (array) $list );
			}
			return class_exists( 'SimpleVPBot_Bot_Admin_Catalog_Scope' )
				? SimpleVPBot_Bot_Admin_Catalog_Scope::filter_plans( (array) $list, $platform, $chat_id )
				: (array) $list;
		}
		if ( 'cards' === $kind ) {
			$list = class_exists( 'SimpleVPBot_Model_Card' ) ? SimpleVPBot_Model_Card::all() : array();
			return class_exists( 'SimpleVPBot_Bot_Admin_Catalog_Scope' )
				? SimpleVPBot_Bot_Admin_Catalog_Scope::filter_cards( (array) $list, $platform, $chat_id )
				: (array) $list;
		}
		$list = class_exists( 'SimpleVPBot_Model_Plan_Category' ) ? SimpleVPBot_Model_Plan_Category::all_ordered() : array();
		return class_exists( 'SimpleVPBot_Bot_Admin_Catalog_Scope' )
			? SimpleVPBot_Bot_Admin_Catalog_Scope::filter_categories( (array) $list, $platform, $chat_id )
			: (array) $list;
	}

	/**
	 * @param object $row  Row.
	 * @param string $kind Kind.
	 * @return string
	 */
	private static function row_label( $row, $kind ) {
		if ( 'cards' === $kind && class_exists( 'SimpleVPBot_Model_Card' ) ) {
			return mb_substr( SimpleVPBot_Model_Card::method_label( $row ), 0, 24 );
		}
		return mb_substr( (string) ( $row->name ?? $row->title ?? $row->label ?? $row->code ?? '' ), 0, 24 );
	}

	/**
	 * @param string             $kind   Kind.
	 * @param array<int, object>   $slice  Page slice.
	 * @param int                  $off    Offset.
	 * @param int                  $total  Total.
	 * @param int                  $lim    Limit.
	 * @param object               $user   User.
	 * @return array<int, array<int, array<string, string>>>
	 */
	private static function list_inline_keyboard( $kind, array $slice, $off, $total, $lim, $user ) {
		$ent = 'plans' === $kind ? 'pl' : ( 'cards' === $kind ? 'cd' : 'pc' );
		$rows = array();
		foreach ( $slice as $row ) {
			if ( ! is_object( $row ) ) {
				continue;
			}
			$id = (int) ( $row->id ?? 0 );
			if ( $id < 1 ) {
				continue;
			}
			$on = ! empty( $row->active ) ? '⏸' : '✅';
			$row_btns = array(
				array(
					'text'          => $on . ' #' . $id,
					'callback_data' => 'pnl:cat:t:' . $ent . ':' . $id,
				),
			);
			if ( 'pl' === $ent ) {
				$row_btns[] = array(
					'text'          => '✏️ #' . $id,
					'callback_data' => 'pnl:cat:e:pl:' . $id,
				);
			} else {
				$row_btns[] = array(
					'text'          => '✏️ #' . $id,
					'callback_data' => 'pnl:cat:e:' . $ent . ':' . $id,
				);
			}
			$row_btns[] = array(
				'text'          => '🗑 #' . $id,
				'callback_data' => 'pnl:cat:d:' . $ent . ':' . $id,
			);
			$rows[] = $row_btns;
		}
		$nav = array();
		if ( $off > 0 ) {
			$nav[] = array(
				'text'          => '◀',
				'callback_data' => 'pnl:cat:l:' . $kind . ':' . max( 0, $off - $lim ),
			);
		}
		$nav[] = array(
			'text'          => '➕',
			'callback_data' => 'pnl:cat:n:' . $ent,
		);
		if ( $total > $off + $lim ) {
			$nav[] = array(
				'text'          => '▶',
				'callback_data' => 'pnl:cat:l:' . $kind . ':' . ( $off + $lim ),
			);
		}
		if ( $nav ) {
			$rows[] = $nav;
		}
		return $rows;
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 * @param string $ent      pl|pc|cd.
	 * @param int    $id       Entity id.
	 */
	private static function toggle_item( $platform, $chat_id, $user, $ent, $id ) {
		$id = (int) $id;
		if ( $id < 1 || ! class_exists( 'SimpleVPBot_Bot_Admin_Mutate' ) ) {
			return;
		}
		if ( 'cd' === $ent && class_exists( 'SimpleVPBot_Bot_Admin_Catalog_Scope' )
			&& ! SimpleVPBot_Bot_Admin_Catalog_Scope::guard_card( $platform, $chat_id, $id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
			return;
		}
		if ( 'pl' === $ent && class_exists( 'SimpleVPBot_Bot_Admin_Catalog_Scope' )
			&& ! SimpleVPBot_Bot_Admin_Catalog_Scope::guard_plan( $platform, $chat_id, $id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
			return;
		}
		if ( 'pc' === $ent && class_exists( 'SimpleVPBot_Bot_Admin_Catalog_Scope' )
			&& ! SimpleVPBot_Bot_Admin_Catalog_Scope::guard_category( $platform, $chat_id, $id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
			return;
		}
		$result = null;
		if ( 'pl' === $ent ) {
			$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
				(int) $user->id,
				'plan',
				array(
					'plan_action' => 'toggle',
					'plan_id'     => $id,
				)
			);
		} elseif ( 'pc' === $ent ) {
			$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
				(int) $user->id,
				'plan_category',
				array(
					'pc_action' => 'toggle',
					'pc_id'     => $id,
				)
			);
		} elseif ( 'cd' === $ent ) {
			$card = SimpleVPBot_Model_Card::find( $id );
			if ( ! $card ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.mutate.not_found', $user ) );
				return;
			}
			$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
				(int) $user->id,
				'card_update',
				array(
					'edit_id'      => $id,
					'card_number'  => (string) ( $card->card_number ?? '' ),
					'holder_name'  => (string) ( $card->holder_name ?? '' ),
					'bank_name'    => (string) ( $card->bank_name ?? '' ),
					'method_key'   => (string) ( $card->method_key ?? '' ),
					'daily_limit'  => (string) ( $card->daily_limit ?? '' ),
					'note'         => (string) ( $card->note ?? '' ),
					'active'       => empty( $card->active ) ? 1 : 0,
				)
			);
		}
		$msg  = SimpleVPBot_Bot_Admin_Mutate::result_message( $user, is_array( $result ) ? $result : array( 'ok' => false ) );
		$kind = 'pl' === $ent ? 'plans' : ( 'pc' === $ent ? 'plan_cats' : 'cards' );
		$d    = SimpleVPBot_State::data( $user );
		$off  = ( is_array( $d ) && isset( $d['kind'] ) && $d['kind'] === $kind && isset( $d['offset'] ) )
			? (int) $d['offset']
			: 0;
		self::send_list( $platform, $chat_id, $user, $kind, $off, $msg );
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 * @param string $ent      pl|pc|cd.
	 * @param int    $id       Entity id.
	 */
	private static function confirm_delete( $platform, $chat_id, $user, $ent, $id ) {
		$id = (int) $id;
		if ( $id < 1 ) {
			return;
		}
		if ( class_exists( 'SimpleVPBot_Bot_Admin_Catalog_Scope' ) ) {
			$ok = 'cd' === $ent
				? SimpleVPBot_Bot_Admin_Catalog_Scope::guard_card( $platform, $chat_id, $id )
				: ( 'pl' === $ent
					? SimpleVPBot_Bot_Admin_Catalog_Scope::guard_plan( $platform, $chat_id, $id )
					: SimpleVPBot_Bot_Admin_Catalog_Scope::guard_category( $platform, $chat_id, $id ) );
			if ( ! $ok ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
				return;
			}
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.catalog.delete_confirm', $user, array( 'id' => (string) $id ) ),
			array(
				'reply_markup' => array(
					'inline_keyboard' => array(
						array(
							array(
								'text'          => SimpleVPBot_Texts::get_for_user( 'btn.admin.confirm_yes', $user, '✅ بله' ),
								'callback_data' => 'pnl:cat:dy:' . $ent . ':' . $id,
							),
							array(
								'text'          => SimpleVPBot_Texts::get_for_user( 'btn.admin.confirm_no', $user, '❌ خیر' ),
								'callback_data' => 'noop',
							),
						),
					),
				),
			)
		);
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 * @param string $ent      pl|pc|cd.
	 * @param int    $id       Entity id.
	 */
	private static function delete_item( $platform, $chat_id, $user, $ent, $id ) {
		$id = (int) $id;
		if ( $id < 1 || ! class_exists( 'SimpleVPBot_Bot_Admin_Mutate' ) ) {
			return;
		}
		if ( 'cd' === $ent && class_exists( 'SimpleVPBot_Bot_Admin_Catalog_Scope' )
			&& ! SimpleVPBot_Bot_Admin_Catalog_Scope::guard_card( $platform, $chat_id, $id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
			return;
		}
		if ( 'pl' === $ent && class_exists( 'SimpleVPBot_Bot_Admin_Catalog_Scope' )
			&& ! SimpleVPBot_Bot_Admin_Catalog_Scope::guard_plan( $platform, $chat_id, $id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
			return;
		}
		if ( 'pc' === $ent && class_exists( 'SimpleVPBot_Bot_Admin_Catalog_Scope' )
			&& ! SimpleVPBot_Bot_Admin_Catalog_Scope::guard_category( $platform, $chat_id, $id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
			return;
		}
		$result = null;
		if ( 'pl' === $ent ) {
			$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
				(int) $user->id,
				'plan',
				array(
					'plan_action' => 'delete',
					'plan_id'     => $id,
				)
			);
		} elseif ( 'pc' === $ent ) {
			$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
				(int) $user->id,
				'plan_category',
				array(
					'pc_action' => 'delete',
					'pc_id'     => $id,
				)
			);
		} elseif ( 'cd' === $ent ) {
			$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
				(int) $user->id,
				'card_delete',
				array( 'edit_id' => $id )
			);
		}
		$msg  = SimpleVPBot_Bot_Admin_Mutate::result_message( $user, is_array( $result ) ? $result : array( 'ok' => false ) );
		$kind = 'pl' === $ent ? 'plans' : ( 'pc' === $ent ? 'plan_cats' : 'cards' );
		$d    = SimpleVPBot_State::data( $user );
		$off  = ( is_array( $d ) && isset( $d['kind'] ) && $d['kind'] === $kind && isset( $d['offset'] ) )
			? (int) $d['offset']
			: 0;
		self::send_list( $platform, $chat_id, $user, $kind, $off, $msg );
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 * @param int    $plan_id  Plan id.
	 */
	private static function start_plan_edit_wizard( $platform, $chat_id, $user, $plan_id ) {
		$plan_id = (int) $plan_id;
		if ( $plan_id < 1 || ! class_exists( 'SimpleVPBot_Model_Plan' ) ) {
			return;
		}
		if ( class_exists( 'SimpleVPBot_Bot_Admin_Catalog_Scope' )
			&& ! SimpleVPBot_Bot_Admin_Catalog_Scope::guard_plan( $platform, $chat_id, $plan_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
			return;
		}
		$row = SimpleVPBot_Model_Plan::find( $plan_id );
		if ( ! $row ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.mutate.not_found', $user ) );
			return;
		}
		SimpleVPBot_State::set(
			(int) $user->id,
			'admin_catalog_plan_edit',
			array(
				'plan_id' => $plan_id,
			)
		);
		$hint = SimpleVPBot_Bot_Admin_Texts::msg(
			'msg.admin.prompt_catalog_plan_edit',
			$user,
			array(
				'id'            => (string) $plan_id,
				'name'          => (string) ( $row->name ?? '' ),
				'category'      => (string) ( $row->category ?? 'normal' ),
				'duration_days' => (string) (int) ( $row->duration_days ?? 0 ),
				'traffic_gb'    => (string) (int) ( $row->traffic_gb ?? 0 ),
				'price'         => (string) (float) ( $row->price ?? 0 ),
				'inbound_id'    => (string) (int) ( $row->inbound_id ?? 0 ),
				'clients_count' => (string) max( 1, (int) ( $row->clients_count ?? 1 ) ),
				'active'        => ! empty( $row->active ) ? '1' : '0',
			)
		);
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $hint );
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 * @param int    $card_id  Card id.
	 */
	private static function start_card_edit_wizard( $platform, $chat_id, $user, $card_id ) {
		$card_id = (int) $card_id;
		if ( $card_id < 1 || ! class_exists( 'SimpleVPBot_Model_Card' ) ) {
			return;
		}
		if ( class_exists( 'SimpleVPBot_Bot_Admin_Catalog_Scope' )
			&& ! SimpleVPBot_Bot_Admin_Catalog_Scope::guard_card( $platform, $chat_id, $card_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
			return;
		}
		$row = SimpleVPBot_Model_Card::find( $card_id );
		if ( ! $row ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.mutate.not_found', $user ) );
			return;
		}
		SimpleVPBot_State::set( (int) $user->id, 'admin_catalog_card_edit', array( 'card_id' => $card_id ) );
		$hint = SimpleVPBot_Bot_Admin_Texts::msg(
			'msg.admin.prompt_catalog_card_edit',
			$user,
			array(
				'id'          => (string) $card_id,
				'card_number' => (string) ( $row->card_number ?? '' ),
				'holder_name' => (string) ( $row->holder_name ?? '' ),
				'bank_name'   => (string) ( $row->bank_name ?? '' ),
				'method_key'  => (string) ( $row->method_key ?? 'c2c' ),
				'daily_limit' => (string) ( $row->daily_limit ?? '0' ),
				'priority'    => (string) (int) ( $row->priority ?? 0 ),
				'note'        => (string) ( $row->note ?? '' ),
				'active'      => ! empty( $row->active ) ? '1' : '0',
			)
		);
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $hint );
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 * @param int    $cat_id   Category id.
	 */
	private static function start_category_edit_wizard( $platform, $chat_id, $user, $cat_id ) {
		$cat_id = (int) $cat_id;
		if ( $cat_id < 1 || ! class_exists( 'SimpleVPBot_Model_Plan_Category' ) ) {
			return;
		}
		if ( class_exists( 'SimpleVPBot_Bot_Admin_Catalog_Scope' )
			&& ! SimpleVPBot_Bot_Admin_Catalog_Scope::guard_category( $platform, $chat_id, $cat_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
			return;
		}
		$row = SimpleVPBot_Model_Plan_Category::find( $cat_id );
		if ( ! $row ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.mutate.not_found', $user ) );
			return;
		}
		SimpleVPBot_State::set( (int) $user->id, 'admin_catalog_category_edit', array( 'pc_id' => $cat_id ) );
		$hint = SimpleVPBot_Bot_Admin_Texts::msg(
			'msg.admin.prompt_catalog_category_edit',
			$user,
			array(
				'id'         => (string) $cat_id,
				'slug'       => (string) ( $row->slug ?? '' ),
				'label'      => (string) ( $row->label ?? '' ),
				'sort_order' => (string) (int) ( $row->sort_order ?? 0 ),
				'active'     => ! empty( $row->active ) ? '1' : '0',
			)
		);
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $hint );
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	public static function route_state( array $ctx ) {
		$user = $ctx['user'];
		if ( ! $user ) {
			return false;
		}
		$st = (string) $user->state;
		if ( 'admin_catalog_plan_edit' === $st ) {
			return self::route_plan_edit_state( $ctx );
		}
		if ( 'admin_catalog_card_edit' === $st ) {
			return self::route_card_edit_state( $ctx );
		}
		if ( 'admin_catalog_category_edit' === $st ) {
			return self::route_category_edit_state( $ctx );
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	private static function route_plan_edit_state( array $ctx ) {
		$user = $ctx['user'];
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$text     = trim( (string) $ctx['text'] );
		$data     = SimpleVPBot_State::get( (int) $user->id );
		$data     = is_array( $data ) ? $data : array();
		$plan_id  = (int) ( $data['plan_id'] ?? 0 );
		if ( $plan_id < 1 || ! class_exists( 'SimpleVPBot_Model_Plan' ) || ! class_exists( 'SimpleVPBot_Bot_Admin_Mutate' ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			return true;
		}
		if ( class_exists( 'SimpleVPBot_Bot_Admin_Catalog_Scope' )
			&& ! SimpleVPBot_Bot_Admin_Catalog_Scope::guard_plan( $platform, $chat_id, $plan_id ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
			return true;
		}
		$existing = SimpleVPBot_Model_Plan::find( $plan_id );
		if ( ! $existing ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.mutate.not_found', $user ) );
			return true;
		}
		$lines = preg_split( '/\r\n|\r|\n/', $text );
		$lines = is_array( $lines ) ? array_values( array_filter( array_map( 'trim', $lines ), static function ( $l ) {
			return '' !== (string) $l;
		} ) ) : array();
		if ( count( $lines ) < 7 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.catalog.plan_lines', $user ) );
			return true;
		}
		$post = array(
			'plan_action'     => 'update',
			'plan_id'         => $plan_id,
			'name'            => (string) $lines[0],
			'category'        => (string) $lines[1],
			'duration_days'   => (int) SimpleVPBot_Bot_Runtime::normalize_digits( (string) $lines[2] ),
			'traffic_gb'      => (int) SimpleVPBot_Bot_Runtime::normalize_digits( (string) $lines[3] ),
			'price'           => (float) str_replace( ',', '.', (string) $lines[4] ),
			'inbound_id'      => (int) SimpleVPBot_Bot_Runtime::normalize_digits( (string) $lines[5] ),
			'clients_count'   => max( 1, (int) SimpleVPBot_Bot_Runtime::normalize_digits( (string) $lines[6] ) ),
			'plan_pricing_type' => (string) ( $existing->pricing_type ?? 'fixed' ),
			'pricing_type'    => (string) ( $existing->pricing_type ?? 'fixed' ),
			'service_type'    => (string) ( $existing->service_type ?? 'xray' ),
			'plan_panel_id'   => (int) ( $existing->panel_id ?? 1 ),
			'price_per_gb'    => (float) ( $existing->price_per_gb ?? 0 ),
			'traffic_gb_min'  => (int) ( $existing->traffic_gb_min ?? 0 ),
			'traffic_gb_max'  => (int) ( $existing->traffic_gb_max ?? 0 ),
			'l2tp_server_id'  => (int) ( $existing->l2tp_server_id ?? 0 ),
			'sort_order'      => (int) ( $existing->sort_order ?? 0 ),
			'plan_active'     => isset( $lines[7] ) ? ( (int) SimpleVPBot_Bot_Runtime::normalize_digits( (string) $lines[7] ) ? 1 : 0 ) : (int) ( $existing->active ?? 1 ),
		);
		$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user( (int) $user->id, 'plan', $post );
		SimpleVPBot_State::clear( (int) $user->id );
		$msg = SimpleVPBot_Bot_Admin_Mutate::result_message( $user, is_array( $result ) ? $result : array( 'ok' => false ) );
		if ( ! empty( $result['ok'] ) ) {
			self::send_list( $platform, $chat_id, $user, 'plans', 0, $msg );
		} else {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $msg );
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	private static function route_card_edit_state( array $ctx ) {
		$user     = $ctx['user'];
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$text     = trim( (string) $ctx['text'] );
		$data     = SimpleVPBot_State::get( (int) $user->id );
		$data     = is_array( $data ) ? $data : array();
		$card_id  = (int) ( $data['card_id'] ?? 0 );
		if ( $card_id < 1 || ! class_exists( 'SimpleVPBot_Model_Card' ) || ! class_exists( 'SimpleVPBot_Bot_Admin_Mutate' ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			return true;
		}
		if ( class_exists( 'SimpleVPBot_Bot_Admin_Catalog_Scope' )
			&& ! SimpleVPBot_Bot_Admin_Catalog_Scope::guard_card( $platform, $chat_id, $card_id ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
			return true;
		}
		$segs = array_map( 'trim', explode( '|', $text ) );
		if ( count( $segs ) < 6 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.catalog.card_lines', $user ) );
			return true;
		}
		$method = class_exists( 'SimpleVPBot_Service_Admin_Catalog' )
			? SimpleVPBot_Service_Admin_Catalog::sanitize_card_method_key( (string) $segs[3] )
			: sanitize_key( (string) $segs[3] );
		$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
			(int) $user->id,
			'card_update',
			array(
				'edit_id'     => $card_id,
				'card_number' => sanitize_text_field( (string) $segs[0] ),
				'holder_name' => sanitize_text_field( (string) $segs[1] ),
				'bank_name'   => sanitize_text_field( (string) $segs[2] ),
				'method_key'  => $method,
				'daily_limit' => (float) str_replace( ',', '.', (string) $segs[4] ),
				'priority'    => (int) $segs[5],
				'note'        => isset( $segs[6] ) ? sanitize_textarea_field( (string) $segs[6] ) : '',
				'active'      => isset( $segs[7] ) ? ( (int) SimpleVPBot_Bot_Runtime::normalize_digits( (string) $segs[7] ) ? 1 : 0 ) : 1,
			)
		);
		SimpleVPBot_State::clear( (int) $user->id );
		$msg = SimpleVPBot_Bot_Admin_Mutate::result_message( $user, is_array( $result ) ? $result : array( 'ok' => false ) );
		if ( ! empty( $result['ok'] ) ) {
			self::send_list( $platform, $chat_id, $user, 'cards', 0, $msg );
		} else {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $msg );
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	private static function route_category_edit_state( array $ctx ) {
		$user     = $ctx['user'];
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$text     = trim( (string) $ctx['text'] );
		$data     = SimpleVPBot_State::get( (int) $user->id );
		$data     = is_array( $data ) ? $data : array();
		$pc_id    = (int) ( $data['pc_id'] ?? 0 );
		if ( $pc_id < 1 || ! class_exists( 'SimpleVPBot_Model_Plan_Category' ) || ! class_exists( 'SimpleVPBot_Bot_Admin_Mutate' ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			return true;
		}
		if ( class_exists( 'SimpleVPBot_Bot_Admin_Catalog_Scope' )
			&& ! SimpleVPBot_Bot_Admin_Catalog_Scope::guard_category( $platform, $chat_id, $pc_id ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
			return true;
		}
		$lines = preg_split( '/\r\n|\r|\n/', $text );
		$lines = is_array( $lines ) ? array_values( array_filter( array_map( 'trim', $lines ), static function ( $l ) {
			return '' !== (string) $l;
		} ) ) : array();
		if ( count( $lines ) < 2 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.catalog.category_lines', $user ) );
			return true;
		}
		$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
			(int) $user->id,
			'plan_category',
			array(
				'pc_action' => 'update',
				'pc_id'     => $pc_id,
				'pc_label'  => (string) $lines[0],
				'pc_sort'   => (int) SimpleVPBot_Bot_Runtime::normalize_digits( (string) $lines[1] ),
				'pc_active' => isset( $lines[2] ) ? ( (int) SimpleVPBot_Bot_Runtime::normalize_digits( (string) $lines[2] ) ? 1 : 0 ) : 1,
			)
		);
		SimpleVPBot_State::clear( (int) $user->id );
		$msg = SimpleVPBot_Bot_Admin_Mutate::result_message( $user, is_array( $result ) ? $result : array( 'ok' => false ) );
		if ( ! empty( $result['ok'] ) ) {
			self::send_list( $platform, $chat_id, $user, 'plan_cats', 0, $msg );
		} else {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $msg );
		}
		return true;
	}
}
