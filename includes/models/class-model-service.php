<?php
/**
 * Service model.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Service
 */
class SimpleVPBot_Model_Service {

	/**
	 * Table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_services';
	}

	/**
	 * Find active (non soft-deleted) service by id.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function find( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE id = %d AND deleted_at IS NULL',
				$id
			)
		); // phpcs:ignore
	}

	/**
	 * Find by id including soft-deleted rows (internal / admin tooling).
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function find_any( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) ); // phpcs:ignore
	}

	/**
	 * Find by inbound + client email (panel identity).
	 *
	 * @param int    $inbound_id Inbound id.
	 * @param string $email Client email.
	 * @return object|null
	 */
	public static function find_by_inbound_email( $inbound_id, $email, $panel_id = 1 ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE inbound_id = %d AND email = %s AND panel_id = %d AND deleted_at IS NULL LIMIT 1',
				(int) $inbound_id,
				(string) $email,
				(int) $panel_id
			)
		); // phpcs:ignore
	}

	/**
	 * By user.
	 *
	 * @param int $user_id User id.
	 * @return array<int, object>
	 */
	public static function by_user( $user_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE user_id = %d AND deleted_at IS NULL ORDER BY id DESC',
				$user_id
			)
		); // phpcs:ignore
	}

	/**
	 * Count active for user (not expired if expires set).
	 *
	 * @param int $user_id User id.
	 * @return int
	 */
	public static function count_active( $user_id ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table() . ' WHERE user_id = %d AND deleted_at IS NULL AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())',
				$user_id
			)
		); // phpcs:ignore
	}

	/**
	 * Insert.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return int
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$wpdb->insert( self::table(), $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update.
	 *
	 * @param int                  $id Id.
	 * @param array<string, mixed> $data Data.
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$wpdb->update( self::table(), $data, array( 'id' => $id ) );
	}

	/**
	 * Soft-delete: mark deleted_at, clear FK references (transactions, transfer codes).
	 * Does not remove the row from DB.
	 *
	 * @param int $id svp_services.id.
	 * @return bool True if a row was soft-deleted.
	 */
	public static function soft_delete( $id ) {
		global $wpdb;
		$sid = (int) $id;
		if ( $sid < 1 ) {
			return false;
		}
		$tbl = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tbl} WHERE id = %d AND deleted_at IS NULL LIMIT 1", $sid ) );
		if ( $exists !== $sid ) {
			return false;
		}
		$tx_tbl = SimpleVPBot_Model_Transaction::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->update( $tx_tbl, array( 'service_id' => null ), array( 'service_id' => $sid ) );
		if ( class_exists( 'SimpleVPBot_Service_Transfer' ) ) {
			SimpleVPBot_Service_Transfer::ensure_table();
			$codes = SimpleVPBot_Service_Transfer::codes_table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->delete( $codes, array( 'service_id' => $sid ) );
		}
		$now = current_time( 'mysql', 1 );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->update( $tbl, array( 'deleted_at' => $now ), array( 'id' => $sid ) );
		return $wpdb->rows_affected() > 0;
	}

	/**
	 * Soft-delete service (alias for {@see soft_delete}).
	 *
	 * @param int $id svp_services.id.
	 * @return bool
	 */
	public static function delete_row( $id ) {
		return self::soft_delete( $id );
	}

	/**
	 * All active services for cron.
	 *
	 * @return array<int, object>
	 */
	public static function all() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' WHERE deleted_at IS NULL' ); // phpcs:ignore
	}

	/**
	 * Transfer service ownership to another svp user.
	 *
	 * @param int $service_id svp_services.id.
	 * @param int $target_user_id svp_users.id of new owner.
	 * @return array{ok:bool, reason:string, previous_user_id?:int}
	 */
	public static function transfer_to( $service_id, $target_user_id ) {
		$sid = (int) $service_id;
		$tid = (int) $target_user_id;
		if ( $sid < 1 || $tid < 1 ) {
			return array( 'ok' => false, 'reason' => 'bad_params' );
		}
		$svc = self::find( $sid );
		if ( ! $svc ) {
			return array( 'ok' => false, 'reason' => 'service_not_found' );
		}
		$target = SimpleVPBot_Model_User::find( $tid );
		if ( ! $target ) {
			return array( 'ok' => false, 'reason' => 'target_not_found' );
		}
		if ( 'blocked' === (string) $target->status ) {
			return array( 'ok' => false, 'reason' => 'target_blocked' );
		}
		$prev = (int) $svc->user_id;
		if ( $prev === $tid ) {
			return array( 'ok' => true, 'reason' => 'noop', 'previous_user_id' => $prev );
		}
		self::update( $sid, array( 'user_id' => $tid ) );
		return array( 'ok' => true, 'reason' => 'transferred', 'previous_user_id' => $prev );
	}

	/**
	 * Is L2TP service.
	 *
	 * @param object $svc Service row.
	 * @return bool
	 */
	public static function is_l2tp( $svc ) {
		return is_object( $svc ) && 'l2tp' === (string) ( $svc->service_type ?? 'xray' );
	}

	/**
	 * Active plan for Xray renew / add-volume pricing: row plan_id if valid, else settings default.
	 *
	 * @param object $svc Service row.
	 * @return object|null Plan row or null.
	 */
	public static function effective_plan_for_pricing( $svc ) {
		if ( ! is_object( $svc ) || self::is_l2tp( $svc ) ) {
			return null;
		}
		$pid = (int) ( $svc->plan_id ?? 0 );
		if ( $pid > 0 ) {
			$p = SimpleVPBot_Model_Plan::find( $pid );
			if ( $p && (int) $p->active ) {
				return $p;
			}
		}
		$fb = (int) SimpleVPBot_Settings::get( 'default_service_plan_id', 0 );
		if ( $fb > 0 ) {
			$p = SimpleVPBot_Model_Plan::find( $fb );
			if ( $p && (int) $p->active ) {
				return $p;
			}
		}
		return null;
	}

	/**
	 * @param object $svc Service row.
	 * @return int Plan id or 0.
	 */
	public static function effective_plan_id_for_pricing( $svc ) {
		$p = self::effective_plan_for_pricing( $svc );
		return $p ? (int) $p->id : 0;
	}

	/**
	 * Count services on a panel (for admin delete guard).
	 *
	 * @param int $panel_id svp_panels.id.
	 * @return int
	 */
	public static function count_for_panel( $panel_id ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table() . ' WHERE panel_id = %d AND deleted_at IS NULL',
				(int) $panel_id
			)
		); // phpcs:ignore
	}

	/**
	 * Decrypted L2TP credentials for a service, or null.
	 *
	 * @param object $svc Service row.
	 * @return array{username:string,password:string,psk:string,host:string,server:object}|null
	 */
	public static function l2tp_credentials( $svc ) {
		if ( ! self::is_l2tp( $svc ) || empty( $svc->l2tp_server_id ) ) {
			return null;
		}
		$srv_row = SimpleVPBot_Model_L2TP_Server::find( (int) $svc->l2tp_server_id );
		if ( ! $srv_row ) {
			return null;
		}
		$srv = SimpleVPBot_Model_L2TP_Server::decrypted( $srv_row );
		$pwd = '';
		if ( ! empty( $svc->l2tp_password_enc ) ) {
			$pwd = (string) SimpleVPBot_Secret_Box::decrypt( (string) $svc->l2tp_password_enc );
		}
		return array(
			'username' => (string) ( $svc->l2tp_username ?? '' ),
			'password' => $pwd,
			'psk'      => (string) ( $srv->l2tp_psk ?? '' ),
			'host'     => (string) ( $srv->l2tp_host ?? $srv->ssh_host ?? '' ),
			'server'   => $srv,
		);
	}

	/**
	 * Xray services on a given 3x-ui panel id.
	 *
	 * @param int $panel_id svp_panels.id.
	 * @return int
	 */
	public static function count_by_panel_id( $panel_id ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table() . ' WHERE panel_id = %d AND service_type = %s AND deleted_at IS NULL',
				(int) $panel_id,
				'xray'
			)
		); // phpcs:ignore
	}
}
