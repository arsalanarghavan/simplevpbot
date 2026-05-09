<?php
/**
 * Approve/reject payment receipts (shared: bot callback + WP admin).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Receipt_Processor
 */
class SimpleVPBot_Receipt_Processor {

	/**
	 * Approve a pending receipt.
	 *
	 * @param int    $rid Receipt id.
	 * @param string $admin_label Shown on buttons (e.g. @user or display name or wp user_login).
	 * @return array{ok:bool, reason:string, purchase_failed?:bool, rec?:object, tx?:object, meta?:array}
	 */
	public static function approve( $rid, $admin_label ) {
		$rid   = (int) $rid;
		$label = (string) $admin_label;
		$rec   = SimpleVPBot_Model_Receipt::find( $rid );
		if ( ! $rec || 'pending' !== $rec->status ) {
			return array( 'ok' => false, 'reason' => 'not_pending' );
		}
		$tx = SimpleVPBot_Model_Transaction::find( (int) $rec->transaction_id );
		if ( ! $tx ) {
			return array( 'ok' => false, 'reason' => 'no_tx' );
		}
		$meta = json_decode( (string) $tx->meta_json, true );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		self::normalize_intent_meta( $meta );
		$purchase_failed = false;
		$provision_err   = '';
		$provision_info  = null;
		if ( 'topup' === $tx->type ) {
			$user_row = SimpleVPBot_Model_User::find( (int) $tx->user_id );
			if ( $user_row ) {
				SimpleVPBot_Model_User::update( (int) $user_row->id, array( 'balance' => (float) $user_row->balance + (float) $rec->amount ) );
			}
			SimpleVPBot_Model_Transaction::set_status( (int) $tx->id, 'approved' );
		} elseif ( 'purchase' === $tx->type && ! empty( $meta['intent'] ) && 'renew_same' === (string) $meta['intent'] ) {
			$res_svc = self::resolve_intent_service_for_transaction( $tx, $meta );
			if ( ! $res_svc['ok'] ) {
				$purchase_failed = true;
				$provision_err   = (string) $res_svc['reason'];
			} else {
				$rn = SimpleVPBot_Service_Renew::apply_after_payment( (int) $res_svc['service_id'] );
				if ( ! empty( $rn['ok'] ) ) {
					SimpleVPBot_Model_Transaction::update(
						(int) $tx->id,
						array(
							'status'     => 'approved',
							'service_id' => (int) $res_svc['service_id'],
							'meta_json'  => wp_json_encode( $meta ),
						)
					);
				} else {
					$purchase_failed = true;
					$provision_err   = (string) ( $rn['message'] ?? 'renew_failed' );
					SimpleVPBot_Logger::error(
						'purchase renew_same failed',
						array(
							'tx_id'  => (int) $tx->id,
							'rid'    => $rid,
							'reason' => $provision_err,
						)
					);
				}
			}
		} elseif ( 'purchase' === $tx->type && ! empty( $meta['intent'] ) && 'add_volume' === (string) $meta['intent'] && (int) ( $meta['extra_gb'] ?? 0 ) >= 1 ) {
			$res_svc = self::resolve_intent_service_for_transaction( $tx, $meta );
			if ( ! $res_svc['ok'] ) {
				$purchase_failed = true;
				$provision_err   = (string) $res_svc['reason'];
				SimpleVPBot_Logger::error(
					'purchase add_volume resolve failed',
					array(
						'tx_id'      => (int) $tx->id,
						'rid'        => $rid,
						'reason'     => $provision_err,
						'extra_gb'   => (int) ( $meta['extra_gb'] ?? 0 ),
						'service_id' => (int) ( $meta['service_id'] ?? 0 ),
					)
				);
			} else {
				$rn = SimpleVPBot_Service_Renew::apply_add_volume_after_payment( (int) $res_svc['service_id'], (int) $meta['extra_gb'] );
				if ( ! empty( $rn['ok'] ) ) {
					SimpleVPBot_Model_Transaction::update(
						(int) $tx->id,
						array(
							'status'     => 'approved',
							'service_id' => (int) $res_svc['service_id'],
							'meta_json'  => wp_json_encode( $meta ),
						)
					);
				} else {
					$purchase_failed = true;
					$provision_err   = (string) ( $rn['message'] ?? 'add_volume_failed' );
					SimpleVPBot_Logger::error(
						'purchase add_volume failed',
						array(
							'tx_id'      => (int) $tx->id,
							'rid'        => $rid,
							'reason'     => $provision_err,
							'intent'     => 'add_volume',
							'service_id' => (int) ( $meta['service_id'] ?? 0 ),
							'extra_gb'   => (int) ( $meta['extra_gb'] ?? 0 ),
							'tx_user_id' => (int) $tx->user_id,
						)
					);
				}
			}
		} elseif ( 'purchase' === $tx->type && ! empty( $meta['intent'] ) && 'add_user_slots' === (string) $meta['intent'] && isset( $meta['extra_users'] ) ) {
			$res_svc = self::resolve_intent_service_for_transaction( $tx, $meta );
			if ( ! $res_svc['ok'] ) {
				$purchase_failed = true;
				$provision_err   = (string) $res_svc['reason'];
			} else {
				$rn = SimpleVPBot_Service_Renew::apply_add_user_slots_after_payment( (int) $res_svc['service_id'], (int) $meta['extra_users'] );
				if ( ! empty( $rn['ok'] ) ) {
					SimpleVPBot_Model_Transaction::update(
						(int) $tx->id,
						array(
							'status'     => 'approved',
							'service_id' => (int) $res_svc['service_id'],
							'meta_json'  => wp_json_encode( $meta ),
						)
					);
				} else {
					$purchase_failed = true;
					$provision_err   = (string) ( $rn['message'] ?? 'add_user_slots_failed' );
					SimpleVPBot_Logger::error(
						'purchase add_user_slots failed',
						array(
							'tx_id'  => (int) $tx->id,
							'rid'    => $rid,
							'reason' => $provision_err,
						)
					);
				}
			}
		} elseif ( 'purchase' === $tx->type && ! empty( $meta['plan_id'] ) ) {
			$vol = isset( $meta['volume_gb'] ) ? (int) $meta['volume_gb'] : null;
			$det = SimpleVPBot_Service_Provisioner::create_from_plan_detailed( (int) $tx->user_id, (int) $meta['plan_id'], $vol );
			if ( ! empty( $det['ok'] ) ) {
				SimpleVPBot_Model_Transaction::update(
					(int) $tx->id,
					array(
						'status'     => 'approved',
						'service_id' => (int) $det['service_id'],
					)
				);
			} else {
				$purchase_failed = true;
				$provision_err   = (string) ( $det['reason'] ?? 'purchase_failed' );
				$provision_info  = $det;
				SimpleVPBot_Logger::error(
					'purchase provisioning failed',
					array(
						'tx_id'  => (int) $tx->id,
						'rid'    => $rid,
						'reason' => $provision_err,
						'detail' => (string) ( $det['detail'] ?? '' ),
					)
				);
			}
		} elseif ( 'purchase' === $tx->type ) {
			$purchase_failed = true;
			$intent            = isset( $meta['intent'] ) ? (string) $meta['intent'] : '';
			if ( 'add_volume' === $intent && (int) ( $meta['extra_gb'] ?? 0 ) < 1 ) {
				$provision_err = 'extra_gb_missing';
			} else {
				$provision_err = 'no_plan_id';
			}
			SimpleVPBot_Logger::error(
				'purchase fallback no_plan_id',
				array(
					'tx_id'      => (int) $tx->id,
					'rid'        => $rid,
					'intent'     => $intent,
					'plan_id'    => (int) ( $meta['plan_id'] ?? 0 ),
					'service_id' => (int) ( $meta['service_id'] ?? 0 ),
					'extra_gb'   => (int) ( $meta['extra_gb'] ?? 0 ),
				)
			);
		}

		if ( $purchase_failed ) {
			$markup_err = array(
				'inline_keyboard' => array(
					array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '⚠️ خطا در آماده‌سازی سرویس: ' . $provision_err . ' · ' . $label ), 'callback_data' => 'noop' ) ),
				),
			);
			self::edit_admin_messages( $rec, $markup_err );
			return array(
				'ok'              => false,
				'reason'          => 'purchase_failed',
				'purchase_failed' => true,
				'rec'             => $rec,
				'tx'              => $tx,
				'meta'            => $meta,
				'provision_error' => $provision_err,
				'provision_info'  => $provision_info,
			);
		}

		SimpleVPBot_Model_Receipt::update(
			$rid,
			array(
				'status'     => 'approved',
				'decided_at' => current_time( 'mysql' ),
			)
		);
		$markup_done = array(
			'inline_keyboard' => array(
				array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '✅ رسید تایید شد · ' . $label ), 'callback_data' => 'noop' ) ),
			),
		);
		self::edit_admin_messages( $rec, $markup_done );
		$user_row = SimpleVPBot_Model_User::find( (int) $rec->user_id );
		if ( $user_row ) {
			self::notify_user_both_bots( $user_row, '✅ پرداخت شما تایید شد. ممنون!' );
			if ( 'purchase' === $tx->type ) {
				$tx2 = SimpleVPBot_Model_Transaction::find( (int) $tx->id );
				$svc = $tx2 && $tx2->service_id ? SimpleVPBot_Model_Service::find( (int) $tx2->service_id ) : null;
				if ( $svc ) {
					$extra = array(
						'reply_markup' => array(
							'inline_keyboard' => array(
								array(
									array(
										'text'          => SimpleVPBot_Keyboards::glass_button_text( SimpleVPBot_Texts::get( 'btn.service.show_panel', '🖥 جزئیات سرویس' ) ),
										'callback_data' => 'svc:p:' . (int) $svc->id,
									),
								),
							),
						),
					);
					$intent = isset( $meta['intent'] ) ? (string) $meta['intent'] : '';
					if ( 'renew_same' === $intent ) {
						self::notify_user_both_bots( $user_row, '♻️ تمدید سرویس شما اعمال شد.', $extra );
					} elseif ( 'add_volume' === $intent ) {
						self::notify_user_both_bots( $user_row, '➕ حجم سرویس شما افزایش یافت.', $extra );
					} elseif ( 'add_user_slots' === $intent ) {
						self::notify_user_both_bots( $user_row, '👥 محدودیت کاربر هم‌زمان برای سرویس شما بیشتر شد.', $extra );
					} elseif ( ! empty( $meta['plan_id'] ) ) {
						self::notify_user_service_ready( $user_row, (int) $svc->id );
					}
				}
			}
		}
		if ( 'purchase' === (string) $tx->type ) {
			$tx_chk = SimpleVPBot_Model_Transaction::find( (int) $tx->id );
			if ( $tx_chk && 'approved' === (string) $tx_chk->status ) {
				SimpleVPBot_Purchase_Side_Effects::on_paid_transaction( (int) $tx->id );
			}
		}
		return array( 'ok' => true, 'reason' => 'approved', 'rec' => $rec, 'tx' => $tx, 'meta' => $meta );
	}

	/**
	 * Normalize intent meta (aliases for extra_gb / extra_users).
	 *
	 * @param array<string, mixed> $meta Meta by reference.
	 */
	private static function normalize_intent_meta( array &$meta ) {
		$intent = isset( $meta['intent'] ) ? (string) $meta['intent'] : '';
		if ( 'add_volume' === $intent && ( ! isset( $meta['extra_gb'] ) || (int) $meta['extra_gb'] < 1 ) ) {
			foreach ( array( 'volume_gb', 'gb', 'add_gb', 'traffic_gb', 'extra_traffic_gb' ) as $alias ) {
				if ( isset( $meta[ $alias ] ) && (int) $meta[ $alias ] > 0 ) {
					$meta['extra_gb'] = (int) $meta[ $alias ];
					break;
				}
			}
		}
		if ( 'add_user_slots' === $intent && ( ! isset( $meta['extra_users'] ) || (int) $meta['extra_users'] < 1 ) ) {
			foreach ( array( 'extra_slots', 'user_slots', 'slots' ) as $alias ) {
				if ( isset( $meta[ $alias ] ) && (int) $meta[ $alias ] > 0 ) {
					$meta['extra_users'] = (int) $meta[ $alias ];
					break;
				}
			}
		}
	}

	/**
	 * When the payer has exactly one non-L2TP service, use it for renew/add_volume/add_user_slots intents.
	 *
	 * @param int $user_id svp_users.id.
	 * @return object|null Service row.
	 */
	private static function single_eligible_intent_service_for_user( $user_id ) {
		$uid = (int) $user_id;
		if ( $uid < 1 ) {
			return null;
		}
		$list = SimpleVPBot_Model_Service::by_user( $uid );
		$xray = array();
		foreach ( (array) $list as $svc ) {
			if ( $svc && ! SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
				$xray[] = $svc;
			}
		}
		if ( 1 !== count( $xray ) ) {
			return null;
		}
		return $xray[0];
	}

	/**
	 * For renew / add_volume / add_user_slots: find service that belongs to transaction user.
	 * Uses meta.service_id and/or transaction.service_id if meta is stale or missing.
	 *
	 * @param object               $tx   Transaction row.
	 * @param array<string, mixed> $meta Decoded meta_json (may be normalized).
	 * @return array{ok:bool, service_id:int, service:object|null, reason:string}
	 */
	private static function resolve_intent_service_for_transaction( $tx, array &$meta ) {
		$uid         = (int) $tx->user_id;
		$candidates  = array();
		$from_meta   = isset( $meta['service_id'] ) ? (int) $meta['service_id'] : 0;
		$from_tx_col = ! empty( $tx->service_id ) ? (int) $tx->service_id : 0;
		if ( $from_meta > 0 ) {
			$candidates[] = $from_meta;
		}
		if ( $from_tx_col > 0 && $from_tx_col !== $from_meta ) {
			$candidates[] = $from_tx_col;
		}
		$candidates = array_values( array_unique( array_filter( $candidates ) ) );
		foreach ( $candidates as $sid ) {
			$svc = SimpleVPBot_Model_Service::find( $sid );
			if ( $svc && (int) $svc->user_id === $uid ) {
				$meta['service_id'] = (int) $svc->id;
				return array(
					'ok'         => true,
					'service_id' => (int) $svc->id,
					'service'    => $svc,
					'reason'     => '',
				);
			}
		}
		$fb = self::single_eligible_intent_service_for_user( $uid );
		if ( $fb ) {
			$meta['service_id'] = (int) $fb->id;
			SimpleVPBot_Logger::info(
				'intent_service_resolve_fallback_single',
				array(
					'tx_user_id'          => $uid,
					'service_id'          => (int) $fb->id,
					'had_candidates'      => $candidates,
					'resolved_from_empty' => empty( $candidates ),
				)
			);
			return array(
				'ok'         => true,
				'service_id' => (int) $fb->id,
				'service'    => $fb,
				'reason'     => '',
			);
		}
		if ( empty( $candidates ) ) {
			return array(
				'ok'         => false,
				'service_id' => 0,
				'service'    => null,
				'reason'     => 'service_id_missing',
			);
		}
		$first = SimpleVPBot_Model_Service::find( (int) $candidates[0] );
		if ( ! $first ) {
			return array(
				'ok'         => false,
				'service_id' => 0,
				'service'    => null,
				'reason'     => 'service_not_found',
			);
		}
		return array(
			'ok'         => false,
			'service_id' => 0,
			'service'    => $first,
			'reason'     => 'service_mismatch',
		);
	}

	/**
	 * Retry provisioning for a receipt whose transaction is approved/pending but without a service.
	 *
	 * @param int    $rid   Receipt id.
	 * @param string $label Admin label.
	 * @return array{ok:bool, reason:string, service_id?:int, detail?:string}
	 */
	public static function retry_provision_for_receipt( $rid, $label = '' ) {
		$rid = (int) $rid;
		$rec = SimpleVPBot_Model_Receipt::find( $rid );
		if ( ! $rec ) {
			return array( 'ok' => false, 'reason' => 'not_found' );
		}
		$tx = SimpleVPBot_Model_Transaction::find( (int) $rec->transaction_id );
		if ( ! $tx || 'purchase' !== (string) $tx->type ) {
			return array( 'ok' => false, 'reason' => 'not_purchase' );
		}
		if ( ! empty( $tx->service_id ) ) {
			return array( 'ok' => true, 'reason' => 'already_provisioned', 'service_id' => (int) $tx->service_id );
		}
		$meta = json_decode( (string) $tx->meta_json, true );
		if ( ! is_array( $meta ) ) {
			return array( 'ok' => false, 'reason' => 'no_plan_id' );
		}
		self::normalize_intent_meta( $meta );
		if ( ! empty( $meta['intent'] ) && in_array( (string) $meta['intent'], array( 'renew_same', 'add_volume', 'add_user_slots' ), true ) ) {
			if ( 'pending' !== (string) $tx->status ) {
				return array( 'ok' => false, 'reason' => 'intent_tx_not_pending' );
			}
			$ful = self::fulfill_purchase_by_transaction( (int) $tx->id, 'admin_retry_receipt' );
			if ( empty( $ful['ok'] ) ) {
				return array( 'ok' => false, 'reason' => (string) ( $ful['reason'] ?? 'fulfill_failed' ) );
			}
			$tx2 = SimpleVPBot_Model_Transaction::find( (int) $tx->id );
			if ( 'pending' === (string) $rec->status ) {
				SimpleVPBot_Model_Receipt::update(
					$rid,
					array(
						'status'     => 'approved',
						'decided_at' => current_time( 'mysql' ),
					)
				);
			}
			$markup_retry = array(
				'inline_keyboard' => array(
					array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '✅ سفارش اعمال شد (' . ( $label ?: 'admin' ) . ')' ), 'callback_data' => 'noop' ) ),
				),
			);
			self::edit_admin_messages( $rec, $markup_retry );
			return array(
				'ok'         => true,
				'reason'     => 'intent_fulfilled',
				'service_id' => $tx2 && ! empty( $tx2->service_id ) ? (int) $tx2->service_id : 0,
			);
		}
		if ( empty( $meta['plan_id'] ) ) {
			return array( 'ok' => false, 'reason' => 'no_plan_id' );
		}
		$vol = isset( $meta['volume_gb'] ) ? (int) $meta['volume_gb'] : null;
		$det = SimpleVPBot_Service_Provisioner::create_from_plan_detailed( (int) $tx->user_id, (int) $meta['plan_id'], $vol );
		if ( empty( $det['ok'] ) ) {
			return array(
				'ok'     => false,
				'reason' => (string) ( $det['reason'] ?? 'provision_failed' ),
				'detail' => (string) ( $det['detail'] ?? '' ),
			);
		}
		SimpleVPBot_Model_Transaction::update(
			(int) $tx->id,
			array(
				'status'     => 'approved',
				'service_id' => (int) $det['service_id'],
			)
		);
		if ( 'pending' === (string) $rec->status ) {
			SimpleVPBot_Model_Receipt::update(
				$rid,
				array(
					'status'     => 'approved',
					'decided_at' => current_time( 'mysql' ),
				)
			);
		}
		$markup_done = array(
			'inline_keyboard' => array(
				array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '✅ سرویس ساخته شد (' . ( $label ?: 'admin' ) . ')' ), 'callback_data' => 'noop' ) ),
			),
		);
		self::edit_admin_messages( $rec, $markup_done );
		$user = SimpleVPBot_Model_User::find( (int) $rec->user_id );
		if ( $user ) {
			self::notify_user_service_ready( $user, (int) $det['service_id'] );
		}
		SimpleVPBot_Purchase_Side_Effects::on_paid_transaction( (int) $tx->id );
		return array( 'ok' => true, 'reason' => 'provisioned', 'service_id' => (int) $det['service_id'] );
	}

	/**
	 * Approve a purchase transaction without a receipt (e.g. Bale wallet SuccessfulPayment).
	 *
	 * @param int    $tx_id         Transaction id.
	 * @param string $source_label  Log label (e.g. bale_wallet).
	 * @return array{ok:bool, reason?:string, tx?:object, meta?:array}
	 */
	public static function fulfill_purchase_by_transaction( $tx_id, $source_label = 'bale_wallet' ) {
		$tx = SimpleVPBot_Model_Transaction::find( (int) $tx_id );
		if ( ! $tx || 'pending' !== (string) $tx->status || 'purchase' !== (string) $tx->type ) {
			return array( 'ok' => false, 'reason' => 'bad_tx' );
		}
		$meta = json_decode( (string) $tx->meta_json, true );
		if ( ! is_array( $meta ) ) {
			return array( 'ok' => false, 'reason' => 'no_plan' );
		}
		self::normalize_intent_meta( $meta );
		$intent = isset( $meta['intent'] ) ? (string) $meta['intent'] : '';

		if ( 'renew_same' === $intent ) {
			$res_svc = self::resolve_intent_service_for_transaction( $tx, $meta );
			if ( ! $res_svc['ok'] ) {
				return array( 'ok' => false, 'reason' => (string) $res_svc['reason'] );
			}
			$intent_sid = (int) $res_svc['service_id'];
			$rn         = SimpleVPBot_Service_Renew::apply_after_payment( $intent_sid );
			if ( empty( $rn['ok'] ) ) {
				SimpleVPBot_Logger::error(
					'fulfill_purchase renew_same failed',
					array( 'tx_id' => (int) $tx->id, 'source' => (string) $source_label, 'msg' => (string) ( $rn['message'] ?? '' ) )
				);
				return array( 'ok' => false, 'reason' => 'renew_failed' );
			}
			SimpleVPBot_Model_Transaction::update(
				(int) $tx->id,
				array(
					'status'     => 'approved',
					'service_id' => $intent_sid,
					'meta_json'  => wp_json_encode( $meta ),
				)
			);
			$user = SimpleVPBot_Model_User::find( (int) $tx->user_id );
			if ( $user ) {
				self::notify_user_both_bots( $user, '✅ پرداخت شما تایید شد. ممنون!' );
				$svc = SimpleVPBot_Model_Service::find( $intent_sid );
				if ( $svc ) {
					$extra = array(
						'reply_markup' => array(
							'inline_keyboard' => array(
								array(
									array(
										'text'          => SimpleVPBot_Keyboards::glass_button_text( SimpleVPBot_Texts::get( 'btn.service.show_panel', '🖥 جزئیات سرویس' ) ),
										'callback_data' => 'svc:p:' . (int) $svc->id,
									),
								),
							),
						),
					);
					self::notify_user_both_bots( $user, '♻️ تمدید سرویس شما اعمال شد.', $extra );
				}
			}
			SimpleVPBot_Purchase_Side_Effects::on_paid_transaction( (int) $tx->id );
			return array( 'ok' => true, 'tx' => $tx, 'meta' => $meta );
		}

		if ( 'add_volume' === $intent && (int) ( $meta['extra_gb'] ?? 0 ) >= 1 ) {
			$res_svc = self::resolve_intent_service_for_transaction( $tx, $meta );
			if ( ! $res_svc['ok'] ) {
				SimpleVPBot_Logger::error(
					'fulfill_purchase add_volume resolve failed',
					array(
						'tx_id'      => (int) $tx->id,
						'source'     => (string) $source_label,
						'reason'     => (string) $res_svc['reason'],
						'extra_gb'   => (int) ( $meta['extra_gb'] ?? 0 ),
						'service_id' => (int) ( $meta['service_id'] ?? 0 ),
					)
				);
				return array( 'ok' => false, 'reason' => (string) $res_svc['reason'] );
			}
			$intent_sid = (int) $res_svc['service_id'];
			$rn         = SimpleVPBot_Service_Renew::apply_add_volume_after_payment( $intent_sid, (int) $meta['extra_gb'] );
			if ( empty( $rn['ok'] ) ) {
				SimpleVPBot_Logger::error(
					'fulfill_purchase add_volume failed',
					array(
						'tx_id'      => (int) $tx->id,
						'source'     => (string) $source_label,
						'msg'        => (string) ( $rn['message'] ?? '' ),
						'service_id' => $intent_sid,
						'extra_gb'   => (int) ( $meta['extra_gb'] ?? 0 ),
					)
				);
				return array( 'ok' => false, 'reason' => 'add_volume_failed' );
			}
			SimpleVPBot_Model_Transaction::update(
				(int) $tx->id,
				array(
					'status'     => 'approved',
					'service_id' => $intent_sid,
					'meta_json'  => wp_json_encode( $meta ),
				)
			);
			$user = SimpleVPBot_Model_User::find( (int) $tx->user_id );
			if ( $user ) {
				self::notify_user_both_bots( $user, '✅ پرداخت شما تایید شد. ممنون!' );
				$svc = SimpleVPBot_Model_Service::find( $intent_sid );
				if ( $svc ) {
					$extra = array(
						'reply_markup' => array(
							'inline_keyboard' => array(
								array(
									array(
										'text'          => SimpleVPBot_Keyboards::glass_button_text( SimpleVPBot_Texts::get( 'btn.service.show_panel', '🖥 جزئیات سرویس' ) ),
										'callback_data' => 'svc:p:' . (int) $svc->id,
									),
								),
							),
						),
					);
					self::notify_user_both_bots( $user, '➕ حجم سرویس شما افزایش یافت.', $extra );
				}
			}
			SimpleVPBot_Purchase_Side_Effects::on_paid_transaction( (int) $tx->id );
			return array( 'ok' => true, 'tx' => $tx, 'meta' => $meta );
		}

		if ( 'add_user_slots' === $intent && isset( $meta['extra_users'] ) ) {
			$res_svc = self::resolve_intent_service_for_transaction( $tx, $meta );
			if ( ! $res_svc['ok'] ) {
				return array( 'ok' => false, 'reason' => (string) $res_svc['reason'] );
			}
			$intent_sid = (int) $res_svc['service_id'];
			$rn         = SimpleVPBot_Service_Renew::apply_add_user_slots_after_payment( $intent_sid, (int) $meta['extra_users'] );
			if ( empty( $rn['ok'] ) ) {
				SimpleVPBot_Logger::error(
					'fulfill_purchase add_user_slots failed',
					array( 'tx_id' => (int) $tx->id, 'source' => (string) $source_label, 'msg' => (string) ( $rn['message'] ?? '' ) )
				);
				return array( 'ok' => false, 'reason' => 'add_user_slots_failed' );
			}
			SimpleVPBot_Model_Transaction::update(
				(int) $tx->id,
				array(
					'status'     => 'approved',
					'service_id' => $intent_sid,
					'meta_json'  => wp_json_encode( $meta ),
				)
			);
			$user = SimpleVPBot_Model_User::find( (int) $tx->user_id );
			if ( $user ) {
				self::notify_user_both_bots( $user, '✅ پرداخت شما تایید شد. ممنون!' );
				$svc = SimpleVPBot_Model_Service::find( $intent_sid );
				if ( $svc ) {
					$extra = array(
						'reply_markup' => array(
							'inline_keyboard' => array(
								array(
									array(
										'text'          => SimpleVPBot_Keyboards::glass_button_text( SimpleVPBot_Texts::get( 'btn.service.show_panel', '🖥 جزئیات سرویس' ) ),
										'callback_data' => 'svc:p:' . (int) $svc->id,
									),
								),
							),
						),
					);
					self::notify_user_both_bots( $user, '👥 محدودیت کاربر هم‌زمان برای سرویس شما بیشتر شد.', $extra );
				}
			}
			SimpleVPBot_Purchase_Side_Effects::on_paid_transaction( (int) $tx->id );
			return array( 'ok' => true, 'tx' => $tx, 'meta' => $meta );
		}

		if ( empty( $meta['plan_id'] ) ) {
			return array( 'ok' => false, 'reason' => 'no_plan' );
		}
		$vol = isset( $meta['volume_gb'] ) ? (int) $meta['volume_gb'] : null;
		$sid = SimpleVPBot_Service_Provisioner::create_from_plan( (int) $tx->user_id, (int) $meta['plan_id'], $vol );
		if ( ! $sid ) {
			SimpleVPBot_Logger::error(
				'fulfill_purchase_by_transaction: provisioning failed',
				array( 'tx_id' => (int) $tx->id, 'source' => (string) $source_label )
			);
			return array( 'ok' => false, 'reason' => 'provision_failed' );
		}
		SimpleVPBot_Model_Transaction::update(
			(int) $tx->id,
			array(
				'status'     => 'approved',
				'service_id' => $sid,
			)
		);
		$user = SimpleVPBot_Model_User::find( (int) $tx->user_id );
		if ( $user ) {
			self::notify_user_both_bots( $user, '✅ پرداخت شما تایید شد. ممنون!' );
			$tx2 = SimpleVPBot_Model_Transaction::find( (int) $tx->id );
			$svc = $tx2 && $tx2->service_id ? SimpleVPBot_Model_Service::find( (int) $tx2->service_id ) : null;
			if ( $svc ) {
				self::notify_user_service_ready( $user, (int) $svc->id );
			}
		}
		SimpleVPBot_Purchase_Side_Effects::on_paid_transaction( (int) $tx->id );
		return array( 'ok' => true, 'tx' => $tx, 'meta' => $meta );
	}

	/**
	 * Reject a pending receipt.
	 *
	 * @param int    $rid Receipt id.
	 * @param string $admin_label Admin label.
	 * @return array{ok:bool, reason:string}
	 */
	public static function reject( $rid, $admin_label ) {
		$label = (string) $admin_label;
		$rec   = SimpleVPBot_Model_Receipt::find( (int) $rid );
		if ( ! $rec || 'pending' !== $rec->status ) {
			return array( 'ok' => false, 'reason' => 'not_pending' );
		}
		$tx = SimpleVPBot_Model_Transaction::find( (int) $rec->transaction_id );
		if ( ! $tx ) {
			return array( 'ok' => false, 'reason' => 'no_tx' );
		}
		SimpleVPBot_Model_Receipt::update(
			(int) $rid,
			array(
				'status'     => 'rejected',
				'decided_at' => current_time( 'mysql' ),
			)
		);
		SimpleVPBot_Model_Transaction::set_status( (int) $tx->id, 'rejected' );
		$markup_done = array(
			'inline_keyboard' => array(
				array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '❌ رسید رد شد · ' . $label ), 'callback_data' => 'noop' ) ),
			),
		);
		self::edit_admin_messages( $rec, $markup_done );
		$user_row = SimpleVPBot_Model_User::find( (int) $rec->user_id );
		if ( $user_row ) {
			self::notify_user_both_bots( $user_row, '⛔ رسید پرداخت شما رد شد. در صورت اعتراض یا سوال با پشتیبانی تماس بگیرید.' );
		}
		return array( 'ok' => true, 'reason' => 'rejected' );
	}

	/**
	 * Change receipt status from WP admin (any transition among pending/approved/rejected).
	 *
	 * @param int    $rid Receipt id.
	 * @param string $new_status pending|approved|rejected.
	 * @param string $admin_label WP user_login or label.
	 * @return array{ok:bool, reason?:string, purchase_failed?:bool, note?:string}
	 */
	public static function admin_set_receipt_status( $rid, $new_status, $admin_label ) {
		$rid        = (int) $rid;
		$new_status = sanitize_key( (string) $new_status );
		$label      = (string) $admin_label;
		if ( ! in_array( $new_status, array( 'pending', 'approved', 'rejected' ), true ) ) {
			return array( 'ok' => false, 'reason' => 'bad_status' );
		}
		$rec = SimpleVPBot_Model_Receipt::find( $rid );
		if ( ! $rec ) {
			return array( 'ok' => false, 'reason' => 'not_found' );
		}
		$old = (string) $rec->status;
		if ( $old === $new_status ) {
			return array( 'ok' => true, 'reason' => 'noop' );
		}
		$tx = SimpleVPBot_Model_Transaction::find( (int) $rec->transaction_id );
		if ( ! $tx ) {
			return array( 'ok' => false, 'reason' => 'no_tx' );
		}

		if ( 'pending' === $old && 'approved' === $new_status ) {
			return self::approve( $rid, $label );
		}
		if ( 'pending' === $old && 'rejected' === $new_status ) {
			return self::reject( $rid, $label );
		}

		if ( 'rejected' === $old && 'approved' === $new_status ) {
			SimpleVPBot_Model_Receipt::update( $rid, array( 'status' => 'pending', 'decided_at' => null ) );
			SimpleVPBot_Model_Transaction::set_status( (int) $tx->id, 'pending' );
			return self::approve( $rid, $label );
		}
		if ( 'rejected' === $old && 'pending' === $new_status ) {
			SimpleVPBot_Model_Receipt::update( $rid, array( 'status' => 'pending', 'decided_at' => null ) );
			SimpleVPBot_Model_Transaction::set_status( (int) $tx->id, 'pending' );
			return array( 'ok' => true, 'reason' => 'reset_pending' );
		}

		if ( 'approved' === $old && 'rejected' === $new_status ) {
			$rev = self::reverse_receipt_approval_effects( $rec, $tx );
			if ( empty( $rev['ok'] ) ) {
				return $rev;
			}
			SimpleVPBot_Model_Receipt::update(
				$rid,
				array(
					'status'     => 'rejected',
					'decided_at' => current_time( 'mysql' ),
				)
			);
			SimpleVPBot_Model_Transaction::set_status( (int) $tx->id, 'rejected' );
			self::edit_admin_messages(
				$rec,
				array(
					'inline_keyboard' => array(
						array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '❌ رسید بعد از تایید رد شد · ' . $label ), 'callback_data' => 'noop' ) ),
					),
				)
			);
			$user_row = SimpleVPBot_Model_User::find( (int) $rec->user_id );
			if ( $user_row ) {
				self::notify_user_both_bots(
					$user_row,
					'⛔ رسید پرداخت شما پس از تأیید اولیه رد شد و تراکنش لغو شد. در صورت نیاز با پشتیبانی تماس بگیرید.'
				);
			}
			return array( 'ok' => true, 'reason' => 'rejected_after_approved', 'note' => (string) ( $rev['note'] ?? '' ) );
		}

		if ( 'approved' === $old && 'pending' === $new_status ) {
			$rev = self::reverse_receipt_approval_effects( $rec, $tx );
			if ( empty( $rev['ok'] ) ) {
				return $rev;
			}
			SimpleVPBot_Model_Receipt::update( $rid, array( 'status' => 'pending', 'decided_at' => null ) );
			SimpleVPBot_Model_Transaction::set_status( (int) $tx->id, 'pending' );
			return array( 'ok' => true, 'reason' => 'back_to_pending', 'note' => (string) ( $rev['note'] ?? '' ) );
		}

		return array( 'ok' => false, 'reason' => 'transition_not_supported' );
	}

	/**
	 * Undo financial / tx side-effects of an approved receipt (before setting rejected/pending).
	 *
	 * @param object $rec Receipt.
	 * @param object $tx  Transaction.
	 * @return array{ok:bool, reason?:string, note?:string}
	 */
	private static function reverse_receipt_approval_effects( $rec, $tx ) {
		if ( 'approved' !== (string) $tx->status ) {
			return array( 'ok' => true, 'reason' => 'tx_not_approved' );
		}
		if ( 'topup' === (string) $tx->type ) {
			$user = SimpleVPBot_Model_User::find( (int) $tx->user_id );
			if ( $user ) {
				$new_bal = max( 0.0, (float) $user->balance - (float) $rec->amount );
				SimpleVPBot_Model_User::update( (int) $user->id, array( 'balance' => $new_bal ) );
			}
			return array( 'ok' => true, 'reason' => 'reversed_topup' );
		}
		if ( 'purchase' === (string) $tx->type ) {
			SimpleVPBot_Model_Transaction::update( (int) $tx->id, array( 'service_id' => null ) );
			return array(
				'ok'   => true,
				'note' => __( 'تراکنش خرید به‌حالت قبل برگردانده شد؛ ردیف سرویس در دیتابیس ممکن است باقی مانده باشد.', 'simplevpbot' ),
			);
		}
		return array( 'ok' => true, 'reason' => 'no_effect' );
	}

	/**
	 * Update inline keyboard on all admin messages for this receipt.
	 *
	 * @param object               $rec Receipt row.
	 * @param array<string, mixed> $markup Reply markup.
	 */
	public static function edit_admin_messages( $rec, array $markup ) {
		$list = json_decode( (string) $rec->admin_messages_json, true );
		if ( ! is_array( $list ) ) {
			return;
		}
		$fallback = '';
		if ( ! empty( $markup['inline_keyboard'][0][0]['text'] ) ) {
			$fallback = (string) $markup['inline_keyboard'][0][0]['text'];
		}
		if ( '' === $fallback ) {
			$fallback = 'رسید #' . (int) $rec->id . ' به‌روز شد.';
		}
		foreach ( $list as $m ) {
			$plat = isset( $m['platform'] ) ? (string) $m['platform'] : 'telegram';
			$cid  = (int) ( $m['chat_id'] ?? 0 );
			$mid  = (int) ( $m['message_id'] ?? 0 );
			if ( ! $cid || ! $mid ) {
				continue;
			}
			$res = SimpleVPBot_Bot_Runtime::edit_reply_markup( $plat, $cid, $mid, $markup );
			if ( ! is_array( $res ) || empty( $res['ok'] ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $plat, $cid, $fallback );
			}
		}
	}

	/**
	 * Notify user that a newly provisioned service is ready (both bots + details button).
	 *
	 * @param object $user       svp_users row.
	 * @param int    $service_id svp_services.id.
	 */
	public static function notify_user_service_ready( $user, $service_id ) {
		$sid = (int) $service_id;
		if ( $sid < 1 || ! is_object( $user ) ) {
			return;
		}
		$extra = array(
			'reply_markup' => array(
				'inline_keyboard' => array(
					array(
						array(
							'text'          => SimpleVPBot_Keyboards::glass_button_text( SimpleVPBot_Texts::get( 'btn.service.show_panel', '🖥 جزئیات سرویس' ) ),
							'callback_data' => 'svc:p:' . $sid,
						),
					),
				),
			),
		);
		self::notify_user_both_bots( $user, '🎉 سرویس جدید شما آماده است.', $extra );
	}

	/**
	 * Notify end user on Telegram and/or Bale.
	 *
	 * @param object               $user User row.
	 * @param string               $text Text.
	 * @param array<string, mixed> $extra Extra.
	 */
	public static function notify_user_both_bots( $user, $text, array $extra = array() ) {
		if ( ! empty( $user->tg_user_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( 'telegram', (int) $user->tg_user_id, $text, $extra );
		}
		if ( ! empty( $user->bale_user_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message( 'bale', (int) $user->bale_user_id, $text, $extra );
		}
	}
}
