<?php
/**
 * Run heavy bot work after HTTP response (shutdown + optional cron fallback).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Deferred_Work
 */
class SimpleVPBot_Deferred_Work {

	const RECEIPT_APPROVE_CRON_HOOK = 'svp_deferred_receipt_approve';

	const WALLET_FULFILL_CRON_HOOK = 'svp_deferred_wallet_fulfill';

	const CRYPTO_FULFILL_CRON_HOOK = 'svp_deferred_crypto_fulfill';

	const RECEIPT_ADMIN_NOTIFY_CRON_HOOK = 'svp_deferred_receipt_admin_notify';

	const SVC_CONFIG_DELIVERY_CRON_HOOK = 'svp_deferred_svc_config_delivery';

	const RECEIPT_PROVISION_RETRY_CRON_HOOK = 'svp_deferred_receipt_provision_retry';

	const SVC_PANEL_DELIVERY_CRON_HOOK = 'svp_deferred_svc_panel_delivery';

	const BUY_CHECKOUT_CRON_HOOK = 'svp_deferred_buy_checkout';

	const C2C_INVOICE_CRON_HOOK = 'svp_deferred_c2c_invoice';

	/**
	 * @var array<int, array{fn: callable, label: string}>
	 */
	private static $queue = array();

	/**
	 * @var bool
	 */
	private static $shutdown_registered = false;

	/**
	 * Register cron handlers.
	 */
	public static function init() {
		add_action( self::RECEIPT_APPROVE_CRON_HOOK, array( 'SimpleVPBot_Receipt_Processor', 'approve_continue_cron' ), 10, 2 );
		add_action( self::WALLET_FULFILL_CRON_HOOK, array( 'SimpleVPBot_Handler_Buy', 'deferred_wallet_fulfill_cron' ), 10, 6 );
		add_action( self::CRYPTO_FULFILL_CRON_HOOK, array( 'SimpleVPBot_Crypto_Payment', 'deferred_crypto_fulfill_cron' ), 10, 1 );
		add_action( self::RECEIPT_ADMIN_NOTIFY_CRON_HOOK, array( 'SimpleVPBot_Handler_Buy', 'deferred_receipt_admin_notify_cron' ), 10, 5 );
		add_action( self::SVC_CONFIG_DELIVERY_CRON_HOOK, array( 'SimpleVPBot_Handler_Service', 'deferred_svc_config_delivery_cron' ), 10, 4 );
		add_action( self::RECEIPT_PROVISION_RETRY_CRON_HOOK, array( 'SimpleVPBot_Receipt_Processor', 'receipt_provision_retry_cron' ), 10, 1 );
		add_action( self::SVC_PANEL_DELIVERY_CRON_HOOK, array( 'SimpleVPBot_Handler_Service', 'deferred_svc_panel_delivery_cron' ), 10, 7 );
		add_action( self::BUY_CHECKOUT_CRON_HOOK, array( 'SimpleVPBot_Handler_Buy', 'deferred_purchase_checkout_cron' ), 10, 7 );
		add_action( self::C2C_INVOICE_CRON_HOOK, array( 'SimpleVPBot_Handler_Buy', 'deferred_c2c_invoice_cron' ), 10, 6 );
	}

	/**
	 * Queue a callable to run on shutdown (after response when possible).
	 *
	 * @param callable $fn    Work to run.
	 * @param string   $label Log label.
	 */
	public static function run_after_response( callable $fn, $label = '' ) {
		self::$queue[] = array(
			'fn'    => $fn,
			'label' => (string) $label,
		);
		if ( ! self::$shutdown_registered ) {
			self::$shutdown_registered = true;
			add_action( 'shutdown', array( __CLASS__, 'run_shutdown_queue' ), 0 );
		}
	}

	/**
	 * Queue shutdown work and schedule a cron fallback if shutdown never runs.
	 *
	 * @param callable             $fn         Shutdown callable.
	 * @param string               $cron_hook  Cron action hook.
	 * @param array<int, mixed>    $cron_args  Cron args.
	 * @param string               $label      Log label.
	 */
	public static function run_after_response_or_cron( callable $fn, $cron_hook, array $cron_args, $label = '' ) {
		self::run_after_response( $fn, $label );
		$cron_hook = (string) $cron_hook;
		if ( '' === $cron_hook ) {
			return;
		}
		$lock_key = 'svp_dwc_' . md5( $cron_hook . '|' . wp_json_encode( $cron_args ) );
		if ( get_transient( $lock_key ) ) {
			return;
		}
		set_transient( $lock_key, '1', 600 );
		if ( ! wp_next_scheduled( $cron_hook, $cron_args ) ) {
			wp_schedule_single_event( time() + 1, $cron_hook, $cron_args );
		}
	}

	/**
	 * Cancel a single scheduled cron event (idempotent helper for deferred workers).
	 *
	 * @param string            $cron_hook Cron action hook.
	 * @param array<int, mixed> $cron_args Cron args.
	 */
	public static function clear_scheduled_cron( $cron_hook, array $cron_args ) {
		$cron_hook = (string) $cron_hook;
		if ( '' === $cron_hook ) {
			return;
		}
		wp_clear_scheduled_hook( $cron_hook, $cron_args );
	}

	/**
	 * Schedule a single cron event (retry helper; does not use shutdown lock).
	 *
	 * @param string            $cron_hook      Cron action hook.
	 * @param array<int, mixed> $cron_args      Cron args.
	 * @param int               $delay_seconds  Delay before run.
	 */
	public static function schedule_cron_retry( $cron_hook, array $cron_args, $delay_seconds = 30 ) {
		$cron_hook = (string) $cron_hook;
		if ( '' === $cron_hook ) {
			return;
		}
		$delay = max( 5, (int) $delay_seconds );
		if ( ! wp_next_scheduled( $cron_hook, $cron_args ) ) {
			wp_schedule_single_event( time() + $delay, $cron_hook, $cron_args );
		}
	}

	/**
	 * Execute queued callables (shutdown hook).
	 */
	public static function run_shutdown_queue() {
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			@fastcgi_finish_request(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		$queue       = self::$queue;
		self::$queue = array();
		foreach ( $queue as $item ) {
			try {
				call_user_func( $item['fn'] );
			} catch ( Throwable $e ) { // phpcs:ignore
				if ( class_exists( 'SimpleVPBot_Logger' ) ) {
					SimpleVPBot_Logger::error(
						'deferred work failed',
						array(
							'label' => (string) $item['label'],
							'm'     => $e->getMessage(),
						)
					);
				}
			}
		}
	}
}
