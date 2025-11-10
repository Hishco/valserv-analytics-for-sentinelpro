<?php

if ( ! defined('ABSPATH') ) { exit; }

/**
 * Admin Settings Page for SentinelPro Analytics Plugin.
 *
 * This file is deprecated as the settings page rendering logic
 * has been moved into `class-valserv-analytics-for-sentinelpro-admin.php` for better organization.
 *
 * This file previously contained the functions for:
 * - Adding the plugin settings page to the WordPress admin menu.
 * - Registering the plugin settings and their fields.
 * - Rendering the actual HTML for the settings form.
 *
 * For future development, all admin-related settings page logic should be
 * implemented within the `SentinelPro_Analytics_Admin` class.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
 * NOTE: The logic for creating the settings page has been moved
 * into the `SentinelPro_Analytics_Admin` class.
 *
 * The `admin/settings-page.php` file is now essentially a placeholder
 * or will be removed if no other specific logic needs to reside here.
 *
 * The main plugin file `sentinelpro.php` now includes:
 * `admin/class-valserv-analytics-for-sentinelpro-admin.php`
 * which contains the actual settings page implementation.
 */
