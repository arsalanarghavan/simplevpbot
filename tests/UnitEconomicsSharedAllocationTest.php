<?php
/**
 * Volume-weighted shared infrastructure allocation tests.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class UnitEconomicsSharedAllocationTest extends TestCase {

	/**
	 * Load calculator without WordPress.
	 */
	protected function setUp(): void {
		parent::setUp();
		if ( ! function_exists( 'sanitize_text_field' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
			function sanitize_text_field( $str ) {
				return is_string( $str ) ? trim( strip_tags( $str ) ) : '';
			}
		}
		if ( ! function_exists( 'sanitize_key' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
			function sanitize_key( $key ) {
				return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
			}
		}
		if ( ! function_exists( 'sanitize_textarea_field' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
			function sanitize_textarea_field( $str ) {
				return is_string( $str ) ? trim( $str ) : '';
			}
		}
		if ( ! class_exists( 'SimpleVPBot_Unit_Economics_Calculator' ) ) {
			require_once dirname( __DIR__ ) . '/includes/helpers/class-unit-economics-calculator.php';
		}
		if ( ! class_exists( 'SimpleVPBot_Model_Panel_Economics_Line' ) ) {
			require_once dirname( __DIR__ ) . '/includes/models/class-model-panel-economics-line.php';
		}
	}

	/**
	 * Shared fixed 1000, panel A 75 GB, panel B 25 GB → alloc 750 / 250.
	 */
	public function test_volume_weighted_shared_fixed_allocation(): void {
		$shared = array(
			array(
				'panel_id'      => 0,
				'category'      => 'internal_server',
				'label'         => 'Bot host',
				'cost_amount'   => 1000,
				'billing_cycle' => 'monthly',
			),
		);
		$config = array(
			'total_sold_volume_gb' => 100,
			'selling_price_per_gb' => 5000,
			'volume_mode'          => 'manual',
		);
		$sales = array(
			'by_panel' => array(
				1 => 75.0,
				2 => 25.0,
			),
			'total_gb' => 100.0,
		);

		$this->assertEqualsWithDelta(
			750.0,
			SimpleVPBot_Unit_Economics_Calculator::shared_fixed_alloc_for_panel( 1000.0, 75.0, 100.0 ),
			0.01
		);
		$this->assertEqualsWithDelta(
			250.0,
			SimpleVPBot_Unit_Economics_Calculator::shared_fixed_alloc_for_panel( 1000.0, 25.0, 100.0 ),
			0.01
		);

		$panel_a = SimpleVPBot_Unit_Economics_Calculator::calculate_for_panel(
			1,
			array(),
			$config,
			$sales,
			$shared
		);
		$panel_b = SimpleVPBot_Unit_Economics_Calculator::calculate_for_panel(
			2,
			array(),
			$config,
			$sales,
			$shared
		);

		$this->assertEqualsWithDelta(
			750.0,
			$panel_a['costAllocation']['shared_fixed_alloc_monthly'],
			0.01
		);
		$this->assertEqualsWithDelta(
			250.0,
			$panel_b['costAllocation']['shared_fixed_alloc_monthly'],
			0.01
		);
	}

	/**
	 * Invalid payment_method normalizes to other.
	 */
	public function test_payment_method_sanitize_enum(): void {
		$line = SimpleVPBot_Model_Panel_Economics_Line::sanitize_line(
			array(
				'label'           => 'CDN',
				'payment_method'  => 'not_a_real_method',
				'billing_cycle'   => 'monthly',
				'category'        => 'cdn',
			)
		);
		$this->assertSame( 'other', $line['payment_method'] );

		$ok = SimpleVPBot_Model_Panel_Economics_Line::sanitize_line(
			array(
				'label'          => 'Host',
				'payment_method' => 'usdt_trc20',
			)
		);
		$this->assertSame( 'usdt_trc20', $ok['payment_method'] );
	}
}
