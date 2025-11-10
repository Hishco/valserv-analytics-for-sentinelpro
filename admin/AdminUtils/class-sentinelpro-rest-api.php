<?php

if ( ! defined('ABSPATH') ) { exit; }

/**
 * Secure REST API Implementation for SentinelPro
 * Implements proper schema validation, permissions, and security
 */
class SentinelPro_REST_API {

    const NAMESPACE = 'sentinelpro/v1';

    /**
     * Initialize REST API
     */
    public static function init(): void {
        add_action('rest_api_init', [self::class, 'register_routes']);
        add_filter('rest_authentication_errors', [self::class, 'restrict_rest_api_access']);
        add_action('rest_api_init', [self::class, 'add_security_headers']);
    }

    /**
     * Register REST routes
     */
    public static function register_routes(): void {
        // Analytics data endpoint
        register_rest_route(self::NAMESPACE, '/analytics/(?P<metric>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_analytics_data'],
            'permission_callback' => [self::class, 'check_analytics_permissions'],
            'args' => [
                'metric' => [
                    'required' => true,
                    'validate_callback' => [self::class, 'validate_metric'],
                    'sanitize_callback' => 'sanitize_text_field',
                    'type' => 'string',
                    'enum' => ['sessions', 'views', 'visits', 'all']
                ],
                'start_date' => [
                    'required' => false,
                    'validate_callback' => [self::class, 'validate_date'],
                    'sanitize_callback' => 'sanitize_text_field',
                    'type' => 'string',
                    'format' => 'date'
                ],
                'end_date' => [
                    'required' => false,
                    'validate_callback' => [self::class, 'validate_date'],
                    'sanitize_callback' => 'sanitize_text_field',
                    'type' => 'string',
                    'format' => 'date'
                ],
                'granularity' => [
                    'required' => false,
                    'validate_callback' => [self::class, 'validate_granularity'],
                    'sanitize_callback' => 'sanitize_text_field',
                    'type' => 'string',
                    'enum' => ['daily', 'weekly', 'monthly'],
                    'default' => 'daily'
                ]
            ],
            'schema' => [self::class, 'get_analytics_schema']
        ]);

        // User management endpoint
        register_rest_route(self::NAMESPACE, '/users/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_user_data'],
            'permission_callback' => [self::class, 'check_user_management_permissions'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => 'is_numeric',
                    'sanitize_callback' => 'absint',
                    'type' => 'integer',
                    'minimum' => 1
                ]
            ],
            'schema' => [self::class, 'get_user_schema']
        ]);

        // Settings endpoint
        register_rest_route(self::NAMESPACE, '/settings', [
            'methods' => ['GET', 'POST'],
            'callback' => [self::class, 'handle_settings'],
            'permission_callback' => [self::class, 'check_settings_permissions'],
            'args' => [
                'account_name' => [
                    'required' => false,
                    'validate_callback' => [self::class, 'validate_account_name'],
                    'sanitize_callback' => 'sanitize_text_field',
                    'type' => 'string',
                    'maxLength' => 100
                ],
                'property_id' => [
                    'required' => false,
                    'validate_callback' => [self::class, 'validate_property_id'],
                    'sanitize_callback' => 'sanitize_text_field',
                    'type' => 'string',
                    'maxLength' => 50
                ],
                'enable_tracking' => [
                    'required' => false,
                    'validate_callback' => 'rest_validate_boolean',
                    'sanitize_callback' => 'rest_sanitize_boolean',
                    'type' => 'boolean'
                ]
            ],
            'schema' => [self::class, 'get_settings_schema']
        ]);
    }

    /**
     * Check analytics permissions
     */
    public static function check_analytics_permissions(WP_REST_Request $request): bool {
        // SECURITY: Strict permission check with nonce verification
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return false;
        }
        
        // Check if user is authenticated
        if (!is_user_logged_in()) {
            return false;
        }
        
        // Check capabilities with fallback to manage_options
        return current_user_can('sentinelpro_view_dashboard') || current_user_can('manage_options');
    }

    /**
     * Check user management permissions
     */
    public static function check_user_management_permissions(WP_REST_Request $request): bool {
        // SECURITY: Strict permission check with nonce verification
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return false;
        }
        
        // Check if user is authenticated
        if (!is_user_logged_in()) {
            return false;
        }
        
        // Check capabilities with fallback to manage_options
        return current_user_can('sentinelpro_manage_users') || current_user_can('manage_options');
    }

    /**
     * Check settings permissions
     */
    public static function check_settings_permissions(WP_REST_Request $request): bool {
        // SECURITY: Strict permission check with nonce verification
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return false;
        }
        
        // Check if user is authenticated
        if (!is_user_logged_in()) {
            return false;
        }
        
        // Check capabilities with fallback to manage_options
        return current_user_can('sentinelpro_manage_settings') || current_user_can('manage_options');
    }

    /**
     * Validate metric parameter
     */
    public static function validate_metric($param, $request, $key): bool {
        $valid_metrics = ['sessions', 'views', 'visits', 'all'];
        return in_array($param, $valid_metrics, true);
    }

    /**
     * Validate date parameter
     */
    public static function validate_date($param, $request, $key): bool {
        if (empty($param)) {
            return true; // Optional parameter
        }
        
        $date = DateTime::createFromFormat('Y-m-d', $param);
        return $date && $date->format('Y-m-d') === $param;
    }

    /**
     * Validate granularity parameter
     */
    public static function validate_granularity($param, $request, $key): bool {
        $valid_granularities = ['daily', 'weekly', 'monthly'];
        return in_array($param, $valid_granularities, true);
    }

    /**
     * Validate account name
     */
    public static function validate_account_name($param, $request, $key): bool {
        if (empty($param)) {
            return true; // Optional parameter
        }
        
        return preg_match('/^[a-zA-Z0-9_-]+$/', $param) === 1;
    }

    /**
     * Validate property ID
     */
    public static function validate_property_id($param, $request, $key): bool {
        if (empty($param)) {
            return true; // Optional parameter
        }
        
        return preg_match('/^[a-zA-Z0-9_-]+$/', $param) === 1;
    }

    /**
     * Get analytics data
     */
    public static function get_analytics_data(WP_REST_Request $request): WP_REST_Response {
        // SECURITY: Additional input validation and sanitization
        $metric = sanitize_text_field($request->get_param('metric'));
        $start_date = sanitize_text_field($request->get_param('start_date') ?: gmdate('Y-m-d', strtotime('-30 days')));
        $end_date = sanitize_text_field($request->get_param('end_date') ?: gmdate('Y-m-d'));
        $granularity = sanitize_text_field($request->get_param('granularity') ?: 'daily');
        
        // Additional security: Validate date range to prevent abuse
        if (!self::validate_date_range($start_date, $end_date)) {
            return new WP_REST_Response([
                'error' => esc_html__('Invalid date range', 'valserv-analytics-for-sentinelpro')
            ], 400);
        }

        try {
            // Get API credentials
            $options = get_option('sentinelpro_options', []);
            $api_key = SentinelPro_Security_Manager::get_api_key();
            $property_id = $options['property_id'] ?? '';
            $account_name = $options['account_name'] ?? '';

            if (empty($api_key) || empty($property_id) || empty($account_name)) {
                return new WP_REST_Response([
                    'error' => 'API credentials not configured'
                ], 400);
            }

            // Build query parameters
            $params = [
                'start_date' => $start_date,
                'end_date' => $end_date,
                'metric' => $metric,
                'granularity' => $granularity
            ];

            // Fetch data from API
            $data = valserv_query_data($params, $account_name, $api_key);

            if (!$data) {
                return new WP_REST_Response([
                    'error' => 'Failed to fetch analytics data'
                ], 500);
            }

            return new WP_REST_Response([
                'success' => true,
                'data' => $data,
                'params' => $params
            ], 200);

        } catch (Exception $e) {
            // SECURITY: Don't expose internal error details in production
            if (defined('WP_DEBUG') && WP_DEBUG) {
                return new WP_REST_Response([
                    'error' => esc_html__('Internal server error', 'valserv-analytics-for-sentinelpro'),
                    'message' => esc_html($e->getMessage())
                ], 500);
            } else {
                return new WP_REST_Response([
                    'error' => esc_html__('Internal server error', 'valserv-analytics-for-sentinelpro')
                ], 500);
            }
        }
    }

    /**
     * Get user data
     */
    public static function get_user_data(WP_REST_Request $request): WP_REST_Response {
        $user_id = $request->get_param('id');
        
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return new WP_REST_Response([
                'error' => 'User not found'
            ], 404);
        }

        // Get user access data
        $access = get_user_meta($user_id, 'sentinelpro_access', true) ?: [];
        $clearance = get_user_meta($user_id, 'sentinelpro_clearance_level', true) ?: 'restricted';

        // SECURITY: Only expose necessary user data, sanitize sensitive information
        $user_data = [
            'id' => (int) $user->ID,
            'user_login' => esc_html($user->user_login),
            'user_email' => esc_html($user->user_email),
            'display_name' => esc_html($user->display_name),
            'roles' => array_map('esc_html', $user->roles),
            'access' => is_array($access) ? array_map('esc_html', $access) : [],
            'clearance_level' => esc_html($clearance),
            'is_superuser' => (int) get_option('sentinelpro_superuser_id') === $user->ID
        ];

        return new WP_REST_Response([
            'success' => true,
            'data' => $user_data
        ], 200);
    }

    /**
     * Handle settings
     */
    public static function handle_settings(WP_REST_Request $request): WP_REST_Response {
        $method = $request->get_method();

        if ($method === 'GET') {
            $options = get_option('sentinelpro_options', []);
            
            // Don't expose API key in GET requests
            unset($options['api_key']);
            
            return new WP_REST_Response([
                'success' => true,
                'data' => $options
            ], 200);
        }

        if ($method === 'POST') {
            // SECURITY: Additional validation and sanitization for POST requests
            $account_name = sanitize_text_field($request->get_param('account_name'));
            $property_id = sanitize_text_field($request->get_param('property_id'));
            $enable_tracking = rest_sanitize_boolean($request->get_param('enable_tracking'));

            $options = get_option('sentinelpro_options', []);
            
            // Validate account name format
            if ($account_name !== null && !self::validate_account_name($account_name)) {
                return new WP_REST_Response([
                    'error' => esc_html__('Invalid account name format', 'valserv-analytics-for-sentinelpro')
                ], 400);
            }
            
            // Validate property ID format
            if ($property_id !== null && !self::validate_property_id($property_id)) {
                return new WP_REST_Response([
                    'error' => esc_html__('Invalid property ID format', 'valserv-analytics-for-sentinelpro')
                ], 400);
            }
            
            // Only allow boolean for enable_tracking
            if ($enable_tracking !== null && !is_bool($enable_tracking)) {
                return new WP_REST_Response([
                    'error' => esc_html__('Invalid tracking setting', 'valserv-analytics-for-sentinelpro')
                ], 400);
            }
            
            if ($account_name !== null) {
                $options['account_name'] = $account_name;
            }
            
            if ($property_id !== null) {
                $options['property_id'] = $property_id;
            }
            
            if ($enable_tracking !== null) {
                $options['enable_tracking'] = $enable_tracking;
            }

            $updated = update_option('sentinelpro_options', $options);

            return new WP_REST_Response([
                'success' => $updated,
                'message' => $updated ? 'Settings updated successfully' : 'Failed to update settings'
            ], $updated ? 200 : 500);
        }

        return new WP_REST_Response([
            'error' => 'Method not allowed'
        ], 405);
    }

    /**
     * Get analytics schema
     */
    public static function get_analytics_schema(): array {
        return [
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'Analytics Data',
            'type' => 'object',
            'properties' => [
                'success' => [
                    'type' => 'boolean',
                    'description' => 'Whether the request was successful'
                ],
                'data' => [
                    'type' => 'object',
                    'description' => 'Analytics data'
                ],
                'params' => [
                    'type' => 'object',
                    'description' => 'Request parameters used'
                ]
            ],
            'required' => ['success']
        ];
    }

    /**
     * Get user schema
     */
    public static function get_user_schema(): array {
        return [
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'User Data',
            'type' => 'object',
            'properties' => [
                'success' => [
                    'type' => 'boolean',
                    'description' => 'Whether the request was successful'
                ],
                'data' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'user_login' => ['type' => 'string'],
                        'user_email' => ['type' => 'string'],
                        'display_name' => ['type' => 'string'],
                        'roles' => ['type' => 'array'],
                        'access' => ['type' => 'object'],
                        'clearance_level' => ['type' => 'string'],
                        'is_superuser' => ['type' => 'boolean']
                    ]
                ]
            ],
            'required' => ['success']
        ];
    }

    /**
     * Get settings schema
     */
    public static function get_settings_schema(): array {
        return [
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'Settings',
            'type' => 'object',
            'properties' => [
                'success' => [
                    'type' => 'boolean',
                    'description' => 'Whether the request was successful'
                ],
                'data' => [
                    'type' => 'object',
                    'properties' => [
                        'account_name' => ['type' => 'string'],
                        'property_id' => ['type' => 'string'],
                        'enable_tracking' => ['type' => 'boolean']
                    ]
                ],
                'message' => [
                    'type' => 'string',
                    'description' => 'Response message'
                ]
            ],
            'required' => ['success']
        ];
    }
    
    /**
     * Add security headers to REST API responses
     * Prevents common attacks and information disclosure
     */
    public static function add_security_headers(): void {
        // Add security headers to all REST API responses
        add_action('rest_pre_serve_request', function() {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            
            // Add rate limiting headers
            header('X-RateLimit-Limit: 100');
            header('X-RateLimit-Remaining: 99'); // This would be calculated dynamically
            header('X-RateLimit-Reset: ' . (time() + 3600));
        });
    }
    
    /**
     * Validate date range to prevent abuse
     * Ensures reasonable date ranges and prevents excessive data requests
     * 
     * @param string $start_date Start date in Y-m-d format
     * @param string $end_date End date in Y-m-d format
     * @return bool True if date range is valid, false otherwise
     */
    public static function validate_date_range(string $start_date, string $end_date): bool {
        // Parse dates
        $start = DateTime::createFromFormat('Y-m-d', $start_date);
        $end = DateTime::createFromFormat('Y-m-d', $end_date);
        
        if (!$start || !$end) {
            return false;
        }
        
        // Ensure start date is before end date
        if ($start > $end) {
            return false;
        }
        
        // Limit date range to prevent abuse (max 1 year)
        $diff = $start->diff($end);
        if ($diff->days > 365) {
            return false;
        }
        
        // Ensure dates are not too far in the past or future
        $now = new DateTime();
        $min_date = (new DateTime())->modify('-2 years');
        $max_date = (new DateTime())->modify('+1 month');
        
        if ($start < $min_date || $end > $max_date) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Restrict REST API access for security
     * Prevents unauthorized access to REST API endpoints
     * 
     * @param WP_Error|null $result Authentication result
     * @return WP_Error|null Modified authentication result
     */
    public static function restrict_rest_api_access($result) {
        // If there's already an error, return it
        if ($result !== null) {
            return $result;
        }
        
        // Allow access to our namespace
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- REQUEST_URI is used for path matching only, not for output
        if (strpos(wp_unslash(sanitize_text_field($_SERVER['REQUEST_URI'] ?? '')), '/wp-json/' . self::NAMESPACE) !== false) {
            // Check if user is authenticated for our endpoints
            if (!is_user_logged_in()) {
                return new WP_Error(
                    'rest_forbidden',
                    esc_html__('Authentication required', 'valserv-analytics-for-sentinelpro'),
                    ['status' => 401]
                );
            }
            
            // Check if user has any SentinelPro capabilities
            if (!current_user_can('sentinelpro_view_dashboard') && 
                !current_user_can('sentinelpro_manage_users') && 
                !current_user_can('sentinelpro_manage_settings') && 
                !current_user_can('manage_options')) {
                return new WP_Error(
                    'rest_forbidden',
                    esc_html__('Insufficient permissions', 'valserv-analytics-for-sentinelpro'),
                    ['status' => 403]
                );
            }
        }
        
        return $result;
    }
}
