<?php
/**
 * Resolve inbound display names (site + reseller aliases, panel remark fallback).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Inbound_Display_Name
 */
class SimpleVPBot_Inbound_Display_Name {

	/**
	 * @param int $panel_id      Panel id.
	 * @param int $inbound_id    Inbound id.
	 * @param int $svp_user_id   End-user id (for reseller chain).
	 * @return string
	 */
	public static function for_inbound( $panel_id, $inbound_id, $svp_user_id = 0 ) {
		$alias = self::for_config_label( $panel_id, $inbound_id, $svp_user_id );
		if ( '' !== $alias ) {
			return $alias;
		}
		return self::panel_inbound_remark( (int) $panel_id, (int) $inbound_id );
	}

	/**
	 * Explicit inbound alias only (reseller + site map); no panel remark fallback.
	 *
	 * @param int $panel_id    Panel id.
	 * @param int $inbound_id  Inbound id.
	 * @param int $svp_user_id End-user id (for reseller chain).
	 * @return string
	 */
	public static function for_config_label( $panel_id, $inbound_id, $svp_user_id = 0 ) {
		$pid = (int) $panel_id;
		$iid = (int) $inbound_id;
		if ( $pid < 1 || $iid < 1 ) {
			return '';
		}
		$key = $pid . ':' . $iid;

		if ( $svp_user_id > 0 && class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
			$rid = SimpleVPBot_Reseller_Branding::nearest_reseller_id_for_user( (int) $svp_user_id );
			if ( $rid > 0 && class_exists( 'SimpleVPBot_Model_Reseller_Inbound_Display_Name' ) ) {
				$res = SimpleVPBot_Model_Reseller_Inbound_Display_Name::label_for( $rid, $pid, $iid );
				if ( '' !== $res ) {
					return $res;
				}
			}
		}

		if ( class_exists( 'SimpleVPBot_Settings' ) ) {
			$site = SimpleVPBot_Settings::inbound_display_names_map();
			if ( isset( $site[ $key ] ) && '' !== trim( (string) $site[ $key ] ) ) {
				return trim( (string) $site[ $key ] );
			}
		}

		return '';
	}

	/**
	 * Panel inbound remark from client cache.
	 *
	 * @param int $panel_id   Panel id.
	 * @param int $inbound_id Inbound id.
	 * @return string
	 */
	public static function panel_inbound_remark( $panel_id, $inbound_id ) {
		$pid = (int) $panel_id;
		$iid = (int) $inbound_id;
		if ( $pid < 1 || $iid < 1 ) {
			return '';
		}
		global $wpdb;
		if ( ! class_exists( 'SimpleVPBot_Model_Panel_Inbound_Client' ) ) {
			return '';
		}
		$t = SimpleVPBot_Model_Panel_Inbound_Client::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$remark = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT inbound_remark FROM {$t} WHERE panel_id = %d AND inbound_id = %d AND inbound_remark != '' LIMIT 1",
				$pid,
				$iid
			)
		);
		return is_string( $remark ) ? trim( $remark ) : '';
	}
}
