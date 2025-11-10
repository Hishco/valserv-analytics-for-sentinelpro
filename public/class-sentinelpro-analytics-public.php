<?php

if ( ! defined('ABSPATH') ) { exit; }

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for injecting tracking scripts.
 *
 * @since      1.0.0
 * @package    SentinelPro_Analytics
 * @subpackage SentinelPro_Analytics/public
 */
class SentinelPro_Analytics_Public {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name  The name of the plugin.
     * @param    string    $version      The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // 👇 Add this line to register the method
        add_action('wp_head', [$this, 'inject_tracking_script']);
    }

    /**
     * Injects the SentinelPro tracking script into the site <head>.
     * Respects settings: property ID and exclude logged-in users.
     *
     * @since 1.0.0
     */
    public function inject_tracking_script() {
        $property_id = get_option('sentinelpro_property_id');

        if (empty($property_id)) {
            return; // No tracking if property ID is missing
        }

        wp_enqueue_script(
            'sentinelpro-tracker',
            'https://analytics.sentinelpro.com/track.js?id=' . esc_attr($property_id),
            [],
            '1.0',
            true
        );
    }
}
