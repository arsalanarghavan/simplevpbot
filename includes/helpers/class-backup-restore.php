<?php
/**
 * Restore WordPress-side data from SimpleVPBot backup zip (svp_* + options JSON).
 * Optional: import panel SQLite files back into 3x-ui via server/importDB.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Backup_Restore
 */
class SimpleVPBot_Backup_Restore {

	/** Max plugin-tables.sql size read into memory (bytes). */
	const MAX_SQL_BYTES = 33554432;

	/**
	 * Restore from a local zip path (merge-only WordPress tables; optional panel DB import).
	 *
	 * @param string               $zip_path Absolute path.
	 * @param array<string, mixed> $opts     restore_panel_db (bool).
	 * @return array<string, mixed>|\WP_Error Stats on success.
	 */
	public static function restore_from_zip_path( $zip_path, array $opts = array() ) {
		$restore_panel = ! empty( $opts['restore_panel_db'] );
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'svp_no_zip', __( 'ZipArchive در سرور فعال نیست.', 'simplevpbot' ) );
		}
		if ( ! is_readable( $zip_path ) ) {
			return new WP_Error( 'svp_zip_read', __( 'فایل زیپ خوانا نیست.', 'simplevpbot' ) );
		}
		$z = new ZipArchive();
		if ( true !== $z->open( $zip_path ) ) {
			return new WP_Error( 'svp_zip_open', __( 'باز کردن زیپ ناموفق بود.', 'simplevpbot' ) );
		}
		$mj = $z->getFromName( 'wordpress/manifest.json' );
		if ( false === $mj || '' === $mj ) {
			$z->close();
			return new WP_Error( 'svp_manifest', __( 'manifest.json در زیپ یافت نشد.', 'simplevpbot' ) );
		}
		$manifest = json_decode( $mj, true );
		if ( ! is_array( $manifest ) || empty( $manifest['format_version'] ) ) {
			$z->close();
			return new WP_Error( 'svp_manifest_json', __( 'manifest نامعتبر است.', 'simplevpbot' ) );
		}
		if ( (int) $manifest['format_version'] !== SimpleVPBot_Backup_Export::MANIFEST_VERSION ) {
			$z->close();
			return new WP_Error( 'svp_manifest_ver', __( 'نسخهٔ فرمت بکاپ پشتیبانی نمی‌شود.', 'simplevpbot' ) );
		}
		$tables = isset( $manifest['tables'] ) && is_array( $manifest['tables'] ) ? $manifest['tables'] : array();
		foreach ( $tables as $t ) {
			if ( ! SimpleVPBot_Backup_Export::is_allowed_table_name( (string) $t ) ) {
				$z->close();
				return new WP_Error( 'svp_table', __( 'نام جدول غیرمجاز در manifest:', 'simplevpbot' ) . ' ' . (string) $t );
			}
		}

		$panel_stats = array();
		if ( $restore_panel ) {
			$panel_res = self::restore_panel_dbs_from_zip( $z, $manifest );
			if ( is_wp_error( $panel_res ) ) {
				$z->close();
				return $panel_res;
			}
			$panel_stats = is_array( $panel_res ) ? $panel_res : array();
		}

		$perms_json = $z->getFromName( 'wordpress/reseller-permissions.json' );
		$sql        = $z->getFromName( 'wordpress/plugin-tables.sql' );
		$z->close();
		if ( false === $sql || '' === $sql ) {
			return new WP_Error( 'svp_sql', __( 'plugin-tables.sql در زیپ یافت نشد.', 'simplevpbot' ) );
		}
		if ( strlen( (string) $sql ) > self::MAX_SQL_BYTES ) {
			return new WP_Error( 'svp_sql_size', __( 'فایل SQL بکاپ بیش از حد بزرگ است.', 'simplevpbot' ) );
		}

		if ( ! class_exists( 'SimpleVPBot_Backup_Merge_Restore' ) ) {
			return new WP_Error( 'svp_merge_missing', __( 'ماژول بازگردانی ادغامی در دسترس نیست.', 'simplevpbot' ) );
		}

		$dump = SimpleVPBot_Backup_Export::parse_sql_dump( (string) $sql );
		$res  = SimpleVPBot_Backup_Merge_Restore::restore_from_dump( $dump );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$out = is_array( $res ) ? $res : array();
		if ( false === $perms_json || '' === $perms_json ) {
			$out['reseller_permissions_skipped'] = true;
		} elseif ( class_exists( 'SimpleVPBot_Model_User' ) ) {
			$perms_map = json_decode( (string) $perms_json, true );
			if ( is_array( $perms_map ) ) {
				$out['reseller_permissions_restored'] = SimpleVPBot_Model_User::restore_reseller_permissions_from_export( $perms_map );
			}
		}
		if ( ! empty( $panel_stats ) ) {
			$out['panel_restore'] = $panel_stats;
		}
		return $out;
	}

	/**
	 * Import panel/*.db entries from zip into matching 3x-ui panels.
	 *
	 * @param ZipArchive           $z        Open zip.
	 * @param array<string, mixed> $manifest Parsed manifest.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function restore_panel_dbs_from_zip( ZipArchive $z, array $manifest ) {
		if ( ! class_exists( 'SimpleVPBot_Xui_Client' ) ) {
			return new WP_Error( 'svp_xui', __( 'کلاینت پنل در دسترس نیست.', 'simplevpbot' ) );
		}
		$names = array();
		if ( ! empty( $manifest['panel_db_files'] ) && is_array( $manifest['panel_db_files'] ) ) {
			foreach ( $manifest['panel_db_files'] as $n ) {
				$n = (string) $n;
				if ( '' !== $n ) {
					$names[] = $n;
				}
			}
		}
		if ( empty( $names ) ) {
			for ( $i = 0; $i < $z->numFiles; $i++ ) {
				$stat = $z->statIndex( $i );
				if ( ! is_array( $stat ) || empty( $stat['name'] ) ) {
					continue;
				}
				$entry = (string) $stat['name'];
				if ( preg_match( '#^panel/panel-[a-zA-Z0-9._-]+\.db$#', $entry ) ) {
					$names[] = $entry;
				}
			}
		}
		$names = array_values( array_unique( $names ) );
		if ( empty( $names ) ) {
			return new WP_Error( 'svp_no_panel_db', __( 'فایل DB پنل در این زیپ نیست.', 'simplevpbot' ) );
		}

		$tmpdir = SimpleVPBot_Backup_Export::base_tmp_dir() . 'restore-panel-' . wp_generate_password( 8, false, false ) . '/';
		wp_mkdir_p( $tmpdir );

		$imported = array();
		$failed   = array();
		foreach ( $names as $zip_name ) {
			$panel_id = self::panel_id_from_zip_entry( $zip_name );
			$bytes    = $z->getFromName( $zip_name );
			if ( false === $bytes || '' === $bytes ) {
				$failed[] = array(
					'zip_name' => $zip_name,
					'panel_id' => $panel_id,
					'step'     => 'extract',
				);
				continue;
			}
			$local = $tmpdir . basename( $zip_name );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
			if ( false === file_put_contents( $local, $bytes ) ) {
				$failed[] = array(
					'zip_name' => $zip_name,
					'panel_id' => $panel_id,
					'step'     => 'write',
				);
				continue;
			}
			$res = SimpleVPBot_Xui_Client::run_with_panel(
				$panel_id,
				function () use ( $local ) {
					if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
						return array( 'ok' => false, 'step' => 'login' );
					}
					$imp = SimpleVPBot_Xui_Client::import_db_from_path( $local );
					if ( ! empty( $imp['ok'] ) ) {
						return array( 'ok' => true );
					}
					return array(
						'ok'      => false,
						'step'    => 'import',
						'message' => (string) ( $imp['message'] ?? 'import_failed' ),
					);
				}
			);
			if ( is_array( $res ) && ! empty( $res['ok'] ) ) {
				$imported[] = array(
					'zip_name' => $zip_name,
					'panel_id' => $panel_id,
				);
				SimpleVPBot_Logger::info( 'backup restore: panel db imported', array( 'panel_id' => $panel_id, 'zip' => $zip_name ) );
			} else {
				$step = is_array( $res ) ? (string) ( $res['step'] ?? 'import' ) : 'import';
				$failed[] = array(
					'zip_name' => $zip_name,
					'panel_id' => $panel_id,
					'step'     => $step,
					'message'  => is_array( $res ) ? (string) ( $res['message'] ?? '' ) : '',
				);
				SimpleVPBot_Logger::error( 'backup restore: panel db import failed', array( 'panel_id' => $panel_id, 'zip' => $zip_name, 'res' => $res ) );
			}
			if ( is_readable( $local ) ) {
				@unlink( $local ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}

		if ( is_dir( $tmpdir ) ) {
			$glob = glob( $tmpdir . '*' );
			if ( is_array( $glob ) ) {
				foreach ( $glob as $f ) {
					if ( is_file( $f ) ) {
						@unlink( $f ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					}
				}
			}
			@rmdir( $tmpdir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		if ( empty( $imported ) ) {
			return new WP_Error(
				'svp_panel_import_all_failed',
				__( 'بازگردانی DB پنل به ۳x-ui ناموفق بود.', 'simplevpbot' )
			);
		}

		return array(
			'imported' => $imported,
			'failed'   => $failed,
			'ok_count' => count( $imported ),
			'fail_count' => count( $failed ),
		);
	}

	/**
	 * Parse panel id from zip entry path panel/panel-{id}-label.db or panel-legacy.db.
	 *
	 * @param string $zip_name Path inside zip.
	 * @return int 0 for legacy.
	 */
	private static function panel_id_from_zip_entry( $zip_name ) {
		$name = (string) $zip_name;
		if ( 'panel/panel-legacy.db' === $name ) {
			return 0;
		}
		if ( preg_match( '#^panel/panel-(\d+)-#', $name, $m ) ) {
			return (int) $m[1];
		}
		return 0;
	}
}
