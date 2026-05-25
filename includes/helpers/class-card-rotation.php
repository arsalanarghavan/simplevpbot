<?php
/**
 * Bank card checkout rotation (round-robin + random with daily-limit wrap).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Card_Rotation
 */
class SimpleVPBot_Card_Rotation {

	/**
	 * Allowed cards_display_mode values.
	 *
	 * @return array<int, string>
	 */
	public static function allowed_display_modes() {
		return array( 'list', 'sequential', 'random' );
	}

	/**
	 * Sanitize cards_display_mode setting.
	 *
	 * @param mixed $raw Raw value.
	 * @return string
	 */
	public static function sanitize_display_mode( $raw ) {
		$mode = sanitize_key( (string) $raw );
		return in_array( $mode, self::allowed_display_modes(), true ) ? $mode : 'list';
	}

	/**
	 * Owner scope key for rotation cursor (per card pool).
	 *
	 * @param int $transaction_id Transaction id.
	 * @return string
	 */
	public static function resolve_owner_scope_key( $transaction_id ) {
		$tid = (int) $transaction_id;
		$tx  = $tid > 0 && class_exists( 'SimpleVPBot_Model_Transaction' )
			? SimpleVPBot_Model_Transaction::find( $tid )
			: null;
		if ( ! $tx ) {
			return '0';
		}
		$meta = json_decode( (string) ( $tx->meta_json ?? '{}' ), true );
		if ( is_array( $meta ) && ! empty( $meta['invoice_card_owner_scope_svp_id'] ) ) {
			$scope_rid = (int) $meta['invoice_card_owner_scope_svp_id'];
			if ( $scope_rid > 0 ) {
				return 'scope:' . $scope_rid;
			}
		}
		if ( class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
			$uid = (int) ( $tx->user_id ?? 0 );
			$rid = $uid > 0 ? (int) SimpleVPBot_Reseller_Branding::nearest_reseller_id_for_user( $uid ) : 0;
			if ( $rid > 0 ) {
				return 'reseller:' . $rid;
			}
		}
		return '0';
	}

	/**
	 * Whether a card is under its daily approved limit.
	 *
	 * @param object $card Card row.
	 * @param int    $transaction_id Pending transaction id (excluded from usage sum).
	 * @return bool
	 */
	public static function is_card_eligible( $card, $transaction_id = 0 ) {
		$cid   = (int) ( is_object( $card ) ? ( $card->id ?? 0 ) : 0 );
		$limit = (float) ( is_object( $card ) ? ( $card->daily_limit ?? 0 ) : 0 );
		if ( $cid < 1 ) {
			return false;
		}
		if ( $limit <= 0 ) {
			return true;
		}
		$used = 0.0;
		if ( class_exists( 'SimpleVPBot_Model_Receipt' ) ) {
			$used = (float) SimpleVPBot_Model_Receipt::approved_sum_for_card_today( $cid, (int) $transaction_id );
		}
		return $used + 0.000001 < $limit;
	}

	/**
	 * Pick card(s) for one checkout according to display mode.
	 *
	 * @param array<int, object> $cards Ordered active cards.
	 * @param string             $mode list|sequential|random.
	 * @param string             $scope_key Rotation scope.
	 * @param int                $transaction_id Transaction id.
	 * @return array<int, object>
	 */
	public static function pick_for_checkout( array $cards, $mode, $scope_key, $transaction_id = 0 ) {
		$mode = self::sanitize_display_mode( $mode );
		if ( empty( $cards ) ) {
			return array();
		}
		if ( 'list' === $mode ) {
			return $cards;
		}
		if ( 'random' === $mode ) {
			return self::pick_random( $cards, (int) $transaction_id );
		}
		return self::pick_round_robin( $cards, (string) $scope_key, (int) $transaction_id );
	}

	/**
	 * Round-robin: start at stored cursor, skip full cards, wrap when all full.
	 *
	 * @param array<int, object> $cards Cards.
	 * @param string             $scope_key Scope key.
	 * @param int                $transaction_id Transaction id.
	 * @return array<int, object>
	 */
	private static function pick_round_robin( array $cards, $scope_key, $transaction_id ) {
		$n = count( $cards );
		if ( $n < 1 ) {
			return array();
		}
		if ( 1 === $n ) {
			return array( $cards[0] );
		}
		$start = self::get_cursor( $scope_key ) % $n;
		for ( $i = 0; $i < $n; $i++ ) {
			$idx  = ( $start + $i ) % $n;
			$card = $cards[ $idx ];
			if ( self::is_card_eligible( $card, $transaction_id ) ) {
				self::advance_cursor( $scope_key, ( $idx + 1 ) % $n );
				return array( $card );
			}
		}
		// All daily limits reached — loop: still show next in rotation and advance.
		$card = $cards[ $start ];
		self::advance_cursor( $scope_key, ( $start + 1 ) % $n );
		return array( $card );
	}

	/**
	 * Random eligible card; if all full, random from entire pool (wrap).
	 *
	 * @param array<int, object> $cards Cards.
	 * @param int                $transaction_id Transaction id.
	 * @return array<int, object>
	 */
	private static function pick_random( array $cards, $transaction_id ) {
		$eligible = array();
		foreach ( $cards as $c ) {
			if ( self::is_card_eligible( $c, $transaction_id ) ) {
				$eligible[] = $c;
			}
		}
		$pool = ! empty( $eligible ) ? $eligible : $cards;
		$idx  = self::random_index( count( $pool ) );
		return array( $pool[ $idx ] );
	}

	/**
	 * Read rotation cursor for a scope.
	 *
	 * @param string $scope_key Scope key.
	 * @return int
	 */
	public static function get_cursor( $scope_key ) {
		$key     = sanitize_key( str_replace( array( ':', '-' ), '_', (string) $scope_key ) );
		$cursors = SimpleVPBot_Settings::get( 'cards_rotation_cursors', array() );
		if ( ! is_array( $cursors ) ) {
			return 0;
		}
		return max( 0, (int) ( $cursors[ $key ] ?? 0 ) );
	}

	/**
	 * Persist rotation cursor for a scope.
	 *
	 * @param string $scope_key Scope key.
	 * @param int    $next_index Next cursor value.
	 */
	public static function advance_cursor( $scope_key, $next_index ) {
		$key     = sanitize_key( str_replace( array( ':', '-' ), '_', (string) $scope_key ) );
		$cursors = SimpleVPBot_Settings::get( 'cards_rotation_cursors', array() );
		if ( ! is_array( $cursors ) ) {
			$cursors = array();
		}
		$cursors[ $key ] = max( 0, (int) $next_index );
		SimpleVPBot_Settings::update( array( 'cards_rotation_cursors' => $cursors ) );
	}

	/**
	 * Random index in [0, count).
	 *
	 * @param int $count Pool size.
	 * @return int
	 */
	private static function random_index( $count ) {
		$count = (int) $count;
		if ( $count < 2 ) {
			return 0;
		}
		if ( function_exists( 'wp_rand' ) ) {
			return (int) wp_rand( 0, $count - 1 );
		}
		return (int) mt_rand( 0, $count - 1 );
	}
}
