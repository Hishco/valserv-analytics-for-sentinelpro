// DashboardHelpers/DataOrchestrator.js

let debounceTimeout = null;

export default class DataOrchestrator {
    constructor({
        dataFetcher,
        chartRenderer,
        tableRenderer,
        status,
        dateManager,
        filterBuilder,
        spinner,
        refs
        }) {
        this.dataFetcher = dataFetcher;
        this.chartRenderer = chartRenderer;
        this.tableRenderer = tableRenderer;
        this.status = status;
        this.dateManager = dateManager;
        this.filterBuilder = filterBuilder;
        this.spinner = spinner;
        this.refs = refs;

        this.raw = [];
        this.cachedCompareData = [];
        this.cachedCompareDates = [];
        this.requestedDatesGlobal = [];
        this.currentMetric = 'traffic';
        this.dataCache = {}; // Add cache object
        
        // Cache for comparison parameters to avoid unnecessary refetches
        this.lastCompareParams = null;
        
        // Cache for original comparison data (before processing)
        this.cachedOriginalCompareData = [];
    }

    // Helper to create a unique cache key from filters and params
    getCacheKey(filters, startDate, endDate, metric, granularity) {
        // Convert filters to a sorted object for uniqueness
        const filterObj = {};
        Array.from(filters.keys()).sort().forEach(key => {
            filterObj[key] = filters.get(key);
        });
        return JSON.stringify({
            filters: filterObj,
            startDate,
            endDate,
            metric,
            granularity
        });
    }

    // Helper to check if comparison parameters have changed
    hasCompareParamsChanged(compareStartDate, compareEndDate, mainDimensions, mainMetric, granularity) {
        const currentParams = {
            compareStartDate,
            compareEndDate,
            mainDimensions,
            mainMetric,
            granularity
        };
        
        if (!this.lastCompareParams) {
            this.lastCompareParams = currentParams;
            return true; // First time, so it has "changed"
        }
        
        const hasChanged = JSON.stringify(currentParams) !== JSON.stringify(this.lastCompareParams);
        if (hasChanged) {
            this.lastCompareParams = currentParams;
        }
        
        return hasChanged;
    }

    // Helper to clear comparison cache
    clearComparisonCache() {
        this.cachedCompareData = [];
        this.cachedCompareDates = [];
        this.cachedOriginalCompareData = [];
        this.lastCompareParams = null;
    }

    // Public method to clear comparison cache (can be called from other components)
    clearComparisonData() {
        this.clearComparisonCache();
    }

    async fetch(metricOverride = 'traffic') {
        // Debounce API requests
        if (debounceTimeout) clearTimeout(debounceTimeout);
        return new Promise((resolve, reject) => {
            debounceTimeout = setTimeout(async () => {
                try {
                    const result = await this._fetchWithRetry(metricOverride);
                    resolve(result);
                } catch (e) {
                    reject(e);
                }
            }, 400); // 400ms debounce
        });
    }

    async _fetchWithRetry(metricOverride = 'traffic', attempt = 1) {
        try {
            return await this._fetchInternal(metricOverride);
        } catch (err) {
            // Check for 429 Too Many Requests
            if (err && err.status === 429 && attempt <= 5) {
                let retryAfter = 0;
                if (err.headers && err.headers.get) {
                    const ra = err.headers.get('Retry-After');
                    if (ra) retryAfter = parseInt(ra, 10) * 1000;
                }
                if (!retryAfter || isNaN(retryAfter)) {
                    retryAfter = Math.min(16000, 1000 * Math.pow(2, attempt - 1)); // 1s, 2s, 4s, 8s, 16s
                }
                if (this.status && typeof this.status.show === 'function') {
                    this.status.show(`Too many requests. Retrying in ${retryAfter / 1000}s...`, false);
                }
                await new Promise(res => setTimeout(res, retryAfter));
                return this._fetchWithRetry(metricOverride, attempt + 1);
            }
            throw err;
        }
    }

    async _fetchInternal(metricOverride = 'traffic') {
        try {
            const {
                startInput,
                endInput,
                compareStartInput,
                compareEndInput,
                granularitySelect
            } = this.refs;

            const selectedMetric = metricOverride || 'traffic';
            const isAll = selectedMetric === 'all';
            this.currentMetric = isAll ? 'traffic' : selectedMetric;

            const granularity = granularitySelect?.value || 'daily';

            let startDate = startInput?.value;
            let endDate = endInput?.value;

            // Set default dates if not provided
            if (!startDate || !endDate) {
                const today = new Date();
                const yesterday = new Date(today);
                yesterday.setDate(today.getDate() - 1);
                const past = new Date(today);
                past.setDate(today.getDate() - 30); // <-- changed from -31 to -30

                startDate = past.toISOString().split('T')[0];
                endDate = yesterday.toISOString().split('T')[0];

                startInput.value = startDate;
                endInput.value = endDate;
            }

            // Get comparison settings
            const compareToggle = document.getElementById('compare-toggle');
            const compareEnabled = compareToggle && compareToggle.checked;
            let compareStartDate = compareStartInput && compareStartInput.value ? compareStartInput.value : null;
            let compareEndDate = compareEndInput && compareEndInput.value ? compareEndInput.value : null;

            if (compareEnabled && (!compareStartDate || !compareEndDate)) {
                compareStartDate = null;
                compareEndDate = null;
            }

            // Build filters
            const filters = this.filterBuilder.build();
            const postId = filters.get('post_id');
            if (postId) filters.set('post_id', postId);

            const dimension = filters.get('dimensions');
            const mainMetric = isAll ? 'all' : selectedMetric;

            if (this.spinner) this.spinner.style.display = 'block';

            // Check database status first
            const dbStatus = await this.dataFetcher.checkDatabaseStatus();

            // Fetch main data
            const mainResult = await this.dataFetcher.fetchData({
                metric: mainMetric,
                granularity,
                startDate,
                endDate,
                filters,
                postId
            });

            let compareResult = null;
            if (compareEnabled && compareStartDate && compareEndDate) {
                // Create clean filters for comparison data (exclude date parameters)
                const cleanFilters = new Map();
                for (const [key, value] of filters) {
                    if (key !== 'start_date' && key !== 'end_date') {
                        cleanFilters.set(key, value);
                    }
                }

                compareResult = await this.dataFetcher.fetchData({
                    metric: mainMetric,
                    granularity,
                    startDate: compareStartDate,
                    endDate: compareEndDate,
                    filters: cleanFilters,
                    postId
                });
            }

            // Process and render data
            if (mainResult && mainResult.data) {
                const mainData = mainResult.data.map(row => this.normalizeRowKeys(row));
                const compareData = compareResult && compareResult.data ? compareResult.data.map(row => this.normalizeRowKeys(row)) : [];

                // Store comparison data separately (like the working version)
                this.cachedCompareData = compareData;
                this.cachedCompareDates = compareResult ? compareResult.requestedDates || [compareStartDate, compareEndDate] : [];



                // Update status
                if (this.status && typeof this.status.hide === 'function') {
                    this.status.hide();
                }
                
                // Return the data for the dashboard
                return {
                    data: mainData,
                    comparisonData: compareData
                };
            } else {
                if (this.status && typeof this.status.show === 'function') {
                    this.status.show('No data available for the selected date range.', true);
                }
                return [];
            }

        } catch (error) {
            if (this.status && typeof this.status.show === 'function') {
                this.status.show('Error loading data. Please try again.', true);
            }
        } finally {
            if (this.spinner) this.spinner.style.display = 'none';
        }
    }

    // Helper to queue chunked requests sequentially
    async fetchChunksSequentially(chunkParamsArray) {
        const results = [];
        const failedChunks = [];
        for (const params of chunkParamsArray) {
            let success = false, attempts = 0;
            while (!success && attempts < 5) {
                try {
                    const result = await this._fetchChunkWithRetry(params);
                    results.push(result);
                    success = true;
                } catch (err) {
                    if (err && err.status === 429) {
                        let retryAfter = 0;
                        if (err.headers && err.headers.get) {
                            const ra = err.headers.get('Retry-After');
                            if (ra) retryAfter = parseInt(ra, 10) * 1000;
                        }
                        if (!retryAfter || isNaN(retryAfter)) {
                            retryAfter = Math.min(16000, 1000 * Math.pow(2, attempts));
                        }
                        if (this.status && typeof this.status.show === 'function') {
                            this.status.show(`Too many requests. Retrying in ${retryAfter / 1000}s...`, false);
                        }
                        await new Promise(res => setTimeout(res, retryAfter));
                        attempts++;
                    } else {
                        break;
                    }
                }
            }
            if (!success) {
                failedChunks.push(params);
            }
        }
        return { results, failedChunks };
    }

    async _fetchChunkWithRetry(params, attempt = 1) {
        try {
            return await this.dataFetcher.fetchData(params);
        } catch (err) {
            if (err && err.status === 429 && attempt <= 5) {
                let retryAfter = 0;
                if (err.headers && err.headers.get) {
                    const ra = err.headers.get('Retry-After');
                    if (ra) retryAfter = parseInt(ra, 10) * 1000;
                }
                if (!retryAfter || isNaN(retryAfter)) {
                    retryAfter = Math.min(16000, 1000 * Math.pow(2, attempt - 1));
                }
                if (this.status && typeof this.status.show === 'function') {
                    this.status.show(`Too many requests. Retrying in ${retryAfter / 1000}s...`, false);
                }
                await new Promise(res => setTimeout(res, retryAfter));
                return this._fetchChunkWithRetry(params, attempt + 1);
            }
            throw err;
        }
    }

    normalizeRowKeys(row) {
        const map = {
            pagesPerSession: 'pagespersession',
            averageEngagedDuration: 'averageengagedduration',
            averageEngagedDepth: 'averageengageddepth',
            averageConnectionSpeed: 'averageconnectionspeed'
        };
        const normalized = {};
        for (const [key, value] of Object.entries(row)) {
            const mappedKey = map[key] || key.toLowerCase();
            normalized[mappedKey] = value;
        }
        return normalized;
    }

    renderFixedTable(data, metric, requestedDates, comparisonData, comparisonDates) {
        if (this.tableRenderer) {
            this.tableRenderer.render({
                data,
                metric,
                granularity: 'daily',
                start: requestedDates?.[0],
                end: requestedDates?.[requestedDates.length - 1],
                requestedDates,
                comparisonData,
                comparisonDates
            });
        }
    }

    renderChart(data, metric, requestedDates = [], comparisonData = [], comparisonDates = []) {
        const compareEnabled = document.getElementById('compare-toggle')?.checked;
        const chartContainer = document.getElementById('sentinelpro-chart-scroll');
        const svgRenderer = window.GaugeSVGRenderer ? new window.GaugeSVGRenderer('gauge-summary-cards') : null;
        const granularity = this.refs.granularitySelect?.value || 'daily';

        if (metric === 'engagement') {
            if (chartContainer) chartContainer.style.display = 'none';
            const gaugeCards = document.getElementById('gauge-summary-cards');
            if (gaugeCards) gaugeCards.innerHTML = '';
            const metrics = Array.from(document.querySelectorAll('.metric-avg:checked')).map(i => i.value);
            if (svgRenderer && gaugeCards) {
                svgRenderer.render(
                    data,
                    metrics,
                    requestedDates,
                    compareEnabled ? this.cachedCompareData : [],
                    compareEnabled ? this.cachedCompareDates : [],
                    granularity
                );
            }
            return;
        } else {
            const gaugeCards = document.getElementById('gauge-summary-cards');
            if (gaugeCards) gaugeCards.innerHTML = '';
        }

        if (chartContainer) {
            chartContainer.style.display = 'block';
            this.chartRenderer.render({
                data,
                metric,
                granularity,
                start: this.refs.startInput.value,
                end: this.refs.endInput.value,
                requestedDates,
                comparisonData: compareEnabled ? this.cachedCompareData : [],
                comparisonDates: compareEnabled ? this.cachedCompareDates : []
            });
        }
    }

    refreshDisplay() {
        if (this.tableRenderer) {
            this.tableRenderer.render({
                data: this.raw || [],
                metric: this.currentMetric,
                granularity: this.refs.granularitySelect?.value || 'daily',
                start: this.refs.startInput?.value,
                end: this.refs.endInput?.value,
                requestedDates: this.requestedDatesGlobal || [],
                comparisonData: this.cachedCompareData || [],
                comparisonDates: this.cachedCompareDates || []
            });
        }
    }


}