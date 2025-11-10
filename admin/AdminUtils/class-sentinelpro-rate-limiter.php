<?php

if ( ! defined('ABSPATH') ) { exit; }

/**
 * Rate Limiting System for SentinelPro
 * Prevents abuse of AJAX endpoints and API calls
 */
class SentinelPro_Rate_Limiter {

    private const RATE_LIMIT_WINDOW = 60; // 1 minute
    private const MAX_REQUESTS_PER_WINDOW = 30;
    private const RATE_LIMIT_TRANSIENT_PREFIX = 'sentinelpro_rate_limit_';

    /**
     * Initialize rate limiting
     */
    public static function init(): void {
        add_action('wp_ajax_valserv_fetch_data', [self::class, 'check_rate_limit'], 1);
        add_action('wp_ajax_sentinelpro_fetch_users', [self::class, 'check_rate_limit'], 1);
        add_action('wp_ajax_sentinelpro_log_event', [self::class, 'check_rate_limit'], 1);
        add_action('wp_ajax_nopriv_sentinelpro_log_event', [self::class, 'check_rate_limit'], 1);
    }

    /**
     * Check rate limit for current request
     */
    public static function check_rate_limit(): void {
        // SECURITY: Verify nonce for all AJAX requests
        if (!check_ajax_referer('sentinelpro_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => esc_html__('Security check failed', 'valserv-analytics-for-sentinelpro')], 403);
            return;
        }
        
        // For authenticated users, check capabilities
        if (is_user_logged_in() && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Insufficient permissions', 'valserv-analytics-for-sentinelpro')], 403);
            return;
        }
        
        $ip = self::get_client_ip();
        $action = sanitize_text_field(wp_unslash($_POST['action'] ?? $_GET['action'] ?? 'unknown'));
        
        // Validate action parameter to prevent injection
        if (!self::is_valid_action($action)) {
            wp_send_json_error(['message' => esc_html__('Invalid action parameter', 'valserv-analytics-for-sentinelpro')], 400);
            return;
        }
        
        $rate_limit_key = self::RATE_LIMIT_TRANSIENT_PREFIX . md5($ip . $action);
        
        // Get current rate limit data
        $rate_limit_data = get_transient($rate_limit_key);
        if (!$rate_limit_data) {
            $rate_limit_data = [];
        }

        $current_time = time();
        
        // Clean old entries (older than window)
        $rate_limit_data = array_filter($rate_limit_data, function($timestamp) use ($current_time) {
            return $timestamp > ($current_time - self::RATE_LIMIT_WINDOW);
        });

        // Check if limit exceeded
        if (count($rate_limit_data) >= self::MAX_REQUESTS_PER_WINDOW) {
            self::log_rate_limit_violation($ip, $action);
            wp_send_json_error([
                'message' => esc_html__('Rate limit exceeded. Please try again later.', 'valserv-analytics-for-sentinelpro'),
                'retry_after' => self::RATE_LIMIT_WINDOW
            ], 429);
            return;
        }

        // Add current request
        $rate_limit_data[] = $current_time;
        
        // Store updated data
        set_transient($rate_limit_key, $rate_limit_data, self::RATE_LIMIT_WINDOW + 10);
        
        // Prevent potential DoS by limiting total rate limit keys
        self::cleanup_old_rate_limits();

        // Log warning if approaching limit
        if (count($rate_limit_data) >= (self::MAX_REQUESTS_PER_WINDOW * 0.8)) {
            self::log_rate_limit_warning($ip, $action, count($rate_limit_data));
        }
    }

    /**
     * Get client IP address with proxy support
     */
    private static function get_client_ip(): string {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_CLIENT_IP',        // Client IP
            'HTTP_X_FORWARDED_FOR',  // X-Forwarded-For
            'HTTP_X_FORWARDED',      // X-Forwarded
            'HTTP_X_CLUSTER_CLIENT_IP', // Cluster client IP
            'HTTP_FORWARDED_FOR',    // Forwarded-For
            'HTTP_FORWARDED',        // Forwarded
            'REMOTE_ADDR'            // Remote address
        ];

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Server variables are validated and sanitized through IP filtering
                $server_value = $_SERVER[$key];
                if (is_string($server_value)) {
                    foreach (explode(',', $server_value) as $ip) {
                        $ip = trim($ip);
                        // Validate IP and reject private/reserved ranges for security
                        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                            return $ip;
                        }
                    }
                }
            }
        }

        // Fallback to REMOTE_ADDR with validation
        $fallback_ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        if (filter_var($fallback_ip, FILTER_VALIDATE_IP) !== false) {
            return $fallback_ip;
        }

        return 'unknown';
    }

    /**
     * Log rate limit violation
     */
    private static function log_rate_limit_violation(string $ip, string $action): void {
        $log_data = [
            'timestamp' => current_time('mysql'),
            'ip' => $ip,
            'action' => $action,
            'user_agent' => sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')),
            'user_id' => get_current_user_id(),
            'type' => 'rate_limit_violation'
        ];

        if (class_exists('SentinelPro_Security_Manager')) {
            SentinelPro_Security_Manager::log_security_event(
                get_current_user_id(),
                'rate_limit_violation',
                "IP: {$ip}, Action: {$action}",
                'high'
            );
        } else {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Rate limit violation logging is essential for security monitoring
            error_log('SentinelPro Rate Limit Violation: ' . json_encode($log_data));
        }
    }

    /**
     * Log rate limit warning
     */
    private static function log_rate_limit_warning(string $ip, string $action, int $request_count): void {
        if (class_exists('SentinelPro_Security_Manager')) {
            SentinelPro_Security_Manager::log_security_event(
                get_current_user_id(),
                'rate_limit_warning',
                "IP: {$ip}, Action: {$action}, Requests: {$request_count}",
                'medium'
            );
        }
    }

    /**
     * Get rate limit status for an IP and action
     */
    public static function get_rate_limit_status(string $ip, string $action): array {
        $rate_limit_key = self::RATE_LIMIT_TRANSIENT_PREFIX . md5($ip . $action);
        $rate_limit_data = get_transient($rate_limit_key) ?: [];
        
        $current_time = time();
        $recent_requests = array_filter($rate_limit_data, function($timestamp) use ($current_time) {
            return $timestamp > ($current_time - self::RATE_LIMIT_WINDOW);
        });

        return [
            'current_requests' => count($recent_requests),
            'max_requests' => self::MAX_REQUESTS_PER_WINDOW,
            'window_seconds' => self::RATE_LIMIT_WINDOW,
            'remaining_requests' => max(0, self::MAX_REQUESTS_PER_WINDOW - count($recent_requests)),
            'reset_time' => $current_time + self::RATE_LIMIT_WINDOW
        ];
    }

    /**
     * Clear rate limit for an IP and action (admin function)
     */
    public static function clear_rate_limit(string $ip, string $action): bool {
        if (!current_user_can('manage_options')) {
            return false;
        }

        $rate_limit_key = self::RATE_LIMIT_TRANSIENT_PREFIX . md5($ip . $action);
        return delete_transient($rate_limit_key);
    }

    /**
     * Get all active rate limits (admin function)
     */
    public static function get_all_rate_limits(): array {
        if (!current_user_can('manage_options')) {
            return [];
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Rate limit cleanup query for transients
        $transients = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} 
                 WHERE option_name LIKE %s",
                '_transient_' . self::RATE_LIMIT_TRANSIENT_PREFIX . '%'
            )
        );

        $rate_limits = [];
        foreach ($transients as $transient) {
            $key = str_replace('_transient_', '', $transient->option_name);
            $data = maybe_unserialize($transient->option_value);
            
            if (is_array($data)) {
                $current_time = time();
                $recent_requests = array_filter($data, function($timestamp) use ($current_time) {
                    return $timestamp > ($current_time - self::RATE_LIMIT_WINDOW);
                });

                if (!empty($recent_requests)) {
                    $rate_limits[$key] = [
                        'requests' => count($recent_requests),
                        'max_requests' => self::MAX_REQUESTS_PER_WINDOW,
                        'last_request' => max($recent_requests)
                    ];
                }
            }
        }

        return $rate_limits;
    }
    
    /**
     * Clean up old rate limit entries to prevent DoS attacks
     * Limits the total number of rate limit transients in the database
     */
    private static function cleanup_old_rate_limits(): void {
        global $wpdb;
        
        // Get count of current rate limit transients
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Rate limit count query for cleanup
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} 
                 WHERE option_name LIKE %s",
                '_transient_' . self::RATE_LIMIT_TRANSIENT_PREFIX . '%'
            )
        );
        
        // If we have too many, clean up the oldest ones
        if ($count > 1000) { // Arbitrary limit to prevent database bloat
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Rate limit cleanup query for old transients
            $old_transients = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT option_name FROM {$wpdb->options} 
                     WHERE option_name LIKE %s 
                     ORDER BY option_value ASC 
                     LIMIT %d",
                    '_transient_' . self::RATE_LIMIT_TRANSIENT_PREFIX . '%',
                    500 // Clean up half of them
                )
            );
            
            foreach ($old_transients as $transient) {
                $key = str_replace('_transient_', '', $transient->option_name);
                delete_transient($key);
            }
        }
    }
    
    /**
     * Validate action parameter to prevent injection
     * Only allow known, safe action names
     * 
     * @param string $action The action to validate
     * @return bool True if action is valid, false otherwise
     */
    private static function is_valid_action(string $action): bool {
        // Only allow alphanumeric characters, underscores, and hyphens
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $action)) {
            return false;
        }
        
        // Whitelist of allowed actions
        $allowed_actions = [
            'valserv_fetch_data',
            'sentinelpro_fetch_users',
            'sentinelpro_log_event'
        ];
        
        return in_array($action, $allowed_actions, true);
    }
}
