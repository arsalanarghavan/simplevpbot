<?php
/**
 * Reseller bot runtime: catalog scope, checkout meta, signup binding.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Bot_Reseller_Scope
 */
class SimpleVPBot_Bot_Reseller_Scope {

	/** @var int Acting admin svp_users.id on main bot (dual-role reseller). */
	private static $acting_admin_svp_user_id = 0;

	/**
	 * Set acting admin for scope/permission on main bot (cleared each request).
	 *
	 * @param int $svp_user_id Admin user row id.
	 */
	public static function set_acting_admin_user( $svp_user_id ) {
		self::$acting_admin_svp_user_id = max( 0, (int) $svp_user_id );
	}

	/**
	 * Reseller id for bot admin scope (reseller bot context or dual-role on main bot).
	 *
	 * @return int 0 = site-wide scope.
	 */
	public static function resolve_scope_reseller_id() {
		$rid = self::active_reseller_id();
		if ( $rid > 0 ) {
			return $rid;
		}
		if ( self::$acting_admin_svp_user_id > 0 && class_exists( 'SimpleVPBot_Reseller_Permission_Gate' ) ) {
			return SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( self::$acting_admin_svp_user_id );
		}
		return 0;
	}

	/**
	 * Whether bot admin hub should apply reseller scope (reseller bot or dual-role on main bot).
	 *
	 * @return bool
	 */
	public static function is_scoped_bot_admin_context() {
		return self::resolve_scope_reseller_id() > 0;
	}

	/**
	 * Alias for plan/bootstrap naming.
	 *
	 * @param array<string, mixed> $ctx Handler context.
	 */
	public static function bootstrap_acting_admin_from_ctx( array $ctx ) {
		if ( class_exists( 'SimpleVPBot_Bot_Admin_Guard' ) ) {
			SimpleVPBot_Bot_Admin_Guard::bootstrap_acting_admin_from_ctx( $ctx );
		}
	}

	/**
	 * Active reseller svp_users.id from webhook context (0 = main bot).
	 *
	 * @return int
	 */
	public static function active_reseller_id() {
		if ( ! class_exists( 'SimpleVPBot_Bot_Context' ) || ! SimpleVPBot_Bot_Context::is_reseller_bot() ) {
			return 0;
		}
		return (int) SimpleVPBot_Bot_Context::reseller_svp_user_id();
	}

	/**
	 * Whether the current webhook request is served by a reseller bot.
	 *
	 * @return bool
	 */
	public static function is_reseller_bot_request() {
		return self::active_reseller_id() > 0;
	}

	/**
	 * Reseller bot admins must not read/write site-wide Settings from the bot hub.
	 *
	 * @return bool
	 */
	public static function reseller_blocks_global_settings() {
		return self::is_reseller_bot_request();
	}

	/**
	 * @return string
	 */
	public static function reseller_global_settings_denied_message() {
		if ( class_exists( 'SimpleVPBot_Texts' ) ) {
			return (string) SimpleVPBot_Texts::get( 'msg.reseller.global_settings_denied' );
		}
		return '⛔ Site-wide settings can only be changed from the main bot or admin dashboard.';
	}

	/**
	 * Clear settings wizard state and notify when global settings are blocked.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param int    $user_id  svp_users.id (optional).
	 * @return bool True when blocked (caller should return).
	 */
	public static function deny_global_settings_bot_action( $platform, $chat_id, $user_id = 0 ) {
		if ( ! self::reseller_blocks_global_settings() ) {
			return false;
		}
		$uid = (int) $user_id;
		if ( $uid > 0 && class_exists( 'SimpleVPBot_State' ) ) {
			SimpleVPBot_State::clear( $uid );
		}
		if ( class_exists( 'SimpleVPBot_Bot_Runtime' ) ) {
			SimpleVPBot_Bot_Runtime::send_message(
				(string) $platform,
				(int) $chat_id,
				self::reseller_global_settings_denied_message()
			);
		}
		return true;
	}

	/**
	 * Admin hub submenu codes that expose or mutate global settings.
	 *
	 * @param string $code Submenu code (gen, set, …).
	 * @return bool
	 */
	public static function reseller_hub_submenu_blocked( $code ) {
		if ( ! self::reseller_blocks_global_settings() ) {
			return false;
		}
		return in_array(
			(string) $code,
			array( 'gen', 'adv', 'bot', 'bak', 'not', 'pan', 'pay', 'log', 'l2p', 'txt', 'brd' ),
			true
		);
	}

	/**
	 * Whether a reseller may sell plans on a panel (panel_access or wholesale line).
	 *
	 * @param int $reseller_svp_user_id Reseller svp_users.id (0 = main bot / no filter).
	 * @param int $panel_id             svp_panels.id.
	 * @return bool
	 */
	public static function reseller_can_sell_on_panel_for( $reseller_svp_user_id, $panel_id ) {
		$rid = (int) $reseller_svp_user_id;
		$pid = (int) $panel_id;
		if ( $pid < 1 ) {
			return false;
		}
		if ( $rid < 1 ) {
			return true;
		}
		if ( class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' )
			&& SimpleVPBot_Model_Reseller_Panel_Price::has_panel_access( $rid, $pid ) ) {
			return true;
		}
		if ( class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Line' )
			&& SimpleVPBot_Model_Reseller_Wholesale_Line::reseller_can_use_panel( $rid, $pid ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param int $panel_id svp_panels.id.
	 * @return bool
	 */
	public static function reseller_can_sell_on_panel( $panel_id ) {
		return self::reseller_can_sell_on_panel_for( self::active_reseller_id(), $panel_id );
	}

	/**
	 * Plan/card owner ids visible on the current bot (empty = no owner filter / main bot).
	 *
	 * @return array<int, int>
	 */
	public static function catalog_owner_ids() {
		$rid = self::active_reseller_id();
		if ( $rid < 1 ) {
			return array();
		}
		return array( $rid );
	}

	/**
	 * Panel ids a reseller may sell on (panel prices + wholesale line assignments).
	 *
	 * @param int $reseller_svp_user_id Reseller svp_users.id.
	 * @return array<int, int>
	 */
	public static function allowed_panel_ids_for( $reseller_svp_user_id ) {
		$rid = (int) $reseller_svp_user_id;
		if ( $rid < 1 ) {
			return array();
		}
		$out = array();
		if ( class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' ) ) {
			foreach ( SimpleVPBot_Model_Reseller_Panel_Price::list_for_reseller( $rid ) as $row ) {
				$pid = (int) ( $row->panel_id ?? 0 );
				if ( $pid > 0 && SimpleVPBot_Model_Reseller_Panel_Price::row_allows_panel_use( $row ) ) {
					$out[] = $pid;
				}
			}
		}
		if ( class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Line' ) ) {
			foreach ( SimpleVPBot_Model_Reseller_Wholesale_Line::lines_for_reseller( $rid ) as $wl ) {
				$pid = (int) ( $wl->panel_id ?? 0 );
				if ( $pid > 0 ) {
					$out[] = $pid;
				}
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Panel ids the active reseller may sell on (from reseller_panel_prices).
	 *
	 * @return array<int, int> Empty on main bot = no panel filter.
	 */
	public static function allowed_panel_ids() {
		$rid = self::active_reseller_id();
		if ( $rid < 1 ) {
			return array();
		}
		return self::allowed_panel_ids_for( $rid );
	}

	/**
	 * @param int $panel_id svp_panels.id.
	 * @return bool
	 */
	public static function panel_allowed_in_context( $panel_id ) {
		$pid = (int) $panel_id;
		if ( $pid < 1 ) {
			return false;
		}
		$rid = self::resolve_scope_reseller_id();
		if ( $rid < 1 ) {
			return true;
		}
		$allowed = self::allowed_panel_ids_for( $rid );
		if ( empty( $allowed ) ) {
			return false;
		}
		return in_array( $pid, $allowed, true );
	}

	/**
	 * Reseller downline scope ids; always includes the reseller actor (never empty when actor valid).
	 *
	 * @param int        $reseller_svp_user_id Reseller svp_users.id.
	 * @param array|null $raw_scope_ids        Pre-fetched scope ids or null to load from model.
	 * @return array<int, int>
	 */
	public static function effective_downline_user_ids( $reseller_svp_user_id, $raw_scope_ids = null ) {
		$rid = (int) $reseller_svp_user_id;
		if ( $rid < 1 ) {
			return array();
		}
		$ids = is_array( $raw_scope_ids )
			? array_values(
				array_filter(
					array_map( 'intval', $raw_scope_ids ),
					static function ( $v ) {
						return (int) $v > 0;
					}
				)
			)
			: array();
		if ( empty( $ids ) && class_exists( 'SimpleVPBot_Model_User' ) ) {
			$ids = SimpleVPBot_Model_User::reseller_scope_user_ids( $rid );
			$ids = array_values(
				array_filter(
					array_map( 'intval', (array) $ids ),
					static function ( $v ) {
						return (int) $v > 0;
					}
				)
			);
		}
		if ( empty( $ids ) ) {
			return array( $rid );
		}
		if ( ! in_array( $rid, $ids, true ) ) {
			$ids[] = $rid;
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * User ids visible for moderation lists (downline + signup_reseller attribution).
	 *
	 * @param int        $reseller_svp_user_id Reseller svp_users.id.
	 * @param array|null $raw_scope_ids        Pre-fetched downline ids or null.
	 * @return array<int, int>
	 */
	public static function effective_moderatable_user_ids( $reseller_svp_user_id, $raw_scope_ids = null ) {
		$rid  = (int) $reseller_svp_user_id;
		$base = self::effective_downline_user_ids( $rid, $raw_scope_ids );
		if ( $rid < 1 || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return $base;
		}
		global $wpdb;
		$t     = SimpleVPBot_Model_User::table();
		$extra = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$t} WHERE signup_reseller_svp_id = %d AND id <> %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$rid,
				$rid
			)
		);
		if ( empty( $extra ) ) {
			return $base;
		}
		$merged = array_merge( $base, array_map( 'intval', (array) $extra ) );
		return array_values( array_unique( array_filter( $merged, static function ( $v ) {
			return (int) $v > 0;
		} ) ) );
	}

	/**
	 * Whether a reseller actor may moderate a target user (closure + direct invite + signup bot).
	 *
	 * @param int $reseller_svp_user_id Reseller svp_users.id.
	 * @param int $target_user_id       Target svp_users.id.
	 * @return bool
	 */
	public static function reseller_may_moderate_user_for( $reseller_svp_user_id, $target_user_id ) {
		$rid = (int) $reseller_svp_user_id;
		$uid = (int) $target_user_id;
		if ( $uid < 1 || $rid < 1 || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return false;
		}
		if ( SimpleVPBot_Model_User::reseller_can_access_user( $rid, $uid ) ) {
			return true;
		}
		$row = SimpleVPBot_Model_User::find( $uid );
		if ( ! $row ) {
			return false;
		}
		if ( (int) ( $row->invited_by ?? 0 ) === $rid ) {
			return true;
		}
		if ( (int) ( $row->signup_reseller_svp_id ?? 0 ) === $rid ) {
			return true;
		}
		return false;
	}

	/**
	 * Whether bot admin hub may act on a user in the current reseller bot context.
	 *
	 * @param int $target_user_id svp_users.id.
	 * @return bool
	 */
	public static function bot_admin_may_access_user( $target_user_id ) {
		return self::bot_admin_may_moderate_user( $target_user_id );
	}

	/**
	 * User ids visible in bot admin hub lists (null = no filter / main bot).
	 *
	 * @return array<int, int>|null
	 */
	public static function bot_admin_scope_user_ids() {
		$rid = self::resolve_scope_reseller_id();
		if ( $rid < 1 ) {
			return null;
		}
		return self::effective_moderatable_user_ids( $rid );
	}

	/**
	 * Whether bot admin may act on a service (owner in downline).
	 *
	 * @param int $service_id svp_services.id.
	 * @return bool
	 */
	public static function bot_admin_may_access_service( $service_id ) {
		$rid = self::resolve_scope_reseller_id();
		if ( $rid < 1 ) {
			return true;
		}
		$sid = (int) $service_id;
		if ( $sid < 1 || ! class_exists( 'SimpleVPBot_Model_Service' ) ) {
			return false;
		}
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc ) {
			return false;
		}
		return self::bot_admin_may_moderate_user( (int) ( $svc->user_id ?? 0 ) );
	}

	/**
	 * Whether bot admin may act on a receipt (payer in downline).
	 *
	 * @param int $receipt_id Receipt id.
	 * @return bool
	 */
	public static function bot_admin_may_access_receipt( $receipt_id ) {
		$rid = self::resolve_scope_reseller_id();
		if ( $rid < 1 ) {
			return true;
		}
		$rid_rcp = (int) $receipt_id;
		if ( $rid_rcp < 1 || ! class_exists( 'SimpleVPBot_Model_Receipt' ) ) {
			return false;
		}
		$rec = SimpleVPBot_Model_Receipt::find( $rid_rcp );
		if ( ! $rec ) {
			return false;
		}
		return self::bot_admin_may_moderate_user( (int) ( $rec->user_id ?? 0 ) );
	}

	/**
	 * User moderation scope: downline, direct invite, or signup via this reseller bot.
	 *
	 * @param int $target_user_id svp_users.id.
	 * @return bool
	 */
	public static function bot_admin_may_moderate_user( $target_user_id ) {
		$uid = (int) $target_user_id;
		if ( $uid < 1 ) {
			return false;
		}
		$rid = self::resolve_scope_reseller_id();
		if ( $rid < 1 ) {
			return true;
		}
		return self::reseller_may_moderate_user_for( $rid, $uid );
	}

	/**
	 * Site-wide bulk ops (all services) are forbidden on reseller bots.
	 *
	 * @return bool
	 */
	public static function bot_admin_site_bulk_blocked() {
		return self::resolve_scope_reseller_id() > 0;
	}

	/**
	 * Filter user search rows to bot admin scope.
	 *
	 * @param array<int, object> $users Rows from search.
	 * @return array<int, object>
	 */
	public static function filter_users_for_bot_admin_scope( array $users ) {
		$scope = self::bot_admin_scope_user_ids();
		if ( ! is_array( $scope ) ) {
			return $users;
		}
		$allowed = array_flip( array_map( 'intval', $scope ) );
		return array_values(
			array_filter(
				$users,
				static function ( $u ) use ( $allowed ) {
					return $u && is_object( $u ) && isset( $allowed[ (int) ( $u->id ?? 0 ) ] );
				}
			)
		);
	}

	/**
	 * Panel ids visible in bot admin inbound tools (null = all).
	 *
	 * @return array<int, int>|null
	 */
	public static function bot_admin_allowed_panel_ids() {
		$rid = self::resolve_scope_reseller_id();
		if ( $rid < 1 ) {
			return null;
		}
		return self::allowed_panel_ids_for( $rid );
	}

	/**
	 * Deny message when site-wide bulk is blocked.
	 *
	 * @return string
	 */
	public static function bot_admin_site_bulk_denied_message() {
		if ( class_exists( 'SimpleVPBot_Texts' ) ) {
			return (string) SimpleVPBot_Texts::get( 'msg.reseller.site_bulk_denied' );
		}
		return '⛔ Site-wide bulk operations are not available on this bot.';
	}

	/**
	 * Reseller to use for outbound bot messages (billing meta, invited_by chain, or webhook context).
	 *
	 * @param object|null          $user svp_users row.
	 * @param object|null          $tx   Transaction row (optional).
	 * @return int
	 */
	public static function resolve_reseller_id_for_notify( $user, $tx = null ) {
		if ( $tx && is_object( $tx ) ) {
			$meta = json_decode( (string) ( $tx->meta_json ?? '{}' ), true );
			if ( is_array( $meta ) && ! empty( $meta['billing_reseller_svp_id'] ) ) {
				return (int) $meta['billing_reseller_svp_id'];
			}
			if ( is_array( $meta ) && ! empty( $meta['invoice_card_owner_scope_svp_id'] ) ) {
				return (int) $meta['invoice_card_owner_scope_svp_id'];
			}
		}
		$ctx = self::active_reseller_id();
		if ( $ctx > 0 ) {
			return $ctx;
		}
		if ( $user && class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
			$rid = (int) SimpleVPBot_Reseller_Branding::nearest_reseller_id_for_user( (int) $user->id );
			if ( $rid > 0 ) {
				return $rid;
			}
		}
		if ( $user && ! empty( $user->signup_reseller_svp_id ) && class_exists( 'SimpleVPBot_Model_User' ) ) {
			$sr = (int) $user->signup_reseller_svp_id;
			if ( $sr > 0 ) {
				$res = SimpleVPBot_Model_User::find( $sr );
				if ( $res && SimpleVPBot_Model_User::is_reseller_row( $res ) ) {
					return $sr;
				}
			}
		}
		return 0;
	}

	/**
	 * Whether a plan row may be sold on the current bot request.
	 *
	 * @param object|null $plan Plan row.
	 * @return bool
	 */
	public static function plan_visible_in_context( $plan ) {
		if ( ! $plan || ! is_object( $plan ) ) {
			return false;
		}
		$rid = self::resolve_scope_reseller_id();
		if ( $rid > 0 ) {
			return self::plan_visible_for_reseller( $plan, $rid );
		}
		$owners = self::catalog_owner_ids();
		if ( ! empty( $owners ) ) {
			$oid = (int) ( $plan->owner_svp_user_id ?? 0 );
			if ( ! in_array( $oid, $owners, true ) ) {
				return false;
			}
		}
		$panel_id = (int) ( $plan->panel_id ?? 0 );
		if ( $panel_id > 0 && ! self::panel_allowed_in_context( $panel_id ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Whether a plan may be sold by a specific reseller (owner + panel access).
	 *
	 * @param object|null $plan         Plan row.
	 * @param int         $reseller_id  Reseller svp_users.id.
	 * @return bool
	 */
	public static function plan_visible_for_reseller( $plan, $reseller_id ) {
		if ( ! $plan || ! is_object( $plan ) ) {
			return false;
		}
		$rid = (int) $reseller_id;
		if ( $rid < 1 ) {
			return false;
		}
		if ( (int) ( $plan->owner_svp_user_id ?? 0 ) !== $rid ) {
			return false;
		}
		$panel_id = (int) ( $plan->panel_id ?? 0 );
		if ( $panel_id < 1 ) {
			return true;
		}
		$allowed = self::allowed_panel_ids_for( $rid );
		return ! empty( $allowed ) && in_array( $panel_id, $allowed, true );
	}

	/**
	 * Merge reseller billing/card scope into checkout transaction meta.
	 *
	 * @param array<string, mixed> $meta Existing meta.
	 * @return array<string, mixed>
	 */
	public static function enrich_checkout_meta( array $meta ) {
		$rid = self::active_reseller_id();
		if ( $rid < 1 ) {
			return $meta;
		}
		$meta['billing_reseller_svp_id']          = $rid;
		$meta['invoice_card_owner_scope_svp_id'] = $rid;
		return $meta;
	}

	/**
	 * Invoice/card billing scope for bot admin service create/renew/volume on reseller bot.
	 *
	 * @return int svp_users.id or 0 on main bot.
	 */
	public static function bot_admin_invoice_card_scope_reseller_id() {
		$rid = self::resolve_scope_reseller_id();
		if ( $rid < 1 || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return 0;
		}
		$row = SimpleVPBot_Model_User::find( $rid );
		return ( $row && SimpleVPBot_Model_User::is_reseller_row( $row ) ) ? $rid : 0;
	}

	/**
	 * Resolve invited_by for a new user on /start (ref_* wins; else reseller bot owner).
	 *
	 * @param int $ref_candidate Parsed ref_* inviter id.
	 * @return int svp_users.id or 0.
	 */
	public static function resolve_invited_by_for_signup( $ref_candidate ) {
		$ref = (int) $ref_candidate;
		if ( $ref > 0 ) {
			$bind = SimpleVPBot_Referral_Service::validate_bind_inviter_id( $ref, 0 );
			if ( $bind > 0 ) {
				return $bind;
			}
			if ( SimpleVPBot_Settings::get( 'referral_enabled', false ) ) {
				$legacy = SimpleVPBot_Referral_Service::validate_inviter_id( $ref, 0 );
				if ( $legacy > 0 ) {
					return $legacy;
				}
			}
		}
		$rid = self::active_reseller_id();
		if ( $rid < 1 ) {
			return 0;
		}
		$reseller = SimpleVPBot_Model_User::find( $rid );
		if ( ! $reseller || ! SimpleVPBot_Model_User::is_reseller_row( $reseller ) ) {
			return 0;
		}
		if ( 'approved' !== (string) ( $reseller->status ?? '' ) ) {
			return 0;
		}
		return $rid;
	}

	/**
	 * Admin chat ids for receipt/approval in current bot context.
	 *
	 * @param string $platform telegram|bale.
	 * @return array{telegram:int[],bale:int[]}
	 */
	public static function admin_ids_for_context( $platform ) {
		$platform = sanitize_key( (string) $platform );
		$tg_ids   = array_map( 'intval', (array) SimpleVPBot_Settings::get( 'admin_telegram_ids', array() ) );
		$bl_ids   = array_map( 'intval', (array) SimpleVPBot_Settings::get( 'admin_bale_ids', array() ) );

		if ( self::active_reseller_id() < 1 ) {
			return array(
				'telegram' => array_values( array_filter( $tg_ids ) ),
				'bale'     => array_values( array_filter( $bl_ids ) ),
			);
		}

		$prof = class_exists( 'SimpleVPBot_Bot_Context' ) ? SimpleVPBot_Bot_Context::reseller_profile() : null;
		$ids  = array();
		if ( $prof && class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			$raw = 'bale' === $platform
				? ( $prof->admin_bale_ids ?? '' )
				: ( $prof->admin_telegram_ids ?? '' );
			$ids = array_map( 'intval', (array) SimpleVPBot_Model_Reseller_Bot_Profile::decode_admin_ids( $raw ) );
		}
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		if ( ! empty( $ids ) ) {
			return array(
				'telegram' => 'telegram' === $platform ? $ids : array(),
				'bale'     => 'bale' === $platform ? $ids : array(),
			);
		}

		$reseller = SimpleVPBot_Model_User::find( self::active_reseller_id() );
		if ( ! $reseller ) {
			return array( 'telegram' => array(), 'bale' => array() );
		}
		$rtg = (int) ( $reseller->tg_user_id ?? 0 );
		$rbl = (int) ( $reseller->bale_user_id ?? 0 );
		return array(
			'telegram' => $rtg > 0 ? array( $rtg ) : array(),
			'bale'     => $rbl > 0 ? array( $rbl ) : array(),
		);
	}
}
