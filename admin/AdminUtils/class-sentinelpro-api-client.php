<?php

if ( ! defined('ABSPATH') ) { exit; }

class SentinelPro_API_Client {
    // Base URL constants for future-proofing
    const BASE_ANALYTICS_URL = 'https://analytics.sentinelpro.com/api/v1/';
    const BASE_ACCOUNT_API = 'https://%s.sentinelpro.com/api/v1/';
    
    /**
     * Normalize and validate account name to safe subdomain token
     */
    private static function normalize_account_name(string $name): string {
        $name = strtolower(trim($name));
        if (!preg_match('/^[a-z0-9-]+$/', $name)) {
            return '';
        }
        return $name;
    }
    
    /**
     * Sanitize date string to ensure valid format
     */
    private static function sanitize_date(string $d): string {
        $d = sanitize_text_field($d);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : '';
    }

    public static function fetch_all_traffic_pages(array $query, string $account_name, string $api_key): array {
        if (empty($query['propertyId']) || empty($account_name) || empty($api_key)) {
            return [];
        }

        $account = self::normalize_account_name($account_name);
        if ($account === '') {
            return [];
        }

        $endpoint = sprintf(self::BASE_ACCOUNT_API, $account) . 'traffic/';
        $all_data = [];
        $page = 1;
        $page_size = isset($query['pagination']['pageSize']) ? (int) $query['pagination']['pageSize'] : 1000;
        $max_pages = 50; // safety cap

        do {
            $query['pagination']['pageNumber'] = $page;
            $jsonData = wp_json_encode($query, JSON_UNESCAPED_SLASHES);
            $url = $endpoint . '?data=' . rawurlencode($jsonData);

            $response = wp_safe_remote_get($url, [
                'headers' => [
                    'SENTINEL-API-KEY' => $api_key,
                    'Accept'           => 'application/json',
                ],
                'timeout' => 15,
            ]);

            if (is_wp_error($response)) {
                break;
            }

            $status = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if ($status !== 200 || !isset($data['data']) || !is_array($data['data'])) {
                break;
            }

            $all_data = array_merge($all_data, $data['data']);
            $total_pages = 1;

            // Try a couple of common shapes
            if (isset($data['totalPage'])) {
                $total_pages = max(1, (int) $data['totalPage']);
            } elseif (isset($data['pagination']['totalPages'])) {
                $total_pages = max(1, (int) $data['pagination']['totalPages']);
            } elseif (isset($data['total']) && $page_size > 0) {
                $total_pages = max(1, (int) ceil(((int) $data['total']) / $page_size));
            }

            $page++;
        } while ($page <= min($total_pages, $max_pages));

        return $all_data;
    }


    public static function handle_api_response($response): void {
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => '❌ API request failed.', 'details' => $response->get_error_message()]);
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = wp_remote_retrieve_body($response);
        $json   = json_decode($body, true);

        if ($status === 200 && isset($json['data']) && is_array($json['data'])) {
            wp_send_json_success($json['data']);
        }

        $payload = ['message' => '❌ Invalid API response.', 'status' => $status];
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $payload['body'] = $body;
        }
        wp_send_json_error($payload);
    }

    public static function get_api_credentials(): array {
        $options = get_option('sentinelpro_options', []);
        return [
            'account_name' => $options['account_name'] ?? '',
            'api_key'      => SentinelPro_Security_Manager::get_api_key(),
            'property_id'  => $options['property_id'] ?? '',
        ];
    }

    public static function build_traffic_query(array $params, string $property_id, ?int $post_id = null): array {
        $start = self::sanitize_date($params['start_date'] ?? '');
        $end = self::sanitize_date($params['end_date'] ?? '');

        $allowed_metrics = ['sessions', 'visits', 'views'];
        $metric = $params['metric'] ?? 'sessions';
        if (!in_array($metric, $allowed_metrics, true)) {
            $metric = 'sessions';
        }

        $allowed_granularity = ['daily', 'hourly'];
        $granularity = $params['granularity'] ?? 'daily';
        if (!in_array($granularity, $allowed_granularity, true)) {
            $granularity = 'daily';
        }

        $query = [
            'propertyId' => $property_id,
            'dateRange' => [
                'startDate' => $start,
                'endDate' => $end
            ],
            'granularity' => $granularity,
            'metrics' => [$metric],
            'pagination' => [
                'pageNumber' => 1,
                'pageSize' => 1000
            ]
        ];
        
        if (!empty($params['dimensions'])) {
            $dim = sanitize_text_field($params['dimensions']);
            $query['dimensions'] = [$dim];
        }
        
        if ($post_id) {
            $query['filters'] = [
                [
                    'field' => 'post_id',
                    'operator' => 'equals',
                    'value' => (int) $post_id
                ]
            ];
        }
        
        return $query;
    }

    public static function extract_data_from_response($response): array {
        if (is_wp_error($response)) {
            return ['data' => [], 'requestedDates' => [], 'error' => $response->get_error_message()];
        }
        
        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        $data = (isset($decoded['data']) && is_array($decoded['data'])) ? $decoded['data'] : [];
        $dates = (isset($decoded['requestedDates']) && is_array($decoded['requestedDates'])) ? $decoded['requestedDates'] : [];
        
        return ['data' => $data, 'requestedDates' => $dates];
    }

    /**
     * Request custom dimensions from SentinelPro API
     */
    public static function request_custom_dimensions(string $account_name, string $property_id, string $api_key): array|false {
        $account = self::normalize_account_name($account_name);
        if ($account === '') {
            return false;
        }

        $endpoint = sprintf(self::BASE_ACCOUNT_API, $account) . 'dimensions/';
        $url = add_query_arg(['propertyId' => $property_id], $endpoint);

        $response = wp_safe_remote_get($url, [
            'headers' => [
                'SENTINEL-API-KEY' => $api_key,
                'Accept'           => 'application/json',
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status === 200 && isset($data['dimensions']) && is_array($data['dimensions'])) {
            return $data['dimensions'];
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- API error logging is essential for troubleshooting
            error_log("SentinelPro: Invalid response for custom dimensions request. Status: {$status}");
        }
        return false;
    }

    /**
     * Request new pixelCode with updated custom dimensions
     */
    public static function request_pixel_code(string $account_name, string $property_id, string $api_key, array $dimensions): string|false {
        $account = self::normalize_account_name($account_name);
        if ($account === '') {
            return false;
        }

        $endpoint = sprintf(self::BASE_ACCOUNT_API, $account) . 'pixel/';

        $response = wp_safe_remote_post($endpoint, [
            'headers' => [
                'SENTINEL-API-KEY' => $api_key,
                'Content-Type'     => 'application/json',
                'Accept'           => 'application/json',
            ],
            'body' => wp_json_encode([
                'propertyId' => $property_id,
                'dimensions' => array_values(array_map('sanitize_text_field', $dimensions)),
            ], JSON_UNESCAPED_SLASHES),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- API error logging is essential for troubleshooting
                error_log("SentinelPro: Failed to request pixel code: " . $response->get_error_message());
            }
            return false;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status === 200 && isset($data['pixelCode'])) {
            return $data['pixelCode'];
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- API error logging is essential for troubleshooting
            error_log("SentinelPro: Invalid response for pixel code request. Status: {$status}");
        }
        return false;
    }

    /**
     * Request pixel code via pixelCode endpoint (raw script response)
     * Uses GET with encoded payload matching production example
     */
    public static function request_pixel_code_raw(string $account_name, string $property_id, string $api_key): string|false {
        $account = self::normalize_account_name($account_name);
        if ($account === '') {
            return false;
        }

        $endpoint = sprintf(self::BASE_ACCOUNT_API, $account) . 'pixelCode/';
        $payload = [
            'filters' => [
                'propertyId' => [
                    'eq' => $property_id,
                ],
            ],
        ];
        $url = $endpoint . '?data=' . rawurlencode(wp_json_encode($payload, JSON_UNESCAPED_SLASHES));

        $response = wp_safe_remote_get($url, [
            'headers' => [
                'SENTINEL-API-KEY' => $api_key,
                'Accept'           => 'application/json',
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- API error logging is essential for troubleshooting
                error_log('SentinelPro: Failed to request pixel code (raw): ' . $response->get_error_message());
            }
            return false;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = wp_remote_retrieve_body($response);

        if ($status === 200 && is_string($body) && $body !== '') {
            return $body;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- API error logging is essential for troubleshooting
            error_log('SentinelPro: Invalid response for pixelCode request (raw). Status: ' . $status);
        }
        return false;
    }
}
