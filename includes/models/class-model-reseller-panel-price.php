<?php
/**
 * Admin-set wholesale price per GB for a reseller on a panel.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Reseller_Panel_Price
 */
class SimpleVPBot_Model_Reseller_Panel_Price {

	/**
	 * Request-level memo for list_for_reseller().
	 *
	 * @var array<int, array<int, object>>
	 */
	private static $list_for_reseller_memo = array();

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_reseller_panel_prices';
	}

	/**
	 * Row for one reseller + panel (null if none).
	 *
	 * @param int $reseller_svp_user_id Reseller svp_users.id.
	 * @param int $panel_id             Panel id.
	 * @return object|null
	 */
	public static function get_panel_row( $reseller_svp_user_id, $panel_id ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		$p = (int) $panel_id;
		if ( $r < 1 || $p < 1 ) {
			return null;
		}
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE reseller_svp_user_id = %d AND panel_id = %d LIMIT 1", $r, $p ) );
	}

	/**
	 * Unit price (toman per GB) or 0 if unset.
	 *
	 * @param int $reseller_svp_user_id Reseller svp_users.id.
	 * @param int $panel_id             Panel id.
	 * @return float
	 */
	public static function get_unit_price( $reseller_svp_user_id, $panel_id ) {
		$row = self::get_panel_row( $reseller_svp_user_id, $panel_id );
		if ( ! $row ) {
			return 0.0;
		}
		return (float) ( $row->price_per_gb ?? 0 );
	}

	/**
	 * Effective wholesale unit floor for a reseller on a panel (catalog + parent floor).
	 *
	 * @param int $reseller_svp_user_id Reseller id.
	 * @param int $panel_id             Panel id.
	 * @return float
	 */
	public static function effective_wholesale_floor( $reseller_svp_user_id, $panel_id, $opts = array() ) {
		$r = (int) $reseller_svp_user_id;
		$p = (int) $panel_id;
		if ( $r < 1 || $p < 1 ) {
			return 0.0;
		}
		$panel_row = null;
		if ( isset( $opts['panel_row'] ) && is_object( $opts['panel_row'] ) ) {
			$panel_row = $opts['panel_row'];
		} else {
			$panel_row = self::get_panel_row( $r, $p );
		}
		$unit = $panel_row ? (float) ( $panel_row->price_per_gb ?? 0 ) : 0.0;
		if ( $unit <= 0 ) {
			$catalog = self::resolve_catalog_defaults( $r, $p );
			$unit    = (float) ( $catalog['price_per_gb'] ?? 0 );
		}
		$parent_floor = 0.0;
		if ( class_exists( 'SimpleVPBot_Model_User' ) && class_exists( 'SimpleVPBot_Model_Reseller_Parent_Panel_Floor' ) ) {
			$u_parent = isset( $opts['actor_user_row'] ) && is_object( $opts['actor_user_row'] )
				? $opts['actor_user_row']
				: SimpleVPBot_Model_User::find( $r );
			$p_inv    = $u_parent ? (int) ( $u_parent->invited_by ?? 0 ) : 0;
			if ( $p_inv > 0 ) {
				$parent_floor = (float) SimpleVPBot_Model_Reseller_Parent_Panel_Floor::get_min_price( $p_inv, $r, $p );
			}
		}
		return max( $unit, $parent_floor );
	}

	/**
	 * Index panel price rows by panel_id (from list_for_reseller memo).
	 *
	 * @param array<int, object> $rows Rows from list_for_reseller.
	 * @return array<int, object>
	 */
	public static function index_rows_by_panel( array $rows ) {
		$map = array();
		foreach ( $rows as $row ) {
			if ( ! is_object( $row ) ) {
				continue;
			}
			$pid = (int) ( $row->panel_id ?? 0 );
			if ( $pid > 0 ) {
				$map[ $pid ] = $row;
			}
		}
		return $map;
	}

	/**
	 * Whether a stored row grants use of the panel (explicit access or positive wholesale price).
	 *
	 * @param object|null $row Row from {@see get_panel_row()} or list_for_reseller.
	 * @return bool
	 */
	public static function row_allows_panel_use( $row ) {
		if ( ! $row || ! is_object( $row ) ) {
			return false;
		}
		$acc   = (int) ( $row->panel_access ?? 0 );
		$price = (float) ( $row->price_per_gb ?? 0 );
		return ( 1 === $acc || $price > 0 );
	}

	/**
	 * Whether reseller may use this panel (row exists and access or price > 0).
	 *
	 * @param int $reseller_svp_user_id Id.
	 * @param int $panel_id             Panel id.
	 * @return bool
	 */
	public static function has_panel_access( $reseller_svp_user_id, $panel_id ) {
		$row = self::get_panel_row( $reseller_svp_user_id, $panel_id );
		return self::row_allows_panel_use( $row );
	}

	/**
	 * All rows for one reseller.
	 *
	 * @param int $reseller_svp_user_id Id.
	 * @return array<int, object>
	 */
	public static function list_for_reseller( $reseller_svp_user_id ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		if ( $r < 1 ) {
			return array();
		}
		if ( isset( self::$list_for_reseller_memo[ $r ] ) ) {
			return self::$list_for_reseller_memo[ $r ];
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE reseller_svp_user_id = %d ORDER BY panel_id ASC',
				$r
			)
		); // phpcs:ignore
		self::$list_for_reseller_memo[ $r ] = is_array( $rows ) ? $rows : array();
		return self::$list_for_reseller_memo[ $r ];
	}

	/**
	 * Panel price rows keyed by reseller id (batch for admin resellers tab).
	 *
	 * @param array<int, int> $reseller_ids Reseller ids.
	 * @return array<string, array<int, object>>
	 */
	public static function rows_map_for_resellers( array $reseller_ids ) {
		global $wpdb;
		$ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $reseller_ids ),
					static function ( $v ) {
						return $v > 0;
					}
				)
			)
		);
		$out = array();
		if ( empty( $ids ) ) {
			return $out;
		}
		foreach ( $ids as $rid ) {
			$out[ (string) $rid ] = array();
		}
		$ph = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . self::table() . " WHERE reseller_svp_user_id IN ({$ph}) ORDER BY reseller_svp_user_id ASC, panel_id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$ids
			)
		);
		foreach ( (array) $rows as $row ) {
			if ( ! $row || ! is_object( $row ) ) {
				continue;
			}
			$key = (string) (int) ( $row->reseller_svp_user_id ?? 0 );
			if ( '' === $key || '0' === $key ) {
				continue;
			}
			if ( ! isset( $out[ $key ] ) ) {
				$out[ $key ] = array();
			}
			$out[ $key ][] = $row;
		}
		return $out;
	}

	/**
	 * Why the dashboard may show no panels: stored rows vs JOINable rows (same rules as REST).
	 *
	 * @param int $reseller_svp_user_id svp_users.id.
	 * @return array{stored_rows:int,joinable_rows:int,orphan_panel_ids:int[],inactive_row_count:int}|null
	 */
	public static function access_diagnostics( $reseller_svp_user_id ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		if ( $r < 1 || ! class_exists( 'SimpleVPBot_Model_Panel' ) ) {
			return null;
		}
		$t  = self::table();
		$tp = SimpleVPBot_Model_Panel::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE reseller_svp_user_id = %d", $r ) );
		$orphan = array();
		$inactive = 0;
		foreach ( (array) $rows as $row ) {
			if ( ! is_object( $row ) ) {
				continue;
			}
			$pid = (int) ( $row->panel_id ?? 0 );
			if ( $pid > 0 && ! SimpleVPBot_Model_Panel::find( $pid ) ) {
				$orphan[] = $pid;
			}
			if ( ! self::row_allows_panel_use( $row ) ) {
				++$inactive;
			}
		}
		$joinable = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$t} r INNER JOIN {$tp} p ON p.id = r.panel_id WHERE r.reseller_svp_user_id = %d AND ( r.panel_access = 1 OR r.price_per_gb > 0 )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array(
			'stored_rows'          => count( (array) $rows ),
			'joinable_rows'        => $joinable,
			'orphan_panel_ids'     => array_values( array_unique( array_map( 'intval', $orphan ) ) ),
			'inactive_row_count'   => $inactive,
		);
	}

	/**
	 * Resolve wholesale price + server defaults from assigned/site wholesale catalog lines.
	 *
	 * @param int $reseller_svp_user_id Reseller id (0 = site catalog only).
	 * @param int $panel_id             Panel id.
	 * @return array{price_per_gb:float, default_service_type:string, default_inbound_id:int, default_l2tp_server_id:int, wholesale_line_id:int, wholesale_line_label:string}
	 */
	public static function resolve_catalog_defaults( $reseller_svp_user_id, $panel_id ) {
		$rid = (int) $reseller_svp_user_id;
		$pid = (int) $panel_id;
		$out = array(
			'price_per_gb'           => 0.0,
			'default_service_type'   => 'xray',
			'default_inbound_id'     => 0,
			'default_l2tp_server_id' => 0,
			'wholesale_line_id'      => 0,
			'wholesale_line_label'   => '',
		);
		if ( $pid < 1 || ! class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Line' ) || ! class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Tier' ) ) {
			return $out;
		}
		$candidates = array();
		if ( $rid > 0 ) {
			foreach ( SimpleVPBot_Model_Reseller_Wholesale_Line::lines_for_reseller( $rid ) as $line ) {
				if ( (int) ( $line->panel_id ?? 0 ) === $pid ) {
					$candidates[] = $line;
				}
			}
		}
		if ( empty( $candidates ) ) {
			foreach ( SimpleVPBot_Model_Reseller_Wholesale_Line::all_active() as $line ) {
				if ( (int) ( $line->panel_id ?? 0 ) === $pid ) {
					$candidates[] = $line;
				}
			}
		}
		if ( empty( $candidates ) ) {
			return $out;
		}
		$line = $candidates[0];
		$lid  = (int) ( $line->id ?? 0 );
		$tiers = $lid > 0 ? SimpleVPBot_Model_Reseller_Wholesale_Tier::by_line( $lid ) : array();
		$ppb   = 0.0;
		foreach ( (array) $tiers as $tier ) {
			$tpp = (float) ( $tier->price_per_gb ?? 0 );
			if ( $tpp > 0 && ( $ppb <= 0 || $tpp < $ppb ) ) {
				$ppb = $tpp;
			}
		}
		$dstype = isset( $line->default_service_type ) ? sanitize_key( (string) $line->default_service_type ) : 'xray';
		if ( ! in_array( $dstype, array( 'xray', 'l2tp' ), true ) ) {
			$dstype = 'xray';
		}
		$out['price_per_gb']           = round( $ppb, 4 );
		$out['default_service_type']   = $dstype;
		$out['default_inbound_id']     = max( 0, (int) ( $line->default_inbound_id ?? 0 ) );
		$out['default_l2tp_server_id'] = max( 0, (int) ( $line->default_l2tp_server_id ?? 0 ) );
		$out['wholesale_line_id']      = $lid;
		$out['wholesale_line_label']   = (string) ( $line->label ?? '' );
		return $out;
	}

	/**
	 * Batch resolve catalog defaults for multiple panels (one lines query per reseller).
	 *
	 * @param int   $reseller_svp_user_id Reseller id (0 = site catalog only).
	 * @param int[] $panel_ids            Panel ids.
	 * @return array<int, array<string, mixed>>
	 */
	public static function resolve_catalog_defaults_map( $reseller_svp_user_id, array $panel_ids ) {
		$rid  = (int) $reseller_svp_user_id;
		$pids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $panel_ids ),
					static function ( $v ) {
						return (int) $v > 0;
					}
				)
			)
		);
		$empty = array(
			'price_per_gb'           => 0.0,
			'default_service_type'   => 'xray',
			'default_inbound_id'     => 0,
			'default_l2tp_server_id' => 0,
			'wholesale_line_id'      => 0,
			'wholesale_line_label'   => '',
		);
		$out = array();
		foreach ( $pids as $pid ) {
			$out[ $pid ] = $empty;
		}
		if ( empty( $pids ) || ! class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Line' ) || ! class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Tier' ) ) {
			return $out;
		}
		$reseller_by_panel = array();
		if ( $rid > 0 ) {
			foreach ( SimpleVPBot_Model_Reseller_Wholesale_Line::lines_for_reseller( $rid ) as $line ) {
				$pid = (int) ( $line->panel_id ?? 0 );
				if ( $pid > 0 ) {
					$reseller_by_panel[ $pid ][] = $line;
				}
			}
		}
		$site_by_panel = array();
		foreach ( SimpleVPBot_Model_Reseller_Wholesale_Line::all_active() as $line ) {
			$pid = (int) ( $line->panel_id ?? 0 );
			if ( $pid > 0 ) {
				$site_by_panel[ $pid ][] = $line;
			}
		}
		$line_ids = array();
		foreach ( $pids as $pid ) {
			$candidates = ! empty( $reseller_by_panel[ $pid ] ) ? $reseller_by_panel[ $pid ] : ( $site_by_panel[ $pid ] ?? array() );
			if ( empty( $candidates ) ) {
				continue;
			}
			$lid = (int) ( $candidates[0]->id ?? 0 );
			if ( $lid > 0 ) {
				$line_ids[] = $lid;
			}
		}
		$tier_map = SimpleVPBot_Model_Reseller_Wholesale_Tier::by_line_ids( $line_ids );
		foreach ( $pids as $pid ) {
			$candidates = ! empty( $reseller_by_panel[ $pid ] ) ? $reseller_by_panel[ $pid ] : ( $site_by_panel[ $pid ] ?? array() );
			if ( empty( $candidates ) ) {
				continue;
			}
			$line = $candidates[0];
			$lid  = (int) ( $line->id ?? 0 );
			$ppb  = 0.0;
			foreach ( (array) ( $tier_map[ $lid ] ?? array() ) as $tier ) {
				$tpp = (float) ( $tier->price_per_gb ?? 0 );
				if ( $tpp > 0 && ( $ppb <= 0 || $tpp < $ppb ) ) {
					$ppb = $tpp;
				}
			}
			$dstype = isset( $line->default_service_type ) ? sanitize_key( (string) $line->default_service_type ) : 'xray';
			if ( ! in_array( $dstype, array( 'xray', 'l2tp' ), true ) ) {
				$dstype = 'xray';
			}
			$out[ $pid ] = array(
				'price_per_gb'           => round( $ppb, 4 ),
				'default_service_type'   => $dstype,
				'default_inbound_id'     => max( 0, (int) ( $line->default_inbound_id ?? 0 ) ),
				'default_l2tp_server_id' => max( 0, (int) ( $line->default_l2tp_server_id ?? 0 ) ),
				'wholesale_line_id'      => $lid,
				'wholesale_line_label'   => (string) ( $line->label ?? '' ),
			);
		}
		return $out;
	}

	/**
	 * Site-wide wholesale catalog defaults indexed by panel id (admin UI read-only hints).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function site_wholesale_catalog_by_panel() {
		static $memo = null;
		if ( null !== $memo ) {
			return $memo;
		}
		$out = array();
		if ( ! class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Line' ) ) {
			$memo = $out;
			return $memo;
		}
		foreach ( SimpleVPBot_Model_Reseller_Wholesale_Line::all_active() as $line ) {
			$pid = (int) ( $line->panel_id ?? 0 );
			if ( $pid < 1 || isset( $out[ $pid ] ) ) {
				continue;
			}
			$defaults = self::resolve_catalog_defaults( 0, $pid );
			if ( $defaults['price_per_gb'] <= 0 && '' === $defaults['wholesale_line_label'] ) {
				continue;
			}
			$out[ $pid ] = $defaults;
		}
		$memo = $out;
		return $memo;
	}

	/**
	 * Replace all price rows for a reseller (transactional).
	 *
	 * @param int                                $reseller_svp_user_id Reseller id.
	 * @param array<int, array<string, mixed>> $rows                 Each: panel_id, price_per_gb, panel_access?, default_*.
	 * @return array{ok:bool, message?:string, skipped_panel_ids?:int[]}
	 */
	public static function replace_all_for_reseller( $reseller_svp_user_id, array $rows ) {
		global $wpdb;
		$r = (int) $reseller_svp_user_id;
		if ( $r < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_reseller' );
		}
		$t                   = self::table();
		$skipped_panel_ids   = array();
		$prepared            = array();
		$existing_by_panel   = array();
		foreach ( self::list_for_reseller( $r ) as $ex_row ) {
			$epid = (int) ( $ex_row->panel_id ?? 0 );
			if ( $epid > 0 ) {
				$existing_by_panel[ $epid ] = $ex_row;
			}
		}
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$pid = (int) ( $row['panel_id'] ?? 0 );
			$ppb = isset( $row['price_per_gb'] ) ? (float) $row['price_per_gb'] : null;
			$pacc = array_key_exists( 'panel_access', $row ) ? (int) ( ! empty( $row['panel_access'] ) ) : 1;
			if ( null !== $ppb && $ppb > 0 ) {
				$pacc = 1;
			}
			if ( $pid < 1 ) {
				continue;
			}
			if ( null === $ppb || $ppb < 0 ) {
				$ppb = 0.0;
			}
			if ( ! class_exists( 'SimpleVPBot_Model_Panel' ) || ! SimpleVPBot_Model_Panel::find( $pid ) ) {
				$skipped_panel_ids[] = $pid;
				continue;
			}
			$dstype = isset( $row['default_service_type'] ) ? sanitize_key( (string) $row['default_service_type'] ) : 'xray';
			if ( ! in_array( $dstype, array( 'xray', 'l2tp' ), true ) ) {
				$dstype = 'xray';
			}
			$inbound = max( 0, (int) ( $row['default_inbound_id'] ?? 0 ) );
			$l2tp    = max( 0, (int) ( $row['default_l2tp_server_id'] ?? 0 ) );
			if ( $pacc && $ppb <= 0 && ! array_key_exists( 'price_per_gb', $row ) ) {
				$catalog = self::resolve_catalog_defaults( $r, $pid );
				if ( $catalog['price_per_gb'] > 0 ) {
					$ppb = (float) $catalog['price_per_gb'];
				}
				if ( ! isset( $row['default_service_type'] ) && '' !== (string) ( $catalog['default_service_type'] ?? '' ) ) {
					$dstype = (string) $catalog['default_service_type'];
				}
				if ( ! isset( $row['default_inbound_id'] ) && (int) ( $catalog['default_inbound_id'] ?? 0 ) > 0 ) {
					$inbound = (int) $catalog['default_inbound_id'];
				}
				if ( ! isset( $row['default_l2tp_server_id'] ) && (int) ( $catalog['default_l2tp_server_id'] ?? 0 ) > 0 ) {
					$l2tp = (int) $catalog['default_l2tp_server_id'];
				}
			} elseif ( $pacc && $ppb <= 0 && isset( $existing_by_panel[ $pid ] ) ) {
				$ex = $existing_by_panel[ $pid ];
				$ppb = (float) ( $ex->price_per_gb ?? 0 );
				if ( ! isset( $row['default_service_type'] ) ) {
					$dstype = sanitize_key( (string) ( $ex->default_service_type ?? 'xray' ) );
				}
				if ( ! isset( $row['default_inbound_id'] ) ) {
					$inbound = max( 0, (int) ( $ex->default_inbound_id ?? 0 ) );
				}
				if ( ! isset( $row['default_l2tp_server_id'] ) ) {
					$l2tp = max( 0, (int) ( $ex->default_l2tp_server_id ?? 0 ) );
				}
			}
			if ( ! $pacc ) {
				continue;
			}
			$prepared[] = array(
				'panel_id'               => $pid,
				'price_per_gb'           => round( $ppb, 0 ),
				'panel_access'           => 1,
				'default_service_type'   => $dstype,
				'default_inbound_id'     => $inbound,
				'default_l2tp_server_id' => $l2tp,
			);
		}
		$skipped_panel_ids = array_values( array_unique( array_map( 'intval', $skipped_panel_ids ) ) );

		if ( ! empty( $rows ) && empty( $prepared ) ) {
			return array(
				'ok'                => false,
				'message'           => 'no_valid_panels',
				'skipped_panel_ids' => $skipped_panel_ids,
			);
		}

		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		try {
			$wpdb->delete( $t, array( 'reseller_svp_user_id' => $r ) );
			foreach ( $prepared as $row ) {
				$ins = $wpdb->insert(
					$t,
					array(
						'reseller_svp_user_id'   => $r,
						'panel_id'               => $row['panel_id'],
						'price_per_gb'           => $row['price_per_gb'],
						'panel_access'           => $row['panel_access'],
						'default_service_type'   => $row['default_service_type'],
						'default_inbound_id'     => $row['default_inbound_id'],
						'default_l2tp_server_id' => $row['default_l2tp_server_id'],
						'updated_at'             => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%f', '%d', '%s', '%d', '%d', '%s' )
				);
				if ( false === $ins ) {
					throw new RuntimeException( 'insert_failed' );
				}
			}
			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$out = array( 'ok' => true );
			if ( ! empty( $skipped_panel_ids ) ) {
				$out['skipped_panel_ids'] = $skipped_panel_ids;
			}
			return $out;
		} catch ( Throwable $e ) { // phpcs:ignore
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return array( 'ok' => false, 'message' => $e->getMessage() ?: 'db' );
		}
	}
}
