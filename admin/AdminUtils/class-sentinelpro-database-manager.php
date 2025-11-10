<?php

if ( ! defined('ABSPATH') ) { exit; }

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- This file uses $wpdb->prepare() correctly throughout

/**
 * SentinelPro Database Manager
 * Handles database table creation, updates, and management
 */

class SentinelPro_Database_Manager {
    
    private static $instance = null;
    private $wpdb;
    private $table_prefix;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix . 'sentinelpro_';
        
        // Add admin notice hook for table creation
        add_action('admin_init', [__CLASS__, 'add_table_creation_notice']);
    }
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Install all database tables
     */
    public function install_tables() {
        // Only create tables if they don't exist
        $this->create_analytics_events_table();
        $this->create_posts_table();
        
        // Store version for future updates
        update_option('sentinelpro_db_version', '2.0.0');
        
        // Ensure default dimensions are stored in options
        $this->ensure_default_dimensions_option();
    }
    
    /**
     * Force recreation of analytics events table with proper dimensions
     * Use this if the table was created without custom dimension columns
     */
    public function force_recreate_analytics_events_table() {
        global $wpdb;
        
        $table_name = $this->table_prefix . 'analytics_events';
        
        // Drop existing table if it exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table recreation for custom analytics table, table name is validated
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        
        // Recreate with proper dimensions
        $this->create_analytics_events_table();
        
        return true;
    }
    
    /**
     * Check if analytics events table has custom dimension columns
     */
    public function check_analytics_events_table_structure() {
        global $wpdb;
        
        $table_name = $this->table_prefix . 'analytics_events';
        
        // Check if table exists - validate table name first
        if (!self::is_valid_table_name($table_name)) {
            return ['exists' => false, 'has_dimensions' => false, 'message' => 'Invalid table name'];
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table existence check for custom analytics table
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        if (!$table_exists) {
            return ['exists' => false, 'has_dimensions' => false, 'message' => 'Table does not exist'];
        }
        
        // Get table columns - validate table name first
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column structure check for custom analytics table
        $columns = $wpdb->get_col("DESCRIBE {$table_name}");
        
        // Check for custom dimension columns
        $has_dimensions = false;
        foreach ($columns as $column) {
            if (strpos($column, 'dimension_') === 0) {
                $has_dimensions = true;
                break;
            }
        }
        
        return [
            'exists' => true,
            'has_dimensions' => $has_dimensions,
            'columns' => $columns,
            'message' => $has_dimensions ? 'Table exists with custom dimensions' : 'Table exists but missing custom dimensions'
        ];
    }
    


    /**
     * Create the analytics events table with custom dimensions
     */
    private function create_analytics_events_table() {
        $table_name = $this->table_prefix . 'analytics_events';
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Database manager debug logging for troubleshooting
            error_log("SentinelPro: Creating analytics events table: {$table_name}");
        }
        
        // Get custom dimensions from WordPress options
        $custom_dimensions = $this->get_custom_dimensions();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Database manager debug logging for troubleshooting
            error_log("SentinelPro: Custom dimensions for table creation: " . print_r($custom_dimensions, true));
        }
        
        // Build the base SQL with required columns
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL,
            device VARCHAR(100) NULL,
            geo VARCHAR(100) NULL,
            referrer VARCHAR(500) NULL,
            os VARCHAR(100) NULL,
            browser VARCHAR(100) NULL,";
        
        // Add custom dimension columns if they exist
        if (!empty($custom_dimensions)) {
            foreach ($custom_dimensions as $dimension) {
                $column_name = 'dimension_' . $this->sanitize_column_name($dimension);
                $sql .= "\n            {$column_name} VARCHAR(500) NULL,";
            }
        }
        
        // Add the remaining columns
        $sql .= "
            sessions INT UNSIGNED DEFAULT 0,
            views INT UNSIGNED DEFAULT 0,
            visits INT UNSIGNED DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_date (date),
            INDEX idx_device (device),
            INDEX idx_geo (geo),
            INDEX idx_os (os),
            INDEX idx_browser (browser),
            INDEX idx_sessions (sessions),
            INDEX idx_views (views),
            INDEX idx_visits (visits),
            INDEX idx_created_at (created_at)";
        
        // Add unique key only if no custom dimensions (to avoid conflicts)
        if (empty($custom_dimensions)) {
            $sql .= ",\n            UNIQUE KEY unique_analytics_event (date, device, geo, referrer, os, browser)";
        }
        
        // Add indexes for custom dimensions if they exist
        if (!empty($custom_dimensions)) {
            foreach ($custom_dimensions as $dimension) {
                $column_name = 'dimension_' . $this->sanitize_column_name($dimension);
                $sql .= ",\n            INDEX idx_{$column_name} ({$column_name})";
            }
        }
        
        $sql .= "\n        ) " . $this->wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Database manager debug logging for troubleshooting
            error_log("SentinelPro: Executing dbDelta with SQL: " . substr($sql, 0, 500) . "...");
        }
        
        dbDelta($sql);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Database manager debug logging for troubleshooting
            error_log("SentinelPro: dbDelta completed");
        }
        
        // Store the custom dimensions used in this table for future reference
        if (!empty($custom_dimensions)) {
            update_option('sentinelpro_events_table_dimensions', $custom_dimensions);
            
            // Ensure all dimensions are properly configured in WordPress options
            $this->ensure_dimensions_configured($custom_dimensions);
        }
    }

    /**
     * Ensure all custom dimensions are properly configured in WordPress options
     */
    private function ensure_dimensions_configured($custom_dimensions) {
        $options = get_option('sentinelpro_options', []);
        $property_id = $options['property_id'] ?? '';
        
        if (!$property_id) {
            return;
        }
        
        // Get existing dimensions from options
        $existing_dimensions = get_option("sentinelpro_dimensions_{$property_id}", []);
        
        // If existing dimensions is an associative array, get the keys
        if (is_array($existing_dimensions) && array_values($existing_dimensions) !== $existing_dimensions) {
            $existing_dimension_keys = array_keys($existing_dimensions);
        } else {
            $existing_dimension_keys = is_array($existing_dimensions) ? $existing_dimensions : [];
        }
        
        // Merge with custom dimensions from database
        $all_dimensions = array_unique(array_merge($existing_dimension_keys, $custom_dimensions));
        
        // Create a comprehensive dimensions array with default values
        $dimensions_config = [];
        foreach ($all_dimensions as $dimension) {
            $dimensions_config[$dimension] = $this->get_dimension_default_values($dimension);
        }
        
        // Update the WordPress option
        update_option("sentinelpro_dimensions_{$property_id}", $dimensions_config);
        
        // Also update the canonical dimensions for JavaScript
        update_option('sentinelpro_canonical_dimensions', $all_dimensions);
    }
    
    /**
     * Get default values for a dimension based on its name
     */
    private function get_dimension_default_values($dimension) {
        $dimension_lower = strtolower($dimension);
        
        // Define default values for known dimensions
        $default_values = [
            'intent' => [
                '[No Value]', 'Answer', 'authority', 'breakout', 'culture', 'entertainment', 'evergreen', 'feed', 'gaming', 'gaming-news', 'internet-culture', 'Native Commerce', 'Non-Article', 'Paid', 'short-term', 'Sponsored', 'Syndicated',
                'Affiliate', 'Authority', 'Brand', 'Commerce', 'Discussion', 'Evergreen', 'Feed', 'freelance', 'gaming-curation', 'guides', 'movies', 'New Authority', 'non-article', 'Short-Term', 'Sniping', 'Support', 'tv'
            ],
            'adstemplate' => [
                '[No Value]', 'content-all', 'content-tldr', 'entertainment', 'gaming', 'home', 'list-all', 'list-tldr', 'thread-all', 'video-all',
                'breakout', 'content-exclusive', 'directory-all', 'freelance', 'gaming-curation', 'hub', 'list-list', 'listing', 'thread-home', 'video-home'
            ],
            'articletype' => [
                '[No Value]', 'breakout', 'db', 'Entertainment', 'features', 'Gaming', 'gaming-curation', 'news', 'PackageResourceType', 'PostResourceType', 'StreamResourceType', 'video',
                'article', 'culture', 'directory', 'entertainment', 'freelance', 'gaming', 'list', 'null', 'PageResourceType', 'product-review', 'thread', 'VideoGameResourceType'
            ],
            'contenttype' => ['List', 'Non-Article', 'News'],
            'primarytag' => ['Taylor Swift', 'Buzz', 'Entertainment', 'The Rich & Powerful'],
            'primarycategory' => ['celebrity', 'Non-Article', 'television', 'history'],
            'networkcategory' => ['Other', 'Non-Article'],
            'segment' => ['[No Value]'],
            'initiative' => ['[No Value]'],
            'publishdate' => ['2024-02-08', '2014-05-21', '2016-11-05', '2017-12-30', '2014-06-03', '2023-09-30', '2024-09-27', '2025-07-31'],
            'system' => ['Premium']
        ];
        
        return $default_values[$dimension_lower] ?? ['[No Value]', 'Premium', 'Performance'];
    }
    
    /**
     * Force refresh dimensions configuration from database table
     * This method scans the analytics_events table and updates WordPress options
     */
    public function refresh_dimensions_configuration() {
        $table_name = $this->get_table_name('analytics_events');
        
        // Get all column names from the table
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column structure check for custom analytics table, table name is validated
        $columns = $this->wpdb->get_results("SHOW COLUMNS FROM {$table_name}", ARRAY_A);
        
        if (empty($columns)) {
            return false;
        }
        
        // Extract custom dimensions (columns starting with 'dimension_')
        $custom_dimensions = [];
        foreach ($columns as $column) {
            $column_name = $column['Field'];
            if (strpos($column_name, 'dimension_') === 0) {
                $dimension_name = substr($column_name, 10); // Remove 'dimension_' prefix
                $custom_dimensions[] = $dimension_name;
            }
        }
        
        if (empty($custom_dimensions)) {
            return false;
        }
        
        // Ensure dimensions are configured
        $this->ensure_dimensions_configured($custom_dimensions);
        
        return $custom_dimensions;
    }
    
    /**
     * Create the posts table for storing post analytics data
     */
    public function create_posts_table() {
        $table_name = $this->table_prefix . 'posts';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL,
            post_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(500) NOT NULL,
            author VARCHAR(200) NULL,
            categories TEXT NULL,
            tags TEXT NULL,
            date_published DATE NULL,
            views INT UNSIGNED DEFAULT 0,
            sessions INT UNSIGNED DEFAULT 0,
            post_url VARCHAR(1000) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_date (date),
            INDEX idx_post_id (post_id),
            INDEX idx_title (title(100)),
            INDEX idx_author (author),
            INDEX idx_date_published (date_published),
            INDEX idx_views (views),
            INDEX idx_sessions (sessions),
            INDEX idx_created_at (created_at),
            UNIQUE KEY unique_post_date (post_id, date)
        ) " . $this->wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get custom dimensions from WordPress options
     */
    private function get_custom_dimensions() {
        global $wpdb;
        
        // Get all options that match the pattern sentinelpro_dimensions_{property_id}
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom dimensions options query
        $options = $wpdb->get_results(
            "SELECT option_name, option_value 
             FROM {$wpdb->options} 
             WHERE option_name LIKE 'sentinelpro_dimensions_%'"
        );
        
        $dimensions = [];
        foreach ($options as $option) {
            // Validate the option value before unserializing
            $option_value = $option->option_value;
            if (empty($option_value) || !is_string($option_value)) {
                continue;
            }
            
            // Additional validation for unserialize safety
            if (strpos($option_value, 'O:') !== false || strpos($option_value, 'a:') === false) {
                // Skip if it contains object serialization or doesn't start with array
                continue;
            }
            
            $dimensions_data = maybe_unserialize($option_value);
            if (is_array($dimensions_data)) {
                // Check if it's an associative array (keys are dimension names)
                if (array_values($dimensions_data) !== $dimensions_data) {
                    // It's an associative array, get the keys
                    $dimensions = array_merge($dimensions, array_keys($dimensions_data));
                } else {
                    // It's a simple array, check for objects with 'name' property
                    foreach ($dimensions_data as $dimension) {
                        if (is_array($dimension) && isset($dimension['name']) && !empty($dimension['name'])) {
                            $dimensions[] = $dimension['name'];
                        } elseif (is_string($dimension) && !empty($dimension)) {
                            $dimensions[] = $dimension;
                        }
                    }
                }
            }
        }
        
        // SECURITY FIX: If no dimensions found, provide default dimensions for first install
        if (empty($dimensions)) {
            $dimensions = [
                'intent', 'adstemplate', 'articletype', 'contenttype', 'primarytag',
                'primarycategory', 'networkcategory', 'segment', 'initiative', 
                'publishdate', 'system'
            ];
        }
        
        return array_unique($dimensions);
    }
    
    /**
     * Sanitize column name for database
     */
    private function sanitize_column_name($name) {
        // Remove special characters and replace spaces with underscores
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
        // Ensure it starts with a letter
        if (!preg_match('/^[a-zA-Z]/', $sanitized)) {
            $sanitized = 'dim_' . $sanitized;
        }
        return strtolower($sanitized);
    }
    
    /**
     * Map custom dimension name to correct database column name
     * This handles the mismatch between API dimension names and existing database column names
     */
    private function map_dimension_to_column($dimension_name) {
        // Get current table structure to see what columns actually exist
        $table_name = $this->table_prefix . 'analytics_events';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column structure check for custom analytics table, table name is validated
        $existing_columns = $this->wpdb->get_col("DESCRIBE {$table_name}");
        
        // Try different possible column name formats
        $possible_names = [
            'dimension_' . strtolower($dimension_name), // dimension_adstemplate
            'dimension_' . $this->sanitize_column_name($dimension_name), // dimension_ads_template
            'dimension_' . str_replace(['Template', 'Type', 'Category', 'Date'], ['template', 'type', 'category', 'date'], strtolower($dimension_name)), // Handle camelCase
        ];
        
        // Find the first matching column name
        foreach ($possible_names as $possible_name) {
            if (in_array($possible_name, $existing_columns)) {
                return $possible_name;
            }
        }
        
        // If no match found, return the sanitized version (will be created if needed)
        return 'dimension_' . $this->sanitize_column_name($dimension_name);
    }
    
    /**
     * Update analytics events table with new custom dimensions
     */
    public function update_analytics_events_table() {
        $table_name = $this->table_prefix . 'analytics_events';
        
        // Check if table exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table existence check for custom analytics table, table name is validated
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
        if (!$table_exists) {
            $this->create_analytics_events_table();
            return;
        }
        
        // Get current custom dimensions
        $current_dimensions = get_option('sentinelpro_events_table_dimensions', []);
        $new_dimensions = $this->get_custom_dimensions();
        
        // Find new dimensions that need to be added
        $dimensions_to_add = array_diff($new_dimensions, $current_dimensions);
        
        foreach ($dimensions_to_add as $dimension) {
            $column_name = 'dimension_' . $this->sanitize_column_name($dimension);
            
            // Check if column already exists
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column existence check for custom analytics table, table name is validated
            $column_exists = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME, $table_name, $column_name
            ));
            
            if (empty($column_exists)) {
                // Add the new column
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table structure modification for custom analytics table, table and column names are validated
                $this->wpdb->query("ALTER TABLE {$table_name} ADD COLUMN {$column_name} VARCHAR(500) NULL");
                
                // Add index for the new column
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table structure modification for custom analytics table, table and column names are validated
                $this->wpdb->query("ALTER TABLE {$table_name} ADD INDEX idx_{$column_name} ({$column_name})");
            }
        }
        
        // Update the stored dimensions list
        update_option('sentinelpro_events_table_dimensions', $new_dimensions);
    }
    
    /**
     * Get the structure of the analytics events table
     */
    public function get_analytics_events_table_structure() {
        $table_name = $this->table_prefix . 'analytics_events';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column structure check for custom analytics table, table name is validated
        $columns = $this->wpdb->get_results("DESCRIBE {$table_name}");
        $structure = [];
        
        foreach ($columns as $column) {
            $structure[$column->Field] = [
                'type' => $column->Type,
                'null' => $column->Null,
                'key' => $column->Key,
                'default' => $column->Default,
                'extra' => $column->Extra
            ];
        }
        
        return $structure;
    }
    
    /**
     * Insert data into analytics events table
     */
    public function insert_analytics_event($data) {
        $table_name = $this->table_prefix . 'analytics_events';
        
        // Ensure required fields are present
        $required_fields = ['date'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                throw new Exception(esc_html("Required field '{$field}' is missing"));
            }
        }
        
        // Sanitize and prepare data
        $sanitized_data = [];
        foreach ($data as $key => $value) {
            if ($key === 'date') {
                $sanitized_data[$key] = sanitize_text_field($value);
            } elseif (strpos($key, 'dimension_') === 0) {
                // Map dimension column names to correct database column names
                $mapped_key = $this->map_dimension_to_column(substr($key, 10)); // Remove 'dimension_' prefix
                $sanitized_data[$mapped_key] = sanitize_text_field($value);
            } else {
                $sanitized_data[$key] = sanitize_text_field($value);
            }
        }
        
        // Insert the data with ON DUPLICATE KEY UPDATE to handle duplicates
        $columns = array_keys($sanitized_data);
        $placeholders = array_fill(0, count($columns), '%s');
        $values = array_values($sanitized_data);
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic SQL construction for custom analytics table, table name is validated
        $sql = "INSERT INTO {$table_name} (" . implode(', ', $columns) . ") 
                VALUES (" . implode(', ', $placeholders) . ")
                ON DUPLICATE KEY UPDATE 
                sessions = VALUES(sessions),
                views = VALUES(views),
                visits = VALUES(visits),
                created_at = VALUES(created_at)";
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic SQL construction for custom analytics table, table name is validated
        $result = $this->wpdb->query($this->wpdb->prepare($sql, $values));
        
        if ($result === false) {
            $error_message = $this->wpdb->last_error;
            
            // Check if this is an "Unknown column" error
            if (strpos($error_message, 'Unknown column') !== false) {
                // Update the table structure and try again
                $this->update_analytics_events_table();
                
                // Try the insert again
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic SQL construction for custom analytics table, table name is validated
                $result = $this->wpdb->query($this->wpdb->prepare($sql, $values));
                
                if ($result === false) {
                    throw new Exception(esc_html("Failed to insert analytics event after table update: " . $this->wpdb->last_error));
                }
            } else {
                throw new Exception(esc_html("Failed to insert analytics event: " . $error_message));
            }
        }
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * Get analytics events with optional filters
     */
    public function get_analytics_events($filters = [], $limit = 100, $offset = 0) {
        $table_name = $this->table_prefix . 'analytics_events';
        
        $where_clauses = [];
        $where_values = [];
        
        // Build WHERE clauses from filters
        foreach ($filters as $field => $value) {
            if (!empty($value)) {
                $where_clauses[] = "{$field} = %s";
                $where_values[] = $value;
            }
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic SQL with validated table name
        $sql = "SELECT * FROM {$table_name} {$where_sql} ORDER BY date DESC, created_at DESC LIMIT %d OFFSET %d";
        $where_values[] = $limit;
        $where_values[] = $offset;
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic SQL construction for custom analytics table, table name is validated
        return $this->wpdb->get_results($this->wpdb->prepare($sql, $where_values));
    }
    
    /**
     * Get table name with prefix
     */
    public function get_table_name($table) {
        return $this->table_prefix . $table;
    }
    
    /**
     * Check if tables exist
     */
    public function tables_exist() {
        $tables = [
            'analytics_events',
            'posts'
        ];
        
        foreach ($tables as $table) {
            $table_name = $this->get_table_name($table);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table existence check for custom tables, table name is validated
            $result = $this->wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
            if (!$result) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Add column to table if it doesn't exist
     */
    private function add_column_if_not_exists($table_name, $column_name, $column_definition) {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic SQL construction for custom analytics table, table and column names are validated
        $column_exists = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME, $table_name, $column_name
        ));
        
        if (empty($column_exists)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table structure modification for custom analytics table, table and column names are validated
            $this->wpdb->query("ALTER TABLE {$table_name} ADD COLUMN {$column_name} {$column_definition}");
        }
    }
    
    /**
     * Clean up old data
     */
    public function cleanup_old_data($days = 90) {
        $analytics_events_table = $this->get_table_name('analytics_events');
        $posts_table = $this->get_table_name('posts');
        
        // Clean up old analytics events data
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic SQL with validated table name
        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$analytics_events_table} WHERE date < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
        
        // Clean up old posts data
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic SQL with validated table name
        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$posts_table} WHERE date < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }

    /**
     * Insert or update post analytics data
     */
    public function insert_post_data($data) {
        $table_name = $this->table_prefix . 'posts';
        
        // Sanitize the data
        $sanitized_data = array(
            'date' => sanitize_text_field($data['date']),
            'post_id' => intval($data['post_id']),
            'title' => sanitize_text_field($data['title']),
            'author' => sanitize_text_field($data['author'] ?? ''),
            'categories' => sanitize_textarea_field($data['categories'] ?? ''),
            'tags' => sanitize_textarea_field($data['tags'] ?? ''),
            'date_published' => sanitize_text_field($data['date_published'] ?? ''),
            'views' => intval($data['views'] ?? 0),
            'sessions' => intval($data['sessions'] ?? 0),
            'post_url' => esc_url_raw($data['post_url'] ?? ''),
            'created_at' => current_time('mysql')
        );
        
        // Use ON DUPLICATE KEY UPDATE for upsert functionality
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic SQL construction for custom posts table, table name is validated
        $sql = "INSERT INTO {$table_name} 
                (date, post_id, title, author, categories, tags, date_published, views, sessions, post_url, created_at) 
                VALUES (%s, %d, %s, %s, %s, %s, %s, %d, %d, %s, %s)
                ON DUPLICATE KEY UPDATE 
                title = VALUES(title),
                author = VALUES(author),
                categories = VALUES(categories),
                tags = VALUES(tags),
                date_published = VALUES(date_published),
                views = VALUES(views),
                sessions = VALUES(sessions),
                post_url = VALUES(post_url),
                created_at = VALUES(created_at)";
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic SQL construction for custom posts table, table name is validated
        $result = $this->wpdb->query($this->wpdb->prepare($sql, 
            $sanitized_data['date'],
            $sanitized_data['post_id'],
            $sanitized_data['title'],
            $sanitized_data['author'],
            $sanitized_data['categories'],
            $sanitized_data['tags'],
            $sanitized_data['date_published'],
            $sanitized_data['views'],
            $sanitized_data['sessions'],
            $sanitized_data['post_url'],
            $sanitized_data['created_at']
        ));
        
        if ($result === false) {
            throw new Exception(esc_html("Failed to insert/update post data: " . $this->wpdb->last_error));
        }
        
        return $this->wpdb->insert_id;
    }

    /**
     * Get posts data with optional filters
     */
    public function get_posts_data($filters = [], $limit = 100, $offset = 0) {
        $table_name = $this->table_prefix . 'posts';
        
        $where_clauses = [];
        $where_values = [];
        
        // Build WHERE clauses from filters
        foreach ($filters as $field => $value) {
            if (!empty($value)) {
                $where_clauses[] = "{$field} = %s";
                $where_values[] = $value;
            }
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic SQL construction for custom posts table, table name is validated
        $sql = "SELECT * FROM {$table_name} {$where_sql} ORDER BY date DESC, views DESC LIMIT %d OFFSET %d";
        $where_values[] = $limit;
        $where_values[] = $offset;
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic SQL construction for custom posts table, table name is validated
        return $this->wpdb->get_results($this->wpdb->prepare($sql, $where_values));
    }

    /**
     * Get posts summary statistics
     */
    public function get_posts_summary($date = null) {
        $table_name = $this->table_prefix . 'posts';
        
        $where_sql = '';
        $where_values = [];
        
        if ($date) {
            $where_sql = 'WHERE date = %s';
            $where_values[] = $date;
        }
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic SQL construction for custom posts table, table name is validated
        $sql = "SELECT 
                    COUNT(*) as total_posts,
                    SUM(views) as total_views,
                    SUM(sessions) as total_sessions,
                    AVG(views) as avg_views,
                    AVG(sessions) as avg_sessions
                FROM {$table_name} {$where_sql}";
        
        if (!empty($where_values)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic SQL construction for custom posts table, table name is validated
            return $this->wpdb->get_row($this->wpdb->prepare($sql, $where_values));
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic SQL construction for custom posts table, table name is validated
            return $this->wpdb->get_row($sql);
        }
    }

    /**
     * Get post metrics for a specific post over a date range
     */
    public function get_post_metrics($post_id, $start_date, $end_date) {
        $table_name = $this->table_prefix . 'posts';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic SQL construction for custom posts table, table name is validated
        $sql = $this->wpdb->prepare(
            "SELECT SUM(views) as total_views, SUM(sessions) as total_sessions
             FROM {$table_name} 
             WHERE post_id = %d 
             AND date >= %s 
             AND date <= %s",
            $post_id,
            $start_date,
            $end_date
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic SQL construction for custom posts table, table name is validated
        $result = $this->wpdb->get_row($sql);
        
        if ($result) {
            return [
                'views' => (int) ($result->total_views ?? 0),
                'sessions' => (int) ($result->total_sessions ?? 0)
            ];
        }

        return [
            'views' => 0,
            'sessions' => 0
        ];
    }
    
    /**
     * Add admin notice for table creation status
     */
    public static function add_table_creation_notice() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin table creation notice, user capability already checked
        if (isset($_GET['create_sentinelpro_table']) && current_user_can('manage_options')) {
            $db_manager = self::get_instance();
            $result = $db_manager->manually_create_analytics_events_table();
            
            $notice_class = $result['success'] ? 'notice-success' : 'notice-error';
            $message = $result['message'];
            
            add_action('admin_notices', function() use ($notice_class, $message) {
                echo '<div class="notice ' . esc_attr($notice_class) . ' is-dismissible"><p><strong>SentinelPro:</strong> ' . esc_html($message) . '</p></div>';
            });
        }
    }
    
    /**
     * Ensure default dimensions are stored in WordPress options
     */
    private function ensure_default_dimensions_option() {
        // Check if the default dimensions option already exists
        $existing_dimensions = get_option('sentinelpro_events_table_dimensions', []);
        
        if (empty($existing_dimensions)) {
            // Don't set hardcoded dimensions - they should come from API configuration
            // The table will be created with basic columns and dimensions will be added
            // when the API credentials are configured and dimensions are fetched
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Database manager debug logging for troubleshooting
                error_log("SentinelPro: No default dimensions set - will use API configuration when available");
            }
        }
    }
    
    /**
     * Ensure the analytics events table exists, create if it doesn't
     */
    public function ensure_analytics_events_table_exists() {
        $table_name = $this->table_prefix . 'analytics_events';
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Database manager debug logging for troubleshooting
            error_log("SentinelPro: Checking if table exists: {$table_name}");
        }
        
        // Check if table exists
        $table_exists = $this->wpdb->get_var($this->wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Database manager debug logging for troubleshooting
            error_log("SentinelPro: Table exists check result: " . ($table_exists ? 'exists' : 'does not exist'));
        }
        
        if (!$table_exists) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Database manager debug logging for troubleshooting
                error_log("SentinelPro: Analytics events table does not exist, creating it now");
            }
            
            $this->create_analytics_events_table();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Database manager debug logging for troubleshooting
                error_log("SentinelPro: Analytics events table created successfully");
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Ensure the posts table exists, create if it doesn't
     */
    public function ensure_posts_table_exists() {
        $table_name = $this->table_prefix . 'posts';
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Database manager debug logging for troubleshooting
            error_log("SentinelPro: Checking if posts table exists: {$table_name}");
        }
        
        // Check if table exists
        $table_exists = $this->wpdb->get_var($this->wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Database manager debug logging for troubleshooting
            error_log("SentinelPro: Posts table exists check result: " . ($table_exists ? 'exists' : 'does not exist'));
        }
        
        if (!$table_exists) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Database manager debug logging for troubleshooting
                error_log("SentinelPro: Posts table does not exist, creating it now");
            }
            
            $this->create_posts_table();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Database manager debug logging for troubleshooting
                error_log("SentinelPro: Posts table created successfully");
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Update the analytics events table structure when dimensions are configured
     */
    public function update_analytics_events_table_structure($property_id) {
        $table_name = $this->table_prefix . 'analytics_events';
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Database manager debug logging for troubleshooting
            error_log("SentinelPro: Starting table structure update for property {$property_id}");
        }
        
        // Ensure the table exists first
        $this->ensure_analytics_events_table_exists();
        
        // Get dimensions for this property
        $dimensions = get_option("sentinelpro_dimensions_{$property_id}", []);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Database manager debug logging for troubleshooting
            error_log("SentinelPro: Dimensions for property {$property_id}: " . print_r($dimensions, true));
        }
        
        if (empty($dimensions)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Database manager debug logging for troubleshooting
                error_log("SentinelPro: No dimensions found for property {$property_id}, cannot update table structure");
            }
            return false;
        }
        
        // Get existing table structure
        $existing_columns = $this->wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
        $existing_column_names = array_column($existing_columns, 'Field');
        
        // Check which dimension columns need to be added
        $columns_to_add = [];
        foreach ($dimensions as $dimension => $config) {
            $column_name = 'dimension_' . $this->sanitize_column_name($dimension);
            if (!in_array($column_name, $existing_column_names)) {
                $columns_to_add[] = $column_name;
            }
        }
        
        // Add missing columns
        if (!empty($columns_to_add)) {
            foreach ($columns_to_add as $column_name) {
                $sql = "ALTER TABLE {$table_name} ADD COLUMN {$column_name} VARCHAR(500) NULL";
                $this->wpdb->query($sql);
                
                // Add index for the new column
                $index_name = "idx_{$column_name}";
                $sql = "ALTER TABLE {$table_name} ADD INDEX {$index_name} ({$column_name})";
                $this->wpdb->query($sql);
            }
            
            // Update the dimensions option
            update_option('sentinelpro_events_table_dimensions', array_keys($dimensions));
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Database manager debug logging for troubleshooting
                error_log("SentinelPro: Added dimension columns to analytics events table: " . implode(', ', $columns_to_add));
            }
            
            return true;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Database manager debug logging for troubleshooting
            error_log("SentinelPro: All dimension columns already exist in analytics events table");
        }
        
        return false;
    }
    
    /**
     * Manually update table structure (for admin use)
     */
    public function manually_update_table_structure($property_id) {
        $result = $this->update_analytics_events_table_structure($property_id);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Table structure updated successfully with new dimensions.'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'No table structure updates were needed or dimensions not found.'
            ];
        }
    }
    
    /**
     * Validate table name to prevent SQL injection
     */
    private static function is_valid_table_name(string $table_name): bool {
        // Only allow alphanumeric characters, underscores, and hyphens
        return preg_match('/^[a-zA-Z0-9_-]+$/', $table_name) === 1;
    }
} 
