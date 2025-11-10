// DashboardHelpers/DataUtils.js

export function filterValidRows(data, isDaily, start, end, requestedDates) {
    return data.filter(row => {
        if (!row || typeof row.date !== 'string') {
            return false;
        }

        const dateParts = row.date.split(' ');
        const rowDateStr = dateParts[0];

        if (!rowDateStr) {
            return false;
        }

        return isDaily
            ? (rowDateStr >= start && rowDateStr <= end)
            : requestedDates.includes(rowDateStr);
    });
}

export function normalizeRowKeys(row) {
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

// Converts geo array to 2-letter codes
export function toAlpha2Geo(geoArr) {
    // COUNTRY_ALPHA3_TO_2 must be defined in the scope where this is used
    return geoArr.map(code => {
        if (code.length === 2) return code.toUpperCase();
        if (typeof COUNTRY_ALPHA3_TO_2 !== 'undefined' && COUNTRY_ALPHA3_TO_2[code.toUpperCase()]) return COUNTRY_ALPHA3_TO_2[code.toUpperCase()];
        return code;
    });
}

// Formats a date to YYYY-MM-DD
export function formatDate(date) {
    if (typeof date === 'string') return date;
    return date.toISOString().split('T')[0];
}

// Formats a number with commas
export function formatNumber(num) {
    return num.toLocaleString();
}

// Generates an array of YYYY-MM-DD strings from start to end (inclusive)
export function generateDateRange(start, end) {
    const dates = [];
    
    // Validate input dates
    if (!start || !end) {
        return dates;
    }
    
    let current = new Date(start);
    const endDate = new Date(end);
    
    // Check if dates are valid
    if (isNaN(current.getTime()) || isNaN(endDate.getTime())) {
        return dates;
    }
    
    while (current <= endDate) {
        dates.push(current.toISOString().split('T')[0]);
        current.setDate(current.getDate() + 1);
    }
    return dates;
}
