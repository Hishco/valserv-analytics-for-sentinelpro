<?php
/**
 * Front-end tracking integration for Valserv Analytics.
 *
 * @package ValservAnalyticsForSentinelPro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueues the SentinelPro tracking script when enabled.
 */
class Valserv_Analytics_Tracker {

    /**
     * @var string Option name used for plugin settings.
     */
    private $option_name;

    /**
     * Constructor.
     *
     * @param string $option_name Name of the option used to persist settings.
     */
    public function __construct( string $option_name ) {
        $this->option_name = $option_name;
    }

    /**
     * Sets up the hooks required on the public site.
     */
    public function init(): void {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_tracker' ] );
    }

    /**
     * Enqueues the tracking script with inline configuration.
     */
    public function enqueue_tracker(): void {
        $settings = Valserv_Analytics_Settings::get_settings();

        if ( empty( $settings['enable_tracking'] ) ) {
            return;
        }

        $account  = $settings['account_name'];
        $property = $settings['property_id'];

        if ( '' === $account || '' === $property ) {
            return;
        }

        $config = [
            'account'     => $account,
            'property_id' => $property,
            'share_usage' => ! empty( $settings['share_usage'] ),
        ];

        $handle   = 'valserv-sentinelpro-tracker';
        $endpoint = 'https://collector.sentinelpro.com/v1/tracker.js';

        wp_enqueue_script( $handle, $endpoint, [], VALSERV_ANALYTICS_VERSION, true );
        wp_add_inline_script( $handle, 'window.valservSentinelPro=' . wp_json_encode( $config ) . ';', 'before' );
    }
}
