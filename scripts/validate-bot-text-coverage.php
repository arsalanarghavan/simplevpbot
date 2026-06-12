#!/usr/bin/env php
<?php
/**
 * DEPRECATED (v13 ARCH-11): WP plugin `includes/` archived — use Laravel TextService + PHPUnit.
 *
 * @see backend/tests/Feature/Core/LoggingChannelsTest.php
 * @see docs/evidence/arch-decommission-ready-v13.md
 */
fwrite( STDERR, "DEPRECATED: use backend artisan test instead of WP includes/ scripts.\n" );
exit( 2 );

$root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}
$dirs = array(
	$root . '/includes/bot',
	$root . '/includes/cron',
	$root . '/includes/helpers',
);

require_once $root . '/includes/class-bot-text-defaults.php';
require_once $root . '/includes/class-bot-text-defaults-extended.php';

$catalog_keys = array();
$catalog_locales = array();
foreach ( SimpleVPBot_Bot_Text_Defaults::all_rows() as $row ) {
	$kn = (string) ( $row['key_name'] ?? '' );
	if ( '' === $kn ) {
		continue;
	}
	$catalog_keys[ $kn ] = true;
	$loc = (string) ( $row['locale'] ?? 'fa' );
	if ( ! isset( $catalog_locales[ $kn ] ) ) {
		$catalog_locales[ $kn ] = array();
	}
	$catalog_locales[ $kn ][ $loc ] = true;
}

$files = array();
foreach ( $dirs as $dir ) {
	if ( ! is_dir( $dir ) ) {
		continue;
	}
	$rii = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $rii as $f ) {
		if ( $f->isFile() && substr( $f->getFilename(), -4 ) === '.php' ) {
			$files[] = $f->getPathname();
		}
	}
}
sort( $files );

$key_patterns = array(
	'/SimpleVPBot_Texts::(?:get|get_for_user|get_in_bot_context|label)\(\s*[\'"]([^\'"]+)[\'"]/',
	'/self::admin_msg\(\s*[\'"]([^\'"]+)[\'"]/',
	'/SimpleVPBot_Bot_Admin_Texts::get\(\s*[\'"]([^\'"]+)[\'"]/',
);

$referenced_keys = array();
$hardcoded_hits  = array();
$wp_i18n_hits     = array();

$send_message_pattern = '/send_message\s*\(/';
$hardcoded_ui_pattern = '/(?:send_message|send_message_with_support|notify_user)\s*\([^;]*[\'"]([^\'"]*[\x{0600}-\x{06FF}\x{1F300}-\x{1FAFF}][^\'"]*)[\'"]/u';
$wp_i18n_pattern      = '/__\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]simplevpbot[\'"]\s*\)/';

foreach ( $files as $path ) {
	$src = file_get_contents( $path );
	if ( ! is_string( $src ) ) {
		continue;
	}
	// Ignore null-coalesce Persian/emoji fallbacks (catalog keys should be used instead).
	$src_for_hardcoded = preg_replace( '/\?\?\s*[\'"][^\'"]*[\'"]/', "''", $src );
	if ( ! is_string( $src_for_hardcoded ) ) {
		$src_for_hardcoded = $src;
	}
	$rel = str_replace( $root . '/', '', $path );
	$has_send = (bool) preg_match( $send_message_pattern, $src );

	foreach ( $key_patterns as $pat ) {
		if ( preg_match_all( $pat, $src, $m ) ) {
			foreach ( $m[1] as $key ) {
				$key = trim( (string) $key );
				if ( '' !== $key ) {
					$referenced_keys[ $key ] = true;
				}
			}
		}
	}

	if ( preg_match_all( $hardcoded_ui_pattern, $src_for_hardcoded, $m, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $m[1] as $cap ) {
			$s = trim( (string) $cap[0] );
			if ( strlen( $s ) < 3 ) {
				continue;
			}
			if ( preg_match( '/^(svc:|buy:|adm:|reg:|rc:|cb:)/', $s ) ) {
				continue;
			}
			if ( preg_match( '/^\{/', $s ) ) {
				continue;
			}
			$line = 1 + substr_count( substr( $src_for_hardcoded, 0, (int) $cap[1] ), "\n" );
			$hardcoded_hits[] = array(
				'file' => $rel,
				'line' => $line,
				'text' => mb_substr( $s, 0, 72, 'UTF-8' ),
			);
		}
	}

	if ( $has_send && preg_match_all( $wp_i18n_pattern, $src, $m, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $m[1] as $cap ) {
			$s    = trim( (string) $cap[0] );
			$line = 1 + substr_count( substr( $src, 0, (int) $cap[1] ), "\n" );
			$wp_i18n_hits[] = array(
				'file' => $rel,
				'line' => $line,
				'text' => mb_substr( $s, 0, 72, 'UTF-8' ),
			);
		}
	}
}

$missing_in_catalog = array();
$missing_locale     = array();
foreach ( array_keys( $referenced_keys ) as $key ) {
	if ( str_ends_with( $key, '.' ) ) {
		continue;
	}
	if ( ! isset( $catalog_keys[ $key ] ) ) {
		$missing_in_catalog[] = $key;
		continue;
	}
	foreach ( array( 'fa', 'en' ) as $loc ) {
		if ( empty( $catalog_locales[ $key ][ $loc ] ) ) {
			$missing_locale[] = $key . ' (' . $loc . ')';
		}
	}
}
sort( $missing_in_catalog );
sort( $missing_locale );

echo "Bot text coverage validation\n";
echo 'Catalog keys: ' . count( $catalog_keys ) . "\n";
echo 'Referenced keys: ' . count( $referenced_keys ) . "\n";
echo 'Missing in catalog: ' . count( $missing_in_catalog ) . "\n";
echo 'Missing locale rows: ' . count( $missing_locale ) . "\n";
echo 'Hardcoded send_message strings: ' . count( $hardcoded_hits ) . "\n";
echo 'WP __() in send_message files: ' . count( $wp_i18n_hits ) . "\n\n";

if ( ! empty( $missing_in_catalog ) ) {
	echo "Missing keys in catalog:\n";
	foreach ( $missing_in_catalog as $k ) {
		echo "  - {$k}\n";
	}
	echo "\n";
}

if ( ! empty( $missing_locale ) ) {
	echo "Missing fa/en in catalog:\n";
	foreach ( $missing_locale as $k ) {
		echo "  - {$k}\n";
	}
	echo "\n";
}

if ( ! empty( $hardcoded_hits ) ) {
	echo "Hardcoded user-visible strings:\n";
	foreach ( $hardcoded_hits as $h ) {
		echo "  - {$h['file']}:{$h['line']}  {$h['text']}\n";
	}
	echo "\n";
}

if ( ! empty( $wp_i18n_hits ) ) {
	echo "WordPress __() strings (migrate to svp_texts keys):\n";
	foreach ( $wp_i18n_hits as $h ) {
		echo "  - {$h['file']}:{$h['line']}  {$h['text']}\n";
	}
	echo "\n";
}

$fail = ! empty( $missing_in_catalog ) || ! empty( $missing_locale ) || ! empty( $hardcoded_hits ) || ! empty( $wp_i18n_hits );
exit( $fail ? 1 : 0 );
