<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SentinelPro Database Configuration
 * Manages database settings and connection types
 */
class SentinelPro_Database_Config {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get database type (local or shared)
     */
    public function get_database_type() {
        $options = get_option('sentinelpro_options', []);
        return $options['database_type'] ?? 'local';
    }
    
    /**
     * Set database type
     */
    public function set_database_type($type) {
        $options = get_option('sentinelpro_options', []);
        $options['database_type'] = $type;
        update_option('sentinelpro_options', $options);
    }
    
    /**
     * Get shared database configuration
     */
    public function get_shared_db_config() {
        return [
            'host' => defined('SENTINELPRO_SHARED_DB_HOST') ? SENTINELPRO_SHARED_DB_HOST : '',
            'database' => defined('SENTINELPRO_SHARED_DB_NAME') ? SENTINELPRO_SHARED_DB_NAME : '',
            'username' => defined('SENTINELPRO_SHARED_DB_USER') ? SENTINELPRO_SHARED_DB_USER : '',
            'password' => defined('SENTINELPRO_SHARED_DB_PASS') ? SENTINELPRO_SHARED_DB_PASS : ''
        ];
    }
    
    /**
     * Test shared database connection
     */
    public function test_shared_connection() {
        $config = $this->get_shared_db_config();
        
        if (empty($config['host']) || empty($config['database'])) {
            return ['success' => false, 'message' => 'Shared database not configured'];
        }
        
        try {
            // Use WordPress database connection instead of direct mysqli
            global $wpdb;
            
            // Test connection by attempting a simple query
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Database connection test query
            $result = $wpdb->get_var("SELECT 1");
            
            if ($result !== '1') {
                return ['success' => false, 'message' => esc_html__('Connection failed: Database test query failed', 'valserv-analytics-for-sentinelpro')];
            }
            
            return ['success' => true, 'message' => esc_html__('Connection successful', 'valserv-analytics-for-sentinelpro')];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => esc_html__('Connection error: ', 'valserv-analytics-for-sentinelpro') . esc_html($e->getMessage())];
        }
    }
    
    /**
     * Get database instance based on configuration
     */
    public function get_database_instance($user_id = null, $property_id = null) {
        $type = $this->get_database_type();
        
        switch ($type) {
            case 'shared':
                return new SentinelPro_Shared_Database($user_id, $property_id);
            case 'local':
            default:
                return new SentinelPro_Local_Database($user_id, $property_id);
        }
    }
    
    /**
     * Add database settings to admin page
     */
    public function add_database_settings($admin_page) {
        add_settings_section(
            'sentinelpro_database_settings',
            'Database Configuration',
            [$this, 'render_database_section'],
            $admin_page
        );
        
        add_settings_field(
            'database_type',
            'Database Type',
            [$this, 'render_database_type_field'],
            $admin_page,
            'sentinelpro_database_settings'
        );
        
        add_settings_field(
            'shared_db_test',
            'Test Shared Database',
            [$this, 'render_shared_db_test_field'],
            $admin_page,
            'sentinelpro_database_settings'
        );
    }
    
    /**
     * Render database settings section
     */
    public function render_database_section() {
        echo '<p>Configure how SentinelPro stores analytics data:</p>';
    }
    
    /**
     * Render database type field
     */
    public function render_database_type_field() {
        $options = get_option('sentinelpro_options', []);
        $current_type = $options['database_type'] ?? 'local';
        
        echo '<select name="sentinelpro_options[database_type]">';
        echo '<option value="local"' . selected(esc_attr($current_type), 'local', false) . '>Local Database (Current)</option>';
        echo '<option value="shared"' . selected(esc_attr($current_type), 'shared', false) . '>Shared Database (Recommended for Public Plugin)</option>';
        echo '</select>';
        
        echo '<p class="description">';
        echo '<strong>' . esc_html__('Local Database:', 'valserv-analytics-for-sentinelpro') . '</strong> ' . esc_html__('Data stored in your WordPress database (current setup)', 'valserv-analytics-for-sentinelpro') . '<br>';
echo '<strong>' . esc_html__('Shared Database:', 'valserv-analytics-for-sentinelpro') . '</strong> ' . esc_html__('Data stored in a centralized database (better for public plugin)', 'valserv-analytics-for-sentinelpro');
        echo '</p>';
    }
    
    /**
     * Render shared database test field
     */
    public function render_shared_db_test_field() {
        $test_result = $this->test_shared_connection();
        
        // Enqueue the database test script
        wp_enqueue_script('valserv-database-test', plugin_dir_url(__FILE__) . '../js/database-test.js', ['jquery'], '1.0.0', true);
        wp_localize_script('valserv-database-test', 'valservAdminData', [
            'testDbNonce' => wp_create_nonce('sentinelpro_test_db')
        ]);
        
        echo '<button type="button" id="test-shared-db" class="button">Test Shared Database Connection</button>';
        echo '<div id="test-result" style="margin-top: 10px; padding: 10px; border-radius: 3px; display: none;"></div>';
    }
    
    /**
     * Handle AJAX test request
     */
    public function handle_test_shared_db() {
        // Verify nonce for security
        if (!check_ajax_referer('sentinelpro_test_db', 'nonce', false)) {
            wp_send_json_error(['message' => esc_html__('Security check failed', 'valserv-analytics-for-sentinelpro')], 403);
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Insufficient permissions', 'valserv-analytics-for-sentinelpro')], 403);
        }
        
        $result = $this->test_shared_connection();
        wp_send_json($result);
    }
    
    /**
     * Get database statistics
     */
    public function get_database_stats() {
        $db = $this->get_database_instance();
        
        return [
            'type' => $this->get_database_type(),
            'user_id' => get_current_user_id(),
            'property_id' => $this->get_property_id(),
            'connection_status' => $this->test_shared_connection()
        ];
    }
    
    private function get_property_id() {
        $options = get_option('sentinelpro_options', []);
        return $options['property_id'] ?? '';
    }
} 
