<?php
/**
 * Payment method toggles (site + per-reseller) and checkout helpers.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Payment_Methods
 */
class SimpleVPBot_Payment_Methods {

	/**
	 * Known method keys.
	 *
	 * @return array<int, string>
	 */
	public static function keys() {
		return array( 'c2c', 'crypto', 'crypto_auto', 'bale_wallet', 'site_wallet', 'wallet_topup' );
	}

	/**
	 * Default map (all enabled).
	 *
	 * @return array<string, bool>
	 */
	public static function defaults_map() {
		$out = array();
		foreach ( self::keys() as $k ) {
			$out[ $k ] = true;
		}
		return $out;
	}

	/**
	 * Sanitize a partial/full map.
	 *
	 * @param mixed $raw Raw input.
	 * @return array<string, bool>
	 */
	public static function sanitize_map( $raw ) {
		$out = self::defaults_map();
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		foreach ( self::keys() as $k ) {
			if ( array_key_exists( $k, $raw ) ) {
				$out[ $k ] = ! empty( $raw[ $k ] );
			}
		}
		return $out;
	}

	/**
	 * Site-wide payment method map.
	 *
	 * @return array<string, bool>
	 */
	public static function site_map() {
		$raw = SimpleVPBot_Settings::get( 'payment_methods', array() );
		return self::sanitize_map( is_array( $raw ) ? $raw : array() );
	}

	/**
	 * Reseller-only overrides (empty = inherit site).
	 *
	 * @param int $reseller_svp_user_id Reseller svp_users.id.
	 * @return array<string, bool>
	 */
	public static function reseller_override_map( $reseller_svp_user_id ) {
		$rid = (int) $reseller_svp_user_id;
		if ( $rid < 1 || ! class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			return array();
		}
		$row = SimpleVPBot_Model_Reseller_Bot_Profile::find_by_reseller( $rid );
		if ( ! $row || empty( $row->payment_methods_json ) ) {
			return array();
		}
		$j = json_decode( (string) $row->payment_methods_json, true );
		if ( ! is_array( $j ) ) {
			return array();
		}
		return self::sanitize_map( $j );
	}

	/**
	 * Effective map for owner scope (0 = main site bot).
	 *
	 * @param int $owner_rid Reseller owner id or 0.
	 * @return array<string, bool>
	 */
	public static function effective( $owner_rid = 0 ) {
		$map = self::site_map();
		$ov  = self::reseller_override_map( (int) $owner_rid );
		foreach ( self::keys() as $k ) {
			if ( array_key_exists( $k, $ov ) ) {
				$map[ $k ] = ! empty( $ov[ $k ] );
			}
		}
		return $map;
	}

	/**
	 * Effective map for current bot request context.
	 *
	 * @return array<string, bool>
	 */
	public static function effective_for_request() {
		return self::effective( self::resolve_owner_rid( null ) );
	}

	/**
	 * Resolve owner id from explicit value, bot context, or transaction meta.
	 *
	 * @param int|null $owner_rid Explicit owner or null for request context.
	 * @return int
	 */
	public static function resolve_owner_rid( $owner_rid = null ) {
		if ( null !== $owner_rid ) {
			return max( 0, (int) $owner_rid );
		}
		if ( class_exists( 'SimpleVPBot_Bot_Context' ) && SimpleVPBot_Bot_Context::is_reseller_bot() ) {
			return SimpleVPBot_Bot_Context::reseller_svp_user_id();
		}
		return 0;
	}

	/**
	 * Owner scope from transaction meta or request.
	 *
	 * @param object|null $tx Transaction row.
	 * @return int
	 */
	public static function resolve_owner_from_tx( $tx ) {
		if ( $tx ) {
			$meta = json_decode( (string) ( $tx->meta_json ?? '{}' ), true );
			if ( is_array( $meta ) && ! empty( $meta['invoice_card_owner_scope_svp_id'] ) ) {
				return (int) $meta['invoice_card_owner_scope_svp_id'];
			}
		}
		return self::resolve_owner_rid( null );
	}

	/**
	 * Whether a method is enabled for owner scope.
	 *
	 * @param string   $key       Method key.
	 * @param int|null $owner_rid Owner id or null for request context.
	 * @return bool
	 */
	public static function is_enabled( $key, $owner_rid = null ) {
		$key = sanitize_key( (string) $key );
		if ( ! in_array( $key, self::keys(), true ) ) {
			return false;
		}
		$rid = null === $owner_rid ? self::resolve_owner_rid( null ) : max( 0, (int) $owner_rid );
		$map = self::effective( $rid );
		return ! empty( $map[ $key ] );
	}

	/**
	 * Filter card rows by enabled card-based methods.
	 *
	 * @param array<int, object> $cards     Card rows.
	 * @param int|null           $owner_rid Owner scope.
	 * @return array<int, object>
	 */
	public static function filter_cards( array $cards, $owner_rid = null ) {
		$rid = self::resolve_owner_rid( $owner_rid );
		$map = self::effective( $rid );
		$out = array();
		foreach ( $cards as $c ) {
			if ( ! is_object( $c ) ) {
				continue;
			}
			$mk = class_exists( 'SimpleVPBot_Model_Card' )
				? SimpleVPBot_Model_Card::normalize_method_key( (string) ( $c->method_key ?? 'c2c' ) )
				: sanitize_key( (string) ( $c->method_key ?? 'c2c' ) );
			if ( ! empty( $map[ $mk ] ) ) {
				$out[] = $c;
			}
		}
		return $out;
	}

	/**
	 * Bale wallet provider token for owner scope.
	 *
	 * @param int|null $owner_rid Owner id.
	 * @return string
	 */
	public static function bale_wallet_token( $owner_rid = null ) {
		$rid = self::resolve_owner_rid( $owner_rid );
		if ( $rid > 0 && class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			$prof = SimpleVPBot_Model_Reseller_Bot_Profile::find_by_reseller( $rid );
			if ( $prof && '' !== trim( (string) ( $prof->bale_wallet_provider_token ?? '' ) ) ) {
				return (string) $prof->bale_wallet_provider_token;
			}
		}
		return (string) SimpleVPBot_Settings::get( 'bale_wallet_provider_token', '' );
	}

	/**
	 * Whether Bale wallet row should appear at checkout.
	 *
	 * @param string   $platform  telegram|bale.
	 * @param int|null $owner_rid Owner scope.
	 * @return bool
	 */
	public static function show_bale_wallet( $platform, $owner_rid = null ) {
		$rid = self::resolve_owner_rid( $owner_rid );
		if ( ! self::is_enabled( 'bale_wallet', $rid ) ) {
			return false;
		}
		if ( class_exists( 'SimpleVPBot_Platforms' ) && ! SimpleVPBot_Platforms::is_enabled( 'bale', $rid ) ) {
			return false;
		}
		if ( 'bale' !== sanitize_key( (string) $platform ) ) {
			return false;
		}
		return '' !== trim( self::bale_wallet_token( $rid ) );
	}

	/**
	 * Wallet amount already applied from site balance (meta).
	 *
	 * @param object|null $tx Transaction.
	 * @return float
	 */
	public static function wallet_applied_toman( $tx ) {
		if ( ! $tx ) {
			return 0.0;
		}
		$meta = json_decode( (string) $tx->meta_json, true );
		if ( ! is_array( $meta ) ) {
			return 0.0;
		}
		return max( 0.0, round( (float) ( $meta['wallet_applied_toman'] ?? 0 ), 2 ) );
	}

	/**
	 * Whether site wallet button may appear at checkout (full or partial).
	 *
	 * @param object|null $tx        Transaction.
	 * @param object|null $user      User row.
	 * @param int|null    $owner_rid Owner scope.
	 * @return bool
	 */
	public static function can_offer_site_wallet( $tx, $user, $owner_rid = null ) {
		$rid = self::resolve_owner_rid( $owner_rid );
		if ( ! self::is_enabled( 'site_wallet', $rid ) ) {
			return false;
		}
		if ( ! $tx || 'purchase' !== (string) $tx->type ) {
			return false;
		}
		if ( 'pending' !== (string) $tx->status ) {
			return false;
		}
		if ( self::wallet_applied_toman( $tx ) > 0 ) {
			return false;
		}
		$need = round( (float) $tx->amount, 2 );
		if ( $need <= 0 || ! $user ) {
			return false;
		}
		return round( (float) $user->balance, 2 ) > 0;
	}

	/**
	 * Whether site wallet can cover the full pending amount in one step.
	 *
	 * @param object|null $tx        Transaction.
	 * @param object|null $user      User row.
	 * @param int|null    $owner_rid Owner scope.
	 * @return bool
	 */
	public static function show_site_wallet( $tx, $user, $owner_rid = null ) {
		if ( ! self::can_offer_site_wallet( $tx, $user, $owner_rid ) ) {
			return false;
		}
		$need = round( (float) $tx->amount, 2 );
		return round( (float) $user->balance, 2 ) >= $need;
	}

	/**
	 * Whether checkout can proceed with at least one payment option.
	 *
	 * @param string      $platform  Platform.
	 * @param object|null $tx        Transaction.
	 * @param object|null $user      User row.
	 * @param int|null         $owner_rid Owner scope.
	 * @param array<int, object>|null $cards Pre-resolved cards (skips active_for_transaction).
	 * @return bool
	 */
	public static function checkout_has_any_method( $platform, $tx, $user, $owner_rid = null, $cards = null ) {
		if ( ! $tx ) {
			return false;
		}
		$rid = self::resolve_owner_rid( $owner_rid );
		$tid = (int) $tx->id;
		if ( null === $cards ) {
			$cards = class_exists( 'SimpleVPBot_Model_Card' )
				? self::filter_cards( SimpleVPBot_Model_Card::active_for_transaction( $tid, $tx ), $rid )
				: array();
		}
		if ( ! empty( $cards ) ) {
			return true;
		}
		if ( self::show_bale_wallet( $platform, $rid ) ) {
			return true;
		}
		if ( self::can_offer_site_wallet( $tx, $user, $rid ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Dashboard payload: effective map for actor.
	 *
	 * @param int  $actor_reseller_id Reseller actor id (0 = site admin).
	 * @param bool $include_site      Include site defaults alongside effective.
	 * @return array<string, mixed>
	 */
	public static function dashboard_payload( $actor_reseller_id = 0, $include_site = true ) {
		$rid = max( 0, (int) $actor_reseller_id );
		$out = array(
			'effective' => self::effective( $rid ),
		);
		if ( $include_site ) {
			$out['site'] = self::site_map();
		}
		if ( $rid > 0 ) {
			$out['resellerOverride'] = self::reseller_override_map( $rid );
		}
		return $out;
	}
}
