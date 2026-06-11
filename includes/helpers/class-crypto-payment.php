<?php
/**
 * NOWPayments: create payment + IPN webhook.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Crypto_Payment
 */
class SimpleVPBot_Crypto_Payment {

	/**
	 * REST routes.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register crypto IPN route (path secret + NOWPayments HMAC body).
	 */
	public static function register_routes() {
		register_rest_route(
			'simplevpbot/v1',
			'/crypto-ipn/(?P<path_secret>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_ipn' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Public IPN URL for NOWPayments dashboard.
	 *
	 * @return string
	 */
	public static function ipn_callback_url() {
		$path = (string) SimpleVPBot_Settings::get( 'crypto_ipn_path_secret', '' );
		if ( '' === $path ) {
			return '';
		}
		return SimpleVPBot_Settings::public_site_url() . '/wp-json/simplevpbot/v1/crypto-ipn/' . rawurlencode( $path );
	}

	/**
	 * Create NOWPayments invoice; returns message parts for the bot.
	 *
	 * @param object $tx        Transaction row.
	 * @param object $card      Card row (crypto_auto).
	 * @param string $platform  telegram|bale.
	 * @return array{ok:bool, text?:string, reply_markup?:array<string, mixed>, message?:string}
	 */
	public static function create_nowpayments_invoice( $tx, $card, $platform ) {
		$api = trim( (string) SimpleVPBot_Settings::get( 'crypto_nowpayments_api_key', '' ) );
		if ( '' === $api ) {
			return array( 'ok' => false, 'message' => 'API key خالی است.' );
		}
		$ipn_url = self::ipn_callback_url();
		if ( '' === $ipn_url ) {
			return array( 'ok' => false, 'message' => 'رمز مسیر IPN خالی است؛ یک‌بار ذخیرهٔ تنظیمات عمومی/کارت‌ها را بزنید.' );
		}
		$rate = (float) SimpleVPBot_Settings::get( 'crypto_toman_per_usd', 50000.0 );
		if ( $rate < 1.0 ) {
			$rate = 50000.0;
		}
		$toman = (float) $tx->amount;
		$usd   = round( $toman / $rate, 2 );
		if ( $usd < 0.01 ) {
			$usd = max( 0.01, round( $toman / $rate, 4 ) );
		}
		$pay_currency = sanitize_key( (string) SimpleVPBot_Settings::get( 'crypto_nowpayments_pay_currency', 'usdttrc20' ) );
		if ( '' === $pay_currency ) {
			$pay_currency = 'usdttrc20';
		}
		$body = array(
			'price_amount'      => $usd,
			'price_currency'    => 'usd',
			'pay_currency'      => $pay_currency,
			'order_id'          => (string) (int) $tx->id,
			'order_description' => 'SimpleVPBot tx ' . (int) $tx->id,
			'ipn_callback_url'  => $ipn_url,
		);
		$timeout = class_exists( 'SimpleVPBot_Settings' ) ? SimpleVPBot_Settings::crypto_invoice_timeout_sec() : 12;
		$res     = wp_remote_post(
			'https://api.nowpayments.io/v1/payment',
			array(
				'timeout' => $timeout,
				'headers' => array(
					'x-api-key'    => $api,
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'message' => $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$raw  = (string) wp_remote_retrieve_body( $res );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return array( 'ok' => false, 'message' => 'پاسخ نامعتبر از NOWPayments.' );
		}
		if ( $code < 200 || $code >= 300 ) {
			$err = isset( $data['message'] ) ? (string) $data['message'] : $raw;
			return array( 'ok' => false, 'message' => $err );
		}
		$payment_id = isset( $data['payment_id'] ) ? (string) $data['payment_id'] : '';
		if ( $payment_id !== '' ) {
			$meta = json_decode( (string) $tx->meta_json, true );
			$meta = is_array( $meta ) ? $meta : array();
			$meta['nowpayments_payment_id'] = $payment_id;
			SimpleVPBot_Model_Transaction::update(
				(int) $tx->id,
				array( 'meta_json' => wp_json_encode( $meta ) )
			);
		}
		$pay_url = '';
		foreach ( array( 'invoice_url', 'pay_url' ) as $k ) {
			if ( ! empty( $data[ $k ] ) && is_string( $data[ $k ] ) ) {
				$pay_url = (string) $data[ $k ];
				break;
			}
		}
		$addr = isset( $data['pay_address'] ) ? (string) $data['pay_address'] : '';
		$amt  = isset( $data['pay_amount'] ) ? (string) $data['pay_amount'] : '';
		$cur  = isset( $data['pay_currency'] ) ? (string) $data['pay_currency'] : $pay_currency;
		$text = "₿ پرداخت کریپتو (NOWPayments)\n➖➖➖➖➖➖➖➖\n🆔 سفارش: " . (int) $tx->id . "\n💵 مبلغ سفارش: " . number_format( $toman ) . " تومان\n";
		if ( $pay_url !== '' ) {
			$text .= "\n➡️ لینک پرداخت را باز کنید و طبق راهنما پرداخت را تمام کنید.\n";
			$markup = array(
				'inline_keyboard' => array(
					array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '🔗 پرداخت در NOWPayments' ), 'url' => $pay_url ) ),
				),
			);
			return array( 'ok' => true, 'text' => $text, 'reply_markup' => $markup );
		}
		if ( $addr !== '' ) {
			$text .= "\n📍 آدرس ولت:\n" . $addr . "\n";
			if ( $amt !== '' ) {
				$text .= '🔢 مبلغ: ' . $amt . ' ' . $cur . "\n";
			}
			$text .= "\n⚠️ شبکه را اشتباه انتخاب نکنید.\n";
			$rows   = array();
			if ( 'telegram' === $platform ) {
				$rows[] = array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '📋 کپی آدرس' ), 'copy_text' => array( 'text' => $addr ) ) );
			}
			return array(
				'ok'           => true,
				'text'         => $text,
				'reply_markup' => array( 'inline_keyboard' => $rows ),
			);
		}
		return array( 'ok' => false, 'message' => 'لینک یا آدرس پرداخت در پاسخ نبود.' );
	}

	/**
	 * NOWPayments IPN callback.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_ipn( $request ) {
		$path = (string) $request['path_secret'];
		$want = (string) SimpleVPBot_Settings::get( 'crypto_ipn_path_secret', '' );
		if ( '' === $want || ! hash_equals( $want, $path ) ) {
			return new WP_REST_Response( array( 'error' => 'forbidden' ), 403 );
		}
		$raw = $request->get_body();
		if ( '' === $raw ) {
			return new WP_REST_Response( array( 'ok' => true, 'note' => 'empty' ), 200 );
		}
		$ipn_secret = trim( (string) SimpleVPBot_Settings::get( 'crypto_nowpayments_ipn_secret', '' ) );
		if ( '' === $ipn_secret ) {
			return new WP_REST_Response( array( 'error' => 'ipn_secret_required' ), 403 );
		}
		$sig = (string) $request->get_header( 'x-nowpayments-sig' );
		if ( '' === $sig ) {
			return new WP_REST_Response( array( 'error' => 'no_sig' ), 403 );
		}
		$calc = hash_hmac( 'sha512', $raw, $ipn_secret );
		if ( ! hash_equals( $calc, $sig ) ) {
			SimpleVPBot_Logger::error( 'crypto_ipn: bad signature' );
			return new WP_REST_Response( array( 'error' => 'bad_sig' ), 403 );
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return new WP_REST_Response( array( 'error' => 'bad_json' ), 400 );
		}
		$status = isset( $data['payment_status'] ) ? (string) $data['payment_status'] : '';
		if ( 'finished' !== $status && 'confirmed' !== $status ) {
			return new WP_REST_Response( array( 'ok' => true, 'ignored' => $status ), 200 );
		}
		$oid = isset( $data['order_id'] ) ? (string) $data['order_id'] : '';
		if ( ! preg_match( '/^\d+$/', $oid ) ) {
			return new WP_REST_Response( array( 'error' => 'bad_order' ), 400 );
		}
		$tx_id = (int) $oid;
		$tx    = SimpleVPBot_Model_Transaction::find( $tx_id );
		if ( ! $tx || 'purchase' !== (string) $tx->type ) {
			return new WP_REST_Response( array( 'error' => 'bad_tx' ), 400 );
		}
		if ( 'approved' === (string) $tx->status ) {
			return new WP_REST_Response( array( 'ok' => true, 'note' => 'already_approved' ), 200 );
		}
		$tx_meta = json_decode( (string) $tx->meta_json, true );
		if ( ! is_array( $tx_meta ) ) {
			$tx_meta = array();
		}
		$expected_pid = isset( $tx_meta['nowpayments_payment_id'] ) ? (string) $tx_meta['nowpayments_payment_id'] : '';
		$incoming_pid = isset( $data['payment_id'] ) ? (string) $data['payment_id'] : '';
		if ( '' !== $expected_pid && '' !== $incoming_pid && $expected_pid !== $incoming_pid ) {
			SimpleVPBot_Logger::error(
				'crypto_ipn payment_id mismatch',
				array(
					'tx_id'    => $tx_id,
					'expected' => $expected_pid,
					'incoming' => $incoming_pid,
				)
			);
			return new WP_REST_Response( array( 'error' => 'payment_id_mismatch' ), 409 );
		}
		self::schedule_crypto_fulfill( $tx_id );
		return new WP_REST_Response( array( 'ok' => true, 'queued' => true ), 200 );
	}

	/**
	 * Queue NOWPayments fulfill after HTTP response.
	 *
	 * @param int $tx_id Transaction id.
	 */
	private static function schedule_crypto_fulfill( $tx_id ) {
		$tx_id = (int) $tx_id;
		if ( $tx_id < 1 ) {
			return;
		}
		$work = static function () use ( $tx_id ) {
			self::run_deferred_crypto_fulfill( $tx_id );
		};
		if ( class_exists( 'SimpleVPBot_Deferred_Work' ) ) {
			SimpleVPBot_Deferred_Work::run_after_response_or_cron(
				$work,
				SimpleVPBot_Deferred_Work::CRYPTO_FULFILL_CRON_HOOK,
				array( $tx_id ),
				'crypto_fulfill'
			);
		} else {
			$work();
		}
	}

	/**
	 * Cron fallback for deferred crypto fulfill.
	 *
	 * @param int $tx_id Transaction id.
	 */
	public static function deferred_crypto_fulfill_cron( $tx_id ) {
		self::run_deferred_crypto_fulfill( (int) $tx_id );
	}

	/**
	 * Run purchase fulfill for a NOWPayments IPN (idempotent).
	 *
	 * @param int $tx_id Transaction id.
	 */
	const CRYPTO_FULFILL_MAX_ATTEMPTS = 3;

	/**
	 * @param int $tx_id Transaction id.
	 * @return string
	 */
	private static function crypto_fulfill_attempt_transient_key( $tx_id ) {
		return 'svp_crypto_fulfill_try_' . (int) $tx_id;
	}

	/**
	 * @param int $tx_id Transaction id.
	 * @return string
	 */
	private static function crypto_fulfill_notified_transient_key( $tx_id ) {
		return 'svp_crypto_fulfill_notified_' . (int) $tx_id;
	}

	/**
	 * @param string $reason Fulfill failure reason.
	 * @return bool
	 */
	private static function crypto_fulfill_reason_non_retryable( $reason ) {
		return in_array( (string) $reason, array( 'bad_tx', 'no_plan', 'no_plan_id' ), true );
	}

	/**
	 * Notify user once when crypto fulfill ultimately fails.
	 *
	 * @param object $tx     Transaction row.
	 * @param string $reason Failure reason.
	 */
	private static function notify_crypto_fulfill_failed( $tx, $reason ) {
		unset( $reason );
		$tx_id = (int) $tx->id;
		if ( get_transient( self::crypto_fulfill_notified_transient_key( $tx_id ) ) ) {
			return;
		}
		set_transient( self::crypto_fulfill_notified_transient_key( $tx_id ), '1', 3600 );
		$user = SimpleVPBot_Model_User::find( (int) $tx->user_id );
		if ( ! $user ) {
			return;
		}
		$meta     = json_decode( (string) ( $tx->meta_json ?? '' ), true );
		$platform = ( is_array( $meta ) && class_exists( 'SimpleVPBot_Service_Naming' ) )
			? SimpleVPBot_Service_Naming::platform_from_meta( $meta )
			: 'telegram';
		if ( ! in_array( $platform, array( 'telegram', 'bale' ), true ) ) {
			$platform = 'telegram';
		}
		$chat_id = 'bale' === $platform ? (int) ( $user->bale_user_id ?? 0 ) : (int) ( $user->tg_user_id ?? 0 );
		if ( $chat_id < 1 ) {
			return;
		}
		$text = SimpleVPBot_Texts::format(
			SimpleVPBot_Texts::get_for_user(
				'msg.buy.fulfill_failed_crypto',
				$user,
				'⛔ تکمیل سفارش پرداخت کریپتو ناموفق بود. شماره سفارش: #{id}. لطفاً با پشتیبانی تماس بگیرید.'
			),
			array( 'id' => $tx_id )
		);
		SimpleVPBot_Bot_Runtime::send_message_with_support( $platform, $chat_id, $text );
	}

	/**
	 * Run purchase fulfill for a NOWPayments IPN (idempotent, with retry).
	 *
	 * @param int $tx_id Transaction id.
	 */
	private static function run_deferred_crypto_fulfill( $tx_id ) {
		$tx_id = (int) $tx_id;
		if ( $tx_id < 1 ) {
			return;
		}
		$tx = SimpleVPBot_Model_Transaction::find( $tx_id );
		if ( ! $tx || 'approved' === (string) $tx->status ) {
			delete_transient( self::crypto_fulfill_attempt_transient_key( $tx_id ) );
			return;
		}
		$res = SimpleVPBot_Receipt_Processor::fulfill_purchase_by_transaction( $tx_id, 'nowpayments' );
		if ( ! empty( $res['ok'] ) ) {
			delete_transient( self::crypto_fulfill_attempt_transient_key( $tx_id ) );
			delete_transient( self::crypto_fulfill_notified_transient_key( $tx_id ) );
			return;
		}
		$reason = (string) ( $res['reason'] ?? '' );
		if ( class_exists( 'SimpleVPBot_Logger' ) ) {
			SimpleVPBot_Logger::error(
				'crypto_ipn fulfill failed',
				array( 'tx_id' => $tx_id, 'reason' => $reason )
			);
		}
		if ( self::crypto_fulfill_reason_non_retryable( $reason ) ) {
			self::notify_crypto_fulfill_failed( $tx, $reason );
			return;
		}
		$attempt = (int) get_transient( self::crypto_fulfill_attempt_transient_key( $tx_id ) );
		if ( $attempt < self::CRYPTO_FULFILL_MAX_ATTEMPTS - 1 ) {
			set_transient( self::crypto_fulfill_attempt_transient_key( $tx_id ), (string) ( $attempt + 1 ), 3600 );
			$delay = 30 * ( $attempt + 1 );
			if ( ! wp_next_scheduled( SimpleVPBot_Deferred_Work::CRYPTO_FULFILL_CRON_HOOK, array( $tx_id ) ) ) {
				wp_schedule_single_event( time() + $delay, SimpleVPBot_Deferred_Work::CRYPTO_FULFILL_CRON_HOOK, array( $tx_id ) );
			}
			return;
		}
		self::notify_crypto_fulfill_failed( $tx, $reason );
	}
}
