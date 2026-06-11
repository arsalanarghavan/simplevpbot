<?php
/**
 * Behavioral IDOR tests: reseller A cannot access peer-owned services.
 *
 * @package SimpleVPBot
 */

require_once dirname( __DIR__ ) . '/fixtures/class-service-idor-fixture.php';

/**
 * Class BotAdminServiceIdorIntegrationTest
 */
class BotAdminServiceIdorIntegrationTest extends WP_UnitTestCase {

	/** @var SimpleVPBot_Service_Idor_Fixture|null */
	private $fixture;

	/**
	 * @inheritDoc
	 */
	protected function setUp(): void {
		parent::setUp();
		if ( ! class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) || ! class_exists( 'SimpleVPBot_Model_Service' ) ) {
			$this->markTestSkipped( 'SimpleVPBot service scope classes not loaded.' );
		}
		$this->fixture = SimpleVPBot_Service_Idor_Fixture::seed( 883000000 + random_int( 1000, 99999 ) );
	}

	/**
	 * @inheritDoc
	 */
	protected function tearDown(): void {
		if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			SimpleVPBot_Bot_Reseller_Scope::set_acting_admin_user( 0 );
		}
		if ( $this->fixture instanceof SimpleVPBot_Service_Idor_Fixture ) {
			$this->fixture->tear_down();
		}
		parent::tearDown();
	}

	/**
	 * Parent reseller (dual-role) cannot access peer-owned service.
	 */
	public function test_cross_tenant_service_access_forbidden() {
		$peer_sid = (int) $this->fixture->peer_service_id;
		$this->assertGreaterThan( 0, $peer_sid );

		SimpleVPBot_Bot_Reseller_Scope::set_acting_admin_user( (int) $this->fixture->tree->parent_id );
		$this->assertFalse(
			SimpleVPBot_Bot_Reseller_Scope::bot_admin_may_access_service( $peer_sid ),
			'Parent reseller must not access peer-owned service'
		);
	}

	/**
	 * Parent reseller may access service owned by downline user.
	 */
	public function test_downline_service_access_allowed() {
		$own_sid = (int) $this->fixture->downline_service_id;
		$this->assertGreaterThan( 0, $own_sid );

		SimpleVPBot_Bot_Reseller_Scope::set_acting_admin_user( (int) $this->fixture->tree->parent_id );
		$this->assertTrue(
			SimpleVPBot_Bot_Reseller_Scope::bot_admin_may_access_service( $own_sid ),
			'Parent reseller should access downline user service'
		);
	}

	/**
	 * Service pick path delegates through guarded callback (contract).
	 */
	public function test_service_pick_uses_guarded_delegate() {
		$pnl = (string) file_get_contents(
			dirname( __DIR__, 2 ) . '/includes/bot/handlers/class-handler-admin-pnl.php'
		);
		$this->assertStringContainsString( 'bot_admin_delegate_service_callback', $pnl );
		$this->assertStringContainsString(
			'self::bot_admin_delegate_service_callback( $platform, $chat_id, $from_id, $pick_sid',
			$pnl
		);
	}
}
