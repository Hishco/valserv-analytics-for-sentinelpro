<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Comprehensive security configuration for SentinelPro.
 * Implements security best practices and provides centralized security management.
 */
final class SentinelPro_Security_Config {

    private function __construct() {} // prevent instantiation

    /**
     * Custom capabilities for SentinelPro.
     */
    public static function get_custom_capabilities(): array {
        return [
            'sp_access' => __('Access SentinelPro', 'valserv-analytics-for-sentinelpro'),
            'sp_manage' => __('Manage SentinelPro', 'valserv-analytics-for-sentinelpro'),
            'sp_view_dashboard' => __('View Dashboard', 'valserv-analytics-for-sentinelpro'),
            'sp_manage_users' => __('Manage Users', 'valserv-analytics-for-sentinelpro'),
            'sp_manage_api' => __('Manage API Settings', 'valserv-analytics-for-sentinelpro'),
            'sp_view_analytics' => __('View Analytics', 'valserv-analytics-for-sentinelpro'),
            'sp_export_data' => __('Export Data', 'valserv-analytics-for-sentinelpro'),
        ];
    }

    /**
     * Initialize security features.
     */
    public static function init(): void {
        // Add custom capabilities
        add_action('admin_init', [self::class, 'maybe_add_capabilities']);
        
        // Security headers
        add_action('send_headers', [self::class, 'add_security_headers']);
        
        // Block unexpected hosts
        add_filter('http_request_host_is_external', [self::class, 'filter_external_hosts'], 10, 2);
        
        // Rate limiting for AJAX endpoints
        add_action('wp_ajax_valserv_fetch_database_data', [self::class, 'rate_limit_ajax'], 1);
        add_action('wp_ajax_sentinelpro_save_setting', [self::class, 'rate_limit_ajax'], 1);
        
        // Database version management - only run if constants are defined
        if (defined('SENTINELPRO_DB_VERSION')) {
            add_action('admin_init', [self::class, 'maybe_upgrade_database']);
        }
    }

    /**
     * Add custom capabilities to administrator role.
     */
    public static function maybe_add_capabilities(): void {
        $admin_role = get_role('administrator');
        if (!$admin_role) {
            return;
        }

        $capabilities = self::get_custom_capabilities();
        foreach (array_keys($capabilities) as $cap) {
            if (!$admin_role->has_cap($cap)) {
                $admin_role->add_cap($cap);
            }
        }
    }

    /**
     * Add security headers.
     */
    public static function add_security_headers(): void {
        if (!is_admin()) {
            return;
        }

        // Content Security Policy (Report-Only initially)
        $csp = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; frame-ancestors 'self';";
        header("Content-Security-Policy-Report-Only: {$csp}");

        // X-Frame-Options
        header('X-Frame-Options: SAMEORIGIN');

        // X-Content-Type-Options
        header('X-Content-Type-Options: nosniff');

        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    /**
     * Filter external hosts to prevent SSRF.
     */
    public static function filter_external_hosts(bool $is_external, string $host): bool {
        // Allow only known SentinelPro domains
        $allowed_hosts = [
            'sentinelpro.com',
            'valserv.com',
        ];

        foreach ($allowed_hosts as $allowed_host) {
            if (str_ends_with($host, $allowed_host)) {
                return true;
            }
        }

        // Block unexpected hosts
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security host blocking logging is essential for troubleshooting
            error_log("SentinelPro: Blocked external request to unexpected host: {$host}");
        }

        return false;
    }

    /**
     * Rate limit AJAX requests.
     */
    public static function rate_limit_ajax(): void {
        // Verify nonce for security
        if (!check_ajax_referer('sentinelpro_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed'], 403);
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Authentication required'], 401);
        }

        $throttle_key = 'valserv_ajax_' . $user_id . '_' . current_action();
        $tries = (int) get_transient($throttle_key);
        
        if ($tries > 50) { // 50 requests per 5 minutes
            wp_send_json_error(['message' => 'Rate limit exceeded. Try again later.'], 429);
        }
        
        set_transient($throttle_key, $tries + 1, 5 * MINUTE_IN_SECONDS);
    }

    /**
     * Database version management.
     */
    public static function maybe_upgrade_database(): void {
        $current_version = get_option('sentinelpro_db_version', '0.0.0');
        $target_version = defined('SENTINELPRO_DB_VERSION') ? SENTINELPRO_DB_VERSION : '1.0.0';

        if (version_compare($current_version, $target_version, '<')) {
            self::upgrade_database($current_version, $target_version);
        }
    }

    /**
     * Upgrade database schema.
     */
    private static function upgrade_database(string $from_version, string $to_version): void {
        global $wpdb;

        // Create tables with proper charset and collation
        $charset_collate = $wpdb->get_charset_collate();

        // Example table creation (adjust based on your actual schema)
        $table_name = $wpdb->prefix . 'sentinelpro_analytics';
        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder -- Table name is validated and safe, %i is used for table name
        $sql = $wpdb->prepare("CREATE TABLE IF NOT EXISTS %i (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            date date NOT NULL,
            device varchar(20) NOT NULL,
            geo varchar(10) NOT NULL,
            referrer text,
            os varchar(20),
            browser varchar(20),
            views int(11) DEFAULT 0,
            sessions int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY date (date),
            KEY device (device),
            KEY geo (geo)
        ) %s", $table_name, $charset_collate);

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Update version
        update_option('sentinelpro_db_version', $to_version, false);
    }

    /**
     * Validate and sanitize date range.
     */
    public static function validate_date_range(string $start_date, string $end_date, int $max_days = 400): bool {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || 
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
            return false;
        }

        $start = strtotime($start_date);
        $end = strtotime($end_date);
        
        if ($start === false || $end === false || $start > $end) {
            return false;
        }

        $days_diff = ($end - $start) / DAY_IN_SECONDS;
        return $days_diff <= $max_days;
    }

    /**
     * Validate and sanitize dimensions.
     */
    public static function validate_dimensions(array $dimensions): array {
        $allowed_dimensions = ['date', 'device', 'geo', 'referrer', 'os', 'browser'];
        $valid_dimensions = [];

        foreach ($dimensions as $dimension) {
            $clean_dim = sanitize_key(trim($dimension));
            if (in_array($clean_dim, $allowed_dimensions, true)) {
                $valid_dimensions[] = $clean_dim;
            }
        }

        return $valid_dimensions;
    }

    /**
     * Create options with autoload disabled.
     */
    public static function create_options(): void {
        // Create main options array with autoload disabled
        if (!get_option('sentinelpro_options')) {
            add_option('sentinelpro_options', [], '', 'no');
        }

        // Create other options
        $options = [
            'sentinelpro_db_version' => '1.0.0',
            'sentinelpro_articles_imported' => false,
            'sentinelpro_cron_timezone' => 'UTC',
        ];

        foreach ($options as $option => $default_value) {
            if (!get_option($option)) {
                add_option($option, $default_value, '', 'no');
            }
        }
    }

    /**
     * Clean up on plugin uninstall.
     */
    public static function cleanup(): void {
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            return;
        }

        // Remove options
        $options = [
            'sentinelpro_options',
            'sentinelpro_db_version',
            'sentinelpro_articles_imported',
            'sentinelpro_cron_timezone',
            'sentinelpro_superuser_id',
        ];

        foreach ($options as $option) {
            delete_option($option);
        }

        // Remove custom capabilities
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $capabilities = self::get_custom_capabilities();
            foreach (array_keys($capabilities) as $cap) {
                $admin_role->remove_cap($cap);
            }
        }

        // Drop custom tables
        global $wpdb;
        $table_name = $wpdb->prefix . 'sentinelpro_analytics';
        
        // Validate table name before dropping
        if (self::is_valid_table_name($table_name)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder -- Plugin uninstall cleanup of custom table, table name is validated
            $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table_name));
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
