<?php

if ( ! defined('ABSPATH') ) { exit; }

class SentinelPro_Auth_Handler {
    
    /**
     * Normalize and validate account name to safe subdomain token
     */
    private static function normalize_account_name(string $name): string {
        $name = strtolower(trim($name));
        return preg_match('/^[a-z0-9-]+$/', $name) ? $name : '';
    }
    
    public static function save(array $data): void {
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Auth handler debug logging for troubleshooting
            error_log('SentinelPro: Auth handler save method called');
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Auth handler debug logging for troubleshooting
            error_log('SentinelPro: Input data: ' . print_r($data, true));
        }
        
        // Superuser check - only superuser can save API settings, or first admin during initial installation
        $current_user_id = get_current_user_id();
        $superuser_id = (int) get_option('sentinelpro_superuser_id');
        $is_initial_installation = !$superuser_id && !SentinelPro_User_Access_Manager::are_api_credentials_configured();
        $can_save = ($current_user_id === $superuser_id) || ($is_initial_installation && current_user_can('manage_options'));
        
        if (!$can_save) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Auth handler debug logging for troubleshooting
                error_log('SentinelPro: User is not superuser and not first admin during initial installation - cannot save API settings');
            }
            wp_send_json_error(['message' => 'Only the SuperUser can save API settings. Contact your administrator.'], 403);
        }
        
        // Capability check for security
        if (!current_user_can('manage_options')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Auth handler debug logging for troubleshooting
                error_log('SentinelPro: User lacks manage_options capability');
            }
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
        }
        
        ob_start();
        
        // Rate limiting for auth attempts
        $throttle_key = 'valserv_auth_' . get_current_user_id();
        $tries = (int) get_transient($throttle_key);
        if ($tries > 20) { // 20 tries per 10 minutes
            $output = ob_get_clean();
            self::log_buffer($output);
            wp_send_json_error(['message' => 'Too many attempts. Try again later.'], 429);
        }
        set_transient($throttle_key, $tries + 1, 10 * MINUTE_IN_SECONDS);

        // Sanitize and validate inputs
        $plan_raw = wp_unslash($data['plan'] ?? '');
        $plan = sanitize_key($plan_raw);
        $allowed_plans = ['free', 'pro', 'enterprise']; // adjust as needed
        if (!in_array($plan, $allowed_plans, true)) {
            $plan = 'free';
        }

        $token = trim(sanitize_text_field(wp_unslash($data['token'] ?? '')));
        $account_name = trim(sanitize_text_field(wp_unslash($data['account_name'] ?? '')));
        $property_id = trim(sanitize_text_field(wp_unslash($data['property_id'] ?? '')));
        $enable_tracking = !empty($data['enable_tracking']) ? 1 : 0;
        $cron_timezone = sanitize_text_field(wp_unslash($data['cron_timezone'] ?? ''));
        
        // Debug logging for data validation
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Auth handler debug logging for troubleshooting
            error_log("SentinelPro: Token length: " . strlen($token));
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Auth handler debug logging for troubleshooting
            error_log("SentinelPro: Account name: {$account_name}");
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Auth handler debug logging for troubleshooting
            error_log("SentinelPro: Property ID: {$property_id}");
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Auth handler debug logging for troubleshooting
            error_log("SentinelPro: Enable tracking: {$enable_tracking}");
        }

        if (!$plan || !$token || !$account_name) {
            $output = ob_get_clean();
            self::log_buffer($output);
            wp_send_json_error(['message' => 'Missing data']);
        }
        
        // Validate account name before using in hostname
        $account_name = self::normalize_account_name($account_name);
        if ($account_name === '') {
            $output = ob_get_clean();
            self::log_buffer($output);
            wp_send_json_error(['message' => 'Invalid account name']);
        }

        // Get existing sentinelpro_options array or initialize if it doesn't exist
        // Note: This should be seeded with autoload='no' on plugin activation
        $options = get_option('sentinelpro_options', []);

        // Update values within the options array
        $options['account_name'] = $account_name;
        $options['property_id'] = $property_id;
        $options['enable_tracking'] = $enable_tracking;
        $options['plan'] = $plan;
        $options['user_id'] = absint($data['user_id'] ?? 0);

        // Save the consolidated options array (without API key first)
        update_option('sentinelpro_options', $options);
        
        // Use secure storage for API key
        SentinelPro_Security_Manager::store_api_key($token);

        // Handle timezone setting - always set a timezone
        if (!empty($cron_timezone) && in_array($cron_timezone, DateTimeZone::listIdentifiers())) {
            update_option('sentinelpro_cron_timezone', $cron_timezone);
            
            // Update the superuser's timezone preference
            $superuser_id = (int) get_option('sentinelpro_superuser_id');
            if ($superuser_id) {
                update_user_meta($superuser_id, 'sentinelpro_cron_timezone', $cron_timezone);
            }
            
            // Reschedule cron with new timezone
            if (class_exists('SentinelPro_Universal_Cron_Manager')) {
                $cron_manager = SentinelPro_Universal_Cron_Manager::get_instance();
                $cron_manager->reschedule_cron();
            }
            
            // Add timezone info to success message
            $timezone_message = " Timezone auto-detected: {$cron_timezone}";
        } else {
            // Always set a timezone - use WordPress default timezone if detection failed
            $wp_timezone = get_option('timezone_string', 'UTC');
            if (in_array($wp_timezone, DateTimeZone::listIdentifiers())) {
                update_option('sentinelpro_cron_timezone', $wp_timezone);
                $timezone_message = " Using WordPress timezone: {$wp_timezone}";
            } else {
                update_option('sentinelpro_cron_timezone', 'UTC');
                $timezone_message = " Using default timezone: UTC";
            }
            
            // Update the superuser's timezone preference
            $superuser_id = (int) get_option('sentinelpro_superuser_id');
            if ($superuser_id) {
                update_user_meta($superuser_id, 'sentinelpro_cron_timezone', get_option('sentinelpro_cron_timezone'));
            }
            
            // Reschedule cron with new timezone
            if (class_exists('SentinelPro_Universal_Cron_Manager')) {
                $cron_manager = SentinelPro_Universal_Cron_Manager::get_instance();
                $cron_manager->reschedule_cron();
            }
        }

        $start_date = gmdate('Y-m-d', strtotime('-1 day'));
        $end_date = gmdate('Y-m-d', strtotime('+1 day'));

        $query = [
            'filters' => [
                'date' => ['gt' => $start_date, 'lt' => $end_date],
                'propertyId' => ['in' => [$property_id]],
            ],
            'granularity' => 'daily',
            'metrics' => ['views'],
            'dimensions' => ['date'],
            'orderBy' => ['date' => 'asc'],
            'pagination' => ['pageSize' => 1, 'pageNumber' => 1]
        ];

        $encoded_query = rawurlencode(wp_json_encode($query, JSON_UNESCAPED_SLASHES));
        $url = "https://{$account_name}.sentinelpro.com/api/v1/traffic/?data={$encoded_query}";

        $response = wp_safe_remote_get($url, [
            'headers' => [
                'SENTINEL-API-KEY' => $token,
                'Accept' => 'application/json'
            ],
            'timeout' => 30,
            'sslverify' => true
        ]);

        if (is_wp_error($response)) {
            $output = ob_get_clean();
            self::log_buffer($output);
            
            $error_message = '⚠️ Network error during API validation. Credentials saved but access restricted.';
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $error_message .= " Error: " . $response->get_error_message();
            }
            
            // Still save the settings but with restricted access
            if (class_exists('SentinelPro_Security_Manager')) {
                SentinelPro_Security_Manager::store_clearance_level($target_user_id, 'restricted');
            } else {
                update_user_meta($target_user_id, 'sentinelpro_clearance_level', 'restricted');
            }
            
            // Even if network error, try to create the table with basic structure
            if (class_exists('SentinelPro_Database_Manager')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Auth handler debug logging for troubleshooting
                error_log("SentinelPro: Network error, but attempting to create table with basic structure");
            }
                $db_manager = SentinelPro_Database_Manager::get_instance();
                $db_manager->ensure_analytics_events_table_exists();
            }
            
            wp_send_json_error([
                'message'   => $error_message,
                'clearance' => 'restricted',
                'redirect'  => admin_url('admin.php?page=sentinelpro-api-input'),
            ]);
            return;
        }
        
        $status = wp_remote_retrieve_response_code($response);
        $body   = wp_remote_retrieve_body($response);

        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Auth handler debug logging for troubleshooting
            error_log("SentinelPro Auth Debug - Status: {$status}, Body: {$body}");
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Auth handler debug logging for troubleshooting
            error_log("SentinelPro Auth Debug - URL: {$url}");
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Auth handler debug logging for troubleshooting
            error_log("SentinelPro Auth Debug - Account: {$account_name}, Property: {$property_id}");
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Auth handler debug logging for troubleshooting
            error_log("SentinelPro Auth Debug - JSON Body: " . print_r($json_body, true));
        }
        
        $target_user_id = absint($data['user_id'] ?? 0);
        if ($target_user_id <= 0 || !get_user_by('ID', $target_user_id)) {
            $target_user_id = get_current_user_id();
        }

        $existing_superuser = (int) get_option('sentinelpro_superuser_id');

        $json_body = json_decode(trim($body), true);
        
        // --- START OF MODIFIED SECTION ---
        $is_forbidden_json = false; // Initialize the variable explicitly
        if (is_array($json_body) && isset($json_body['status']) && (int) $json_body['status'] === 403) {
            $is_forbidden_json = true;
        }
        // --- END OF MODIFIED SECTION ---

        // 🔍 Check the actual API response content to determine the right clearance level
        $api_message = '';
        if (is_array($json_body) && isset($json_body['message'])) {
            $api_message = strtolower(trim($json_body['message']));
        }

        // 🟥 1. If status is 403 or 500 with 403 JSON, check the actual message
        if ($status === 403 || $is_forbidden_json) {
            // Check if it's "property not found" (invalid credentials) vs "access restricted" (valid user, limited access)
            if (!empty($api_message) && (strpos($api_message, 'property not found') !== false || strpos($api_message, 'invalid credentials') !== false)) {
                // This is invalid credentials - set to restricted
                if (class_exists('SentinelPro_Security_Manager')) {
                    SentinelPro_Security_Manager::store_clearance_level($target_user_id, 'restricted');
                } else {
                    update_user_meta($target_user_id, 'sentinelpro_clearance_level', 'restricted');
                }
                
                // Even if invalid credentials, try to create the table with basic structure
                if (class_exists('SentinelPro_Database_Manager')) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Auth handler debug logging for troubleshooting
                    error_log("SentinelPro: Invalid credentials, but attempting to create table with basic structure");
                }
                    $db_manager = SentinelPro_Database_Manager::get_instance();
                    $db_manager->ensure_analytics_events_table_exists();
                }

                $output = ob_get_clean();
                self::log_buffer($output);
                
                $error_message = '❌ Invalid credentials – property not found or access denied.';
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    $error_message .= " (API Status: {$status})";
                }
                
                wp_send_json_error([
                    'message'   => $error_message,
                    'clearance' => 'restricted',
                    'redirect'  => admin_url('admin.php?page=sentinelpro-api-input'),
                ]);
                return;
            } else {
                // This is "access restricted" - valid user with limited access, set to elevated
                if (class_exists('SentinelPro_Security_Manager')) {
                    SentinelPro_Security_Manager::store_clearance_level($target_user_id, 'elevated');
                } else {
                    update_user_meta($target_user_id, 'sentinelpro_clearance_level', 'elevated');
                }
                
                // Even if access restricted, try to create the table with basic structure
                if (class_exists('SentinelPro_Database_Manager')) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Auth handler debug logging for troubleshooting
                    error_log("SentinelPro: Access restricted, but attempting to create table with basic structure");
                }
                    $db_manager = SentinelPro_Database_Manager::get_instance();
                    $db_manager->ensure_analytics_events_table_exists();
                }

                $output = ob_get_clean();
                self::log_buffer($output);
                
                $error_message = '🚫 Access is restricted, elevated access applied.';
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    $error_message .= " (API Status: {$status})";
                }
                
                wp_send_json_error([
                    'message'   => $error_message,
                    'clearance' => 'elevated',
                    'redirect'  => admin_url('admin.php?page=sentinelpro-api-input'),
                ]);
                return;
            }
        }

        // 🟩 2. If status is 200 → Admin
        if ($status === 200) {
            // Reset rate limit after successful validation
            delete_transient($throttle_key);
            
            // Use secure clearance level system
            if (class_exists('SentinelPro_Security_Manager')) {
                SentinelPro_Security_Manager::store_clearance_level($target_user_id, 'admin');
            } else {
                update_user_meta($target_user_id, 'sentinelpro_clearance_level', 'admin');
            }
            if (!$existing_superuser || !get_user_by('ID', $existing_superuser)) {
                SentinelPro_User_Access_Manager::promote_to_superuser($target_user_id);
            }
            
            // Fetch and store custom dimensions from the API
            if (class_exists('SentinelPro_API_Client')) {
                $dimensions = SentinelPro_API_Client::request_custom_dimensions($account_name, $property_id, $token);
                if ($dimensions && is_array($dimensions)) {
                    // Store dimensions for this property
                    update_option("sentinelpro_dimensions_{$property_id}", $dimensions);
                    
                    // Also update the canonical dimensions option
                    update_option('sentinelpro_canonical_dimensions', array_keys($dimensions));
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Auth handler debug logging for troubleshooting
                        error_log("SentinelPro: Fetched and stored custom dimensions: " . implode(', ', array_keys($dimensions)));
                    }
                }
            }
            
            // Update the analytics events table structure with the configured dimensions
            if (class_exists('SentinelPro_Database_Manager')) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Auth handler debug logging for troubleshooting
                    error_log("SentinelPro: About to update table structure for property {$property_id}");
                }
                $db_manager = SentinelPro_Database_Manager::get_instance();
                $result = $db_manager->update_analytics_events_table_structure($property_id);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Auth handler debug logging for troubleshooting
                    error_log("SentinelPro: Table structure update result: " . ($result ? 'success' : 'failed'));
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Auth handler debug logging for troubleshooting
                    error_log("SentinelPro: Database_Manager class not found");
                }
            }

            $output = ob_get_clean();
            self::log_buffer($output);
            wp_send_json_success([
                'message' => '✅ Credentials validated and saved.' . $timezone_message,
                'clearance' => 'admin',
                'redirect' => admin_url('admin.php?page=sentinelpro-settings'), // 👈 This is the page for logged-in view
            ]);

            return;
        }


        // 🟨 3. All else → Restricted (but still save the settings)
        // Use secure clearance level system
        if (class_exists('SentinelPro_Security_Manager')) {
            SentinelPro_Security_Manager::store_clearance_level($target_user_id, 'restricted');
        } else {
            update_user_meta($target_user_id, 'sentinelpro_clearance_level', 'restricted');
        }
        
        // Even if API validation failed, try to create the table with basic structure
        if (class_exists('SentinelPro_Database_Manager')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Auth handler debug logging for troubleshooting
                error_log("SentinelPro: API validation failed, but attempting to create table with basic structure");
            }
            $db_manager = SentinelPro_Database_Manager::get_instance();
            $db_manager->ensure_analytics_events_table_exists();
        }

        $output = ob_get_clean();
        self::log_buffer($output);
        
        $error_message = '⚠️ Credentials saved but API validation failed. Access restricted.';
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $error_message .= " (API Status: {$status})";
        }
        
        wp_send_json_error([
            'message'   => $error_message,
            'clearance' => 'restricted',
            'redirect'  => admin_url('admin.php?page=sentinelpro-api-input'), // 👈 Redirect to api-input page
        ]);
    }

    public static function is_authenticated(): bool {
        $user_id = get_current_user_id();
        // Use secure clearance level system
        if (class_exists('SentinelPro_Security_Manager')) {
            $level = SentinelPro_Security_Manager::get_clearance_level($user_id);
        } else {
            $level = get_user_meta($user_id, 'sentinelpro_clearance_level', true);
        }
        return in_array($level, ['admin', 'elevated'], true);
    }

    private static function log_buffer(string $buffer): void {
        if (defined('WP_DEBUG') && WP_DEBUG && !empty($buffer)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Auth handler debug logging for troubleshooting
            error_log("❌ Output before JSON: >>>{$buffer}<<<");
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Auth handler debug logging for troubleshooting
            foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $trace) {
                if (isset($trace['file'], $trace['line'])) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Auth handler debug logging for troubleshooting
                    error_log("→ {$trace['file']}:{$trace['line']}");
                }
            }
        }
    }
}
