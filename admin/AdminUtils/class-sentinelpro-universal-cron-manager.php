<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- This file uses $wpdb->prepare() correctly throughout

/**
 * SentinelPro Universal Cron Manager
 * Handles cron jobs across all hosting providers automatically
 */

class SentinelPro_Universal_Cron_Manager {
    
    private static $instance = null;
    private $cron_hook = 'sentinelpro_daily_data_fetch';
    private $cron_interval = 'daily';
    private $cron_time = '04:00'; // 4:00 AM
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Only initialize cron system when WordPress is fully loaded
        add_action('wp_loaded', array($this, 'init_cron_system'));
        add_action($this->cron_hook, array($this, 'run_daily_data_fetch'));
        // Removed all admin interface AJAX handlers - cron jobs run silently in background
        
        // Force reschedule to 4:00 AM if this is a new version
        add_action('init', array($this, 'check_and_reschedule_to_4am'));
    }
    
    /**
     * Initialize the cron system
     */
    public function init_cron_system() {
        // Only initialize once per request
        static $initialized = false;
        if ($initialized) {
            return;
        }
        $initialized = true;
        
        // Check if we've already initialized recently (within 5 minutes)
        $last_init = get_transient('sentinelpro_cron_last_init');
        if ($last_init && (time() - $last_init) < 300) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cron initialization status logging is essential for troubleshooting
            error_log("SentinelPro: Cron system already initialized recently, skipping");
        }
            return;
        }
        
        // Check if cron is already scheduled
        $cron_count = $this->get_cron_count();
        if ($cron_count > 1) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Duplicate cron warning logging is essential for troubleshooting
            error_log("SentinelPro: WARNING - Found {$cron_count} cron jobs scheduled, clearing duplicates");
            $this->clear_cron();
            $this->clear_duplicate_crons();
        }
        
        if (!$this->is_cron_scheduled()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cron scheduling status logging is essential for troubleshooting
                error_log("SentinelPro: Cron not scheduled, initializing...");
            }
            
            // Clear any existing cron jobs to prevent duplicates
            $this->clear_cron();
            $this->clear_duplicate_crons(); // Clear any duplicate cron jobs
            
            // Schedule the cron job
            $this->schedule_cron();
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cron scheduling status logging is essential for troubleshooting
                error_log("SentinelPro: Cron already scheduled, skipping initialization");
            }
        }
        
        // Set transient to prevent frequent re-initialization
        set_transient('sentinelpro_cron_last_init', time(), 300);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cron initialization logging is essential for troubleshooting
            error_log("SentinelPro: Cron system initialization completed at " . gmdate('Y-m-d H:i:s'));
        }
        
        // Add custom cron interval if needed
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
    }
    
    /**
     * Schedule the cron job
     */
    public function schedule_cron() {
        // Calculate next run time (4:00 AM today or tomorrow)
        $next_run = $this->calculate_next_run_time();
        
        // Schedule the event
        wp_schedule_event($next_run, $this->cron_interval, $this->cron_hook);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cron scheduling logging is essential for troubleshooting
            error_log("SentinelPro: Scheduled daily data fetch for " . gmdate('Y-m-d H:i:s', $next_run));
        }
        
        return $next_run;
    }
    
    /**
     * Check if the cron job is already scheduled
     */
    public function is_cron_scheduled() {
        $cron_jobs = _get_cron_array();
        
        foreach ($cron_jobs as $timestamp => $cron) {
            if (isset($cron[$this->cron_hook])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get the count of scheduled cron jobs for this hook
     */
    public function get_cron_count() {
        $cron_jobs = _get_cron_array();
        $count = 0;
        
        foreach ($cron_jobs as $timestamp => $cron) {
            if (isset($cron[$this->cron_hook])) {
                $count += count($cron[$this->cron_hook]);
            }
        }
        
        return $count;
    }
    
    /**
     * Calculate the next run time (4:00 AM)
     */
    public function calculate_next_run_time() {
        // Get the superuser's timezone preference, default to WordPress timezone
        $superuser_timezone = $this->get_superuser_timezone();
        
        // Create DateTime object in the superuser's timezone
        $timezone = new DateTimeZone($superuser_timezone);
        $now = new DateTime('now', $timezone);
        
        // Calculate 4:00 AM today in the superuser's timezone
        $today_4am = new DateTime('today 4:00 AM', $timezone);
        
        // If it's past 4:00 AM today, schedule for tomorrow
        if ($now >= $today_4am) {
            $tomorrow_4am = new DateTime('tomorrow 4:00 AM', $timezone);
            return $tomorrow_4am->getTimestamp();
        }
        
        return $today_4am->getTimestamp();
    }
    
    /**
     * Get the superuser's timezone preference for cron scheduling
     */
    public function get_superuser_timezone() {
        // First check the option-based timezone setting
        $timezone = get_option('sentinelpro_cron_timezone');
        if ($timezone && in_array($timezone, DateTimeZone::listIdentifiers())) {
            return $timezone;
        }
        
        // Fallback to WordPress timezone setting
        $wp_timezone = get_option('timezone_string');
        if ($wp_timezone && in_array($wp_timezone, DateTimeZone::listIdentifiers())) {
            return $wp_timezone;
        }
        
        // Final fallback to UTC
        return 'UTC';
    }
    
    /**
     * Set the superuser's timezone preference for cron scheduling
     */
    public function set_superuser_timezone($timezone) {
        if (in_array($timezone, DateTimeZone::listIdentifiers())) {
            update_option('sentinelpro_cron_timezone', $timezone);
            
            // Reschedule the cron job with the new timezone
            $this->reschedule_cron();
            
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Timezone update logging is essential for troubleshooting
        error_log("SentinelPro: Updated cron timezone to {$timezone}");
            return true;
        }
        
        return false;
    }
    
    /**
     * Get available timezones for selection
     */
    public function get_available_timezones() {
        $timezones = DateTimeZone::listIdentifiers();
        $formatted = [];
        
        foreach ($timezones as $timezone) {
            $date = new DateTime('now', new DateTimeZone($timezone));
            $offset = $date->format('P');
            $formatted[$timezone] = "({$offset}) {$timezone}";
        }
        
        return $formatted;
    }
    
    /**
     * Add custom cron intervals
     */
    public function add_cron_intervals($schedules) {
        $schedules['sentinelpro_daily'] = array(
            'interval' => 86400, // 24 hours
            'display' => 'Once Daily (4:00 AM)'
        );
        return $schedules;
    }
    
    /**
     * Run the daily data fetch
     */
    public function run_daily_data_fetch() {
        // Prevent duplicate execution with a lock
        $lock_key = 'sentinelpro_cron_running';
        $lock_timeout = 300; // 5 minutes
        
        // Check if cron is already running
        $is_running = get_transient($lock_key);
        if ($is_running) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cron duplicate execution logging is essential for troubleshooting
        error_log("SentinelPro: Daily data fetch already running, skipping duplicate execution");
            return;
        }
        
        // Check if we've already run today
        $last_run = get_option('sentinelpro_last_cron_run');
        if ($last_run) {
            $last_run_date = gmdate('Y-m-d', $last_run);
            $today = gmdate('Y-m-d');
            if ($last_run_date === $today) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cron duplicate execution logging is essential for troubleshooting
                error_log("SentinelPro: Daily data fetch already completed today ({$today}), skipping duplicate execution");
                return;
            }
        }
        
        // Set lock to prevent duplicate execution
        set_transient($lock_key, time(), $lock_timeout);
        
        // Log the trigger source for debugging
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Backtrace is essential for cron debugging
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $trigger_source = 'unknown';
        foreach ($backtrace as $trace) {
            if (isset($trace['function']) && $trace['function'] === 'do_action') {
                $trigger_source = 'WordPress cron system';
                break;
            } elseif (isset($trace['file']) && strpos($trace['file'], 'manual-cron-trigger') !== false) {
                $trigger_source = 'manual trigger script';
                break;
            } elseif (isset($trace['file']) && strpos($trace['file'], 'trigger-cron') !== false) {
                $trigger_source = 'trigger script';
                break;
            }
        }
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cron trigger source logging is essential for troubleshooting
        error_log("SentinelPro: Cron triggered by: {$trigger_source}");
        
        try {
            // Get yesterday's date (like import-full-dimensions.php does)
            $yesterday = gmdate('Y-m-d', strtotime('-1 day'));
            $today = gmdate('Y-m-d');
            
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cron execution logging is essential for troubleshooting
            error_log("SentinelPro: Starting daily data fetch for {$yesterday} at " . gmdate('Y-m-d H:i:s'));
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Process information logging is essential for troubleshooting
            error_log("SentinelPro: Process ID: " . getmypid() . ", Memory usage: " . memory_get_usage(true));
            
            // Get API credentials
            $creds = $this->get_api_credentials();
            if (!$creds['property_id'] || !$creds['api_key'] || !$creds['account_name']) {
                throw new Exception("API credentials not configured");
            }
            
            // Import yesterday's analytics data (like import-full-dimensions.php)
            $analytics_result = $this->import_yesterday_analytics_data($yesterday, $creds);
            
            // Import yesterday's post metrics
            $post_result = $this->import_yesterday_post_data($yesterday, $creds);
            
            // Clean up old post data (older than 31 days)
            $cleanup_result = $this->cleanup_old_post_data();
            
            // Log success
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cron completion logging is essential for troubleshooting
            error_log("SentinelPro: Daily data fetch completed successfully at " . gmdate('Y-m-d H:i:s'));
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Analytics result logging is essential for troubleshooting
            error_log("SentinelPro: Analytics - Records: {$analytics_result['imported']}, Bots filtered: {$analytics_result['bots_filtered']}, Errors: {$analytics_result['errors']}");
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Post result logging is essential for troubleshooting
            error_log("SentinelPro: Posts - Processed: {$post_result['posts_processed']}, Records: {$post_result['records_created']}, Bots filtered: {$post_result['bots_filtered']}, Errors: {$post_result['errors']}");
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cleanup result logging is essential for troubleshooting
            error_log("SentinelPro: Cleanup - Old records removed: {$cleanup_result['removed']}");
            
            // Update last run time
            update_option('sentinelpro_last_cron_run', current_time('timestamp'));
            
            // Clear the lock on successful completion
            delete_transient($lock_key);
            
        } catch (Exception $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cron error logging is essential for troubleshooting
            error_log("SentinelPro: Daily data fetch failed: " . $e->getMessage());
            
            // Update last error time
            update_option('sentinelpro_last_cron_error', current_time('timestamp'));
            update_option('sentinelpro_last_cron_error_message', $e->getMessage());
            
            // Clear the lock on error
            delete_transient($lock_key);
        }
    }
    
    /**
     * Run manual data fetch (bypasses daily run checks)
     */
    public function run_manual_data_fetch() {
        // Prevent duplicate execution with a lock
        $lock_key = 'sentinelpro_manual_cron_running';
        $lock_timeout = 300; // 5 minutes
        
        // Check if manual cron is already running
        $is_running = get_transient($lock_key);
        if ($is_running) {
            // Check if the lock is stale (older than 5 minutes)
            $lock_time = intval($is_running);
            if ((time() - $lock_time) > 300) { // 5 minutes
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Manual cron stale lock cleanup
                error_log("SentinelPro: Clearing stale manual cron lock");
                delete_transient($lock_key);
            } else {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Manual cron duplicate execution logging is essential for troubleshooting
                error_log("SentinelPro: Manual data fetch already running, skipping duplicate execution");
                return false;
            }
        }
        
        // Set lock to prevent duplicate execution
        set_transient($lock_key, time(), $lock_timeout);
        
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Manual cron trigger logging is essential for troubleshooting
        error_log("SentinelPro: Manual data fetch triggered by manual script");
        
        try {
            // Get yesterday's date (like import-full-dimensions.php does)
            $yesterday = gmdate('Y-m-d', strtotime('-1 day'));
            $today = gmdate('Y-m-d');
            
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Manual cron execution logging is essential for troubleshooting
            error_log("SentinelPro: Starting manual data fetch for {$yesterday} at " . gmdate('Y-m-d H:i:s'));
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Process information logging is essential for troubleshooting
            error_log("SentinelPro: Process ID: " . getmypid() . ", Memory usage: " . memory_get_usage(true));
            
            // Get API credentials
            $creds = $this->get_api_credentials();
            if (!$creds['property_id'] || !$creds['api_key'] || !$creds['account_name']) {
                throw new Exception("API credentials not configured");
            }
            
            // Import yesterday's analytics data (like import-full-dimensions.php)
            $analytics_result = $this->import_yesterday_analytics_data($yesterday, $creds);
            
            // Import yesterday's post metrics
            $post_result = $this->import_yesterday_post_data($yesterday, $creds);
            
            // Clean up old post data (older than 31 days)
            $cleanup_result = $this->cleanup_old_post_data();
            
            // Log success
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Manual cron completion logging is essential for troubleshooting
            error_log("SentinelPro: Manual data fetch completed successfully at " . gmdate('Y-m-d H:i:s'));
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Manual analytics result logging is essential for troubleshooting
            error_log("SentinelPro: Analytics - Records: {$analytics_result['imported']}, Bots filtered: {$analytics_result['bots_filtered']}, Errors: {$analytics_result['errors']}");
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Manual post result logging is essential for troubleshooting
            error_log("SentinelPro: Posts - Processed: {$post_result['posts_processed']}, Records: {$post_result['records_created']}, Bots filtered: {$post_result['bots_filtered']}, Errors: {$post_result['errors']}");
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Manual cleanup result logging is essential for troubleshooting
            error_log("SentinelPro: Cleanup - Old records removed: {$cleanup_result['removed']}");
            
            // Update last run time
            update_option('sentinelpro_last_cron_run', current_time('timestamp'));
            
            // Clear the lock on successful completion
            delete_transient($lock_key);
            
            return true;
            
        } catch (Exception $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Manual cron error logging is essential for troubleshooting
            error_log("SentinelPro: Manual data fetch failed: " . $e->getMessage());
            
            // Update last error time
            update_option('sentinelpro_last_cron_error', current_time('timestamp'));
            update_option('sentinelpro_last_cron_error_message', $e->getMessage());
            
            // Clear the lock on error
            delete_transient($lock_key);
            
            throw $e; // Re-throw the exception for the calling script to handle
        }
    }
    
    /**
     * Get API credentials
     */
    private function get_api_credentials() {
        $options = get_option('sentinelpro_options', []);
        return [
            'property_id' => $options['property_id'] ?? '',
            'api_key' => SentinelPro_Security_Manager::get_api_key(),
            'account_name' => $options['account_name'] ?? ''
        ];
    }
    
    /**
     * Import yesterday's analytics data (like import-full-dimensions.php)
     */
    private function import_yesterday_analytics_data($yesterday, $creds, $dry_run = false) {
        $db_manager = SentinelPro_Database_Manager::get_instance();
        $total_imported = 0;
        $total_errors = 0;
        $total_bots_filtered = 0;
        $sample_data = array();
        
        try {
            // Use all available dimensions (like import-full-dimensions.php)
            $dimensions = [
                "date", "device", "geo", "referrer", "os", "browser", "intent",
                "adsTemplate", "contentType", "articleType", "primaryTag", 
                "primaryCategory", "networkCategory", "segment", "initiative", "publishDate"
            ];
            
            // Initialize pagination variables
            $page_number = 1;
            $page_size = 1000; // Maximum page size before HTTP 400 error
            $max_pages = 50; // Safety limit to prevent infinite loops
            $all_records = [];
            
            // Fetch all pages of data
            while ($page_number <= $max_pages) {
                // Build API request data for current page
                $data = [
                                            "filters" => [
                            "date" => [
                                "gte" => $yesterday,
                                "lt" => gmdate('Y-m-d', strtotime($yesterday . ' +1 day'))
                            ],
                        "propertyId" => [
                            "in" => [$creds['property_id']]
                        ],
                    ],
                    "granularity" => "daily",
                    "metrics" => ["sessions", "views", "visits"],
                    "dimensions" => $dimensions,
                    "orderBy" => ["date" => "asc"],
                    "pagination" => [
                        "pageSize" => $page_size,
                        "pageNumber" => $page_number
                    ]
                ];
                
                $jsonData = json_encode($data, JSON_UNESCAPED_SLASHES);
                $endpoint = "https://{$creds['account_name']}.sentinelpro.com/api/v1/traffic/";
                $finalUrl = $endpoint . '?data=' . rawurlencode($jsonData);
                
                // Make API call for current page using WordPress HTTP API
                $response = wp_remote_get($finalUrl, [
                    'timeout' => 60,
                    'headers' => [
                        'SENTINEL-API-KEY' => $creds['api_key'],
                        'Accept' => 'application/json'
                    ]
                ]);
                
                if (is_wp_error($response)) {
                    throw new Exception("API request failed: " . $response->get_error_message());
                }
                
                $status = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);
                
                if ($status !== 200) {
                    throw new Exception("API returned status code: {$status} for page {$page_number}");
                }
                
                $api_response = json_decode($response_body, true);
                if (!$api_response || !isset($api_response['data'])) {
                    throw new Exception("Invalid API response format for page {$page_number}");
                }
                
                $page_records = $api_response['data'];
                $records_count = count($page_records);
                $total_count = $api_response['totalCount'] ?? 0;
                $total_pages = $api_response['totalPage'] ?? 1;
                
                // Log pagination progress
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- API pagination logging is essential for troubleshooting
                error_log("SentinelPro: Fetched page {$page_number}/{$total_pages} with {$records_count} records (Total: {$total_count})");
                
                // Add page records to all records
                $all_records = array_merge($all_records, $page_records);
                
                // Check if we've fetched all pages
                if ($records_count === 0 || $page_number >= $total_pages) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- API completion logging is essential for troubleshooting
                    error_log("SentinelPro: Completed fetching all {$total_pages} pages with " . count($all_records) . " total records");
                    break;
                }
                
                $page_number++;
            }
            
            // Process and insert all collected records
            foreach ($all_records as $record) {
                // Skip bot traffic
                if (isset($record['device']) && strtolower($record['device']) === 'bot') {
                    $total_bots_filtered++;
                    continue;
                }
                
                try {
                    // Prepare base data
                    $insert_data = [
                        'date' => $record['date'],
                        'device' => $record['device'] ?? 'Unknown',
                        'geo' => $record['geo'] ?? '',
                        'referrer' => $record['referrer'] ?? '',
                        'os' => $record['os'] ?? '',
                        'browser' => $record['browser'] ?? '',
                        'sessions' => intval($record['sessions'] ?? 0),
                        'views' => intval($record['views'] ?? 0),
                        'visits' => intval($record['visits'] ?? 0)
                    ];
                    
                    // Add all custom dimensions
                    foreach ($record as $key => $value) {
                        if (in_array($key, ['date', 'device', 'geo', 'referrer', 'os', 'browser', 'sessions', 'views', 'visits'])) {
                            continue;
                        }
                        
                        $db_column = strtolower($key);
                        global $wpdb;
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column existence check for custom table with validated table name
                        $column_exists = $wpdb->get_var($wpdb->prepare(
                            "SHOW COLUMNS FROM {$db_manager->get_table_name('analytics_events')} LIKE %s",
                            'dimension_' . $db_column
                        ));
                        
                        if ($column_exists) {
                            $insert_data['dimension_' . $db_column] = $value;
                        }
                    }
                    
                    if ($dry_run) {
                        // In dry run mode, just collect sample data
                        if (count($sample_data) < 10) {
                            $sample_data[] = [
                                'date' => $insert_data['date'],
                                'device' => $insert_data['device'],
                                'country' => $insert_data['geo'],
                                'sessions' => $insert_data['sessions'],
                                'views' => $insert_data['views'],
                                'visits' => $insert_data['visits']
                            ];
                        }
                        $total_imported++;
                    } else {
                        // Normal mode - insert into database
                        $result = $db_manager->insert_analytics_event($insert_data);
                        if ($result !== false) {
                            $total_imported++;
                        } else {
                            $total_errors++;
                        }
                    }
                    
                } catch (Exception $e) {
                    $total_errors++;
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Analytics record error logging is essential for troubleshooting
                    error_log("SentinelPro: Error processing analytics record: " . $e->getMessage());
                }
            }
            
        } catch (Exception $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Analytics import error logging is essential for troubleshooting
            error_log("SentinelPro: Error importing analytics data: " . $e->getMessage());
            $total_errors++;
        }
        
        return [
            'imported' => $total_imported,
            'bots_filtered' => $total_bots_filtered,
            'errors' => $total_errors,
            'total_records' => $total_imported,
            'sample_data' => $sample_data
        ];
    }
    
    /**
     * Import yesterday's post data
     */
    private function import_yesterday_post_data($yesterday, $creds, $dry_run = false) {
        $db_manager = SentinelPro_Database_Manager::get_instance();
        $posts_processed = 0;
        $records_created = 0;
        $errors = 0;
        $total_bots_filtered = 0;
        $sample_data = array();
        
        try {
            // Get all published posts
            $posts = get_posts([
                'post_type' => 'post',
                'post_status' => 'publish',
                'numberposts' => -1
            ]);
            
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Post processing logging is essential for troubleshooting
            error_log("SentinelPro: Processing " . count($posts) . " published posts for {$yesterday}");
            
            foreach ($posts as $post) {
                try {
                    $posts_processed++;
                    
                    // Get post metrics from API with bot filtering
                    $post_metrics = $this->get_post_metrics($post, $creds);
                    
                    if ($post_metrics) {
                        // Track bot filtering
                        $total_bots_filtered += $post_metrics['bots_filtered'] ?? 0;
                        
                        // Insert post data
                        $post_data = [
                            'post_id' => $post->ID,
                            'date' => $yesterday,
                            'views' => $post_metrics['views'] ?? 0,
                            'sessions' => $post_metrics['sessions'] ?? 0,
                            'title' => $post->post_title,
                            'author' => get_the_author_meta('display_name', $post->post_author),
                            'categories' => $this->get_post_categories($post->ID),
                            'tags' => $this->get_post_tags($post->ID),
                            'date_published' => get_the_date('Y-m-d', $post->ID),
                            'post_url' => get_permalink($post->ID),
                            'created_at' => current_time('mysql')
                        ];
                        
                        if ($dry_run) {
                            // In dry run mode, just collect sample data
                            if (count($sample_data) < 10) {
                                $sample_data[] = [
                                    'post_id' => $post_data['post_id'],
                                    'title' => $post_data['title'],
                                    'date' => $post_data['date'],
                                    'sessions' => $post_data['sessions'],
                                    'views' => $post_data['views'],
                                    'bots_filtered' => $post_metrics['bots_filtered'] ?? 0
                                ];
                            }
                            $records_created++;
                        } else {
                            // Normal mode - insert into database
                            $result = $db_manager->insert_post_data($post_data);
                            if ($result !== false) {
                                $records_created++;
                                
                                // Log progress every 50 posts
                                if ($records_created % 50 === 0) {
                                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Post processing progress logging is essential for troubleshooting
            error_log("SentinelPro: Processed {$records_created} posts so far for {$yesterday}");
                                }
                            } else {
                                $errors++;
                                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Post data insertion error logging is essential for troubleshooting
                            error_log("SentinelPro: Failed to insert data for post {$post->ID}");
                            }
                        }
                    } else {
                        // No data found for this post (this is normal for some posts)
                        if ($dry_run && count($sample_data) < 5) {
                            $sample_data[] = [
                                'post_id' => $post->ID,
                                'title' => $post->post_title,
                                'date' => $yesterday,
                                'sessions' => 0,
                                'views' => 0,
                                'bots_filtered' => 0,
                                'note' => 'No data found'
                            ];
                        }
                    }
                    
                } catch (Exception $e) {
                    $errors++;
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Post processing error logging is essential for troubleshooting
                    error_log("SentinelPro: Error processing post {$post->ID}: " . $e->getMessage());
                }
            }
            
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Post import completion logging is essential for troubleshooting
        error_log("SentinelPro: Post data import completed for {$yesterday} - Posts: {$posts_processed}, Records: {$records_created}, Errors: {$errors}, Bots filtered: {$total_bots_filtered}");
            
        } catch (Exception $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Post import error logging is essential for troubleshooting
            error_log("SentinelPro: Error importing post data: " . $e->getMessage());
            $errors++;
        }
        
        return [
            'posts_processed' => $posts_processed,
            'records_created' => $records_created,
            'errors' => $errors,
            'bots_filtered' => $total_bots_filtered,
            'sample_data' => $sample_data
        ];
    }
    
    /**
     * Get metrics for a specific post from SentinelPro API
     */
    private function get_post_metrics($post, $creds) {
        try {
            $path = wp_parse_url(get_permalink($post->ID), PHP_URL_PATH);
            if (!$path) {
                return null;
            }
            
            $data = [
                "filters" => [
                    "propertyId" => [
                        "in" => [$creds['property_id']]
                    ],
                    "url" => [
                        "eq" => $path
                    ]
                ],
                "granularity" => "daily",
                "metrics" => ["sessions", "views", "visits"],
                "dimensions" => ["date", "device"], // Include device for bot filtering
                "orderBy" => ["date" => "asc"],
                "pagination" => [
                    "pageSize" => 1000,
                    "pageNumber" => 1
                ]
            ];
            
            $jsonData = json_encode($data, JSON_UNESCAPED_SLASHES);
            $endpoint = "https://{$creds['account_name']}.sentinelpro.com/api/v1/traffic/";
            $finalUrl = $endpoint . '?data=' . rawurlencode($jsonData);
            
            // Fetch all pages of data
            $all_data = [];
            $page_number = 1;
            $total_pages = 1;
            
            do {
                $data['pagination']['pageNumber'] = $page_number;
                $jsonData = json_encode($data, JSON_UNESCAPED_SLASHES);
                $finalUrl = $endpoint . '?data=' . rawurlencode($jsonData);
                
                // Make API call using WordPress HTTP API
                $response = wp_remote_get($finalUrl, [
                    'timeout' => 30,
                    'headers' => [
                        'SENTINEL-API-KEY' => $creds['api_key'],
                        'Accept' => 'application/json'
                    ]
                ]);
                
                if (is_wp_error($response)) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- API error logging is essential for troubleshooting
                    error_log("SentinelPro: API request failed for post {$post->ID} page {$page_number}: " . $response->get_error_message());
                    break;
                }
                
                $status = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);
                
                if ($status !== 200) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- API status error logging is essential for troubleshooting
                    error_log("SentinelPro: API returned status {$status} for post {$post->ID} page {$page_number}");
                    break;
                }
                
                $api_response = json_decode($response_body, true);
                if (!$api_response || !isset($api_response['data']) || !is_array($api_response['data'])) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- API response validation logging is essential for troubleshooting
                    error_log("SentinelPro: Invalid API response for post {$post->ID} page {$page_number}");
                    break;
                }
                
                // Get pagination info from first response
                if ($page_number === 1) {
                    $total_pages = isset($api_response['totalPage']) ? intval($api_response['totalPage']) : 1;
                    $total_count = isset($api_response['totalCount']) ? intval($api_response['totalCount']) : count($api_response['data']);
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Post pagination logging is essential for troubleshooting
                    error_log("SentinelPro: Post {$post->ID} has {$total_count} total records across {$total_pages} pages");
                }
                
                $all_data = array_merge($all_data, $api_response['data']);
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Post page fetching logging is essential for troubleshooting
                    error_log("SentinelPro: Fetched page {$page_number} for post {$post->ID} with " . count($api_response['data']) . " records");
                }
                
                $page_number++;
                
                // Safety check to prevent infinite loops
                if ($page_number > 20) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Safety limit logging is essential for troubleshooting
                    error_log("SentinelPro: Safety limit reached for post {$post->ID}, stopping pagination at page 20");
                    break;
                }
                
            } while ($page_number <= $total_pages);
            
            if (empty($all_data)) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- No data logging is essential for troubleshooting
                error_log("SentinelPro: No data retrieved for post {$post->ID}");
                return null;
            }
            
            // Filter out bot traffic and aggregate metrics
            $total_views = 0;
            $total_sessions = 0;
            $total_visits = 0;
            $bots_filtered = 0;
            
            foreach ($all_data as $record) {
                // Skip bot traffic
                $device = isset($record['device']) ? strtolower(trim($record['device'])) : '';
                if ($device === 'bot' || 
                    $device === 'crawler' || 
                    $device === 'spider' ||
                    strpos($device, 'bot') !== false ||
                    strpos($device, 'crawler') !== false ||
                    strpos($device, 'spider') !== false) {
                    $bots_filtered++;
                    continue;
                }
                
                // Add human traffic metrics
                $total_views += intval($record['views'] ?? 0);
                $total_sessions += intval($record['sessions'] ?? 0);
                $total_visits += intval($record['visits'] ?? 0);
            }
            
            // Log bot filtering results for debugging
            if ($bots_filtered > 0) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Bot filtering logging is essential for troubleshooting
            error_log("SentinelPro: Filtered {$bots_filtered} bot records for post {$post->ID}");
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Post metrics logging is essential for troubleshooting
            error_log("SentinelPro: Post {$post->ID} final metrics - Sessions: {$total_sessions}, Views: {$total_views}, Visits: {$total_visits}");
            }
            
            return [
                'views' => $total_views,
                'sessions' => $total_sessions,
                'visits' => $total_visits,
                'bots_filtered' => $bots_filtered
            ];
            
        } catch (Exception $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Post metrics error logging is essential for troubleshooting
            error_log("SentinelPro: Error getting post metrics for post {$post->ID}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get post categories
     */
    private function get_post_categories($post_id) {
        $categories = get_the_category($post_id);
        return implode(', ', array_map(function($cat) {
            return $cat->name;
        }, $categories));
    }
    
    /**
     * Get post tags
     */
    private function get_post_tags($post_id) {
        $tags = get_the_tags($post_id);
        if (!$tags) return '';
        return implode(', ', array_map(function($tag) {
            return $tag->name;
        }, $tags));
    }
    
    /**
     * Clean up old post data (older than 31 days)
     */
    private function cleanup_old_post_data($dry_run = false) {
        $db_manager = SentinelPro_Database_Manager::get_instance();
        $removed = 0;
        $old_records_found = 0;
        $sample_old_data = array();
        
        try {
            // Calculate cutoff date (31 days ago)
            $cutoff_date = gmdate('Y-m-d', strtotime('-31 days'));
            $cutoff_datetime = $cutoff_date . ' 00:00:00';
            
            $table_name = $db_manager->get_table_name('posts');
            
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cleanup start logging is essential for troubleshooting
            error_log("SentinelPro: Starting cleanup of old post data - cutoff date: {$cutoff_date}");
            
            // First, count how many old records exist
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Count query for custom table cleanup with validated table name
            $old_records_found = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE created_at < %s",
                $cutoff_datetime
            ));
            
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cleanup count logging is essential for troubleshooting
            error_log("SentinelPro: Found {$old_records_found} post records older than 31 days");
            
            if ($old_records_found > 0) {
                if ($dry_run) {
                    // In dry run mode, just sample old records
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Sample query for custom table cleanup with validated table name
                    $old_records = $wpdb->get_results($wpdb->prepare(
                        "SELECT post_id, title, created_at FROM {$table_name} WHERE created_at < %s ORDER BY created_at ASC LIMIT 10",
                        $cutoff_datetime
                    ));
                    
                    foreach ($old_records as $record) {
                        $days_old = floor((time() - strtotime($record->created_at)) / (24 * 60 * 60));
                        $sample_old_data[] = [
                            'post_id' => $record->post_id,
                            'title' => $record->title,
                            'created_at' => $record->created_at,
                            'days_old' => $days_old
                        ];
                    }
                    
                    $removed = 0; // No actual deletion in dry run
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Dry run logging is essential for troubleshooting
                    error_log("SentinelPro: DRY RUN - Would delete {$old_records_found} old post records");
                    
                } else {
                    // Normal mode - delete old records
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Delete query for custom table cleanup with validated table name
                    $result = $wpdb->query($wpdb->prepare(
                        "DELETE FROM {$table_name} WHERE created_at < %s",
                        $cutoff_datetime
                    ));
                    
                    if ($result !== false) {
                        $removed = $result;
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cleanup success logging is essential for troubleshooting
                        error_log("SentinelPro: Successfully deleted {$removed} old post records (older than 31 days)");
                    } else {
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cleanup error logging is essential for troubleshooting
                        error_log("SentinelPro: Error deleting old post records: " . $wpdb->last_error);
                    }
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cleanup status logging is essential for troubleshooting
                    error_log("SentinelPro: No old post records found to clean up");
                }
            }
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cleanup exception logging is essential for troubleshooting
                error_log("SentinelPro: Error cleaning up old post data: " . $e->getMessage());
            }
        }
        
        return [
            'removed' => $removed,
            'old_records_found' => $old_records_found,
            'sample_old_data' => $sample_old_data
        ];
    }
    
    /**
     * Get cron status information
     */
    public function get_cron_status() {
        $next_run = wp_next_scheduled($this->cron_hook);
        $last_run = get_option('sentinelpro_last_cron_run');
        $last_error = get_option('sentinelpro_last_cron_error');
        $last_error_message = get_option('sentinelpro_last_cron_error_message');
        $superuser_timezone = $this->get_superuser_timezone();
        
        // Check for duplicate cron jobs
        $duplicate_crons = $this->check_for_duplicate_crons();
        
        // Format next run time in the superuser's timezone
        $next_run_formatted = null;
        if ($next_run) {
            $timezone = new DateTimeZone($superuser_timezone);
            $next_run_date = new DateTime('@' . $next_run);
            $next_run_date->setTimezone($timezone);
            $next_run_formatted = $next_run_date->format('Y-m-d H:i:s') . ' (' . $superuser_timezone . ')';
        }
        
        $status = array(
            'is_scheduled' => $next_run !== false,
            'next_run' => $next_run_formatted,
            'next_run_timestamp' => $next_run,
            'time_until_next' => $next_run ? $next_run - time() : null,
            'last_run' => $last_run ? gmdate('Y-m-d H:i:s', $last_run) : null,
            'last_error' => $last_error ? gmdate('Y-m-d H:i:s', $last_error) : null,
            'last_error_message' => $last_error_message,
            'wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            'cron_hook' => $this->cron_hook,
            'cron_interval' => $this->cron_interval,
            'cron_time' => $this->cron_time,
            'timezone' => $superuser_timezone,
            'duplicate_crons_found' => count($duplicate_crons) > 1,
            'duplicate_crons' => $duplicate_crons
        );
        
        return $status;
    }
    
    /**
     * Test the cron system manually
     */
    public function test_cron() {
        try {
            // Run the daily data fetch manually
            $this->run_daily_data_fetch();
            
            return array(
                'success' => true,
                'message' => 'Cron test completed successfully',
                'timestamp' => current_time('Y-m-d H:i:s')
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Cron test failed: ' . $e->getMessage(),
                'timestamp' => current_time('Y-m-d H:i:s')
            );
        }
    }

    /**
     * Test the daily data fetch with dry run option
     */
    public function test_daily_data_fetch($test_date = null, $dry_run = false) {
        try {
            // Use provided date or default to yesterday
            $yesterday = $test_date ?: gmdate('Y-m-d', strtotime('-1 day'));
            $today = gmdate('Y-m-d');
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Test data fetch logging is essential for troubleshooting
                error_log("SentinelPro: Starting test data fetch for {$yesterday} (dry_run: " . ($dry_run ? 'true' : 'false') . ") at " . gmdate('Y-m-d H:i:s'));
            }
            
            // Get API credentials
            $creds = $this->get_api_credentials();
            if (!$creds['property_id'] || !$creds['api_key'] || !$creds['account_name']) {
                throw new Exception("API credentials not configured");
            }
            
            $result = array(
                'success' => true,
                'analytics' => array(),
                'posts' => array(),
                'cleanup' => array()
            );
            
            // Test analytics data import
            $analytics_result = $this->import_yesterday_analytics_data($yesterday, $creds, $dry_run);
            $result['analytics'] = $analytics_result;
            
            // Test post data import
            $post_result = $this->import_yesterday_post_data($yesterday, $creds, $dry_run);
            $result['posts'] = $post_result;
            
            // Test cleanup
            $cleanup_result = $this->cleanup_old_post_data($dry_run);
            $result['cleanup'] = $cleanup_result;
            
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Test completion logging is essential for troubleshooting
            error_log("SentinelPro: Test data fetch completed successfully at " . gmdate('Y-m-d H:i:s'));
            
            return $result;
            
        } catch (Exception $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Test error logging is essential for troubleshooting
            error_log("SentinelPro: Test data fetch failed: " . $e->getMessage());
            
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Get recent logs from debug.log
     */
    public function get_recent_logs($limit = 10) {
        $log_file = WP_CONTENT_DIR . '/debug.log';
        if (!file_exists($log_file)) {
            return array();
        }
        
        $log_content = file_get_contents($log_file);
        $sentinelpro_logs = array_filter(explode("\n", $log_content), function($line) {
            return strpos($line, 'SentinelPro') !== false;
        });
        
        if (empty($sentinelpro_logs)) {
            return array();
        }
        
        // Get the most recent logs
        $recent_logs = array_slice(array_reverse($sentinelpro_logs), 0, $limit);
        
        return $recent_logs;
    }
    
    /**
     * Clear the cron job
     */
    public function clear_cron() {
        wp_clear_scheduled_hook($this->cron_hook);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cron clearing logging is essential for troubleshooting
            error_log("SentinelPro: Cleared cron job: {$this->cron_hook}");
        }
    }
    
    /**
     * Clear any duplicate cron jobs that might exist
     */
    public function clear_duplicate_crons() {
        // Clear any cron jobs with similar names that might have been created by other handlers
        $cron_hooks_to_clear = [
            'sentinelpro_daily_data_fetch',
            'sentinelpro_daily_analytics_fetch',
            'sentinelpro_daily_cleanup'
        ];
        
        foreach ($cron_hooks_to_clear as $hook) {
            wp_clear_scheduled_hook($hook);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cron manager debug logging for troubleshooting
                error_log("SentinelPro: Cleared potential duplicate cron hook: {$hook}");
            }
        }
    }
    
    /**
     * Force clear all cron jobs and reschedule properly
     */
    public function force_reset_cron() {
        // Clear all potential cron jobs
        $this->clear_duplicate_crons();
        $this->clear_cron();
        
        // Wait a moment to ensure WordPress processes the clearing
        sleep(1);
        
        // Reschedule the cron job
        $this->schedule_cron();
        
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cron reset logging is essential for troubleshooting
        error_log("SentinelPro: Force reset cron completed - cleared all jobs and rescheduled");
        
        return $this->get_cron_status();
    }
    
    /**
     * Reschedule the cron job
     */
    public function reschedule_cron() {
        $this->clear_cron();
        return $this->schedule_cron();
    }
    
    /**
     * Force reschedule the cron job to 4:00 AM and ensure single execution
     */
    public function force_reschedule_to_4am() {
        // Clear any existing cron jobs
        $this->clear_cron();
        $this->clear_duplicate_crons();
        
        // Clear any stale locks
        delete_transient('sentinelpro_cron_running');
        delete_transient('sentinelpro_manual_cron_running');
        delete_transient('sentinelpro_ajax_cron_trigger');
        
        // Clear last run time to ensure it runs on next schedule
        delete_option('sentinelpro_last_cron_run');
        
        // Schedule the cron job for 4:00 AM
        $next_run = $this->schedule_cron();
        
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Force reschedule logging
        error_log("SentinelPro: Force rescheduled cron job to 4:00 AM. Next run: " . gmdate('Y-m-d H:i:s', $next_run));
        
        return $next_run;
    }
    
    /**
     * Check if cron needs to be rescheduled to 4:00 AM and do it automatically
     */
    public function check_and_reschedule_to_4am() {
        // Only run once per request
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;
        
        // Check if we've already rescheduled to 4:00 AM
        $rescheduled_to_4am = get_option('sentinelpro_cron_rescheduled_to_4am');
        if ($rescheduled_to_4am) {
            return;
        }
        
        // Get current cron status
        $status = $this->get_cron_status();
        
        // If cron is scheduled but not for 4:00 AM, reschedule it
        if ($status['is_scheduled']) {
            $next_run_timestamp = $status['next_run_timestamp'];
            if ($next_run_timestamp) {
                $next_run_hour = (int)gmdate('H', $next_run_timestamp);
                if ($next_run_hour !== 4) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Auto reschedule logging
                    error_log("SentinelPro: Auto-rescheduling cron job from hour {$next_run_hour} to 4:00 AM");
                    $this->force_reschedule_to_4am();
                    update_option('sentinelpro_cron_rescheduled_to_4am', true);
                }
            }
        } else {
            // If not scheduled at all, schedule it for 4:00 AM
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Auto schedule logging
            error_log("SentinelPro: Auto-scheduling cron job for 4:00 AM");
            $this->force_reschedule_to_4am();
            update_option('sentinelpro_cron_rescheduled_to_4am', true);
        }
    }
    
    /**
     * Get server-level cron command for manual setup
     */
    public function get_server_cron_command() {
        $site_url = get_site_url();
        return "0 4 * * * wget -q -O /dev/null \"{$site_url}/wp-cron.php?doing_wp_cron\"";
    }
    
    /**
     * Check if cron is working properly
     */
    public function is_cron_working() {
        $status = $this->get_cron_status();
        
        // Check if it's scheduled
        if (!$status['is_scheduled']) {
            return false;
        }
        
        // Check if WordPress cron is disabled but no server cron is set up
        if ($status['wp_cron_disabled']) {
            // In this case, we can't automatically detect if server cron is working
            // So we'll assume it's working if there are no recent errors
            $last_error = $status['last_error'];
            if ($last_error) {
                $error_time = strtotime($last_error);
                $days_since_error = (time() - $error_time) / 86400;
                
                // If error is older than 7 days, assume it's working
                return $days_since_error > 7;
            }
            
            return true; // No errors, assume working
        }
        
        // For WordPress cron, check if it's running regularly
        $last_run = $status['last_run'];
        if ($last_run) {
            $last_run_time = strtotime($last_run);
            $days_since_run = (time() - $last_run_time) / 86400;
            
            // If it hasn't run in more than 2 days, there might be an issue
            return $days_since_run <= 2;
        }
        
        // If never run but scheduled, assume it's working
        return true;
    }

    /**
     * Check for multiple cron jobs and log them for debugging
     */
    public function check_for_duplicate_crons() {
        $cron_hooks_to_check = [
            'sentinelpro_daily_data_fetch',
            'sentinelpro_daily_analytics_fetch',
            'sentinelpro_daily_cleanup'
        ];
        
        $found_crons = [];
        foreach ($cron_hooks_to_check as $hook) {
            $next_run = wp_next_scheduled($hook);
            if ($next_run) {
                $found_crons[$hook] = $next_run;
            }
        }
        
        if (count($found_crons) > 1) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Duplicate cron warning logging is essential for troubleshooting
            error_log("SentinelPro: WARNING - Multiple cron jobs found: " . print_r($found_crons, true));
        }
        
        return $found_crons;
    }
    
    /**
     * Cron jobs now run silently in the background without admin interface
     */

    /**
     * Render the cron manager admin page
     */
    public function render_cron_manager_page() {
        // Enqueue cron manager script
        wp_enqueue_script(
            'valserv-cron-manager',
            SENTINELPRO_ANALYTICS_PLUGIN_URL . 'admin/js/cron-manager.js',
            array('jquery'),
            SENTINELPRO_ANALYTICS_VERSION,
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script(
            'valserv-cron-manager',
            'valservCronData',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sentinelpro_cron_nonce')
            )
        );
        
        ?>
        <div class="wrap">
            <h1>SentinelPro Cron Manager</h1>
            <div class="sentinelpro-cron-status">
                <h2>Current Cron Status</h2>
                <?php
                $status = $this->get_cron_status();
                $is_scheduled = $status['is_scheduled'];
                $next_run = $status['next_run'];
                $time_until_next = $status['time_until_next'];
                $last_run = $status['last_run'];
                $last_error = $status['last_error'];
                $last_error_message = $status['last_error_message'];
                $wp_cron_disabled = $status['wp_cron_disabled'];
                $cron_hook = $status['cron_hook'];
                $cron_interval = $status['cron_interval'];
                $cron_time = $status['cron_time'];
                $timezone = $status['timezone'];
                $duplicate_crons_found = $status['duplicate_crons_found'];
                $duplicate_crons = $status['duplicate_crons'];

                echo '<p>Cron Job: <strong>' . esc_html($cron_hook) . '</strong> (Interval: ' . esc_html($cron_interval) . ', Next Run: ' . esc_html($next_run) . ')</p>';
                echo '<p>Superuser Timezone: <strong>' . esc_html($timezone) . '</strong></p>';
                echo '<p>Last Run: <strong>' . ($last_run ? esc_html(gmdate('Y-m-d H:i:s', $last_run)) : 'Never') . '</strong></p>';
                echo '<p>Last Error: <strong>' . ($last_error ? esc_html(gmdate('Y-m-d H:i:s', $last_error)) : 'Never') . '</strong></p>';
                echo '<p>Last Error Message: <strong>' . esc_html($last_error_message) . '</strong></p>';
                echo '<p>WordPress Cron Disabled: <strong>' . ($wp_cron_disabled ? 'Yes' : 'No') . '</strong></p>';
                echo '<p>Is Scheduled: <strong>' . ($is_scheduled ? 'Yes' : 'No') . '</strong></p>';
                echo '<p>Time Until Next Run: <strong>' . ($time_until_next ? esc_html(round($time_until_next / 86400, 2) . ' days') : 'Less than 1 minute') . '</strong></p>';

                if ($duplicate_crons_found) {
                    echo '<div class="notice notice-warning"><p>WARNING: Multiple cron jobs with the same hook name were found. This might indicate conflicting cron handlers or duplicate plugin installations. Please check your WordPress cron logs for more details.</p></div>';
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Debug output for duplicate cron detection
                    echo '<pre>' . esc_html(print_r($duplicate_crons, true)) . '</pre>';
                }

                // Manual cron job actions removed - only status monitoring remains

                echo '<h2>Recent Logs</h2>';
                $recent_logs = $this->get_recent_logs();
                if (!empty($recent_logs)) {
                    echo '<pre>';
                    foreach ($recent_logs as $log) {
                        echo esc_html($log) . "\n";
                    }
                    echo '</pre>';
                } else {
                    echo '<p>No recent SentinelPro logs found.</p>';
                }
                ?>
            </div>
        </div>
        <?php
    }

    // AJAX handlers removed - manual cron job functionality disabled
    
    /**
     * Check for multiple cron jobs and log them for debugging
     */
} 
