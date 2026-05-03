<?php
/**
 * PHPUnit bootstrap (no WordPress load — smoke-level tests only).
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

$root = dirname( __DIR__ );
require_once $root . '/vendor/autoload.php';
