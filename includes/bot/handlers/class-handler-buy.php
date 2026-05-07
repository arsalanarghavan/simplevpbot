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
	public static function checkout_message_for_tx( $tx, $title_line = '' ) {
		if ( ! $tx ) {
			return '';
		}
		$meta   = json_decode( (string) $tx->meta_json, true );
		$meta   = is_array( $meta ) ? $meta : array();
		$tid    = (int) $tx->id;
		$amount = (float) $tx->amount;
		$lines  = array();
		if ( $title_line !== '' ) {
			$lines[] = $title_line;
		}
		$brand = class_exists( 'SimpleVPBot_Bot_Context' ) ? trim( (string) SimpleVPBot_Bot_Context::active_brand_name() ) : '';
		if ( '' !== $brand ) {
			$lines[] = '🧾 سفارش ' . $brand . ' #' . SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $tid );
		} else {
			$lines[] = '🧾 سفارش #' . SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $tid );
		}
		if ( ! empty( $meta['discount_code'] ) ) {
			$sub  = isset( $meta['subtotal_toman'] ) ? (float) $meta['subtotal_toman'] : $amount;
			$disc = isset( $meta['discount_toman'] ) ? (float) $meta['discount_toman'] : 0.0;
			$lines[] = '🏷 کد: ' . (string) $meta['discount_code'] . ' · تخفیف: ' . SimpleVPBot_Bot_Persian_Text::format_toman_fa( $disc ) . ' تومان';
			$lines[] = 'قبل از تخفیف: ' . SimpleVPBot_Bot_Persian_Text::format_toman_fa( $sub ) . ' تومان';
		}
		$lines[] = '💵 قابل پرداخت: ' . SimpleVPBot_Bot_Persian_Text::format_toman_fa( $amount ) . ' تومان';
		$uid_bal = (int) ( $tx->user_id ?? 0 );
		if ( $uid_bal > 0 ) {
			$ub = SimpleVPBot_Model_User::find( $uid_bal );
			if ( $ub ) {
				$lines[] = '💼 موجودی کیف پول شما: ' . SimpleVPBot_Bot_Persian_Text::format_toman_fa( (float) $ub->balance ) . ' تومان';
			}
		}
		$lines[] = '';
		$lines[] = 'روش پرداخت را انتخاب کنید:';
		return implode( "\n", $lines );
	}

	/**
	 * Inline keyboard: discount row + payment methods.
	 *
	 * @param string $platform telegram|bale.
	 * @param int    $tid      Transaction id.
	 * @return array<string, mixed>
	 */
	public static function checkout_reply_markup( $platform, $tid ) {
		$cards            = SimpleVPBot_Model_Card::active_for_transaction( (int) $tid );
		$wallet_tok       = (string) SimpleVPBot_Settings::get( 'bale_wallet_provider_token', '' );
		$show_bale_wallet = ( 'bale' === $platform && $wallet_tok !== '' );
		$show_site_wallet = false;
		$tx_chk           = SimpleVPBot_Model_Transaction::find( (int) $tid );
		if ( $tx_chk && 'pending' === (string) $tx_chk->status && 'purchase' === (string) $tx_chk->type ) {
			$need = round( (float) $tx_chk->amount, 2 );
			if ( $need > 0 ) {
				$u_chk = SimpleVPBot_Model_User::find( (int) $tx_chk->user_id );
				if ( $u_chk && round( (float) $u_chk->balance, 2 ) >= $need ) {
					$show_site_wallet = true;
				}
			}
		}
		$pay              = SimpleVPBot_Keyboards::inline_payment_method( $cards, (int) $tid, $show_bale_wallet, $show_site_wallet );
		$rows             = isset( $pay['inline_keyboard'] ) && is_array( $pay['inline_keyboard'] ) ? $pay['inline_keyboard'] : array();
		array_unshift(
			$rows,
			array(
				array(
					'text'          => SimpleVPBot_Keyboards::glass_button_text( '🏷 کد تخفیف' ),
					'callback_data' => 'buy:dc:' . (int) $tid,
				),
				array(
					'text'          => SimpleVPBot_Keyboards::glass_button_text( '↩️ حذف تخفیف' ),
					'callback_data' => 'buy:dd:' . (int) $tid,
				),
			)
		);
		return array( 'inline_keyboard' => $rows );
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
	 * @return int Transaction id or 0 on failure.
	 */
	public static function send_purchase_checkout( $platform, $chat_id, $user_id, $amount, array $meta, $initiator_svp_user_id = null ) {
		$svc_id = isset( $meta['service_id'] ) ? (int) $meta['service_id'] : 0;
		$tid    = SimpleVPBot_Model_Transaction::insert(
			array(
				'user_id'    => (int) $user_id,
				'service_id' => $svc_id > 0 ? $svc_id : null,
				'amount'     => round( (float) $amount, 2 ),
				'type'       => 'purchase',
				'status'     => 'pending',
				'meta_json'  => wp_json_encode( $meta ),
			)
		);
		if ( ! $tid ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, (int) $chat_id, '⛔ ثبت سفارش ناموفق بود.' );
			return 0;
		}
		$initiator = null !== $initiator_svp_user_id ? (int) $initiator_svp_user_id : (int) $user_id;
		$buyer     = SimpleVPBot_Model_User::find( (int) $user_id );
		$is_reseller_ctx = class_exists( 'SimpleVPBot_Bot_Context' ) && SimpleVPBot_Bot_Context::is_reseller_bot();
		if ( ! $is_reseller_ctx && $buyer && (int) $initiator === (int) $user_id && SimpleVPBot_Router::is_svp_user_bot_admin( $buyer ) ) {
			$ful = SimpleVPBot_Receipt_Processor::fulfill_purchase_by_transaction( (int) $tid, 'admin_self_checkout' );
			if ( ! empty( $ful['ok'] ) ) {
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					(int) $chat_id,
					'✅ به‌عنوان مدیر، این خرید برای خودتان بدون پرداخت ثبت و اعمال شد.'
				);
				return (int) $tid;
			}
		}
		$cards = SimpleVPBot_Model_Card::active_for_transaction( (int) $tid );
		if ( empty( $cards ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, (int) $chat_id, '⛔ کارتی ثبت نشده. ادمین را مطلع کنید.' );
			return 0;
		}
		$tx_row = SimpleVPBot_Model_Transaction::find( (int) $tid );
		$text   = self::checkout_message_for_tx( $tx_row, '' );
		$markup = self::checkout_reply_markup( $platform, (int) $tid );
		SimpleVPBot_Bot_Runtime::send_message(
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
		if ( ! $tx || 'pending' !== (string) $tx->status || 'purchase' !== (string) $tx->type ) {
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
		SimpleVPBot_Receipt_Processor::fulfill_purchase_by_transaction( (int) $tx->id, 'bale_wallet' );
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
		$act      = $parts[1] ?? '';

		if ( 'dc' === $act && isset( $parts[2] ) ) {
			$tid = (int) $parts[2];
			$tx  = SimpleVPBot_Model_Transaction::find( $tid );
			if ( ! $tx
				|| (int) $tx->user_id !== (int) $user->id
				|| 'pending' !== (string) $tx->status
				|| 'purchase' !== (string) $tx->type ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ سفارش نامعتبر است.' );
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
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '🏷 کد تخفیف را بفرستید. برای انصراف «لغو» بفرستید یا از منوی اصلی یک گزینه را انتخاب کنید.' );
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
				$text   = self::checkout_message_for_tx( $tx2, '' );
				$markup = self::checkout_reply_markup( $platform, $tid );
				SimpleVPBot_Bot_Runtime::edit_message_text( $platform, $chat_id, $msg_id, $text, array( 'reply_markup' => $markup ) );
			}
			return;
		}

		if ( 'pn' === $act && isset( $parts[2] ) ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				'ℹ️ این دکمه دیگر استفاده نمی‌شود. از 🛒 خرید سرویس دوباره شروع کنید؛ همهٔ دسته‌ها در یک لیست نمایش داده می‌شوند.'
			);
			self::send_category_picker( $platform, $chat_id );
			return;
		}

		if ( 'g' === $act && isset( $parts[2] ) ) {
			$cid     = (int) $parts[2];
			$cat_row = SimpleVPBot_Model_Plan_Category::find( $cid );
			if ( ! $cat_row || ! (int) $cat_row->active ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ این دسته در دسترس نیست یا غیرفعال شده است.' );
				return;
			}
			$panel_id = max( 1, (int) ( $cat_row->panel_id ?? 1 ) );
			$cat      = (string) $cat_row->slug;
			$plans    = SimpleVPBot_Model_Plan::by_category( $cat, $panel_id );
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
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ این دسته در دسترس نیست یا غیرفعال شده است.' );
				return;
			}
			$plans = SimpleVPBot_Model_Plan::by_category( $cat, $panel_id );
			self::send_plan_picker_for_plans( $platform, $chat_id, (string) $cat_row->label, $plans );
			return;
		}
		if ( 'p' === $act && isset( $parts[2] ) ) {
			$pid  = (int) $parts[2];
			$plan = SimpleVPBot_Model_Plan::find( $pid );
			if ( ! $plan || ! (int) $plan->active ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ این پلن در دسترس نیست یا غیرفعال شده است.' );
				return;
			}
			if ( SimpleVPBot_Model_Plan::is_per_gb( $plan ) ) {
				$min = (int) ( $plan->traffic_gb_min ?? 0 );
				$max = (int) ( $plan->traffic_gb_max ?? 0 );
				if ( $min < 1 || $max < 1 || $min > $max || (float) ( $plan->price_per_gb ?? 0 ) <= 0 ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ پلن «' . (string) $plan->name . '» اشتباه پیکربندی شده (قیمت به ازای هر گیگابایت یا محدودهٔ حجم). با ادمین تماس بگیرید.' );
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
				SimpleVPBot_Texts::format(
					"📦 {name}\n💰 قیمت: {price} تومان\n⏳ مدت: {d} روز\n📊 حجم: {t} گیگابایت\n➖➖➖➖➖➖➖➖\nتایید می‌کنید؟",
					array(
						'name'  => (string) $plan->name,
						'price' => SimpleVPBot_Bot_Persian_Text::format_toman_fa( (float) $plan->price ),
						'd'     => SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) (int) $plan->duration_days ),
						't'     => SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) (int) $plan->traffic_gb ),
					)
				),
				array(
					'reply_markup' => array(
						'inline_keyboard' => array(
							array(
								array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '✅ تایید خرید' ), 'callback_data' => 'buy:cf:' . $pid ),
								array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '❌ انصراف' ), 'callback_data' => 'buy:x:0' ),
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
			if ( ! $plan || ! (int) $plan->active ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ این پلن در دسترس نیست یا غیرفعال شده است.' );
				return;
			}
			$vol_chosen = isset( $parts[3] ) ? (int) $parts[3] : null;
			$amount     = (float) $plan->price;
			$meta       = array( 'plan_id' => $pid );
			if ( SimpleVPBot_Model_Plan::is_per_gb( $plan ) ) {
				if ( null === $vol_chosen || $vol_chosen < 1 || ! SimpleVPBot_Model_Plan::is_volume_in_range( $plan, $vol_chosen ) ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ حجم انتخاب‌شده معتبر نیست.' );
					return;
				}
				$amount = SimpleVPBot_Model_Plan::total_price( $plan, $vol_chosen );
				$meta['volume_gb'] = $vol_chosen;
			} elseif ( null !== $vol_chosen && $vol_chosen > 0 ) {
				return;
			}
			self::send_purchase_checkout( $platform, $chat_id, (int) $user->id, $amount, $meta );
			return;
		}
		if ( 'pm' === $act && isset( $parts[2], $parts[3] ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			$tx_id   = (int) $parts[2];
			$card_id = (int) $parts[3];
			$tx      = SimpleVPBot_Model_Transaction::find( $tx_id );
			$card    = SimpleVPBot_Model_Card::find( $card_id );
			if ( ! $tx
				|| (int) $tx->user_id !== (int) $user->id
				|| 'pending' !== (string) $tx->status
				|| 'purchase' !== (string) $tx->type
				|| ! $card
				|| ! (int) $card->active ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ این بخش خرید منقضی یا نامعتبر است.' );
				return;
			}
			self::send_purchase_step_invoice(
				$ctx,
				$tx,
				$card,
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
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ سفارش خرید نامعتبر است.' );
				return;
			}
			$need = round( (float) $tx->amount, 2 );
			if ( $need <= 0 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ مبلغ سفارش نامعتبر است.' );
				return;
			}
			if ( ! SimpleVPBot_Model_User::decrement_balance_if_sufficient( (int) $user->id, $need ) ) {
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					'⛔ موجودی کیف پول شما برای این پرداخت کافی نیست. ابتدا حساب را شارژ کنید یا روش دیگری انتخاب کنید.'
				);
				return;
			}
			$ful = SimpleVPBot_Receipt_Processor::fulfill_purchase_by_transaction( (int) $tx->id, 'site_wallet' );
			if ( empty( $ful['ok'] ) ) {
				SimpleVPBot_Model_User::increment_balance( (int) $user->id, $need );
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					'⛔ تکمیل سفارش ناموفق بود. مبلغ به کیف پول شما بازگردانده شد. با پشتیبانی تماس بگیرید.'
				);
				return;
			}
			return;
		}
		if ( 'bw' === $act && isset( $parts[2] ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			$tx_id = (int) $parts[2];
			$tx    = SimpleVPBot_Model_Transaction::find( $tx_id );
			if ( 'bale' !== $platform ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ پرداخت کیف پول فقط در بله در دسترس است.' );
				return;
			}
			$ptok = (string) SimpleVPBot_Settings::get( 'bale_wallet_provider_token', '' );
			if ( $ptok === '' ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ پرداخت کیف پول در حال حاضر غیرفعال است.' );
				return;
			}
			if ( ! $tx || (int) $tx->user_id !== (int) $user->id || 'pending' !== (string) $tx->status || 'purchase' !== (string) $tx->type ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ سفارش خرید نامعتبر است.' );
				return;
			}
			$meta  = json_decode( (string) $tx->meta_json, true );
			$meta  = is_array( $meta ) ? $meta : array();
			$pid   = ! empty( $meta['plan_id'] ) ? (int) $meta['plan_id'] : 0;
			$plan  = $pid ? SimpleVPBot_Model_Plan::find( $pid ) : null;
			if ( ! $plan || ! (int) $plan->active ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ پلن این سفارش در دسترس نیست.' );
				return;
			}
			$label = mb_substr( (string) $plan->name, 0, 32 );
			$rial  = (int) round( (float) $tx->amount, 0 ) * 10;
			$pay   = self::bale_wallet_build_payload( (int) $tx->id, (int) $user->id );
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
			$res   = SimpleVPBot_Bot_Runtime::send_invoice(
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
					'start_parameter' => 'vp_' . (int) $tx->id,
				)
			);
			if ( ! $res || empty( $res['ok'] ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ ارسال فاکتور ممکن نشد. کمی بعد دوباره تلاش کنید.' );
			}
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
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '📸 لطفاً تصویر رسید کارت‌به‌کارت را همینجا ارسال کنید.' );
			return;
		}
		if ( 'x' === $act ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '❌ لغو شد.' );
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
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ جلسه نامعتبر بود.' );
				return;
			}
			if ( '' === $raw || 'لغو' === $raw || 'انصراف' === $raw ) {
				SimpleVPBot_State::clear( (int) $user->id );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '❌ ورود کد تخفیف لغو شد.' );
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
					'bad_status'         => 'این سفارش دیگر قابل تخفیف نیست.',
				);
				$msg = isset( $map[ $reason ] ) ? $map[ $reason ] : 'کد تایید نشد (' . $reason . ').';
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ ' . $msg );
				return;
			}
			SimpleVPBot_State::clear( (int) $user->id );
			$tx2    = SimpleVPBot_Model_Transaction::find( $tid );
			$text   = self::checkout_message_for_tx( $tx2, '' );
			$markup = self::checkout_reply_markup( $platform, $tid );
			$cmid   = (int) ( $sd['checkout_msg_id'] ?? 0 );
			$cchat  = (int) ( $sd['checkout_chat_id'] ?? 0 );
			$plat   = isset( $sd['platform'] ) ? (string) $sd['platform'] : $platform;
			if ( $cmid > 0 && $cchat > 0 ) {
				SimpleVPBot_Bot_Runtime::edit_message_text( $plat, $cchat, $cmid, $text, array( 'reply_markup' => $markup ) );
			}
			$disc = isset( $res['discount_toman'] ) ? (float) $res['discount_toman'] : 0.0;
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '✅ کد تایید شد. تخفیف: ' . SimpleVPBot_Bot_Persian_Text::format_toman_fa( $disc ) . ' تومان.' );
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
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, 'ℹ️ از دکمه‌های منو استفاده کنید.' );
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
		if ( ! $plan || ! (int) $plan->active || ! SimpleVPBot_Model_Plan::is_per_gb( $plan ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ جلسه خرید نامعتبر است. دوباره از منو شروع کنید.' );
			return;
		}
		if ( ! preg_match( '/^\d+$/u', $raw ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ فقط یک عدد صحیح بفرستید (مثلا 20).' );
			return;
		}
		$gb = (int) $raw;
		if ( $gb < 1 || ! SimpleVPBot_Model_Plan::is_volume_in_range( $plan, $gb ) ) {
			$min = (int) ( $plan->traffic_gb_min ?? 0 );
			$max = (int) ( $plan->traffic_gb_max ?? 0 );
			$min_fa = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $min );
			$max_fa = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $max );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, "⛔ حجم باید بین {$min_fa} و {$max_fa} گیگابایت باشد." );
			return;
		}
		$amount = SimpleVPBot_Model_Plan::total_price( $plan, $gb );
		SimpleVPBot_State::clear( (int) $user->id );
		$cb = 'buy:cf:' . (int) $plan->id . ':' . $gb;
		if ( strlen( $cb ) > 64 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ خطای داخلی: شناسه بیش از حد بزرگ است. با ادمین تماس بگیرید.' );
			return;
		}
		$gb_fa = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $gb );
		$d_fa  = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) (int) $plan->duration_days );
		$text  = "📦 " . (string) $plan->name . "\n";
		$text .= '📊 حجم: ' . $gb_fa . " گیگابایت\n";
		$text .= '⏳ مدت: ' . $d_fa . " روز\n";
		$text .= '💰 مبلغ قابل پرداخت: ' . SimpleVPBot_Bot_Persian_Text::format_toman_fa( $amount ) . " تومان\n";
		$text .= "\n➖➖➖➖➖➖➖➖\nتایید می‌کنید؟";
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$text,
			array(
				'reply_markup' => array(
					'inline_keyboard' => array(
						array(
							array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '✅ تایید و پرداخت' ), 'callback_data' => $cb ),
							array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '❌ انصراف' ), 'callback_data' => 'buy:x:0' ),
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
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ ابتدا خرید را از منو شروع کنید.' );
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
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '✅ رسید دریافت شد. پس از تایید ادمین به شما اطلاع داده می‌شود.' );

		$admin_msgs = array();
		$body       = SimpleVPBot_Bot_Admin_User_Caption::receipt_new_caption( $user, $tx, (int) $rid );
		$photo_args = array( 'reply_markup' => SimpleVPBot_Keyboards::inline_receipt( (int) $rid ) );
		$admin_ids  = self::admin_ids_for_current_context();
		$tg_ids     = $admin_ids['telegram'];
		$bl_ids     = $admin_ids['bale'];
		$tg_tok     = (string) SimpleVPBot_Bot_Runtime::bot_token_for_current_context( 'telegram' );
		$bl_tok     = (string) SimpleVPBot_Bot_Runtime::bot_token_for_current_context( 'bale' );

		$local_path = self::download_receipt_to_temp( $platform, $file_id );
		if ( '' === $local_path || ! is_readable( $local_path ) ) {
			usleep( 400000 );
			$local_path = self::download_receipt_to_temp( $platform, $file_id );
		}
		$tg_file_id = 'telegram' === $platform ? $file_id : '';
		$bl_file_id = 'bale' === $platform ? $file_id : '';

		if ( $tg_tok ) {
			foreach ( $tg_ids as $adm ) {
				$adm = (int) $adm;
				$r   = self::send_admin_receipt_photo_retry( 'telegram', $adm, $tg_file_id, '', $local_path, $body, $photo_args, (int) $rid );
				if ( is_array( $r ) && ! empty( $r['result']['message_id'] ) ) {
					$admin_msgs[] = array( 'platform' => 'telegram', 'chat_id' => $adm, 'message_id' => (int) $r['result']['message_id'] );
				} else {
					SimpleVPBot_Bot_Runtime::send_message(
						'telegram',
						$adm,
						'⛔ رسید #' . (int) $rid . " — ارسال عکس ناموفق بود؛ در پنل «رسیدها» بازبینی کنید.\n" . wp_strip_all_tags( $body ),
						array( 'reply_markup' => SimpleVPBot_Keyboards::inline_receipt( (int) $rid ) )
					);
				}
				usleep( 200000 );
			}
		}
		if ( $bl_tok ) {
			foreach ( $bl_ids as $adm ) {
				$adm = (int) $adm;
				$r   = self::send_admin_receipt_photo_retry( 'bale', $adm, '', $bl_file_id, $local_path, $body, $photo_args, (int) $rid );
				if ( is_array( $r ) && ! empty( $r['result']['message_id'] ) ) {
					$admin_msgs[] = array( 'platform' => 'bale', 'chat_id' => $adm, 'message_id' => (int) $r['result']['message_id'] );
				} else {
					SimpleVPBot_Bot_Runtime::send_message(
						'bale',
						$adm,
						'⛔ رسید #' . (int) $rid . " — ارسال عکس ناموفق بود؛ در پنل «رسیدها» بازبینی کنید.\n" . wp_strip_all_tags( $body ),
						array( 'reply_markup' => SimpleVPBot_Keyboards::inline_receipt( (int) $rid ) )
					);
				}
				usleep( 200000 );
			}
		}
		if ( $local_path && file_exists( $local_path ) ) {
			@unlink( $local_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		SimpleVPBot_Model_Receipt::update( $rid, array( 'admin_messages_json' => wp_json_encode( $admin_msgs ) ) );
	}

	/**
	 * Send receipt image to one admin with retries (avoids treating plain text as the receipt).
	 *
	 * @param string               $platform   telegram|bale (uploader platform; photo API target matches).
	 * @param int                  $admin_chat Destination chat id.
	 * @param string               $tg_file_id Telegram file_id when receipt came from Telegram.
	 * @param string               $bl_file_id Bale file_id when receipt came from Bale.
	 * @param string               $local_path Temp path from download_receipt_to_temp or ''.
	 * @param string               $body       Caption.
	 * @param array<string, mixed> $photo_args e.g. reply_markup.
	 * @param int                  $rid        Receipt id for logs.
	 * @return array<string, mixed>|null Telegram/Bale API response with result.message_id on success.
	 */
	private static function send_admin_receipt_photo_retry( $platform, $admin_chat, $tg_file_id, $bl_file_id, $local_path, $body, array $photo_args, $rid ) {
		$admin_chat = (int) $admin_chat;
		$rid        = (int) $rid;
		$attempts   = 8;
		$delay_us   = 350000;
		for ( $i = 0; $i < $attempts; $i++ ) {
			$r = null;
			if ( 'telegram' === $platform ) {
				if ( '' !== (string) $tg_file_id ) {
					$r = SimpleVPBot_Bot_Runtime::send_photo( 'telegram', $admin_chat, (string) $tg_file_id, $body, $photo_args );
				}
				if ( ( ! is_array( $r ) || empty( $r['result']['message_id'] ) ) && $local_path && is_readable( $local_path ) ) {
					$r = SimpleVPBot_Bot_Runtime::send_photo_file( 'telegram', $admin_chat, $local_path, $body, $photo_args );
				}
			} else {
				if ( '' !== (string) $bl_file_id ) {
					$r = SimpleVPBot_Bot_Runtime::send_photo( 'bale', $admin_chat, (string) $bl_file_id, $body, $photo_args );
				}
				if ( ( ! is_array( $r ) || empty( $r['result']['message_id'] ) ) && $local_path && is_readable( $local_path ) ) {
					$r = SimpleVPBot_Bot_Runtime::send_photo_file( 'bale', $admin_chat, $local_path, $body, $photo_args );
				}
			}
			if ( is_array( $r ) && ! empty( $r['result']['message_id'] ) ) {
				return $r;
			}
			usleep( $delay_us );
		}
		SimpleVPBot_Logger::error(
			'receipt admin photo delivery failed after retries',
			array( 'receipt_id' => $rid, 'platform' => (string) $platform, 'admin_chat' => $admin_chat )
		);
		return null;
	}

	/**
	 * Download uploaded receipt file to a temp path; empty string on failure.
	 *
	 * @param string $platform telegram|bale.
	 * @param string $file_id  File id on the uploader's platform.
	 * @return string Local path or ''.
	 */
	private static function download_receipt_to_temp( $platform, $file_id ) {
		$file_id = (string) $file_id;
		if ( '' === $file_id ) {
			return '';
		}
		$client = SimpleVPBot_Bot_Runtime::client( $platform );
		if ( ! $client ) {
			return '';
		}
		$gf   = $client->get_file( array( 'file_id' => $file_id ) );
		$path = ( is_array( $gf ) && ! empty( $gf['result']['file_path'] ) ) ? (string) $gf['result']['file_path'] : '';
		if ( '' === $path ) {
			return '';
		}
		$tok = (string) SimpleVPBot_Bot_Runtime::bot_token_for_current_context( $platform );
		if ( '' === $tok ) {
			return '';
		}
		if ( 'bale' === $platform ) {
			$url = 'https://tapi.bale.ai/file/bot' . rawurlencode( $tok ) . '/' . $path;
		} else {
			$url = 'https://api.telegram.org/file/bot' . rawurlencode( $tok ) . '/' . $path;
		}
		$resp = wp_remote_get( $url, array( 'timeout' => 45, 'redirection' => 3, 'sslverify' => false ) );
		if ( is_wp_error( $resp ) || (int) wp_remote_retrieve_response_code( $resp ) !== 200 ) {
			return '';
		}
		$body = (string) wp_remote_retrieve_body( $resp );
		if ( '' === $body ) {
			return '';
		}
		$tmp = function_exists( 'wp_tempnam' ) ? wp_tempnam( 'svp_receipt' ) : @tempnam( sys_get_temp_dir(), 'svp_receipt' ); // phpcs:ignore
		if ( ! $tmp ) {
			return '';
		}
		$bytes = file_put_contents( $tmp, $body ); // phpcs:ignore
		if ( false === $bytes || $bytes < 1 ) {
			@unlink( $tmp ); // phpcs:ignore
			return '';
		}
		$ext = '.jpg';
		$lp  = strtolower( (string) $path );
		if ( false !== strpos( $lp, '.png' ) ) {
			$ext = '.png';
		} elseif ( false !== strpos( $lp, '.webp' ) ) {
			$ext = '.webp';
		} elseif ( false !== strpos( $lp, '.jpeg' ) || false !== strpos( $lp, '.jpg' ) ) {
			$ext = '.jpg';
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
		$tg_ids = array_map( 'intval', (array) SimpleVPBot_Settings::get( 'admin_telegram_ids', array() ) );
		$bl_ids = array_map( 'intval', (array) SimpleVPBot_Settings::get( 'admin_bale_ids', array() ) );
		if ( class_exists( 'SimpleVPBot_Bot_Context' ) && SimpleVPBot_Bot_Context::is_reseller_bot() ) {
			$reseller = SimpleVPBot_Model_User::find( (int) SimpleVPBot_Bot_Context::reseller_svp_user_id() );
			if ( ! $reseller ) {
				return array( 'telegram' => array(), 'bale' => array() );
			}
			$rtg = (int) ( $reseller->tg_user_id ?? 0 );
			$rbl = (int) ( $reseller->bale_user_id ?? 0 );
			$tg_ids = $rtg > 0 ? array( $rtg ) : array();
			$bl_ids = $rbl > 0 ? array( $rbl ) : array();
		}
		return array(
			'telegram' => array_values( array_filter( $tg_ids ) ),
			'bale'     => array_values( array_filter( $bl_ids ) ),
		);
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
		$slug     = (string) $cat_row->slug;
		foreach ( SimpleVPBot_Model_Plan::by_category( $slug, $panel_id ) as $p ) {
			if ( $p && (int) $p->active ) {
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
				$active[] = $p;
			}
		}
		if ( empty( $active ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ پلنی در این دسته نیست.' );
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
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ پلنی در این دسته نیست.' );
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
		$cats = SimpleVPBot_Model_Plan_Category::active_ordered();
		$list = array();
		foreach ( $cats as $c ) {
			if ( self::category_has_buyable_plans( $c ) ) {
				$list[] = $c;
			}
		}
		if ( empty( $list ) ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				'⛔ دستهٔ فعالی با پلن برای خرید وجود ندارد. بعداً مراجعه کنید یا با پشتیبانی تماس بگیرید.'
			);
			return;
		}
		$lines = self::inline_keyboard_for_category_rows( $list );
		if ( empty( $lines ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ دستهٔ معتبری برای نمایش نیست.' );
			return;
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			'🛒 دستهٔ سرویس را انتخاب کنید:',
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
		$cats     = SimpleVPBot_Model_Plan_Category::active_ordered_for_panel( $panel_id );
		$list     = array();
		foreach ( $cats as $c ) {
			if ( self::category_has_buyable_plans( $c ) ) {
				$list[] = $c;
			}
		}
		if ( empty( $list ) ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				'⛔ برای این پنل دستهٔ فعالی با پلن برای خرید وجود ندارد. لطفاً بعداً مراجعه کنید یا با پشتیبانی تماس بگیرید.'
			);
			return;
		}
		$lines = self::inline_keyboard_for_category_rows( $list );
		if ( empty( $lines ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ دستهٔ معتبری برای نمایش نیست.' );
			return;
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			'🛒 دستهٔ سرویس را انتخاب کنید:',
			array( 'reply_markup' => array( 'inline_keyboard' => $lines ) )
		);
	}

	/**
	 * Step B: full invoice, copy row, then receipt state (same as legacy buy:cd follow-up).
	 *
	 * @param array<string, mixed> $ctx  Context.
	 * @param object                 $tx   Transaction.
	 * @param object                 $card Card.
	 */
	private static function send_purchase_step_invoice( array $ctx, $tx, $card ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = $ctx['user'];
		$amount   = (float) $tx->amount;
		if ( SimpleVPBot_Model_Card::is_crypto_auto( $card ) ) {
			$cr = SimpleVPBot_Crypto_Payment::create_nowpayments_invoice( $tx, $card, $platform );
			if ( empty( $cr['ok'] ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ ' . (string) ( $cr['message'] ?? 'خطا در ساخت پرداخت.' ) );
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
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				'⏳ بعد از تأیید پرداخت در NOWPayments، سفارش خودکار تکمیل می‌شود. اگر چیزی گیر کرد با پشتیبانی تماس بگیر.'
			);
			SimpleVPBot_State::clear( (int) $user->id );
			return;
		}
		$text   = self::format_purchase_invoice_text( $card, $tx, $amount, $platform );
		$markup = SimpleVPBot_Keyboards::inline_invoice_actions( $card, $amount, $platform, '' );
		$extra  = array( 'reply_markup' => $markup );
		if ( 'telegram' === $platform ) {
			$extra['parse_mode'] = 'HTML';
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$text,
			$extra
		);
		SimpleVPBot_State::set(
			(int) $user->id,
			'receipt_upload',
			array(
				'transaction_id' => (int) $tx->id,
				'card_id'        => (int) $card->id,
			)
		);
		$receipt_hint = SimpleVPBot_Model_Card::is_crypto_manual( $card )
			? '📸 بعد از واریز، تصویر تراکنش یا اسکرین‌شات را همینجا بفرست (txid یا رسید صرافی).'
			: '📸 لطفاً تصویر رسید کارت‌به‌کارت را همینجا ارسال کنید.';
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $receipt_hint );
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
