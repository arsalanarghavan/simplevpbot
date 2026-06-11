<?php
/**
 * Contract tests for receipt photo delivery and moderation feedback.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class ReceiptDeliveryContractsTest extends TestCase {

	/**
	 * Bale sendPhoto path must scrub and truncate captions.
	 */
	public function test_send_photo_uses_prepare_photo_caption(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/class-bot-runtime.php' );
		$this->assertStringContainsString( 'function prepare_photo_caption', $code );
		$this->assertStringContainsString( 'prepare_photo_caption( $platform, (string) $caption )', $code );
		$this->assertMatchesRegularExpression(
			'/function send_photo[\s\S]*prepare_photo_caption/',
			$code
		);
		$this->assertMatchesRegularExpression(
			'/function send_photo_file[\s\S]*prepare_photo_caption/',
			$code
		);
	}

	/**
	 * Receipt callback must defer answer and give admin toast + edit clicked message.
	 */
	public function test_handle_receipt_provides_admin_feedback(): void {
		$cb = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-callback.php' );
		$this->assertStringContainsString( '$defer_cb_answer = 0 === strpos( $data, \'rc:\' )', $cb );
		$this->assertStringContainsString( 'admin_feedback_text', $cb );
		$this->assertStringContainsString( 'finalize_clicked_admin_message', $cb );
		$this->assertStringContainsString( "'text'              => \$toast", $cb );
		$this->assertMatchesRegularExpression(
			'/function handle_receipt[\s\S]*\$admin_msg_id/',
			$cb
		);
	}

	/**
	 * Deferred receipt admin notify must skip duplicate delivery.
	 */
	public function test_receipt_admin_notify_idempotency_guard(): void {
		$buy = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-buy.php' );
		$this->assertStringContainsString( 'function receipt_admin_photo_delivered', $buy );
		$this->assertMatchesRegularExpression(
			'/function run_deferred_receipt_admin_notify[\s\S]*receipt_admin_photo_delivered/',
			$buy
		);
		$this->assertMatchesRegularExpression(
			'/function deliver_receipt_to_admins[\s\S]*receipt_admin_photo_delivered/',
			$buy
		);
		$this->assertStringContainsString( 'send_admin_receipt_photo_ladder', $buy );
	}

	/**
	 * Failed delivery warns admin but does not register text-only as delivered receipt.
	 */
	public function test_photo_fallback_does_not_record_delivered_entry(): void {
		$buy = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-buy.php' );
		$this->assertStringContainsString( 'function notify_admin_receipt_photo_fallback', $buy );
		$this->assertStringContainsString( 'function send_admin_receipt_photo_ladder', $buy );
		$this->assertDoesNotMatchRegularExpression(
			'/notify_admin_receipt_photo_fallback[\s\S]*admin_msgs\[\]/',
			$buy
		);
		$this->assertStringContainsString( 'download_bot_file_to_path', $buy );
	}

	/**
	 * Single-message receipt: no success path with empty photo caption.
	 */
	public function test_no_empty_caption_photo_success(): void {
		$buy = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-buy.php' );
		$this->assertDoesNotMatchRegularExpression(
			'/photo without caption/i',
			$buy
		);
		$this->assertMatchesRegularExpression(
			"/function try_send_admin_receipt_photo_once[\s\S]*'' === trim/",
			$buy
		);
		$caption = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-bot-admin-user-caption.php' );
		$this->assertStringContainsString( 'receipt_new_caption_for_platform', $caption );
		$this->assertStringContainsString( 'fit_receipt_caption_for_photo', $caption );
	}

	/**
	 * Dashboard receipt image proxy resolves reseller bot token.
	 */
	public function test_receipt_image_uses_reseller_token(): void {
		$ajax = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-admin-ajax.php' );
		$this->assertStringContainsString( 'resolve_reseller_id_for_notify', $ajax );
		$this->assertStringContainsString( 'bot_token_for_reseller', $ajax );
	}

	/**
	 * Multipart uploads use image MIME types, not octet-stream.
	 */
	public function test_multipart_uses_image_mime(): void {
		$client = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-bot-client.php' );
		$this->assertStringContainsString( 'multipart_mime_for_path', $client );
		$this->assertStringNotContainsString( 'Content-Type: application/octet-stream', $client );
	}

	/**
	 * Receipt moderation must not send new chat messages on edit failure.
	 */
	public function test_moderation_uses_glass_button_only(): void {
		$rp = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-receipt-processor.php' );
		$this->assertStringContainsString( 'edit_reply_markup_with_retry', $rp );
		$this->assertMatchesRegularExpression(
			'/function edit_admin_messages[\s\S]*edit_reply_markup_with_retry/',
			$rp
		);
		$this->assertStringNotContainsString( "SimpleVPBot_Bot_Runtime::send_message( \$plat, \$cid, \$fallback )", $rp );
		$cb = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-callback.php' );
		$this->assertMatchesRegularExpression(
			'/function handle_receipt[\s\S]*answer_callback_query/',
			$cb
		);
		$this->assertStringNotContainsString( 'تایید شد اما ساخت سرویس روی پنل ناموفق', $cb );
	}

	/**
	 * Config delivery retries portal fetch until subscription is ready.
	 */
	public function test_config_delivery_retries_portal_data(): void {
		$svc = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-service.php' );
		$this->assertStringContainsString( 'function run_svc_config_delivery', $svc );
		$this->assertMatchesRegularExpression(
			'/function run_svc_config_delivery[\s\S]*portal_data_has_sendable_config/',
			$svc
		);
		$this->assertMatchesRegularExpression(
			'/function run_svc_config_delivery[\s\S]*schedule_cron_retry/',
			$svc
		);
		$this->assertStringContainsString( 'config_delivery_exhausted_retries', $svc );
		$this->assertMatchesRegularExpression(
			'/function telegram_send_config_unified[\s\S]*resolve_user_dashboard_url/',
			$svc
		);
		$this->assertMatchesRegularExpression(
			'/function telegram_send_config_unified[\s\S]*build_combined_config_message_html/',
			$svc
		);
		$this->assertMatchesRegularExpression(
			'/function telegram_send_config_unified[\s\S]*?function handle_config_wire/',
			$svc
		);
		$auto_block = array();
		if ( preg_match( '/function telegram_send_config_unified[\s\S]*?function handle_config_wire/', $svc, $auto_block ) ) {
			$this->assertStringNotContainsString( 'telegram_config_send_fail', $auto_block[0] );
		}
		$this->assertMatchesRegularExpression(
			'/function run_svc_config_delivery[\s\S]*?function schedule_svc_panel_full_delivery/',
			$svc
		);
		$retry_block = array();
		if ( preg_match( '/function run_svc_config_delivery[\s\S]*?function schedule_svc_panel_full_delivery/', $svc, $retry_block ) ) {
			$this->assertStringNotContainsString( 'auto_config_pending', $retry_block[0] );
			$this->assertStringNotContainsString( 'telegram_config_send_fail', $retry_block[0] );
		}
		$this->assertStringContainsString( 'send_config_qr_photo', $svc );
		$this->assertMatchesRegularExpression(
			'/function telegram_send_config_unified[\s\S]*qr_sent/',
			$svc
		);
		$this->assertStringContainsString( 'set_config_delivery_intro', $svc );
	}

	/**
	 * Telegram auto-delivery skips separate service-ready when config is queued.
	 */
	public function test_service_ready_skips_separate_message_for_telegram(): void {
		$rp = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-receipt-processor.php' );
		$this->assertStringContainsString( 'user_gets_telegram_config_delivery', $rp );
		$this->assertMatchesRegularExpression(
			'/function notify_user_service_ready[\s\S]*set_config_delivery_intro/',
			$rp
		);
		$this->assertMatchesRegularExpression(
			'/function notify_user_service_ready[\s\S]*enqueue_config_delivery_for_user[\s\S]*notify_user_both_bots/',
			$rp
		);
	}

	/**
	 * Unified post-approve notify does not gate service-ready on plan_id alone.
	 */
	public function test_unified_post_approve_notify(): void {
		$rp = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-receipt-processor.php' );
		$this->assertStringContainsString( 'function notify_user_after_purchase_approved', $rp );
		$this->assertMatchesRegularExpression(
			'/function approve_continue[\s\S]*notify_user_after_purchase_approved/',
			$rp
		);
		$this->assertMatchesRegularExpression(
			'/function notify_user_after_purchase_approved[\s\S]*notify_user_service_ready/',
			$rp
		);
		$this->assertStringContainsString( 'receipt_notify_skipped_no_service', $rp );
	}

	/**
	 * Config auto-push uses tg_user_id, not legacy telegram_id.
	 */
	public function test_enqueue_config_uses_tg_user_id(): void {
		$svc = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-service.php' );
		$this->assertStringContainsString( 'function resolve_telegram_chat_id', $svc );
		$this->assertMatchesRegularExpression(
			'/function enqueue_config_delivery_for_user[\s\S]*resolve_telegram_chat_id/',
			$svc
		);
		$this->assertStringNotContainsString( 'telegram_id ?? 0', $svc );
	}

	/**
	 * Approve path clears deferred cron after success.
	 */
	public function test_approve_clears_deferred_cron(): void {
		$rp = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-receipt-processor.php' );
		$this->assertMatchesRegularExpression(
			'/function approve_continue[\s\S]*clear_scheduled_cron/',
			$rp
		);
		$this->assertMatchesRegularExpression(
			'/function approve_continue[\s\S]*notify_user_after_purchase_approved/',
			$rp
		);
	}

	/**
	 * Sync approve updates clicked admin message and does not defer from async_start.
	 */
	public function test_sync_approve_finalize_clicked(): void {
		$rp  = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-receipt-processor.php' );
		$cb  = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-callback.php' );
		$this->assertStringContainsString( 'finalize_clicked_if', $rp );
		$this->assertMatchesRegularExpression(
			'/function approve_async_start[\s\S]*approve_continue/',
			$rp
		);
		$this->assertDoesNotMatchRegularExpression(
			'/function approve_async_start[\s\S]*run_after_response_or_cron/',
			$rp
		);
		$this->assertStringNotContainsString( 'RECEIPT_APPROVE_CRON_HOOK', $cb );
		$this->assertMatchesRegularExpression(
			'/approve_continue\([^)]*clicked/',
			$cb
		);
	}

	/**
	 * Fallback text must not satisfy photo-delivered guard.
	 */
	public function test_text_fallback_does_not_block_photo_retry(): void {
		$buy = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-buy.php' );
		$this->assertMatchesRegularExpression(
			"/function receipt_admin_photo_delivered[\s\S]*kind.*photo/",
			$buy
		);
		$this->assertStringNotContainsString( 'function receipt_admin_notify_already_done', $buy );
	}

	/**
	 * Approved-to-rejected new purchase disables provisioned service.
	 */
	public function test_reverse_new_purchase_disables_service(): void {
		$rp = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-receipt-processor.php' );
		$this->assertStringContainsString( 'is_purchase_intent_modification', $rp );
		$this->assertStringContainsString( 'xray_delete_panel_client', $rp );
		$this->assertStringNotContainsString( 'purchase_has_service', $rp );
	}
}
