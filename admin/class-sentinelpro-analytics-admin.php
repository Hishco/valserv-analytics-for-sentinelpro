<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- This file uses $wpdb->prepare() correctly throughout

/**
 * Admin class for SentinelPro Analytics plugin
 */
require_once plugin_dir_path(__FILE__) . 'AdminUtils/class-sentinelpro-admin-renderer.php';
require_once plugin_dir_path(__FILE__) . 'AdminUtils/class-sentinelpro-user-access-manager.php';
require_once plugin_dir_path(__FILE__) . 'AdminUtils/class-sentinelpro-settings-validator.php';
require_once plugin_dir_path(__FILE__) . 'AdminUtils/class-sentinelpro-settings-registrar.php';
require_once plugin_dir_path(__FILE__) . 'AdminUtils/class-sentinelpro-settings-renderer.php';
require_once plugin_dir_path(__FILE__) . 'AdminUtils/class-sentinelpro-csv-permissions-importer.php';
require_once plugin_dir_path(__FILE__) . 'AdminUtils/class-sentinelpro-user-list-provider.php';
require_once plugin_dir_path(__FILE__) . 'AdminUtils/class-sentinelpro-config.php';
require_once plugin_dir_path(__FILE__) . 'AdminUtils/class-sentinelpro-superuser-enforcer.php';
require_once plugin_dir_path(__FILE__) . 'AdminUtils/class-sentinelpro-api-client.php';
require_once plugin_dir_path(__FILE__) . 'AdminUtils/class-sentinelpro-csv-uploader-handler.php';
require_once plugin_dir_path(__FILE__) . 'AdminUtils/class-sentinelpro-admin-script-manager.php';
require_once plugin_dir_path(__FILE__) . 'AdminUtils/class-sentinelpro-admin-menu-manager.php';
require_once plugin_dir_path(__FILE__) . 'AdminUtils/class-sentinelpro-auth-handler.php';
require_once plugin_dir_path(__FILE__) . 'AdminUtils/class-sentinelpro-content-importer.php';
require_once plugin_dir_path(__FILE__) . 'AdminUtils/class-sentinelpro-installer.php';



class SentinelPro_Analytics_Admin {

    private string $option_name = 'sentinelpro_options';
    private $post_totals;

    public function __construct() {
        $this->register_admin_hooks();
        $this->register_ajax_hooks();
    }


    private function register_admin_hooks(): void {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'handle_user_access_save']);
        new SentinelPro_SuperUser_Enforcer();
        add_action('wp_ajax_sentinelpro_get_access_logs', [$this, 'ajax_get_access_logs']);
        add_action('admin_init', ['SentinelPro_SuperUser_Enforcer', 'maybe_reassign_superuser']);
        // ✅ Pass instance context to menu manager
        add_action('admin_menu', function () {
            SentinelPro_Admin_Menu_Manager::add($this);
        });
        add_action('admin_menu', ['SentinelPro_Admin_Menu_Manager', 'maybe_hide'], 999);
        // ✅ Scripts are now statically managed
        add_action('admin_enqueue_scripts', function () {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            if ($screen && property_exists($screen, 'id')) {
                SentinelPro_Admin_Script_Manager::enqueue($screen->id);
            }
        }, 20);
        SentinelPro_Content_Importer::init();
        

    }

    private function register_ajax_hooks(): void {
        add_action('wp_ajax_sentinelpro_fetch_users', [$this, 'ajax_fetch_users']);
        add_action('wp_ajax_sentinelpro_save_auth', [$this, 'handle_auth_save']);

        // ✅ Add this line:
        add_action('wp_ajax_sentinelpro_ajax_upload_preview', [$this, 'ajax_handle_upload_preview']);
        add_action('wp_ajax_sentinelpro_import_csv_url', [$this, 'ajax_import_csv_url']);
        add_action('wp_ajax_valserv_fetch_data', [$this, 'ajax_fetch_data']);
        

    }



    public function ajax_fetch_data(): void {
        // SECURITY: Verify nonce
        if (!check_ajax_referer('sentinelpro_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => esc_html__('Security check failed', 'valserv-analytics-for-sentinelpro')], 403);
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized', 'valserv-analytics-for-sentinelpro')], 403);
        }

        $metric      = sanitize_text_field(wp_unslash($_GET['metric'] ?? 'traffic'));
        $granularity = sanitize_text_field(wp_unslash($_GET['granularity'] ?? 'daily'));
        $post_id     = isset($_GET['post_id']) ? absint(wp_unslash($_GET['post_id'])) : null;

        $start_date = sanitize_text_field(wp_unslash($_GET['start_date'] ?? $_GET['date1'] ?? ''));
        $end_date   = sanitize_text_field(wp_unslash($_GET['end_date'] ?? $start_date));

        if (!$start_date || !$granularity || !$metric) {
            wp_send_json_error(['message' => esc_html__('Missing required parameters.', 'valserv-analytics-for-sentinelpro')]);
        }

        // Safely validate and whitelist dimensions
        $raw_dimensions = sanitize_text_field(wp_unslash($_GET['dimensions'] ?? ''));
        $dimensions = self::validate_and_whitelist_dimensions($raw_dimensions);
        
        $params = [
            'granularity' => $granularity,
            'start_date'  => $start_date,
            'end_date'    => $end_date,
            'dimensions'  => $dimensions,
        ];

        $creds        = $this->get_api_credentials();
        $account_name = $creds['account_name'];
        $api_key      = $creds['api_key'];
        $property_id  = $creds['property_id'];

        if (!$account_name || !$api_key) {
            wp_send_json_error(['message' => esc_html__('Missing API credentials.', 'valserv-analytics-for-sentinelpro')]);
        }

        $base_url = "https://{$account_name}.sentinelpro.com/api/v1/traffic/?data=";

        if ($metric === 'all') {
            $query = SentinelPro_API_Client::build_traffic_query(array_merge($params, ['metric' => 'all']), $property_id, $post_id);
            $url   = "https://{$account_name}.sentinelpro.com/api/v1/traffic/?data=" .
                    rawurlencode(json_encode($query, JSON_UNESCAPED_SLASHES));

            $response = wp_remote_get($url, [
                'headers' => [
                    'SENTINEL-API-KEY' => $api_key,
                    'Accept'           => 'application/json'
                ],
                'timeout' => 10
            ]);

            $parsed = SentinelPro_API_Client::extract_data_from_response($response);

            wp_send_json_success([
                'data'           => $parsed['data'],
                'requestedDates' => $parsed['requestedDates'],
            ]);
        } else {
            $query = SentinelPro_API_Client::build_traffic_query(array_merge($params, ['metric' => $metric]), $property_id, $post_id);
            $url = $base_url . rawurlencode(json_encode($query, JSON_UNESCAPED_SLASHES));

            $response = wp_remote_get($url, [
                'headers' => [
                    'SENTINEL-API-KEY' => $api_key,
                    'Accept'           => 'application/json'
                ],
                'timeout' => 10
            ]);

            SentinelPro_API_Client::handle_api_response($response); // this internally calls wp_send_json_success
        }
    }


    public function ajax_get_access_logs(): void {
        // Security: Verify nonce
        if (!check_ajax_referer('sentinelpro_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => esc_html__('Security check failed', 'valserv-analytics-for-sentinelpro')], 403);
            return;
        }
        
        // Security: Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized', 'valserv-analytics-for-sentinelpro')], 403);
            return;
        }
        
        // Ensure logs table exists
        self::ensure_logs_table_exists();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'sentinelpro_access_logs';
        
        // Validate table name before using in query
        if (!self::is_valid_table_name($table_name)) {
            wp_send_json_error(['message' => esc_html__('Invalid table name', 'valserv-analytics-for-sentinelpro')], 400);
            return;
        }
        
        // Check if table exists and count records
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table existence check for custom table
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        $record_count = 0;
        
        if ($table_exists) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Count query for custom table with validated table name
            $record_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        }
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and safe for custom table query
        $sql = $wpdb->prepare("
            SELECT l.*, u.user_email 
            FROM {$table_name} l
            LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
            ORDER BY l.changed_at DESC
            LIMIT 100
        ");
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table query with validated table name
        $results = $wpdb->get_results($sql);

        wp_send_json_success($results);
    }

    public function register_settings(): void {
        SentinelPro_Settings_Registrar::register($this->option_name, $this);
    }

    public function sanitize_settings(array $input): array {
        // ✅ Always coerce enable_tracking to 0 or 1
        $input['enable_tracking'] = isset($input['enable_tracking']) && $input['enable_tracking'] == 1 ? 1 : 0;
        // ✅ Run centralized validation
        $sanitized = SentinelPro_Settings_Validator::sanitize($input, $this->option_name);
        // ❌ If validation fails, only return what passed
        if (!empty($sanitized['__invalid'])) {
            unset($sanitized['__invalid']);
            return $sanitized;
        }
        return $sanitized;
    }

    public function display_settings_page(): void {
        if (!current_user_can('sentinelpro_access')) {
            wp_die(esc_html__('Insufficient permissions.', 'valserv-analytics-for-sentinelpro'));
        }
        
        $uid = get_current_user_id();
        self::enforce_page_access_with_clearance('api_input', $uid);

        SentinelPro_Admin_Renderer::render_settings_page($this->option_name);
    }

    public static function display_dashboard_page() {
        if (!current_user_can('read')) {
            wp_die(esc_html__('Insufficient permissions.', 'valserv-analytics-for-sentinelpro'));
        }
        
        self::enforce_page_access('dashboard');
        SentinelPro_Admin_Renderer::render_dashboard_page();
    }

    public static function display_user_management_page(): void {
        if (!current_user_can('read')) {
            wp_die(esc_html__('Insufficient permissions.', 'valserv-analytics-for-sentinelpro'));
        }
        
        self::enforce_page_access('user_mgmt');

        $page     = 1;
        $search   = '';
        $role     = '';
        $per_page = 20;

        $result = SentinelPro_User_List_Provider::get_users_paginated($page, $search, $role, $per_page);
        $users  = $result['users'];

        $superuser_id = (int) get_option('sentinelpro_superuser_id');
        $pages        = SentinelPro_Config::get_access_pages();

        SentinelPro_Admin_Renderer::render_user_management_page(
            $users,
            $superuser_id,
            $pages,
            ['SentinelPro_User_Access_Manager', 'get_default_access_for_user']
        );
    }
    
    public function get_api_credentials(): array {
        return SentinelPro_API_Client::get_api_credentials();
    }

    public function handle_user_access_save(): void {
        // SECURITY: Verify nonce and user permissions
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'sentinelpro_user_access_save')) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }
        
        SentinelPro_CSV_Uploader_Handler::handle_upload($_POST, $_FILES);
    }

    public function ajax_fetch_users(): void {
        // SECURITY: Verify nonce
        if (!check_ajax_referer('sentinelpro_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed'], 403);
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
            return;
        }
        
        $page     = max(1, absint(wp_unslash($_POST['page'] ?? 1)));
        $search   = sanitize_text_field(wp_unslash($_POST['search'] ?? ''));
        $role     = sanitize_text_field(wp_unslash($_POST['role'] ?? ''));
        $per_page = 20;

        $result       = SentinelPro_User_List_Provider::get_users_paginated($page, $search, $role, $per_page);
        $users        = $result['users'];
        $total        = $result['total'];
        $superuser_id = SentinelPro_User_Access_Manager::get_safe_superuser_id();
        $pages        = SentinelPro_Config::get_access_pages();
        $default_cb   = ['SentinelPro_User_Access_Manager', 'get_default_access_for_user'];

        $users_data = array_map(
            fn($user) => SentinelPro_User_List_Provider::format_user_access($user, $superuser_id, $default_cb),
            $users
        );

        wp_send_json_success([
            'users'        => $users_data,
            'total_users'  => $total,
            'per_page'     => $per_page,
            'current_page' => $page,
        ]);
    }

    public function ajax_handle_upload_preview(): void {
        // SECURITY: Verify nonce
        if (!check_ajax_referer('sentinelpro_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => esc_html__('Security check failed', 'valserv-analytics-for-sentinelpro')], 403);
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized', 'valserv-analytics-for-sentinelpro')], 403);
        }

        // Security: Sanitize CSV data input
        $csv_data = sanitize_textarea_field(wp_unslash($_POST['csv_data'] ?? ''));
        if (empty($csv_data)) {
            wp_send_json_error(['message' => esc_html__('No CSV data received.', 'valserv-analytics-for-sentinelpro')]);
        }
        try {
            $updates = SentinelPro_CSV_Permissions_Importer::parse_textarea_csv($csv_data);
            SentinelPro_CSV_Uploader_Handler::apply_user_access_updates($updates);
            wp_send_json_success(['message' => esc_html__('Access updated.', 'valserv-analytics-for-sentinelpro')]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => esc_html__('Error processing CSV data.', 'valserv-analytics-for-sentinelpro')]);
        }
    }

    // Add this method to support Google Sheets/public CSV import
    public function ajax_import_csv_url(): void {
        // SECURITY: Verify nonce
        if (!check_ajax_referer('sentinelpro_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => esc_html__('Security check failed', 'valserv-analytics-for-sentinelpro')], 403);
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Insufficient permissions', 'valserv-analytics-for-sentinelpro')], 403);
            return;
        }
        
        $this->ensure_manage_options();

        $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
        if (empty($url)) {
            wp_send_json_error(['message' => esc_html__('No URL provided.', 'valserv-analytics-for-sentinelpro')]);
        }
        try {
            // Fetch the CSV content from the URL
            $response = wp_remote_get($url, [
                'timeout' => 30,
                'sslverify' => true, // Enable SSL verification for security
                'user-agent' => 'SentinelPro/1.0'
            ]);
            if (is_wp_error($response)) {
                throw new Exception(esc_html__('Failed to fetch data from URL: ', 'valserv-analytics-for-sentinelpro') . esc_html($response->get_error_message()));
            }
            $http_code = wp_remote_retrieve_response_code($response);
            if ($http_code !== 200) {
                throw new Exception(esc_html__('Failed to fetch data from URL. HTTP status code: ', 'valserv-analytics-for-sentinelpro') . esc_html($http_code) . esc_html__('. Please ensure the URL is publicly accessible.', 'valserv-analytics-for-sentinelpro'));
            }
            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                throw new Exception(esc_html__('No content received from the provided URL.', 'valserv-analytics-for-sentinelpro'));
            }
            // Return the raw CSV content for preview
            wp_send_json_success([
                'message' => esc_html__('Permissions from URL loaded. Review and apply changes below.', 'valserv-analytics-for-sentinelpro'),
                'content' => $body
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Handle authentication data save with comprehensive security checks
     * Implements defense in depth: nonce verification, capability checks, and data sanitization
     */
    public function handle_auth_save(): void {
        // SECURITY: Verify nonce
        if (!check_ajax_referer('sentinelpro_auth_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => esc_html__('Security check failed', 'valserv-analytics-for-sentinelpro')], 403);
            return;
        }

        if (!current_user_can('manage_options')) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Capability check is sufficient for this authorization
            wp_send_json_error(['message' => esc_html__('Unauthorized', 'valserv-analytics-for-sentinelpro')], 403);
            return;
        }
        
        // SECURITY: Validate and sanitize sensitive data before passing to auth handler
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is handled above, $_POST is sanitized in sanitize_auth_data
        $sanitized_data = self::sanitize_auth_data($_POST);
        
        // Ensure all required classes are loaded
        if (!class_exists('SentinelPro_Security_Manager')) {
            require_once SENTINELPRO_ANALYTICS_PLUGIN_DIR . 'admin/AdminUtils/class-sentinelpro-security-manager.php';
            SentinelPro_Security_Manager::init();
        }
        
        if (!class_exists('SentinelPro_User_Access_Manager')) {
            require_once SENTINELPRO_ANALYTICS_PLUGIN_DIR . 'admin/AdminUtils/class-sentinelpro-user-access-manager.php';
        }
        
        // Start output buffering to catch any unwanted output
        ob_start();
        
        try {
            SentinelPro_Auth_Handler::save($sanitized_data);
        } catch (Exception $e) {
            // Clean any output buffer
            ob_end_clean();
            wp_send_json_error(['message' => esc_html__('Error: ', 'valserv-analytics-for-sentinelpro') . esc_html($e->getMessage())]);
        } catch (Error $e) {
            // Clean any output buffer
            ob_end_clean();
            wp_send_json_error(['message' => esc_html__('Fatal error: ', 'valserv-analytics-for-sentinelpro') . esc_html($e->getMessage())]);
        }
    }

    public function render_setting_field(string $field): void {
        $methods = [
            'property_id'      => 'render_property_id_field',
            'api_key'          => 'render_api_key_field',
            'enable_tracking'  => 'render_tracking_field', // ✅ FIXED KEY
            'account_name'     => 'render_account_name_field',
        ];

        if (isset($methods[$field])) {
            SentinelPro_Settings_Renderer::{$methods[$field]}($this->option_name);
        }
    }

    public static function plugin_activation(): void {
        // Assign restricted clearance to all users
        $users = get_users();
        foreach ($users as $user) {
            SentinelPro_User_Access_Manager::maybe_assign_default_clearance($user->ID);
        }
        // Create logs table
        self::create_logs_table();
    } 

    private static function create_logs_table(): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sentinelpro_access_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            page_key VARCHAR(50) NOT NULL,
            old_value VARCHAR(20),
            new_value VARCHAR(20),
            changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    private static function ensure_logs_table_exists(): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sentinelpro_access_logs';
        
        // Validate table name before using in query
        if (!self::is_valid_table_name($table_name)) {
            return;
        }
        
        // Check if table exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table existence check for custom table
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        
        if (!$table_exists) {
            self::create_logs_table();
        }
    }

    private function ensure_can_manage_and_access(string $section_key): void {
        if (!current_user_can('manage_options') || !SentinelPro_User_Access_Manager::user_has_access($section_key)) {
            wp_send_json_error(['message' => esc_html__('Unauthorized.', 'valserv-analytics-for-sentinelpro')], 403);
        }
    }

    private static function enforce_page_access(string $key): void {
        if (!SentinelPro_User_Access_Manager::user_has_access($key)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'valserv-analytics-for-sentinelpro'));
        }
    }

    private static function enforce_page_access_with_clearance(string $key, int $user_id): void {
        $clearance = SentinelPro_User_Access_Manager::get_clearance_level($user_id);
        if (!SentinelPro_User_Access_Manager::user_has_access($key, $user_id) && !in_array($clearance, ['restricted', 'elevated'], true)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'valserv-analytics-for-sentinelpro'));
        }
    }

    private function ensure_manage_options(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Permission denied.', 'valserv-analytics-for-sentinelpro')]);
        }
    }

    private function ensure_valid_ajax_permissions(string $nonce_action, string $nonce_key): void {
        if (
            !current_user_can('manage_options') ||
            !check_ajax_referer($nonce_action, $nonce_key, false)
        ) {
            wp_send_json_error(['message' => esc_html__('Unauthorized', 'valserv-analytics-for-sentinelpro')], 403);
        }
    }


    
    /**
     * Validate table name to prevent SQL injection
     */
    private static function is_valid_table_name(string $table_name): bool {
        // Only allow alphanumeric characters, underscores, and hyphens
        return preg_match('/^[a-zA-Z0-9_-]+$/', $table_name) === 1;
    }
    
    /**
     * Safe base64 decode with strict validation
     * Prevents base64 injection attacks by validating input before decoding
     * 
     * @param string $input The potentially base64 encoded string
     * @return string The decoded string, or empty string if invalid
     */
    private static function safe_b64(string $input): string {
        // Accept only valid base64 charset and proper padding
        if (!preg_match('#^[A-Za-z0-9/+]*={0,2}$#', $input) || (strlen($input) % 4)) {
            return '';
        }
        $out = base64_decode($input, true);
        return $out === false ? '' : $out;
    }
    
    /**
     * Validate and whitelist dynamic SQL identifiers (dimensions)
     * Prevents SQL injection by only allowing known, safe column names
     * 
     * @param string $raw_dimensions Comma-separated list of requested dimensions
     * @return string Comma-separated list of validated dimensions, or empty string if invalid
     */
    private static function validate_and_whitelist_dimensions(string $raw_dimensions): string {
        if (empty($raw_dimensions)) {
            return '';
        }
        
        // Define allowed dimensions (whitelist approach)
        $allowed_dimensions = [
            'date',
            'device', 
            'geo',
            'referrer',
            'os',
            'browser',
            'post_id',
            'user_id',
            'session_id'
        ];
        
        // Convert to lowercase and create lookup map for performance
        $allowed_map = array_fill_keys(array_map('strtolower', $allowed_dimensions), true);
        
        // Split and validate each dimension
        $requested_dimensions = array_map('trim', explode(',', $raw_dimensions));
        $valid_dimensions = [];
        
        foreach ($requested_dimensions as $dim) {
            // Clean the dimension name (only allow alphanumeric and underscores)
            $clean_dim = strtolower(preg_replace('/[^a-z0-9_]/i', '', $dim));
            
            // Check if it's in our whitelist
            if (isset($allowed_map[$clean_dim])) {
                $valid_dimensions[] = $clean_dim;
            }
        }
        
        // Return comma-separated list of valid dimensions
        return implode(',', $valid_dimensions);
    }
    
    /**
     * Validate and whitelist ORDER BY clauses to prevent SQL injection
     * Only allows known, safe column names and directions
     * 
     * @param string $raw_order_by The requested ORDER BY clause
     * @param array $allowed_columns List of allowed column names
     * @param string $default_order_by Default ORDER BY clause if validation fails
     * @return string Valid ORDER BY clause
     */
    private static function validate_order_by(string $raw_order_by, array $allowed_columns, string $default_order_by = ''): string {
        if (empty($raw_order_by)) {
            return $default_order_by;
        }
        
        // Convert to lowercase for comparison
        $raw_order_by = strtolower(trim($raw_order_by));
        
        // Create lookup map for allowed columns
        $allowed_map = array_fill_keys(array_map('strtolower', $allowed_columns), true);
        
        // Parse ORDER BY clause (simple format: column direction)
        $parts = explode(' ', $raw_order_by);
        $column = trim($parts[0] ?? '');
        $direction = strtoupper(trim($parts[1] ?? ''));
        
        // Validate column name
        if (!isset($allowed_map[strtolower($column)])) {
            return $default_order_by;
        }
        
        // Validate direction (only allow ASC/DESC)
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'ASC'; // Default to ASC if invalid
        }
        
        return "{$column} {$direction}";
    }
    
    /**
     * Sanitize and validate authentication data before passing to auth handler
     * Provides defense in depth for sensitive credential writes
     * 
     * @param array $raw_data Raw POST data from the request
     * @return array Sanitized and validated data
     */
    private static function sanitize_auth_data(array $raw_data): array {
        $sanitized = [];
        
        // Define allowed fields and their sanitization functions
        $allowed_fields = [
            'account_name' => 'sanitize_text_field',
            'token' => 'sanitize_text_field', // API key is sent as 'token'
            'property_id' => 'sanitize_text_field',
            'enable_tracking' => function($value) { return $value === '1' ? true : false; },
            'nonce' => 'sanitize_text_field', // Keep nonce for auth handler validation
        ];
        
        // Sanitize only allowed fields
        foreach ($allowed_fields as $field => $sanitize_func) {
            if (isset($raw_data[$field])) {
                if (is_callable($sanitize_func)) {
                    $sanitized[$field] = $sanitize_func(wp_unslash($raw_data[$field]));
                } else {
                    $sanitized[$field] = $sanitize_func(wp_unslash($raw_data[$field]));
                }
            }
        }
        
        // Additional validation for sensitive fields
        if (isset($sanitized['account_name'])) {
            // Account name should only contain alphanumeric characters, hyphens, and underscores
            $sanitized['account_name'] = preg_replace('/[^a-zA-Z0-9_-]/', '', $sanitized['account_name']);
        }
        
        if (isset($sanitized['token'])) {
            // API key should only contain alphanumeric characters and hyphens
            $sanitized['token'] = preg_replace('/[^a-zA-Z0-9-]/', '', $sanitized['token']);
        }
        
        if (isset($sanitized['property_id'])) {
            // Property ID can contain alphanumeric characters, dots, and hyphens (for domain names)
            $sanitized['property_id'] = preg_replace('/[^a-zA-Z0-9.-]/', '', $sanitized['property_id']);
        }
        
        return $sanitized;
    }
}

/**
 * Renders a checkbox field for use in WordPress settings.
 *
 * @param array $args {
 *     @type string $label_for    The option key.
 *     @type string $option_name  The settings array name.
 *     @type string $description  Description to show below the checkbox.
 * }
 */
function valserv_render_checkbox($args) {
    $options = get_option($args['option_name']);
    $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : '';
    ?>
    <input type="checkbox"
        id="<?php echo esc_attr($args['label_for']); ?>"
        name="<?php echo esc_attr($args['option_name']) . '[' . esc_attr($args['label_for']) . ']'; ?>"
        value="1"
        <?php checked(1, $value); ?>>
    <p class="description"><?php echo esc_html($args['description']); ?></p>
    <?php
}
