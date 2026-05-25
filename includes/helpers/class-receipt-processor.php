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
		if ( ! $rec ) {
			return array( 'ok' => false, 'reason' => 'not_found' );
		}
		if ( 'approved' === (string) $rec->status ) {
			return array( 'ok' => true, 'reason' => 'already_approved' );
		}
		if ( 'processing' === (string) $rec->status ) {
			return array( 'ok' => false, 'reason' => 'already_processing' );
		}
		if ( 'pending' !== (string) $rec->status ) {
			return array( 'ok' => false, 'reason' => 'not_pending' );
		}
		if ( ! SimpleVPBot_Model_Receipt::claim_pending( $rid ) ) {
			$rec2 = SimpleVPBot_Model_Receipt::find( $rid );
			if ( $rec2 && 'approved' === (string) $rec2->status ) {
				return array( 'ok' => true, 'reason' => 'already_approved' );
			}
			return array( 'ok' => false, 'reason' => 'already_processing' );
		}
		$rec = SimpleVPBot_Model_Receipt::find( $rid );
		$tx  = $rec ? SimpleVPBot_Model_Transaction::find( (int) $rec->transaction_id ) : null;
		if ( ! $rec || ! $tx ) {
			SimpleVPBot_Model_Receipt::release_to_pending( $rid );
			return array( 'ok' => false, 'reason' => 'no_tx' );
		}
		$meta = json_decode( (string) $tx->meta_json, true );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		self::normalize_intent_meta( $meta );
		$effects = self::execute_approve_effects( $tx, $rec, $meta );
		if ( ! empty( $effects['purchase_failed'] ) ) {
			SimpleVPBot_Model_Receipt::release_to_pending( $rid );
			$provision_info = $effects['provision_info'] ?? null;
			$provision_err  = self::format_provision_error_for_admin(
				(string) ( $effects['provision_error'] ?? 'purchase_failed' ),
				$provision_info
			);
			$markup_err     = array(
				'inline_keyboard' => array(
					array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '⚠️ خطا در آماده‌سازی سرویس: ' . $provision_err . ' · ' . $label ), 'callback_data' => 'noop' ) ),
				),
			);
			self::edit_admin_messages( $rec, $markup_err );
			$raw_reason = (string) ( $effects['provision_error'] ?? 'purchase_failed' );
			return array(
				'ok'              => false,
				'reason'          => $provision_err,
				'message'         => $provision_err,
				'reason_code'     => 'purchase_failed',
				'provision_reason'=> $raw_reason,
				'purchase_failed' => true,
				'rec'             => $rec,
				'tx'              => $tx,
				'meta'            => $meta,
				'provision_error' => $provision_err,
				'provision_info'  => $provision_info,
			);
		}
		if ( empty( $effects['ok'] ) ) {
			SimpleVPBot_Model_Receipt::release_to_pending( $rid );
			return array( 'ok' => false, 'reason' => (string) ( $effects['reason'] ?? 'effects_failed' ) );
		}
		if ( ! SimpleVPBot_Model_Receipt::try_finalize_approved( $rid ) ) {
			return array( 'ok' => false, 'reason' => 'finalize_race' );
		}
		$markup_done = array(
			'inline_keyboard' => array(
				array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '✅ رسید تایید شد · ' . $label ), 'callback_data' => 'noop' ) ),
			),
		);
		self::edit_admin_messages( $rec, $markup_done );
		$user_row = SimpleVPBot_Model_User::find( (int) $rec->user_id );
		if ( $user_row ) {
			self::notify_user_both_bots( $user_row, '✅ پرداخت شما تایید شد. ممنون!', array(), $tx );
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
						self::notify_user_both_bots( $user_row, '♻️ تمدید سرویس شما اعمال شد.', $extra, $tx );
					} elseif ( 'add_volume' === $intent ) {
						self::notify_user_both_bots( $user_row, '➕ حجم سرویس شما افزایش یافت.', $extra, $tx );
					} elseif ( 'add_user_slots' === $intent ) {
						self::notify_user_both_bots( $user_row, '👥 محدودیت کاربر هم‌زمان برای سرویس شما بیشتر شد.', $extra, $tx );
					} elseif ( ! empty( $meta['plan_id'] ) ) {
						self::notify_user_service_ready( $user_row, (int) $svc->id, $tx );
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
		if ( class_exists( 'SimpleVPBot_Audit_Log' ) ) {
			$actor = SimpleVPBot_Audit_Log::current_actor_fields();
			SimpleVPBot_Audit_Log::record(
				array_merge(
					$actor,
					array(
						'domain'      => 'billing',
						'event_type'  => 'receipt.approve',
						'target_type' => 'receipt',
						'target_id'   => (int) $rid,
						'payload'     => array(
							'tx_id'   => (int) $tx->id,
							'user_id' => (int) $rec->user_id,
							'label'   => (string) $label,
						),
					)
				)
			);
		}
		return array( 'ok' => true, 'reason' => 'approved', 'rec' => $rec, 'tx' => $tx, 'meta' => $meta );
	}

	/**
	 * Apply financial + provisioning effects for an approved receipt (tx must be pending).
	 *
	 * @param object               $tx   Transaction row.
	 * @param object               $rec  Receipt row.
	 * @param array<string, mixed> $meta Decoded meta_json.
	 * @return array{ok:bool, reason?:string, purchase_failed?:bool, provision_error?:string, provision_info?:mixed}
	 */
	private static function execute_approve_effects( $tx, $rec, array &$meta ) {
		$purchase_failed = false;
		$provision_err   = '';
		$provision_info  = null;
		if ( 'topup' === $tx->type ) {
			if ( ! SimpleVPBot_Model_User::increment_balance( (int) $tx->user_id, (float) $rec->amount ) ) {
				return array( 'ok' => false, 'reason' => 'topup_user_missing' );
			}
			if ( ! SimpleVPBot_Model_Transaction::try_approve_from_pending( (int) $tx->id ) ) {
				return array( 'ok' => false, 'reason' => 'tx_not_pending' );
			}
			return array( 'ok' => true );
		}
		if ( 'purchase' !== $tx->type ) {
			return array( 'ok' => false, 'reason' => 'unsupported_tx_type' );
		}
		if ( ! empty( $meta['intent'] ) && 'renew_same' === (string) $meta['intent'] ) {
			$res_svc = self::resolve_intent_service_for_transaction( $tx, $meta, true );
			if ( ! $res_svc['ok'] ) {
				$purchase_failed = true;
				$provision_err   = (string) $res_svc['reason'];
			} else {
				$rn = SimpleVPBot_Service_Renew::apply_after_payment( (int) $res_svc['service_id'] );
				if ( ! empty( $rn['ok'] ) ) {
					if ( ! self::try_approve_purchase_tx( $tx, $meta, (int) $res_svc['service_id'] ) ) {
						$purchase_failed = true;
						$provision_err   = 'tx_not_pending';
					}
				} else {
					$purchase_failed = true;
					$provision_err   = (string) ( $rn['message'] ?? 'renew_failed' );
					SimpleVPBot_Logger::error(
						'purchase renew_same failed',
						array(
							'tx_id'  => (int) $tx->id,
							'rid'    => (int) $rec->id,
							'reason' => $provision_err,
						)
					);
				}
			}
		} elseif ( ! empty( $meta['intent'] ) && 'add_volume' === (string) $meta['intent'] && (int) ( $meta['extra_gb'] ?? 0 ) >= 1 ) {
			$res_svc = self::resolve_intent_service_for_transaction( $tx, $meta, true );
			if ( ! $res_svc['ok'] ) {
				$purchase_failed = true;
				$provision_err   = (string) $res_svc['reason'];
				SimpleVPBot_Logger::error(
					'purchase add_volume resolve failed',
					array(
						'tx_id'      => (int) $tx->id,
						'rid'        => (int) $rec->id,
						'reason'     => $provision_err,
						'extra_gb'   => (int) ( $meta['extra_gb'] ?? 0 ),
						'service_id' => (int) ( $meta['service_id'] ?? 0 ),
					)
				);
			} else {
				$rn = SimpleVPBot_Service_Renew::apply_add_volume_after_payment( (int) $res_svc['service_id'], (int) $meta['extra_gb'] );
				if ( ! empty( $rn['ok'] ) ) {
					if ( ! self::try_approve_purchase_tx( $tx, $meta, (int) $res_svc['service_id'] ) ) {
						$purchase_failed = true;
						$provision_err   = 'tx_not_pending';
					}
				} else {
					$purchase_failed = true;
					$provision_err   = (string) ( $rn['message'] ?? 'add_volume_failed' );
					SimpleVPBot_Logger::error(
						'purchase add_volume failed',
						array(
							'tx_id'      => (int) $tx->id,
							'rid'        => (int) $rec->id,
							'reason'     => $provision_err,
							'intent'     => 'add_volume',
							'service_id' => (int) ( $meta['service_id'] ?? 0 ),
							'extra_gb'   => (int) ( $meta['extra_gb'] ?? 0 ),
							'tx_user_id' => (int) $tx->user_id,
						)
					);
				}
			}
		} elseif ( ! empty( $meta['intent'] ) && 'add_user_slots' === (string) $meta['intent'] && isset( $meta['extra_users'] ) ) {
			$res_svc = self::resolve_intent_service_for_transaction( $tx, $meta, true );
			if ( ! $res_svc['ok'] ) {
				$purchase_failed = true;
				$provision_err   = (string) $res_svc['reason'];
			} else {
				$rn = SimpleVPBot_Service_Renew::apply_add_user_slots_after_payment( (int) $res_svc['service_id'], (int) $meta['extra_users'] );
				if ( ! empty( $rn['ok'] ) ) {
					if ( ! self::try_approve_purchase_tx( $tx, $meta, (int) $res_svc['service_id'] ) ) {
						$purchase_failed = true;
						$provision_err   = 'tx_not_pending';
					}
				} else {
					$purchase_failed = true;
					$provision_err   = (string) ( $rn['message'] ?? 'add_user_slots_failed' );
					SimpleVPBot_Logger::error(
						'purchase add_user_slots failed',
						array(
							'tx_id'  => (int) $tx->id,
							'rid'    => (int) $rec->id,
							'reason' => $provision_err,
						)
					);
				}
			}
		} elseif ( ! empty( $meta['plan_id'] ) ) {
			$vol = isset( $meta['volume_gb'] ) ? (int) $meta['volume_gb'] : null;
			$det = SimpleVPBot_Service_Provisioner::create_from_plan_detailed( (int) $tx->user_id, (int) $meta['plan_id'], $vol );
			if ( ! empty( $det['ok'] ) ) {
				if ( ! self::try_approve_purchase_tx( $tx, $meta, (int) $det['service_id'] ) ) {
					$purchase_failed = true;
					$provision_err   = 'tx_not_pending';
				}
			} else {
				$purchase_failed = true;
				$provision_err   = (string) ( $det['reason'] ?? 'purchase_failed' );
				$provision_info  = $det;
				SimpleVPBot_Logger::error(
					'purchase provisioning failed',
					array(
						'tx_id'  => (int) $tx->id,
						'rid'    => (int) $rec->id,
						'reason' => $provision_err,
						'detail' => (string) ( $det['detail'] ?? '' ),
					)
				);
			}
		} else {
			$purchase_failed = true;
			$intent          = isset( $meta['intent'] ) ? (string) $meta['intent'] : '';
			if ( 'add_volume' === $intent && (int) ( $meta['extra_gb'] ?? 0 ) < 1 ) {
				$provision_err = 'extra_gb_missing';
			} else {
				$provision_err = 'no_plan_id';
			}
			SimpleVPBot_Logger::error(
				'purchase fallback no_plan_id',
				array(
					'tx_id'      => (int) $tx->id,
					'rid'        => (int) $rec->id,
					'intent'     => $intent,
					'plan_id'    => (int) ( $meta['plan_id'] ?? 0 ),
					'service_id' => (int) ( $meta['service_id'] ?? 0 ),
					'extra_gb'   => (int) ( $meta['extra_gb'] ?? 0 ),
				)
			);
		}

		if ( $purchase_failed ) {
			SimpleVPBot_Logger::error(
				'receipt approve provisioning failed',
				array(
					'tx_id'            => (int) $tx->id,
					'rid'              => (int) $rec->id,
					'provision_reason' => (string) $provision_err,
					'intent'           => isset( $meta['intent'] ) ? (string) $meta['intent'] : '',
					'plan_id'          => (int) ( $meta['plan_id'] ?? 0 ),
					'service_id'       => (int) ( $meta['service_id'] ?? 0 ),
				)
			);
			return array(
				'ok'              => false,
				'purchase_failed' => true,
				'provision_error' => $provision_err,
				'provision_info'  => $provision_info,
			);
		}
		return array( 'ok' => true );
	}

	/**
	 * Conditionally approve purchase transaction after successful provision.
	 *
	 * @param object               $tx         Transaction row.
	 * @param array<string, mixed> $meta       Meta (encoded on approve).
	 * @param int                  $service_id Linked service id.
	 * @return bool
	 */
	private static function try_approve_purchase_tx( $tx, array $meta, $service_id ) {
		return SimpleVPBot_Model_Transaction::try_approve_from_pending(
			(int) $tx->id,
			array(
				'service_id' => (int) $service_id,
				'meta_json'  => wp_json_encode( $meta ),
			)
		);
	}

	/**
	 * Roll back a freshly provisioned service when transaction approval fails (avoid orphan panel client).
	 *
	 * @param int $service_id svp_services.id.
	 */
	private static function revert_orphan_provisioned_service( $service_id ) {
		$sid = (int) $service_id;
		if ( $sid < 1 ) {
			return;
		}
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc || ! empty( $svc->deleted_at ) ) {
			return;
		}
		if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			if ( class_exists( 'SimpleVPBot_L2TP_Provisioner' ) ) {
				SimpleVPBot_L2TP_Provisioner::delete_user( $svc );
			}
			SimpleVPBot_Model_Service::soft_delete( $sid );
			return;
		}
		if ( class_exists( 'SimpleVPBot_Service_Dashboard_Panel' ) ) {
			SimpleVPBot_Service_Dashboard_Panel::xray_delete_panel_client( $sid );
			return;
		}
		SimpleVPBot_Model_Service::soft_delete( $sid );
	}

	/**
	 * Short Persian label for admin receipt button when provisioning fails.
	 *
	 * @param string               $raw  Machine reason or localized message from renew/provisioner.
	 * @param array<string, mixed>|null $info Optional create_from_plan_detailed payload.
	 * @return string
	 */
	private static function format_provision_error_for_admin( $raw, $info = null ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			$raw = 'purchase_failed';
		}
		if ( 0 === strpos( $raw, '⛔' ) || 0 === strpos( $raw, '⚠️' ) ) {
			$out = $raw;
		} else {
			$map = array(
				'addclient_panel'        => '⛔ ساخت کلاینت روی پنل',
				'panel_quota_patch_failed' => '⛔ تنظیم سهمیه روی پنل',
				'panel_verify_failed'    => '⛔ تأیید کلاینت روی پنل',
				'login_fail'             => '⛔ ورود به پنل',
				'inbound_not_found'      => '⛔ اینباند پنل یافت نشد',
				'inbound_missing'        => '⛔ اینباند پلن تنظیم نشده',
				'uuid_missing'           => '⛔ UUID پنل',
				'db_insert'              => '⛔ ذخیره در دیتابیس',
				'plan_missing_or_inactive' => '⛔ پلن نامعتبر',
				'volume_out_of_range'    => '⛔ حجم خارج از محدوده',
				'l2tp_create_failed'     => '⛔ ساخت L2TP',
				'l2tp_disabled'          => '⛔ L2TP غیرفعال',
				'no_plan_id'             => '⛔ پلن در سفارش نیست',
				'extra_gb_missing'       => '⛔ حجم اضافه در سفارش نیست',
				'service_id_missing'     => '⛔ سرویس در سفارش نیست',
				'service_not_found'      => '⛔ سرویس یافت نشد',
				'service_mismatch'       => '⛔ سرویس متعلق به کاربر نیست',
				'renew_failed'           => '⛔ تمدید سرویس',
				'add_volume_failed'      => '⛔ افزایش حجم',
				'add_user_slots_failed'  => '⛔ افزایش کاربر هم‌زمان',
				'tx_not_pending'         => '⛔ تراکنش دیگر معلق نیست',
				'purchase_failed'        => '⛔ آماده‌سازی سرویس',
				'provision_failed'       => '⛔ آماده‌سازی سرویس',
			);
			$out = isset( $map[ $raw ] ) ? (string) $map[ $raw ] : $raw;
			if ( is_array( $info ) ) {
				$panel = $info['panel'] ?? null;
				$pm    = is_array( $panel ) ? trim( (string) ( $panel['msg'] ?? '' ) ) : '';
				if ( '' === $pm ) {
					$pm = trim( (string) ( $info['detail'] ?? '' ) );
				}
				if ( '' !== $pm ) {
					$out .= ' (' . $pm . ')';
				}
			}
		}
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) && mb_strlen( $out, 'UTF-8' ) > 120 ) {
			return mb_substr( $out, 0, 117, 'UTF-8' ) . '…';
		}
		if ( strlen( $out ) > 120 ) {
			return substr( $out, 0, 117 ) . '…';
		}
		return $out;
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
	private static function resolve_intent_service_for_transaction( $tx, array &$meta, $strict = false ) {
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
		if ( ! $strict ) {
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
			self::notify_user_service_ready( $user, (int) $det['service_id'], $tx );
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
			$res_svc = self::resolve_intent_service_for_transaction( $tx, $meta, true );
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
			if ( ! self::try_approve_purchase_tx( $tx, $meta, $intent_sid ) ) {
				return array( 'ok' => false, 'reason' => 'tx_not_pending' );
			}
			$user = SimpleVPBot_Model_User::find( (int) $tx->user_id );
			if ( $user ) {
				self::notify_user_both_bots( $user, '✅ پرداخت شما تایید شد. ممنون!', array(), $tx );
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
					self::notify_user_both_bots( $user, '♻️ تمدید سرویس شما اعمال شد.', $extra, $tx );
				}
			}
			SimpleVPBot_Purchase_Side_Effects::on_paid_transaction( (int) $tx->id );
			return array( 'ok' => true, 'tx' => $tx, 'meta' => $meta );
		}

		if ( 'add_volume' === $intent && (int) ( $meta['extra_gb'] ?? 0 ) >= 1 ) {
			$res_svc = self::resolve_intent_service_for_transaction( $tx, $meta, true );
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
			if ( ! self::try_approve_purchase_tx( $tx, $meta, $intent_sid ) ) {
				return array( 'ok' => false, 'reason' => 'tx_not_pending' );
			}
			$user = SimpleVPBot_Model_User::find( (int) $tx->user_id );
			if ( $user ) {
				self::notify_user_both_bots( $user, '✅ پرداخت شما تایید شد. ممنون!', array(), $tx );
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
					self::notify_user_both_bots( $user, '➕ حجم سرویس شما افزایش یافت.', $extra, $tx );
				}
			}
			SimpleVPBot_Purchase_Side_Effects::on_paid_transaction( (int) $tx->id );
			return array( 'ok' => true, 'tx' => $tx, 'meta' => $meta );
		}

		if ( 'add_user_slots' === $intent && isset( $meta['extra_users'] ) ) {
			$res_svc = self::resolve_intent_service_for_transaction( $tx, $meta, true );
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
			if ( ! self::try_approve_purchase_tx( $tx, $meta, $intent_sid ) ) {
				return array( 'ok' => false, 'reason' => 'tx_not_pending' );
			}
			$user = SimpleVPBot_Model_User::find( (int) $tx->user_id );
			if ( $user ) {
				self::notify_user_both_bots( $user, '✅ پرداخت شما تایید شد. ممنون!', array(), $tx );
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
					self::notify_user_both_bots( $user, '👥 محدودیت کاربر هم‌زمان برای سرویس شما بیشتر شد.', $extra, $tx );
				}
			}
			SimpleVPBot_Purchase_Side_Effects::on_paid_transaction( (int) $tx->id );
			return array( 'ok' => true, 'tx' => $tx, 'meta' => $meta );
		}

		if ( empty( $meta['plan_id'] ) ) {
			return array( 'ok' => false, 'reason' => 'no_plan' );
		}
		$vol = isset( $meta['volume_gb'] ) ? (int) $meta['volume_gb'] : null;
		$det = SimpleVPBot_Service_Provisioner::create_from_plan_detailed( (int) $tx->user_id, (int) $meta['plan_id'], $vol );
		if ( empty( $det['ok'] ) || empty( $det['service_id'] ) ) {
			SimpleVPBot_Logger::error(
				'fulfill_purchase_by_transaction: provisioning failed',
				array(
					'tx_id'  => (int) $tx->id,
					'source' => (string) $source_label,
					'reason' => (string) ( $det['reason'] ?? 'provision_failed' ),
				)
			);
			return array( 'ok' => false, 'reason' => 'provision_failed' );
		}
		$sid = (int) $det['service_id'];
		if ( ! self::try_approve_purchase_tx( $tx, $meta, $sid ) ) {
			self::revert_orphan_provisioned_service( $sid );
			SimpleVPBot_Logger::error(
				'fulfill_purchase_by_transaction: approve failed after provision',
				array( 'tx_id' => (int) $tx->id, 'source' => (string) $source_label, 'service_id' => $sid )
			);
			return array( 'ok' => false, 'reason' => 'tx_not_pending' );
		}
		$user = SimpleVPBot_Model_User::find( (int) $tx->user_id );
		if ( $user ) {
			self::notify_user_both_bots( $user, '✅ پرداخت شما تایید شد. ممنون!', array(), $tx );
			$tx2 = SimpleVPBot_Model_Transaction::find( (int) $tx->id );
			$svc = $tx2 && $tx2->service_id ? SimpleVPBot_Model_Service::find( (int) $tx2->service_id ) : null;
			if ( $svc ) {
				self::notify_user_service_ready( $user, (int) $svc->id, $tx );
			}
		}
		SimpleVPBot_Purchase_Side_Effects::on_paid_transaction( (int) $tx->id );
		return array( 'ok' => true, 'tx' => $tx, 'meta' => $meta );
	}

	/**
	 * Configured receipt reject reasons (site settings).
	 *
	 * @return array<int, string>
	 */
	public static function reject_reasons_list() {
		$raw = class_exists( 'SimpleVPBot_Settings' )
			? SimpleVPBot_Settings::get( 'receipt_reject_reasons' )
			: array();
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $line ) {
			$t = trim( (string) $line );
			if ( '' !== $t ) {
				$out[] = $t;
			}
		}
		return $out;
	}

	/**
	 * Reason text by list index from settings.
	 *
	 * @param int $index Zero-based index.
	 * @return string
	 */
	public static function reject_reason_by_index( $index ) {
		$list = self::reject_reasons_list();
		$i    = (int) $index;
		return isset( $list[ $i ] ) ? (string) $list[ $i ] : '';
	}

	/**
	 * Reject a pending receipt.
	 *
	 * @param int    $rid Receipt id.
	 * @param string $admin_label Admin label.
	 * @param string $reject_reason Optional reason sent to user.
	 * @return array{ok:bool, reason:string}
	 */
	public static function reject( $rid, $admin_label, $reject_reason = '' ) {
		$label = (string) $admin_label;
		$rec   = SimpleVPBot_Model_Receipt::find( (int) $rid );
		if ( ! $rec || ! in_array( (string) $rec->status, array( 'pending', 'processing' ), true ) ) {
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
		$reject_reason = trim( (string) $reject_reason );
		$btn_line      = '❌ رسید رد شد';
		if ( '' !== $reject_reason ) {
			$short = $reject_reason;
			if ( function_exists( 'mb_strlen' ) && mb_strlen( $short ) > 36 ) {
				$short = mb_substr( $short, 0, 33 ) . '…';
			} elseif ( strlen( $short ) > 36 ) {
				$short = substr( $short, 0, 33 ) . '…';
			}
			$btn_line .= ' · ' . $short;
		}
		$btn_line .= ' · ' . $label;
		$markup_done = array(
			'inline_keyboard' => array(
				array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( $btn_line, 64 ), 'callback_data' => 'noop' ) ),
			),
		);
		self::edit_admin_messages( $rec, $markup_done );
		$user_row = SimpleVPBot_Model_User::find( (int) $rec->user_id );
		if ( $user_row ) {
			if ( '' !== $reject_reason ) {
				$msg = SimpleVPBot_Texts::format(
					SimpleVPBot_Texts::get( 'msg.receipt.rejected_with_reason', '⛔ رسید پرداخت شما به علت ({reason}) رد شد.' ),
					array( 'reason' => $reject_reason )
				);
			} else {
				$msg = '⛔ رسید پرداخت شما رد شد.';
			}
			$msg .= "\n" . 'در صورت اعتراض یا سوال با پشتیبانی تماس بگیرید.';
			self::notify_user_both_bots( $user_row, $msg, array(), $tx );
		}
		if ( class_exists( 'SimpleVPBot_Audit_Log' ) ) {
			$actor = SimpleVPBot_Audit_Log::current_actor_fields();
			SimpleVPBot_Audit_Log::record(
				array_merge(
					$actor,
					array(
						'domain'      => 'billing',
						'event_type'  => 'receipt.reject',
						'target_type' => 'receipt',
						'target_id'   => (int) $rid,
						'payload'     => array(
							'tx_id'         => (int) $tx->id,
							'reject_reason' => trim( (string) $reject_reason ),
						),
					)
				)
			);
		}
		return array( 'ok' => true, 'reason' => 'rejected' );
	}

	/**
	 * Change receipt status from WP admin (any transition among pending/approved/rejected).
	 *
	 * @param int    $rid Receipt id.
	 * @param string $new_status pending|approved|rejected.
	 * @param string $admin_label WP user_login or label.
	 * @param string $reject_reason Optional reason sent to user when rejecting.
	 * @return array{ok:bool, reason?:string, purchase_failed?:bool, note?:string}
	 */
	public static function admin_set_receipt_status( $rid, $new_status, $admin_label, $reject_reason = '' ) {
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
		if ( in_array( $old, array( 'pending', 'processing' ), true ) && 'rejected' === $new_status ) {
			return self::reject( $rid, $label, $reject_reason );
		}
		if ( 'processing' === $old && 'pending' === $new_status ) {
			SimpleVPBot_Model_Receipt::update( $rid, array( 'status' => 'pending', 'decided_at' => null ) );
			SimpleVPBot_Model_Transaction::set_status( (int) $tx->id, 'pending' );
			return array( 'ok' => true, 'reason' => 'reset_pending' );
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
			if ( class_exists( 'SimpleVPBot_Audit_Log' ) ) {
				$actor = SimpleVPBot_Audit_Log::current_actor_fields();
				SimpleVPBot_Audit_Log::record(
					array_merge(
						$actor,
						array(
							'domain'      => 'billing',
							'event_type'  => 'receipt.reject_after_approve',
							'target_type' => 'receipt',
							'target_id'   => (int) $rid,
							'payload'     => array(
								'tx_id'      => (int) $tx->id,
								'service_id' => (int) ( $rev['service_id'] ?? 0 ),
								'reason'     => (string) ( $rev['reason'] ?? '' ),
							),
						)
					)
				);
			}
			$user_row = SimpleVPBot_Model_User::find( (int) $rec->user_id );
			if ( $user_row ) {
				$msg = '⛔ رسید پرداخت شما پس از تأیید اولیه رد شد و تراکنش لغو شد.';
				$reject_reason = trim( (string) $reject_reason );
				if ( '' !== $reject_reason ) {
					$msg .= "\n" . 'دلیل: ' . $reject_reason;
				}
				$msg .= "\n" . 'در صورت نیاز با پشتیبانی تماس بگیرید.';
				self::notify_user_both_bots(
					$user_row,
					$msg,
					array(),
					$tx
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
			$uid = (int) $tx->user_id;
			if ( $uid > 0 ) {
				SimpleVPBot_Model_User::increment_balance( $uid, -1 * (float) $rec->amount );
			}
			return array( 'ok' => true, 'reason' => 'reversed_topup' );
		}
		if ( 'purchase' === (string) $tx->type ) {
			if ( self::is_purchase_intent_modification( $tx ) ) {
				return array( 'ok' => true, 'reason' => 'intent_tx_only' );
			}
			$sid = (int) ( $tx->service_id ?? 0 );
			if ( $sid > 0 ) {
				$svc = SimpleVPBot_Model_Service::find( $sid );
				if ( $svc && empty( $svc->deleted_at ) ) {
					if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
						if ( class_exists( 'SimpleVPBot_L2TP_Provisioner' ) ) {
							SimpleVPBot_L2TP_Provisioner::delete_user( $svc );
						}
						SimpleVPBot_Model_Service::soft_delete( $sid );
					} elseif ( class_exists( 'SimpleVPBot_Service_Dashboard_Panel' ) ) {
						$del = SimpleVPBot_Service_Dashboard_Panel::xray_delete_panel_client( $sid );
						if ( empty( $del['ok'] ) ) {
							return array(
								'ok'     => false,
								'reason' => 'panel_disable_failed',
								'note'   => (string) ( $del['reason'] ?? 'del_failed' ),
							);
						}
					} else {
						SimpleVPBot_Model_Service::soft_delete( $sid );
					}
				}
				return array( 'ok' => true, 'reason' => 'service_disabled', 'service_id' => $sid );
			}
			return array( 'ok' => true, 'reason' => 'purchase_tx_reset' );
		}
		return array( 'ok' => true, 'reason' => 'no_effect' );
	}

	/**
	 * Whether purchase tx is renew/volume/slots (not a new service provision).
	 *
	 * @param object $tx Transaction row.
	 * @return bool
	 */
	private static function is_purchase_intent_modification( $tx ) {
		$meta = json_decode( (string) ( $tx->meta_json ?? '{}' ), true );
		if ( ! is_array( $meta ) ) {
			return false;
		}
		$intent = isset( $meta['intent'] ) ? (string) $meta['intent'] : '';
		return in_array( $intent, array( 'renew_same', 'add_volume', 'add_user_slots' ), true );
	}

	/**
	 * Edit inline keyboard with retries; never send a new chat message.
	 *
	 * @param string               $platform Platform.
	 * @param int                  $chat_id  Chat id.
	 * @param int                  $msg_id   Message id.
	 * @param array<string, mixed> $markup   Reply markup.
	 * @param string               $context  Log context label.
	 * @return bool
	 */
	private static function edit_reply_markup_with_retry( $platform, $chat_id, $msg_id, array $markup, $context = 'receipt_moderation' ) {
		$chat_id = (int) $chat_id;
		$msg_id  = (int) $msg_id;
		if ( $chat_id < 1 || $msg_id < 1 ) {
			return false;
		}
		for ( $i = 0; $i < 3; $i++ ) {
			$res = SimpleVPBot_Bot_Runtime::edit_reply_markup( $platform, $chat_id, $msg_id, $markup );
			if ( is_array( $res ) && ! empty( $res['ok'] ) ) {
				return true;
			}
			usleep( 250000 );
		}
		if ( class_exists( 'SimpleVPBot_Logger' ) ) {
			SimpleVPBot_Logger::error(
				'receipt moderation edit_reply_markup failed',
				array(
					'context'  => (string) $context,
					'platform' => (string) $platform,
					'chat_id'  => $chat_id,
					'msg_id'   => $msg_id,
				)
			);
		}
		return false;
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
		foreach ( $list as $m ) {
			$plat = isset( $m['platform'] ) ? (string) $m['platform'] : 'telegram';
			$cid  = (int) ( $m['chat_id'] ?? 0 );
			$mid  = (int) ( $m['message_id'] ?? 0 );
			if ( ! $cid || ! $mid ) {
				continue;
			}
			self::edit_reply_markup_with_retry( $plat, $cid, $mid, $markup, 'edit_admin_messages' );
		}
	}

	/**
	 * Update inline keyboard on the admin message that was clicked (callback).
	 *
	 * @param string               $platform Platform.
	 * @param int                  $chat_id  Admin chat id.
	 * @param int                  $msg_id   Message id.
	 * @param array<string, mixed> $markup   Reply markup.
	 */
	public static function finalize_clicked_admin_message( $platform, $chat_id, $msg_id, array $markup ) {
		self::edit_reply_markup_with_retry( $platform, (int) $chat_id, (int) $msg_id, $markup, 'finalize_clicked' );
	}

	/**
	 * Short admin toast text after approve/reject from bot callback.
	 *
	 * @param array<string, mixed> $res       Processor result.
	 * @param bool                 $approved  True when action was approve.
	 * @return string
	 */
	public static function admin_feedback_text( array $res, $approved ) {
		$ok     = ! empty( $res['ok'] );
		$reason = (string) ( $res['reason'] ?? '' );
		if ( ! empty( $res['purchase_failed'] ) ) {
			return '⚠️ تایید انجام شد اما ساخت سرویس ناموفق بود.';
		}
		if ( $ok ) {
			if ( $approved ) {
				if ( 'already_approved' === $reason ) {
					return 'ℹ️ این رسید قبلاً تایید شده بود.';
				}
				return '✅ رسید تایید شد.';
			}
			return '❌ رسید رد شد.';
		}
		$map = array(
			'not_found'          => '⛔ رسید یافت نشد.',
			'not_pending'        => '⛔ رسید در وضعیت قابل تغییر نیست.',
			'already_processing' => '⏳ رسید در حال پردازش است؛ کمی بعد دوباره تلاش کنید.',
			'no_tx'              => '⛔ تراکنش مرتبط یافت نشد.',
			'effects_failed'     => '⛔ خطا در اعمال تایید.',
			'finalize_race'      => '⛔ تداخل هم‌زمان؛ دوباره تلاش کنید.',
			'topup_user_missing' => '⛔ کاربر برای شارژ یافت نشد.',
			'tx_not_pending'     => '⛔ تراکنش دیگر معلق نیست.',
			'unsupported_tx_type'=> '⛔ نوع تراکنش پشتیبانی نمی‌شود.',
			'panel_disable_failed' => '⛔ غیرفعال‌سازی سرویس روی پنل ناموفق بود.',
		);
		if ( isset( $map[ $reason ] ) ) {
			return $map[ $reason ];
		}
		return $approved ? '⛔ تایید رسید انجام نشد.' : '⛔ رد رسید انجام نشد.';
	}

	/**
	 * Notify user that a newly provisioned service is ready (both bots + details button).
	 *
	 * @param object      $user       svp_users row.
	 * @param int         $service_id svp_services.id.
	 * @param object|null $context_tx Transaction for reseller notify scope (optional).
	 */
	public static function notify_user_service_ready( $user, $service_id, $context_tx = null ) {
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
		self::notify_user_both_bots( $user, '🎉 سرویس جدید شما آماده است.', $extra, $context_tx );
	}

	/**
	 * Notify end user on Telegram and/or Bale.
	 *
	 * @param object               $user User row.
	 * @param string               $text Text.
	 * @param array<string, mixed> $extra Extra.
	 */
	public static function notify_user_both_bots( $user, $text, array $extra = array(), $context_tx = null ) {
		if ( class_exists( 'SimpleVPBot_User_Notify' ) ) {
			SimpleVPBot_User_Notify::send_to_user( $user, $text, $extra, $context_tx );
		}
	}
}
