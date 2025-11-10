// DashboardHelpers/CacheManager.js

// Build normalization map from localized canonical dimensions
const canonicalDimensions = window.SentinelProCanonicalDimensions || [];
const normalizationMap = {};
canonicalDimensions.forEach(dim => {
    normalizationMap[dim.toLowerCase()] = dim;
});

function valservNormalizeDimensionName(name) {
    return normalizationMap[name.toLowerCase()] || name;
}

function valservNormalizeRowKeys(row) {
    const newRow = {};
    for (const key in row) {
        const normKey = normalizationMap[key.toLowerCase()] || key;
        newRow[normKey] = row[key];
    }
    return newRow;
}

class ValservCacheManager {
    static debug = false;
    constructor(storageKey, ttlMs = 86400000) { // Default is 24 hours (24 * 60 * 60 * 1000)
        this.storageKey = storageKey;
        this.ttlMs = ttlMs;
        this.cache = {};
        this.load();
    }

    load() {
        const stored = localStorage.getItem(this.storageKey);
        if (!stored) return;
        try {
            const parsed = JSON.parse(stored);
            const now = Date.now();
            Object.entries(parsed).forEach(([key, entry]) => {
                // This is where 'freshness' is determined during load
                if (entry.timestamp && (now - entry.timestamp < this.ttlMs)) {
                    this.cache[key] = entry;
                } else {
        
                }
            });
        } catch (err) {
            // Failed to parse cache from localStorage
        }
    }

    save() {
        localStorage.setItem(this.storageKey, JSON.stringify(this.cache));

    }

    get(key) {
        return this.cache[key];
    }

    set(key, data, meta = {}) {
        this.cache[key] = {
            data: data, // Store whatever 'data' is provided
            timestamp: Date.now(),
            meta: meta
        };
        this.save();
    }

    clear(key) {
        delete this.cache[key];
        this.save();
    }

    clearAll() {
        this.cache = {};
        localStorage.removeItem(this.storageKey);
    }

    keys() {
        return Object.keys(this.cache);
    }

    hasFresh(key) {
        const entry = this.cache[key];
        if (!entry || !entry.timestamp) {
            return false;
        }
        const now = Date.now();
        return (now - entry.timestamp < this.ttlMs);
    }

    refresh(key) {
        if (this.cache[key]) {
            this.cache[key].timestamp = Date.now();
            this.save();
        }
    }

    /**
     * Attempts to find a superset cache entry for a given key.
     * @param {string} requestedKey - The cache key being requested.
     * @returns {{key: string, entry: object}|null} - The superset cache entry and its key, or null if not found.
     */
    findSupersetCache(requestedKey) {
        // Only works for daily keys: daily:start:end:dim:...:metric:...
        const parseKey = (key) => {
            // Example: daily:2025-06-24:2025-07-23:dim:device::metric:all
            if (!/^daily:\d{4}-\d{2}-\d{2}:/.test(key)) return null;
            const parts = key.split(":");
            if (parts.length < 7 || !parts.includes("metric")) return null;
            let obj = {};
            obj.type = parts[0];
            obj.start = parts[1];
            obj.end = parts[2];
            // Support both with and without postId
            let idx = 3;
            if (parts[idx] === "post") {
                obj.postId = parts[idx + 1];
                idx += 2;
            }
            if (parts[idx] === "dim") {
                obj.dimension = parts[idx + 1];
                idx += 2;
            }
            obj.geo = "";
            if (parts[idx] && parts[idx].startsWith("geo:")) {
                obj.geo = parts[idx].slice(4);
                idx++;
            }
            if (parts[idx] === "metric") {
                obj.metric = parts[idx + 1];
            }
            return obj;
        };
        const req = parseKey(requestedKey);
        if (!req) return null;
        const reqStart = req.start;
        const reqEnd = req.end;
        for (const [key, entry] of Object.entries(this.cache)) {
            if (key === requestedKey) continue;
            const k = parseKey(key);
            if (!k) continue;
            // Must match all non-date fields
            if (
                k.dimension === req.dimension &&
                k.metric === req.metric &&
                (k.postId || "") === (req.postId || "") &&
                (k.geo || "") === (req.geo || "")
            ) {
                // Check if k.start <= req.start && k.end >= req.end
                if (k.start <= reqStart && k.end >= reqEnd) {
                    // Also check freshness
                    if (this.hasFresh(key) && Array.isArray(entry?.data) && entry.data.length > 0) {
                        return { key, entry };
                    }
                }
            }
        }
        return null;
    }

    // --- RANGE-BASED CACHE OPTIMIZATION ---
    /**
     * Generate a range cache key for a dimension, metric, and date range.
     */
    static getRangeCacheKey(dimension, start, end, metric) {
        // Always sort dimensions for cache key consistency
        let dimStr = dimension
            ? dimension.split(',').map(d => d.trim()).filter(Boolean).sort().join(',')
            : '';
        return `range:${dimStr}:${start}:${end}:metric:${metric}`;
    }

    /**
     * Find an overlapping range cache key for the same dimension/metric.
     * Returns { key, entry, start, end } or null.
     */
    findOverlappingRangeCache(dimension, reqStart, reqEnd, metric) {
        const reqStartDate = new Date(reqStart);
        const reqEndDate = new Date(reqEnd);
        for (const [key, entry] of Object.entries(this.cache)) {
            if (!key.startsWith(`range:${dimension}:`)) continue;
            if (!key.includes(`:metric:${metric}`)) continue;
            // Parse start/end from key
            const parts = key.split(":");
            const cachedStart = parts[2];
            const cachedEnd = parts[3];
            const cachedStartDate = new Date(cachedStart);
            const cachedEndDate = new Date(cachedEnd);
            // Overlap if any intersection
            if (cachedEndDate >= reqStartDate && cachedStartDate <= reqEndDate) {
                return { key, entry, start: cachedStart, end: cachedEnd };
            }
        }
        return null;
    }

    /**
     * Find all overlapping or adjacent range cache keys for a given dimension, metric, and requested range.
     * Returns an array of { key, entry, start, end }.
     */
    findAllOverlappingOrAdjacentRanges(dimension, reqStart, reqEnd, metric) {
        const reqStartDate = new Date(reqStart);
        const reqEndDate = new Date(reqEnd);
        const results = [];
        

        
        for (const [key, entry] of Object.entries(this.cache)) {
            if (!key.startsWith(`range:${dimension}:`)) continue;
            if (!key.includes(`:metric:${metric}`)) continue;
            const parts = key.split(":");
            const cachedStart = parts[2];
            const cachedEnd = parts[3];
            const cachedStartDate = new Date(cachedStart);
            const cachedEndDate = new Date(cachedEnd);
            // Overlap or adjacent if cachedEnd >= reqStart - 1 and cachedStart <= reqEnd + 1
            if (cachedEndDate >= new Date(reqStartDate.getTime() - 86400000) &&
                cachedStartDate <= new Date(reqEndDate.getTime() + 86400000)) {
                results.push({ key, entry, start: cachedStart, end: cachedEnd });
            }
        }
        
        return results;
    }

    /**
     * Merge all overlapping/adjacent ranges and new data into a single expanded range, delete old keys, and store merged data.
     * Returns { mergedData, newStart, newEnd, newKey }
     */
    mergeAndReplaceRanges(dimension, reqStart, reqEnd, metric, newData) {
        const overlaps = this.findAllOverlappingOrAdjacentRanges(dimension, reqStart, reqEnd, metric);
        
        // If we have new data but no overlapping ranges, just store the new data directly
        if (newData && newData.length > 0 && overlaps.length === 0) {
            const newKey = this.constructor.getRangeCacheKey(dimension, reqStart, reqEnd, metric);
            this.set(newKey, newData);
            return { mergedData: newData, newStart: reqStart, newEnd: reqEnd, newKey };
        }
        
        let allData = [...(newData || [])];
        let minStart = reqStart, maxEnd = reqEnd;
        for (const { key, entry, start, end } of overlaps) {
            allData = allData.concat(entry.data || []);
            if (start < minStart) minStart = start;
            if (end > maxEnd) maxEnd = end;
        }
        // Debug: Log before deduplication
        // Deduplicate and sort
        const map = new Map();
        allData.forEach(row => {
            if (!row || !row.date) return;
            row = valservNormalizeRowKeys(row); // normalize keys
            // Dynamically deduplicate by date and all dimension fields (except metrics and known non-dimension fields)
            const dimensionFields = Object.keys(row)
                .filter(key => key !== 'sessions' && key !== 'visits' && key !== 'views' && key !== 'bounce_rate' && key !== 'date' && key !== 'value' && key !== 'metric' && key !== 'count')
                .map(valservNormalizeDimensionName);
            const k = [
                row.date,
                ...dimensionFields.map(dim => row[dim] !== undefined ? row[dim] : '')
            ].join('|');
            map.set(k, row);
        });
        const mergedData = Array.from(map.values()).sort((a, b) => a.date.localeCompare(b.date));
        // Delete old keys
        overlaps.forEach(({ key }) => {
            this.clear(key);
        });
        // Store new expanded range
        const newKey = this.constructor.getRangeCacheKey(dimension, minStart, maxEnd, metric);
        this.set(newKey, mergedData);
        // Also delete all overlapping daily:... cache keys for this range
        this.clearDailyChunksForRange && this.clearDailyChunksForRange(dimension, minStart, maxEnd, metric);
        return { mergedData, newStart: minStart, newEnd: maxEnd, newKey };
    }

    /**
     * Merge and expand cached data with new data, deduplicating by date.
     * Returns { mergedData, newStart, newEnd }
     */
    static mergeAndExpandRangeCache(existingData, existingStart, existingEnd, newData, newStart, newEnd) {
        // Combine arrays, deduplicate by date (and device if present), and sort
        const map = new Map();
        [...(existingData || []), ...(newData || [])].forEach(row => {
            if (!row || !row.date) return;
            row = valservNormalizeRowKeys(row); // normalize keys
            // Dynamically deduplicate by date and all dimension fields (except metrics and known non-dimension fields)
            const dimensionFields = Object.keys(row)
                .filter(key => key !== 'sessions' && key !== 'visits' && key !== 'views' && key !== 'bounce_rate' && key !== 'date' && key !== 'value' && key !== 'metric' && key !== 'count')
                .map(valservNormalizeDimensionName);
            const key = [
                row.date,
                ...dimensionFields.map(dim => row[dim] !== undefined ? row[dim] : '')
            ].join('|');
            map.set(key, row);
        });
        const mergedData = Array.from(map.values()).sort((a, b) => a.date.localeCompare(b.date));
        // New range is min(start), max(end)
        const allDates = mergedData.map(row => row.date);
        const allStart = allDates.length ? allDates[0] : newStart;
        const allEnd = allDates.length ? allDates[allDates.length - 1] : newEnd;
        return { mergedData, newStart: allStart, newEnd: allEnd };
    }

    /**
     * Delete all daily:... cache keys for a given dimension, metric, and date range.
     */
    clearDailyChunksForRange(dimension, start, end, metric) {
        const keysToDelete = [];
        for (const key of Object.keys(this.cache)) {
            if (key.startsWith('daily:') && key.includes(`dim:${dimension}`) && key.includes(`metric:${metric}`)) {
                // Parse date from key
                const parts = key.split(':');
                const chunkStart = parts[1];
                const chunkEnd = parts[2];
                if (chunkEnd >= start && chunkStart <= end) {
                    keysToDelete.push(key);
                }
            }
        }
        keysToDelete.forEach(key => this.clear(key));
    }    
}

export default ValservCacheManager;