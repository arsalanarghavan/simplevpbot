<?php
/**
 * Sales volume extraction for unit economics.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class UnitEconomicsSalesVolumeTest extends TestCase {

	/**
	 * Load helper without WordPress.
	 */
	protected function setUp(): void {
		parent::setUp();
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/' );
		}
		if ( ! class_exists( 'SimpleVPBot_Unit_Economics_Sales_Volume' ) ) {
			require_once dirname( __DIR__ ) . '/includes/helpers/class-unit-economics-sales-volume.php';
		}
	}

	/**
	 * add_volume purchase counts extra_gb and resolves panel via service.
	 */
	public function test_add_volume_gb_and_panel_from_service(): void {
		$tx = (object) array(
			'type'       => 'purchase',
			'service_id' => 10,
			'meta_json'  => wp_json_encode(
				array(
					'intent'   => 'add_volume',
					'extra_gb' => 25,
				)
			),
		);
		$plans    = array();
		$services = array(
			10 => (object) array( 'panel_id' => 3 ),
		);
		$this->assertSame( 25.0, SimpleVPBot_Unit_Economics_Sales_Volume::gb_from_transaction_row( $tx, $plans, $services ) );
		$this->assertSame( 3, SimpleVPBot_Unit_Economics_Sales_Volume::panel_id_from_transaction_row( $tx, $plans, $services ) );
	}

	/**
	 * New purchase with fixed plan uses traffic_gb.
	 */
	public function test_purchase_fixed_plan_traffic(): void {
		$tx = (object) array(
			'type'       => 'purchase',
			'service_id' => 0,
			'meta_json'  => wp_json_encode( array( 'plan_id' => 5 ) ),
		);
		$plans = array(
			5 => (object) array(
				'panel_id'     => 2,
				'pricing_type' => 'fixed',
				'traffic_gb'   => 100,
			),
		);
		$services = array();
		$this->assertSame( 100.0, SimpleVPBot_Unit_Economics_Sales_Volume::gb_from_transaction_row( $tx, $plans, $services ) );
		$this->assertSame( 2, SimpleVPBot_Unit_Economics_Sales_Volume::panel_id_from_transaction_row( $tx, $plans, $services ) );
	}

	/**
	 * renew_same intent on purchase yields zero GB.
	 */
	public function test_renew_same_zero_gb(): void {
		$tx = (object) array(
			'type'       => 'purchase',
			'service_id' => 1,
			'meta_json'  => wp_json_encode( array( 'intent' => 'renew_same' ) ),
		);
		$plans    = array();
		$services = array( 1 => (object) array( 'panel_id' => 1 ) );
		$this->assertSame( 0.0, SimpleVPBot_Unit_Economics_Sales_Volume::gb_from_transaction_row( $tx, $plans, $services ) );
	}

	/**
	 * Autorenew renew row without GB in meta yields zero.
	 */
	public function test_renew_autorenew_zero_gb(): void {
		$tx = (object) array(
			'type'       => 'renew',
			'service_id' => 4,
			'meta_json'  => wp_json_encode( array( 'autorenew' => true, 'plan_id' => 1 ) ),
		);
		$plans    = array( 1 => (object) array( 'panel_id' => 1, 'traffic_gb' => 50 ) );
		$services = array( 4 => (object) array( 'panel_id' => 1 ) );
		$this->assertSame( 0.0, SimpleVPBot_Unit_Economics_Sales_Volume::gb_from_transaction_row( $tx, $plans, $services ) );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $data Data.
	 * @return string
	 */
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}
