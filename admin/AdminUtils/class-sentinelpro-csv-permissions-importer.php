<?php

if ( ! defined('ABSPATH') ) { exit; }

class SentinelPro_CSV_Permissions_Importer {

    private static array $expected_headers = [
        'User', 'Full Name', 'Email', 'Role',
        'API Input', 'Dashboard', 'User Management', 'Post Analytics Column'
    ];
// (can be kept as-is for export UI; no changes required)


    public static function parse_textarea_csv(string $raw_csv): array {
        $lines = array_map('str_getcsv', explode("\n", trim($raw_csv)));
        return self::parse_rows($lines);
    }

    public static function parse_file_upload(array $file_data): array {
        if (empty($file_data['tmp_name'])) {
            throw new Exception('🚫 File upload failed or is empty.');
        }
        
        // Use WordPress upload APIs for security
        $overrides = [
            'test_form' => false,
            'mimes' => ['csv' => 'text/csv'],
            'unique_filename_callback' => 'wp_unique_filename',
            'max_size' => 1024 * 1024 // 1MB
        ];
        
        $file = wp_handle_upload($file_data, $overrides);
        
        if (isset($file['error'])) {
            throw new Exception(esc_html('🚫 Upload error: ' . $file['error']));
        }
        
        // Additional security validation
        $check = wp_check_filetype_and_ext($file['file'], $file['url']);
        if (!$check['ext'] || $check['ext'] !== 'csv') {
            // Clean up the uploaded file
            wp_delete_file($file['file']);
            throw new Exception('🚫 Invalid file type. Only CSV files are allowed.');
        }
        
        // Enhanced security checks
        self::validate_uploaded_file_content($file['file']);
        
        $rows = [];
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CSV file reading for permissions import
        if (($handle = fopen($file['file'], 'r')) !== false) {
            while (($data = fgetcsv($handle)) !== false) {
                $rows[] = $data;
            }
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CSV file reading for permissions import
            fclose($handle);
        } else {
            // Clean up the uploaded file
            wp_delete_file($file['file']);
            throw new Exception('🚫 Unable to open uploaded file.');
        }
        
        // Clean up the uploaded file after processing
        wp_delete_file($file['file']);
        
        return self::parse_rows($rows);
    }
    
    /**
     * Validate uploaded file content for security
     */
    private static function validate_uploaded_file_content(string $file_path): void {
        // Check for PHP code in file content - SECURE VERSION
        $file_content = file_get_contents($file_path);
        if ($file_content === false) {
            throw new Exception('🚫 Unable to read uploaded file.');
        }
        
        // Enhanced security checks - PATTERN MATCHING ONLY, NO EXECUTION
        // These patterns are used to detect malicious files, not execute them
        $dangerous_patterns = [
            '<?php', '<?=', '<? ', '<?\n', '<?\r', '<?\t',
            'eval(', 'base64_decode(', 'shell_exec(', 'system(',
            'exec(', 'passthru(', 'include(', 'require(',
            'file_get_contents(', 'fopen(', 'file_put_contents('
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            if (stripos($file_content, $pattern) !== false) {
                throw new Exception(esc_html('🚫 File contains potentially malicious content: ' . $pattern));
            }
        }
        
        // Check for null bytes
        if (strpos($file_content, "\0") !== false) {
            throw new Exception('🚫 File contains null bytes');
        }
        
        // Check file size limit (max 1MB for CSV)
        if (strlen($file_content) > 1024 * 1024) {
            throw new Exception('🚫 File too large. Maximum size: 1MB');
        }
    }

    /**
     * Parses CSV data from a remote URL (e.g., Google Sheets public link).
     *
     * @param string $url The URL to the CSV file.
     * @return array An array of user access updates.
     * @throws Exception If the URL content cannot be fetched or parsed.
     */
    public static function parse_remote_csv(string $url): array {
        $response = wp_remote_get($url, [
            'timeout' => 30, // seconds
            'sslverify' => true, // Enable SSL verification for security
            'user-agent' => 'SentinelPro/1.0'
        ]);

        if (is_wp_error($response)) {
            throw new Exception(esc_html('🚫 Failed to fetch data from URL: ' . $response->get_error_message()));
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            throw new Exception(esc_html("🚫 Failed to fetch data from URL. HTTP status code: {$http_code}. Please ensure the URL is publicly accessible."));
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            throw new Exception('🚫 No content received from the provided URL.');
        }

        // Use fgetcsv for robust parsing
        $rows = [];
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CSV parsing for remote URL data
        $stream = fopen('php://memory', 'r+');
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CSV parsing for remote URL data
        fwrite($stream, $body);
        rewind($stream);
        while (($data = fgetcsv($stream)) !== false) {
            $rows[] = $data;
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CSV parsing for remote URL data
        fclose($stream);

        return self::parse_rows($rows);
    }


    private static function parse_rows(array $rows): array {
        if (!$rows || count($rows) < 2) {
            throw new Exception('🚫 Uploaded data is empty or malformed.');
        }

        // Normalize headers to slugs
        $raw_headers = $rows[0];
        $header_slugs = array_map(function ($h) {
            $h = trim($h, "\"\xEF\xBB\xBF"); // Trim BOM and quotes
            $h = strtolower($h);
            return preg_replace('/[^a-z0-9]+/', '_', $h);
        }, $raw_headers);

        $slug_to_index = array_flip($header_slugs);

        // Define expected slugs
        $expected_slugs = [
            'user', 'full_name', 'email', 'role',
            'api_input', 'dashboard', 'user_management', 'post_analytics_column'
        ];

        // Validate all required headers exist
        $missing = array_diff($expected_slugs, array_keys($slug_to_index));
        if (!empty($missing)) {
            throw new Exception(esc_html('🚫 Invalid CSV headers. Missing: ' . implode(', ', $missing)));
        }

        $users = get_users(['fields' => ['ID', 'user_email']]);
        $email_to_id = array_column($users, 'ID', 'user_email');

        $seen_emails = [];
        $updates = [];

        foreach (array_slice($rows, 1) as $i => $row) {
            if (!is_array($row)) continue;

            // Normalize row values and pad missing columns
            $row = array_map('trim', $row);
            $row = array_pad($row, count($raw_headers), '');

            $email = strtolower($row[$slug_to_index['email']] ?? '');

            if (!$email) {
                throw new Exception(esc_html("🚫 Row " . ($i + 2) . ": Missing email."));
            }

            if (in_array($email, $seen_emails, true)) {
                throw new Exception(esc_html("🚫 Row " . ($i + 2) . ": Duplicate email '$email'."));
            }

            if (!isset($email_to_id[$email])) {
                throw new Exception(esc_html("🚫 Row " . ($i + 2) . ": Unknown user '$email'."));
            }

            $permissions = [
                'api_input'        => strtoupper($row[$slug_to_index['api_input']] ?? ''),
                'dashboard'        => strtoupper($row[$slug_to_index['dashboard']] ?? ''),
                'user_mgmt'        => strtoupper($row[$slug_to_index['user_management']] ?? ''),
                'post_column'      => strtoupper($row[$slug_to_index['post_analytics_column']] ?? ''),
            ];

            foreach ($permissions as $key => $val) {
                if (!in_array($val, ['ALLOWED', 'RESTRICTED'], true)) {
                    throw new Exception(esc_html("🚫 Row " . ($i + 2) . ": Invalid value '$val' in column '$key'. Use ALLOWED or RESTRICTED only."));
                }
            }

            $seen_emails[] = $email;
            $uid = $email_to_id[$email];

            $updates[$uid] = [
                'api_input'   => $permissions['api_input'] === 'ALLOWED',
                'dashboard'   => $permissions['dashboard'] === 'ALLOWED',
                'user_mgmt'   => $permissions['user_mgmt'] === 'ALLOWED',
                'post_column' => $permissions['post_column'] === 'ALLOWED',
            ];
        }
        return $updates;
    }

    private static function parse_csv_updates(string $csv_raw): array {
        $csv = sanitize_textarea_field(stripslashes($csv_raw));
        $updates = SentinelPro_CSV_Permissions_Importer::parse_textarea_csv($csv);

        if (!is_array($updates) || empty($updates)) {
            throw new Exception('Parsed CSV data is empty or invalid.');
        }

        return $updates;
    }

    public static function get_expected_headers(): array {
        return apply_filters('sentinelpro_csv_expected_headers', self::$expected_headers);
    }
}
