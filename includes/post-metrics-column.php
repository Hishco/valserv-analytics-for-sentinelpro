<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class SentinelPro_Post_Metrics_Column
 * Shows individual post view count over the past 30 days.
 * 
 * This class uses a STRICT database-first approach:
 * 1. ALWAYS queries the local database first for fresh analytics data
 * 2. Only falls back to post meta/transients if database is completely unavailable
 * 3. Only makes API calls as a last resort when database is unavailable
 * 
 * This ensures the edit.php page always displays the most current data
 * from the database rather than stale cached values from local storage.
 * 
 * Priority order:
 * 1. Database (primary source)
 * 2. Post meta (for sorting only, when database unavailable)
 * 3. Transients (when database unavailable)
 * 4. API calls (when database unavailable)
 */
class SentinelPro_Post_Metrics_Column {

    private string $views_prefix = 'sentinelpro_views_';
    private string $sessions_prefix = 'sentinelpro_sessions_';
    private string $option_name = 'sentinelpro_options';

    private static $instance;

    public static function instance(): self{
        if(!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('manage_post_posts_columns', [$this, 'add_columns']);
        add_action('manage_post_posts_custom_column', [$this, 'render_columns'], 10, 2);
        add_filter('manage_edit-post_sortable_columns', [$this, 'make_columns_sortable']);
    }



    public function add_columns(array $columns): array {
        if (!class_exists('SentinelPro_User_Access_Manager')) {
            return $columns;
        }

        if (!SentinelPro_User_Access_Manager::user_has_access('post_column')) {
            return $columns;
        }

        $columns['sentinelpro_views'] = 'Views (L30D)';
        $columns['sentinelpro_sessions'] = 'Sessions (L30D)';
        return $columns;
    }


    public function render_columns(string $column_name, int $post_id): void {
        if (!class_exists('SentinelPro_User_Access_Manager') ||
            !SentinelPro_User_Access_Manager::user_has_access('post_column')) {
            echo esc_html('—');
            return;
        }

        // Always try to get metrics from database first
        $db_metrics = $this->get_metrics_from_database($post_id);
        
        // Allow external systems to force database refresh
        $force_refresh = apply_filters('sentinelpro_force_db_refresh', false, $post_id);
        if ($force_refresh && $this->is_database_available()) {
            $db_metrics = $this->refresh_post_metrics_from_database($post_id);
        }
        
        if (!empty($db_metrics)) {
            // Update post meta for sorting
            foreach ($db_metrics as $metric => $value) {
                update_post_meta($post_id, "_sentinelpro_{$metric}", $value);
            }
            
            // Render the requested column from database data
            if ($column_name === 'sentinelpro_views' && isset($db_metrics['views'])) {
                echo esc_html(number_format_i18n($db_metrics['views']));
                return;
            }
            
            if ($column_name === 'sentinelpro_sessions' && isset($db_metrics['sessions'])) {
                echo esc_html(number_format_i18n($db_metrics['sessions']));
                return;
            }
        }

        // Only fall back to individual metric fetching if database is completely unavailable
        if (!$this->is_database_available()) {
            $this->render_metric_column($post_id, 'views', $column_name, 'sentinelpro_views');
            $this->render_metric_column($post_id, 'sessions', $column_name, 'sentinelpro_sessions');
        } else {
            // Database is available but no data found - show 0
            if ($column_name === 'sentinelpro_views') {
                echo esc_html(number_format_i18n(0));
            } elseif ($column_name === 'sentinelpro_sessions') {
                echo esc_html(number_format_i18n(0));
            }
        }
    }

    private function render_metric_column(int $post_id, string $metric, string $column_name, string $expected_column_name): void {
        if ($column_name !== $expected_column_name) return;

        $value = $this->get_cached_metric($post_id, $metric);

        echo esc_html(number_format_i18n(is_numeric($value) ? $value : 0));
    }


    private function get_cached_metric(int $post_id, string $metric): ?int {
        // Always try database first for fresh data
        $db_value = $this->get_metric_from_database($post_id, $metric);
        if ($db_value !== null) {
            // Update post meta for sorting
            update_post_meta($post_id, "_sentinelpro_{$metric}", $db_value);
            return $db_value;
        }

        // Only fall back to post meta if database is unavailable
        if (!$this->is_database_available()) {
            $meta_value = get_post_meta($post_id, "_sentinelpro_{$metric}", true);
            if (is_numeric($meta_value) && $meta_value >= 0) {
                return (int) $meta_value;
            }
        }

        // Only use transients if database is completely unavailable
        if (!$this->is_database_available()) {
            $prefix = $metric === 'views' ? $this->views_prefix : $this->sessions_prefix;
            $transient_key = $prefix . $post_id;
            $cached = get_transient($transient_key);

            if ($cached !== false) {
                update_post_meta($post_id, "_sentinelpro_{$metric}", (int) $cached);
                return (int) $cached;
            }
        }

        // Only make API calls if database is unavailable and no cached data exists
        if (!$this->is_database_available()) {
            $all_metrics = $this->fetch_metrics_for_post($post_id);
            $this->set_all_metrics_transients($post_id, $all_metrics);
            return isset($all_metrics[$metric]) ? (int) $all_metrics[$metric] : null;
        }

        return null;
    }

    /**
     * Get both views and sessions metrics from database for the last 30 days
     */
    private function get_metrics_from_database(int $post_id): array {
        // Check if the database manager class exists
        if (!class_exists('SentinelPro_Database_Manager')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Database class availability logging is essential for troubleshooting
                error_log('SentinelPro: Database manager class not found');
            }
            return [];
        }

        $db_manager = SentinelPro_Database_Manager::get_instance();
        
        // Check if tables exist
        if (!$db_manager->tables_exist()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Database table availability logging is essential for troubleshooting
                error_log('SentinelPro: Database tables do not exist');
            }
            return [];
        }
        
        // Calculate date range for last 30 days
        $end_date = gmdate('Y-m-d');
        $start_date = gmdate('Y-m-d', strtotime('-30 days'));

        try {
            // Get metrics from database
            $metrics = $db_manager->get_post_metrics($post_id, $start_date, $end_date);
            return $metrics;
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Database error logging is essential for troubleshooting
                error_log('SentinelPro: Error getting post metrics from database: ' . $e->getMessage());
            }
            return [];
        }
    }

    /**
     * Check if database is available for post metrics
     */
    private function is_database_available(): bool {
        if (!class_exists('SentinelPro_Database_Manager')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Database class availability logging is essential for troubleshooting
                error_log('SentinelPro: Database manager class not found in is_database_available');
            }
            return false;
        }

        $db_manager = SentinelPro_Database_Manager::get_instance();
        $tables_exist = $db_manager->tables_exist();
        
        if (defined('WP_DEBUG') && WP_DEBUG && !$tables_exist) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Database table availability logging is essential for troubleshooting
            error_log('SentinelPro: Database tables do not exist in is_database_available');
        }
        
        return $tables_exist;
    }

    /**
     * Force refresh metrics from database for a specific post
     * This ensures we always get the latest data from the database
     */
    public function refresh_post_metrics_from_database(int $post_id): array {
        if (!$this->is_database_available()) {
            return [];
        }

        $db_metrics = $this->get_metrics_from_database($post_id);
        
        if (!empty($db_metrics)) {
            // Update post meta for sorting
            foreach ($db_metrics as $metric => $value) {
                update_post_meta($post_id, "_sentinelpro_{$metric}", $value);
            }
        }
        
        return $db_metrics;
    }

    /**
     * Get metric from database for the last 30 days
     */
    private function get_metric_from_database(int $post_id, string $metric): ?int {
        // Check if the database manager class exists
        if (!class_exists('SentinelPro_Database_Manager')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Database class availability logging is essential for troubleshooting
                error_log('SentinelPro: Database manager class not found');
            }
            return null;
        }

        $db_manager = SentinelPro_Database_Manager::get_instance();
        
        // Check if tables exist
        if (!$db_manager->tables_exist()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Database table availability logging is essential for troubleshooting
                error_log('SentinelPro: Database tables do not exist');
            }
            return null;
        }
        
        // Calculate date range for last 30 days
        $end_date = gmdate('Y-m-d');
        $start_date = gmdate('Y-m-d', strtotime('-30 days'));

        try {
            // Get metrics from database
            $metrics = $db_manager->get_post_metrics($post_id, $start_date, $end_date);
            
            if (isset($metrics[$metric])) {
                return (int) $metrics[$metric];
            }
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Database error logging is essential for troubleshooting
                error_log('SentinelPro: Error getting post metric from database: ' . $e->getMessage());
            }
        }

        return null;
    }

    private function set_all_metrics_transients(int $post_id, array $metrics): void {
        foreach (['views', 'sessions'] as $m) {
            $value = $metrics[$m] ?? null;
            // Ensure a numeric value, default to 0 if invalid or missing
            $numeric_value = (isset($value) && is_numeric($value) && $value >= 0) ? (int) $value : 0;

            $key = ($m === 'views' ? $this->views_prefix : $this->sessions_prefix) . $post_id;
            set_transient($key, $numeric_value, HOUR_IN_SECONDS);

            // ✅ Always save post meta so it's sortable, even if 0
            update_post_meta($post_id, "_sentinelpro_{$m}", $numeric_value);

        }
    }




    private function fetch_metrics_for_post(int $post_id): array {
        $options = get_option($this->option_name);
        $api_key = SentinelPro_Security_Manager::get_api_key();
        $property_id = sanitize_text_field($options['property_id'] ?? '');

        if (empty($api_key) || empty($property_id)) {
            return [];
        }

        $permalink = get_permalink($post_id);
        if (!$permalink) {
            return [];
        }

        $parsed_url = wp_parse_url($permalink);
        $page_path = $parsed_url['path'] ?? null;

        if (!$page_path) {
            return [];
        }

        // ✅ Define required values
        $start_date  = gmdate('Y-m-d', strtotime('-30 days'));
        $end_date    = gmdate('Y-m-d');
        $granularity = 'daily';
        // Add filter for external devs
        $metrics     = apply_filters('sentinelpro_post_metrics', ['views', 'sessions']);

        $headers = [
            'SENTINEL-API-KEY' => $api_key,
            'Accept'           => 'application/json'
        ];

        $start = new DateTime($start_date);
        $end   = new DateTime($end_date);

        $all_data = [];

        $current = clone $start;
        while ($current < $end) {
            $chunk_start = clone $current;
            $chunk_end = (clone $chunk_start)->modify('+9 days');
            if ($chunk_end > $end) {
                $chunk_end = clone $end;
            }

            $chunk_gt = $chunk_start->format('Y-m-d');
            $chunk_lt = $chunk_end->format('Y-m-d');

            $chunk_data = [
                "filters" => [
                    "date" => [
                        "gte" => $chunk_gt,
                        "lt" => $chunk_lt
                    ],
                    "propertyId" => [
                        "in" => [$property_id]
                    ],
                    "pagePath" => [
                        "eq" => $page_path
                    ]
                ],
                "granularity" => $granularity,
                "metrics" => $metrics,
                "dimensions" => ["date", "pagePath"],
                "orderBy" => ["date" => "asc"],
                "pagination" => [
                    "pageSize" => 1000,
                    "pageNumber" => 1
                ]
            ];

            $url = $this->build_api_request_url_with_data($property_id, $chunk_data);
            $response = $this->make_api_request($url, $headers);
            $parsed = $this->parse_api_response($response, $post_id);

            foreach ($metrics as $metric) {
                if (!isset($all_data[$metric])) {
                    $all_data[$metric] = 0;
                }
                $all_data[$metric] += isset($parsed[$metric]) ? (int)$parsed[$metric] : 0;
            }

            $current = (clone $chunk_end)->modify('+1 day');
        }
        // Otherwise, return null — do not cache zero fallback
        return $all_data;
    }

    private function build_api_request_url_with_data(string $property_id, array $data): string {
        $options = get_option($this->option_name);
        $account_name = sanitize_text_field($options['account_name']);
        $base_url = "https://{$account_name}.sentinelpro.com/api/v1/traffic/";
        return $base_url . '?data=' . rawurlencode(json_encode($data, JSON_UNESCAPED_SLASHES));
    }




    private function make_api_request(string $url, array $headers): array|WP_Error {
        return wp_remote_get($url, [
            'headers' => $headers,
            'timeout' => 10,
        ]);
    }

    public function make_columns_sortable(array $columns): array {
        $columns['sentinelpro_views'] = 'sentinelpro_views';
        $columns['sentinelpro_sessions'] = 'sentinelpro_sessions';
        return $columns;
    }




    private function parse_api_response($response, int $post_id): array {
        if (is_wp_error($response) || !is_array($response)) {
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($body['data']) || !is_array($body['data'])) {
            return [];
        }

        $totals = ['views' => 0, 'sessions' => 0];
        foreach ($body['data'] as $entry) {
            if (isset($entry['views'])) {
                $totals['views'] += intval($entry['views']);
            }
            if (isset($entry['sessions'])) {
                $totals['sessions'] += intval($entry['sessions']);
            }
        }
        return $totals;
    }
}
