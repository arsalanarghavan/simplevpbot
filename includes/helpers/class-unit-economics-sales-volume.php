<?php
/**
 * Rolling sold volume (GB) by panel from approved purchase/renew transactions.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Unit_Economics_Sales_Volume
 */
class SimpleVPBot_Unit_Economics_Sales_Volume {

	const CACHE_GROUP = 'simplevpbot';

	const CACHE_KEY_PREFIX = 'sales_vol_';

	/**
	 * Default rolling window (days).
	 */
	const DEFAULT_WINDOW_DAYS = 30;

	/**
	 * Rolling volume aggregated by panel.
	 *
	 * @param int|null $days Window length; null uses config or default.
	 * @return array{by_panel: array<int, float>, total_gb: float, window_days: int, computed_at: string, receipt_stats: array{pending_count: int, pending_gb_estimate: float}}
	 */
	public static function rolling_volume_by_panel( $days = null ) {
		$window = self::resolve_window_days( $days );
		$cached = wp_cache_get( self::CACHE_KEY_PREFIX . $window, self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$by_panel = array();
		$rows     = self::fetch_approved_transactions( $window );
		$plans    = array();
		$services = array();

		foreach ( $rows as $tx ) {
			$gb = self::gb_from_transaction_row( $tx, $plans, $services );
			if ( $gb <= 0 ) {
				continue;
			}
			$pid = self::panel_id_from_transaction_row( $tx, $plans, $services );
			if ( ! isset( $by_panel[ $pid ] ) ) {
				$by_panel[ $pid ] = 0.0;
			}
			$by_panel[ $pid ] += $gb;
		}

		$total = 0.0;
		foreach ( $by_panel as $v ) {
			$total += (float) $v;
		}

		$out = array(
			'by_panel'       => $by_panel,
			'total_gb'       => round( $total, 4 ),
			'window_days'    => $window,
			'computed_at'    => gmdate( 'Y-m-d H:i:s' ),
			'receipt_stats'  => self::pending_receipt_stats( $window ),
		);

		wp_cache_set( self::CACHE_KEY_PREFIX . $window, $out, self::CACHE_GROUP, 5 * MINUTE_IN_SECONDS );

		return $out;
	}

	/**
	 * @param int|null $days Override.
	 * @return int
	 */
	public static function resolve_window_days( $days = null ) {
		if ( null !== $days ) {
			return max( 1, min( 365, (int) $days ) );
		}
		if ( class_exists( 'SimpleVPBot_Model_Unit_Economics_Config' ) ) {
			$row = SimpleVPBot_Model_Unit_Economics_Config::get();
			if ( $row && isset( $row->volume_window_days ) ) {
				return max( 1, min( 365, (int) $row->volume_window_days ) );
			}
		}
		return self::DEFAULT_WINDOW_DAYS;
	}

	/**
	 * Clear cached sales volume (after config save, etc.).
	 */
	public static function bust_cache() {
		for ( $d = 1; $d <= 365; $d++ ) {
			wp_cache_delete( self::CACHE_KEY_PREFIX . $d, self::CACHE_GROUP );
		}
		wp_cache_delete( self::CACHE_KEY_PREFIX . self::DEFAULT_WINDOW_DAYS, self::CACHE_GROUP );
	}

	/**
	 * @param int $window Days.
	 * @return array<int, object>
	 */
	private static function fetch_approved_transactions( $window ) {
		global $wpdb;
		$t = $wpdb->prefix . 'svp_transactions';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, user_id, service_id, type, status, meta_json, created_at
				FROM {$t}
				WHERE status = 'approved'
				AND type IN ('purchase','renew')
				AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
				ORDER BY id ASC",
				$window
			)
		);
	}

	/**
	 * GB sold on this transaction (0 if not applicable).
	 *
	 * @param object               $tx       Transaction row.
	 * @param array<int, object>   $plans    Plan cache.
	 * @param array<int, object>   $services Service cache.
	 * @return float
	 */
	public static function gb_from_transaction_row( $tx, array &$plans = array(), array &$services = array() ) {
		$type = (string) ( $tx->type ?? '' );
		$meta = self::decode_meta( $tx->meta_json ?? '' );
		self::normalize_intent_meta( $meta );

		$intent = isset( $meta['intent'] ) ? (string) $meta['intent'] : '';

		if ( 'purchase' === $type ) {
			if ( 'add_volume' === $intent ) {
				return max( 0.0, (float) (int) ( $meta['extra_gb'] ?? 0 ) );
			}
			if ( in_array( $intent, array( 'renew_same', 'add_user_slots' ), true ) ) {
				return 0.0;
			}
			$plan_id = (int) ( $meta['plan_id'] ?? 0 );
			if ( $plan_id > 0 ) {
				$plan = self::plan_cached( $plan_id, $plans );
				if ( $plan ) {
					if ( class_exists( 'SimpleVPBot_Model_Plan' ) && SimpleVPBot_Model_Plan::is_per_gb( $plan ) ) {
						$vol = isset( $meta['volume_gb'] ) ? (int) $meta['volume_gb'] : 0;
						return max( 0.0, (float) $vol );
					}
					return max( 0.0, (float) (int) ( $plan->traffic_gb ?? 0 ) );
				}
				if ( isset( $meta['volume_gb'] ) && (int) $meta['volume_gb'] > 0 ) {
					return max( 0.0, (float) (int) $meta['volume_gb'] );
				}
			}
			if ( isset( $meta['volume_gb'] ) && (int) $meta['volume_gb'] > 0 ) {
				return max( 0.0, (float) (int) $meta['volume_gb'] );
			}
			return 0.0;
		}

		if ( 'renew' === $type ) {
			foreach ( array( 'volume_gb', 'extra_gb', 'gb', 'add_gb', 'traffic_gb', 'extra_traffic_gb' ) as $key ) {
				if ( isset( $meta[ $key ] ) && (int) $meta[ $key ] > 0 ) {
					return max( 0.0, (float) (int) $meta[ $key ] );
				}
			}
			return 0.0;
		}

		return 0.0;
	}

	/**
	 * Resolve panel_id for attribution.
	 *
	 * @param object             $tx       Transaction row.
	 * @param array<int, object> $plans    Plan cache.
	 * @param array<int, object> $services Service cache.
	 * @return int
	 */
	public static function panel_id_from_transaction_row( $tx, array &$plans = array(), array &$services = array() ) {
		$sid = (int) ( $tx->service_id ?? 0 );
		if ( $sid > 0 ) {
			$svc = self::service_cached( $sid, $services );
			if ( $svc && (int) ( $svc->panel_id ?? 0 ) > 0 ) {
				return (int) $svc->panel_id;
			}
		}

		$meta = self::decode_meta( $tx->meta_json ?? '' );
		$plan_id = (int) ( $meta['plan_id'] ?? 0 );
		if ( $plan_id > 0 ) {
			$plan = self::plan_cached( $plan_id, $plans );
			if ( $plan && (int) ( $plan->panel_id ?? 0 ) > 0 ) {
				return (int) $plan->panel_id;
			}
		}

		$meta_sid = (int) ( $meta['service_id'] ?? 0 );
		if ( $meta_sid > 0 ) {
			$svc = self::service_cached( $meta_sid, $services );
			if ( $svc && (int) ( $svc->panel_id ?? 0 ) > 0 ) {
				return (int) $svc->panel_id;
			}
		}

		return 0;
	}

	/**
	 * Pending receipt stats (informational only).
	 *
	 * @param int $window Days.
	 * @return array{pending_count: int, pending_gb_estimate: float}
	 */
	public static function pending_receipt_stats( $window ) {
		global $wpdb;
		$r_t = $wpdb->prefix . 'svp_receipts';
		$x_t = $wpdb->prefix . 'svp_transactions';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tx.meta_json
				FROM {$r_t} r
				INNER JOIN {$x_t} tx ON tx.id = r.transaction_id
				WHERE r.status = 'pending'
				AND tx.status = 'pending'
				AND tx.type IN ('purchase','renew')
				AND r.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
				$window
			)
		);
		$count = 0;
		$gb    = 0.0;
		$plans = array();
		$svc   = array();
		foreach ( $rows as $row ) {
			$count++;
			$fake       = (object) array(
				'type'       => 'purchase',
				'meta_json'  => $row->meta_json ?? '',
				'service_id' => 0,
			);
			$gb += self::gb_from_transaction_row( $fake, $plans, $svc );
		}
		return array(
			'pending_count'        => $count,
			'pending_gb_estimate'  => round( $gb, 4 ),
		);
	}

	/**
	 * @param string $raw JSON.
	 * @return array<string, mixed>
	 */
	public static function decode_meta( $raw ) {
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * @param array<string, mixed> $meta Meta by reference.
	 */
	public static function normalize_intent_meta( array &$meta ) {
		$intent = isset( $meta['intent'] ) ? (string) $meta['intent'] : '';
		if ( 'add_volume' === $intent && ( ! isset( $meta['extra_gb'] ) || (int) $meta['extra_gb'] < 1 ) ) {
			foreach ( array( 'volume_gb', 'gb', 'add_gb', 'traffic_gb', 'extra_traffic_gb' ) as $alias ) {
				if ( isset( $meta[ $alias ] ) && (int) $meta[ $alias ] > 0 ) {
					$meta['extra_gb'] = (int) $meta[ $alias ];
					break;
				}
			}
		}
	}

	/**
	 * @param int                $plan_id Plan id.
	 * @param array<int, object> $cache   Cache.
	 * @return object|null
	 */
	private static function plan_cached( $plan_id, array &$cache ) {
		$plan_id = (int) $plan_id;
		if ( $plan_id < 1 ) {
			return null;
		}
		if ( ! isset( $cache[ $plan_id ] ) ) {
			$cache[ $plan_id ] = class_exists( 'SimpleVPBot_Model_Plan' )
				? SimpleVPBot_Model_Plan::find( $plan_id )
				: null;
		}
		return $cache[ $plan_id ];
	}

	/**
	 * @param int                $service_id Service id.
	 * @param array<int, object> $cache      Cache.
	 * @return object|null
	 */
	private static function service_cached( $service_id, array &$cache ) {
		$service_id = (int) $service_id;
		if ( $service_id < 1 ) {
			return null;
		}
		if ( ! isset( $cache[ $service_id ] ) ) {
			$cache[ $service_id ] = class_exists( 'SimpleVPBot_Model_Service' )
				? SimpleVPBot_Model_Service::find( $service_id )
				: null;
		}
		return $cache[ $service_id ];
	}
}
