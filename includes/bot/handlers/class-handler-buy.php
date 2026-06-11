<?php
/**
 * Purchase flow (card-to-card) + receipt upload.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Buy
 */
class SimpleVPBot_Handler_Buy {

	/**
	 * HMAC for Bale wallet invoice payload (no stored secret in payload).
	 *
	 * @param int $tx_id  Transaction id.
	 * @param int $user_id svp user id.
	 * @return string
	 */
	public static function bale_wallet_build_payload( $tx_id, $user_id ) {
		$k = (string) SimpleVPBot_Settings::get( 'portal_link_secret', '' );
		if ( $k === '' ) {
			$k = (string) wp_salt( 'auth' );
		}
		$h = hash_hmac( 'sha256', 'bale_w|' . (int) $tx_id . '|' . (int) $user_id, $k );
		return (string) ( (int) $tx_id ) . ':' . substr( $h, 0, 12 );
	}

	/**
	 * Checkout caption from transaction row (pending purchase).
	 *
	 * @param object|null $tx          Transaction.
	 * @param string      $title_line Optional first line (e.g. admin invoice label).
	 * @return string
	 */
	/**
	 * Unified plan summary block (fixed + per-GB).
	 *
	 * @param object     $plan      Plan row.
	 * @param object|null $user     User for i18n.
	 * @param float      $amount    Toman payable.
	 * @param int|null   $volume_gb Chosen GB for per-GB; null uses plan.traffic_gb.
	 * @return string
	 */
	public static function plan_checkout_summary_text( $plan, $user, $amount, $volume_gb = null ) {
		if ( ! $plan ) {
			return '';
		}
		$gb = null !== $volume_gb ? (int) $volume_gb : (int) ( $plan->traffic_gb ?? 0 );
		return SimpleVPBot_Texts::format(
			SimpleVPBot_Texts::get_for_user( 'msg.buy.plan_checkout_summary', $user ),
			array(
				'name'   => (string) ( $plan->name ?? '' ),
				'gb'     => SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $gb ),
				'days'   => SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) (int) ( $plan->duration_days ?? 0 ) ),
				'amount' => SimpleVPBot_Bot_Persian_Text::format_toman_fa( (float) $amount ),
			)
		);
	}

	/**
	 * Plan summary + confirm footer (buy:p / per-GB volume step).
	 *
	 * @param object     $plan      Plan row.
	 * @param object|null $user     User.
	 * @param float      $amount    Toman.
	 * @param int|null   $volume_gb GB.
	 * @return string
	 */
	public static function plan_confirm_message_text( $plan, $user, $amount, $volume_gb = null ) {
		$summary = self::plan_checkout_summary_text( $plan, $user, $amount, $volume_gb );
		if ( '' === $summary ) {
			return '';
		}
		$footer = SimpleVPBot_Texts::get_for_user( 'msg.buy.plan_confirm_footer', $user );
		return $summary . "\n\n" . $footer;
	}

	/**
	 * Plan summary from transaction meta (checkout screen).
	 *
	 * @param array<string, mixed> $meta      meta_json.
	 * @param float                $amount    Payable toman.
	 * @param object|null          $user      User.
	 * @return string
	 */
	private static function plan_summary_text_from_tx_meta( array $meta, $amount, $user = null ) {
		if ( empty( $meta['plan_id'] ) ) {
			return '';
		}
		$plan = SimpleVPBot_Model_Plan::find( (int) $meta['plan_id'] );
		if ( ! $plan ) {
			return '';
		}
		$vol = isset( $meta['volume_gb'] ) ? (int) $meta['volume_gb'] : null;
		return self::plan_checkout_summary_text( $plan, $user, (float) $amount, $vol );
	}

	public static function checkout_message_for_tx( $tx, $title_line = '', $user = null ) {
		if ( ! $tx ) {
			return '';
		}
		$uid_bal = (int) ( $tx->user_id ?? 0 );
		$ub      = ( $user && ! empty( $user->id ) ) ? $user : ( $uid_bal > 0 ? SimpleVPBot_Model_User::find( $uid_bal ) : null );
		$meta    = json_decode( (string) $tx->meta_json, true );
		$meta    = is_array( $meta ) ? $meta : array();
		$tid     = (int) $tx->id;
		$amount  = (float) $tx->amount;
		$lines   = array();
		if ( $title_line !== '' ) {
			$lines[] = $title_line;
		}
		$plan_summary = '';
		if ( 'purchase' === (string) $tx->type ) {
			$plan_summary = self::plan_summary_text_from_tx_meta( $meta, $amount, $ub );
			if ( '' !== $plan_summary ) {
				$lines[] = $plan_summary;
				$lines[] = '';
			}
		}
		if ( 'topup' === (string) $tx->type ) {
			$lines[] = SimpleVPBot_Texts::format(
				SimpleVPBot_Texts::get_for_user( 'msg.wallet.topup_order', $ub ),
				array( 'id' => SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $tid ) )
			);
		} else {
		$brand = class_exists( 'SimpleVPBot_Bot_Context' ) ? trim( (string) SimpleVPBot_Bot_Context::active_brand_name() ) : '';
		if ( '' !== $brand ) {
			$lines[] = SimpleVPBot_Texts::format(
				SimpleVPBot_Texts::get_for_user( 'msg.buy.order_brand', $ub ),
				array(
					'brand' => $brand,
					'id'    => SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $tid ),
				)
			);
		} else {
			$lines[] = SimpleVPBot_Texts::format(
				SimpleVPBot_Texts::get_for_user( 'msg.buy.order', $ub ),
				array( 'id' => SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $tid ) )
			);
		}
		}
		if ( ! empty( $meta['discount_code'] ) ) {
			$sub  = isset( $meta['subtotal_toman'] ) ? (float) $meta['subtotal_toman'] : $amount;
			$disc = isset( $meta['discount_toman'] ) ? (float) $meta['discount_toman'] : 0.0;
			$lines[] = SimpleVPBot_Texts::format(
				SimpleVPBot_Texts::get_for_user( 'msg.buy.discount_line', $ub ),
				array(
					'code'     => (string) $meta['discount_code'],
					'discount' => SimpleVPBot_Bot_Persian_Text::format_toman_fa( $disc ),
				)
			);
			$lines[] = SimpleVPBot_Texts::format(
				SimpleVPBot_Texts::get_for_user( 'msg.buy.before_discount', $ub ),
				array( 'subtotal' => SimpleVPBot_Bot_Persian_Text::format_toman_fa( $sub ) )
			);
		}
		$wallet_applied = isset( $meta['wallet_applied_toman'] ) ? max( 0.0, (float) $meta['wallet_applied_toman'] ) : 0.0;
		if ( $wallet_applied > 0 ) {
			$lines[] = SimpleVPBot_Texts::format(
				SimpleVPBot_Texts::get_for_user( 'msg.buy.wallet_applied_line', $ub ),
				array( 'applied' => SimpleVPBot_Bot_Persian_Text::format_toman_fa( $wallet_applied ) )
			);
			$lines[] = SimpleVPBot_Texts::format(
				SimpleVPBot_Texts::get_for_user( 'msg.buy.wallet_remaining_line', $ub ),
				array( 'remaining' => SimpleVPBot_Bot_Persian_Text::format_toman_fa( $amount ) )
			);
		} elseif ( '' === $plan_summary ) {
			$lines[] = SimpleVPBot_Texts::format(
				SimpleVPBot_Texts::get_for_user( 'msg.buy.payable', $ub ),
				array( 'amount' => SimpleVPBot_Bot_Persian_Text::format_toman_fa( $amount ) )
			);
		}
		if ( $ub ) {
			$lines[] = SimpleVPBot_Texts::format(
				SimpleVPBot_Texts::get_for_user( 'msg.buy.wallet_balance', $ub ),
				array( 'balance' => SimpleVPBot_Bot_Persian_Text::format_toman_fa( (float) $ub->balance ) )
			);
		}
		$lines[] = '';
		$lines[] = SimpleVPBot_Texts::get_for_user( 'msg.buy.pick_payment', $ub );
		return implode( "\n", $lines );
	}

	/**
	 * Inline keyboard: discount row + payment methods.
	 *
	 * @param string $platform telegram|bale.
	 * @param int    $tid      Transaction id.
	 * @return array<string, mixed>
	 */
	public static function checkout_reply_markup( $platform, $tid, $tx_chk = null, $user = null, $cards = null ) {
		$tid = (int) $tid;
		if ( ! $tx_chk ) {
			$tx_chk = SimpleVPBot_Model_Transaction::find( $tid );
		}
		$owner_rid = class_exists( 'SimpleVPBot_Payment_Methods' ) && $tx_chk
			? SimpleVPBot_Payment_Methods::resolve_owner_from_tx( $tx_chk )
			: 0;
		if ( null === $cards ) {
			$cards = class_exists( 'SimpleVPBot_Payment_Methods' )
				? SimpleVPBot_Payment_Methods::filter_cards( SimpleVPBot_Model_Card::active_for_transaction( $tid, $tx_chk ), $owner_rid )
				: SimpleVPBot_Model_Card::active_for_transaction( $tid, $tx_chk );
		}
		$ub       = $user;
		if ( ! $ub && $tx_chk && (int) $tx_chk->user_id > 0 ) {
			$ub = SimpleVPBot_Model_User::find( (int) $tx_chk->user_id );
		}
		$show_bale_wallet = class_exists( 'SimpleVPBot_Payment_Methods' )
			? SimpleVPBot_Payment_Methods::show_bale_wallet( $platform, $owner_rid )
			: ( 'bale' === $platform && '' !== (string) SimpleVPBot_Settings::get( 'bale_wallet_provider_token', '' ) );
		$show_site_wallet = class_exists( 'SimpleVPBot_Payment_Methods' )
			? SimpleVPBot_Payment_Methods::can_offer_site_wallet( $tx_chk, $ub, $owner_rid )
			: false;
		if ( ! class_exists( 'SimpleVPBot_Payment_Methods' ) && $tx_chk && 'pending' === (string) $tx_chk->status && 'purchase' === (string) $tx_chk->type ) {
			$need = round( (float) $tx_chk->amount, 2 );
			$meta_chk = json_decode( (string) $tx_chk->meta_json, true );
			$meta_chk = is_array( $meta_chk ) ? $meta_chk : array();
			$applied  = max( 0.0, (float) ( $meta_chk['wallet_applied_toman'] ?? 0 ) );
			if ( $need > 0 && $ub && $applied <= 0 && round( (float) $ub->balance, 2 ) > 0 ) {
				$show_site_wallet = true;
			}
		}
		$pay  = SimpleVPBot_Keyboards::inline_payment_method( $cards, (int) $tid, $show_bale_wallet, $show_site_wallet, $ub );
		$rows = isset( $pay['inline_keyboard'] ) && is_array( $pay['inline_keyboard'] ) ? $pay['inline_keyboard'] : array();
		if ( $tx_chk && 'purchase' === (string) $tx_chk->type ) {
			array_unshift(
				$rows,
				array(
					array(
						'text'          => SimpleVPBot_Keyboards::i18n_btn( 'btn.pay.discount_code', $ub ),
						'callback_data' => 'buy:dc:' . (int) $tid,
					),
					array(
						'text'          => SimpleVPBot_Keyboards::i18n_btn( 'btn.pay.remove_discount', $ub ),
						'callback_data' => 'buy:dd:' . (int) $tid,
					),
				)
			);
		}
		return array( 'inline_keyboard' => $rows );
	}

	/**
	 * Inline keyboard for partial site-wallet confirmation.
	 *
	 * @param int         $tid  Transaction id.
	 * @param object|null $user User row.
	 * @return array<string, mixed>
	 */
	private static function wallet_partial_confirm_markup( $tid, $user ) {
		$tid = (int) $tid;
		return array(
			'inline_keyboard' => array(
				array(
					array(
						'text'          => SimpleVPBot_Texts::get_for_user( 'btn.pay.wallet_partial_yes', $user ),
						'callback_data' => 'buy:swy:' . $tid,
					),
					array(
						'text'          => SimpleVPBot_Texts::get_for_user( 'btn.pay.wallet_partial_no', $user ),
						'callback_data' => 'buy:swn:' . $tid,
					),
				),
			),
		);
	}

	/**
	 * Show site-wallet payment confirmation (full or partial).
	 *
	 * @param string      $platform  Platform.
	 * @param int         $chat_id   Chat id.
	 * @param int         $msg_id    Message id.
	 * @param object      $tx        Transaction.
	 * @param object      $user      User row.
	 * @param int         $owner_rid Owner scope.
	 */
	private static function send_site_wallet_confirm( $platform, $chat_id, $msg_id, $tx, $user, $owner_rid ) {
		$tx_id   = (int) $tx->id;
		$need    = round( (float) $tx->amount, 2 );
		$balance = round( (float) $user->balance, 2 );
		$full    = class_exists( 'SimpleVPBot_Payment_Methods' )
			&& SimpleVPBot_Payment_Methods::show_site_wallet( $tx, $user, $owner_rid );
		if ( $full ) {
			$text = SimpleVPBot_Texts::format(
				SimpleVPBot_Texts::get_for_user( 'msg.buy.wallet_full_confirm', $user ),
				array(
					'amount'  => SimpleVPBot_Bot_Persian_Text::format_toman_fa( $need ),
					'balance' => SimpleVPBot_Bot_Persian_Text::format_toman_fa( $balance ),
				)
			);
		} else {
			$remaining = max( 0.0, round( $need - $balance, 2 ) );
			$text      = SimpleVPBot_Texts::format(
				SimpleVPBot_Texts::get_for_user( 'msg.buy.wallet_partial_confirm', $user ),
				array(
					'balance'   => SimpleVPBot_Bot_Persian_Text::format_toman_fa( $balance ),
					'need'      => SimpleVPBot_Bot_Persian_Text::format_toman_fa( $need ),
					'remaining' => SimpleVPBot_Bot_Persian_Text::format_toman_fa( $remaining ),
				)
			);
		}
		$markup = self::wallet_partial_confirm_markup( $tx_id, $user );
		if ( $msg_id > 0 ) {
			SimpleVPBot_Bot_Runtime::edit_message_text( $platform, $chat_id, $msg_id, $text, array( 'reply_markup' => $markup ) );
		} else {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $text, array( 'reply_markup' => $markup ) );
		}
	}

	/**
	 * Pay full pending amount from site wallet and fulfill purchase.
	 *
	 * @param object $tx       Transaction.
	 * @param object $user     User row.
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @return bool True on success.
	 */
	private static function fulfill_site_wallet_full_payment( $tx, $user, $platform, $chat_id ) {
		$need = round( (float) $tx->amount, 2 );
		if ( $need <= 0 ) {
			return false;
		}
		if ( ! SimpleVPBot_Model_User::decrement_balance_if_sufficient( (int) $user->id, $need ) ) {
			return false;
		}
		self::schedule_wallet_fulfill( (int) $tx->id, (int) $user->id, $platform, $chat_id, 'site_wallet', $need );
		return true;
	}

	/**
	 * Cron fallback for deferred wallet fulfill.
	 *
	 * @param int    $tx_id         Transaction id.
	 * @param int    $user_id       svp_users.id.
	 * @param string $platform      telegram|bale.
	 * @param int    $chat_id       Chat id.
	 * @param string $source        Fulfill source label.
	 * @param float  $refund_amount Amount to refund on failure.
	 */
	public static function deferred_wallet_fulfill_cron( $tx_id, $user_id, $platform, $chat_id, $source, $refund_amount ) {
		self::run_deferred_wallet_fulfill(
			(int) $tx_id,
			(int) $user_id,
			(string) $platform,
			(int) $chat_id,
			(string) $source,
			(float) $refund_amount
		);
	}

	/**
	 * Queue wallet fulfill after HTTP response.
	 *
	 * @param int    $tx_id         Transaction id.
	 * @param int    $user_id       User id.
	 * @param string $platform      Platform.
	 * @param int    $chat_id       Chat id.
	 * @param string $source        Source label.
	 * @param float  $refund_amount Refund on failure.
	 */
	private static function schedule_wallet_fulfill( $tx_id, $user_id, $platform, $chat_id, $source, $refund_amount ) {
		if ( ! class_exists( 'SimpleVPBot_Deferred_Work' ) ) {
			self::run_deferred_wallet_fulfill( $tx_id, $user_id, $platform, $chat_id, $source, $refund_amount );
			return;
		}
		SimpleVPBot_Deferred_Work::run_after_response_or_cron(
			static function () use ( $tx_id, $user_id, $platform, $chat_id, $source, $refund_amount ) {
				self::run_deferred_wallet_fulfill( $tx_id, $user_id, $platform, $chat_id, $source, $refund_amount );
			},
			SimpleVPBot_Deferred_Work::WALLET_FULFILL_CRON_HOOK,
			array( (int) $tx_id, (int) $user_id, (string) $platform, (int) $chat_id, (string) $source, (float) $refund_amount ),
			'wallet_fulfill'
		);
	}

	/**
	 * Run purchase fulfill after wallet debit (background).
	 *
	 * @param int    $tx_id         Transaction id.
	 * @param int    $user_id       User id.
	 * @param string $platform      Platform.
	 * @param int    $chat_id       Chat id.
	 * @param string $source        Source label.
	 * @param float  $refund_amount Refund on failure.
	 */
	private static function run_deferred_wallet_fulfill( $tx_id, $user_id, $platform, $chat_id, $source, $refund_amount ) {
		$tx_id         = (int) $tx_id;
		$user_id       = (int) $user_id;
		$chat_id       = (int) $chat_id;
		$refund_amount = round( (float) $refund_amount, 2 );
		$user          = SimpleVPBot_Model_User::find( $user_id );
		$ful           = SimpleVPBot_Receipt_Processor::fulfill_purchase_by_transaction( $tx_id, (string) $source );
		if ( ! empty( $ful['ok'] ) ) {
			return;
		}
		if ( $refund_amount > 0 ) {
			SimpleVPBot_Model_User::increment_balance( $user_id, $refund_amount );
		}
		if ( $chat_id > 0 && $user ) {
			if ( 'bale_wallet' === (string) $source ) {
				SimpleVPBot_Bot_Runtime::send_message_with_support(
					$platform,
					$chat_id,
					SimpleVPBot_Texts::format(
						SimpleVPBot_Texts::get_for_user( 'msg.buy.fulfill_failed_bale', $user ),
						array( 'id' => $tx_id )
					)
				);
			} else {
				SimpleVPBot_Bot_Runtime::send_message_with_support(
					$platform,
					$chat_id,
					SimpleVPBot_Texts::get_for_user( 'msg.buy.fulfill_failed_refunded', $user )
				);
			}
		}
	}

	/**
	 * Toast for deferred buy callbacks.
	 *
	 * @param string $platform Platform.
	 * @param string $cb_id    Callback query id.
	 */
	private static function answer_processing_toast( $platform, $cb_id ) {
		if ( '' === (string) $cb_id ) {
			return;
		}
		SimpleVPBot_Bot_Runtime::answer_callback_query(
			$platform,
			array(
				'callback_query_id' => (string) $cb_id,
				'text'              => '⏳ در حال پردازش…',
			)
		);
	}

	/**
	 * Merge reseller scope + nearest-reseller card scope into checkout meta.
	 *
	 * @param array<string, mixed> $meta    Checkout meta.
	 * @param int                  $user_id svp_users.id.
	 * @return array<string, mixed>
	 */
	private static function prescope_checkout_meta( array $meta, $user_id ) {
		if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			$meta = SimpleVPBot_Bot_Reseller_Scope::enrich_checkout_meta( $meta );
		}
		if ( empty( $meta['invoice_card_owner_scope_svp_id'] ) ) {
			$active_rid = class_exists( 'SimpleVPBot_Bot_Reseller_Scope' )
				? (int) SimpleVPBot_Bot_Reseller_Scope::active_reseller_id()
				: 0;
			if ( $active_rid < 1 && class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
				$rid = (int) SimpleVPBot_Reseller_Branding::nearest_reseller_id_for_user( (int) $user_id );
				if ( $rid > 0 ) {
					$meta['invoice_card_owner_scope_svp_id'] = $rid;
				}
			}
		}
		return $meta;
	}

	/**
	 * Build transaction row object after insert (avoids immediate re-fetch).
	 *
	 * @param int                  $tid      Transaction id.
	 * @param int                  $user_id  User id.
	 * @param float                $amount   Amount toman.
	 * @param string               $type     purchase|topup.
	 * @param array<string, mixed> $meta     meta_json source.
	 * @param int|null             $svc_id   Optional service id.
	 * @return object
	 */
	private static function transaction_row_from_insert( $tid, $user_id, $amount, $type, array $meta, $svc_id = null ) {
		$meta_json = wp_json_encode( $meta );
		return (object) array(
			'id'         => (int) $tid,
			'user_id'    => (int) $user_id,
			'service_id' => null !== $svc_id && (int) $svc_id > 0 ? (int) $svc_id : null,
			'amount'     => round( (float) $amount, 2 ),
			'type'       => (string) $type,
			'status'     => 'pending',
			'meta_json'  => is_string( $meta_json ) ? $meta_json : '{}',
		);
	}

	/**
	 * Resolve checkout cards once (active_for_transaction + owner filter + meta cache).
	 *
	 * @param object   $tx_row    Transaction row.
	 * @param int|null $owner_rid Owner scope override.
	 * @return array<int, object>
	 */
	private static function resolve_checkout_cards( $tx_row, $owner_rid = null ) {
		if ( ! $tx_row || empty( $tx_row->id ) ) {
			return array();
		}
		$tid = (int) $tx_row->id;
		$rid = null !== $owner_rid
			? (int) $owner_rid
			: ( class_exists( 'SimpleVPBot_Payment_Methods' )
				? (int) SimpleVPBot_Payment_Methods::resolve_owner_from_tx( $tx_row )
				: 0 );
		$cards = SimpleVPBot_Model_Card::active_for_transaction( $tid, $tx_row );
		if ( class_exists( 'SimpleVPBot_Payment_Methods' ) ) {
			$cards = SimpleVPBot_Payment_Methods::filter_cards( $cards, $rid );
		}
		$meta = json_decode( (string) ( $tx_row->meta_json ?? '{}' ), true );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		if ( empty( $meta['checkout_card_ids'] ) && ! empty( $cards ) ) {
			$ids = array();
			foreach ( $cards as $c ) {
				if ( is_object( $c ) && ! empty( $c->id ) ) {
					$ids[] = (int) $c->id;
				}
			}
			if ( ! empty( $ids ) ) {
				$meta['checkout_card_ids'] = $ids;
				$meta_json                 = wp_json_encode( $meta );
				SimpleVPBot_Model_Transaction::update( $tid, array( 'meta_json' => $meta_json ) );
				$tx_row->meta_json = $meta_json;
			}
		}
		return is_array( $cards ) ? $cards : array();
	}

	/**
	 * Refresh checkout message after partial wallet apply or cancel.
	 *
	 * @param string      $platform Platform.
	 * @param int         $chat_id  Chat id.
	 * @param int         $msg_id   Message id.
	 * @param object|null $tx       Transaction.
	 */
	private static function refresh_checkout_message( $platform, $chat_id, $msg_id, $tx ) {
		if ( ! $tx || $msg_id <= 0 ) {
			return;
		}
		$tid  = (int) $tx->id;
		$user = (int) $tx->user_id > 0 ? SimpleVPBot_Model_User::find( (int) $tx->user_id ) : null;
		$text = self::checkout_message_for_tx( $tx, '', $user );
		$markup = self::checkout_reply_markup( $platform, $tid, $tx, $user );
		SimpleVPBot_Bot_Runtime::edit_message_text( $platform, $chat_id, $msg_id, $text, array( 'reply_markup' => $markup ) );
	}

	/**
	 * Deduct full wallet balance toward pending purchase; update amount/meta.
	 *
	 * @param object      $tx       Transaction.
	 * @param object      $user     User row.
	 * @param string      $platform Platform.
	 * @param int         $chat_id  Chat id.
	 * @param int         $msg_id   Message id (0 = send new checkout).
	 * @return bool True on success.
	 */
	private static function apply_partial_site_wallet( $tx, $user, $platform, $chat_id, $msg_id ) {
		$tx_id = (int) $tx->id;
		$uid   = (int) $user->id;
		if ( class_exists( 'SimpleVPBot_Payment_Methods' ) && SimpleVPBot_Payment_Methods::wallet_applied_toman( $tx ) > 0 ) {
			return false;
		}
		$applied = round( (float) $user->balance, 2 );
		if ( $applied <= 0 ) {
			return false;
		}
		$need      = round( (float) $tx->amount, 2 );
		$remaining = max( 0.0, round( $need - $applied, 2 ) );
		$meta      = json_decode( (string) $tx->meta_json, true );
		$meta      = is_array( $meta ) ? $meta : array();
		if ( ! isset( $meta['payable_before_wallet_toman'] ) ) {
			$meta['payable_before_wallet_toman'] = $need;
		}
		$meta['wallet_applied_toman'] = $applied;
		if ( ! SimpleVPBot_Model_User::decrement_balance_if_sufficient( $uid, $applied ) ) {
			return false;
		}
		SimpleVPBot_Model_Transaction::update(
			$tx_id,
			array(
				'amount'    => $remaining,
				'meta_json' => wp_json_encode( $meta ),
			)
		);
		$tx2 = SimpleVPBot_Model_Transaction::find( $tx_id );
		if ( ! $tx2 ) {
			SimpleVPBot_Model_User::increment_balance( $uid, $applied );
			return false;
		}
		if ( $remaining <= 0 ) {
			SimpleVPBot_Bot_Runtime::send_message_interactive(
				$platform,
				$chat_id,
				'⏳ پرداخت ثبت شد. سرویس در حال آماده‌سازی است…'
			);
			self::schedule_wallet_fulfill( $tx_id, $uid, $platform, $chat_id, 'site_wallet', $applied );
			return true;
		}
		$text   = self::checkout_message_for_tx( $tx2, '', $user );
		$markup = self::checkout_reply_markup( $platform, $tx_id, $tx2, $user );
		if ( $msg_id > 0 ) {
			SimpleVPBot_Bot_Runtime::edit_message_text( $platform, $chat_id, $msg_id, $text, array( 'reply_markup' => $markup ) );
		} else {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $text, array( 'reply_markup' => $markup ) );
		}
		return true;
	}

	/**
	 * Create pending purchase transaction and send the same payment keyboard as buy:cf.
	 *
	 * @param string               $platform telegram|bale.
	 * @param int                  $chat_id  Chat id.
	 * @param int                  $user_id               svp_users.id (transaction owner / beneficiary).
	 * @param float                $amount                Toman.
	 * @param array<string, mixed> $meta                  meta_json (plan_id, intent, service_id, …).
	 * @param int|null             $initiator_svp_user_id Bot user who started checkout; when omitted, same as $user_id. Pass admin id when an admin opens payment for another user's service so free admin self-checkout does not apply.
	 * @param int                  $edit_msg_id           Edit this message instead of sending new (0 = send).
	 * @return int Transaction id or 0 on failure.
	 */
	public static function send_purchase_checkout( $platform, $chat_id, $user_id, $amount, array $meta, $initiator_svp_user_id = null, $edit_msg_id = 0 ) {
		$user_id = (int) $user_id;
		$meta    = self::prescope_checkout_meta( $meta, $user_id );
		$plat    = sanitize_key( (string) $platform );
		if ( in_array( $plat, array( 'telegram', 'bale' ), true ) ) {
			$meta['platform'] = $plat;
		}
		$user   = SimpleVPBot_Model_User::find( $user_id );
		$svc_id = isset( $meta['service_id'] ) ? (int) $meta['service_id'] : 0;
		$amt    = round( (float) $amount, 2 );
		$tid    = SimpleVPBot_Model_Transaction::insert(
			array(
				'user_id'    => $user_id,
				'service_id' => $svc_id > 0 ? $svc_id : null,
				'amount'     => $amt,
				'type'       => 'purchase',
				'status'     => 'pending',
				'meta_json'  => wp_json_encode( $meta ),
			)
		);
		if ( ! $tid ) {
			$fail_msg = SimpleVPBot_Texts::get_for_user( 'msg.buy.order_failed', $user );
			if ( (int) $edit_msg_id > 0 ) {
				SimpleVPBot_Bot_Runtime::edit_message_text( $platform, (int) $chat_id, (int) $edit_msg_id, $fail_msg, array() );
			} else {
				SimpleVPBot_Bot_Runtime::send_message_interactive( $platform, (int) $chat_id, $fail_msg );
			}
			return 0;
		}
		$tx_row    = self::transaction_row_from_insert( (int) $tid, $user_id, $amt, 'purchase', $meta, $svc_id );
		$initiator = null !== $initiator_svp_user_id ? (int) $initiator_svp_user_id : $user_id;
		$buyer     = $user;
		$is_reseller_ctx = class_exists( 'SimpleVPBot_Bot_Context' ) && SimpleVPBot_Bot_Context::is_reseller_bot();
		if ( ! $is_reseller_ctx && $buyer && $initiator === $user_id && SimpleVPBot_Router::is_svp_user_bot_admin( $buyer ) ) {
			$ful = SimpleVPBot_Receipt_Processor::fulfill_purchase_by_transaction( (int) $tid, 'admin_self_checkout' );
			if ( ! empty( $ful['ok'] ) ) {
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					(int) $chat_id,
					SimpleVPBot_Texts::get_for_user( 'msg.buy.admin_self_checkout_ok', $user )
				);
				return (int) $tid;
			}
		}
		$cards = self::resolve_checkout_cards( $tx_row );
		if ( class_exists( 'SimpleVPBot_Payment_Methods' ) && ! SimpleVPBot_Payment_Methods::checkout_has_any_method( $platform, $tx_row, $user, null, $cards ) ) {
			SimpleVPBot_Model_Transaction::set_status( (int) $tid, 'cancelled' );
			$no_pay = SimpleVPBot_Texts::get_for_user( 'msg.buy.no_payment_methods', $user );
			if ( (int) $edit_msg_id > 0 ) {
				SimpleVPBot_Bot_Runtime::edit_message_text( $platform, (int) $chat_id, (int) $edit_msg_id, $no_pay, array() );
			} else {
				SimpleVPBot_Bot_Runtime::send_message_interactive( $platform, (int) $chat_id, $no_pay );
			}
			return 0;
		}
		$text    = self::checkout_message_for_tx( $tx_row, '', $user );
		$markup  = self::checkout_reply_markup( $platform, (int) $tid, $tx_row, $user, $cards );
		$extra   = array( 'reply_markup' => $markup );
		$edit_id = (int) $edit_msg_id;
		if ( $edit_id > 0 ) {
			SimpleVPBot_Bot_Runtime::edit_message_text(
				$platform,
				(int) $chat_id,
				$edit_id,
				$text,
				$extra
			);
		} else {
			SimpleVPBot_Bot_Runtime::send_message_interactive(
				$platform,
				(int) $chat_id,
				$text,
				$extra
			);
		}
		return (int) $tid;
	}

	/**
	 * Cron fallback for deferred purchase checkout (buy:cf).
	 *
	 * @param string $platform      telegram|bale.
	 * @param int    $chat_id       Chat id.
	 * @param int    $edit_msg_id   Message to edit (0 = send new).
	 * @param int    $user_id       svp_users.id.
	 * @param float  $amount        Toman.
	 * @param string $meta_json     JSON-encoded meta.
	 * @param int    $initiator_id  Initiator user id.
	 */
	public static function deferred_purchase_checkout_cron( $platform, $chat_id, $edit_msg_id, $user_id, $amount, $meta_json, $initiator_id ) {
		$meta = json_decode( (string) $meta_json, true );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		self::run_deferred_purchase_checkout(
			(string) $platform,
			(int) $chat_id,
			(int) $edit_msg_id,
			(int) $user_id,
			(float) $amount,
			$meta,
			(int) $initiator_id
		);
	}

	/**
	 * Queue purchase checkout after buy:cf (fast callback ack).
	 *
	 * @param string               $platform     Platform.
	 * @param int                  $chat_id      Chat id.
	 * @param int                  $edit_msg_id  Confirm message id.
	 * @param int                  $user_id      User id.
	 * @param float                $amount       Toman.
	 * @param array<string, mixed> $meta         Checkout meta.
	 * @param int                  $initiator_id Initiator id.
	 */
	private static function schedule_deferred_purchase_checkout( $platform, $chat_id, $edit_msg_id, $user_id, $amount, array $meta, $initiator_id ) {
		$meta_json = (string) wp_json_encode( $meta );
		$work      = static function () use ( $platform, $chat_id, $edit_msg_id, $user_id, $amount, $meta_json, $initiator_id ) {
			self::run_deferred_purchase_checkout(
				(string) $platform,
				(int) $chat_id,
				(int) $edit_msg_id,
				(int) $user_id,
				(float) $amount,
				json_decode( $meta_json, true ) ?: array(),
				(int) $initiator_id
			);
		};
		if ( class_exists( 'SimpleVPBot_Deferred_Work' ) ) {
			SimpleVPBot_Deferred_Work::run_after_response_or_cron(
				$work,
				SimpleVPBot_Deferred_Work::BUY_CHECKOUT_CRON_HOOK,
				array( (string) $platform, (int) $chat_id, (int) $edit_msg_id, (int) $user_id, (float) $amount, $meta_json, (int) $initiator_id ),
				'buy_checkout'
			);
		} else {
			$work();
		}
	}

	/**
	 * Create checkout transaction and show payment keyboard (background).
	 *
	 * @param string               $platform     Platform.
	 * @param int                  $chat_id      Chat id.
	 * @param int                  $edit_msg_id  Message to edit.
	 * @param int                  $user_id      User id.
	 * @param float                $amount       Toman.
	 * @param array<string, mixed> $meta         Meta.
	 * @param int                  $initiator_id Initiator id.
	 */
	private static function run_deferred_purchase_checkout( $platform, $chat_id, $edit_msg_id, $user_id, $amount, array $meta, $initiator_id ) {
		$user = SimpleVPBot_Model_User::find( (int) $user_id );
		$tid  = self::send_purchase_checkout(
			(string) $platform,
			(int) $chat_id,
			(int) $user_id,
			(float) $amount,
			$meta,
			(int) $initiator_id,
			(int) $edit_msg_id
		);
		if ( $tid > 0 || ! $user ) {
			return;
		}
		$fail = SimpleVPBot_Texts::get_for_user( 'msg.buy.order_failed', $user );
		if ( (int) $edit_msg_id > 0 ) {
			SimpleVPBot_Bot_Runtime::edit_message_text(
				(string) $platform,
				(int) $chat_id,
				(int) $edit_msg_id,
				$fail,
				array()
			);
		} else {
			SimpleVPBot_Bot_Runtime::send_message_with_support( (string) $platform, (int) $chat_id, $fail );
		}
	}

	/**
	 * Transient lock key while c2c invoice is being prepared.
	 *
	 * @param int $user_id User id.
	 * @param int $tx_id   Transaction id.
	 * @return string
	 */
	private static function c2c_invoice_lock_key( $user_id, $tx_id ) {
		return 'svp_pm_lock_' . (int) $user_id . '_' . (int) $tx_id;
	}

	/**
	 * Cron fallback for deferred c2c invoice (buy:pm).
	 *
	 * @param string $platform     Platform.
	 * @param int    $chat_id      Chat id.
	 * @param int    $edit_msg_id  Checkout message id.
	 * @param int    $user_id      User id.
	 * @param int    $tx_id        Transaction id.
	 * @param int    $card_id      Card id.
	 */
	public static function deferred_c2c_invoice_cron( $platform, $chat_id, $edit_msg_id, $user_id, $tx_id, $card_id ) {
		self::run_deferred_c2c_invoice(
			(string) $platform,
			(int) $chat_id,
			(int) $edit_msg_id,
			(int) $user_id,
			(int) $tx_id,
			(int) $card_id
		);
	}

	/**
	 * Queue c2c invoice after buy:pm (fast callback ack).
	 *
	 * @param string $platform    Platform.
	 * @param int    $chat_id     Chat id.
	 * @param int    $edit_msg_id Checkout message id.
	 * @param int    $user_id     User id.
	 * @param int    $tx_id       Transaction id.
	 * @param int    $card_id     Card id.
	 */
	private static function schedule_deferred_c2c_invoice( $platform, $chat_id, $edit_msg_id, $user_id, $tx_id, $card_id ) {
		$work = static function () use ( $platform, $chat_id, $edit_msg_id, $user_id, $tx_id, $card_id ) {
			self::run_deferred_c2c_invoice(
				(string) $platform,
				(int) $chat_id,
				(int) $edit_msg_id,
				(int) $user_id,
				(int) $tx_id,
				(int) $card_id
			);
		};
		if ( class_exists( 'SimpleVPBot_Deferred_Work' ) ) {
			SimpleVPBot_Deferred_Work::run_after_response_or_cron(
				$work,
				SimpleVPBot_Deferred_Work::C2C_INVOICE_CRON_HOOK,
				array( (string) $platform, (int) $chat_id, (int) $edit_msg_id, (int) $user_id, (int) $tx_id, (int) $card_id ),
				'c2c_invoice'
			);
		} else {
			$work();
		}
	}

	/**
	 * Build c2c invoice and set receipt_upload (background).
	 *
	 * @param string $platform    Platform.
	 * @param int    $chat_id     Chat id.
	 * @param int    $edit_msg_id Checkout message id.
	 * @param int    $user_id     User id.
	 * @param int    $tx_id       Transaction id.
	 * @param int    $card_id     Card id.
	 */
	private static function run_deferred_c2c_invoice( $platform, $chat_id, $edit_msg_id, $user_id, $tx_id, $card_id ) {
		$lock_key = self::c2c_invoice_lock_key( $user_id, $tx_id );
		delete_transient( $lock_key );

		$user = SimpleVPBot_Model_User::find( (int) $user_id );
		$tx   = SimpleVPBot_Model_Transaction::find( (int) $tx_id );
		$card = SimpleVPBot_Model_Card::find( (int) $card_id );
		if ( ! $user || ! $tx || ! $card
			|| (int) $tx->user_id !== (int) $user_id
			|| 'pending' !== (string) $tx->status
			|| ! in_array( (string) $tx->type, array( 'purchase', 'topup' ), true )
			|| ! (int) $card->active ) {
			if ( $user && (int) $edit_msg_id > 0 ) {
				SimpleVPBot_Bot_Runtime::edit_message_text(
					(string) $platform,
					(int) $chat_id,
					(int) $edit_msg_id,
					SimpleVPBot_Texts::get_for_user( 'msg.buy.section_expired', $user ),
					array()
				);
			}
			return;
		}
		self::send_purchase_step_invoice(
			array(
				'platform' => (string) $platform,
				'chat_id'  => (int) $chat_id,
				'user'     => $user,
			),
			$tx,
			$card,
			(int) $edit_msg_id
		);
	}

	/**
	 * Create pending wallet top-up and send payment keyboard.
	 *
	 * @param string               $platform Platform.
	 * @param int                  $chat_id  Chat id.
	 * @param object               $user     User row.
	 * @param float                $amount   Toman.
	 * @param array<string, mixed> $meta_extra Extra meta_json fields.
	 * @param string               $title_line Optional first line.
	 * @return int Transaction id or 0.
	 */
	public static function create_topup_checkout( $platform, $chat_id, $user, $amount, array $meta_extra = array(), $title_line = '' ) {
		$amt = round( (float) $amount, 2 );
		if ( ! is_finite( $amt ) || $amt <= 0 || $amt > 1e11 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, (int) $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.wallet.topup_invalid', $user ) );
			return 0;
		}
		$meta = array_merge(
			array(
				'wallet_topup' => true,
			),
			$meta_extra
		);
		$meta = self::prescope_checkout_meta( $meta, (int) $user->id );
		$plat = sanitize_key( (string) $platform );
		if ( in_array( $plat, array( 'telegram', 'bale' ), true ) ) {
			$meta['platform'] = $plat;
		}
		$owner_rid = class_exists( 'SimpleVPBot_Payment_Methods' ) ? SimpleVPBot_Payment_Methods::resolve_owner_rid( null ) : 0;
		if ( $owner_rid > 0 && empty( $meta['invoice_card_owner_scope_svp_id'] ) ) {
			$meta['invoice_card_owner_scope_svp_id'] = $owner_rid;
		}
		$tid = SimpleVPBot_Model_Transaction::insert(
			array(
				'user_id'    => (int) $user->id,
				'service_id' => null,
				'amount'     => $amt,
				'type'       => 'topup',
				'status'     => 'pending',
				'meta_json'  => wp_json_encode( $meta ),
			)
		);
		if ( ! $tid ) {
			SimpleVPBot_Bot_Runtime::send_message_interactive( $platform, (int) $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.order_failed', $user ) );
			return 0;
		}
		$tx_row = self::transaction_row_from_insert( (int) $tid, (int) $user->id, $amt, 'topup', $meta );
		$cards  = self::resolve_checkout_cards( $tx_row );
		if ( ! class_exists( 'SimpleVPBot_Payment_Methods' ) || ! SimpleVPBot_Payment_Methods::checkout_has_any_method( $platform, $tx_row, $user, null, $cards ) ) {
			SimpleVPBot_Model_Transaction::set_status( (int) $tid, 'cancelled' );
			SimpleVPBot_Bot_Runtime::send_message_interactive( $platform, (int) $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.no_payment_methods', $user ) );
			return 0;
		}
		if ( '' === $title_line ) {
			$title_line = SimpleVPBot_Texts::get_for_user( 'msg.wallet.topup_checkout_title', $user );
		}
		$text   = self::checkout_message_for_tx( $tx_row, $title_line, $user );
		$markup = self::checkout_reply_markup( $platform, (int) $tid, $tx_row, $user, $cards );
		SimpleVPBot_Bot_Runtime::send_message_interactive(
			$platform,
			(int) $chat_id,
			$text,
			array( 'reply_markup' => $markup )
		);
		return (int) $tid;
	}

	public static function bale_wallet_parse_and_verify( $payload, $bale_from_id ) {
		$p   = (string) $payload;
		$pos = strrpos( $p, ':' );
		if ( false === $pos || $pos < 1 ) {
			return null;
		}
		$tid = (int) substr( $p, 0, $pos );
		$sig = substr( $p, $pos + 1 );
		if ( $tid < 1 || 12 !== strlen( $sig ) ) {
			return null;
		}
		$tx = SimpleVPBot_Model_Transaction::find( $tid );
		if ( ! $tx || 'pending' !== (string) $tx->status || ! in_array( (string) $tx->type, array( 'purchase', 'topup' ), true ) ) {
			return null;
		}
		$user = SimpleVPBot_Model_User::find( (int) $tx->user_id );
		if ( ! $user || (int) $user->bale_user_id !== (int) $bale_from_id ) {
			return null;
		}
		$exp = self::bale_wallet_build_payload( (int) $tx->id, (int) $user->id );
		if ( ! hash_equals( $exp, $p ) ) {
			return null;
		}
		return $tx;
	}

	/**
	 * Answer pre_checkout_query (Bale).
	 *
	 * @param array<string, mixed> $pre_checkout Pre-checkout object.
	 */
	public static function handle_bale_pre_checkout( array $pre_checkout ) {
		$qid     = (string) ( $pre_checkout['id'] ?? '' );
		$from_id = (int) ( $pre_checkout['from']['id'] ?? 0 );
		$total   = isset( $pre_checkout['total_amount'] ) ? (int) $pre_checkout['total_amount'] : 0;
		$payload = (string) ( $pre_checkout['invoice_payload'] ?? '' );
		if ( ! $qid || ! $from_id || $payload === '' ) {
			SimpleVPBot_Bot_Runtime::answer_pre_checkout_query( 'bale', $qid, false, 'داده ناقص است.' );
			return;
		}
		$tx = self::bale_wallet_parse_and_verify( $payload, $from_id );
		if ( ! $tx ) {
			SimpleVPBot_Bot_Runtime::answer_pre_checkout_query( 'bale', $qid, false, 'سفارش یافت نشد.' );
			return;
		}
		// Bale IRR uses whole Rial; invoice is toman×10 (fractional toman amounts round to nearest toman).
		$rial = (int) round( (float) $tx->amount, 0 ) * 10;
		if ( $rial !== $total ) {
			SimpleVPBot_Bot_Runtime::answer_pre_checkout_query( 'bale', $qid, false, 'مبلغ با سفارش مطابقت ندارد.' );
			return;
		}
		SimpleVPBot_Bot_Runtime::answer_pre_checkout_query( 'bale', $qid, true, '' );
	}

	/**
	 * After SuccessfulPayment (Bale).
	 *
	 * @param array<string, mixed> $ctx Context: platform, user, chat_id, message.
	 */
	public static function handle_successful_payment( array $ctx ) {
		$platform = (string) ( $ctx['platform'] ?? '' );
		if ( 'bale' !== $platform ) {
			return;
		}
		$msg = isset( $ctx['message'] ) && is_array( $ctx['message'] ) ? $ctx['message'] : array();
		$sp  = isset( $msg['successful_payment'] ) && is_array( $msg['successful_payment'] ) ? $msg['successful_payment'] : array();
		$from_id = (int) ( $msg['from']['id'] ?? 0 );
		if ( ! $from_id ) {
			return;
		}
		$payload = (string) ( $sp['invoice_payload'] ?? '' );
		$tx      = self::bale_wallet_parse_and_verify( $payload, $from_id );
		if ( ! $tx ) {
			return;
		}
		$rial = (int) round( (float) $tx->amount, 0 ) * 10;
		if ( isset( $sp['total_amount'] ) && (int) $sp['total_amount'] !== $rial ) {
			return;
		}
		if ( 'topup' === (string) $tx->type ) {
			if ( ! SimpleVPBot_Model_User::increment_balance( (int) $tx->user_id, (float) $tx->amount ) ) {
				SimpleVPBot_Logger::error( 'bale_wallet topup increment failed', array( 'tx_id' => (int) $tx->id ) );
				return;
			}
			if ( ! SimpleVPBot_Model_Transaction::try_approve_from_pending( (int) $tx->id ) ) {
				return;
			}
			$user = SimpleVPBot_Model_User::find( (int) $tx->user_id );
			$chat_id = (int) ( $ctx['chat_id'] ?? 0 );
			if ( $user && $chat_id > 0 ) {
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					SimpleVPBot_Texts::get_for_user( 'msg.wallet.topup_done', $user )
				);
			}
			return;
		}
		$chat_id = (int) ( $ctx['chat_id'] ?? 0 );
		$user    = isset( $ctx['user'] ) ? $ctx['user'] : SimpleVPBot_Model_User::find( (int) $tx->user_id );
		if ( $chat_id > 0 && $user ) {
			SimpleVPBot_Bot_Runtime::send_message_interactive(
				$platform,
				$chat_id,
				'⏳ پرداخت ثبت شد. سرویس در حال آماده‌سازی است…'
			);
		}
		self::schedule_wallet_fulfill( (int) $tx->id, (int) $tx->user_id, $platform, $chat_id, 'bale_wallet', 0.0 );
	}

	/**
	 * Inline: buy:c, buy:p, buy:cf, buy:cf:gb, buy:pm, buy:sw, buy:bw, buy:cd, buy:x…
	 *
	 * @param array<string, mixed> $ctx Context.
	 */
	public static function handle_callback( array $ctx ) {
		$platform = (string) $ctx['platform'];
		$user     = $ctx['user'];
		$parts    = $ctx['parts'];
		$chat_id  = (int) $ctx['chat_id'];
		$msg_id   = (int) ( $ctx['msg_id'] ?? 0 );
		$cb_id    = isset( $ctx['cb_id'] ) ? (string) $ctx['cb_id'] : '';
		$act      = $parts[1] ?? '';

		if ( 'dc' === $act && isset( $parts[2] ) ) {
			$tid = (int) $parts[2];
			$tx  = SimpleVPBot_Model_Transaction::find( $tid );
			if ( ! $tx
				|| (int) $tx->user_id !== (int) $user->id
				|| 'pending' !== (string) $tx->status
				|| 'purchase' !== (string) $tx->type ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.invalid_order', $user ) );
				return;
			}
			SimpleVPBot_State::set(
				(int) $user->id,
				'buy_discount',
				array(
					'transaction_id'    => $tid,
					'checkout_chat_id'  => $chat_id,
					'checkout_msg_id'   => $msg_id,
					'platform'          => $platform,
				)
			);
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.prompt_discount', $user ) );
			return;
		}
		if ( 'dd' === $act && isset( $parts[2] ) ) {
			$tid = (int) $parts[2];
			$tx  = SimpleVPBot_Model_Transaction::find( $tid );
			if ( ! $tx
				|| (int) $tx->user_id !== (int) $user->id
				|| 'pending' !== (string) $tx->status
				|| 'purchase' !== (string) $tx->type ) {
				return;
			}
			SimpleVPBot_Discount_Service::clear_pending_discount( $tid );
			SimpleVPBot_State::clear( (int) $user->id );
			if ( $msg_id > 0 ) {
				$tx2    = SimpleVPBot_Model_Transaction::find( $tid );
				$text   = self::checkout_message_for_tx( $tx2, '', $user );
				$markup = self::checkout_reply_markup( $platform, $tid, $tx2, $user );
				SimpleVPBot_Bot_Runtime::edit_message_text( $platform, $chat_id, $msg_id, $text, array( 'reply_markup' => $markup ) );
			}
			return;
		}

		if ( 'pn' === $act && isset( $parts[2] ) ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Texts::get_for_user( 'msg.buy.deprecated_plan_button', $user )
			);
			self::send_category_picker( $platform, $chat_id );
			return;
		}

		if ( 'g' === $act && isset( $parts[2] ) ) {
			$cid     = (int) $parts[2];
			$cat_row = SimpleVPBot_Model_Plan_Category::find( $cid );
			if ( ! $cat_row || ! (int) $cat_row->active ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.category_unavailable', $user ) );
				return;
			}
			$panel_id = max( 1, (int) ( $cat_row->panel_id ?? 1 ) );
			$cat      = (string) $cat_row->slug;
			$plans = self::plans_for_category_cached( $cat_row );
			self::send_plan_picker_for_plans( $platform, $chat_id, (string) $cat_row->label, $plans );
			return;
		}

		if ( 'c' === $act && isset( $parts[2] ) ) {
			$panel_id = 1;
			$cat       = '';
			if ( isset( $parts[3] ) ) {
				$panel_id = max( 1, (int) $parts[2] );
				$cat      = (string) $parts[3];
			} else {
				$cat = (string) $parts[2];
			}
			$cat_row = SimpleVPBot_Model_Plan_Category::find_by_panel_slug( $panel_id, $cat );
			if ( ! $cat_row || ! (int) $cat_row->active ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.category_unavailable', $user ) );
				return;
			}
			$plans = self::plans_for_category_cached( $cat_row );
			self::send_plan_picker_for_plans( $platform, $chat_id, (string) $cat_row->label, $plans );
			return;
		}
		if ( 'p' === $act && isset( $parts[2] ) ) {
			$pid  = (int) $parts[2];
			$plan = SimpleVPBot_Model_Plan::find( $pid );
			if ( ! self::plan_available_in_context( $plan ) || ( class_exists( 'SimpleVPBot_Feature_L2tp' ) && ! SimpleVPBot_Feature_L2tp::plan_visible( $plan ) ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.plan_unavailable', $user ) );
				return;
			}
			if ( SimpleVPBot_Model_Plan::is_per_gb( $plan ) ) {
				$min = (int) ( $plan->traffic_gb_min ?? 0 );
				$max = (int) ( $plan->traffic_gb_max ?? 0 );
				if ( $min < 1 || $max < 1 || $min > $max || (float) ( $plan->price_per_gb ?? 0 ) <= 0 ) {
					SimpleVPBot_Bot_Runtime::send_message(
						$platform,
						$chat_id,
						SimpleVPBot_Texts::format(
							SimpleVPBot_Texts::get_for_user( 'msg.buy.plan_misconfigured', $user ),
							array( 'name' => (string) $plan->name )
						)
					);
					return;
				}
				SimpleVPBot_State::set( (int) $user->id, 'buy_choose_traffic', array( 'plan_id' => $pid ) );
				$ppg  = SimpleVPBot_Bot_Persian_Text::format_toman_fa( (float) ( $plan->price_per_gb ?? 0 ) );
				$min  = (int) ( $plan->traffic_gb_min ?? 0 );
				$max  = (int) ( $plan->traffic_gb_max ?? 0 );
				$min_fa = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $min );
				$max_fa = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $max );
				$d_fa   = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) (int) $plan->duration_days );
				$text   = "📦 " . (string) $plan->name . "\n";
				$text  .= '💰 قیمت: ' . $ppg . ' تومان به ازای هر گیگابایت' . "\n";
				$text  .= '⏳ مدت: ' . $d_fa . " روز\n";
				$text  .= "📊 حجم: باید بین {$min_fa} تا {$max_fa} گیگابایت باشد.\n";
				$text  .= "\n➖➖➖➖➖➖➖➖\n🔢 حجم مورد نیاز را فقط به صورت عدد (گیگابایت) بفرستید؛ مثلاً " . SimpleVPBot_Bot_Persian_Text::digits_to_fa( '50' );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $text );
				return;
			}
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				self::plan_confirm_message_text( $plan, $user, (float) $plan->price ),
				array(
					'reply_markup' => array(
						'inline_keyboard' => array(
							array(
								array( 'text' => SimpleVPBot_Keyboards::glass_button_text( SimpleVPBot_Texts::get_for_user( 'btn.pay.confirm_buy', $user ) ), 'callback_data' => 'buy:cf:' . $pid ),
								array( 'text' => SimpleVPBot_Keyboards::glass_button_text( SimpleVPBot_Texts::get_for_user( 'btn.pay.cancel', $user ) ), 'callback_data' => 'buy:x:0' ),
							),
						),
					),
				)
			);
			return;
		}
		if ( 'cf' === $act && isset( $parts[2] ) ) {
			$pid  = (int) $parts[2];
			$plan = SimpleVPBot_Model_Plan::find( $pid );
			if ( ! self::plan_available_in_context( $plan ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.plan_unavailable', $user ) );
				return;
			}
			$vol_chosen = isset( $parts[3] ) ? (int) $parts[3] : null;
			$amount     = (float) $plan->price;
			$meta       = array( 'plan_id' => $pid );
			if ( SimpleVPBot_Model_Plan::is_per_gb( $plan ) ) {
				if ( null === $vol_chosen || $vol_chosen < 1 || ! SimpleVPBot_Model_Plan::is_volume_in_range( $plan, $vol_chosen ) ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.volume_invalid', $user ) );
					return;
				}
				$amount = SimpleVPBot_Model_Plan::total_price( $plan, $vol_chosen );
				$meta['volume_gb'] = $vol_chosen;
			} elseif ( null !== $vol_chosen && $vol_chosen > 0 ) {
				return;
			}
			self::run_deferred_purchase_checkout(
				(string) $platform,
				(int) $chat_id,
				(int) $msg_id,
				(int) $user->id,
				(float) $amount,
				$meta,
				(int) $user->id
			);
			return;
		}
		if ( 'pm' === $act && isset( $parts[2], $parts[3] ) ) {
			$tx_id   = (int) $parts[2];
			$card_id = (int) $parts[3];
			if ( 'receipt_upload' === (string) $user->state ) {
				$sd = SimpleVPBot_State::data( $user );
				if ( (int) ( $sd['transaction_id'] ?? 0 ) === $tx_id ) {
					self::answer_processing_toast( $platform, $cb_id );
					return;
				}
			}
			$lock_key = self::c2c_invoice_lock_key( (int) $user->id, $tx_id );
			if ( get_transient( $lock_key ) ) {
				self::answer_processing_toast( $platform, $cb_id );
				return;
			}
			$tx   = SimpleVPBot_Model_Transaction::find( $tx_id );
			$card = SimpleVPBot_Model_Card::find( $card_id );
			if ( ! $tx
				|| (int) $tx->user_id !== (int) $user->id
				|| 'pending' !== (string) $tx->status
				|| ! in_array( (string) $tx->type, array( 'purchase', 'topup' ), true )
				|| ! $card
				|| ! (int) $card->active ) {
				SimpleVPBot_Bot_Runtime::send_message_interactive( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.section_expired', $user ) );
				return;
			}
			set_transient( $lock_key, '1', 30 );
			SimpleVPBot_State::clear( (int) $user->id );
			self::run_deferred_c2c_invoice(
				(string) $platform,
				(int) $chat_id,
				(int) $msg_id,
				(int) $user->id,
				(int) $tx_id,
				(int) $card_id
			);
			return;
		}
		if ( 'sw' === $act && isset( $parts[2] ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			$tx_id = (int) $parts[2];
			$tx    = SimpleVPBot_Model_Transaction::find( $tx_id );
			if ( ! $tx
				|| (int) $tx->user_id !== (int) $user->id
				|| 'pending' !== (string) $tx->status
				|| 'purchase' !== (string) $tx->type ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.purchase_invalid', $user ) );
				return;
			}
			$owner_rid = class_exists( 'SimpleVPBot_Payment_Methods' )
				? SimpleVPBot_Payment_Methods::resolve_owner_from_tx( $tx )
				: 0;
			if ( class_exists( 'SimpleVPBot_Payment_Methods' )
				&& ! SimpleVPBot_Payment_Methods::can_offer_site_wallet( $tx, $user, $owner_rid ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.wallet_disabled', $user ) );
				return;
			}
			$need = round( (float) $tx->amount, 2 );
			if ( $need <= 0 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.amount_invalid', $user ) );
				return;
			}
			self::answer_processing_toast( $platform, $cb_id );
			self::send_site_wallet_confirm( $platform, $chat_id, $msg_id, $tx, $user, $owner_rid );
			return;
		}
		if ( 'swy' === $act && isset( $parts[2] ) ) {
			self::answer_processing_toast( $platform, $cb_id );
			SimpleVPBot_State::clear( (int) $user->id );
			$tx_id = (int) $parts[2];
			$tx    = SimpleVPBot_Model_Transaction::find( $tx_id );
			if ( ! $tx
				|| (int) $tx->user_id !== (int) $user->id
				|| 'pending' !== (string) $tx->status
				|| 'purchase' !== (string) $tx->type ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.purchase_invalid', $user ) );
				return;
			}
			$owner_rid = class_exists( 'SimpleVPBot_Payment_Methods' )
				? SimpleVPBot_Payment_Methods::resolve_owner_from_tx( $tx )
				: 0;
			if ( class_exists( 'SimpleVPBot_Payment_Methods' )
				&& ! SimpleVPBot_Payment_Methods::can_offer_site_wallet( $tx, $user, $owner_rid ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.purchase_invalid', $user ) );
				return;
			}
			if ( class_exists( 'SimpleVPBot_Payment_Methods' )
				&& SimpleVPBot_Payment_Methods::show_site_wallet( $tx, $user, $owner_rid ) ) {
				if ( ! self::fulfill_site_wallet_full_payment( $tx, $user, $platform, $chat_id ) ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.wallet_insufficient', $user ) );
				}
				return;
			}
			if ( ! self::apply_partial_site_wallet( $tx, $user, $platform, $chat_id, $msg_id ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.wallet_insufficient', $user ) );
			}
			return;
		}
		if ( 'swn' === $act && isset( $parts[2] ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			$tx_id = (int) $parts[2];
			$tx    = SimpleVPBot_Model_Transaction::find( $tx_id );
			if ( $tx
				&& (int) $tx->user_id === (int) $user->id
				&& 'pending' === (string) $tx->status
				&& 'purchase' === (string) $tx->type ) {
				self::refresh_checkout_message( $platform, $chat_id, $msg_id, $tx );
			}
			return;
		}
		if ( 'bw' === $act && isset( $parts[2] ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			$tx_id = (int) $parts[2];
			$tx    = SimpleVPBot_Model_Transaction::find( $tx_id );
			if ( 'bale' !== $platform ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.wallet_bale_only', $user ) );
				return;
			}
			$owner_rid = class_exists( 'SimpleVPBot_Payment_Methods' ) && $tx
				? SimpleVPBot_Payment_Methods::resolve_owner_from_tx( $tx )
				: 0;
			$ptok = class_exists( 'SimpleVPBot_Payment_Methods' )
				? SimpleVPBot_Payment_Methods::bale_wallet_token( $owner_rid )
				: (string) SimpleVPBot_Settings::get( 'bale_wallet_provider_token', '' );
			if ( $ptok === '' ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.wallet_disabled', $user ) );
				return;
			}
			if ( ! $tx || (int) $tx->user_id !== (int) $user->id || 'pending' !== (string) $tx->status || ! in_array( (string) $tx->type, array( 'purchase', 'topup' ), true ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.purchase_invalid', $user ) );
				return;
			}
			if ( 'topup' === (string) $tx->type ) {
				$title = SimpleVPBot_Bot_Runtime::scrub_bale_text( SimpleVPBot_Texts::get_for_user( 'msg.wallet.topup_bale_title', $user ) );
				$label = mb_substr( $title, 0, 32 );
				$desc  = SimpleVPBot_Bot_Runtime::scrub_bale_text(
					SimpleVPBot_Texts::format(
						SimpleVPBot_Texts::get_for_user( 'msg.wallet.topup_bale_desc', $user ),
						array( 'id' => (string) (int) $tx->id )
					)
				);
			} else {
			$meta  = json_decode( (string) $tx->meta_json, true );
			$meta  = is_array( $meta ) ? $meta : array();
			$pid   = ! empty( $meta['plan_id'] ) ? (int) $meta['plan_id'] : 0;
			$plan  = $pid ? SimpleVPBot_Model_Plan::find( $pid ) : null;
			if ( ! self::plan_available_in_context( $plan ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.plan_missing', $user ) );
				return;
			}
			$label = mb_substr( (string) $plan->name, 0, 32 );
			$intent = isset( $meta['intent'] ) ? (string) $meta['intent'] : '';
			if ( 'renew_same' === $intent ) {
				$title = SimpleVPBot_Bot_Runtime::scrub_bale_text( 'تمدید: ' . (string) $plan->name );
			} elseif ( 'add_volume' === $intent ) {
				$title = SimpleVPBot_Bot_Runtime::scrub_bale_text( 'افزایش حجم: ' . (string) $plan->name );
			} elseif ( 'add_user_slots' === $intent ) {
				$title = SimpleVPBot_Bot_Runtime::scrub_bale_text( 'افزایش کاربر: ' . (string) $plan->name );
			} else {
				$title = SimpleVPBot_Bot_Runtime::scrub_bale_text( 'خرید: ' . (string) $plan->name );
			}
			$desc  = SimpleVPBot_Bot_Runtime::scrub_bale_text( 'مبلغ به ریال. شناسه: ' . (int) $tx->id );
			}
			self::answer_processing_toast( $platform, $cb_id );
			self::send_bale_wallet_invoice_deferred(
				$platform,
				$chat_id,
				$user,
				$tx,
				$ptok,
				$title,
				$desc,
				$label
			);
			return;
		}
		if ( 'cd' === $act && isset( $parts[2], $parts[3] ) ) {
			$card_id = (int) $parts[2];
			$tx_id   = (int) $parts[3];
			$tx      = SimpleVPBot_Model_Transaction::find( $tx_id );
			if ( ! $tx || (int) $tx->user_id !== (int) $user->id ) {
				return;
			}
			SimpleVPBot_State::set(
				(int) $user->id,
				'receipt_upload',
				array(
					'transaction_id' => $tx_id,
					'card_id'        => $card_id,
				)
			);
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.send_receipt_photo', $user ) );
			return;
		}
		if ( 'x' === $act ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.cancelled', $user ) );
		}
	}

	/**
	 * State-based text (per-GB volume entry).
	 *
	 * @param array<string, mixed> $ctx Context.
	 */
	public static function handle_state( array $ctx ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = $ctx['user'];
		$st       = (string) $user->state;
		if ( 'buy_choose_traffic' === $st ) {
			self::handle_traffic_volume_text(
				array(
					'platform' => $platform,
					'chat_id'  => $chat_id,
					'user'     => $user,
					'text'     => (string) ( $ctx['text'] ?? '' ),
				)
			);
			return;
		}
		if ( 'buy_discount' === $st ) {
			$raw = trim( SimpleVPBot_Bot_Runtime::normalize_digits( (string) ( $ctx['text'] ?? '' ) ) );
			$sd  = SimpleVPBot_State::data( $user );
			$tid = (int) ( $sd['transaction_id'] ?? 0 );
			if ( $tid < 1 ) {
				SimpleVPBot_State::clear( (int) $user->id );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.session_invalid', $user ) );
				return;
			}
			if ( '' === $raw || 'لغو' === $raw || 'انصراف' === $raw ) {
				SimpleVPBot_State::clear( (int) $user->id );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.discount_cancelled', $user ) );
				return;
			}
			$res = SimpleVPBot_Discount_Service::apply_to_pending_transaction( $tid, $raw );
			if ( empty( $res['ok'] ) ) {
				$reason = (string) ( $res['reason'] ?? 'invalid' );
				$map    = array(
					'invalid_code'       => 'کد نامعتبر است.',
					'intent_not_allowed' => 'این کد برای این نوع سفارش قابل استفاده نیست.',
					'expired'            => 'کد منقضی شده است.',
					'not_started'        => 'کد هنوز فعال نشده است.',
					'max_uses'           => 'سقف استفاده از این کد پر شده است.',
					'below_min_order'    => 'مبلغ سفارش برای این کد کافی نیست.',
					'above_max_order'    => 'مبلغ سفارش برای این کد بیش از حد مجاز است.',
					'user_not_allowed'   => 'این کد برای حساب شما نیست.',
					'plan_not_allowed'   => 'این کد برای پلن انتخاب‌شده قابل استفاده نیست.',
					'volume_required'    => 'این کد فقط برای خرید/افزایش حجم (گیگ) است.',
					'bad_status'         => 'این سفارش دیگر قابل تخفیف نیست.',
				);
				$msg = isset( $map[ $reason ] ) ? $map[ $reason ] : 'کد تایید نشد (' . $reason . ').';
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ ' . $msg );
				return;
			}
			SimpleVPBot_State::clear( (int) $user->id );
			$tx2    = SimpleVPBot_Model_Transaction::find( $tid );
			$text   = self::checkout_message_for_tx( $tx2, '', $user );
			$markup = self::checkout_reply_markup( $platform, $tid, $tx2, $user );
			$cmid   = (int) ( $sd['checkout_msg_id'] ?? 0 );
			$cchat  = (int) ( $sd['checkout_chat_id'] ?? 0 );
			$plat   = isset( $sd['platform'] ) ? (string) $sd['platform'] : $platform;
			if ( $cmid > 0 && $cchat > 0 ) {
				SimpleVPBot_Bot_Runtime::edit_message_text( $plat, $cchat, $cmid, $text, array( 'reply_markup' => $markup ) );
			}
			$disc = isset( $res['discount_toman'] ) ? (float) $res['discount_toman'] : 0.0;
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Texts::format(
					SimpleVPBot_Texts::get_for_user( 'msg.buy.discount_applied', $user ),
					array( 'discount' => SimpleVPBot_Bot_Persian_Text::format_toman_fa( $disc ) )
				)
			);
			return;
		}
		if ( preg_match( '/^svc_addvol_(\d+)$/', $st, $am ) ) {
			SimpleVPBot_Handler_Service::handle_addvol_text(
				array(
					'platform'   => $platform,
					'chat_id'    => $chat_id,
					'user'       => $user,
					'text'       => (string) ( $ctx['text'] ?? '' ),
					'service_id' => (int) $am[1],
					'from_id'    => (int) ( $ctx['from_id'] ?? 0 ),
				)
			);
			return;
		}
		if ( preg_match( '/^svc_addusers_(\d+)$/', $st, $am ) ) {
			SimpleVPBot_Handler_Service::handle_addusers_text(
				array(
					'platform'   => $platform,
					'chat_id'    => $chat_id,
					'user'       => $user,
					'text'       => (string) ( $ctx['text'] ?? '' ),
					'service_id' => (int) $am[1],
					'from_id'    => (int) ( $ctx['from_id'] ?? 0 ),
				)
			);
			return;
		}
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.use_menu', $user ) );
	}

	/**
	 * Parse GB integer for per-GB plan; show confirm with buy:cf:plan:gb.
	 *
	 * @param array<string, mixed> $ctx Context.
	 */
	private static function handle_traffic_volume_text( array $ctx ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = $ctx['user'];
		$raw      = trim( SimpleVPBot_Bot_Runtime::normalize_digits( (string) $ctx['text'] ) );
		$sd       = SimpleVPBot_State::data( $user );
		$pid      = (int) ( $sd['plan_id'] ?? 0 );
		$plan     = $pid ? SimpleVPBot_Model_Plan::find( $pid ) : null;
		if ( ! self::plan_available_in_context( $plan ) || ! SimpleVPBot_Model_Plan::is_per_gb( $plan ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.session_restart', $user ) );
			return;
		}
		if ( ! preg_match( '/^\d+$/u', $raw ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.integer_gb', $user ) );
			return;
		}
		$gb = (int) $raw;
		if ( $gb < 1 || ! SimpleVPBot_Model_Plan::is_volume_in_range( $plan, $gb ) ) {
			$min = (int) ( $plan->traffic_gb_min ?? 0 );
			$max = (int) ( $plan->traffic_gb_max ?? 0 );
			$min_fa = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $min );
			$max_fa = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $max );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Texts::format(
					SimpleVPBot_Texts::get_for_user( 'msg.buy.volume_range', $user ),
					array(
						'min' => $min_fa,
						'max' => $max_fa,
					)
				)
			);
			return;
		}
		$amount = SimpleVPBot_Model_Plan::total_price( $plan, $gb );
		SimpleVPBot_State::clear( (int) $user->id );
		$cb = 'buy:cf:' . (int) $plan->id . ':' . $gb;
		if ( strlen( $cb ) > 64 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.id_overflow', $user ) );
			return;
		}
		$text = self::plan_confirm_message_text( $plan, $user, $amount, $gb );
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$text,
			array(
				'reply_markup' => array(
					'inline_keyboard' => array(
						array(
							array( 'text' => SimpleVPBot_Keyboards::glass_button_text( SimpleVPBot_Texts::get_for_user( 'btn.pay.confirm_pay', $user ) ), 'callback_data' => $cb ),
							array( 'text' => SimpleVPBot_Keyboards::glass_button_text( SimpleVPBot_Texts::get_for_user( 'btn.pay.cancel', $user ) ), 'callback_data' => 'buy:x:0' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Receipt photo upload.
	 *
	 * @param array<string, mixed> $ctx Context.
	 */
	public static function handle_receipt_photo( array $ctx ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = $ctx['user'];
		$msg      = $ctx['message'];
		if ( 'receipt_upload' !== (string) $user->state ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.start_from_menu', $user ) );
			return;
		}
		$sd  = SimpleVPBot_State::data( $user );
		$txid = (int) ( $sd['transaction_id'] ?? 0 );
		$cid  = (int) ( $sd['card_id'] ?? 0 );
		if ( ! $txid || ! $cid ) {
			SimpleVPBot_State::clear( (int) $user->id );
			return;
		}
		$tx = SimpleVPBot_Model_Transaction::find( $txid );
		if ( ! $tx ) {
			return;
		}
		$photos = isset( $msg['photo'] ) && is_array( $msg['photo'] ) ? $msg['photo'] : array();
		$last   = end( $photos );
		$file_id = is_array( $last ) && isset( $last['file_id'] ) ? (string) $last['file_id'] : '';
		if ( ! $file_id ) {
			return;
		}
		$rid = SimpleVPBot_Model_Receipt::insert(
			array(
				'user_id'        => (int) $user->id,
				'transaction_id' => $txid,
				'tg_file_id'     => 'telegram' === $platform ? $file_id : '',
				'bale_file_id'   => 'bale' === $platform ? $file_id : '',
				'amount'         => (float) $tx->amount,
				'card_id'        => $cid,
				'status'         => 'pending',
				'admin_messages_json' => wp_json_encode( array() ),
			)
		);
		SimpleVPBot_State::clear( (int) $user->id );
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.receipt_received', $user ) );

		$uid  = (int) $user->id;
		$txid = (int) $tx->id;
		$deliver = static function () use ( $rid, $platform, $file_id, $uid, $txid ) {
			self::run_deferred_receipt_admin_notify( (int) $rid, (string) $platform, (string) $file_id, (int) $uid, (int) $txid );
		};
		if ( class_exists( 'SimpleVPBot_Deferred_Work' ) ) {
			SimpleVPBot_Deferred_Work::run_after_response_or_cron(
				$deliver,
				SimpleVPBot_Deferred_Work::RECEIPT_ADMIN_NOTIFY_CRON_HOOK,
				array( (int) $rid, (string) $platform, (string) $file_id, (int) $uid, (int) $txid ),
				'receipt_admin_notify'
			);
		} else {
			$deliver();
		}
	}

	/**
	 * Cron fallback for deferred receipt admin notify.
	 *
	 * @param int    $rid      Receipt id.
	 * @param string $platform telegram|bale.
	 * @param string $file_id  Platform file id.
	 * @param int    $uid      svp_users.id.
	 * @param int    $txid     Transaction id.
	 */
	public static function deferred_receipt_admin_notify_cron( $rid, $platform, $file_id, $uid, $txid ) {
		self::run_deferred_receipt_admin_notify( (int) $rid, (string) $platform, (string) $file_id, (int) $uid, (int) $txid );
	}

	/**
	 * @param int $rid Receipt id.
	 * @return array<int, array<string, mixed>>
	 */
	private static function receipt_admin_messages_list( $rid ) {
		$rec = SimpleVPBot_Model_Receipt::find( (int) $rid );
		if ( ! $rec ) {
			return array();
		}
		$list = json_decode( (string) ( $rec->admin_messages_json ?? '' ), true );
		return is_array( $list ) ? $list : array();
	}

	/**
	 * Whether at least one admin received the receipt photo.
	 *
	 * @param int $rid Receipt id.
	 * @return bool
	 */
	private static function receipt_admin_photo_delivered( $rid ) {
		foreach ( self::receipt_admin_messages_list( $rid ) as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['message_id'] ) ) {
				continue;
			}
			if ( 'photo' === (string) ( $entry['kind'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether this admin chat already has a photo notify entry.
	 *
	 * @param int    $rid      Receipt id.
	 * @param string $platform telegram|bale.
	 * @param int    $chat_id  Admin chat id.
	 * @return bool
	 */
	private static function admin_receipt_photo_delivered_for_chat( $rid, $platform, $chat_id ) {
		$plat = (string) $platform;
		$cid  = (int) $chat_id;
		foreach ( self::receipt_admin_messages_list( $rid ) as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['message_id'] ) ) {
				continue;
			}
			if ( $plat === (string) ( $entry['platform'] ?? '' )
				&& $cid === (int) ( $entry['chat_id'] ?? 0 )
				&& 'photo' === (string) ( $entry['kind'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param int    $rid      Receipt id.
	 * @param string $platform telegram|bale.
	 * @param string $file_id  Platform file id.
	 * @param int    $uid      User id.
	 * @param int    $txid     Transaction id.
	 */
	private static function clear_receipt_admin_notify_cron( $rid, $platform, $file_id, $uid, $txid ) {
		if ( ! class_exists( 'SimpleVPBot_Deferred_Work' ) ) {
			return;
		}
		SimpleVPBot_Deferred_Work::clear_scheduled_cron(
			SimpleVPBot_Deferred_Work::RECEIPT_ADMIN_NOTIFY_CRON_HOOK,
			array( (int) $rid, (string) $platform, (string) $file_id, (int) $uid, (int) $txid )
		);
	}

	/**
	 * @param int $rid Receipt id.
	 * @return string
	 */
	private static function receipt_admin_notify_attempt_key( $rid ) {
		return 'svp_receipt_admin_try_' . (int) $rid;
	}

	/**
	 * In-flight lock while receipt admin notify is running.
	 *
	 * @param int $rid Receipt id.
	 * @return string
	 */
	private static function receipt_admin_notify_lock_key( $rid ) {
		return 'svp_receipt_admin_notify_' . (int) $rid;
	}

	/**
	 * Load user/tx and notify admins about a new receipt.
	 *
	 * @param int    $rid      Receipt id.
	 * @param string $platform telegram|bale.
	 * @param string $file_id  Platform file id.
	 * @param int    $uid      svp_users.id.
	 * @param int    $txid     Transaction id.
	 */
	private static function run_deferred_receipt_admin_notify( $rid, $platform, $file_id, $uid, $txid ) {
		$rid = (int) $rid;
		if ( self::receipt_admin_photo_delivered( $rid ) ) {
			self::clear_receipt_admin_notify_cron( $rid, $platform, $file_id, $uid, $txid );
			return;
		}
		$try_key = self::receipt_admin_notify_attempt_key( $rid );
		$tries   = (int) get_transient( $try_key );
		if ( $tries >= 3 ) {
			self::clear_receipt_admin_notify_cron( $rid, $platform, $file_id, $uid, $txid );
			return;
		}
		if ( $tries > 0 ) {
			sleep( min( 5, 2 * $tries ) );
		}
		set_transient( $try_key, (string) ( $tries + 1 ), 3600 );
		$user_row = SimpleVPBot_Model_User::find( (int) $uid );
		$tx_row   = SimpleVPBot_Model_Transaction::find( (int) $txid );
		if ( $user_row && $tx_row ) {
			self::deliver_receipt_to_admins( (int) $rid, (string) $platform, (string) $file_id, $user_row, $tx_row );
		}
		if ( self::receipt_admin_photo_delivered( $rid ) ) {
			delete_transient( $try_key );
			self::clear_receipt_admin_notify_cron( $rid, $platform, $file_id, $uid, $txid );
		}
	}

	/**
	 * Download receipt photo and notify admins (deferred from handle_receipt_photo).
	 *
	 * @param int                  $rid      Receipt id.
	 * @param string               $platform telegram|bale.
	 * @param string               $file_id  Platform file id.
	 * @param object               $user     User row.
	 * @param object               $tx       Transaction row.
	 */
	public static function deliver_receipt_to_admins( $rid, $platform, $file_id, $user, $tx ) {
		$rid      = (int) $rid;
		$platform = (string) $platform;
		$file_id  = (string) $file_id;
		if ( self::receipt_admin_photo_delivered( $rid ) ) {
			return;
		}
		$lock_key = self::receipt_admin_notify_lock_key( $rid );
		if ( get_transient( $lock_key ) ) {
			return;
		}
		set_transient( $lock_key, '1', 120 );
		$admin_msgs = array();
		$photo_args = array( 'reply_markup' => SimpleVPBot_Keyboards::inline_receipt( $rid ) );
		$admin_ids  = self::admin_ids_for_current_context();
		$tg_ids     = $admin_ids['telegram'];
		$bl_ids     = $admin_ids['bale'];
		$tg_tok     = (string) SimpleVPBot_Bot_Runtime::bot_token_for_current_context( 'telegram' );
		$bl_tok     = (string) SimpleVPBot_Bot_Runtime::bot_token_for_current_context( 'bale' );
		$notify_us  = class_exists( 'SimpleVPBot_Settings' ) ? SimpleVPBot_Settings::bot_admin_notify_usleep() : 80000;

		$rec         = SimpleVPBot_Model_Receipt::find( $rid );
		$stored_path = ( is_object( $rec ) && class_exists( 'SimpleVPBot_Receipt_Image_Store' ) )
			? SimpleVPBot_Receipt_Image_Store::readable_path_for_receipt( $rec )
			: '';
		$local_path  = ( '' !== $stored_path && is_readable( $stored_path ) ) ? $stored_path : '';
		$temp_owned  = false;
		if ( '' === $local_path ) {
			$local_path = self::download_receipt_to_temp( $platform, $file_id );
			if ( '' === $local_path || ! is_readable( $local_path ) ) {
				usleep( 150000 );
				$local_path = self::download_receipt_to_temp( $platform, $file_id );
			}
			$temp_owned = '' !== $local_path && is_readable( $local_path );
			if ( $temp_owned && class_exists( 'SimpleVPBot_Receipt_Image_Store' ) ) {
				SimpleVPBot_Receipt_Image_Store::persist_from_temp( $rid, $local_path );
				$rec = SimpleVPBot_Model_Receipt::find( $rid ) ?: $rec;
			}
		}
		$tg_file_id = 'telegram' === $platform ? $file_id : '';
		$bl_file_id = 'bale' === $platform ? $file_id : '';

		if ( $tg_tok ) {
			$body_tg = SimpleVPBot_Bot_Admin_User_Caption::receipt_new_caption_for_platform( $user, $tx, $rid, 'telegram' );
			foreach ( $tg_ids as $adm ) {
				$adm = (int) $adm;
				if ( self::admin_receipt_photo_delivered_for_chat( $rid, 'telegram', $adm ) ) {
					continue;
				}
				if ( is_object( $rec ) ) {
					$r = self::send_admin_receipt_photo_review( 'telegram', $adm, $rec, $tg_file_id, '', $body_tg, $photo_args, $rid );
				} else {
					$r = self::send_admin_receipt_photo_retry( 'telegram', $adm, $tg_file_id, '', $local_path, $body_tg, $photo_args, $rid, 4 );
				}
				if ( is_array( $r ) && ! empty( $r['result']['message_id'] ) ) {
					$admin_msgs[] = array(
						'platform'   => 'telegram',
						'chat_id'    => $adm,
						'message_id' => (int) $r['result']['message_id'],
						'kind'       => 'photo',
					);
				} else {
					self::notify_admin_receipt_photo_fallback( 'telegram', $adm, $rid );
				}
				if ( $notify_us > 0 ) {
					usleep( $notify_us );
				}
			}
		}
		if ( $bl_tok ) {
			$body_bl = SimpleVPBot_Bot_Admin_User_Caption::receipt_new_caption_for_platform( $user, $tx, $rid, 'bale' );
			foreach ( $bl_ids as $adm ) {
				$adm = (int) $adm;
				if ( self::admin_receipt_photo_delivered_for_chat( $rid, 'bale', $adm ) ) {
					continue;
				}
				if ( is_object( $rec ) ) {
					$r = self::send_admin_receipt_photo_review( 'bale', $adm, $rec, '', $bl_file_id, $body_bl, $photo_args, $rid );
				} else {
					$r = self::send_admin_receipt_photo_retry( 'bale', $adm, '', $bl_file_id, $local_path, $body_bl, $photo_args, $rid, 4 );
				}
				if ( is_array( $r ) && ! empty( $r['result']['message_id'] ) ) {
					$admin_msgs[] = array(
						'platform'   => 'bale',
						'chat_id'    => $adm,
						'message_id' => (int) $r['result']['message_id'],
						'kind'       => 'photo',
					);
				} else {
					self::notify_admin_receipt_photo_fallback( 'bale', $adm, $rid );
				}
				if ( $notify_us > 0 ) {
					usleep( $notify_us );
				}
			}
		}
		if ( $temp_owned && $local_path && file_exists( $local_path ) ) {
			@unlink( $local_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		if ( ! empty( $admin_msgs ) ) {
			self::merge_admin_message_entries( $rid, $admin_msgs );
		}
		delete_transient( $lock_key );
	}

	/**
	 * Send receipt photo+caption+keyboard in one message with a multi-strategy retry ladder.
	 *
	 * @param string               $platform           telegram|bale.
	 * @param int                  $admin_chat         Admin chat id.
	 * @param object|null          $rec                Receipt row (stored image).
	 * @param string               $tg_file_id         Source Telegram file_id.
	 * @param string               $bl_file_id         Source Bale file_id.
	 * @param string               $body               Caption (non-empty).
	 * @param array<string, mixed> $photo_args         reply_markup etc.
	 * @param int                  $rid                Receipt id.
	 * @param string               $initial_local_path Optional temp path from uploader.
	 * @return array<string, mixed>|null
	 */
	private static function send_admin_receipt_photo_ladder( $platform, $admin_chat, $rec, $tg_file_id, $bl_file_id, $body, array $photo_args, $rid, $initial_local_path = '' ) {
		$admin_chat = (int) $admin_chat;
		$rid        = (int) $rid;
		$platform   = (string) $platform;
		$body       = trim( (string) $body );
		if ( $admin_chat < 1 || $rid < 1 || '' === $body ) {
			return null;
		}
		$tg_src = trim( (string) $tg_file_id );
		$bl_src = trim( (string) $bl_file_id );
		$tg_for = 'telegram' === $platform ? $tg_src : '';
		$bl_for = 'bale' === $platform ? $bl_src : '';
		$stored = ( is_object( $rec ) && class_exists( 'SimpleVPBot_Receipt_Image_Store' ) )
			? SimpleVPBot_Receipt_Image_Store::readable_path_for_receipt( $rec )
			: '';
		if ( '' === $stored && '' !== (string) $initial_local_path && is_readable( (string) $initial_local_path ) ) {
			$stored = (string) $initial_local_path;
		}
		$caption_variants = array( $body );
		if ( class_exists( 'SimpleVPBot_Bot_Admin_User_Caption' ) ) {
			$san = SimpleVPBot_Bot_Admin_User_Caption::sanitize_receipt_caption_retry( $platform, $body );
			if ( '' !== $san && $san !== $body ) {
				$caption_variants[] = $san;
			}
		}
		$caption_variants = array_values( array_unique( array_filter( $caption_variants, static function ( $c ) {
			return '' !== trim( (string) $c );
		} ) ) );
		$temp_path  = '';
		$temp_owned = false;
		$delay_us   = 150000;
		$last_err   = '';
		$strategies = array(
			array( 'local' => $stored, 'tg' => $tg_for, 'bl' => $bl_for ),
			array( 'local' => '', 'tg' => $tg_for, 'bl' => $bl_for ),
			array( 'local' => $stored, 'tg' => '', 'bl' => '' ),
		);
		foreach ( $strategies as $strat ) {
			$local_path = (string) ( $strat['local'] ?? '' );
			if ( '' === $local_path ) {
				if ( '' === $temp_path ) {
					if ( '' !== $tg_src ) {
						$temp_path = self::download_receipt_to_temp( 'telegram', $tg_src );
					} elseif ( '' !== $bl_src ) {
						$temp_path = self::download_receipt_to_temp( 'bale', $bl_src );
					}
					$temp_owned = '' !== $temp_path && is_readable( $temp_path );
				}
				$local_path = $temp_path;
			}
			foreach ( $caption_variants as $caption ) {
				$r = self::try_send_admin_receipt_photo_once(
					$platform,
					$admin_chat,
					(string) ( $strat['tg'] ?? '' ),
					(string) ( $strat['bl'] ?? '' ),
					$local_path,
					(string) $caption,
					$photo_args
				);
				if ( is_array( $r ) && ! empty( $r['result']['message_id'] ) ) {
					if ( $temp_owned && $temp_path && file_exists( $temp_path ) ) {
						@unlink( $temp_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					}
					return $r;
				}
				if ( is_array( $r ) ) {
					$last_err = (string) ( $r['description'] ?? wp_json_encode( $r ) );
				}
				usleep( $delay_us );
			}
		}
		if ( $temp_owned && $temp_path && file_exists( $temp_path ) ) {
			@unlink( $temp_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		SimpleVPBot_Logger::error(
			'receipt admin photo+caption delivery failed',
			array(
				'receipt_id' => $rid,
				'platform'   => $platform,
				'admin_chat' => $admin_chat,
				'api_error'  => $last_err,
			)
		);
		return null;
	}

	/**
	 * Send receipt image to one admin with retries (single photo message, caption required).
	 *
	 * @param string               $platform   telegram|bale.
	 * @param int                  $admin_chat Destination chat id.
	 * @param string               $tg_file_id Telegram file_id when receipt came from Telegram.
	 * @param string               $bl_file_id Bale file_id when receipt came from Bale.
	 * @param string               $local_path Temp path from download_receipt_to_temp or ''.
	 * @param string               $body       Caption.
	 * @param array<string, mixed> $photo_args e.g. reply_markup.
	 * @param int                  $rid        Receipt id for logs.
	 * @param int                  $max_attempts Unused; ladder handles attempts.
	 * @return array<string, mixed>|null
	 */
	public static function send_admin_receipt_photo_retry( $platform, $admin_chat, $tg_file_id, $bl_file_id, $local_path, $body, array $photo_args, $rid, $max_attempts = 4 ) {
		unset( $max_attempts );
		$rec = SimpleVPBot_Model_Receipt::find( (int) $rid );
		return self::send_admin_receipt_photo_ladder(
			$platform,
			(int) $admin_chat,
			$rec ?: null,
			$tg_file_id,
			$bl_file_id,
			$body,
			$photo_args,
			(int) $rid,
			(string) $local_path
		);
	}

	/**
	 * Receipt photo send for bulk «تأیید رسیدها» (stored file first, caption always on photo).
	 *
	 * @param string               $platform   telegram|bale.
	 * @param int                  $admin_chat Admin chat id.
	 * @param object|null          $rec        Receipt row (for stored_image_path).
	 * @param string               $tg_file_id Telegram file_id.
	 * @param string               $bl_file_id Bale file_id.
	 * @param string               $body       Caption.
	 * @param array<string, mixed> $photo_args Extra params.
	 * @param int                  $rid        Receipt id for logs.
	 * @return array<string, mixed>|null
	 */
	public static function send_admin_receipt_photo_review( $platform, $admin_chat, $rec, $tg_file_id, $bl_file_id, $body, array $photo_args, $rid ) {
		return self::send_admin_receipt_photo_ladder(
			$platform,
			(int) $admin_chat,
			$rec,
			$tg_file_id,
			$bl_file_id,
			$body,
			$photo_args,
			(int) $rid,
			''
		);
	}

	/**
	 * Merge admin notify entries without duplicate platform/chat/message_id.
	 *
	 * @param int                             $receipt_id   Receipt id.
	 * @param array<int, array<string, mixed>> $new_entries New rows.
	 * @return void
	 */
	public static function merge_admin_message_entries( $receipt_id, array $new_entries ) {
		$rid = (int) $receipt_id;
		if ( $rid < 1 || empty( $new_entries ) ) {
			return;
		}
		$rec = SimpleVPBot_Model_Receipt::find( $rid );
		if ( ! $rec ) {
			return;
		}
		$existing = json_decode( (string) ( $rec->admin_messages_json ?? '' ), true );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		foreach ( $new_entries as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['message_id'] ) ) {
				continue;
			}
			$plat = (string) ( $entry['platform'] ?? '' );
			$cid  = (int) ( $entry['chat_id'] ?? 0 );
			$mid  = (int) ( $entry['message_id'] ?? 0 );
			$kind = (string) ( $entry['kind'] ?? 'photo' );
			$dup  = false;
			foreach ( $existing as $idx => $e ) {
				if ( ! is_array( $e ) ) {
					continue;
				}
				if ( $plat === (string) ( $e['platform'] ?? '' )
					&& $cid === (int) ( $e['chat_id'] ?? 0 ) ) {
					if ( 'photo' === $kind && 'text_fallback' === (string) ( $e['kind'] ?? '' ) ) {
						$existing[ $idx ] = $entry;
					}
					$dup = true;
					break;
				}
				if ( $plat === (string) ( $e['platform'] ?? '' )
					&& $cid === (int) ( $e['chat_id'] ?? 0 )
					&& $mid === (int) ( $e['message_id'] ?? 0 ) ) {
					$dup = true;
					break;
				}
			}
			if ( ! $dup ) {
				$existing[] = $entry;
			}
		}
		SimpleVPBot_Model_Receipt::update( $rid, array( 'admin_messages_json' => wp_json_encode( $existing ) ) );
	}

	/**
	 * Single sendPhoto attempt (file_id then temp file upload).
	 *
	 * @param string               $platform   telegram|bale.
	 * @param int                  $admin_chat Admin chat id.
	 * @param string               $tg_file_id Telegram file_id.
	 * @param string               $bl_file_id Bale file_id.
	 * @param string               $local_path Temp file path.
	 * @param string               $body       Caption (empty allowed).
	 * @param array<string, mixed> $photo_args Extra params.
	 * @return array<string, mixed>|null
	 */
	private static function try_send_admin_receipt_photo_once( $platform, $admin_chat, $tg_file_id, $bl_file_id, $local_path, $body, array $photo_args ) {
		if ( '' === trim( (string) $body ) ) {
			return null;
		}
		$r = null;
		if ( 'telegram' === $platform ) {
			if ( '' !== (string) $tg_file_id ) {
				$r = SimpleVPBot_Bot_Runtime::send_photo( 'telegram', (int) $admin_chat, (string) $tg_file_id, (string) $body, $photo_args );
			}
			if ( ( ! is_array( $r ) || empty( $r['result']['message_id'] ) ) && $local_path && is_readable( $local_path ) ) {
				$r = SimpleVPBot_Bot_Runtime::send_photo_file( 'telegram', (int) $admin_chat, $local_path, (string) $body, $photo_args );
			}
		} else {
			if ( '' !== (string) $bl_file_id ) {
				$r = SimpleVPBot_Bot_Runtime::send_photo( 'bale', (int) $admin_chat, (string) $bl_file_id, (string) $body, $photo_args );
			}
			if ( ( ! is_array( $r ) || empty( $r['result']['message_id'] ) ) && $local_path && is_readable( $local_path ) ) {
				$r = SimpleVPBot_Bot_Runtime::send_photo_file( 'bale', (int) $admin_chat, $local_path, (string) $body, $photo_args );
			}
		}
		return is_array( $r ) ? $r : null;
	}

	/**
	 * Short admin notice when photo+caption delivery failed (cron will retry; not a delivered receipt).
	 *
	 * @param string $platform   telegram|bale.
	 * @param int    $admin_chat Admin chat id.
	 * @param int    $rid        Receipt id.
	 */
	public static function notify_admin_receipt_photo_fallback( $platform, $admin_chat, $rid ) {
		$rid  = (int) $rid;
		$text = '⏳ رسید #' . $rid . ' — ارسال عکس+جزئیات ناموفق بود؛ به‌زودی دوباره تلاش می‌شود. در پنل «رسیدها» هم موجود است.';
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			(int) $admin_chat,
			$text
		);
	}

	/**
	 * Download uploaded receipt file to a temp path; empty string on failure.
	 *
	 * @param string $platform telegram|bale.
	 * @param string $file_id  File id on the uploader's platform.
	 * @return string Local path or ''.
	 */
	public static function download_receipt_to_temp( $platform, $file_id ) {
		$file_id = (string) $file_id;
		if ( '' === $file_id ) {
			return '';
		}
		$tmp = function_exists( 'wp_tempnam' ) ? wp_tempnam( 'svp_receipt' ) : @tempnam( sys_get_temp_dir(), 'svp_receipt' ); // phpcs:ignore
		if ( ! $tmp ) {
			return '';
		}
		$down = SimpleVPBot_Bot_Runtime::download_bot_file_to_path( $platform, $file_id, $tmp );
		if ( is_wp_error( $down ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( class_exists( 'SimpleVPBot_Logger' ) ) {
				SimpleVPBot_Logger::error(
					'receipt temp download failed',
					array(
						'platform' => (string) $platform,
						'error'    => $down->get_error_message(),
					)
				);
			}
			return '';
		}
		$ext = '.jpg';
		if ( is_readable( $tmp ) ) {
			$head = (string) file_get_contents( $tmp, false, null, 0, 12 ); // phpcs:ignore
			if ( 0 === strpos( $head, "\x89PNG" ) ) {
				$ext = '.png';
			} elseif ( 0 === strpos( $head, 'RIFF' ) && false !== strpos( substr( $head, 0, 12 ), 'WEBP' ) ) {
				$ext = '.webp';
			}
		}
		$final = $tmp . $ext;
		if ( @rename( $tmp, $final ) ) { // phpcs:ignore
			return $final;
		}
		return $tmp;
	}

	/**
	 * Resolve admin chat ids for receipt moderation in current bot context.
	 *
	 * @return array{telegram:int[],bale:int[]}
	 */
	private static function admin_ids_for_current_context() {
		if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return array(
				'telegram' => SimpleVPBot_Bot_Reseller_Scope::admin_ids_for_context( 'telegram' )['telegram'],
				'bale'     => SimpleVPBot_Bot_Reseller_Scope::admin_ids_for_context( 'bale' )['bale'],
			);
		}
		$tg_ids = array_map( 'intval', (array) SimpleVPBot_Settings::get( 'admin_telegram_ids', array() ) );
		$bl_ids = array_map( 'intval', (array) SimpleVPBot_Settings::get( 'admin_bale_ids', array() ) );
		return array(
			'telegram' => array_values( array_filter( $tg_ids ) ),
			'bale'     => array_values( array_filter( $bl_ids ) ),
		);
	}

	/**
	 * Owner scope for catalog queries on the current bot request.
	 *
	 * @return array<int, int>
	 */
	private static function catalog_owner_ids() {
		return class_exists( 'SimpleVPBot_Bot_Reseller_Scope' )
			? SimpleVPBot_Bot_Reseller_Scope::catalog_owner_ids()
			: array();
	}

	/**
	 * @param array<int, object> $cats Category rows.
	 * @return array<int, object>
	 */
	private static function buyable_categories_for_context( array $cats ) {
		$list = array();
		foreach ( $cats as $c ) {
			if ( self::category_has_buyable_plans( $c ) ) {
				$list[] = $c;
			}
		}
		return $list;
	}

	/**
	 * Transient key for buyable category list (reseller + panel scope).
	 *
	 * @return string
	 */
	private static function buy_catalog_cache_key() {
		$rid    = class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ? (int) SimpleVPBot_Bot_Reseller_Scope::active_reseller_id() : 0;
		$owners = self::catalog_owner_ids();
		sort( $owners );
		$parts = array( (string) $rid, implode( ',', $owners ) );
		if ( $rid > 0 && class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			$panels = SimpleVPBot_Bot_Reseller_Scope::allowed_panel_ids_for( $rid );
			sort( $panels );
			$parts[] = implode( ',', $panels );
		}
		return 'svp_buy_cats_' . substr( md5( implode( '|', $parts ) ), 0, 16 );
	}

	/**
	 * Buyable categories via one plan query + transient cache (picker fast path).
	 *
	 * @param array<int, object> $cats Category rows.
	 * @return array<int, object>
	 */
	private static function buyable_categories_for_context_fast( array $cats ) {
		if ( empty( $cats ) ) {
			return array();
		}
		$ttl       = class_exists( 'SimpleVPBot_Settings' ) ? SimpleVPBot_Settings::buy_catalog_cache_ttl_sec() : 90;
		$cache_key = self::buy_catalog_cache_key();
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			$id_set = array_flip( array_map( 'intval', $cached ) );
			$list   = array();
			foreach ( $cats as $c ) {
				if ( isset( $id_set[ (int) $c->id ] ) ) {
					$list[] = $c;
				}
			}
			return $list;
		}
		$plans        = SimpleVPBot_Model_Plan::all_active_for_owners( self::catalog_owner_ids() );
		$buyable_keys = array();
		foreach ( $plans as $p ) {
			if ( ! self::plan_available_in_context( $p ) ) {
				continue;
			}
			if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) && ! SimpleVPBot_Feature_L2tp::plan_visible( $p ) ) {
				continue;
			}
			$panel_id = max( 1, (int) ( $p->panel_id ?? 1 ) );
			$slug     = (string) ( $p->category ?? '' );
			if ( '' === $slug ) {
				continue;
			}
			$buyable_keys[ $panel_id . ':' . $slug ] = true;
		}
		$list = array();
		$ids  = array();
		foreach ( $cats as $c ) {
			if ( ! $c || ! (int) $c->active ) {
				continue;
			}
			$panel_id = max( 1, (int) ( $c->panel_id ?? 1 ) );
			if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) && ! SimpleVPBot_Bot_Reseller_Scope::panel_allowed_in_context( $panel_id ) ) {
				continue;
			}
			$key = $panel_id . ':' . (string) $c->slug;
			if ( empty( $buyable_keys[ $key ] ) ) {
				continue;
			}
			$list[] = $c;
			$ids[]  = (int) $c->id;
		}
		set_transient( $cache_key, $ids, $ttl );
		return $list;
	}

	/**
	 * Cached plans for a category row (buy:g / buy:c).
	 *
	 * @param object $cat_row svp_plan_categories row.
	 * @return array<int, object>
	 */
	private static function plans_for_category_cached( $cat_row ) {
		$cat_id = (int) ( $cat_row->id ?? 0 );
		$ttl    = class_exists( 'SimpleVPBot_Settings' ) ? SimpleVPBot_Settings::buy_catalog_cache_ttl_sec() : 90;
		$key    = 'svp_buy_plans_' . $cat_id;
		$cached = $cat_id > 0 ? get_transient( $key ) : false;
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$panel_id = max( 1, (int) ( $cat_row->panel_id ?? 1 ) );
		$plans    = self::plans_for_category( (string) $cat_row->slug, $panel_id );
		if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) ) {
			$plans = SimpleVPBot_Feature_L2tp::filter_plans( (array) $plans );
		}
		if ( $cat_id > 0 ) {
			set_transient( $key, $plans, $ttl );
		}
		return $plans;
	}

	/**
	 * Active plans in category for current bot tenant scope.
	 *
	 * @param string $category Category slug.
	 * @param int    $panel_id Panel id.
	 * @return array<int, object>
	 */
	private static function plans_for_category( $category, $panel_id ) {
		return SimpleVPBot_Model_Plan::by_category_for_owners( (string) $category, (int) $panel_id, self::catalog_owner_ids() );
	}

	/**
	 * Whether a plan row is buyable in the current bot context.
	 *
	 * @param object|null $plan Plan row.
	 * @return bool
	 */
	private static function plan_available_in_context( $plan ) {
		if ( ! $plan || ! (int) $plan->active ) {
			return false;
		}
		if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) && ! SimpleVPBot_Bot_Reseller_Scope::plan_visible_in_context( $plan ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Whether category has at least one active plan (buy flow).
	 *
	 * @param object $cat_row svp_plan_categories row.
	 * @return bool
	 */
	private static function category_has_buyable_plans( $cat_row ) {
		if ( ! $cat_row || ! (int) $cat_row->active ) {
			return false;
		}
		$panel_id = max( 1, (int) ( $cat_row->panel_id ?? 1 ) );
		if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) && ! SimpleVPBot_Bot_Reseller_Scope::panel_allowed_in_context( $panel_id ) ) {
			return false;
		}
		$slug     = (string) $cat_row->slug;
		foreach ( self::plans_for_category( $slug, $panel_id ) as $p ) {
			if ( self::plan_available_in_context( $p ) ) {
				if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) && ! SimpleVPBot_Feature_L2tp::plan_visible( $p ) ) {
					continue;
				}
				return true;
			}
		}
		return false;
	}

	/**
	 * Inline keyboard rows for category rows (callback buy:g:{id}).
	 *
	 * @param array<int, object> $cats Category rows.
	 * @return array<int, array<int, array<string, string>>>
	 */
	private static function inline_keyboard_for_category_rows( array $cats ) {
		$row   = array();
		$lines = array();
		foreach ( $cats as $c ) {
			$cid = (int) $c->id;
			$cb  = 'buy:g:' . $cid;
			if ( strlen( $cb ) > 64 ) {
				continue;
			}
			$lab   = (string) $c->label;
			$inner = 64 - mb_strlen( SimpleVPBot_Keyboards::GLASS_PREFIX, 'UTF-8' );
			if ( $inner < 6 ) {
				$inner = 6;
			}
			if ( mb_strlen( $lab, 'UTF-8' ) > $inner ) {
				$lab = mb_substr( $lab, 0, $inner, 'UTF-8' );
			}
			$row[] = array(
				'text'          => SimpleVPBot_Keyboards::glass_button_text( $lab, 64 ),
				'callback_data' => $cb,
			);
			if ( count( $row ) >= 2 ) {
				$lines[] = $row;
				$row     = array();
			}
		}
		if ( ! empty( $row ) ) {
			$lines[] = $row;
		}
		return $lines;
	}

	/**
	 * Send plan list: intro lines + one button per plan.
	 *
	 * @param string               $platform Platform.
	 * @param int                  $chat_id  Chat id.
	 * @param string               $category_label Category title for user.
	 * @param array<int, object>   $plans      Plan rows (may include inactive — filtered).
	 */
	public static function send_plan_picker_for_plans( $platform, $chat_id, $category_label, array $plans ) {
		$active = array();
		foreach ( $plans as $p ) {
			if ( $p && (int) $p->active ) {
				if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) && ! SimpleVPBot_Feature_L2tp::plan_visible( $p ) ) {
					continue;
				}
				$active[] = $p;
			}
		}
		if ( empty( $active ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.no_plans_in_category', $user ) );
			return;
		}
		$intro   = array();
		$intro[] = '📂 دسته: ' . trim( (string) $category_label );
		$intro[] = 'یکی از پلن‌های زیر را انتخاب کنید.';
		$rows = array();
		foreach ( $active as $p ) {
			$cb = 'buy:p:' . (int) $p->id;
			if ( strlen( $cb ) > 64 ) {
				continue;
			}
			$rows[] = array(
				array(
					'text'          => SimpleVPBot_Bot_Persian_Text::plan_picker_glass_button( $p ),
					'callback_data' => $cb,
				),
			);
		}
		if ( empty( $rows ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.no_plans_in_category', $user ) );
			return;
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			implode( "\n", $intro ),
			array( 'reply_markup' => array( 'inline_keyboard' => $rows ) )
		);
	}

	/**
	 * Send category picker (first step).
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat id.
	 */
	public static function send_category_picker( $platform, $chat_id ) {
		$list = self::buyable_categories_for_context_fast( SimpleVPBot_Model_Plan_Category::active_ordered() );
		if ( empty( $list ) ) {
			SimpleVPBot_Bot_Runtime::send_message_with_support(
				$platform,
				$chat_id,
				SimpleVPBot_Texts::get_for_user( 'msg.buy.no_active_categories', $user )
			);
			return;
		}
		$lines = self::inline_keyboard_for_category_rows( $list );
		if ( empty( $lines ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.no_categories', $user ) );
			return;
		}
		SimpleVPBot_Bot_Runtime::send_message_interactive(
			$platform,
			$chat_id,
			SimpleVPBot_Texts::get_for_user( 'msg.buy.pick_category', $user ),
			array( 'reply_markup' => array( 'inline_keyboard' => $lines ) )
		);
	}

	/**
	 * Category buttons scoped to one panel (callback buy:c:{panel_id}:{slug}).
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param int    $panel_id svp_panels.id.
	 */
	public static function send_category_picker_for_panel( $platform, $chat_id, $panel_id ) {
		$panel_id = max( 1, (int) $panel_id );
		if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) && ! SimpleVPBot_Bot_Reseller_Scope::panel_allowed_in_context( $panel_id ) ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Texts::get_for_user( 'msg.buy.panel_not_for_sale', $user )
			);
			return;
		}
		$list = self::buyable_categories_for_context_fast( SimpleVPBot_Model_Plan_Category::active_ordered_for_panel( $panel_id ) );
		if ( empty( $list ) ) {
			SimpleVPBot_Bot_Runtime::send_message_with_support(
				$platform,
				$chat_id,
				SimpleVPBot_Texts::get_for_user( 'msg.buy.no_categories_for_panel', $user )
			);
			return;
		}
		$lines = self::inline_keyboard_for_category_rows( $list );
		if ( empty( $lines ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.no_categories', $user ) );
			return;
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			SimpleVPBot_Texts::get_for_user( 'msg.buy.pick_category', $user ),
			array( 'reply_markup' => array( 'inline_keyboard' => $lines ) )
		);
	}

	/**
	 * Step B: full invoice, copy row, then receipt state (same as legacy buy:cd follow-up).
	 *
	 * @param array<string, mixed> $ctx         Context.
	 * @param object                 $tx          Transaction.
	 * @param object                 $card        Card.
	 * @param int                    $edit_msg_id Message to edit (checkout); 0 = send new.
	 */
	private static function send_purchase_step_invoice( array $ctx, $tx, $card, $edit_msg_id = 0 ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = $ctx['user'];
		$amount   = (float) $tx->amount;
		if ( SimpleVPBot_Model_Card::is_crypto_auto( $card ) ) {
			self::send_crypto_invoice_deferred( $platform, $chat_id, $user, $tx, $card );
			return;
		}
		$receipt_hint = SimpleVPBot_Model_Card::is_crypto_manual( $card )
			? '📸 بعد از واریز، تصویر تراکنش یا اسکرین‌شات را همینجا بفرست (txid یا رسید صرافی).'
			: SimpleVPBot_Texts::get_for_user( 'msg.buy.send_receipt_photo', $user );
		$text   = self::format_purchase_invoice_text( $card, $tx, $amount, $platform ) . "\n\n" . $receipt_hint;
		$markup = SimpleVPBot_Keyboards::inline_invoice_actions( $card, $amount, $platform, '', $user );
		$extra  = array( 'reply_markup' => $markup );
		if ( 'telegram' === $platform ) {
			$extra['parse_mode'] = 'HTML';
		}
		$sent = false;
		if ( (int) $edit_msg_id > 0 ) {
			$sent = SimpleVPBot_Bot_Runtime::edit_message_text(
				$platform,
				$chat_id,
				(int) $edit_msg_id,
				$text,
				$extra
			);
		}
		if ( ! $sent ) {
			if ( (int) $edit_msg_id > 0 ) {
				SimpleVPBot_Bot_Runtime::send_message_interactive(
					$platform,
					$chat_id,
					SimpleVPBot_Texts::get_for_user( 'msg.buy.invoice_failed', $user )
				);
				return;
			}
			SimpleVPBot_Bot_Runtime::send_message_interactive(
				$platform,
				$chat_id,
				$text,
				$extra
			);
		}
		SimpleVPBot_State::set(
			(int) $user->id,
			'receipt_upload',
			array(
				'transaction_id' => (int) $tx->id,
				'card_id'        => (int) $card->id,
			)
		);
	}

	/**
	 * NOWPayments invoice: ack immediately, create link after HTTP response.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User row.
	 * @param object $tx       Transaction.
	 * @param object $card     Card row.
	 */
	private static function send_crypto_invoice_deferred( $platform, $chat_id, $user, $tx, $card ) {
		SimpleVPBot_Bot_Runtime::send_message_interactive(
			$platform,
			$chat_id,
			'⏳ در حال ساخت لینک پرداخت…'
		);
		$tx_id   = (int) $tx->id;
		$user_id = (int) $user->id;
		$work    = static function () use ( $platform, $chat_id, $user_id, $tx_id, $card ) {
			$user = SimpleVPBot_Model_User::find( $user_id );
			$tx2  = SimpleVPBot_Model_Transaction::find( $tx_id );
			if ( ! $tx2 || ! $user
				|| (int) $tx2->user_id !== $user_id
				|| 'pending' !== (string) $tx2->status ) {
				return;
			}
			$cr = SimpleVPBot_Crypto_Payment::create_nowpayments_invoice( $tx2, $card, $platform );
			if ( empty( $cr['ok'] ) ) {
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					SimpleVPBot_Texts::format(
						SimpleVPBot_Texts::get_for_user( 'msg.buy.payment_error', $user ),
						array(
							'message' => (string) ( $cr['message'] ?? SimpleVPBot_Texts::get_for_user( 'msg.buy.payment_create_failed', $user ) ),
						)
					)
				);
				return;
			}
			$extra = array( 'reply_markup' => isset( $cr['reply_markup'] ) && is_array( $cr['reply_markup'] ) ? $cr['reply_markup'] : array() );
			if ( 'telegram' === $platform ) {
				$extra['parse_mode'] = 'HTML';
			}
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				(string) ( $cr['text'] ?? '' ),
				$extra
			);
			SimpleVPBot_Bot_Runtime::send_message_with_support(
				$platform,
				$chat_id,
				SimpleVPBot_Texts::get_for_user( 'msg.buy.crypto_pending_hint', $user )
			);
			SimpleVPBot_State::clear( $user_id );
		};
		if ( class_exists( 'SimpleVPBot_Deferred_Work' ) ) {
			SimpleVPBot_Deferred_Work::run_after_response( $work, 'crypto_invoice' );
		} else {
			$work();
		}
	}

	/**
	 * Bale wallet invoice: ack immediately, send_invoice after HTTP response.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User row.
	 * @param object $tx       Transaction.
	 * @param string $ptok     Provider token.
	 * @param string $title    Invoice title.
	 * @param string $desc     Invoice description.
	 * @param string $label    Price label.
	 */
	private static function send_bale_wallet_invoice_deferred( $platform, $chat_id, $user, $tx, $ptok, $title, $desc, $label ) {
		SimpleVPBot_Bot_Runtime::send_message_interactive(
			$platform,
			$chat_id,
			'⏳ در حال ساخت فاکتور…'
		);
		$tx_id   = (int) $tx->id;
		$user_id = (int) $user->id;
		$work    = static function () use ( $platform, $chat_id, $user_id, $tx_id, $ptok, $title, $desc, $label ) {
			$user = SimpleVPBot_Model_User::find( $user_id );
			$tx2  = SimpleVPBot_Model_Transaction::find( $tx_id );
			if ( ! $tx2 || ! $user
				|| (int) $tx2->user_id !== $user_id
				|| 'pending' !== (string) $tx2->status
				|| ! in_array( (string) $tx2->type, array( 'purchase', 'topup' ), true ) ) {
				return;
			}
			$rial = (int) round( (float) $tx2->amount, 0 ) * 10;
			$pay  = self::bale_wallet_build_payload( $tx_id, $user_id );
			$res  = SimpleVPBot_Bot_Runtime::send_invoice(
				'bale',
				array(
					'chat_id'         => $chat_id,
					'title'           => mb_substr( $title, 0, 32 ),
					'description'     => mb_substr( $desc, 0, 200 ),
					'payload'         => $pay,
					'provider_token'  => $ptok,
					'currency'        => 'IRR',
					'prices'          => array(
						array( 'label' => $label, 'amount' => $rial ),
					),
					'start_parameter' => 'vp_' . $tx_id,
				)
			);
			if ( ! $res || empty( $res['ok'] ) ) {
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					SimpleVPBot_Texts::get_for_user( 'msg.buy.invoice_failed', $user )
				);
			}
		};
		if ( class_exists( 'SimpleVPBot_Deferred_Work' ) ) {
			SimpleVPBot_Deferred_Work::run_after_response( $work, 'bale_wallet_invoice' );
		} else {
			$work();
		}
	}

	/**
	 * Invoice text for manual crypto (wallet in card_number, network in holder_name).
	 *
	 * @param object $card         Card.
	 * @param object $tx           Transaction.
	 * @param float  $amount_toman Amount.
	 * @param string $platform     telegram|bale.
	 * @return string
	 */
	private static function format_crypto_invoice_text( $card, $tx, $amount_toman, $platform = 'telegram' ) {
		$addr   = trim( (string) $card->card_number );
		$net    = trim( (string) $card->holder_name );
		$note   = trim( (string) ( $card->note ?? '' ) );
		$is_tg  = 'telegram' === $platform;
		$amt    = SimpleVPBot_Bot_Persian_Text::format_toman_fa( (float) $amount_toman );
		$txid_f = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) (int) $tx->id );
		$brand = class_exists( 'SimpleVPBot_Bot_Context' ) ? trim( (string) SimpleVPBot_Bot_Context::active_brand_name() ) : '';
		if ( $is_tg ) {
			$t  = "₿ پرداخت کریپتو\n";
			if ( '' !== $brand ) {
				$t .= '🏷 برند: ' . esc_html( $brand ) . "\n";
			}
			$t .= "➖➖➖➖➖➖➖➖\n";
			$t .= '🌐 شبکه / نوع: ' . esc_html( $net ) . "\n";
			$t .= "📍 آدرس ولت:\n<code>" . esc_html( $addr ) . "</code>\n";
			$t .= '💵 مبلغ: ' . esc_html( $amt ) . " تومان\n";
			$t .= '🆔 شناسه سفارش: ' . esc_html( $txid_f ) . "\n";
			$t .= "⚠️ حتماً شبکه را درست انتخاب کن.\n";
		} else {
			$t  = "₿ پرداخت کریپتو\n";
			if ( '' !== $brand ) {
				$t .= '🏷 برند: ' . $brand . "\n";
			}
			$t .= "➖➖➖➖➖➖➖➖\n";
			$t .= '🌐 شبکه / نوع: ' . $net . "\n";
			$t .= "📍 آدرس ولت:\n" . $addr . "\n";
			$t .= '💵 مبلغ: ' . $amt . " تومان\n";
			$t .= '🆔 شناسه سفارش: ' . $txid_f . "\n";
			$t .= "⚠️ حتماً شبکه را درست انتخاب کن.\n";
		}
		if ( $note !== '' ) {
			$t .= '📝 راهنما / ممو: ' . ( $is_tg ? esc_html( $note ) : $note ) . "\n";
		}
		return $t;
	}

	private static function format_purchase_invoice_text( $card, $tx, $amount_toman, $platform = 'telegram' ) {
		if ( SimpleVPBot_Model_Card::is_crypto_manual( $card ) ) {
			return self::format_crypto_invoice_text( $card, $tx, $amount_toman, $platform );
		}
		$pan_digits = preg_replace( '/\D+/', '', (string) $card->card_number );
		$holder     = (string) $card->holder_name;
		$note       = trim( (string) ( $card->note ?? '' ) );
		$is_tg      = 'telegram' === $platform;

		$amt_fa = SimpleVPBot_Bot_Persian_Text::format_toman_fa( (float) $amount_toman );
		$txid_f = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) (int) $tx->id );
		$brand = class_exists( 'SimpleVPBot_Bot_Context' ) ? trim( (string) SimpleVPBot_Bot_Context::active_brand_name() ) : '';
		if ( $is_tg ) {
			$t  = "🧾 فاکتور پرداخت\n";
			if ( '' !== $brand ) {
				$t .= '🏷 برند: ' . esc_html( $brand ) . "\n";
			}
			$t .= "➖➖➖➖➖➖➖➖\n";
			$t .= "💳 شماره کارت:\n";
			$t .= '<code>' . esc_html( $pan_digits ) . "</code>\n";
			$t .= '👤 صاحب: ' . esc_html( $holder ) . "\n";
			$t .= '💵 مبلغ: ' . esc_html( $amt_fa ) . " تومان\n";
			$t .= '🆔 تراکنش: ' . esc_html( $txid_f ) . "\n";
		} else {
			$t  = "🧾 فاکتور پرداخت\n";
			if ( '' !== $brand ) {
				$t .= '🏷 برند: ' . $brand . "\n";
			}
			$t .= "➖➖➖➖➖➖➖➖\n";
			$t .= "💳 شماره کارت:\n";
			$t .= $pan_digits . "\n";
			$t .= '👤 صاحب: ' . $holder . "\n";
			$t .= '💵 مبلغ: ' . $amt_fa . " تومان\n";
			$t .= '🆔 تراکنش: ' . $txid_f . "\n";
		}
		if ( $note !== '' ) {
			$t .= '📝 یادداشت: ' . ( $is_tg ? esc_html( $note ) : $note ) . "\n";
		}
		return $t;
	}
}
