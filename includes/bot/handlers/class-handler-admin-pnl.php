<?php
/**
 * Admin hub: Reply keyboards + legacy pnl:* callbacks (deprecated).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Admin_Pnl
 */
class SimpleVPBot_Handler_Admin_Pnl {

	/**
	 * Reseller bot admin scope user ids (null = main bot, no filter).
	 *
	 * @return array<int, int>|null
	 */
	private static function bot_admin_scope_user_ids() {
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return null;
		}
		return SimpleVPBot_Bot_Reseller_Scope::bot_admin_scope_user_ids();
	}

	/**
	 * Set acting admin from chat for dual-role scope on main bot.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 */
	private static function bootstrap_scope_from_chat( $platform, $chat_id ) {
		$admin_u = self::resolve_admin_user_for_chat( $platform, $chat_id );
		if ( $admin_u && class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			SimpleVPBot_Bot_Reseller_Scope::set_acting_admin_user( (int) $admin_u->id );
		}
	}

	/**
	 * Deny when acting admin lacks permission for an operation.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param string $op       Operation key (Reseller_Permission_Gate).
	 * @return bool True when allowed.
	 */
	private static function bot_admin_guard_op( $platform, $chat_id, $op ) {
		$admin_u = self::resolve_admin_user_for_chat( $platform, $chat_id );
		if ( class_exists( 'SimpleVPBot_Bot_Admin_Guard' ) && $admin_u ) {
			SimpleVPBot_Bot_Reseller_Scope::set_acting_admin_user( (int) $admin_u->id );
		}
		if ( ! $admin_u || ! class_exists( 'SimpleVPBot_Bot_Admin_Guard' ) ) {
			return true;
		}
		if ( SimpleVPBot_Bot_Admin_Guard::may_call_op( $admin_u, $op ) ) {
			return true;
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			SimpleVPBot_Bot_Admin_Guard::denied_message( $admin_u )
		);
		return false;
	}

	/**
	 * Deny bot admin action when target user is outside reseller downline.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param int    $uid      Target user id.
	 * @return bool True when allowed.
	 */
	private static function bot_admin_guard_user( $platform, $chat_id, $uid ) {
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return true;
		}
		self::bootstrap_scope_from_chat( $platform, $chat_id );
		if ( SimpleVPBot_Bot_Reseller_Scope::bot_admin_may_moderate_user( (int) $uid ) ) {
			return true;
		}
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.user_not_found', $platform, $chat_id ) );
		return false;
	}

	/**
	 * Deny when service owner is outside reseller downline.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param int    $sid      Service id.
	 * @return bool True when allowed.
	 */
	private static function bot_admin_guard_service( $platform, $chat_id, $sid ) {
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return true;
		}
		self::bootstrap_scope_from_chat( $platform, $chat_id );
		if ( SimpleVPBot_Bot_Reseller_Scope::bot_admin_may_access_service( (int) $sid ) ) {
			return true;
		}
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.user_not_found', $platform, $chat_id ) );
		return false;
	}

	/**
	 * Deny when receipt user is outside reseller downline.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param int    $rid      Receipt id.
	 * @return bool True when allowed.
	 */
	private static function bot_admin_guard_receipt( $platform, $chat_id, $rid ) {
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return true;
		}
		self::bootstrap_scope_from_chat( $platform, $chat_id );
		if ( SimpleVPBot_Bot_Reseller_Scope::bot_admin_may_access_receipt( (int) $rid ) ) {
			return true;
		}
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.user_not_found', $platform, $chat_id ) );
		return false;
	}

	/**
	 * Block site-wide bulk on reseller bot.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @return bool True when blocked (caller should return).
	 */
	public static function bot_admin_deny_site_bulk( $platform, $chat_id ) {
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return false;
		}
		self::bootstrap_scope_from_chat( $platform, $chat_id );
		if ( ! SimpleVPBot_Bot_Reseller_Scope::bot_admin_site_bulk_blocked() ) {
			return false;
		}
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Reseller_Scope::bot_admin_site_bulk_denied_message() );
		return true;
	}

	/**
	 * POST context for catalog mutations from bot hub (reseller actor when applicable).
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @return array<string, mixed>
	 */
	private static function bot_admin_catalog_post_for_context( $platform = '', $chat_id = 0 ) {
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return array();
		}
		if ( '' !== (string) $platform && (int) $chat_id > 0 ) {
			self::bootstrap_scope_from_chat( $platform, $chat_id );
			$admin_u = self::resolve_admin_user_for_chat( $platform, $chat_id );
			if ( $admin_u && ! empty( $admin_u->id ) ) {
				return array( '__actor_svp_user_id' => (int) $admin_u->id );
			}
		}
		$rid = SimpleVPBot_Bot_Reseller_Scope::resolve_scope_reseller_id();
		return $rid > 0 ? array( '__actor_svp_user_id' => $rid ) : array();
	}

	/**
	 * Deny when card is outside reseller ownership.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param int    $card_id  Card id.
	 * @return bool True when allowed.
	 */
	private static function bot_admin_guard_card( $platform, $chat_id, $card_id ) {
		$cid = (int) $card_id;
		if ( $cid < 1 || ! class_exists( 'SimpleVPBot_Model_Card' ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.user_not_found', $platform, $chat_id ) );
			return false;
		}
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return true;
		}
		self::bootstrap_scope_from_chat( $platform, $chat_id );
		if ( ! SimpleVPBot_Bot_Reseller_Scope::is_scoped_bot_admin_context() ) {
			return true;
		}
		$card = SimpleVPBot_Model_Card::find( $cid );
		if ( ! $card ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.user_not_found', $platform, $chat_id ) );
			return false;
		}
		$rid   = SimpleVPBot_Bot_Reseller_Scope::resolve_scope_reseller_id();
		$owner = (int) ( $card->owner_svp_user_id ?? 0 );
		if ( $rid < 1 || $owner !== $rid ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.user_not_found', $platform, $chat_id ) );
			return false;
		}
		return true;
	}

	/**
	 * Deny when panel is outside reseller allowed list.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param int    $panel_id Panel id (0 = legacy OK on main bot).
	 * @return bool True when allowed.
	 */
	private static function bot_admin_guard_panel( $platform, $chat_id, $panel_id ) {
		$pid = (int) $panel_id;
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return true;
		}
		self::bootstrap_scope_from_chat( $platform, $chat_id );
		if ( ! SimpleVPBot_Bot_Reseller_Scope::is_scoped_bot_admin_context() ) {
			return true;
		}
		if ( $pid < 1 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.panel_inactive', $platform, $chat_id ) );
			return false;
		}
		$allowed = SimpleVPBot_Bot_Reseller_Scope::bot_admin_allowed_panel_ids();
		if ( is_array( $allowed ) && ! in_array( $pid, array_map( 'intval', $allowed ), true ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.panel_inactive', $platform, $chat_id ) );
			return false;
		}
		return true;
	}

	/**
	 * Block global L2TP server ops on reseller bot.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @return bool True when blocked.
	 */
	private static function bot_admin_deny_global_l2tp( $platform, $chat_id ) {
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return false;
		}
		return SimpleVPBot_Bot_Reseller_Scope::deny_global_settings_bot_action( $platform, $chat_id );
	}

	/**
	 * Deny reseller bot from global site settings / catalog wizards / logs / texts.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param int    $user_id  Admin svp user id (optional).
	 * @return bool True when blocked.
	 */
	private static function bot_admin_deny_reseller_global( $platform, $chat_id, $user_id = 0 ) {
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return false;
		}
		return SimpleVPBot_Bot_Reseller_Scope::deny_global_settings_bot_action( $platform, $chat_id, (int) $user_id );
	}

	/**
	 * Filter catalog rows to reseller bot visibility.
	 *
	 * @param array<int, object> $list Plan rows.
	 * @return array<int, object>
	 */
	private static function bot_admin_filter_plans_for_context( array $list, $platform = '', $chat_id = 0 ) {
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return $list;
		}
		if ( '' !== (string) $platform && (int) $chat_id > 0 ) {
			self::bootstrap_scope_from_chat( $platform, $chat_id );
		}
		if ( ! SimpleVPBot_Bot_Reseller_Scope::is_scoped_bot_admin_context() ) {
			return $list;
		}
		return array_values(
			array_filter(
				$list,
				static function ( $p ) {
					return SimpleVPBot_Bot_Reseller_Scope::plan_visible_in_context( $p );
				}
			)
		);
	}

	/**
	 * @param array<int, object> $list Category rows.
	 * @return array<int, object>
	 */
	private static function bot_admin_filter_categories_for_context( array $list, $platform = '', $chat_id = 0 ) {
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return $list;
		}
		if ( '' !== (string) $platform && (int) $chat_id > 0 ) {
			self::bootstrap_scope_from_chat( $platform, $chat_id );
		}
		if ( ! SimpleVPBot_Bot_Reseller_Scope::is_scoped_bot_admin_context() ) {
			return $list;
		}
		return array_values(
			array_filter(
				$list,
				static function ( $c ) {
					$pid = (int) ( $c->panel_id ?? 0 );
					return $pid < 1 || SimpleVPBot_Bot_Reseller_Scope::panel_allowed_in_context( $pid );
				}
			)
		);
	}

	/**
	 * @param array<int, object> $list Card rows.
	 * @return array<int, object>
	 */
	private static function bot_admin_filter_cards_for_context( array $list, $platform = '', $chat_id = 0 ) {
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return $list;
		}
		if ( '' !== (string) $platform && (int) $chat_id > 0 ) {
			self::bootstrap_scope_from_chat( $platform, $chat_id );
		}
		if ( ! SimpleVPBot_Bot_Reseller_Scope::is_scoped_bot_admin_context() ) {
			return $list;
		}
		$rid = SimpleVPBot_Bot_Reseller_Scope::resolve_scope_reseller_id();
		if ( $rid < 1 ) {
			return array();
		}
		return array_values(
			array_filter(
				$list,
				static function ( $c ) use ( $rid ) {
					return (int) ( $c->owner_svp_user_id ?? 0 ) === $rid;
				}
			)
		);
	}

	/**
	 * Delegate service callback after scope guard.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param int    $from_id  From id.
	 * @param int    $sid      Service id.
	 * @param string $action   Handler action code.
	 * @return bool True when dispatched.
	 */
	private static function bot_admin_delegate_service_callback( $platform, $chat_id, $from_id, $sid, $action ) {
		$sid = (int) $sid;
		if ( ! self::bot_admin_guard_op( $platform, $chat_id, 'service_manage' ) ) {
			return false;
		}
		if ( $sid < 1 || ! self::bot_admin_guard_service( $platform, $chat_id, $sid ) ) {
			return false;
		}
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc ) {
			return false;
		}
		$owner = SimpleVPBot_Model_User::find( (int) $svc->user_id );
		if ( ! $owner ) {
			return false;
		}
		SimpleVPBot_Handler_Service::handle_callback(
			array(
				'platform' => $platform,
				'user'     => $owner,
				'action'   => (string) $action,
				'svc_id'   => $sid,
				'chat_id'  => (int) $chat_id,
				'msg_id'   => 0,
				'from_id'  => (int) $from_id,
			)
		);
		return true;
	}

	/**
	 * Stats text for admin dashboard button (scoped on reseller bot).
	 *
	 * @param int $day_offset 0..7.
	 * @return string
	 */
	public static function admin_stats_text_for_chat( $day_offset, $admin_svp_user_id = 0 ) {
		return self::admin_stats_text_for_context( $day_offset, (int) $admin_svp_user_id );
	}

	/**
	 * Stats text scoped to reseller bot context when applicable.
	 *
	 * @param int $day_offset 0..7.
	 * @param int $admin_svp_user_id Acting admin svp_users.id (main bot dual-role).
	 * @return string
	 */
	private static function admin_stats_text_for_context( $day_offset, $admin_svp_user_id = 0 ) {
		if ( ! class_exists( 'SimpleVPBot_Admin_Dashboard_Stats' ) ) {
			return '';
		}
		$scope_rid = 0;
		if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			if ( $admin_svp_user_id > 0 ) {
				SimpleVPBot_Bot_Reseller_Scope::set_acting_admin_user( $admin_svp_user_id );
			}
			$scope_rid = SimpleVPBot_Bot_Reseller_Scope::resolve_scope_reseller_id();
		}
		if ( $scope_rid > 0 && class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			$scope  = SimpleVPBot_Bot_Reseller_Scope::effective_moderatable_user_ids( $scope_rid );
			$panels = SimpleVPBot_Bot_Reseller_Scope::allowed_panel_ids_for( $scope_rid );
			return SimpleVPBot_Admin_Dashboard_Stats::format_reseller_text(
				is_array( $scope ) ? $scope : array(),
				is_array( $panels ) ? $panels : array(),
				$day_offset
			);
		}
		return SimpleVPBot_Admin_Dashboard_Stats::format_text( $day_offset );
	}

	/**
	 * Send root hub message (reply or callback follow-up).
	 *
	 * @param string $platform telegram|bale.
	 * @param int    $chat_id Chat id.
	 */
	public static function send_hub( $platform, $chat_id ) {
		$admin_user = self::resolve_admin_user_for_chat( $platform, $chat_id );
		if ( class_exists( 'SimpleVPBot_Handler_Admin_Panel' ) && $admin_user ) {
			SimpleVPBot_Handler_Admin_Panel::send_landing( $platform, $chat_id, $admin_user );
			return;
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			SimpleVPBot_Texts::get_for_user( 'msg.admin.hub_menu', $admin_user ),
			array( 'reply_markup' => self::reply_markup_main_for_chat( $platform, $chat_id ) )
		);
	}

	/**
	 * Resolve svp_users row for admin chat (locale for hub messages).
	 *
	 * @param string $platform telegram|bale.
	 * @param int    $chat_id  Chat id.
	 * @return object|null
	 */
	private static function resolve_admin_user_for_chat( $platform, $chat_id ) {
		if ( ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return null;
		}
		return 'bale' === $platform
			? SimpleVPBot_Model_User::find_by_bale( (int) $chat_id )
			: SimpleVPBot_Model_User::find_by_telegram( (int) $chat_id );
	}

	/**
	 * Localized admin message for current chat locale.
	 *
	 * @param string               $key      Text key.
	 * @param string               $platform telegram|bale.
	 * @param int                  $chat_id  Admin chat id.
	 * @param array<string,string> $vars     Placeholders.
	 * @param string               $default  Fallback.
	 * @return string
	 */
	private static function admin_msg( $key, $platform, $chat_id, array $vars = array(), $default = '' ) {
		$u = self::resolve_admin_user_for_chat( $platform, $chat_id );
		$t = SimpleVPBot_Texts::get_for_user( $key, $u, $default );
		return empty( $vars ) ? $t : SimpleVPBot_Texts::format( $t, $vars );
	}

	/**
	 * Main admin Reply keyboard (hub + shortcuts + portal triggers).
	 *
	 * @param string $platform telegram|bale.
	 * @param int    $chat_id  Admin chat id.
	 * @return array<string, mixed>
	 */
	public static function reply_markup_main_for_chat( $platform, $chat_id ) {
		return SimpleVPBot_Keyboards::admin_main_reply_for_chat( $platform, $chat_id );
	}

	/**
	 * @deprecated Legacy inline hub; callbacks still answered with main Reply keyboard.
	 * @return array<string, mixed>
	 */
	public static function inline_hub_root_for_admin_chat( $platform, $chat_id ) {
		return self::reply_markup_main_for_chat( $platform, $chat_id );
	}

	/**
	 * @deprecated
	 * @return array<string, mixed>
	 */
	public static function inline_hub_root() {
		return array( 'inline_keyboard' => array() );
	}

	/**
	 * Show one user card: portal, block/unblock, then same service UI as the user (list or full menu).
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat id.
	 * @param int    $uid User id.
	 */
	public static function send_user_admin_card( $platform, $chat_id, $uid ) {
		SimpleVPBot_Handler_Admin_Users::send_user_admin_card( $platform, $chat_id, $uid );
	}

	/**
	 * Send pending receipts to admin with same caption/inline as live uploads; paginated.
	 *
	 * @param string $platform telegram|bale.
	 * @param int    $chat_id  Admin chat.
	 * @param int    $offset   Offset into pending list (oldest first).
	 */
	public static function send_pending_receipts_review_paged( $platform, $chat_id, $offset = 0 ) {
		SimpleVPBot_Handler_Admin_Receipts::send_pending_review_paged( $platform, $chat_id, $offset );
	}

	/**
	 * One pending receipt: photo + caption like user upload notify, or text fallback.
	 *
	 * @param string $platform telegram|bale.
	 * @param int    $chat_id  Admin destination.
	 * @param object $rec      Receipt row.
	 */
	private static function send_one_pending_receipt_review( $platform, $chat_id, $rec ) {
		SimpleVPBot_Handler_Admin_Receipts::send_one_pending_review( $platform, $chat_id, $rec );
	}

	/**
	 * Route pnl:* callbacks (platform admin only; caller checks).
	 *
	 * @param array<string, mixed> $ctx platform, chat_id, parts (explode of callback_data).
	 */
	public static function handle( array $ctx ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$parts    = isset( $ctx['parts'] ) && is_array( $ctx['parts'] ) ? $ctx['parts'] : array();
		$sub      = isset( $parts[1] ) ? (string) $parts[1] : '';
		$user     = isset( $ctx['user'] ) ? $ctx['user'] : null;
		$uid      = $user && ! empty( $user->id ) ? (int) $user->id : 0;

		if ( in_array( $sub, array( 'cat', 'pick' ), true ) ) {
			return;
		}

		if ( class_exists( 'SimpleVPBot_Bot_Admin_Guard' ) ) {
			SimpleVPBot_Bot_Admin_Guard::bootstrap_acting_admin_from_ctx( $ctx );
		}

		if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) && SimpleVPBot_Bot_Reseller_Scope::reseller_blocks_global_settings() ) {
			$blocked_cb = array( 'bk', 'crx', 'sw', 'op', 'wz', 'w', 'lg', 'th', 'tv', 'tx' );
			if ( in_array( $sub, $blocked_cb, true )
				&& SimpleVPBot_Bot_Reseller_Scope::deny_global_settings_bot_action( $platform, $chat_id, $uid ) ) {
				return;
			}
		}

		if ( 'h' === $sub ) {
			self::send_hub( $platform, $chat_id );
			return;
		}
		if ( 'svc_del' === $sub && isset( $parts[2] ) ) {
			$sid = (int) $parts[2];
			if ( $sid < 1 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.service_id_invalid', $platform, $chat_id ) );
				return;
			}
			if ( ! self::bot_admin_guard_op( $platform, $chat_id, 'service_manage' ) ) {
				return;
			}
			$svc = SimpleVPBot_Model_Service::find( $sid );
			if ( ! $svc ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.service_soft_delete_fail', $platform, $chat_id ) );
				return;
			}
			if ( ! self::bot_admin_guard_service( $platform, $chat_id, $sid ) ) {
				return;
			}
			$owner_uid = (int) $svc->user_id;
			$em        = (string) ( $svc->email ?? '' );
			$ok        = SimpleVPBot_Model_Service::soft_delete( $sid );
			if ( $ok ) {
				if ( class_exists( 'SimpleVPBot_User_Activity_Log' ) && ! empty( $ctx['user'] ) && is_object( $ctx['user'] ) ) {
					$actor = (int) $ctx['user']->id;
					$ch    = 'telegram' === $platform ? 'telegram' : 'bale';
					SimpleVPBot_User_Activity_Log::append(
						array(
							'subject_svp_user_id' => $owner_uid > 0 ? $owner_uid : 0,
							'channel'             => $ch,
							'actor_kind'          => 'svp_user',
							'actor_wp_user_id'    => 0,
							'actor_svp_user_id'   => $actor,
							'platform_chat_id'    => (int) $chat_id,
							'event_type'          => 'service_soft_delete',
							'payload'             => array(
								'service_id' => $sid,
								'email'      => $em,
								'source'     => 'admin_bot_callback',
							),
						)
					);
				}
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					self::admin_msg( 'msg.admin.service_soft_deleted_ok', $platform, $chat_id, array( 'id' => $sid ) )
				);
			} else {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.soft_delete_fail', $platform, $chat_id ) );
			}
			return;
		}
		if ( 'st' === $sub && isset( $parts[2] ) && class_exists( 'SimpleVPBot_Admin_Dashboard_Stats' ) ) {
			$off    = max( 0, min( 7, (int) $parts[2] ) );
			$msg_id = isset( $ctx['msg_id'] ) ? (int) $ctx['msg_id'] : 0;
			$text   = self::admin_stats_text_for_context( $off );
			$mk     = SimpleVPBot_Admin_Dashboard_Stats::inline_day_picker( $off );
			if ( $msg_id > 0 ) {
				$res = SimpleVPBot_Bot_Runtime::edit_message_text(
					$platform,
					$chat_id,
					$msg_id,
					$text,
					array( 'reply_markup' => $mk )
				);
				if ( ! is_array( $res ) || empty( $res['ok'] ) ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $text, array( 'reply_markup' => $mk ) );
				}
			} else {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $text, array( 'reply_markup' => $mk ) );
			}
			return;
		}
		if ( 'bdy' === $sub && isset( $parts[2] ) ) {
			if ( class_exists( 'SimpleVPBot_Handler_Admin_Bulk' ) ) {
				SimpleVPBot_Handler_Admin_Bulk::execute_extend_days( $platform, $chat_id, (int) $parts[2] );
			}
			return;
		}
		if ( 'bd' === $sub && isset( $parts[2] ) ) {
			self::send_bulk_days_confirm( $platform, $chat_id, max( 1, (int) $parts[2] ) );
			return;
		}
		if ( 'bgy' === $sub && isset( $parts[2] ) ) {
			if ( class_exists( 'SimpleVPBot_Handler_Admin_Bulk' ) ) {
				SimpleVPBot_Handler_Admin_Bulk::execute_add_volume( $platform, $chat_id, (int) $parts[2] );
			}
			return;
		}
		if ( 'bg' === $sub && isset( $parts[2] ) ) {
			self::send_bulk_gb_confirm( $platform, $chat_id, max( 1, (int) $parts[2] ) );
			return;
		}
		if ( 'ua' === $sub ) {
			$off = isset( $parts[2] ) ? (int) $parts[2] : 0;
			$mid = isset( $ctx['msg_id'] ) ? (int) $ctx['msg_id'] : 0;
			self::send_approved_users_page( $platform, $chat_id, $off, $mid );
			return;
		}
		if ( 'hcs' === $sub && isset( $parts[2] ) && ! empty( $ctx['user'] ) ) {
			if ( ! self::bot_admin_guard_op( $platform, $chat_id, 'service_manage' ) ) {
				return;
			}
			$tuid = (int) $parts[2];
			if ( ! self::bot_admin_guard_user( $platform, $chat_id, $tuid ) ) {
				return;
			}
			SimpleVPBot_State::clear( (int) $ctx['user']->id );
			self::send_admin_create_service_plan_picker( $platform, $chat_id, $tuid );
			return;
		}
		if ( 'nsp' === $sub && isset( $parts[2], $parts[3] ) && ! empty( $ctx['user'] ) ) {
			self::handle_admin_create_service_plan_pick( $ctx, (int) $parts[2], (int) $parts[3] );
			return;
		}
		if ( 'nsx' === $sub && isset( $parts[2], $parts[3], $parts[4] ) && ! empty( $ctx['user'] ) ) {
			self::handle_admin_create_service_execute(
				$ctx,
				(int) $parts[2],
				(int) $parts[3],
				null,
				strtolower( (string) $parts[4] )
			);
			return;
		}
		if ( 'nsm' === $sub && isset( $parts[2], $parts[3], $parts[4], $parts[5] ) && ! empty( $ctx['user'] ) ) {
			self::handle_admin_create_service_execute(
				$ctx,
				(int) $parts[2],
				(int) $parts[3],
				(int) $parts[4],
				strtolower( (string) $parts[5] )
			);
			return;
		}
		if ( 'nrr' === $sub && isset( $parts[2], $parts[3] ) && ! empty( $ctx['user'] ) ) {
			self::handle_admin_service_payment_execute( $ctx, 'renew', (int) $parts[2], null, strtolower( (string) $parts[3] ) );
			return;
		}
		if ( 'nva' === $sub && isset( $parts[2], $parts[3], $parts[4] ) && ! empty( $ctx['user'] ) ) {
			self::handle_admin_service_payment_execute( $ctx, 'vol', (int) $parts[2], (int) $parts[3], strtolower( (string) $parts[4] ) );
			return;
		}
		if ( 'nus' === $sub && isset( $parts[2], $parts[3], $parts[4] ) && ! empty( $ctx['user'] ) ) {
			self::handle_admin_service_payment_execute( $ctx, 'slots', (int) $parts[2], (int) $parts[3], strtolower( (string) $parts[4] ) );
			return;
		}
		if ( 'wbp' === $sub && isset( $parts[2] ) && ! empty( $ctx['user'] ) ) {
			$tuid = (int) $parts[2];
			if ( $tuid > 0 ) {
				if ( ! self::bot_admin_guard_op( $platform, $chat_id, 'user_search' ) ) {
					return;
				}
				if ( ! self::bot_admin_guard_user( $platform, $chat_id, $tuid ) ) {
					return;
				}
				SimpleVPBot_State::set( (int) $ctx['user']->id, 'admin_w_balance', array( 'target_uid' => $tuid, 'sign' => 1 ) );
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					self::admin_msg( 'msg.admin.prompt_wallet_credit', $platform, $chat_id, array( 'id' => $tuid ) )
				);
			}
			return;
		}
		if ( 'wbm' === $sub && isset( $parts[2] ) && ! empty( $ctx['user'] ) ) {
			$tuid = (int) $parts[2];
			if ( $tuid > 0 ) {
				if ( ! self::bot_admin_guard_op( $platform, $chat_id, 'user_search' ) ) {
					return;
				}
				if ( ! self::bot_admin_guard_user( $platform, $chat_id, $tuid ) ) {
					return;
				}
				SimpleVPBot_State::set( (int) $ctx['user']->id, 'admin_w_balance', array( 'target_uid' => $tuid, 'sign' => -1 ) );
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					self::admin_msg( 'msg.admin.prompt_wallet_debit', $platform, $chat_id, array( 'id' => $tuid ) )
				);
			}
			return;
		}
		if ( 'ar' === $sub && isset( $parts[2] ) && ! empty( $ctx['user'] ) ) {
			if ( ! self::bot_admin_guard_op( $platform, $chat_id, 'service_manage' ) ) {
				return;
			}
			$sid = (int) $parts[2];
			if ( ! self::bot_admin_guard_service( $platform, $chat_id, $sid ) ) {
				return;
			}
			SimpleVPBot_State::set( (int) $ctx['user']->id, 'admin_line_nr', array( 'service_id' => $sid ) );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.prompt_renew_line', $platform, $chat_id, array( 'id' => $sid ) ) );
			return;
		}
		if ( 'av' === $sub && isset( $parts[2] ) && ! empty( $ctx['user'] ) ) {
			if ( ! self::bot_admin_guard_op( $platform, $chat_id, 'service_manage' ) ) {
				return;
			}
			$sid = (int) $parts[2];
			if ( ! self::bot_admin_guard_service( $platform, $chat_id, $sid ) ) {
				return;
			}
			SimpleVPBot_State::set( (int) $ctx['user']->id, 'admin_line_nv', array( 'service_id' => $sid ) );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.prompt_add_volume_line', $platform, $chat_id, array( 'id' => $sid ) ), array( 'parse_mode' => 'HTML' ) );
			return;
		}
		if ( 'hcb' === $sub && ! empty( $ctx['user'] ) ) {
			if ( self::bot_admin_deny_site_bulk( $platform, $chat_id ) ) {
				return;
			}
			SimpleVPBot_State::set( (int) $ctx['user']->id, 'admin_line_bl', array() );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				self::admin_msg( 'msg.admin.prompt_bulk_xray', $platform, $chat_id ),
				array( 'parse_mode' => 'HTML' )
			);
			return;
		}
		if ( 'crx' === $sub ) {
			$all = SimpleVPBot_Settings::all();
			$all['crypto_ipn_path_secret'] = wp_generate_password( 32, false, false );
			SimpleVPBot_Settings::update( $all );
			SimpleVPBot_Texts::clear_cache();
			$uipn = SimpleVPBot_Crypto_Payment::ipn_callback_url();
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				self::admin_msg( 'msg.admin.ipn_saved', $platform, $chat_id ) . ( $uipn ? self::admin_msg( 'msg.admin.ipn_link_line', $platform, $chat_id, array( 'url' => $uipn ) ) : '' ),
				array( 'reply_markup' => self::inline_hub_root_for_admin_chat( $platform, $chat_id ) )
			);
			return;
		}
		if ( 'l2' === $sub && isset( $parts[2], $parts[3] ) ) {
			if ( self::bot_admin_deny_global_l2tp( $platform, $chat_id ) ) {
				return;
			}
			$act = (string) $parts[2];
			$lid = (int) $parts[3];
			if ( 'g' === $act && $lid > 0 ) {
				$row = SimpleVPBot_Model_L2TP_Server::find( $lid );
				if ( $row ) {
					$new = empty( $row->active ) ? 1 : 0;
					SimpleVPBot_Model_L2TP_Server::update( $lid, array( 'active' => $new ) );
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.server_active', $platform, $chat_id, array( 'id' => $lid, 'state' => $new ) ) );
				}
				return;
			}
			if ( 'd' === $act && $lid > 0 ) {
				SimpleVPBot_Model_L2TP_Server::delete( $lid );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.server_deleted', $platform, $chat_id, array( 'id' => $lid ) ) );
				return;
			}
		}
		if ( 'bk' === $sub ) {
			if ( class_exists( 'SimpleVPBot_Handler_Admin_Backup' ) ) {
				SimpleVPBot_Handler_Admin_Backup::handle_callback( $ctx, $parts );
			}
			return;
		}
		if ( 'op' === $sub && isset( $parts[2] ) ) {
			SimpleVPBot_Handler_Admin_Settings::handle_op( $ctx, (string) $parts[2] );
			return;
		}
		if ( 'wz' === $sub && isset( $parts[2], $parts[3] ) ) {
			SimpleVPBot_Handler_Admin_Settings::start_wizard( $ctx, (string) $parts[2], (string) $parts[3] );
			return;
		}
		if ( 'w' === $sub && isset( $parts[2] ) ) {
			SimpleVPBot_Handler_Admin_Settings::start_catalog_wizard( $ctx, (string) $parts[2] );
			return;
		}
		if ( 'pe' === $sub && isset( $parts[2], $parts[3] ) ) {
			$act = (string) $parts[2];
			$uid = (int) $parts[3];
			if ( $uid > 0 && in_array( $act, array( 'a', 'r' ), true ) && class_exists( 'SimpleVPBot_Handler_Callback' ) ) {
				$from = isset( $ctx['from'] ) && is_array( $ctx['from'] ) ? $ctx['from'] : array();
				if ( empty( $from['id'] ) && isset( $ctx['from_id'] ) ) {
					$from['id'] = (int) $ctx['from_id'];
				}
				SimpleVPBot_Handler_Callback::admin_apply_registration( $platform, $act, $uid, $from, $chat_id );
			}
			return;
		}
		if ( 'up' === $sub && 'n' === ( $parts[2] ?? '' ) && isset( $parts[3] ) ) {
			$mid = isset( $ctx['msg_id'] ) ? (int) $ctx['msg_id'] : 0;
			self::send_pending_users_page( $platform, $chat_id, (int) $parts[3], $mid );
			return;
		}
		if ( 'pq' === $sub && isset( $parts[2] ) ) {
			$mid = isset( $ctx['msg_id'] ) ? (int) $ctx['msg_id'] : 0;
			self::send_pending_users_page( $platform, $chat_id, (int) $parts[2], $mid );
			return;
		}
		if ( 'aq' === $sub && isset( $parts[2] ) ) {
			$mid = isset( $ctx['msg_id'] ) ? (int) $ctx['msg_id'] : 0;
			self::send_approved_users_page( $platform, $chat_id, (int) $parts[2], $mid );
			return;
		}
		if ( 'rq' === $sub && isset( $parts[2] ) ) {
			$mid = isset( $ctx['msg_id'] ) ? (int) $ctx['msg_id'] : 0;
			self::send_rejected_users_page( $platform, $chat_id, (int) $parts[2], $mid );
			return;
		}
		if ( 'ui' === $sub && isset( $parts[2] ) ) {
			self::send_user_admin_preview( $platform, $chat_id, (int) $parts[2] );
			return;
		}
		if ( 'rr' === $sub && isset( $parts[2] ) && class_exists( 'SimpleVPBot_User_Membership' ) ) {
			$uid = (int) $parts[2];
			if ( ! self::bot_admin_guard_user( $platform, $chat_id, $uid ) ) {
				return;
			}
			$r   = SimpleVPBot_User_Membership::reopen_rejected_to_pending( $uid );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				! empty( $r['ok'] ) ? self::admin_msg( 'msg.admin.user_requeued', $platform, $chat_id, array( 'id' => $uid ) ) : self::admin_msg( 'msg.admin.requeue_failed', $platform, $chat_id, array( 'reason' => (string) ( $r['reason'] ?? '—' ) ) )
			);
			return;
		}
		if ( 'lg' === $sub && isset( $parts[2] ) ) {
			self::send_logs_page( $platform, $chat_id, (int) $parts[2] );
			return;
		}
		if ( 'ib' === $sub && isset( $parts[2] ) ) {
			$op = (string) $parts[2];
			if ( 'p' === $op && isset( $parts[3] ) ) {
				self::send_inbounds_list_for_panel( $platform, $chat_id, $ctx, max( 1, (int) $parts[3] ) );
			} elseif ( 'l' === $op ) {
				self::send_inbounds_list( $platform, $chat_id, $ctx );
			} elseif ( 'i' === $op && isset( $parts[3] ) ) {
				self::send_inbound_clients( $platform, $chat_id, (int) $parts[3], $ctx );
			} elseif ( 'k' === $op && isset( $parts[3] ) ) {
				$iid = (int) $parts[3];
				$pid = 1;
				if ( ! empty( $ctx['user'] ) && $ctx['user']->id ) {
					$ibx = get_transient( 'svp_ibctx_' . (int) $ctx['user']->id );
					if ( is_array( $ibx ) && isset( $ibx['panel_id'] ) ) {
						$pid = (int) $ibx['panel_id'];
						if ( $pid < 0 ) {
							$pid = 0;
						}
					}
				}
				if ( ! self::bot_admin_guard_panel( $platform, $chat_id, $pid ) ) {
					return;
				}
				$r   = SimpleVPBot_Service_Admin_Ops::inbound_autolink( $iid, $pid );
				$msg = ! empty( $r['ok'] ) ? ( '✅ ' . mb_substr( wp_json_encode( $r['data'] ?? array() ), 0, 3000 ) ) : ( '⛔ ' . (string) ( $r['message'] ?? '' ) );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $msg );
			}
			return;
		}
		if ( 'il' === $sub && isset( $parts[2] ) && ! empty( $ctx['user'] ) ) {
			self::start_inbound_link( $ctx, (int) $parts[2] );
			return;
		}
		if ( 'th' === $sub && isset( $parts[2] ) && ! empty( $ctx['user'] ) ) {
			$h   = (string) $parts[2];
			$key = get_transient( 'svp_txh_' . $h );
			if ( is_string( $key ) && '' !== $key ) {
				SimpleVPBot_State::set( (int) $ctx['user']->id, 'admin_txt_edit', array( 'key' => $key ) );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.prompt_new_value', $platform, $chat_id, array( 'key' => $key ) ) );
			}
			return;
		}
		if ( 'tv' === $sub && isset( $parts[2] ) ) {
			$h   = (string) $parts[2];
			$key = get_transient( 'svp_txv_' . $h );
			if ( is_string( $key ) && '' !== $key ) {
				$val = SimpleVPBot_Model_Text::get( $key, '—' );
				$val = mb_substr( wp_strip_all_tags( $val ), 0, 500 );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.text_preview', $platform, $chat_id, array( 'key' => $key, 'value' => $val ) ) );
			}
			return;
		}
		if ( 'll' === $sub && isset( $parts[2] ) ) {
			$sid = (int) $parts[2];
			if ( ! self::bot_admin_guard_service( $platform, $chat_id, $sid ) ) {
				return;
			}
			$r   = SimpleVPBot_Service_Admin_Ops::l2tp_test( $sid );
			$msg = ! empty( $r['ok'] ) ? ( '✅ ' . (string) ( $r['message'] ?? 'OK' ) . "\n" . mb_substr( wp_json_encode( $r['data'] ?? array() ), 0, 2500 ) ) : ( '⛔ ' . (string) ( $r['message'] ?? '' ) );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $msg );
			return;
		}
		if ( 'dl' === $sub && isset( $parts[2], $parts[3] ) && ! empty( $ctx['user'] )
			&& class_exists( 'SimpleVPBot_Handler_Admin_Catalog' ) ) {
			SimpleVPBot_Handler_Admin_Catalog::dispatch_legacy( $ctx, 'dy', (string) $parts[2], (int) $parts[3] );
			return;
		}
		if ( 'picku' === $sub && isset( $parts[2] ) ) {
			self::send_user_admin_card( $platform, $chat_id, (int) $parts[2] );
			return;
		}
		if ( 'umsg' === $sub && isset( $parts[2] ) && ! empty( $ctx['user'] ) ) {
			$tuid = (int) $parts[2];
			if ( $tuid > 0 ) {
				if ( ! self::bot_admin_guard_user( $platform, $chat_id, $tuid ) ) {
					return;
				}
				SimpleVPBot_State::set( (int) $ctx['user']->id, 'admin_dm', array( 'target_user_id' => $tuid ) );
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					self::admin_msg( 'msg.admin.prompt_dm', $platform, $chat_id, array( 'id' => $tuid ) )
				);
			}
			return;
		}
		if ( 'rcp' === $sub ) {
			$off = 0;
			if ( isset( $parts[2], $parts[3] ) && 'p' === (string) $parts[2] ) {
				$off = max( 0, (int) $parts[3] );
			}
			self::send_pending_receipts_review_paged( $platform, $chat_id, $off );
			return;
		}
		if ( 'blk' === $sub && isset( $parts[2] ) ) {
			$uid = (int) $parts[2];
			if ( ! self::bot_admin_guard_op( $platform, $chat_id, 'user_search' ) ) {
				return;
			}
			if ( ! self::bot_admin_guard_user( $platform, $chat_id, $uid ) ) {
				return;
			}
			SimpleVPBot_Model_User::update( $uid, array( 'status' => 'blocked' ) );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.user_status_updated', $platform, $chat_id ) );
			return;
		}
		if ( 'ub' === $sub && isset( $parts[2] ) ) {
			$uid = (int) $parts[2];
			if ( ! self::bot_admin_guard_op( $platform, $chat_id, 'user_search' ) ) {
				return;
			}
			if ( ! self::bot_admin_guard_user( $platform, $chat_id, $uid ) ) {
				return;
			}
			SimpleVPBot_Model_User::update( $uid, array( 'status' => 'approved' ) );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.user_status_updated', $platform, $chat_id ) );
			return;
		}
		if ( 'stx' === $sub && isset( $parts[2] ) ) {
			$sid = (int) $parts[2];
			if ( $sid > 0 ) {
				if ( ! self::bot_admin_guard_op( $platform, $chat_id, 'service_manage' ) ) {
					return;
				}
				if ( ! self::bot_admin_guard_service( $platform, $chat_id, $sid ) ) {
					return;
				}
				$user = isset( $ctx['user'] ) ? $ctx['user'] : null;
				if ( $user ) {
					SimpleVPBot_State::set( (int) $user->id, 'adm_service_transfer_' . $sid, array( 'service_id' => $sid ) );
				}
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					self::admin_msg( 'msg.admin.transfer_service_simple', $platform, $chat_id, array( 'id' => $sid ) )
				);
			}
			return;
		}
		if ( 'tx' === $sub ) {
			if ( self::bot_admin_deny_reseller_global( $platform, $chat_id ) ) {
				return;
			}
			$op = isset( $parts[2] ) ? (string) $parts[2] : '';
			if ( 'p' === $op && isset( $parts[3] ) ) {
				$u = null;
				if ( isset( $ctx['user'] ) ) {
					$u = $ctx['user'];
				} elseif ( isset( $parts[4] ) && (int) $parts[4] > 0 ) {
					$u = SimpleVPBot_Model_User::find( (int) $parts[4] );
				}
				self::send_text_keys_page( $platform, $chat_id, (int) $parts[3], $u );
				return;
			}
			if ( 'v' === $op ) {
				$key = implode( ':', array_slice( $parts, 3 ) );
				$key = trim( $key );
				if ( '' !== $key ) {
					$val = SimpleVPBot_Model_Text::get( $key, '—' );
					$val = mb_substr( wp_strip_all_tags( $val ), 0, 500 );
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.text_preview', $platform, $chat_id, array( 'key' => $key, 'value' => $val ) ) );
				}
				return;
			}
		}
		if ( 'sw' === $sub && isset( $parts[2] ) ) {
			$key = sanitize_key( (string) $parts[2] );
			if ( SimpleVPBot_Admin_Actions::toggle_bool_setting( $key ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.setting_changed', $platform, $chat_id, array( 'key' => $key ) ) );
			} else {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.setting_not_switchable', $platform, $chat_id ) );
			}
			return;
		}
		if ( 'pl' === $sub && isset( $parts[2], $parts[3] ) && 'a' === $parts[2] && ! empty( $ctx['user'] )
			&& class_exists( 'SimpleVPBot_Handler_Admin_Catalog' ) ) {
			SimpleVPBot_Handler_Admin_Catalog::dispatch_legacy( $ctx, 't', 'pl', (int) $parts[3] );
			return;
		}
		if ( 'pc' === $sub && isset( $parts[2], $parts[3] ) && 'a' === $parts[2] && ! empty( $ctx['user'] )
			&& class_exists( 'SimpleVPBot_Handler_Admin_Catalog' ) ) {
			SimpleVPBot_Handler_Admin_Catalog::dispatch_legacy( $ctx, 't', 'pc', (int) $parts[3] );
			return;
		}
		if ( 'cd' === $sub && isset( $parts[2], $parts[3] ) && 'a' === $parts[2] && ! empty( $ctx['user'] )
			&& class_exists( 'SimpleVPBot_Handler_Admin_Catalog' ) ) {
			SimpleVPBot_Handler_Admin_Catalog::dispatch_legacy( $ctx, 't', 'cd', (int) $parts[3] );
			return;
		}
		if ( 'm' === $sub && isset( $parts[2] ) ) {
			self::send_submenu( $platform, $chat_id, (string) $parts[2], $ctx );
			return;
		}
		if ( '' !== $sub ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				self::admin_msg( 'msg.admin.button_unknown', $platform, $chat_id ),
				array( 'reply_markup' => self::inline_hub_root_for_admin_chat( $platform, $chat_id ) )
			);
		}
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat.
	 * @param string $code Short tab code.
	 * @param array<string, mixed> $ctx Optional: user for per-admin flows (e.g. text keys edit).
	 */
	public static function send_submenu( $platform, $chat_id, $code, $ctx = null ) {
		$tu = ( is_array( $ctx ) && ! empty( $ctx['user'] ) ) ? $ctx['user'] : null;
		if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' )
			&& SimpleVPBot_Bot_Reseller_Scope::reseller_hub_submenu_blocked( (string) $code ) ) {
			$uid = $tu && ! empty( $tu->id ) ? (int) $tu->id : 0;
			SimpleVPBot_Bot_Reseller_Scope::deny_global_settings_bot_action( $platform, $chat_id, $uid );
			return;
		}
		$s  = SimpleVPBot_Settings::all();
		switch ( $code ) {
			case 'gen':
				$tg_n = is_array( $s['admin_telegram_ids'] ?? null ) ? count( (array) $s['admin_telegram_ids'] ) : 0;
				$bl_n = is_array( $s['admin_bale_ids'] ?? null ) ? count( (array) $s['admin_bale_ids'] ) : 0;
				$t    = SimpleVPBot_Bot_Admin_Texts::msg(
					'msg.admin.submenu.gen',
					$tu,
					array(
						'enabled'      => ! empty( $s['enabled'] ) ? '✓' : '✗',
						'test'         => ! empty( $s['test_account_enabled'] ) ? '✓' : '✗',
						'tg_n'         => (string) $tg_n,
						'bl_n'         => (string) $bl_n,
						'portal_page'  => (string) (int) ( $s['portal_page_id'] ?? 0 ),
						'default_plan' => (string) (int) ( $s['default_service_plan_id'] ?? 0 ),
					)
				);
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t, array( 'reply_markup' => SimpleVPBot_Keyboards::admin_general_submenu_reply( $tu ) ) );
				return;
			case 'set':
				$extra = ( class_exists( 'SimpleVPBot_Feature_L2tp' ) && SimpleVPBot_Feature_L2tp::enabled() )
					? SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.submenu.set_l2tp', $tu )
					: '';
				$t = SimpleVPBot_Bot_Admin_Texts::msg(
					'msg.admin.submenu.set',
					$tu,
					array(
						'body' => SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.submenu.set_body', $tu, array( 'extra' => $extra ) ),
					)
				);
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t, array( 'reply_markup' => SimpleVPBot_Keyboards::admin_settings_catalog_reply( $tu ) ) );
				return;
			case 'adv':
				$t = SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.submenu.adv', $tu );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t, array( 'reply_markup' => SimpleVPBot_Keyboards::admin_settings_advanced_reply( $tu ) ) );
				return;
			case 'bot':
				$tl = strlen( (string) ( $s['telegram_token'] ?? '' ) );
				$bl = strlen( (string) ( $s['bale_token'] ?? '' ) );
				$t  = SimpleVPBot_Bot_Admin_Texts::msg(
					'msg.admin.submenu.bot',
					$tu,
					array(
						'tg_len'   => (string) $tl,
						'bale_len' => (string) $bl,
					)
				);
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t, array( 'reply_markup' => SimpleVPBot_Keyboards::admin_bot_submenu_reply( $tu ) ) );
				return;
			case 'pan':
				$t = SimpleVPBot_Bot_Admin_Texts::msg(
					'msg.admin.submenu.pan',
					$tu,
					array(
						'url_state' => '' !== (string) ( $s['panel_url'] ?? '' )
							? SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.submenu.pan_has_url', $tu, array(), 'URL: دارد' )
							: SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.submenu.pan_no_url', $tu, array(), 'URL: خالی' ),
					)
				);
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t, array( 'reply_markup' => SimpleVPBot_Keyboards::admin_panel_submenu_reply( $tu ) ) );
				return;
			case 'bak':
				if ( class_exists( 'SimpleVPBot_Handler_Admin_Backup' ) ) {
					SimpleVPBot_Handler_Admin_Backup::send_panel( $platform, $chat_id, $tu );
					return;
				}
				self::send_backup_panel( $platform, $chat_id );
				return;
			case 'not':
				$t = SimpleVPBot_Bot_Admin_Texts::msg(
					'msg.admin.submenu.not',
					$tu,
					array(
						'low_pct'     => (string) (int) ( $s['notify_low_traffic_percent'] ?? 10 ),
						'concurrent'  => (string) (int) ( $s['default_concurrent_users'] ?? 2 ),
						'expiry_days' => esc_html( implode( ',', (array) ( $s['notify_expiry_days'] ?? array( 3, 1 ) ) ) ),
					)
				);
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t, array( 'reply_markup' => SimpleVPBot_Keyboards::admin_notif_submenu_reply( $tu ) ) );
				return;
			case 'plc':
				if ( ! $tu && class_exists( 'SimpleVPBot_Model_User' ) ) {
					$tu = 'bale' === $platform ? SimpleVPBot_Model_User::find_by_bale( $chat_id ) : SimpleVPBot_Model_User::find_by_telegram( $chat_id );
				}
				if ( $tu && class_exists( 'SimpleVPBot_Handler_Admin_Catalog' ) ) {
					SimpleVPBot_Handler_Admin_Catalog::send_list( $platform, $chat_id, $tu, 'plan_cats', 0 );
					return;
				}
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.denied_tab', $platform, $chat_id ) );
				return;
			case 'pln':
				if ( ! $tu && class_exists( 'SimpleVPBot_Model_User' ) ) {
					$tu = 'bale' === $platform ? SimpleVPBot_Model_User::find_by_bale( $chat_id ) : SimpleVPBot_Model_User::find_by_telegram( $chat_id );
				}
				if ( $tu && class_exists( 'SimpleVPBot_Handler_Admin_Catalog' ) ) {
					SimpleVPBot_Handler_Admin_Catalog::send_list( $platform, $chat_id, $tu, 'plans', 0 );
					return;
				}
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.denied_tab', $platform, $chat_id ) );
				return;
			case 'crd':
				if ( ! $tu && class_exists( 'SimpleVPBot_Model_User' ) ) {
					$tu = 'bale' === $platform ? SimpleVPBot_Model_User::find_by_bale( $chat_id ) : SimpleVPBot_Model_User::find_by_telegram( $chat_id );
				}
				if ( $tu && class_exists( 'SimpleVPBot_Handler_Admin_Catalog' ) ) {
					SimpleVPBot_Handler_Admin_Catalog::send_list( $platform, $chat_id, $tu, 'cards', 0 );
					return;
				}
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.denied_tab', $platform, $chat_id ) );
				return;
			case 'usr':
				SimpleVPBot_Handler_Admin_Users::send_pending_page( $platform, $chat_id, 0, 0 );
				return;
			case 'rcp':
				self::send_pending_receipts_review_paged( $platform, $chat_id, 0 );
				return;
			case 'txt':
				$tu = ( is_array( $ctx ) && ! empty( $ctx['user'] ) ) ? $ctx['user'] : null;
				self::send_text_keys_page( $platform, $chat_id, 0, $tu );
				return;
			case 'l2p':
				if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) && ! SimpleVPBot_Feature_L2tp::enabled() ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.unavailable', $platform, $chat_id ) );
					return;
				}
				self::send_l2tp_admin_panel( $platform, $chat_id );
				return;
			case 'pay':
				self::send_crypto_pay_panel( $platform, $chat_id );
				return;
			case 'blk':
				if ( $tu && class_exists( 'SimpleVPBot_Handler_Admin_Bulk' ) ) {
					SimpleVPBot_Handler_Admin_Bulk::open_tab( $platform, $chat_id, $tu, is_array( $ctx ) ? $ctx : array() );
					return;
				}
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.submenu.bulk', $tu ),
					array( 'reply_markup' => SimpleVPBot_Keyboards::admin_bulk_submenu_reply( $tu ) )
				);
				return;
			case 'log':
				if ( class_exists( 'SimpleVPBot_Handler_Admin_Logs' ) ) {
					SimpleVPBot_Handler_Admin_Logs::send_page( $platform, $chat_id, 0, $tu );
					return;
				}
				self::send_logs_page( $platform, $chat_id, 0 );
				return;
			case 'inl':
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.submenu.inl', $tu ),
					array( 'reply_markup' => SimpleVPBot_Keyboards::admin_inbound_submenu_reply( $tu ) )
				);
				return;
			case 'brd':
				$br  = SimpleVPBot_Model_Broadcast::list_recent( 5, 0 );
				$lst = '';
				if ( empty( $br ) ) {
					$lst = SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.submenu.brd_empty', $tu );
				} else {
					foreach ( $br as $b ) {
						$lst .= '#' . (int) $b->id . ' ' . (string) $b->status . ' · ' . (string) $b->created_at . "\n";
					}
				}
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.submenu.brd', $tu, array( 'list' => $lst ) ),
					array( 'reply_markup' => SimpleVPBot_Keyboards::admin_only_back_reply() )
				);
				return;
			default:
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.unknown', $platform, $chat_id ), array( 'reply_markup' => self::reply_markup_main_for_chat( $platform, $chat_id ) ) );
		}
	}

	/**
	 * Paginated text keys (view + optional edit from callback).
	 *
	 * @param string    $platform Platform.
	 * @param int       $chat_id  Chat.
	 * @param int       $offset   Offset.
	 * @param object|null $user   Admin user (for edit hash / pagination).
	 */
	/**
	 * @param string      $platform Platform.
	 * @param int         $chat_id  Chat id.
	 * @param object|null $user     User.
	 * @param int         $offset   Offset.
	 */
	public static function open_texts_tab( $platform, $chat_id, $user = null, $offset = 0 ) {
		self::send_text_keys_page( $platform, $chat_id, $offset, $user );
	}

	private static function send_text_keys_page( $platform, $chat_id, $offset, $user = null ) {
		$all   = SimpleVPBot_Model_Text::all();
		$off   = max( 0, (int) $offset );
		$slice = array_slice( $all, $off, 8 );
		if ( empty( $slice ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.no_text_saved', $platform, $chat_id ), array( 'reply_markup' => self::reply_markup_main_for_chat( $platform, $chat_id ) ) );
			return;
		}
		$uid  = ( $user && ! empty( $user->id ) ) ? (int) $user->id : 0;
		$rows = array();
		foreach ( $slice as $row ) {
			$key = (string) $row->key_name;
			$h8v = substr( md5( $key . 'v' ), 0, 8 );
			set_transient( 'svp_txv_' . $h8v, $key, 3600 );
			$row_btns = array( array( 'text' => '👁 ' . $h8v . ' ' . mb_substr( $key, 0, 20 ) ) );
			if ( $user && $uid ) {
				$h8e = substr( md5( $key . 'u' . $uid ), 0, 8 );
				set_transient( 'svp_txh_' . $h8e, $key, 3600 );
				$row_btns[] = array( 'text' => '✏ ' . $h8e );
			}
			$rows[] = $row_btns;
		}
		$nav = array();
		$nav_prev = ( $user && is_object( $user ) )
			? SimpleVPBot_Texts::get_for_user( 'btn.admin.texts_prev', $user, '◀ متن قبلی' )
			: '◀ متن قبلی';
		$nav_next = ( $user && is_object( $user ) )
			? SimpleVPBot_Texts::get_for_user( 'btn.admin.texts_next', $user, 'متن بعدی ▶' )
			: 'متن بعدی ▶';
		$nav_reset = ( $user && is_object( $user ) )
			? SimpleVPBot_Texts::get_for_user( 'btn.admin.texts_reset_all', $user, '🔄 همه به پیش‌فرض' )
			: '🔄 همه به پیش‌فرض';
		if ( $off > 0 ) {
			$nav[] = array( 'text' => $nav_prev );
		}
		if ( $off + 8 < count( $all ) ) {
			$nav[] = array( 'text' => $nav_next );
		}
		if ( $nav ) {
			$rows[] = $nav;
		}
		if ( $user && $uid ) {
			$rows[] = array( array( 'text' => $nav_reset ) );
		}
		if ( $user && ! empty( $user->id ) ) {
			SimpleVPBot_State::set( (int) $user->id, 'admin_txt_page', array( 'off' => $off ) );
		}
		$hint = '📝 کلیدهای متن — 👁 مشاهده (کد ۸ کاراکتری)؛ ✏ ویرایش سپس متن جدید بفرستید.' . "\n"
			. '🔄 «همه به پیش‌فرض» همهٔ کلیدها را به مقادیر پیش‌فرض نسخهٔ فعلی پلاگین برمی‌گرداند.';
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$hint,
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $rows ) )
		);
	}

	/**
	 * Send full backup control panel (inline).
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat.
	 */
	public static function send_backup_panel( $platform, $chat_id ) {
		if ( class_exists( 'SimpleVPBot_Handler_Admin_Backup' ) ) {
			SimpleVPBot_Handler_Admin_Backup::send_panel( $platform, $chat_id );
			return;
		}
		$s = SimpleVPBot_Settings::all();
		$t = self::backup_panel_caption( $s );
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$t,
			array( 'reply_markup' => self::backup_panel_reply_markup( $s ) )
		);
	}

	/**
	 * @param array<string, mixed> $s Settings.
	 * @return string
	 */
	public static function backup_panel_caption( $s ) {
		if ( class_exists( 'SimpleVPBot_Handler_Admin_Backup' ) ) {
			return SimpleVPBot_Handler_Admin_Backup::panel_caption( $s );
		}
		$iv = (int) ( $s['backup_interval_minutes'] ?? 60 );
		$t  = "💾 بکاپ و ریستور\n➖➖➖➖\n";
		$t .= '⏱ فاصله: ' . $iv . " دقیقه\n";
		$t .= '📢 TG chat id: ' . (int) ( $s['backup_telegram_chat_id'] ?? 0 ) . "\n";
		$t .= '💬 Bale chat id: ' . (int) ( $s['backup_bale_chat_id'] ?? 0 ) . "\n";
		$sta  = ! empty( $s['backup_send_telegram_admins'] ) ? '✓' : '✗';
		$sba  = ! empty( $s['backup_send_bale_admins'] ) ? '✓' : '✗';
		$stc  = ! empty( $s['backup_send_telegram_channel'] ) ? '✓' : '✗';
		$sbc  = ! empty( $s['backup_send_bale_channel'] ) ? '✓' : '✗';
		$t   .= "ارسال: TG ادمین {$sta} · Bale ادمین {$sba} · TG کانال {$stc} · Bale کانال {$sbc}\n";
		$lbat = (int) get_option( 'simplevpbot_last_backup_at', 0 );
		$lbui = (int) get_option( 'simplevpbot_last_backup_built_at', 0 );
		$t   .= 'آخرین ارسال موفق: ' . self::fmt_backup_ts( $lbat ) . "\n";
		$t   .= 'آخرین ساخت زیپ: ' . self::fmt_backup_ts( $lbui ) . "\n";
		$t   .= "➖\nدکمه‌ها: بکاپ الان، تیک‌ها، ویرایش مقدار، ریستور (۲ مرحله).";
		return $t;
	}

	/**
	 * @param int $ts Unix.
	 * @return string
	 */
	private static function fmt_backup_ts( $ts ) {
		$ts = (int) $ts;
		if ( $ts <= 0 ) {
			return '—';
		}
		if ( class_exists( 'SimpleVPBot_Jalali_Date' ) ) {
			return SimpleVPBot_Jalali_Date::format_datetime( $ts );
		}
		return wp_date( 'Y-m-d H:i', $ts, wp_timezone() );
	}

	/**
	 * @param array<string, mixed> $s Settings.
	 * @return array<string, mixed>
	 */
	public static function backup_panel_reply_markup( $s ) {
		return SimpleVPBot_Keyboards::admin_backup_panel_reply( $s );
	}

	/**
	 * Step 1 of 2: bulk +days — ask inline confirmation.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat.
	 * @param int    $days Days.
	 */
	private static function send_bulk_days_confirm( $platform, $chat_id, $days ) {
		if ( class_exists( 'SimpleVPBot_Handler_Admin_Bulk' ) ) {
			SimpleVPBot_Handler_Admin_Bulk::days_confirm( $platform, $chat_id, $days );
			return;
		}
		if ( self::bot_admin_deny_site_bulk( $platform, $chat_id ) ) {
			return;
		}
		$d = max( 1, (int) $days );
		$t = "⚠️ تأیید عملیات گروهی\n➖\nافزودن «{$d}» روز به سرویس‌های Xray (حداکثر ۲۰۰ سرویس در هر اجرا).\nادامه؟";
		$rows = array(
			array(
				array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '✅ تأیید +' . $d . ' روز', 256 ) ),
				array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '❌ لغو گروهی', 256 ) ),
			),
		);
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t, array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $rows ) ) );
	}

	/**
	 * Step 1 of 2: bulk +GB — ask inline confirmation.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat.
	 * @param int    $gb Gigabytes.
	 */
	private static function send_bulk_gb_confirm( $platform, $chat_id, $gb ) {
		if ( class_exists( 'SimpleVPBot_Handler_Admin_Bulk' ) ) {
			SimpleVPBot_Handler_Admin_Bulk::gb_confirm( $platform, $chat_id, $gb );
			return;
		}
		if ( self::bot_admin_deny_site_bulk( $platform, $chat_id ) ) {
			return;
		}
		$g = max( 1, (int) $gb );
		$t = "⚠️ تأیید عملیات گروهی\n➖\nافزودن «{$g}» گیگ به هر سرویس Xray (حداکثر ۲۰۰ سرویس).\nادامه؟";
		$rows = array(
			array(
				array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '✅ تأیید +' . $g . ' GB', 256 ) ),
				array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '❌ لغو گروهی', 256 ) ),
			),
		);
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t, array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $rows ) ) );
	}

	/**
	 * Public wrappers for Bot UI router (bulk confirm step 1).
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat id.
	 * @param int    $days Days.
	 */
	public static function router_bulk_days_confirm( $platform, $chat_id, $days ) {
		self::send_bulk_days_confirm( $platform, $chat_id, $days );
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat id.
	 * @param int    $gb GB.
	 */
	public static function router_bulk_gb_confirm( $platform, $chat_id, $gb ) {
		self::send_bulk_gb_confirm( $platform, $chat_id, $gb );
	}

	/**
	 * Approved users (paged, inline).
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat.
	 * @param int    $offset Offset.
	 * @param int    $edit_msg_id Edit existing message id (optional).
	 */
	private static function send_approved_users_page( $platform, $chat_id, $offset = 0, $edit_msg_id = 0 ) {
		SimpleVPBot_Handler_Admin_Users::send_approved_page( $platform, $chat_id, $offset, $edit_msg_id );
	}

	/**
	 * Rejected users with reopen-to-queue (inline).
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat.
	 * @param int    $offset Offset.
	 * @param int    $edit_msg_id Edit message id.
	 */
	private static function send_rejected_users_page( $platform, $chat_id, $offset = 0, $edit_msg_id = 0 ) {
		SimpleVPBot_Handler_Admin_Users::send_rejected_page( $platform, $chat_id, $offset, $edit_msg_id );
	}

	/**
	 * L2TP servers: test, toggle, delete, add wizard.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat.
	 */
	private static function send_l2tp_admin_panel( $platform, $chat_id ) {
		if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) && ! SimpleVPBot_Feature_L2tp::enabled() ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.unavailable', $platform, $chat_id ) );
			return;
		}
		$lrows = SimpleVPBot_Model_L2TP_Server::all();
		$t     = '🔌 L2TP (' . count( $lrows ) . ")\n➖\n";
		$rows  = array(
			array( array( 'text' => '➕ سرور جدید (خطی)' ) ),
		);
		foreach ( array_slice( $lrows, 0, 6 ) as $srv ) {
			$id = (int) $srv->id;
			$sl = trim( (string) ( $srv->label ?? '' ) );
			if ( '' === $sl ) {
				$sl = '#' . $id;
			}
			$rows[] = array(
				array( 'text' => 'L2 تست ' . $id ),
				array( 'text' => 'L2 سوییچ ' . $id ),
				array( 'text' => 'L2 حذف ' . $id ),
			);
		}
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t, array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $rows ) ) );
	}

	/**
	 * NOWPayments / IPN summary + wizards.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat.
	 */
	private static function send_crypto_pay_panel( $platform, $chat_id ) {
		$s   = SimpleVPBot_Settings::all();
		$ipn = SimpleVPBot_Crypto_Payment::ipn_callback_url();
		$ak  = (string) ( $s['crypto_nowpayments_api_key'] ?? '' );
		$t   = "₿ کریپتو (NOWPayments)\n➖\n";
		$t  .= 'API key: ' . ( '' !== $ak ? '✓ (' . strlen( $ak ) . ')' : '—' ) . "\n";
		$t  .= 'IPN: ' . ( '' !== $ipn ? $ipn : '—' ) . "\n";
		$t  .= 'pay_currency: ' . (string) ( $s['crypto_nowpayments_pay_currency'] ?? '' );
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t, array( 'reply_markup' => SimpleVPBot_Keyboards::admin_crypto_submenu_reply( null ) ) );
	}

	/**
	 * Map w|f|i to admin_create_service mode.
	 *
	 * @param string $letter w|f|i.
	 * @return string wallet|free|invoice or ''.
	 */
	private static function admin_create_service_mode_from_letter( $letter ) {
		$l = strtolower( (string) $letter );
		if ( 'w' === $l ) {
			return 'wallet';
		}
		if ( 'f' === $l ) {
			return 'free';
		}
		if ( 'i' === $l ) {
			return 'invoice';
		}
		return '';
	}

	/**
	 * Inline rows: payment mode for admin create service (used from Hub + Handler_Admin).
	 *
	 * @param int      $target_uid svp_users.id.
	 * @param int      $plan_id    Plan id.
	 * @param int|null $volume_gb  null = fixed plan.
	 * @return array<int, array<int, array<string, string>>>
	 */
	public static function admin_create_service_mode_keyboard( $target_uid, $plan_id, $volume_gb ) {
		$t = (int) $target_uid;
		$p = (int) $plan_id;
		$v = null === $volume_gb ? '' : (string) (int) $volume_gb;
		$rows = array();
		if ( '' === $v ) {
			foreach ( array( 'w' => '💳 کیف پول', 'f' => '🎁 رایگان', 'i' => '🧾 فاکتور' ) as $k => $lab ) {
				$cb = 'pnl:nsx:' . $t . ':' . $p . ':' . $k;
				if ( strlen( $cb ) <= 64 ) {
					$rows[] = array(
						array(
							'text'          => SimpleVPBot_Keyboards::glass_button_text( $lab, 64 ),
							'callback_data' => $cb,
						),
					);
				}
			}
			return $rows;
		}
		foreach ( array( 'w' => '💳 کیف پول', 'f' => '🎁 رایگان', 'i' => '🧾 فاکتور' ) as $k => $lab ) {
			$cb = 'pnl:nsm:' . $t . ':' . $p . ':' . $v . ':' . $k;
			if ( strlen( $cb ) <= 64 ) {
				$rows[] = array(
					array(
						'text'          => SimpleVPBot_Keyboards::glass_button_text( $lab, 64 ),
						'callback_data' => $cb,
					),
				);
			}
		}
		return $rows;
	}

	/**
	 * Inline rows: payment mode for admin renew / add volume / add user slots (labels match create-service flow).
	 *
	 * @param string   $kind       renew|vol|slots.
	 * @param int      $service_id Service id.
	 * @param int|null $extra      GB for vol, slot count for slots, omit for renew.
	 * @return array<int, array<int, array<string, string>>>
	 */
	public static function admin_service_payment_mode_inline_rows( $kind, $service_id, $extra = null ) {
		$sid = (int) $service_id;
		$rows = array();
		foreach ( array( 'w' => '💳 کیف پول', 'f' => '🎁 رایگان', 'i' => '🧾 فاکتور' ) as $k => $lab ) {
			if ( 'renew' === $kind ) {
				$cb = 'pnl:nrr:' . $sid . ':' . $k;
			} elseif ( 'vol' === $kind ) {
				$cb = 'pnl:nva:' . $sid . ':' . (int) $extra . ':' . $k;
			} else {
				$cb = 'pnl:nus:' . $sid . ':' . (int) $extra . ':' . $k;
			}
			if ( strlen( $cb ) <= 64 ) {
				$rows[] = array(
					array(
						'text'          => SimpleVPBot_Keyboards::glass_button_text( $lab, 64 ),
						'callback_data' => $cb,
					),
				);
			}
		}
		return $rows;
	}

	/**
	 * Execute pnl:nrr / pnl:nva / pnl:nus after admin picked payment mode.
	 *
	 * @param array<string, mixed> $ctx          Callback context.
	 * @param string               $kind         renew|vol|slots.
	 * @param int                  $service_id   Service id.
	 * @param int|null             $extra_gb_or_n Extra GB or extra users; null for renew.
	 * @param string               $letter       w|f|i.
	 */
	private static function handle_admin_service_payment_execute( array $ctx, $kind, $service_id, $extra_gb_or_n, $letter ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		if ( ! self::bot_admin_guard_op( $platform, $chat_id, 'service_manage' ) ) {
			return;
		}
		$mode     = self::admin_create_service_mode_from_letter( $letter );
		if ( '' === $mode || ! class_exists( 'SimpleVPBot_Admin_User_Ops' ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.method_invalid', $platform, $chat_id ) );
			return;
		}
		$sid = (int) $service_id;
		if ( $sid < 1 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.service_invalid', $platform, $chat_id ) );
			return;
		}
		if ( ! self::bot_admin_guard_service( $platform, $chat_id, $sid ) ) {
			return;
		}
		$scope = class_exists( 'SimpleVPBot_Bot_Reseller_Scope' )
			? SimpleVPBot_Bot_Reseller_Scope::bot_admin_invoice_card_scope_reseller_id()
			: 0;
		if ( 'renew' === $kind ) {
			$r = SimpleVPBot_Admin_User_Ops::admin_renew_service( $sid, $mode, $scope );
		} elseif ( 'vol' === $kind ) {
			$g = null === $extra_gb_or_n ? 0 : (int) $extra_gb_or_n;
			$r = SimpleVPBot_Admin_User_Ops::admin_add_volume( $sid, $g, $mode, $scope );
		} else {
			$n = null === $extra_gb_or_n ? 0 : (int) $extra_gb_or_n;
			$r = SimpleVPBot_Admin_User_Ops::admin_add_user_slots( $sid, $n, $mode, $scope );
		}
		if ( ! empty( $r['ok'] ) ) {
			$msg = isset( $r['transaction_id'] )
				? '✅ فاکتور ارسال شد (سفارش #' . (int) $r['transaction_id'] . ').'
				: '✅ انجام شد.';
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $msg );
			return;
		}
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.error_generic', $platform, $chat_id, array( 'reason' => (string) ( $r['reason'] ?? self::admin_msg( 'msg.admin.fallback.error', $platform, $chat_id ) ) ) ) );
	}

	/**
	 * Step 1: pick plan for target user (admin create service).
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat.
	 * @param int    $target_uid svp_users.id.
	 */
	private static function send_admin_create_service_plan_picker( $platform, $chat_id, $target_uid ) {
		$tuid = (int) $target_uid;
		if ( $tuid < 1 || ! SimpleVPBot_Model_User::find( $tuid ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.target_user_not_found', $platform, $chat_id ) );
			return;
		}
		if ( ! class_exists( 'SimpleVPBot_Admin_User_Ops' ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.ops_unavailable', $platform, $chat_id ) );
			return;
		}
		$plans = SimpleVPBot_Model_Plan::all_active();
		if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			$plans = array_values(
				array_filter(
					(array) $plans,
					static function ( $pl ) {
						return $pl && SimpleVPBot_Bot_Reseller_Scope::plan_visible_in_context( $pl );
					}
				)
			);
		}
		if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) ) {
			$plans = SimpleVPBot_Feature_L2tp::filter_plans( (array) $plans );
		}
		if ( empty( $plans ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.no_active_plans', $platform, $chat_id ) );
			return;
		}
		$tuid_fa = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $tuid );
		$intro   = array();
		$intro[] = '➕ ساخت سرویس برای کاربر #' . $tuid_fa;
		$intro[] = 'پلن را از دکمه‌های زیر انتخاب کنید. برای لغو /cancel بفرستید.';
		$rows = array();
		foreach ( $plans as $pl ) {
			if ( ! $pl || ! (int) $pl->active ) {
				continue;
			}
			if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) && ! SimpleVPBot_Feature_L2tp::plan_visible( $pl ) ) {
				continue;
			}
			$pid = (int) $pl->id;
			$cb  = 'pnl:nsp:' . $tuid . ':' . $pid;
			if ( strlen( $cb ) > 64 ) {
				continue;
			}
			$rows[] = array(
				array(
					'text'          => SimpleVPBot_Bot_Persian_Text::plan_picker_glass_button( $pl ),
					'callback_data' => $cb,
				),
			);
		}
		if ( empty( $rows ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.plan_ids_too_large', $platform, $chat_id ) );
			return;
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			implode( "\n", $intro ),
			array( 'reply_markup' => array( 'inline_keyboard' => $rows ) )
		);
	}

	/**
	 * After admin picked a plan: per-GB ask volume, else show payment mode.
	 *
	 * @param array<string, mixed> $ctx        Callback context.
	 * @param int                  $target_uid Target user.
	 * @param int                  $plan_id    Plan id.
	 */
	private static function handle_admin_create_service_plan_pick( array $ctx, $target_uid, $plan_id ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		if ( ! self::bot_admin_guard_op( $platform, $chat_id, 'service_manage' ) ) {
			return;
		}
		$admin    = $ctx['user'];
		$tuid     = (int) $target_uid;
		$pid      = (int) $plan_id;
		if ( $tuid < 1 || ! SimpleVPBot_Model_User::find( $tuid ) || $pid < 1 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.invalid_data', $platform, $chat_id ) );
			return;
		}
		if ( ! self::bot_admin_guard_user( $platform, $chat_id, $tuid ) ) {
			return;
		}
		$plan = SimpleVPBot_Model_Plan::find( $pid );
		if ( ! $plan || ! (int) $plan->active ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.plan_unavailable', $platform, $chat_id ) );
			return;
		}
		if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) && ! SimpleVPBot_Bot_Reseller_Scope::plan_visible_in_context( $plan ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.plan_unavailable', $platform, $chat_id ) );
			return;
		}
		if ( SimpleVPBot_Model_Plan::is_per_gb( $plan ) ) {
			$min = (int) ( $plan->traffic_gb_min ?? 0 );
			$max = (int) ( $plan->traffic_gb_max ?? 0 );
			if ( $min < 1 || $max < 1 || $min > $max || (float) ( $plan->price_per_gb ?? 0 ) <= 0 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.plan_pergb_misconfigured', $platform, $chat_id ) );
				return;
			}
			SimpleVPBot_State::set(
				(int) $admin->id,
				'admin_ns_vol',
				array(
					'target_uid' => $tuid,
					'plan_id'    => $pid,
				)
			);
			$ppg    = SimpleVPBot_Bot_Persian_Text::format_toman_fa( (float) ( $plan->price_per_gb ?? 0 ) );
			$tuid_f = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $tuid );
			$min_f  = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $min );
			$max_f  = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $max );
			$d_fa   = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) (int) $plan->duration_days );
			$txt    = "➕ ساخت سرویس برای #{$tuid_f}\n📦 پلن: " . (string) $plan->name . "\n";
			$txt   .= '💰 ' . $ppg . ' تومان به ازای هر گیگابایت' . "\n";
			$txt   .= '⏳ مدت: ' . $d_fa . " روز\n";
			$txt   .= "۲) حجم را فقط به صورت عدد (گیگابایت) بین {$min_f} و {$max_f} بفرستید.\n/cancel برای لغو.";
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $txt );
			return;
		}
		$mk = self::admin_create_service_mode_keyboard( $tuid, $pid, null );
		if ( empty( $mk ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.internal_button_error', $platform, $chat_id ) );
			return;
		}
		$tuid_f = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $tuid );
		$txt    = "➕ ساخت سرویس برای #{$tuid_f}\n📦 پلن: " . (string) $plan->name . "\n۳) روش اعمال را انتخاب کنید:";
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$txt,
			array( 'reply_markup' => array( 'inline_keyboard' => $mk ) )
		);
	}

	/**
	 * Run admin_create_service and clear state.
	 *
	 * @param array<string, mixed> $ctx        Context.
	 * @param int                  $target_uid Target user.
	 * @param int                  $plan_id    Plan id.
	 * @param int|null             $volume_gb  null for fixed plan.
	 * @param string               $letter     w|f|i.
	 */
	private static function handle_admin_create_service_execute( array $ctx, $target_uid, $plan_id, $volume_gb, $letter ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		if ( ! self::bot_admin_guard_op( $platform, $chat_id, 'service_manage' ) ) {
			return;
		}
		$admin    = $ctx['user'];
		$mode     = self::admin_create_service_mode_from_letter( $letter );
		if ( '' === $mode ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.pay_method_invalid', $platform, $chat_id ) );
			return;
		}
		$plan = SimpleVPBot_Model_Plan::find( (int) $plan_id );
		if ( ! $plan || ! (int) $plan->active ) {
			SimpleVPBot_State::clear( (int) $admin->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.plan_invalid', $platform, $chat_id ) );
			return;
		}
		if ( ! self::bot_admin_guard_user( $platform, $chat_id, (int) $target_uid ) ) {
			SimpleVPBot_State::clear( (int) $admin->id );
			return;
		}
		if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) && ! SimpleVPBot_Bot_Reseller_Scope::plan_visible_in_context( $plan ) ) {
			SimpleVPBot_State::clear( (int) $admin->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.plan_invalid', $platform, $chat_id ) );
			return;
		}
		$vol = null;
		if ( SimpleVPBot_Model_Plan::is_per_gb( $plan ) ) {
			$g = null === $volume_gb ? 0 : (int) $volume_gb;
			if ( $g < 1 || ! SimpleVPBot_Model_Plan::is_volume_in_range( $plan, $g ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.volume_invalid_for_plan', $platform, $chat_id ) );
				return;
			}
			$vol = $g;
		} elseif ( null !== $volume_gb && (int) $volume_gb > 0 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.fixed_plan_no_volume', $platform, $chat_id ) );
			return;
		}
		if ( ! class_exists( 'SimpleVPBot_Admin_User_Ops' ) ) {
			SimpleVPBot_State::clear( (int) $admin->id );
			return;
		}
		$scope = class_exists( 'SimpleVPBot_Bot_Reseller_Scope' )
			? SimpleVPBot_Bot_Reseller_Scope::bot_admin_invoice_card_scope_reseller_id()
			: 0;
		$r = SimpleVPBot_Admin_User_Ops::admin_create_service( (int) $target_uid, (int) $plan_id, $vol, $mode, $scope );
		SimpleVPBot_State::clear( (int) $admin->id );
		if ( empty( $r['ok'] ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.error_generic', $platform, $chat_id, array( 'reason' => (string) ( $r['reason'] ?? self::admin_msg( 'msg.admin.fallback.error', $platform, $chat_id ) ) ) ) );
			return;
		}
		if ( isset( $r['service_id'] ) ) {
			$msg = '✅ سرویس #' . SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) (int) $r['service_id'] );
		} else {
			$txid = (int) ( $r['transaction_id'] ?? 0 );
			$msg  = '✅ فاکتور ارسال شد (سفارش #' . SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $txid ) . ').';
		}
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $msg );
	}

	/**
	 * Full user row + optional Telegram profile photo for admins.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Admin chat.
	 * @param int    $uid svp_users.id.
	 */
	private static function send_user_admin_preview( $platform, $chat_id, $uid ) {
		$uid = (int) $uid;
		if ( ! self::bot_admin_guard_user( $platform, $chat_id, $uid ) ) {
			return;
		}
		$u   = SimpleVPBot_Model_User::find( $uid );
		if ( ! $u ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.user_not_found', $platform, $chat_id ) );
			return;
		}
		$t  = "👤 کاربر #{$uid}\n➖➖➖➖➖➖➖➖\n";
		$t .= 'وضعیت: ' . (string) $u->status . "\n";
		$t .= 'نام: ' . trim( (string) $u->first_name . ' ' . (string) $u->last_name ) . "\n";
		$t .= 'یوزرنیم: ' . ( $u->username ? '@' . (string) $u->username : '—' ) . "\n";
		$t .= 'TG id: ' . ( $u->tg_user_id ? (string) (int) $u->tg_user_id : '—' ) . "\n";
		$t .= 'Bale id: ' . ( $u->bale_user_id ? (string) (int) $u->bale_user_id : '—' ) . "\n";
		$t .= 'تلفن: ' . (string) ( $u->phone ?? '' ) . "\n";
		$t .= 'موجودی: ' . number_format( (float) $u->balance ) . "\n";
		$t .= 'ساخته: ' . (string) $u->created_at . "\n";
		if ( 'telegram' === $platform && ! empty( $u->tg_user_id ) ) {
			$tmp = SimpleVPBot_Bot_Runtime::telegram_user_profile_photo_temp( (int) $u->tg_user_id );
			if ( '' !== $tmp && is_readable( $tmp ) ) {
				SimpleVPBot_Bot_Runtime::send_photo_file( $platform, $chat_id, $tmp, $t, array() );
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				return;
			}
		}
		if ( 'bale' === $platform ) {
			$t .= "\nℹ️ پیش‌نمایش عکس پروفایل در بله در این نسخه پشتیبانی نمی‌شود.";
		}
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $t );
	}

	/**
	 * Pending users (inline glass, 5 per page, newest first).
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat.
	 * @param int    $offset Offset.
	 * @param int    $edit_msg_id Edit this message (0 = new).
	 */
	private static function send_pending_users_page( $platform, $chat_id, $offset = 0, $edit_msg_id = 0 ) {
		SimpleVPBot_Handler_Admin_Users::send_pending_page( $platform, $chat_id, $offset, $edit_msg_id );
	}

	/**
	 * Logs with pagination.
	 *
	 * @param string      $platform Platform.
	 * @param int         $chat_id  Chat.
	 * @param int         $offset   Offset.
	 * @param object|null $user     Admin user for state.
	 */
	public static function send_logs_page( $platform, $chat_id, $offset = 0, $user = null ) {
		if ( class_exists( 'SimpleVPBot_Handler_Admin_Logs' ) ) {
			SimpleVPBot_Handler_Admin_Logs::send_page( $platform, $chat_id, $offset, $user );
			return;
		}
		global $wpdb;
		$lt  = $wpdb->prefix . 'svp_logs';
		$off = max( 0, (int) $offset );
		$lim = 8;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders
		$logs = $wpdb->get_results( $wpdb->prepare( "SELECT level, message, created_at FROM {$lt} ORDER BY id DESC LIMIT %d OFFSET %d", $lim, $off ) );
		$cnt  = count( (array) $logs );
		$t    = "📜 لاگ (" . ( $off + 1 ) . "–" . ( $off + $cnt ) . ")\n➖\n";
		if ( empty( $logs ) ) {
			$t .= 'رکوردی نیست.';
		} else {
			foreach ( $logs as $lg ) {
				$t .= '[' . (string) $lg->level . '] ' . mb_substr( (string) $lg->message, 0, 70 ) . "\n";
			}
		}
		if ( $user && ! empty( $user->id ) ) {
			SimpleVPBot_State::set( (int) $user->id, 'admin_log_page', array( 'off' => $off ) );
		}
		$nav = array();
		if ( $off > 0 ) {
			$nav[] = array( 'text' => '◀ لاگ قبلی' );
		}
		if ( $cnt >= $lim ) {
			$nav[] = array( 'text' => 'لاگ بعدی ▶' );
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

	/**
	 * @param array<string, mixed> $ctx With user.
	 */
	private static function send_inbounds_list( $platform, $chat_id, $ctx ) {
		SimpleVPBot_Handler_Admin_Inbound::send_inbounds_list( $platform, $chat_id, $ctx );
	}

	/**
	 * List inbounds for one 3x-ui panel (sets svp_ibctx_{user} for follow-up callbacks).
	 *
	 * @param string               $platform Platform.
	 * @param int                  $chat_id  Chat id.
	 * @param array<string, mixed> $ctx      Context.
	 * @param int                  $panel_id 0 = legacy settings panel; else svp_panels.id.
	 */
	private static function send_inbounds_list_for_panel( $platform, $chat_id, $ctx, $panel_id ) {
		SimpleVPBot_Handler_Admin_Inbound::send_inbounds_list_for_panel( $platform, $chat_id, $ctx, $panel_id );
	}

	/**
	 * @param array<string, mixed> $ctx Context with user.
	 */
	private static function send_inbound_clients( $platform, $chat_id, $inbound_id, $ctx ) {
		SimpleVPBot_Handler_Admin_Inbound::send_clients( $platform, $chat_id, $inbound_id, $ctx );
	}

	/**
	 * @param array<string, mixed> $ctx With user.
	 * @param int                $idx Index in stored emails.
	 */
	private static function start_inbound_link( array $ctx, $idx ) {
		SimpleVPBot_Handler_Admin_Inbound::start_link( $ctx, $idx );
	}

	/**
	 * Run legacy pnl:* handler from a synthetic callback string (Reply UI).
	 *
	 * @param array<string, mixed> $ctx platform, chat_id, user, from_id?.
	 * @param string               $data e.g. pnl:bk:run.
	 */
	public static function dispatch_reply_as_callback( array $ctx, $data ) {
		$subctx           = $ctx;
		$subctx['parts']  = explode( ':', (string) $data );
		$subctx['msg_id'] = 0;
		self::handle( $subctx );
	}

	/**
	 * Match reply label with numeric placeholder ({id}, {n}, #{id}).
	 *
	 * @param string      $text        Incoming text.
	 * @param object|null $user        Admin user (locale).
	 * @param string      $key         i18n key.
	 * @param string      $default     FA/default template.
	 * @return int|null Matched id or null.
	 */
	private static function match_l10n_id_button( $text, $user, $key, $default ) {
		$labels = array( $default );
		if ( $user && is_object( $user ) ) {
			$labels[] = SimpleVPBot_Texts::get_for_user( $key, $user, $default );
		}
		$labels = array_values( array_unique( array_filter( $labels ) ) );
		foreach ( $labels as $tmpl ) {
			foreach ( array( '{id}', '{n}', '#{id}' ) as $ph ) {
				if ( false === strpos( $tmpl, $ph ) ) {
					continue;
				}
				$parts = explode( $ph, $tmpl, 2 );
				if ( 2 !== count( $parts ) ) {
					continue;
				}
				$re = '/^' . preg_quote( $parts[0], '/' ) . '(\d+)' . preg_quote( $parts[1], '/' ) . '$/u';
				if ( preg_match( $re, $text, $m ) ) {
					return (int) $m[1];
				}
			}
		}
		return null;
	}

	/**
	 * Match exact localized label (no placeholders).
	 *
	 * @param string      $text    Incoming text.
	 * @param object|null $user    Admin user.
	 * @param string      $key     i18n key.
	 * @param string      $default Default label.
	 * @return bool
	 */
	private static function text_is_l10n( $text, $user, $key, $default ) {
		if ( $user && is_object( $user ) && $text === SimpleVPBot_Texts::get_for_user( $key, $user, $default ) ) {
			return true;
		}
		return $text === $default;
	}

	/**
	 * Admin Reply routes (admin_mode). Return true if handled.
	 *
	 * @param array<string, mixed> $ctx platform, chat_id, user, text, from_id?.
	 */
	public static function route_menu_text( array $ctx ): bool {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = isset( $ctx['user'] ) ? $ctx['user'] : null;
		$text     = trim( (string) $ctx['text'] );
		$from_id = isset( $ctx['from_id'] ) ? (int) $ctx['from_id'] : 0;
		if ( ! $from_id && ! empty( $ctx['from'] ) && is_array( $ctx['from'] ) ) {
			$from_id = (int) ( $ctx['from']['id'] ?? 0 );
		}
		if ( '' === $text || ! $user ) {
			return false;
		}
		if ( $text === SimpleVPBot_Keyboards::admin_back_main_label() ) {
			self::send_hub( $platform, $chat_id );
			return true;
		}
		$pt = SimpleVPBot_Texts::get( 'btn.admin.send_my_portal', '🌐 ارسال لینک پنل وب من' );
		if ( $text === $pt ) {
			$me = ( 'bale' === $platform ) ? SimpleVPBot_Model_User::find_by_bale( $chat_id ) : SimpleVPBot_Model_User::find_by_telegram( $chat_id );
			if ( $me && (int) $me->id > 0 ) {
				$u = SimpleVPBot_Portal_Link::build_url( (int) $me->id );
				if ( '' !== $u ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.portal_link_prefix', $platform, $chat_id, array( 'url' => $u ) ) );
					return true;
				}
			}
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.portal_link_unset', $platform, $chat_id ) );
			return true;
		}
		$at = SimpleVPBot_Texts::get( 'btn.admin.send_admin_portal', '🖥 ارسال لینک پنل ادمین وب' );
		if ( $text === $at ) {
			$me = ( 'bale' === $platform ) ? SimpleVPBot_Model_User::find_by_bale( $chat_id ) : SimpleVPBot_Model_User::find_by_telegram( $chat_id );
			if ( $me && (int) $me->id > 0 ) {
				$u = SimpleVPBot_Portal_Link::build_admin_url( (int) $me->id );
				if ( '' !== $u ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.admin_portal_link_prefix', $platform, $chat_id, array( 'url' => $u ) ) );
					return true;
				}
			}
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.admin_panel_unset', $platform, $chat_id ) );
			return true;
		}
		if ( class_exists( 'SimpleVPBot_UI_Reply_Router' ) && SimpleVPBot_UI_Reply_Router::try_dispatch_hub_action( $ctx ) ) {
			return true;
		}
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) || ! SimpleVPBot_Bot_Reseller_Scope::reseller_blocks_global_settings() ) {
			$s  = SimpleVPBot_Settings::all();
			$bk = class_exists( 'SimpleVPBot_Handler_Admin_Backup' )
				? SimpleVPBot_Handler_Admin_Backup::reply_label_map( $s, $user )
				: array();
			if ( isset( $bk[ $text ] ) ) {
				self::dispatch_reply_as_callback( $ctx, $bk[ $text ] );
				return true;
			}
		}
		$tn = SimpleVPBot_Keyboards::strip_glass_prefix( SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
		$bulk_days = self::match_l10n_id_button( $tn, $user, 'btn.admin.bulk_days', '+{n} روز' );
		if ( null !== $bulk_days ) {
			self::send_bulk_days_confirm( $platform, $chat_id, $bulk_days );
			return true;
		}
		if ( preg_match( '/^\+(\d+) روز$/u', $tn, $m ) ) {
			self::send_bulk_days_confirm( $platform, $chat_id, (int) $m[1] );
			return true;
		}
		$bulk_gb = self::match_l10n_id_button( $tn, $user, 'btn.admin.bulk_gb', '+{n} GB' );
		if ( null !== $bulk_gb ) {
			self::send_bulk_gb_confirm( $platform, $chat_id, $bulk_gb );
			return true;
		}
		if ( preg_match( '/^\+(\d+) GB$/u', $tn, $m ) ) {
			self::send_bulk_gb_confirm( $platform, $chat_id, (int) $m[1] );
			return true;
		}
		$confirm_days = self::match_l10n_id_button( $tn, $user, 'msg.admin.bulk_confirm_days', '✅ تأیید +{n} روز' );
		if ( null !== $confirm_days && class_exists( 'SimpleVPBot_Admin_User_Ops' ) ) {
			if ( self::bot_admin_deny_site_bulk( $platform, $chat_id ) ) {
				return true;
			}
			$d = max( 1, $confirm_days );
			$r = SimpleVPBot_Admin_User_Ops::bulk_extend_days( $d, true, 200 );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				self::admin_msg( 'msg.admin.bulk_days_done', $platform, $chat_id, array( 'days' => $d, 'done' => (int) $r['done'], 'errors' => (int) $r['errors'] ) ),
				array( 'reply_markup' => self::reply_markup_main_for_chat( $platform, $chat_id ) )
			);
			return true;
		}
		if ( preg_match( '/^✅ تأیید\+(\d+) روز$/u', $tn, $m ) && class_exists( 'SimpleVPBot_Admin_User_Ops' ) ) {
			if ( self::bot_admin_deny_site_bulk( $platform, $chat_id ) ) {
				return true;
			}
			$d = max( 1, (int) $m[1] );
			$r = SimpleVPBot_Admin_User_Ops::bulk_extend_days( $d, true, 200 );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				self::admin_msg( 'msg.admin.bulk_days_done', $platform, $chat_id, array( 'days' => $d, 'done' => (int) $r['done'], 'errors' => (int) $r['errors'] ) ),
				array( 'reply_markup' => self::reply_markup_main_for_chat( $platform, $chat_id ) )
			);
			return true;
		}
		$confirm_gb = self::match_l10n_id_button( $tn, $user, 'msg.admin.bulk_confirm_gb', '✅ تأیید +{n} GB' );
		if ( null !== $confirm_gb && class_exists( 'SimpleVPBot_Admin_User_Ops' ) ) {
			if ( self::bot_admin_deny_site_bulk( $platform, $chat_id ) ) {
				return true;
			}
			$g = max( 1, $confirm_gb );
			$r = SimpleVPBot_Admin_User_Ops::bulk_add_volume( $g, 200 );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				self::admin_msg( 'msg.admin.bulk_gb_done', $platform, $chat_id, array( 'gb' => $g, 'done' => (int) $r['done'], 'errors' => (int) $r['errors'] ) ),
				array( 'reply_markup' => self::reply_markup_main_for_chat( $platform, $chat_id ) )
			);
			return true;
		}
		if ( preg_match( '/^✅ تأیید\+(\d+) GB$/u', $tn, $m ) && class_exists( 'SimpleVPBot_Admin_User_Ops' ) ) {
			if ( self::bot_admin_deny_site_bulk( $platform, $chat_id ) ) {
				return true;
			}
			$g = max( 1, (int) $m[1] );
			$r = SimpleVPBot_Admin_User_Ops::bulk_add_volume( $g, 200 );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				self::admin_msg( 'msg.admin.bulk_gb_done', $platform, $chat_id, array( 'gb' => $g, 'done' => (int) $r['done'], 'errors' => (int) $r['errors'] ) ),
				array( 'reply_markup' => self::reply_markup_main_for_chat( $platform, $chat_id ) )
			);
			return true;
		}
		if ( '❌ لغو گروهی' === $text || ( $user && $text === SimpleVPBot_Texts::get_for_user( 'msg.admin.bulk_cancel', $user ) ) ) {
			self::send_hub( $platform, $chat_id );
			return true;
		}
		if ( $user && $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.catalog.new_category', $user ) ) {
			SimpleVPBot_Handler_Admin_Settings::start_catalog_wizard( $ctx, 'pc' );
			return true;
		}
		if ( $user && $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.catalog.new_plan', $user ) ) {
			SimpleVPBot_Handler_Admin_Settings::start_catalog_wizard( $ctx, 'pl' );
			return true;
		}
		if ( $user && $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.catalog.new_card', $user ) ) {
			SimpleVPBot_Handler_Admin_Settings::start_catalog_wizard( $ctx, 'cd' );
			return true;
		}
		if ( $user && $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.catalog.new_l2tp', $user, '➕ سرور جدید (خطی)' ) ) {
			SimpleVPBot_Handler_Admin_Settings::start_catalog_wizard( $ctx, 'l2' );
			return true;
		}
		if ( preg_match( '/^([✓✗])\s+#(\d+)\s+/u', $text, $m ) ) {
			$id = (int) $m[2];
			if ( class_exists( 'SimpleVPBot_Handler_Admin_Catalog' ) ) {
				if ( SimpleVPBot_Model_Plan_Category::find( $id ) ) {
					SimpleVPBot_Handler_Admin_Catalog::dispatch_legacy( $ctx, 't', 'pc', $id );
					return true;
				}
				if ( SimpleVPBot_Model_Plan::find( $id ) ) {
					SimpleVPBot_Handler_Admin_Catalog::dispatch_legacy( $ctx, 't', 'pl', $id );
					return true;
				}
				if ( SimpleVPBot_Model_Card::find( $id ) ) {
					SimpleVPBot_Handler_Admin_Catalog::dispatch_legacy( $ctx, 't', 'cd', $id );
					return true;
				}
			}
		}
		$uid_approve = self::match_l10n_id_button( $tn, $user, 'btn.admin.user_approve', '✅ کاربر {id}' );
		if ( null !== $uid_approve && class_exists( 'SimpleVPBot_Handler_Callback' ) ) {
			$from = isset( $ctx['from'] ) && is_array( $ctx['from'] ) ? $ctx['from'] : array();
			if ( empty( $from['id'] ) && $from_id > 0 ) {
				$from['id'] = $from_id;
			}
			SimpleVPBot_Handler_Callback::admin_apply_registration( $platform, 'a', $uid_approve, $from, $chat_id );
			return true;
		}
		$uid_reject = self::match_l10n_id_button( $tn, $user, 'btn.admin.user_reject', '❌ کاربر {id}' );
		if ( null !== $uid_reject && class_exists( 'SimpleVPBot_Handler_Callback' ) ) {
			$from = isset( $ctx['from'] ) && is_array( $ctx['from'] ) ? $ctx['from'] : array();
			if ( empty( $from['id'] ) && $from_id > 0 ) {
				$from['id'] = $from_id;
			}
			SimpleVPBot_Handler_Callback::admin_apply_registration( $platform, 'r', $uid_reject, $from, $chat_id );
			return true;
		}
		if ( self::text_is_l10n( $text, $user, 'btn.admin.users_approved_list', '✅ لیست تأییدشده‌ها' ) ) {
			self::send_approved_users_page( $platform, $chat_id, 0, 0 );
			return true;
		}
		if ( self::text_is_l10n( $text, $user, 'btn.admin.users_approved_next', 'تأییدشده بعدی ▶' ) ) {
			self::send_approved_users_page( $platform, $chat_id, 5, 0 );
			return true;
		}
		if ( self::text_is_l10n( $text, $user, 'btn.admin.users_approved_prev', '◀ تأییدشده قبلی' ) ) {
			self::send_approved_users_page( $platform, $chat_id, 0, 0 );
			return true;
		}
		if ( self::text_is_l10n( $text, $user, 'btn.admin.users_pending_next', 'انتظار بعدی ▶' ) ) {
			self::send_pending_users_page( $platform, $chat_id, 5, 0 );
			return true;
		}
		if ( self::text_is_l10n( $text, $user, 'btn.admin.users_pending_prev', '◀ انتظار قبلی' ) ) {
			self::send_pending_users_page( $platform, $chat_id, 0, 0 );
			return true;
		}
		if ( self::text_is_l10n( $text, $user, 'btn.admin.logs_next', 'لاگ بعدی ▶' ) ) {
			if ( self::bot_admin_deny_reseller_global( $platform, $chat_id, $user ? (int) $user->id : 0 ) ) {
				return true;
			}
			$d   = ( $user && 'admin_log_page' === (string) $user->state ) ? SimpleVPBot_State::data( $user ) : array();
			$off = isset( $d['off'] ) ? (int) $d['off'] + 8 : 8;
			self::send_logs_page( $platform, $chat_id, $off, $user );
			return true;
		}
		if ( self::text_is_l10n( $text, $user, 'btn.admin.logs_prev', '◀ لاگ قبلی' ) ) {
			if ( self::bot_admin_deny_reseller_global( $platform, $chat_id, $user ? (int) $user->id : 0 ) ) {
				return true;
			}
			$d   = ( $user && 'admin_log_page' === (string) $user->state ) ? SimpleVPBot_State::data( $user ) : array();
			$off = isset( $d['off'] ) ? max( 0, (int) $d['off'] - 8 ) : 0;
			self::send_logs_page( $platform, $chat_id, $off, $user );
			return true;
		}
		if ( self::text_is_l10n( $text, $user, 'btn.admin.texts_next', 'متن بعدی ▶' ) && $user ) {
			if ( self::bot_admin_deny_reseller_global( $platform, $chat_id, (int) $user->id ) ) {
				return true;
			}
			$d = SimpleVPBot_State::data( $user );
			$off = isset( $d['off'] ) ? (int) $d['off'] + 8 : 8;
			self::send_text_keys_page( $platform, $chat_id, $off, $user );
			return true;
		}
		if ( self::text_is_l10n( $text, $user, 'btn.admin.texts_prev', '◀ متن قبلی' ) && $user ) {
			if ( self::bot_admin_deny_reseller_global( $platform, $chat_id, (int) $user->id ) ) {
				return true;
			}
			$d   = SimpleVPBot_State::data( $user );
			$off = max( 0, ( isset( $d['off'] ) ? (int) $d['off'] : 8 ) - 8 );
			self::send_text_keys_page( $platform, $chat_id, $off, $user );
			return true;
		}
		if ( $user && self::text_is_l10n( $text, $user, 'btn.admin.texts_reset_all', '🔄 همه به پیش‌فرض' ) ) {
			if ( ! SimpleVPBot_Router::is_platform_admin( $platform, $from_id ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.texts_reset_denied', $platform, $chat_id ) );
				return true;
			}
			if ( self::bot_admin_deny_reseller_global( $platform, $chat_id, (int) $user->id ) ) {
				return true;
			}
			SimpleVPBot_Activator::reset_texts_to_defaults();
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::admin_msg( 'msg.admin.texts_reset_ok', $platform, $chat_id ) );
			self::send_text_keys_page( $platform, $chat_id, 0, $user );
			return true;
		}
		if ( preg_match( '/^👁 ([a-f0-9]{8})\s/u', $text, $m ) || preg_match( '/^👁 ([a-f0-9]{8})\s/u', $tn, $m ) ) {
			self::dispatch_reply_as_callback( $ctx, 'pnl:tv:' . $m[1] );
			return true;
		}
		if ( preg_match( '/^✏ ([a-f0-9]{8})$/u', $text, $m ) || preg_match( '/^✏ ([a-f0-9]{8})$/u', $tn, $m ) ) {
			self::dispatch_reply_as_callback( $ctx, 'pnl:th:' . $m[1] );
			return true;
		}
		$panel_id = self::match_l10n_id_button( $tn, $user, 'btn.admin.inbound_panel', '📡 پنل #{id}' );
		if ( null === $panel_id && preg_match( '/^📡 پنل #(\d+)/u', $tn, $m ) ) {
			$panel_id = (int) $m[1];
		}
		if ( null !== $panel_id ) {
			self::send_inbounds_list_for_panel( $platform, $chat_id, $ctx, $panel_id );
			return true;
		}
		$inbound_id = self::match_l10n_id_button( $tn, $user, 'btn.admin.inbound_pick', '📌 Inbound #{id}' );
		if ( null === $inbound_id && preg_match( '/^📌 Inbound #(\d+)/u', $tn, $m ) ) {
			$inbound_id = (int) $m[1];
		}
		if ( null !== $inbound_id ) {
			self::send_inbound_clients( $platform, $chat_id, $inbound_id, $ctx );
			return true;
		}
		if ( preg_match( '/^📧(\d+)·/u', $tn, $m ) ) {
			self::start_inbound_link( $ctx, (int) $m[1] );
			return true;
		}
		$autolink_id = self::match_l10n_id_button( $tn, $user, 'btn.admin.inbound_autolink', '⚡ autolink #{id}' );
		if ( null !== $autolink_id ) {
			self::dispatch_reply_as_callback( $ctx, 'pnl:ib:k:' . $autolink_id );
			return true;
		}
		if ( self::text_is_l10n( $text, $user, 'btn.admin.inbound_back_list', '↩ لیست Inbound' ) ) {
			self::dispatch_reply_as_callback( $ctx, 'pnl:ib:l' );
			return true;
		}
		$l2_test = self::match_l10n_id_button( $tn, $user, 'btn.admin.l2_test', 'L2 تست {id}' );
		if ( null !== $l2_test ) {
			self::dispatch_reply_as_callback( $ctx, 'pnl:ll:' . $l2_test );
			return true;
		}
		$l2_toggle = self::match_l10n_id_button( $tn, $user, 'btn.admin.l2_toggle', 'L2 سوییچ {id}' );
		if ( null !== $l2_toggle ) {
			self::dispatch_reply_as_callback( $ctx, 'pnl:l2:g:' . $l2_toggle );
			return true;
		}
		$l2_delete = self::match_l10n_id_button( $tn, $user, 'btn.admin.l2_delete', 'L2 حذف {id}' );
		if ( null !== $l2_delete ) {
			self::dispatch_reply_as_callback( $ctx, 'pnl:l2:d:' . $l2_delete );
			return true;
		}
		$portal_uid = self::match_l10n_id_button( $tn, $user, 'btn.admin.user_portal_link', '🌐 لینک پورتال کاربر #{id}' );
		if ( null !== $portal_uid ) {
			if ( ! self::bot_admin_guard_user( $platform, $chat_id, $portal_uid ) ) {
				return true;
			}
			$url = SimpleVPBot_Portal_Link::build_url( $portal_uid );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '' !== $url ? $url : self::admin_msg( 'msg.admin.link_empty', $platform, $chat_id ) );
			return true;
		}
		$block_uid = self::match_l10n_id_button( $tn, $user, 'btn.admin.user_block', '⛔ بلاک #{id}' );
		if ( null !== $block_uid ) {
			self::dispatch_reply_as_callback( $ctx, 'pnl:blk:' . $block_uid );
			return true;
		}
		$unblock_uid = self::match_l10n_id_button( $tn, $user, 'btn.admin.user_unblock', '✅ آنبلاک #{id}' );
		if ( null !== $unblock_uid ) {
			self::dispatch_reply_as_callback( $ctx, 'pnl:ub:' . $unblock_uid );
			return true;
		}
		$create_uid = self::match_l10n_id_button( $tn, $user, 'btn.admin.user_create_service', '➕ ساخت سرویس برای #{id}' );
		if ( null !== $create_uid ) {
			self::dispatch_reply_as_callback( $ctx, 'pnl:hcs:' . $create_uid );
			return true;
		}
		$renew_sid = self::match_l10n_id_button( $tn, $user, 'btn.admin.service_renew', '♻️ تمدید سرویس #{id}' );
		if ( null !== $renew_sid ) {
			self::dispatch_reply_as_callback( $ctx, 'pnl:ar:' . $renew_sid );
			return true;
		}
		$vol_sid = self::match_l10n_id_button( $tn, $user, 'btn.admin.service_add_volume', '➕ حجم سرویس #{id}' );
		if ( null !== $vol_sid ) {
			self::dispatch_reply_as_callback( $ctx, 'pnl:av:' . $vol_sid );
			return true;
		}
		$detail_sid = self::match_l10n_id_button( $tn, $user, 'btn.admin.service_details', '🖥 جزئیات #{id}' );
		if ( null !== $detail_sid ) {
			self::bot_admin_delegate_service_callback( $platform, $chat_id, $from_id, $detail_sid, 'm' );
			return true;
		}
		$usage_sid = self::match_l10n_id_button( $tn, $user, 'btn.admin.service_usage', '📊 مصرف #{id}' );
		if ( null !== $usage_sid ) {
			self::bot_admin_delegate_service_callback( $platform, $chat_id, $from_id, $usage_sid, 'us' );
			return true;
		}
		$config_sid = self::match_l10n_id_button( $tn, $user, 'btn.admin.service_config', '🔗 کانفیگ #{id}' );
		if ( null !== $config_sid ) {
			self::bot_admin_delegate_service_callback( $platform, $chat_id, $from_id, $config_sid, 'l' );
			return true;
		}
		$key_sid = self::match_l10n_id_button( $tn, $user, 'btn.admin.service_key', '🔑 کلید #{id}' );
		if ( null !== $key_sid ) {
			self::bot_admin_delegate_service_callback( $platform, $chat_id, $from_id, $key_sid, 'k' );
			return true;
		}
		$servers_sid = self::match_l10n_id_button( $tn, $user, 'btn.admin.service_servers', '🔄 سرورها #{id}' );
		if ( null !== $servers_sid ) {
			self::bot_admin_delegate_service_callback( $platform, $chat_id, $from_id, $servers_sid, 'u' );
			return true;
		}
		$rename_sid = self::match_l10n_id_button( $tn, $user, 'btn.admin.service_rename', '✏️ نام #{id}' );
		if ( null !== $rename_sid ) {
			self::bot_admin_delegate_service_callback( $platform, $chat_id, $from_id, $rename_sid, 'rn' );
			return true;
		}
		$note_sid = self::match_l10n_id_button( $tn, $user, 'btn.admin.service_note', '📝 یادداشت #{id}' );
		if ( null !== $note_sid ) {
			self::bot_admin_delegate_service_callback( $platform, $chat_id, $from_id, $note_sid, 'n' );
			return true;
		}
		$alerts_sid = self::match_l10n_id_button( $tn, $user, 'btn.admin.service_alerts', '🔔 هشدار #{id}' );
		if ( null !== $alerts_sid ) {
			self::bot_admin_delegate_service_callback( $platform, $chat_id, $from_id, $alerts_sid, 'al' );
			return true;
		}
		$transfer_sid = self::match_l10n_id_button( $tn, $user, 'btn.admin.service_transfer', '🎁 انتقال سرویس #{id}' );
		if ( null !== $transfer_sid ) {
			self::dispatch_reply_as_callback( $ctx, 'pnl:stx:' . $transfer_sid );
			return true;
		}
		$pick_sid = self::match_l10n_id_button( $tn, $user, 'btn.admin.service_pick', '📡 سرویس #{id}' );
		if ( null === $pick_sid && preg_match( '/^📡 سرویس #(\d+)/u', $tn, $m ) ) {
			$pick_sid = (int) $m[1];
		}
		if ( null !== $pick_sid ) {
			self::bot_admin_delegate_service_callback( $platform, $chat_id, $from_id, $pick_sid, 'm' );
			return true;
		}
		if ( preg_match( '/^👤 pick (\d+)$/u', $tn, $m ) ) {
			self::send_user_admin_card( $platform, $chat_id, (int) $m[1] );
			return true;
		}
		return false;
	}

	/**
	 * Registration / receipt Reply buttons for platform admins (any mode).
	 *
	 * @param string               $platform Platform.
	 * @param int                  $chat_id Chat.
	 * @param int                  $from_id Platform user id.
	 * @param object               $user svp user row.
	 * @param string               $text Message text.
	 * @param array<string, mixed> $from Telegram from array.
	 * @return bool Handled.
	 */
	public static function route_moderation_reply_text( $platform, $chat_id, $from_id, $user, $text, array $from ) {
		return SimpleVPBot_Handler_Admin_Users::route_moderation_reply_text( $platform, $chat_id, $from_id, $user, $text, $from );
	}

	/**
	 * Admin display label from Telegram/Bale from array.
	 *
	 * @param array<string, mixed> $from From payload.
	 * @return string
	 */
	private static function moderation_admin_label( array $from ) {
		$uname = (string) ( $from['username'] ?? '' );
		return $uname ? '@' . $uname : (string) ( $from['first_name'] ?? '' );
	}
}
