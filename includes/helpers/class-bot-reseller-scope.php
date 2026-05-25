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
		return '⛔ تنظیمات سراسری سایت فقط از ربات اصلی یا داشبورد مدیریت قابل تغییر است. برای ربات نماینده از داشبورد «ربات نماینده» استفاده کنید.';
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
			array( 'gen', 'adv', 'bot', 'bak', 'not', 'pan', 'pay', 'log' ),
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
		return array( 0, $rid );
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
		$out = array();
		if ( class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' ) ) {
			foreach ( SimpleVPBot_Model_Reseller_Panel_Price::list_for_reseller( $rid ) as $row ) {
				$pid = (int) ( $row->panel_id ?? 0 );
				if ( $pid > 0 && SimpleVPBot_Model_Reseller_Panel_Price::has_panel_access( $rid, $pid ) ) {
					$out[] = $pid;
				}
			}
		}
		if ( class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Line' ) ) {
			foreach ( SimpleVPBot_Model_Reseller_Wholesale_Line::lines_for_reseller( $rid ) as $wl ) {
				$pid = (int) ( $wl->panel_id ?? 0 );
				if ( $pid > 0 && SimpleVPBot_Model_Reseller_Wholesale_Line::reseller_can_use_panel( $rid, $pid ) ) {
					$out[] = $pid;
				}
			}
		}
		return array_values( array_unique( $out ) );
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
		$allowed = self::allowed_panel_ids();
		if ( empty( $allowed ) ) {
			return true;
		}
		return in_array( $pid, $allowed, true );
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
