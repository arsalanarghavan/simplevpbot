<?php
/**
 * Validate and apply discount codes to pending purchase transactions.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Discount_Service
 */
class SimpleVPBot_Discount_Service {

	/**
	 * @param array<string, mixed> $meta Transaction meta.
	 * @return float
	 */
	public static function volume_gb_from_meta( array $meta ) {
		if ( isset( $meta['volume_gb'] ) && is_numeric( $meta['volume_gb'] ) ) {
			return max( 0.0, (float) $meta['volume_gb'] );
		}
		if ( isset( $meta['vol_gb'] ) && is_numeric( $meta['vol_gb'] ) ) {
			return max( 0.0, (float) $meta['vol_gb'] );
		}
		return 0.0;
	}

	/**
	 * @param array<string, mixed> $meta Transaction meta.
	 * @return int
	 */
	public static function plan_id_from_meta( array $meta ) {
		return isset( $meta['plan_id'] ) ? max( 0, (int) $meta['plan_id'] ) : 0;
	}

	/**
	 * Map purchase meta to discount column flag name.
	 *
	 * @param array<string, mixed> $meta Transaction meta.
	 * @return string Column: allow_new_purchase|allow_renew_same|allow_add_volume|allow_add_user_slots
	 */
	public static function intent_flag_from_meta( array $meta ) {
		$intent = isset( $meta['intent'] ) ? (string) $meta['intent'] : '';
		if ( 'renew_same' === $intent ) {
			return 'allow_renew_same';
		}
		if ( 'add_volume' === $intent ) {
			return 'allow_add_volume';
		}
		if ( 'add_user_slots' === $intent ) {
			return 'allow_add_user_slots';
		}
		return 'allow_new_purchase';
	}

	/**
	 * Compute discount amount in toman (subtotal is positive toman).
	 *
	 * @param float                $subtotal Toman.
	 * @param object               $row Discount code row.
	 * @param array<string, mixed> $meta Transaction meta.
	 * @return float Discount toman (>= 0, <= subtotal).
	 */
	public static function compute_discount_toman( $subtotal, $row, array $meta = array() ) {
		$base = max( 0.0, round( (float) $subtotal, 2 ) );
		$type = (string) ( $row->discount_type ?? 'percent' );
		$val  = max( 0.0, (float) ( $row->discount_value ?? 0 ) );
		$vol  = self::volume_gb_from_meta( $meta );

		if ( 'fixed_toman' === $type ) {
			$disc = min( $base, round( $val, 2 ) );
		} elseif ( 'fixed_per_gb' === $type ) {
			if ( $vol <= 0 ) {
				return 0.0;
			}
			$disc = min( $base, round( $val * $vol, 2 ) );
		} elseif ( 'percent_per_gb' === $type ) {
			if ( $vol <= 0 ) {
				return 0.0;
			}
			$pct  = min( 100.0, $val );
			$disc = min( $base, round( $base * $pct / 100.0, 2 ) );
		} else {
			$pct  = min( 100.0, $val );
			$disc = min( $base, round( $base * $pct / 100.0, 2 ) );
		}

		if ( null !== $row->max_discount_toman && (float) $row->max_discount_toman > 0 ) {
			$disc = min( $disc, (float) $row->max_discount_toman );
		}
		return max( 0.0, round( $disc, 2 ) );
	}

	/**
	 * Validate code against a pending purchase transaction (no DB write).
	 *
	 * @param object               $tx   Transaction row.
	 * @param string               $code Raw code.
	 * @param array<string, mixed> $meta Decoded meta_json.
	 * @return array{ok:bool, reason?:string, discount_toman?:float, final_amount?:float, code_row?:object|null}
	 */
	public static function validate_for_pending_transaction( $tx, $code, array $meta ) {
		if ( ! $tx || 'pending' !== (string) $tx->status ) {
			return array( 'ok' => false, 'reason' => 'bad_status' );
		}
		if ( 'purchase' !== (string) $tx->type ) {
			return array( 'ok' => false, 'reason' => 'not_purchase' );
		}
		$owner_candidates = array( 0 );
		$uid              = (int) ( $tx->user_id ?? 0 );
		$billing_rid      = 0;
		if ( is_array( $meta ) && ! empty( $meta['billing_reseller_svp_id'] ) ) {
			$billing_rid = (int) $meta['billing_reseller_svp_id'];
		} elseif ( is_array( $meta ) && ! empty( $meta['invoice_card_owner_scope_svp_id'] ) ) {
			$billing_rid = (int) $meta['invoice_card_owner_scope_svp_id'];
		}
		if ( $billing_rid > 0 ) {
			$owner_candidates = array( $billing_rid, 0 );
		} elseif ( $uid > 0 && class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
			$rid = (int) SimpleVPBot_Reseller_Branding::nearest_reseller_id_for_user( $uid );
			if ( $rid > 0 ) {
				$owner_candidates = array( $rid, 0 );
			}
		}
		$row = SimpleVPBot_Model_Discount_Code::find_by_code_for_owners( $code, $owner_candidates );
		if ( ! $row || ! (int) $row->active ) {
			return array( 'ok' => false, 'reason' => 'invalid_code' );
		}
		$restricted = isset( $row->restricted_svp_user_id ) ? (int) $row->restricted_svp_user_id : 0;
		if ( $restricted > 0 && $uid !== $restricted ) {
			return array( 'ok' => false, 'reason' => 'user_not_allowed' );
		}
		$allowed_plans = SimpleVPBot_Model_Discount_Code::parse_allowed_plan_ids( $row );
		if ( ! empty( $allowed_plans ) ) {
			$plan_id = self::plan_id_from_meta( $meta );
			if ( $plan_id < 1 || ! in_array( $plan_id, $allowed_plans, true ) ) {
				return array( 'ok' => false, 'reason' => 'plan_not_allowed' );
			}
		}
		$now_mysql = current_time( 'mysql' );
		if ( ! empty( $row->valid_from ) && (string) $row->valid_from > $now_mysql ) {
			return array( 'ok' => false, 'reason' => 'not_started' );
		}
		if ( ! empty( $row->valid_until ) && (string) $row->valid_until < $now_mysql ) {
			return array( 'ok' => false, 'reason' => 'expired' );
		}
		if ( null !== $row->max_uses && (int) $row->max_uses > 0 && (int) $row->uses_count >= (int) $row->max_uses ) {
			return array( 'ok' => false, 'reason' => 'max_uses' );
		}
		$flag = self::intent_flag_from_meta( $meta );
		if ( empty( $row->$flag ) ) {
			return array( 'ok' => false, 'reason' => 'intent_not_allowed' );
		}
		$subtotal = (float) $tx->amount;
		if ( isset( $meta['subtotal_toman'] ) ) {
			$subtotal = (float) $meta['subtotal_toman'];
		}
		if ( null !== $row->min_order_toman && (float) $row->min_order_toman > 0 && $subtotal < (float) $row->min_order_toman ) {
			return array( 'ok' => false, 'reason' => 'below_min_order' );
		}
		if ( null !== $row->max_order_toman && (float) $row->max_order_toman > 0 && $subtotal > (float) $row->max_order_toman ) {
			return array( 'ok' => false, 'reason' => 'above_max_order' );
		}
		$type = (string) ( $row->discount_type ?? 'percent' );
		if ( in_array( $type, array( 'fixed_per_gb', 'percent_per_gb' ), true ) && self::volume_gb_from_meta( $meta ) <= 0 ) {
			return array( 'ok' => false, 'reason' => 'volume_required' );
		}
		$disc  = self::compute_discount_toman( $subtotal, $row, $meta );
		$final = max( 0.0, round( $subtotal - $disc, 2 ) );
		return array(
			'ok'             => true,
			'discount_toman' => $disc,
			'final_amount'   => $final,
			'code_row'       => $row,
		);
	}

	/**
	 * Apply validated code: set amount to final, merge meta (uses subtotal from current amount if needed).
	 *
	 * @param int    $tx_id Transaction id.
	 * @param string $code  Raw code.
	 * @return array{ok:bool, reason?:string, final_amount?:float, discount_toman?:float}
	 */
	public static function apply_to_pending_transaction( $tx_id, $code ) {
		$tx = SimpleVPBot_Model_Transaction::find( (int) $tx_id );
		if ( ! $tx ) {
			return array( 'ok' => false, 'reason' => 'no_tx' );
		}
		$meta = json_decode( (string) $tx->meta_json, true );
		$meta = is_array( $meta ) ? $meta : array();
		if ( ! isset( $meta['subtotal_toman'] ) ) {
			$meta['subtotal_toman'] = (float) $tx->amount;
		}
		$chk = self::validate_for_pending_transaction( $tx, $code, $meta );
		if ( empty( $chk['ok'] ) ) {
			return array( 'ok' => false, 'reason' => (string) ( $chk['reason'] ?? 'invalid' ) );
		}
		$row  = $chk['code_row'];
		$disc = (float) $chk['discount_toman'];
		$fin  = (float) $chk['final_amount'];
		$meta['subtotal_toman']    = (float) $meta['subtotal_toman'];
		$meta['discount_toman']   = $disc;
		$meta['discount_code']    = SimpleVPBot_Model_Discount_Code::normalize_code( $code );
		$meta['discount_code_id'] = (int) $row->id;
		unset( $meta['discount_use_recorded'] );
		SimpleVPBot_Model_Transaction::update(
			(int) $tx->id,
			array(
				'amount'    => $fin,
				'meta_json' => wp_json_encode( $meta ),
			)
		);
		return array( 'ok' => true, 'final_amount' => $fin, 'discount_toman' => $disc );
	}

	/**
	 * Remove discount from pending tx (restore subtotal).
	 *
	 * @param int $tx_id Id.
	 * @return array{ok:bool, reason?:string, amount?:float}
	 */
	public static function clear_pending_discount( $tx_id ) {
		$tx = SimpleVPBot_Model_Transaction::find( (int) $tx_id );
		if ( ! $tx || 'pending' !== (string) $tx->status || 'purchase' !== (string) $tx->type ) {
			return array( 'ok' => false, 'reason' => 'bad_tx' );
		}
		$meta = json_decode( (string) $tx->meta_json, true );
		$meta = is_array( $meta ) ? $meta : array();
		if ( empty( $meta['discount_code_id'] ) ) {
			return array( 'ok' => true, 'amount' => (float) $tx->amount );
		}
		$sub = isset( $meta['subtotal_toman'] ) ? (float) $meta['subtotal_toman'] : (float) $tx->amount;
		unset( $meta['discount_toman'], $meta['discount_code'], $meta['discount_code_id'], $meta['subtotal_toman'], $meta['discount_use_recorded'] );
		SimpleVPBot_Model_Transaction::update(
			(int) $tx->id,
			array(
				'amount'    => round( $sub, 2 ),
				'meta_json' => wp_json_encode( $meta ),
			)
		);
		return array( 'ok' => true, 'amount' => round( $sub, 2 ) );
	}

	/**
	 * After payment approved: increment code uses once (idempotent via meta on buyer tx).
	 *
	 * @param object $tx Approved transaction row (fresh from DB).
	 */
	public static function maybe_record_redemption( $tx ) {
		if ( ! $tx || 'purchase' !== (string) $tx->type || 'approved' !== (string) $tx->status ) {
			return;
		}
		$meta = json_decode( (string) $tx->meta_json, true );
		if ( ! is_array( $meta ) || empty( $meta['discount_code_id'] ) || ! empty( $meta['discount_use_recorded'] ) ) {
			return;
		}
		$cid = (int) $meta['discount_code_id'];
		SimpleVPBot_Model_Discount_Code::increment_uses( $cid );
		if ( class_exists( 'SimpleVPBot_Model_Discount_Redemption' ) ) {
			$existing = SimpleVPBot_Model_Discount_Redemption::find_by_transaction( (int) $tx->id );
			if ( ! $existing ) {
				$sub = isset( $meta['subtotal_toman'] ) ? (float) $meta['subtotal_toman'] : (float) $tx->amount + (float) ( $meta['discount_toman'] ?? 0 );
				$vol = self::volume_gb_from_meta( $meta );
				SimpleVPBot_Model_Discount_Redemption::insert(
					array(
						'discount_code_id' => $cid,
						'transaction_id'   => (int) $tx->id,
						'svp_user_id'      => (int) ( $tx->user_id ?? 0 ),
						'subtotal_toman'   => round( $sub, 2 ),
						'discount_toman'   => round( (float) ( $meta['discount_toman'] ?? 0 ), 2 ),
						'volume_gb'        => $vol > 0 ? $vol : null,
					)
				);
			}
		}
		$meta['discount_use_recorded'] = true;
		SimpleVPBot_Model_Transaction::update(
			(int) $tx->id,
			array( 'meta_json' => wp_json_encode( $meta ) )
		);
	}
}
