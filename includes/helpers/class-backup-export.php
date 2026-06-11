<?php
/**
 * Build SimpleVPBot backup zip: panel SQLite file(s) + WordPress svp_* SQL + manifest + settings JSON.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Backup_Export
 */
class SimpleVPBot_Backup_Export {

	const MANIFEST_VERSION = 1;

	/**
	 * Writable temp directory under uploads.
	 *
	 * @return string
	 */
	public static function base_tmp_dir() {
		$dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'simplevpbot/tmp/';
		wp_mkdir_p( $dir );
		return $dir;
	}

	/**
	 * Persistent backup directory on site (not web-served when possible).
	 *
	 * @return string Trailing slash.
	 */
	public static function site_backup_dir() {
		$dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'simplevpbot-backups/';
		wp_mkdir_p( $dir );
		$ht = $dir . '.htaccess';
		if ( ! is_readable( $ht ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
			@file_put_contents( $ht, "Order deny,allow\nDeny from all\n" );
		}
		return $dir;
	}

	/**
	 * Copy a finished backup zip into site storage (caller keeps original until done).
	 *
	 * @param string $zip_abs Absolute path to zip in tmp.
	 * @param string $stamp   Filename-safe stamp (basename uses simplevpbot-backup-{stamp}.zip).
	 * @return bool
	 */
	public static function copy_zip_to_site_storage( $zip_abs, $stamp ) {
		$zip_abs = (string) $zip_abs;
		$stamp   = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $stamp );
		if ( '' === $stamp || ! is_readable( $zip_abs ) ) {
			return false;
		}
		$dest = self::site_backup_dir() . 'simplevpbot-backup-' . $stamp . '.zip';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
		return @copy( $zip_abs, $dest );
	}

	/**
	 * Remove oldest site-stored zips beyond keep count.
	 *
	 * @param int $keep_count Number of newest files to retain (minimum 1).
	 */
	public static function prune_site_backups( $keep_count ) {
		$keep = max( 1, (int) $keep_count );
		$dir  = self::site_backup_dir();
		$list = glob( $dir . 'simplevpbot-backup-*.zip' );
		if ( ! is_array( $list ) || count( $list ) <= $keep ) {
			return;
		}
		usort(
			$list,
			static function ( $a, $b ) {
				return (int) @filemtime( $b ) - (int) @filemtime( $a ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		);
		foreach ( array_slice( $list, $keep ) as $old ) {
			if ( is_string( $old ) && is_file( $old ) ) {
				@unlink( $old ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
	}

	/**
	 * Whether a basename is an allowed on-site backup zip name.
	 *
	 * @param string $filename Basename only.
	 * @return bool
	 */
	public static function is_valid_site_backup_filename( $filename ) {
		$name = basename( (string) $filename );
		if ( '' === $name || $name !== (string) $filename ) {
			return false;
		}
		return (bool) preg_match( '/^simplevpbot-backup-[a-zA-Z0-9_-]+\.zip$/', $name );
	}

	/**
	 * Absolute path to a site-stored backup if valid and inside backup dir.
	 *
	 * @param string $filename Basename from list.
	 * @return string Empty if invalid.
	 */
	public static function resolve_site_backup_path( $filename ) {
		if ( ! self::is_valid_site_backup_filename( $filename ) ) {
			return '';
		}
		$name = basename( (string) $filename );
		$dir  = self::site_backup_dir();
		$path = $dir . $name;
		if ( ! is_readable( $path ) || ! is_file( $path ) ) {
			return '';
		}
		$real_dir  = realpath( $dir );
		$real_path = realpath( $path );
		if ( false === $real_dir || false === $real_path || 0 !== strpos( $real_path, $real_dir ) ) {
			return '';
		}
		return $real_path;
	}

	/**
	 * Read manifest.json from zip (best-effort).
	 *
	 * @param string $zip_abs Absolute zip path.
	 * @return array<string, mixed>|null
	 */
	public static function read_zip_manifest( $zip_abs ) {
		if ( ! class_exists( 'ZipArchive' ) || ! is_readable( $zip_abs ) ) {
			return null;
		}
		$z = new ZipArchive();
		if ( true !== $z->open( $zip_abs ) ) {
			return null;
		}
		$mj = $z->getFromName( 'wordpress/manifest.json' );
		$z->close();
		if ( false === $mj || '' === $mj ) {
			return null;
		}
		$manifest = json_decode( (string) $mj, true );
		return is_array( $manifest ) ? $manifest : null;
	}

	/**
	 * Summarize panel DB coverage for list UI.
	 *
	 * @param string $zip_abs Absolute zip path.
	 * @return array{has_panel_db:bool, panel_db_status:string, panel_db_detail:string, panels_expected:int, panel_db_files:array<int,string>, panel_db_failures:array<int,array<string,mixed>>}
	 */
	public static function zip_panel_db_summary( $zip_abs ) {
		$empty = array(
			'has_panel_db'        => false,
			'panel_db_status'     => 'na',
			'panel_db_detail'     => '',
			'panels_expected'     => 0,
			'panel_db_files'      => array(),
			'panel_db_failures'   => array(),
		);
		$manifest = self::read_zip_manifest( $zip_abs );
		if ( null === $manifest ) {
			return $empty;
		}
		$files = isset( $manifest['panel_db_files'] ) && is_array( $manifest['panel_db_files'] )
			? array_values( array_filter( array_map( 'strval', $manifest['panel_db_files'] ) ) )
			: array();
		$failures = isset( $manifest['panel_db_failures'] ) && is_array( $manifest['panel_db_failures'] )
			? $manifest['panel_db_failures']
			: array();
		$expected = max( 0, (int) ( $manifest['panels_expected'] ?? 0 ) );
		if ( $expected < 1 && ( ! empty( $files ) || ! empty( $failures ) ) ) {
			$expected = count( $files ) + count( $failures );
		}
		$has = ! empty( $files );
		$status = 'na';
		if ( $expected > 0 ) {
			if ( count( $files ) >= $expected && empty( $failures ) ) {
				$status = 'full';
			} elseif ( $has ) {
				$status = 'partial';
			} else {
				$status = 'none';
			}
		} elseif ( $has ) {
			$status = 'full';
		}
		$detail = '';
		if ( 'partial' === $status || 'none' === $status ) {
			$parts = array();
			foreach ( $failures as $f ) {
				if ( ! is_array( $f ) ) {
					continue;
				}
				$lbl  = trim( (string) ( $f['label'] ?? '' ) );
				$step = trim( (string) ( $f['step'] ?? '' ) );
				if ( '' === $lbl ) {
					$lbl = 'panel-' . (int) ( $f['panel_id'] ?? 0 );
				}
				$parts[] = '' !== $step ? $lbl . ' (' . $step . ')' : $lbl;
			}
			$detail = implode( ', ', array_slice( $parts, 0, 6 ) );
			$rest   = count( $parts ) - 6;
			if ( $rest > 0 ) {
				$detail .= ' +' . $rest;
			}
		}
		return array(
			'has_panel_db'      => $has,
			'panel_db_status'   => $status,
			'panel_db_detail'   => $detail,
			'panels_expected'   => $expected,
			'panel_db_files'    => $files,
			'panel_db_failures' => $failures,
		);
	}

	/**
	 * List site-stored backup zips (newest first).
	 *
	 * @return array<int, array{filename:string, size_bytes:int, created_at:int, has_panel_db:bool, panel_db_status:string, panel_db_detail:string}>
	 */
	public static function list_site_backup_files() {
		$dir  = self::site_backup_dir();
		$list = glob( $dir . 'simplevpbot-backup-*.zip' );
		if ( ! is_array( $list ) ) {
			return array();
		}
		usort(
			$list,
			static function ( $a, $b ) {
				return (int) @filemtime( $b ) - (int) @filemtime( $a ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		);
		$rows = array();
		foreach ( $list as $path ) {
			if ( ! is_string( $path ) || ! is_file( $path ) ) {
				continue;
			}
			$name = basename( $path );
			if ( ! self::is_valid_site_backup_filename( $name ) ) {
				continue;
			}
			$mtime  = (int) @filemtime( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$panel  = self::zip_panel_db_summary( $path );
			$rows[] = array(
				'filename'         => $name,
				'size_bytes'       => (int) @filesize( $path ), // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				'created_at'       => $mtime > 0 ? $mtime : 0,
				'has_panel_db'     => ! empty( $panel['has_panel_db'] ),
				'panel_db_status'  => (string) ( $panel['panel_db_status'] ?? 'na' ),
				'panel_db_detail'  => (string) ( $panel['panel_db_detail'] ?? '' ),
			);
		}
		return $rows;
	}

	/**
	 * List all plugin tables (wp_*svp_*).
	 *
	 * @return array<int, string>
	 */
	public static function list_svp_tables() {
		global $wpdb;
		$pattern = $wpdb->prefix . 'svp_%';
		$names   = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern ) ); // phpcs:ignore
		return is_array( $names ) ? array_values( array_filter( array_map( 'strval', $names ) ) ) : array();
	}

	/**
	 * Whether table name is allowed for backup/restore (prefix + svp_).
	 *
	 * @param string $table Full table name.
	 * @return bool
	 */
	public static function is_allowed_table_name( $table ) {
		global $wpdb;
		$t = (string) $table;
		$p = (string) $wpdb->prefix;
		if ( strlen( $t ) < strlen( $p ) + 5 ) {
			return false;
		}
		return 0 === strpos( $t, $p . 'svp_' );
	}

	/**
	 * Extract backtick-quoted table name from our export style: INSERT INTO `tbl` ...
	 *
	 * @param string $line One SQL line.
	 * @return string|null Table name or null if not a matching INSERT.
	 */
	public static function parse_insert_table_name( $line ) {
		if ( ! preg_match( '/^\s*INSERT\s+INTO\s+`([^`]+)`/i', trim( (string) $line ), $m ) ) {
			return null;
		}
		return (string) $m[1];
	}

	/**
	 * Parse one export INSERT line into table, columns, and raw SQL values.
	 *
	 * @param string $line One INSERT line from plugin-tables.sql.
	 * @return array{table:string,columns:array<int,string>,values:array<int,mixed>}|null
	 */
	public static function parse_insert_line( $line ) {
		$line = trim( (string) $line );
		if ( ! preg_match(
			'/^\s*INSERT\s+INTO\s+`([^`]+)`\s*\(([^)]+)\)\s*VALUES\s*\((.*)\)\s*;?\s*$/is',
			$line,
			$m
		) ) {
			return null;
		}
		$table = (string) $m[1];
		if ( ! self::is_allowed_table_name( $table ) ) {
			return null;
		}
		$col_raw = (string) $m[2];
		$val_raw = (string) $m[3];
		$columns = array();
		if ( preg_match_all( '/`([^`]+)`/', $col_raw, $cm ) ) {
			foreach ( $cm[1] as $c ) {
				$columns[] = (string) $c;
			}
		}
		if ( empty( $columns ) ) {
			return null;
		}
		$values = self::parse_sql_values_list( $val_raw );
		if ( null === $values || count( $values ) !== count( $columns ) ) {
			return null;
		}
		return array(
			'table'   => $table,
			'columns' => $columns,
			'values'  => $values,
		);
	}

	/**
	 * Parse VALUES (...) list from export SQL (NULL, numbers, quoted strings).
	 *
	 * @param string $values_part Content inside outer parentheses.
	 * @return array<int, mixed>|null
	 */
	public static function parse_sql_values_list( $values_part ) {
		$s   = (string) $values_part;
		$len = strlen( $s );
		$i   = 0;
		$out = array();
		while ( $i < $len ) {
			while ( $i < $len && ( ' ' === $s[ $i ] || "\t" === $s[ $i ] || ',' === $s[ $i ] ) ) {
				++$i;
			}
			if ( $i >= $len ) {
				break;
			}
			if ( $i + 4 <= $len && 0 === strcasecmp( substr( $s, $i, 4 ), 'NULL' ) ) {
				$next = $i + 4;
				if ( $next >= $len || ',' === $s[ $next ] || ')' === $s[ $next ] ) {
					$out[] = null;
					$i     = $next;
					continue;
				}
			}
			if ( "'" === $s[ $i ] ) {
				++$i;
				$buf = '';
				while ( $i < $len ) {
					$ch = $s[ $i ];
					if ( "'" === $ch ) {
						if ( $i + 1 < $len && "'" === $s[ $i + 1 ] ) {
							$buf .= "'";
							$i   += 2;
							continue;
						}
						++$i;
						break;
					}
					$buf .= $ch;
					++$i;
				}
				$out[] = $buf;
				continue;
			}
			$start = $i;
			while ( $i < $len && ',' !== $s[ $i ] && ')' !== $s[ $i ] ) {
				++$i;
			}
			$token = trim( substr( $s, $start, $i - $start ) );
			if ( '' === $token ) {
				return null;
			}
			if ( is_numeric( $token ) ) {
				$out[] = strpos( $token, '.' ) !== false ? (float) $token : (int) $token;
			} else {
				$out[] = $token;
			}
		}
		return $out;
	}

	/**
	 * Parse full plugin-tables.sql dump into rows grouped by table.
	 *
	 * @param string $sql SQL dump body.
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public static function parse_sql_dump( $sql ) {
		$by_table = array();
		$lines    = preg_split( "/\R/u", (string) $sql );
		if ( ! is_array( $lines ) ) {
			return $by_table;
		}
		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line || 0 === strpos( $line, '--' ) ) {
				continue;
			}
			$up = strtoupper( $line );
			if ( 0 === strpos( $up, 'SET ' ) || 0 !== strpos( $up, 'INSERT ' ) ) {
				continue;
			}
			$parsed = self::parse_insert_line( $line );
			if ( null === $parsed ) {
				continue;
			}
			$table = $parsed['table'];
			$row   = array();
			foreach ( $parsed['columns'] as $idx => $col ) {
				$row[ $col ] = $parsed['values'][ $idx ];
			}
			if ( ! isset( $by_table[ $table ] ) ) {
				$by_table[ $table ] = array();
			}
			$by_table[ $table ][] = $row;
		}
		return $by_table;
	}

	/**
	 * Escape SQL string literal for INSERT (mysqli when available).
	 *
	 * @param mixed $v Value.
	 * @return string SQL fragment without outer quotes for NULL.
	 */
	public static function sql_literal( $v ) {
		if ( null === $v ) {
			return 'NULL';
		}
		global $wpdb;
		$dbh = $wpdb->dbh;
		if ( is_object( $dbh ) && method_exists( $dbh, 'real_escape_string' ) ) {
			return "'" . $dbh->real_escape_string( (string) $v ) . "'";
		}
		return "'" . esc_sql( (string) $v ) . "'";
	}

	/**
	 * Export rows as INSERT statements (one statement per line).
	 *
	 * @param array<int, string> $tables Table names.
	 * @return string
	 */
	public static function export_tables_sql( array $tables ) {
		global $wpdb;
		$out = "-- SimpleVPBot plugin tables backup\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n";
		$batch = 200;
		foreach ( $tables as $t ) {
			if ( ! self::is_allowed_table_name( $t ) ) {
				continue;
			}
			$out .= "\n-- TABLE `{$t}`\n";
			$cols = $wpdb->get_col( "SHOW COLUMNS FROM `{$t}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( empty( $cols ) ) {
				continue;
			}
			$col_list = '`' . implode( '`,`', array_map( static function ( $c ) {
				return str_replace( '`', '', (string) $c );
			}, $cols ) ) . '`';
			$offset   = 0;
			while ( true ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$rows = $wpdb->get_results( "SELECT * FROM `{$t}` LIMIT {$batch} OFFSET {$offset}", ARRAY_A );
				if ( empty( $rows ) ) {
					break;
				}
				foreach ( $rows as $row ) {
					$vals = array();
					foreach ( $cols as $c ) {
						$vals[] = self::sql_literal( array_key_exists( $c, $row ) ? $row[ $c ] : null );
					}
					$t_safe = str_replace( '`', '', $t );
					$out .= 'INSERT INTO `' . $t_safe . "` ({$col_list}) VALUES (" . implode( ',', $vals ) . ");\n";
				}
				$offset += $batch;
				if ( count( $rows ) < $batch ) {
					break;
				}
			}
		}
		$out .= "SET FOREIGN_KEY_CHECKS=1;\n";
		return $out;
	}

	/**
	 * Manifest array for zip.
	 *
	 * @param array<int, string> $tables Tables included.
	 * @return array<string, mixed>
	 */
	public static function build_manifest( array $tables, array $panel_zip_names = array(), array $panel_db_failures = array(), $panels_expected = 0 ) {
		$names = array_values( array_filter( array_map( 'strval', $panel_zip_names ) ) );
		$fail  = array();
		foreach ( $panel_db_failures as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$fail[] = array(
				'panel_id' => (int) ( $row['panel_id'] ?? 0 ),
				'label'    => (string) ( $row['label'] ?? '' ),
				'step'     => (string) ( $row['step'] ?? '' ),
			);
		}
		$expected = max( 0, (int) $panels_expected );
		if ( $expected < 1 && ( ! empty( $names ) || ! empty( $fail ) ) ) {
			$expected = count( $names ) + count( $fail );
		}
		return array(
			'format_version'      => self::MANIFEST_VERSION,
			'plugin_version'      => defined( 'SIMPLEVPBOT_VERSION' ) ? SIMPLEVPBOT_VERSION : '',
			'db_version'          => (string) get_option( 'simplevpbot_db_version', '' ),
			'generated_at_utc'    => gmdate( 'c' ),
			'tables'              => array_values( $tables ),
			'wordpress_files'     => array(
				'wordpress/manifest.json',
				'wordpress/plugin-settings.json',
				'wordpress/reseller-permissions.json',
				'wordpress/plugin-tables.sql',
			),
			'plugin_settings_contains_secrets' => false,
			'plugin_settings_secrets_redacted' => true,
			'has_panel_db'        => ! empty( $names ),
			'panel_db_files'      => $names,
			'panels_expected'     => $expected,
			'panel_db_failures'   => $fail,
		);
	}

	/**
	 * Secret keys stripped from backup plugin-settings.json (values replaced with _set flags).
	 *
	 * @return string[]
	 */
	private static function plugin_settings_secret_keys_for_export() {
		return array(
			'telegram_token',
			'telegram_webhook_secret',
			'telegram_secret_header',
			'bale_token',
			'bale_webhook_secret',
			'panel_password',
			'panel_api_token',
			'panel_login_secret',
			'portal_link_secret',
			'crypto_nowpayments_api_key',
			'crypto_nowpayments_ipn_secret',
			'crypto_ipn_path_secret',
			'bale_wallet_provider_token',
			'telegram_proxy_password',
		);
	}

	/**
	 * Redact secret values from settings array for backup export.
	 *
	 * @param array<string, mixed> $settings Raw plugin settings.
	 * @return array<string, mixed>
	 */
	public static function redact_plugin_settings_for_export( array $settings ) {
		foreach ( self::plugin_settings_secret_keys_for_export() as $k ) {
			if ( ! empty( $settings[ $k ] ) ) {
				$settings[ $k . '_set' ] = true;
				unset( $settings[ $k ] );
			}
		}
		return $settings;
	}

	/**
	 * JSON blob of plugin-related options.
	 *
	 * @return string
	 */
	public static function export_plugin_options_json() {
		$raw_settings = get_option( SimpleVPBot_Settings::OPTION_KEY, array() );
		if ( ! is_array( $raw_settings ) ) {
			$raw_settings = array();
		}
		$data = array(
			SimpleVPBot_Settings::OPTION_KEY => self::redact_plugin_settings_for_export( $raw_settings ),
			'simplevpbot_db_version'         => get_option( 'simplevpbot_db_version', '' ),
		);
		return wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * JSON blob of per-reseller permission options (simplevpbot_reseller_perms_*).
	 *
	 * @return string
	 */
	public static function export_reseller_permissions_json() {
		$data = array();
		if ( class_exists( 'SimpleVPBot_Model_User' ) ) {
			$data = SimpleVPBot_Model_User::export_all_reseller_permissions();
		}
		return wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Build zip path. Optionally embed one or more panel SQLite files.
	 *
	 * @param string               $stamp          Filename-safe stamp (no extension).
	 * @param array<int, array{path:string, zip_name:string}> $panel_db_entries Absolute path + path inside zip (e.g. panel/panel-1-main.db).
	 * @return string|\WP_Error Absolute path to .zip
	 */
	public static function build_zip( $stamp, array $panel_db_entries = array(), array $panel_db_failures = array(), $panels_expected = 0 ) {
		$stamp = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $stamp );
		if ( '' === $stamp ) {
			return new WP_Error( 'svp_bad_stamp', 'Invalid backup stamp.' );
		}
		$dir = self::base_tmp_dir();
		$zip_path = $dir . 'simplevpbot-backup-' . $stamp . '.zip';

		$tables = self::list_svp_tables();
		if ( empty( $tables ) ) {
			SimpleVPBot_Logger::error( 'backup: no svp tables found' );
		}

		$sql = self::export_tables_sql( $tables );

		$valid_entries = array();
		$zip_names     = array();
		foreach ( $panel_db_entries as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$p   = isset( $row['path'] ) ? (string) $row['path'] : '';
			$inn = isset( $row['zip_name'] ) ? (string) $row['zip_name'] : '';
			if ( '' === $p || ! is_readable( $p ) || '' === $inn ) {
				continue;
			}
			if ( ! preg_match( '#^panel/panel-[a-zA-Z0-9._-]+\.db$#', $inn ) ) {
				SimpleVPBot_Logger::error( 'backup: skip invalid panel zip path', array( 'zip_name' => $inn ) );
				continue;
			}
			$valid_entries[] = array( 'path' => $p, 'zip_name' => $inn );
			$zip_names[]     = $inn;
		}

		$manifest = self::build_manifest( $tables, $zip_names, $panel_db_failures, $panels_expected );

		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'svp_no_zip', 'ZipArchive not available.' );
		}
		$z = new ZipArchive();
		if ( true !== $z->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'svp_zip_open', 'Cannot create zip.' );
		}
		$z->addFromString( 'wordpress/manifest.json', wp_json_encode( $manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		$z->addFromString( 'wordpress/plugin-settings.json', self::export_plugin_options_json() );
		$z->addFromString( 'wordpress/reseller-permissions.json', self::export_reseller_permissions_json() );
		$z->addFromString( 'wordpress/plugin-tables.sql', $sql );
		foreach ( $valid_entries as $e ) {
			$z->addFile( $e['path'], $e['zip_name'] );
		}
		$z->close();

		if ( ! is_readable( $zip_path ) ) {
			return new WP_Error( 'svp_zip_missing', 'Zip was not written.' );
		}
		return $zip_path;
	}
}
