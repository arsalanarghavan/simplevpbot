<?php
/**
 * Aggregated reseller performance reports for the admin dashboard tab.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Admin_Reseller_Reports
 */
class SimpleVPBot_Admin_Reseller_Reports {

	const ALLOWED_WINDOW_DAYS = array( 7, 30, 90 );

	/** @var array<string, array<string, mixed>> */
	private static $aggregate_maps_cache = array();

	/**
	 * Resolve rolling window from request (7, 30, or 90 days).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return int
	 */
	public static function window_days_from_request( WP_REST_Request $req ) {
		$raw = (int) $req->get_param( 'reseller_reports_days' );
		if ( in_array( $raw, self::ALLOWED_WINDOW_DAYS, true ) ) {
			return $raw;
		}
		return 30;
	}

	/**
	 * Overview KPI block for a single reseller actor (self-service dashboard).
	 *
	 * @param int $actor_uid    Reseller svp_users.id.
	 * @param int $window_days  7, 30, or 90.
	 * @return array<string, mixed>|null
	 */
	public static function build_actor_summary( $actor_uid, $window_days = 30 ) {
		$actor_uid = (int) $actor_uid;
		if ( $actor_uid < 1 || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return null;
		}
		$window = in_array( (int) $window_days, self::ALLOWED_WINDOW_DAYS, true ) ? (int) $window_days : 30;
		$since  = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $window . ' days', time() ) );
		$maps   = self::aggregate_maps( $since, array( $actor_uid ) );
		$user   = SimpleVPBot_Model_User::find( $actor_uid );
		if ( ! $user ) {
			return null;
		}
		$ur = array(
			'id'         => (int) $user->id,
			'username'   => (string) ( $user->username ?? '' ),
			'first_name' => (string) ( $user->first_name ?? '' ),
			'last_name'  => (string) ( $user->last_name ?? '' ),
			'status'     => (string) ( $user->status ?? '' ),
			'balance'    => (float) ( $user->balance ?? 0 ),
		);
		$row = self::row_from_user_and_maps( $ur, $maps, $actor_uid );

		return array(
			'window_days'     => $window,
			'since'           => $since,
			'backfill_done'   => (bool) get_option( SimpleVPBot_Reseller_Backfill::BACKFILL_DONE_OPTION, false ),
			'sales_toman'     => (float) ( $row['sales_toman'] ?? 0 ),
			'sales_count'     => (int) ( $row['sales_count'] ?? 0 ),
			'wholesale_toman' => (float) ( $row['wholesale_toman'] ?? 0 ),
			'wholesale_gb'    => (float) ( $row['wholesale_gb'] ?? 0 ),
			'margin_est'      => (float) ( $row['margin_est'] ?? 0 ),
			'downline_users'  => (int) ( $row['downline_users'] ?? 0 ),
			'active_services' => (int) ( $row['active_services'] ?? 0 ),
			'receipts_toman'  => (float) ( $row['receipts_toman'] ?? 0 ),
		);
	}

	/**
	 * Resolve overview metrics window from request (7, 30, or 90 days).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return int
	 */
	public static function overview_metrics_days_from_request( WP_REST_Request $req ) {
		$raw = (int) $req->get_param( 'overviewMetricsDays' );
		if ( in_array( $raw, self::ALLOWED_WINDOW_DAYS, true ) ) {
			return $raw;
		}
		return 30;
	}

	/**
	 * Reseller ids in ancestor downline (any depth, excludes ancestor).
	 *
	 * @param int $ancestor_id Parent reseller svp_users.id.
	 * @return array<int, int>
	 */
	public static function downline_reseller_ids_for( $ancestor_id ) {
		global $wpdb;
		$aid = (int) $ancestor_id;
		if ( $aid < 1 || ! class_exists( 'SimpleVPBot_Reseller_Closure' ) || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return array();
		}
		$u_tbl = SimpleVPBot_Model_User::table();
		$ct    = SimpleVPBot_Reseller_Closure::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$cols = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT u.id FROM {$u_tbl} u
				INNER JOIN {$ct} c ON c.descendant_id = u.id AND c.ancestor_id = %d AND c.depth > 0
				WHERE u.role = 'reseller'
				ORDER BY u.id ASC",
				$aid
			)
		);
		$out = array();
		foreach ( (array) $cols as $c ) {
			$id = (int) $c;
			if ( $id > 0 ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/**
	 * Empty reports payload when scope has no downline resellers.
	 *
	 * @param int    $window Window days.
	 * @param string $since  Since datetime UTC.
	 * @return array<string, mixed>
	 */
	private static function empty_scoped_reports_payload( $window, $since ) {
		return array(
			'window_days'   => (int) $window,
			'since'         => (string) $since,
			'backfill_done' => (bool) get_option( SimpleVPBot_Reseller_Backfill::BACKFILL_DONE_OPTION, false ),
			'summary'       => array(
				'reseller_count'        => 0,
				'total_sales_toman'     => 0.0,
				'total_wholesale_toman' => 0.0,
				'total_receipts_toman'  => 0.0,
				'total_downline_users'  => 0,
				'margin_est'            => 0.0,
				'top_reseller'          => array(),
			),
			'rows'          => array(),
			'daily'         => array(),
			'daily_scoped'  => false,
			'total'         => 0,
		);
	}

	/**
	 * Build full reports payload for reseller_reports tab.
	 *
	 * @param WP_REST_Request $req        Request.
	 * @param array{page:int,per_page:int,offset:int} $pagination Pagination slice.
	 * @param int|null        $scope_ancestor_id When set, limit to downline resellers of this ancestor.
	 * @return array<string, mixed>
	 */
	public static function build( WP_REST_Request $req, array $pagination, $scope_ancestor_id = null ) {
		global $wpdb;

		$window = self::window_days_from_request( $req );
		$since  = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $window . ' days', time() ) );
		$scope  = null !== $scope_ancestor_id ? (int) $scope_ancestor_id : 0;
		$scope_downline_ids = array();
		if ( $scope > 0 ) {
			$scope_downline_ids = self::downline_reseller_ids_for( $scope );
			if ( empty( $scope_downline_ids ) ) {
				return self::empty_scoped_reports_payload( $window, $since );
			}
		}
		$sort   = sanitize_key( (string) $req->get_param( 'reseller_reports_sort' ) );
		if ( ! in_array( $sort, array( 'sales', 'wholesale', 'downline', 'balance', 'name' ), true ) ) {
			$sort = 'sales';
		}

		$q = trim( sanitize_text_field( (string) wp_unslash( (string) $req->get_param( 'reseller_reports_q' ) ) ) );
		if ( strlen( $q ) > 128 ) {
			$q = substr( $q, 0, 128 );
		}

		$u_tbl = SimpleVPBot_Model_User::table();
		$where = " WHERE u.role = 'reseller' ";
		$vals  = array();
		$search = class_exists( 'SimpleVPBot_Model_User' )
			? SimpleVPBot_Model_User::admin_search_users_clause( $q, 'u' )
			: null;
		if ( is_array( $search ) ) {
			$where .= $search['sql'];
			$vals   = array_merge( $vals, $search['values'] );
		}
		if ( $scope > 0 && ! empty( $scope_downline_ids ) ) {
			$ph     = implode( ',', array_fill( 0, count( $scope_downline_ids ), '%d' ) );
			$where .= " AND u.id IN ({$ph}) ";
			$vals   = array_merge( $vals, $scope_downline_ids );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$cnt_sql = "SELECT COUNT(*) FROM {$u_tbl} u {$where}";
		$tot     = $vals
			? (int) $wpdb->get_var( $wpdb->prepare( $cnt_sql, $vals ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			: (int) $wpdb->get_var( $cnt_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids_sql = "SELECT u.id FROM {$u_tbl} u {$where}";
		$match_ids = $vals
			? array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $ids_sql, $vals ) ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			: array_map( 'intval', (array) $wpdb->get_col( $ids_sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$match_ids = array_values(
			array_unique(
				array_filter(
					$match_ids,
					static function ( $v ) {
						return (int) $v > 0;
					}
				)
			)
		);

		$order_sql = 'u.id DESC';
		if ( 'name' === $sort ) {
			$order_sql = "TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) ASC, u.username ASC, u.id ASC";
		}

		$off = max( 0, (int) ( $pagination['offset'] ?? 0 ) );
		$pp  = max( 1, (int) ( $pagination['per_page'] ?? 25 ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$list_sql = "SELECT u.id, u.username, u.first_name, u.last_name, u.status, u.balance FROM {$u_tbl} u {$where} ORDER BY {$order_sql}";

		if ( 'name' === $sort ) {
			$list_sql_paged = $list_sql . ' LIMIT %d OFFSET %d';
			$list_args      = array_merge( $vals, array( $pp, $off ) );
			$page_rows      = $vals
				? $wpdb->get_results( $wpdb->prepare( $list_sql_paged, $list_args ), ARRAY_A ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				: $wpdb->get_results( $wpdb->prepare( $list_sql_paged, $pp, $off ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$page_ids  = array();
			foreach ( (array) $page_rows as $ur ) {
				if ( is_array( $ur ) && isset( $ur['id'] ) ) {
					$page_ids[] = (int) $ur['id'];
				}
			}
			$page_maps = self::aggregate_maps( $since, $page_ids );
			$page      = array();
			foreach ( (array) $page_rows as $ur ) {
				if ( ! is_array( $ur ) ) {
					continue;
				}
				$rid = (int) ( $ur['id'] ?? 0 );
				if ( $rid < 1 ) {
					continue;
				}
				$page[] = self::row_from_user_and_maps( $ur, $page_maps, $rid );
			}
		} else {
			$ranked_ids = self::rank_reseller_ids_by_metric_sql( $match_ids, $sort, $since, $u_tbl );
			$page_ids   = array_slice( $ranked_ids, $off, $pp );
			$page_maps  = self::aggregate_maps( $since, $page_ids );
			$page       = array();
			if ( ! empty( $page_ids ) ) {
				$id_ph = implode( ',', array_fill( 0, count( $page_ids ), '%d' ) );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
				$page_rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT u.id, u.username, u.first_name, u.last_name, u.status, u.balance FROM {$u_tbl} u WHERE u.id IN ({$id_ph})",
						$page_ids
					),
					ARRAY_A
				);
				$by_id = array();
				foreach ( (array) $page_rows as $ur ) {
					if ( is_array( $ur ) && isset( $ur['id'] ) ) {
						$by_id[ (int) $ur['id'] ] = $ur;
					}
				}
				foreach ( $page_ids as $rid ) {
					$ur = isset( $by_id[ (int) $rid ] ) ? $by_id[ (int) $rid ] : null;
					if ( ! is_array( $ur ) ) {
						continue;
					}
					$page[] = self::row_from_user_and_maps( $ur, $page_maps, (int) $rid );
				}
			}
		}

		$daily_maps = self::fetch_scoped_daily_maps( $since, $match_ids );
		$summary    = self::build_summary_sql( $since, $match_ids );

		$chart_rids = ( '' !== $q ) ? $match_ids : array();
		$daily      = ( '' !== $q && ! empty( $chart_rids ) )
			? self::build_daily_series_for_resellers( $since, $window, $chart_rids )
			: self::build_daily_series( $daily_maps['daily_sales'], $daily_maps['daily_wholesale'], $since, $window );

		return array(
			'window_days'   => $window,
			'since'         => $since,
			'backfill_done' => (bool) get_option( SimpleVPBot_Reseller_Backfill::BACKFILL_DONE_OPTION, false ),
			'summary'       => $summary,
			'rows'          => $page,
			'daily'         => $daily,
			'daily_scoped'  => '' !== $q && ! empty( $chart_rids ),
			'total'         => (int) $tot,
		);
	}

	/**
	 * Rank reseller ids using lightweight SQL (paginate-before-aggregate for row maps).
	 *
	 * @param array<int, int> $match_ids Reseller ids.
	 * @param string          $sort      Sort key.
	 * @param string          $since     MySQL datetime UTC.
	 * @param string          $u_tbl     Users table name.
	 * @return array<int, int>
	 */
	private static function rank_reseller_ids_by_metric_sql( array $match_ids, $sort, $since, $u_tbl ) {
		$match_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $match_ids ),
					static function ( $v ) {
						return (int) $v > 0;
					}
				)
			)
		);
		if ( empty( $match_ids ) ) {
			return array();
		}

		global $wpdb;
		$ph   = implode( ',', array_fill( 0, count( $match_ids ), '%d' ) );
		$metrics = array();
		foreach ( $match_ids as $rid ) {
			$metrics[ (int) $rid ] = 0.0;
		}

		if ( 'balance' === $sort ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
			$bal_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, balance FROM {$u_tbl} WHERE id IN ({$ph})",
					$match_ids
				),
				ARRAY_A
			);
			foreach ( (array) $bal_rows as $br ) {
				if ( is_array( $br ) && isset( $br['id'] ) ) {
					$metrics[ (int) $br['id'] ] = (float) ( $br['balance'] ?? 0 );
				}
			}
		} elseif ( 'downline' === $sort && class_exists( 'SimpleVPBot_Reseller_Closure' ) ) {
			$ct = SimpleVPBot_Reseller_Closure::table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
			$dl_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ancestor_id AS rid, COUNT(*) AS cnt FROM {$ct} WHERE depth > 0 AND ancestor_id IN ({$ph}) GROUP BY ancestor_id",
					$match_ids
				),
				ARRAY_A
			);
			foreach ( (array) $dl_rows as $r ) {
				$rid = (int) ( $r['rid'] ?? 0 );
				if ( $rid > 0 ) {
					$metrics[ $rid ] = (float) (int) ( $r['cnt'] ?? 0 );
				}
			}
		} elseif ( 'wholesale' === $sort && class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Accrual' ) ) {
			$acc_t = SimpleVPBot_Model_Reseller_Wholesale_Accrual::table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
			$wh_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT reseller_svp_user_id AS rid, COALESCE(SUM(delta_wholesale_toman), 0) AS wholesale_toman
					FROM {$acc_t}
					WHERE created_at >= %s AND reseller_svp_user_id IN ({$ph})
					GROUP BY reseller_svp_user_id",
					array_merge( array( $since ), $match_ids )
				),
				ARRAY_A
			);
			foreach ( (array) $wh_rows as $r ) {
				$rid = (int) ( $r['rid'] ?? 0 );
				if ( $rid > 0 ) {
					$metrics[ $rid ] = (float) ( $r['wholesale_toman'] ?? 0 );
				}
			}
		} elseif ( class_exists( 'SimpleVPBot_Model_Transaction' ) ) {
			$tx_t         = SimpleVPBot_Model_Transaction::table();
			$billing_expr = SimpleVPBot_Model_Transaction::billing_reseller_id_sql_expr( 't' );
			$billing_has  = SimpleVPBot_Model_Transaction::billing_reseller_present_sql( 't' );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
			$sales_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT {$billing_expr} AS rid, COALESCE(SUM(ABS(t.amount)), 0) AS sales_toman
					FROM {$tx_t} t
					WHERE t.status = 'approved'
					AND t.type IN ('purchase', 'renew')
					AND t.created_at >= %s
					AND {$billing_has}
					AND {$billing_expr} IN ({$ph})
					GROUP BY rid",
					array_merge( array( $since ), $match_ids )
				),
				ARRAY_A
			);
			foreach ( (array) $sales_rows as $r ) {
				$rid = (int) ( $r['rid'] ?? 0 );
				if ( $rid > 0 ) {
					$metrics[ $rid ] = (float) ( $r['sales_toman'] ?? 0 );
				}
			}
		}

		$ranked = $match_ids;
		usort(
			$ranked,
			static function ( $a, $b ) use ( $metrics ) {
				$cmp = (float) ( $metrics[ (int) $b ] ?? 0 ) <=> (float) ( $metrics[ (int) $a ] ?? 0 );
				return 0 !== $cmp ? $cmp : ( (int) $b <=> (int) $a );
			}
		);
		return array_values(
			array_filter(
				array_map( 'intval', $ranked ),
				static function ( $v ) {
					return (int) $v > 0;
				}
			)
		);
	}

	/**
	 * Rank reseller ids by aggregate metric without loading full user rows.
	 *
	 * @param array<int, int>     $match_ids Reseller ids.
	 * @param array<string,mixed> $maps      Aggregate maps.
	 * @param string              $sort      Sort key.
	 * @param string              $u_tbl     Users table name.
	 * @return array<int, int>
	 */
	private static function rank_reseller_ids_by_metric( array $match_ids, array $maps, $sort, $u_tbl ) {
		global $wpdb;
		$balance_by_id = array();
		if ( 'balance' === $sort && ! empty( $match_ids ) ) {
			$ph = implode( ',', array_fill( 0, count( $match_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
			$bal_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, balance FROM {$u_tbl} WHERE id IN ({$ph})",
					$match_ids
				),
				ARRAY_A
			);
			foreach ( (array) $bal_rows as $br ) {
				if ( is_array( $br ) && isset( $br['id'] ) ) {
					$balance_by_id[ (int) $br['id'] ] = (float) ( $br['balance'] ?? 0 );
				}
			}
		}
		$ranked = array();
		foreach ( $match_ids as $rid_raw ) {
			$rid = (int) $rid_raw;
			if ( $rid < 1 ) {
				continue;
			}
			$sales = isset( $maps['sales'][ $rid ] ) ? $maps['sales'][ $rid ] : array( 'toman' => 0.0 );
			$wh    = isset( $maps['wholesale'][ $rid ] ) ? $maps['wholesale'][ $rid ] : array( 'toman' => 0.0 );
			$ranked[] = array(
				'rid'             => $rid,
				'sales_toman'     => (float) ( $sales['toman'] ?? 0 ),
				'wholesale_toman' => (float) ( $wh['toman'] ?? 0 ),
				'downline_users'  => (int) ( $maps['downline'][ $rid ] ?? 0 ),
				'balance'         => isset( $balance_by_id[ $rid ] ) ? $balance_by_id[ $rid ] : 0.0,
			);
		}
		if ( 'sales' === $sort ) {
			usort(
				$ranked,
				static function ( $a, $b ) {
					$cmp = (float) ( $b['sales_toman'] ?? 0 ) <=> (float) ( $a['sales_toman'] ?? 0 );
					return 0 !== $cmp ? $cmp : ( (int) ( $b['rid'] ?? 0 ) <=> (int) ( $a['rid'] ?? 0 ) );
				}
			);
		} elseif ( 'wholesale' === $sort ) {
			usort(
				$ranked,
				static function ( $a, $b ) {
					$cmp = (float) ( $b['wholesale_toman'] ?? 0 ) <=> (float) ( $a['wholesale_toman'] ?? 0 );
					return 0 !== $cmp ? $cmp : ( (int) ( $b['rid'] ?? 0 ) <=> (int) ( $a['rid'] ?? 0 ) );
				}
			);
		} elseif ( 'downline' === $sort ) {
			usort(
				$ranked,
				static function ( $a, $b ) {
					$cmp = (int) ( $b['downline_users'] ?? 0 ) <=> (int) ( $a['downline_users'] ?? 0 );
					return 0 !== $cmp ? $cmp : ( (int) ( $b['rid'] ?? 0 ) <=> (int) ( $a['rid'] ?? 0 ) );
				}
			);
		} elseif ( 'balance' === $sort ) {
			usort(
				$ranked,
				static function ( $a, $b ) {
					$cmp = (float) ( $b['balance'] ?? 0 ) <=> (float) ( $a['balance'] ?? 0 );
					return 0 !== $cmp ? $cmp : ( (int) ( $b['rid'] ?? 0 ) <=> (int) ( $a['rid'] ?? 0 ) );
				}
			);
		}
		$out = array();
		foreach ( $ranked as $item ) {
			$rid = (int) ( $item['rid'] ?? 0 );
			if ( $rid > 0 ) {
				$out[] = $rid;
			}
		}
		return $out;
	}

	/**
	 * Batch aggregate metrics keyed by reseller id.
	 *
	 * @param string        $since MySQL datetime UTC.
	 * @param array<int>|null $scope_reseller_ids Optional reseller id filter.
	 * @return array<string, mixed>
	 */
	private static function aggregate_maps( $since, array $scope_reseller_ids = null ) {
		global $wpdb;

		$scope_key = 'all';
		if ( null !== $scope_reseller_ids ) {
			$scope_ids = array_values(
				array_unique(
					array_filter(
						array_map( 'intval', $scope_reseller_ids ),
						static function ( $v ) {
							return (int) $v > 0;
						}
					)
				)
			);
			if ( empty( $scope_ids ) ) {
				return array(
					'downline'        => array(),
					'active_services' => array(),
					'sales'           => array(),
					'wholesale'       => array(),
					'receipts'        => array(),
					'daily_sales'     => array(),
					'daily_wholesale' => array(),
				);
			}
			$scope_reseller_ids = $scope_ids;
			$scope_key          = implode( ',', $scope_ids );
		}

		$cache_key = $since . '|' . $scope_key;
		if ( isset( self::$aggregate_maps_cache[ $cache_key ] ) ) {
			return self::$aggregate_maps_cache[ $cache_key ];
		}
		$object_key = 'svp_res_agg_' . md5( $cache_key );
		$cached_obj = wp_cache_get( $object_key, 'simplevpbot' );
		if ( is_array( $cached_obj ) ) {
			self::$aggregate_maps_cache[ $cache_key ] = $cached_obj;
			return $cached_obj;
		}

		$scope_in = null;
		if ( null !== $scope_reseller_ids ) {
			$scope_in = implode( ',', array_map( 'absint', $scope_reseller_ids ) );
		}

		$out = array(
			'downline'         => array(),
			'active_services'  => array(),
			'sales'            => array(),
			'wholesale'        => array(),
			'receipts'         => array(),
			'daily_sales'      => array(),
			'daily_wholesale'  => array(),
		);

		$ct = class_exists( 'SimpleVPBot_Reseller_Closure' ) ? SimpleVPBot_Reseller_Closure::table() : '';
		if ( '' !== $ct ) {
			$dl_where = 'depth > 0';
			if ( null !== $scope_in ) {
				$dl_where .= " AND ancestor_id IN ({$scope_in})";
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$dl_rows = $wpdb->get_results(
				"SELECT ancestor_id AS rid, COUNT(*) AS cnt FROM {$ct} WHERE {$dl_where} GROUP BY ancestor_id",
				ARRAY_A
			);
			foreach ( (array) $dl_rows as $r ) {
				$rid = (int) ( $r['rid'] ?? 0 );
				if ( $rid > 0 ) {
					$out['downline'][ $rid ] = (int) ( $r['cnt'] ?? 0 );
				}
			}

			$s_tbl = SimpleVPBot_Model_Service::table();
			$svc_where = 's.deleted_at IS NULL';
			if ( null !== $scope_in ) {
				$svc_where .= " AND c.ancestor_id IN ({$scope_in})";
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$svc_rows = $wpdb->get_results(
				"SELECT c.ancestor_id AS rid,
					SUM(CASE WHEN (s.expires_at IS NULL OR s.expires_at > UTC_TIMESTAMP()) THEN 1 ELSE 0 END) AS active_n
				FROM {$s_tbl} s
				INNER JOIN {$ct} c ON c.descendant_id = s.user_id AND c.depth > 0
				WHERE {$svc_where}
				GROUP BY c.ancestor_id",
				ARRAY_A
			);
			foreach ( (array) $svc_rows as $r ) {
				$rid = (int) ( $r['rid'] ?? 0 );
				if ( $rid > 0 ) {
					$out['active_services'][ $rid ] = (int) ( $r['active_n'] ?? 0 );
				}
			}
		}

		if ( ! class_exists( 'SimpleVPBot_Model_Transaction' ) ) {
			return $out;
		}

		$tx_t = SimpleVPBot_Model_Transaction::table();
		$billing_expr = SimpleVPBot_Model_Transaction::billing_reseller_id_sql_expr( 't' );
		$billing_has  = SimpleVPBot_Model_Transaction::billing_reseller_present_sql( 't' );
		$sales_scope  = '';
		if ( null !== $scope_in ) {
			$sales_scope = " AND {$billing_expr} IN ({$scope_in})";
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sales_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$billing_expr} AS rid,
					COUNT(*) AS sales_count,
					COALESCE(SUM(ABS(t.amount)), 0) AS sales_toman
				FROM {$tx_t} t
				WHERE t.status = 'approved'
				AND t.type IN ('purchase', 'renew')
				AND t.created_at >= %s
				AND {$billing_has}
				{$sales_scope}
				GROUP BY rid
				HAVING rid > 0",
				$since
			),
			ARRAY_A
		);
		foreach ( (array) $sales_rows as $r ) {
			$rid = (int) ( $r['rid'] ?? 0 );
			if ( $rid > 0 ) {
				$out['sales'][ $rid ] = array(
					'count' => (int) ( $r['sales_count'] ?? 0 ),
					'toman' => round( (float) ( $r['sales_toman'] ?? 0 ), 2 ),
				);
			}
		}

		$daily_sales_scope = '';
		if ( null !== $scope_in ) {
			$daily_sales_scope = " AND {$billing_expr} IN ({$scope_in})";
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$daily_sales_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(t.created_at) AS d, COALESCE(SUM(ABS(t.amount)), 0) AS sales_toman
				FROM {$tx_t} t
				WHERE t.status = 'approved'
				AND t.type IN ('purchase', 'renew')
				AND t.created_at >= %s
				AND {$billing_has}
				{$daily_sales_scope}
				GROUP BY DATE(t.created_at)
				ORDER BY d ASC",
				$since
			),
			ARRAY_A
		);
		foreach ( (array) $daily_sales_rows as $r ) {
			$d = isset( $r['d'] ) ? (string) $r['d'] : '';
			if ( '' !== $d ) {
				$out['daily_sales'][ $d ] = round( (float) ( $r['sales_toman'] ?? 0 ), 2 );
			}
		}

		if ( class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Accrual' ) ) {
			$acc_t = SimpleVPBot_Model_Reseller_Wholesale_Accrual::table();
			$wh_scope = '';
			if ( null !== $scope_in ) {
				$wh_scope = " AND reseller_svp_user_id IN ({$scope_in})";
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wh_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT reseller_svp_user_id AS rid,
						COALESCE(SUM(delta_gb), 0) AS wholesale_gb,
						COALESCE(SUM(delta_wholesale_toman), 0) AS wholesale_toman
					FROM {$acc_t}
					WHERE created_at >= %s
					{$wh_scope}
					GROUP BY reseller_svp_user_id",
					$since
				),
				ARRAY_A
			);
			foreach ( (array) $wh_rows as $r ) {
				$rid = (int) ( $r['rid'] ?? 0 );
				if ( $rid > 0 ) {
					$out['wholesale'][ $rid ] = array(
						'gb'    => (float) ( $r['wholesale_gb'] ?? 0 ),
						'toman' => round( (float) ( $r['wholesale_toman'] ?? 0 ), 2 ),
					);
				}
			}

			$daily_wh_scope = '';
			if ( null !== $scope_in ) {
				$daily_wh_scope = " AND reseller_svp_user_id IN ({$scope_in})";
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$daily_wh_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DATE(created_at) AS d, COALESCE(SUM(delta_wholesale_toman), 0) AS wholesale_toman
					FROM {$acc_t}
					WHERE created_at >= %s
					{$daily_wh_scope}
					GROUP BY DATE(created_at)
					ORDER BY d ASC",
					$since
				),
				ARRAY_A
			);
			foreach ( (array) $daily_wh_rows as $r ) {
				$d = isset( $r['d'] ) ? (string) $r['d'] : '';
				if ( '' !== $d ) {
					$out['daily_wholesale'][ $d ] = round( (float) ( $r['wholesale_toman'] ?? 0 ), 2 );
				}
			}
		}

		$rcpt_t = $wpdb->prefix . 'svp_receipts';
		$rcp_scope = '';
		if ( null !== $scope_in ) {
			$rcp_scope = " AND {$billing_expr} IN ({$scope_in})";
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rcp_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$billing_expr} AS rid, COALESCE(SUM(r.amount), 0) AS receipts_toman
				FROM {$rcpt_t} r
				INNER JOIN {$tx_t} t ON t.id = r.transaction_id
				WHERE r.status = 'approved'
				AND t.status = 'approved'
				AND r.created_at >= %s
				AND {$billing_has}
				{$rcp_scope}
				GROUP BY rid
				HAVING rid > 0",
				$since
			),
			ARRAY_A
		);
		foreach ( (array) $rcp_rows as $r ) {
			$rid = (int) ( $r['rid'] ?? 0 );
			if ( $rid > 0 ) {
				$out['receipts'][ $rid ] = round( (float) ( $r['receipts_toman'] ?? 0 ), 2 );
			}
		}

		self::$aggregate_maps_cache[ $cache_key ] = $out;
		wp_cache_set( $object_key, $out, 'simplevpbot', 300 );
		return $out;
	}

	/**
	 * @param array<string, mixed> $ur     User row.
	 * @param array<string, mixed> $maps   Aggregates.
	 * @param int                  $rid    Reseller id.
	 * @return array<string, mixed>
	 */
	private static function row_from_user_and_maps( array $ur, array $maps, $rid ) {
		$sales = isset( $maps['sales'][ $rid ] ) ? $maps['sales'][ $rid ] : array( 'count' => 0, 'toman' => 0.0 );
		$wh    = isset( $maps['wholesale'][ $rid ] ) ? $maps['wholesale'][ $rid ] : array( 'gb' => 0.0, 'toman' => 0.0 );
		$sales_toman     = (float) ( $sales['toman'] ?? 0 );
		$wholesale_toman = (float) ( $wh['toman'] ?? 0 );

		return array(
			'reseller_id'       => $rid,
			'username'          => (string) ( $ur['username'] ?? '' ),
			'first_name'        => (string) ( $ur['first_name'] ?? '' ),
			'last_name'         => (string) ( $ur['last_name'] ?? '' ),
			'status'            => (string) ( $ur['status'] ?? '' ),
			'balance'           => round( (float) ( $ur['balance'] ?? 0 ), 2 ),
			'downline_users'    => (int) ( $maps['downline'][ $rid ] ?? 0 ),
			'active_services'   => (int) ( $maps['active_services'][ $rid ] ?? 0 ),
			'sales_count'       => (int) ( $sales['count'] ?? 0 ),
			'sales_toman'       => $sales_toman,
			'wholesale_gb'      => round( (float) ( $wh['gb'] ?? 0 ), 2 ),
			'wholesale_toman'   => $wholesale_toman,
			'receipts_toman'    => (float) ( $maps['receipts'][ $rid ] ?? 0 ),
			'margin_est'        => round( $sales_toman - $wholesale_toman, 2 ),
		);
	}

	/**
	 * Daily sales/wholesale series scoped to reseller ids (no per-id row maps).
	 *
	 * @param string        $since MySQL datetime UTC.
	 * @param array<int>    $reseller_ids Reseller ids.
	 * @return array{daily_sales: array<string, float>, daily_wholesale: array<string, float>}
	 */
	private static function fetch_scoped_daily_maps( $since, array $reseller_ids ) {
		global $wpdb;

		$scope_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $reseller_ids ),
					static function ( $v ) {
						return (int) $v > 0;
					}
				)
			)
		);
		$out = array(
			'daily_sales'     => array(),
			'daily_wholesale' => array(),
		);
		if ( empty( $scope_ids ) || ! class_exists( 'SimpleVPBot_Model_Transaction' ) ) {
			return $out;
		}

		$scope_in     = implode( ',', array_map( 'absint', $scope_ids ) );
		$tx_t         = SimpleVPBot_Model_Transaction::table();
		$billing_expr = SimpleVPBot_Model_Transaction::billing_reseller_id_sql_expr( 't' );
		$billing_has  = SimpleVPBot_Model_Transaction::billing_reseller_present_sql( 't' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$daily_sales_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(t.created_at) AS d, COALESCE(SUM(ABS(t.amount)), 0) AS sales_toman
				FROM {$tx_t} t
				WHERE t.status = 'approved'
				AND t.type IN ('purchase', 'renew')
				AND t.created_at >= %s
				AND {$billing_has}
				AND {$billing_expr} IN ({$scope_in})
				GROUP BY DATE(t.created_at)
				ORDER BY d ASC",
				$since
			),
			ARRAY_A
		);
		foreach ( (array) $daily_sales_rows as $r ) {
			$d = isset( $r['d'] ) ? (string) $r['d'] : '';
			if ( '' !== $d ) {
				$out['daily_sales'][ $d ] = round( (float) ( $r['sales_toman'] ?? 0 ), 2 );
			}
		}

		if ( class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Accrual' ) ) {
			$acc_t = SimpleVPBot_Model_Reseller_Wholesale_Accrual::table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$daily_wh_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DATE(created_at) AS d, COALESCE(SUM(delta_wholesale_toman), 0) AS wholesale_toman
					FROM {$acc_t}
					WHERE created_at >= %s
					AND reseller_svp_user_id IN ({$scope_in})
					GROUP BY DATE(created_at)
					ORDER BY d ASC",
					$since
				),
				ARRAY_A
			);
			foreach ( (array) $daily_wh_rows as $r ) {
				$d = isset( $r['d'] ) ? (string) $r['d'] : '';
				if ( '' !== $d ) {
					$out['daily_wholesale'][ $d ] = round( (float) ( $r['wholesale_toman'] ?? 0 ), 2 );
				}
			}
		}

		return $out;
	}

	/**
	 * Summary KPIs via scoped SQL (avoids full per-reseller aggregate_maps).
	 *
	 * @param string     $since MySQL datetime UTC.
	 * @param array<int> $reseller_ids Reseller ids in scope.
	 * @return array<string, mixed>
	 */
	private static function build_summary_sql( $since, array $reseller_ids ) {
		global $wpdb;

		$scope_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $reseller_ids ),
					static function ( $v ) {
						return (int) $v > 0;
					}
				)
			)
		);
		if ( empty( $scope_ids ) ) {
			return array(
				'reseller_count'        => 0,
				'total_sales_toman'     => 0.0,
				'total_wholesale_toman' => 0.0,
				'total_receipts_toman'  => 0.0,
				'total_downline_users'  => 0,
				'margin_est'            => 0.0,
				'top_reseller'          => array(
					'reseller_id' => 0,
					'name'        => '',
					'sales_toman' => 0.0,
				),
			);
		}

		$scope_in = implode( ',', array_map( 'absint', $scope_ids ) );
		$total_sales     = 0.0;
		$total_wholesale = 0.0;
		$total_receipts  = 0.0;
		$total_downline  = 0;
		$top_id          = 0;
		$top_sales       = 0.0;

		if ( class_exists( 'SimpleVPBot_Model_Transaction' ) ) {
			$tx_t         = SimpleVPBot_Model_Transaction::table();
			$billing_expr = SimpleVPBot_Model_Transaction::billing_reseller_id_sql_expr( 't' );
			$billing_has  = SimpleVPBot_Model_Transaction::billing_reseller_present_sql( 't' );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total_sales = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(ABS(t.amount)), 0)
					FROM {$tx_t} t
					WHERE t.status = 'approved'
					AND t.type IN ('purchase', 'renew')
					AND t.created_at >= %s
					AND {$billing_has}
					AND {$billing_expr} IN ({$scope_in})",
					$since
				)
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$top_row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT {$billing_expr} AS rid, COALESCE(SUM(ABS(t.amount)), 0) AS sales_toman
					FROM {$tx_t} t
					WHERE t.status = 'approved'
					AND t.type IN ('purchase', 'renew')
					AND t.created_at >= %s
					AND {$billing_has}
					AND {$billing_expr} IN ({$scope_in})
					GROUP BY rid
					ORDER BY sales_toman DESC, rid DESC
					LIMIT 1",
					$since
				),
				ARRAY_A
			);
			if ( is_array( $top_row ) ) {
				$top_id    = (int) ( $top_row['rid'] ?? 0 );
				$top_sales = (float) ( $top_row['sales_toman'] ?? 0 );
			}
		}

		if ( class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Accrual' ) ) {
			$acc_t = SimpleVPBot_Model_Reseller_Wholesale_Accrual::table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total_wholesale = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(delta_wholesale_toman), 0)
					FROM {$acc_t}
					WHERE created_at >= %s
					AND reseller_svp_user_id IN ({$scope_in})",
					$since
				)
			);
		}

		$rcpt_t = $wpdb->prefix . 'svp_receipts';
		if ( class_exists( 'SimpleVPBot_Model_Transaction' ) ) {
			$tx_t         = SimpleVPBot_Model_Transaction::table();
			$billing_expr = SimpleVPBot_Model_Transaction::billing_reseller_id_sql_expr( 't' );
			$billing_has  = SimpleVPBot_Model_Transaction::billing_reseller_present_sql( 't' );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total_receipts = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(r.amount), 0)
					FROM {$rcpt_t} r
					INNER JOIN {$tx_t} t ON t.id = r.transaction_id
					WHERE r.status = 'approved'
					AND t.status = 'approved'
					AND r.created_at >= %s
					AND {$billing_has}
					AND {$billing_expr} IN ({$scope_in})",
					$since
				)
			);
		}

		if ( class_exists( 'SimpleVPBot_Reseller_Closure' ) ) {
			$ct = SimpleVPBot_Reseller_Closure::table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total_downline = (int) $wpdb->get_var(
				"SELECT COALESCE(SUM(cnt), 0) FROM (
					SELECT COUNT(*) AS cnt FROM {$ct}
					WHERE depth > 0 AND ancestor_id IN ({$scope_in})
					GROUP BY ancestor_id
				) x"
			);
		}

		$top_name = '';
		if ( $top_id > 0 && class_exists( 'SimpleVPBot_Model_User' ) ) {
			$labels = SimpleVPBot_Model_User::labels_by_ids( array( $top_id ) );
			$top_name = isset( $labels[ $top_id ] ) ? (string) $labels[ $top_id ] : ( '#' . $top_id );
		}

		return array(
			'reseller_count'        => count( $scope_ids ),
			'total_sales_toman'     => round( $total_sales, 2 ),
			'total_wholesale_toman' => round( $total_wholesale, 2 ),
			'total_receipts_toman'  => round( $total_receipts, 2 ),
			'total_downline_users'  => $total_downline,
			'margin_est'            => round( $total_sales - $total_wholesale, 2 ),
			'top_reseller'          => array(
				'reseller_id' => $top_id,
				'name'        => $top_name,
				'sales_toman' => round( $top_sales, 2 ),
			),
		);
	}

	/**
	 * Summary KPIs from aggregate maps without materializing full row stubs.
	 *
	 * @param array<string, mixed> $maps         Aggregates.
	 * @param int[]                $reseller_ids Scoped reseller ids.
	 * @return array<string, mixed>
	 */
	private static function build_summary_from_maps( array $maps, array $reseller_ids ) {
		$id_set = array_flip(
			array_values(
				array_unique(
					array_filter(
						array_map( 'intval', $reseller_ids ),
						static function ( $v ) {
							return (int) $v > 0;
						}
					)
				)
			)
		);
		$total_sales     = 0.0;
		$total_wholesale = 0.0;
		$total_receipts  = 0.0;
		$total_downline  = 0;
		$top_id          = 0;
		$top_sales       = 0.0;

		foreach ( (array) ( $maps['sales'] ?? array() ) as $rid => $sales ) {
			$rid = (int) $rid;
			if ( ! isset( $id_set[ $rid ] ) ) {
				continue;
			}
			$sales_toman = (float) ( is_array( $sales ) ? ( $sales['toman'] ?? 0 ) : 0 );
			$total_sales += $sales_toman;
			if ( $sales_toman > $top_sales ) {
				$top_sales = $sales_toman;
				$top_id    = $rid;
			}
		}
		foreach ( (array) ( $maps['wholesale'] ?? array() ) as $rid => $wh ) {
			$rid = (int) $rid;
			if ( ! isset( $id_set[ $rid ] ) ) {
				continue;
			}
			$total_wholesale += (float) ( is_array( $wh ) ? ( $wh['toman'] ?? 0 ) : 0 );
		}
		foreach ( (array) ( $maps['receipts'] ?? array() ) as $rid => $rcp ) {
			$rid = (int) $rid;
			if ( ! isset( $id_set[ $rid ] ) ) {
				continue;
			}
			$total_receipts += (float) $rcp;
		}
		foreach ( (array) ( $maps['downline'] ?? array() ) as $rid => $cnt ) {
			$rid = (int) $rid;
			if ( ! isset( $id_set[ $rid ] ) ) {
				continue;
			}
			$total_downline += (int) $cnt;
		}

		$top_name = '';
		if ( $top_id > 0 && class_exists( 'SimpleVPBot_Model_User' ) ) {
			$labels = SimpleVPBot_Model_User::labels_by_ids( array( $top_id ) );
			$top_name = isset( $labels[ $top_id ] ) ? (string) $labels[ $top_id ] : ( '#' . $top_id );
		}

		return array(
			'reseller_count'        => count( $id_set ),
			'total_sales_toman'     => round( $total_sales, 2 ),
			'total_wholesale_toman' => round( $total_wholesale, 2 ),
			'total_receipts_toman'  => round( $total_receipts, 2 ),
			'total_downline_users'  => $total_downline,
			'margin_est'            => round( $total_sales - $total_wholesale, 2 ),
			'top_reseller'          => array(
				'reseller_id' => $top_id,
				'name'        => $top_name,
				'sales_toman' => round( $top_sales, 2 ),
			),
		);
	}

	/**
	 * @param array<string, mixed> $maps  Aggregates.
	 * @param array<int, array<string, mixed>> $stubs All reseller rows.
	 * @return array<string, mixed>
	 */
	private static function build_summary( array $maps, array $stubs ) {
		$total_sales      = 0.0;
		$total_wholesale  = 0.0;
		$total_receipts   = 0.0;
		$total_downline   = 0;
		$top_id           = 0;
		$top_sales        = 0.0;
		$top_name         = '';

		foreach ( $stubs as $row ) {
			$sales = (float) ( $row['sales_toman'] ?? 0 );
			$total_sales     += $sales;
			$total_wholesale += (float) ( $row['wholesale_toman'] ?? 0 );
			$total_receipts  += (float) ( $row['receipts_toman'] ?? 0 );
			$total_downline  += (int) ( $row['downline_users'] ?? 0 );
			if ( $sales > $top_sales ) {
				$top_sales = $sales;
				$top_id    = (int) ( $row['reseller_id'] ?? 0 );
				$fn        = trim( (string) ( $row['first_name'] ?? '' ) );
				$ln        = trim( (string) ( $row['last_name'] ?? '' ) );
				$un        = trim( (string) ( $row['username'] ?? '' ) );
				$top_name  = trim( $fn . ' ' . $ln );
				if ( '' === $top_name && '' !== $un ) {
					$top_name = '@' . ltrim( $un, '@' );
				}
				if ( '' === $top_name ) {
					$top_name = '#' . $top_id;
				}
			}
		}

		return array(
			'reseller_count'      => count( $stubs ),
			'total_sales_toman'   => round( $total_sales, 2 ),
			'total_wholesale_toman' => round( $total_wholesale, 2 ),
			'total_receipts_toman'  => round( $total_receipts, 2 ),
			'total_downline_users'  => $total_downline,
			'margin_est'            => round( $total_sales - $total_wholesale, 2 ),
			'top_reseller'          => array(
				'reseller_id' => $top_id,
				'name'        => $top_name,
				'sales_toman' => round( $top_sales, 2 ),
			),
		);
	}

	/**
	 * @param array<string, float> $daily_sales     Date => toman.
	 * @param array<string, float> $daily_wholesale Date => toman.
	 * @param string               $since           Window start.
	 * @param int                  $window_days     Days.
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_daily_series( array $daily_sales, array $daily_wholesale, $since, $window_days ) {
		$out   = array();
		$start = strtotime( $since );
		$end   = time();
		if ( ! $start || $start >= $end ) {
			return $out;
		}
		$days = max( 1, min( 90, (int) $window_days ) );
		for ( $i = 0; $i <= $days; $i++ ) {
			$ts   = $start + ( $i * DAY_IN_SECONDS );
			if ( $ts > $end ) {
				break;
			}
			$d    = gmdate( 'Y-m-d', $ts );
			$out[] = array(
				'date'            => $d,
				'sales_toman'     => (float) ( $daily_sales[ $d ] ?? 0 ),
				'wholesale_toman' => (float) ( $daily_wholesale[ $d ] ?? 0 ),
			);
		}
		return $out;
	}

	/**
	 * Daily sales/wholesale series scoped to specific reseller ids (search filter).
	 *
	 * @param string $since       Window start UTC.
	 * @param int    $window_days Days.
	 * @param int[]  $reseller_ids Reseller ids.
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_daily_series_for_resellers( $since, $window_days, array $reseller_ids ) {
		global $wpdb;

		$rids = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', $reseller_ids ),
					static function ( $v ) {
						return (int) $v > 0;
					}
				)
			)
		);
		if ( empty( $rids ) || ! class_exists( 'SimpleVPBot_Model_Transaction' ) ) {
			return array();
		}
		$in = implode( ',', $rids );
		$tx_t = SimpleVPBot_Model_Transaction::table();
		$billing_expr = SimpleVPBot_Model_Transaction::billing_reseller_id_sql_expr( 't' );
		$billing_has  = SimpleVPBot_Model_Transaction::billing_reseller_present_sql( 't' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sales_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(t.created_at) AS d, COALESCE(SUM(ABS(t.amount)), 0) AS sales_toman
				FROM {$tx_t} t
				WHERE t.status = 'approved'
				AND t.type IN ('purchase', 'renew')
				AND t.created_at >= %s
				AND {$billing_has}
				AND {$billing_expr} IN ({$in})
				GROUP BY DATE(t.created_at)
				ORDER BY d ASC",
				$since
			),
			ARRAY_A
		);
		$daily_sales = array();
		foreach ( (array) $sales_rows as $r ) {
			$d = isset( $r['d'] ) ? (string) $r['d'] : '';
			if ( '' !== $d ) {
				$daily_sales[ $d ] = round( (float) ( $r['sales_toman'] ?? 0 ), 2 );
			}
		}

		$daily_wholesale = array();
		if ( class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Accrual' ) ) {
			$acc_t = SimpleVPBot_Model_Reseller_Wholesale_Accrual::table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wh_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DATE(created_at) AS d, COALESCE(SUM(delta_wholesale_toman), 0) AS wholesale_toman
					FROM {$acc_t}
					WHERE created_at >= %s
					AND reseller_svp_user_id IN ({$in})
					GROUP BY DATE(created_at)
					ORDER BY d ASC",
					$since
				),
				ARRAY_A
			);
			foreach ( (array) $wh_rows as $r ) {
				$d = isset( $r['d'] ) ? (string) $r['d'] : '';
				if ( '' !== $d ) {
					$daily_wholesale[ $d ] = round( (float) ( $r['wholesale_toman'] ?? 0 ), 2 );
				}
			}
		}

		return self::build_daily_series( $daily_sales, $daily_wholesale, $since, $window_days );
	}
}
