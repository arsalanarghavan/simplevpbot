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

	/** Mis-scaled cap marker (51200 GB shown in UI after bad rebuild/sync). */
	const MISCALE_CAP_MARKER_GB = 51200;

	/**
	 * Whether stored quota bytes match the known 51200 GB cap bug.
	 *
	 * @param int $bytes svp_services.total_traffic or panel-equivalent bytes.
	 * @return bool
	 */
	public static function is_51200_cap_bug_bytes( $bytes ) {
		$b = (int) $bytes;
		if ( $b <= 0 ) {
			return false;
		}
		$gb = class_exists( 'SimpleVPBot_Service_Renew' )
			? (int) SimpleVPBot_Service_Renew::BYTES_PER_GB
			: 1073741824;
		if ( $b === self::MISCALE_CAP_MARKER_GB ) {
			return true;
		}
		$exact = (int) ( self::MISCALE_CAP_MARKER_GB * $gb );
		if ( $b === $exact ) {
			return true;
		}
		if ( $b > $exact && $b < $exact + $gb ) {
			return true;
		}
		$as_gb = (int) floor( $b / $gb );
		return self::MISCALE_CAP_MARKER_GB === $as_gb;
	}

	/** Bytes mistakenly applied when an earlier repair tool guessed 50 GB (51200÷1024 fallacy). */
	const WRONG_FALLBACK_GB = 50;

	/**
	 * Whether quota bytes equal the mistaken 50 GB fallback from the old repair tool.
	 *
	 * @param int $bytes Stored quota bytes.
	 * @return bool
	 */
	public static function is_wrong_50gb_fallback_bytes( $bytes ) {
		$gb = class_exists( 'SimpleVPBot_Service_Renew' )
			? (int) SimpleVPBot_Service_Renew::BYTES_PER_GB
			: 1073741824;
		return (int) $bytes === (int) ( self::WRONG_FALLBACK_GB * $gb );
	}

	/**
	 * Valid human traffic_gb for plans / remarks (excludes the 51200 bug marker).
	 *
	 * @param int $gb Gigabytes.
	 * @return bool
	 */
	public static function is_valid_traffic_gb( $gb ) {
		$g = (int) $gb;
		return $g >= 1 && $g <= 2048 && self::MISCALE_CAP_MARKER_GB !== $g;
	}

	/**
	 * Parse "· 100 GB" style volume from service remark (per-GB purchases).
	 *
	 * @param string $remark Service remark.
	 * @return int GB or 0.
	 */
	public static function volume_gb_from_service_remark( $remark ) {
		$r = trim( (string) $remark );
		if ( '' === $r || ! preg_match( '/·\s*(\d{1,4})\s*GB\b/i', $r, $m ) ) {
			return 0;
		}
		$g = (int) $m[1];
		return self::is_valid_traffic_gb( $g ) ? $g : 0;
	}

	/**
	 * Resolve correct quota bytes for 51200-cap repair. Never guesses — returns false when unknown.
	 *
	 * @param object $svc               Service row (plan_traffic_gb, plan_pricing_type, remark).
	 * @param int    $panel_id          Panel id.
	 * @param int    $inbound_id        Resolved inbound id.
	 * @param string $email             Client email.
	 * @param bool   $allow_panel_live  When false, skip live panel API (dry-run / preview).
	 * @return array{bytes:int,source:string}|false
	 */
	public static function resolve_quota_bytes_for_51200_repair( $svc, $panel_id, $inbound_id, $email, $allow_panel_live = true ) {
		$gb_u    = class_exists( 'SimpleVPBot_Service_Renew' )
			? (int) SimpleVPBot_Service_Renew::BYTES_PER_GB
			: 1073741824;
		$plan_gb = isset( $svc->plan_traffic_gb ) ? (int) $svc->plan_traffic_gb : 0;
		$pricing = isset( $svc->plan_pricing_type ) ? (string) $svc->plan_pricing_type : 'fixed';

		if ( 'per_gb' !== $pricing && self::is_valid_traffic_gb( $plan_gb ) ) {
			return array(
				'bytes'  => self::cap_traffic_bytes( $plan_gb * $gb_u ),
				'source' => 'plan_traffic_gb',
			);
		}

		$remark_gb = self::volume_gb_from_service_remark( isset( $svc->remark ) ? (string) $svc->remark : '' );
		if ( $remark_gb > 0 ) {
			if ( 'per_gb' === $pricing && class_exists( 'SimpleVPBot_Model_Plan' ) ) {
				$min = isset( $svc->plan_traffic_gb_min ) ? (int) $svc->plan_traffic_gb_min : 0;
				$max = isset( $svc->plan_traffic_gb_max ) ? (int) $svc->plan_traffic_gb_max : 0;
				if ( $min >= 1 && $max >= $min && ( $remark_gb < $min || $remark_gb > $max ) ) {
					$remark_gb = 0;
				}
			}
			if ( $remark_gb > 0 ) {
				return array(
					'bytes'  => self::cap_traffic_bytes( $remark_gb * $gb_u ),
					'source' => 'remark_volume_gb',
				);
			}
		}

		$cached = self::cached_client_limit_bytes( (int) $panel_id, (int) $inbound_id, (string) $email );
		if ( $cached > 0 ) {
			return array(
				'bytes'  => $cached,
				'source' => 'cache_limit_bytes',
			);
		}

		if ( $allow_panel_live ) {
			$live = self::panel_live_quota_bytes( (int) $panel_id, (int) $inbound_id, (string) $email );
			if ( $live > 0 ) {
				return array(
					'bytes'  => $live,
					'source' => 'panel_live',
				);
			}
		}

		return false;
	}

	/**
	 * Cached limit_bytes for a panel client (skipped when it still shows the 51200 bug).
	 *
	 * @param int    $panel_id   Panel id.
	 * @param int    $inbound_id Inbound id.
	 * @param string $email      Client email.
	 * @return int Bytes or 0.
	 */
	private static function cached_client_limit_bytes( $panel_id, $inbound_id, $email ) {
		if ( ! class_exists( 'SimpleVPBot_Model_Panel_Inbound_Client' ) ) {
			return 0;
		}
		global $wpdb;
		$t = SimpleVPBot_Model_Panel_Inbound_Client::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT limit_bytes FROM {$t} WHERE panel_id = %d AND inbound_id = %d AND email = %s LIMIT 1",
				(int) $panel_id,
				(int) $inbound_id,
				(string) $email
			)
		);
		$b = (int) $row;
		if ( $b <= 0 || self::is_51200_cap_bug_bytes( $b ) || self::is_wrong_50gb_fallback_bytes( $b ) ) {
			return 0;
		}
		return self::cap_traffic_bytes( $b );
	}

	/**
	 * Read live quota from panel inbound JSON + getClientTraffics.
	 *
	 * @param int    $panel_id   Panel id.
	 * @param int    $inbound_id Inbound id.
	 * @param string $email      Client email.
	 * @return int Bytes or 0.
	 */
	private static function panel_live_quota_bytes( $panel_id, $inbound_id, $email ) {
		if ( ! class_exists( 'SimpleVPBot_Xui_Client' ) ) {
			return 0;
		}
		$em    = trim( (string) $email );
		$bytes = 0;
		$ok    = SimpleVPBot_Xui_Client::run_with_panel(
			(int) $panel_id,
			static function () use ( $inbound_id, $em, &$bytes ) {
				if ( ! SimpleVPBot_Xui_Client::login() ) {
					return false;
				}
				$inb = SimpleVPBot_Xui_Client::inbound_get( (int) $inbound_id );
				$cl  = SimpleVPBot_Xui_Client::inbound_client_by_email( $inb, $em );
				if ( ! is_array( $cl ) ) {
					return false;
				}
				$bytes = self::resolve_quota_bytes( $cl['totalGB'] ?? 0, $em );
				return true;
			}
		);
		if ( ! $ok || $bytes <= 0 || self::is_51200_cap_bug_bytes( $bytes ) || self::is_wrong_50gb_fallback_bytes( $bytes ) ) {
			return 0;
		}
		return self::cap_traffic_bytes( (int) $bytes );
	}

	/**
	 * Target bytes after fixing the 51200 cap bug (plan only; 0 = unknown — do not guess).
	 *
	 * @param int $bytes            Current (buggy) bytes.
	 * @param int $plan_traffic_gb  Plan traffic_gb when linked (optional).
	 * @return int
	 */
	public static function correct_51200_cap_bytes( $bytes, $plan_traffic_gb = 0 ) {
		if ( ! self::is_51200_cap_bug_bytes( $bytes ) ) {
			return (int) $bytes;
		}
		$gb      = class_exists( 'SimpleVPBot_Service_Renew' )
			? (int) SimpleVPBot_Service_Renew::BYTES_PER_GB
			: 1073741824;
		$plan_gb = (int) $plan_traffic_gb;
		if ( self::is_valid_traffic_gb( $plan_gb ) ) {
			return self::cap_traffic_bytes( $plan_gb * $gb );
		}
		return 0;
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

			$resolved = self::resolve_user_id_from_panel_client_detail( $c );
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
	 * Resolve svp_users.id from a 3x-ui client row (tgId, bot email, remark, text ids).
	 *
	 * @param array<string, mixed> $client Panel client array.
	 * @return int|null User id or null when none/ambiguous.
	 */
	public static function resolve_user_id_from_panel_client( array $client ) {
		$resolved = self::resolve_user_id_from_panel_client_detail( $client );
		if ( 'ok' === $resolved['status'] && ! empty( $resolved['user_id'] ) ) {
			return (int) $resolved['user_id'];
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $client Panel client array.
	 * @return array{status:string,user_id?:int}
	 */
	public static function resolve_user_id_from_panel_client_detail( array $client ) {
		$found_ids = array();

		$tg = (int) ( $client['tgId'] ?? $client['tg_id'] ?? 0 );
		if ( $tg > 0 ) {
			$u = SimpleVPBot_Model_User::find_unique_approved_by_chat_id( $tg );
			if ( $u ) {
				$found_ids[] = (int) $u->id;
			}
		}

		$email = trim( (string) ( $client['email'] ?? '' ) );
		if ( '' !== $email && preg_match( '/^u(\d+)_/i', $email, $em ) ) {
			$uid = (int) $em[1];
			if ( self::is_approved_user_id( $uid ) ) {
				$found_ids[] = $uid;
			}
		}

		foreach ( array( 'remark', 'comment', 'memo', 'note', 'desc' ) as $rk ) {
			$uid = self::user_id_from_bot_remark_label( (string) ( $client[ $rk ] ?? '' ) );
			if ( $uid > 0 ) {
				$found_ids[] = $uid;
			}
		}

		$text_res = self::resolve_user_from_candidate( self::candidate_text( $client ) );
		if ( 'ambiguous' === $text_res['status'] ) {
			return array( 'status' => 'ambiguous' );
		}
		if ( 'ok' === $text_res['status'] && ! empty( $text_res['user_id'] ) ) {
			$found_ids[] = (int) $text_res['user_id'];
		}

		$found_ids = array_values( array_unique( array_filter( array_map( 'intval', $found_ids ) ) ) );
		if ( count( $found_ids ) > 1 ) {
			return array( 'status' => 'ambiguous' );
		}
		if ( 1 === count( $found_ids ) ) {
			return array( 'status' => 'ok', 'user_id' => $found_ids[0] );
		}
		return array( 'status' => 'none' );
	}

	/**
	 * @param int $user_id svp_users.id.
	 * @return bool
	 */
	private static function is_approved_user_id( $user_id ) {
		$uid = (int) $user_id;
		if ( $uid < 1 ) {
			return false;
		}
		$u = SimpleVPBot_Model_User::find( $uid );
		return $u && is_object( $u ) && 'approved' === (string) ( $u->status ?? '' );
	}

	/**
	 * Bot panel remark prefix `#123_slug` → user id.
	 *
	 * @param string $text Remark or comment.
	 * @return int
	 */
	private static function user_id_from_bot_remark_label( $text ) {
		$t = trim( (string) $text );
		if ( '' === $t ) {
			return 0;
		}
		if ( preg_match( '/^#(\d+)_/i', $t, $m ) ) {
			$uid = (int) $m[1];
			return self::is_approved_user_id( $uid ) ? $uid : 0;
		}
		return 0;
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
