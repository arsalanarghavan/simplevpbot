<?php
/**
 * Non-destructive merge restore: match users by tg/bale/wp only, remap FKs, insert missing rows.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Backup_Merge_Restore
 */
class SimpleVPBot_Backup_Merge_Restore {

	/** @var array<int, string> */
	const IMPORT_ORDER = array(
		'svp_texts',
		'svp_panels',
		'svp_plan_categories',
		'svp_plans',
		'svp_l2tp_servers',
		'svp_monitor_hosts',
		'svp_panel_inbound_api',
		'svp_panel_inbound_clients',
		'svp_panel_online_daily',
		'svp_users',
		'svp_cards',
		'svp_discount_codes',
		'svp_services',
		'svp_transactions',
		'svp_receipts',
		'svp_pending_approvals',
		'svp_sync_codes',
		'svp_broadcasts',
		'svp_broadcast_queue',
		'svp_referral_events',
		'svp_user_activity',
		'svp_reseller_panel_prices',
		'svp_reseller_wholesale_lines',
		'svp_reseller_wholesale_tiers',
		'svp_reseller_wholesale_line_assignments',
		'svp_reseller_wholesale_accruals',
		'svp_reseller_parent_panel_floors',
		'svp_reseller_bot_profiles',
		'svp_reseller_closure',
		'svp_discount_redemptions',
		'svp_service_ip_log',
		'svp_users_bulk_jobs',
		'svp_users_bulk_job_items',
		'svp_audit_log',
		'svp_logs',
		'svp_service_transfer_codes',
	);

	/** Columns remapped via user id map. */
	const USER_FK_COLUMNS = array(
		'user_id',
		'owner_svp_user_id',
		'inviter_svp_user_id',
		'resulting_svp_user_id',
		'reseller_svp_user_id',
		'parent_svp_user_id',
		'child_svp_user_id',
		'subject_svp_user_id',
		'actor_svp_user_id',
		'svp_user_id',
		'created_by_svp_user_id',
		'restricted_svp_user_id',
		'signup_reseller_svp_id',
		'owner_id',
	);

	/**
	 * Run merge restore from parsed dump.
	 *
	 * @param array<string, array<int, array<string, mixed>>> $dump_by_table Parsed SQL rows.
	 * @return array<string, mixed>|\WP_Error Stats array or error.
	 */
	public static function restore_from_dump( array $dump_by_table ) {
		global $wpdb;

		$stats = array(
			'users_matched'  => 0,
			'users_inserted' => 0,
			'users_skipped'  => 0,
			'rows_inserted'  => array(),
			'rows_skipped'   => array(),
			'errors'         => array(),
		);

		$id_maps   = array();
		$prefix    = $wpdb->prefix;
		$users_tbl = $prefix . 'svp_users';

		$user_rows = isset( $dump_by_table[ $users_tbl ] ) ? $dump_by_table[ $users_tbl ] : array();
		unset( $dump_by_table[ $users_tbl ] );

		$user_map = self::import_users( $user_rows, $stats );
		if ( is_wp_error( $user_map ) ) {
			return $user_map;
		}
		$id_maps[ $users_tbl ] = $user_map;

		$ordered = self::ordered_tables( array_keys( $dump_by_table ), $prefix );
		foreach ( $ordered as $table ) {
			if ( $table === $users_tbl || empty( $dump_by_table[ $table ] ) ) {
				continue;
			}
			$map = self::import_generic_table(
				$table,
				$dump_by_table[ $table ],
				$id_maps,
				$stats
			);
			if ( ! empty( $map ) ) {
				$id_maps[ $table ] = $map;
			}
		}

		self::patch_user_self_fks( $user_rows, $user_map, $stats );

		if ( class_exists( 'SimpleVPBot_Texts' ) ) {
			SimpleVPBot_Texts::clear_cache();
		}

		return $stats;
	}

	/**
	 * @param array<int, array<string, mixed>> $user_rows Backup user rows.
	 * @param array<string, mixed>            $stats    Stats (by ref).
	 * @return array<int, int>|\WP_Error backup_user_id => live_user_id
	 */
	private static function import_users( array $user_rows, array &$stats ) {
		global $wpdb;

		$map              = array();
		$deferred_self_fk = array();

		foreach ( $user_rows as $row ) {
			$backup_id = isset( $row['id'] ) ? (int) $row['id'] : 0;
			if ( $backup_id < 1 ) {
				$stats['errors'][] = array(
					'table'  => $wpdb->prefix . 'svp_users',
					'reason' => 'missing_backup_user_id',
				);
				++$stats['users_skipped'];
				continue;
			}

			$resolve = self::resolve_live_user_for_backup_row( $row );
			if ( 'ambiguous' === $resolve['status'] ) {
				$stats['errors'][] = array(
					'table'  => $wpdb->prefix . 'svp_users',
					'reason' => 'ambiguous_identity',
					'id'     => $backup_id,
				);
				++$stats['users_skipped'];
				continue;
			}

			if ( 'matched' === $resolve['status'] && $resolve['user'] ) {
				$live_id            = (int) $resolve['user']->id;
				$map[ $backup_id ]  = $live_id;
				++$stats['users_matched'];
				self::maybe_fill_empty_live_user_fields( $resolve['user'], $row );
				$deferred_self_fk[ $backup_id ] = array(
					'invited_by'              => isset( $row['invited_by'] ) ? (int) $row['invited_by'] : 0,
					'signup_reseller_svp_id'  => isset( $row['signup_reseller_svp_id'] ) ? (int) $row['signup_reseller_svp_id'] : 0,
				);
				continue;
			}

			$insert_row = $row;
			unset( $insert_row['id'] );
			unset( $insert_row['invited_by'], $insert_row['signup_reseller_svp_id'] );

			$live_cols = self::live_columns( $wpdb->prefix . 'svp_users' );
			$insert_row = self::filter_row_to_columns( $insert_row, $live_cols );

			$ok = $wpdb->insert( $wpdb->prefix . 'svp_users', $insert_row );
			if ( false === $ok ) {
				$stats['errors'][] = array(
					'table'  => $wpdb->prefix . 'svp_users',
					'reason' => $wpdb->last_error ? $wpdb->last_error : 'insert_failed',
					'id'     => $backup_id,
				);
				++$stats['users_skipped'];
				continue;
			}

			$new_id            = (int) $wpdb->insert_id;
			$map[ $backup_id ] = $new_id;
			++$stats['users_inserted'];
			$deferred_self_fk[ $backup_id ] = array(
				'invited_by'             => isset( $row['invited_by'] ) ? (int) $row['invited_by'] : 0,
				'signup_reseller_svp_id' => isset( $row['signup_reseller_svp_id'] ) ? (int) $row['signup_reseller_svp_id'] : 0,
			);
		}

		// Store deferred for patch after full user_map is known (handled in patch_user_self_fks).
		$stats['_deferred_user_self_fk'] = $deferred_self_fk;

		return $map;
	}

	/**
	 * @param array<int, array<string, mixed>> $user_rows Backup rows.
	 * @param array<int, int>                 $user_map  backup => live.
	 * @param array<string, mixed>            $stats     Stats (by ref).
	 */
	private static function patch_user_self_fks( array $user_rows, array $user_map, array &$stats ) {
		$deferred = isset( $stats['_deferred_user_self_fk'] ) && is_array( $stats['_deferred_user_self_fk'] )
			? $stats['_deferred_user_self_fk']
			: array();
		unset( $stats['_deferred_user_self_fk'] );

		foreach ( $deferred as $backup_id => $fks ) {
			if ( ! isset( $user_map[ $backup_id ] ) ) {
				continue;
			}
			$live_id = (int) $user_map[ $backup_id ];
			$patch   = array();
			$inv     = (int) ( $fks['invited_by'] ?? 0 );
			if ( $inv > 0 && isset( $user_map[ $inv ] ) ) {
				$patch['invited_by'] = (int) $user_map[ $inv ];
			}
			$signup = (int) ( $fks['signup_reseller_svp_id'] ?? 0 );
			if ( $signup > 0 && isset( $user_map[ $signup ] ) ) {
				$patch['signup_reseller_svp_id'] = (int) $user_map[ $signup ];
			}
			if ( empty( $patch ) || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
				continue;
			}
			SimpleVPBot_Model_User::update( $live_id, $patch );
		}
	}

	/**
	 * @param array<string, mixed> $row Backup user row.
	 * @return array{status:string,user:?object}
	 */
	private static function resolve_live_user_for_backup_row( array $row ) {
		$tg = (int) ( $row['tg_user_id'] ?? 0 );
		$bl = (int) ( $row['bale_user_id'] ?? 0 );
		$wp = (int) ( $row['wp_user_id'] ?? 0 );

		if ( $tg < 1 && $bl < 1 && $wp < 1 ) {
			return array(
				'status' => 'new',
				'user'   => null,
			);
		}

		if ( ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return array(
				'status' => 'new',
				'user'   => null,
			);
		}

		$hits = array();
		if ( $tg > 0 ) {
			$u = SimpleVPBot_Model_User::find_by_telegram( $tg );
			if ( $u ) {
				$hits[ (int) $u->id ] = $u;
			}
		}
		if ( $bl > 0 ) {
			$u = SimpleVPBot_Model_User::find_by_bale( $bl );
			if ( $u ) {
				$hits[ (int) $u->id ] = $u;
			}
		}
		if ( $wp > 0 ) {
			$u = SimpleVPBot_Model_User::find_by_wp_user( $wp );
			if ( $u ) {
				$hits[ (int) $u->id ] = $u;
			}
		}

		if ( count( $hits ) > 1 ) {
			return array(
				'status' => 'ambiguous',
				'user'   => null,
			);
		}
		if ( 1 === count( $hits ) ) {
			return array(
				'status' => 'matched',
				'user'   => reset( $hits ),
			);
		}

		return array(
			'status' => 'new',
			'user'   => null,
		);
	}

	/**
	 * Fill empty platform ids on live user from backup (non-destructive).
	 *
	 * @param object               $live Live user row.
	 * @param array<string, mixed> $backup Backup row.
	 */
	private static function maybe_fill_empty_live_user_fields( $live, array $backup ) {
		if ( ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return;
		}
		$patch = array();
		$tg_l  = (int) ( $live->tg_user_id ?? 0 );
		$tg_b  = (int) ( $backup['tg_user_id'] ?? 0 );
		if ( $tg_l < 1 && $tg_b > 0 ) {
			$patch['tg_user_id'] = $tg_b;
		}
		$bl_l = (int) ( $live->bale_user_id ?? 0 );
		$bl_b = (int) ( $backup['bale_user_id'] ?? 0 );
		if ( $bl_l < 1 && $bl_b > 0 ) {
			$patch['bale_user_id'] = $bl_b;
		}
		$wp_l = (int) ( $live->wp_user_id ?? 0 );
		$wp_b = (int) ( $backup['wp_user_id'] ?? 0 );
		if ( $wp_l < 1 && $wp_b > 0 ) {
			$patch['wp_user_id'] = $wp_b;
		}
		if ( ! empty( $patch ) ) {
			SimpleVPBot_Model_User::update( (int) $live->id, $patch );
		}
	}

	/**
	 * @param string                             $table Full table name.
	 * @param array<int, array<string, mixed>>   $rows Backup rows.
	 * @param array<string, array<int, int>>     $id_maps Table => backup_pk => live_pk.
	 * @param array<string, mixed>               $stats Stats (by ref).
	 * @return array<int, int> backup_pk => live_pk for this table when it has id.
	 */
	private static function import_generic_table( $table, array $rows, array &$id_maps, array &$stats ) {
		global $wpdb;

		$map       = array();
		$live_cols = self::live_columns( $table );
		$has_id    = in_array( 'id', $live_cols, true );

		foreach ( $rows as $row ) {
			$backup_pk = $has_id && isset( $row['id'] ) ? (int) $row['id'] : 0;

			if ( $has_id && $backup_pk > 0 ) {
				$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE id = %d LIMIT 1", $backup_pk ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( $exists ) {
					$map[ $backup_pk ] = (int) $exists;
					self::bump_stat( $stats, 'rows_skipped', $table );
					continue;
				}
			}

			$prepared = self::remap_row_foreign_keys( $table, $row, $id_maps, $stats );
			if ( null === $prepared ) {
				self::bump_stat( $stats, 'rows_skipped', $table );
				continue;
			}

			$dedupe = self::find_existing_row_for_dedupe( $table, $prepared );
			if ( null !== $dedupe ) {
				if ( $backup_pk > 0 ) {
					$map[ $backup_pk ] = (int) $dedupe;
				}
				self::bump_stat( $stats, 'rows_skipped', $table );
				continue;
			}

			$insert_row = $prepared;
			if ( $has_id ) {
				unset( $insert_row['id'] );
			}
			$insert_row = self::filter_row_to_columns( $insert_row, $live_cols );

			$ok = $wpdb->insert( $table, $insert_row );
			if ( false === $ok ) {
				$stats['errors'][] = array(
					'table'  => $table,
					'reason' => $wpdb->last_error ? $wpdb->last_error : 'insert_failed',
					'id'     => $backup_pk,
				);
				self::bump_stat( $stats, 'rows_skipped', $table );
				continue;
			}

			$new_pk = $has_id ? (int) $wpdb->insert_id : 0;
			if ( $backup_pk > 0 && $new_pk > 0 ) {
				$map[ $backup_pk ] = $new_pk;
			}
			self::bump_stat( $stats, 'rows_inserted', $table );
		}

		return $map;
	}

	/**
	 * @param string                             $table Table name.
	 * @param array<string, mixed>               $row Row.
	 * @param array<string, array<int, int>>     $id_maps Maps.
	 * @param array<string, mixed>               $stats Stats.
	 * @return array<string, mixed>|null Null when row should be skipped.
	 */
	private static function remap_row_foreign_keys( $table, array $row, array $id_maps, array &$stats ) {
		global $wpdb;

		$out       = $row;
		$users_tbl = $wpdb->prefix . 'svp_users';

		foreach ( self::USER_FK_COLUMNS as $col ) {
			if ( ! array_key_exists( $col, $out ) ) {
				continue;
			}
			$raw = (int) $out[ $col ];
			if ( $raw < 1 ) {
				continue;
			}
			if ( ! isset( $id_maps[ $users_tbl ][ $raw ] ) ) {
				$stats['errors'][] = array(
					'table'  => $table,
					'reason' => 'missing_user_map',
					'column' => $col,
					'value'  => $raw,
				);
				return null;
			}
			$out[ $col ] = (int) $id_maps[ $users_tbl ][ $raw ];
		}

		if ( isset( $out['service_id'] ) ) {
			$sid = (int) $out['service_id'];
			if ( $sid > 0 ) {
				$svc_tbl = $wpdb->prefix . 'svp_services';
				if ( ! isset( $id_maps[ $svc_tbl ][ $sid ] ) ) {
					$stats['errors'][] = array(
						'table'  => $table,
						'reason' => 'missing_service_map',
						'value'  => $sid,
					);
					return null;
				}
				$out['service_id'] = (int) $id_maps[ $svc_tbl ][ $sid ];
			}
		}

		if ( isset( $out['transaction_id'] ) ) {
			$tid = (int) $out['transaction_id'];
			if ( $tid > 0 ) {
				$tx_tbl = $wpdb->prefix . 'svp_transactions';
				if ( ! isset( $id_maps[ $tx_tbl ][ $tid ] ) ) {
					$stats['errors'][] = array(
						'table'  => $table,
						'reason' => 'missing_transaction_map',
						'value'  => $tid,
					);
					return null;
				}
				$out['transaction_id'] = (int) $id_maps[ $tx_tbl ][ $tid ];
			}
		}

		if ( isset( $out['job_id'] ) ) {
			$jid = (int) $out['job_id'];
			if ( $jid > 0 ) {
				$job_tbl = $wpdb->prefix . 'svp_users_bulk_jobs';
				if ( ! isset( $id_maps[ $job_tbl ][ $jid ] ) ) {
					$stats['errors'][] = array(
						'table'  => $table,
						'reason' => 'missing_job_map',
						'value'  => $jid,
					);
					return null;
				}
				$out['job_id'] = (int) $id_maps[ $job_tbl ][ $jid ];
			}
		}

		// Remap other numeric FKs that point to tables we have maps for (plan_id, panel_id, card_id, ...).
		foreach ( $out as $col => $val ) {
			if ( ! is_numeric( $val ) ) {
				continue;
			}
			$ival = (int) $val;
			if ( $ival < 1 ) {
				continue;
			}
			if ( in_array( $col, self::USER_FK_COLUMNS, true ) ) {
				continue;
			}
			if ( in_array( $col, array( 'service_id', 'transaction_id', 'job_id', 'id' ), true ) ) {
				continue;
			}
			$target = self::guess_map_table_for_column( $col, $wpdb->prefix );
			if ( ! $target || ! isset( $id_maps[ $target ][ $ival ] ) ) {
				continue;
			}
			$out[ $col ] = (int) $id_maps[ $target ][ $ival ];
		}

		return $out;
	}

	/**
	 * @param string               $table Table.
	 * @param array<string, mixed> $row Row (already remapped).
	 * @return int|null Existing live primary key if duplicate.
	 */
	private static function find_existing_row_for_dedupe( $table, array $row ) {
		global $wpdb;

		$svc_tbl = $wpdb->prefix . 'svp_services';
		if ( $table === $svc_tbl && class_exists( 'SimpleVPBot_Model_Service' ) ) {
			$iid = (int) ( $row['inbound_id'] ?? 0 );
			$em  = isset( $row['email'] ) ? trim( (string) $row['email'] ) : '';
			$pid = (int) ( $row['panel_id'] ?? 1 );
			if ( $iid > 0 && '' !== $em ) {
				$svc = SimpleVPBot_Model_Service::find_by_inbound_email( $iid, $em, $pid > 0 ? $pid : 1 );
				if ( $svc ) {
					return (int) $svc->id;
				}
			}
		}

		return null;
	}

	/**
	 * @param string $column Column name.
	 * @param string $prefix Table prefix.
	 * @return string|null Full table name if mappable.
	 */
	private static function guess_map_table_for_column( $column, $prefix ) {
		if ( 'plan_id' === $column ) {
			return $prefix . 'svp_plans';
		}
		if ( 'panel_id' === $column ) {
			return $prefix . 'svp_panels';
		}
		if ( 'card_id' === $column ) {
			return $prefix . 'svp_cards';
		}
		if ( 'line_id' === $column ) {
			return $prefix . 'svp_reseller_wholesale_lines';
		}
		if ( 'l2tp_server_id' === $column ) {
			return $prefix . 'svp_l2tp_servers';
		}
		return null;
	}

	/**
	 * @param array<int, string> $tables From dump.
	 * @param string             $prefix WP prefix.
	 * @return array<int, string>
	 */
	private static function ordered_tables( array $tables, $prefix ) {
		$want = array();
		foreach ( self::IMPORT_ORDER as $short ) {
			$full = $prefix . $short;
			if ( in_array( $full, $tables, true ) ) {
				$want[] = $full;
			}
		}
		foreach ( $tables as $t ) {
			if ( ! in_array( $t, $want, true ) ) {
				$want[] = $t;
			}
		}
		if ( class_exists( 'SimpleVPBot_Service_Transfer' ) && method_exists( 'SimpleVPBot_Service_Transfer', 'codes_table' ) ) {
			$st = SimpleVPBot_Service_Transfer::codes_table();
			if ( in_array( $st, $tables, true ) && ! in_array( $st, $want, true ) ) {
				$want[] = $st;
			}
		}
		return $want;
	}

	/**
	 * @param string $table Table name.
	 * @return array<int, string>
	 */
	private static function live_columns( $table ) {
		global $wpdb;
		$cols = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $cols ) ? array_values( array_map( 'strval', $cols ) ) : array();
	}

	/**
	 * @param array<string, mixed> $row Row.
	 * @param array<int, string>   $cols Columns.
	 * @return array<string, mixed>
	 */
	private static function filter_row_to_columns( array $row, array $cols ) {
		$out = array();
		foreach ( $cols as $c ) {
			if ( array_key_exists( $c, $row ) ) {
				$out[ $c ] = $row[ $c ];
			}
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $stats Stats.
	 * @param string               $key   rows_inserted|rows_skipped.
	 * @param string               $table Table.
	 */
	private static function bump_stat( array &$stats, $key, $table ) {
		if ( ! isset( $stats[ $key ][ $table ] ) ) {
			$stats[ $key ][ $table ] = 0;
		}
		++$stats[ $key ][ $table ];
	}
}
