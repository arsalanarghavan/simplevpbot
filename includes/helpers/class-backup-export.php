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
	public static function build_manifest( array $tables, array $panel_zip_names = array() ) {
		$names = array_values( array_filter( array_map( 'strval', $panel_zip_names ) ) );
		return array(
			'format_version'   => self::MANIFEST_VERSION,
			'plugin_version'   => defined( 'SIMPLEVPBOT_VERSION' ) ? SIMPLEVPBOT_VERSION : '',
			'db_version'       => (string) get_option( 'simplevpbot_db_version', '' ),
			'generated_at_utc' => gmdate( 'c' ),
			'tables'           => array_values( $tables ),
			'has_panel_db'     => ! empty( $names ),
			'panel_db_files'   => $names,
		);
	}

	/**
	 * JSON blob of plugin-related options.
	 *
	 * @return string
	 */
	public static function export_plugin_options_json() {
		$data = array(
			SimpleVPBot_Settings::OPTION_KEY => get_option( SimpleVPBot_Settings::OPTION_KEY, array() ),
			'simplevpbot_db_version'         => get_option( 'simplevpbot_db_version', '' ),
		);
		return wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Build zip path. Optionally embed one or more panel SQLite files.
	 *
	 * @param string               $stamp          Filename-safe stamp (no extension).
	 * @param array<int, array{path:string, zip_name:string}> $panel_db_entries Absolute path + path inside zip (e.g. panel/panel-1-main.db).
	 * @return string|\WP_Error Absolute path to .zip
	 */
	public static function build_zip( $stamp, array $panel_db_entries = array() ) {
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

		$manifest = self::build_manifest( $tables, $zip_names );

		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'svp_no_zip', 'ZipArchive not available.' );
		}
		$z = new ZipArchive();
		if ( true !== $z->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'svp_zip_open', 'Cannot create zip.' );
		}
		$z->addFromString( 'wordpress/manifest.json', wp_json_encode( $manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		$z->addFromString( 'wordpress/plugin-settings.json', self::export_plugin_options_json() );
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
