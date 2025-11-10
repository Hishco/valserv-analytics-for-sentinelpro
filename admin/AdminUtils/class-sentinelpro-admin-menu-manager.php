<?php

if ( ! defined('ABSPATH') ) { exit; }

/**
 * Handles admin menu registration and conditional visibility
 */
class SentinelPro_Admin_Menu_Manager {

    // Page slug constants
    const PAGE_SETTINGS = 'sentinelpro-settings';
    const PAGE_API_INPUT = 'sentinelpro-api-input';
    const PAGE_EVENT_DATA = 'sentinelpro-event-data';
    const PAGE_USER_MANAGEMENT = 'sentinelpro-user-management';

    /**
     * Registers SentinelPro plugin menu and submenus.
     */
    public static function add(SentinelPro_Analytics_Admin $admin): void {
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Menu registration logging is essential for troubleshooting
            error_log("SentinelPro: Menu manager add() called");
        }
        
        // Always log menu registration attempt for troubleshooting
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Menu registration logging is essential for troubleshooting
        error_log("SentinelPro: Menu registration attempt - User ID: " . get_current_user_id());
        
        // Sanitize $_GET['page'] before comparing
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page parameter is used for menu routing, not form processing
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        
        if (
            $page === self::PAGE_API_INPUT &&
            SentinelPro_Auth_Handler::is_authenticated() &&
            SentinelPro_User_Access_Manager::get_clearance_level(get_current_user_id()) === 'admin'
        ) {
            wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SETTINGS));
            exit;
        }

        if (
            $page === self::PAGE_SETTINGS &&
            SentinelPro_User_Access_Manager::get_clearance_level(get_current_user_id()) === 'restricted'
        ) {
            wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_API_INPUT));
            exit;
        }

        $uid = get_current_user_id();
        $clearance = SentinelPro_User_Access_Manager::get_clearance_level($uid);
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- User clearance logging is essential for troubleshooting
            error_log("SentinelPro: User ID: {$uid}, Clearance: {$clearance}");
        }

        // Always log clearance level for troubleshooting
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- User clearance logging is essential for troubleshooting
        error_log("SentinelPro: User clearance level: {$clearance}");

        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- User clearance logging is essential for troubleshooting
            error_log("SentinelPro: Checking clearance level: {$clearance}");
        }
        
        // 🛡️ Allow restricted/elevated users to see the API Input tab, or anyone if credentials aren't configured
        if (in_array($clearance, ['restricted', 'elevated'], true) || 
            (empty($clearance) && !SentinelPro_User_Access_Manager::are_api_credentials_configured())) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Menu access logging is essential for troubleshooting
                error_log("SentinelPro: Adding restricted user menu");
            }

            $result = add_menu_page(
                'SentinelPro',
                'SentinelPro',
                'sentinelpro_access',
                self::PAGE_API_INPUT,
                [$admin, 'display_settings_page'],
                'dashicons-chart-line',
                60
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Menu registration logging is essential for troubleshooting
                error_log("SentinelPro: Restricted user menu result: " . ($result ? 'success' : 'failed'));
            }

            return; // ✅ Prevent access to any other tabs
        }

        // 💡 From here on: normal access based on user_has_access()
        $has_api      = SentinelPro_User_Access_Manager::user_has_access('api_input', $uid);
        $has_event    = SentinelPro_User_Access_Manager::user_has_access('dashboard', $uid);
        $has_mgmt     = SentinelPro_User_Access_Manager::user_has_access('user_mgmt', $uid);
        $has_auth     = current_user_can('read');
        $has_post_col = SentinelPro_User_Access_Manager::user_has_access('post_column', $uid);

        $superuser_id = (int) get_option('sentinelpro_superuser_id');
        
        // Only superuser can access API Input settings, or first admin during initial installation
        if ($uid !== $superuser_id) {
            $is_initial_installation = !$superuser_id && !SentinelPro_User_Access_Manager::are_api_credentials_configured();
            if (!$is_initial_installation || !current_user_can('manage_options')) {
                $has_api = false;
            }
        }
        
        // 🔧 FALLBACK: If user has no clearance level (new installation), set appropriate clearance
        if (empty($clearance)) {
            // Check if API credentials are configured first
            $credentials_configured = SentinelPro_User_Access_Manager::are_api_credentials_configured();
            
            if (!$credentials_configured) {
                // If no credentials configured, set as restricted regardless of role
                $clearance = 'restricted';
                SentinelPro_Security_Manager::store_clearance_level($uid, 'restricted');
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Installation setup logging is essential for troubleshooting
                error_log("SentinelPro: No API credentials configured - setting user {$uid} as restricted");
            } elseif (current_user_can('manage_options')) {
                $clearance = 'admin';
                // Set them as admin clearance for future use
                SentinelPro_Security_Manager::store_clearance_level($uid, 'admin');
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Installation setup logging is essential for troubleshooting
                error_log("SentinelPro: New installation detected - setting user {$uid} as admin");
            } else {
                // For non-admin users on fresh installation, set as restricted but allow API Input access
                $clearance = 'restricted';
                SentinelPro_Security_Manager::store_clearance_level($uid, 'restricted');
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Installation setup logging is essential for troubleshooting
                error_log("SentinelPro: New installation detected - setting user {$uid} as restricted");
            }
        } else {
            // 🔧 ADDITIONAL FIX: If user has clearance level but no API credentials, force restricted
            $credentials_configured = SentinelPro_User_Access_Manager::are_api_credentials_configured();
            if (!$credentials_configured && $clearance === 'admin') {
                // Force admin users to restricted when no credentials are configured
                $clearance = 'restricted';
                SentinelPro_Security_Manager::store_clearance_level($uid, 'restricted');
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Credential check logging is essential for troubleshooting
                error_log("SentinelPro: Admin user {$uid} forced to restricted due to no API credentials");
            }
        }
        
        if (!$has_api && !$has_event && !$has_mgmt && !$has_auth && !$has_post_col && $uid !== $superuser_id && !empty($clearance)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Access permission logging is essential for troubleshooting
            error_log("SentinelPro: No access permissions found - User has no menu access");
            return;
        }

        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Access permission logging is essential for troubleshooting
            error_log("SentinelPro: Checking admin access permissions - API: " . ($has_api ? 'yes' : 'no') . ", Dashboard: " . ($has_event ? 'yes' : 'no') . ", Management: " . ($has_mgmt ? 'yes' : 'no'));
        }
        
        // ✅ Main menu
        if ($clearance === 'admin') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Menu registration logging is essential for troubleshooting
                error_log("SentinelPro: Adding admin menu");
            }
            
            $result = add_menu_page(
                'SentinelPro Dashboard',
                'SentinelPro',
                'sentinelpro_access',
                self::PAGE_SETTINGS,
                [$admin, 'display_settings_page'],
                'dashicons-chart-line',
                60
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Menu registration logging is essential for troubleshooting
                error_log("SentinelPro: add_menu_page result: " . ($result ? 'success' : 'failed'));
            }

            if ($has_api) {
                add_submenu_page(
                    self::PAGE_SETTINGS,
                    'API Input',
                    'API Input',
                    'read',
                    self::PAGE_SETTINGS,
                    [$admin, 'display_settings_page']
                );
            }

            if ($has_event) {
                add_submenu_page(
                    self::PAGE_SETTINGS,
                    'Dashboard',
                    'Dashboard',
                    'read',
                    self::PAGE_EVENT_DATA,
                    [$admin, 'display_dashboard_page']
                );
            }

            if ($has_mgmt) {
                add_submenu_page(
                    self::PAGE_SETTINGS,
                    'User Management',
                    'User Management',
                    'read',
                    self::PAGE_USER_MANAGEMENT,
                    [$admin, 'display_user_management_page']
                );
            }

        }
    }

    /**
     * Conditionally hides submenus based on user access.
     */
    public static function maybe_hide(): void {
        $uid = get_current_user_id();
        $clearance = SentinelPro_User_Access_Manager::get_clearance_level($uid);
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Menu visibility logging is essential for troubleshooting
            error_log("SentinelPro: maybe_hide() called - User ID: {$uid}, Clearance: {$clearance}");
        }

        if (in_array($clearance, ['restricted', 'elevated'], true)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Menu visibility logging is essential for troubleshooting
                error_log("SentinelPro: Skipping hide for restricted/elevated user");
            }
            return; // ✅ Skip hiding for restricted/elevated users
        }

        if (!SentinelPro_User_Access_Manager::user_has_access('dashboard', $uid)) {
            remove_submenu_page(self::PAGE_SETTINGS, self::PAGE_EVENT_DATA);
        }

        if (!SentinelPro_User_Access_Manager::user_has_access('api_input', $uid)) {
            remove_submenu_page(self::PAGE_SETTINGS, self::PAGE_API_INPUT);
        }

        if (!SentinelPro_User_Access_Manager::user_has_access('user_mgmt', $uid)) {
            remove_submenu_page(self::PAGE_SETTINGS, self::PAGE_USER_MANAGEMENT);
        }

        $has_dashboard = SentinelPro_User_Access_Manager::user_has_access('dashboard', $uid);
        $has_api = SentinelPro_User_Access_Manager::user_has_access('api_input', $uid);
        $has_mgmt = SentinelPro_User_Access_Manager::user_has_access('user_mgmt', $uid);
        
        $should_remove_main = !($has_dashboard || $has_api || $has_mgmt);
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Menu visibility logging is essential for troubleshooting
            error_log("SentinelPro: maybe_hide() - Dashboard: " . ($has_dashboard ? 'yes' : 'no') . ", API: " . ($has_api ? 'yes' : 'no') . ", Management: " . ($has_mgmt ? 'yes' : 'no') . ", Should remove main: " . ($should_remove_main ? 'yes' : 'no'));
        }

        if ($should_remove_main) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Menu visibility logging is essential for troubleshooting
                error_log("SentinelPro: Removing main menu page");
            }
            global $submenu;
            if (isset($submenu[self::PAGE_SETTINGS])) {
                foreach ($submenu[self::PAGE_SETTINGS] as $item) {
                                    if ($item[2] === 'sentinelpro-auth') {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Menu visibility logging is essential for troubleshooting
                        error_log("SentinelPro: Keeping main menu due to auth tab");
                    }
                    return; // Keep main menu if auth tab exists
                }
                }
            }
            remove_menu_page(self::PAGE_SETTINGS);
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Menu visibility logging is essential for troubleshooting
                error_log("SentinelPro: Keeping main menu - user has access");
            }
        }
    }
    


    /**
     * Registers all menu and submenu hooks for SentinelPro.
     */
    public static function register_hooks(SentinelPro_Analytics_Admin $admin): void {
        add_action('admin_menu', function () use ($admin) {
            self::add($admin);
        }, 999); // Higher priority to ensure it runs after other plugins
        add_action('admin_head', [self::class, 'maybe_hide']);
        

        

        

    }
    
    
}
