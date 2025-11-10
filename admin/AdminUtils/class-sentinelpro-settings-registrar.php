<?php

if ( ! defined('ABSPATH') ) { exit; }

class SentinelPro_Settings_Registrar {

    /**
     * In future, consider type-hinting $context with an interface like:
     * interface SentinelPro_Settings_Context {
     *     public function sanitize_settings($input);
     *     public function render_setting_field(string $key);
     * }
     */
    public static function register(string $option_name, object $context): void {
        // If $option_name is always 'sentinelpro_options', only one register_setting is needed.
        register_setting('sentinelpro_settings_group', $option_name, [
            'sanitize_callback' => [$context, 'sanitize_settings'],
        ]);

        add_settings_section(
            'sentinelpro_main_section',
            __('Main Settings', 'valserv-analytics-for-sentinelpro'),
            null,
            'sentinelpro-settings'
        );

        add_settings_field(
            'account_name',
            __('Account Name', 'valserv-analytics-for-sentinelpro'),
            function () use ($context) {
                $context->render_setting_field('account_name');
            },
            'sentinelpro-settings',
            'sentinelpro_main_section'
        );

        add_settings_field(
            'property_id',
            __('Property ID', 'valserv-analytics-for-sentinelpro'),
            function () use ($context) {
                $context->render_setting_field('property_id');
            },
            'sentinelpro-settings',
            'sentinelpro_main_section'
        );

        add_settings_field(
            'api_key',
            __('API Key', 'valserv-analytics-for-sentinelpro'),
            function () use ($context) {
                $context->render_setting_field('api_key');
            },
            'sentinelpro-settings',
            'sentinelpro_main_section'
        );

        add_settings_field(
            'enable_tracking',
            __('Enable Tracking in WP CMS', 'valserv-analytics-for-sentinelpro'),
            function () use ($context) {
                $context->render_setting_field('enable_tracking');
            },
            'sentinelpro-settings',
            'sentinelpro_main_section'
        );
    }
}
