<?php

if ( ! defined('ABSPATH') ) { exit; }

class SentinelPro_Installer{
    // public static function create_logs_table(): void {
    //     error_log('SENTINELPRO: create_logs_table() called');

    //     global $wpdb;
    //     $table_name = esc_sql($wpdb->prefix . 'sentinelpro_access_logs');
    //     $charset_collate = $wpdb->get_charset_collate();

    //     $sql = "CREATE TABLE $table_name (
    //         id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    //         user_id BIGINT(20) UNSIGNED NOT NULL,
    //         page_key VARCHAR(255) NOT NULL,
    //         old_value VARCHAR(50),
    //         new_value VARCHAR(50),
    //         changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    //         PRIMARY KEY (id),
    //         KEY user_id (user_id),
    //         KEY page_key (page_key)
    //     ) $charset_collate;";

    //     if (!function_exists('dbDelta')) {
    //         require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    //     }

    //     if (function_exists('dbDelta')) {
    //         dbDelta($sql);
    //         error_log('SENTINELPRO: dbDelta executed for logs table.');
    //     } else {
    //         error_log('SENTINELPRO: dbDelta still not available.');
    //     }
    // }
}
