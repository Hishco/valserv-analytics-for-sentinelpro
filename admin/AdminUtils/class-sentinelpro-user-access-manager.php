<?php

class SentinelPro_User_Access_Manager {

    public const META_KEY = 'sentinelpro_access';
    private static ?int $cached_superuser = null;

    public static function get_default_access_for_user(WP_User $user): array {
        $clearance = self::get_clearance_level($user->ID);
        if (method_exists('SentinelPro_Config', 'get_access_for_clearance')) {
            $access = SentinelPro_Config::get_access_for_clearance($clearance);
            if (is_array($access)) {
                return $access;
            }
        }
        // Fallback to hardcoded defaults
        $defaults = [
            'api_input'   => false,
            'dashboard'  => false,
            'user_mgmt'   => false,
            'post_column' => false,
        ];
        $superuser_id = self::get_safe_superuser_id();
        if ($user->ID === $superuser_id) {
            return array_fill_keys(array_keys($defaults), true);
        }
        return $defaults;
    }

    public static function user_has_access(string $page_key, int $user_id = null): bool {
        $user_id = $user_id ?? get_current_user_id();

        // ✅ SuperUser always has full access
        $superuser_id = self::get_safe_superuser_id();
        if ($user_id === $superuser_id) {
            return true;
        }

        // ✅ Fetch access meta
        $meta = get_user_meta($user_id, self::META_KEY, true);
        if (!is_array($meta)) {
            $meta = [];
        }

        // ✅ Check if user has explicit access to this page
        if (isset($meta[$page_key]) && !empty($meta[$page_key])) {
            return true;
        }

        // ✅ Special case: API Input page should be accessible to everyone until credentials are configured
        if ($page_key === 'api_input' && !self::are_api_credentials_configured()) {
            return true;
        }

        // ✅ Check clearance level for restricted/elevated users
        $clearance = self::get_clearance_level($user_id);
        if (in_array($clearance, ['restricted', 'elevated'], true)) {
            // Restricted users can only access API input
            return $page_key === 'api_input';
        }

        return false;
    }

    /**
     * Check if API credentials are properly configured
     */
    public static function are_api_credentials_configured(): bool {
        $options = get_option('sentinelpro_options', []);
        $account_name = $options['account_name'] ?? '';
        $property_id = $options['property_id'] ?? '';
        
        // Check if API key exists using the secure manager
        $api_key = '';
        if (class_exists('SentinelPro_Security_Manager')) {
            $api_key = SentinelPro_Security_Manager::get_api_key();
        }
        
        return !empty($account_name) && !empty($property_id) && !empty($api_key);
    }

    public static function get_safe_superuser_id(): int {
        if (self::$cached_superuser === null) {
            $superuser_id = (int) get_option('sentinelpro_superuser_id');
            
            // Verify the superuser still exists
            if ($superuser_id && get_user_by('ID', $superuser_id)) {
                self::$cached_superuser = $superuser_id;
            } else {
                self::$cached_superuser = 0;
            }
        }
        
        return self::$cached_superuser;
    }

    public static function maybe_assign_default_clearance($user_id): void {
        // Use the secure clearance level system
        if (class_exists('SentinelPro_Security_Manager')) {
            $current = SentinelPro_Security_Manager::get_clearance_level($user_id);
            
            if (in_array($current, ['admin', 'elevated', 'restricted'], true)) {
                return;
            }
            
            $default = SentinelPro_Config::get_default_clearance_level();
            SentinelPro_Security_Manager::store_clearance_level($user_id, $default);
        } else {
            // Fallback to direct user meta access
            $current = get_user_meta($user_id, 'sentinelpro_clearance_level', true);
            
            if (in_array($current, ['admin', 'elevated', 'restricted'], true)) {
                return;
            }
            
            $default = SentinelPro_Config::get_default_clearance_level();
            update_user_meta($user_id, 'sentinelpro_clearance_level', $default);
        }
    }

    public static function is_user_restricted($user_id = null): bool {
        $user_id = $user_id ?? get_current_user_id();
        $level = self::get_clearance_level($user_id);
        return in_array($level, ['restricted', 'elevated'], true);
    }

    public static function get_clearance_level($user_id = null): string {
        $user_id = $user_id ?? get_current_user_id();
        $superuser_id = (int) get_option('sentinelpro_superuser_id');

        // The SuperUser always gets 'admin' clearance (or their explicitly set level)
        if ($user_id === $superuser_id) {
            // Use secure clearance level system for superuser too
            if (class_exists('SentinelPro_Security_Manager')) {
                $clearance = SentinelPro_Security_Manager::get_clearance_level($user_id);
                return $clearance ?: 'admin';
            } else {
                return get_user_meta($user_id, 'sentinelpro_clearance_level', true) ?: 'admin';
            }
        }

        // Use the secure clearance level system
        if (class_exists('SentinelPro_Security_Manager')) {
            return SentinelPro_Security_Manager::get_clearance_level($user_id);
        } else {
            // Fallback to direct user meta access
            return get_user_meta($user_id, 'sentinelpro_clearance_level', true) ?: SentinelPro_Config::get_default_clearance_level();
        }
    }

    public static function all_access(bool $allowed): array {
        return [
            'api_input'   => $allowed,
            'dashboard'   => $allowed,
            'user_mgmt'   => $allowed,
            'post_column' => $allowed,
        ];
    }

    public static function promote_to_superuser(int $user_id): void {
        $existing_id = (int) get_option('sentinelpro_superuser_id');

        // Only assign SuperUser if none exists
        if ($existing_id && get_user_by('ID', $existing_id)) {
            return; // 🔒 Do nothing — one is already assigned
        }

        update_option('sentinelpro_superuser_id', $user_id);
        update_user_meta($user_id, self::META_KEY, self::all_access(true));
        
        // Set clearance level using secure system
        if (class_exists('SentinelPro_Security_Manager')) {
            SentinelPro_Security_Manager::store_clearance_level($user_id, 'admin');
        } else {
            update_user_meta($user_id, 'sentinelpro_clearance_level', 'admin');
        }
    }
}
add_action('user_register', ['SentinelPro_User_Access_Manager', 'maybe_assign_default_clearance']);
