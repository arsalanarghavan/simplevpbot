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
		$res = wp_remote_post(
			'https://api.nowpayments.io/v1/payment',
			array(
				'timeout' => 30,
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
		$res = SimpleVPBot_Receipt_Processor::fulfill_purchase_by_transaction( $tx_id, 'nowpayments' );
		if ( empty( $res['ok'] ) ) {
			SimpleVPBot_Logger::error(
				'crypto_ipn fulfill failed',
				array( 'tx_id' => $tx_id, 'reason' => (string) ( $res['reason'] ?? '' ) )
			);
			return new WP_REST_Response( array( 'ok' => false, 'reason' => (string) ( $res['reason'] ?? '' ) ), 200 );
		}
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
}
