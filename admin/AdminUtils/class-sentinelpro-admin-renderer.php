<?php

if ( ! defined('ABSPATH') ) { exit; }

/**
 * Renders SentinelPro admin page views.
 */
class SentinelPro_Admin_Renderer {



    public static function render_dashboard_page(): void {
        $user_id = get_current_user_id();
        
        // Enqueue dashboard script properly as a module
        wp_enqueue_script(
            'valserv-dashboard',
            SENTINELPRO_ANALYTICS_PLUGIN_URL . 'admin/js/dashboard-main.js',
            array('jquery'),
            SENTINELPRO_ANALYTICS_VERSION,
            true
        );
        
        // Enqueue dashboard filters script
        wp_enqueue_script(
            'valserv-dashboard-filters',
            SENTINELPRO_ANALYTICS_PLUGIN_URL . 'admin/js/dashboard-filters.js',
            array('jquery'),
            SENTINELPRO_ANALYTICS_VERSION,
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script(
            'valserv-dashboard',
            'valservDashboardData',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sentinelpro_nonce')
            )
        );
        
        $clearance = SentinelPro_User_Access_Manager::get_clearance_level($user_id);
        if ($clearance !== 'admin') {
            wp_die(esc_html__('Access denied. You do not have permission to view this page.', 'valserv-analytics-for-sentinelpro'));
        }
        echo '<div class="sentinelpro-dashboard-wrapper"><div class="wrap">';
        // Add logo to top left


        // Header
        echo '<div class="sentinelpro-page-title">';
        echo '<h1><span class="dashicons dashicons-chart-bar"></span> ' . esc_html__('SentinelPro Analytics Dashboard', 'valserv-analytics-for-sentinelpro') . '</h1>';
        echo '<p class="description">' . esc_html__('Visualize user behavior by sessions or custom events. Use the filters below to refine your dataset and compare across dates or metrics.', 'valserv-analytics-for-sentinelpro') . '</p>';
        echo '</div>';

        // Toggle
        echo '<button id="toggle-filters" class="button" style="margin-bottom: 10px;">' . esc_html__('Hide Filters', 'valserv-analytics-for-sentinelpro') . '</button>';

        // === TOP CONTROLS SECTION ===
        echo '<div class="sentinelpro-top-controls">';
        echo '<div class="sentinelpro-control-pill">';
        echo '<span class="sentinelpro-pill-label"><span class="dashicons dashicons-calendar"></span> ' . esc_html__('FROM', 'valserv-analytics-for-sentinelpro') . '</span>';
        echo '<input type="text" id="filter-daterange" class="sentinelpro-control-select" />';
        echo '<input type="hidden" id="filter-start" />';
        echo '<input type="hidden" id="filter-end" />';
        echo '<input type="hidden" id="compare-start" />';
        echo '<input type="hidden" id="compare-end" />';
        echo '<div id="custom-date-indicator" style="display: none;">';
        echo '<span class="sentinelpro-custom-chip"><span>' . esc_html__('Custom Dates', 'valserv-analytics-for-sentinelpro') . '</span><span id="reset-dates">✕</span></span>';
        echo '</div>';
        echo '</div>';
        // FILTERS BUTTON
        echo '<div class="sentinelpro-control-pill pill-btn">';
        echo '<button id="filters-button" class="sentinelpro-filters-btn">';
        echo '<span class="dashicons dashicons-filter"></span> ' . esc_html__('FILTERS', 'valserv-analytics-for-sentinelpro') . '';
        echo '</button>';
        echo '</div>';
        echo '</div>';

        // === SELECTED FILTERS SECTION ===
        echo '<div class="sentinelpro-selected-filters">';
        echo '<span class="sentinelpro-pill-label" style="margin-right: 8px;">' . esc_html__('SELECTED FILTERS:', 'valserv-analytics-for-sentinelpro') . '</span>';
        echo '<div id="filter-tags-container" class="sentinelpro-filter-tags">';
        echo '<span class="sentinelpro-filter-tag-pill">' . esc_html__('Desktop', 'valserv-analytics-for-sentinelpro') . ' <button class="remove-filter">×</button></span>';
        echo '<span class="sentinelpro-filter-tag-pill">' . esc_html__('Mobile', 'valserv-analytics-for-sentinelpro') . ' <button class="remove-filter">×</button></span>';
        echo '<span class="sentinelpro-filter-tag-pill">' . esc_html__('Tablet', 'valserv-analytics-for-sentinelpro') . ' <button class="remove-filter">×</button></span>';
        echo '</div>';
        echo '</div>';

        // === CHART SECTION ===
        echo '<div class="sentinelpro-chart-section">';
        echo '<div id="sentinelpro-status-message" class="sentinelpro-status-message" style="margin:10px 0;"></div>';
        echo '<div class="sentinelpro-chart-header">';
        echo '<div class="sentinelpro-control-pill">';
        echo '<span class="sentinelpro-pill-label">' . esc_html__('METRICS', 'valserv-analytics-for-sentinelpro') . '</span>';
        echo '<select id="metrics-select" class="sentinelpro-control-select sentinelpro-metrics-select" style="padding-right: 40px;">';
        echo '<option value="sessions" selected>' . esc_html__('Sessions', 'valserv-analytics-for-sentinelpro') . '</option>';
        echo '<option value="visits">' . esc_html__('Visits', 'valserv-analytics-for-sentinelpro') . '</option>';
        echo '<option value="views">' . esc_html__('Views', 'valserv-analytics-for-sentinelpro') . '</option>';
        // echo '<option value="bounce_rate">' . esc_html__('Bounce Rate', 'valserv-analytics-for-sentinelpro') . '</option>';
        echo '</select>';
        echo '</div>';
        echo '</div>';
        // Chart.js canvas div
        echo '<div id="sentinelpro-chart" style="width:100%;height:400px;"><canvas></canvas></div>';
        echo '</div>';

        // === DATA TABLE SECTION ===
        echo '<div class="sentinelpro-data-table-section">';
        echo '<div class="sentinelpro-table-header" style="display: flex; justify-content: space-between; align-items: center;">';
        echo '<h3 style="margin:0;">' . esc_html__('Data Breakdown', 'valserv-analytics-for-sentinelpro') . '</h3>';
        echo '<div style="display:flex;gap:8px;">';
        echo '<button id="export-chart-btn" class="sentinelpro-export-btn"><span class="dashicons dashicons-download"></span> ' . esc_html__('Export Chart', 'valserv-analytics-for-sentinelpro') . '</button>';
        echo '<button id="export-data-btn" class="sentinelpro-export-btn"><span class="dashicons dashicons-download"></span> ' . esc_html__('Export Table', 'valserv-analytics-for-sentinelpro') . '</button>';
        echo '<button id="export-chart-table-btn" class="sentinelpro-export-btn"><span class="dashicons dashicons-download"></span> ' . esc_html__('Export Chart & Table', 'valserv-analytics-for-sentinelpro') . '</button>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="sentinelpro-table-container">';
        echo '<table id="sentinelpro-data-table" class="sentinelpro-data-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th class="sortable" data-key="date" style="text-align:left;">Date</th>';
        echo '<th class="sortable" data-key="sessions" style="text-align:center;">Sessions</th>';
        echo '<th class="sortable" data-key="visits" style="text-align:center;">Visits</th>';
        echo '<th class="sortable" data-key="views" style="text-align:center;">Views</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody id="sentinelpro-data-table-body">';
        echo '<tr class="loading-row">';
        echo '<td colspan="4">Loading data...</td>';
        echo '</tr>';
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        
        echo '<div class="sentinelpro-pagination-controls">';
        echo '<button class="pagination-btn">First</button>';
        echo '<button class="pagination-btn active">1</button>';
        echo '<button class="pagination-btn">Last</button>';
        echo '</div>';
        echo '</div>';

        // === FILTERS MODAL ===
        echo '<div id="filters-modal" class="sentinelpro-modal" style="display: none;">';
        echo '<div class="sentinelpro-modal-content" style="max-width:480px;">';
        echo '<div class="sentinelpro-modal-header">';
        echo '<h3><span class="dashicons dashicons-filter"></span> FILTERS</h3>';
        echo '<button id="close-filters-modal" class="sentinelpro-close-btn">×</button>';
        echo '</div>';
        echo '<div class="sentinelpro-modal-body">';
        
        // DEFAULT DIMENSIONS SECTION
        echo '<div class="sentinelpro-filter-section">';
        echo '<h4 class="sentinelpro-section-title">DEFAULT DIMENSIONS</h4>';
        
        // Device (with count indicator)
        echo '<div class="sentinelpro-filter-item">';
        echo '<div class="sentinelpro-filter-item-header" data-api-key="device">';
        echo '<span>' . esc_html__('DEVICE', 'valserv-analytics-for-sentinelpro') . '</span>';
        echo '<span class="sentinelpro-filter-count">3</span>';
        echo '<span class="sentinelpro-filter-chevron">▼</span>';
        echo '</div>';
        echo '<div class="sentinelpro-filter-item-content">';
        echo '<div class="sentinelpro-checkbox-group">';
        echo '<label><input type="checkbox" value="desktop" checked> ' . esc_html__('Desktop', 'valserv-analytics-for-sentinelpro') . '</label>';
        echo '<label><input type="checkbox" value="mobile" checked> ' . esc_html__('Mobile', 'valserv-analytics-for-sentinelpro') . '</label>';
        echo '<label><input type="checkbox" value="tablet" checked> ' . esc_html__('Tablet', 'valserv-analytics-for-sentinelpro') . '</label>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Other default dimensions
        $defaultDimensions = ['GEO', 'REFERRER', 'OPERATING SYSTEM', 'BROWSER']; // Removed 'AD BLOCK'
        $dimensionApiKeyMap = [
            'DEVICE' => 'device',
            'GEO' => 'geo',
            'REFERRER' => 'referrer',
            'OPERATING SYSTEM' => 'os',
            'BROWSER' => 'browser',
            // 'AD BLOCK' => 'adblock', // Removed
        ];
        foreach ($defaultDimensions as $dimension) {
            $id = strtolower(str_replace(' ', '-', $dimension));
            $apiKey = $dimensionApiKeyMap[$dimension] ?? '';
            echo '<div class="sentinelpro-filter-item">';
            echo '<div class="sentinelpro-filter-item-header"' . ($apiKey ? ' data-api-key="' . esc_attr($apiKey) . '"' : '') . '>';
            echo '<span>' . esc_html($dimension) . '</span>';
            echo '<span class="sentinelpro-filter-chevron">▼</span>';
            echo '</div>';
            echo '<div class="sentinelpro-filter-item-content">';
            if ($dimension === 'GEO') {
                // Five checkboxes for top countries
                echo '<div class="sentinelpro-checkbox-group" id="geo-checkbox-group">';
                echo '<label><input type="checkbox" value="US"> ' . esc_html__('US', 'valserv-analytics-for-sentinelpro') . '</label>';
                echo '<label><input type="checkbox" value="GB"> ' . esc_html__('GB', 'valserv-analytics-for-sentinelpro') . '</label>';
                echo '<label><input type="checkbox" value="CA"> ' . esc_html__('CA', 'valserv-analytics-for-sentinelpro') . '</label>';
                echo '</div>';
                // Custom country checkboxes container
                echo '<div id="geo-custom-checkboxes"></div>';
                // Search box for other countries
                echo '<div style="margin-top:10px;">';
                echo '<input type="text" id="geo-search-box" class="sentinelpro-control-select" placeholder="' . esc_attr__('Search country...', 'valserv-analytics-for-sentinelpro') . '" autocomplete="off">';
                echo '<div id="geo-search-results" class="sentinelpro-search-results" style="display:none;"></div>';
                echo '</div>';
            } else if ($dimension === 'REFERRER') {
                // Multi-column checkbox list for Referrer
                $referrerOptions = [
                    'Android App', 'Apple News', 'AOL', 'Bing', 'Brave', 'Direct', 'Dogpile', 'DuckDuckGo', 'Ecosia', 'Email',
                    'Facebook', 'Flipboard', 'Google', 'IMDb', 'Instagram', 'Internal Network', 'LinkedIn', 'MSN', 'Outlook Live',
                    'Pinterest', 'Quora', 'Qwant', 'Reddit', 'SmartNews', 'Startpage', 'Steam', 'Threads', 'Twitter', 'Wikipedia',
                    'Yahoo', 'Yandex', 'Youtube', 'Other Referrers', 'Other Search', 'Other Social'
                ];
                // Move all 'Other' options to the end
                $mainOptions = array_filter($referrerOptions, function($opt) { return stripos($opt, 'Other') === false; });
                $otherOptions = array_filter($referrerOptions, function($opt) { return stripos($opt, 'Other') !== false; });
                $orderedOptions = array_merge($mainOptions, $otherOptions);
                echo '<div style="margin-bottom:8px;">';
                echo '<button type="button" class="sentinelpro-referrer-select-all" style="margin-right:8px;">' . esc_html__('SELECT ALL', 'valserv-analytics-for-sentinelpro') . '</button>';
                echo '<button type="button" class="sentinelpro-referrer-deselect-all">' . esc_html__('DESELECT ALL', 'valserv-analytics-for-sentinelpro') . '</button>';
                echo '</div>';
                echo '<div class="sentinelpro-checkbox-group sentinelpro-referrer-checkboxes" style="display:grid;grid-template-columns:repeat(3,1fr);gap:0 24px;max-width:600px;">';
                foreach ($orderedOptions as $option) {
                    $val = strtolower(str_replace([' ', '/'], ['-', ''], $option));
                    echo '<label><input type="checkbox" value="' . esc_attr($option) . '"> ' . esc_html($option) . '</label>';
                }
                echo '</div>';
            } else if ($dimension === 'OPERATING SYSTEM') {
                // Two-column checkbox list for Operating System
                $osOptions = ['Android', 'iOS', 'Other'];
                echo '<div style="margin-bottom:8px;">';
                echo '<button type="button" class="sentinelpro-os-select-all" style="margin-right:8px;">' . esc_html__('SELECT ALL', 'valserv-analytics-for-sentinelpro') . '</button>';
                echo '<button type="button" class="sentinelpro-os-deselect-all">' . esc_html__('DESELECT ALL', 'valserv-analytics-for-sentinelpro') . '</button>';
                echo '</div>';
                echo '<div class="sentinelpro-checkbox-group sentinelpro-os-checkboxes" style="display:grid;grid-template-columns:repeat(2,1fr);gap:0 24px;max-width:220px;">';
                foreach ($osOptions as $option) {
                    $val = strtolower($option);
                    echo '<label><input type="checkbox" value="' . esc_attr($option) . '"> ' . esc_html($option) . '</label>';
                }
                echo '</div>';
            } else if ($dimension === 'BROWSER') {
                // Two-column checkbox list for Browser
                $browserOptions = ['Chrome', 'Firefox', 'Edge', 'Opera', 'Safari', 'Other'];
                echo '<div style="margin-bottom:8px;">';
                echo '<button type="button" class="sentinelpro-browser-select-all" style="margin-right:8px;">' . esc_html__('SELECT ALL', 'valserv-analytics-for-sentinelpro') . '</button>';
                echo '<button type="button" class="sentinelpro-browser-deselect-all">' . esc_html__('DESELECT ALL', 'valserv-analytics-for-sentinelpro') . '</button>';
                echo '</div>';
                echo '<div class="sentinelpro-checkbox-group sentinelpro-browser-checkboxes" style="display:grid;grid-template-columns:repeat(2,1fr);gap:0 24px;max-width:220px;">';
                foreach ($browserOptions as $option) {
                    $val = strtolower($option);
                    echo '<label><input type="checkbox" value="' . esc_attr($option) . '"> ' . esc_html($option) . '</label>';
                }
                echo '</div>';
            } else {
                echo '<select class="sentinelpro-filter-select" id="filter-' . esc_attr($id) . '">';
                echo '<option value="">' . esc_html__('All', 'valserv-analytics-for-sentinelpro') . ' ' . esc_html($dimension) . 's</option>';
                echo '<option value="option1">Option 1</option>';
                echo '<option value="option2">Option 2</option>';
                echo '<option value="option3">Option 3</option>';
                echo '</select>';
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        
        // CUSTOM DIMENSIONS SECTION
        echo '<div class="sentinelpro-filter-section">';
        echo '<div style="margin-bottom: 15px;">';
        echo '<h4 class="sentinelpro-section-title">' . esc_html__('CUSTOM DIMENSIONS', 'valserv-analytics-for-sentinelpro') . '</h4>';
        echo '</div>';
        // Dynamically fetch custom dimensions from options
        $options = get_option('sentinelpro_options', []);
        $property_id = $options["property_id"] ?? '';
        $customDimensionsArr = [];
        if ($property_id) {
            $customDimensionsArr = get_option("sentinelpro_dimensions_{$property_id}", []);
            
            // If the old dimension data doesn't exist, create it with your working data
            if (empty($customDimensionsArr)) {
                $customDimensionsArr = [
                    'intent' => ['[No Value]', 'Answer', 'authority', 'breakout', 'culture', 'entertainment', 'evergreen', 'feed', 'gaming', 'gaming-news', 'internet-culture', 'Native Commerce', 'Non-Article', 'Paid', 'short-term', 'Sponsored', 'Syndicated', 'Affiliate', 'Authority', 'Brand', 'Commerce', 'Discussion', 'Evergreen', 'Feed', 'freelance', 'gaming-curation', 'guides', 'movies', 'New Authority', 'non-article', 'Short-Term', 'Sniping', 'Support', 'tv'],
                    'adsTemplate' => ['[No Value]', 'content-all', 'content-tldr', 'entertainment', 'gaming', 'home', 'list-all', 'list-tldr', 'thread-all', 'video-all', 'breakout', 'content-exclusive', 'directory-all', 'freelance', 'gaming-curation', 'hub', 'list-list', 'listing', 'thread-home', 'video-home'],
                    'contentType' => ['List', 'Non-Article', 'News'],
                    'articleType' => ['[No Value]', 'breakout', 'db', 'Entertainment', 'features', 'Gaming', 'gaming-curation', 'news', 'PackageResourceType', 'PostResourceType', 'StreamResourceType', 'video', 'article', 'culture', 'directory', 'entertainment', 'freelance', 'gaming', 'list', 'null', 'PageResourceType', 'product-review', 'thread', 'VideoGameResourceType'],
                    'primaryTag' => ['Taylor Swift', 'Buzz', 'Entertainment', 'The Rich & Powerful'],
                    'primaryCategory' => ['celebrity', 'Non-Article', 'television', 'history'],
                    'networkCategory' => ['Other', 'Non-Article'],
                    'segment' => ['[No Value]'],
                    'initiative' => ['[No Value]'],
                    'publishDate' => ['2024-02-08', '2014-05-21', '2016-11-05', '2017-12-30', '2014-06-03', '2023-09-30', '2024-09-27', '2025-07-31'],
                    'system' => ['Premium']
                ];
                
                // Save this data to the database so it persists
                update_option("sentinelpro_dimensions_{$property_id}", $customDimensionsArr);
            }
        }

        foreach ($customDimensionsArr as $dimensionKey => $dimensionVal) {
            // Skip SYSTEM dimension
            if (strtolower($dimensionKey) === 'system') {
                continue;
            }
            // Insert space before capital letters (except the first), then replace underscores/dashes with space, then ALL CAPS
            $spaced = preg_replace('/([a-z])([A-Z])/', '$1 $2', $dimensionKey);
            $label = strtoupper(str_replace(['_', '-'], ' ', $spaced));
            $id = strtolower(str_replace([' ', '_'], '-', $dimensionKey));
            echo '<div class="sentinelpro-filter-item">';
            echo '<div class="sentinelpro-filter-item-header" data-api-key="' . esc_attr($dimensionKey) . '">';
            echo '<span>' . esc_html($label) . '</span>';
            echo '<span class="sentinelpro-filter-count"></span>';
            echo '<span class="sentinelpro-filter-chevron">▼</span>';
            echo '</div>';
            echo '<div class="sentinelpro-filter-item-content">';

            // Generalized dropdown+contains for these dimensions
            $dropdownDimensions = [
                'contentType' => ['Content type', 'ContentType'],
                'primaryTag' => ['Primary Tag', 'PrimaryTag'],
                'primaryCategory' => ['Primary Category', 'PrimaryCategory'],
                'networkCategory' => ['Network Category', 'NetworkCategory'],
                'segment' => ['Segment', 'Segment'],
                'publishDate' => ['Publish Date', 'PublishDate'],
            ];
            if ($dimensionKey === 'initiative') {
                // Render Initiative as a single checkbox for [No Value] and a contains searchbox
                echo '<div style="margin-bottom:8px;">';
                echo '<button type="button" class="sentinelpro-custom-select-all" data-dimension="initiative" style="margin-right:8px;">' . esc_html__('SELECT ALL', 'valserv-analytics-for-sentinelpro') . '</button>';
                echo '<button type="button" class="sentinelpro-custom-deselect-all" data-dimension="initiative">' . esc_html__('DESELECT ALL', 'valserv-analytics-for-sentinelpro') . '</button>';
                echo '</div>';
                echo '<div class="sentinelpro-checkbox-group sentinelpro-custom-checkboxes" data-dimension="initiative" style="display:block;max-width:400px;">';
                echo '<label><input type="checkbox" value="[No Value]"> ' . esc_html__('[No Value]', 'valserv-analytics-for-sentinelpro') . '</label>';
                echo '</div>';
                echo '<div style="margin: 12px 0 6px 0; color: #666; font-size: 12px;">' . esc_html__('or', 'valserv-analytics-for-sentinelpro') . '</div>';
                echo '<input type="text" class="sentinelpro-control-select sentinelpro-custom-search" data-dimension="initiative" placeholder="' . esc_attr__('CONTAINS', 'valserv-analytics-for-sentinelpro') . '" autocomplete="off" style="margin-bottom: 8px;">';
                echo '</div>';
            } else if (array_key_exists($dimensionKey, $dropdownDimensions)) {
                list($dropdownLabel, $dimCamel) = $dropdownDimensions[$dimensionKey];
                echo '<div class="sentinelpro-generic-dropdown-wrapper" data-dimension="' . esc_attr($dimCamel) . '" style="position:relative;">';
                echo '<div style="position:relative;">';
                echo '<input type="text" class="sentinelpro-control-select sentinelpro-generic-exact-search" data-dimension="' . esc_attr($dimCamel) . '" data-mode="exact" placeholder="' . esc_attr__('Exact match', 'valserv-analytics-for-sentinelpro') . '" autocomplete="off" readonly style="margin-bottom: 2px; padding-left: 28px; cursor:pointer;" />';
                echo '<span class="sentinelpro-dropdown-icon" style="position:absolute;left:8px;top:50%;transform:translateY(-50%);pointer-events:none;">';
                echo '<img src="' . esc_url(SENTINELPRO_ANALYTICS_PLUGIN_URL . 'assets/external-libs/magic-emoji.png') . '" alt="magic" style="width:16px;height:16px;vertical-align:middle;" />';
                echo '</span>';
                echo '<div class="sentinelpro-generic-dropdown" style="display:none; position:absolute; left:0; top:40px; z-index:1001;">';
                echo '<div class="dropdown-header sticky">';
                echo '<input type="text" class="dropdown-search" placeholder="' . esc_attr__('Type to search', 'valserv-analytics-for-sentinelpro') . '" autocomplete="off" />';
                echo '<div class="dropdown-labels"><span>' . esc_html($dropdownLabel) . '</span></div>';
                echo '</div>';
                echo '<ul class="dropdown-list"></ul>';
                echo '</div>';
                echo '</div>';
                echo '<div style="margin: 6px 0 6px 0; color: #666; font-size: 12px;">' . esc_html__('or', 'valserv-analytics-for-sentinelpro') . '</div>';
                echo '<input type="text" class="sentinelpro-control-select sentinelpro-generic-contains-search" data-dimension="' . esc_attr($dimCamel) . '" data-mode="contains" placeholder="' . esc_attr__('CONTAINS', 'valserv-analytics-for-sentinelpro') . '" autocomplete="off" style="margin-bottom: 8px;">';
                echo '</div>';
            } else {
                // Render as checkbox group with select/deselect buttons for other dimensions
                echo '<div style="margin-bottom:8px;">';
                echo '<button type="button" class="sentinelpro-custom-select-all" data-dimension="' . esc_attr($dimensionKey) . '" style="margin-right:8px;">' . esc_html__('SELECT ALL', 'valserv-analytics-for-sentinelpro') . '</button>';
                echo '<button type="button" class="sentinelpro-custom-deselect-all" data-dimension="' . esc_attr($dimensionKey) . '">' . esc_html__('DESELECT ALL', 'valserv-analytics-for-sentinelpro') . '</button>';
                echo '</div>';
                echo '<div class="sentinelpro-checkbox-group sentinelpro-custom-checkboxes" data-dimension="' . esc_attr($dimensionKey) . '" style="display:grid;grid-template-columns:repeat(2,1fr);gap:0 24px;max-width:400px;">';
                if (is_array($dimensionVal)) {
                    foreach ($dimensionVal as $option) {
                        if (!empty($option) && $option !== 'null') {
                            echo '<label><input type="checkbox" value="' . esc_attr($option) . '"> ' . esc_html($option) . '</label>';
                        }
                    }
                } else if (strtolower($dimensionKey) === 'intent') {
                    $intentOptions = [
                        '[No Value]', 'Answer', 'authority', 'breakout', 'culture', 'entertainment', 'evergreen', 'feed', 'gaming', 'gaming-news', 'internet-culture', 'Native Commerce', 'Non-Article', 'Paid', 'short-term', 'Sponsored', 'Syndicated',
                        'Affiliate', 'Authority', 'Brand', 'Commerce', 'Discussion', 'Evergreen', 'Feed', 'freelance', 'gaming-curation', 'guides', 'movies', 'New Authority', 'non-article', 'Short-Term', 'Sniping', 'Support', 'tv'
                    ];
                    foreach ($intentOptions as $option) {
                        echo '<label><input type="checkbox" value="' . esc_attr($option) . '"> ' . esc_html($option) . '</label>';
                    }
                } else if (strtolower($dimensionKey) === 'adstemplate') {
                    $adsTemplateOptions = [
                        '[No Value]', 'content-all', 'content-tldr', 'entertainment', 'gaming', 'home', 'list-all', 'list-tldr', 'thread-all', 'video-all',
                        'breakout', 'content-exclusive', 'directory-all', 'freelance', 'gaming-curation', 'hub', 'list-list', 'listing', 'thread-home', 'video-home'
                    ];
                    foreach ($adsTemplateOptions as $option) {
                        echo '<label><input type="checkbox" value="' . esc_attr($option) . '"> ' . esc_html($option) . '</label>';
                    }
                } else if (strtolower($dimensionKey) === 'articletype') {
                    $articleTypeOptions = [
                        '[No Value]', 'breakout', 'db', 'Entertainment', 'features', 'Gaming', 'gaming-curation', 'news', 'PackageResourceType', 'PostResourceType', 'StreamResourceType', 'video',
                        'article', 'culture', 'directory', 'entertainment', 'freelance', 'gaming', 'list', 'null', 'PageResourceType', 'product-review', 'thread', 'VideoGameResourceType'
                    ];
                    foreach ($articleTypeOptions as $option) {
                        echo '<label><input type="checkbox" value="' . esc_attr($option) . '"> ' . esc_html($option) . '</label>';
                    }
                } else {
                    // For dimensions that should use checkboxes, show hardcoded options
                    // since we don't have actual values from the database
                    echo '<label><input type="checkbox" value="' . esc_attr__('Premium', 'valserv-analytics-for-sentinelpro') . '"> ' . esc_html__('Premium', 'valserv-analytics-for-sentinelpro') . '</label>';
                    echo '<label><input type="checkbox" value="' . esc_attr__('Performance', 'valserv-analytics-for-sentinelpro') . '"> ' . esc_html__('Performance', 'valserv-analytics-for-sentinelpro') . '</label>';
                    echo '<label><input type="checkbox" value="' . esc_attr__('premium', 'valserv-analytics-for-sentinelpro') . '"> ' . esc_html__('premium', 'valserv-analytics-for-sentinelpro') . '</label>';
                    echo '<label><input type="checkbox" value="' . esc_attr__('[No Value]', 'valserv-analytics-for-sentinelpro') . '"> ' . esc_html__('[No Value]', 'valserv-analytics-for-sentinelpro') . '</label>';
                }
                echo '</div>';
                echo '<div style="margin-top:10px;">';
                echo '<input type="text" class="sentinelpro-control-select sentinelpro-custom-search" data-dimension="' . esc_attr($dimensionKey) . '" placeholder="' . esc_attr__('CONTAINS', 'valserv-analytics-for-sentinelpro') . '" autocomplete="off">';
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        
        // Move the footer here, inside .sentinelpro-modal-content
        echo '<div class="sentinelpro-modal-footer">';
        echo '<button id="apply-filters" class="sentinelpro-btn-primary">' . esc_html__('Apply Filters', 'valserv-analytics-for-sentinelpro') . '</button>';
        echo '<button id="clear-filters" class="sentinelpro-btn-secondary">' . esc_html__('Clear All', 'valserv-analytics-for-sentinelpro') . '</button>';
        echo '</div>';
        echo '</div>'; // .sentinelpro-modal-content
        echo '</div>'; // #filters-modal



        echo '</div></div>'; // .wrap and .sentinelpro-dashboard-wrapper
        
        // Enqueue dashboard filters script
        wp_enqueue_script(
            'valserv-dashboard-filters',
            SENTINELPRO_ANALYTICS_PLUGIN_URL . 'admin/js/dashboard-filters.js',
            array('jquery'),
            SENTINELPRO_ANALYTICS_VERSION,
            true
        );

    }

    public static function render_user_management_page(array $users, int $superuser_id, array $pages, callable $default_access_callback): void {
        $user_id = get_current_user_id();
        
        // Enqueue user management script properly as a module
        wp_enqueue_script(
            'valserv-user-management',
            SENTINELPRO_ANALYTICS_PLUGIN_URL . 'admin/js/user-management.js',
            array('jquery'),
            SENTINELPRO_ANALYTICS_VERSION,
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script(
            'valserv-user-management',
            'valservUserMgmtData',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sentinelpro_nonce'),
                'expectedHeaders' => SentinelPro_CSV_Permissions_Importer::get_expected_headers()
            )
        );
        
        $clearance = SentinelPro_User_Access_Manager::get_clearance_level($user_id);
        
        // User management page access
        
        if ($clearance !== 'admin') {
            $superuser_id = SentinelPro_User_Access_Manager::get_safe_superuser_id();
            $message = "🚫 " . esc_html__('Access denied. You do not have permission to view this page. Current clearance: ', 'valserv-analytics-for-sentinelpro') . esc_html($clearance) . ". " . esc_html__('Only users with \'admin\' clearance can access user management. ', 'valserv-analytics-for-sentinelpro');
            $message .= esc_html__('Contact the SuperUser (ID: ', 'valserv-analytics-for-sentinelpro') . esc_html($superuser_id) . esc_html__(') to upgrade your clearance level.', 'valserv-analytics-for-sentinelpro');
            if (!$superuser_id) {
                $message .= esc_html__('No SuperUser is currently assigned. Contact your system administrator.', 'valserv-analytics-for-sentinelpro');
            }
            wp_die(esc_html($message));
        }
        echo '<div class="wrap">';

        echo '<h1><span class="dashicons dashicons-admin-users"></span> ' . esc_html__('User Management', 'valserv-analytics-for-sentinelpro') . '</h1>';
        echo '<a href="#sentinelpro-bulk-access" class="sentinelpro-jump-link">' . esc_html__('⬇️ Jump to Bulk Access Management', 'valserv-analytics-for-sentinelpro') . '</a>';

        // === Filter Bar ===
        echo '<div class="sentinelpro-dashboard-section sentinelpro-filter-bar">';
        echo '<input type="text" id="sentinelpro-user-search" placeholder="' . esc_attr__('🔍 Search users...', 'valserv-analytics-for-sentinelpro') . '" />';
        echo '<select id="sentinelpro-user-role-filter">';
        echo '<option value="">🔄 ' . esc_html__('All Roles', 'valserv-analytics-for-sentinelpro') . '</option>';
        echo '<option value="administrator">' . esc_html__('Administrator', 'valserv-analytics-for-sentinelpro') . '</option>';
        echo '<option value="editor">' . esc_html__('Editor', 'valserv-analytics-for-sentinelpro') . '</option>';
        echo '<option value="author">' . esc_html__('Author', 'valserv-analytics-for-sentinelpro') . '</option>';
        echo '<option value="contributor">' . esc_html__('Contributor', 'valserv-analytics-for-sentinelpro') . '</option>';
        echo '<option value="subscriber">' . esc_html__('Subscriber', 'valserv-analytics-for-sentinelpro') . '</option>';
        echo '</select>';
        echo '<select id="sentinelpro-rows-per-page">';
        echo '<option value="25">' . esc_html__('25 rows', 'valserv-analytics-for-sentinelpro') . '</option>';
        echo '<option value="50">' . esc_html__('50 rows', 'valserv-analytics-for-sentinelpro') . '</option>';
        echo '<option value="75">' . esc_html__('75 rows', 'valserv-analytics-for-sentinelpro') . '</option>';
        echo '<option value="100">' . esc_html__('100 rows', 'valserv-analytics-for-sentinelpro') . '</option>';
        echo '</select>';
        echo '</div>';

        // === Access Table ===
        echo '<div class="sentinelpro-dashboard-section">';
        echo '<form method="post">';
        wp_nonce_field('sentinelpro_user_mgmt_save', 'sentinelpro_user_mgmt_nonce');

        echo '<table id="sentinelpro-user-table" class="user-access-table">';
        echo '<thead><tr><th style="text-align:left;">' . esc_html__('User', 'valserv-analytics-for-sentinelpro') . '</th><th style="text-align:center;">' . esc_html__('Full Name', 'valserv-analytics-for-sentinelpro') . '</th><th style="text-align:center;">' . esc_html__('Email', 'valserv-analytics-for-sentinelpro') . '</th><th style="text-align:center;">' . esc_html__('Role', 'valserv-analytics-for-sentinelpro') . '</th>';
        foreach ($pages as $key => $label) {
            echo "<th style=\"text-align:center;\">" . esc_html($label) . "</th>";
        }
        echo '</tr></thead><tbody id="sentinelpro-user-table-body">';
        

        
        echo '<tr class="sentinelpro-no-users-row" style="display: none;"><td colspan="' . (3 + count($pages)) . '">' . esc_html__('No users found.', 'valserv-analytics-for-sentinelpro') . '</td></tr>';

        echo '</tbody></table>';
        // Example: Add this line somewhere in your HTML, for instance, near the filter bar or pagination controls
        echo '<p>' . esc_html__('Total Users Found: ', 'valserv-analytics-for-sentinelpro') . '<span id="sentinelpro-total-users">0</span></p>';
        echo '<div id="sentinelpro-pagination-status"></div>';
        echo '<div id="sentinelpro-pagination-controls"></div>';

        echo '<button type="submit" class="sentinelpro-btn sentinelpro-btn-primary">' . esc_html__('💾 Save Access Settings', 'valserv-analytics-for-sentinelpro') . '</button>';
        echo '</form>';
        echo '</div>';

        echo '<button id="sentinelpro-view-access-logs" class="button button-secondary" style="margin-bottom: 15px;">
            📜 ' . esc_html__('View Access Logs', 'valserv-analytics-for-sentinelpro') . '
        </button>';

        echo '<div id="sentinelpro-access-logs-modal" style="display: none;"></div>';

        // === Bulk Access Panel ===
        echo '<div id="sentinelpro-bulk-access" class="sentinelpro-dashboard-section">';
        echo '<h2><span class="dashicons dashicons-upload"></span> ' . esc_html__('Bulk Access Management', 'valserv-analytics-for-sentinelpro') . '</h2>';
        echo '<p>' . esc_html__('Export the current access matrix to a CSV file for bulk editing.', 'valserv-analytics-for-sentinelpro') . '</p>';
        echo '<div class="sentinelpro-button-group">';
        echo '<button type="button" id="export-user-all" class="sentinelpro-btn sentinelpro-btn-secondary">' . esc_html__('📤 Export All', 'valserv-analytics-for-sentinelpro') . '</button>';
        echo '<button type="button" id="export-user-visible" class="sentinelpro-btn sentinelpro-btn-secondary">' . esc_html__('📄 Export Visible', 'valserv-analytics-for-sentinelpro') . '</button>';
        echo '</div>';

        echo '<p><strong>' . esc_html__('📥 Upload Updated Access Sheet', 'valserv-analytics-for-sentinelpro') . '</strong></p>';
        echo '<div id="sentinelpro-upload-dropzone">';
        echo '<p><strong>' . esc_html__('Drag & drop your CSV/XLSX file here', 'valserv-analytics-for-sentinelpro') . '</strong></p>';
        echo '<p style="font-size: 13px; color: #666;">' . esc_html__('or click to select a file', 'valserv-analytics-for-sentinelpro') . '</p>';
        echo '<input type="file" id="sentinelpro-upload-input" accept=".csv,.xlsx" style="display: none;" />';
        echo '</div>';

        echo '<p style="margin-top: 20px;"><strong>' . esc_html__('🔗 Import from Google Sheets / Public URL', 'valserv-analytics-for-sentinelpro') . '</strong></p>';
        echo '<div style="display: flex; gap: 0; margin-bottom: 10px;">';
        echo '<input type="text" id="sentinelpro-access-url-input" name="sentinelpro_access_url" class="regular-text" placeholder="' . esc_attr__('Enter Google Sheet CSV export URL or any public CSV URL', 'valserv-analytics-for-sentinelpro') . '" style="flex: 1; border-radius: 4px 0 0 4px; border: 1px solid #ddd; padding: 8px 12px; height: 36px; box-sizing: border-box; margin: 0;" />';
        echo '<button type="button" class="sentinelpro-btn sentinelpro-btn-secondary" id="sentinelpro-import-url-btn" style="white-space: nowrap; border-radius: 0 4px 4px 0; padding: 8px 16px; height: 36px; box-sizing: border-box; border: 1px solid #ddd; border-left: none; margin: 0; background: #f0f0f1; color: #2c3338;">' . esc_html__('🔗 Import from URL', 'valserv-analytics-for-sentinelpro') . '</button>';
        echo '</div>';

        echo '<p class="description">' . wp_kses(__('You may only change the <strong>ALLOWED</strong> or <strong>RESTRICTED</strong> values. Do not alter names, emails, or roles. The SuperUser row is locked.', 'valserv-analytics-for-sentinelpro'), ['strong' => []]) . '</p>';
        echo '<div id="sentinelpro-preview-wrapper" style="display: none;"></div>';

        echo '<form method="post" enctype="multipart/form-data" id="sentinelpro-upload-form">';
        wp_nonce_field('sentinelpro_user_mgmt_save', 'sentinelpro_user_mgmt_nonce');
        echo '<input type="hidden" name="sentinelpro_access_upload_hidden" id="sentinelpro-access-hidden" />';
        echo '<button type="submit" class="sentinelpro-btn sentinelpro-btn-primary" id="apply-file-permissions-btn">' . esc_html__('Apply Uploaded Permissions', 'valserv-analytics-for-sentinelpro') . '</button>';
        echo '</form>';
        echo '</div>';

        // === SuperUser Reassignment Panel ===
        $current_user = wp_get_current_user();
        // Retrieve the ID of the currently designated SuperUser
        $superuser_id = (int) get_option('sentinelpro_superuser_id');

        // The panel should only be visible if the current logged-in user IS the SuperUser
        if ($current_user->ID === $superuser_id) {
            echo '<div class="sentinelpro-dashboard-section" style="border-left: 4px solid #d63638;">';
            echo '<h2><span class="dashicons dashicons-shield-alt"></span> ' . esc_html__('Reassign SuperUser', 'valserv-analytics-for-sentinelpro') . '</h2>';
            echo '<p>' . esc_html__('The SuperUser has full access to all SentinelPro features and cannot be edited by others. You may reassign this role if necessary.', 'valserv-analytics-for-sentinelpro') . '</p>';
            echo '<form method="post" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">';
            wp_nonce_field('sentinelpro_reassign_superuser', 'sentinelpro_superuser_nonce');
            echo '<select name="new_superuser" class="regular-text" style="min-width: 300px;">';
            foreach ($users as $user) {
                $display = esc_html("{$user->display_name} ({$user->user_email})");
                echo '<option value="' . esc_attr($user->ID) . '" ' . selected($user->ID, $superuser_id, false) . '>' . esc_html($display) . '</option>';
            }

            echo '</select>';
            echo '<button type="submit" class="sentinelpro-btn sentinelpro-btn-primary">' . esc_html__('🔁 Reassign SuperUser', 'valserv-analytics-for-sentinelpro') . '</button>';
            echo '</form>';
            echo '</div>';
        }



        // === Hidden DOM elements for JS compatibility ===
        echo '<textarea id="sentinelpro-access-textarea" style="display:none;"></textarea>';
        echo '<button id="sentinelpro-upload-textarea-btn" style="display:none;"></button>';
        echo '<select id="filter-by-status" style="display:none;"></select>';

        // Headers data is now passed via localized script
        echo '</div>'; // .wrap
    }




    public static function render_settings_page(string $option_name): void {
        if (!SentinelPro_User_Access_Manager::user_has_access('api_input')) {
            wp_die(esc_html__('Insufficient permissions.', 'valserv-analytics-for-sentinelpro'));
        }
        
        // Check if current user is superuser or if this is initial installation
        $current_user_id = get_current_user_id();
        $superuser_id = (int) get_option('sentinelpro_superuser_id');
        $is_initial_installation = !$superuser_id && !SentinelPro_User_Access_Manager::are_api_credentials_configured();
        $can_edit = ($current_user_id === $superuser_id) || ($is_initial_installation && current_user_can('manage_options'));
        
        if (!$can_edit) {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('SentinelPro Settings', 'valserv-analytics-for-sentinelpro') . '</h1>';
            echo '<div class="notice notice-warning">';
            echo '<p><strong>' . esc_html__('Access Restricted', 'valserv-analytics-for-sentinelpro') . '</strong></p>';
            echo '<p>' . esc_html__('Only the SuperUser can modify API settings. You can view the current settings but cannot make changes.', 'valserv-analytics-for-sentinelpro') . '</p>';
            if ($superuser_id) {
                echo '<p>' . esc_html__('Contact your SuperUser (User ID: ', 'valserv-analytics-for-sentinelpro') . esc_html($superuser_id) . esc_html__(') to make changes to the API configuration.', 'valserv-analytics-for-sentinelpro') . '</p>';
            } else {
                echo '<p>' . esc_html__('Contact your administrator to configure the initial API settings.', 'valserv-analytics-for-sentinelpro') . '</p>';
            }
            echo '</div>';
            
            // Show current settings in read-only format
            $options = get_option('sentinelpro_options', []);
            echo '<div class="sentinelpro-settings-card">';
            echo '<h2>' . esc_html__('Current Settings (Read-Only)', 'valserv-analytics-for-sentinelpro') . '</h2>';
            echo '<table class="form-table">';
            echo '<tr><th>' . esc_html__('Account Name', 'valserv-analytics-for-sentinelpro') . '</th><td>' . esc_html($options['account_name'] ?? 'Not set') . '</td></tr>';
            echo '<tr><th>' . esc_html__('Property ID', 'valserv-analytics-for-sentinelpro') . '</th><td>' . esc_html($options['property_id'] ?? 'Not set') . '</td></tr>';
            echo '<tr><th>' . esc_html__('API Key', 'valserv-analytics-for-sentinelpro') . '</th><td>' . esc_html__('Hidden for security', 'valserv-analytics-for-sentinelpro') . '</td></tr>';
            echo '<tr><th>' . esc_html__('Enable Tracking', 'valserv-analytics-for-sentinelpro') . '</th><td>' . (isset($options['enable_tracking']) && $options['enable_tracking'] ? 'Yes' : 'No') . '</td></tr>';
            echo '</table>';
            echo '</div>';
            echo '</div>';
            return;
        }
        // Enqueue settings script and styles properly
        wp_enqueue_script(
            'valserv-settings',
            SENTINELPRO_ANALYTICS_PLUGIN_URL . 'admin/js/api-settings-page.js',
            array('jquery'),
            SENTINELPRO_ANALYTICS_VERSION,
            true
        );
        
        wp_enqueue_style(
            'valserv-settings',
            SENTINELPRO_ANALYTICS_PLUGIN_URL . 'admin/css/settings-page.css',
            array(),
            SENTINELPRO_ANALYTICS_VERSION
        );
        
        // Localize script with AJAX data
        wp_localize_script(
            'valserv-settings',
            'valservSettingsData',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sentinelpro_nonce')
            )
        );
        
        $options = get_option('sentinelpro_options', []);
        echo '<div class="sentinelpro-settings-card">';
        echo '<h1>' . esc_html__('SentinelPro Settings', 'valserv-analytics-for-sentinelpro') . '</h1>';
        echo '<div class="sentinelpro-settings-desc">' . esc_html__('All necessary information can be found in the Account Settings page on SentinelPro', 'valserv-analytics-for-sentinelpro') . '</div>';
        
        // Custom dimensions notice
        echo '<div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px; color: #856404;">';
        echo '<strong>' . esc_html__('💡 Custom Dimensions Notice: ', 'valserv-analytics-for-sentinelpro') . '</strong>' . esc_html__('If you have added new custom dimensions or removed old ones in your SentinelPro property, please re-save your API settings to ensure the changes are properly synchronized.', 'valserv-analytics-for-sentinelpro');
        echo '</div>';
        
        echo '<form method="post" id="sentinelpro-settings-form">';

        settings_errors('sentinelpro_options');
        wp_nonce_field('sentinelpro_auth_nonce', 'nonce');

        // Account Name
        echo '<table class="form-table"><tr><th scope="row"><label for="account_name">' . esc_html__('Account Name', 'valserv-analytics-for-sentinelpro') . '</label></th><td>';
        echo '<input name="sentinelpro_options[account_name]" type="text" id="account_name" value="' . esc_attr($options['account_name'] ?? '') . '" />';
        echo '<span class="description">' . esc_html__('Can be found in Account Details', 'valserv-analytics-for-sentinelpro') . '</span>';
        echo '</td></tr>';

        // Property ID
        echo '<tr><th scope="row"><label for="property_id">' . esc_html__('Property ID', 'valserv-analytics-for-sentinelpro') . '</label></th><td>';
        echo '<input name="sentinelpro_options[property_id]" type="text" id="property_id" value="' . esc_attr($options['property_id'] ?? '') . '" />';
        echo '<span class="description">' . esc_html__('Can be found in Property Management', 'valserv-analytics-for-sentinelpro') . '</span>';
        echo '<span class="description">⚠️' . esc_html__('Warning: Changing between Property IDs may result in invalid or mixed data.', 'valserv-analytics-for-sentinelpro') . '</span>';
        echo '</td></tr>';

        // API Key
        echo '<tr><th scope="row"><label for="api_key">' . esc_html__('API Key', 'valserv-analytics-for-sentinelpro') . '</label></th><td>';
        $api_key = SentinelPro_Security_Manager::get_api_key();
        echo '<input name="sentinelpro_options[api_key]" type="text" id="api_key" value="' . esc_attr($api_key) . '" />';
        echo '<span class="description">' . esc_html__('Can be found in Account API. Key is securely encrypted when saved.', 'valserv-analytics-for-sentinelpro') . '</span>';
        echo '</td></tr>';

        // Enable Tracking
        $options = get_option('sentinelpro_options');
        $checked = (!empty($options) && !empty($options['enable_tracking'])) ? ' checked' : '';
        echo '<tr><th scope="row"><label for="enable_tracking">' . esc_html__('Enable Tracking in WP CMS', 'valserv-analytics-for-sentinelpro') . '</label></th><td style="padding-top:6px;">';
        echo '<input type="hidden" name="sentinelpro_options[enable_tracking]" value="0" />';
        echo '<input name="sentinelpro_options[enable_tracking]" type="checkbox" id="enable_tracking" value="1"' . esc_attr($checked) . ' />';
        echo '<span class="description">' . esc_html__('Inject tracker script', 'valserv-analytics-for-sentinelpro') . '</span>';
        echo '</td></tr></table>';

        submit_button();

        echo '<span>' . esc_html__('Bot Traffic will not be tracked or displayed in the plugin dashboard.', 'valserv-analytics-for-sentinelpro') . '</span>';
        echo '<br><small style="color: #666; font-style: italic;">💡 ' . esc_html__('Your timezone will be automatically detected and configured when you save changes', 'valserv-analytics-for-sentinelpro') . '</small>';
        echo '</form>';
        echo '</div>';

        $clearance = SentinelPro_User_Access_Manager::get_clearance_level(get_current_user_id());
        
        // Add clearance data to existing localized script
        wp_localize_script(
            'valserv-settings',
            'valservClearanceData',
            array(
                'clearance' => $clearance,
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sentinelpro_nonce')
            )
        );
        // Removed the credential re-check and reload script to prevent infinite reload loop.
    }

}

add_filter('admin_body_class', function ($classes) {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'toplevel_page_sentinelpro-event-data') {
        $classes .= ' sentinelpro-event-data-page';
    }
    return $classes;
});
