<?php

if ( ! defined('ABSPATH') ) { exit; }

/**
 * SentinelPro Input Validator
 * Comprehensive input validation and sanitization system
 */

class SentinelPro_Input_Validator {
    
    /**
     * Validate and sanitize text input
     */
    public static function validate_text($input, $max_length = 255, $required = false) {
        if ($required && empty($input)) {
            throw new Exception('This field is required');
        }
        
        if (!is_string($input)) {
            throw new Exception('Invalid input type');
        }
        
        $input = trim($input);
        
        if ($required && empty($input)) {
            throw new Exception('This field is required');
        }
        
        if (strlen($input) > $max_length) {
            throw new Exception("Input too long. Maximum length: " . esc_html($max_length) . " characters");
        }
        
        // Check for potentially dangerous content
        // PATTERN MATCHING ONLY, NO EXECUTION - Used to detect malicious input
        // These patterns are scanned for in user input to identify potential attacks
        $dangerous_patterns = [
            'javascript:', 'vbscript:', 'onload=', 'onerror=', 'onclick=',
            'eval(', 'base64_decode(', 'document.cookie', 'window.location'
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            if (stripos($input, $pattern) !== false) {
                throw new Exception('Input contains potentially dangerous content');
            }
        }
        
        return sanitize_text_field($input);
    }
    
    /**
     * Validate and sanitize email input
     */
    public static function validate_email($input, $required = false) {
        if ($required && empty($input)) {
            throw new Exception('Email is required');
        }
        
        if (!empty($input)) {
            $email = sanitize_email($input);
            if (!is_email($email)) {
                throw new Exception('Invalid email format');
            }
            return $email;
        }
        
        return '';
    }
    
    /**
     * Validate and sanitize URL input
     */
    public static function validate_url($input, $required = false, $allowed_domains = []) {
        if ($required && empty($input)) {
            throw new Exception('URL is required');
        }
        
        if (!empty($input)) {
            $url = esc_url_raw($input);
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new Exception('Invalid URL format');
            }
            
            // Check allowed domains if specified
            if (!empty($allowed_domains)) {
                $parsed_url = wp_parse_url($url);
                $domain = $parsed_url['host'] ?? '';
                
                $allowed = false;
                foreach ($allowed_domains as $allowed_domain) {
                    if (strpos($domain, $allowed_domain) !== false) {
                        $allowed = true;
                        break;
                    }
                }
                
                if (!$allowed) {
                    throw new Exception('URL domain not allowed');
                }
            }
            
            return $url;
        }
        
        return '';
    }
    
    /**
     * Validate and sanitize integer input
     */
    public static function validate_int($input, $min = null, $max = null, $required = false) {
        if ($required && empty($input)) {
            throw new Exception('This field is required');
        }
        
        if (!empty($input)) {
            $int = intval($input);
            
            if ($min !== null && $int < $min) {
                throw new Exception("Value must be at least " . esc_html($min));
            }
            
            if ($max !== null && $int > $max) {
                throw new Exception("Value must be no more than " . esc_html($max));
            }
            
            return $int;
        }
        
        return 0;
    }
    
    /**
     * Validate and sanitize float input
     */
    public static function validate_float($input, $min = null, $max = null, $required = false) {
        if ($required && empty($input)) {
            throw new Exception('This field is required');
        }
        
        if (!empty($input)) {
            $float = floatval($input);
            
            if ($min !== null && $float < $min) {
                throw new Exception("Value must be at least " . esc_html($min));
            }
            
            if ($max !== null && $float > $max) {
                throw new Exception("Value must be no more than " . esc_html($max));
            }
            
            return $float;
        }
        
        return 0.0;
    }
    
    /**
     * Validate and sanitize boolean input
     */
    public static function validate_bool($input, $required = false) {
        if ($required && empty($input)) {
            throw new Exception('This field is required');
        }
        
        if (is_bool($input)) {
            return $input;
        }
        
        if (is_string($input)) {
            $input = strtolower(trim($input));
            if (in_array($input, ['true', '1', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($input, ['false', '0', 'no', 'off'], true)) {
                return false;
            }
        }
        
        if (is_numeric($input)) {
            return (bool) $input;
        }
        
        return false;
    }
    
    /**
     * Validate and sanitize array input
     */
    public static function validate_array($input, $required = false, $max_items = 100) {
        if ($required && empty($input)) {
            throw new Exception('This field is required');
        }
        
        if (!is_array($input)) {
            throw new Exception('Invalid input type - array expected');
        }
        
        if (count($input) > $max_items) {
            throw new Exception("Too many items. Maximum: " . esc_html($max_items));
        }
        
        return array_map('sanitize_text_field', $input);
    }
    
    /**
     * Validate and sanitize date input
     */
    public static function validate_date($input, $format = 'Y-m-d', $required = false) {
        if ($required && empty($input)) {
            throw new Exception('Date is required');
        }
        
        if (!empty($input)) {
            $date = DateTime::createFromFormat($format, $input);
            if (!$date) {
                throw new Exception('Invalid date format');
            }
            
            return $date->format($format);
        }
        
        return '';
    }
    
    /**
     * Validate and sanitize file upload
     */
    public static function validate_file_upload($file, $allowed_types = [], $max_size = 5242880) {
        if (empty($file) || !is_array($file)) {
            throw new Exception('No file uploaded');
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . esc_html($file['error']));
        }
        
        if ($file['size'] > $max_size) {
            throw new Exception('File too large. Maximum size: ' . esc_html(size_format($max_size)));
        }
        
        // Use WordPress upload APIs for security
        $overrides = [
            'test_form' => false,
            'mimes' => $allowed_types ?: ['csv' => 'text/csv'],
            'unique_filename_callback' => 'wp_unique_filename',
            'max_size' => $max_size
        ];
        
        $uploaded_file = wp_handle_upload($file, $overrides);
        
        if (isset($uploaded_file['error'])) {
            throw new Exception('Upload error: ' . esc_html($uploaded_file['error']));
        }
        
        // Additional security validation
        $check = wp_check_filetype_and_ext($uploaded_file['file'], $uploaded_file['url']);
        if (!$check['ext']) {
            // Clean up the uploaded file
            wp_delete_file($uploaded_file['file']);
            throw new Exception('Invalid file type detected');
        }
        
        // Check for malicious content - PATTERN MATCHING ONLY, NO EXECUTION
        // These patterns are scanned for in file content to identify potential attacks
        // The patterns include base64_decode, eval, shell_exec, etc. for detection purposes
        $file_content = file_get_contents($uploaded_file['file']);
        if ($file_content !== false) {
            $dangerous_patterns = [
                '<?php', '<?=', '<? ', '<?\n', '<?\r', '<?\t',
                'eval(', 'base64_decode(', 'shell_exec(', 'system(',
                'exec(', 'passthru(', 'include(', 'require('
            ];
            
            foreach ($dangerous_patterns as $pattern) {
                if (stripos($file_content, $pattern) !== false) {
                    // Clean up the uploaded file
                    wp_delete_file($uploaded_file['file']);
                    throw new Exception('File contains potentially malicious content');
                }
            }
        }
        
        return $uploaded_file;
    }
    
    /**
     * Validate and sanitize JSON input
     */
    public static function validate_json($input, $required = false, $max_depth = 10) {
        if ($required && empty($input)) {
            throw new Exception('JSON input is required');
        }
        
        if (!empty($input)) {
            if (is_string($input)) {
                $decoded = json_decode($input, true, $max_depth);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Invalid JSON format: ' . esc_html(json_last_error_msg()));
                }
                return $decoded;
            }
            
            if (is_array($input)) {
                return $input;
            }
        }
        
        return [];
    }
    
    /**
     * Validate and sanitize HTML input
     */
    public static function validate_html($input, $allowed_tags = [], $max_length = 10000) {
        if (strlen($input) > $max_length) {
            throw new Exception("Input too long. Maximum length: " . esc_html($max_length) . " characters");
        }
        
        if (empty($allowed_tags)) {
            // Default allowed tags for basic formatting
            $allowed_tags = [
                'p' => [],
                'br' => [],
                'strong' => [],
                'em' => [],
                'u' => [],
                'ol' => [],
                'ul' => [],
                'li' => []
            ];
        }
        
        return wp_kses($input, $allowed_tags);
    }
    
    /**
     * Validate and sanitize SQL input (for custom queries)
     */
    public static function validate_sql_identifier($input) {
        // Only allow alphanumeric characters, underscores, and dots
        if (!preg_match('/^[a-zA-Z0-9_.]+$/', $input)) {
            throw new Exception('Invalid SQL identifier');
        }
        
        return $input;
    }
    
    /**
     * Validate and sanitize file path
     */
    public static function validate_file_path($input, $base_path = '') {
        $real_input = realpath($input);
        $real_base = realpath($base_path);
        
        if (!$real_input) {
            throw new Exception('Invalid file path');
        }
        
        if ($real_base && strpos($real_input, $real_base) !== 0) {
            throw new Exception('File path outside allowed directory');
        }
        
        return $real_input;
    }
    
    /**
     * Validate and sanitize IP address
     */
    public static function validate_ip($input, $required = false) {
        if ($required && empty($input)) {
            throw new Exception('IP address is required');
        }
        
        if (!empty($input)) {
            if (!filter_var($input, FILTER_VALIDATE_IP)) {
                throw new Exception('Invalid IP address format');
            }
            return $input;
        }
        
        return '';
    }
    
    /**
     * Validate and sanitize phone number
     */
    public static function validate_phone($input, $required = false) {
        if ($required && empty($input)) {
            throw new Exception('Phone number is required');
        }
        
        if (!empty($input)) {
            // Remove all non-digit characters
            $cleaned = preg_replace('/[^0-9]/', '', $input);
            
            if (strlen($cleaned) < 10 || strlen($cleaned) > 15) {
                throw new Exception('Invalid phone number length');
            }
            
            return $cleaned;
        }
        
        return '';
    }
    
    /**
     * Validate and sanitize postal code
     */
    public static function validate_postal_code($input, $required = false) {
        if ($required && empty($input)) {
            throw new Exception('Postal code is required');
        }
        
        if (!empty($input)) {
            // Basic postal code validation (adjust for your country)
            if (!preg_match('/^[A-Z0-9\s-]{3,10}$/i', $input)) {
                throw new Exception('Invalid postal code format');
            }
            
            return strtoupper(trim($input));
        }
        
        return '';
    }
    
    /**
     * Safe base64 decode with strict validation
     * Prevents base64 injection attacks by validating input before decoding
     * NEVER execute or include decoded content - only use for data processing
     * 
     * @param string $input The potentially base64 encoded string
     * @return string The decoded string, or empty string if invalid
     */
    public static function safe_b64(string $input): string {
        // Only allow standard base64 characters
        if (!preg_match('#^[A-Za-z0-9/+]*={0,2}$#', $input)) {
            return '';
        }
        
        // Length must be multiple of 4
        if ((strlen($input) % 4) !== 0) {
            return '';
        }
        
        // Decode with strict mode enabled
        $decoded = base64_decode($input, true);
        
        // Return empty string if decoding failed
        return $decoded === false ? '' : $decoded;
    }
}
