<?php
/**
 * Valserv Analytics settings helpers.
 *
 * @package ValservAnalyticsForSentinelPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides helpers for working with plugin settings.
 */
class Valserv_Analytics_Settings {

	/**
	 * Name of the option used to persist plugin settings.
	 */
	public const OPTION_NAME = 'valserv_analytics_settings';

	/**
	 * Retrieves the default settings for the plugin.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_default_settings(): array {
		return array(
			'account_name'    => '',
			'property_id'     => '',
			'api_key'         => '',
			'enable_tracking' => false,
			'share_usage'     => false,
		);
	}

	/**
	 * Returns the stored settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings = wp_parse_args( $settings, self::get_default_settings() );

		return self::sanitize_settings( $settings );
	}

	/**
	 * Sanitizes settings prior to saving.
	 *
	 * @param array<string, mixed> $settings Raw settings from the form.
	 *
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( $settings ): array {
		if ( ! is_array( $settings ) ) {
			return self::get_default_settings();
		}

		$sanitized = self::get_default_settings();

		if ( isset( $settings['account_name'] ) ) {
			$sanitized['account_name'] = sanitize_text_field( wp_unslash( $settings['account_name'] ) );
		}

		if ( isset( $settings['property_id'] ) ) {
			$sanitized['property_id'] = sanitize_text_field( wp_unslash( $settings['property_id'] ) );
		}

		if ( isset( $settings['api_key'] ) ) {
			$sanitized['api_key'] = sanitize_text_field( wp_unslash( $settings['api_key'] ) );
		}

		if ( isset( $settings['enable_tracking'] ) ) {
			$sanitized['enable_tracking'] = rest_sanitize_boolean( $settings['enable_tracking'] );
		}

		if ( isset( $settings['share_usage'] ) ) {
			$sanitized['share_usage'] = rest_sanitize_boolean( $settings['share_usage'] );
		}

		return $sanitized;
	}
}
