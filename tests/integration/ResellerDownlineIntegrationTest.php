<?php
/**
 * Behavioral integration tests for parent sub-reseller create and scope.
 *
 * Run with wp-env: npx @wordpress/env start && WP_TESTS_DIR=$(npx @wordpress/env run cli wp eval 'echo getenv("WP_TESTS_DIR");') php vendor/bin/phpunit -c phpunit.integration.xml.dist
 *
 * @package SimpleVPBot
 */

require_once dirname( __DIR__ ) . '/fixtures/class-reseller-tree-fixture.php';

/**
 * Class ResellerDownlineIntegrationTest
 */
class ResellerDownlineIntegrationTest extends WP_UnitTestCase {

	/** @var SimpleVPBot_Reseller_Tree_Fixture|null */
	private $fixture;

	/**
	 * @inheritDoc
	 */
	protected function setUp(): void {
		parent::setUp();
		if ( ! class_exists( 'SimpleVPBot_Model_User' ) || ! class_exists( 'SimpleVPBot_Dashboard_Admin_Mutations' ) ) {
			$this->markTestSkipped( 'SimpleVPBot classes not loaded.' );
		}
		$this->fixture = SimpleVPBot_Reseller_Tree_Fixture::seed( 881000000 + (int) ( microtime( true ) * 1000 ) % 100000 );
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
	 * Parent reseller can create sub-reseller with bot id; invited_by = parent.
	 */
	public function test_parent_creates_sub_reseller_via_bot_id() {
		$parent = $this->fixture->parent_id;
		$tg     = 881000000 + random_int( 10000, 99999 );
		$res    = SimpleVPBot_Dashboard_Admin_Mutations::apply(
			'user_manual_create',
			array(
				'role'                => 'reseller',
				'status'              => 'approved',
				'tg_user_id'          => $tg,
				'__actor_svp_user_id' => $parent,
				'invited_by'          => $parent,
			)
		);
		$this->assertNotEmpty( $res['ok'] );
		$uid = (int) ( $res['user_id'] ?? 0 );
		$this->assertGreaterThan( 0, $uid );
		$row = SimpleVPBot_Model_User::find( $uid );
		$this->assertNotNull( $row );
		$this->assertSame( 'reseller', (string) ( $row->role ?? '' ) );
		$this->assertSame( $parent, (int) ( $row->invited_by ?? 0 ) );
	}

	/**
	 * Parent cannot moderate users outside downline tree.
	 */
	public function test_parent_cannot_moderate_outside_downline() {
		$parent = $this->fixture->parent_id;
		$peer   = $this->fixture->end_user_id;
		$this->assertFalse(
			SimpleVPBot_Bot_Reseller_Scope::reseller_may_moderate_user_for( $parent, $peer )
		);
		$this->assertTrue(
			SimpleVPBot_Bot_Reseller_Scope::reseller_may_moderate_user_for( $parent, $this->fixture->child_id )
		);
	}

	/**
	 * downline_reseller_ids_for includes child and grandchild, not peer.
	 */
	public function test_downline_reseller_ids_scope() {
		$ids = SimpleVPBot_Admin_Reseller_Reports::downline_reseller_ids_for( $this->fixture->parent_id );
		$this->assertContains( $this->fixture->child_id, $ids );
		$this->assertContains( $this->fixture->grandchild_id, $ids );
		$this->assertNotContains( $this->fixture->peer_id, $ids );
		$this->assertNotContains( $this->fixture->parent_id, $ids );
	}

	/**
	 * Parent may WP-provision direct child; peer is forbidden.
	 */
	public function test_parent_wp_provision_direct_child_only() {
		$parent = $this->fixture->parent_id;
		$child  = $this->fixture->child_id;
		$peer   = $this->fixture->peer_id;
		$ok     = SimpleVPBot_Dashboard_Admin_Mutations::apply(
			'reseller_wp_provision',
			array(
				'svp_user_id'         => $child,
				'wp_username'         => 'itest_child_wp_' . wp_generate_password( 6, false, false ),
				'wp_password'         => 'secret12',
				'__actor_svp_user_id' => $parent,
			)
		);
		$this->assertNotEmpty( $ok['ok'] );

		$bad = SimpleVPBot_Dashboard_Admin_Mutations::apply(
			'reseller_wp_provision',
			array(
				'svp_user_id'         => $peer,
				'wp_username'         => 'itest_peer_wp_' . wp_generate_password( 6, false, false ),
				'wp_password'         => 'secret12',
				'__actor_svp_user_id' => $parent,
			)
		);
		$this->assertEmpty( $bad['ok'] );
		$this->assertSame( 'forbidden_scope', (string) ( $bad['message'] ?? '' ) );
	}
}
