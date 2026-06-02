<?php
/**
 * Match subscription config URIs to panel inbound ids (port / remark).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Config_Inbound_Match
 */
class SimpleVPBot_Config_Inbound_Match {

	/**
	 * Resolve inbound id for one config URI.
	 *
	 * @param string               $uri               Config line.
	 * @param int                  $panel_id          Panel id.
	 * @param int                  $service_inbound_id Fallback when single line.
	 * @param array<string, mixed> $context           Optional inbound_catalog: list of {id, remark, port}.
	 * @return int
	 */
	public static function inbound_id_for_uri( $uri, $panel_id, $service_inbound_id = 0, array $context = array() ) {
		$pid = (int) $panel_id;
		$catalog = isset( $context['inbound_catalog'] ) && is_array( $context['inbound_catalog'] )
			? $context['inbound_catalog']
			: ( $pid > 0 ? self::inbound_catalog_for_panel( $pid ) : array() );

		if ( empty( $catalog ) ) {
			return max( 0, (int) $service_inbound_id );
		}

		$port = self::uri_port( (string) $uri );
		if ( $port > 0 ) {
			foreach ( $catalog as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				if ( (int) ( $row['port'] ?? 0 ) === $port ) {
					return (int) ( $row['id'] ?? 0 );
				}
			}
		}

		if ( class_exists( 'SimpleVPBot_Config_Link' ) ) {
			$frag = trim( SimpleVPBot_Config_Link::uri_fragment_label( (string) $uri ) );
			if ( '' !== $frag ) {
				foreach ( $catalog as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$remark = trim( (string) ( $row['remark'] ?? '' ) );
					if ( '' !== $remark && ( $frag === $remark || 0 === strpos( $frag, $remark . ' ' ) || 0 === strpos( $frag, $remark . '-' ) ) ) {
						return (int) ( $row['id'] ?? 0 );
					}
				}
			}
		}

		return max( 0, (int) $service_inbound_id );
	}

	/**
	 * Distinct inbounds for a panel from client cache.
	 *
	 * @param int $panel_id Panel id.
	 * @return array<int, array{id:int, remark:string, port:int}>
	 */
	public static function inbound_catalog_for_panel( $panel_id ) {
		$pid = (int) $panel_id;
		if ( $pid < 1 || ! class_exists( 'SimpleVPBot_Model_Panel_Inbound_Client' ) ) {
			return array();
		}
		global $wpdb;
		$t = SimpleVPBot_Model_Panel_Inbound_Client::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT inbound_id AS id, MAX(inbound_remark) AS remark, MAX(port) AS port
				FROM {$t} WHERE panel_id = %d GROUP BY inbound_id ORDER BY inbound_id ASC",
				$pid
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $row ) {
			$iid = (int) ( $row['id'] ?? 0 );
			if ( $iid < 1 ) {
				continue;
			}
			$out[] = array(
				'id'     => $iid,
				'remark' => (string) ( $row['remark'] ?? '' ),
				'port'   => (int) ( $row['port'] ?? 0 ),
			);
		}
		return $out;
	}

	/**
	 * Parse host:port from vless/vmess/trojan URI.
	 *
	 * @param string $uri Config URI.
	 * @return int Port or 0.
	 */
	public static function uri_port( $uri ) {
		$u = trim( (string) $uri );
		if ( '' === $u || false === strpos( $u, '://' ) ) {
			return 0;
		}
		$after = substr( $u, strpos( $u, '://' ) + 3 );
		$at    = strrpos( $after, '@' );
		if ( false !== $at ) {
			$after = substr( $after, $at + 1 );
		}
		$q = strpos( $after, '?' );
		if ( false !== $q ) {
			$after = substr( $after, 0, $q );
		}
		$hash = strpos( $after, '#' );
		if ( false !== $hash ) {
			$after = substr( $after, 0, $hash );
		}
		$colon = strrpos( $after, ':' );
		if ( false === $colon ) {
			return 0;
		}
		$port = (int) substr( $after, $colon + 1 );
		return $port > 0 && $port <= 65535 ? $port : 0;
	}
}
