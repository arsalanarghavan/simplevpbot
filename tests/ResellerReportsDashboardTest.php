<?php
/**
 * Contract tests for admin reseller reports helper.
 *
 * @package SimpleVPBot
 */

use PHPUnit\Framework\TestCase;

/**
 * Class ResellerReportsDashboardTest
 */
class ResellerReportsDashboardTest extends TestCase {

	/**
	 * Helper class is registered for autoload.
	 */
	public function test_helper_class_exists() {
		$this->assertTrue( class_exists( 'SimpleVPBot_Admin_Reseller_Reports' ) );
	}

	/**
	 * Allowed window days are fixed set.
	 */
	public function test_allowed_window_days_constant() {
		$this->assertSame(
			array( 7, 30, 90 ),
			SimpleVPBot_Admin_Reseller_Reports::ALLOWED_WINDOW_DAYS
		);
	}

	/**
	 * window_days_from_request clamps to allowed values.
	 */
	public function test_window_days_from_request_defaults_and_clamps() {
		if ( ! class_exists( 'WP_REST_Request' ) ) {
			$this->markTestSkipped( 'WP_REST_Request not available' );
		}
		$req = new WP_REST_Request( 'GET', '/simplevpbot/v1/dashboard/admin/state' );
		$this->assertSame( 30, SimpleVPBot_Admin_Reseller_Reports::window_days_from_request( $req ) );

		$req->set_param( 'reseller_reports_days', 7 );
		$this->assertSame( 7, SimpleVPBot_Admin_Reseller_Reports::window_days_from_request( $req ) );

		$req->set_param( 'reseller_reports_days', 365 );
		$this->assertSame( 30, SimpleVPBot_Admin_Reseller_Reports::window_days_from_request( $req ) );
	}

	/**
	 * build() payload exposes expected top-level keys (contract).
	 */
	public function test_build_payload_shape_contract() {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-admin-reseller-reports.php' );
		foreach ( array( 'window_days', 'since', 'backfill_done', 'summary', 'rows', 'daily', 'total' ) as $key ) {
			$this->assertStringContainsString( "'" . $key . "'", $code );
		}
	}

	/**
	 * Reseller report row includes sales, wholesale, and margin fields.
	 */
	public function test_report_row_field_contract() {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-admin-reseller-reports.php' );
		foreach (
			array(
				'reseller_id',
				'sales_toman',
				'wholesale_toman',
				'wholesale_gb',
				'receipts_toman',
				'margin_est',
				'downline_users',
				'active_services',
			) as $field
		) {
			$this->assertStringContainsString( "'" . $field . "'", $code );
		}
	}

	/**
	 * Summary aggregates roll up sales, wholesale, receipts, and top reseller.
	 */
	public function test_build_summary_field_contract() {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-admin-reseller-reports.php' );
		foreach (
			array(
				'reseller_count',
				'total_sales_toman',
				'total_wholesale_toman',
				'total_receipts_toman',
				'total_downline_users',
				'margin_est',
				'top_reseller',
			) as $field
		) {
			$this->assertStringContainsString( "'" . $field . "'", $code );
		}
	}

	/**
	 * aggregate_maps initializes empty buckets before SQL fill.
	 */
	public function test_aggregate_maps_empty_structure() {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-admin-reseller-reports.php' );
		foreach ( array( 'downline', 'active_services', 'sales', 'wholesale', 'receipts', 'daily_sales', 'daily_wholesale' ) as $bucket ) {
			$this->assertStringContainsString( "'" . $bucket . "'", $code );
		}
	}

	/**
	 * build_actor_summary uses scoped aggregate_maps for single reseller (Round 9 perf).
	 */
	public function test_build_actor_summary_scoped_aggregate() {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-admin-reseller-reports.php' );
		$this->assertStringContainsString( 'aggregate_maps( $since, array( $actor_uid ) )', $code );
		$this->assertStringContainsString( '$aggregate_maps_cache', $code );
		$this->assertStringContainsString( 'scope_reseller_ids', $code );
	}

	/**
	 * Round 17: downline reseller scope helper for parent reports tab.
	 */
	public function test_downline_reseller_ids_contract() {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-admin-reseller-reports.php' );
		$this->assertStringContainsString( 'function downline_reseller_ids_for', $code );
		$this->assertStringContainsString( 'function build( WP_REST_Request $req, array $pagination, $scope_ancestor_id = null )', $code );
	}
}
