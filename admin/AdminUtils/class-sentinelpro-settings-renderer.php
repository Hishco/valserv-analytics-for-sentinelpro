<?php

if ( ! defined('ABSPATH') ) { exit; }

class SentinelPro_Settings_Renderer {

    public static function render_account_name_field(string $option_name): void {
        $options = get_option('sentinelpro_options', []);
        $value = esc_attr($options['account_name'] ?? '');
        $id = 'sentinelpro_account_name';
        echo "<label for='" . esc_attr($id) . "' style='display:block;margin-bottom:4px;'>" . esc_html__('Account Name', 'valserv-analytics-for-sentinelpro') . "</label>";
        echo "<input id='" . esc_attr($id) . "' type='text' name='" . esc_attr($option_name) . "[account_name]' value='" . esc_attr($value) . "' />";
    }

    public static function render_property_id_field(string $option_name): void {
        $options = get_option('sentinelpro_options', []);
        $value = esc_attr($options['property_id'] ?? '');
        $id = 'sentinelpro_property_id';
        echo "<label for='" . esc_attr($id) . "' style='display:block;margin-bottom:4px;'>" . esc_html__('Property ID', 'valserv-analytics-for-sentinelpro') . "</label>";
        echo "<input id='" . esc_attr($id) . "' type='text' name='" . esc_attr($option_name) . "[property_id]' value='" . esc_attr($value) . "' />";
    }

    public static function render_api_key_field(string $option_name): void {
        $options = get_option('sentinelpro_options', []);
        $value = esc_attr($options['api_key'] ?? '');
        $id = 'sentinelpro_api_key';
        echo "<label for='" . esc_attr($id) . "' style='display:block;margin-bottom:4px;'>" . esc_html__('API Key', 'valserv-analytics-for-sentinelpro') . "</label>";
        echo "<input id='" . esc_attr($id) . "' type='password' name='" . esc_attr($option_name) . "[api_key]' value='" . esc_attr($value) . "' autocomplete='off' />";
    }

    public static function render_tracking_field(string $option_name): void {
        $options = get_option($option_name, []);
        $checked = !empty($options['enable_tracking']);
        $id = 'sentinelpro_enable_tracking';
        echo "<input type='hidden' name='" . esc_attr($option_name) . "[enable_tracking]' value='0' />";
        echo "<input id='" . esc_attr($id) . "' type='checkbox' name='" . esc_attr($option_name) . "[enable_tracking]' value='1' " . checked(true, $checked, false) . " />";
        echo "<label for='" . esc_attr($id) . "' style='margin-left:8px;'>" . esc_html__('Enable Tracking in WP CMS', 'valserv-analytics-for-sentinelpro') . "</label>";
    }
}
