<?php

if ( ! defined('ABSPATH') ) { exit; }

    require_once plugin_dir_path(__FILE__) . 'class-sentinelpro-user-access-manager.php';
    // Assuming SentinelPro_CSV_Permissions_Importer is in a separate file and loaded
    // For example: require_once plugin_dir_path(__FILE__) . 'class-sentinelpro-csv-permissions-importer.php';


class SentinelPro_CSV_Uploader_Handler {

    public static function handle_upload(array $post, array $files): void {
        if (!current_user_can('manage_options')) return;

        try {
            // NEW: Check if the "Import from URL" button was specifically clicked
            if (isset($post['sentinelpro_import_url'])) {
                $url = sanitize_url($post['sentinelpro_access_url'] ?? ''); // Get URL, default to empty string if not set

                if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                    $updates = SentinelPro_CSV_Permissions_Importer::parse_remote_csv($url);
                    self::apply_user_access_updates($updates);
                    self::success_notice('✅ Permissions from URL applied successfully.');
                } else {
                    self::error_notice('❌ Invalid or empty URL provided for import.');
                }
                return; // Important: return after handling URL import
            }

            if (!empty($post['sentinelpro_access_upload_hidden'])) {
                $updates = SentinelPro_CSV_Permissions_Importer::parse_textarea_csv(
                    sanitize_textarea_field($post['sentinelpro_access_upload_hidden'])
                );
                self::apply_user_access_updates($updates);
                self::success_notice('✅ Uploaded permissions applied successfully.');
                return;
            }

            if (!empty($files['sentinelpro_access_upload']['tmp_name'])) {
                $updates = SentinelPro_CSV_Permissions_Importer::parse_file_upload($files['sentinelpro_access_upload']);
                self::apply_user_access_updates($updates);
                self::success_notice('✅ CSV permissions applied successfully.');
                return;
            }

            if (isset($post['sentinelpro_access'])) {
                self::handle_manual_toggle_update($post);
                return;
            }
        } catch (Exception $e) {
            self::error_notice($e->getMessage());
        }
    }

    private static function handle_manual_toggle_update(array $post): void {
        check_admin_referer('sentinelpro_user_mgmt_save', 'sentinelpro_user_mgmt_nonce');
        $superuser_id = (int) get_option('sentinelpro_superuser_id');

        foreach ($post['sentinelpro_access'] as $user_id => $access_array) {
            $user_id = intval($user_id);
            if ($user_id === $superuser_id) continue;

            // Get current access before updating
            $old_access = get_user_meta($user_id, 'sentinelpro_access', true) ?: [];

            $sanitized = array_map(fn($v) => (bool) $v, array_combine(
                array_map('sanitize_key', array_keys($access_array)),
                array_values($access_array)
            ));

            // Log changes before updating
            foreach ($sanitized as $key => $new_val) {
                $old_val = $old_access[$key] ?? false;
                if ($old_val !== $new_val) {
                    self::log_access_change($user_id, $key, $old_val, $new_val);
                }
            }

            update_user_meta($user_id, 'sentinelpro_access', $sanitized);
        }

        self::success_notice('✅ User permissions and roles saved successfully.');
    }


    public static function apply_user_access_updates(array $updates): void {
        $superuser_id = (int) get_option('sentinelpro_superuser_id');
        $current_user_id = get_current_user_id();
        foreach ($updates as $user_id => $new_access) {
            // Prevent non-superusers from changing the superuser's permissions
            if ($user_id === $superuser_id && $current_user_id !== $superuser_id) {
                continue;
            }
            $old_access = get_user_meta($user_id, SentinelPro_User_Access_Manager::META_KEY, true) ?: [];

            foreach ($new_access as $key => $new_val) {
                $old_val = $old_access[$key] ?? false;

                if ($old_val !== $new_val) {
                    self::log_access_change($user_id, $key, $old_val, $new_val);
                }
            }

            update_user_meta($user_id, SentinelPro_User_Access_Manager::META_KEY, $new_access);

            // 🚫 Do not alter clearance for SuperUser
            if ($user_id === $superuser_id) {
                continue;
            }

            // 🧠 Determine if user has any ALLOWED access
            $has_access = array_reduce(
                array_values($new_access),
                fn($carry, $allowed) => $carry || !empty($allowed),
                false
            );

            // 🔁 Sync clearance level
            $new_clearance = $has_access ? 'admin' : 'restricted';
            update_user_meta($user_id, 'sentinelpro_clearance_level', $new_clearance);
        }
    }



    private static function log_access_change($user_id, $page_key, $old_val, $new_val): void {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Insert into custom access logs table
        $wpdb->insert(
            "{$wpdb->prefix}sentinelpro_access_logs",
            [
                'user_id'    => $user_id,
                'page_key'   => $page_key,
                'old_value'  => $old_val ? 'ALLOWED' : 'RESTRICTED',
                'new_value'  => $new_val ? 'ALLOWED' : 'RESTRICTED',
                'changed_at' => current_time('mysql'),
            ]
        );
    }


    private static function success_notice(string $message): void {
        add_action('admin_notices', fn() => print('<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>'));
    }

    private static function error_notice(string $message): void {
        add_action('admin_notices', fn() => print('<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>'));
    }

    public static function ajax_import_csv_url(): void {
        self::ensure_valid_ajax_permissions('sentinelpro_user_mgmt_nonce', '_wpnonce');

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- URL validation and sanitization handled in validate_csv_url method, nonce verified in ensure_valid_ajax_permissions
        $raw_url = $_POST['url'] ?? '';
        $url     = self::validate_csv_url($raw_url);
        $body    = self::fetch_csv_content($url);

        wp_send_json_success(['content' => $body]);
    }


    private static function validate_csv_url(string $url): string {
        $url = esc_url_raw($url);

        if (
            !$url ||
            !filter_var($url, FILTER_VALIDATE_URL) ||
            !in_array(wp_parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
        ) {
            wp_send_json_error(['message' => 'Invalid or unsafe URL']);
        }
        
        // Additional security checks
        $parsed_url = wp_parse_url($url);
        
        // Block local file access
        if (isset($parsed_url['host']) && in_array($parsed_url['host'], ['localhost', '127.0.0.1', '::1'])) {
            wp_send_json_error(['message' => 'Local file access not allowed']);
        }
        
        // Block private IP ranges
        if (isset($parsed_url['host']) && filter_var($parsed_url['host'], FILTER_VALIDATE_IP)) {
            $ip = $parsed_url['host'];
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                // IP is public, allow it
            } else {
                wp_send_json_error(['message' => 'Private IP access not allowed']);
            }
        }
        
        // Check file extension
        $path = $parsed_url['path'] ?? '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            wp_send_json_error(['message' => 'Only CSV files are allowed']);
        }

        return $url;
    }

    private static function fetch_csv_content(string $url): string {
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'sslverify' => true,
            'user-agent' => 'SentinelPro/1.0'
        ]);

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- CSV fetch error logging for debugging
                error_log('[SentinelPro] CSV Fetch failed: ' . $response->get_error_message());
            }
            wp_send_json_error(['message' => 'Failed to fetch file.']);
        }

        $body = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');

        if (!$body) {
            wp_send_json_error(['message' => 'Empty response from file.']);
        }
        
        // Validate content type
        if ($content_type && !preg_match('/text\/csv|text\/plain|application\/csv/', $content_type)) {
            wp_send_json_error(['message' => 'Invalid content type. Only CSV files are allowed.']);
        }
        
        // Check for malicious content
        if (strpos($body, '<?php') !== false || strpos($body, '<?=') !== false) {
            wp_send_json_error(['message' => 'File contains potentially malicious content.']);
        }
        
        // Limit file size (max 1MB)
        if (strlen($body) > 1024 * 1024) {
            wp_send_json_error(['message' => 'File too large. Maximum size: 1MB']);
        }

        return $body;
    }

    private static function ensure_valid_ajax_permissions(string $nonce_action, string $nonce_field): void {
        if (!current_user_can('manage_options') || !check_ajax_referer($nonce_action, $nonce_field, false)) {
            wp_send_json_error(['message' => '❌ Permission denied or invalid nonce.'], 403);
        }
    }
}
