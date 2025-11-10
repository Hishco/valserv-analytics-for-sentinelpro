<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class SentinelPro_SuperUser_Enforcer {

    private const FULL_ACCESS_KEYS = ['api_input', 'dashboard', 'user_mgmt', 'post_column'];

    public function __construct(){
        add_action('update_option_sentinelpro_options', [$this, 'initialize_superuser_on_first_save'], 10, 3);
        // Schedule daily enforcement if not already scheduled
        if (!wp_next_scheduled('sentinelpro_enforce_superuser_access')) {
            wp_schedule_event(time(), 'daily', 'sentinelpro_enforce_superuser_access');
        }
        add_action('sentinelpro_enforce_superuser_access', [__CLASS__, 'enforce']);
    }

    public static function enforce(): void {
        $superuser_id = (int) get_option('sentinelpro_superuser_id');
        if (!$superuser_id) return;

        $users = get_users([
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Performance optimization for superuser enforcement
            'meta_key'      => 'sentinelpro_access',
            'meta_compare'  => 'EXISTS',
            'fields'        => ['ID'],
            'number'        => 500,
            'count_total'   => false,
        ]);

        foreach ($users as $user) {
            $user_id = is_object($user) ? $user->ID : (int) $user;
            if ($user_id === $superuser_id) continue;

            $meta = get_user_meta($user_id, 'sentinelpro_access', true);
            if (!is_array($meta)) continue;

            $has_all = array_reduce(self::FULL_ACCESS_KEYS, fn($carry, $key) =>
                $carry && !empty($meta[$key]), true
            );

            if ($has_all) {
                update_user_meta($user_id, 'sentinelpro_access', SentinelPro_User_Access_Manager::all_access(false));
            }
        }
    }

    public static function maybe_reassign_superuser(): void {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (
            empty($_POST['new_superuser']) ||
            !check_admin_referer('sentinelpro_reassign_superuser', 'sentinelpro_superuser_nonce')
        ) {
            return;
        }

        $new_id = (int) (isset($_POST['new_superuser']) ? sanitize_text_field(wp_unslash($_POST['new_superuser'])) : '');
        $old_id = (int) get_option('sentinelpro_superuser_id');

        if (self::is_valid_reassignment($new_id, $old_id)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- SuperUser changes must be logged for security audit
            error_log("SentinelPro: SuperUser changed from {$old_id} to {$new_id}");
            update_option('sentinelpro_superuser_id', $new_id);
            self::apply_superuser_grants($new_id);

            if ($old_id && get_user_by('ID', $old_id) && $old_id !== $new_id) {
                self::revoke_superuser_grants($old_id);
            }
            self::render_admin_notice('success', '✅ SuperUser reassigned successfully.');
        } else {
            self::render_admin_notice('error', '🚫 SuperUser reassignment failed or skipped. Please check logs for details.');
        }
    }


    private static function is_valid_reassignment(int $new_id, int $old_id): bool {
        return $new_id && $new_id !== $old_id && get_user_by('ID', $new_id);
    }

    private static function apply_superuser_grants(int $user_id): void {
        update_user_meta($user_id, SentinelPro_User_Access_Manager::META_KEY, SentinelPro_User_Access_Manager::all_access(true));
        update_user_meta($user_id, 'sentinelpro_clearance_level', 'admin');
    }

    private static function revoke_superuser_grants(int $user_id): void {
        update_user_meta($user_id, SentinelPro_User_Access_Manager::META_KEY, SentinelPro_User_Access_Manager::all_access(false));
        update_user_meta($user_id, 'sentinelpro_clearance_level', 'restricted');
    }

    public static function initialize_superuser_on_first_save($old_value, $new_value): void {
        if (self::superuser_exists()) {
            $id = get_option('sentinelpro_superuser_id');
            return;
        }

        if (!self::current_user_is_admin()) {
            $user = wp_get_current_user();
            return;
        }

        $current_user_id = get_current_user_id();
        self::assign_superuser($current_user_id);
    }

    private static function render_admin_notice(string $type, string $message): void {
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            add_action('admin_notices', function () use ($type, $message) {
                $safe_type = sanitize_html_class($type);
                $safe_message = wp_kses_post($message);
                echo '<div class="notice notice-' . esc_attr($safe_type) . ' is-dismissible"><p>' . wp_kses_post($safe_message) . '</p></div>';
            });
        }
    }

    private static function superuser_exists(): bool {
        return (bool) get_option('sentinelpro_superuser_id');
    }

    private static function current_user_is_admin(): bool {
        $user = wp_get_current_user();
        return in_array('administrator', (array) $user->roles, true);
    }

    private static function assign_superuser(int $user_id): void {
        update_option('sentinelpro_superuser_id', $user_id);

        update_user_meta($user_id, 'sentinelpro_access', [
            'api_input'   => true,
            'dashboard'   => true,
            'user_mgmt'   => true,
            'post_column' => true,
        ]);
    }
}
