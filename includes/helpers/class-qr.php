<?php
/**
 * Local QR (chillerlan/php-qrcode + GD) — بدون ارسال داده به سرورهای شخص‌ثالث.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QRCodeException;
use chillerlan\QRCode\QROptions;

/**
 * Class SimpleVPBot_Qr
 */
class SimpleVPBot_Qr {

	/**
	 * Padding around matrix for «کارت» look (pixels).
	 */
	const CARD_PADDING = 28;

	/**
	 * Whether bundled Composer autoload is available.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return class_exists( QRCode::class ) && extension_loaded( 'gd' );
	}

	/**
	 * Build QROptions (filterable for branding).
	 *
	 * @param string $text Payload (used for length hint only if needed later).
	 * @return QROptions
	 */
	private static function options( $text ) {
		unset( $text );
		// پالت «VPN / آبی اطمینان»: پس‌زمینه روشن، ماژول‌های تیره با تفاوت ظریف finder/data.
		$module_values = array(
			QRMatrix::M_DARKMODULE       => array( 16, 62, 110 ),
			QRMatrix::M_DATA_DARK        => array( 32, 118, 188 ),
			QRMatrix::M_FINDER_DARK      => array( 14, 58, 102 ),
			QRMatrix::M_FINDER_DOT       => array( 24, 108, 168 ),
			QRMatrix::M_ALIGNMENT_DARK   => array( 32, 118, 188 ),
			QRMatrix::M_TIMING_DARK      => array( 70, 142, 198 ),
			QRMatrix::M_FORMAT_DARK      => array( 32, 118, 188 ),
			QRMatrix::M_VERSION_DARK     => array( 32, 118, 188 ),
			QRMatrix::M_QUIETZONE_DARK   => array( 248, 252, 255 ),
		);

		$opts = array(
			'version'             => QRCode::VERSION_AUTO,
			'outputType'          => QRCode::OUTPUT_IMAGE_PNG,
			'eccLevel'            => QRCode::ECC_Q,
			'scale'               => 6,
			'imageBase64'         => false,
			'imageTransparent'    => false,
			'imageTransparencyBG' => array( 248, 252, 255 ),
			'addQuietzone'        => true,
			'quietzoneSize'       => 4,
			'pngCompression'      => 6,
			'moduleValues'        => $module_values,
		);

		/**
		 * Filter QR options (chillerlan QROptions constructor array).
		 *
		 * @param array<string, mixed> $opts Options.
		 */
		return new QROptions( apply_filters( 'simplevpbot_qr_options', $opts ) );
	}

	/**
	 * Raw PNG bytes for text.
	 *
	 * @param string $text Text.
	 * @return string|false PNG binary.
	 */
	public static function png_bytes( $text ) {
		if ( ! self::is_available() ) {
			SimpleVPBot_Logger::error(
				'qr: library unavailable',
				array(
					'gd'     => extension_loaded( 'gd' ),
					'qrcode' => class_exists( QRCode::class ),
				)
			);
			return false;
		}
		try {
			$qr   = new QRCode( self::options( $text ) );
			$core = $qr->render( $text );
			if ( ! is_string( $core ) || '' === $core ) {
				return false;
			}
			return self::apply_card_frame( $core );
		} catch ( QRCodeException $e ) {
			SimpleVPBot_Logger::error( 'qr: encode failed', array( 'message' => $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Soft card frame + subtle inner shadow band around code.
	 *
	 * @param string $qr_png_binary Core PNG from chillerlan.
	 * @return string|false
	 */
	private static function apply_card_frame( $qr_png_binary ) {
		if ( ! extension_loaded( 'gd' ) ) {
			return $qr_png_binary;
		}
		$src = @imagecreatefromstring( $qr_png_binary ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $src ) {
			return $qr_png_binary;
		}
		$sw  = imagesx( $src );
		$sh  = imagesy( $src );
		$pad = (int) apply_filters( 'simplevpbot_qr_card_padding', self::CARD_PADDING );
		$dw  = $sw + 2 * $pad;
		$dh  = $sh + 2 * $pad;
		$dst = imagecreatetruecolor( $dw, $dh );
		if ( ! $dst ) {
			imagedestroy( $src );
			return $qr_png_binary;
		}
		imagealphablending( $dst, true );

		$bg_top    = imagecolorallocate( $dst, 236, 244, 255 );
		$bg_bottom = imagecolorallocate( $dst, 252, 254, 255 );
		for ( $y = 0; $y < $dh; $y++ ) {
			$t      = $dh > 1 ? $y / ( $dh - 1 ) : 0;
			$r      = (int) round( 236 + ( 252 - 236 ) * $t );
			$g      = (int) round( 244 + ( 254 - 244 ) * $t );
			$b      = (int) round( 255 + ( 255 - 255 ) * $t );
			$blend = imagecolorallocate( $dst, $r, $g, $b );
			imageline( $dst, 0, $y, $dw, $y, $blend );
		}

		$shadow = imagecolorallocatealpha( $dst, 40, 90, 140, 55 );
		imagefilledrectangle( $dst, $pad - 2, $pad + 3, $pad + $sw + 1, $pad + $sh + 4, $shadow );

		imagecopy( $dst, $src, $pad, $pad, 0, 0, $sw, $sh );
		imagedestroy( $src );

		$border = imagecolorallocate( $dst, 52, 124, 186 );
		imagesetthickness( $dst, 2 );
		imagerectangle( $dst, 4, 4, $dw - 5, $dh - 5, $border );
		$inner = imagecolorallocatealpha( $dst, 255, 255, 255, 115 );
		imagesetthickness( $dst, 1 );
		imagerectangle( $dst, $pad - 6, $pad - 6, $pad + $sw + 5, $pad + $sh + 5, $inner );

		ob_start();
		imagepng( $dst, null, 6 );
		$out = ob_get_clean();
		imagedestroy( $dst );

		return false !== $out ? (string) $out : false;
	}

	/**
	 * Save temp PNG path.
	 *
	 * @param string $text Text.
	 * @return string|false Path.
	 */
	public static function temp_png( $text ) {
		$bin = self::png_bytes( $text );
		if ( ! $bin ) {
			return false;
		}
		$dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'simplevpbot/tmp/';
		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		$file = $dir . 'qr-' . wp_generate_password( 8, false ) . '.png';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $file, $bin ) ) {
			return false;
		}
		return $file;
	}
}
