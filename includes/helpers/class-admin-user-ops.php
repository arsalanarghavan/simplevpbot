<?php
/**
 * Admin-initiated purchases / renewals (bot + portal). Caller must authorize.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Admin_User_Ops
 */
class SimpleVPBot_Admin_User_Ops {

	/**
	 * Price for new service from plan (toman).
	 *
	 * @param object   $plan Plan row.
	 * @param int|null $volume_gb Volume for per-GB plans.
	 * @return float
	 */
	public static function price_new_service( $plan, $volume_gb ) {
		if ( ! $plan ) {
			return 0.0;
		}
		if ( SimpleVPBot_Model_Plan::is_per_gb( $plan ) ) {
			$g = max( 1, (int) $volume_gb );
			return round( SimpleVPBot_Model_Plan::total_price( $plan, $g ), 2 );
		}
		return round( (float) ( $plan->price ?? 0 ), 2 );
	}

	/**
	 * Debit reseller actor balance (dashboard wallet purchases for downline users).
	 *
	 * @param float $price    Toman.
	 * @param int   $actor_id svp_users.id (reseller).
	 * @return bool True if one row updated.
	 */
	private static function reseller_dashboard_debit_actor( $price, $actor_id ) {
		global $wpdb;
		$actor_id = (int) $actor_id;
		$price    = round( (float) $price, 2 );
		if ( $actor_id < 1 || $price <= 0 ) {
			return false;
		}
		$tbl = SimpleVPBot_Model_User::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "UPDATE {$tbl} SET balance = balance - %f WHERE id = %d AND balance >= %f", $price, $actor_id, $price ) );
		return (int) $wpdb->rows_affected > 0;
	}

	/**
	 * Refund reseller actor after failed provision.
	 *
	 * @param float $price    Toman.
	 * @param int   $actor_id svp_users.id.
	 */
	private static function reseller_dashboard_refund_actor( $price, $actor_id ) {
		global $wpdb;
		$actor_id = (int) $actor_id;
		$price    = round( (float) $price, 2 );
		if ( $actor_id < 1 || $price <= 0 ) {
			return;
		}
		$tbl = SimpleVPBot_Model_User::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "UPDATE {$tbl} SET balance = balance + %f WHERE id = %d", $price, $actor_id ) );
	}

	/**
	 * Meta flags when the reseller's wallet paid for a customer's service.
	 *
	 * @param int                  $actor_id   Reseller svp id.
	 * @param int                  $customer_id Customer svp id.
	 * @param array<string, mixed> $base       Existing meta.
	 * @return array<string, mixed>
	 */
	private static function meta_with_reseller_billing( $actor_id, $customer_id, array $base ) {
		$base['billing_reseller_svp_id']     = (int) $actor_id;
		$base['billing_customer_svp_id']      = (int) $customer_id;
		$base['reseller_dashboard_wallet']    = true;
		return $base;
	}

	/**
	 * Create new service: free | wallet | invoice (pending checkout to user chats).
	 *
	 * @param int    $target_user_id svp_users.id.
	 * @param int    $plan_id        Plan id.
	 * @param int|null $volume_gb    For per-GB plans.
	 * @param string $mode                              free|wallet|invoice.
	 * @param int    $invoice_card_scope_reseller_svp_id When >0 (dashboard reseller actor), checkout cards are scoped to owners 0 + this id via transaction meta.
	 * @return array{ok:bool, reason?:string, service_id?:int, transaction_id?:int, detail?:string}
	 */
	public static function admin_create_service( $target_user_id, $plan_id, $volume_gb, $mode, $invoice_card_scope_reseller_svp_id = 0 ) {
		$uid  = (int) $target_user_id;
		$pid  = (int) $plan_id;
		$mode = sanitize_key( (string) $mode );
		$scope_cards = (int) $invoice_card_scope_reseller_svp_id;
		$user = SimpleVPBot_Model_User::find( $uid );
		$plan = SimpleVPBot_Model_Plan::find( $pid );
		if ( ! $user || 'approved' !== (string) $user->status ) {
			return array( 'ok' => false, 'reason' => 'bad_user' );
		}
		if ( ! $plan || ! (int) $plan->active ) {
			return array( 'ok' => false, 'reason' => 'bad_plan' );
		}
		if ( SimpleVPBot_Model_Plan::is_per_gb( $plan ) ) {
			if ( null === $volume_gb || (int) $volume_gb < 1 || ! SimpleVPBot_Model_Plan::is_volume_in_range( $plan, (int) $volume_gb ) ) {
				return array( 'ok' => false, 'reason' => 'volume_out_of_range' );
			}
		}

		$price = self::price_new_service( $plan, $volume_gb );

		if ( 'free' === $mode ) {
			if ( $scope_cards > 0 ) {
				return array( 'ok' => false, 'reason' => 'forbidden_free_reseller' );
			}
			$det = SimpleVPBot_Service_Provisioner::create_from_plan_detailed( $uid, $pid, $volume_gb );
			if ( empty( $det['ok'] ) ) {
				return array( 'ok' => false, 'reason' => (string) ( $det['reason'] ?? 'provision_failed' ), 'detail' => (string) ( $det['detail'] ?? '' ) );
			}
			SimpleVPBot_Model_Transaction::insert(
				array(
					'user_id'    => $uid,
					'service_id' => (int) $det['service_id'],
					'amount'     => 0,
					'type'       => 'purchase',
					'status'     => 'approved',
					'meta_json'  => wp_json_encode( array( 'plan_id' => $pid, 'volume_gb' => $volume_gb, 'admin_gift' => true ) ),
				)
			);
			SimpleVPBot_Receipt_Processor::notify_user_service_ready( $user, (int) $det['service_id'] );
			return array( 'ok' => true, 'service_id' => (int) $det['service_id'] );
		}

		if ( 'wallet' === $mode ) {
			if ( $price <= 0 ) {
				if ( $scope_cards > 0 ) {
					return array( 'ok' => false, 'reason' => 'forbidden_free_reseller' );
				}
				return self::admin_create_service( $uid, $pid, $volume_gb, 'free', $scope_cards );
			}
			global $wpdb;
			$tbl = SimpleVPBot_Model_User::table();
			if ( $scope_cards > 0 ) {
				if ( ! self::reseller_dashboard_debit_actor( $price, $scope_cards ) ) {
					return array( 'ok' => false, 'reason' => 'insufficient_balance' );
				}
			} else {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$aff = $wpdb->query( $wpdb->prepare( "UPDATE {$tbl} SET balance = balance - %f WHERE id = %d AND balance >= %f", $price, $uid, $price ) );
				if ( ! $aff ) {
					return array( 'ok' => false, 'reason' => 'insufficient_balance' );
				}
			}
			$det = SimpleVPBot_Service_Provisioner::create_from_plan_detailed( $uid, $pid, $volume_gb );
			if ( empty( $det['ok'] ) ) {
				if ( $scope_cards > 0 ) {
					self::reseller_dashboard_refund_actor( $price, $scope_cards );
				} else {
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->query( $wpdb->prepare( "UPDATE {$tbl} SET balance = balance + %f WHERE id = %d", $price, $uid ) );
				}
				return array( 'ok' => false, 'reason' => (string) ( $det['reason'] ?? 'provision_failed' ), 'detail' => (string) ( $det['detail'] ?? '' ) );
			}
			$purchase_meta = array(
				'plan_id'      => $pid,
				'volume_gb'    => $volume_gb,
				'admin_wallet' => true,
			);
			if ( $scope_cards > 0 ) {
				$purchase_meta = self::meta_with_reseller_billing( $scope_cards, $uid, $purchase_meta );
			}
			$ins_id = SimpleVPBot_Model_Transaction::insert(
				array(
					'user_id'    => $uid,
					'service_id' => (int) $det['service_id'],
					'amount'     => $price,
					'type'       => 'purchase',
					'status'     => 'approved',
					'meta_json'  => wp_json_encode( $purchase_meta ),
				)
			);
			if ( $ins_id ) {
				SimpleVPBot_Purchase_Side_Effects::on_paid_transaction( (int) $ins_id );
			}
			SimpleVPBot_Receipt_Processor::notify_user_service_ready( $user, (int) $det['service_id'] );
			return array( 'ok' => true, 'service_id' => (int) $det['service_id'] );
		}

		if ( 'invoice' === $mode ) {
			if ( $price <= 0 ) {
				if ( $scope_cards > 0 ) {
					return array( 'ok' => false, 'reason' => 'forbidden_free_reseller' );
				}
				return self::admin_create_service( $uid, $pid, $volume_gb, 'free', $scope_cards );
			}
			$meta = array(
				'plan_id'         => $pid,
				'volume_gb'       => $volume_gb,
				'admin_invoice'   => true,
			);
			$tid = self::enqueue_purchase_invoice( $user, $price, $meta, $scope_cards );
			if ( $tid < 1 ) {
				return array( 'ok' => false, 'reason' => 'checkout_failed' );
			}
			return array( 'ok' => true, 'transaction_id' => $tid );
		}

		return array( 'ok' => false, 'reason' => 'bad_mode' );
	}

	/**
	 * Insert pending purchase and push payment keyboard to user's Telegram/Bale chats.
	 *
	 * @param object               $user   svp_users row.
	 * @param float                $amount Toman.
	 * @param array<string, mixed> $meta                              Transaction meta.
	 * @param int                    $invoice_card_scope_reseller_svp_id When >0, sets meta invoice_card_owner_scope_svp_id for {@see SimpleVPBot_Model_Card::active_for_transaction()}.
	 * @return int Transaction id or 0.
	 */
	public static function enqueue_purchase_invoice( $user, $amount, array $meta, $invoice_card_scope_reseller_svp_id = 0 ) {
		$scope = (int) $invoice_card_scope_reseller_svp_id;
		if ( $scope > 0 ) {
			$meta['invoice_card_owner_scope_svp_id'] = $scope;
		}
		$uid = (int) $user->id;
		$tid = SimpleVPBot_Model_Transaction::insert(
			array(
				'user_id'    => $uid,
				'service_id' => null,
				'amount'     => round( (float) $amount, 2 ),
				'type'       => 'purchase',
				'status'     => 'pending',
				'meta_json'  => wp_json_encode( $meta ),
			)
		);
		if ( $tid < 1 ) {
			return 0;
		}
		$cards = SimpleVPBot_Model_Card::active_for_transaction( (int) $tid );
		if ( empty( $cards ) ) {
			SimpleVPBot_Model_Transaction::set_status( $tid, 'cancelled' );
			return 0;
		}
		$tx_row = SimpleVPBot_Model_Transaction::find( (int) $tid );
		$text_tg    = SimpleVPBot_Handler_Buy::checkout_message_for_tx( $tx_row, '🧾 فاکتور سفارش (ادمین)' );
		$markup_tg  = SimpleVPBot_Handler_Buy::checkout_reply_markup( 'telegram', (int) $tid );
		$markup_bl  = SimpleVPBot_Handler_Buy::checkout_reply_markup( 'bale', (int) $tid );
		$text_bl    = SimpleVPBot_Handler_Buy::checkout_message_for_tx( $tx_row, '🧾 فاکتور سفارش (ادمین)' );
		$sent             = false;
		if ( ! empty( $user->tg_user_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( 'telegram', (int) $user->tg_user_id, $text_tg, array( 'reply_markup' => $markup_tg ) );
			$sent = true;
		}
		if ( ! empty( $user->bale_user_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( 'bale', (int) $user->bale_user_id, $text_bl, array( 'reply_markup' => $markup_bl ) );
			$sent = true;
		}
		if ( ! $sent ) {
			SimpleVPBot_Model_Transaction::set_status( $tid, 'cancelled' );
			return 0;
		}
		return (int) $tid;
	}

	/**
	 * Renew same cap: free | wallet | invoice.
	 *
	 * @param int    $service_id Service id.
	 * @param string $mode                              free|wallet|invoice.
	 * @param int    $invoice_card_scope_reseller_svp_id Dashboard reseller actor for invoice card scope (0 = default behaviour).
	 * @return array{ok:bool, reason?:string, transaction_id?:int}
	 */
	public static function admin_renew_service( $service_id, $mode, $invoice_card_scope_reseller_svp_id = 0 ) {
		$sid  = (int) $service_id;
		$mode = sanitize_key( (string) $mode );
		$svc  = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'reason' => 'no_service' );
		}
		$uid  = (int) $svc->user_id;
		$user = SimpleVPBot_Model_User::find( $uid );
		$plan = SimpleVPBot_Model_Service::effective_plan_for_pricing( $svc );
		if ( ! $plan || ! $user ) {
			return array( 'ok' => false, 'reason' => 'bad_plan_user' );
		}
		$price = SimpleVPBot_Service_Renew::checkout_price_renew( $svc, $plan );
		$scope = (int) $invoice_card_scope_reseller_svp_id;

		if ( ( 'free' === $mode || $price <= 0 ) && $scope > 0 ) {
			return array( 'ok' => false, 'reason' => 'forbidden_free_reseller' );
		}

		if ( 'free' === $mode || $price <= 0 ) {
			$rn = SimpleVPBot_Service_Renew::apply_after_payment( $sid );
			return ! empty( $rn['ok'] ) ? array( 'ok' => true ) : array( 'ok' => false, 'reason' => (string) ( $rn['message'] ?? 'renew_failed' ) );
		}

		if ( 'wallet' === $mode ) {
			global $wpdb;
			$tbl = SimpleVPBot_Model_User::table();
			if ( $scope > 0 ) {
				if ( ! self::reseller_dashboard_debit_actor( $price, $scope ) ) {
					return array( 'ok' => false, 'reason' => 'insufficient_balance' );
				}
			} else {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$aff = $wpdb->query( $wpdb->prepare( "UPDATE {$tbl} SET balance = balance - %f WHERE id = %d AND balance >= %f", $price, $uid, $price ) );
				if ( ! $aff ) {
					return array( 'ok' => false, 'reason' => 'insufficient_balance' );
				}
			}
			$rn = SimpleVPBot_Service_Renew::apply_after_payment( $sid );
			if ( empty( $rn['ok'] ) ) {
				if ( $scope > 0 ) {
					self::reseller_dashboard_refund_actor( $price, $scope );
				} else {
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->query( $wpdb->prepare( "UPDATE {$tbl} SET balance = balance + %f WHERE id = %d", $price, $uid ) );
				}
				return array( 'ok' => false, 'reason' => (string) ( $rn['message'] ?? 'renew_failed' ) );
			}
			$renew_meta = array( 'intent' => 'renew_same', 'admin_wallet' => true );
			if ( $scope > 0 ) {
				$renew_meta = self::meta_with_reseller_billing( $scope, $uid, $renew_meta );
			}
			$ins_id = SimpleVPBot_Model_Transaction::insert(
				array(
					'user_id'    => $uid,
					'service_id' => $sid,
					'amount'     => $price,
					'type'       => 'renew',
					'status'     => 'approved',
					'meta_json'  => wp_json_encode( $renew_meta ),
				)
			);
			if ( $ins_id ) {
				SimpleVPBot_Purchase_Side_Effects::on_paid_transaction( (int) $ins_id );
			}
			return array( 'ok' => true );
		}

		if ( 'invoice' === $mode ) {
			$meta = array(
				'intent'        => 'renew_same',
				'service_id'    => $sid,
				'admin_invoice' => true,
			);
			$tid = self::enqueue_purchase_invoice( $user, $price, $meta, (int) $invoice_card_scope_reseller_svp_id );
			return $tid > 0 ? array( 'ok' => true, 'transaction_id' => $tid ) : array( 'ok' => false, 'reason' => 'checkout_failed' );
		}

		return array( 'ok' => false, 'reason' => 'bad_mode' );
	}

	/**
	 * Add volume GB: free | wallet | invoice.
	 *
	 * @param int    $service_id Service id.
	 * @param int    $extra_gb   Extra GB.
	 * @param string $mode                              free|wallet|invoice.
	 * @param int    $invoice_card_scope_reseller_svp_id Dashboard reseller actor for invoice card scope.
	 * @return array{ok:bool, reason?:string, transaction_id?:int}
	 */
	public static function admin_add_volume( $service_id, $extra_gb, $mode, $invoice_card_scope_reseller_svp_id = 0 ) {
		$sid  = (int) $service_id;
		$g    = max( 1, (int) $extra_gb );
		$mode = sanitize_key( (string) $mode );
		$svc  = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'reason' => 'no_service' );
		}
		$uid  = (int) $svc->user_id;
		$user = SimpleVPBot_Model_User::find( $uid );
		$plan = SimpleVPBot_Model_Service::effective_plan_for_pricing( $svc );
		if ( ! $plan || ! $user ) {
			return array( 'ok' => false, 'reason' => 'bad_plan_user' );
		}
		$price = SimpleVPBot_Service_Renew::checkout_price_add_volume( $plan, $g );
		$scope = (int) $invoice_card_scope_reseller_svp_id;

		if ( ( 'free' === $mode || $price <= 0 ) && $scope > 0 ) {
			return array( 'ok' => false, 'reason' => 'forbidden_free_reseller' );
		}

		if ( 'free' === $mode || $price <= 0 ) {
			$rn = SimpleVPBot_Service_Renew::apply_add_volume_after_payment( $sid, $g );
			return ! empty( $rn['ok'] ) ? array( 'ok' => true ) : array( 'ok' => false, 'reason' => (string) ( $rn['message'] ?? 'add_vol_failed' ) );
		}
		if ( 'wallet' === $mode ) {
			global $wpdb;
			$tbl = SimpleVPBot_Model_User::table();
			if ( $scope > 0 ) {
				if ( ! self::reseller_dashboard_debit_actor( $price, $scope ) ) {
					return array( 'ok' => false, 'reason' => 'insufficient_balance' );
				}
			} else {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$aff = $wpdb->query( $wpdb->prepare( "UPDATE {$tbl} SET balance = balance - %f WHERE id = %d AND balance >= %f", $price, $uid, $price ) );
				if ( ! $aff ) {
					return array( 'ok' => false, 'reason' => 'insufficient_balance' );
				}
			}
			$rn = SimpleVPBot_Service_Renew::apply_add_volume_after_payment( $sid, $g );
			if ( empty( $rn['ok'] ) ) {
				if ( $scope > 0 ) {
					self::reseller_dashboard_refund_actor( $price, $scope );
				} else {
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->query( $wpdb->prepare( "UPDATE {$tbl} SET balance = balance + %f WHERE id = %d", $price, $uid ) );
				}
				return array( 'ok' => false, 'reason' => (string) ( $rn['message'] ?? 'add_vol_failed' ) );
			}
			$vol_meta = array( 'intent' => 'add_volume', 'extra_gb' => $g, 'admin_wallet' => true );
			if ( $scope > 0 ) {
				$vol_meta = self::meta_with_reseller_billing( $scope, $uid, $vol_meta );
			}
			$ins_id = SimpleVPBot_Model_Transaction::insert(
				array(
					'user_id'    => $uid,
					'service_id' => $sid,
					'amount'     => $price,
					'type'       => 'purchase',
					'status'     => 'approved',
					'meta_json'  => wp_json_encode( $vol_meta ),
				)
			);
			if ( $ins_id ) {
				SimpleVPBot_Purchase_Side_Effects::on_paid_transaction( (int) $ins_id );
			}
			return array( 'ok' => true );
		}
		if ( 'invoice' === $mode ) {
			$meta = array(
				'intent'        => 'add_volume',
				'service_id'    => $sid,
				'extra_gb'      => $g,
				'admin_invoice' => true,
			);
			$tid = self::enqueue_purchase_invoice( $user, $price, $meta, (int) $invoice_card_scope_reseller_svp_id );
			return $tid > 0 ? array( 'ok' => true, 'transaction_id' => $tid ) : array( 'ok' => false, 'reason' => 'checkout_failed' );
		}
		return array( 'ok' => false, 'reason' => 'bad_mode' );
	}

	/**
	 * Reduce volume GB (free only, clamped at zero).
	 *
	 * @param int    $service_id Service id.
	 * @param int    $reduce_gb  GB to reduce.
	 * @param string $mode       free.
	 * @return array{ok:bool, reason?:string}
	 */
	public static function admin_reduce_volume( $service_id, $reduce_gb, $mode = 'free' ) {
		$sid  = (int) $service_id;
		$g    = max( 1, (int) $reduce_gb );
		$mode = sanitize_key( (string) $mode );
		if ( 'free' !== $mode ) {
			return array( 'ok' => false, 'reason' => 'bad_mode' );
		}
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'reason' => 'no_service' );
		}
		$rn = SimpleVPBot_Service_Renew::apply_reduce_volume_free( $sid, $g );
		return ! empty( $rn['ok'] ) ? array( 'ok' => true ) : array( 'ok' => false, 'reason' => (string) ( $rn['message'] ?? 'reduce_vol_failed' ) );
	}

	/**
	 * Add user slots when priced.
	 *
	 * @param int    $service_id Service id.
	 * @param int    $extra_users Count.
	 * @param string $mode                              free|wallet|invoice.
	 * @param int    $invoice_card_scope_reseller_svp_id Dashboard reseller actor for invoice card scope.
	 * @return array{ok:bool, reason?:string, transaction_id?:int}
	 */
	public static function admin_add_user_slots( $service_id, $extra_users, $mode, $invoice_card_scope_reseller_svp_id = 0 ) {
		$n    = max( 1, (int) $extra_users );
		$mode = sanitize_key( (string) $mode );
		$svc  = SimpleVPBot_Model_Service::find( (int) $service_id );
		if ( ! $svc ) {
			return array( 'ok' => false, 'reason' => 'no_service' );
		}
		$uid  = (int) $svc->user_id;
		$user = SimpleVPBot_Model_User::find( $uid );
		if ( ! $user ) {
			return array( 'ok' => false, 'reason' => 'bad_user' );
		}
		$price = SimpleVPBot_Service_Renew::checkout_price_add_user_slots( $n );
		$scope = (int) $invoice_card_scope_reseller_svp_id;

		if ( ( 'free' === $mode || $price <= 0 ) && $scope > 0 ) {
			return array( 'ok' => false, 'reason' => 'forbidden_free_reseller' );
		}

		if ( 'free' === $mode || $price <= 0 ) {
			$rn = SimpleVPBot_Service_Renew::apply_add_user_slots_after_payment( (int) $service_id, $n );
			return ! empty( $rn['ok'] ) ? array( 'ok' => true ) : array( 'ok' => false, 'reason' => (string) ( $rn['message'] ?? 'slots_failed' ) );
		}
		if ( 'wallet' === $mode ) {
			global $wpdb;
			$tbl = SimpleVPBot_Model_User::table();
			if ( $scope > 0 ) {
				if ( ! self::reseller_dashboard_debit_actor( $price, $scope ) ) {
					return array( 'ok' => false, 'reason' => 'insufficient_balance' );
				}
			} else {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$aff = $wpdb->query( $wpdb->prepare( "UPDATE {$tbl} SET balance = balance - %f WHERE id = %d AND balance >= %f", $price, $uid, $price ) );
				if ( ! $aff ) {
					return array( 'ok' => false, 'reason' => 'insufficient_balance' );
				}
			}
			$rn = SimpleVPBot_Service_Renew::apply_add_user_slots_after_payment( (int) $service_id, $n );
			if ( empty( $rn['ok'] ) ) {
				if ( $scope > 0 ) {
					self::reseller_dashboard_refund_actor( $price, $scope );
				} else {
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->query( $wpdb->prepare( "UPDATE {$tbl} SET balance = balance + %f WHERE id = %d", $price, $uid ) );
				}
				return array( 'ok' => false, 'reason' => (string) ( $rn['message'] ?? 'slots_failed' ) );
			}
			$slot_meta = array( 'intent' => 'add_user_slots', 'extra_users' => $n, 'admin_wallet' => true );
			if ( $scope > 0 ) {
				$slot_meta = self::meta_with_reseller_billing( $scope, $uid, $slot_meta );
			}
			$ins_id = SimpleVPBot_Model_Transaction::insert(
				array(
					'user_id'    => $uid,
					'service_id' => (int) $service_id,
					'amount'     => $price,
					'type'       => 'purchase',
					'status'     => 'approved',
					'meta_json'  => wp_json_encode( $slot_meta ),
				)
			);
			if ( $ins_id ) {
				SimpleVPBot_Purchase_Side_Effects::on_paid_transaction( (int) $ins_id );
			}
			return array( 'ok' => true );
		}
		if ( 'invoice' === $mode ) {
			$meta = array(
				'intent'        => 'add_user_slots',
				'service_id'    => (int) $service_id,
				'extra_users'   => $n,
				'admin_invoice' => true,
			);
			$tid = self::enqueue_purchase_invoice( $user, $price, $meta, (int) $invoice_card_scope_reseller_svp_id );
			return $tid > 0 ? array( 'ok' => true, 'transaction_id' => $tid ) : array( 'ok' => false, 'reason' => 'checkout_failed' );
		}
		return array( 'ok' => false, 'reason' => 'bad_mode' );
	}

	/**
	 * Reduce user slots (free only, clamped at zero).
	 *
	 * @param int    $service_id Service id.
	 * @param int    $reduce_users Count.
	 * @param string $mode free.
	 * @return array{ok:bool, reason?:string}
	 */
	public static function admin_reduce_user_slots( $service_id, $reduce_users, $mode = 'free' ) {
		$sid  = (int) $service_id;
		$n    = max( 1, (int) $reduce_users );
		$mode = sanitize_key( (string) $mode );
		if ( 'free' !== $mode ) {
			return array( 'ok' => false, 'reason' => 'bad_mode' );
		}
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'reason' => 'no_service' );
		}
		$rn = SimpleVPBot_Service_Renew::apply_reduce_user_slots_free( $sid, $n );
		return ! empty( $rn['ok'] ) ? array( 'ok' => true ) : array( 'ok' => false, 'reason' => (string) ( $rn['message'] ?? 'slots_failed' ) );
	}

	/**
	 * Bulk: add days to all non-L2TP services (or all if $xray_only false).
	 *
	 * @param int  $days       Days to add.
	 * @param bool $xray_only  Skip L2TP when true.
	 * @param int  $max_ops    Safety cap.
	 * @return array{ok:bool, done:int, errors:int}
	 */
	public static function bulk_extend_days( $days, $xray_only = true, $max_ops = 200 ) {
		$d        = max( 1, min( 3650, (int) $days ) );
		$services = SimpleVPBot_Model_Service::all();
		$done     = 0;
		$errors   = 0;
		$n        = 0;
		foreach ( $services as $svc ) {
			if ( $n >= $max_ops ) {
				break;
			}
			if ( $xray_only && SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
				continue;
			}
			++$n;
			$r = SimpleVPBot_Service_Renew::apply_extend_days_free( (int) $svc->id, $d );
			if ( ! empty( $r['ok'] ) ) {
				++$done;
			} else {
				++$errors;
			}
		}
		return array( 'ok' => true, 'done' => $done, 'errors' => $errors );
	}

	/**
	 * Bulk: reduce days from all non-L2TP services (or all if $xray_only false), floor at now.
	 *
	 * @param int  $days       Days to reduce.
	 * @param bool $xray_only  Skip L2TP when true.
	 * @param int  $max_ops    Safety cap.
	 * @return array{ok:bool, done:int, errors:int}
	 */
	public static function bulk_reduce_days( $days, $xray_only = true, $max_ops = 200 ) {
		$d        = max( 1, min( 3650, (int) $days ) );
		$services = SimpleVPBot_Model_Service::all();
		$done     = 0;
		$errors   = 0;
		$n        = 0;
		foreach ( $services as $svc ) {
			if ( $n >= $max_ops ) {
				break;
			}
			if ( $xray_only && SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
				continue;
			}
			++$n;
			$r = SimpleVPBot_Service_Renew::apply_reduce_days_free( (int) $svc->id, $d );
			if ( ! empty( $r['ok'] ) ) {
				++$done;
			} else {
				++$errors;
			}
		}
		return array( 'ok' => true, 'done' => $done, 'errors' => $errors );
	}

	/**
	 * Bulk: add GB to all Xray services.
	 *
	 * @param int $extra_gb  GB per service.
	 * @param int $max_ops   Cap.
	 * @return array{ok:bool, done:int, errors:int}
	 */
	public static function bulk_add_volume( $extra_gb, $max_ops = 200 ) {
		$g        = max( 1, (int) $extra_gb );
		$services = SimpleVPBot_Model_Service::all();
		$done     = 0;
		$errors   = 0;
		$n        = 0;
		foreach ( $services as $svc ) {
			if ( $n >= $max_ops ) {
				break;
			}
			if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
				continue;
			}
			++$n;
			$r = SimpleVPBot_Service_Renew::apply_add_volume_after_payment( (int) $svc->id, $g );
			if ( ! empty( $r['ok'] ) ) {
				++$done;
			} else {
				++$errors;
			}
		}
		return array( 'ok' => true, 'done' => $done, 'errors' => $errors );
	}

	/**
	 * Bulk: reduce GB from all Xray services (floor at zero).
	 *
	 * @param int $reduce_gb GB per service.
	 * @param int $max_ops   Cap.
	 * @return array{ok:bool, done:int, errors:int}
	 */
	public static function bulk_reduce_volume( $reduce_gb, $max_ops = 200 ) {
		$g        = max( 1, (int) $reduce_gb );
		$services = SimpleVPBot_Model_Service::all();
		$done     = 0;
		$errors   = 0;
		$n        = 0;
		foreach ( $services as $svc ) {
			if ( $n >= $max_ops ) {
				break;
			}
			if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
				continue;
			}
			++$n;
			$r = SimpleVPBot_Service_Renew::apply_reduce_volume_free( (int) $svc->id, $g );
			if ( ! empty( $r['ok'] ) ) {
				++$done;
			} else {
				++$errors;
			}
		}
		return array( 'ok' => true, 'done' => $done, 'errors' => $errors );
	}
}
