<?php
/**
 * Seed peer-owned services for cross-tenant service IDOR integration tests.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

require_once __DIR__ . '/class-reseller-tree-fixture.php';

/**
 * Class SimpleVPBot_Service_Idor_Fixture
 */
class SimpleVPBot_Service_Idor_Fixture {

	/** @var SimpleVPBot_Reseller_Tree_Fixture */
	public $tree;

	/** @var int */
	public $peer_service_id = 0;

	/** @var int */
	public $downline_service_id = 0;

	/** @var int */
	public $downline_user_id = 0;

	/** @var array<int, int> */
	private $created_service_ids = array();

	/** @var array<int, int> */
	private $created_user_ids = array();

	/**
	 * @param int $base Telegram id base.
	 * @return self
	 */
	public static function seed( $base = 883000000 ) {
		$f       = new self();
		$f->tree = SimpleVPBot_Reseller_Tree_Fixture::seed( (int) $base );

		if ( ! class_exists( 'SimpleVPBot_Model_Service' ) || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return $f;
		}

		$peer_end = (int) $f->tree->end_user_id;
		if ( $peer_end > 0 ) {
			$f->peer_service_id = $f->insert_service( $peer_end );
		}

		$f->downline_user_id = SimpleVPBot_Model_User::insert(
			array(
				'tg_user_id'   => (int) $base + 50,
				'bale_user_id' => null,
				'username'     => 'itest_downline_' . (int) $base,
				'first_name'   => 'ITest',
				'last_name'    => 'Downline',
				'phone'        => '',
				'role'         => 'user',
				'balance'      => 0,
				'status'       => 'approved',
				'admin_mode'   => 0,
				'invited_by'   => (int) $f->tree->child_id,
				'wp_user_id'   => null,
			)
		);
		if ( $f->downline_user_id > 0 ) {
			$f->created_user_ids[] = $f->downline_user_id;
			$f->downline_service_id = $f->insert_service( $f->downline_user_id );
		}

		return $f;
	}

	/**
	 * @param int $user_id Owner user id.
	 * @return int Service id.
	 */
	private function insert_service( $user_id ) {
		$uid = (int) $user_id;
		if ( $uid < 1 ) {
			return 0;
		}
		$email = 'itest_svc_' . $uid . '@local.test';
		$sid   = SimpleVPBot_Model_Service::insert(
			array(
				'user_id'         => $uid,
				'panel_id'        => 1,
				'inbound_id'      => 1,
				'xui_client_id'   => 'itest-' . $uid,
				'xui_client_uuid' => 'itest-' . $uid,
				'email'           => $email,
				'remark'          => 'ITest Service',
				'plan_id'         => 1,
				'expires_at'      => gmdate( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS ),
				'total_traffic'   => 1073741824,
				'sub_id'          => 'itest' . $uid,
				'provision_type'  => 'plan',
				'service_type'    => 'xray',
			)
		);
		if ( $sid > 0 ) {
			$this->created_service_ids[] = $sid;
		}
		return (int) $sid;
	}

	/**
	 * Delete seeded services and extra users; tear down reseller tree.
	 */
	public function tear_down() {
		if ( class_exists( 'SimpleVPBot_Model_Service' ) ) {
			foreach ( array_reverse( $this->created_service_ids ) as $id ) {
				SimpleVPBot_Model_Service::soft_delete( (int) $id );
			}
		}
		if ( class_exists( 'SimpleVPBot_Model_User' ) && ! empty( $this->created_user_ids ) ) {
			global $wpdb;
			$t = SimpleVPBot_Model_User::table();
			foreach ( array_reverse( $this->created_user_ids ) as $id ) {
				$uid = (int) $id;
				if ( $uid < 1 ) {
					continue;
				}
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->delete( $t, array( 'id' => $uid ) );
			}
		}
		if ( $this->tree instanceof SimpleVPBot_Reseller_Tree_Fixture ) {
			$this->tree->tear_down();
		}
	}
}
