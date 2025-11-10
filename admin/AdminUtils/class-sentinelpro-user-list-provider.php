<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class SentinelPro_User_List_Provider {

    /**
     * Fetches paginated user list based on search, role, and offset.
     */
    public static function get_users(int $page, string $search, string $role, int $per_page = 20): array {
        $args = self::build_user_query_args($page, $search, $role, $per_page);
        return get_users($args);
    }


    /**
     * Counts total matching users for pagination purposes.
     */
    public static function count_users(string $search, string $role): int {
        $args = self::build_user_query_args(1, $search, $role, 0);
        $args['fields'] = 'ID';
        return count(get_users($args));
    }


    /**
     * Outputs HTML table rows for user access table.
     */
    public static function render_user_table_rows(array $users, int $superuser_id, array $pages, callable $default_access_callback): string {
        ob_start();

        foreach ($users as $user) {
            $savedAccess   = get_user_meta($user->ID, 'sentinelpro_access', true) ?: [];
            $defaultAccess = is_callable($default_access_callback)
                ? call_user_func($default_access_callback, $user)
                : [];
            $access = !empty($savedAccess) ? $savedAccess : $defaultAccess;

            echo '<tr>';
            echo "<td><code>" . esc_html($user->user_email) . "</code></td>";
            echo '<td>' . esc_html($user->display_name) . '</td>';

            $roleDisplay = isset($user->roles) && is_array($user->roles)
                ? implode(', ', array_map('esc_html', $user->roles))
                : '—';
            $roleNote = ((int) $user->ID === (int) $superuser_id) ? ' <strong style="color:#0073aa;">(SuperUser)</strong>' : '';
            echo '<td>' . esc_html($roleDisplay) . wp_kses_post($roleNote) . '</td>';

            foreach ($pages as $key => $label) {
                $hasAccess   = !empty($access[$key]);
                $labelText   = $hasAccess ? 'ALLOWED' : 'RESTRICTED';
                $color       = $hasAccess ? '#2ecc71' : '#e74c3c';
                $toggleValue = $hasAccess ? 1 : 0;

                if ($user->ID === $superuser_id) {
                    echo '<td style="text-align:center;">
                        <span class="sentinelpro-access-label" data-locked="1" style="display:inline-block; background:' . esc_attr($color) . '; color:#fff; padding:4px 10px; border-radius:12px; font-size:12px; opacity: 0.6; cursor: not-allowed;">
                            ' . esc_html($labelText) . '
                        </span>
                        <input type="hidden" name="sentinelpro_access[' . esc_attr($user->ID) . '][' . esc_attr($key) . ']" value="' . esc_attr($toggleValue) . '" disabled />
                    </td>';
                } else {
                    echo '<td style="text-align:center;">
                        <span class="sentinelpro-access-label" style="display:inline-block; background:' . esc_attr($color) . '; color:#fff; padding:4px 10px; border-radius:12px; font-size:12px; cursor:pointer;"
                            data-user="' . esc_attr($user->ID) . '" data-key="' . esc_attr($key) . '" data-status="' . esc_attr($toggleValue) . '">
                            ' . esc_html($labelText) . '
                        </span>
                        <input type="hidden" name="sentinelpro_access[' . esc_attr($user->ID) . '][' . esc_attr($key) . ']" value="' . esc_attr($toggleValue) . '" />
                    </td>';
                }
            }

            echo '</tr>';
        }

        return ob_get_clean();
    }

    public static function get_users_paginated(int $page = 1, string $search = '', string $role = '', int $per_page = 20): array {
        $args = self::build_user_query_args($page, $search, $role, $per_page);
        $args = apply_filters('sentinelpro_user_query_args', $args, $page, $search, $role);
        $args['count_total'] = true;
        $query = new WP_User_Query($args);
        $users = $query->get_results();
        $total = $query->get_total();
        return compact('users', 'total');
    }


    // SentinelPro_User_List_Provider (or new class SentinelPro_User_Formatter)
    public static function format_user_access($user, int $superuser_id, callable $default_cb): array {
        $savedAccess   = get_user_meta($user->ID, 'sentinelpro_access', true) ?: [];
        $defaultAccess = is_callable($default_cb) ? call_user_func($default_cb, $user) : [];
        $access        = array_merge($defaultAccess, $savedAccess);

        return [
            'id'           => $user->ID,
            'user_login'   => $user->user_login,
            'user_email'   => $user->user_email,
            'full_name'    => $user->display_name,
            'role'         => implode(', ', $user->roles),
            'is_superuser' => $user->ID === $superuser_id,
            'access'       => [
                'api_input'   => !empty($access['api_input']),
                'dashboard'   => !empty($access['dashboard']),
                'user_mgmt'   => !empty($access['user_mgmt']),
                'post_column' => !empty($access['post_column']),
            ],
        ];
    }

    private static function build_user_query_args(int $page, string $search, string $role, int $per_page): array {
        $args = [
            'number' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'search_columns' => ['user_email', 'display_name'],
        ];

        if (!empty($search)) {
            $args['search'] = "*{$search}*";
        }

        if (!empty($role)) {
            $args['role'] = $role;
        }
        return $args;
    }
}
