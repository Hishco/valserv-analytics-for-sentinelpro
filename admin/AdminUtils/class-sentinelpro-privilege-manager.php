<?php

if ( ! defined('ABSPATH') ) { exit; }

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- This file uses $wpdb->prepare() correctly throughout

/**
 * SentinelPro Privilege Manager
 * Comprehensive privilege escalation protection system
 */

class SentinelPro_Privilege_Manager {
    
    private static $instance = null;
    private $privilege_cache = [];
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('admin_init', [$this, 'init_privilege_protection']);
        add_action('wp_ajax_sentinelpro_change_user_role', [$this, 'secure_change_user_role']);
        add_action('wp_ajax_sentinelpro_change_clearance', [$this, 'secure_change_clearance']);
        add_action('wp_ajax_sentinelpro_change_access', [$this, 'secure_change_access']);
    }
    
    /**
     * Initialize privilege protection
     */
    public function init_privilege_protection() {
        // Monitor user role changes
        add_action('set_user_role', [$this, 'log_role_change'], 10, 3);
        
        // Monitor user meta changes
        add_action('updated_user_meta', [$this, 'log_meta_change'], 10, 4);
        
        // Prevent unauthorized privilege changes
        add_filter('user_has_cap', [$this, 'filter_user_capabilities'], 10, 4);
    }
    
    /**
     * Secure user role change
     */
    public function secure_change_user_role() {
        // Verify nonce
        if (!check_ajax_referer('sentinelpro_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed'], 403);
            return;
        }
        
        // Check if user can manage users
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient privileges'], 403);
            return;
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $new_role = sanitize_text_field(wp_unslash($_POST['new_role'] ?? ''));
        
        // Validate user ID
        if (!$user_id || !get_user_by('ID', $user_id)) {
            wp_send_json_error(['message' => 'Invalid user ID'], 400);
            return;
        }
        
        // Validate role
        $allowed_roles = ['subscriber', 'contributor', 'author', 'editor', 'administrator'];
        if (!in_array($new_role, $allowed_roles, true)) {
            wp_send_json_error(['message' => 'Invalid role'], 400);
            return;
        }
        
        // Prevent changing superuser role
        $superuser_id = (int) get_option('sentinelpro_superuser_id');
        if ($user_id === $superuser_id) {
            wp_send_json_error(['message' => 'Cannot change superuser role'], 403);
            return;
        }
        
        // Prevent self-role-change to higher privilege
        $current_user = wp_get_current_user();
        if ($user_id === $current_user->ID) {
            $current_role = $current_user->roles[0] ?? 'subscriber';
            if ($this->is_higher_privilege($new_role, $current_role)) {
                wp_send_json_error(['message' => 'Cannot elevate own privileges'], 403);
                return;
            }
        }
        
        // Log the change attempt
        $this->log_privilege_change($current_user->ID, $user_id, 'role_change', [
            'old_role' => get_user_meta($user_id, 'wp_capabilities', true),
            'new_role' => $new_role
        ]);
        
        // Perform the role change
        $user = new WP_User($user_id);
        $user->set_role($new_role);
        
        wp_send_json_success(['message' => 'User role updated successfully']);
    }
    
    /**
     * Secure clearance level change
     */
    public function secure_change_clearance() {
        // Verify nonce
        if (!check_ajax_referer('sentinelpro_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed'], 403);
            return;
        }
        
        // Check if user can manage users
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient privileges'], 403);
            return;
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $new_clearance = sanitize_text_field(wp_unslash($_POST['new_clearance'] ?? ''));
        
        // Validate user ID
        if (!$user_id || !get_user_by('ID', $user_id)) {
            wp_send_json_error(['message' => 'Invalid user ID'], 400);
            return;
        }
        
        // Validate clearance level
        $allowed_clearances = ['restricted', 'elevated', 'admin'];
        if (!in_array($new_clearance, $allowed_clearances, true)) {
            wp_send_json_error(['message' => 'Invalid clearance level'], 400);
            return;
        }
        
        // Prevent changing superuser clearance
        $superuser_id = (int) get_option('sentinelpro_superuser_id');
        if ($user_id === $superuser_id) {
            wp_send_json_error(['message' => 'Cannot change superuser clearance'], 403);
            return;
        }
        
        // Prevent self-clearance-elevation
        $current_user = wp_get_current_user();
        if ($user_id === $current_user->ID) {
            $current_clearance = get_user_meta($user_id, 'sentinelpro_clearance_level', true);
            if ($this->is_higher_clearance($new_clearance, $current_clearance)) {
                wp_send_json_error(['message' => 'Cannot elevate own clearance'], 403);
                return;
            }
        }
        
        // Log the change attempt
        $this->log_privilege_change($current_user->ID, $user_id, 'clearance_change', [
            'old_clearance' => get_user_meta($user_id, 'sentinelpro_clearance_level', true),
            'new_clearance' => $new_clearance
        ]);
        
        // Perform the clearance change
        update_user_meta($user_id, 'sentinelpro_clearance_level', $new_clearance);
        
        wp_send_json_success(['message' => 'User clearance updated successfully']);
    }
    
    /**
     * Secure access change
     */
    public function secure_change_access() {
        // Verify nonce
        if (!check_ajax_referer('sentinelpro_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed'], 403);
            return;
        }
        
        // Check if user can manage users
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient privileges'], 403);
            return;
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Access changes are validated and sanitized in the loop below
        $access_changes = wp_unslash($_POST['access_changes'] ?? []);
        
        // Validate user ID
        if (!$user_id || !get_user_by('ID', $user_id)) {
            wp_send_json_error(['message' => 'Invalid user ID'], 400);
            return;
        }
        
        // Validate access changes
        $allowed_access_keys = ['api_input', 'dashboard', 'user_mgmt', 'post_column'];
        $validated_changes = [];
        
        foreach ($access_changes as $key => $value) {
            if (in_array($key, $allowed_access_keys, true)) {
                $validated_changes[$key] = (bool) $value;
            }
        }
        
        if (empty($validated_changes)) {
            wp_send_json_error(['message' => 'No valid access changes'], 400);
            return;
        }
        
        // Prevent changing superuser access
        $superuser_id = (int) get_option('sentinelpro_superuser_id');
        if ($user_id === $superuser_id) {
            wp_send_json_error(['message' => 'Cannot change superuser access'], 403);
            return;
        }
        
        // Log the change attempt
        $current_user = wp_get_current_user();
        $old_access = get_user_meta($user_id, 'sentinelpro_access', true) ?: [];
        
        $this->log_privilege_change($current_user->ID, $user_id, 'access_change', [
            'old_access' => $old_access,
            'new_access' => $validated_changes
        ]);
        
        // Perform the access change
        update_user_meta($user_id, 'sentinelpro_access', $validated_changes);
        
        wp_send_json_success(['message' => 'User access updated successfully']);
    }
    
    /**
     * Log role changes
     */
    public function log_role_change($user_id, $role, $old_roles) {
        $old_role = !empty($old_roles) ? $old_roles[0] : 'none';
        
        $this->log_privilege_change(0, $user_id, 'role_change', [
            'old_role' => $old_role,
            'new_role' => $role,
            'changed_by' => 'system'
        ]);
    }
    
    /**
     * Log meta changes
     */
    public function log_meta_change($meta_id, $user_id, $meta_key, $meta_value) {
        // Only log sensitive meta changes
        $sensitive_keys = [
            'sentinelpro_clearance_level',
            'sentinelpro_access',
            'wp_capabilities'
        ];
        
        if (in_array($meta_key, $sensitive_keys, true)) {
            $this->log_privilege_change(0, $user_id, 'meta_change', [
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This is an array key, not a database query
                'meta_key' => $meta_key,
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- This is an array key, not a database query
                'meta_value' => $meta_value,
                'changed_by' => 'system'
            ]);
        }
    }
    
    /**
     * Filter user capabilities to prevent privilege escalation
     */
    public function filter_user_capabilities($allcaps, $caps, $args, $user) {
        // Prevent users from modifying their own high-privilege capabilities
        if (isset($args[0]) && in_array($args[0], ['manage_options', 'edit_users', 'delete_users'], true)) {
            $current_user = wp_get_current_user();
            if ($user->ID === $current_user->ID) {
                // Check if this is a legitimate admin action
                if (!$this->is_legitimate_admin_action()) {
                    unset($allcaps[$args[0]]);
                }
            }
        }
        
        return $allcaps;
    }
    
    /**
     * Check if action is legitimate admin action
     */
    private function is_legitimate_admin_action() {
        // Check if this is a legitimate admin request
        $legitimate_actions = [
            'admin_init',
            'admin_menu',
            'admin_enqueue_scripts',
            'wp_ajax_sentinelpro_change_user_role',
            'wp_ajax_sentinelpro_change_clearance',
            'wp_ajax_sentinelpro_change_access'
        ];
        
        $current_action = current_action();
        return in_array($current_action, $legitimate_actions, true);
    }
    
    /**
     * Check if role has higher privilege
     */
    private function is_higher_privilege($new_role, $current_role) {
        $role_hierarchy = [
            'subscriber' => 1,
            'contributor' => 2,
            'author' => 3,
            'editor' => 4,
            'administrator' => 5
        ];
        
        $new_level = $role_hierarchy[$new_role] ?? 0;
        $current_level = $role_hierarchy[$current_role] ?? 0;
        
        return $new_level > $current_level;
    }
    
    /**
     * Check if clearance has higher level
     */
    private function is_higher_clearance($new_clearance, $current_clearance) {
        $clearance_hierarchy = [
            'restricted' => 1,
            'elevated' => 2,
            'admin' => 3
        ];
        
        $new_level = $clearance_hierarchy[$new_clearance] ?? 0;
        $current_level = $clearance_hierarchy[$current_clearance] ?? 0;
        
        return $new_level > $current_level;
    }
    
    /**
     * Log privilege change
     */
    private function log_privilege_change($changer_id, $target_id, $change_type, $details) {
        if (class_exists('SentinelPro_Security_Manager')) {
            SentinelPro_Security_Manager::log_security_event($changer_id, $change_type, json_encode($details), 'high');
        } else {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security event logging is essential for audit trails
            error_log("SentinelPro Security: Privilege change - {$change_type} by user {$changer_id} on user {$target_id}: " . json_encode($details));
        }
    }
    
    /**
     * Get user privilege summary
     */
    public function get_user_privilege_summary($user_id) {
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return null;
        }
        
        $role = $user->roles[0] ?? 'none';
        $clearance = get_user_meta($user_id, 'sentinelpro_clearance_level', true) ?: 'restricted';
        $access = get_user_meta($user_id, 'sentinelpro_access', true) ?: [];
        
        return [
            'user_id' => $user_id,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'role' => $role,
            'clearance_level' => $clearance,
            'access_permissions' => $access,
            'is_superuser' => ($user_id === (int) get_option('sentinelpro_superuser_id')),
            'can_manage_users' => user_can($user_id, 'manage_options'),
            'can_edit_users' => user_can($user_id, 'edit_users'),
            'last_modified' => get_user_meta($user_id, 'sentinelpro_last_privilege_change', true)
        ];
    }
    
    /**
     * Validate privilege change request
     */
    public function validate_privilege_change($changer_id, $target_id, $change_type, $new_value) {
        $changer = get_user_by('ID', $changer_id);
        $target = get_user_by('ID', $target_id);
        
        if (!$changer || !$target) {
            return ['valid' => false, 'message' => 'Invalid user ID'];
        }
        
        // Check if changer has permission
        if (!user_can($changer_id, 'manage_options')) {
            return ['valid' => false, 'message' => 'Insufficient privileges'];
        }
        
        // Prevent changing superuser
        $superuser_id = (int) get_option('sentinelpro_superuser_id');
        if ($target_id === $superuser_id) {
            return ['valid' => false, 'message' => 'Cannot modify superuser'];
        }
        
        // Prevent self-elevation
        if ($changer_id === $target_id) {
            if ($change_type === 'role' && $this->is_higher_privilege($new_value, $changer->roles[0] ?? 'none')) {
                return ['valid' => false, 'message' => 'Cannot elevate own role'];
            }
            
            if ($change_type === 'clearance' && $this->is_higher_clearance($new_value, get_user_meta($changer_id, 'sentinelpro_clearance_level', true) ?: 'restricted')) {
                return ['valid' => false, 'message' => 'Cannot elevate own clearance'];
            }
        }
        
        return ['valid' => true, 'message' => 'Change validated'];
    }
}

// Initialize privilege manager
SentinelPro_Privilege_Manager::get_instance();
