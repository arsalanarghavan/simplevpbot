<?php
/**
 * Link an existing 3x-ui client to a bot user (no addClient).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Inbound_Linker
 */
class SimpleVPBot_Inbound_Linker {

	/**
	 * Hard ceiling for one client quota (bytes) in DB and on panel JSON (prevents petabyte accidents).
	 * 50 tebibytes.
	 */
	private const MAX_CLIENT_TRAFFIC_BYTES = 54975581388800;

	/**
	 * Clamp stored / panel traffic to a safe maximum; log when clamping.
	 *
	 * @param int $bytes Requested quota bytes (0 = unlimited).
	 * @return int
	 */
	public static function cap_traffic_bytes( $bytes ) {
		$b = (int) $bytes;
		if ( $b <= 0 ) {
			return 0;
		}
		if ( $b > self::MAX_CLIENT_TRAFFIC_BYTES ) {
			SimpleVPBot_Logger::error(
				'traffic_bytes_capped',
				array(
					'requested' => $b,
					'cap'        => self::MAX_CLIENT_TRAFFIC_BYTES,
				)
			);
			return self::MAX_CLIENT_TRAFFIC_BYTES;
		}
		return $b;
	}

	/**
	 * Interpret inbound JSON `totalGB` as a byte count (3x-ui wire format for this field).
	 *
	 * Never multiply by GiB here: small numbers (e.g. 50000) are real byte caps, not «50 000 GB».
	 * When you have a client email and an API session, use {@see self::resolve_quota_bytes} so the
	 * panel DB row (`getClientTraffics` → obj.total, always bytes) wins over JSON if present.
	 *
	 * @param mixed $raw Raw totalGB value from inbound settings JSON.
	 * @return int
	 */
	public static function totalgb_to_bytes( $raw ) {
		if ( ! is_numeric( $raw ) ) {
			return 0;
		}
		$n = (float) $raw;
		if ( $n <= 0 ) {
			return 0;
		}
		return self::cap_traffic_bytes( (int) round( $n ) );
	}

	/**
	 * Authoritative traffic limit in bytes: prefer 3x-ui `getClientTraffics` (DB `client_traffics.total`),
	 * else inbound JSON `totalGB` interpreted as bytes.
	 *
	 * @param mixed  $raw_from_inbound_json Value from inbound `clients[].totalGB`.
	 * @param string $client_email          Client email tag (panel); empty skips API.
	 * @return int
	 */
	public static function resolve_quota_bytes( $raw_from_inbound_json, $client_email = '' ) {
		$from_json = self::totalgb_to_bytes( $raw_from_inbound_json );
		$em        = trim( (string) $client_email );
		if ( '' === $em || ! class_exists( 'SimpleVPBot_Xui_Client' ) ) {
			return $from_json;
		}
		$tr = SimpleVPBot_Xui_Client::get_client_traffics( $em );
		$obj = is_array( $tr ) && isset( $tr['obj'] ) && is_array( $tr['obj'] ) ? $tr['obj'] : null;
		if ( ! is_array( $obj ) || ! array_key_exists( 'total', $obj ) || ! is_numeric( $obj['total'] ) ) {
			return $from_json;
		}
		$api_total = (int) $obj['total'];
		if ( $api_total > 0 ) {
			return self::cap_traffic_bytes( $api_total );
		}
		return $from_json;
	}

	/**
	 * Outbound JSON value for client `totalGB` on 3x-ui create/update (traffic limit in bytes; 0 = unlimited).
	 *
	 * Despite the name, current 3x-ui APIs expect bytes here (see panel addClient examples).
	 *
	 * @param int $total_traffic_bytes Same semantics as `svp_services.total_traffic`.
	 * @return int
	 */
	public static function panel_client_totalgb_json_value( $total_traffic_bytes ) {
		$b = self::cap_traffic_bytes( (int) $total_traffic_bytes );
		return $b > 0 ? $b : 0;
	}

	/**
	 * One-shot DB repair: cap `svp_services.total_traffic` rows above MAX (Xray + L2TP).
	 *
	 * @return int Rows updated (0 if none or failure).
	 */
	public static function repair_cap_total_traffic_in_database() {
		global $wpdb;
		$t   = SimpleVPBot_Model_Service::table();
		$cap = self::MAX_CLIENT_TRAFFIC_BYTES;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare( "UPDATE {$t} SET total_traffic = %d WHERE total_traffic > %d", $cap, $cap );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		$n = $wpdb->query( $sql );
		return false === $n ? 0 : (int) $n;
	}

	/**
	 * Create local service row for existing panel client.
	 *
	 * @param int    $inbound_id Inbound id.
	 * @param string $email Client email in panel.
	 * @param int    $user_id svp_users.id.
	 * @return array{ok:bool, message?:string, service_id?:int}
	 */
	public static function link( $inbound_id, $email, $user_id, $panel_id = 1 ) {
		$inbound_id = (int) $inbound_id;
		$email      = trim( (string) $email );
		$user_id    = (int) $user_id;
		$bind       = (int) $panel_id;
		if ( $bind < 0 ) {
			$bind = 0;
		}
		$store_panel = $bind > 0 ? $bind : 1;
		if ( $inbound_id < 1 || '' === $email || $user_id < 1 ) {
			return array( 'ok' => false, 'message' => 'bad_params' );
		}
		if ( ! SimpleVPBot_Model_User::find( $user_id ) ) {
			return array( 'ok' => false, 'message' => 'no_user' );
		}
		$dup = SimpleVPBot_Model_Service::find_by_inbound_email( $inbound_id, $email, $store_panel );
		if ( $dup ) {
			return array( 'ok' => false, 'message' => 'exists' );
		}
		return SimpleVPBot_Xui_Client::run_with_panel(
			$bind,
			function () use ( $inbound_id, $email, $user_id, $store_panel ) {
				return self::link_on_bound_panel( $inbound_id, $email, $user_id, $store_panel );
			}
		);
	}

	/**
	 * @param int    $inbound_id Inbound id.
	 * @param string $email Client email.
	 * @param int    $user_id User id.
	 * @param int    $panel_id Panel id (already bound on Xui client).
	 * @return array{ok:bool, message?:string, service_id?:int}
	 */
	private static function link_on_bound_panel( $inbound_id, $email, $user_id, $panel_id ) {
		if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
			return array( 'ok' => false, 'message' => 'panel_login' );
		}
		$inbound = SimpleVPBot_Xui_Client::inbound_get( $inbound_id );
		if ( ! $inbound ) {
			return array( 'ok' => false, 'message' => 'no_inbound' );
		}
		$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
		$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
		if ( ! is_array( $dec ) || empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
			return array( 'ok' => false, 'message' => 'no_clients' );
		}
		$client = null;
		foreach ( $dec['clients'] as $c ) {
			if ( is_array( $c ) && isset( $c['email'] ) && (string) $c['email'] === $email ) {
				$client = $c;
				break;
			}
		}
		if ( ! $client ) {
			return array( 'ok' => false, 'message' => 'client_not_found' );
		}
		$uuid  = isset( $client['id'] ) ? (string) $client['id'] : '';
		$sub   = isset( $client['subId'] ) ? (string) $client['subId'] : '';
		$rem   = isset( $client['remark'] ) ? (string) $client['remark'] : $email;
		$total_bytes = self::resolve_quota_bytes( $client['totalGB'] ?? 0, $email );
		$exp_ms = isset( $client['expiryTime'] ) ? (int) $client['expiryTime'] : 0;
		$expires  = null;
		if ( $exp_ms > 0 ) {
			$expires = gmdate( 'Y-m-d H:i:s', (int) ( $exp_ms / 1000 ) );
		}
		$sid = SimpleVPBot_Model_Service::insert(
			array(
				'user_id'         => $user_id,
				'panel_id'        => $panel_id,
				'inbound_id'      => $inbound_id,
				'xui_client_id'   => $uuid,
				'xui_client_uuid' => $uuid,
				'email'           => $email,
				'remark'          => $rem,
				'plan_id'         => null,
				'expires_at'      => $expires,
				'total_traffic'   => $total_bytes,
				'sub_id'          => $sub,
				'provision_type'  => 'linked',
			)
		);
		if ( ! $sid ) {
			return array( 'ok' => false, 'message' => 'db' );
		}
		return array( 'ok' => true, 'service_id' => $sid );
	}

	/**
	 * Auto-link inbound clients to approved bot users by identifiers embedded in
	 * client fields (email + remark/comment).
	 *
	 * @param int $inbound_id Inbound id.
	 * @return array<string, mixed>
	 */
	public static function auto_link_inbound_clients( $inbound_id, $panel_id = 1 ) {
		$iid = (int) $inbound_id;
		if ( $iid < 1 ) {
			return array( 'ok' => false, 'message' => 'bad_params' );
		}
		$bind        = (int) $panel_id;
		if ( $bind < 0 ) {
			$bind = 0;
		}
		$store_panel = $bind > 0 ? $bind : 1;
		return SimpleVPBot_Xui_Client::run_with_panel(
			$bind,
			function () use ( $iid, $store_panel ) {
				return self::auto_link_inbound_clients_on_panel( $iid, $store_panel );
			}
		);
	}

	/**
	 * @param int $iid Inbound id.
	 * @param int $panel_id Panel (Xui already bound).
	 * @return array<string, mixed>
	 */
	private static function auto_link_inbound_clients_on_panel( $iid, $panel_id ) {
		if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
			return array( 'ok' => false, 'message' => 'panel_login' );
		}
		$inbound = SimpleVPBot_Xui_Client::inbound_get( $iid );
		if ( ! $inbound ) {
			return array( 'ok' => false, 'message' => 'no_inbound' );
		}
		$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
		$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
		if ( ! is_array( $dec ) || empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
			return array( 'ok' => true, 'linked' => 0, 'skipped' => 0, 'ambiguous' => 0, 'errors' => 0, 'details' => array() );
		}

		$stats = array(
			'ok'        => true,
			'linked'    => 0,
			'skipped'   => 0,
			'ambiguous' => 0,
			'errors'    => 0,
			'details'   => array(),
		);

		foreach ( $dec['clients'] as $c ) {
			if ( ! is_array( $c ) || empty( $c['email'] ) ) {
				continue;
			}
			$email = trim( (string) $c['email'] );
			if ( '' === $email ) {
				continue;
			}

			if ( SimpleVPBot_Model_Service::find_by_inbound_email( $iid, $email, $panel_id ) ) {
				$stats['skipped']++;
				continue;
			}

			$candidate = self::candidate_text( $c );
			$resolved  = self::resolve_user_from_candidate( $candidate );
			if ( 'ambiguous' === $resolved['status'] ) {
				$stats['ambiguous']++;
				$stats['details'][] = array( 'email' => $email, 'status' => 'ambiguous' );
				continue;
			}
			if ( 'none' === $resolved['status'] || empty( $resolved['user_id'] ) ) {
				$stats['skipped']++;
				continue;
			}

			$link = self::link( $iid, $email, (int) $resolved['user_id'], $panel_id );
			if ( ! empty( $link['ok'] ) ) {
				$stats['linked']++;
				$stats['details'][] = array(
					'email'      => $email,
					'status'     => 'linked',
					'user_id'    => (int) $resolved['user_id'],
					'service_id' => (int) ( $link['service_id'] ?? 0 ),
				);
			} else {
				$stats['errors']++;
				$stats['details'][] = array(
					'email'   => $email,
					'status'  => 'error',
					'message' => (string) ( $link['message'] ?? 'err' ),
				);
			}
		}

		return $stats;
	}

	/**
	 * Aggregate text fields to search identifiers.
	 *
	 * @param array<string, mixed> $client Client row.
	 * @return string
	 */
	private static function candidate_text( array $client ) {
		$parts = array();
		foreach ( array( 'email', 'remark', 'comment', 'memo', 'note', 'desc' ) as $k ) {
			if ( isset( $client[ $k ] ) && '' !== trim( (string) $client[ $k ] ) ) {
				$parts[] = (string) $client[ $k ];
			}
		}
		return trim( implode( ' ', $parts ) );
	}

	/**
	 * Resolve user from parsed identifiers in text.
	 *
	 * @param string $text Candidate text.
	 * @return array{status:string,user_id?:int}
	 */
	private static function resolve_user_from_candidate( $text ) {
		$ids  = self::extract_chat_ids( $text );
		$user = null;
		foreach ( $ids as $cid ) {
			$found = SimpleVPBot_Model_User::find_unique_approved_by_chat_id( $cid );
			if ( $found ) {
				if ( $user && (int) $user->id !== (int) $found->id ) {
					return array( 'status' => 'ambiguous' );
				}
				$user = $found;
			}
		}

		$usernames = self::extract_usernames( $text );
		foreach ( $usernames as $uname ) {
			$found = SimpleVPBot_Model_User::find_unique_approved_by_username( $uname );
			if ( $found ) {
				if ( $user && (int) $user->id !== (int) $found->id ) {
					return array( 'status' => 'ambiguous' );
				}
				$user = $found;
			}
		}

		if ( ! $user ) {
			return array( 'status' => 'none' );
		}
		return array( 'status' => 'ok', 'user_id' => (int) $user->id );
	}

	/**
	 * Extract unique @username tokens.
	 *
	 * @param string $text Text.
	 * @return array<int, string>
	 */
	private static function extract_usernames( $text ) {
		$t = (string) $text;
		$out = array();
		if ( preg_match_all( '/@([a-zA-Z0-9_]{3,64})/', $t, $m ) && ! empty( $m[1] ) ) {
			foreach ( $m[1] as $u ) {
				$out[] = strtolower( (string) $u );
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Extract likely chat ids from text.
	 *
	 * @param string $text Text.
	 * @return array<int, int>
	 */
	private static function extract_chat_ids( $text ) {
		$t = (string) $text;
		$out = array();
		if ( preg_match_all( '/(?<!\d)(\d{5,20})(?!\d)/', $t, $m ) && ! empty( $m[1] ) ) {
			foreach ( $m[1] as $n ) {
				$v = (int) $n;
				if ( $v > 0 ) {
					$out[] = $v;
				}
			}
		}
		return array_values( array_unique( $out ) );
	}
}
