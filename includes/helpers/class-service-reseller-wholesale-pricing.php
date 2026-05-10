<?php
/**
 * Tiered wholesale floor + accruals for reseller catalog lines.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Service_Reseller_Wholesale_Pricing
 */
class SimpleVPBot_Service_Reseller_Wholesale_Pricing {

	/**
	 * Whether tier thresholds are met at given cumulative totals.
	 *
	 * @param object $tier      Tier row.
	 * @param float  $tot_gb    Cumulative GB.
	 * @param float  $tot_toman Cumulative wholesale tomans.
	 * @return bool
	 */
	public static function tier_matches( $tier, $tot_gb, $tot_toman ) {
		if ( ! $tier || ! is_object( $tier ) ) {
			return false;
		}
		$mg = (int) ( $tier->min_total_gb ?? 0 );
		$mt = (float) ( $tier->min_total_toman ?? 0 );
		if ( $mg > 0 && $tot_gb + 0.0001 < (float) $mg ) {
			return false;
		}
		if ( $mt > 0 && $tot_toman + 0.01 < $mt ) {
			return false;
		}
		return true;
	}

	/**
	 * Highest tier satisfied by totals (sort_order ascending scan; last match wins).
	 *
	 * @param array<int, object> $tiers     Sorted tiers.
	 * @param float              $tot_gb    Total GB.
	 * @param float              $tot_toman Total wholesale tomans.
	 * @return object|null Winning tier row.
	 */
	public static function pick_effective_tier( array $tiers, $tot_gb, $tot_toman ) {
		$winner = null;
		foreach ( $tiers as $t ) {
			if ( self::tier_matches( $t, $tot_gb, $tot_toman ) ) {
				$winner = $t;
			}
		}
		return $winner;
	}

	/**
	 * Unit wholesale price (toman/GB) for next purchase given current cumulative totals.
	 *
	 * @param int $reseller_svp_user_id Reseller id.
	 * @param int $line_id              Wholesale line id.
	 * @return float
	 */
	public static function effective_unit_price( $reseller_svp_user_id, $line_id ) {
		$tot = SimpleVPBot_Model_Reseller_Wholesale_Accrual::totals( (int) $reseller_svp_user_id, (int) $line_id );
		$tiers = SimpleVPBot_Model_Reseller_Wholesale_Tier::by_line( (int) $line_id );
		if ( empty( $tiers ) ) {
			return 0.0;
		}
		$tier = self::pick_effective_tier( $tiers, (float) $tot['gb'], (float) $tot['toman'] );
		return $tier ? (float) ( $tier->price_per_gb ?? 0 ) : 0.0;
	}

	/**
	 * Apply wholesale line routing fields into sanitized plan row (reseller actor).
	 *
	 * @param int                             $actor    Reseller svp_users.id.
	 * @param array<string, int|float|string|null> $row_data Plan row (mutated).
	 * @return array{ok:bool, code?:string}
	 */
	public static function apply_line_to_plan_row( $actor, array &$row_data ) {
		$actor = (int) $actor;
		$lid   = isset( $row_data['wholesale_line_id'] ) ? (int) $row_data['wholesale_line_id'] : 0;
		if ( $actor < 1 || $lid < 1 ) {
			return array( 'ok' => true );
		}
		if ( ! SimpleVPBot_Model_Reseller_Wholesale_Assignment::is_assigned( $actor, $lid ) ) {
			return array( 'ok' => false, 'code' => 'wholesale_line_not_assigned' );
		}
		$line = SimpleVPBot_Model_Reseller_Wholesale_Line::find( $lid );
		if ( ! $line || ! (int) ( $line->active ?? 0 ) ) {
			return array( 'ok' => false, 'code' => 'wholesale_line_invalid' );
		}
		$row_data['panel_id'] = max( 1, (int) ( $line->panel_id ?? 1 ) );
		$dstype               = isset( $line->default_service_type ) ? sanitize_key( (string) $line->default_service_type ) : 'xray';
		if ( ! in_array( $dstype, array( 'xray', 'l2tp' ), true ) ) {
			$dstype = 'xray';
		}
		$row_data['service_type'] = $dstype;
		if ( 'l2tp' === $dstype ) {
			$row_data['inbound_id'] = 0;
			$l2                     = max( 0, (int) ( $line->default_l2tp_server_id ?? 0 ) );
			$row_data['l2tp_server_id'] = $l2 > 0 ? $l2 : null;
		} else {
			$row_data['inbound_id']     = max( 0, (int) ( $line->default_inbound_id ?? 0 ) );
			$row_data['l2tp_server_id'] = null;
		}
		return array( 'ok' => true );
	}

	/**
	 * Minimum retail unit / fixed price floor from wholesale tiers (+ optional parent floor).
	 *
	 * @param int $reseller_svp_user_id Reseller.
	 * @param int $line_id              Line id (or 0 to skip).
	 * @param int $panel_id             Panel for legacy parent floor.
	 * @return float Effective min price per GB (or interpreted for fixed plans externally).
	 */
	public static function wholesale_floor_unit( $reseller_svp_user_id, $line_id, $panel_id = 1 ) {
		$actor = (int) $reseller_svp_user_id;
		$lid   = (int) $line_id;
		$unit  = 0.0;
		if ( $lid > 0 ) {
			$unit = self::effective_unit_price( $actor, $lid );
		}
		if ( $unit <= 0 && class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' ) ) {
			$unit = (float) SimpleVPBot_Model_Reseller_Panel_Price::get_unit_price( $actor, (int) $panel_id );
		}
		$parent_floor = 0.0;
		if ( $actor > 0 && class_exists( 'SimpleVPBot_Model_User' ) && class_exists( 'SimpleVPBot_Model_Reseller_Parent_Panel_Floor' ) ) {
			$u = SimpleVPBot_Model_User::find( $actor );
			$parent_id = $u ? (int) ( $u->invited_by ?? 0 ) : 0;
			if ( $parent_id > 0 ) {
				$parent_floor = SimpleVPBot_Model_Reseller_Parent_Panel_Floor::get_min_price( $parent_id, $actor, (int) $panel_id );
			}
		}
		return max( $unit, (float) $parent_floor );
	}

	/**
	 * Ladder snapshot for dashboard UI.
	 *
	 * @param int $reseller_svp_user_id Reseller.
	 * @param int $line_id              Line.
	 * @return array<string, mixed>
	 */
	public static function ladder_snapshot( $reseller_svp_user_id, $line_id ) {
		$r = (int) $reseller_svp_user_id;
		$l = (int) $line_id;
		$tot = SimpleVPBot_Model_Reseller_Wholesale_Accrual::totals( $r, $l );
		$tiers = SimpleVPBot_Model_Reseller_Wholesale_Tier::by_line( $l );
		$tg = (float) $tot['gb'];
		$tt = (float) $tot['toman'];
		$current = self::pick_effective_tier( $tiers, $tg, $tt );
		$next    = null;
		foreach ( $tiers as $t ) {
			if ( ! self::tier_matches( $t, $tg, $tt ) ) {
				$next = $t;
				break;
			}
		}
		$gb_need = null;
		$toman_need = null;
		if ( $next ) {
			$nmg = (int) ( $next->min_total_gb ?? 0 );
			$nmt = (float) ( $next->min_total_toman ?? 0 );
			if ( $nmg > 0 ) {
				$gb_need = max( 0, $nmg - $tg );
			}
			if ( $nmt > 0 ) {
				$toman_need = max( 0.0, $nmt - $tt );
			}
		}
		return array(
			'total_gb'             => $tg,
			'total_wholesale_toman' => $tt,
			'current_tier_id'      => $current ? (int) $current->id : null,
			'current_price_per_gb' => $current ? (float) $current->price_per_gb : null,
			'next_tier_id'         => $next ? (int) $next->id : null,
			'next_price_per_gb'    => $next ? (float) $next->price_per_gb : null,
			'gb_to_next_tier'      => $gb_need,
			'toman_to_next_tier'   => $toman_need,
			'tiers'                => array_map(
				static function ( $t ) {
					return array(
						'id'               => (int) $t->id,
						'sort_order'       => (int) $t->sort_order,
						'price_per_gb'     => (float) $t->price_per_gb,
						'min_total_gb'     => (int) $t->min_total_gb,
						'min_total_toman'  => (float) $t->min_total_toman,
					);
				},
				$tiers
			),
		);
	}

	/**
	 * Record accrual after approved purchase transaction (dedup by transaction_id).
	 *
	 * @param object $tx Transaction row.
	 */
	public static function maybe_record_accrual_from_transaction( $tx ) {
		if ( ! $tx || ! is_object( $tx ) ) {
			return;
		}
		if ( 'purchase' !== (string) ( $tx->type ?? '' ) || 'approved' !== (string) ( $tx->status ?? '' ) ) {
			return;
		}
		$meta = json_decode( (string) ( $tx->meta_json ?? '{}' ), true );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		$tx_id = (int) $tx->id;
		$plan_id = 0;
		$delta_gb = 0;
		$service_id = isset( $tx->service_id ) ? (int) $tx->service_id : 0;

		if ( ! empty( $meta['intent'] ) && 'add_volume' === (string) $meta['intent'] ) {
			$sid = isset( $meta['service_id'] ) ? (int) $meta['service_id'] : $service_id;
			if ( $sid < 1 ) {
				return;
			}
			$svc = SimpleVPBot_Model_Service::find_any( $sid );
			if ( ! $svc ) {
				return;
			}
			$plan_id = (int) ( $svc->plan_id ?? 0 );
			$delta_gb = max( 0, (int) ( $meta['extra_gb'] ?? $meta['volume_gb'] ?? $meta['gb'] ?? 0 ) );
		} elseif ( ! empty( $meta['plan_id'] ) ) {
			$plan_id = (int) $meta['plan_id'];
			$plan = SimpleVPBot_Model_Plan::find( $plan_id );
			if ( ! $plan ) {
				return;
			}
			if ( SimpleVPBot_Model_Plan::is_per_gb( $plan ) ) {
				$delta_gb = max( 0, (int) ( $meta['volume_gb'] ?? 0 ) );
			} else {
				$delta_gb = max( 0, (int) ( $plan->traffic_gb ?? 0 ) );
			}
		} else {
			return;
		}

		if ( $plan_id < 1 || $delta_gb < 1 ) {
			return;
		}

		$plan = SimpleVPBot_Model_Plan::find( $plan_id );
		if ( ! $plan ) {
			return;
		}
		$owner = (int) ( $plan->owner_svp_user_id ?? 0 );
		if ( $owner < 1 ) {
			return;
		}
		$owner_row = SimpleVPBot_Model_User::find( $owner );
		if ( ! $owner_row || ! SimpleVPBot_Model_User::is_reseller_row( $owner_row ) ) {
			return;
		}
		$line_id = (int) ( $plan->wholesale_line_id ?? 0 );
		if ( $line_id < 1 ) {
			return;
		}
		if ( ! SimpleVPBot_Model_Reseller_Wholesale_Assignment::is_assigned( $owner, $line_id ) ) {
			return;
		}

		$tot_before = SimpleVPBot_Model_Reseller_Wholesale_Accrual::totals( $owner, $line_id );
		$unit       = self::effective_unit_price( $owner, $line_id );
		if ( $unit <= 0 ) {
			$tiers = SimpleVPBot_Model_Reseller_Wholesale_Tier::by_line( $line_id );
			$tier  = self::pick_effective_tier( $tiers, (float) $tot_before['gb'], (float) $tot_before['toman'] );
			$unit  = $tier ? (float) $tier->price_per_gb : 0.0;
		}
		if ( $unit <= 0 ) {
			return;
		}

		$cost = round( $unit * $delta_gb, 2 );
		SimpleVPBot_Model_Reseller_Wholesale_Accrual::insert_if_new_tx(
			array(
				'reseller_svp_user_id'  => $owner,
				'line_id'               => $line_id,
				'delta_gb'              => $delta_gb,
				'delta_wholesale_toman' => $cost,
				'unit_price_applied'    => $unit,
				'transaction_id'        => $tx_id,
				'service_id'          => $service_id > 0 ? $service_id : null,
			)
		);
	}
}
