<?php
/**
 * Contract: unit economics tables exist in activator DDL.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class UnitEconomicsMigrationTest extends TestCase {

	/**
	 * Activator defines 2.3.8 unit economics schema helpers.
	 */
	public function test_activator_unit_economics_ddl(): void {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-activator.php' );
		$this->assertStringContainsString( "const DB_VERSION = '2.4.4'", $code );
		$this->assertStringContainsString( 'maybe_migrate_238_unit_economics', $code );
		$this->assertStringContainsString( 'maybe_migrate_239_panel_economics_lines', $code );
		$this->assertStringContainsString( 'maybe_migrate_240_unit_economics_volume_mode', $code );
		$this->assertStringContainsString( 'class-unit-economics-sales-volume.php', (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-plugin.php' ) );
		$this->assertStringContainsString( 'simplevpbot_cron_panel_economics_renewal', (string) file_get_contents( dirname( __DIR__ ) . '/includes/cron/class-cron-manager.php' ) );
		$this->assertStringContainsString( 'svp_unit_economics_config', $code );
		$this->assertStringContainsString( 'svp_panel_economics_lines', $code );
		$this->assertStringContainsString( 'sql_panel_economics_lines', $code );
		$this->assertStringContainsString( 'panel_economics_save', (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-dashboard-admin-mutations.php' ) );
	}
}
