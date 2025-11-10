<?php

if ( ! defined('ABSPATH') ) { exit; }

final class SentinelPro_Content_Importer {

    private function __construct() {} // prevent instantiation

    /**
     * Initialize content import functionality.
     */
    public static function init(): void {
        add_action('admin_init', [self::class, 'maybe_import_sentinelpro_articles']);
    }

    /**
     * Import SentinelPro articles if not already imported.
     * 
     * @return void
     */
    public static function maybe_import_sentinelpro_articles(): void {
        // Capability check for security
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if already imported
        if (get_option('sentinelpro_articles_imported')) {
            return;
        }

        // Use WordPress helpers for path construction
        $dir = trailingslashit(plugin_dir_path(__FILE__)) . 'html/';
        $file1 = $dir . 'article_sentinelpro-the-best-ga4-alternative (3).html';
        $file2 = $dir . 'article_sentinelpro-faq.html';

        // SECURITY: Validate file paths to prevent path traversal
        $real_dir = realpath($dir);
        $real_file1 = realpath($file1);
        $real_file2 = realpath($file2);
        
        // Ensure files are within the expected directory
        if (!$real_dir || !$real_file1 || !$real_file2 || 
            strpos($real_file1, $real_dir) !== 0 || 
            strpos($real_file2, $real_dir) !== 0) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security violation logging is essential for monitoring
                error_log('SentinelPro: Security violation - file path validation failed');
            }
            return;
        }

        if (!file_exists($file1) || !file_exists($file2)) {
            return; // Avoid fatal errors if files are missing
        }

        // SECURITY: Read files with additional validation
        $content1 = file_get_contents($file1);
        $content2 = file_get_contents($file2);
        
        // Validate file contents
        if ($content1 === false || $content2 === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- File operation error logging is essential for troubleshooting
                error_log('SentinelPro: Unable to read content files');
            }
            return;
        }

        // Extract body content safely
        $body1 = self::extract_body_content($content1);
        $body2 = self::extract_body_content($content2);

        // Create posts with proper sanitization
        $post1_id = wp_insert_post([
            'post_title' => sanitize_text_field('SentinelPro: The Best GA4 Alternative'),
            'post_content' => wp_kses_post($body1),
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_author' => get_current_user_id(),
        ], true);

        $post2_id = wp_insert_post([
            'post_title' => sanitize_text_field('SentinelPro FAQ'),
            'post_content' => wp_kses_post($body2),
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_author' => get_current_user_id(),
        ], true);

        // Only mark as imported if both posts were created successfully
        if (!is_wp_error($post1_id) && !is_wp_error($post2_id)) {
            update_option('sentinelpro_articles_imported', true, false); // autoload = false
        }
    }

    /**
     * Safely extract body content from HTML.
     * 
     * @param string $html HTML content
     * @return string Extracted body content or empty string
     */
    private static function extract_body_content(string $html): string {
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches)) {
            return $matches[1];
        }
        return '';
    }
}
