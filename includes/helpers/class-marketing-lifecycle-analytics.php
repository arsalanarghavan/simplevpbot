<?php
/**
 * Marketing lifecycle KPIs, funnel, segment counts, eligibility SQL.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Marketing_Lifecycle_Analytics
 */
class SimpleVPBot_Marketing_Lifecycle_Analytics {

	const ALLOWED_WINDOW_DAYS = array( 7, 30, 90 );

	/**
	 * @param int $raw Request days.
	 * @return int
	 */
	public static function normalize_window_days( $raw ) {
		$d = (int) $raw;
		return in_array( $d, self::ALLOWED_WINDOW_DAYS, true ) ? $d : 30;
	}

	/**
	 * Scope user ids clause for reseller.
	 *
	 * @param int    $owner_svp_user_id 0 = all users.
	 * @param string $alias User table alias.
	 * @return array{sql:string,values:array<int>}|null
	 */
	public static function scope_clause( $owner_svp_user_id, $alias = 'u' ) {
		$oid = (int) $owner_svp_user_id;
		if ( $oid < 1 ) {
			return null;
		}
		return SimpleVPBot_Model_User::reseller_moderation_scope_clause( $oid, $alias );
	}

	/**
	 * Full dashboard payload.
	 *
	 * @param int $window_days 7|30|90.
	 * @param int $owner_svp_user_id 0 site, >0 reseller.
	 * @param bool $site_admin_list_rules List all rules when site admin.
	 * @return array<string, mixed>
	 */
	public static function build_dashboard_payload( $window_days, $owner_svp_user_id = 0, $site_admin_list_rules = true ) {
		$window = self::normalize_window_days( $window_days );
		$since  = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $window . ' days', time() ) );
		$segments = self::segment_counts( $owner_svp_user_id );
		$offer_stats = self::offer_stats( $since, $owner_svp_user_id, $site_admin_list_rules );
		$funnel = self::funnel_daily( $since, $owner_svp_user_id );
		$rules = array();
		$rule_rows = SimpleVPBot_Model_Marketing_Rule::list_for_dashboard( $owner_svp_user_id, $site_admin_list_rules );
		foreach ( $rule_rows as $r ) {
			$p = SimpleVPBot_Model_Marketing_Rule::to_payload( $r );
			if ( $p ) {
				$rules[] = $p;
			}
		}
		return array(
			'window_days' => $window,
			'since'       => $since,
			'summary'     => array_merge(
				$offer_stats,
				array(
					'segment_counts'   => $segments,
					'retention_rate'   => self::retention_rate( $since, $owner_svp_user_id ),
					'new_to_paid_rate' => self::new_to_paid_rate( $since, $owner_svp_user_id ),
				)
			),
			'funnel'      => $funnel,
			'rules'       => $rules,
			'rule_stats'  => self::per_rule_stats( $since, $rule_rows, $owner_svp_user_id ),
		);
	}

	/**
	 * Default thresholds when no enabled rule exists for a segment.
	 *
	 * @param string $segment_key Segment key.
	 * @return object
	 */
	public static function default_rule_object( $segment_key ) {
		$sk = SimpleVPBot_Model_Marketing_Rule::sanitize_segment( (string) $segment_key );
		return (object) array(
			'segment_key'         => $sk,
			'after_days'          => 'never_purchased' === $sk ? 3 : 45,
			'pending_hours'       => 24,
			'funnel_idle_hours'   => 48,
			'expires_within_days' => 7,
		);
	}

	/**
	 * Best enabled rule for segment + owner (lowest priority, then id).
	 *
	 * @param string $segment_key Segment.
	 * @param int    $owner_svp_user_id Owner scope.
	 * @return object
	 */
	public static function resolve_rule_for_segment( $segment_key, $owner_svp_user_id = 0 ) {
		$sk = SimpleVPBot_Model_Marketing_Rule::sanitize_segment( (string) $segment_key );
		if ( '' === $sk ) {
			return self::default_rule_object( $segment_key );
		}
		global $wpdb;
		$oid = max( 0, (int) $owner_svp_user_id );
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . SimpleVPBot_Model_Marketing_Rule::table() . ' WHERE owner_svp_user_id = %d AND segment_key = %s AND enabled = 1 ORDER BY priority ASC, id ASC LIMIT 1',
				$oid,
				$sk
			)
		); // phpcs:ignore
		return $row ? $row : self::default_rule_object( $sk );
	}

	/**
	 * @param int $owner Scope.
	 * @return array<string, int>
	 */
	public static function segment_counts( $owner_svp_user_id = 0 ) {
		$out = array();
		foreach ( SimpleVPBot_Model_Marketing_Rule::SEGMENT_KEYS as $sk ) {
			$rule      = self::resolve_rule_for_segment( $sk, (int) $owner_svp_user_id );
			$out[ $sk ] = count( self::eligible_user_ids_for_rule( $rule, (int) $owner_svp_user_id, 500 ) );
		}
		return $out;
	}

	/**
	 * Per-rule performance in window + current eligible count.
	 *
	 * @param string             $since Since datetime.
	 * @param array<int, object> $rule_rows Rule rows.
	 * @param int                $owner_svp_user_id Scope.
	 * @return array<int, array<string, mixed>>
	 */
	public static function per_rule_stats( $since, array $rule_rows, $owner_svp_user_id = 0 ) {
		global $wpdb;
		$ot  = SimpleVPBot_Model_Marketing_Offer::table();
		$tx  = SimpleVPBot_Model_Transaction::table();
		$out = array();
		foreach ( $rule_rows as $rule ) {
			$rid = (int) ( $rule->id ?? 0 );
			if ( $rid < 1 ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sent = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$ot} WHERE rule_id = %d AND status IN ('sent','converted') AND sent_at >= %s",
					$rid,
					$since
				)
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$conv = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$ot} WHERE rule_id = %d AND status = 'converted' AND sent_at >= %s",
					$rid,
					$since
				)
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$revenue = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(ABS(t.amount)),0) FROM {$tx} t
					INNER JOIN {$ot} o ON o.converted_transaction_id = t.id
					WHERE o.rule_id = %d AND o.status = 'converted' AND t.status = 'approved' AND o.sent_at >= %s",
					$rid,
					$since
				)
			);
			$eligible = count( self::eligible_user_ids_for_rule( $rule, (int) $owner_svp_user_id, 500 ) );
			$rate     = $sent > 0 ? round( ( $conv / $sent ) * 100, 2 ) : 0.0;
			$out[]    = array(
				'rule_id'          => $rid,
				'segment_key'      => (string) ( $rule->segment_key ?? '' ),
				'sent'             => $sent,
				'converted'        => $conv,
				'success_rate'     => $rate,
				'revenue_toman'    => round( $revenue, 2 ),
				'eligible_now'     => $eligible,
			);
		}
		return $out;
	}

	/**
	 * @param object $rule Rule row.
	 * @param int    $owner_svp_user_id Scope.
	 * @param int    $limit Limit.
	 * @return array<int, int>
	 */
	public static function eligible_user_ids_for_rule( $rule, $owner_svp_user_id, $limit = 40 ) {
		global $wpdb;
		$seg = SimpleVPBot_Model_Marketing_Rule::sanitize_segment( (string) ( $rule->segment_key ?? '' ) );
		if ( '' === $seg ) {
			return array();
		}
		$u_tbl = SimpleVPBot_Model_User::table();
		$tx_t  = SimpleVPBot_Model_Transaction::table();
		$s_tbl = SimpleVPBot_Model_Service::table();
		$scope = self::scope_clause( $owner_svp_user_id, 'u' );
		$scope_sql = $scope ? $scope['sql'] : '';
		$scope_vals = $scope ? $scope['values'] : array();
		$lim = max( 1, min( 500, (int) $limit ) );
		$sql = '';
		$vals = array_merge( $scope_vals );

		if ( 'churned' === $seg ) {
			$after = max( 1, (int) ( $rule->after_days ?? 45 ) );
			$cut   = gmdate( 'Y-m-d H:i:s', time() - $after * DAY_IN_SECONDS );
			$sql   = "SELECT u.id FROM {$u_tbl} u WHERE u.status = 'approved'{$scope_sql}
				AND EXISTS (SELECT 1 FROM {$tx_t} t WHERE t.user_id = u.id AND t.status = 'approved' AND t.type IN ('purchase','renew'))
				AND NOT EXISTS (SELECT 1 FROM {$tx_t} t2 WHERE t2.user_id = u.id AND t2.status = 'approved' AND t2.type IN ('purchase','renew') AND t2.created_at >= %s)
				LIMIT %d";
			$vals[] = $cut;
			$vals[] = $lim;
		} elseif ( 'never_purchased' === $seg ) {
			$after = max( 1, (int) ( $rule->after_days ?? 3 ) );
			$cut   = gmdate( 'Y-m-d H:i:s', time() - $after * DAY_IN_SECONDS );
			$sql   = "SELECT u.id FROM {$u_tbl} u WHERE u.status = 'approved' AND u.created_at <= %s{$scope_sql}
				AND NOT EXISTS (SELECT 1 FROM {$tx_t} t WHERE t.user_id = u.id AND t.status = 'approved' AND t.type IN ('purchase','renew'))
				LIMIT %d";
			$vals[] = $cut;
			$vals[] = $lim;
		} elseif ( 'abandoned_checkout' === $seg ) {
			$hrs = max( 1, (int) ( $rule->pending_hours ?? 24 ) );
			$cut = gmdate( 'Y-m-d H:i:s', time() - $hrs * HOUR_IN_SECONDS );
			$sql = "SELECT DISTINCT u.id FROM {$u_tbl} u INNER JOIN {$tx_t} t ON t.user_id = u.id
				WHERE u.status = 'approved' AND t.status = 'pending' AND t.type = 'purchase' AND t.created_at <= %s{$scope_sql}
				LIMIT %d";
			$vals[] = $cut;
			$vals[] = $lim;
		} elseif ( 'stale_buy_funnel' === $seg ) {
			$hrs = max( 1, (int) ( $rule->funnel_idle_hours ?? 48 ) );
			$cut = gmdate( 'Y-m-d H:i:s', time() - $hrs * HOUR_IN_SECONDS );
			$sql = "SELECT u.id FROM {$u_tbl} u WHERE u.status = 'approved' AND u.state IS NOT NULL
				AND u.state LIKE 'buy_%'{$scope_sql}
				AND NOT EXISTS (SELECT 1 FROM {$tx_t} t WHERE t.user_id = u.id AND t.status = 'pending' AND t.type = 'purchase' AND t.created_at >= %s)
				LIMIT %d";
			$vals[] = $cut;
			$vals[] = $lim;
		} elseif ( 'expiring_renew' === $seg ) {
			$days = max( 1, (int) ( $rule->expires_within_days ?? 7 ) );
			$end  = gmdate( 'Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS );
			$sql  = "SELECT DISTINCT u.id FROM {$u_tbl} u INNER JOIN {$s_tbl} s ON s.user_id = u.id
				WHERE u.status = 'approved' AND s.deleted_at IS NULL
				AND s.expires_at IS NOT NULL AND s.expires_at > UTC_TIMESTAMP() AND s.expires_at <= %s{$scope_sql}
				LIMIT %d";
			$vals[] = $end;
			$vals[] = $lim;
		}

		if ( '' === $sql ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$cols = $wpdb->get_col( $wpdb->prepare( $sql, $vals ) );
		$out  = array();
		foreach ( (array) $cols as $c ) {
			$id = (int) $c;
			if ( $id > 0 ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/**
	 * @param string $since Since datetime.
	 * @param int    $owner Scope.
	 * @param bool   $site_admin Site admin sees all offers.
	 * @return array<string, mixed>
	 */
	private static function offer_stats( $since, $owner_svp_user_id, $site_admin ) {
		global $wpdb;
		$ot = SimpleVPBot_Model_Marketing_Offer::table();
		$rt = SimpleVPBot_Model_Marketing_Rule::table();
		$tx = SimpleVPBot_Model_Transaction::table();
		$scope = '';
		$vals  = array( $since );
		if ( ! $site_admin || (int) $owner_svp_user_id > 0 ) {
			$scope = ' AND r.owner_svp_user_id = %d ';
			$vals[] = (int) $owner_svp_user_id;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sent = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$ot} o INNER JOIN {$rt} r ON r.id = o.rule_id
			WHERE o.status IN ('sent','converted') AND o.sent_at >= %s{$scope}",
			$vals
		) );
		$vals_c = $vals;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$conv = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$ot} o INNER JOIN {$rt} r ON r.id = o.rule_id
			WHERE o.status = 'converted' AND o.sent_at >= %s{$scope}",
			$vals_c
		) );
		$rev_vals = array( $since );
		$rev_scope = '';
		if ( ! $site_admin || (int) $owner_svp_user_id > 0 ) {
			$rev_scope = ' AND r.owner_svp_user_id = %d ';
			$rev_vals[] = (int) $owner_svp_user_id;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$revenue = (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(ABS(t.amount)),0) FROM {$tx} t
			INNER JOIN {$ot} o ON o.converted_transaction_id = t.id
			INNER JOIN {$rt} r ON r.id = o.rule_id
			WHERE t.status = 'approved' AND o.status = 'converted' AND t.created_at >= %s{$rev_scope}",
			$rev_vals
		) );
		$rate = $sent > 0 ? round( ( $conv / $sent ) * 100, 2 ) : 0.0;
		$ab_scope = $scope ? ' AND r.owner_svp_user_id = %d AND r.segment_key = %s ' : " AND r.segment_key = %s ";
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ab_sent = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$ot} o INNER JOIN {$rt} r ON r.id = o.rule_id
			WHERE o.status IN ('sent','converted') AND o.sent_at >= %s{$ab_scope}",
			$scope ? array( $since, (int) $owner_svp_user_id, 'abandoned_checkout' ) : array( $since, 'abandoned_checkout' )
		) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ab_conv = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$ot} o INNER JOIN {$rt} r ON r.id = o.rule_id
			WHERE o.status = 'converted' AND o.sent_at >= %s{$ab_scope}",
			$scope ? array( $since, (int) $owner_svp_user_id, 'abandoned_checkout' ) : array( $since, 'abandoned_checkout' )
		) );
		$ab_rate = $ab_sent > 0 ? round( ( $ab_conv / $ab_sent ) * 100, 2 ) : 0.0;
		return array(
			'offers_sent'              => $sent,
			'offers_converted'         => $conv,
			'sent_count'               => $sent,
			'converted_count'          => $conv,
			'offer_success_rate'       => $rate,
			'abandoned_recovery_rate'  => $ab_rate,
			'campaign_revenue_toman'   => round( $revenue, 2 ),
		);
	}

	/**
	 * @param string $since Since.
	 * @param int    $owner Scope.
	 * @return float Percent 0-100.
	 */
	private static function retention_rate( $since, $owner_svp_user_id ) {
		global $wpdb;
		$u_tbl = SimpleVPBot_Model_User::table();
		$tx_t  = SimpleVPBot_Model_Transaction::table();
		$scope = self::scope_clause( $owner_svp_user_id, 'u' );
		$scope_sql = $scope ? $scope['sql'] : '';
		$scope_vals = $scope ? $scope['values'] : array();
		$before = gmdate( 'Y-m-d H:i:s', strtotime( $since . ' -30 days', time() ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$base = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT u.id) FROM {$u_tbl} u INNER JOIN {$tx_t} t ON t.user_id = u.id
			WHERE t.status = 'approved' AND t.type IN ('purchase','renew') AND t.created_at < %s AND t.created_at >= %s{$scope_sql}",
			array_merge( array( $since, $before ), $scope_vals )
		) );
		if ( $base < 1 ) {
			return 0.0;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ret = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT u.id) FROM {$u_tbl} u INNER JOIN {$tx_t} t ON t.user_id = u.id
			WHERE t.status = 'approved' AND t.type IN ('purchase','renew') AND t.created_at >= %s{$scope_sql}
			AND EXISTS (SELECT 1 FROM {$tx_t} t0 WHERE t0.user_id = u.id AND t0.status = 'approved' AND t0.created_at < %s)",
			array_merge( array( $since, $since ), $scope_vals )
		) );
		return round( ( $ret / $base ) * 100, 2 );
	}

	/**
	 * @param string $since Since.
	 * @param int    $owner Scope.
	 * @return float
	 */
	private static function new_to_paid_rate( $since, $owner_svp_user_id ) {
		global $wpdb;
		$u_tbl = SimpleVPBot_Model_User::table();
		$tx_t  = SimpleVPBot_Model_Transaction::table();
		$scope = self::scope_clause( $owner_svp_user_id, 'u' );
		$scope_sql = $scope ? $scope['sql'] : '';
		$scope_vals = $scope ? $scope['values'] : array();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$new_users = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$u_tbl} u WHERE u.status = 'approved' AND u.created_at >= %s{$scope_sql}",
			array_merge( array( $since ), $scope_vals )
		) );
		if ( $new_users < 1 ) {
			return 0.0;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$paid = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT u.id) FROM {$u_tbl} u INNER JOIN {$tx_t} t ON t.user_id = u.id
			WHERE u.status = 'approved' AND u.created_at >= %s AND t.status = 'approved' AND t.type IN ('purchase','renew'){$scope_sql}",
			array_merge( array( $since ), $scope_vals )
		) );
		return round( ( $paid / $new_users ) * 100, 2 );
	}

	/**
	 * @param string $since Since.
	 * @param int    $owner Scope.
	 * @return array<int, array<string, mixed>>
	 */
	private static function funnel_daily( $since, $owner_svp_user_id ) {
		global $wpdb;
		$u_tbl = SimpleVPBot_Model_User::table();
		$tx_t  = SimpleVPBot_Model_Transaction::table();
		$scope = self::scope_clause( $owner_svp_user_id, 'u' );
		$scope_sql = $scope ? str_replace( 'u.', 'u2.', $scope['sql'] ) : '';
		$scope_vals = $scope ? $scope['values'] : array();
		$days = array();
		$start = strtotime( $since );
		for ( $t = $start; $t <= time(); $t += DAY_IN_SECONDS ) {
			$d = gmdate( 'Y-m-d', $t );
			$days[ $d ] = array(
				'date'            => $d,
				'registered'      => 0,
				'first_pending'   => 0,
				'first_paid'      => 0,
			);
		}
		$vals = array_merge( array( $since ), $scope_vals );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$reg = $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE(u.created_at) AS d, COUNT(*) AS c FROM {$u_tbl} u
			WHERE u.status = 'approved' AND u.created_at >= %s{$scope_sql} GROUP BY DATE(u.created_at)",
			$vals
		), ARRAY_A );
		foreach ( (array) $reg as $r ) {
			$d = (string) ( $r['d'] ?? '' );
			if ( isset( $days[ $d ] ) ) {
				$days[ $d ]['registered'] = (int) ( $r['c'] ?? 0 );
			}
		}
		$scope_u = $scope ? $scope['sql'] : '';
		$pend_vals = array_merge( array( $since ), $scope_vals );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$pend = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(t.created_at) AS d, COUNT(DISTINCT t.user_id) AS c FROM {$tx_t} t
				INNER JOIN {$u_tbl} u ON u.id = t.user_id
				WHERE t.status = 'pending' AND t.type = 'purchase' AND t.created_at >= %s{$scope_u}
				AND NOT EXISTS (
					SELECT 1 FROM {$tx_t} t0 WHERE t0.user_id = t.user_id AND t0.type = 'purchase' AND t0.created_at < t.created_at
				)
				GROUP BY DATE(t.created_at)",
				$pend_vals
			),
			ARRAY_A
		);
		foreach ( (array) $pend as $r ) {
			$d = (string) ( $r['d'] ?? '' );
			if ( isset( $days[ $d ] ) ) {
				$days[ $d ]['first_pending'] = (int) ( $r['c'] ?? 0 );
			}
		}
		$paid_vals = array_merge( array( $since ), $scope_vals );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$paid = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(t.created_at) AS d, COUNT(DISTINCT t.user_id) AS c FROM {$tx_t} t
				INNER JOIN {$u_tbl} u ON u.id = t.user_id
				WHERE t.status = 'approved' AND t.type IN ('purchase','renew') AND t.created_at >= %s{$scope_u}
				AND NOT EXISTS (
					SELECT 1 FROM {$tx_t} t0 WHERE t0.user_id = t.user_id AND t0.status = 'approved'
					AND t0.type IN ('purchase','renew') AND t0.created_at < t.created_at
				)
				GROUP BY DATE(t.created_at)",
				$paid_vals
			),
			ARRAY_A
		);
		foreach ( (array) $paid as $r ) {
			$d = (string) ( $r['d'] ?? '' );
			if ( isset( $days[ $d ] ) ) {
				$days[ $d ]['first_paid'] = (int) ( $r['c'] ?? 0 );
			}
		}
		return array_values( $days );
	}
}
