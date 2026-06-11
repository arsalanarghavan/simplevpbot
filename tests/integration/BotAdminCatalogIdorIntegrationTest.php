<?php
/**
 * Behavioral IDOR tests: reseller A cannot mutate peer-owned catalog entities.
 *
 * @package SimpleVPBot
 */

require_once dirname( __DIR__ ) . '/fixtures/class-catalog-idor-fixture.php';

/**
 * Class BotAdminCatalogIdorIntegrationTest
 */
class BotAdminCatalogIdorIntegrationTest extends WP_UnitTestCase {

	/** @var SimpleVPBot_Catalog_Idor_Fixture|null */
	private $fixture;

	/**
	 * @inheritDoc
	 */
	protected function setUp(): void {
		parent::setUp();
		if ( ! class_exists( 'SimpleVPBot_Bot_Admin_Mutate' ) || ! class_exists( 'SimpleVPBot_Model_Plan' ) ) {
			$this->markTestSkipped( 'SimpleVPBot catalog mutate classes not loaded.' );
		}
		$this->fixture = SimpleVPBot_Catalog_Idor_Fixture::seed( 882000000 + random_int( 1000, 99999 ) );
	}

	/**
	 * @inheritDoc
	 */
	protected function tearDown(): void {
		if ( $this->fixture instanceof SimpleVPBot_Catalog_Idor_Fixture ) {
			$this->fixture->tear_down();
		}
		parent::tearDown();
	}

	/**
	 * Parent reseller cannot toggle peer-owned plan.
	 */
	public function test_cross_tenant_plan_toggle_forbidden() {
		$actor  = (int) $this->fixture->tree->parent_id;
		$plan_id = (int) $this->fixture->peer_plan_id;
		$this->assertGreaterThan( 0, $plan_id );

		$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
			$actor,
			'plan',
			array(
				'plan_action' => 'toggle',
				'plan_id'     => $plan_id,
			)
		);
		$this->assertEmpty( $result['ok'] );
		$this->assertContains(
			(string) ( $result['code'] ?? $result['message'] ?? '' ),
			array( 'forbidden', 'forbidden_scope', 'forbidden_perm' ),
			'Expected forbidden for cross-tenant plan toggle'
		);
	}

	/**
	 * Parent reseller cannot delete peer-owned plan.
	 */
	public function test_cross_tenant_plan_delete_forbidden() {
		$actor   = (int) $this->fixture->tree->parent_id;
		$plan_id = (int) $this->fixture->peer_plan_id;
		$this->assertGreaterThan( 0, $plan_id );

		$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
			$actor,
			'plan',
			array(
				'plan_action' => 'delete',
				'plan_id'     => $plan_id,
			)
		);
		$this->assertEmpty( $result['ok'] );
	}

	/**
	 * Parent reseller cannot update peer-owned card.
	 */
	public function test_cross_tenant_card_update_forbidden() {
		$actor   = (int) $this->fixture->tree->parent_id;
		$card_id = (int) $this->fixture->peer_card_id;
		$this->assertGreaterThan( 0, $card_id );

		$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
			$actor,
			'card_update',
			array(
				'edit_id'     => $card_id,
				'card_number' => '6037990000000099',
				'holder_name' => 'Hacked',
				'bank_name'   => 'X',
				'method_key'  => 'c2c',
				'active'      => 1,
			)
		);
		$this->assertEmpty( $result['ok'] );
		$this->assertSame( 'forbidden_scope', (string) ( $result['message'] ?? '' ) );
	}

	/**
	 * Parent reseller cannot delete peer-owned card.
	 */
	public function test_cross_tenant_card_delete_forbidden() {
		$actor   = (int) $this->fixture->tree->parent_id;
		$card_id = (int) $this->fixture->peer_card_id;
		$this->assertGreaterThan( 0, $card_id );

		$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
			$actor,
			'card_delete',
			array(
				'edit_id' => $card_id,
			)
		);
		$this->assertEmpty( $result['ok'] );
	}

	/**
	 * Parent reseller cannot toggle category that contains peer foreign plans.
	 */
	public function test_cross_tenant_category_toggle_forbidden() {
		$actor = (int) $this->fixture->tree->parent_id;
		$pc_id = (int) $this->fixture->peer_category_id;
		$this->assertGreaterThan( 0, $pc_id );

		$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
			$actor,
			'plan_category',
			array(
				'pc_action' => 'toggle',
				'pc_id'     => $pc_id,
			)
		);
		$this->assertEmpty( $result['ok'] );
		$this->assertContains(
			(string) ( $result['code'] ?? '' ),
			array( 'category_foreign_plans', 'panel_not_allowed', 'forbidden_perm' )
		);
	}

	/**
	 * Catalog scope guard rejects peer plan for parent chat context.
	 */
	public function test_guard_plan_blocks_cross_tenant() {
		if ( ! class_exists( 'SimpleVPBot_Bot_Admin_Catalog_Scope' ) || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			$this->markTestSkipped( 'Catalog scope not available.' );
		}
		$parent = SimpleVPBot_Model_User::find( (int) $this->fixture->tree->parent_id );
		$this->assertNotNull( $parent );
		$tg = (int) ( $parent->tg_user_id ?? 0 );
		$this->assertGreaterThan( 0, $tg );

		$ok = SimpleVPBot_Bot_Admin_Catalog_Scope::guard_plan(
			'telegram',
			$tg,
			(int) $this->fixture->peer_plan_id
		);
		$this->assertFalse( $ok );
	}

	/**
	 * Parent reseller cannot update peer-owned plan.
	 */
	public function test_cross_tenant_plan_update_forbidden() {
		$actor   = (int) $this->fixture->tree->parent_id;
		$plan_id = (int) $this->fixture->peer_plan_id;
		$this->assertGreaterThan( 0, $plan_id );

		$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
			$actor,
			'plan',
			array(
				'plan_action'   => 'update',
				'plan_id'       => $plan_id,
				'name'          => 'Hacked Plan',
				'category'      => 'hacked',
				'duration_days' => 30,
				'traffic_gb'    => 10,
				'price'         => 1,
				'inbound_id'    => 1,
				'clients_count' => 1,
				'plan_active'   => 1,
			)
		);
		$this->assertEmpty( $result['ok'] );
		$this->assertContains(
			(string) ( $result['code'] ?? $result['message'] ?? '' ),
			array( 'forbidden', 'forbidden_scope', 'forbidden_perm' )
		);
	}

	/**
	 * Parent reseller cannot delete peer-owned category.
	 */
	public function test_cross_tenant_category_delete_forbidden() {
		$actor = (int) $this->fixture->tree->parent_id;
		$pc_id = (int) $this->fixture->peer_category_id;
		$this->assertGreaterThan( 0, $pc_id );

		$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
			$actor,
			'plan_category',
			array(
				'pc_action' => 'delete',
				'pc_id'     => $pc_id,
			)
		);
		$this->assertEmpty( $result['ok'] );
		$this->assertContains(
			(string) ( $result['code'] ?? $result['message'] ?? '' ),
			array( 'category_foreign_plans', 'panel_not_allowed', 'forbidden_perm', 'forbidden_scope', 'forbidden' )
		);
	}

	/**
	 * Parent reseller cannot update peer-owned category.
	 */
	public function test_cross_tenant_category_update_forbidden() {
		$actor = (int) $this->fixture->tree->parent_id;
		$pc_id = (int) $this->fixture->peer_category_id;
		$this->assertGreaterThan( 0, $pc_id );

		$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
			$actor,
			'plan_category',
			array(
				'pc_action' => 'update',
				'pc_id'     => $pc_id,
				'pc_label'  => 'Hacked Category',
				'pc_sort'   => 99,
				'pc_active' => 1,
			)
		);
		$this->assertEmpty( $result['ok'] );
		$this->assertContains(
			(string) ( $result['code'] ?? $result['message'] ?? '' ),
			array( 'category_foreign_plans', 'panel_not_allowed', 'forbidden_perm', 'forbidden_scope', 'forbidden' )
		);
	}

	/**
	 * Catalog scope guard rejects peer card for parent chat context.
	 */
	public function test_guard_card_blocks_cross_tenant() {
		if ( ! class_exists( 'SimpleVPBot_Bot_Admin_Catalog_Scope' ) || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			$this->markTestSkipped( 'Catalog scope not available.' );
		}
		$parent = SimpleVPBot_Model_User::find( (int) $this->fixture->tree->parent_id );
		$this->assertNotNull( $parent );
		$tg = (int) ( $parent->tg_user_id ?? 0 );
		$this->assertGreaterThan( 0, $tg );

		$ok = SimpleVPBot_Bot_Admin_Catalog_Scope::guard_card(
			'telegram',
			$tg,
			(int) $this->fixture->peer_card_id
		);
		$this->assertFalse( $ok );
	}
}
