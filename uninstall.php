<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package ValservAnalyticsForSentinelPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-valserv-analytics-settings.php';

delete_option( Valserv_Analytics_Settings::OPTION_NAME );

delete_site_option( Valserv_Analytics_Settings::OPTION_NAME );
