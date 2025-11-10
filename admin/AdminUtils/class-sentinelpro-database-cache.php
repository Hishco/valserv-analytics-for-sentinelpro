<?php

if ( ! defined('ABSPATH') ) { exit; }

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

/**
 * SentinelPro Database Cache Manager
 * Replaces localStorage with database caching
 */

class SentinelPro_Database_Cache {
    
    private static $instance = null;
    private $wpdb;
    private $table_name;
    private $ttl_hours = 24; // Default 24 hour cache
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'sentinelpro_analytics_cache';
    }
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get cached data
     */
    public function get($cache_key) {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Using $wpdb->prepare() correctly
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and safe
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE cache_key = %s AND expires_at > NOW()",
                $cache_key
            )
        );
        
        if ($result) {
            return [
                'data' => json_decode($result->data, true),
                'requestedDates' => json_decode($result->requested_dates, true),
                'timestamp' => $result->created_at
            ];
        }
        
        return null;
    }
    
    /**
     * Set cached data
     */
    public function set($cache_key, $data, $requested_dates, $meta = []) {
        $dimension_key = $meta['dimension_key'] ?? '';
        $start_date = $meta['start_date'] ?? '';
        $end_date = $meta['end_date'] ?? '';
        $metric = $meta['metric'] ?? '';
        $post_id = $meta['post_id'] ?? null;
        $granularity = $meta['granularity'] ?? 'daily';
        
        $expires_at = gmdate('Y-m-d H:i:s', strtotime("+{$this->ttl_hours} hours"));
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Cache storage for custom cache table, table name is validated
        $this->wpdb->replace(
            $this->table_name,
            [
                'cache_key' => $cache_key,
                'dimension_key' => $dimension_key,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'metric' => $metric,
                'post_id' => $post_id,
                'granularity' => $granularity,
                'data' => json_encode($data),
                'requested_dates' => json_encode($requested_dates),
                'expires_at' => $expires_at,
                'updated_at' => current_time('mysql')
            ],
            [
                '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s'
            ]
        );
        
        return true;
    }
    
    /**
     * Clear specific cache entry
     */
    public function clear($cache_key) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Cache deletion for custom cache table, table name is validated
        return $this->wpdb->delete(
            $this->table_name,
            ['cache_key' => $cache_key],
            ['%s']
        );
    }
    
    /**
     * Clear all cache
     */
    public function clear_all() {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Cache cleanup for custom cache table, table name is validated
        return $this->wpdb->query("DELETE FROM {$this->table_name}");
    }
    
    /**
     * Find overlapping range cache
     */
    public function find_overlapping_range_cache($dimension, $start_date, $end_date, $metric) {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Using $wpdb->prepare() correctly
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and safe
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                WHERE dimension_key = %s 
                AND metric = %s 
                AND start_date <= %s 
                AND end_date >= %s 
                AND expires_at > NOW()
                ORDER BY (end_date - start_date) ASC
                LIMIT 1",
                $dimension, $metric, $end_date, $start_date
            )
        );
        
        if ($result) {
            return [
                'key' => $result->cache_key,
                'start' => $result->start_date,
                'end' => $result->end_date,
                'entry' => [
                    'data' => json_decode($result->data, true),
                    'requestedDates' => json_decode($result->requested_dates, true)
                ]
            ];
        }
        
        return null;
    }
    
    /**
     * Find all overlapping or adjacent ranges
     */
    public function find_all_overlapping_or_adjacent_ranges($dimension, $start_date, $end_date, $metric) {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Using $wpdb->prepare() correctly
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and safe
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                WHERE dimension_key = %s 
                AND metric = %s 
                AND (
                    (start_date <= %s AND end_date >= %s) OR
                    (end_date >= DATE_SUB(%s, INTERVAL 1 DAY) AND start_date <= DATE_ADD(%s, INTERVAL 1 DAY))
                )
                AND expires_at > NOW()",
                $dimension, $metric, $end_date, $start_date, $start_date, $end_date
            )
        );
        
        $overlaps = [];
        foreach ($results as $result) {
            $overlaps[] = [
                'key' => $result->cache_key,
                'start' => $result->start_date,
                'end' => $result->end_date,
                'entry' => [
                    'data' => json_decode($result->data, true),
                    'requestedDates' => json_decode($result->requested_dates, true)
                ]
            ];
        }
        
        return $overlaps;
    }
    
    /**
     * Merge and replace ranges
     */
    public function merge_and_replace_ranges($dimension, $start_date, $end_date, $metric, $new_data) {
        $overlaps = $this->find_all_overlapping_or_adjacent_ranges($dimension, $start_date, $end_date, $metric);
        
        $all_data = $new_data;
        $min_start = $start_date;
        $max_end = $end_date;
        
        foreach ($overlaps as $overlap) {
            $all_data = array_merge($all_data, $overlap['entry']['data']);
            if ($overlap['start'] < $min_start) $min_start = $overlap['start'];
            if ($overlap['end'] > $max_end) $max_end = $overlap['end'];
        }
        
        // Deduplicate data
        $unique_data = [];
        $seen = [];
        foreach ($all_data as $row) {
            $key = $row['date'] . '|' . ($row['device'] ?? '') . '|' . ($row['country'] ?? '');
            if (!isset($seen[$key])) {
                $unique_data[] = $row;
                $seen[$key] = true;
            }
        }
        
        // Sort by date
        usort($unique_data, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });
        
        // Delete old cache entries
        foreach ($overlaps as $overlap) {
            $this->clear($overlap['key']);
        }
        
        // Create new cache key
        $new_cache_key = "range:{$dimension}:{$min_start}:{$max_end}:metric:{$metric}";
        
        // Store merged data
        $this->set($new_cache_key, $unique_data, [$min_start, $max_end], [
            'dimension_key' => $dimension,
            'start_date' => $min_start,
            'end_date' => $max_end,
            'metric' => $metric
        ]);
        
        return [
            'mergedData' => $unique_data,
            'newStart' => $min_start,
            'newEnd' => $max_end,
            'newKey' => $new_cache_key
        ];
    }
    
    /**
     * Get cache statistics
     */
    public function get_stats() {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Cache statistics for custom cache table, table name is validated
        $total_entries = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Cache statistics for custom cache table, table name is validated
        $expired_entries = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE expires_at < NOW()");
        $active_entries = $total_entries - $expired_entries;
        
        return [
            'total_entries' => $total_entries,
            'active_entries' => $active_entries,
            'expired_entries' => $expired_entries
        ];
    }
    
    /**
     * Check if cache entry is fresh (not expired)
     */
    public function has_fresh($cache_key) {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Using $wpdb->prepare() correctly
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and safe
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE cache_key = %s AND expires_at > NOW()",
                $cache_key
            )
        );
        
        return $result > 0;
    }

    /**
     * Find superset cache entry
     */
    public function find_superset_cache($cache_key) {
        // Parse the cache key to extract dimension, start, end, metric
        if (preg_match('/^range:(.+):(.+):(.+):metric:(.+)$/', $cache_key, $matches)) {
            $dimension = $matches[1];
            $requested_start = $matches[2];
            $requested_end = $matches[3];
            $metric = $matches[4];
            
            // Find a cache entry that covers the requested range
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Using $wpdb->prepare() correctly
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and safe
            $result = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table_name} 
                     WHERE dimension_key = %s 
                     AND metric = %s 
                     AND start_date <= %s 
                     AND end_date >= %s 
                     AND expires_at > NOW()
                     ORDER BY (end_date - start_date) ASC
                     LIMIT 1",
                    $dimension, $metric, $requested_start, $requested_end
                )
            );
            
            if ($result) {
                return [
                    'data' => json_decode($result->data, true),
                    'requestedDates' => json_decode($result->requested_dates, true),
                    'timestamp' => $result->created_at,
                    'key' => $result->cache_key,
                    'start' => $result->start_date,
                    'end' => $result->end_date
                ];
            }
        }
        
        return null;
    }

    /**
     * Clean up expired cache entries
     */
    public function cleanup_expired() {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Cache cleanup for custom cache table, table name is validated
        return $this->wpdb->query("DELETE FROM {$this->table_name} WHERE expires_at < NOW()");
    }
} 
