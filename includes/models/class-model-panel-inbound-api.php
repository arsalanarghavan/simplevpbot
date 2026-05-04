<?php
/**
 * Cached full inbound JSON from X-UI (for dashboard URI rebuild from DB rows).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Panel_Inbound_Api
 */
class SimpleVPBot_Model_Panel_Inbound_Api {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_panel_inbound_api';
	}

	/**
	 * Upsert inbound JSON snapshot.
	 *
	 * @param int    $panel_id   Panel id.
	 * @param int    $inbound_id Inbound id.
	 * @param string $json       JSON-encoded inbound array from API.
	 */
	public static function upsert( $panel_id, $inbound_id, $json ) {
		global $wpdb;
		$t   = self::table();
		$pid = (int) $panel_id;
		$iid = (int) $inbound_id;
		$now = current_time( 'mysql', true );
		$j   = (string) $json;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE panel_id = %d AND inbound_id = %d", $pid, $iid ) );
		if ( $existing ) {
			$wpdb->update(
				$t,
				array(
					'inbound_json' => $j,
					'synced_at'    => $now,
				),
				array( 'id' => (int) $existing ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$t,
				array(
					'panel_id'     => $pid,
					'inbound_id'   => $iid,
					'inbound_json' => $j,
					'synced_at'    => $now,
				),
				array( '%d', '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Decode all inbound API blobs for a panel: inbound_id => array|null.
	 *
	 * @param int $panel_id Panel id.
	 * @return array<int, array<string,mixed>>
	 */
	public static function inbound_map_for_panel( $panel_id ) {
		global $wpdb;
		$t   = self::table();
		$pid = (int) $panel_id;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT inbound_id, inbound_json FROM {$t} WHERE panel_id = %d", $pid ) );
		$out  = array();
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			if ( ! is_object( $row ) ) {
				continue;
			}
			$iid = (int) $row->inbound_id;
			$dec = json_decode( (string) $row->inbound_json, true );
			if ( is_array( $dec ) && $iid > 0 ) {
				$out[ $iid ] = $dec;
			}
		}
		return $out;
	}

	/**
	 * Delete cached inbound API row (e.g. when inbound removed).
	 *
	 * @param int $panel_id   Panel id.
	 * @param int $inbound_id Inbound id.
	 */
	public static function delete_inbound( $panel_id, $inbound_id ) {
		global $wpdb;
		$wpdb->delete(
			self::table(),
			array(
				'panel_id'   => (int) $panel_id,
				'inbound_id' => (int) $inbound_id,
			),
			array( '%d', '%d' )
		);
	}
}
