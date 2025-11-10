<?php

if ( ! defined('ABSPATH') ) { exit; }

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- This file uses $wpdb->prepare() correctly throughout

// Ensure database manager is available
if (!class_exists('SentinelPro_Database_Manager')) {
    require_once SENTINELPRO_ANALYTICS_PLUGIN_DIR . 'admin/AdminUtils/class-sentinelpro-database-manager.php';
}

/**
 * Manages all script and style enqueues for SentinelPro admin pages.
 */
class SentinelPro_Admin_Script_Manager {



    /**
     * Get the correct plugin URL using WordPress helpers
     */
    private static function get_plugin_url($path = '') {
        return trailingslashit(SENTINELPRO_ANALYTICS_PLUGIN_URL) . ltrim($path, '/');
    }

    /**
     * Get the correct AJAX URL using WordPress helpers
     */
    private static function get_ajax_url() {
        return admin_url('admin-ajax.php');
    }

    /**
     * Conditionally enqueue admin scripts based on the current page.
     */
    public static function enqueue(string $current_page): void {
        // Capability check for security
        if (!current_user_can('manage_options')) {
            return;
        }

        // Sanitize current page for defense-in-depth
        $current_page = sanitize_key($current_page);

        // Common Scripts
        // Enqueue Chart.js itself as a dependency for other Chart.js related scripts
        wp_enqueue_script(
            'chartjs',
            self::get_plugin_url('assets/external-libs/chart.umd.min.js'),
            [],
            '4.5.1',
            true
        );

        wp_enqueue_script(
            'chartjs-adapter-date-fns',
            self::get_plugin_url('assets/external-libs/chartjs-adapter-date-fns.js'),
            ['chartjs'],
            '1.0',
            true
        );

        wp_enqueue_script(
            'sentinelpro-gauge-svg',
            self::get_plugin_url('admin/js/DashboardHelpers/GaugeSVGRenderer.js'),
            [], // no dependencies
            '1.0',
            true
        );

        wp_enqueue_style(
            'sentinelpro-shared',
            self::get_plugin_url('admin/css/shared-components.css'),
            [],
            '1.0'
        );

        wp_enqueue_style(
            'sentinelpro-layout',
            self::get_plugin_url('admin/css/layout.css'),
            [],
            '1.0'
        );

        wp_enqueue_style(
            'sentinelpro-base',
            self::get_plugin_url('admin/css/base.css'),
            [],
            '1.0'
        );

         // Add jQuery UI Autocomplete
        wp_enqueue_script('jquery-ui-autocomplete');

        // Include jQuery UI CSS (optional if your theme already includes it)
        wp_enqueue_style('jquery-ui-css', self::get_plugin_url('assets/external-libs/jquery-ui.css'), [], '1.0');

        // Localize for AJAX

        // SentinelPro Pages
        switch ($current_page) {
            case 'toplevel_page_sentinelpro-api-input':
            case 'toplevel_page_sentinelpro-settings':

                // Also enqueue settings specific JS if dashboard is the main settings page
                wp_enqueue_script(
                    'sentinelpro-api-settings',
                    self::get_plugin_url('admin/js/api-settings-page.js'),
                    [], // No specific JS dependencies
                    '1.0',
                    true
                );
                wp_enqueue_style(
                    'sentinelpro-api-settings',
                    self::get_plugin_url('admin/css/api-settings.css'),
                    [],
                    '1.0'
                );

                wp_localize_script('sentinelpro-api-settings', 'SentinelProAuth', [
                    'ajax_url' => self::get_ajax_url(),
                    'nonce'    => wp_create_nonce('sentinelpro_auth_nonce'),
                ]);
                
                wp_localize_script('sentinelpro-api-settings', 'SentinelProClearance', [
                    'ajax_url' => self::get_ajax_url(),
                    'nonce'    => wp_create_nonce('sentinelpro_nonce'),
                ]);
                break;

            case 'sentinelpro_page_sentinelpro-event-data':

                // Enqueue html2canvas and jsPDF for PDF export functionality in ChartRenderer
                wp_enqueue_script(
                    'sentinelpro-html2canvas',
                    self::get_plugin_url('assets/vendor/html2canvas.min.js'),
                    [],
                    '1.4.1',
                    true
                );

                wp_enqueue_script(
                    'sentinelpro-jspdf',
                    self::get_plugin_url('assets/vendor/jspdf.umd.min.js'),
                    [],
                    '2.5.1',
                    true
                );

                // Enqueue ChartRenderer.js first, as event-data-page.js depends on it.
                wp_enqueue_script(
                    'sentinelpro-chart-renderer',
                    self::get_plugin_url('admin/js/DashboardHelpers/ChartRenderer.js'),
                    ['chartjs', 'chartjs-adapter-date-fns'],
                    '1.0',
                    true
                );

                // Enqueue StatusPopup first
                wp_enqueue_script(
                    'sentinelpro-status-popup',
                    self::get_plugin_url('admin/js/DashboardHelpers/StatusPopup.js'),
                    ['jquery', 'moment'],
                    '1.0',
                    true
                );

                // Enqueue DateRangeValidator
                wp_enqueue_script(
                    'sentinelpro-date-validator',
                    self::get_plugin_url('admin/js/DashboardHelpers/DateRangeValidator.js'),
                    ['jquery', 'moment', 'sentinelpro-status-popup'],
                    '1.0',
                    true
                );

                wp_enqueue_script(
                    'sentinelpro-chart-render',
                    self::get_plugin_url('admin/js/dashboard-main.js'),
                    ['jquery', 'sentinelpro-chart-renderer', 'sentinelpro-status-popup', 'sentinelpro-date-validator', 'chartjs', 'chartjs-adapter-date-fns'],
                    '1.0',
                    true
                );

                wp_enqueue_script(
                    'sentinelpro-enhanced-dashboard',
                    self::get_plugin_url('admin/js/enhanced-dashboard.js'),
                    ['jquery', 'chartjs', 'chartjs-adapter-date-fns', 'daterangepicker', 'moment'],
                    '1.0',
                    true
                );

                $property_id = get_option('sentinelpro_options', [])['property_id'] ?? '';
                
                // Ensure the database manager has run to populate dimensions
                if (class_exists('SentinelPro_Database_Manager')) {
                    $db_manager = SentinelPro_Database_Manager::get_instance();
                    $db_manager->ensure_analytics_events_table_exists();
                }
                
                // Load dimensions from sentinelpro_events_table_dimensions (where they are actually stored)
                $dimensions = get_option('sentinelpro_events_table_dimensions', []);
                
                // If it's an associative array, use the keys as the dimension names
                if ($dimensions && is_array($dimensions) && array_values($dimensions) !== $dimensions) {
                    $dimensions = array_keys($dimensions);
                }
                

                



                
                // Add default dimensions if missing
                $default_dimensions = ['device', 'geo', 'browser', 'os', 'referrer'];
                foreach ($default_dimensions as $dim) {
                    if (!in_array($dim, $dimensions, true)) {
                        $dimensions[] = $dim;
                    }
                }
                wp_localize_script('sentinelpro-enhanced-dashboard', 'SentinelProCanonicalDimensions', $dimensions);
                
                wp_localize_script('sentinelpro-enhanced-dashboard', 'SentinelProAjax', [
                    'ajax_url' => self::get_ajax_url(),
                    'nonce'    => wp_create_nonce('sentinelpro_nonce'),
                ]);

                wp_localize_script('sentinelpro-chart-render', 'SentinelProAjax', [
                    'ajax_url' => self::get_ajax_url(),
                    'nonce'    => wp_create_nonce('sentinelpro_chart_nonce'),
                ]);

                wp_localize_script('sentinelpro-chart-render', 'SentinelProChartData', [
                    'data' => [],
                ]);

                wp_enqueue_style(
                    'sentinelpro-chart-page',
                    self::get_plugin_url('admin/css/event-data.css'),
                    [],
                    '1.0'
                );

                wp_enqueue_style(
                    'sentinelpro-enhanced-dashboard',
                    self::get_plugin_url('admin/css/enhanced-dashboard.css'),
                    [],
                    '1.0'
                );

                wp_enqueue_style(
                    'sentinelpro-datepicker-theme',
                    self::get_plugin_url('admin/css/datepicker-theme.css'),
                    [],
                    '1.0'
                );

                wp_localize_script('sentinelpro-chart-render', 'SentinelProAutocomplete', [
                    'ajax_url' => self::get_ajax_url(),
                    'nonce'    => wp_create_nonce('autocomplete_nonce'),
                ]);

                // Corrected path to daterangepicker.js
                wp_enqueue_script(
                'daterangepicker',
                trailingslashit(SENTINELPRO_ANALYTICS_PLUGIN_URL) . 'assets/DateRangePicker/daterangepicker.js',
                ['jquery', 'moment'],
                '3.1',
                true
                );

                wp_enqueue_style(
                'daterangepicker-style',
                trailingslashit(SENTINELPRO_ANALYTICS_PLUGIN_URL) . 'assets/DateRangePicker/daterangepicker.css',
                [],
                '1.0'
                );



                break;

            case 'sentinelpro_page_sentinelpro-user-management':

                // Enqueue all User Management Helper scripts first, as user-management.js depends on them.
                // The order below is logical based on the import structure:
                // Utils -> DOMCache, TableManager, FileUploader, PreviewRenderer -> UserManager (user-management.js)

                wp_enqueue_script(
                    'sentinelpro-um-utils',
                    self::get_plugin_url('admin/js/UserManagementHelpers/Utils.js'),
                    [], // No specific dependencies for basic utils, unless you add external libraries.
                    '1.0',
                    true
                );

                wp_enqueue_script(
                    'sentinelpro-um-domcache',
                    self::get_plugin_url('admin/js/UserManagementHelpers/DOMCache.js'),
                    [], // No direct JS dependencies other than internal browser APIs
                    '1.0',
                    true
                );

                wp_enqueue_script(
                    'sentinelpro-um-tablemanager',
                    self::get_plugin_url('admin/js/UserManagementHelpers/TableManager.js'),
                    ['sentinelpro-um-utils'], // TableManager uses createElement from Utils
                    '1.0',
                    true
                );

                wp_enqueue_script(
                    'sentinelpro-um-fileuploader',
                    self::get_plugin_url('admin/js/UserManagementHelpers/FileUploader.js'),
                    [], // Depends on global XLSX, not directly on other local modules via import
                    '1.0',
                    true
                );

                wp_enqueue_script(
                    'sheetjs',
                    self::get_plugin_url('assets/external-libs/xlsx.full.min.js'),
                    [],
                    '0.18.5',
                    true
                );

                wp_enqueue_script(
                    'sentinelpro-um-previewrenderer',
                    self::get_plugin_url('admin/js/UserManagementHelpers/PreviewRenderer.js'),
                    ['sentinelpro-um-utils'], // PreviewRenderer uses createElement and exportCSV from Utils
                    '1.0',
                    true
                );

                wp_enqueue_script(
                    'sentinelpro-user-management',
                    self::get_plugin_url('admin/js/user-management.js'),
                    ['sheetjs', 'sentinelpro-um-domcache', 'sentinelpro-um-tablemanager', 'sentinelpro-um-fileuploader', 'sentinelpro-um-previewrenderer', 'sentinelpro-um-utils'], // All helpers and SheetJS
                    '1.0',
                    true
                );

                wp_localize_script('sentinelpro-user-management', 'sentinelpro_user_management_vars', array(
                    'ajaxurl' => self::get_ajax_url(),
                    'nonce'   => wp_create_nonce('sentinelpro_nonce')
                ));


                wp_enqueue_style(
                    'sentinelpro-user-management',
                    self::get_plugin_url('admin/css/user-management.css'),
                    [],
                    '1.0'
                );
                break;
            case 'edit.php':
                wp_enqueue_script(
                    'sentinelpro-post-table-totals',
                    self::get_plugin_url('admin/js/post-table-totals.js'),
                    [],
                    '1.0',
                    true
                );

                wp_enqueue_style(
                    'sentinelpro-post-table-totals-style',
                    self::get_plugin_url('admin/css/post-table-totals.css'),
                    [],
                    '1.0'
                );
                break;
            default:
                break;
        }
    }

    public static function enqueue_and_localize_post_totals_script($hook_suffix) {
        // Capability check for security
        if (!current_user_can('edit_posts')) {
            return;
        }

        // Target only the 'edit.php' (posts list) screen
        global $pagenow; // Get current page filename (e.g., 'edit.php', 'post-new.php')
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ('edit.php' !== $pagenow || !$screen || $screen->post_type !== 'post') {
            return; // Not on the "All Posts" screen, so don't enqueue anything
        }

        // Check for cached totals first
        $totals = get_transient('valserv_post_totals');
        if (false === $totals) {
            // Fetch posts and calculate totals with safety cap
            $all_posts = get_posts([
                'posts_per_page'          => 5000, // Safety cap to prevent timeouts
                'post_type'               => 'post',
                'fields'                  => 'ids', // Only retrieve post IDs for efficiency
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Using EXISTS meta_query with strict caps and optimized args
                'meta_query'              => [ // Filter to only posts that might have views/sessions data
                    'relation' => 'OR',
                    [
                        'key'     => '_sentinelpro_views',
                        'compare' => 'EXISTS',
                    ],
                    [
                        'key'     => '_sentinelpro_sessions',
                        'compare' => 'EXISTS',
                    ],
                ],
                'no_found_rows'           => true, // Optimize query for no pagination needed
                'orderby'                 => 'none', // Avoid ORDER BY for speed
                // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters -- Performance optimization for admin dashboard totals
                'suppress_filters'        => true,  // Bypass filters for performance
                'update_post_meta_cache'  => false, // We fetch meta individually later if needed
                'update_post_term_cache'  => false, // Not using terms here
            ]);

            $total_views = 0;
            $total_sessions = 0;

            foreach ($all_posts as $post_id) {
                // Retrieve post meta, casting to (int) to ensure numeric operations
                $views = (int) get_post_meta($post_id, '_sentinelpro_views', true);
                $sessions = (int) get_post_meta($post_id, '_sentinelpro_sessions', true);
                $total_views += $views;
                $total_sessions += $sessions;
            }

            // Cache the totals for 5 minutes
            $totals = ['total_views' => $total_views, 'total_sessions' => $total_sessions];
            set_transient('valserv_post_totals', $totals, MINUTE_IN_SECONDS * 5);
        }

        $total_views = $totals['total_views'];
        $total_sessions = $totals['total_sessions'];

        // Localize the script with the calculated totals
        // This makes a JavaScript object named `sentinelProPostTotals` available globally
        // to the `sentinelpro-post-table-totals` script.
        wp_localize_script(
            'sentinelpro-post-table-totals', // The handle of the script this data is for
            'sentinelProPostTotals',          // The name of the JS object that will hold the data
            [
                'totalViews'    => $total_views,
                'totalSessions' => $total_sessions,
            ]
        );

        // Enqueue the post-table-totals.js script
        wp_enqueue_script(
            'sentinelpro-post-table-totals',
            self::get_plugin_url('admin/js/post-table-totals.js'),
            [], // No specific JS dependencies for this script, as it reads localized data
            '1.0', // Version number
            true // Load in the footer
        );

        // Enqueue the optional CSS for styling the totals row
        wp_enqueue_style(
            'sentinelpro-post-table-totals-style',
            self::get_plugin_url('admin/css/post-table-totals.css'),
            [],
            '1.0'
        );
    }
}

// ✅ Ensure module-based scripts are rendered with type="module"
add_filter('script_loader_tag', function ($tag, $handle) {
    $module_handles = [
        'sentinelpro-chart-render',
        'valserv-dashboard', // Dashboard script from admin renderer
        'valserv-user-management', // User management script from admin renderer
        'sentinelpro-api-settings',
        'sentinelpro-user-management', // Main user-management script
        'sentinelpro-chart-renderer', // Already there
        'sentinelpro-status-popup', // Status popup module
        'sentinelpro-date-validator', // Date range validator module
        // Add all new User Management Helper scripts to ensure they are loaded as modules
        'sentinelpro-um-utils',
        'sentinelpro-um-domcache',
        'sentinelpro-um-tablemanager',
        'sentinelpro-um-fileuploader',
        'sentinelpro-um-previewrenderer',
        'sentinelpro-post-table-totals',
        'sentinelpro-enhanced-dashboard', // <-- Ensure enhanced-dashboard is loaded as a module
    ];
    if (in_array($handle, $module_handles, true)) {
        return str_replace('<script ', '<script type="module" ', $tag);
    }
    return $tag;
}, 10, 2);

?>
