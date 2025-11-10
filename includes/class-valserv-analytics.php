<?php
/**
 * Core bootstrap class for the Valserv Analytics plugin.
 *
 * @package ValservAnalyticsForSentinelPro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Coordinates plugin setup for both admin and public contexts.
 */
class Valserv_Analytics {

    /**
     * Boots plugin functionality.
     */
    public function init(): void {
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
        add_action( 'admin_init', [ $this, 'register_privacy_content' ] );

        if ( is_admin() ) {
            $admin = new Valserv_Analytics_Admin( Valserv_Analytics_Settings::OPTION_NAME );
            $admin->init();
        }

        $tracker = new Valserv_Analytics_Tracker( Valserv_Analytics_Settings::OPTION_NAME );
        $tracker->init();
    }

    /**
     * Loads the plugin text domain for localisation.
     */
    public function load_textdomain(): void {
        load_plugin_textdomain( 'valserv-analytics-for-sentinelpro', false, dirname( plugin_basename( VALSERV_ANALYTICS_PLUGIN_FILE ) ) . '/languages' );
    }

    /**
     * Registers privacy policy content describing data usage.
     */
    public function register_privacy_content(): void {
        if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
            return;
        }

        $content  = '<p>';
        $content .= esc_html__( 'Valserv Analytics for SentinelPro connects your site to the SentinelPro analytics platform. When enabled, the plugin sends page view data, anonymised usage information, and the configured property ID to SentinelPro servers.', 'valserv-analytics-for-sentinelpro' );
        $content .= '</p>';
        $content .= '<p>';
        $content .= esc_html__( 'The plugin never shares WordPress user passwords or other unrelated data. Site administrators can disable tracking at any time from the plugin settings page.', 'valserv-analytics-for-sentinelpro' );
        $content .= '</p>';

        wp_add_privacy_policy_content( 'Valserv Analytics for SentinelPro', wp_kses_post( $content ) );
    }
}
