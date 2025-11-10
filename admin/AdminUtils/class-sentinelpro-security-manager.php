<?php
/**
 * SentinelPro Security Manager
 * 
 * Provides secure storage and retrieval of sensitive data like API keys
 * using salting and encryption techniques.
 * 
 * Enhanced with clearance level integrity protection, HMAC signatures,
 * tamper detection, and bypass prevention.
 */

class SentinelPro_Security_Manager {
    
    /**
     * Salt key for API key encryption
     */
    const SALT_KEY_OPTION = 'sentinelpro_security_salt';
    
    /**
     * Encryption method
     */
    const ENCRYPTION_METHOD = 'aes-256-cbc';
    
    /**
     * Clearance level integrity hash key
     */
    const CLEARANCE_INTEGRITY_KEY = 'sentinelpro_clearance_integrity';
    
    /**
     * API credentials hash key for integrity verification
     */
    const API_CREDS_HASH_KEY = 'sentinelpro_api_creds_hash';
    
    /**
     * Maximum clearance level attempts before lockout
     */
    const MAX_CLEARANCE_ATTEMPTS = 5;
    
    /**
     * Lockout duration in seconds (15 minutes)
     */
    const LOCKOUT_DURATION = 900;
    
    /**
     * HMAC secret for clearance level signatures
     */
    const CLEARANCE_HMAC_SECRET = 'sentinelpro_clearance_hmac_secret';
    
    /**
     * Audit log table name
     */
    const AUDIT_LOG_TABLE = 'sentinelpro_security_audit_log';
    
    /**
     * Initialize security settings
     */
    public static function init() {
        // Generate salt if it doesn't exist
        if (!get_option(self::SALT_KEY_OPTION)) {
            self::generate_salt();
        }
        
        // Generate HMAC secret if it doesn't exist
        if (!get_option(self::CLEARANCE_HMAC_SECRET)) {
            self::generate_hmac_secret();
        }
        
        // Initialize clearance level protection
        add_action('wp_ajax_sentinelpro_set_clearance', [__CLASS__, 'secure_set_clearance'], 5);
        add_action('wp_ajax_sentinelpro_get_clearance', [__CLASS__, 'secure_get_clearance'], 5);
        
        // Add integrity checks to page access
        add_action('admin_init', [__CLASS__, 'verify_clearance_integrity']);
        
        // Prevent direct access to sensitive files
        add_action('init', [__CLASS__, 'prevent_direct_file_access']);
        
        // Prevent file editing through WordPress
        // add_action('init', [__CLASS__, 'disable_file_editing']);
        
        // Monitor user meta changes for tampering
        add_action('updated_user_meta', [__CLASS__, 'monitor_user_meta_changes'], 10, 4);
        
        // Create audit log table if it doesn't exist
        add_action('init', [__CLASS__, 'ensure_audit_table_exists']);
        
        // Add security status check to admin notices
        add_action('admin_notices', [__CLASS__, 'display_security_alerts']);
        
        // Run integrity checks on all users (once per day)
        if (!get_transient('sentinelpro_daily_integrity_check')) {
            add_action('shutdown', [__CLASS__, 'run_daily_integrity_checks']);
            set_transient('sentinelpro_daily_integrity_check', true, 86400); // 24 hours
        }
        
        // Add automatic file integrity protection
        // add_action('init', [__CLASS__, 'protect_critical_files']);
        
        // Add automatic clearance level protection on every request
        add_action('init', [__CLASS__, 'enforce_clearance_integrity']);
        
        // Automatic file integrity monitoring
        // add_action('init', [__CLASS__, 'monitor_file_integrity']);
        // add_action('init', [__CLASS__, 'create_file_backups']);
        add_action('init', [__CLASS__, 'prevent_direct_file_access']);
    }
    
    /**
     * Automatically protect critical plugin files from tampering
     */
    public static function protect_critical_files() {
        $plugin_dir = SENTINELPRO_ANALYTICS_PLUGIN_DIR;
        $critical_files = [
            'sentinelpro.php',
            'admin/AdminUtils/class-sentinelpro-security-manager.php',
            'admin/AdminUtils/class-sentinelpro-user-access-manager.php',
            'admin/AdminUtils/class-sentinelpro-config.php'
        ];
        
        foreach ($critical_files as $file) {
            $file_path = $plugin_dir . $file;
            if (file_exists($file_path)) {
                $current_hash = hash_file('sha256', $file_path);
                $stored_hash = get_option("sentinelpro_file_hash_{$file}");
                
                if (!$stored_hash) {
                    // First time - store the hash
                    update_option("sentinelpro_file_hash_{$file}", $current_hash, false);
                } elseif ($stored_hash !== $current_hash) {
                    // File has been tampered with - attempt restoration
                    self::restore_tampered_file($file, $file_path, $stored_hash);
                }
            }
        }
    }
    

    
    /**
     * Attempt to restore a tampered file
     */
    private static function restore_tampered_file($filename, $file_path, $expected_hash) {
        // Log the tampering attempt
        self::log_security_event(0, 'file_tampering_detected', 
            "File: {$filename} - Hash mismatch detected", 'critical');
        
        // Try to restore from backup if available
        $backup_path = $file_path . '.backup';
        if (file_exists($backup_path)) {
            if (copy($backup_path, $file_path)) {
                self::log_security_event(0, 'file_restored', 
                    "File: {$filename} - Restored from backup", 'high');
                return;
            }
        }
        
        // If no backup, create one now and alert admin
        copy($file_path, $backup_path);
        set_transient('sentinelpro_file_tampering_alert', $filename, 86400);
        
        // Force user logout if they're logged in
        if (is_user_logged_in()) {
            wp_logout();
            wp_redirect(wp_login_url() . '?tampering_detected=1');
            exit;
        }
    }
    
    /**
     * Disable file editing through WordPress admin
     */
    public static function disable_file_editing() {
        // Disable file editing in WordPress admin
        if (!defined('DISALLOW_FILE_EDIT')) {
            define('DISALLOW_FILE_EDIT', true);
        }
        
        // Remove file editor menu items
        remove_submenu_page('themes.php', 'theme-editor.php');
        remove_submenu_page('plugins.php', 'plugin-editor.php');
        
        // Block access to file editor pages
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- File editor blocking is a safe admin action with capability check
        if (isset($_GET['page']) && in_array($_GET['page'], ['theme-editor.php', 'plugin-editor.php'])) {
            wp_die('File editing has been disabled for security reasons.');
        }
    }
    
    /**
     * Prevent direct access to sensitive plugin files
     */
    public static function prevent_direct_file_access() {
        // Always check for direct file access attempts
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $script_name = isset($_SERVER['SCRIPT_NAME']) ? sanitize_text_field(wp_unslash($_SERVER['SCRIPT_NAME'])) : '';
        
        // Block direct access to PHP files in plugin directory
        if (strpos($request_uri, '/wp-content/plugins/valserv-analytics-for-sentinelpro-new-cache/') !== false) {
            if (strpos($script_name, 'wp-content/plugins/valserv-analytics-for-sentinelpro-new-cache/') !== false) {
                // This is a direct file access - block it
                http_response_code(403);
                die('Direct access to plugin files is not allowed.');
            }
        }
        
        if (!defined('ABSPATH')) {
            // Check if this is a direct file access attempt
            $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
            $sensitive_patterns = [
                '/valserv-analytics-for-sentinelpro-new-cache/admin/AdminUtils/',
                '/valserv-analytics-for-sentinelpro-new-cache/includes/',
                '/valserv-analytics-for-sentinelpro-new-cache/sentinelpro.php'
            ];
            
            foreach ($sensitive_patterns as $pattern) {
                if (strpos($request_uri, $pattern) !== false) {
                    http_response_code(403);
                    die('Direct access not allowed.');
                }
            }
        }
    }
    
    /**
     * Generate a unique salt for this installation
     */
    private static function generate_salt() {
        // Create a unique salt using WordPress constants and random data
        $salt_components = [
            ABSPATH,
            wp_salt('auth'),
            wp_salt('secure_auth'), 
            wp_salt('logged_in'),
            wp_salt('nonce'),
            uniqid('sentinelpro_', true),
            wp_rand(),
            microtime(true),
            isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : 'localhost'
        ];
        
        $raw_salt = implode('|', $salt_components);
        $salt = hash('sha256', $raw_salt);
        
        update_option(self::SALT_KEY_OPTION, $salt, false); // Don't autoload
        
        return $salt;
    }
    
    /**
     * Get the security salt
     */
    private static function get_salt() {
        $salt = get_option(self::SALT_KEY_OPTION);
        
        if (!$salt) {
            $salt = self::generate_salt();
        }
        
        return $salt;
    }
    
    /**
     * Create an encryption key from the salt and a secret
     */
    private static function derive_key($salt) {
        // Use WordPress constants and the salt to derive a key
        $key_components = [
            $salt,
            AUTH_KEY ?? 'fallback_auth',
            SECURE_AUTH_KEY ?? 'fallback_secure', 
            LOGGED_IN_KEY ?? 'fallback_logged',
            NONCE_KEY ?? 'fallback_nonce',
            defined('DB_PASSWORD') ? DB_PASSWORD : 'fallback_db'
        ];
        
        $raw_key = implode(':', $key_components);
        
        // Derive a 32-byte key using PBKDF2
        return hash_pbkdf2('sha256', $raw_key, $salt, 10000, 32, true);
    }
    
    /**
     * Encrypt sensitive data
     */
    public static function encrypt($data) {
        if (empty($data)) {
            return '';
        }
        
        try {
            $salt = self::get_salt();
            $key = self::derive_key($salt);
            
            // Generate a random IV
            $iv_length = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
            $iv = openssl_random_pseudo_bytes($iv_length);
            
            // Encrypt the data
            $encrypted = openssl_encrypt($data, self::ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);
            
            if ($encrypted === false) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Encryption error logging is essential for security monitoring
                error_log('SentinelPro Security: Encryption failed');
                return $data; // Fallback to plain text
            }
            
            // Combine IV and encrypted data, then base64 encode
            $result = base64_encode($iv . $encrypted);
            
            // Add a prefix to identify encrypted data
            return 'sentinelpro_encrypted:' . $result;
            
        } catch (Exception $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Encryption error logging is essential for security monitoring
            error_log('SentinelPro Security: Encryption error - ' . $e->getMessage());
            return $data; // Fallback to plain text
        }
    }
    
    /**
     * Decrypt sensitive data
     */
    public static function decrypt($encrypted_data) {
        if (empty($encrypted_data)) {
            return '';
        }
        
        // Check if data is encrypted
        if (!self::is_encrypted($encrypted_data)) {
            return $encrypted_data; // Return as-is if not encrypted
        }
        
        try {
            // Remove the encryption prefix
            $data_without_prefix = str_replace('sentinelpro_encrypted:', '', $encrypted_data);
            $decoded = base64_decode($data_without_prefix);
            
            if ($decoded === false) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Base64 decode error logging is essential for security monitoring
                error_log('SentinelPro Security: Base64 decode failed');
                return $encrypted_data;
            }
            
            $salt = self::get_salt();
            $key = self::derive_key($salt);
            
            // Extract IV and encrypted data
            $iv_length = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
            $iv = substr($decoded, 0, $iv_length);
            $encrypted = substr($decoded, $iv_length);
            
            // Decrypt the data
            $decrypted = openssl_decrypt($encrypted, self::ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);
            
            if ($decrypted === false) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Decryption error logging is essential for security monitoring
                error_log('SentinelPro Security: Decryption failed');
                return $encrypted_data; // Return encrypted data if decryption fails
            }
            
            return $decrypted;
            
        } catch (Exception $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Decryption error logging is essential for security monitoring
            error_log('SentinelPro Security: Decryption error - ' . $e->getMessage());
            return $encrypted_data; // Fallback to encrypted data
        }
    }
    
    /**
     * Check if data is encrypted
     */
    public static function is_encrypted($data) {
        return is_string($data) && strpos($data, 'sentinelpro_encrypted:') === 0;
    }
    
    /**
     * Securely store API key
     */
    public static function store_api_key($api_key) {
        if (empty($api_key)) {
            return false;
        }
        
        $encrypted_key = self::encrypt($api_key);
        
        $options = get_option('sentinelpro_options', []);
        $options['api_key'] = $encrypted_key;
        
        $result = update_option('sentinelpro_options', $options);
        
        if ($result) {
            // Update API credentials hash for integrity checking
            self::update_api_creds_hash();
        }
        
        return $result;
    }
    
    /**
     * Retrieve and decrypt API key
     */
    public static function get_api_key() {
        $options = get_option('sentinelpro_options', []);
        $encrypted_key = $options['api_key'] ?? '';
        
        if (empty($encrypted_key)) {
            return '';
        }
        
        return self::decrypt($encrypted_key);
    }
    
    /**
     * Generate integrity hash for API credentials
     */
    private static function generate_api_creds_hash() {
        $options = get_option('sentinelpro_options', []);
        $api_key = $options['api_key'] ?? '';
        $account_name = $options['account_name'] ?? '';
        $property_id = $options['property_id'] ?? '';
        
        $creds_string = $api_key . '|' . $account_name . '|' . $property_id;
        return hash_hmac('sha256', $creds_string, self::get_salt());
    }
    
    /**
     * Update API credentials hash
     */
    private static function update_api_creds_hash() {
        $hash = self::generate_api_creds_hash();
        update_option(self::API_CREDS_HASH_KEY, $hash, false);
    }
    
    /**
     * Verify API credentials integrity
     */
    public static function verify_api_creds_integrity() {
        $stored_hash = get_option(self::API_CREDS_HASH_KEY);
        $current_hash = self::generate_api_creds_hash();
        
        return $stored_hash === $current_hash;
    }
    
    /**
     * Generate integrity hash for clearance level
     */
    private static function generate_clearance_integrity_hash($user_id, $clearance_level) {
        // Use the new HMAC signature system for better security
        return self::generate_clearance_signature($user_id, $clearance_level);
    }
    
    /**
     * Store clearance level with integrity protection
     */
    public static function store_clearance_level($user_id, $clearance_level) {
        if (!in_array($clearance_level, ['admin', 'elevated', 'restricted'], true)) {
            return false;
        }
        
        // Generate integrity hash
        $integrity_hash = self::generate_clearance_integrity_hash($user_id, $clearance_level);
        
        // Store clearance level
        update_user_meta($user_id, 'sentinelpro_clearance_level', $clearance_level);
        
        // Store integrity hash
        update_user_meta($user_id, 'sentinelpro_clearance_integrity', $integrity_hash);
        
        // Log the clearance level change
        self::log_clearance_change($user_id, $clearance_level);
        
        return true;
    }
    
    /**
     * Get clearance level with integrity verification
     */
    public static function get_clearance_level($user_id) {
        $clearance_level = get_user_meta($user_id, 'sentinelpro_clearance_level', true);
        $stored_integrity = get_user_meta($user_id, 'sentinelpro_clearance_integrity', true);
        
        if (empty($clearance_level)) {
            return 'restricted';
        }
        
        // Verify integrity
        $expected_integrity = self::generate_clearance_integrity_hash($user_id, $clearance_level);
        
        if ($stored_integrity !== $expected_integrity) {
            // Integrity check failed - reset to restricted
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Clearance integrity check logging is essential for security monitoring
            error_log("SentinelPro Security: Clearance level integrity check failed for user {$user_id}");
            self::store_clearance_level($user_id, 'restricted');
            return 'restricted';
        }
        
        return $clearance_level;
    }
    
    /**
     * Check if user is locked out due to too many clearance attempts
     */
    private static function is_user_locked_out($user_id) {
        $lockout_key = "sentinelpro_clearance_lockout_{$user_id}";
        $lockout_data = get_transient($lockout_key);
        
        if ($lockout_data) {
            $attempts = $lockout_data['attempts'] ?? 0;
            $lockout_time = $lockout_data['lockout_time'] ?? 0;
            
            if ($attempts >= self::MAX_CLEARANCE_ATTEMPTS && (time() - $lockout_time) < self::LOCKOUT_DURATION) {
                return true;
            }
            
            // Reset if lockout period has expired
            if ((time() - $lockout_time) >= self::LOCKOUT_DURATION) {
                delete_transient($lockout_key);
            }
        }
        
        return false;
    }
    
    /**
     * Record clearance level attempt
     */
    private static function record_clearance_attempt($user_id) {
        $lockout_key = "sentinelpro_clearance_lockout_{$user_id}";
        $lockout_data = get_transient($lockout_key) ?: ['attempts' => 0, 'lockout_time' => 0];
        
        $lockout_data['attempts']++;
        
        if ($lockout_data['attempts'] >= self::MAX_CLEARANCE_ATTEMPTS) {
            $lockout_data['lockout_time'] = time();
        }
        
        set_transient($lockout_key, $lockout_data, self::LOCKOUT_DURATION);
    }
    
    /**
     * Secure clearance level setting via AJAX
     */
    public static function secure_set_clearance() {
        // Check if user is locked out
        $user_id = get_current_user_id();
        if (self::is_user_locked_out($user_id)) {
            wp_send_json_error(['message' => 'Account temporarily locked due to too many attempts. Please try again later.'], 429);
            return;
        }
        
        // Verify nonce and permissions
        if (!check_ajax_referer('sentinelpro_nonce', 'nonce', false) || !current_user_can('read')) {
            self::record_clearance_attempt($user_id);
            wp_send_json_error(['message' => 'Unauthorized'], 403);
            return;
        }
        
        $requested_clearance = sanitize_text_field(wp_unslash($_POST['clearance'] ?? 'restricted'));
        
        // Validate clearance level
        if (!in_array($requested_clearance, ['admin', 'elevated', 'restricted'], true)) {
            self::record_clearance_attempt($user_id);
            wp_send_json_error(['message' => 'Invalid clearance level'], 400);
            return;
        }
        
        // Verify API credentials integrity before allowing clearance changes
        if (!self::verify_api_creds_integrity()) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- API credentials integrity check logging is essential for security monitoring
            error_log("SentinelPro Security: API credentials integrity check failed for user {$user_id}");
            wp_send_json_error(['message' => 'Security verification failed'], 403);
            return;
        }
        
        // Store clearance level with integrity protection
        if (self::store_clearance_level($user_id, $requested_clearance)) {
            wp_send_json_success(['clearance' => $requested_clearance]);
        } else {
            wp_send_json_error(['message' => 'Failed to set clearance level'], 500);
        }
    }
    
    /**
     * Secure clearance level retrieval via AJAX
     */
    public static function secure_get_clearance() {
        if (!check_ajax_referer('sentinelpro_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
            return;
        }
        
        $user_id = get_current_user_id();
        $clearance = self::get_clearance_level($user_id);
        
        wp_send_json_success(['clearance' => $clearance]);
    }
    
    /**
     * Verify clearance level integrity on admin pages
     */
    public static function verify_clearance_integrity() {
        if (!is_admin() || !current_user_can('read')) {
            return;
        }
        
        $user_id = get_current_user_id();
        $current_clearance = get_user_meta($user_id, 'sentinelpro_clearance_level', true);
        
        if (!empty($current_clearance)) {
            $verified_clearance = self::get_clearance_level($user_id);
            
            if ($current_clearance !== $verified_clearance) {
                // Clearance level was tampered with - log and reset
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Clearance tampering logging is essential for security monitoring
                error_log("SentinelPro Security: Clearance level tampering detected for user {$user_id}");
                self::log_security_violation($user_id, 'clearance_tampering', $current_clearance, $verified_clearance);
            }
        }
    }
    
    /**
     * Log clearance level changes
     */
    private static function log_clearance_change($user_id, $new_clearance) {
        $old_clearance = get_user_meta($user_id, 'sentinelpro_clearance_level', true);
        
        if ($old_clearance !== $new_clearance) {
            // Log the change
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Clearance level change logging is essential for security monitoring
            error_log("SentinelPro Security: Clearance level changed for user {$user_id} from {$old_clearance} to {$new_clearance}");
            
            // Log security event
            self::log_security_event($user_id, 'clearance_level_changed', 
                "Level: {$new_clearance}, Old level: {$old_clearance}", 'high');
        }
    }
    
    /**
     * Log security violations (simplified - just error log)
     */
    private static function log_security_violation($user_id, $violation_type, $old_value = '', $new_value = '') {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'user_id' => $user_id,
            'violation_type' => $violation_type,
            'old_value' => $old_value,
            'new_value' => $new_value,
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : 'unknown'
        ];
        
        // Log the security violation
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security violation logging is essential for monitoring
        error_log("SentinelPro Security Violation: " . json_encode($log_entry));
        
        // Store in database
        self::store_security_log($log_entry);
    }
    
    /**
     * Migrate existing plain text API key to encrypted storage
     */
    public static function migrate_existing_api_key() {
        $options = get_option('sentinelpro_options', []);
        $current_api_key = $options['api_key'] ?? '';
        
        // Skip if no API key or already encrypted
        if (empty($current_api_key) || self::is_encrypted($current_api_key)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- API key migration logging is essential for security monitoring
            error_log('SentinelPro Security: No API key migration needed');
            return false;
        }
        
        // Encrypt the existing key
        $encrypted_key = self::encrypt($current_api_key);
        $options['api_key'] = $encrypted_key;
        
        $result = update_option('sentinelpro_options', $options);
        
        if ($result) {
            // Update API credentials hash
            self::update_api_creds_hash();
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- API key migration logging is essential for security monitoring
            error_log('SentinelPro Security: Successfully migrated API key to encrypted storage');
        } else {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- API key migration error logging is essential for security monitoring
            error_log('SentinelPro Security: Failed to migrate API key to encrypted storage');
        }
        
        return $result;
    }
    
    /**
     * Migrate existing clearance levels to use HMAC signatures
     */
    public static function migrate_clearance_levels_to_hmac() {
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Migration query for clearance levels, necessary for security upgrade
        $users = get_users(['meta_key' => 'sentinelpro_clearance_level']);
        $migrated_count = 0;
        
        foreach ($users as $user) {
            $current_level = get_user_meta($user->ID, 'sentinelpro_clearance_level', true);
            $current_integrity = get_user_meta($user->ID, 'sentinelpro_clearance_integrity', true);
            
            if (!empty($current_level) && empty($current_integrity)) {
                // Generate new HMAC signature
                $new_signature = self::generate_clearance_signature($user->ID, $current_level);
                update_user_meta($user->ID, 'sentinelpro_clearance_integrity', $new_signature);
                $migrated_count++;
            }
        }
        
        if ($migrated_count > 0) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Clearance migration logging is essential for security monitoring
            error_log('SentinelPro Security: Clearance levels migrated to HMAC-protected storage');
        }
        
        return $migrated_count;
    }
    
    /**
     * Validate the encryption/decryption system
     */
    public static function test_encryption() {
        $test_data = 'test_api_key_12345_' . uniqid();
        
        $encrypted = self::encrypt($test_data);
        $decrypted = self::decrypt($encrypted);
        
        return $test_data === $decrypted;
    }
    
    /**
     * Get security status information
     */
    public static function get_security_status() {
        $options = get_option('sentinelpro_options', []);
        $api_key = $options['api_key'] ?? '';
        
        return [
            'salt_exists' => (bool) get_option(self::SALT_KEY_OPTION),
            'encryption_available' => function_exists('openssl_encrypt'),
            'api_key_exists' => !empty($api_key),
            'api_key_encrypted' => self::is_encrypted($api_key),
            'encryption_test_passed' => self::test_encryption(),
            'api_creds_integrity' => self::verify_api_creds_integrity()
        ];
    }
    
    /**
     * Regenerate security salt (use with caution - will invalidate existing encrypted data)
     */
    public static function regenerate_salt() {
        delete_option(self::SALT_KEY_OPTION);
        return self::generate_salt();
    }
    
    /**
     * Secure hash function for additional security operations
     */
    public static function secure_hash($data, $additional_salt = '') {
        $salt = self::get_salt() . $additional_salt;
        return hash_hmac('sha256', $data, $salt);
    }
    
    /**
     * Generate a secure random token
     */
    public static function generate_secure_token($length = 32) {
        if (function_exists('random_bytes')) {
            try {
                return bin2hex(random_bytes($length));
            } catch (Exception $e) {
                // Fallback to mt_rand
            }
        }
        
        // Fallback method
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $token = '';
        for ($i = 0; $i < $length * 2; $i++) {
            $token .= $characters[wp_rand(0, strlen($characters) - 1)];
        }
        return $token;
    }

    /**
     * Generate a unique HMAC secret for clearance level signatures
     */
    private static function generate_hmac_secret() {
        $secret_components = [
            wp_salt('auth'),
            wp_salt('secure_auth'),
            wp_salt('logged_in'),
            wp_salt('nonce'),
            uniqid('sentinelpro_hmac_', true),
            wp_rand(),
            microtime(true),
            isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : 'localhost',
            ABSPATH
        ];
        
        $raw_secret = implode('|', $secret_components);
        $secret = hash('sha256', $raw_secret);
        
        update_option(self::CLEARANCE_HMAC_SECRET, $secret, false);
        
        return $secret;
    }
    
    /**
     * Get the HMAC secret
     */
    private static function get_hmac_secret() {
        $secret = get_option(self::CLEARANCE_HMAC_SECRET);
        
        if (!$secret) {
            $secret = self::generate_hmac_secret();
        }
        
        return $secret;
    }
    
    /**
     * Create audit log table
     */
    public static function ensure_audit_table_exists() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::AUDIT_LOG_TABLE;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table existence check for audit table, table name is validated
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                user_id BIGINT UNSIGNED NOT NULL,
                action VARCHAR(100) NOT NULL,
                details TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY timestamp (timestamp),
                KEY severity (severity)
            ) $charset_collate;";
            
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
        }
    }
    
    /**
     * Log security event to audit table
     */
    public static function log_security_event($user_id, $action, $details = '', $severity = 'medium') {
        global $wpdb;
        $table_name = $wpdb->prefix . self::AUDIT_LOG_TABLE;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Insert into custom audit table with validated table name
        $wpdb->insert(
            $table_name,
            [
                'user_id' => $user_id,
                'action' => $action,
                'details' => $details,
                'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown',
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : 'unknown',
                'severity' => $severity
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );
    }
    
    /**
     * Monitor user meta changes for potential tampering
     */
    public static function monitor_user_meta_changes($meta_id, $user_id, $key, $value) {
        $sensitive_keys = ['sentinelpro_clearance_level', 'sentinelpro_clearance_integrity'];
        
        if (in_array($key, $sensitive_keys, true)) {
            $current_user_id = get_current_user_id();
            $current_user = wp_get_current_user();
            
            // Log the change
            $details = json_encode([
                'changed_key' => $key,
                'new_value' => $value,
                'changed_by_user_id' => $current_user_id,
                'changed_by_user_email' => $current_user->user_email ?? 'unknown',
                'timestamp' => current_time('mysql')
            ]);
            
            self::log_security_event($user_id, 'user_meta_changed', $details, 'high');
            
            // If this is a clearance level change, verify integrity immediately
            if ($key === 'sentinelpro_clearance_level') {
                add_action('shutdown', function() use ($user_id) {
                    self::verify_and_fix_clearance_integrity($user_id);
                });
            }
        }
    }
    
    /**
     * Automatically enforce clearance level integrity on every request
     */
    public static function enforce_clearance_integrity() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $stored_level = get_user_meta($user_id, 'sentinelpro_clearance_level', true);
        $stored_signature = get_user_meta($user_id, 'sentinelpro_clearance_integrity', true);
        
        if (!empty($stored_level)) {
            $expected_signature = self::generate_clearance_signature($user_id, $stored_level);
            
            if (!hash_equals($stored_signature, $expected_signature)) {
                // Tampering detected - immediately reset and log
                self::log_security_event($user_id, 'clearance_tampering_detected', 
                    "Level: {$stored_level} - Signature mismatch", 'critical');
                
                // Reset to restricted immediately
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security violation logging is essential for monitoring
                error_log("SentinelPro Security: Clearance tampering detected for user {$user_id} - resetting to restricted");
                
                // Reset to restricted immediately
                self::store_clearance_level($user_id, 'restricted');
                
                // Force logout and redirect to login
                wp_logout();
                wp_redirect(wp_login_url() . '?tampering_detected=1');
                exit;
            }
        }
    }
    
    /**
     * Verify and fix clearance level integrity for a specific user
     */
    private static function verify_and_fix_clearance_integrity($user_id) {
        $stored_level = get_user_meta($user_id, 'sentinelpro_clearance_level', true);
        $stored_signature = get_user_meta($user_id, 'sentinelpro_clearance_integrity', true);
        
        if (!empty($stored_level)) {
            $expected_signature = self::generate_clearance_signature($user_id, $stored_level);
            
            if (!hash_equals($stored_signature, $expected_signature)) {
                // Tampering detected - reset to restricted
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Clearance tampering logging is essential for security monitoring
                error_log("SentinelPro Security: Clearance level tampering detected for user {$user_id}");
                self::log_security_event($user_id, 'clearance_tampering_detected', 
                    "Level: {$stored_level}, Expected signature: {$expected_signature}, Stored signature: {$stored_signature}", 
                    'critical');
                
                // Reset to restricted
                self::store_clearance_level($user_id, 'restricted');
                
                // Notify administrators
                self::notify_admin_of_tampering($user_id, $stored_level);
            }
        }
    }
    
    /**
     * Notify administrators of detected tampering
     */
    private static function notify_admin_of_tampering($user_id, $tampered_level) {
        $admin_users = get_users(['role' => 'administrator']);
        
        foreach ($admin_users as $admin) {
            $notification_key = "sentinelpro_tampering_alert_{$user_id}_" . time();
            set_transient($notification_key, [
                'type' => 'tampering_detected',
                'user_id' => $user_id,
                'tampered_level' => $tampered_level,
                'timestamp' => current_time('mysql')
            ], 86400); // 24 hours
        }
    }
    
    /**
     * Display security alerts in admin
     */
    public static function display_security_alerts() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }
        
        // Check for tampering alerts
        $tampering_alerts = [];
        $transients = wp_cache_get('alloptions', 'options');
        
        if (is_array($transients)) {
            foreach ($transients as $key => $value) {
                if (strpos($key, 'sentinelpro_tampering_alert_') === 0) {
                    $alert_data = get_transient($key);
                    if ($alert_data && $alert_data['type'] === 'tampering_detected') {
                        $tampering_alerts[] = $alert_data;
                    }
                }
            }
        }
        
        // Display tampering alerts
        foreach ($tampering_alerts as $alert) {
            $user = get_userdata($alert['user_id']);
            $user_email = $user ? $user->user_email : 'Unknown User';
            
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>🚨 Security Alert:</strong> Clearance level tampering detected for user ';
            echo '<strong>' . esc_html($user_email) . '</strong> (ID: ' . esc_html($alert['user_id']) . '). ';
            echo 'User has been automatically reset to restricted access. Please investigate immediately.</p>';
            echo '</div>';
        }
        
        // Check for integrity failures
        $current_user_id = get_current_user_id();
        $current_level = get_user_meta($current_user_id, 'sentinelpro_clearance_level', true);
        
        if (!empty($current_level)) {
            $verified_level = self::get_clearance_level($current_user_id);
            
            if ($current_level !== $verified_level) {
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p><strong>⚠️ Security Warning:</strong> Your clearance level has been reset due to integrity verification failure. ';
                echo 'If you believe this is an error, please contact an administrator.</p>';
                echo '</div>';
            }
        }
    }
    
    /**
     * Generate HMAC signature for clearance level
     */
    private static function generate_clearance_signature($user_id, $clearance_level) {
        $secret = self::get_hmac_secret();
        $data = $user_id . '|' . $clearance_level . '|' . wp_salt('auth');
        return hash_hmac('sha256', $data, $secret);
    }
    
    /**
     * Verify HMAC signature for clearance level
     */
    private static function verify_clearance_signature($user_id, $clearance_level, $signature) {
        $expected = self::generate_clearance_signature($user_id, $clearance_level);
        return hash_equals($expected, $signature);
    }
    
    /**
     * Run daily integrity checks on all users
     */
    public static function run_daily_integrity_checks() {
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Daily integrity check query for clearance levels, necessary for security monitoring
        $users = get_users(['meta_key' => 'sentinelpro_clearance_level']);
        $issues_found = 0;
        
        foreach ($users as $user) {
            $current_level = get_user_meta($user->ID, 'sentinelpro_clearance_level', true);
            $current_integrity = get_user_meta($user->ID, 'sentinelpro_clearance_integrity', true);
            
            if (!empty($current_level)) {
                if (empty($current_integrity)) {
                    // Missing integrity hash - regenerate
                    $new_signature = self::generate_clearance_signature($user->ID, $current_level);
                    update_user_meta($user->ID, 'sentinelpro_clearance_integrity', $new_signature);
                    $issues_found++;
                } else {
                    // Verify existing integrity hash
                    $expected_signature = self::generate_clearance_signature($user->ID, $current_level);
                    if (!hash_equals($current_integrity, $expected_signature)) {
                        // Tampering detected - reset to restricted
                        self::store_clearance_level($user->ID, 'restricted');
                        self::log_security_event($user->ID, 'daily_check_tampering_detected', 
                            "Level: {$current_level}, Expected: {$expected_signature}, Stored: {$current_integrity}", 
                            'critical');
                        $issues_found++;
                    }
                }
            }
        }
        
        if ($issues_found > 0) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Daily integrity check logging is essential for security monitoring
            error_log("SentinelPro Security: Daily integrity check found {$issues_found} issues");
        }
        
        return $issues_found;
    }
}
