<?php
/**
 * Cached X-UI inbound clients per panel (dashboard configs + sync).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Panel_Inbound_Client
 */
class SimpleVPBot_Model_Panel_Inbound_Client {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_panel_inbound_clients';
	}

	/**
	 * Max synced_at for a panel (null if no rows).
	 *
	 * @param int $panel_id Panel id.
	 * @return string|null Datetime string or null.
	 */
	public static function max_synced_at_for_panel( $panel_id ) {
		global $wpdb;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(synced_at) FROM {$t} WHERE panel_id = %d", (int) $panel_id ) );
		return is_string( $row ) && '' !== $row ? $row : null;
	}

	/**
	 * Row count for panel.
	 *
	 * @param int $panel_id Panel id.
	 * @return int
	 */
	public static function count_for_panel( $panel_id ) {
		global $wpdb;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE panel_id = %d", (int) $panel_id ) );
	}

	/**
	 * All rows for one panel (ordered for snapshot grouping).
	 *
	 * @param int $panel_id Panel id.
	 * @return array<int, object>
	 */
	public static function rows_for_panel( $panel_id ) {
		global $wpdb;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t} WHERE panel_id = %d ORDER BY inbound_id ASC, email ASC",
				(int) $panel_id
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Replace all cached clients for one inbound (atomic: delete then insert).
	 *
	 * @param int                             $panel_id       Panel id.
	 * @param int                             $inbound_id     Inbound id.
	 * @param string                          $inbound_remark Inbound remark from panel.
	 * @param string                          $protocol       Lowercase protocol.
	 * @param int                             $port           Port.
	 * @param array<int, array<string,mixed>> $rows           Row arrays (email, xui_client_id, …).
	 */
	public static function replace_inbound_batch( $panel_id, $inbound_id, $inbound_remark, $protocol, $port, array $rows ) {
		global $wpdb;
		$t   = self::table();
		$pid = (int) $panel_id;
		$iid = (int) $inbound_id;
		$wpdb->delete( $t, array( 'panel_id' => $pid, 'inbound_id' => $iid ), array( '%d', '%d' ) );
		$now = current_time( 'mysql', true );
		foreach ( $rows as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$em = isset( $r['email'] ) ? trim( (string) $r['email'] ) : '';
			if ( '' === $em ) {
				continue;
			}
			$wpdb->insert(
				$t,
				array(
					'panel_id'        => $pid,
					'inbound_id'      => $iid,
					'inbound_remark'  => mb_substr( (string) $inbound_remark, 0, 255, 'UTF-8' ),
					'protocol'        => mb_substr( strtolower( (string) $protocol ), 0, 32, 'UTF-8' ),
					'port'            => (int) $port,
					'email'           => $em,
					'xui_client_id'   => mb_substr( (string) ( $r['xui_client_id'] ?? '' ), 0, 191, 'UTF-8' ),
					'remark'          => mb_substr( (string) ( $r['remark'] ?? '' ), 0, 255, 'UTF-8' ),
					'comment'         => mb_substr( (string) ( $r['comment'] ?? '' ), 0, 500, 'UTF-8' ),
					'tg_id'           => mb_substr( (string) ( $r['tg_id'] ?? '' ), 0, 64, 'UTF-8' ),
					'sub_id'          => mb_substr( (string) ( $r['sub_id'] ?? '' ), 0, 128, 'UTF-8' ),
					'enable'          => ! empty( $r['enable'] ) ? 1 : 0,
					'total_gb'        => (int) ( $r['total_gb'] ?? 0 ),
					'expiry_ms'       => (int) ( $r['expiry_ms'] ?? 0 ),
					'used_bytes'      => (int) ( $r['used_bytes'] ?? 0 ),
					'limit_bytes'     => (int) ( $r['limit_bytes'] ?? 0 ),
					'is_online'       => ! empty( $r['is_online'] ) ? 1 : 0,
					'client_ips_json' => isset( $r['client_ips_json'] ) ? (string) $r['client_ips_json'] : null,
					'client_json'     => isset( $r['client_json'] ) ? (string) $r['client_json'] : null,
					'synced_at'       => $now,
				),
				array(
					'%d',
					'%d',
					'%s',
					'%s',
					'%d',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%d',
					'%d',
					'%d',
					'%d',
					'%d',
					'%d',
					'%s',
					'%s',
					'%s',
				)
			);
		}
	}

	/**
	 * Delete one client row from cache.
	 *
	 * @param int    $panel_id   Panel id.
	 * @param int    $inbound_id Inbound id.
	 * @param string $email      Client email.
	 */
	public static function delete_client( $panel_id, $inbound_id, $email ) {
		global $wpdb;
		$wpdb->delete(
			self::table(),
			array(
				'panel_id'   => (int) $panel_id,
				'inbound_id' => (int) $inbound_id,
				'email'      => (string) $email,
			),
			array( '%d', '%d', '%s' )
		);
	}

	/**
	 * Update numeric / flag fields after a successful panel mutation (partial row).
	 *
	 * @param int                  $panel_id Panel id.
	 * @param int                  $inbound_id Inbound id.
	 * @param string               $email    Client email.
	 * @param array<string, mixed> $fields   Allowed keys: enable, used_bytes, limit_bytes, total_gb, expiry_ms, remark, comment, sub_id, is_online, client_ips_json.
	 */
	public static function patch_cached_client( $panel_id, $inbound_id, $email, array $fields ) {
		global $wpdb;
		$allowed = array(
			'enable'          => '%d',
			'used_bytes'      => '%d',
			'limit_bytes'     => '%d',
			'total_gb'        => '%d',
			'expiry_ms'       => '%d',
			'remark'          => '%s',
			'comment'         => '%s',
			'sub_id'          => '%s',
			'is_online'       => '%d',
			'client_ips_json' => '%s',
			'client_json'     => '%s',
			'xui_client_id'   => '%s',
		);
		$data = array();
		$fmt  = array();
		foreach ( $allowed as $key => $ph ) {
			if ( array_key_exists( $key, $fields ) ) {
				$data[ $key ] = $fields[ $key ];
				$fmt[]        = $ph;
			}
		}
		if ( empty( $data ) ) {
			return;
		}
		$data['synced_at'] = current_time( 'mysql', true );
		$fmt[]              = '%s';
		$wpdb->update(
			self::table(),
			$data,
			array(
				'panel_id'   => (int) $panel_id,
				'inbound_id' => (int) $inbound_id,
				'email'      => (string) $email,
			),
			$fmt,
			array( '%d', '%d', '%s' )
		);
	}
}
