<?php
/**
 * Smoke: L2TP feature flag and hide helpers (no WordPress bootstrap).
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class FeatureL2tpHiddenTest extends TestCase {

	/**
	 * Feature helper and settings default exist.
	 */
	public function test_feature_files_and_defaults(): void {
		$root = dirname( __DIR__ );
		$this->assertFileExists( $root . '/includes/helpers/class-feature-l2tp.php' );
		$settings = (string) file_get_contents( $root . '/includes/class-settings.php' );
		$this->assertStringContainsString( "'l2tp_enabled'", $settings );
		$this->assertStringContainsString( "'l2tp_enabled'                   => false", $settings );
		$feat = (string) file_get_contents( $root . '/includes/helpers/class-feature-l2tp.php' );
		$this->assertStringContainsString( 'function enabled', $feat );
		$this->assertStringContainsString( 'function filter_plans', $feat );
		$this->assertStringContainsString( 'function filter_services', $feat );
		$this->assertStringContainsString( 'admin.cat.l2tp', $feat );
	}

	/**
	 * Provisioner blocks L2TP when feature is off.
	 */
	public function test_provisioner_guards_l2tp_disabled(): void {
		$root = dirname( __DIR__ );
		$prov = (string) file_get_contents( $root . '/includes/helpers/class-service-provisioner.php' );
		$this->assertStringContainsString( 'l2tp_disabled', $prov );
		$this->assertStringContainsString( 'SimpleVPBot_Feature_L2tp::enabled()', $prov );
	}
}
