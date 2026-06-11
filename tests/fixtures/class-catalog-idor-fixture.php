<?php
/**
 * Seed peer-owned catalog rows for cross-tenant IDOR integration tests.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

require_once __DIR__ . '/class-reseller-tree-fixture.php';

/**
 * Class SimpleVPBot_Catalog_Idor_Fixture
 */
class SimpleVPBot_Catalog_Idor_Fixture {

	/** @var SimpleVPBot_Reseller_Tree_Fixture */
	public $tree;

	/** @var int */
	public $peer_plan_id = 0;

	/** @var int */
	public $peer_card_id = 0;

	/** @var int */
	public $peer_category_id = 0;

	/** @var array<int, int> */
	private $created_plan_ids = array();

	/** @var array<int, int> */
	private $created_card_ids = array();

	/** @var array<int, int> */
	private $created_category_ids = array();

	/**
	 * @param int $base Telegram id base.
	 * @return self
	 */
	public static function seed( $base = 882000000 ) {
		$f        = new self();
		$f->tree  = SimpleVPBot_Reseller_Tree_Fixture::seed( (int) $base );
		$peer_id  = (int) $f->tree->peer_id;
		$slug     = 'itest_cat_' . (int) $base;

		if ( class_exists( 'SimpleVPBot_Model_Plan_Category' ) ) {
			$f->peer_category_id = SimpleVPBot_Model_Plan_Category::insert(
				array(
					'panel_id'   => 1,
					'slug'       => $slug,
					'label'      => 'ITest Category',
					'sort_order' => 0,
					'active'     => 1,
				)
			);
			if ( $f->peer_category_id > 0 ) {
				$f->created_category_ids[] = $f->peer_category_id;
			}
		}

		if ( class_exists( 'SimpleVPBot_Model_Plan' ) && $f->peer_category_id > 0 ) {
			$f->peer_plan_id = SimpleVPBot_Model_Plan::insert(
				array(
					'name'              => 'ITest Peer Plan',
					'category'          => $slug,
					'duration_days'     => 30,
					'traffic_gb'        => 50,
					'price'             => 100000,
					'pricing_type'      => 'fixed',
					'clients_count'     => 1,
					'inbound_id'        => 1,
					'panel_id'          => 1,
					'owner_svp_user_id' => $peer_id,
					'sort_order'        => 0,
					'service_type'      => 'xray',
					'active'            => 1,
				)
			);
			if ( $f->peer_plan_id > 0 ) {
				$f->created_plan_ids[] = $f->peer_plan_id;
			}
		}

		if ( class_exists( 'SimpleVPBot_Model_Card' ) ) {
			$f->peer_card_id = SimpleVPBot_Model_Card::insert(
				array(
					'owner_svp_user_id' => $peer_id,
					'card_number'       => '6037990000000001',
					'holder_name'       => 'ITest Peer',
					'bank_name'         => 'Test Bank',
					'method_key'        => 'c2c',
					'daily_limit'       => 0,
					'priority'          => 0,
					'note'              => '',
					'active'            => 1,
				)
			);
			if ( $f->peer_card_id > 0 ) {
				$f->created_card_ids[] = $f->peer_card_id;
			}
		}

		return $f;
	}

	/**
	 * Delete seeded catalog rows and reseller tree.
	 */
	public function tear_down() {
		if ( class_exists( 'SimpleVPBot_Model_Plan' ) ) {
			foreach ( array_reverse( $this->created_plan_ids ) as $id ) {
				SimpleVPBot_Model_Plan::delete( (int) $id );
			}
		}
		if ( class_exists( 'SimpleVPBot_Model_Card' ) ) {
			foreach ( array_reverse( $this->created_card_ids ) as $id ) {
				SimpleVPBot_Model_Card::delete( (int) $id );
			}
		}
		if ( class_exists( 'SimpleVPBot_Model_Plan_Category' ) ) {
			foreach ( array_reverse( $this->created_category_ids ) as $id ) {
				SimpleVPBot_Model_Plan_Category::delete( (int) $id );
			}
		}
		if ( $this->tree instanceof SimpleVPBot_Reseller_Tree_Fixture ) {
			$this->tree->tear_down();
		}
	}
}
