<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * SentinelPro Security Headers and Rate Limiting
 * Implements security headers, XSS protection, and rate limiting
 */

/**
 * Security Headers Manager for SentinelPro
 * Implements Content Security Policy and other security headers
 */
class SentinelPro_Security_Headers {

    /**
     * Initialize security headers
     */
    public static function init(): void {
        add_action('admin_init', [self::class, 'set_security_headers']);
        add_action('wp_ajax_valserv_fetch_data', [self::class, 'set_ajax_security_headers']);
        add_action('wp_ajax_sentinelpro_fetch_users', [self::class, 'set_ajax_security_headers']);
    }

    /**
     * Set security headers for admin pages
     */
    public static function set_security_headers(): void {
        // Only set headers for SentinelPro admin pages
        if (!self::is_sentinelpro_admin_page()) {
            return;
        }

        // Content Security Policy (Report-Only initially)
        $csp_policy = self::build_csp_policy();
        header("Content-Security-Policy-Report-Only: {$csp_policy}");

        // X-Frame-Options: Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN');

        // X-Content-Type-Options: Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');

        // X-XSS-Protection: Enable XSS filtering
        header('X-XSS-Protection: 1; mode=block');

        // Referrer-Policy: Control referrer information
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Remove X-Powered-By header
        header_remove('X-Powered-By');

        // Strict-Transport-Security (if HTTPS)
        if (is_ssl()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Set security headers for AJAX responses
     */
    public static function set_ajax_security_headers(): void {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
        }
        
        // X-Content-Type-Options for AJAX responses
        header('X-Content-Type-Options: nosniff');
        
        // X-Frame-Options for AJAX responses
        header('X-Frame-Options: DENY');
    }

    /**
     * Build Content Security Policy
     */
    private static function build_csp_policy(): string {
        $policies = [
            // Default source restrictions
            "default-src 'self'",
            
            // Script sources - allow WordPress admin, inline scripts for charts
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            
            // Style sources - allow WordPress admin, inline styles
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            
            // Image sources - allow data URIs for charts, external images
            "img-src 'self' data: https: http:",
            
            // Font sources - allow Google Fonts
            "font-src 'self' https://fonts.gstatic.com",
            
            // Connect sources - allow AJAX to same origin
            "connect-src 'self'",
            
            // Frame sources - deny embedding
            "frame-src 'none'",
            
            // Object sources - deny plugins
            "object-src 'none'",
            
            // Base URI - restrict to same origin
            "base-uri 'self'",
            
            // Form action - restrict to same origin
            "form-action 'self'",
            
            // Frame ancestors - prevent embedding
            "frame-ancestors 'none'",
            
            // Upgrade insecure requests
            "upgrade-insecure-requests"
        ];

        return implode('; ', $policies);
    }

    /**
     * Check if current page is a SentinelPro admin page
     */
    private static function is_sentinelpro_admin_page(): bool {
        if (!is_admin()) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page parameter is only used for display purposes, not for security-sensitive operations
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        $sentinelpro_pages = [
            'sentinelpro-settings',
            'sentinelpro-api-input', 
            'sentinelpro-event-data',
            'sentinelpro-user-management'
        ];

        return in_array($page, $sentinelpro_pages, true);
    }

    /**
     * Enable strict CSP (remove Report-Only)
     */
    public static function enable_strict_csp(): void {
        if (!self::is_sentinelpro_admin_page()) {
            return;
        }

        $csp_policy = self::build_csp_policy();
        header("Content-Security-Policy: {$csp_policy}");
    }

    /**
     * Get CSP violations (for monitoring)
     */
    public static function log_csp_violation(): void {
        // Verify nonce for CSP report endpoint
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- CSP violation reports are external and don't use WordPress nonces
        if (!isset($_POST['csp-report']) || !wp_verify_nonce(isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '', 'csp_violation_report')) {
            return;
        }

        $raw_input = file_get_contents('php://input');
        $report = json_decode($raw_input, true);
        if ($report) {
            // Sanitize the report data before logging
            $sanitized_report = array_map('sanitize_text_field', $report);
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- CSP violation logging is essential for security monitoring
            error_log('SentinelPro CSP Violation: ' . json_encode($sanitized_report));
        }
    }
}
