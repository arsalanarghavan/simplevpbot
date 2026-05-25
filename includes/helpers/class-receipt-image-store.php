<?php
/**
 * Persist receipt photos under uploads for permanent dashboard access.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Receipt_Image_Store
 */
class SimpleVPBot_Receipt_Image_Store {

	/**
	 * Directory for stored receipt images (trailing slash).
	 *
	 * @return string
	 */
	public static function storage_dir() {
		$dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'simplevpbot/receipts/';
		wp_mkdir_p( $dir );
		$ht = $dir . '.htaccess';
		if ( ! is_readable( $ht ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
			@file_put_contents( $ht, "Deny from all\n" );
		}
		return $dir;
	}

	/**
	 * Relative path stored in DB (from uploads basedir).
	 *
	 * @param int    $receipt_id Receipt id.
	 * @param string $ext        File extension with dot.
	 * @return string
	 */
	public static function relative_path_for( $receipt_id, $ext = '.jpg' ) {
		$rid = (int) $receipt_id;
		$ext = self::normalize_ext( $ext );
		return 'simplevpbot/receipts/' . $rid . $ext;
	}

	/**
	 * Absolute path for a stored relative path.
	 *
	 * @param string $stored_path Relative path from DB.
	 * @return string
	 */
	public static function absolute_path( $stored_path ) {
		$rel = ltrim( str_replace( array( "\0", '../' ), '', (string) $stored_path ), '/' );
		if ( '' === $rel || 0 !== strpos( $rel, 'simplevpbot/receipts/' ) ) {
			return '';
		}
		return trailingslashit( wp_upload_dir()['basedir'] ) . $rel;
	}

	/**
	 * Readable absolute path for receipt row, or ''.
	 *
	 * @param object|null $receipt Receipt row.
	 * @return string
	 */
	public static function readable_path_for_receipt( $receipt ) {
		if ( ! $receipt || empty( $receipt->stored_image_path ) ) {
			return '';
		}
		$abs = self::absolute_path( (string) $receipt->stored_image_path );
		return ( '' !== $abs && is_readable( $abs ) ) ? $abs : '';
	}

	/**
	 * Copy temp upload to permanent storage and update DB.
	 *
	 * @param int    $receipt_id Receipt id.
	 * @param string $temp_path  Local temp file.
	 * @return string Relative stored path or ''.
	 */
	public static function persist_from_temp( $receipt_id, $temp_path ) {
		$rid = (int) $receipt_id;
		if ( $rid < 1 || '' === (string) $temp_path || ! is_readable( $temp_path ) ) {
			return '';
		}
		$ext = self::detect_ext( $temp_path );
		$rel = self::relative_path_for( $rid, $ext );
		$abs = self::absolute_path( $rel );
		if ( '' === $abs ) {
			return '';
		}
		wp_mkdir_p( dirname( $abs ) );
		if ( ! @copy( $temp_path, $abs ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return '';
		}
		SimpleVPBot_Model_Receipt::update(
			$rid,
			array( 'stored_image_path' => $rel )
		);
		return $rel;
	}

	/**
	 * Persist raw bytes (lazy fetch from API).
	 *
	 * @param int    $receipt_id Receipt id.
	 * @param string $body       Image bytes.
	 * @param string $ext        Extension with dot.
	 * @return string Relative path or ''.
	 */
	public static function persist_from_bytes( $receipt_id, $body, $ext = '.jpg' ) {
		$rid = (int) $receipt_id;
		if ( $rid < 1 || '' === (string) $body ) {
			return '';
		}
		$rel = self::relative_path_for( $rid, $ext );
		$abs = self::absolute_path( $rel );
		if ( '' === $abs ) {
			return '';
		}
		wp_mkdir_p( dirname( $abs ) );
		if ( false === file_put_contents( $abs, $body ) ) { // phpcs:ignore
			return '';
		}
		SimpleVPBot_Model_Receipt::update(
			$rid,
			array( 'stored_image_path' => $rel )
		);
		return $rel;
	}

	/**
	 * Whether receipt has a viewable image (stored or platform file_id).
	 *
	 * @param object|null $receipt Receipt row.
	 * @return bool
	 */
	public static function receipt_has_image( $receipt ) {
		if ( ! $receipt ) {
			return false;
		}
		if ( '' !== self::readable_path_for_receipt( $receipt ) ) {
			return true;
		}
		return ! empty( $receipt->tg_file_id ) || ! empty( $receipt->bale_file_id );
	}

	/**
	 * MIME type from stored path.
	 *
	 * @param string $path Absolute or relative path.
	 * @return string
	 */
	public static function mime_for_path( $path ) {
		$lp = strtolower( (string) $path );
		if ( false !== strpos( $lp, '.png' ) ) {
			return 'image/png';
		}
		if ( false !== strpos( $lp, '.webp' ) ) {
			return 'image/webp';
		}
		return 'image/jpeg';
	}

	/**
	 * @param string $ext Extension.
	 * @return string
	 */
	private static function normalize_ext( $ext ) {
		$ext = strtolower( (string) $ext );
		if ( ! in_array( $ext, array( '.jpg', '.jpeg', '.png', '.webp' ), true ) ) {
			return '.jpg';
		}
		return $ext;
	}

	/**
	 * @param string $path File path.
	 * @return string
	 */
	private static function detect_ext( $path ) {
		$lp = strtolower( (string) $path );
		if ( false !== strpos( $lp, '.png' ) ) {
			return '.png';
		}
		if ( false !== strpos( $lp, '.webp' ) ) {
			return '.webp';
		}
		if ( is_readable( $path ) ) {
			$head = (string) file_get_contents( $path, false, null, 0, 12 ); // phpcs:ignore
			if ( 0 === strpos( $head, "\x89PNG" ) ) {
				return '.png';
			}
			if ( 0 === strpos( $head, 'RIFF' ) && false !== strpos( substr( $head, 0, 12 ), 'WEBP' ) ) {
				return '.webp';
			}
		}
		return '.jpg';
	}
}
