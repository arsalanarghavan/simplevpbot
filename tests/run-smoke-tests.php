<?php
/**
 * Minimal smoke tests when PHPUnit cannot run (missing ext-dom/xml).
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

$root = dirname( __DIR__ );

$fail = static function ( string $msg ) use ( $root ): void {
	fwrite( STDERR, "FAIL: {$msg}\n" );
	exit( 1 );
};

foreach (
	array(
		$root . '/simplevpbot.php',
		$root . '/includes/class-plugin.php',
		$root . '/includes/helpers/class-backup-export.php',
		$root . '/includes/helpers/class-backup-restore.php',
		$root . '/includes/helpers/class-admin-user-ops.php',
		$root . '/includes/frontend/class-portal-admin.php',
	) as $f
) {
	if ( ! is_file( $f ) ) {
		$fail( "missing file: {$f}" );
	}
}

$line = "INSERT INTO `wp_svp_users` (`id`) VALUES (1);";
if ( ! preg_match( '/^\s*INSERT\s+INTO\s+`([^`]+)`/i', trim( $line ), $m ) || 'wp_svp_users' !== $m[1] ) {
	$fail( 'INSERT table parse' );
}

echo "OK run-smoke-tests.php\n";
exit( 0 );
