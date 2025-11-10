export default class DataFetcher {
  constructor(cacheManager, ajaxUrl) {
    this.cache = cacheManager;
    this.ajaxUrl = ajaxUrl;
  }

  async fetchData({ metric = 'traffic', granularity, startDate, endDate, filters, postId = null }) {

    const isHourly = granularity === 'hourly';
    return isHourly
        ? this._fetchStandardHourly(startDate, endDate, filters, postId, metric)
        : this._fetchStandardDaily(metric, startDate, endDate, granularity, postId, filters); // ✅ added filters

  }

  // ─────────────────────────────────────────────────────
  async _fetchStandardDaily(metric, startDate, endDate, granularity, postId, filters = null) {
    // Range-based cache optimization
    let dimensions = (filters?.get('dimensions') || '').split(',').map(d => d.trim()).filter(Boolean);
    dimensions = dimensions.map(valservNormalizeDimensionName);
    dimensions = dimensions.filter(dim => canonicalDimensions.includes(dim)); // Only keep canonical
    dimensions = [...new Set(dimensions)]; // deduplicate after normalization
    const dimensionKey = dimensions.join(',');
    const rangeCacheKey = this.cache.constructor.getRangeCacheKey(dimensionKey, startDate, endDate, metric);
    
    // 1. Check for overlapping range cache
    const overlap = this.cache.findOverlappingRangeCache(dimensionKey, startDate, endDate, metric);
    
    if (overlap && Array.isArray(overlap.entry.data)) {
      // 2. Determine missing sub-ranges
      const cachedStart = overlap.start;
      const cachedEnd = overlap.end;
      let missingRanges = [];
      if (startDate < cachedStart) {
        missingRanges.push({ start: startDate, end: this._prevDate(cachedStart) });
      }
      if (endDate > cachedEnd) {
        missingRanges.push({ start: this._nextDate(cachedEnd), end: endDate });
      }
      
      let newData = [];
      for (const range of missingRanges) {
        if (range.start > range.end) continue;
        // Break range into 10-day chunks
        let cur = new Date(range.start);
        const end = new Date(range.end);
        while (cur <= end) {
          const chunkStart = cur.toISOString().split('T')[0];
          const chunkEndDate = new Date(cur);
          chunkEndDate.setDate(chunkEndDate.getDate() + 9);
          if (chunkEndDate > end) chunkEndDate.setTime(end.getTime());
          const chunkEnd = chunkEndDate.toISOString().split('T')[0];
          // Filter the fetched data to only include the exact chunk range we want
          const fetched = await this._fetchStandardDailyChunk(metric, chunkStart, chunkEnd, granularity, postId, filters);
          if (fetched && Array.isArray(fetched.data)) {
                      // Filter to only include data within our exact chunk range
          const filteredData = fetched.data.filter(row => {
            const rowDate = row.date;
            return rowDate >= chunkStart && rowDate <= chunkEnd;
          });
          newData = newData.concat(filteredData);
          } 
          // Move to the day AFTER the chunk ends to ensure no gaps
          cur = new Date(chunkEndDate);
          cur.setDate(cur.getDate() + 1);
          // Throttle to 1 request per second
          await new Promise(res => setTimeout(res, 1000));
        }
      }
      // 3. Merge and expand all overlapping/adjacent range caches
      
      const { mergedData, newStart, newEnd, newKey } = this.cache.mergeAndReplaceRanges(
        dimensionKey,
        startDate,
        endDate,
        metric,
        newData
      );
      
      return { data: mergedData, requestedDates: [newStart, newEnd] };
    } else {
      // No overlap: fetch full range, cache as new key (and merge with any adjacent ranges)
      let newData = [];
      // Break full range into 10-day chunks
      let cur = new Date(startDate);
      const end = new Date(endDate);
      while (cur <= end) {
        const chunkStart = cur.toISOString().split('T')[0];
        const chunkEndDate = new Date(cur);
        chunkEndDate.setDate(chunkEndDate.getDate() + 9);
        if (chunkEndDate > end) chunkEndDate.setTime(end.getTime());
        const chunkEnd = chunkEndDate.toISOString().split('T')[0];
        // Filter the fetched data to only include the exact chunk range we want
        const fetched = await this._fetchStandardDailyChunk(metric, chunkStart, chunkEnd, granularity, postId, filters);
        if (fetched && Array.isArray(fetched.data)) {
          // Filter to only include data within our exact chunk range
          const filteredData = fetched.data.filter(row => {
            const rowDate = row.date;
            return rowDate >= chunkStart && rowDate <= chunkEnd;
          });
          newData = newData.concat(filteredData);
        }
        // Move to the day AFTER the chunk ends to ensure no gaps
        cur = new Date(chunkEndDate);
        cur.setDate(cur.getDate() + 1);
        // Throttle to 1 request per second
        await new Promise(res => setTimeout(res, 1000));
      }
            
      // 3. Merge and expand all overlapping/adjacent range caches
      const { mergedData, newStart, newEnd, newKey } = this.cache.mergeAndReplaceRanges(
        dimensionKey,
        startDate,
        endDate,
        metric,
        newData
      );
      return { data: mergedData, requestedDates: [newStart, newEnd] };
    }
  }

  // Helper to get previous date string
  _prevDate(dateStr) {
    const d = new Date(dateStr);
    d.setDate(d.getDate() - 1);
    return d.toISOString().split('T')[0];
  }
  // Helper to get next date string
  _nextDate(dateStr) {
    const d = new Date(dateStr);
    d.setDate(d.getDate() + 1);
    return d.toISOString().split('T')[0];
  }

  // Helper for a single chunk fetch (original logic)
  async _fetchStandardDailyChunk(metric, startDate, endDate, granularity, postId, filters = null) {
    let dimensions = (filters?.get('dimensions') || '').split(',').map(d => d.trim()).filter(Boolean);
    dimensions = dimensions.map(valservNormalizeDimensionName);
    dimensions = dimensions.filter(dim => canonicalDimensions.includes(dim)); // Only keep canonical
    dimensions = [...new Set(dimensions)]; // deduplicate after normalization
    let dimensionKey = dimensions.join(',');
    // --- GEO cache key enhancement ---
    // Only include geo in the cache key if the API request will actually use a geo filter
    // (i.e., if geo is present in filters AND will be sent to the API)
    let geoKey = '';
    const geoFilterUsed = filters && filters.has('geo') && filters.get('geo').trim() !== '';
    // We always remove geo from the API request, so geoKey should always be blank
    // If you ever change to allow geo-specific API requests, set geoKey accordingly
    // For now, always use the general cache key (no geo)
    const cacheKey = postId
      ? `daily:${startDate}:${endDate}:post:${postId}:dim:${dimensionKey}:metric:${metric}`
      : `daily:${startDate}:${endDate}:dim:${dimensionKey}:metric:${metric}`;
    


    const cached = this.cache.get(cacheKey);
    if (this.cache.hasFresh(cacheKey) && Array.isArray(cached?.data)) {
        return { data: cached.data, requestedDates: [startDate, endDate] };
    }

    // --- Superset cache logic ---
    const superset = this.cache.findSupersetCache(cacheKey);
    if (superset && Array.isArray(superset.entry.data)) {
        let filtered = superset.entry.data;
        // Filter by geo if requested
        if (filters && filters.has('geo')) {
            const geoVal = filters.get('geo');
            const geoArr = geoVal.split(',').map(g => g.trim().toUpperCase()).filter(Boolean);
            filtered = filtered.filter(row => geoArr.includes((row.geo || '').toUpperCase()));
        }
        // Filter by date range
        filtered = filtered.filter(row => row.date >= startDate && row.date <= endDate);
        return { data: filtered, requestedDates: [startDate, endDate] };
    }

    // Make dates inclusive for API (subtract one from start, add one to end)
    let apiStartDate = new Date(startDate);
    apiStartDate.setDate(apiStartDate.getDate() - 1);
    const apiStartDateStr = apiStartDate.toISOString().split('T')[0];
    
    let apiEndDate = new Date(endDate);
    apiEndDate.setDate(apiEndDate.getDate() + 1);
    const apiEndDateStr = apiEndDate.toISOString().split('T')[0];

    // Clone filters and remove geo for API request
    const params = filters instanceof URLSearchParams ? new URLSearchParams(filters) : new URLSearchParams();
    params.delete('geo'); // Remove geo filter for API request
    params.set('action', 'valserv_fetch_data');
    params.set('start_date', apiStartDateStr);
    params.set('end_date', apiEndDateStr);
    params.set('granularity', granularity);
    params.set('metric', metric);
    if (postId) params.set('post_id', postId);

    // Revert: Send dimensions as comma-separated string
    params.set('dimensions', dimensionKey);

    let attempt = 0;
    while (attempt < 3) {
      try {

        const res = await fetch(`${this.ajaxUrl}?${params.toString()}`);
        const status = res.status;
        const text = await res.text();
        let data = null;
        let parsed = false;
        try {
            data = JSON.parse(text);
            parsed = true;
        } catch (jsonErr) {
            // If not valid JSON, treat as generic 500
            if (window.EnhancedDashboardInstance && typeof window.EnhancedDashboardInstance.setClearanceLevel === 'function') {
                window.EnhancedDashboardInstance.setClearanceLevel('restricted');
            }
            if (window.EnhancedDashboardInstance && typeof window.EnhancedDashboardInstance.status === 'object' && window.EnhancedDashboardInstance.status.show) {
                window.EnhancedDashboardInstance.status.show('Server error: Invalid response from backend.', true);
            }
            throw new Error('Invalid JSON from backend');
        }
        // Robust clearance logic: check JSON body for 403 even if HTTP status is 500
        if (parsed && data && data.status === 403 && data.message && data.message.match(/access is restricted/i)) {
            if (window.EnhancedDashboardInstance && typeof window.EnhancedDashboardInstance.setClearanceLevel === 'function') {
                window.EnhancedDashboardInstance.setClearanceLevel('elevated');
            }
        } else if (parsed && status >= 200 && status < 300 && data && data.success !== false) {
            if (window.EnhancedDashboardInstance && typeof window.EnhancedDashboardInstance.setClearanceLevel === 'function') {
                window.EnhancedDashboardInstance.setClearanceLevel('admin');
            }
        } else if (parsed && data.success === false) {
            if (data.data?.message && data.data.message.match(/credentials|account name missing/i)) {
                if (window.EnhancedDashboardInstance && typeof window.EnhancedDashboardInstance.setClearanceLevel === 'function') {
                    window.EnhancedDashboardInstance.setClearanceLevel('restricted');
                }
            }
            if (data.status === 403 && data.message && data.message.match(/access is restricted/i)) {
                if (window.EnhancedDashboardInstance && typeof window.EnhancedDashboardInstance.setClearanceLevel === 'function') {
                    window.EnhancedDashboardInstance.setClearanceLevel('elevated');
                }
            }
        } else if (!parsed || status === 500) {
            // Only treat as generic 500 if not parsed or no specific error
            if (window.EnhancedDashboardInstance && typeof window.EnhancedDashboardInstance.setClearanceLevel === 'function') {
                window.EnhancedDashboardInstance.setClearanceLevel('restricted');
            }
        }
        
        const dataArray = Array.isArray(data?.data) ? data.data.map(row => this._normalizeKeys(row)) : [];
        

        
        this.cache.set(cacheKey, dataArray);
        return { data: dataArray, requestedDates: [startDate, endDate] };
      } catch (err) {
        if (attempt >= 2) {
          this.cache.set(cacheKey, []);
          // Return sample data for development/demo purposes
        }
        await new Promise(resolve => setTimeout(resolve, 3000));
        attempt++;
      }
    }
    // If all retries fail, return sample data
    this.cache.set(cacheKey, []);
  }



  // ─────────────────────────────────────────────────────
  async _fetchStandardHourly(startDate, endDate, filters, postId, metric = 'traffic') {
    const combinedData = [];
    const requestedDates = [];
    const datesToFetch = [startDate];
    if (endDate && endDate !== startDate) datesToFetch.push(endDate);

    for (const date of datesToFetch) {
      requestedDates.push(date);

      const trafficKey = `hourly:${date}:traffic`;
      const trafficCached = this.cache.get(trafficKey);
      if (this.cache.hasFresh(trafficKey) && Array.isArray(trafficCached?.data)) {
        combinedData.push(...trafficCached.data);
      } else {
        const traffic = await this._fetchHourly(date, filters, postId, metric);
        this.cache.set(trafficKey, traffic);
        combinedData.push(...traffic);
      }
    }

    return { data: combinedData, requestedDates };
  }

  async _fetchHourly(dateStr, filters, postId = null, metric = 'traffic') {
    const params = new URLSearchParams(filters); // clone safely

    params.set('action', 'valserv_fetch_data');
    params.delete('metric');
    params.set('date1', dateStr);
    params.delete('date2');
    params.set('metric', metric); // ✅ use actual value
    if (postId) params.set('post_id', postId);

    const res = await fetch(`${this.ajaxUrl}?${params.toString()}`);
    const json = await res.json();
    return json?.data || [];
  }

  _getMissingDates(start, end) {
    const result = [];
    const cur = new Date(start);
    const last = new Date(end);

    while (cur <= last) {
      const dateStr = cur.toISOString().split('T')[0];
      if (!this.cache.hasFresh(`daily:${dateStr}`)) {
        result.push(dateStr);
      }
      cur.setDate(cur.getDate() + 1);
    }

    return result;
  }

  async fetchDimensionData(dimensionKey, filters, postId = null) {
    const cacheKey = `dimension:${dimensionKey}:${filters.toString()}`;
    if (this.cache.hasFresh(cacheKey)) {
        return this.cache.get(cacheKey)?.data || [];
    }

    const params = new URLSearchParams(filters);
    params.set('action', 'sentinelpro_fetch_dimension_data');
    params.set('dimension', dimensionKey);
    if (postId) params.set('post_id', postId);

    const res = await fetch(`${this.ajaxUrl}?${params.toString()}`);
    const json = await res.json();
    const data = Array.isArray(json?.data) ? json.data.map(row => this._normalizeKeys(row)) : [];

    this.cache.set(cacheKey, data);
    return data;
  }

  /**
   * Generalized method to fetch dimension data for any dimension type
   * @param {string} dimensionKey - The dimension key (e.g., 'device', 'geo', 'browser', etc.)
   * @param {Object} options - Options for fetching
   * @param {URLSearchParams} options.filters - Current filters
   * @param {string} options.startDate - Start date
   * @param {string} options.endDate - End date
   * @param {string} options.postId - Post ID (optional)
   * @param {string} options.metric - Metric to fetch (default: 'sessions')
   * @returns {Promise<Array>} Array of dimension data
   */
  async fetchGeneralizedDimensionData(dimensionKey, options = {}) {
    const {
      filters = new URLSearchParams(),
      startDate,
      endDate,
      postId = null,
      metric = 'sessions'
    } = options;

    // Create cache key based on all parameters
    const cacheParams = new URLSearchParams();
    cacheParams.set('dimension', dimensionKey);
    cacheParams.set('metric', metric);
    if (startDate) cacheParams.set('startDate', startDate);
    if (endDate) cacheParams.set('endDate', endDate);
    if (postId) cacheParams.set('postId', postId);
    
    // Add filters to cache key
    for (const [key, value] of filters) {
      if (value && value !== '') {
        cacheParams.set(key, value);
      }
    }

    const cacheKey = `generalized_dimension:${dimensionKey}:${cacheParams.toString()}`;
    
    if (this.cache.hasFresh(cacheKey)) {
      return this.cache.get(cacheKey)?.data || [];
    }

    try {
      const params = new URLSearchParams();
      params.set('action', 'sentinelpro_fetch_dimension_data');
      params.set('dimension', dimensionKey);
      params.set('metric', metric);
      
      if (startDate) params.set('start_date', startDate);
      if (endDate) params.set('end_date', endDate);
      if (postId) params.set('post_id', postId);

      // Add filters
      for (const [key, value] of filters) {
        if (value && value !== '' && key !== dimensionKey) { // Don't include the dimension being fetched
          params.set(key, value);
        }
      }

      const res = await fetch(`${this.ajaxUrl}?${params.toString()}`);
      const json = await res.json();
      
      if (json.success) {
        const data = Array.isArray(json.data) ? json.data.map(row => this._normalizeKeys(row)) : [];
        this.cache.set(cacheKey, data);
        return data;
      } else {
        return [];
      }
    } catch (error) {
      return [];
    }
  }

  /**
   * Get unique values for a dimension with counts
   * @param {string} dimensionKey - The dimension key
   * @param {Object} options - Options for fetching
   * @returns {Promise<Array>} Array of {value, count} objects
   */
  async getDimensionValues(dimensionKey, options = {}) {
    const data = await this.fetchGeneralizedDimensionData(dimensionKey, options);
    
    // Group by dimension value and count
    const valueCounts = {};
    data.forEach(row => {
      const value = row[dimensionKey] || row.dimension_value || row.value;
      if (value) {
        const normalizedValue = String(value).trim();
        if (normalizedValue) {
          valueCounts[normalizedValue] = (valueCounts[normalizedValue] || 0) + 1;
        }
      }
    });

    // Convert to array and sort by count (descending)
    return Object.entries(valueCounts)
      .map(([value, count]) => ({ value, count }))
      .sort((a, b) => b.count - a.count);
  }

  _normalizeKeys(row) {
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
}

// Utility to update access meta when clearance is set to restricted
async function setAccessRestricted() {
    await fetch(window.valservDashboardData?.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'sentinelpro_set_access_restricted',
            nonce: window.valservDashboardData?.nonce
        })
    });
}
// Patch EnhancedDashboardInstance.setClearanceLevel to sync access meta
if (typeof window !== 'undefined' && window.EnhancedDashboardInstance) {
    const origSetClearanceLevel = window.EnhancedDashboardInstance.setClearanceLevel;
    window.EnhancedDashboardInstance.setClearanceLevel = async function(level) {
        if (origSetClearanceLevel) await origSetClearanceLevel.call(this, level);
        if (level === 'restricted') {
            await setAccessRestricted();
        }
    };
}

// Use the same normalization map as in CacheManager.js
const canonicalDimensions = window.SentinelProCanonicalDimensions || [];
const normalizationMap = {};
canonicalDimensions.forEach(dim => {
    normalizationMap[dim.toLowerCase()] = dim;
});

function valservNormalizeDimensionName(name) {
    return normalizationMap[name.toLowerCase()] || name;
}
