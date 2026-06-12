<?php
/**
 * Contract tests for bulk user notify (volume/extend/slots).
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class UsersBulkNotifyTest extends TestCase {

	/**
	 * Mutations store notify fields on service bulk jobs and expose render/send helpers.
	 */
	public function test_bulk_notify_helpers_in_mutations(): void {
		$mut = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( 'function users_bulk_notify_fields', $mut );
		$this->assertStringContainsString( 'function users_bulk_render_notify_message', $mut );
		$this->assertStringContainsString( 'function users_bulk_maybe_notify_service_op', $mut );
		$this->assertStringContainsString( 'self::users_bulk_notify_fields( $p )', $mut );
		$this->assertStringContainsString( "'notify_message'", $mut );
		$this->assertStringContainsString( '{name}', $mut );
	}

	/**
	 * Cron worker sends one notify per user after successful service bulk op.
	 */
	public function test_cron_calls_bulk_notify(): void {
		$cron = (string) file_get_contents( dirname( __DIR__ ) . '/includes/cron/class-cron-users-bulk.php' );
		$this->assertStringContainsString( 'users_bulk_maybe_notify_service_op', $cron );
		$this->assertStringContainsString( "in_array( \$op, array( 'volume', 'extend', 'slots' )", $cron );
	}

	/**
	 * Dashboard bulk UI exposes notify message for service operations.
	 */
	public function test_dashboard_bulk_notify_ui(): void {
		$ui = (string) file_get_contents(
			dirname( __DIR__ ) . '/frontend/src/components/dashboard-users-bulk-admin.tsx'
		);
		$this->assertStringContainsString( 'notifyMessage', $ui );
		$this->assertStringContainsString( 'notify_message', $ui );
		$this->assertStringContainsString( 'showServiceNotify', $ui );
	}
}
