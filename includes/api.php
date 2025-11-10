<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * SentinelPro API utilities
 *
 * Provides functions to fetch account info and analytics data from SentinelPro.
 */

// =========================
// Fetch Account Info
// =========================


// =========================
// Get Account Name (with cache fallback)
// =========================
function valserv_get_account_name(): string {
    $options = get_option('sentinelpro_options', []);
    return sanitize_text_field($options['account_name'] ?? '');
}

add_action('wp_ajax_valserv_search_posts', 'valserv_search_posts');

function valserv_search_posts() {
    // SECURITY: Verify nonce with proper name
    if (!check_ajax_referer('sentinelpro_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => esc_html__('Security check failed', 'valserv-analytics-for-sentinelpro')], 403);
        return;
    }

    $term = isset($_POST['term']) ? sanitize_text_field(wp_unslash($_POST['term'])) : '';

    $args = [
        's' => $term,
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => 10,
    ];

    $query = new WP_Query($args);
    $results = [];

    foreach ($query->posts as $post) {
        $results[] = [
            'id' => $post->ID,
            'label' => $post->post_title,
            'value' => $post->post_title,
        ];
    }

    wp_send_json($results);
}


// =========================
// Query Data from SentinelPro
// =========================
function valserv_query_data(array $params, string $account_name, string $api_key): ?array {
    if (empty($account_name) || empty($api_key)) {
        return null;
    }

    $jsonData = json_encode($params, JSON_UNESCAPED_SLASHES);
    $endpointPath = 'traffic';
    $url = "https://{$account_name}.sentinelpro.com/api/v1/{$endpointPath}/?data=" . rawurlencode($jsonData);


    $response = wp_remote_get($url, [
        'headers' => [
            'SENTINEL-API-KEY' => $api_key,
            'Accept' => 'application/json'
        ],
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        return null;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);


    if ($code !== 200) {
        return null;
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        return null;
    }
    // Normalize contenttype to contentType for all rows in $data['data']
    if (isset($data['data']) && is_array($data['data'])) {
        foreach ($data['data'] as &$row) {
            if (isset($row['contenttype']) && !isset($row['contentType'])) {
                $row['contentType'] = $row['contenttype'];
                unset($row['contenttype']);
            }
        }
        unset($row); // break reference
    }
    return $data;
}

// =========================
// Helper: Determine Metrics
// =========================
function valserv_get_metric_array(string $requested_metric): array {
    return match ($requested_metric) {
        'all' => ['sessions', 'visits', 'views', 'pagesPerSession', 'averageEngagedDuration', 'averageEngagedDepth', 'averageConnectionSpeed'],
        'engagement' => ['averageEngagedDuration', 'averageEngagedDepth', 'averageConnectionSpeed', 'pagesPerSession'],
        'traffic' => ['sessions', 'visits', 'views'],
        default => [$requested_metric],
    };
}

// =========================
// Helper: Parse Date Range
// =========================
function valserv_parse_date_range(array $params): array {
    $start_date = isset($params['start_date']) ? sanitize_text_field(wp_unslash($params['start_date'])) : '';
    $end_date   = isset($params['end_date']) ? sanitize_text_field(wp_unslash($params['end_date'])) : '';
    $gt = $start_date ?: gmdate('Y-m-d');
    $lt = $end_date ?: gmdate('Y-m-d');

    try {
        $startObj = new DateTime($gt);
        $endObj = new DateTime($lt);

        // ✅ Ensure valid range (start <= end)
        if ($startObj > $endObj) {
            wp_send_json_error([
                'message' => esc_html__('Start date must be before or equal to end date.', 'valserv-analytics-for-sentinelpro'),
                'error_type' => 'invalid_range'
            ], 400);
        }

        // ✅ Make end date inclusive by adding one day to lt
        $gt = $startObj->format('Y-m-d');
        $lt = $endObj->format('Y-m-d');

    } catch (Exception $e) {
        wp_send_json_error([
            'message' => esc_html__('Invalid date format.', 'valserv-analytics-for-sentinelpro'),
            'error_type' => 'invalid_format'
        ], 400);
    }

    return [$gt, $lt];
}


// =========================
// Helper: Build Filters
// =========================
function valserv_build_filters(string $metric, string $property_id, string $gt, string $lt, ?string $page_path = null, ?string $post_id = null, ?string $filter_dimension = null, ?string $filter_value = null): array {
    // Format dates to YYYY-MM-DD for daily granularity
    $gt_date = gmdate('Y-m-d', strtotime($gt));
    $lt_date = gmdate('Y-m-d', strtotime($lt));
    
    $filters = [
        'propertyId' => [
            'in' => [$property_id]
        ],
        'date' => [
            'gt' => $gt_date,
            'lt' => $lt_date
        ]
    ];

    if ($page_path) {
        $filters['pagePath'] = [
            'eq' => $page_path
        ];
    }

    // Add dimension filter if provided
    if ($filter_dimension && $filter_value) {
        $filters[$filter_dimension] = [
            'eq' => $filter_value
        ];
    }

    return $filters;
}


// =========================
// Helper: Build Query Params
// =========================
function valserv_build_query_params(array $filters, array $metrics, string $granularity, array $custom_dimensions = []): array {
    // Always include 'date'
    $dimensions = ['date'];

    // Include 'pagePath' only if it's used as a filter
    if (isset($filters['pagePath'])) {
        $dimensions[] = 'pagePath';
    }

    // Append custom dimensions if provided (without overwriting)
    foreach ($custom_dimensions as $dim) {
        $dim = trim($dim);
        if ($dim && !in_array($dim, $dimensions, true)) {
            $dimensions[] = $dim;
        }
    }

    // ✅ Optional debug log

    return [
        'filters'     => $filters,
        'granularity' => $granularity,
        'metrics'     => $metrics,
        'dimensions'  => $dimensions,
        'orderBy'     => ['date' => 'asc'],
        'pagination'  => [
            'pageSize'   => 1000,
            'pageNumber' => 1
        ]
    ];
}



// =========================
// AJAX: Event/Session Fetch Handler
// =========================
add_action('wp_ajax_valserv_fetch_data', 'valserv_fetch_data');

function valserv_fetch_data() {
    // SECURITY: Verify nonce
    if (!check_ajax_referer('sentinelpro_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => esc_html__('Security check failed', 'valserv-analytics-for-sentinelpro')], 403);
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => esc_html__('Unauthorized', 'valserv-analytics-for-sentinelpro')], 403);
    }

    $creds = SentinelPro_API_Client::get_api_credentials();
    $api_key = $creds['api_key'];
    $property_id = $creds['property_id'];
    $account_name = $creds['account_name'];


    $granularity        = isset($_GET['granularity']) ? sanitize_text_field(wp_unslash($_GET['granularity'])) : 'daily';
    $requested_metric   = isset($_GET['metric']) ? sanitize_text_field(wp_unslash($_GET['metric'])) : 'traffic';
    $post_id = isset($_GET['post_id']) ? absint(wp_unslash($_GET['post_id'])) : 0;
    $page_path = null;
    $dimension_keys = isset($_GET['dimensions']) ? explode(',', sanitize_text_field(wp_unslash($_GET['dimensions']))) : [];
    $filter_dimension = isset($_GET['filter_dimension']) ? sanitize_text_field(wp_unslash($_GET['filter_dimension'])) : '';
    $filter_value = isset($_GET['filter_value']) ? sanitize_text_field(wp_unslash($_GET['filter_value'])) : '';
    $content_type = isset($_GET['contentType']) ? sanitize_text_field(wp_unslash($_GET['contentType'])) : '';
    $content_type_mode = isset($_GET['contentType_mode']) ? sanitize_text_field(wp_unslash($_GET['contentType_mode'])) : 'exact';


    if ($post_id) {
        $permalink = get_permalink($post_id);
        $parsed = wp_parse_url($permalink);
        $page_path = $parsed['path'] ?? null;
    }

    if (empty($api_key) || empty($property_id) || empty($account_name)) {
        wp_send_json_error(['message' => esc_html__('API credentials or account name missing.', 'valserv-analytics-for-sentinelpro')], 500);
    }

    // MODIFIED: If any custom dimension is requested, force metrics to 'sessions'
    if (!empty($dimension_keys)) {
        $metric_array = ['sessions', 'visits', 'views'];
    } else {
        $metric_array = valserv_get_metric_array($requested_metric);
    }

    $all_data = [];

    if ($granularity === 'hourly') {
        $dates = array_filter([
            isset($_GET['date1']) ? sanitize_text_field(wp_unslash($_GET['date1'])) : '',
            isset($_GET['date2']) ? sanitize_text_field(wp_unslash($_GET['date2'])) : ''
        ]);

        if (count($dates) > 2) {
            wp_send_json_error([
                'message' => esc_html__('Hourly comparison supports a maximum of 2 specific dates.', 'valserv-analytics-for-sentinelpro'),
                'error_type' => 'hourly_limit_exceeded'
            ], 400);
        }

        foreach ($dates as $current_date) {
            $start = new DateTime("$current_date 00:00:00");
            $end = (clone $start)->modify('+1 day');
            $gt = $start->format('Y-m-d H:i:s');
            $lt = $end->format('Y-m-d H:i:s');

            // Pass $post_id here for hourly queries
            $filters = valserv_build_filters($requested_metric, $property_id, $gt, $lt, $page_path, $post_id, $filter_dimension, $filter_value);
            $query = valserv_build_query_params($filters, $metric_array, 'hourly', $dimension_keys);


            $data = valserv_query_data($query, $account_name, $api_key);
            if (isset($data['data']) && is_array($data['data'])) {
                // Normalize contenttype to contentType for all rows before merging/caching
                foreach ($data['data'] as &$row) {
                    if (isset($row['contenttype']) && !isset($row['contentType'])) {
                        $row['contentType'] = $row['contenttype'];
                        unset($row['contenttype']);
                    }
                }
                unset($row); // break reference
                $all_data = array_merge($all_data, $data['data']);
            }
        }
    } else {
        [$gt, $lt] = valserv_parse_date_range($_GET);
        $start = new DateTime($gt);
        $end   = new DateTime($lt);

        $interval = new DateInterval('P10D');
        $periods = [];

        $current = clone $start;
        while ($current <= $end) {
            $chunk_start = clone $current;
            $chunk_end = (clone $chunk_start)->modify('+9 days');
            if ($chunk_end > $end) {
                $chunk_end = clone $end;
            }
            $periods[] = [$chunk_start, $chunk_end];
            $current = (clone $chunk_end)->modify('+1 day');
        }

        $page_size = 1000;

        foreach ($periods as [$chunkStart, $chunkEnd]) {
            $chunkGt = $chunkStart->format('Y-m-d');
            $chunkLt = $chunkEnd->format('Y-m-d');

            $filters = valserv_build_filters($requested_metric, $property_id, $chunkGt, $chunkLt, $page_path, $post_id, $filter_dimension, $filter_value);
            $query = valserv_build_query_params($filters, $metric_array, 'daily', $dimension_keys);

            $page = 1;
            do {
                $query['pagination'] = [
                    'pageSize' => $page_size,
                    'pageNumber' => $page
                ];

                $data = valserv_query_data($query, $account_name, $api_key);

                if (!isset($data['data']) || !is_array($data['data'])) {
                    break;
                }

                // Normalize contenttype to contentType for all rows before merging/caching
                foreach ($data['data'] as &$row) {
                    if (isset($row['contenttype']) && !isset($row['contentType'])) {
                        $row['contentType'] = $row['contenttype'];
                        unset($row['contenttype']);
                    }
                }
                unset($row); // break reference
                $all_data = array_merge($all_data, $data['data']);

                $total_pages = isset($data['totalPage']) ? (int) $data['totalPage'] : 1;
                $page++;
            } while ($page <= $total_pages);
        }
    }
    // Normalize contenttype to contentType for all rows
    foreach ($all_data as &$row) {
        if (isset($row['contenttype']) && !isset($row['contentType'])) {
            $row['contentType'] = $row['contenttype'];
            unset($row['contenttype']);
        }
    }
    unset($row); // break reference
    // FINAL normalization: ensure all rows use contentType before output/caching
    foreach ($all_data as &$row) {
        if (isset($row['contenttype']) && !isset($row['contentType'])) {
            $row['contentType'] = $row['contenttype'];
            unset($row['contenttype']);
        }
    }
    unset($row); // break reference
    wp_send_json(['data' => $all_data]);
}

class SentinelPro_API {
    public static function get_report(array $args): ?array {
        $options = get_option('sentinelpro_options', []);
        $account_name = sanitize_text_field($options['account_name'] ?? '');
        $api_key = SentinelPro_Security_Manager::get_api_key();

        if (empty($account_name) || empty($api_key)) {
            return null;
        }

        return valserv_query_data($args, $account_name, $api_key);
    }
}
