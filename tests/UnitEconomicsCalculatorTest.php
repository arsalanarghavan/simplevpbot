<?php
/**
 * Unit economics calculator tests (v2 panel lines).
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class UnitEconomicsCalculatorTest extends TestCase {

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
		if ( ! class_exists( 'SimpleVPBot_Unit_Economics_Calculator' ) ) {
			require_once dirname( __DIR__ ) . '/includes/helpers/class-unit-economics-calculator.php';
		}
	}

	/**
	 * Multiple per_gb CDN + fixed server costs on one panel.
	 */
	public function test_calculate_per_gb_and_fixed(): void {
		$lines = array(
			array(
				'panel_id'      => 1,
				'category'      => 'external_server',
				'label'         => 'Aeza',
				'cost_amount'   => 1022000,
				'billing_cycle' => 'monthly',
			),
			array(
				'panel_id'      => 1,
				'category'      => 'cdn',
				'label'         => 'Arvan',
				'cost_amount'   => 1800,
				'billing_cycle' => 'per_gb',
			),
			array(
				'panel_id'      => 1,
				'category'      => 'cdn',
				'label'         => 'ParsPack',
				'cost_amount'   => 900,
				'billing_cycle' => 'per_gb',
			),
			array(
				'panel_id'      => 1,
				'category'      => 'outbound',
				'label'         => 'Pars daily',
				'cost_amount'   => 10000,
				'billing_cycle' => 'daily',
			),
		);
		$config = array(
			'total_sold_volume_gb' => 1000,
			'selling_price_per_gb' => 5000,
		);
		$result = SimpleVPBot_Unit_Economics_Calculator::calculate( $lines, $config );
		$m      = $result['metrics'];

		// fixed: 1022000 + 10000*30 = 1022000 + 300000 = 1322000
		$this->assertEqualsWithDelta( 1322000.0, $m['total_fixed_monthly_costs'], 1.0 );
		// variable: 1800+900 = 2700
		$this->assertEqualsWithDelta( 2700.0, $m['total_variable_cost_per_gb'], 0.01 );
		$this->assertEqualsWithDelta( 1322.0 + 2700.0, $m['cost_per_gb'], 0.1 );
		$this->assertSame( array(), $result['warnings'] );
	}

	/**
	 * Zero volume warning.
	 */
	public function test_zero_volume_warning(): void {
		$result = SimpleVPBot_Unit_Economics_Calculator::calculate(
			array(
				array(
					'category'      => 'cdn',
					'label'         => 'CDN',
					'cost_amount'   => 100,
					'billing_cycle' => 'per_gb',
				),
			),
			array(
				'total_sold_volume_gb' => 0,
				'selling_price_per_gb' => 100,
			)
		);
		$this->assertContains( 'volume_required', $result['warnings'] );
		$this->assertEquals( 0.0, $result['metrics']['fixed_cost_share_per_gb'] );
	}

	/**
	 * Loss-making price.
	 */
	public function test_loss_making_price_warning(): void {
		$result = SimpleVPBot_Unit_Economics_Calculator::calculate(
			array(
				array(
					'category'      => 'outbound',
					'label'         => 'Out',
					'cost_amount'   => 80,
					'billing_cycle' => 'per_gb',
				),
			),
			array(
				'total_sold_volume_gb' => 100,
				'selling_price_per_gb' => 50,
			)
		);
		$this->assertContains( 'loss_making_price', $result['warnings'] );
	}

	/**
	 * Margin null when selling zero.
	 */
	public function test_margin_null_when_selling_zero(): void {
		$result = SimpleVPBot_Unit_Economics_Calculator::calculate(
			array(),
			array(
				'total_sold_volume_gb' => 100,
				'selling_price_per_gb' => 0,
			)
		);
		$this->assertNull( $result['metrics']['profit_margin_percentage'] );
	}

	/**
	 * Per-panel volume override affects fixed share per GB.
	 */
	public function test_volume_override_per_panel(): void {
		$lines = array(
			array(
				'panel_id'      => 1,
				'category'      => 'external_server',
				'label'         => 'Srv',
				'cost_amount'   => 1000,
				'billing_cycle' => 'monthly',
			),
		);
		$config = array(
			'total_sold_volume_gb' => 100,
			'selling_price_per_gb' => 5000,
		);
		$site = SimpleVPBot_Unit_Economics_Calculator::calculate( $lines, $config, 100 );
		$panel = SimpleVPBot_Unit_Economics_Calculator::calculate( $lines, $config, 50 );
		$this->assertEqualsWithDelta( 10.0, $site['metrics']['fixed_cost_share_per_gb'], 0.01 );
		$this->assertEqualsWithDelta( 20.0, $panel['metrics']['fixed_cost_share_per_gb'], 0.01 );
	}

	/**
	 * Hourly normalization.
	 */
	public function test_line_monthly_fixed_hourly(): void {
		$this->assertEqualsWithDelta(
			7300.0,
			SimpleVPBot_Unit_Economics_Calculator::line_monthly_fixed_cost( 10, 'hourly' ),
			0.01
		);
	}
}
