<?php
/**
 * Restore WordPress-side data from SimpleVPBot backup zip (svp_* + options JSON).
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
	 * Restore from a local zip path (caller moves upload here).
	 *
	 * @param string $zip_path Absolute path.
	 * @return true|\WP_Error
	 */
	public static function restore_from_zip_path( $zip_path ) {
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
		$sql = $z->getFromName( 'wordpress/plugin-tables.sql' );
		if ( false === $sql || '' === $sql ) {
			$z->close();
			return new WP_Error( 'svp_sql', __( 'plugin-tables.sql در زیپ یافت نشد.', 'simplevpbot' ) );
		}
		if ( strlen( (string) $sql ) > self::MAX_SQL_BYTES ) {
			$z->close();
			return new WP_Error( 'svp_sql_size', __( 'فایل SQL بکاپ بیش از حد بزرگ است.', 'simplevpbot' ) );
		}
		$opt_raw = $z->getFromName( 'wordpress/plugin-settings.json' );
		$z->close();

		global $wpdb;
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS=0' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $tables as $t ) {
			$t = (string) $t;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "TRUNCATE TABLE `{$t}`" );
		}

		$lines = preg_split( "/\R/u", (string) $sql );
		if ( ! is_array( $lines ) ) {
			$lines = array();
		}
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || 0 === strpos( $line, '--' ) ) {
				continue;
			}
			$up = strtoupper( $line );
			if ( 0 === strpos( $up, 'SET ' ) ) {
				$wpdb->query( $line ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				continue;
			}
			if ( 0 === strpos( $up, 'INSERT ' ) ) {
				$ins_tbl = SimpleVPBot_Backup_Export::parse_insert_table_name( $line );
				if ( null === $ins_tbl || ! SimpleVPBot_Backup_Export::is_allowed_table_name( $ins_tbl ) ) {
					$wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					return new WP_Error( 'svp_sql_table', __( 'خط INSERT به جدول غیرمجاز یا نامعتبر:', 'simplevpbot' ) . ' ' . (string) ( $ins_tbl ?? '' ) );
				}
				$wpdb->query( $line ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				if ( ! empty( $wpdb->last_error ) ) {
					$wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' ); // phpcs:ignore
					return new WP_Error( 'svp_sql_run', $wpdb->last_error . ' — ' . mb_substr( $line, 0, 120 ) );
				}
			}
		}
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( false !== $opt_raw && '' !== $opt_raw ) {
			$optj = json_decode( (string) $opt_raw, true );
			if ( is_array( $optj ) ) {
				$key = SimpleVPBot_Settings::OPTION_KEY;
				if ( array_key_exists( $key, $optj ) && is_array( $optj[ $key ] ) ) {
					update_option( $key, $optj[ $key ] );
				}
				if ( array_key_exists( 'simplevpbot_db_version', $optj ) ) {
					update_option( 'simplevpbot_db_version', sanitize_text_field( (string) $optj['simplevpbot_db_version'] ) );
				}
			}
		}
		if ( class_exists( 'SimpleVPBot_Texts' ) ) {
			SimpleVPBot_Texts::clear_cache();
		}
		return true;
	}
}
