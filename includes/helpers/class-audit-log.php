<?php
/**
 * Dedicated admin audit log (billing, security, reseller ops).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Audit_Log
 */
class SimpleVPBot_Audit_Log {

	const PAYLOAD_MAX_BYTES = 8192;

	/**
	 * Normalize event type for storage (dots become underscores).
	 *
	 * @param string $event Raw event key.
	 * @return string
	 */
	private static function sanitize_event_type( $event ) {
		$event = strtolower( trim( (string) $event ) );
		$event = str_replace( '.', '_', $event );
		$event = sanitize_key( $event );
		if ( strlen( $event ) > 64 ) {
			$event = substr( $event, 0, 64 );
		}
		return $event;
	}

	/**
	 * Match legacy sanitize_key (dots stripped) and modern underscore form in filters.
	 *
	 * @param string $event Filter input.
	 * @return array<int, string>
	 */
	private static function event_type_filter_values( $event ) {
		$event = trim( (string) $event );
		if ( '' === $event ) {
			return array();
		}
		$legacy = sanitize_key( $event );
		$modern = self::sanitize_event_type( $event );
		$vals   = array();
		if ( '' !== $legacy ) {
			$vals[] = $legacy;
		}
		if ( '' !== $modern && $modern !== $legacy ) {
			$vals[] = $modern;
		}
		return array_values( array_unique( $vals ) );
	}

	/**
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_audit_log';
	}

	/**
	 * Record one audit row (best-effort).
	 *
	 * @param array<string, mixed> $args domain, event_type, actor_kind?, actor_wp_user_id?, actor_svp_user_id?,
	 *                                   target_type?, target_id?, reseller_scope_id?, payload?.
	 */
	public static function record( array $args ) {
		global $wpdb;
		$domain = sanitize_key( (string) ( $args['domain'] ?? 'admin' ) );
		if ( ! in_array( $domain, array( 'admin', 'billing', 'bot', 'security', 'reseller' ), true ) ) {
			$domain = 'admin';
		}
		$event = self::sanitize_event_type( (string) ( $args['event_type'] ?? 'event' ) );
		if ( '' === $event ) {
			$event = 'event';
		}
		$actor_kind = sanitize_key( (string) ( $args['actor_kind'] ?? 'system' ) );
		if ( ! in_array( $actor_kind, array( 'wp_admin', 'reseller', 'system', 'bot_user' ), true ) ) {
			$actor_kind = 'system';
		}
		$payload = $args['payload'] ?? array();
		if ( ! is_array( $payload ) ) {
			$payload = array( 'value' => $payload );
		}
		$json = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) {
			$json = '{}';
		}
		if ( strlen( $json ) > self::PAYLOAD_MAX_BYTES ) {
			$json = substr( $json, 0, self::PAYLOAD_MAX_BYTES ) . '…';
		}
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		$ip_hash = '' !== $ip ? hash( 'sha256', $ip ) : '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			self::table(),
			array(
				'domain'            => $domain,
				'event_type'        => $event,
				'actor_kind'        => $actor_kind,
				'actor_wp_user_id'  => max( 0, (int) ( $args['actor_wp_user_id'] ?? get_current_user_id() ) ),
				'actor_svp_user_id' => max( 0, (int) ( $args['actor_svp_user_id'] ?? 0 ) ),
				'target_type'       => sanitize_key( (string) ( $args['target_type'] ?? '' ) ),
				'target_id'         => max( 0, (int) ( $args['target_id'] ?? 0 ) ),
				'reseller_scope_id' => max( 0, (int) ( $args['reseller_scope_id'] ?? 0 ) ),
				'payload_json'      => $json,
				'ip_hash'           => $ip_hash,
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Paginated query for dashboard.
	 *
	 * @param array<string, mixed> $filters domain?, event_type?, q?.
	 * @param int                  $page    Page (1-based).
	 * @param int                  $per     Per page.
	 * @return array{rows:array<int,array<string,mixed>>, total:int}
	 */
	public static function query( array $filters, $page, $per ) {
		global $wpdb;
		$page = max( 1, (int) $page );
		$per  = max( 1, min( 100, (int) $per ) );
		$off  = ( $page - 1 ) * $per;
		$t    = self::table();
		$w    = array( '1=1' );
		$vals = array();

		$domain = isset( $filters['domain'] ) ? sanitize_key( (string) $filters['domain'] ) : '';
		if ( '' !== $domain ) {
			$w[]    = 'domain = %s';
			$vals[] = $domain;
		}
		$events = isset( $filters['event_type'] ) ? self::event_type_filter_values( (string) $filters['event_type'] ) : array();
		if ( ! empty( $events ) ) {
			if ( 1 === count( $events ) ) {
				$w[]    = 'event_type = %s';
				$vals[] = $events[0];
			} else {
				$placeholders = implode( ', ', array_fill( 0, count( $events ), '%s' ) );
				$w[]          = "event_type IN ({$placeholders})";
				foreach ( $events as $ev_row ) {
					$vals[] = $ev_row;
				}
			}
		}
		$q = isset( $filters['q'] ) ? trim( (string) $filters['q'] ) : '';
		if ( '' !== $q ) {
			$like   = '%' . $wpdb->esc_like( $q ) . '%';
			$w[]    = '( event_type LIKE %s OR payload_json LIKE %s )';
			$vals[] = $like;
			$vals[] = $like;
		}

		$where = implode( ' AND ', $w );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$total_sql = "SELECT COUNT(*) FROM {$t} WHERE {$where}";
		$total     = (int) $wpdb->get_var( $wpdb->prepare( $total_sql, $vals ) );

		$list_vals   = $vals;
		$list_vals[] = $per;
		$list_vals[] = $off;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$sql = "SELECT * FROM {$t} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$raw = $wpdb->get_results( $wpdb->prepare( $sql, $list_vals ), ARRAY_A );

		$rows = array();
		foreach ( (array) $raw as $r ) {
			$payload = array();
			if ( ! empty( $r['payload_json'] ) ) {
				$decoded = json_decode( (string) $r['payload_json'], true );
				if ( is_array( $decoded ) ) {
					$payload = $decoded;
				}
			}
			$rows[] = array(
				'id'                => (int) ( $r['id'] ?? 0 ),
				'created_at'        => (string) ( $r['created_at'] ?? '' ),
				'domain'            => (string) ( $r['domain'] ?? '' ),
				'event_type'        => (string) ( $r['event_type'] ?? '' ),
				'actor_kind'        => (string) ( $r['actor_kind'] ?? '' ),
				'actor_wp_user_id'  => (int) ( $r['actor_wp_user_id'] ?? 0 ),
				'actor_svp_user_id' => (int) ( $r['actor_svp_user_id'] ?? 0 ),
				'target_type'       => (string) ( $r['target_type'] ?? '' ),
				'target_id'         => (int) ( $r['target_id'] ?? 0 ),
				'reseller_scope_id' => (int) ( $r['reseller_scope_id'] ?? 0 ),
				'payload'           => $payload,
			);
		}

		return array(
			'rows'  => $rows,
			'total' => $total,
		);
	}

	/**
	 * Infer actor fields for current REST/dashboard context.
	 *
	 * @return array{actor_kind:string, actor_wp_user_id:int, actor_svp_user_id:int}
	 */
	public static function current_actor_fields() {
		$wp  = (int) get_current_user_id();
		$svp = 0;
		$kind = 'system';
		if ( $wp > 0 && current_user_can( 'manage_options' ) ) {
			$kind = 'wp_admin';
		}
		if ( class_exists( 'SimpleVPBot_Rest_Dashboard' ) ) {
			$ctx = SimpleVPBot_Rest_Dashboard::dashboard_actor_context();
			if ( ! empty( $ctx['isReseller'] ) && ! empty( $ctx['actorUserId'] ) ) {
				$kind = 'reseller';
				$svp  = (int) $ctx['actorUserId'];
			}
		}
		return array(
			'actor_kind'        => $kind,
			'actor_wp_user_id'  => $wp,
			'actor_svp_user_id' => $svp,
		);
	}
}
