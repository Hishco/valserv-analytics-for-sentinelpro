<?php

if ( ! defined('ABSPATH') ) { exit; }

class SentinelPro_Settings_Validator {

    public static function sanitize(array $input, string $option_name = 'sentinelpro_options'): array {
        static $already_run = false;
        if ($already_run) return $input;
        $already_run = true;

        set_transient('sentinelpro_last_save_time', time(), 60);

        $api_key_input = sanitize_text_field($input['api_key'] ?? '');
        
        // Handle API key securely
        $current_api_key = SentinelPro_Security_Manager::get_api_key();
        $api_key_updated = false;
        
        // Handle API key input
        if (!empty($api_key_input)) {
            // Check if this is actually a different key
            if ($api_key_input !== $current_api_key) {
                // Store new API key securely
                SentinelPro_Security_Manager::store_api_key($api_key_input);
                $api_key_updated = true;
            }
            // If it's the same key, no action needed - not an error
        }

        $clean = [
            'account_name'     => sanitize_text_field($input['account_name'] ?? ''),
            'property_id'      => sanitize_text_field($input['property_id'] ?? ''),
            'enable_tracking'  => !empty($input['enable_tracking']) ? 1 : 0,
        ];
        
        // Don't include API key in the clean array - it's stored securely separately

        if (empty($clean['account_name'])) {
            add_settings_error($option_name, 'missing_account_name', __('❌ Account Name is required.', 'valserv-analytics-for-sentinelpro'), 'error');
        }

        if (empty($clean['property_id'])) {
            add_settings_error($option_name, 'missing_property_id', __('❌ Property ID is required.', 'valserv-analytics-for-sentinelpro'), 'error');
        }

        // Check if we have an API key (either existing or newly set)
        $final_api_key = $api_key_updated ? $api_key_input : $current_api_key;
        if (empty($final_api_key)) {
            add_settings_error($option_name, 'missing_api_key', __('❌ API Key is required.', 'valserv-analytics-for-sentinelpro'), 'error');
        } else if ($api_key_updated) {
            add_settings_error($option_name, 'api_key_updated', __('✅ API Key has been securely updated.', 'valserv-analytics-for-sentinelpro'), 'updated');
        }

        // Check for custom dimension changes and request new pixelCode
        if (!empty($clean['account_name']) && !empty($clean['property_id']) && !empty($final_api_key)) {
            self::check_and_update_custom_dimensions($clean['account_name'], $clean['property_id'], $final_api_key);
        }

        return $clean;
    }

    /**
     * Check for custom dimension changes and request new pixelCode
     */
    private static function check_and_update_custom_dimensions(string $account_name, string $property_id, string $api_key): void {
        try {
            // Get current custom dimensions from options
            $current_dimensions = get_option("sentinelpro_dimensions_{$property_id}", []);
            
            // Request new dimensions from API
            $new_dimensions = SentinelPro_API_Client::request_custom_dimensions($account_name, $property_id, $api_key);
            
            if ($new_dimensions !== false) {
                // Store new dimensions
                update_option("sentinelpro_dimensions_{$property_id}", $new_dimensions);
                
                // Mark that dimensions have changed for next cron job
                $dimension_changes = [
                    'added' => array_diff($new_dimensions, $current_dimensions),
                    'removed' => array_diff($current_dimensions, $new_dimensions),
                    'timestamp' => time()
                ];
                
                update_option("sentinelpro_dimension_changes_{$property_id}", $dimension_changes);
                
                // Request new pixelCode with updated dimensions
                $pixel_code = SentinelPro_API_Client::request_pixel_code($account_name, $property_id, $api_key, $new_dimensions);
                
                if ($pixel_code) {
                    update_option("sentinelpro_pixel_code_{$property_id}", $pixel_code);
                    add_settings_error('sentinelpro_options', 'dimensions_updated', __('✅ Custom dimensions updated and new tracking script requested.', 'valserv-analytics-for-sentinelpro'), 'updated');
                }
            }
        } catch (Exception $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging is essential for troubleshooting API issues
            error_log("SentinelPro: Error updating custom dimensions: " . $e->getMessage());
            add_settings_error('sentinelpro_options', 'dimensions_error', __('⚠️ Custom dimensions update failed. Please try again.', 'valserv-analytics-for-sentinelpro'), 'error');
        }
    }
}

