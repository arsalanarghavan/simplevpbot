<?php
/**
 * Post-payment hooks: referral commission + discount redemption counter.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Purchase_Side_Effects
 */
class SimpleVPBot_Purchase_Side_Effects {

	/**
	 * Run after a purchase/renew transaction is approved and provisioned (if any).
	 *
	 * @param int $tx_id Transaction id.
	 */
	public static function on_paid_transaction( $tx_id ) {
		$tx = SimpleVPBot_Model_Transaction::find( (int) $tx_id );
		if ( ! $tx || 'approved' !== (string) $tx->status ) {
			return;
		}
		SimpleVPBot_Referral_Service::maybe_credit_from_transaction( $tx );
		$tx2 = SimpleVPBot_Model_Transaction::find( (int) $tx_id );
		if ( $tx2 ) {
			SimpleVPBot_Discount_Service::maybe_record_redemption( $tx2 );
		}
		if ( class_exists( 'SimpleVPBot_Service_Reseller_Wholesale_Pricing' ) ) {
			SimpleVPBot_Service_Reseller_Wholesale_Pricing::maybe_record_accrual_from_transaction( $tx );
		}
	}
}
