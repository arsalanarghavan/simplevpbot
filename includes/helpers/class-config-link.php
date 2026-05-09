<?php
/**
 * Subscription URL + HTTP body from 3x-ui: {@see SimpleVPBot_Config_Link::fetch_subscription()}.
 * Lines are exactly what the panel serves (public subscription URL and/or `sub/{token}` on the panel host).
 * {@see SimpleVPBot_Config_Link::build()} is internal only — not used for user-facing config lists.
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
	 * Internal: synthesize a share link from API JSON (not the panel subscription body).
	 *
	 * @param array<string, mixed> $inbound Inbound obj from API.
	 * @param array<string, mixed> $client Client object from settings.clients.
	 * @param string               $remark Remark / fragment name.
	 * @param int|null             $panel_id Optional panel id.
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
	 * If this returns empty while the portal still shows a subscription link, check:
	 * per-panel and global `subscription_public_base` in the bot dashboard, subscription
	 * service enabled in 3x-ui (path/port), and that `sub_id` on the service matches the
	 * client on the panel (see plugin logs: subscription fetch failed / bad response).
	 *
	 * @param string   $sub_url   Fully-qualified public subscription URL (for cache key + second-chance fetch).
	 * @param int|null $panel_id  `svp_panels.id` (0 = legacy). When not null, panel `sub/{token}` is tried first — same bytes the panel serves, often reachable when `subscription_public_base` is not.
	 * @return array<int, string> Lines exactly as in the panel subscription HTTP body.
	 */
	public static function fetch_subscription( $sub_url, $panel_id = null ) {
		$url = trim( (string) $sub_url );
		if ( '' === $url ) {
			return array();
		}
		$cache_key = 'svp_sub_' . md5( $url . '|p' . (string) ( null === $panel_id ? 'x' : (int) $panel_id ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}
		$lines = array();
		if ( null !== $panel_id ) {
			$lines = self::fetch_subscription_from_panel_sub_path( $url, (int) $panel_id );
		}
		if ( empty( $lines ) ) {
			$attempts = 5;
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
						'headers'     => array(
							'Accept'     => '*/*',
							'User-Agent' => 'SimpleVPBot/1.0 subscription',
						),
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
		}
		if ( empty( $lines ) ) {
			return array();
		}
		set_transient( $cache_key, $lines, 60 );
		return $lines;
	}

	/**
	 * GET subscription raw body from the panel web root: `sub/{token}` (same output as public sub URL).
	 *
	 * @param string $sub_url   Public subscription URL (path basename = token).
	 * @param int    $panel_id  0 = legacy global panel from settings.
	 * @return array<int, string>
	 */
	private static function fetch_subscription_from_panel_sub_path( $sub_url, $panel_id ) {
		$parts = wp_parse_url( (string) $sub_url );
		if ( ! is_array( $parts ) || empty( $parts['path'] ) ) {
			return array();
		}
		$token = rawurldecode( (string) basename( rtrim( (string) $parts['path'], '/' ) ) );
		if ( '' === $token || ! class_exists( 'SimpleVPBot_Xui_Client' ) ) {
			return array();
		}
		$pid = (int) $panel_id;
		if ( $pid < 0 ) {
			$pid = 0;
		}
		return SimpleVPBot_Xui_Client::run_with_panel(
			$pid,
			function () use ( $token ) {
				$rel_encoded = 'sub/' . rawurlencode( $token );
				$rel_raw     = 'sub/' . $token;
				$candidates  = array_unique(
					array(
						$rel_encoded,
						$rel_raw,
					)
				);
				$pull = static function () use ( $candidates ) {
					foreach ( $candidates as $rel ) {
						$r = SimpleVPBot_Xui_Client::request( $rel, 'GET', array(), true, 0, 'panel' );
						if ( ! empty( $r['ok'] ) && is_string( $r['body'] ?? null ) && strlen( (string) $r['body'] ) > 4 ) {
							$parsed = self::parse_subscription_body( (string) $r['body'] );
							if ( ! empty( $parsed ) ) {
								return $parsed;
							}
						}
					}
					return array();
				};
				$lines = $pull();
				if ( ! empty( $lines ) ) {
					return $lines;
				}
				if ( SimpleVPBot_Xui_Client::login_with_retries( 4, 250000 ) ) {
					return $pull();
				}
				return array();
			}
		);
	}

	/**
	 * Decode subscription body: JSON wrappers, base64 chain, newline URIs, regex scrape.
	 *
	 * @param string $body Response body.
	 * @return array<int, string>
	 */
	public static function parse_subscription_body( $body ) {
		$body = trim( (string) $body );
		if ( '' === $body ) {
			return array();
		}
		if ( strncmp( $body, "\xEF\xBB\xBF", 3 ) === 0 ) {
			$body = substr( $body, 3 );
		}
		$lead = ltrim( $body );
		if ( '' !== $lead && ( '{' === $lead[0] || '[' === $lead[0] ) ) {
			$json = json_decode( $body, true );
			if ( is_array( $json ) ) {
				$from = self::extract_uris_from_subscription_json( $json );
				if ( ! empty( $from ) ) {
					return $from;
				}
			}
		}
		$lines = self::split_subscription_uri_lines( self::expand_subscription_plain( $body ) );
		if ( ! empty( $lines ) ) {
			return $lines;
		}
		return self::extract_uris_by_regex( $body );
	}

	/**
	 * Unwrap base64 layers until "://" appears or decode stalls.
	 *
	 * @param string $body Raw body.
	 * @return string
	 */
	private static function expand_subscription_plain( $body ) {
		$plain = (string) $body;
		$guard = 0;
		while ( $guard < 4 && false === strpos( $plain, '://' ) ) {
			$b64 = str_replace( array( "\r", "\n", "\t", ' ' ), '', $plain );
			if ( '' === $b64 ) {
				break;
			}
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$decoded = base64_decode( $b64, true );
			if ( ! is_string( $decoded ) || '' === $decoded || $decoded === $plain ) {
				break;
			}
			$plain = $decoded;
			$guard++;
		}
		return $plain;
	}

	/**
	 * Split text into share URI lines (non-empty, contain ://).
	 *
	 * @param string $plain Plain or decoded text.
	 * @return array<int, string>
	 */
	private static function split_subscription_uri_lines( $plain ) {
		$plain = (string) $plain;
		$lines = preg_split( '/\r\n|\r|\n/', $plain );
		$out   = array();
		foreach ( (array) $lines as $ln ) {
			$ln = trim( (string) $ln );
			if ( '' === $ln || false === strpos( $ln, '://' ) ) {
				continue;
			}
			$out[] = $ln;
		}
		if ( empty( $out ) && false !== strpos( $plain, '://' ) ) {
			$out[] = trim( $plain );
		}
		return $out;
	}

	/**
	 * Recursively collect strings that look like share URIs (also decodes nested base64 strings).
	 *
	 * @param mixed $data JSON-decoded value.
	 * @param int   $depth Recursion guard.
	 * @return array<int, string>
	 */
	private static function extract_uris_from_subscription_json( $data, $depth = 0 ) {
		$out = array();
		if ( $depth > 8 ) {
			return $out;
		}
		if ( is_string( $data ) ) {
			$t = trim( $data );
			if ( '' === $t ) {
				return $out;
			}
			if ( false !== strpos( $t, '://' ) ) {
				foreach ( self::split_subscription_uri_lines( $t ) as $ln ) {
					$out[] = $ln;
				}
				return $out;
			}
			$expanded = self::expand_subscription_plain( $t );
			if ( false !== strpos( $expanded, '://' ) ) {
				foreach ( self::split_subscription_uri_lines( $expanded ) as $ln ) {
					$out[] = $ln;
				}
			}
			return $out;
		}
		if ( ! is_array( $data ) ) {
			return $out;
		}
		foreach ( $data as $v ) {
			foreach ( self::extract_uris_from_subscription_json( $v, $depth + 1 ) as $ln ) {
				$out[] = $ln;
			}
		}
		return array_values( array_unique( array_filter( $out ) ) );
	}

	/**
	 * Last-resort: find vmess/vless/trojan/ss/tuic/hy2 URIs embedded in HTML or single-line blobs.
	 *
	 * @param string $body Raw body.
	 * @return array<int, string>
	 */
	private static function extract_uris_by_regex( $body ) {
		$s = (string) $body;
		if ( '' === $s ) {
			return array();
		}
		if ( preg_match_all( '/\b(?:vmess|vless|trojan|ss|ssr|tuic|hy2|hysteria2):\/\/\S+/iu', $s, $m ) && ! empty( $m[0] ) ) {
			$out = array();
			foreach ( $m[0] as $raw ) {
				$u = rtrim( (string) $raw, "\t\r\n.,;)'\"»«،" );
				if ( false !== strpos( $u, '://' ) ) {
					$out[] = $u;
				}
			}
			return array_values( array_unique( array_filter( $out ) ) );
		}
		return array();
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
