// DashboardHelpers/FilterBuilder.js

export default class FilterBuilder {
    constructor({ metricSelect, granularitySelect, startInput, endInput, eventTypeSelect, countryInput }) {
        this.metricSelect = metricSelect;
        this.granularitySelect = granularitySelect;
        this.startInput = startInput;
        this.endInput = endInput;
        this.eventTypeSelect = eventTypeSelect;
        this.countryInput = countryInput;
    }

    build(metricOverride) {
        const filters = new URLSearchParams();

        const granularity = this.granularitySelect?.value || 'daily';
        const start = this.startInput?.value || '';
        const end = this.endInput?.value || '';
        const metric = metricOverride || (this.metricSelect?.value || 'traffic');

        // Handle dimension selection from new dropdown
        const dimensionSelect = document.getElementById('dimension-select');
        let dimensionSet = false;
        if (dimensionSelect && dimensionSelect.value) {
            filters.set('dimensions', dimensionSelect.value);
            filters.set('group_by', dimensionSelect.value);
            dimensionSet = true;
        }

        // Map UI/display keys to canonical API keys for standard dimensions
        const DIMENSION_API_KEY_MAP = {
            'geo': 'geo',
            'browser': 'browser',
            'os': 'os',
            'device': 'device',
            'referrer': 'referrer',
            'utm campaign': 'utmCampaign',
            'utm medium': 'utmMedium',
            'utm source': 'utmSource'
        };

        // Build filters from UI state
        if (window.EnhancedDashboardInstance && typeof window.EnhancedDashboardInstance.getSelectedFilters === 'function') {
            const selected = window.EnhancedDashboardInstance.getSelectedFilters();
            let firstActiveFilter = null;
            let customDims = [];
            Object.entries(selected).forEach(([key, value]) => {
                const apiKey = DIMENSION_API_KEY_MAP[key.toLowerCase()] || key;
                if (apiKey === 'geo' && Array.isArray(value) && value.length > 0) {
                    let geoArr = value.map(code => code.toUpperCase());
                    filters.set(apiKey, geoArr.join(','));
                    if (!firstActiveFilter) firstActiveFilter = apiKey;
                } else if (apiKey === 'device' && Array.isArray(value) && value.length > 0) {
                    // Use exact device names
                    filters.set(apiKey, value.join(','));
                    if (!firstActiveFilter) firstActiveFilter = apiKey;
                } else if (Array.isArray(value) && value.length > 0) {
                    filters.set(apiKey, value.join(','));
                    if (!firstActiveFilter) firstActiveFilter = apiKey;
                } else if (typeof value === 'string' && value) {
                    filters.set(apiKey, value);
                    if (!firstActiveFilter) firstActiveFilter = apiKey;
                }
                // Track custom dimensions for dimensions/group_by
                if (
                  !Object.keys(DIMENSION_API_KEY_MAP).includes(key.toLowerCase()) &&
                  !['device','geo','browser','os','referrer','adblock','plan','date'].includes(key.toLowerCase()) &&
                  !key.toLowerCase().endsWith('_mode') &&
                  !key.endsWith('Mode')
                ) {
                    let camelKey = key.replace(/_([a-z])/g, g => g[1].toUpperCase());
                    if (/^[a-z]+$/.test(key)) {
                    } else if (key[0] && key[0] === key[0].toLowerCase()) {
                        camelKey = key.replace(/_([a-z])/g, g => g[1].toUpperCase());
                    } else {
                        camelKey = key;
                    }
                    const knownCamelCase = ['primaryCategory','networkCategory','segment','publishDate','primaryTag','contentType','intent','adsTemplate','articleType','initiative','system'];
                    const found = knownCamelCase.find(dim => dim.toLowerCase() === key.toLowerCase());
                    if (found) camelKey = found;
                    if (!customDims.includes(camelKey)) customDims.push(camelKey);
                }
            });
            
            // Only set dimensions/group_by if explicitly requested (not for regular filtering)
            // This prevents the database query from being too restrictive
            if (window.sentinelpro_activeDimensions && window.sentinelpro_activeDimensions.length > 0) {
                let dimsArr = Array.from(new Set([...window.sentinelpro_activeDimensions, ...customDims]));
                const dims = dimsArr.join(',');
                filters.set('dimensions', dims);
                filters.set('group_by', dims);
            } else if (customDims.length > 0) {
                const dims = customDims.join(',');
                filters.set('dimensions', dims);
                filters.set('group_by', dims);
            }
            // Removed automatic setting of dimensions/group_by based on firstActiveFilter
        }

        // --- Force sync to hidden fields before returning filters ---
        const filterStart = document.getElementById('filter-start');
        const filterEnd = document.getElementById('filter-end');
        if (filterStart && this.startInput?.value) filterStart.value = this.startInput.value;
        if (filterEnd && this.endInput?.value) filterEnd.value = this.endInput.value;

        // Handle date format by granularity
        if (granularity === 'hourly') {
            if (start) filters.set('date1', start);
            if (end) filters.set('date2', end);
        } else {
            if (start) filters.set('start_date', start);
            if (end) filters.set('end_date', end);
        }

        filters.set('metric', metric);
        filters.set('granularity', granularity);

        // Optional country filter
        if (this.countryInput?.value) {
            filters.set('country', this.countryInput.value);
        }

        // Ensure contentType (camelCase) is included as a dimension if 'contenttype' filter is used
        if (filters.has('contenttype')) {
            let dims = filters.get('dimensions');
            if (!dims) {
                filters.set('dimensions', 'contentType');
                filters.set('group_by', 'contentType');
            } else {
                let dimArr = dims.split(',').map(d => d.trim()).filter(Boolean);
                dimArr = dimArr.map(d => d === 'contenttype' ? 'contentType' : d);
                dimArr = [...new Set(dimArr)];
                if (!dimArr.includes('contentType')) dimArr.push('contentType');
                filters.set('dimensions', dimArr.join(','));
                filters.set('group_by', dimArr.join(','));
            }
        }
        let dimsFinal = filters.get('dimensions');
        let dimArr = dimsFinal ? dimsFinal.split(',').map(d => d.trim()).filter(Boolean) : [];
        
        // Debug: Log final filter parameters
        
        return filters;
    }

    // Collect all selected filters from the UI and return a structured object
    static getSelectedFilters() {
        const filters = {};
        // DEVICE
        const deviceHeader = Array.from(document.querySelectorAll('.sentinelpro-filter-item-header')).find(h => h.textContent.trim().toUpperCase().startsWith('DEVICE'));
        if (deviceHeader) {
            const deviceSection = deviceHeader.closest('.sentinelpro-filter-item');
            if (deviceSection) {
                const deviceFilters = Array.from(deviceSection.querySelectorAll('.sentinelpro-checkbox-group input[type="checkbox"]:checked')).map(cb => cb.value);
                if (deviceFilters.length > 0) filters.device = deviceFilters;
            }
        }
        // GEO
        const geoHeader = Array.from(document.querySelectorAll('.sentinelpro-filter-item-header')).find(h => h.textContent.trim().toUpperCase().startsWith('GEO'));
        if (geoHeader) {
            const geoSection = geoHeader.closest('.sentinelpro-filter-item');
            if (geoSection) {
                let geoFilters = Array.from(geoSection.querySelectorAll('#geo-checkbox-group input[type="checkbox"]:checked')).map(cb => cb.value);
                geoFilters = geoFilters.concat(Array.from(geoSection.querySelectorAll('#geo-custom-checkboxes input[type="checkbox"]:checked')).map(cb => cb.value));
                if (geoFilters.length > 0) filters.geo = geoFilters;
            }
        }
        // REFERRER
        const referrerHeader = Array.from(document.querySelectorAll('.sentinelpro-filter-item-header')).find(h => h.textContent.trim().toUpperCase().startsWith('REFERRER'));
        if (referrerHeader) {
            const referrerSection = referrerHeader.closest('.sentinelpro-filter-item');
            if (referrerSection) {
                const referrerFilters = Array.from(referrerSection.querySelectorAll('.sentinelpro-checkbox-group input[type="checkbox"]:checked')).map(cb => cb.value);
                if (referrerFilters.length > 0) filters.referrer = referrerFilters;
            }
        }
        // OPERATING SYSTEM
        const osHeader = Array.from(document.querySelectorAll('.sentinelpro-filter-item-header')).find(h => h.textContent.trim().toUpperCase().startsWith('OPERATING SYSTEM'));
        if (osHeader) {
            const osSection = osHeader.closest('.sentinelpro-filter-item');
            if (osSection) {
                const osFilters = Array.from(osSection.querySelectorAll('.sentinelpro-checkbox-group input[type="checkbox"]:checked')).map(cb => cb.value);
                if (osFilters.length > 0) filters.os = osFilters;
            }
        }
        // BROWSER
        const browserHeader = Array.from(document.querySelectorAll('.sentinelpro-filter-item-header')).find(h => h.textContent.trim().toUpperCase().startsWith('BROWSER'));
        if (browserHeader) {
            const browserSection = browserHeader.closest('.sentinelpro-filter-item');
            if (browserSection) {
                const browserFilters = Array.from(browserSection.querySelectorAll('.sentinelpro-checkbox-group input[type="checkbox"]:checked')).map(cb => cb.value);
                if (browserFilters.length > 0) filters.browser = browserFilters;
            }
        }
        // Custom dimensions
        document.querySelectorAll('.sentinelpro-custom-checkboxes').forEach(checkboxGroup => {
            const dimension = checkboxGroup.getAttribute('data-dimension');
            const checkedBoxes = checkboxGroup.querySelectorAll('input[type="checkbox"]:checked');
            if (checkedBoxes.length > 0) {
                const values = Array.from(checkedBoxes).map(cb => cb.value);
                const apiKey = dimension.toLowerCase();
                filters[apiKey] = values;
            }
        });
        // Generic dropdowns (use window stacks, not DOM)
        for (const key in window) {
            if (key.startsWith('sentinelpro_') && key.endsWith('Stack')) {
                const apiKey = key.slice('sentinelpro_'.length, -'Stack'.length);
                const checked = Array.isArray(window[key]) ? window[key] : [];
                if (checked.length > 0) {
                    filters[apiKey] = checked;
                }
            }
        }
        return filters;
    }

    // Helper: Collect dimension keys from filter headers with a count > 0
    static getDimensionsWithCount() {
        const headers = document.querySelectorAll('.sentinelpro-filter-item-header[data-api-key]');
        const keys = [];
        headers.forEach(header => {
            const countSpan = header.querySelector('.sentinelpro-filter-count');
            if (countSpan) {
                const count = parseInt(countSpan.textContent.trim(), 10);
                if (!isNaN(count) && count > 0) {
                    const apiKey = header.getAttribute('data-api-key');
                    if (apiKey && !keys.includes(apiKey)) {
                        keys.push(apiKey);
                    }
                }
            }
        });
        return keys;
    }
}
