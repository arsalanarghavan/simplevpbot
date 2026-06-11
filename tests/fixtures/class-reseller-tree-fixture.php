<?php
/**
 * Seed a small reseller tree for integration tests.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

/**
 * Class SimpleVPBot_Reseller_Tree_Fixture
 */
class SimpleVPBot_Reseller_Tree_Fixture {

	/** @var int */
	public $parent_id = 0;

	/** @var int */
	public $child_id = 0;

	/** @var int */
	public $grandchild_id = 0;

	/** @var int */
	public $peer_id = 0;

	/** @var int */
	public $end_user_id = 0;

	/** @var array<int, int> */
	private $created_ids = array();

	/**
	 * @param int $base Telegram id base (unique per test run).
	 * @return self
	 */
	public static function seed( $base = 880000000 ) {
		$base = (int) $base;
		if ( $base < 1 ) {
			$base = 880000000;
		}
		$f = new self();

		$f->parent_id = $f->insert_reseller(
			array(
				'tg_user_id' => $base + 1,
				'username'   => 'itest_parent_' . $base,
			)
		);
		$f->child_id = $f->insert_reseller(
			array(
				'tg_user_id'  => $base + 2,
				'username'    => 'itest_child_' . $base,
				'invited_by'  => $f->parent_id,
			)
		);
		$f->grandchild_id = $f->insert_reseller(
			array(
				'tg_user_id'  => $base + 3,
				'username'    => 'itest_grand_' . $base,
				'invited_by'  => $f->child_id,
			)
		);
		$f->peer_id = $f->insert_reseller(
			array(
				'tg_user_id' => $base + 4,
				'username'   => 'itest_peer_' . $base,
			)
		);
		$f->end_user_id = $f->insert_user(
			array(
				'tg_user_id' => $base + 5,
				'username'   => 'itest_user_' . $base,
				'invited_by' => $f->peer_id,
			)
		);

		return $f;
	}

	/**
	 * @param array<string, mixed> $overrides Row overrides.
	 * @return int
	 */
	private function insert_reseller( array $overrides ) {
		$row = array_merge(
			array(
				'bale_user_id' => null,
				'first_name'   => 'ITest',
				'last_name'    => 'Reseller',
				'phone'        => '',
				'role'         => 'reseller',
				'balance'      => 0,
				'status'       => 'approved',
				'admin_mode'   => 0,
				'invited_by'   => null,
				'wp_user_id'   => null,
			),
			$overrides
		);
		$id = SimpleVPBot_Model_User::insert( $row );
		if ( $id > 0 ) {
			$this->created_ids[] = $id;
		}
		return $id;
	}

	/**
	 * @param array<string, mixed> $overrides Row overrides.
	 * @return int
	 */
	private function insert_user( array $overrides ) {
		$row = array_merge(
			array(
				'bale_user_id' => null,
				'first_name'   => 'ITest',
				'last_name'    => 'User',
				'phone'        => '',
				'role'         => 'user',
				'balance'      => 0,
				'status'       => 'approved',
				'admin_mode'   => 0,
				'invited_by'   => null,
				'wp_user_id'   => null,
			),
			$overrides
		);
		$id = SimpleVPBot_Model_User::insert( $row );
		if ( $id > 0 ) {
			$this->created_ids[] = $id;
		}
		return $id;
	}

	/**
	 * Delete seeded rows (best-effort).
	 */
	public function tear_down() {
		global $wpdb;
		$ids = array_reverse( array_values( array_unique( $this->created_ids ) ) );
		if ( empty( $ids ) || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return;
		}
		$t = SimpleVPBot_Model_User::table();
		foreach ( $ids as $id ) {
			$uid = (int) $id;
			if ( $uid < 1 ) {
				continue;
			}
			$row = SimpleVPBot_Model_User::find( $uid );
			if ( $row && ! empty( $row->wp_user_id ) ) {
				$wp_id = (int) $row->wp_user_id;
				if ( $wp_id > 0 && function_exists( 'wp_delete_user' ) ) {
					require_once ABSPATH . 'wp-admin/includes/user.php';
					wp_delete_user( $wp_id );
				}
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->delete( $t, array( 'id' => $uid ) );
		}
	}
}
