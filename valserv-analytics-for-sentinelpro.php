<?php
/**
 * Plugin Name:       Valserv Analytics for SentinelPro
 * Description:       Connects your WordPress site to SentinelPro Analytics with a privacy-aware tracking integration.
 * Version:           2.0.0
 * Author:            Valserv Inc
 * Author URI:        https://valserv.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       valserv-analytics-for-sentinelpro
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'VALSERV_ANALYTICS_VERSION', '2.0.0' );
define( 'VALSERV_ANALYTICS_PLUGIN_FILE', __FILE__ );
define( 'VALSERV_ANALYTICS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VALSERV_ANALYTICS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once VALSERV_ANALYTICS_PLUGIN_DIR . 'includes/class-valserv-analytics-settings.php';
require_once VALSERV_ANALYTICS_PLUGIN_DIR . 'admin/class-valserv-analytics-admin.php';
require_once VALSERV_ANALYTICS_PLUGIN_DIR . 'includes/class-valserv-analytics-tracker.php';
require_once VALSERV_ANALYTICS_PLUGIN_DIR . 'includes/class-valserv-analytics.php';

/**
 * Handles plugin activation.
 */
function valserv_analytics_activate(): void {
    add_option( Valserv_Analytics_Settings::OPTION_NAME, Valserv_Analytics_Settings::get_default_settings(), '', false );
}

register_activation_hook( __FILE__, 'valserv_analytics_activate' );

$valserv_analytics = new Valserv_Analytics();
$valserv_analytics->init();
