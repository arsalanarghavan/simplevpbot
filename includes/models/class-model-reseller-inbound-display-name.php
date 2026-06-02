<?php
/**
 * Per-reseller custom inbound display names.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Reseller_Inbound_Display_Name
 */
class SimpleVPBot_Model_Reseller_Inbound_Display_Name {

	/**
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_reseller_inbound_display_names';
	}

	/**
	 * @param int $reseller_svp_user_id Reseller user id.
	 * @return array<string, string> Keys "panelId:inboundId" => label.
	 */
	public static function map_for_reseller( $reseller_svp_user_id ) {
		$rid = (int) $reseller_svp_user_id;
		if ( $rid < 1 ) {
			return array();
		}
		global $wpdb;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT panel_id, inbound_id, label FROM {$t} WHERE reseller_svp_user_id = %d",
				$rid
			)
		);
		$out = array();
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			$pid = (int) ( $row->panel_id ?? 0 );
			$iid = (int) ( $row->inbound_id ?? 0 );
			$lab = trim( (string) ( $row->label ?? '' ) );
			if ( $pid < 1 || $iid < 1 || '' === $lab ) {
				continue;
			}
			$out[ $pid . ':' . $iid ] = $lab;
		}
		return $out;
	}

	/**
	 * @param int    $reseller_svp_user_id Reseller user id.
	 * @param int    $panel_id             Panel id.
	 * @param int    $inbound_id           Inbound id.
	 * @return string
	 */
	public static function label_for( $reseller_svp_user_id, $panel_id, $inbound_id ) {
		$rid = (int) $reseller_svp_user_id;
		$pid = (int) $panel_id;
		$iid = (int) $inbound_id;
		if ( $rid < 1 || $pid < 1 || $iid < 1 ) {
			return '';
		}
		global $wpdb;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$lab = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT label FROM {$t} WHERE reseller_svp_user_id = %d AND panel_id = %d AND inbound_id = %d LIMIT 1",
				$rid,
				$pid,
				$iid
			)
		);
		return is_string( $lab ) ? trim( $lab ) : '';
	}

	/**
	 * Replace all labels for a reseller (empty values delete rows).
	 *
	 * @param int                  $reseller_svp_user_id Reseller user id.
	 * @param array<string, mixed> $map                  Keys panelId:inboundId.
	 * @return void
	 */
	public static function replace_map_for_reseller( $reseller_svp_user_id, array $map ) {
		$rid = (int) $reseller_svp_user_id;
		if ( $rid < 1 ) {
			return;
		}
		global $wpdb;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->delete( $t, array( 'reseller_svp_user_id' => $rid ), array( '%d' ) );
		foreach ( $map as $key => $raw ) {
			$lab = trim( sanitize_text_field( (string) $raw ) );
			if ( '' === $lab ) {
				continue;
			}
			$parts = explode( ':', (string) $key, 2 );
			if ( count( $parts ) !== 2 ) {
				continue;
			}
			$pid = (int) $parts[0];
			$iid = (int) $parts[1];
			if ( $pid < 1 || $iid < 1 ) {
				continue;
			}
			$wpdb->insert(
				$t,
				array(
					'reseller_svp_user_id' => $rid,
					'panel_id'             => $pid,
					'inbound_id'           => $iid,
					'label'                => mb_substr( $lab, 0, 255, 'UTF-8' ),
				),
				array( '%d', '%d', '%d', '%s' )
			);
		}
	}
}
