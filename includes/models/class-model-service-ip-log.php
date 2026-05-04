<?php
/**
 * IP observations for a service (dashboard / panel sync).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Service_Ip_Log
 */
class SimpleVPBot_Model_Service_Ip_Log {

	/**
	 * Table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_service_ip_log';
	}

	/**
	 * Record one IP hit.
	 *
	 * @param int    $service_id svp_services.id.
	 * @param string $ip         IPv4/IPv6 string.
	 */
	public static function touch_ip( $service_id, $ip ) {
		global $wpdb;
		$sid = (int) $service_id;
		$ip  = trim( (string) $ip );
		if ( $sid < 1 || '' === $ip || strlen( $ip ) > 64 ) {
			return;
		}
		$t   = self::table();
		$now = current_time( 'mysql', 1 );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE service_id = %d AND ip = %s LIMIT 1", $sid, $ip ) );
		if ( $rid > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET last_seen_at = %s, hit_count = hit_count + 1 WHERE id = %d", $now, $rid ) );
			return;
		}
		$wpdb->insert(
			$t,
			array(
				'service_id'     => $sid,
				'ip'             => $ip,
				'first_seen_at'  => $now,
				'last_seen_at'   => $now,
				'hit_count'      => 1,
			),
			array( '%d', '%s', '%s', '%s', '%d' )
		);
	}

	/**
	 * @param int   $service_id Service id.
	 * @param array $ips        List of IP strings.
	 */
	public static function touch_many( $service_id, array $ips ) {
		foreach ( $ips as $one ) {
			self::touch_ip( $service_id, (string) $one );
		}
	}

	/**
	 * Latest rows per service (for dashboard batch).
	 *
	 * @param array<int, int> $service_ids Service ids.
	 * @param int             $per_service Max rows each.
	 * @return array<int, array<int, array<string, mixed>>>
	 */
	public static function latest_for_services( array $service_ids, $per_service = 25 ) {
		global $wpdb;
		$out = array();
		foreach ( $service_ids as $sid ) {
			$out[ (int) $sid ] = array();
		}
		$ids = array_values( array_filter( array_map( 'intval', $service_ids ) ) );
		if ( empty( $ids ) ) {
			return $out;
		}
		$lim = max( 1, min( 100, (int) $per_service ) );
		$t   = self::table();
		$in  = implode( ',', $ids );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT id, service_id, ip, first_seen_at, last_seen_at, hit_count FROM {$t} WHERE service_id IN ({$in}) ORDER BY last_seen_at DESC LIMIT 500",
			ARRAY_A
		);
		foreach ( (array) $rows as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$sid = (int) ( $r['service_id'] ?? 0 );
			if ( $sid < 1 || ! isset( $out[ $sid ] ) ) {
				continue;
			}
			if ( count( $out[ $sid ] ) >= $lim ) {
				continue;
			}
			$out[ $sid ][] = $r;
		}
		return $out;
	}
}
