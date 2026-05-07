<?php
/**
 * Build share URI from inbound + client (best-effort for vless/vmess/trojan/ss).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Config_Link
 */
class SimpleVPBot_Config_Link {

	/**
	 * Extract host from panel URL or subscription base.
	 *
	 * @param string $panel_url Panel URL.
	 * @return string
	 */
	public static function host_from_panel( $panel_url ) {
		$p = wp_parse_url( $panel_url );
		return isset( $p['host'] ) ? (string) $p['host'] : '';
	}

	/**
	 * Build primary link for client.
	 *
	 * @param array<string, mixed> $inbound Inbound obj from API.
	 * @param array<string, mixed> $client Client object from settings.clients.
	 * @param string                 $remark Remark / fragment name.
	 * @return string
	 */
	public static function build( $inbound, $client, $remark = '', $panel_id = null ) {
		$protocol = isset( $inbound['protocol'] ) ? strtolower( (string) $inbound['protocol'] ) : 'vless';
		$port      = isset( $inbound['port'] ) ? (int) $inbound['port'] : 443;
		$panel_url = self::panel_url_for_link( $panel_id );
		$host      = self::host_from_panel( $panel_url );
		$stream    = isset( $inbound['streamSettings'] ) && is_string( $inbound['streamSettings'] )
			? json_decode( $inbound['streamSettings'], true )
			: ( isset( $inbound['streamSettings'] ) && is_array( $inbound['streamSettings'] ) ? $inbound['streamSettings'] : array() );
		if ( ! is_array( $stream ) ) {
			$stream = array();
		}
		$net   = isset( $stream['network'] ) ? (string) $stream['network'] : 'tcp';
		$ws    = isset( $stream['wsSettings'] ) && is_array( $stream['wsSettings'] ) ? $stream['wsSettings'] : array();
		$path  = isset( $ws['path'] ) ? (string) $ws['path'] : '/';
		$headers = isset( $ws['headers'] ) && is_array( $ws['headers'] ) ? $ws['headers'] : array();
		$chost = '';
		foreach ( $headers as $k => $v ) {
			if ( strtolower( (string) $k ) === 'host' ) {
				$chost = is_array( $v ) ? (string) reset( $v ) : (string) $v;
				break;
			}
		}
		$frag = rawurlencode( $remark );

		switch ( $protocol ) {
			case 'vless':
				$id = isset( $client['id'] ) ? (string) $client['id'] : '';
				if ( ! $id ) {
					return '';
				}
				$q = array(
					'encryption' => 'none',
					'security'   => 'none',
					'type'       => $net,
				);
				if ( 'ws' === $net ) {
					$q['path'] = $path;
					if ( $chost ) {
						$q['host'] = $chost;
					}
				}
				return 'vless://' . $id . '@' . $host . ':' . $port . '?' . http_build_query( $q, '', '&', PHP_QUERY_RFC3986 ) . '#' . $frag;
			case 'trojan':
				$pw = isset( $client['password'] ) ? (string) $client['password'] : '';
				if ( ! $pw ) {
					return '';
				}
				$q = array( 'security' => 'none', 'type' => $net );
				if ( 'ws' === $net ) {
					$q['path'] = $path;
					if ( $chost ) {
						$q['host'] = $chost;
					}
				}
				return 'trojan://' . rawurlencode( $pw ) . '@' . $host . ':' . $port . '?' . http_build_query( $q, '', '&', PHP_QUERY_RFC3986 ) . '#' . $frag;
			default:
				return isset( $client['email'] ) ? (string) $client['email'] : '';
		}
	}

	/**
	 * Panel root URL for building host links (settings row or svp_panels).
	 *
	 * @param int|null $panel_id svp_panels.id or null for legacy settings.
	 * @return string
	 */
	private static function panel_url_for_link( $panel_id = null ) {
		if ( null !== $panel_id && (int) $panel_id > 0 && class_exists( 'SimpleVPBot_Model_Panel' ) ) {
			$row = SimpleVPBot_Model_Panel::find( (int) $panel_id );
			if ( $row && is_object( $row ) && '' !== trim( (string) ( $row->panel_url ?? '' ) ) ) {
				return (string) $row->panel_url;
			}
		}
		return (string) SimpleVPBot_Settings::get( 'panel_url', '' );
	}

	/**
	 * @param string   $sub_id   Subscription id / token.
	 * @param int|null $panel_id Optional svp_panels.id for per-panel subscription base.
	 * @return string
	 */
	public static function subscription_url( $sub_id, $panel_id = null ) {
		$base = '';
		if ( null !== $panel_id && (int) $panel_id > 0 && class_exists( 'SimpleVPBot_Model_Panel' ) ) {
			$row = SimpleVPBot_Model_Panel::find( (int) $panel_id );
			if ( $row && is_object( $row ) ) {
				$base = trim( (string) ( $row->subscription_public_base ?? '' ) );
			}
		}
		if ( '' === $base ) {
			$base = trim( (string) SimpleVPBot_Settings::get( 'subscription_public_base', '' ) );
		}
		if ( ! $base || ! $sub_id ) {
			return '';
		}
		return trailingslashit( $base ) . rawurlencode( $sub_id );
	}

	/**
	 * Fetch decoded config URI lines from a 3x-ui subscription URL. The panel
	 * may return plain lines or base64-encoded concatenation; both are handled
	 * without any transformation of the URI strings themselves. Cached 60s.
	 *
	 * @param string $sub_url Fully-qualified subscription URL.
	 * @return array<int, string> List of non-empty URI lines, exactly as served.
	 */
	public static function fetch_subscription( $sub_url ) {
		$url = trim( (string) $sub_url );
		if ( '' === $url ) {
			return array();
		}
		$cache_key = 'svp_sub_' . md5( $url );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}
		$attempts = 5;
		$lines    = array();
		for ( $i = 0; $i < $attempts; $i++ ) {
			if ( $i > 0 ) {
				usleep( 200000 + $i * 150000 );
			}
			$resp = wp_remote_get(
				$url,
				array(
					'timeout'     => 20,
					'redirection' => 3,
					'sslverify'   => false,
					'headers'     => array( 'Accept' => '*/*' ),
				)
			);
			if ( is_wp_error( $resp ) ) {
				if ( class_exists( 'SimpleVPBot_Logger' ) ) {
					SimpleVPBot_Logger::error( 'subscription fetch failed', array( 'url' => $url, 'err' => $resp->get_error_message(), 'try' => $i ) );
				}
				continue;
			}
			$code = (int) wp_remote_retrieve_response_code( $resp );
			$body = (string) wp_remote_retrieve_body( $resp );
			if ( $code < 200 || $code >= 300 || '' === $body ) {
				if ( class_exists( 'SimpleVPBot_Logger' ) ) {
					SimpleVPBot_Logger::error( 'subscription bad response', array( 'url' => $url, 'code' => $code, 'len' => strlen( $body ), 'try' => $i ) );
				}
				continue;
			}
			$lines = self::parse_subscription_body( $body );
			if ( ! empty( $lines ) ) {
				break;
			}
		}
		if ( empty( $lines ) ) {
			return array();
		}
		set_transient( $cache_key, $lines, 60 );
		return $lines;
	}

	/**
	 * Try base64-decode, else return body as-is. Split into non-empty lines.
	 *
	 * @param string $body Response body.
	 * @return array<int, string>
	 */
	public static function parse_subscription_body( $body ) {
		$body  = trim( (string) $body );
		$plain = $body;
		// Heuristic: base64 body rarely contains "://"; if it has, treat as plain.
		if ( false === strpos( $body, '://' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$decoded = base64_decode( str_replace( array( "\r", "\n", ' ' ), '', $body ), true );
			if ( is_string( $decoded ) && '' !== $decoded && false !== strpos( $decoded, '://' ) ) {
				$plain = $decoded;
			}
		}
		$lines = preg_split( '/\r\n|\r|\n/', $plain );
		$out   = array();
		foreach ( (array) $lines as $ln ) {
			$ln = trim( (string) $ln );
			if ( '' === $ln ) {
				continue;
			}
			if ( false === strpos( $ln, '://' ) ) {
				continue;
			}
			$out[] = $ln;
		}
		return $out;
	}

	/**
	 * Replace the first URI fragment (#...) with a new display name (e.g. reseller brand).
	 *
	 * @param string $uri            Config or share URI.
	 * @param string $remark_display Human-readable remark; empty = no change.
	 * @return string
	 */
	public static function replace_uri_fragment( $uri, $remark_display ) {
		$u    = (string) $uri;
		$frag = trim( (string) $remark_display );
		if ( '' === $frag || false === strpos( $u, '://' ) ) {
			return $u;
		}
		$enc = rawurlencode( $frag );
		$p   = strpos( $u, '#' );
		if ( false === $p ) {
			return $u . '#' . $enc;
		}
		return substr( $u, 0, $p + 1 ) . $enc;
	}
}
