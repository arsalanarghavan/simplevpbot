<?php
/**
 * Contract tests for marketing lifecycle module.
 *
 * @package SimpleVPBot
 */

use PHPUnit\Framework\TestCase;

/**
 * Class MarketingLifecycleTest
 */
class MarketingLifecycleTest extends TestCase {

	/**
	 * Core classes autoload.
	 */
	public function test_core_classes_exist() {
		$this->assertTrue( class_exists( 'SimpleVPBot_Model_Marketing_Rule' ) );
		$this->assertTrue( class_exists( 'SimpleVPBot_Model_Marketing_Offer' ) );
		$this->assertTrue( class_exists( 'SimpleVPBot_Marketing_Lifecycle_Analytics' ) );
		$this->assertTrue( class_exists( 'SimpleVPBot_Marketing_Automation' ) );
	}

	/**
	 * Segment keys are fixed set.
	 */
	public function test_segment_keys() {
		$this->assertSame(
			array(
				'churned',
				'never_purchased',
				'abandoned_checkout',
				'stale_buy_funnel',
				'expiring_renew',
			),
			SimpleVPBot_Model_Marketing_Rule::SEGMENT_KEYS
		);
	}

	/**
	 * sanitize_segment accepts only known keys.
	 */
	public function test_sanitize_segment() {
		$this->assertSame( 'churned', SimpleVPBot_Model_Marketing_Rule::sanitize_segment( 'churned' ) );
		$this->assertSame( '', SimpleVPBot_Model_Marketing_Rule::sanitize_segment( 'invalid' ) );
	}

	/**
	 * Window days clamp to allowed values.
	 */
	public function test_normalize_window_days() {
		$this->assertSame( 30, SimpleVPBot_Marketing_Lifecycle_Analytics::normalize_window_days( 0 ) );
		$this->assertSame( 7, SimpleVPBot_Marketing_Lifecycle_Analytics::normalize_window_days( 7 ) );
		$this->assertSame( 30, SimpleVPBot_Marketing_Lifecycle_Analytics::normalize_window_days( 365 ) );
	}

	/**
	 * Reseller permission key registered.
	 */
	public function test_reseller_permission_key_present() {
		$this->assertContains( 'marketing.lifecycle', SimpleVPBot_Model_User::RESELLER_PERMISSION_KEYS );
	}

	/**
	 * Deep link parser extracts offer code.
	 */
	public function test_parse_offer_code_from_start() {
		$this->assertSame(
			'WIN-1-AB',
			SimpleVPBot_Marketing_Automation::parse_offer_code_from_start( '/start offer_WIN-1-AB' )
		);
		$this->assertSame( '', SimpleVPBot_Marketing_Automation::parse_offer_code_from_start( '/start ref_12' ) );
	}

	/**
	 * Analytics exposes resolve_rule_for_segment and rule_stats in payload.
	 */
	public function test_analytics_resolve_and_rule_stats_contract() {
		$code = (string) file_get_contents( dirname( __DIR__ ) . '/includes/helpers/class-marketing-lifecycle-analytics.php' );
		$this->assertStringContainsString( 'function resolve_rule_for_segment', $code );
		$this->assertStringContainsString( 'function per_rule_stats', $code );
		$this->assertStringContainsString( "'rule_stats'", $code );
		$this->assertStringContainsString( 'first_pending', $code );
		$this->assertStringContainsString( 'first_paid', $code );
		$rest = (string) file_get_contents( dirname( __DIR__ ) . '/includes/api/class-rest-dashboard.php' );
		$this->assertStringContainsString( 'marketingRuleStats', $rest );
		$this->assertStringContainsString( 'resolve_rule_for_segment', $rest );
		$ui = (string) file_get_contents( dirname( __DIR__ ) . '/frontend/src/components/dashboard-marketing-lifecycle-admin.tsx' );
		$this->assertStringContainsString( 'DashSheetContent', $ui );
		$this->assertStringContainsString( 'viewSegmentUsersFullList', $ui );
	}
}
