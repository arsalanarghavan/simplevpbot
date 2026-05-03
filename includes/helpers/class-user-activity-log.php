<?php
/**
 * Append and query unified user activity (REST admin + bot updates).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_User_Activity_Log
 */
class SimpleVPBot_User_Activity_Log {

	const PAYLOAD_MAX_BYTES = 8192;

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_user_activity';
	}

	/**
	 * Insert one row.
	 *
	 * @param array<string, mixed> $args Keys: subject_svp_user_id (?int), channel, actor_kind,
	 *                                   actor_wp_user_id (?int), actor_svp_user_id (?int),
	 *                                   platform_chat_id (?int), event_type (string), payload (array|scalar).
	 */
	public static function append( array $args ) {
		global $wpdb;
		$subject = isset( $args['subject_svp_user_id'] ) ? (int) $args['subject_svp_user_id'] : 0;
		$subject = max( 0, $subject );
		$channel = sanitize_key( (string) ( $args['channel'] ?? 'rest' ) );
		if ( ! in_array( $channel, array( 'rest', 'telegram', 'bale' ), true ) ) {
			$channel = 'rest';
		}
		$actor_kind = sanitize_key( (string) ( $args['actor_kind'] ?? 'system' ) );
		if ( ! in_array( $actor_kind, array( 'wp_admin', 'svp_user', 'system' ), true ) ) {
			$actor_kind = 'system';
		}
		$awp   = isset( $args['actor_wp_user_id'] ) ? max( 0, (int) $args['actor_wp_user_id'] ) : 0;
		$asvp  = isset( $args['actor_svp_user_id'] ) ? max( 0, (int) $args['actor_svp_user_id'] ) : 0;
		$pchat = isset( $args['platform_chat_id'] ) ? (int) $args['platform_chat_id'] : 0;
		$event_type = sanitize_key( (string) ( $args['event_type'] ?? 'event' ) );
		if ( strlen( $event_type ) > 64 ) {
			$event_type = substr( $event_type, 0, 64 );
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
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->insert(
			self::table(),
			array(
				'subject_svp_user_id' => $subject,
				'channel'             => $channel,
				'actor_kind'          => $actor_kind,
				'actor_wp_user_id'    => $awp,
				'actor_svp_user_id'   => $asvp,
				'platform_chat_id'    => $pchat,
				'event_type'          => $event_type,
				'payload_json'        => $json,
			),
			array( '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Log one bot webhook update after user resolution.
	 *
	 * @param string               $platform telegram|bale.
	 * @param array<string, mixed> $update   Raw update.
	 * @param object|null          $user     svp_users row or null.
	 * @param int                  $from_id  Platform user id.
	 * @param int                  $chat_id  Chat id.
	 * @param array<string, mixed>|null $cb  callback_query or null.
	 * @param string|null          $text     Message text if any.
	 */
	public static function log_bot_update( $platform, array $update, $user, $from_id, $chat_id, $cb, $text ) {
		$plat = ( 'bale' === $platform ) ? 'bale' : 'telegram';
		$uid  = ( $user && ! empty( $user->id ) ) ? (int) $user->id : 0;
		$upd_id = isset( $update['update_id'] ) ? (int) $update['update_id'] : 0;
		$base   = array(
			'update_id' => $upd_id,
			'from_id'   => (int) $from_id,
			'chat_id'   => (int) $chat_id,
		);
		if ( is_array( $cb ) ) {
			$data = isset( $cb['data'] ) ? (string) $cb['data'] : '';
			if ( strlen( $data ) > 512 ) {
				$data = substr( $data, 0, 512 ) . '…';
			}
			$base['callback_data'] = $data;
			$event                 = 'callback_query';
		} else {
			$t = is_string( $text ) ? trim( $text ) : '';
			if ( $t !== '' && preg_match( '#^/([a-zA-Z0-9_]+)(?:@[a-zA-Z0-9_]+)?(\s|$)#u', $t, $m ) ) {
				$base['command'] = '/' . strtolower( $m[1] );
				$event           = 'command';
			} else {
				$event = 'message';
				if ( strlen( $t ) > 240 ) {
					$t = substr( $t, 0, 240 ) . '…';
				}
				$base['text_preview'] = $t;
			}
		}
		self::append(
			array(
				'subject_svp_user_id' => $uid,
				'channel'             => $plat,
				'actor_kind'          => $uid > 0 ? 'svp_user' : 'system',
				'actor_wp_user_id'    => 0,
				'actor_svp_user_id'   => $uid,
				'platform_chat_id'    => (int) $chat_id,
				'event_type'          => $event,
				'payload'             => $base,
			)
		);
	}

	/**
	 * Paginated rows for a subject user (newest first).
	 *
	 * @param int $user_id svp_users.id.
	 * @param int $page    1-based.
	 * @param int $per_page Per page (max 100).
	 * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
	 */
	public static function fetch_for_subject( $user_id, $page, $per_page ) {
		global $wpdb;
		$uid = (int) $user_id;
		if ( $uid < 1 ) {
			return array( 'rows' => array(), 'total' => 0, 'page' => 1, 'per_page' => 20 );
		}
		$page     = max( 1, (int) $page );
		$per_page = max( 1, min( 100, (int) $per_page ) );
		$off      = ( $page - 1 ) * $per_page;
		$t        = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE subject_svp_user_id = %d", $uid ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t} WHERE subject_svp_user_id = %d ORDER BY id DESC LIMIT %d OFFSET %d",
				$uid,
				$per_page,
				$off
			),
			ARRAY_A
		);
		$rows = array();
		foreach ( (array) $raw as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$pj = isset( $r['payload_json'] ) ? json_decode( (string) $r['payload_json'], true ) : null;
			$r['payload'] = is_array( $pj ) ? $pj : array();
			unset( $r['payload_json'] );
			$rows[] = $r;
		}
		return array(
			'rows'     => $rows,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}
}
