<?php

class SentinelPro_Config {

    public static function get_access_pages(): array {
        return [
            'api_input'   => 'API Input',
            'dashboard'  => 'Dashboard',
            'user_mgmt'   => 'User Management',
            'post_column' => 'Post Analytics Column',
        ];
    }

    public static function get_supported_roles(): array {
        return [
            'administrator' => 'Administrator',
            'editor'        => 'Editor',
            'author'        => 'Author',
            'contributor'   => 'Contributor',
            'subscriber'    => 'Subscriber',
        ];
    }

    public static function get_clearance_levels() {
        return [
            'restricted',
            'elevated',
            'admin',
        ];
    }

    public static function get_default_clearance_level() {
        return 'restricted';
    }

}
