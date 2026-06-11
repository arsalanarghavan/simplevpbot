<?php
/**
 * Uninstall SimpleVPBot.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$prefix = $wpdb->prefix . 'svp_';
$tables = array(
	'logs',
	'broadcast_queue',
	'broadcasts',
	'texts',
	'sync_codes',
	'pending_approvals',
	'receipts',
	'transactions',
	'cards',
	'services',
	'service_transfer_codes',
	'l2tp_servers',
	'plans',
	'plan_categories',
	'users',
);

foreach ( $tables as $t ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$prefix}{$t}" );
}

$options = array(
	'simplevpbot_settings',
	'simplevpbot_db_version',
	'simplevpbot_webhook_secret_telegram',
	'simplevpbot_webhook_secret_bale',
);

foreach ( $options as $opt ) {
	delete_option( $opt );
}

wp_clear_scheduled_hook( 'simplevpbot_cron_backup' );
wp_clear_scheduled_hook( 'simplevpbot_cron_expiry' );
wp_clear_scheduled_hook( 'simplevpbot_cron_purge_expired' );
wp_clear_scheduled_hook( 'simplevpbot_cron_autorenew' );
wp_clear_scheduled_hook( 'simplevpbot_cron_broadcast' );
wp_clear_scheduled_hook( 'simplevpbot_cron_panel_online' );
wp_clear_scheduled_hook( 'simplevpbot_cron_panel_service_sync' );
