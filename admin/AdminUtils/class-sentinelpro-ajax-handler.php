<?php

if ( ! defined('ABSPATH') ) { exit; }

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- This file uses $wpdb->prepare() correctly throughout

/**
 * SentinelPro AJAX Handler
 * Handles AJAX requests for database operations
 */

class SentinelPro_Ajax_Handler {
    
    /**
     * Check user capability and send error if insufficient permissions
     */
    private function must_can($cap) {
        // Use standard WordPress capabilities that are more likely to exist
        if (!current_user_can($cap)) {
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
        }
    }
    
    public function __construct() {
        add_action('wp_ajax_sentinelpro_get_cache', [$this, 'get_cache']);
        add_action('wp_ajax_sentinelpro_set_cache', [$this, 'set_cache']);
        add_action('wp_ajax_sentinelpro_clear_cache', [$this, 'clear_cache']);
        add_action('wp_ajax_sentinelpro_clear_all_cache', [$this, 'clear_all_cache']);
        add_action('wp_ajax_sentinelpro_find_overlapping_cache', [$this, 'find_overlapping_cache']);
        add_action('wp_ajax_sentinelpro_find_all_overlapping_ranges', [$this, 'find_all_overlapping_ranges']);
        add_action('wp_ajax_sentinelpro_merge_and_replace_ranges', [$this, 'merge_and_replace_ranges']);
        add_action('wp_ajax_sentinelpro_has_fresh_cache', [$this, 'has_fresh_cache']);
        add_action('wp_ajax_sentinelpro_find_superset_cache', [$this, 'find_superset_cache']);
        add_action('wp_ajax_sentinelpro_cleanup_expired', [$this, 'cleanup_expired']);
        add_action('wp_ajax_sentinelpro_log_request', [$this, 'log_request']);
        add_action('wp_ajax_sentinelpro_save_user_session', [$this, 'save_user_session']);
        add_action('wp_ajax_sentinelpro_get_user_session', [$this, 'get_user_session']);
        add_action('wp_ajax_sentinelpro_save_setting', [$this, 'save_setting']);
        add_action('wp_ajax_sentinelpro_get_setting', [$this, 'get_setting']);
        add_action('wp_ajax_valserv_fetch_database_data', [$this, 'fetch_database_data']);
        add_action('wp_ajax_sentinelpro_refresh_dimensions_configuration', [$this, 'refresh_dimensions_configuration']);
        add_action('wp_ajax_sentinelpro_get_oldest_date', [$this, 'get_oldest_date']);
        add_action('wp_ajax_sentinelpro_reschedule_cron_4am', [$this, 'reschedule_cron_4am']);
    }
    
    /**
     * Get cached data (deprecated - now using direct database queries)
     */
    public function get_cache() {
        $this->must_can('read');
        check_ajax_referer('sentinelpro_nonce', 'nonce');
        
        wp_send_json_error(['message' => 'Cache functionality deprecated. Use fetch_database_data instead.']);
    }
    
    /**
     * Set cached data (deprecated - now using direct database queries)
     */
    public function set_cache() {
        $this->must_can('read');
        check_ajax_referer('sentinelpro_nonce', 'nonce');
        
        wp_send_json_error(['message' => 'Cache functionality deprecated. Use fetch_database_data instead.']);
    }
    
    /**
     * Clear cache (deprecated - now using direct database queries)
     */
    public function clear_cache() {
        $this->must_can('read');
        check_ajax_referer('sentinelpro_nonce', 'nonce');
        
        wp_send_json_error(['message' => 'Cache functionality deprecated. Use fetch_database_data instead.']);
    }
    
    /**
     * Log analytics request
     */
    public function log_request() {
        $this->must_can('read');
        check_ajax_referer('sentinelpro_nonce', 'nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'sentinelpro_analytics_requests';
        
        $user_id = get_current_user_id();
        $request_type = sanitize_text_field(wp_unslash($_POST['request_type'] ?? ''));
        $metric = sanitize_text_field(wp_unslash($_POST['metric'] ?? ''));
        $granularity = sanitize_text_field(wp_unslash($_POST['granularity'] ?? ''));
        $start_date = sanitize_text_field(wp_unslash($_POST['start_date'] ?? ''));
        $end_date = sanitize_text_field(wp_unslash($_POST['end_date'] ?? ''));
        $dimensions = sanitize_text_field(wp_unslash($_POST['dimensions'] ?? ''));
        $filters = sanitize_text_field(wp_unslash($_POST['filters'] ?? ''));
        $post_id = absint($_POST['post_id'] ?? 0);
        $response_time_ms = absint($_POST['response_time_ms'] ?? 0);
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Input sanitization handled by rest_sanitize_boolean
        $cache_hit = rest_sanitize_boolean($_POST['cache_hit'] ?? false);
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Insert into custom analytics cache table
        $result = $wpdb->insert(
            $table_name,
            [
                'user_id' => $user_id,
                'request_type' => $request_type,
                'metric' => $metric,
                'granularity' => $granularity,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'dimensions' => $dimensions,
                'filters' => $filters,
                'post_id' => $post_id,
                'response_time_ms' => $response_time_ms,
                'cache_hit' => $cache_hit
            ],
            [
                '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d'
            ]
        );
        
        wp_send_json_success($result);
    }
    
    /**
     * Save user session
     */
    public function save_user_session() {
        $this->must_can('read');
        check_ajax_referer('sentinelpro_nonce', 'nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'sentinelpro_analytics_user_sessions';
        
        $user_id = get_current_user_id();
        $session_id = sanitize_text_field(wp_unslash($_POST['session_id'] ?? ''));
        $dashboard_state = sanitize_textarea_field(wp_unslash($_POST['dashboard_state'] ?? ''));
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Replace user session data in custom table
        $result = $wpdb->replace(
            $table_name,
            [
                'user_id' => $user_id,
                'session_id' => $session_id,
                'dashboard_state' => $dashboard_state,
                'last_activity' => current_time('mysql')
            ],
            [
                '%d', '%s', '%s', '%s'
            ]
        );
        
        wp_send_json_success($result);
    }
    
    /**
     * Get user session
     */
    public function get_user_session() {
        $this->must_can('read');
        check_ajax_referer('sentinelpro_nonce', 'nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'sentinelpro_analytics_user_sessions';
        
        $user_id = get_current_user_id();
        $session_id = sanitize_text_field(wp_unslash($_POST['session_id'] ?? ''));
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Get user session from custom table, table name is validated
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE user_id = %d AND session_id = %s",
                $user_id, $session_id
            )
        );
        
        wp_send_json_success($result);
    }
    
    /**
     * Save user setting
     */
    public function save_setting() {
        $this->must_can('read');
        check_ajax_referer('sentinelpro_nonce', 'nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'sentinelpro_analytics_settings';
        
        $user_id = get_current_user_id();
        $setting_key = sanitize_text_field(wp_unslash($_POST['setting_key'] ?? ''));
        $setting_value = sanitize_textarea_field(wp_unslash($_POST['setting_value'] ?? ''));
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Replace user setting in custom table
        $result = $wpdb->replace(
            $table_name,
            [
                'user_id' => $user_id,
                'setting_key' => $setting_key,
                'setting_value' => $setting_value,
                'updated_at' => current_time('mysql')
            ],
            [
                '%d', '%s', '%s', '%s'
            ]
        );
        
        wp_send_json_success($result);
    }
    
    /**
     * Get user setting
     */
    public function get_setting() {
        $this->must_can('read');
        check_ajax_referer('sentinelpro_nonce', 'nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'sentinelpro_analytics_settings';
        
        $user_id = get_current_user_id();
        $setting_key = sanitize_text_field(wp_unslash($_POST['setting_key'] ?? ''));
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Get user setting from custom table, table name is validated
        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT setting_value FROM {$table_name} WHERE user_id = %d AND setting_key = %s",
                $user_id, $setting_key
            )
        );
        
        wp_send_json_success($result);
    }

    public function clear_all_cache() {
        $this->must_can('read');
        check_ajax_referer('sentinelpro_nonce', 'nonce');
        
        wp_send_json_error(['message' => 'Cache functionality deprecated. Use fetch_database_data instead.']);
    }

    public function find_overlapping_cache() {
        $this->must_can('read');
        check_ajax_referer('sentinelpro_nonce', 'nonce');
        
        wp_send_json_error(['message' => 'Cache functionality deprecated. Use fetch_database_data instead.']);
    }

    public function find_all_overlapping_ranges() {
        $this->must_can('read');
        check_ajax_referer('sentinelpro_nonce', 'nonce');
        
        wp_send_json_error(['message' => 'Cache functionality deprecated. Use fetch_database_data instead.']);
    }

    public function merge_and_replace_ranges() {
        $this->must_can('read');
        check_ajax_referer('sentinelpro_nonce', 'nonce');
        
        wp_send_json_error(['message' => 'Cache functionality deprecated. Use fetch_database_data instead.']);
    }

    public function has_fresh_cache() {
        $this->must_can('read');
        check_ajax_referer('sentinelpro_nonce', 'nonce');
        
        wp_send_json_error(['message' => 'Cache functionality deprecated. Use fetch_database_data instead.']);
    }

    public function find_superset_cache() {
        $this->must_can('read');
        check_ajax_referer('sentinelpro_nonce', 'nonce');
        
        wp_send_json_error(['message' => 'Cache functionality deprecated. Use fetch_database_data instead.']);
    }

    public function cleanup_expired() {
        $this->must_can('read');
        check_ajax_referer('sentinelpro_nonce', 'nonce');
        
        wp_send_json_error(['message' => 'Cache functionality deprecated. Use fetch_database_data instead.']);
    }

    /**
     * AJAX handler for refreshing dimensions configuration
     */
    public static function refresh_dimensions_configuration() {
        check_ajax_referer('sentinelpro_admin_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
        }
        
        try {
            // Get database manager instance
            $db_manager = new SentinelPro_Database_Manager();
            
            // Refresh dimensions configuration
            $dimensions = $db_manager->refresh_dimensions_configuration();
            
            if ($dimensions === false) {
                wp_send_json_error(['message' => 'No custom dimensions found in database table']);
            }
            
            wp_send_json_success([
                'message' => 'Dimensions configuration refreshed successfully',
                'dimensions' => $dimensions,
                'count' => count($dimensions)
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error refreshing dimensions: ' . $e->getMessage()]);
        }
    }

    /**
     * Fetch data from the analytics_data table
     */
    public function fetch_database_data() {
        $this->must_can('read');
        
        // Check HTTP method
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] !== 'GET') {
            wp_send_json_error('Invalid method', 405);
        }
        
        // Verify nonce for security
        $nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'sentinelpro_nonce')) {
            wp_send_json_error('Security check failed - nonce invalid or missing', 400);
        }
        
        // Debug: Log sanitized parameters (only in debug mode)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $sanitized_params = array_map('sanitize_text_field', $_GET);
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
            error_log("SentinelPro: fetch_database_data called with parameters: " . json_encode($sanitized_params));
        }
        
        try {
            global $wpdb;
            
            // Sanitize and validate input parameters
            $start_date = sanitize_text_field(wp_unslash($_GET['start_date'] ?? ''));
            $end_date = sanitize_text_field(wp_unslash($_GET['end_date'] ?? ''));
            $metric = sanitize_text_field(wp_unslash($_GET['metric'] ?? 'all'));
            
            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
                wp_send_json_error('Invalid date format. Expected YYYY-MM-DD', 400);
            }
            
            // Validate date range (safety cap)
            $max_days = 400;
            if ((strtotime($end_date) - strtotime($start_date)) / DAY_IN_SECONDS > $max_days) {
                wp_send_json_error('Date range too large', 400);
            }
            
            // Convert 'traffic' or 'sessions' to 'all' since these mean all metrics for the dashboard
            if ($metric === 'traffic' || $metric === 'sessions') {
                $metric = 'all';
            }
            
            // Get all possible filters
            $device = sanitize_text_field(wp_unslash($_GET['device'] ?? ''));
            $geo = sanitize_text_field(wp_unslash($_GET['geo'] ?? ''));
            $referrer = sanitize_text_field(wp_unslash($_GET['referrer'] ?? ''));
            $os = sanitize_text_field(wp_unslash($_GET['os'] ?? ''));
            $browser = sanitize_text_field(wp_unslash($_GET['browser'] ?? ''));
            $post_id = absint($_GET['post_id'] ?? 0);
            
            // Get custom dimension filters and their modes
            $custom_dimension_filters = [];
            $custom_dimension_modes = [];
            foreach ($_GET as $key => $value) {
                // Skip standard filters and other parameters
                if (in_array($key, ['action', 'start_date', 'end_date', 'metric', 'granularity', 'device', 'geo', 'referrer', 'os', 'browser', 'post_id', 'dimensions', 'group_by'])) {
                    continue;
                }
                
                // Store mode parameters (like contentType_mode)
                if (strpos($key, '_mode') !== false) {
                    $dimension_name = str_replace('_mode', '', $key);
                    $custom_dimension_modes[$dimension_name] = sanitize_text_field(wp_unslash($value));
                    continue;
                }
                
                // This is a custom dimension filter
                $custom_dimension_filters[$key] = sanitize_text_field(wp_unslash($value));
            }
            
            // Debug: Log sanitized parameters (only in debug mode)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $sanitized_get = array_map('sanitize_text_field', $_GET);
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
                error_log("SentinelPro: All GET parameters: " . json_encode($sanitized_get));
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
                error_log("SentinelPro: Custom dimension filters: " . json_encode($custom_dimension_filters));
            }
            
            if (!$start_date || !$end_date) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
                    error_log("SentinelPro: Missing required parameters - start_date: '$start_date', end_date: '$end_date'");
                }
                wp_send_json_error('Start date and end date are required');
                return;
            }
            
            // Use the analytics events table
            $table_name = $wpdb->prefix . 'sentinelpro_analytics_events';
            
            // Check if table exists
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Check custom analytics table existence
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
            if (!$table_exists) {
                wp_send_json_error('Analytics events table does not exist. Please ensure the database is properly set up.');
                return;
            }
            
            // Check if table has any data at all
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Count records in custom analytics table, table name is validated
            $total_records = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
                error_log('SentinelPro: Total records in analytics table: ' . $total_records);
            }
            
            if ($total_records == 0) {
                wp_send_json_error('Analytics events table is empty. No data available.');
                return;
            }
            
            // Check if table has data for the date range
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Check data existence in custom analytics table, table name is validated
            $data_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE date >= %s AND date <= %s",
                $start_date,
                $end_date
            ));
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
                error_log('SentinelPro: Records in date range ' . $start_date . ' to ' . $end_date . ': ' . $data_exists);
            }
            
            // Debug: Get a sample of data without filters to see what's available
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Debug sample data from custom analytics table, table name is validated
                $sample_data = $wpdb->get_results($wpdb->prepare(
                    "SELECT date, device, geo, referrer, os, browser, sessions, views, visits 
                     FROM $table_name 
                     WHERE date >= %s AND date <= %s 
                     LIMIT 5",
                    $start_date,
                    $end_date
                ), ARRAY_A);
                
                if ($sample_data) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
                    error_log('SentinelPro: Sample data without filters: ' . json_encode($sample_data));
                }
            }
            
            if (!$data_exists || $data_exists == 0) {
                // No data in database, try to fetch from API and store it
                $api_data = $this->fetch_and_store_from_api($start_date, $end_date, $device, $geo, $referrer, $os, $browser);
                if ($api_data) {
                    wp_send_json_success($api_data);
                    return;
                } else {
                    wp_send_json_error('No data available in database or API for the specified date range.');
                    return;
                }
            }
            
            // Build the WHERE clause
            $where_conditions = ['1=1'];
            $where_values = [];
            
            // Date range
            $where_conditions[] = 'date >= %s AND date <= %s';
            $where_values[] = $start_date;
            $where_values[] = $end_date;
            
            // Device filter
            if ($device) {
                $device_array = array_map('trim', explode(',', $device));
                $placeholders = implode(',', array_fill(0, count($device_array), '%s'));
                $where_conditions[] = "device IN ($placeholders)";
                $where_values = array_merge($where_values, $device_array);
                
                // Debug: Log what device values we're looking for
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
                    error_log('SentinelPro: Device filter values: ' . implode(', ', $device_array));
                }
            }
            
            // Debug: Check what device values exist in the database
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic SQL construction for custom analytics table, table name is validated
                $device_check_query = $wpdb->prepare(
                    "SELECT DISTINCT device FROM $table_name WHERE date >= %s AND date <= %s AND device IS NOT NULL AND device != ''",
                    $start_date,
                    $end_date
                );
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Debug device values from custom analytics table, table name is validated
                $available_devices = $wpdb->get_col($device_check_query);
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
                error_log('SentinelPro: Available devices in database: ' . implode(', ', $available_devices));
            }
            
            // Geo filter
            if ($geo) {
                $geo_array = array_map('trim', explode(',', $geo));
                $placeholders = implode(',', array_fill(0, count($geo_array), '%s'));
                $where_conditions[] = "geo IN ($placeholders)";
                $where_values = array_merge($where_values, $geo_array);
            }
            
            // Referrer filter
            if ($referrer) {
                $referrer_array = array_map('trim', explode(',', $referrer));
                $placeholders = implode(',', array_fill(0, count($referrer_array), '%s'));
                $where_conditions[] = "referrer IN ($placeholders)";
                $where_values = array_merge($where_values, $referrer_array);
            }
            
            // OS filter
            if ($os) {
                $os_array = array_map('trim', explode(',', $os));
                $placeholders = implode(',', array_fill(0, count($os_array), '%s'));
                $where_conditions[] = "os IN ($placeholders)";
                $where_values = array_merge($where_values, $os_array);
            }
            
            // Browser filter
            if ($browser) {
                $browser_array = array_map('trim', explode(',', $browser));
                $placeholders = implode(',', array_fill(0, count($browser_array), '%s'));
                $where_conditions[] = "browser IN ($placeholders)";
                $where_values = array_merge($where_values, $browser_array);
            }
            
            // Custom dimension filters
            foreach ($custom_dimension_filters as $dimension => $value) {
                // Convert dimension name to database column name
                // Handle different dimension name formats
                $dimension_lower = strtolower($dimension);
                
                // Map common dimension names to their database column names
                $dimension_mapping = [
                    'contenttype' => 'dimension_contenttype',
                    'contentType' => 'dimension_contenttype',
                    'primarytag' => 'dimension_primarytag',
                    'primaryTag' => 'dimension_primarytag',
                    'primarycategory' => 'dimension_primarycategory',
                    'primaryCategory' => 'dimension_primarycategory',
                    'networkcategory' => 'dimension_networkcategory',
                    'networkCategory' => 'dimension_networkcategory',
                    'publishdate' => 'dimension_publishdate',
                    'publishDate' => 'dimension_publishdate',
                    'adstemplate' => 'dimension_adstemplate',
                    'adsTemplate' => 'dimension_adstemplate',
                    'articletype' => 'dimension_articletype',
                    'articleType' => 'dimension_articletype',
                ];
                
                $column_name = $dimension_mapping[$dimension] ?? 'dimension_' . $dimension_lower;
                
                // Check if the column exists in the table
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Check custom dimension column existence
                $column_exists = $wpdb->get_results($wpdb->prepare(
                    "SHOW COLUMNS FROM $table_name LIKE %s",
                    $column_name
                ));
                
                if ($column_exists) {
                    // Check if this dimension has a mode (contains vs exact)
                    $mode = $custom_dimension_modes[$dimension] ?? 'exact';
                    
                    if ($mode === 'contains') {
                        // Use LIKE for contains search
                        if (is_array($value) || strpos($value, ',') !== false) {
                            // Multiple values (array or comma-separated) - use OR with LIKE
                            $value_array = is_array($value) ? $value : array_map('trim', explode(',', $value));
                            $like_conditions = [];
                            foreach ($value_array as $val) {
                                $like_conditions[] = "$column_name LIKE %s";
                                $where_values[] = '%' . $wpdb->esc_like($val) . '%';
                            }
                            $where_conditions[] = '(' . implode(' OR ', $like_conditions) . ')';
                        } else {
                            // Single value - use LIKE
                            $where_conditions[] = "$column_name LIKE %s";
                            $where_values[] = '%' . $wpdb->esc_like($value) . '%';
                        }
                        
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
                            error_log("SentinelPro: Added custom dimension CONTAINS filter: $column_name LIKE %" . $value . "%");
                        }
                    } else {
                        // Use exact match (default behavior)
                        if (is_array($value) || strpos($value, ',') !== false) {
                            // Multiple values (array or comma-separated)
                            $value_array = is_array($value) ? $value : array_map('trim', explode(',', $value));
                            $placeholders = implode(',', array_fill(0, count($value_array), '%s'));
                            $where_conditions[] = "$column_name IN ($placeholders)";
                            $where_values = array_merge($where_values, $value_array);
                        } else {
                            // Single value
                            $where_conditions[] = "$column_name = %s";
                            $where_values[] = $value;
                        }
                        
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
                            error_log("SentinelPro: Added custom dimension EXACT filter: $column_name = " . (is_array($value) ? implode(',', $value) : $value));
                        }
                    }
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
                        error_log("SentinelPro: Custom dimension column not found: $column_name (original: $dimension)");
                    }
                }
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            // Check if we have device filters
            $has_device_filter = !empty($device);
            
            // Validate and whitelist requested dimension columns
            $requested_dimensions = isset($_GET['dimensions']) 
                ? array_filter(array_map('trim', explode(',', sanitize_text_field(wp_unslash($_GET['dimensions']))))) 
                : [];
            
            // Get valid dimension columns from database
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Get custom dimension columns from analytics table, table name is validated
            $valid_columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name LIKE 'dimension_%'");
            $valid_map = array_fill_keys($valid_columns, true);
            
            $custom_dimension_columns = [];
            foreach ($requested_dimensions as $dimension) {
                if (!empty($dimension) && !in_array($dimension, ['date', 'device', 'geo', 'referrer', 'os', 'browser'])) {
                    // Normalize dimension name to safe column name
                    $dim = strtolower(preg_replace('/[^a-z0-9_]/i', '', $dimension));
                    $column_name = 'dimension_' . $dim;
                    
                    // Only include if column exists in database
                    if (isset($valid_map[$column_name])) {
                        $custom_dimension_columns[] = $column_name;
                    }
                }
            }
            
            if ($has_device_filter) {
                // If device filter is applied, return device-specific data
                $select_columns = "date, device, geo, referrer, os, browser, sessions, views, visits";
                if (!empty($custom_dimension_columns)) {
                    $select_columns .= ", " . implode(", ", $custom_dimension_columns);
                }
                
                // Build query with proper escaping - placeholders are in $where_clause
                $query = "SELECT $select_columns
                         FROM $table_name 
                         WHERE $where_clause 
                         ORDER BY date ASC";
            } else {
                // If no device filter, return aggregated data (excluding bots)
                $select_columns = "date, 
                                 SUM(CASE WHEN LOWER(device) != 'bot' THEN sessions ELSE 0 END) as sessions,
                                 SUM(CASE WHEN LOWER(device) != 'bot' THEN visits ELSE 0 END) as visits,
                                 SUM(CASE WHEN LOWER(device) != 'bot' THEN views ELSE 0 END) as views";
                
                if (!empty($custom_dimension_columns)) {
                    $select_columns .= ", " . implode(", ", $custom_dimension_columns);
                }
                
                $group_by = "date";
                if (!empty($custom_dimension_columns)) {
                    $group_by .= ", " . implode(", ", $custom_dimension_columns);
                }
                
                // Build query with proper escaping - placeholders are in $where_clause
                $query = "SELECT $select_columns
                         FROM $table_name 
                         WHERE $where_clause 
                         GROUP BY $group_by
                         ORDER BY date ASC";
            }
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Query custom analytics table for dashboard data, dynamic SQL construction
            $results = $wpdb->get_results($wpdb->prepare($query, $where_values), ARRAY_A);
            
            if ($results === false) {
                wp_send_json_error('Database query failed: ' . $wpdb->last_error);
                return;
            }
            
            // Log the query for debugging (only in debug mode)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
                error_log('SentinelPro Database Query: ' . $query);
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
                error_log('SentinelPro Query Results Count: ' . count($results));
            }
            
            // Handle case where no results are found
            if (count($results) === 0) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
                    error_log('SentinelPro: No results found for the specified filters');
                }
                
                // Return empty result set instead of error
                wp_send_json_success([]);
                return;
            }
            
            // Transform the data to match expected format
            $transformed_data = [];
            $grouped_by_date = [];
            
            foreach ($results as $row) {
                $date = $row['date'];
                
                if ($has_device_filter) {
                    // Device-specific data - group by date and sum metrics
                    if (!isset($grouped_by_date[$date])) {
                        $grouped_by_date[$date] = [
                            'date' => $date,
                            'sessions' => 0,
                            'visits' => 0,
                            'views' => 0
                        ];
                    }
                    
                    // Only aggregate non-Bot data
                    $device = strtolower($row['device'] ?? '');
                    if ($device !== 'bot') {
                        $grouped_by_date[$date]['sessions'] += intval($row['sessions']);
                        $grouped_by_date[$date]['visits'] += intval($row['visits']);
                        $grouped_by_date[$date]['views'] += intval($row['views']);
                    }
                } else {
                    // Already aggregated data - use as is
                    $grouped_by_date[$date] = [
                        'date' => $date,
                        'sessions' => intval($row['sessions']),
                        'visits' => intval($row['visits']),
                        'views' => intval($row['views'])
                    ];
                }
            }
            
            // Convert grouped data to array format
            $transformed_data = array_values($grouped_by_date);
            
            // Log the final data for debugging (only in debug mode)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
                error_log('SentinelPro Transformed Data Count: ' . count($transformed_data));
                if (count($transformed_data) > 0) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
                    error_log('SentinelPro Sample Data: ' . json_encode($transformed_data[0]));
                }
            }
            
            wp_send_json_success($transformed_data);
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler error logging for troubleshooting
                error_log('SentinelPro Database Error: ' . $e->getMessage());
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler error logging for troubleshooting
                error_log('SentinelPro Database Error Stack: ' . $e->getTraceAsString());
            }
            wp_send_json_error('Error fetching database data. Please try again.');
        }
    }

    /**
     * Fetch data from API and store in database (with pagination support)
     */
    private function fetch_and_store_from_api($start_date, $end_date, $device = '', $geo = '', $referrer = '', $os = '', $browser = '') {
        try {
            // Get API credentials
            $options = get_option('sentinelpro_options', []);
            $api_key = SentinelPro_Security_Manager::get_api_key();
            $property_id = sanitize_text_field($options['property_id'] ?? '');
            $account_name = sanitize_text_field($options['account_name'] ?? '');
            
            // Validate account name to safe subdomain token
            if (!preg_match('/^[a-z0-9-]+$/i', $account_name)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
                    error_log('SentinelPro: Invalid account name for API fallback');
                }
                return false;
            }

            if (empty($api_key) || empty($property_id) || empty($account_name)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
                    error_log('SentinelPro: API credentials missing for fallback fetch');
                }
                return false;
            }

            // Build API request parameters
            $filters = [
                'propertyId' => [
                    'in' => [$property_id]
                ],
                'date' => [
                    'gt' => $start_date,
                    'lt' => $end_date
                ]
            ];

            // Add dimension filters if provided
            if ($device) {
                $filters['device'] = ['in' => array_map('trim', explode(',', $device))];
            }
            if ($geo) {
                $filters['geo'] = ['in' => array_map('trim', explode(',', $geo))];
            }
            if ($referrer) {
                $filters['referrer'] = ['in' => array_map('trim', explode(',', $referrer))];
            }
            if ($os) {
                $filters['os'] = ['in' => array_map('trim', explode(',', $os))];
            }
            if ($browser) {
                $filters['browser'] = ['in' => array_map('trim', explode(',', $browser))];
            }

            $base_query_data = [
                'filters' => $filters,
                'granularity' => 'daily',
                'metrics' => ['sessions', 'visits', 'views'],
                'dimensions' => ['date', 'device', 'geo', 'referrer', 'os', 'browser'],
                'orderBy' => ['date' => 'asc'],
                'pagination' => [
                    'pageSize' => 1000,
                    'pageNumber' => 1
                ]
            ];

            // Fetch all pages of data
            $all_data = [];
            $page_number = 1;
            $total_pages = 1; // Will be updated from first response
            
            do {
                $query_data = $base_query_data;
                $query_data['pagination']['pageNumber'] = $page_number;
                
                $url = "https://{$account_name}.sentinelpro.com/api/v1/traffic/?data=" . rawurlencode(wp_json_encode($query_data, JSON_UNESCAPED_SLASHES));
                
                $response = wp_safe_remote_get($url, [
                    'headers' => [
                        'SENTINEL-API-KEY' => $api_key,
                        'Accept' => 'application/json'
                    ],
                    'timeout' => 30,
                ]);

                if (is_wp_error($response)) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
                    error_log('SentinelPro: API request failed for page ' . $page_number . ': ' . $response->get_error_message());
                    break;
                }

                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (!isset($body['data']) || !is_array($body['data'])) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
                    error_log('SentinelPro: Invalid API response format for page ' . $page_number);
                    break;
                }

                // Get pagination info from first response
                if ($page_number === 1) {
                    $total_pages = isset($body['totalPage']) ? intval($body['totalPage']) : 1;
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
                        error_log('SentinelPro: Total pages to fetch: ' . $total_pages . ', Total records: ' . (isset($body['totalCount']) ? $body['totalCount'] : 'unknown'));
                    }
                }

                $all_data = array_merge($all_data, $body['data']);
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
                    error_log('SentinelPro: Fetched page ' . $page_number . ' with ' . count($body['data']) . ' records');
                }
                
                $page_number++;
                
                // Safety check to prevent infinite loops
                if ($page_number > 20) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
                    error_log('SentinelPro: Safety limit reached, stopping pagination at page 20');
                    break;
                }
                
            } while ($page_number <= $total_pages);

            if (empty($all_data)) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
                error_log('SentinelPro: No data retrieved from API');
                return false;
            }

            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
                error_log('SentinelPro: Total records fetched from API: ' . count($all_data));
            }

            // Store the data in the database
            $this->store_api_data_in_database($all_data);

            // Transform the data to match expected format
            $transformed_data = $this->transform_api_data_to_dashboard_format($all_data);

            return $transformed_data;

        } catch (Exception $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler error logging for troubleshooting
            error_log('SentinelPro: Error in API fallback: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Store API data in the database
     */
    private function store_api_data_in_database($api_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sentinelpro_analytics_events';

        foreach ($api_data as $row) {
            // Store each device record separately to preserve device dimension
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Insert API data into custom analytics table
            $wpdb->insert($table_name, [
                'date' => $row['date'],
                'device' => $row['device'] ?? '',
                'geo' => $row['geo'] ?? '',
                'referrer' => $row['referrer'] ?? '',
                'os' => $row['os'] ?? '',
                'browser' => $row['browser'] ?? '',
                'sessions' => intval($row['sessions'] ?? 0),
                'views' => intval($row['views'] ?? 0),
                'visits' => intval($row['visits'] ?? 0),
                'created_at' => current_time('mysql')
            ], [
                '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s'
            ]);
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler debug logging for troubleshooting
        error_log('SentinelPro: Stored ' . count($api_data) . ' records in database');
    }

    /**
     * Transform API data to dashboard format
     */
    private function transform_api_data_to_dashboard_format($api_data) {
        $grouped_by_date = [];

        foreach ($api_data as $row) {
            $date = $row['date'];

            if (!isset($grouped_by_date[$date])) {
                $grouped_by_date[$date] = [
                    'date' => $date,
                    'sessions' => 0,
                    'visits' => 0,
                    'views' => 0
                ];
            }

            // Only aggregate non-Bot data for dashboard display
            $device = strtolower($row['device'] ?? '');
            if ($device !== 'bot') {
                $grouped_by_date[$date]['sessions'] += intval($row['sessions'] ?? 0);
                $grouped_by_date[$date]['visits'] += intval($row['visits'] ?? 0);
                $grouped_by_date[$date]['views'] += intval($row['views'] ?? 0);
            }
        }

        return array_values($grouped_by_date);
    }

    /**
     * Get the oldest date available in the database
     */
    public function get_oldest_date() {
        $this->must_can('read');
        check_ajax_referer('sentinelpro_nonce', 'nonce');
        
        try {
            global $wpdb;
            $analytics_table = $wpdb->prefix . 'sentinelpro_analytics_events';
            
            // Get the oldest date from analytics events - fixed for DATE column type
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Get oldest date from custom analytics table, table name is validated
            $oldest_date = $wpdb->get_var("
                SELECT MIN(date) 
                FROM {$analytics_table} 
                WHERE date IS NOT NULL 
                AND date != '0000-00-00'
                AND date >= '2020-01-01'
            ");
            
            if (empty($oldest_date)) {
                // If no valid dates found, check if any data exists at all
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Check data existence in custom analytics table, table name is validated
                $total_records = $wpdb->get_var("SELECT COUNT(*) FROM {$analytics_table}");
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Get sample dates from custom analytics table, table name is validated
                $sample_dates = $wpdb->get_results("SELECT DISTINCT date FROM {$analytics_table} LIMIT 5", ARRAY_A);
                
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r -- AJAX handler debug logging for troubleshooting
                error_log("SentinelPro: No valid dates found. Total records: {$total_records}. Sample dates: " . print_r($sample_dates, true));
                
                // Set a reasonable default (30 days ago)
                $oldest_date = gmdate('Y-m-d', strtotime('-30 days'));
                
                wp_send_json_success([
                    'oldest_date' => $oldest_date,
                    'message' => "No valid dates found in database (Total records: {$total_records}). Using default date.",
                    'debug' => [
                        'total_records' => $total_records,
                        'sample_dates' => $sample_dates,
                        'fallback_used' => true
                    ]
                ]);
            } else {
                wp_send_json_success([
                    'oldest_date' => $oldest_date,
                    'message' => 'Oldest date retrieved successfully'
                ]);
            }
            
        } catch (Exception $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler error logging for troubleshooting
            error_log('SentinelPro Get Oldest Date Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => 'Error retrieving oldest date: ' . $e->getMessage(),
                'oldest_date' => gmdate('Y-m-d', strtotime('-30 days')) // Fallback
            ]);
        }
    }
    
    /**
     * Reschedule cron job to 4:00 AM
     */
    public function reschedule_cron_4am() {
        $this->must_can('manage_options');
        check_ajax_referer('sentinelpro_nonce', 'nonce');
        
        try {
            // Check if cron manager exists
            if (!class_exists('SentinelPro_Universal_Cron_Manager')) {
                throw new Exception('Cron manager not available');
            }
            
            // Get the cron manager instance
            $cron_manager = SentinelPro_Universal_Cron_Manager::get_instance();
            
            // Force reschedule to 4:00 AM
            $next_run = $cron_manager->force_reschedule_to_4am();
            
            // Get the updated status
            $status = $cron_manager->get_cron_status();
            
            $response_data = [
                'success' => true,
                'message' => 'Cron job rescheduled to 4:00 AM successfully',
                'next_run' => $status['next_run'],
                'time_until_next' => $status['time_until_next'],
                'timezone' => $status['timezone']
            ];
            
            wp_send_json_success($response_data);
            
        } catch (Exception $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- AJAX handler error logging for troubleshooting
            error_log('SentinelPro Reschedule Cron Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => 'Error rescheduling cron job: ' . $e->getMessage()
            ]);
        }
    }
}

// Initialize AJAX handler automatically
new SentinelPro_Ajax_Handler(); 
