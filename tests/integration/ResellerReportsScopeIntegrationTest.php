<?php
/**
 * Integration tests for scoped reseller reports build().
 *
 * @package SimpleVPBot
 */

require_once dirname( __DIR__ ) . '/fixtures/class-reseller-tree-fixture.php';

/**
 * Class ResellerReportsScopeIntegrationTest
 */
class ResellerReportsScopeIntegrationTest extends WP_UnitTestCase {

	/** @var SimpleVPBot_Reseller_Tree_Fixture|null */
	private $fixture;

	/**
	 * @inheritDoc
	 */
	protected function setUp(): void {
		parent::setUp();
		if ( ! class_exists( 'SimpleVPBot_Admin_Reseller_Reports' ) ) {
			$this->markTestSkipped( 'Reports helper not loaded.' );
		}
		$this->fixture = SimpleVPBot_Reseller_Tree_Fixture::seed( 882000000 + (int) ( microtime( true ) * 1000 ) % 100000 );
	}

	/**
	 * @inheritDoc
	 */
	protected function tearDown(): void {
		if ( $this->fixture instanceof SimpleVPBot_Reseller_Tree_Fixture ) {
			$this->fixture->tear_down();
		}
		parent::tearDown();
	}

	/**
	 * Scoped build() returns only downline reseller rows.
	 */
	public function test_build_scoped_to_parent_downline() {
		$req = new WP_REST_Request( 'GET', '/simplevpbot/v1/dashboard/admin/state' );
		$req->set_param( 'reseller_reports_days', 30 );
		$built = SimpleVPBot_Admin_Reseller_Reports::build(
			$req,
			array(
				'page'     => 1,
				'per_page' => 25,
				'offset'   => 0,
			),
			$this->fixture->parent_id
		);
		$this->assertSame( 2, (int) ( $built['total'] ?? 0 ) );
		$row_ids = array();
		foreach ( (array) ( $built['rows'] ?? array() ) as $row ) {
			if ( is_array( $row ) && isset( $row['reseller_id'] ) ) {
				$row_ids[] = (int) $row['reseller_id'];
			}
		}
		$this->assertContains( $this->fixture->child_id, $row_ids );
		$this->assertContains( $this->fixture->grandchild_id, $row_ids );
		$this->assertNotContains( $this->fixture->peer_id, $row_ids );
	}

	/**
	 * Scoped build() with no downline returns empty payload.
	 */
	public function test_build_scoped_empty_downline() {
		$req = new WP_REST_Request( 'GET', '/simplevpbot/v1/dashboard/admin/state' );
		$built = SimpleVPBot_Admin_Reseller_Reports::build(
			$req,
			array(
				'page'     => 1,
				'per_page' => 25,
				'offset'   => 0,
			),
			$this->fixture->peer_id
		);
		$this->assertSame( 0, (int) ( $built['total'] ?? -1 ) );
		$this->assertSame( array(), $built['rows'] ?? null );
	}
}
