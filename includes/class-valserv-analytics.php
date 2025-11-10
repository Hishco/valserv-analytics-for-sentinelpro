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
        add_filter( 'http_request_host_is_external', [ $this, 'allow_sentinelpro_hosts' ], 10, 3 );

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
        $content .= esc_html__( 'This plugin connects your site to SentinelPro Analytics to load a tracking script and fetch aggregated metrics.', 'valserv-analytics-for-sentinelpro' );
        $content .= '</p>';
        
        $content .= '<p><strong>' . esc_html__( 'Data sent to SentinelPro:', 'valserv-analytics-for-sentinelpro' ) . '</strong></p>';
        $content .= '<ul>';
        $content .= '<li>' . esc_html__( 'Page URL, referrer, and standard request metadata (IP address and user agent provided by the browser)', 'valserv-analytics-for-sentinelpro' ) . '</li>';
        $content .= '<li>' . esc_html__( 'Event data you configure (e.g., page views, scroll depth, property ID/account name)', 'valserv-analytics-for-sentinelpro' ) . '</li>';
        $content .= '<li>' . esc_html__( 'No names, emails, passwords, or WordPress user IDs are collected or transmitted by this plugin.', 'valserv-analytics-for-sentinelpro' ) . '</li>';
        $content .= '</ul>';
        
        $content .= '<p><strong>' . esc_html__( 'Data use:', 'valserv-analytics-for-sentinelpro' ) . '</strong></p>';
        $content .= '<ul>';
        $content .= '<li>' . esc_html__( 'Data is used to generate aggregated, privacy-focused analytics dashboards.', 'valserv-analytics-for-sentinelpro' ) . '</li>';
        $content .= '<li>' . esc_html__( 'IP addresses and user agents are received as part of standard HTTP requests and are not used to identify individuals.', 'valserv-analytics-for-sentinelpro' ) . '</li>';
        $content .= '</ul>';
        
        $content .= '<p><strong>' . esc_html__( 'Controls:', 'valserv-analytics-for-sentinelpro' ) . '</strong></p>';
        $content .= '<ul>';
        $content .= '<li>' . esc_html__( 'Tracking can be disabled at any time in the plugin settings.', 'valserv-analytics-for-sentinelpro' ) . '</li>';
        $content .= '<li>' . esc_html__( 'You can exclude logged-in users from tracking.', 'valserv-analytics-for-sentinelpro' ) . '</li>';
        $content .= '</ul>';
        
        $content .= '<p>';
        $content .= esc_html__( 'For more information, see SentinelPro\'s documentation and policies on your SentinelPro account site.', 'valserv-analytics-for-sentinelpro' );
        $content .= '</p>';

        wp_add_privacy_policy_content( 'Valserv Analytics for SentinelPro', wp_kses_post( $content ) );
    }

    /**
     * Allows external requests to SentinelPro hosts only.
     *
     * @param bool   $is_external Whether the request is to an external host.
     * @param string $host        The host of the request.
     * @param string $url         The full URL of the request.
     * @return bool
     */
    public function allow_sentinelpro_hosts( bool $is_external, string $host, string $url ): bool {
        $allowed_hosts = [
            'api.sentinelpro.com',
            'cdn.sentinelpro.com',
            'analytics.sentinelpro.com',
        ];

        if ( in_array( $host, $allowed_hosts, true ) ) {
            return true;
        }

        return $is_external;
    }
}
