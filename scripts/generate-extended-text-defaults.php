#!/usr/bin/env php
<?php
/** DEPRECATED (v14 ARCH-11): WP includes/ removed — use Laravel TextService + dashboard Texts UI. */
fwrite(STDERR, "DEPRECATED: bot text defaults managed in Laravel backend.\n");
exit(2);

$root = dirname( __DIR__ );
$out  = $root . '/includes/class-bot-text-defaults-extended.php';

$pairs = require $root . '/scripts/data/bot-text-pairs.php';

$lines = array();
$lines[] = '<?php';
$lines[] = '/**';
$lines[] = ' * Extended bot text seeds (service, buy, admin, keyboards).';
$lines[] = ' *';
$lines[] = ' * @package SimpleVPBot';
$lines[] = ' */';
$lines[] = '';
$lines[] = 'if ( ! defined( \'ABSPATH\' ) ) {';
$lines[] = '	exit;';
$lines[] = '}';
$lines[] = '';
$lines[] = '/**';
$lines[] = ' * Additional default rows for SimpleVPBot_Bot_Text_Defaults::all_rows().';
$lines[] = ' */';
$lines[] = 'class SimpleVPBot_Bot_Text_Defaults_Extended {';
$lines[] = '';
$lines[] = '	private static function pair( array &$rows, $key, $category, $fa, $en ) {';
$lines[] = '		$rows[] = array(';
$lines[] = '			\'key_name\' => (string) $key,';
$lines[] = '			\'category\' => (string) $category,';
$lines[] = '			\'locale\'   => \'fa\',';
$lines[] = '			\'value\'    => (string) $fa,';
$lines[] = '		);';
$lines[] = '		$rows[] = array(';
$lines[] = '			\'key_name\' => (string) $key,';
$lines[] = '			\'category\' => (string) $category,';
$lines[] = '			\'locale\'   => \'en\',';
$lines[] = '			\'value\'    => (string) $en,';
$lines[] = '		);';
$lines[] = '	}';
$lines[] = '';
$lines[] = '	public static function append_rows( array &$r ) {';

foreach ( $pairs as $p ) {
	$key  = $p[0];
	$cat  = $p[1];
	$fa   = str_replace( "'", "\\'", $p[2] );
	$en   = str_replace( "'", "\\'", $p[3] );
	$lines[] = "\t\tself::pair( \$r, '{$key}', '{$cat}', '{$fa}', '{$en}' );";
}

$lines[] = '	}';
$lines[] = '}';
$lines[] = '';

file_put_contents( $out, implode( "\n", $lines ) . "\n" );
echo "Wrote {$out} with " . count( $pairs ) . " keys\n";
