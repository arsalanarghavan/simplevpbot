<?php
/**
 * Marketing automation rules (segments + discount offer templates).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Marketing_Rule
 */
class SimpleVPBot_Model_Marketing_Rule {

	const SEGMENT_KEYS = array(
		'churned',
		'never_purchased',
		'abandoned_checkout',
		'stale_buy_funnel',
		'expiring_renew',
	);

	/**
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_marketing_rules';
	}

	/**
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function find( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id )
		); // phpcs:ignore
	}

	/**
	 * Active rules for owner scope (0 = site) ordered by priority.
	 *
	 * @param int $owner_svp_user_id 0 or reseller id.
	 * @return array<int, object>
	 */
	public static function list_active_for_owner( $owner_svp_user_id = 0 ) {
		global $wpdb;
		$oid = max( 0, (int) $owner_svp_user_id );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE owner_svp_user_id = %d AND enabled = 1 ORDER BY priority ASC, id ASC',
				$oid
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * All rules visible to dashboard (site sees all site rules; reseller sees own).
	 *
	 * @param int $owner_svp_user_id 0 = site admin list (owner 0 only), else reseller id.
	 * @param bool $site_admin When true and owner 0, list only site rules.
	 * @return array<int, object>
	 */
	public static function list_for_dashboard( $owner_svp_user_id = 0, $site_admin = true ) {
		global $wpdb;
		$oid = max( 0, (int) $owner_svp_user_id );
		if ( $site_admin && 0 === $oid ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY owner_svp_user_id ASC, priority ASC, id ASC' );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM ' . self::table() . ' WHERE owner_svp_user_id = %d ORDER BY priority ASC, id ASC',
					$oid
				)
			);
		}
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param array<string, mixed> $data Row.
	 * @return int
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$wpdb->insert( self::table(), $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int                  $id Id.
	 * @param array<string, mixed> $data Row.
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$wpdb->update( self::table(), $data, array( 'id' => (int) $id ) );
	}

	/**
	 * @param int $id Id.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $id ) );
	}

	/**
	 * Sanitize segment key.
	 *
	 * @param string $key Raw.
	 * @return string
	 */
	public static function sanitize_segment( $key ) {
		$k = sanitize_key( (string) $key );
		return in_array( $k, self::SEGMENT_KEYS, true ) ? $k : '';
	}

	/**
	 * Row as REST/dashboard array.
	 *
	 * @param object|null $row Db row.
	 * @return array<string, mixed>|null
	 */
	public static function to_payload( $row ) {
		if ( ! $row || ! is_object( $row ) ) {
			return null;
		}
		return array(
			'id'                  => (int) ( $row->id ?? 0 ),
			'owner_svp_user_id'   => (int) ( $row->owner_svp_user_id ?? 0 ),
			'segment_key'         => (string) ( $row->segment_key ?? '' ),
			'enabled'             => ! empty( $row->enabled ),
			'priority'            => (int) ( $row->priority ?? 100 ),
			'cooldown_days'       => (int) ( $row->cooldown_days ?? 90 ),
			'after_days'          => (int) ( $row->after_days ?? 0 ),
			'pending_hours'       => (int) ( $row->pending_hours ?? 0 ),
			'funnel_idle_hours'   => (int) ( $row->funnel_idle_hours ?? 0 ),
			'expires_within_days' => (int) ( $row->expires_within_days ?? 0 ),
			'discount_type'       => (string) ( $row->discount_type ?? 'percent' ),
			'discount_value'      => (float) ( $row->discount_value ?? 0 ),
			'max_discount_toman'  => isset( $row->max_discount_toman ) ? (float) $row->max_discount_toman : null,
			'code_valid_days'     => (int) ( $row->code_valid_days ?? 7 ),
			'max_uses_per_user'   => (int) ( $row->max_uses_per_user ?? 1 ),
			'message_body'        => (string) ( $row->message_body ?? '' ),
			'channel_telegram'    => ! isset( $row->channel_telegram ) || ! empty( $row->channel_telegram ),
			'channel_bale'        => ! isset( $row->channel_bale ) || ! empty( $row->channel_bale ),
			'created_at'          => (string) ( $row->created_at ?? '' ),
			'updated_at'          => (string) ( $row->updated_at ?? '' ),
		);
	}

	/**
	 * Default site rules from legacy idle settings (once).
	 */
	public static function seed_defaults_if_empty() {
		if ( get_option( 'simplevpbot_marketing_rules_seeded_v1' ) ) {
			return;
		}
		global $wpdb;
		$t   = self::table();
		$cnt = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" ); // phpcs:ignore
		if ( $cnt > 0 ) {
			update_option( 'simplevpbot_marketing_rules_seeded_v1', 1, false );
			self::seed_extra_segments_if_missing();
			return;
		}
		$after = max( 7, (int) SimpleVPBot_Settings::get( 'notify_idle_after_days', 45 ) );
		$cool  = max( 7, (int) SimpleVPBot_Settings::get( 'notify_idle_cooldown_days', 90 ) );
		$idle  = SimpleVPBot_Settings::get( 'notify_idle_enabled', false );
		$now   = current_time( 'mysql' );
		$rules = array(
			array(
				'owner_svp_user_id'   => 0,
				'segment_key'         => 'churned',
				'enabled'             => $idle ? 1 : 0,
				'priority'            => 10,
				'cooldown_days'       => $cool,
				'after_days'          => $after,
				'pending_hours'       => 0,
				'funnel_idle_hours'   => 0,
				'expires_within_days' => 0,
				'discount_type'       => 'percent',
				'discount_value'      => 10,
				'max_discount_toman'  => null,
				'code_valid_days'     => 14,
				'max_uses_per_user'   => 1,
				'message_body'        => '',
				'channel_telegram'    => 1,
				'channel_bale'        => 1,
				'created_at'          => $now,
				'updated_at'          => $now,
			),
			array(
				'owner_svp_user_id'   => 0,
				'segment_key'         => 'never_purchased',
				'enabled'             => 0,
				'priority'            => 20,
				'cooldown_days'       => 30,
				'after_days'          => 3,
				'pending_hours'       => 0,
				'funnel_idle_hours'   => 0,
				'expires_within_days' => 0,
				'discount_type'       => 'percent',
				'discount_value'      => 15,
				'max_discount_toman'  => 50000,
				'code_valid_days'     => 7,
				'max_uses_per_user'   => 1,
				'message_body'        => '',
				'channel_telegram'    => 1,
				'channel_bale'        => 1,
				'created_at'          => $now,
				'updated_at'          => $now,
			),
			array(
				'owner_svp_user_id'   => 0,
				'segment_key'         => 'abandoned_checkout',
				'enabled'             => 0,
				'priority'            => 30,
				'cooldown_days'       => 14,
				'after_days'          => 0,
				'pending_hours'       => 24,
				'funnel_idle_hours'   => 0,
				'expires_within_days' => 0,
				'discount_type'       => 'percent',
				'discount_value'      => 10,
				'max_discount_toman'  => null,
				'code_valid_days'     => 3,
				'max_uses_per_user'   => 1,
				'message_body'        => '',
				'channel_telegram'    => 1,
				'channel_bale'        => 1,
				'created_at'          => $now,
				'updated_at'          => $now,
			),
		);
		foreach ( $rules as $r ) {
			self::insert( $r );
		}
		update_option( 'simplevpbot_marketing_rules_seeded_v1', 1, false );
		self::seed_extra_segments_if_missing();
	}

	/**
	 * Add stale_buy_funnel and expiring_renew defaults when missing.
	 */
	public static function seed_extra_segments_if_missing() {
		if ( get_option( 'simplevpbot_marketing_rules_seeded_v2' ) ) {
			return;
		}
		global $wpdb;
		$t   = self::table();
		$now = current_time( 'mysql' );
		$extra = array(
			array(
				'segment_key' => 'stale_buy_funnel',
				'priority'    => 40,
				'funnel_idle_hours' => 48,
				'discount_value' => 10,
				'code_valid_days' => 5,
			),
			array(
				'segment_key' => 'expiring_renew',
				'priority'    => 50,
				'expires_within_days' => 7,
				'discount_value' => 15,
				'code_valid_days' => 10,
			),
		);
		foreach ( $extra as $e ) {
			$sk = (string) $e['segment_key'];
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$exists = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$t} WHERE owner_svp_user_id = 0 AND segment_key = %s",
					$sk
				)
			);
			if ( $exists > 0 ) {
				continue;
			}
			self::insert(
				array(
					'owner_svp_user_id'   => 0,
					'segment_key'         => $sk,
					'enabled'             => 0,
					'priority'            => (int) $e['priority'],
					'cooldown_days'       => 30,
					'after_days'          => 0,
					'pending_hours'       => 0,
					'funnel_idle_hours'   => (int) ( $e['funnel_idle_hours'] ?? 0 ),
					'expires_within_days' => (int) ( $e['expires_within_days'] ?? 0 ),
					'discount_type'       => 'percent',
					'discount_value'      => (float) ( $e['discount_value'] ?? 10 ),
					'max_discount_toman'  => null,
					'code_valid_days'     => (int) ( $e['code_valid_days'] ?? 7 ),
					'max_uses_per_user'   => 1,
					'message_body'        => '',
					'channel_telegram'    => 1,
					'channel_bale'        => 1,
					'created_at'          => $now,
					'updated_at'          => $now,
				)
			);
		}
		update_option( 'simplevpbot_marketing_rules_seeded_v2', 1, false );
	}
}
