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
	 * Photo fallback text must be tracked in admin_messages_json.
	 */
	public function test_photo_fallback_records_admin_message_id(): void {
		$buy = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-buy.php' );
		$this->assertStringContainsString( 'function notify_admin_receipt_photo_fallback', $buy );
		$this->assertStringContainsString( 'notify_admin_receipt_photo_fallback', $buy );
		$this->assertMatchesRegularExpression(
			'/notify_admin_receipt_photo_fallback[\s\S]*admin_msgs\[\]/',
			$buy
		);
		$this->assertStringContainsString( 'download_bot_file_to_path', $buy );
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
	 * Approved-to-rejected new purchase disables provisioned service.
	 */
	public function test_reverse_new_purchase_disables_service(): void {
		$rp = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-receipt-processor.php' );
		$this->assertStringContainsString( 'is_purchase_intent_modification', $rp );
		$this->assertStringContainsString( 'xray_delete_panel_client', $rp );
		$this->assertStringNotContainsString( 'purchase_has_service', $rp );
	}
}
