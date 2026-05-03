#!/usr/bin/env php
<?php
/**
 * Inventory helper: list PHP files under includes/bot for manual text-key migration (plan phase A–C).
 *
 * Usage: php scripts/bot-text-inventory.php
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
echo "Suggested workflow:\n";
echo "1. Grep for Persian/English literals in handlers and class-keyboards.php.\n";
echo "2. Add keys to SimpleVPBot_Activator::default_text_rows() and replace code with SimpleVPBot_Texts::get().\n";
echo "3. Run plugin upgrade / seed_missing_text_keys so DB picks up new keys.\n\n";
echo "Files:\n";
foreach ( $files as $p ) {
	echo str_replace( $root . '/', '', $p ) . "\n";
}
