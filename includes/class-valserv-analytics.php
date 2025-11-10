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
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( $this, 'register_privacy_content' ) );

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

		$content  = '<h2>' . esc_html__( 'Third-Party Service: SentinelPro Analytics', 'valserv-analytics-for-sentinelpro' ) . '</h2>';
		$content .= '<p>';
		$content .= esc_html__( 'Valserv Analytics for SentinelPro connects your site to the SentinelPro analytics platform. When enabled, the plugin loads a tracking script from the following external service:', 'valserv-analytics-for-sentinelpro' );
		$content .= '</p>';
		$content .= '<ul>';
		$content .= '<li><strong>' . esc_html__( 'Service:', 'valserv-analytics-for-sentinelpro' ) . '</strong> ' . esc_html__( 'SentinelPro Tracking Service', 'valserv-analytics-for-sentinelpro' ) . '</li>';
		$content .= '<li><strong>' . esc_html__( 'Hostname:', 'valserv-analytics-for-sentinelpro' ) . '</strong> <code>collector.sentinelpro.com</code></li>';
		$content .= '<li><strong>' . esc_html__( 'Data Sent:', 'valserv-analytics-for-sentinelpro' ) . '</strong> ' . esc_html__( 'Page view data, anonymised usage information, configured property ID, and account name. No personally identifiable information (PII) such as user passwords, email addresses, or personal data is transmitted by default.', 'valserv-analytics-for-sentinelpro' ) . '</li>';
		$content .= '<li><strong>' . esc_html__( 'Optional Data:', 'valserv-analytics-for-sentinelpro' ) . '</strong> ' . esc_html__( 'If you enable "Share Usage Metrics with SentinelPro", additional anonymised plugin usage statistics may be sent to help improve the service.', 'valserv-analytics-for-sentinelpro' ) . '</li>';
		$content .= '<li><strong>' . esc_html__( 'Data Retention:', 'valserv-analytics-for-sentinelpro' ) . '</strong> ' . esc_html__( 'Data retention is handled by SentinelPro according to their privacy policy. This plugin does not store tracking data locally.', 'valserv-analytics-for-sentinelpro' ) . '</li>';
		$content .= '<li><strong>' . esc_html__( 'Terms of Service:', 'valserv-analytics-for-sentinelpro' ) . '</strong> <a href="https://sentinelpro.ai/terms" target="_blank" rel="noopener noreferrer">https://sentinelpro.ai/terms</a></li>';
		$content .= '<li><strong>' . esc_html__( 'Privacy Policy:', 'valserv-analytics-for-sentinelpro' ) . '</strong> <a href="https://sentinelpro.ai/privacy" target="_blank" rel="noopener noreferrer">https://sentinelpro.ai/privacy</a></li>';
		$content .= '</ul>';
		$content .= '<p>';
		$content .= esc_html__( 'Site administrators can disable tracking at any time from the plugin settings page (Dashboard → Valserv Analytics).', 'valserv-analytics-for-sentinelpro' );
		$content .= '</p>';

		wp_add_privacy_policy_content( 'Valserv Analytics for SentinelPro', wp_kses_post( $content ) );
	}
}
