<?php

/**
 * One-off: extract CREATE TABLE DDL from WordPress activator for Laravel migration.
 * Canonical schema after WP decommission: database/schema/svp_wp_parity.sql
 * WP activator archived on branch archive/wp-plugin.
 */
if (! defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 2).'/');
}

$wpActivator = dirname(__DIR__, 2).'/includes/class-activator.php';
if (! is_file($wpActivator)) {
    fwrite(STDERR, "WP activator not found. Use database/schema/svp_wp_parity.sql or checkout archive/wp-plugin.\n");
    exit(1);
}
require_once $wpActivator;

$p = '';
$charset = 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

$statements = [];

$ref = new ReflectionClass(SimpleVPBot_Activator::class);
$methods = [
    'sql_marketing_rules',
    'sql_marketing_offers',
    'sql_discount_codes',
    'sql_referral_events',
    'sql_user_activity',
    'sql_svp_service_ip_log',
    'sql_discount_redemptions',
    'sql_unit_economics_config',
    'sql_unit_economics_servers',
    'sql_panel_economics_lines',
    'sql_reseller_inbound_display_names',
    'sql_reseller_panel_prices',
    'sql_reseller_parent_panel_floors',
    'sql_reseller_bot_profiles',
    'sql_reseller_closure',
    'sql_audit_log',
    'sql_panel_inbound_clients',
    'sql_panel_inbound_api',
];

foreach ($methods as $method) {
    if ($ref->hasMethod($method)) {
        $m = $ref->getMethod($method);
        $m->setAccessible(true);
        $statements[] = $m->invoke(null, $p, $charset);
    }
}

// Private methods via closure binding
$private = ['sql_svp_services', 'sql_l2tp_servers', 'sql_plans_table', 'sql_plan_categories', 'sql_panels_table', 'sql_panel_online_daily', 'sql_monitor_hosts'];
foreach ($private as $method) {
    $m = $ref->getMethod($method);
    $m->setAccessible(true);
    $statements[] = $m->invoke(null, $p, $charset);
}

// Inline from create_tables
$inline = file_get_contents($wpActivator);
preg_match_all('/\$sql_\w+ = "CREATE TABLE[^"]+";/s', $inline, $matches);
foreach ($matches[0] as $block) {
    if (preg_match('/"(CREATE TABLE[^"]+)"/s', $block, $m)) {
        $statements[] = str_replace('{$p}', '', $m[1]).' '.$charset.';';
    }
}

preg_match('/"CREATE TABLE \{\$p\}svp_inbound_queue[^"]+"/s', $inline, $iq);
if (! empty($iq[0])) {
    $statements[] = str_replace(['{$p}', '"'], ['', ''], $iq[0]).' '.$charset.';';
}

// Wholesale tables from maybe_migrate_220
preg_match_all('/return "CREATE TABLE \{\$p\}svp_reseller_wholesale[^"]+"/s', $inline, $wh);
foreach ($wh[0] as $w) {
    $statements[] = str_replace(['return "', '{$p}'], ['', ''], $w).' '.$charset.';';
}

$out = dirname(__DIR__).'/database/schema/svp_wp_parity.sql';
@mkdir(dirname($out), 0755, true);
$ddl = array_unique(array_map(function ($s) use ($p, $charset) {
    $s = str_replace(['{$p}', '$charset_collate'], ['', $charset], $s);
    $s = preg_replace('/\s+\$charset_collate;/', ';', $s);

    return trim($s);
}, $statements));

file_put_contents($out, implode("\n\n", $ddl)."\n");
echo 'Wrote '.count($ddl).' statements to '.$out.PHP_EOL;
