<?php
/**
 * Bot admin — finance section handlers.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Admin_Finance
 */
class SimpleVPBot_Handler_Admin_Finance {

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 * @param string $tab_key  Tab.
	 * @return bool
	 */
	public static function open_tab( $platform, $chat_id, $user, $tab_key ) {
		$tab_key = sanitize_key( (string) $tab_key );
		switch ( $tab_key ) {
			case 'receipts':
				if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $user->id, 'receipt_review' ) ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
					return true;
				}
				SimpleVPBot_Handler_Admin_Receipts::send_pending_review_paged( $platform, $chat_id, 0 );
				return true;
			case 'plans':
				if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $user->id, 'plan_manage' ) ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
					return true;
				}
				SimpleVPBot_Handler_Admin_Catalog::send_list( $platform, $chat_id, $user, 'plans', 0 );
				return true;
			case 'cards':
				if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $user->id, 'card_manage' ) ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
					return true;
				}
				SimpleVPBot_Handler_Admin_Catalog::send_list( $platform, $chat_id, $user, 'cards', 0 );
				return true;
			case 'plan_cats':
				if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $user->id, 'plan_manage' ) ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
					return true;
				}
				SimpleVPBot_Handler_Admin_Catalog::send_list( $platform, $chat_id, $user, 'plan_cats', 0 );
				return true;
			case 'referral_reports':
				return self::open_referral_reports( $platform, $chat_id, $user );
			case 'reseller_charge':
				return self::open_reseller_charge( $platform, $chat_id, $user );
			case 'unit_economics':
				return self::open_unit_economics( $platform, $chat_id, $user );
		}
		return false;
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 * @return bool
	 */
	private static function open_referral_reports( $platform, $chat_id, $user ) {
		if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $user->id, 'referral_manage' ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
			return true;
		}
		global $wpdb;
		$tbl   = $wpdb->prefix . 'svp_transactions';
		$scope = class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ? SimpleVPBot_Bot_Reseller_Scope::bot_admin_scope_user_ids() : null;
		if ( is_array( $scope ) && ! empty( $scope ) ) {
			$scope_ids    = array_map( 'intval', $scope );
			$placeholders = implode( ',', array_fill( 0, count( $scope_ids ), '%d' ) );
			$base_where   = "type = 'referral_commission' AND status = 'approved' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND user_id IN ({$placeholders})";
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$sum = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM {$tbl} WHERE {$base_where}", $scope_ids ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$cnt = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl} WHERE {$base_where}", $scope_ids ) );
		} else {
			$sum = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$tbl} WHERE type = 'referral_commission' AND status = 'approved' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)" );
			$cnt = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl} WHERE type = 'referral_commission' AND status = 'approved' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)" );
		}
		$body = SimpleVPBot_Bot_Admin_Texts::msg(
			'msg.admin.referral_reports_summary',
			$user,
			array(
				'count'      => (string) $cnt,
				'commission' => number_format( $sum ),
			)
		);
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$body,
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_panel_section_reply( 'finance', $user ) )
		);
		return true;
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 * @return bool
	 */
	private static function open_reseller_charge( $platform, $chat_id, $user ) {
		return self::open_reseller_charge_filtered( $platform, $chat_id, $user, array() );
	}

	/**
	 * @param string               $platform Platform.
	 * @param int                  $chat_id  Chat id.
	 * @param object               $user     User.
	 * @param array<string, mixed> $filters  Filters.
	 * @return bool
	 */
	private static function open_reseller_charge_filtered( $platform, $chat_id, $user, array $filters ) {
		$perm_actor = SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id );
		if ( $perm_actor < 1 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_tab', $user ) );
			return true;
		}
		$row = SimpleVPBot_Model_User::find( $perm_actor );
		$bal = $row ? number_format( (float) $row->balance ) : '0';
		$body = SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.tutorial.reseller_charge', $user, array( 'balance' => $bal ) );
		$page = max( 0, (int) ( $filters['page'] ?? 0 ) );
		$charge = self::query_customer_charges( $perm_actor, $page, $filters );
		if ( ! empty( $charge['lines'] ) ) {
			$body .= "\n\n" . implode( "\n", $charge['lines'] );
		}
		$filters['page']     = $page;
		$filters['has_next'] = ! empty( $charge['has_next'] );
		$filters['total']    = (int) ( $charge['total'] ?? 0 );
		SimpleVPBot_State::set( (int) $user->id, 'admin_reseller_charges_list', $filters );
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$body,
			array(
				'reply_markup' => SimpleVPBot_Keyboards::admin_reseller_charge_reply(
					$user,
					array(
						'has_prev' => $page > 0,
						'has_next' => ! empty( $charge['has_next'] ),
					)
				),
			)
		);
		return true;
	}

	/**
	 * @param int                  $actor_uid Reseller id.
	 * @param int                  $page      Page.
	 * @param array<string, mixed> $filters   type, date_from, date_to.
	 * @return array{lines: array<int, string>, has_next: bool, total: int}
	 */
	private static function query_customer_charges( $actor_uid, $page = 0, array $filters = array() ) {
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) || ! class_exists( 'SimpleVPBot_Model_Transaction' ) ) {
			return array( 'lines' => array(), 'has_next' => false, 'total' => 0 );
		}
		$scope = SimpleVPBot_Bot_Reseller_Scope::effective_moderatable_user_ids( (int) $actor_uid );
		if ( ! is_array( $scope ) || empty( $scope ) ) {
			return array( 'lines' => array(), 'has_next' => false, 'total' => 0 );
		}
		global $wpdb;
		$in_list = implode( ',', array_map( 'intval', $scope ) );
		$lim     = 10;
		$page    = max( 0, (int) $page );
		$off     = $page * $lim;
		$type    = isset( $filters['type'] ) ? sanitize_key( (string) $filters['type'] ) : '';
		$type_sql = '';
		$date_sql = '';
		$args     = array( (int) $actor_uid );
		if ( in_array( $type, array( 'purchase', 'renew', 'volume', 'topup' ), true ) ) {
			$type_sql = ' AND t.type = %s';
			$args[]   = $type;
		}
		$df = isset( $filters['date_from'] ) ? trim( (string) $filters['date_from'] ) : '';
		$dt = isset( $filters['date_to'] ) ? trim( (string) $filters['date_to'] ) : '';
		if ( '' !== $df && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $df ) ) {
			$date_sql .= ' AND DATE(t.created_at) >= %s';
			$args[]    = $df;
		}
		if ( '' !== $dt && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $dt ) ) {
			$date_sql .= ' AND DATE(t.created_at) <= %s';
			$args[]    = $dt;
		}
		$billing = SimpleVPBot_Model_Transaction::billing_reseller_id_sql_expr( 't' );
		$tx_t    = SimpleVPBot_Model_Transaction::table();
		$where   = "t.user_id IN ({$in_list}) AND t.status = 'approved' AND {$billing} = %d{$type_sql}{$date_sql}";
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tx_t} t WHERE {$where}", $args ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.id, t.type, t.amount, t.user_id, t.created_at FROM {$tx_t} t
				WHERE {$where}
				ORDER BY t.id DESC LIMIT %d OFFSET %d",
				array_merge( $args, array( $lim, $off ) )
			)
		);
		$out = array( SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.reseller_charges_header', null ) );
		if ( $page > 0 || ( '' !== $df || '' !== $dt || '' !== $type ) || $total > 0 ) {
			$out[] = SimpleVPBot_Bot_Admin_Texts::msg(
				'msg.admin.reseller_charges_page',
				null,
				array(
					'page'  => (string) ( $page + 1 ),
					'type'  => $type ?: 'all',
					'total' => (string) $total,
				),
				'صفحه ' . ( $page + 1 ) . ' / ' . max( 1, (int) ceil( $total / $lim ) )
			);
		}
		foreach ( (array) $rows as $r ) {
			if ( ! is_object( $r ) ) {
				continue;
			}
			$out[] = '#' . (int) $r->id . ' · ' . (string) ( $r->type ?? '' ) . ' · ' . number_format( (float) ( $r->amount ?? 0 ) ) . ' · ' . substr( (string) ( $r->created_at ?? '' ), 0, 10 );
		}
		if ( empty( $rows ) ) {
			$out[] = SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.reseller_charges_empty', null, array(), '—' );
		}
		return array(
			'lines'    => $out,
			'has_next' => count( (array) $rows ) >= $lim && ( $off + $lim ) < $total,
			'total'    => $total,
		);
	}

	/**
	 * @param int                  $actor_uid Reseller id.
	 * @param int                  $page      Page.
	 * @param array<string, mixed> $filters   type, date_from, date_to.
	 * @return array<int, string>
	 */
	private static function format_customer_charges( $actor_uid, $page = 0, array $filters = array() ) {
		return self::query_customer_charges( $actor_uid, $page, $filters )['lines'];
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
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.reseller_topup', $user ) ) {
			if ( SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id ) < 1 ) {
				return false;
			}
			SimpleVPBot_State::set( (int) $user->id, 'admin_reseller_topup', array() );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_reseller_topup', $user ) );
			return true;
		}
		$filters = array(
			SimpleVPBot_Texts::get_for_user( 'btn.admin.charges_filter_all', $user )      => '',
			SimpleVPBot_Texts::get_for_user( 'btn.admin.charges_filter_purchase', $user ) => 'purchase',
			SimpleVPBot_Texts::get_for_user( 'btn.admin.charges_filter_renew', $user )    => 'renew',
			SimpleVPBot_Texts::get_for_user( 'btn.admin.charges_filter_volume', $user ) => 'volume',
			SimpleVPBot_Texts::get_for_user( 'btn.admin.charges_filter_topup', $user )    => 'topup',
		);
		if ( isset( $filters[ $text ] ) && SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id ) > 0 ) {
			$d = SimpleVPBot_State::data( $user );
			$d = is_array( $d ) ? $d : array();
			self::open_reseller_charge_filtered( $platform, $chat_id, $user, array_merge( $d, array( 'type' => $filters[ $text ], 'page' => 0 ) ) );
			return true;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.charges_filter_dates', $user, '📅 تاریخ' )
			&& SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id ) > 0 ) {
			SimpleVPBot_State::set( (int) $user->id, 'admin_reseller_charges_filter', SimpleVPBot_State::data( $user ) );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_charges_date_from', $user, array(), 'از تاریخ (YYYY-MM-DD یا -):' ) );
			return true;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.charges_prev', $user, '◀ قبلی' ) ) {
			$d = SimpleVPBot_State::data( $user );
			if ( is_array( $d ) ) {
				$page = max( 0, (int) ( $d['page'] ?? 0 ) - 1 );
				self::open_reseller_charge_filtered( $platform, $chat_id, $user, array_merge( $d, array( 'page' => $page ) ) );
				return true;
			}
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.charges_next', $user, 'بعدی ▶' ) ) {
			$d = SimpleVPBot_State::data( $user );
			if ( is_array( $d ) && ! empty( $d['has_next'] ) ) {
				$page = (int) ( $d['page'] ?? 0 ) + 1;
				self::open_reseller_charge_filtered( $platform, $chat_id, $user, array_merge( $d, array( 'page' => $page ) ) );
				return true;
			}
		}
		if ( class_exists( 'SimpleVPBot_Handler_Admin_Catalog' )
			&& SimpleVPBot_Handler_Admin_Catalog::route_text( $ctx ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	public static function route_state( array $ctx ) {
		$user = $ctx['user'];
		if ( 'admin_reseller_charges_filter' === (string) $user->state ) {
			return self::route_charges_filter( $ctx );
		}
		if ( 'admin_reseller_topup' === (string) $user->state ) {
			$platform = (string) $ctx['platform'];
			$chat_id  = (int) $ctx['chat_id'];
			$amt      = (float) str_replace( ',', '.', SimpleVPBot_Bot_Runtime::normalize_digits( trim( (string) $ctx['text'] ) ) );
			if ( $amt <= 0 || ! class_exists( 'SimpleVPBot_Bot_Admin_Mutate' ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.reseller_topup_invalid', $user ) );
				return true;
			}
			$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
				(int) $user->id,
				'reseller_wallet_topup_checkout',
				array( 'amount' => $amt )
			);
			SimpleVPBot_State::clear( (int) $user->id );
			$msg = SimpleVPBot_Bot_Admin_Mutate::result_message( $user, is_array( $result ) ? $result : array( 'ok' => false ) );
			if ( ! empty( $result['ok'] ) && ! empty( $result['notify_sent'] ) ) {
				$msg .= "\n" . SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.reseller_topup_sent', $user );
			}
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $msg );
			return true;
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	private static function route_charges_filter( array $ctx ) {
		$user     = $ctx['user'];
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$text     = trim( (string) $ctx['text'] );
		$data     = SimpleVPBot_State::get( (int) $user->id );
		$data     = is_array( $data ) ? $data : array();
		$step     = (string) ( $data['step'] ?? 'date_from' );
		if ( 'date_from' === $step ) {
			if ( '-' !== $text && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $text ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.discount_date_invalid', $user ) );
				return true;
			}
			$data['date_from'] = ( '-' === $text ) ? '' : $text;
			$data['step']      = 'date_to';
			SimpleVPBot_State::set( (int) $user->id, 'admin_reseller_charges_filter', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_charges_date_to', $user, array(), 'تا تاریخ (YYYY-MM-DD یا -):' ) );
			return true;
		}
		if ( 'date_to' === $step ) {
			if ( '-' !== $text && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $text ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.discount_date_invalid', $user ) );
				return true;
			}
			$data['date_to'] = ( '-' === $text ) ? '' : $text;
			unset( $data['step'] );
			SimpleVPBot_State::clear( (int) $user->id );
			$data['page'] = 0;
			self::open_reseller_charge_filtered( $platform, $chat_id, $user, $data );
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
	private static function open_unit_economics( $platform, $chat_id, $user ) {
		if ( class_exists( 'SimpleVPBot_Handler_Admin_Economics' ) ) {
			return SimpleVPBot_Handler_Admin_Economics::open_tab( $platform, $chat_id, $user );
		}
		if ( SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id ) > 0 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_tab', $user ) );
			return true;
		}
		$body = SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.tutorial.unit_economics', $user );
		if ( class_exists( 'SimpleVPBot_Unit_Economics_Overview' ) ) {
			$ov = SimpleVPBot_Unit_Economics_Overview::build();
			if ( is_array( $ov ) && ! empty( $ov['headline'] ) ) {
				$body .= "\n\n" . (string) $ov['headline'];
			}
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$body,
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_panel_section_reply( 'finance', $user ) )
		);
		return true;
	}
}
