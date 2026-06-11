<?php
/**
 * Bot admin panel — /panel landing, 5-section nav, tab routing.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Admin_Panel
 */
class SimpleVPBot_Handler_Admin_Panel {

	/**
	 * Enable admin mode and send panel landing (KPI + 5 sections).
	 *
	 * @param string $platform telegram|bale.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     Admin user row.
	 */
	public static function send_panel_entry( $platform, $chat_id, $user ) {
		SimpleVPBot_State::clear( (int) $user->id );
		SimpleVPBot_Model_User::update( (int) $user->id, array( 'admin_mode' => 1 ) );
		$user = SimpleVPBot_Model_User::find( (int) $user->id );
		if ( ! $user ) {
			return;
		}
		self::send_landing( $platform, $chat_id, $user );
	}

	/**
	 * Panel landing with KPI summary.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     Admin user.
	 */
	public static function send_landing( $platform, $chat_id, $user ) {
		$body = self::landing_text( $user );
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$body,
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_panel_main_reply( $user ) )
		);
	}

	/**
	 * @param object $user Admin user.
	 * @return string
	 */
	public static function landing_text( $user ) {
		$intro = SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.panel_welcome', $user );
		if ( class_exists( 'SimpleVPBot_Admin_Dashboard_Stats' ) ) {
			$stats = SimpleVPBot_Handler_Admin_Stats::text_for_chat( 0, (int) $user->id );
			if ( is_string( $stats ) && '' !== trim( $stats ) ) {
				$intro .= "\n\n" . trim( $stats );
			}
		}
		$perm_actor = class_exists( 'SimpleVPBot_Reseller_Permission_Gate' )
			? SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id )
			: 0;
		if ( $perm_actor > 0 ) {
			$intro .= "\n\n" . SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.panel.role_reseller', $user );
		} else {
			$intro .= "\n\n" . SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.panel.role_site_admin', $user );
		}
		return $intro;
	}

	/**
	 * Open a section submenu.
	 *
	 * @param string $platform   Platform.
	 * @param int    $chat_id    Chat id.
	 * @param object $user       Admin user.
	 * @param string $section_id Section id.
	 */
	public static function send_section( $platform, $chat_id, $user, $section_id ) {
		$section_id = sanitize_key( (string) $section_id );
		$tabs       = SimpleVPBot_Bot_Admin_Nav::tabs_in_section( $section_id, $user );
		if ( empty( $tabs ) ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_tab', $user ),
				array( 'reply_markup' => SimpleVPBot_Keyboards::admin_panel_main_reply( $user ) )
			);
			return;
		}
		$key  = SimpleVPBot_Bot_Admin_Nav::intro_key( 'section', $section_id );
		$body = SimpleVPBot_Bot_Admin_Texts::msg( $key, $user );
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$body,
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_panel_section_reply( $section_id, $user ) )
		);
	}

	/**
	 * Route tab action.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     Admin user.
	 * @param string $tab_key  Tab key.
	 * @param int    $from_id  Platform user id.
	 * @return bool True if handled.
	 */
	public static function open_tab( $platform, $chat_id, $user, $tab_key, $from_id = 0 ) {
		$tab_key = sanitize_key( (string) $tab_key );
		if ( '' === $tab_key ) {
			return false;
		}
		if ( ! SimpleVPBot_Reseller_Permission_Gate::may_access_tab( (int) $user->id, $tab_key ) ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ),
				array( 'reply_markup' => SimpleVPBot_Keyboards::admin_panel_main_reply( $user ) )
			);
			return true;
		}
		$sec = SimpleVPBot_Bot_Admin_Nav::section_for_tab( $tab_key );
		if ( '' !== $sec ) {
			SimpleVPBot_State::set( (int) $user->id, 'admin_panel_section', array( 'section' => $sec, 'tab' => $tab_key ) );
		}
		if ( 'users' !== $tab_key ) {
			$skip_tutorial = array(
				'referral',
				'marketing_lifecycle',
				'discounts',
				'resellers',
				'reseller_reports',
				'reseller_bots',
				'reseller_xui_panels',
				'reseller_settings',
				'reseller_charge',
				'referral_reports',
				'unit_economics',
				'monitoring',
				'notifications',
				'logs',
				'audit',
				'bot_ui',
			);
			if ( ! in_array( $tab_key, $skip_tutorial, true ) ) {
				$tutorial = SimpleVPBot_Bot_Admin_Nav::intro_key( 'tab', $tab_key );
				$hint     = SimpleVPBot_Bot_Admin_Texts::msg( $tutorial, $user, array(), '' );
				if ( '' !== trim( $hint ) ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $hint );
				}
			}
		}
		switch ( $tab_key ) {
			case 'users':
				SimpleVPBot_State::set( (int) $user->id, 'admin_panel_users_menu', array( 'section' => 'users' ) );
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.tutorial.users', $user ),
					array( 'reply_markup' => SimpleVPBot_Keyboards::admin_users_submenu_reply( $user ) )
				);
				return true;
			case 'users_bulk':
				if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $user->id, 'users_bulk' ) ) {
					return self::deny_op( $platform, $chat_id, $user );
				}
				SimpleVPBot_Handler_Admin_Bulk::open_tab( $platform, $chat_id, $user );
				return true;
			case 'broadcast':
				if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $user->id, 'broadcast' ) ) {
					return self::deny_op( $platform, $chat_id, $user );
				}
				SimpleVPBot_State::set( (int) $user->id, 'admin_broadcast', array() );
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_broadcast', $user )
				);
				return true;
			case 'resellers':
			case 'reseller_reports':
			case 'reseller_bots':
			case 'reseller_xui_panels':
				return SimpleVPBot_Handler_Admin_Resellers::open_tab( $platform, $chat_id, $user, $tab_key );
			case 'referral':
			case 'marketing_lifecycle':
			case 'discounts':
				return SimpleVPBot_Handler_Admin_Marketing::open_tab( $platform, $chat_id, $user, $tab_key );
			case 'plans':
			case 'cards':
			case 'plan_cats':
			case 'receipts':
			case 'referral_reports':
			case 'reseller_charge':
			case 'unit_economics':
				return SimpleVPBot_Handler_Admin_Finance::open_tab( $platform, $chat_id, $user, $tab_key );
			case 'bot_ui':
				return self::open_bot_ui_web( $platform, $chat_id, $user );
			case 'reseller_settings':
				return SimpleVPBot_Handler_Admin_Resellers::open_reseller_settings( $platform, $chat_id, $user );
			case 'monitoring':
				return self::open_monitoring( $platform, $chat_id, $user );
			case 'notifications':
				return self::open_notifications( $platform, $chat_id, $user );
			case 'logs':
				SimpleVPBot_Handler_Admin_Logs::open_tab( $platform, $chat_id, $user, 0 );
				return true;
			case 'backup':
				if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $user->id, 'backup' ) ) {
					return self::deny_op( $platform, $chat_id, $user );
				}
				SimpleVPBot_Handler_Admin_Backup::open_tab( $platform, $chat_id, $user );
				return true;
			case 'texts':
				SimpleVPBot_Handler_Admin_Texts::open_tab( $platform, $chat_id, $user, 0 );
				return true;
			case 'xui_panels':
				SimpleVPBot_Handler_Admin_Inbound::open_xui_panels_tab( $platform, $chat_id, $user );
				return true;
			case 'configs':
				SimpleVPBot_Handler_Admin_Inbound::open_configs_tab( $platform, $chat_id, $user );
				return true;
			case 'l2tp_servers':
				SimpleVPBot_Handler_Admin_Inbound::open_l2tp_tab( $platform, $chat_id, $user );
				return true;
			case 'site_settings':
				SimpleVPBot_Handler_Admin_Pnl::send_submenu( $platform, $chat_id, 'gen', array( 'user' => $user ) );
				return true;
			case 'bots':
				SimpleVPBot_Handler_Admin_Pnl::send_submenu( $platform, $chat_id, 'bot', array( 'user' => $user ) );
				return true;
			case 'audit':
				return self::open_audit( $platform, $chat_id, $user );
			default:
				return false;
		}
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 * @return bool
	 */
	private static function deny_op( $platform, $chat_id, $user ) {
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ),
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_panel_main_reply( $user ) )
		);
		return true;
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 * @return bool
	 */
	private static function open_bot_ui_web( $platform, $chat_id, $user ) {
		$url = class_exists( 'SimpleVPBot_Portal_Link' ) ? SimpleVPBot_Portal_Link::build_admin_url( (int) $user->id ) : '';
		$msg = SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.tutorial.bot_ui_web_only', $user );
		if ( '' !== $url ) {
			$msg .= "\n\n🔗 " . $url;
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$msg,
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_panel_section_reply( 'settings', $user ) )
		);
		return true;
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 * @return bool
	 */
	private static function open_monitoring( $platform, $chat_id, $user ) {
		if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $user->id, 'monitoring' ) ) {
			return self::deny_op( $platform, $chat_id, $user );
		}
		global $wpdb;
		$tbl    = $wpdb->prefix . 'svp_services';
		$scope  = class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ? SimpleVPBot_Bot_Reseller_Scope::bot_admin_scope_user_ids() : null;
		if ( is_array( $scope ) && ! empty( $scope ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $scope ), '%d' ) );
			$vals         = array_map( 'intval', $scope );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$n_active = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl} WHERE status = 'active' AND user_id IN ({$placeholders})", $vals ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$n_exp = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl} WHERE status = 'expired' AND user_id IN ({$placeholders})", $vals ) );
		} else {
			$n_active = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl} WHERE status = 'active'" );
			$n_exp    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl} WHERE status = 'expired'" );
		}
		$body     = SimpleVPBot_Bot_Admin_Texts::msg(
			'msg.admin.monitoring_summary',
			$user,
			array(
				'active'  => (string) $n_active,
				'expired' => (string) $n_exp,
			)
		);
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
	 * @return bool
	 */
	private static function open_notifications( $platform, $chat_id, $user ) {
		$perm_actor = SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id );
		if ( $perm_actor > 0 ) {
			return self::deny_op( $platform, $chat_id, $user );
		}
		$s    = SimpleVPBot_Settings::all();
		$body = SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.tutorial.notifications', $user );
		$body .= "\n\n";
		$body .= '٪ کم: ' . (int) ( $s['notify_low_traffic_percent'] ?? 10 );
		$body .= ' · هم‌زمان: ' . (int) ( $s['default_concurrent_users'] ?? 2 );
		$body .= "\nهشدار روز: " . esc_html( implode( ',', (array) ( $s['notify_expiry_days'] ?? array( 3, 1 ) ) ) );
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$body,
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_notif_submenu_reply( $user ) )
		);
		return true;
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 * @return bool
	 */
	private static function open_audit( $platform, $chat_id, $user ) {
		$perm_actor = class_exists( 'SimpleVPBot_Reseller_Permission_Gate' )
			? SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id )
			: 0;
		if ( $perm_actor > 0 ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_tab', $user ),
				array( 'reply_markup' => SimpleVPBot_Keyboards::admin_panel_section_reply( 'settings', $user ) )
			);
			return true;
		}
		$lines = array();
		if ( class_exists( 'SimpleVPBot_Audit_Log' ) ) {
			$q = SimpleVPBot_Audit_Log::query( array(), 1, 8 );
			foreach ( (array) ( $q['rows'] ?? array() ) as $r ) {
				$lines[] = '• ' . (string) ( $r['event_type'] ?? '' ) . ' — ' . (string) ( $r['created_at'] ?? '' );
			}
		}
		$body = SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.tutorial.audit', $user );
		if ( ! empty( $lines ) ) {
			$body .= "\n\n" . implode( "\n", $lines );
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
	 * Route panel menu text (sections, tabs, back).
	 *
	 * @param array<string, mixed> $ctx Context with platform, chat_id, user, text.
	 * @return bool True if handled.
	 */
	public static function route_text( array $ctx ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = $ctx['user'];
		$text     = trim( (string) $ctx['text'] );

		if ( ! $user || empty( $user->id ) ) {
			return false;
		}

		$back_panel = SimpleVPBot_Texts::get_for_user( 'btn.admin.back_panel', $user );
		$back_sec   = SimpleVPBot_Texts::get_for_user( 'btn.admin.back_section', $user );
		if ( $text === $back_panel || $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.back_menu', $user ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			self::send_landing( $platform, $chat_id, $user );
			return true;
		}

		$sec = SimpleVPBot_Bot_Admin_Nav::match_section_from_text( $text, $user );
		if ( '' !== $sec ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_State::set( (int) $user->id, 'admin_panel_section', array( 'section' => $sec ) );
			self::send_section( $platform, $chat_id, $user, $sec );
			return true;
		}

		$tab = SimpleVPBot_Bot_Admin_Nav::match_tab_from_text( $text, $user );
		if ( '' !== $tab ) {
			SimpleVPBot_State::clear( (int) $user->id );
			return self::open_tab( $platform, $chat_id, $user, $tab );
		}

		if ( $text === $back_sec ) {
			$st     = SimpleVPBot_State::data( $user );
			$sec_id = ! empty( $st['section'] ) ? (string) $st['section'] : '';
			if ( '' === $sec_id && ! empty( $user->state ) && 'admin_panel_section' === (string) $user->state ) {
				$sec_id = ! empty( $st['section'] ) ? (string) $st['section'] : SimpleVPBot_Bot_Admin_Nav::section_for_tab( (string) ( $st['tab'] ?? '' ) );
			}
			if ( '' !== $sec_id ) {
				self::send_section( $platform, $chat_id, $user, $sec_id );
			} else {
				self::send_landing( $platform, $chat_id, $user );
			}
			return true;
		}

		return false;
	}
}
