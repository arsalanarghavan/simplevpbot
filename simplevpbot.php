<?php
/**
 * Plugin Name:       VIP BOT
 * Plugin URI:        https://github.com/simplevpbot/simplevpbot
 * Description:       ربات VIP VPS با اتصال به پنل 3x-ui، تلگرام و بله، مدیریت از وردپرس.
 * Version:           1.0.6
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            ArsalanArghavan.ir
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       simplevpbot
 * Domain Path:       /languages
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIMPLEVPBOT_VERSION', '1.0.6' );
define( 'SIMPLEVPBOT_PLUGIN_FILE', __FILE__ );
define( 'SIMPLEVPBOT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIMPLEVPBOT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SIMPLEVPBOT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

$simplevpbot_autoload = SIMPLEVPBOT_PLUGIN_DIR . 'vendor/autoload.php';
if ( is_readable( $simplevpbot_autoload ) ) {
	require_once $simplevpbot_autoload;
}

require_once SIMPLEVPBOT_PLUGIN_DIR . 'includes/class-plugin.php';

/**
 * Run plugin.
 *
 * @return SimpleVPBot_Plugin
 */
function simplevpbot() {
	return SimpleVPBot_Plugin::instance();
}

simplevpbot();
