<?php
/**
 * Contract tests for per-user service reconcile after DB restore.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class ServiceReconcileTest extends TestCase {

	/**
	 * Linker exposes panel-client user resolution used by reconcile.
	 */
	public function test_linker_resolve_user_id_from_panel_client(): void {
		$linker = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-inbound-linker.php' );
		$this->assertStringContainsString( 'function resolve_user_id_from_panel_client', $linker );
		$this->assertStringContainsString( 'function resolve_user_id_from_panel_client_detail', $linker );
		$this->assertStringContainsString( 'resolve_user_id_from_panel_client_detail', $linker );
		$this->assertStringContainsString( "preg_match( '/^u(\\d+)_/i', \$email", $linker );
		$this->assertStringContainsString( 'find_unique_approved_by_chat_id', $linker );
	}

	/**
	 * Reconcile class and cache query exist.
	 */
	public function test_service_reconcile_class_and_cache_query(): void {
		$recon = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-service-reconcile.php' );
		$this->assertStringContainsString( 'function reconcile_for_user', $recon );
		$this->assertStringContainsString( 'Inbound_Linker::link', $recon );
		$this->assertStringContainsString( 'candidates_for_user_reconcile', $recon );

		$model = (string) file_get_contents( dirname( __DIR__ ) . '/includes/models/class-model-panel-inbound-client.php' );
		$this->assertStringContainsString( 'function candidates_for_user_reconcile', $model );
	}

	/**
	 * Approved login and service menu trigger reconcile.
	 */
	public function test_hooks_call_reconcile(): void {
		$start = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-start.php' );
		$this->assertStringContainsString( 'SimpleVPBot_Service_Reconcile::reconcile_for_user', $start );

		$menu = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-user-menu.php' );
		$this->assertStringContainsString( 'SimpleVPBot_Service_Reconcile::reconcile_for_user', $menu );

		$svc = (string) file_get_contents( dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-service.php' );
		$this->assertStringContainsString( 'SimpleVPBot_Service_Reconcile::reconcile_for_user', $svc );
	}
}
