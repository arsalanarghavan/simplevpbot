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
	 * @return float Discount toman (>= 0, <= subtotal).
	 */
	public static function compute_discount_toman( $subtotal, $row ) {
		$base = max( 0.0, round( (float) $subtotal, 2 ) );
		$type = (string) ( $row->discount_type ?? 'percent' );
		if ( 'fixed_toman' === $type ) {
			$fix = max( 0.0, round( (float) ( $row->discount_value ?? 0 ), 2 ) );
			return min( $base, $fix );
		}
		$pct = max( 0.0, min( 100.0, (float) ( $row->discount_value ?? 0 ) ) );
		return min( $base, round( $base * $pct / 100.0, 2 ) );
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
		$uid = (int) ( $tx->user_id ?? 0 );
		if ( $uid > 0 && class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
			$rid = (int) SimpleVPBot_Reseller_Branding::nearest_reseller_id_for_user( $uid );
			if ( $rid > 0 ) {
				$owner_candidates = array( $rid, 0 );
			}
		}
		$row = SimpleVPBot_Model_Discount_Code::find_by_code_for_owners( $code, $owner_candidates );
		if ( ! $row || ! (int) $row->active ) {
			return array( 'ok' => false, 'reason' => 'invalid_code' );
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
		$disc = self::compute_discount_toman( $subtotal, $row );
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
		$meta['subtotal_toman']   = (float) $meta['subtotal_toman'];
		$meta['discount_toman']  = $disc;
		$meta['discount_code']   = SimpleVPBot_Model_Discount_Code::normalize_code( $code );
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
		$meta['discount_use_recorded'] = true;
		SimpleVPBot_Model_Transaction::update(
			(int) $tx->id,
			array( 'meta_json' => wp_json_encode( $meta ) )
		);
	}
}
