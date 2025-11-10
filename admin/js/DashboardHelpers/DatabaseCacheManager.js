// DashboardHelpers/DatabaseCacheManager.js
// Replaces localStorage with database caching via AJAX

export default class DatabaseCacheManager {
    constructor() {
        this.ajaxUrl = window.valservDashboardData?.ajaxUrl || window.ajaxurl;
        this.nonce = window.valservDashboardData?.nonce || '';
    }

    /**
     * Get cached data from database
     */
    async get(cacheKey) {
        try {
            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'sentinelpro_get_cache',
                    nonce: this.nonce,
                    cache_key: cacheKey
                })
            });

            const result = await response.json();
            return result.success ? result.data : null;
        } catch (error) {
            return null;
        }
    }

    /**
     * Set cached data in database
     */
    async set(cacheKey, data, requestedDates, meta = {}) {
        try {
            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'sentinelpro_set_cache',
                    nonce: this.nonce,
                    cache_key: cacheKey,
                    data: JSON.stringify(data),
                    requested_dates: JSON.stringify(requestedDates),
                    meta: JSON.stringify(meta)
                })
            });

            const result = await response.json();
            return result.success;
        } catch (error) {
            return false;
        }
    }

    /**
     * Clear specific cache entry
     */
    async clear(cacheKey) {
        try {
            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'sentinelpro_clear_cache',
                    nonce: this.nonce,
                    cache_key: cacheKey
                })
            });

            const result = await response.json();
            return result.success;
        } catch (error) {
            return false;
        }
    }

            /**
         * Clear all cache
         */
        async clearAll() {
            try {
                const response = await fetch(this.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'sentinelpro_clear_all_cache',
                        nonce: this.nonce
                    })
                });

                const result = await response.json();
                return result.success;
            } catch (error) {
                return false;
            }
        }

        /**
         * Find overlapping range cache
         */
        async findOverlappingRangeCache(dimension, startDate, endDate, metric) {
            try {
                const response = await fetch(this.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'sentinelpro_find_overlapping_cache',
                        nonce: this.nonce,
                        dimension: dimension,
                        start_date: startDate,
                        end_date: endDate,
                        metric: metric
                    })
                });

                const result = await response.json();
                return result.success ? result.data : null;
            } catch (error) {
                return null;
            }
        }

        /**
         * Find all overlapping or adjacent ranges
         */
        async findAllOverlappingOrAdjacentRanges(dimension, startDate, endDate, metric) {
            try {
                const response = await fetch(this.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'sentinelpro_find_all_overlapping_ranges',
                        nonce: this.nonce,
                        dimension: dimension,
                        start_date: startDate,
                        end_date: endDate,
                        metric: metric
                    })
                });

                const result = await response.json();
                return result.success ? result.data : [];
            } catch (error) {
                return [];
            }
        }

        /**
         * Merge and replace ranges
         */
        async mergeAndReplaceRanges(dimension, startDate, endDate, metric, newData) {
            try {
                const response = await fetch(this.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'sentinelpro_merge_and_replace_ranges',
                        nonce: this.nonce,
                        dimension: dimension,
                        start_date: startDate,
                        end_date: endDate,
                        metric: metric,
                        new_data: JSON.stringify(newData)
                    })
                });

                const result = await response.json();
                return result.success ? result.data : {
                    mergedData: newData,
                    newStart: startDate,
                    newEnd: endDate,
                    newKey: `range:${dimension}:${startDate}:${endDate}:metric:${metric}`
                };
            } catch (error) {
                return {
                    mergedData: newData,
                    newStart: startDate,
                    newEnd: endDate,
                    newKey: `range:${dimension}:${startDate}:${endDate}:metric:${metric}`
                };
            }
        }

    /**
     * Log analytics request
     */
    async logRequest(requestData) {
        try {
            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'sentinelpro_log_request',
                    nonce: this.nonce,
                    request_type: requestData.requestType || 'main',
                    metric: requestData.metric || '',
                    granularity: requestData.granularity || 'daily',
                    start_date: requestData.startDate || '',
                    end_date: requestData.endDate || '',
                    dimensions: requestData.dimensions || '',
                    filters: JSON.stringify(requestData.filters || {}),
                    post_id: requestData.postId || 0,
                    response_time_ms: requestData.responseTime || 0,
                    cache_hit: requestData.cacheHit || false
                })
            });

            const result = await response.json();
            return result.success;
        } catch (error) {
            return false;
        }
    }

    /**
     * Save user session
     */
    async saveUserSession(sessionId, dashboardState) {
        try {
            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'sentinelpro_save_user_session',
                    nonce: this.nonce,
                    session_id: sessionId,
                    dashboard_state: JSON.stringify(dashboardState)
                })
            });

            const result = await response.json();
            return result.success;
        } catch (error) {
            return false;
        }
    }

    /**
     * Get user session
     */
    async getUserSession(sessionId) {
        try {
            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'sentinelpro_get_user_session',
                    nonce: this.nonce,
                    session_id: sessionId
                })
            });

            const result = await response.json();
            return result.success ? result.data : null;
        } catch (error) {
            return null;
        }
    }

    /**
     * Save user setting
     */
    async saveSetting(settingKey, settingValue) {
        try {
            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'sentinelpro_save_setting',
                    nonce: this.nonce,
                    setting_key: settingKey,
                    setting_value: settingValue
                })
            });

            const result = await response.json();
            return result.success;
        } catch (error) {
            return false;
        }
    }

    /**
     * Get user setting
     */
    async getSetting(settingKey) {
        try {
            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'sentinelpro_get_setting',
                    nonce: this.nonce,
                    setting_key: settingKey
                })
            });

            const result = await response.json();
            return result.success ? result.data : null;
        } catch (error) {
            return null;
        }
    }

    /**
     * Get cache statistics
     */
    getStats() {
        // This would need a new AJAX endpoint
        return {
            total_entries: 0,
            active_entries: 0,
            expired_entries: 0
        };
    }

            /**
         * Check if cache entry is fresh (not expired)
         */
        async hasFresh(cacheKey) {
            try {
                const response = await fetch(this.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'sentinelpro_has_fresh_cache',
                        nonce: this.nonce,
                        cache_key: cacheKey
                    })
                });

                const result = await response.json();
                return result.success ? result.data : false;
            } catch (error) {
                return false;
            }
        }

        /**
         * Find superset cache entry
         */
        async findSupersetCache(cacheKey) {
            try {
                const response = await fetch(this.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'sentinelpro_find_superset_cache',
                        nonce: this.nonce,
                        cache_key: cacheKey
                    })
                });

                const result = await response.json();
                return result.success ? result.data : null;
            } catch (error) {
                return null;
            }
        }

        /**
         * Get range cache key (static method)
         */
        static getRangeCacheKey(dimension, start, end, metric) {
            return `range:${dimension}:${start}:${end}:metric:${metric}`;
        }

        /**
         * Clean up expired cache entries
         */
        async cleanupExpired() {
            try {
                const response = await fetch(this.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'sentinelpro_cleanup_expired',
                        nonce: this.nonce
                    })
                });

                const result = await response.json();
                return result.success;
            } catch (error) {
                return false;
            }
        }
    } 