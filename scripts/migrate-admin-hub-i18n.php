#!/usr/bin/env php
<?php
/**
 * Deprecated: Handler_Admin_Hub was removed; admin i18n lives in Handler_Admin_Pnl + facades.
 *
 * @package SimpleVPBot
 */

fwrite( STDERR, "migrate-admin-hub-i18n.php is obsolete (class-handler-admin-hub.php removed).\n" );
fwrite( STDERR, "Use msg.admin.* keys in class-bot-text-defaults-extended.php and Bot_Admin_Texts::msg().\n" );
exit( 0 );
