<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SentinelPro Cron Handler
 * Handles daily automated tasks for analytics data
 */
class SentinelPro_Cron_Handler {
    
    private static $instance = null;
    
    public function __construct() {
        // No longer using database cache
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Main cron job function - runs daily at 11:22 AM
     */
    public function daily_analytics_fetch() {
        // Starting daily cleanup
        
        try {
            // Clean up old data (older than 31 days)
            $this->cleanup_old_data();
            
            // Check for custom dimension changes and update database
            $this->process_custom_dimension_changes();
            
            // Daily cleanup completed successfully
            
        } catch (Exception $e) {
            // Error in daily cleanup - silently continue
        }
    }

    /**
     * Process custom dimension changes and update database accordingly
     */
    private function process_custom_dimension_changes(): void {
        global $wpdb;
        
        try {
            // Get all properties that have dimension changes
            $options = get_option('sentinelpro_options', []);
            $property_id = $options['property_id'] ?? '';
            
            if (empty($property_id)) {
                return;
            }
            
            $dimension_changes = get_option("sentinelpro_dimension_changes_{$property_id}", null);
            
            if (!$dimension_changes || !isset($dimension_changes['timestamp'])) {
                return; // No changes to process
            }
            
            // Check if changes are recent (within last 24 hours)
            if (time() - $dimension_changes['timestamp'] > 86400) {
                return; // Changes are too old
            }
            
            $table_name = $wpdb->prefix . 'sentinelpro_analytics_events';
            
            // Process added dimensions
            if (!empty($dimension_changes['added'])) {
                foreach ($dimension_changes['added'] as $dimension) {
                    $column_name = 'dimension_' . $this->sanitize_column_name($dimension);
                    
                    // Check if column already exists
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Column existence check for custom analytics table
                    $column_exists = $wpdb->get_results($wpdb->prepare(
                        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                         WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                        DB_NAME, $table_name, $column_name
                    ));
                    
                    if (empty($column_exists)) {
                        // Add the new column (using validated table and column names)
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Add column to custom analytics table, table and column names are validated
                        $wpdb->query($wpdb->prepare("ALTER TABLE %s ADD COLUMN %s VARCHAR(500) NULL", $table_name, $column_name));
                        
                        // Add index for the new column
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Add index to custom analytics table, table and column names are validated
                        $wpdb->query($wpdb->prepare("ALTER TABLE %s ADD INDEX %s (%s)", $table_name, "idx_{$column_name}", $column_name));
                        
                        // Column added successfully
                    }
                }
            }
            
            // Process removed dimensions (keep columns but set data to blank)
            if (!empty($dimension_changes['removed'])) {
                foreach ($dimension_changes['removed'] as $dimension) {
                    $column_name = 'dimension_' . $this->sanitize_column_name($dimension);
                    
                    // Check if column exists
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Column existence check for custom analytics table
                    $column_exists = $wpdb->get_results($wpdb->prepare(
                        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                         WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                        DB_NAME, $table_name, $column_name
                    ));
                    
                    if (!empty($column_exists)) {
                        // Set all existing data to blank for this dimension
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Update data in custom analytics table, table and column names are validated
                        $wpdb->query($wpdb->prepare("UPDATE %s SET %s = '' WHERE %s IS NOT NULL", $table_name, $column_name, $column_name));
                        
                        // Data cleared for removed dimension
                    }
                }
            }
            
            // Clear the changes flag after processing
            delete_option("sentinelpro_dimension_changes_{$property_id}");
            
            // Successfully processed custom dimension changes
            
        } catch (Exception $e) {
            // Error processing custom dimension changes - silently continue
        }
    }

    /**
     * Sanitize column name for database use
     */
    private function sanitize_column_name(string $dimension): string {
        // Remove any non-alphanumeric characters and convert to lowercase
        return strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $dimension));
    }
    
    /**
     * Fetch analytics data for each dimension
     */
    private function fetch_dimension_data($dimensions, $start_date, $end_date, $property_id) {
        // This method is now deprecated since we're using the new analytics_events table
        // The data will be imported directly via the import script
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cron job logging for monitoring and debugging
        error_log("SentinelPro: fetch_dimension_data is deprecated - use import script instead");
    }
    
    /**
     * Fetch analytics data for all posts
     */
    private function fetch_posts_data($start_date, $end_date, $property_id) {
        // This method is now deprecated since we're using the new analytics_events table
        // The data will be imported directly via the import script
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cron job logging for monitoring and debugging
        error_log("SentinelPro: fetch_posts_data is deprecated - use import script instead");
    }
    
    /**
     * Clean up data older than 31 days
     */
    private function cleanup_old_data() {
        global $wpdb;
        
        try {
            // Clean up analytics events table
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup old data from custom analytics table
            $events_deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}sentinelpro_analytics_events 
                     WHERE date < DATE_SUB(NOW(), INTERVAL %d DAY)",
                    31
                )
            );
            
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cron job logging for monitoring and debugging
            error_log("SentinelPro: Cleaned up old analytics events data - Events: {$events_deleted}");
            
        } catch (Exception $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cron job error logging for monitoring and debugging
            error_log("SentinelPro: Error cleaning up old data: " . $e->getMessage());
        }
    }
    
    /**
     * Schedule the daily cron job
     */
    public function schedule_daily_job() {
        if (!wp_next_scheduled('sentinelpro_daily_analytics_fetch')) {
                    // Schedule for 11:22 AM daily
        $timestamp = strtotime('tomorrow 11:22 AM');
        wp_schedule_event($timestamp, 'daily', 'sentinelpro_daily_analytics_fetch');
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cron job logging for monitoring and debugging
        error_log('SentinelPro: Scheduled daily analytics fetch for 11:22 AM');
        }
    }
    
    /**
     * Clear the scheduled cron job
     */
    public function clear_scheduled_job() {
        wp_clear_scheduled_hook('sentinelpro_daily_analytics_fetch');
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cron job logging for monitoring and debugging
        error_log('SentinelPro: Cleared daily analytics fetch schedule');
    }
} 
