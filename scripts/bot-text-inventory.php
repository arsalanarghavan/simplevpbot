#!/usr/bin/env php
<?php
/**
 * Inventory helper: list bot PHP files and scan for likely hardcoded UI strings.
 *
 * Usage: php scripts/bot-text-inventory.php
 *
 * After i18n migration, run to find remaining Persian/emoji literals in handlers.
 * Keys in SimpleVPBot_Bot_Text_Defaults / Extended are seeded via plugin upgrade
 * (SimpleVPBot_Activator::seed_missing_text_keys). Reset old DB labels from
 * Dashboard → Texts → reset per key or reset all defaults.
 *
 * @package SimpleVPBot
 */

$root = dirname( __DIR__ );
$dir  = $root . '/includes/bot';
if ( ! is_dir( $dir ) ) {
	fwrite( STDERR, "Missing: {$dir}\n" );
	exit( 1 );
}

$rii = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
$files = array();
foreach ( $rii as $f ) {
	if ( $f->isFile() && substr( $f->getFilename(), -4 ) === '.php' ) {
		$files[] = $f->getPathname();
	}
}
sort( $files );

echo "SimpleVPBot bot text inventory\n";
echo "Root: {$root}\n";
echo 'PHP files under includes/bot: ' . count( $files ) . "\n\n";

$key_count = 0;
if ( is_readable( $root . '/includes/class-bot-text-defaults.php' ) ) {
	require_once $root . '/includes/class-bot-text-defaults.php';
	if ( class_exists( 'SimpleVPBot_Bot_Text_Defaults' ) ) {
		$rows = SimpleVPBot_Bot_Text_Defaults::all_rows();
		$keys = array();
		foreach ( $rows as $row ) {
			if ( ! empty( $row['key_name'] ) ) {
				$keys[ (string) $row['key_name'] ] = true;
			}
		}
		$key_count = count( $keys );
		echo "Default text keys (fa+en rows: " . count( $rows ) . ", unique keys: {$key_count})\n\n";
	}
}

echo "Scanning for send_message / glass_button_text with quoted Persian or emoji UI strings...\n";
$pattern = '/(?:send_message|glass_button_text)\s*\([^;]*[\'"]([^\'"]*[\x{0600}-\x{06FF}\x{1F300}-\x{1FAFF}][^\'"]*)[\'"]/u';
$hits = 0;
foreach ( $files as $path ) {
	$src = file_get_contents( $path );
	if ( ! is_string( $src ) ) {
		continue;
	}
	if ( preg_match_all( $pattern, $src, $m, PREG_OFFSET_CAPTURE ) ) {
		$rel = str_replace( $root . '/', '', $path );
		echo "\n{$rel}:\n";
		$seen = array();
		foreach ( $m[1] as $cap ) {
			$s = trim( (string) $cap[0] );
			if ( strlen( $s ) < 4 || isset( $seen[ $s ] ) ) {
				continue;
			}
			if ( preg_match( '/^(svc:|buy:|adm:|reg:|rc:)/', $s ) ) {
				continue;
			}
			$seen[ $s ] = true;
			echo '  - ' . mb_substr( $s, 0, 80, 'UTF-8' ) . ( mb_strlen( $s, 'UTF-8' ) > 80 ? '…' : '' ) . "\n";
			++$hits;
		}
	}
}
echo "\nPossible hardcoded UI strings: {$hits}\n";
echo "\nWorkflow:\n";
echo "1. Add keys via includes/class-bot-text-defaults-extended.php\n";
echo "2. Replace literals with SimpleVPBot_Texts::get_for_user( 'key', \$user )\n";
echo "3. Upgrade plugin or run seed_missing_text_keys for DB\n";
echo "4. Re-run this script until hits are minimal (admin-hub may still have many)\n";
