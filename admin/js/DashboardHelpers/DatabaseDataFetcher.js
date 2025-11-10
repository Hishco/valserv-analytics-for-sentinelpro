// DashboardHelpers/DatabaseDataFetcher.js
// Reads data directly from wp_sentinelpro_analytics_data table

export default class DatabaseDataFetcher {
    constructor(ajaxUrl) {
        this.ajaxUrl = ajaxUrl;
    }

    /**
     * Fetch data from the database
     */
    async fetchData({ metric = 'all', granularity = 'daily', startDate, endDate, filters, postId = null }) {

        try {
            // Debug logging

            const params = new URLSearchParams({
                action: 'valserv_fetch_database_data',
                start_date: startDate,
                end_date: endDate,
                metric: metric,
                granularity: granularity,
                nonce: window.valservDashboardData?.nonce || ''
            });
            
            // Debug: Log the actual parameters being sent

            // Add filters
            if (filters) {
                for (const [key, value] of filters) {
                    if (value && value !== '') {
                        params.set(key, value);
                    }
                }
            }

            if (postId) {
                params.set('post_id', postId);
            }

            // Validate AJAX URL
            if (!this.ajaxUrl || this.ajaxUrl === '') {
                throw new Error('AJAX URL is not set');
            }
            
            const response = await fetch(`${this.ajaxUrl}?${params.toString()}`, {
                mode: 'cors',
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            let result;
            try {
                result = await response.json();
            } catch (jsonError) {
                throw new Error('Invalid JSON response from server');
            }
            
            if (result.success) {
                return {
                    data: result.data || [],
                    requestedDates: [startDate, endDate]
                };
            } else {
                const errorMessage = result && result.message ? result.message : 'Unknown server error';
                
                // Check if it's a database setup issue
                if (errorMessage && errorMessage.includes('table does not exist')) {
                }
                
                return {
                    data: [],
                    requestedDates: [startDate, endDate],
                    error: errorMessage
                };
            }
        } catch (error) {
            const errorMessage = error && error.message ? error.message : 'Unknown error occurred';
            
            return {
                data: [],
                requestedDates: [startDate, endDate],
                error: errorMessage
            };
        }
    }

    /**
     * Check if database is available and has data
     */
    async checkDatabaseStatus() {
        try {
            const params = new URLSearchParams({
                action: 'valserv_fetch_database_data',
                start_date: new Date().toISOString().split('T')[0],
                end_date: new Date().toISOString().split('T')[0],
                metric: 'all',
                granularity: 'daily',
                nonce: window.valservDashboardData?.nonce || ''
            });

            const response = await fetch(`${this.ajaxUrl}?${params.toString()}`);
            const result = await response.json();

            return {
                available: result && result.success,
                hasData: result && result.success && result.data && result.data.length > 0,
                error: result && result.success ? null : (result.message || 'Unknown error')
            };
        } catch (error) {
            const errorMessage = error && error.message ? error.message : 'Unknown error occurred';
            return {
                available: false,
                hasData: false,
                error: errorMessage
            };
        }
    }

    /**
     * Normalize row keys to match expected format
     */
    _normalizeKeys(row) {
        return {
            date: row.date,
            hour: row.hour,
            metric: row.metric,
            dimension_name: row.dimension_name,
            dimension_value: row.dimension_value,
            value: parseFloat(row.value),
            post_id: row.post_id,
            // Add any additional fields that might be needed
            ...row
        };
    }
}