<?php
/**
 * Contract tests for broadcast platform formatting.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class BroadcastFormatContractsTest extends TestCase {

	/**
	 * Formatter class and plugin autoload entry exist.
	 */
	public function test_broadcast_format_class_registered(): void {
		$root = dirname( __DIR__ );
		$this->assertFileExists( $root . '/includes/helpers/class-broadcast-format.php' );
		$plugin = (string) file_get_contents( $root . '/includes/class-plugin.php' );
		$this->assertStringContainsString( 'SimpleVPBot_Broadcast_Format', $plugin );
		$this->assertStringContainsString( 'helpers/class-broadcast-format.php', $plugin );
	}

	/**
	 * Sanitizer must not convert newlines to unsupported <br> for Telegram HTML.
	 */
	public function test_sanitize_uses_newlines_not_br_tags(): void {
		$file = dirname( __DIR__ ) . '/includes/helpers/class-broadcast-format.php';
		$code = (string) file_get_contents( $file );
		$this->assertStringNotContainsString( "str_replace( \"\\n\", '<br>'", $code );
		$this->assertStringContainsString( "preg_replace( '/<br\\s*\\/?>/i', \"\\n\"", $code );
	}

	/**
	 * Cron worker converts Bale payloads to markdown instead of stripping parse_mode only.
	 */
	public function test_cron_bale_uses_markdown_formatter(): void {
		$file = dirname( __DIR__ ) . '/includes/cron/class-cron-broadcast.php';
		$code = (string) file_get_contents( $file );
		$this->assertStringContainsString( 'format_for_bale_markdown', $code );
		$this->assertStringContainsString( 'should_retry_bale_as_plain', $code );
	}

	/**
	 * Dashboard delegates sanitize to shared formatter.
	 */
	public function test_mutations_delegate_to_broadcast_format(): void {
		$file = dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php';
		$code = (string) file_get_contents( $file );
		$this->assertStringContainsString( 'SimpleVPBot_Broadcast_Format::sanitize_compose_html', $code );
	}

	/**
	 * UI exposes dual Telegram/Bale previews.
	 */
	public function test_dashboard_dual_preview(): void {
		$file = dirname( __DIR__ ) . '/frontend/src/components/dashboard-broadcast-admin.tsx';
		$code = (string) file_get_contents( $file );
		$this->assertStringContainsString( 'BroadcastBalePreview', $code );
		$this->assertStringContainsString( 'previewTelegram', $code );
		$this->assertStringContainsString( 'htmlToBalePreviewMarkdown', $code );
	}
}
