<?php
/**
 * Plugin Name:       Valserv Analytics for SentinelPro
 * Description:       Connect your WordPress site to SentinelPro Analytics. Includes real-time tracking, post-level metrics, and a privacy-focused dashboard.
 * Version:           1.0.0
 * Author:            Valserv Inc
 * Author URI:        https://valserv.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       valserv-analytics-for-sentinelpro
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'SENTINELPRO_ANALYTICS_VERSION', '1.0.0' );
define( 'SENTINELPRO_ANALYTICS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
// Use plugin_dir_url for compatibility, but we'll override it in the script manager
define( 'SENTINELPRO_ANALYTICS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load dependencies
require_once SENTINELPRO_ANALYTICS_PLUGIN_DIR . 'includes/api.php';
require_once SENTINELPRO_ANALYTICS_PLUGIN_DIR . 'admin/AdminUtils/class-sentinelpro-database-manager.php';
require_once SENTINELPRO_ANALYTICS_PLUGIN_DIR . 'includes/post-metrics-column.php';

// Include AJAX handler
require_once SENTINELPRO_ANALYTICS_PLUGIN_DIR . 'admin/AdminUtils/class-sentinelpro-ajax-handler.php';
require_once SENTINELPRO_ANALYTICS_PLUGIN_DIR . 'admin/AdminUtils/class-sentinelpro-cron-handler.php';
// Removed Server Cron Manager - cron jobs now run silently in background
require_once SENTINELPRO_ANALYTICS_PLUGIN_DIR . 'admin/AdminUtils/class-sentinelpro-universal-cron-manager.php';
require_once SENTINELPRO_ANALYTICS_PLUGIN_DIR . 'admin/AdminUtils/class-sentinelpro-security-manager.php';
require_once SENTINELPRO_ANALYTICS_PLUGIN_DIR . 'admin/AdminUtils/class-sentinelpro-security-config.php';

// Function to clear any existing duplicate cron jobs
function valserv_clear_duplicate_crons() {
    // Clear any cron jobs with similar names that might have been created by other handlers
    $cron_hooks_to_clear = [
        'valserv_daily_data_fetch',
        'valserv_daily_analytics_fetch',
        'valserv_daily_cleanup'
    ];
    
    foreach ($cron_hooks_to_clear as $hook) {
        wp_clear_scheduled_hook($hook);
    }
}

// Admin notice about cron fix
function valserv_cron_fix_notice() {
    // Only show to administrators
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Check if we've already shown the notice
    if (get_option('valserv_cron_fix_notice_shown')) {
        return;
    }
    
    // Check if there are duplicate cron jobs
    $duplicate_crons = [];
    $cron_hooks_to_check = [
        'valserv_daily_data_fetch',
        'valserv_daily_analytics_fetch',
        'valserv_daily_cleanup'
    ];
    
    foreach ($cron_hooks_to_check as $hook) {
        $next_run = wp_next_scheduled($hook);
        if ($next_run) {
            $duplicate_crons[$hook] = $next_run;
        }
    }
    
    if (count($duplicate_crons) > 1) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>Valserv Analytics Cron Fix Required:</strong> Multiple cron jobs detected. This may cause duplicate data collection. ';
        echo '<a href="' . esc_url(admin_url('admin.php?page=valserv-cron-manager')) . '">Click here to fix</a> or ';
        echo '<a href="' . esc_url(admin_url('admin.php?page=valserv-cron-manager&action=force_reset')) . '">force reset all cron jobs</a>.</p>';
        echo '</div>';
        
        // Mark notice as shown
        update_option('valserv_cron_fix_notice_shown', true);
    }
}

// Add admin notice about clearance level fix
add_action('admin_notices', 'valserv_clearance_fix_notice');

function valserv_clearance_fix_notice() {
    // Only show to administrators
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Check if we've already shown the notice
    if (get_option('valserv_clearance_fix_notice_shown')) {
        return;
    }
    
    // Check if current user has clearance level set
    $user_id = get_current_user_id();
    $clearance = get_user_meta($user_id, 'sentinelpro_clearance_level', true);
    
    if (empty($clearance)) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>Valserv Analytics Setup Required:</strong> Your user account needs clearance level configuration. ';
        echo '<a href="' . esc_url(admin_url('admin.php?action=valserv_fix_clearance&_wpnonce=' . wp_create_nonce('valserv_fix_clearance'))) . '">Click here to fix</a> or ';
        echo '<a href="' . esc_url(admin_url('admin.php?page=valserv-diagnostic')) . '">run diagnostics</a>.</p>';
        echo '</div>';
        
        // Mark notice as shown
        update_option('valserv_clearance_fix_notice_shown', true);
    }
    
    // Show success message if clearance was fixed
    if (isset($_GET['clearance_fixed']) && sanitize_text_field(wp_unslash($_GET['clearance_fixed'])) === '1' && 
        isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'clearance_fixed')) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>Valserv Analytics:</strong> Clearance levels have been successfully configured. You should now be able to access the API Input page.</p>';
        echo '</div>';
    }
    
    // Show error message if clearance fix failed
    if (isset($_GET['clearance_fix_failed']) && sanitize_text_field(wp_unslash($_GET['clearance_fix_failed'])) === '1' && 
        isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'clearance_fix_failed')) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>Valserv Analytics:</strong> Failed to configure clearance levels. Please check your permissions or contact support.</p>';
        echo '</div>';
    }
}

// Define the cache clearing tool function before it is hooked
function valserv_clear_metrics_cache() {
    // Check for admin capabilities and cache clearing request
    if (!current_user_can('manage_options') || !isset($_GET['clear_metrics_cache'])) return;
    
    // Verify nonce for security
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'clear_metrics_cache')) {
        return;
    }

    $posts = get_posts(['numberposts' => -1, 'post_type' => 'post', 'fields' => 'ids', 'post_status' => 'publish']);
    foreach ($posts as $post_id) {
        delete_transient("sentinelpro_views_{$post_id}");
        delete_transient("sentinelpro_sessions_{$post_id}");
    }
}

// Add database installation hook
register_activation_hook(__FILE__, 'valserv_activate');

function valserv_activate() {
    // Include the database manager and API client
    require_once SENTINELPRO_ANALYTICS_PLUGIN_DIR . 'admin/AdminUtils/class-sentinelpro-database-manager.php';
    require_once SENTINELPRO_ANALYTICS_PLUGIN_DIR . 'admin/AdminUtils/class-sentinelpro-api-client.php';
    
    // Ensure the analytics events table exists (only creates if missing)
    $db_manager = SentinelPro_Database_Manager::get_instance();
    $db_manager->ensure_analytics_events_table_exists();
    
    // Ensure the posts table exists (only creates if missing)
    $db_manager->ensure_posts_table_exists();
    
    // Install all tables including the posts table
    $db_manager->install_tables();
    
    // Set default options if they don't exist
    if (!get_option('valserv_options')) {
        update_option('valserv_options', [
            'account_name' => '',
            'property_id' => '',
            'api_key' => '',
            'enable_tracking' => false
        ]);
    }
    
    // Clear any existing duplicate cron jobs to prevent multiple executions
    valserv_clear_duplicate_crons();
    
    // Initialize security manager and migrate existing data if needed
    SentinelPro_Security_Manager::init();
    SentinelPro_Security_Manager::migrate_existing_api_key();
    SentinelPro_Security_Manager::migrate_clearance_levels_to_hmac();
}

// Add deactivation hook for cleanup
register_deactivation_hook(__FILE__, 'valserv_deactivate');

function valserv_deactivate() {
    // Clear all cron jobs to prevent them from running after deactivation
    valserv_clear_duplicate_crons();
    
    // Optional: Clean up old data on deactivation
    // Uncomment if you want to clean up data when plugin is deactivated
    /*
    require_once plugin_dir_path(__FILE__) . 'admin/AdminUtils/class-sentinelpro-database-manager.php';
    $db_manager = SentinelPro_Database_Manager::get_instance();
    $db_manager->cleanup_old_data(30); // Keep only last 30 days
    */
}

/**
 * Scroll depth tracking (anonymous users only)
 * Script is enqueued separately to comply with WordPress.org guidelines
 */
function valserv_inline_event_logger() {
    if (is_user_logged_in()) return;
    
    // Enqueue the scroll tracking script
    wp_enqueue_script(
        'valserv-scroll-tracking',
        SENTINELPRO_ANALYTICS_PLUGIN_URL . 'admin/js/scroll-tracking.js',
        array('jquery'),
        SENTINELPRO_ANALYTICS_VERSION,
        true
    );
    
    // Localize script with AJAX URL
    wp_localize_script(
        'valserv-scroll-tracking',
        'valservScrollData',
        array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'action' => 'valserv_log_event',
            'nonce' => wp_create_nonce('valserv_log_event')
        )
    );
}

// Bootstrap all plugin hooks
function valserv_bootstrap_plugin() {
    // Register capabilities immediately
    valserv_register_capabilities();
    
    // Activation hooks
    register_activation_hook(__FILE__, ['SentinelPro_Analytics_Admin', 'plugin_activation']);
    register_activation_hook(__FILE__, 'valserv_set_default_user_clearance');

    // Tracking script injection
    add_action('wp_head', 'valserv_analytics_add_tracking_script');

    // Admin setup and scripts
    if (is_admin()) {
        require_once SENTINELPRO_ANALYTICS_PLUGIN_DIR . 'admin/AdminUtils/class-sentinelpro-user-access-manager.php';
        require_once SENTINELPRO_ANALYTICS_PLUGIN_DIR . 'admin/class-sentinelpro-analytics-admin.php';
        new SentinelPro_Analytics_Admin();

        add_action('admin_init', function () {
            require_once SENTINELPRO_ANALYTICS_PLUGIN_DIR . 'includes/post-metrics-column.php';
            SentinelPro_Post_Metrics_Column::instance();
        });

        add_action('admin_enqueue_scripts', [SentinelPro_Admin_Script_Manager::class, 'enqueue_and_localize_post_totals_script']);
    }

    // Cache clearing tool
    add_action('admin_init', 'valserv_clear_metrics_cache', 20);

    // Scroll depth tracking (anonymous users only)
    add_action('wp_footer', 'valserv_inline_event_logger');

    // AJAX handlers
    add_action('wp_ajax_valserv_log_event', 'valserv_log_event');
    add_action('wp_ajax_nopriv_valserv_log_event', 'valserv_log_event');
    
    // Initialize the universal cron manager (handles all cron functionality)
    SentinelPro_Universal_Cron_Manager::get_instance();
    
    // Initialize security manager (handles secure clearance level management)
    SentinelPro_Security_Manager::init();
    
    // Initialize security configuration
    SentinelPro_Security_Config::init();
    
    // Add admin notice about cron fix
    add_action('admin_notices', 'valserv_cron_fix_notice');

    // Add admin notice about clearance level fix
    add_action('admin_notices', 'valserv_clearance_fix_notice');

    add_action('wp_ajax_sentinelpro_set_access_restricted', function() {
        check_ajax_referer('sentinelpro_nonce', 'nonce');
        $user_id = get_current_user_id();
        $access = [
            'api_input'   => false,
            'dashboard'   => false,
            'user_mgmt'   => false,
            'post_column' => false
        ];
        update_user_meta($user_id, 'sentinelpro_access', $access);
        wp_send_json_success(['access' => $access]);
    });

    // Post metrics background prep
    add_action('init', function () {
        if (!current_user_can('manage_options')) return;
        if (!class_exists('SentinelPro_Post_Metrics_Column')) return;
        $column = SentinelPro_Post_Metrics_Column::instance();
        $posts = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1]);
        foreach ($posts as $post) {
            ob_start();
            $column->render_columns('sentinelpro_views', $post->ID);
            $column->render_columns('sentinelpro_sessions', $post->ID);
            ob_end_clean();
        }
    });
}
add_action('plugins_loaded', 'valserv_bootstrap_plugin');

/**
 * Handle anonymous user tracking events
 * This function processes tracking events from non-logged-in users
 */
function valserv_log_event() {
    // Security: Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'valserv_log_event')) {
        wp_die('Security check failed', 'Forbidden', ['response' => 403]);
    }
    
    // Security: Only allow POST requests
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        wp_die('Method not allowed', 'Method Not Allowed', ['response' => 405]);
    }
    
    // Security: Validate content type for beacon requests
    $content_type = isset($_SERVER['CONTENT_TYPE']) ? sanitize_text_field(wp_unslash($_SERVER['CONTENT_TYPE'])) : '';
    if (strpos($content_type, 'application/json') === false) {
        wp_die('Invalid content type', 'Bad Request', ['response' => 400]);
    }
    
    // Security: Get and validate JSON input
    $input = file_get_contents('php://input');
    if (empty($input)) {
        wp_die('No data received', 'Bad Request', ['response' => 400]);
    }
    
    // Security: Basic validation of input size and format
    if (strlen($input) > 10000) { // Limit to 10KB
        wp_die('Input too large', 'Bad Request', ['response' => 400]);
    }
    
    // Security: Validate JSON format
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_die('Invalid JSON', 'Bad Request', ['response' => 400]);
    }
    
    // Security: Sanitize all data from json_decode
    $data = array_map('sanitize_text_field', $data);
    
    // Security: Validate required fields
    $required_fields = ['type', 'value', 'uuid', 'timestamp', 'propertyId'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            wp_die("Missing required field: " . esc_html($field), 'Bad Request', ['response' => 400]);
        }
    }
    
    // Security: Sanitize and validate data
    $event_type = sanitize_text_field($data['type']);
    $event_value = intval($data['value']);
    $uuid = sanitize_text_field($data['uuid']);
    $timestamp = intval($data['timestamp']);
    $property_id = sanitize_text_field($data['propertyId']);
    
    // Security: Validate event type
    $allowed_types = ['engagedDepth'];
    if (!in_array($event_type, $allowed_types, true)) {
        wp_die('Invalid event type', 'Bad Request', ['response' => 400]);
    }
    
    // Security: Validate value ranges
    if ($event_value < 0 || $event_value > 100) {
        wp_die('Invalid event value', 'Bad Request', ['response' => 400]);
    }
    
    // Security: Validate timestamp (within reasonable range)
    $current_time = time();
    $min_time = $current_time - (24 * 60 * 60); // 24 hours ago
    $max_time = $current_time + (60 * 60); // 1 hour in future
    if ($timestamp < $min_time || $timestamp > $max_time) {
        wp_die('Invalid timestamp', 'Bad Request', ['response' => 400]);
    }
    
    // Security: Validate UUID format (basic check)
    if (strlen($uuid) < 10 || strlen($uuid) > 100) {
        wp_die('Invalid UUID format', 'Bad Request', ['response' => 400]);
    }
    
    // Security: Validate property ID format
    if (strlen($property_id) < 3 || strlen($property_id) > 50) {
        wp_die('Invalid property ID', 'Bad Request', ['response' => 400]);
    }
    
    try {
        // Store the event in database (optional - for analytics)
        global $wpdb;
        $table_name = $wpdb->prefix . 'sentinelpro_analytics_events';
        
        // Use prepared statement for database insertion
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Using $wpdb->insert() which is the correct WordPress method
        $result = $wpdb->insert(
            $table_name,
            [
                'date' => gmdate('Y-m-d', $timestamp),
                'device' => 'unknown',
                'geo' => 'unknown',
                'referrer' => 'unknown',
                'os' => 'unknown',
                'browser' => 'unknown',
                'sessions' => 0,
                'visits' => 0,
                'views' => 0,
                'dimension_uuid' => $uuid,
                'dimension_event_type' => $event_type,
                'dimension_event_value' => $event_value,
                'dimension_property_id' => $property_id
            ],
            [
                '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%d', '%s'
            ]
        );
        
        // Return success response
        wp_send_json_success(['status' => 'logged']);
        
    } catch (Exception $e) {
        // Return success anyway to avoid breaking client-side tracking
        wp_send_json_success(['status' => 'logged']);
    }
}

// Improved tracking script injection
function valserv_analytics_add_tracking_script() {
    if (!is_user_logged_in()) {
        return;
    }
    
    $user_id = get_current_user_id();
    $clearance = get_user_meta($user_id, 'sentinelpro_clearance_level', true);

    // Only load for approved roles
    if (!in_array($clearance, ['admin', 'elevated'], true)) {
        return;
    }

    // Ensure required options exist
    $options = get_option('valserv_options', []);
    
    if (empty($options['enable_tracking']) || empty($options['property_id']) || empty($options['account_name'])) {
        return;
    }

    $property_id  = $options['property_id'];
    $account_name = $options['account_name'];

    // Try to get cached pixel code
    $pixel_option_key = "sentinelpro_pixel_code_{$property_id}";
    $pixel_code = get_option($pixel_option_key);

    // If not cached, request from API
    if (empty($pixel_code) && class_exists('SentinelPro_API_Client')) {
        $api_key = class_exists('SentinelPro_Security_Manager') ? SentinelPro_Security_Manager::get_api_key() : '';

        // Preferred: fetch via pixelCode endpoint (raw script)
        $fetched = SentinelPro_API_Client::request_pixel_code_raw($account_name, $property_id, $api_key);

        // Fallback: fetch via pixel endpoint with dimensions
        if (empty($fetched)) {
            $dimensions = get_option("sentinelpro_dimensions_{$property_id}", []);
            if (!is_array($dimensions)) {
                $dimensions = [];
            }
            $fetched = SentinelPro_API_Client::request_pixel_code($account_name, $property_id, $api_key, $dimensions);
        }

        if (!empty($fetched)) {
            $pixel_code = $fetched;
            update_option($pixel_option_key, $pixel_code);
        } else {
            // Auto-retry: Try to fetch again with a different approach
            sleep(1);
            
            // Try the raw endpoint again
            $retry_fetched = SentinelPro_API_Client::request_pixel_code_raw($account_name, $property_id, $api_key);
            
            if (!empty($retry_fetched)) {
                $pixel_code = $retry_fetched;
                update_option($pixel_option_key, $pixel_code);
            } else {
                // Final fallback: try with a basic request
                $basic_fetched = SentinelPro_API_Client::request_pixel_code($account_name, $property_id, $api_key, []);
                
                if (!empty($basic_fetched)) {
                    $pixel_code = $basic_fetched;
                    update_option($pixel_option_key, $pixel_code);
                }
            }
        }
    }

    // Output the pixel code as provided by the API
    if (!empty($pixel_code)) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pixel code is trusted script from API
        echo $pixel_code;
    }
}

// Enqueue Chart.js for admin pages
function valserv_admin_enqueue_charts($hook) {
    if (strpos($hook, 'valserv') === false) return;
    
    wp_enqueue_script(
        'valserv-chartjs',
        SENTINELPRO_ANALYTICS_PLUGIN_URL . 'assets/external-libs/chart.umd.min.js',
        array(),
        SENTINELPRO_ANALYTICS_VERSION,
        true
    );
}
add_action('admin_enqueue_scripts', 'valserv_admin_enqueue_charts');

// Temporary function to set user clearance for testing
function valserv_set_admin_clearance() {
    if (!is_user_logged_in()) return;
    $user_id = get_current_user_id();
    update_user_meta($user_id, 'sentinelpro_clearance_level', 'admin');
    echo "User clearance set to admin for user ID: " . esc_html($user_id);
}

/**
 * Simple function to check current user's clearance level
 */
function valserv_check_clearance() {
    if (!is_user_logged_in()) {
        echo "User not logged in<br>";
        return;
    }
    
    $user_id = get_current_user_id();
    $user = wp_get_current_user();
    $clearance = get_user_meta($user_id, 'sentinelpro_clearance_level', true);
    
    echo "<h3>Clearance Level Check</h3>";
    echo "<p><strong>User:</strong> " . esc_html($user->user_login) . " (ID: " . esc_html($user_id) . ")</p>";
    echo "<p><strong>Clearance Level:</strong> " . esc_html($clearance ?: 'not set') . "</p>";
    echo "<p><strong>WordPress Role:</strong> " . esc_html(implode(', ', $user->roles)) . "</p>";
    
    // Check if tracking script would inject
    $would_inject = in_array($clearance, ['admin', 'elevated'], true);
    echo "<p><strong>Would Tracking Script Inject:</strong> " . esc_html($would_inject ? 'YES' : 'NO') . "</p>";
    
    if (!$would_inject) {
        echo "<p><strong>Reason:</strong> Clearance level '" . esc_html($clearance) . "' not in approved list (admin/elevated)</p>";
    }
    
    // Check options
    $options = get_option('sentinelpro_options', []);
    echo "<p><strong>Enable Tracking:</strong> " . esc_html(isset($options['enable_tracking']) && $options['enable_tracking'] ? 'YES' : 'NO') . "</p>";
    echo "<p><strong>Property ID:</strong> " . esc_html(isset($options['property_id']) ? $options['property_id'] : 'NOT SET') . "</p>";
    echo "<p><strong>Account Name:</strong> " . esc_html(isset($options['account_name']) ? $options['account_name'] : 'NOT SET') . "</p>";
    
    // Test tracking script injection directly
    echo "<h3>Manual Tracking Script Test</h3>";
    ob_start();
    valserv_analytics_add_tracking_script();
    $output = ob_get_clean();
    
    if (!empty($output)) {
        echo "<p>Tracking script would inject:</p>";
        echo "<pre>" . esc_html($output) . "</pre>";
    } else {
        echo "<p>No tracking script output</p>";
        echo "<p><strong>Debug Info:</strong></p>";
        echo "<ul>";
        echo "<li>User logged in: " . esc_html(is_user_logged_in() ? 'YES' : 'NO') . "</li>";
        echo "<li>Clearance level: " . esc_html($clearance ?: 'not set') . "</li>";
        echo "<li>Clearance approved: " . esc_html(in_array($clearance, ['admin', 'elevated'], true) ? 'YES' : 'NO') . "</li>";
        echo "<li>Enable tracking: " . esc_html(isset($options['enable_tracking']) && $options['enable_tracking'] ? 'YES' : 'NO') . "</li>";
        echo "<li>Property ID set: " . esc_html(!empty($options['property_id']) ? 'YES' : 'NO') . "</li>";
        echo "<li>Account name set: " . esc_html(!empty($options['account_name']) ? 'YES' : 'NO') . "</li>";
        echo "</ul>";
    }
}

/**
 * Test function to manually trigger tracking script injection
 */
function valserv_test_tracking_injection() {
    if (!is_user_logged_in()) {
        echo "User not logged in<br>";
        return;
    }
    
    echo "<h3>Manual Tracking Script Test</h3>";
    
    // Test the tracking script function directly
    ob_start();
    valserv_analytics_add_tracking_script();
    $output = ob_get_clean();
    
    if (!empty($output)) {
        echo "<p>Tracking script would inject:</p>";
        echo "<pre>" . esc_html($output) . "</pre>";
    } else {
        echo "<p>No tracking script output</p>";
        
        // Check what's in the options
        $options = get_option('sentinelpro_options', []);
        echo "<p><strong>Options:</strong></p>";
        echo "<p>Enable tracking: " . esc_html(isset($options['enable_tracking']) && $options['enable_tracking'] ? 'Yes' : 'No') . "</p>";
        echo "<p>Property ID: " . esc_html(isset($options['property_id']) ? $options['property_id'] : 'Not set') . "</p>";
        echo "<p>Account name: " . esc_html(isset($options['account_name']) ? $options['account_name'] : 'Not set') . "</p>";
        
        // Check if API client class exists
        if (class_exists('SentinelPro_API_Client')) {
            echo "<p>SentinelPro_API_Client class exists</p>";
            
            // Test API request manually
            $property_id = $options['property_id'] ?? '';
            $account_name = $options['account_name'] ?? '';
            $api_key = class_exists('SentinelPro_Security_Manager') ? SentinelPro_Security_Manager::get_api_key() : '';
            
            if (!empty($property_id) && !empty($account_name) && !empty($api_key)) {
                echo "<p>Testing API request...</p>";
                
                // Test the raw pixel code request
                $result = SentinelPro_API_Client::request_pixel_code_raw($account_name, $property_id, $api_key);
                
                if ($result !== false) {
                    echo '<p>API request successful! Pixel code length: ' . esc_html(strlen($result)) . ' characters</p>';
                    echo "<p>First 100 characters: " . esc_html(substr($result, 0, 100)) . "...</p>";
                } else {
                    echo "<p>API request failed</p>";
                    echo "<p>Let's check what's in the cache:</p>";
                    
                    // Check cached pixel code
                    $pixel_option_key = "sentinelpro_pixel_code_{$property_id}";
                    $cached_pixel = get_option($pixel_option_key);
                    echo "<p><strong>Cached pixel code:</strong> " . esc_html(!empty($cached_pixel) ? 'Found (' . strlen($cached_pixel) . ' chars)' : 'Not found') . "</p>";
                    
                    if (empty($cached_pixel)) {
                        echo "<p>Attempting to fetch pixel code now...</p>";
                        
                        // Try to fetch again
                        $retry_result = SentinelPro_API_Client::request_pixel_code_raw($account_name, $property_id, $api_key);
                        if ($retry_result !== false) {
                            echo "<p>Retry successful! Pixel code length: " . esc_html(strlen($retry_result)) . " characters</p>";
                            
                            // Cache it
                            update_option($pixel_option_key, $retry_result);
                            echo "<p>Pixel code cached successfully</p>";
                        } else {
                            echo "<p>Retry also failed</p>";
                        }
                    }
                }
            } else {
                echo "<p>Missing required data for API request:</p>";
                echo "<ul>";
                echo "<li>Property ID: " . esc_html($property_id ?: 'NOT SET') . "</li>";
                echo "<li>Account name: " . esc_html($account_name ?: 'NOT SET') . "</li>";
                echo "<li>API Key: " . esc_html($api_key ? 'SET' : 'NOT SET') . "</li>";
                echo "</ul>";
            }
        } else {
            echo "<p>SentinelPro_API_Client class not found</p>";
        }
    }
}

/**
 * Test function to check tracking script status
 */
function valserv_test_tracking_status() {
    if (!is_user_logged_in()) {
        echo "User not logged in<br>";
        return;
    }
    
    $user_id = get_current_user_id();
    $user = wp_get_current_user();
    $clearance = get_user_meta($user_id, 'sentinelpro_clearance_level', true);
    $options = get_option('sentinelpro_options', []);
    
    echo "<h3>Tracking Script Status Check</h3>";
    echo '<p><strong>User:</strong> ' . esc_html($user->user_login) . ' (ID: ' . esc_html($user_id) . ')</p>';
    echo '<p><strong>Clearance Level:</strong> ' . esc_html($clearance) . '</p>';
    echo '<p><strong>Enable Tracking:</strong> ' . esc_html(isset($options['enable_tracking']) && $options['enable_tracking'] ? 'YES' : 'NO') . '</p>';
    echo '<p><strong>Account Name:</strong> ' . esc_html(isset($options['account_name']) ? $options['account_name'] : 'NOT SET') . '</p>';
    echo '<p><strong>Property ID:</strong> ' . esc_html(isset($options['property_id']) ? $options['property_id'] : 'NOT SET') . '</p>';
    echo '<p><strong>API Key:</strong> ' . esc_html(isset($options['api_key']) ? 'SET' : 'NOT SET') . '</p>';
    
    // Check if tracking script would inject
    $would_inject = true;
    $reasons = [];
    
    if (!in_array($clearance, ['admin', 'elevated'], true)) {
        $would_inject = false;
        $reasons[] = "User clearance '{$clearance}' not in approved list (admin/elevated)";
    }
    
    if (empty($options['enable_tracking'])) {
        $would_inject = false;
        $reasons[] = "Tracking not enabled";
    }
    
    if (empty($options['property_id'])) {
        $would_inject = false;
        $reasons[] = "Property ID not set";
    }
    
    if (empty($options['account_name'])) {
        $would_inject = false;
        $reasons[] = "Account name not set";
    }
    
    echo "<p><strong>Would Tracking Script Inject:</strong> " . esc_html($would_inject ? 'YES' : 'NO') . "</p>";
    
    if (!$would_inject) {
        echo "<p><strong>Reasons:</strong></p><ul>";
        foreach ($reasons as $reason) {
            echo "<li>" . esc_html($reason) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>All conditions met for tracking script injection</p>";
        
        // Try to get pixel code
        $property_id = $options['property_id'];
        $pixel_option_key = "sentinelpro_pixel_code_{$property_id}";
        $pixel_code = get_option($pixel_option_key);
        
        if (!empty($pixel_code)) {
            echo "<p>Pixel code found in cache</p>";
        } else {
            echo "<p>No pixel code in cache - would need to fetch from API</p>";
        }
    }
    
    // Add fix button
    if (!$would_inject && $clearance !== 'admin') {
        echo "<p><button onclick='upgradeClearance()' class='button button-primary'>Upgrade to Admin Clearance</button></p>";
    }
}

/**
 * Automatically check and correct clearance levels based on API credentials
 */
function valserv_auto_correct_clearance_levels() {
    // Only run for administrators
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $user_id = get_current_user_id();
    $current_clearance = get_user_meta($user_id, 'sentinelpro_clearance_level', true);
    
    // Check if API credentials are configured
    if (class_exists('SentinelPro_User_Access_Manager')) {
        $credentials_configured = SentinelPro_User_Access_Manager::are_api_credentials_configured();
        
        // If no credentials configured but user has admin clearance, force to restricted
        if (!$credentials_configured && $current_clearance === 'admin') {
            if (class_exists('SentinelPro_Security_Manager')) {
                SentinelPro_Security_Manager::store_clearance_level($user_id, 'restricted');
            } else {
                update_user_meta($user_id, 'sentinelpro_clearance_level', 'restricted');
            }
        }
        // If credentials are configured but user has restricted clearance and is admin, upgrade to admin
        elseif ($credentials_configured && $current_clearance === 'restricted' && current_user_can('manage_options')) {
            if (class_exists('SentinelPro_Security_Manager')) {
                SentinelPro_Security_Manager::store_clearance_level($user_id, 'admin');
            } else {
                update_user_meta($user_id, 'sentinelpro_clearance_level', 'admin');
            }
        }
    }
}

// Run clearance correction on admin pages
add_action('admin_init', 'valserv_auto_correct_clearance_levels');

// Also run clearance correction when options are updated
add_action('update_option_sentinelpro_options', function($old_value, $new_value) {
    // Check if API credentials were just configured
    $old_credentials = !empty($old_value['account_name']) && !empty($old_value['property_id']);
    $new_credentials = !empty($new_value['account_name']) && !empty($new_value['property_id']);
    
    if (!$old_credentials && $new_credentials) {
        // API credentials were just configured, upgrade current user to admin
        $user_id = get_current_user_id();
        if (current_user_can('manage_options')) {
            if (class_exists('SentinelPro_Security_Manager')) {
                SentinelPro_Security_Manager::store_clearance_level($user_id, 'admin');
            } else {
                update_user_meta($user_id, 'sentinelpro_clearance_level', 'admin');
            }
        }
    }
}, 10, 2);

/**
 * Register custom capabilities for SentinelPro
 */
function valserv_register_capabilities() {
    // Get the administrator role
    $admin_role = get_role('administrator');
    
    if ($admin_role) {
        // Add custom capabilities to administrator role
        $admin_role->add_cap('sentinelpro_access');
        $admin_role->add_cap('sentinelpro_manage_analytics');
        $admin_role->add_cap('sentinelpro_view_dashboard');
        $admin_role->add_cap('sentinelpro_view_users');
    }
    
    // Also add to editor role for testing
    $editor_role = get_role('editor');
    if ($editor_role) {
        $editor_role->add_cap('sentinelpro_access');
        $editor_role->add_cap('sentinelpro_view_dashboard');
    }
}

// Register capabilities on plugin activation
register_activation_hook(__FILE__, 'valserv_register_capabilities');

/**
 * Set default user clearance on plugin activation
 */
function valserv_set_default_user_clearance() {
    // Get all users
    $users = get_users(['role__in' => ['administrator', 'editor', 'author', 'contributor', 'subscriber']]);
    
    foreach ($users as $user) {
        // Check if user already has a clearance level
        $current_clearance = get_user_meta($user->ID, 'sentinelpro_clearance_level', true);
        
        if (empty($current_clearance)) {
            // Set default clearance based on user role
            $default_clearance = 'restricted'; // Default for most users
            
            if ($user->has_cap('manage_options')) {
                $default_clearance = 'admin'; // Administrators get admin clearance
            } elseif ($user->has_cap('edit_posts')) {
                $default_clearance = 'elevated'; // Editors and authors get elevated clearance
            }
            
            // Use secure clearance level system if available
            if (class_exists('SentinelPro_Security_Manager')) {
                SentinelPro_Security_Manager::store_clearance_level($user->ID, $default_clearance);
            } else {
                update_user_meta($user->ID, 'sentinelpro_clearance_level', $default_clearance);
            }
        }
    }
    
    // Ensure superuser is set if not already set
    $superuser_id = (int) get_option('sentinelpro_superuser_id');
    if (!$superuser_id || !get_user_by('ID', $superuser_id)) {
        // Find the first administrator
        $admins = get_users(['role' => 'administrator', 'number' => 1]);
        if (!empty($admins)) {
            $admin = $admins[0];
            update_option('sentinelpro_superuser_id', $admin->ID);
            
            // Set admin clearance for superuser
            if (class_exists('SentinelPro_Security_Manager')) {
                SentinelPro_Security_Manager::store_clearance_level($admin->ID, 'admin');
            } else {
                update_user_meta($admin->ID, 'sentinelpro_clearance_level', 'admin');
            }
        }
    }
}

/**
 * Manual function to fix clearance levels for existing installations
 * Can be called via admin action or directly
 */
function valserv_fix_clearance_levels() {
    if (!current_user_can('manage_options')) {
        return false;
    }
    
    // Set default clearance levels
    valserv_set_default_user_clearance();
    
    // Force refresh of menu
    delete_transient('sentinelpro_menu_cache');
    
    return true;
}

// Add admin action to fix clearance levels
add_action('admin_action_valserv_fix_clearance', function() {
    // Check nonce for security
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'valserv_fix_clearance')) {
        wp_die('Security check failed');
    }
    
    if (valserv_fix_clearance_levels()) {
        wp_redirect(admin_url('admin.php?page=sentinelpro-settings&clearance_fixed=1'));
    } else {
        wp_redirect(admin_url('admin.php?page=sentinelpro-settings&clearance_fix_failed=1'));
    }
    exit;
});

// Auto-fetch pixel code when plugin loads to ensure it's available
add_action('init', 'valserv_auto_fetch_pixel_code');

/**
 * Automatically fetch pixel code when plugin loads
 */
function valserv_auto_fetch_pixel_code() {
    // Only run for logged-in users
    if (!is_user_logged_in()) {
        return;
    }
    
    // Check if we need to fetch pixel code
    $options = get_option('sentinelpro_options', []);
    if (empty($options['enable_tracking']) || empty($options['property_id']) || empty($options['account_name'])) {
        return;
    }
    
    $property_id = $options['property_id'];
    $pixel_option_key = "sentinelpro_pixel_code_{$property_id}";
    $cached_pixel = get_option($pixel_option_key);
    
    // If no cached pixel code, try to fetch it
    if (empty($cached_pixel) && class_exists('SentinelPro_API_Client')) {
        $account_name = $options['account_name'];
        $api_key = class_exists('SentinelPro_Security_Manager') ? SentinelPro_Security_Manager::get_api_key() : '';
        
        if (!empty($api_key)) {
            // Try to fetch pixel code
            $fetched = SentinelPro_API_Client::request_pixel_code_raw($account_name, $property_id, $api_key);
            
            if (!empty($fetched)) {
                update_option($pixel_option_key, $fetched);
            }
        }
    }
}

/**
 * Quick fix function for API Input page access
 * Call this function to immediately fix clearance levels and access
 */
function valserv_quick_fix_api_input_access() {
    if (!current_user_can('manage_options')) {
        echo "Insufficient permissions<br>";
        return;
    }
    
    echo "Running quick fix for API Input access...<br>";
    
    // Set default clearance levels
    valserv_set_default_user_clearance();
    
    // Force refresh any cached data
    delete_transient('sentinelpro_menu_cache');
    
    echo "Quick fix completed! Please refresh the page and check if the API Input page is now visible.<br>";
    echo "If the issue persists, please run the diagnostic function: sentinelpro_debug_api_input_access()<br>";
}

// Enqueue admin diagnostic functions
add_action('admin_enqueue_scripts', function($hook) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Only enqueue on admin pages
    if (strpos($hook, 'valserv') !== false || strpos($hook, 'sentinelpro') !== false) {
        wp_enqueue_script(
            'valserv-admin-functions',
            SENTINELPRO_ANALYTICS_PLUGIN_URL . 'admin/js/admin-functions.js',
            array('jquery'),
            SENTINELPRO_ANALYTICS_VERSION,
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script(
            'valserv-admin-functions',
            'valservAdminData',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'statusNonce' => wp_create_nonce('sentinelpro_status_nonce'),
                'clearanceNonce' => wp_create_nonce('sentinelpro_clearance_nonce'),
                'testInjectionNonce' => wp_create_nonce('sentinelpro_test_injection_nonce'),
                'credentialsNonce' => wp_create_nonce('sentinelpro_credentials_nonce')
            )
        );
    }
});

// Add AJAX handlers for the JavaScript functions
add_action('wp_ajax_sentinelpro_check_status', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    if (!check_ajax_referer('sentinelpro_status_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $user_id = get_current_user_id();
    $user = wp_get_current_user();
    
    $status = [
        'user_id' => $user_id,
        'username' => $user->user_login,
        'roles' => $user->roles,
        'clearance_level' => get_user_meta($user_id, 'sentinelpro_clearance_level', true) ?: 'not set',
        'api_credentials_configured' => false,
        'access_permissions' => [],
        'superuser_id' => (int) get_option('sentinelpro_superuser_id'),
        'is_superuser' => false,
        'capabilities' => []
    ];
    
    // Check API credentials
    if (class_exists('SentinelPro_User_Access_Manager')) {
        $status['api_credentials_configured'] = SentinelPro_User_Access_Manager::are_api_credentials_configured();
        
        // Check access to different pages
        $pages = ['api_input', 'dashboard', 'user_mgmt', 'post_column'];
        foreach ($pages as $page) {
            $status['access_permissions'][$page] = SentinelPro_User_Access_Manager::user_has_access($page, $user_id);
        }
    }
    
    // Check superuser status
    $status['is_superuser'] = ($user_id === $status['superuser_id']);
    
    // Check capabilities
    $capabilities = ['manage_options', 'read', 'sentinelpro_access', 'sentinelpro_view_dashboard', 'sentinelpro_view_users'];
    foreach ($capabilities as $cap) {
        $status['capabilities'][$cap] = current_user_can($cap);
    }
    
    wp_send_json_success($status);
});

add_action('wp_ajax_sentinelpro_reset_clearance', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    if (!check_ajax_referer('sentinelpro_clearance_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $clearance = sanitize_text_field(wp_unslash($_POST['clearance'] ?? 'restricted'));
    
    if (!in_array($clearance, ['admin', 'elevated', 'restricted'], true)) {
        wp_send_json_error('Invalid clearance level');
        return;
    }
    
    $user_id = get_current_user_id();
    
    // Set clearance level
    if (class_exists('SentinelPro_Security_Manager')) {
        SentinelPro_Security_Manager::store_clearance_level($user_id, $clearance);
    } else {
        update_user_meta($user_id, 'sentinelpro_clearance_level', $clearance);
    }
    
    wp_send_json_success("Clearance level set to {$clearance}");
});

add_action('wp_ajax_sentinelpro_check_credentials_and_upgrade', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    if (!check_ajax_referer('sentinelpro_credentials_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $user_id = get_current_user_id();
    $current_clearance = get_user_meta($user_id, 'sentinelpro_clearance_level', true);
    
    // Check if API credentials are configured
    $credentials_configured = false;
    if (class_exists('SentinelPro_User_Access_Manager')) {
        $credentials_configured = SentinelPro_User_Access_Manager::are_api_credentials_configured();
    }
    
    $upgraded = false;
    
    // If credentials are configured and user has restricted clearance, upgrade to admin
    if ($credentials_configured && $current_clearance === 'restricted') {
        if (class_exists('SentinelPro_Security_Manager')) {
            SentinelPro_Security_Manager::store_clearance_level($user_id, 'admin');
        } else {
            update_user_meta($user_id, 'sentinelpro_clearance_level', 'admin');
        }
        
        $upgraded = true;
    }
    
    wp_send_json_success([
        'credentials_configured' => $credentials_configured,
        'current_clearance' => $current_clearance,
        'upgraded' => $upgraded,
        'message' => $upgraded ? 'Clearance upgraded to admin' : 'No upgrade needed'
    ]);
});

add_action('wp_ajax_sentinelpro_test_tracking', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    if (!check_ajax_referer('sentinelpro_test_tracking_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    ob_start();
    valserv_test_tracking_status();
    $output = ob_get_clean();
    
    wp_send_json_success($output);
});

add_action('wp_ajax_sentinelpro_test_injection', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    if (!check_ajax_referer('sentinelpro_test_injection_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    ob_start();
    valserv_test_tracking_injection();
    $output = ob_get_clean();
    
    wp_send_json_success($output);
});

add_action('wp_ajax_sentinelpro_check_clearance', function() {
    if (!current_user_can('read')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    if (!check_ajax_referer('sentinelpro_check_clearance_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    ob_start();
    valserv_check_clearance();
    $output = ob_get_clean();
    
    wp_send_json_success($output);
});

add_action('wp_ajax_sentinelpro_set_clearance_from_js', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    if (!check_ajax_referer('sentinelpro_clearance_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $clearance = sanitize_text_field(wp_unslash($_POST['clearance'] ?? 'restricted'));
    
    if (!in_array($clearance, ['admin', 'elevated', 'restricted'], true)) {
        wp_send_json_error('Invalid clearance level');
        return;
    }
    
    $user_id = get_current_user_id();
    
    // Set clearance level
    if (class_exists('SentinelPro_Security_Manager')) {
        SentinelPro_Security_Manager::store_clearance_level($user_id, $clearance);
    } else {
        update_user_meta($user_id, 'sentinelpro_clearance_level', $clearance);
    }
    
    // Clearance level updated
    
    wp_send_json_success([
        'message' => "Clearance level set to {$clearance}",
        'clearance' => $clearance,
        'user_id' => $user_id
    ]);
});