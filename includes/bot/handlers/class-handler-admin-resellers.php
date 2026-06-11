<?php
/**
 * Bot admin — resellers section handlers.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Admin_Resellers
 */
class SimpleVPBot_Handler_Admin_Resellers {

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     Admin user.
	 * @param string $tab_key  Tab key.
	 * @return bool
	 */
	public static function open_tab( $platform, $chat_id, $user, $tab_key ) {
		$tab_key = sanitize_key( (string) $tab_key );
		if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $user->id, 'reseller_list' ) && in_array( $tab_key, array( 'resellers', 'reseller_reports' ), true ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
			return true;
		}
		switch ( $tab_key ) {
			case 'resellers':
				self::send_resellers_list( $platform, $chat_id, $user, 0 );
				return true;
			case 'reseller_reports':
				self::send_reseller_reports( $platform, $chat_id, $user );
				return true;
			case 'reseller_bots':
				self::send_reseller_bots( $platform, $chat_id, $user );
				return true;
			case 'reseller_xui_panels':
				self::send_reseller_xui_panels( $platform, $chat_id, $user, 0 );
				return true;
		}
		return false;
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 * @return bool
	 */
	public static function open_reseller_settings( $platform, $chat_id, $user ) {
		$perm_actor = SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id );
		if ( $perm_actor < 1 ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_tab', $user )
			);
			return true;
		}
		$perms = SimpleVPBot_Model_User::reseller_permissions( $perm_actor );
		$lines = array();
		foreach ( SimpleVPBot_Model_User::RESELLER_PERMISSION_KEYS as $k ) {
			$lines[] = '• ' . $k . ': ' . ( ! empty( $perms[ $k ] ) ? '✅' : '❌' );
		}
		$prof = class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' )
			? SimpleVPBot_Model_Reseller_Bot_Profile::find_by_reseller( $perm_actor )
			: null;
		$body = SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.tutorial.reseller_settings', $user );
		$body .= "\n\n" . implode( "\n", $lines );
		if ( $prof ) {
			$body .= "\n\n🤖 ربات: " . ( ! empty( $prof->enabled ) ? 'فعال' : 'غیرفعال' );
			$body .= "\n@" . (string) ( $prof->telegram_bot_username ?? '' );
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$body,
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_panel_section_reply( 'settings', $user ) )
		);
		return true;
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 * @param int    $offset   Offset.
	 */
	private static function send_resellers_list( $platform, $chat_id, $user, $offset ) {
		global $wpdb;
		$tbl    = $wpdb->prefix . 'svp_users';
		$limit  = 10;
		$off    = max( 0, (int) $offset );
		$perm_actor = SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id );
		$where  = "role = 'reseller' AND status = 'approved'";
		$vals   = array();
		if ( $perm_actor > 0 && class_exists( 'SimpleVPBot_Admin_Reseller_Reports' ) ) {
			$ids = SimpleVPBot_Admin_Reseller_Reports::downline_reseller_ids_for( $perm_actor );
			if ( empty( $ids ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.resellers_empty', $user ) );
				return;
			}
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$where       .= " AND id IN ({$placeholders})";
			$vals         = array_map( 'intval', $ids );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl} WHERE {$where}", $vals ) );
		$sql   = "SELECT id, tg_username, bale_username, balance FROM {$tbl} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d";
		$qvals = array_merge( $vals, array( $limit, $off ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $qvals ) );
		$lines = array( SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.resellers_list_header', $user, array( 'total' => (string) $total ) ) );
		foreach ( (array) $rows as $r ) {
			$label = SimpleVPBot_Model_User::label( $r );
			$lines[] = '#' . (int) $r->id . ' · ' . $label . ' · ' . number_format( (float) $r->balance );
		}
		if ( empty( $rows ) ) {
			$lines[] = SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.resellers_empty', $user );
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			implode( "\n", $lines ),
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_panel_section_reply( 'resellers', $user ) )
		);
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 */
	private static function send_reseller_reports( $platform, $chat_id, $user ) {
		$perm_actor = SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id );
		$actor      = $perm_actor > 0 ? $perm_actor : (int) $user->id;
		$body       = SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.tutorial.reseller_reports', $user );
		if ( class_exists( 'SimpleVPBot_Admin_Reseller_Reports' ) ) {
			$sum = SimpleVPBot_Admin_Reseller_Reports::build_actor_summary( $actor, 30 );
			if ( is_array( $sum ) ) {
				$body .= "\n\n📊 ۳۰ روز اخیر:";
				$labels = array(
					'sales_toman'     => 'msg.admin.report.sales_toman',
					'wholesale_toman' => 'msg.admin.report.wholesale_toman',
					'margin_est'      => 'msg.admin.report.margin_est',
					'downline_users'  => 'msg.admin.report.downline_users',
				);
				foreach ( $labels as $k => $msg_key ) {
					if ( isset( $sum[ $k ] ) ) {
						$lbl = SimpleVPBot_Bot_Admin_Texts::msg( $msg_key, $user, array(), $k );
						$body .= "\n• " . $lbl . ': ' . (string) $sum[ $k ];
					}
				}
			}
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$body,
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_panel_section_reply( 'resellers', $user ) )
		);
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 */
	private static function send_reseller_bots( $platform, $chat_id, $user ) {
		if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $user->id, 'reseller_bot_manage' ) ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user )
			);
			return;
		}
		$perm_actor = SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id );
		$body       = SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.tutorial.reseller_bots', $user );
		if ( $perm_actor > 0 && class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			$p = SimpleVPBot_Model_Reseller_Bot_Profile::find_by_reseller( $perm_actor );
			if ( $p ) {
				$body .= "\n\nوضعیت: " . ( ! empty( $p->enabled ) ? '✅ فعال' : '❌ غیرفعال' );
				$body .= "\nTelegram: @" . (string) ( $p->telegram_bot_username ?? '—' );
				$body .= "\nBale: @" . (string) ( $p->bale_bot_username ?? '—' );
				$body .= "\nWebhook: " . ( ! empty( $p->webhook_set ) ? 'تنظیم‌شده' : 'خالی' );
			}
		} elseif ( class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			$rows = SimpleVPBot_Model_Reseller_Bot_Profile::list_resellers_bot_admin_paginated( 8, 0 );
			foreach ( (array) $rows as $row ) {
				$body .= "\n• #" . (int) ( $row->reseller_svp_user_id ?? 0 ) . ' @' . (string) ( $row->telegram_bot_username ?? '' );
			}
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$body,
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_panel_section_reply( 'resellers', $user ) )
		);
	}

	/**
	 * Site-admin paginated list of XUI panels and reseller access counts.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 * @param int    $offset   List offset.
	 */
	private static function send_reseller_xui_panels( $platform, $chat_id, $user, $offset ) {
		$perm_actor = SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id );
		if ( $perm_actor > 0 ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_tab', $user ),
				array( 'reply_markup' => SimpleVPBot_Keyboards::admin_panel_section_reply( 'resellers', $user ) )
			);
			return;
		}
		$limit  = 8;
		$off    = max( 0, (int) $offset );
		$panels = class_exists( 'SimpleVPBot_Model_Panel' ) ? SimpleVPBot_Model_Panel::all_ordered() : array();
		$total  = count( $panels );
		$slice  = array_slice( $panels, $off, $limit );
		$counts = array();
		if ( class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' ) ) {
			global $wpdb;
			$t = SimpleVPBot_Model_Reseller_Panel_Price::table();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$count_rows = $wpdb->get_results(
				"SELECT panel_id, COUNT(DISTINCT reseller_svp_user_id) AS cnt FROM {$t} WHERE panel_access = 1 OR price_per_gb > 0 GROUP BY panel_id"
			);
			foreach ( (array) $count_rows as $cr ) {
				if ( ! is_object( $cr ) ) {
					continue;
				}
				$counts[ (int) ( $cr->panel_id ?? 0 ) ] = (int) ( $cr->cnt ?? 0 );
			}
		}
		$body = SimpleVPBot_Bot_Admin_Texts::msg(
			'msg.admin.reseller_xui_panels_header',
			$user,
			array(
				'total'  => (string) $total,
				'offset' => (string) ( $off + 1 ),
				'end'    => (string) min( $off + $limit, $total ),
			)
		);
		if ( empty( $slice ) ) {
			$body .= "\n" . SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.reseller_xui_panels_empty', $user );
		} else {
			foreach ( $slice as $p ) {
				$pid   = (int) ( $p->id ?? 0 );
				$label = (string) ( $p->label ?? $p->name ?? ( '#' . $pid ) );
				$state = ! empty( $p->active ) ? '✅' : '⏸';
				$rc    = $counts[ $pid ] ?? 0;
				$body .= "\n• #{$pid} {$label} {$state} · " . SimpleVPBot_Bot_Admin_Texts::msg(
					'msg.admin.reseller_xui_panel_resellers',
					$user,
					array( 'count' => (string) $rc )
				);
			}
			if ( $total > $off + $limit ) {
				$body .= "\n…";
			}
		}
		SimpleVPBot_State::set( (int) $user->id, 'admin_xui_panels', array( 'offset' => $off ) );
		$nav_rows = array();
		$nav      = array();
		if ( $off > 0 ) {
			$nav[] = array( 'text' => SimpleVPBot_Texts::get_for_user( 'btn.admin.xui_panels_prev', $user ) );
		}
		if ( $total > $off + $limit ) {
			$nav[] = array( 'text' => SimpleVPBot_Texts::get_for_user( 'btn.admin.xui_panels_next', $user ) );
		}
		if ( $nav ) {
			$nav_rows[] = $nav;
		}
		$perm_actor_site = SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id );
		if ( $perm_actor_site < 1 ) {
			$nav_rows[] = array( array( 'text' => SimpleVPBot_Texts::get_for_user( 'btn.admin.xui_panel_assign', $user ) ) );
		}
		$back = SimpleVPBot_Keyboards::admin_panel_section_reply( 'resellers', $user );
		if ( $nav_rows && isset( $back['keyboard'] ) && is_array( $back['keyboard'] ) ) {
			$back = SimpleVPBot_Keyboards::admin_reply_wrap_rows( array_merge( $nav_rows, $back['keyboard'] ) );
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$body,
			array( 'reply_markup' => $back )
		);
	}

	/**
	 * Route reseller section reply actions (XUI pagination, assign).
	 *
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
		$prev = SimpleVPBot_Texts::get_for_user( 'btn.admin.xui_panels_prev', $user );
		$next = SimpleVPBot_Texts::get_for_user( 'btn.admin.xui_panels_next', $user );
		if ( $text === $prev || $text === $next ) {
			$d   = ( 'admin_xui_panels' === (string) $user->state ) ? SimpleVPBot_State::data( $user ) : array();
			$off = isset( $d['offset'] ) ? (int) $d['offset'] : 0;
			if ( $text === $next ) {
				$off += 8;
			} else {
				$off = max( 0, $off - 8 );
			}
			self::send_reseller_xui_panels( $platform, $chat_id, $user, $off );
			return true;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.xui_panel_assign', $user ) ) {
			if ( SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id ) > 0 ) {
				return false;
			}
			SimpleVPBot_State::set( (int) $user->id, 'admin_xui_assign', array( 'step' => 'reseller_id' ) );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_xui_assign_reseller', $user )
			);
			return true;
		}
		if ( 'admin_xui_assign' === (string) $user->state ) {
			return self::route_xui_assign( $ctx );
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	private static function route_xui_assign( array $ctx ) {
		$user     = $ctx['user'];
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$text     = trim( (string) $ctx['text'] );
		$data     = SimpleVPBot_State::data( $user );
		$data     = is_array( $data ) ? $data : array();
		$step     = (string) ( $data['step'] ?? '' );
		if ( 'reseller_id' === $step ) {
			$rid = (int) SimpleVPBot_Bot_Runtime::normalize_digits( $text );
			if ( $rid < 1 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.reseller_id_invalid', $user ) );
				return true;
			}
			$row = SimpleVPBot_Model_User::find( $rid );
			if ( ! $row || ! SimpleVPBot_Model_User::is_reseller_row( $row ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.reseller_not_found', $user ) );
				return true;
			}
			$data['reseller_id'] = $rid;
			$data['step']        = 'panel_id';
			SimpleVPBot_State::set( (int) $user->id, 'admin_xui_assign', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_xui_assign_panel', $user ) );
			return true;
		}
		if ( 'panel_id' === $step ) {
			$pid = (int) SimpleVPBot_Bot_Runtime::normalize_digits( $text );
			if ( $pid < 1 || ! class_exists( 'SimpleVPBot_Model_Panel' ) || ! SimpleVPBot_Model_Panel::find( $pid ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.panel_id_invalid', $user ) );
				return true;
			}
			$data['panel_id'] = $pid;
			$data['step']     = 'price';
			SimpleVPBot_State::set( (int) $user->id, 'admin_xui_assign', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_xui_assign_price', $user ) );
			return true;
		}
		if ( 'price' === $step ) {
			$price = (float) str_replace( ',', '.', SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
			$rid   = (int) ( $data['reseller_id'] ?? 0 );
			$pid   = (int) ( $data['panel_id'] ?? 0 );
			if ( $rid < 1 || $pid < 1 || ! class_exists( 'SimpleVPBot_Bot_Admin_Mutate' ) ) {
				SimpleVPBot_State::clear( (int) $user->id );
				return true;
			}
			$rows = array();
			if ( class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' ) ) {
				$found = false;
				foreach ( SimpleVPBot_Model_Reseller_Panel_Price::list_for_reseller( $rid ) as $ex ) {
					if ( ! is_object( $ex ) ) {
						continue;
					}
					$epid = (int) ( $ex->panel_id ?? 0 );
					if ( $epid < 1 ) {
						continue;
					}
					if ( $epid === $pid ) {
						$found = true;
						$rows[] = array(
							'panel_id'     => $pid,
							'price_per_gb' => $price,
							'panel_access' => 1,
						);
					} else {
						$rows[] = array(
							'panel_id'     => $epid,
							'price_per_gb' => (float) ( $ex->price_per_gb ?? 0 ),
							'panel_access' => (int) ( $ex->panel_access ?? 1 ),
						);
					}
				}
				if ( ! $found ) {
					$rows[] = array(
						'panel_id'     => $pid,
						'price_per_gb' => $price,
						'panel_access' => 1,
					);
				}
			} else {
				$rows[] = array(
					'panel_id'     => $pid,
					'price_per_gb' => $price,
					'panel_access' => 1,
				);
			}
			$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
				(int) $user->id,
				'reseller_panel_prices_save',
				array(
					'reseller_svp_user_id' => $rid,
					'rows'                 => $rows,
				)
			);
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Mutate::result_message( $user, is_array( $result ) ? $result : array( 'ok' => false ) )
			);
			return true;
		}
		return false;
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 * @deprecated Kept for backward compatibility; use send_reseller_xui_panels().
	 */
	private static function send_reseller_xui_hint( $platform, $chat_id, $user ) {
		self::send_reseller_xui_panels( $platform, $chat_id, $user, 0 );
	}
}
