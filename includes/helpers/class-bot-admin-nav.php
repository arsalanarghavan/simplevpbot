<?php
/**
 * Bot admin panel navigation — mirrors frontend admin-nav.ts (5 sections).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Bot_Admin_Nav
 */
class SimpleVPBot_Bot_Admin_Nav {

	/** @var array<string, array<string, mixed>> */
	const SECTIONS = array(
		'users'      => array(
			'label_key' => 'btn.admin.section.users',
			'emoji'     => '👥',
			'tabs'      => array(
				array( 'tab' => 'users', 'group' => 'users_menu' ),
				array( 'tab' => 'users_bulk', 'group' => 'users_menu' ),
				array( 'tab' => 'broadcast', 'group' => 'users_menu' ),
			),
		),
		'resellers'  => array(
			'label_key' => 'btn.admin.section.resellers',
			'emoji'     => '🏪',
			'tabs'      => array(
				array( 'tab' => 'resellers', 'group' => 'resellers_menu' ),
				array( 'tab' => 'reseller_reports', 'group' => 'resellers_menu' ),
				array( 'tab' => 'reseller_bots', 'group' => 'resellers_menu' ),
				array( 'tab' => 'reseller_xui_panels', 'group' => 'resellers_menu' ),
			),
		),
		'marketing'  => array(
			'label_key' => 'btn.admin.section.marketing',
			'emoji'     => '📣',
			'tabs'      => array(
				array( 'tab' => 'referral', 'group' => 'marketing_menu' ),
				array( 'tab' => 'marketing_lifecycle', 'group' => 'marketing_menu' ),
				array( 'tab' => 'discounts', 'group' => 'marketing_menu' ),
			),
		),
		'finance'    => array(
			'label_key' => 'btn.admin.section.finance',
			'emoji'     => '💰',
			'tabs'      => array(
				array( 'tab' => 'plans', 'group' => 'finance_menu' ),
				array( 'tab' => 'unit_economics', 'group' => 'finance_menu' ),
				array( 'tab' => 'cards', 'group' => 'finance_menu' ),
				array( 'tab' => 'receipts', 'group' => 'finance_menu' ),
				array( 'tab' => 'referral_reports', 'group' => 'finance_menu' ),
				array( 'tab' => 'reseller_charge', 'group' => 'finance_menu' ),
			),
		),
		'settings'   => array(
			'label_key' => 'btn.admin.section.settings',
			'emoji'     => '⚙️',
			'tabs'      => array(
				array( 'tab' => 'monitoring', 'group' => 'servers_menu' ),
				array( 'tab' => 'bots', 'group' => 'bot_menu' ),
				array( 'tab' => 'plan_cats', 'group' => 'bot_menu' ),
				array( 'tab' => 'texts', 'group' => 'bot_menu' ),
				array( 'tab' => 'bot_ui', 'group' => 'bot_menu' ),
				array( 'tab' => 'xui_panels', 'group' => 'servers_menu' ),
				array( 'tab' => 'configs', 'group' => 'servers_menu' ),
				array( 'tab' => 'l2tp_servers', 'group' => 'servers_menu' ),
				array( 'tab' => 'site_settings', 'group' => 'system_prefs_menu' ),
				array( 'tab' => 'backup', 'group' => 'system_prefs_menu' ),
				array( 'tab' => 'notifications', 'group' => 'system_prefs_menu' ),
				array( 'tab' => 'logs', 'group' => 'system_prefs_menu' ),
				array( 'tab' => 'audit', 'group' => 'system_prefs_menu' ),
				array( 'tab' => 'reseller_settings', 'group' => 'system_prefs_menu' ),
			),
		),
	);

	/** @var array<string, string> tab_key => btn.admin.* label key override. */
	const TAB_BTN_KEYS = array(
		'users'               => 'btn.admin.users_search',
		'users_bulk'          => 'btn.admin.bulk_short',
		'broadcast'           => 'btn.admin.broadcast',
		'receipts'            => 'btn.admin.receipts',
		'plans'               => 'btn.admin.tab.plans',
		'plan_cats'           => 'btn.admin.tab.plan_cats',
		'cards'               => 'btn.admin.tab.cards',
		'referral'            => 'btn.admin.tab.referral',
		'marketing_lifecycle' => 'btn.admin.tab.marketing_lifecycle',
		'discounts'           => 'btn.admin.tab.discounts',
		'resellers'           => 'btn.admin.tab.resellers',
		'reseller_reports'    => 'btn.admin.tab.reseller_reports',
		'reseller_bots'       => 'btn.admin.tab.reseller_bots',
		'reseller_xui_panels' => 'btn.admin.tab.reseller_xui_panels',
		'referral_reports'    => 'btn.admin.tab.referral_reports',
		'reseller_charge'     => 'btn.admin.tab.reseller_charge',
		'unit_economics'      => 'btn.admin.tab.unit_economics',
		'monitoring'          => 'btn.admin.tab.monitoring',
		'bot_ui'              => 'btn.admin.tab.bot_ui',
		'site_settings'       => 'btn.admin.tab.site_settings',
		'notifications'       => 'btn.admin.tab.notifications',
		'logs'                => 'btn.admin.tab.logs',
		'audit'               => 'btn.admin.tab.audit',
		'reseller_settings'   => 'btn.admin.tab.reseller_settings',
	);

	/**
	 * @return array<int, string>
	 */
	public static function section_ids() {
		return array_keys( self::SECTIONS );
	}

	/**
	 * @param object|null $user Admin user row.
	 * @return array<string, bool>
	 */
	public static function allowed_tabs( $user ) {
		$uid = ( $user && ! empty( $user->id ) ) ? (int) $user->id : 0;
		if ( ! class_exists( 'SimpleVPBot_Reseller_Permission_Gate' ) ) {
			return array();
		}
		return SimpleVPBot_Reseller_Permission_Gate::allowed_tabs_for_actor( $uid );
	}

	/**
	 * Sections visible for actor (≥1 allowed tab).
	 *
	 * @param object|null $user Admin user.
	 * @return array<int, string>
	 */
	public static function visible_section_ids( $user ) {
		$allowed = self::allowed_tabs( $user );
		$out     = array();
		foreach ( self::SECTIONS as $sec_id => $def ) {
			foreach ( (array) ( $def['tabs'] ?? array() ) as $entry ) {
				$tab = (string) ( $entry['tab'] ?? '' );
				if ( isset( $allowed[ $tab ] ) && true === $allowed[ $tab ] ) {
					$out[] = $sec_id;
					break;
				}
			}
		}
		return $out;
	}

	/**
	 * Allowed tabs in a section (deduped).
	 *
	 * @param string      $section_id Section id.
	 * @param object|null $user       Admin user.
	 * @return array<int, string>
	 */
	public static function tabs_in_section( $section_id, $user ) {
		$section_id = sanitize_key( (string) $section_id );
		if ( ! isset( self::SECTIONS[ $section_id ] ) ) {
			return array();
		}
		$allowed = self::allowed_tabs( $user );
		$seen    = array();
		$out     = array();
		foreach ( (array) self::SECTIONS[ $section_id ]['tabs'] as $entry ) {
			$tab = (string) ( $entry['tab'] ?? '' );
			if ( isset( $seen[ $tab ] ) ) {
				continue;
			}
			$seen[ $tab ] = true;
			if ( isset( $allowed[ $tab ] ) && true === $allowed[ $tab ] ) {
				$out[] = $tab;
			}
		}
		return $out;
	}

	/**
	 * Localized section button label.
	 *
	 * @param string      $section_id Section id.
	 * @param object|null $user       User row.
	 * @return string
	 */
	public static function section_label( $section_id, $user = null ) {
		$section_id = sanitize_key( (string) $section_id );
		if ( ! isset( self::SECTIONS[ $section_id ] ) ) {
			return '';
		}
		$def = self::SECTIONS[ $section_id ];
		$key = (string) ( $def['label_key'] ?? '' );
		$lbl = ( $user && class_exists( 'SimpleVPBot_Texts' ) )
			? SimpleVPBot_Texts::get_for_user( $key, $user, $section_id )
			: ( class_exists( 'SimpleVPBot_Texts' ) ? SimpleVPBot_Texts::get( $key, $section_id ) : $section_id );
		$emoji = (string) ( $def['emoji'] ?? '' );
		return trim( $emoji . ' ' . $lbl );
	}

	/**
	 * Localized tab button label.
	 *
	 * @param string      $tab_key Tab key.
	 * @param object|null $user    User row.
	 * @return string
	 */
	public static function tab_label( $tab_key, $user = null ) {
		$tab_key = sanitize_key( (string) $tab_key );
		$btn_key = isset( self::TAB_BTN_KEYS[ $tab_key ] ) ? self::TAB_BTN_KEYS[ $tab_key ] : '';
		if ( '' !== $btn_key && class_exists( 'SimpleVPBot_Texts' ) ) {
			return ( $user && is_object( $user ) )
				? SimpleVPBot_Texts::get_for_user( $btn_key, $user )
				: SimpleVPBot_Texts::get( $btn_key, $tab_key );
		}
		return $tab_key;
	}

	/**
	 * Intro text key for section or tab.
	 *
	 * @param string $kind section|tab.
	 * @param string $id   Section or tab id.
	 * @return string
	 */
	public static function intro_key( $kind, $id ) {
		$id = sanitize_key( (string) $id );
		if ( 'section' === $kind ) {
			return 'msg.admin.section.' . $id . '.intro';
		}
		return 'msg.admin.tutorial.' . $id;
	}

	/**
	 * Resolve section id from button text.
	 *
	 * @param string      $text Button text.
	 * @param object|null $user User row.
	 * @return string Empty if no match.
	 */
	public static function match_section_from_text( $text, $user ) {
		$text = trim( (string) $text );
		foreach ( self::section_ids() as $sec_id ) {
			$lbl = self::section_label( $sec_id, $user );
			if ( $text === $lbl || SimpleVPBot_Keyboards::strip_glass_prefix( $text ) === SimpleVPBot_Keyboards::strip_glass_prefix( $lbl ) ) {
				return $sec_id;
			}
		}
		return '';
	}

	/**
	 * Resolve tab key from button text within visible tabs.
	 *
	 * @param string      $text Button text.
	 * @param object|null $user User row.
	 * @return string
	 */
	public static function match_tab_from_text( $text, $user ) {
		$text    = trim( (string) $text );
		$allowed = self::allowed_tabs( $user );
		foreach ( array_keys( $allowed ) as $tab ) {
			if ( ! $allowed[ $tab ] ) {
				continue;
			}
			$lbl = self::tab_label( $tab, $user );
			if ( $text === $lbl || SimpleVPBot_Keyboards::strip_glass_prefix( $text ) === SimpleVPBot_Keyboards::strip_glass_prefix( $lbl ) ) {
				return $tab;
			}
		}
		// Legacy button labels.
		if ( class_exists( 'SimpleVPBot_Texts' ) && $user ) {
			$legacy = array(
				'users'      => array( 'btn.admin.users_search', 'btn.admin.users_queue', 'btn.admin.users' ),
				'users_bulk' => array( 'btn.admin.bulk_short' ),
				'broadcast'  => array( 'btn.admin.broadcast' ),
				'receipts'   => array( 'btn.admin.receipts' ),
				'plans'      => array( 'btn.admin.settings' ),
			);
			foreach ( $legacy as $tab => $keys ) {
				foreach ( $keys as $k ) {
					if ( $text === SimpleVPBot_Texts::get_for_user( $k, $user ) ) {
						return $tab;
					}
				}
			}
		}
		return '';
	}

	/**
	 * Legacy hub code for tab if any.
	 *
	 * @param string $tab_key Tab key.
	 * @return string
	 * @deprecated Use Handler_Admin_Panel::open_tab native routes.
	 */
	public static function hub_code_for_tab( $tab_key ) {
		return '';
	}

	/**
	 * Find section containing tab.
	 *
	 * @param string $tab_key Tab key.
	 * @return string
	 */
	public static function section_for_tab( $tab_key ) {
		$tab_key = sanitize_key( (string) $tab_key );
		foreach ( self::SECTIONS as $sec_id => $def ) {
			foreach ( (array) ( $def['tabs'] ?? array() ) as $entry ) {
				if ( $tab_key === (string) ( $entry['tab'] ?? '' ) ) {
					return $sec_id;
				}
			}
		}
		return '';
	}

	/**
	 * Whether text matches any admin panel section/tab/back label (frees wizard navigation).
	 *
	 * @param string      $text Button text.
	 * @param object|null $user User row.
	 * @return bool
	 */
	public static function is_admin_nav_text( $text, $user ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return false;
		}
		if ( '' !== self::match_section_from_text( $text, $user ) ) {
			return true;
		}
		if ( '' !== self::match_tab_from_text( $text, $user ) ) {
			return true;
		}
		if ( ! $user || ! class_exists( 'SimpleVPBot_Texts' ) ) {
			return false;
		}
		$back_keys = array( 'btn.admin.back_panel', 'btn.admin.back_section', 'btn.admin.back_menu' );
		foreach ( $back_keys as $k ) {
			if ( $text === SimpleVPBot_Texts::get_for_user( $k, $user ) ) {
				return true;
			}
		}
		return false;
	}
}
