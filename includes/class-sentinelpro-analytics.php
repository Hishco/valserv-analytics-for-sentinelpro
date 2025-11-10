<?php

if ( ! defined('ABSPATH') ) { exit; }

class SentinelPro_Analytics {
    private string $plugin_name = 'valserv-analytics-for-sentinelpro';
    private string $version = '1.0.0';
    private SentinelPro_Analytics_Loader $loader;

    public function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function load_dependencies(): void {
        if (!class_exists('SentinelPro_Analytics_Loader')) {
            require_once plugin_dir_path(__FILE__) . 'class-valserv-analytics-for-sentinelpro-loader.php';
        }
        if (!class_exists('SentinelPro_Analytics_Admin')) {
            require_once plugin_dir_path(__FILE__) . '../admin/class-valserv-analytics-for-sentinelpro-admin.php';
        }
        if (!class_exists('SentinelPro_Analytics_Public')) {
            require_once plugin_dir_path(__FILE__) . '../public/class-valserv-analytics-for-sentinelpro-public.php';
        }
        if (!class_exists('SentinelPro_Post_Metrics_Column')) {
            require_once plugin_dir_path(__FILE__) . '../admin/post-metrics-column.php';
        }
        $this->loader = new SentinelPro_Analytics_Loader();
    }

    private function define_admin_hooks(): void {
        $admin = new SentinelPro_Analytics_Admin();
        $metrics = SentinelPro_Post_Metrics_Column::instance();
        // Admin and metrics classes are self-hooking in their constructors
    }

    private function define_public_hooks(): void {
        $plugin_public = new SentinelPro_Analytics_Public($this->plugin_name, $this->version);

        // ✅ Fixed: now uses the correct variable
        $this->loader->add_action('wp_head', $plugin_public, 'inject_tracking_script');
    }

    public function run(): void {
        $this->loader->run();
    }
}
