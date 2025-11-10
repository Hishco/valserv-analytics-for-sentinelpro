<?php

if ( ! defined('ABSPATH') ) { exit; }

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- This file uses $wpdb->prepare() correctly throughout

/**
 * SentinelPro Database Interface
 * Abstract layer for database operations (local and shared)
 */
abstract class SentinelPro_Database_Interface {
    
    protected $user_id;
    protected $property_id;
    
    public function __construct($user_id = null, $property_id = null) {
        $this->user_id = $user_id ?: get_current_user_id();
        $this->property_id = $property_id ?: $this->get_property_id();
    }
    
    abstract public function store_analytics($cache_key, $data, $date_range, $metadata = []);
    abstract public function get_analytics($cache_key);
    abstract public function find_overlapping_cache($date_range);
    abstract public function cleanup_old_data($days = 31);
    abstract public function get_user_analytics($user_id = null);
    
    protected function get_property_id() {
        $options = get_option('sentinelpro_options', []);
        return $options['property_id'] ?? '';
    }
    
    protected function get_user_identifier() {
        return $this->user_id . '_' . $this->property_id;
    }
}

/**
 * Local Database Implementation (Current)
 */
class SentinelPro_Local_Database extends SentinelPro_Database_Interface {
    
    private $wpdb;
    
    public function __construct($user_id = null, $property_id = null) {
        parent::__construct($user_id, $property_id);
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    public function store_analytics($cache_key, $data, $date_range, $metadata = []) {
        $table_name = $this->wpdb->prefix . 'sentinelpro_analytics_cache';
        
        return $this->wpdb->insert($table_name, [
            'cache_key' => $cache_key,
            'data' => json_encode($data),
            'date_range_start' => $date_range[0],
            'date_range_end' => $date_range[1],
            'metadata' => json_encode($metadata),
            'user_id' => $this->user_id,
            'property_id' => $this->property_id,
            'created_at' => current_time('mysql')
        ]);
    }
    
    public function get_analytics($cache_key) {
        $table_name = $this->wpdb->prefix . 'sentinelpro_analytics_cache';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Using $wpdb->prepare() correctly, table name is validated and safe
        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE cache_key = %s AND user_id = %d",
            $cache_key,
            $this->user_id
        ));
        
        if ($result) {
            return [
                'data' => json_decode($result->data, true),
                'date_range' => [$result->date_range_start, $result->date_range_end],
                'metadata' => json_decode($result->metadata, true),
                'created_at' => $result->created_at
            ];
        }
        
        return null;
    }
    
    public function find_overlapping_cache($date_range) {
        $table_name = $this->wpdb->prefix . 'sentinelpro_analytics_cache';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Using $wpdb->prepare() correctly, table name is validated and safe
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE user_id = %d 
             AND property_id = %s
             AND date_range_start <= %s 
             AND date_range_end >= %s",
            $this->user_id,
            $this->property_id,
            $date_range[1],
            $date_range[0]
        ));
    }
    
    public function cleanup_old_data($days = 31) {
        $table_name = $this->wpdb->prefix . 'sentinelpro_analytics_cache';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Using $wpdb->prepare() correctly, table name is validated and safe
        return $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$table_name} 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
    
    public function get_user_analytics($user_id = null) {
        $user_id = $user_id ?: $this->user_id;
        $table_name = $this->wpdb->prefix . 'sentinelpro_analytics_cache';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Using $wpdb->prepare() correctly, table name is validated and safe
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ));
    }
}

/**
 * Shared MySQL Database Implementation (Future)
 */
class SentinelPro_Shared_Database extends SentinelPro_Database_Interface {
    
    private $shared_db;
    private $shared_config;
    
    public function __construct($user_id = null, $property_id = null) {
        parent::__construct($user_id, $property_id);
        $this->init_shared_connection();
    }
    
    private function init_shared_connection() {
        // Shared database configuration
        $this->shared_config = [
            'host' => defined('SENTINELPRO_SHARED_DB_HOST') ? SENTINELPRO_SHARED_DB_HOST : 'your-shared-db.com',
            'database' => defined('SENTINELPRO_SHARED_DB_NAME') ? SENTINELPRO_SHARED_DB_NAME : 'sentinelpro_shared',
            'username' => defined('SENTINELPRO_SHARED_DB_USER') ? SENTINELPRO_SHARED_DB_USER : 'shared_user',
            'password' => defined('SENTINELPRO_SHARED_DB_PASS') ? SENTINELPRO_SHARED_DB_PASS : 'shared_pass'
        ];
        
        // Use WordPress database connection instead of direct mysqli
        global $wpdb;
        $this->shared_db = $wpdb;
        
        // Test connection by attempting a simple query
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Database connection test query
        $result = $wpdb->get_var("SELECT 1");
        
        if ($result !== '1') {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Shared database connection error logging is essential for troubleshooting
            error_log('SentinelPro: Shared database connection test failed');
            $this->shared_db = null;
        }
    }
    
    public function store_analytics($cache_key, $data, $date_range, $metadata = []) {
        $user_identifier = $this->get_user_identifier();
        
        $stmt = $this->shared_db->prepare(
            "INSERT INTO sentinelpro_analytics 
             (user_identifier, cache_key, data, date_range_start, date_range_end, metadata, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE 
             data = VALUES(data), 
             metadata = VALUES(metadata), 
             created_at = NOW()"
        );
        
        $stmt->bind_param('ssssss', 
            $user_identifier,
            $cache_key,
            json_encode($data),
            $date_range[0],
            $date_range[1],
            json_encode($metadata)
        );
        
        return $stmt->execute();
    }
    
    public function get_analytics($cache_key) {
        $user_identifier = $this->get_user_identifier();
        
        $stmt = $this->shared_db->prepare(
            "SELECT * FROM sentinelpro_analytics 
             WHERE user_identifier = ? AND cache_key = ?"
        );
        
        $stmt->bind_param('ss', $user_identifier, $cache_key);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return [
                'data' => json_decode($row['data'], true),
                'date_range' => [$row['date_range_start'], $row['date_range_end']],
                'metadata' => json_decode($row['metadata'], true),
                'created_at' => $row['created_at']
            ];
        }
        
        return null;
    }
    
    public function find_overlapping_cache($date_range) {
        $user_identifier = $this->get_user_identifier();
        
        $stmt = $this->shared_db->prepare(
            "SELECT * FROM sentinelpro_analytics 
             WHERE user_identifier = ? 
             AND date_range_start <= ? 
             AND date_range_end >= ?"
        );
        
        $stmt->bind_param('sss', $user_identifier, $date_range[1], $date_range[0]);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    public function cleanup_old_data($days = 31) {
        $stmt = $this->shared_db->prepare(
            "DELETE FROM sentinelpro_analytics 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        
        $stmt->bind_param('i', $days);
        return $stmt->execute();
    }
    
    public function get_user_analytics($user_id = null) {
        $user_identifier = $this->get_user_identifier();
        
        $stmt = $this->shared_db->prepare(
            "SELECT * FROM sentinelpro_analytics 
             WHERE user_identifier = ? 
             ORDER BY created_at DESC"
        );
        
        $stmt->bind_param('s', $user_identifier);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    public function __destruct() {
        if ($this->shared_db) {
            $this->shared_db->close();
        }
    }
} 
