<?php
/**
 * Live 3x-ui panel metrics for dashboard monitoring (cached per panel).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Dashboard_Panel_Live
 */
class SimpleVPBot_Dashboard_Panel_Live {

	const TRANSIENT_PREFIX = 'svp_dash_live_p_';
	const CACHE_TTL        = 55;

	/**
	 * Transient key for one panel snapshot.
	 *
	 * @param int $panel_id Panel id.
	 * @return string
	 */
	public static function transient_key( $panel_id ) {
		return self::TRANSIENT_PREFIX . (int) $panel_id;
	}

	/**
	 * Delete cached snapshot for a panel.
	 *
	 * @param int $panel_id Panel id.
	 */
	public static function clear_cache( $panel_id ) {
		delete_transient( self::transient_key( $panel_id ) );
	}

	/**
	 * Build a small JSON-safe summary from 3x-ui server/status (forks differ).
	 *
	 * @param mixed $json Decoded JSON.
	 * @return array<string, mixed>
	 */
	public static function summarize_server_status( $json ) {
		$out = array();
		if ( ! is_array( $json ) ) {
			return $out;
		}
		self::walk_status( isset( $json['obj'] ) && is_array( $json['obj'] ) ? $json['obj'] : $json, '', $out, 0 );
		return $out;
	}

	/**
	 * @param mixed                $node Node.
	 * @param string               $path Path prefix.
	 * @param array<string, mixed> $out Output accumulator.
	 * @param int                  $depth Depth.
	 */
	private static function walk_status( $node, $path, array &$out, $depth ) {
		if ( $depth > 4 || count( $out ) >= 20 ) {
			return;
		}
		if ( ! is_array( $node ) ) {
			return;
		}
		foreach ( $node as $k => $v ) {
			if ( ! is_string( $k ) || '' === $k ) {
				continue;
			}
			$key = strtolower( $k );
			if ( in_array( $key, array( 'password', 'token', 'secret', 'cookie', 'privatekey', 'private_key' ), true ) ) {
				continue;
			}
			$here = '' === $path ? $k : $path . '.' . $k;
			if ( is_scalar( $v ) && ( is_numeric( $v ) || ( is_string( $v ) && strlen( (string) $v ) < 64 ) ) ) {
				if ( is_numeric( $v ) || preg_match( '/cpu|mem|ram|disk|load|uptime|xray|traffic|net|swap|used|total|percent|count/i', $key ) ) {
					$out[ $here ] = is_numeric( $v ) ? (float) $v + 0 : (string) $v;
				}
			} elseif ( is_array( $v ) && $depth < 4 ) {
				self::walk_status( $v, $here, $out, $depth + 1 );
			}
		}
	}

	/**
	 * Fetch or return cached live snapshot for one panel.
	 *
	 * @param int  $panel_id Panel id.
	 * @param bool $force_refresh Skip transient and re-fetch.
	 * @return array<string, mixed>
	 */
	public static function snapshot_for_panel( $panel_id, $force_refresh = false ) {
		$panel_id = (int) $panel_id;
		if ( $panel_id < 1 ) {
			return array(
				'panelId'   => 0,
				'ok'        => false,
				'error'     => 'invalid_panel',
				'onlineNow' => null,
				'status'    => null,
				'checkedAt' => gmdate( 'c' ),
			);
		}
		if ( ! $force_refresh ) {
			$cached = get_transient( self::transient_key( $panel_id ) );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}
		$out = array(
			'panelId'   => $panel_id,
			'ok'        => false,
			'error'     => '',
			'onlineNow' => null,
			'status'    => null,
			'checkedAt' => gmdate( 'c' ),
		);
		if ( ! class_exists( 'SimpleVPBot_Xui_Client' ) || ! class_exists( 'SimpleVPBot_Cron_Panel_Online' ) ) {
			$out['error'] = 'missing_xui';
			set_transient( self::transient_key( $panel_id ), $out, 30 );
			return $out;
		}
		$pid = $panel_id;
		$row = SimpleVPBot_Xui_Client::run_with_panel(
			$panel_id,
			function () use ( $pid ) {
				$o = array(
					'panelId'   => $pid,
					'ok'        => false,
					'error'     => '',
					'onlineNow' => null,
					'status'    => null,
					'checkedAt' => gmdate( 'c' ),
				);
				if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
					$o['error'] = 'login_failed';
					return $o;
				}
				$on_fetch = SimpleVPBot_Xui_Client::fetch_onlines();
				if ( empty( $on_fetch['ok'] ) ) {
					$o['error']     = (string) ( $on_fetch['error'] ?? 'onlines_failed' );
					$o['onlineNow'] = null;
				} else {
					$o['onlineNow'] = SimpleVPBot_Xui_Client::count_onlines_response( $on_fetch['json'] ?? null );
				}
				$st          = SimpleVPBot_Xui_Client::server_status();
				$o['status'] = self::summarize_server_status( $st );
				$o['ok']     = true;
				return $o;
			}
		);
		if ( is_array( $row ) ) {
			$out = $row;
		}
		$out['panelId']   = $panel_id;
		$out['checkedAt'] = gmdate( 'c' );
		if (
			! empty( $out['ok'] )
			&& isset( $out['onlineNow'] )
			&& class_exists( 'SimpleVPBot_Model_Panel_Online_Daily' )
			&& class_exists( 'SimpleVPBot_Admin_Dashboard_Stats' )
		) {
			SimpleVPBot_Model_Panel_Online_Daily::upsert_max(
				$panel_id,
				SimpleVPBot_Admin_Dashboard_Stats::stat_date_for_offset( 0 ),
				(int) $out['onlineNow']
			);
		}
		set_transient( self::transient_key( $panel_id ), $out, self::CACHE_TTL );
		return $out;
	}

	/**
	 * GET metrics_url (HTTPS only) with optional Bearer auth; result cached briefly.
	 *
	 * @param int    $host_id Host row id.
	 * @param string $url URL.
	 * @param string $bearer Raw bearer token (empty = none).
	 * @param bool   $force_refresh Skip transient.
	 * @return array<string, mixed>
	 */
	public static function snapshot_external_host( $host_id, $url, $bearer, $force_refresh = false ) {
		$host_id = (int) $host_id;
		$key      = 'svp_dash_live_ext_' . $host_id;
		if ( ! $force_refresh ) {
			$cached = get_transient( $key );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}
		$out = array(
			'hostId'    => $host_id,
			'ok'        => false,
			'error'     => '',
			'metrics'   => null,
			'checkedAt' => gmdate( 'c' ),
		);
		$url = trim( (string) $url );
		if ( '' === $url || 0 !== strpos( $url, 'https://' ) ) {
			$out['error'] = 'https_only';
			set_transient( $key, $out, 120 );
			return $out;
		}
		$headers = array( 'Accept' => 'application/json' );
		$b       = trim( (string) $bearer );
		if ( '' !== $b ) {
			$headers['Authorization'] = 'Bearer ' . $b;
		}
		$res = wp_remote_get(
			$url,
			array(
				'timeout'     => 6,
				'redirection' => 2,
				'sslverify'   => true,
				'headers'     => $headers,
			)
		);
		if ( is_wp_error( $res ) ) {
			$out['error'] = $res->get_error_message();
			set_transient( $key, $out, 60 );
			return $out;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( $code < 200 || $code >= 300 ) {
			$out['error'] = 'http_' . $code;
			set_transient( $key, $out, 60 );
			return $out;
		}
		$body = (string) wp_remote_retrieve_body( $res );
		$json = json_decode( $body, true );
		if ( ! is_array( $json ) ) {
			$out['error'] = 'not_json';
			set_transient( $key, $out, 60 );
			return $out;
		}
		$flat = array();
		self::walk_status( $json, '', $flat, 0 );
		$out['metrics'] = $flat;
		$out['ok']      = true;
		set_transient( $key, $out, self::CACHE_TTL );
		return $out;
	}
}
