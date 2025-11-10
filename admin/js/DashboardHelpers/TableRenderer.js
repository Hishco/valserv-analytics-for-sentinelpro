// DashboardHelpers/TableRenderer.js

export default class TableRenderer {
    constructor(tableElement, paginationContainer) {
        this.table = tableElement;
        this.paginationContainer = paginationContainer;
        this.sortKey = 'sessions'; // Default sort by sessions for geo
        this.sortAsc = false; // Default descending for sessions
        this.page = 1;
        this.rowsPerPage = 100;
        this.activeDimension = null;
        this._lastArgs = null; // Store last render args
    }

    render(args) {
        this._lastArgs = args; // Store for re-rendering
        const { data, metric, granularity, start, end, requestedDates, comparisonData = [], comparisonDates = [] } = args;
        const isDaily = granularity === 'daily';
        const initialMetricSet = this._getMetricSet(metric);

        // Safety check: ensure table element exists
        if (!this.table) {
            return;
        }

        if (initialMetricSet.length === 0) {
            this.table.innerHTML = `<thead><tr><th>No metrics selected</th></tr></thead><tbody><tr><td colspan="100%">Please select at least one metric to display in the table.</td></tr></tbody>`;
            this._renderPagination(0);
            return;
        }

        // Always use _renderDaily for table rendering
        const filtered = this._filterData(data, isDaily, start, end, requestedDates);
        const safeMain = filtered.filter(row => row && typeof row.date === 'string');
        const safeCompare = comparisonData.filter(row => row && typeof row.date === 'string');

        if (isDaily) {
            this._renderDaily(safeMain, safeCompare, initialMetricSet);
        } else {
            this._renderHourly({
                data: safeMain,
                metric,
                granularity,
                start,
                end,
                requestedDates,
                comparisonData: safeCompare,
                comparisonDates
            });
        }
    }

    _detectActiveCustomDimension(data) {
        if (!Array.isArray(data) || !data.length) return null;

        const knownDims = Array.from(document.querySelectorAll('#custom-dimension-sidebar .dimension-filter'))
            .map(cb => cb.value.toLowerCase());

        const rowKeys = Object.keys(data[0]).map(k => k.toLowerCase());

        return knownDims.find(dim => rowKeys.includes(dim)) || null;
    }




    _getMetricSet(metric) {
        if (metric === 'traffic') {
            return Array.from(document.querySelectorAll('.metric-traffic:checked')).map(i => i.value);
        } else if (metric === 'engagement') {
            return Array.from(document.querySelectorAll('.metric-avg:checked')).map(i => i.value);
        } else {
            return [metric];
        }
    }

    _filterData(data, isDaily, start, end, requestedDates) {
        return data.filter(row => {
            if (!row || typeof row.date !== 'string') return false;
            const [rowDate] = row.date.split(' ');
            if (!rowDate) return false;
            return isDaily
                ? (rowDate >= start && rowDate <= end)
                : requestedDates.includes(rowDate);
        });
    }

    _renderDaily(mainData, comparisonData = [], metrics) {
        const cleanedMainData = mainData.filter(row => row && typeof row.date === 'string');
        const formatter = new Intl.NumberFormat();
        const allMetrics = ['sessions', 'visits', 'views'];

        // Calculate totals
        const totals = {
            sessions: cleanedMainData.reduce((sum, row) => sum + (parseFloat(row.sessions) || 0), 0),
            visits: cleanedMainData.reduce((sum, row) => sum + (parseFloat(row.visits) || 0), 0),
            views: cleanedMainData.reduce((sum, row) => sum + (parseFloat(row.views) || 0), 0)
        };
        // Calculate comparison totals if present
        const compareTotals = comparisonData && comparisonData.length > 0 ? {
            sessions: comparisonData.reduce((sum, row) => sum + (parseFloat(row.sessions) || 0), 0),
            visits: comparisonData.reduce((sum, row) => sum + (parseFloat(row.visits) || 0), 0),
            views: comparisonData.reduce((sum, row) => sum + (parseFloat(row.views) || 0), 0)
        } : null;

        function renderTotalCell(mainVal, compareVal) {
            if (compareVal === undefined || compareVal === null) {
                return `<td style="text-align:center;">${formatter.format(mainVal)}</td>`;
            }
            const diff = mainVal - compareVal;
            const percent = compareVal === 0 ? 0 : ((diff / Math.abs(compareVal)) * 100);
            let arrow = '';
            let color = '';
            if (percent > 0.01) {
                arrow = '↑';
                color = 'green';
            } else if (percent < -0.01) {
                arrow = '↓';
                color = 'red';
            } else {
                arrow = '';
                color = 'gray';
            }
            const percentStr = percent === 0 ? '0.0%' : `${arrow} ${Math.abs(percent).toFixed(1)}%`;
            return `<td style="text-align:center;">${formatter.format(mainVal)} – ${formatter.format(compareVal)} <span style="color:${color};font-weight:bold;">${percentStr}</span></td>`;
        }

        // Total row
        const totalRow = `<tr class="total-row">
            <td>Total</td>
            ${renderTotalCell(totals.sessions, compareTotals ? compareTotals.sessions : null)}
            ${renderTotalCell(totals.visits, compareTotals ? compareTotals.visits : null)}
            ${renderTotalCell(totals.views, compareTotals ? compareTotals.views : null)}
        </tr>`;

        // Data rows (with sorting, ignoring Total row)
        let sortableRows = cleanedMainData.slice();
        if (this.sortKey) {
            const key = this.sortKey;
            const isDate = key === 'date';
            sortableRows.sort((a, b) => {
                if (isDate) {
                    // Sort by date
                    return this.sortAsc
                        ? new Date(a.date) - new Date(b.date)
                        : new Date(b.date) - new Date(a.date);
                } else {
                    // Sort by metric
                    const aVal = parseFloat(a[key]) || 0;
                    const bVal = parseFloat(b[key]) || 0;
                    return this.sortAsc ? aVal - bVal : bVal - aVal;
                }
            });
        }
        const rows = sortableRows.map((row, i) => {
            // Fix date parsing to prevent timezone shifts
            const [year, month, day] = row.date.split('-').map(Number);
            const dateObj = new Date(year, month - 1, day); // month is 0-indexed
            const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'short' });
            const formattedDate = dateObj.toLocaleDateString('en-US', { year: 'numeric', month: '2-digit', day: '2-digit' });
            
            // Find matching comparison row by index (index-aligned)
            const compareRow = Array.isArray(comparisonData) && comparisonData.length > i ? comparisonData[i] : null;
            function renderCell(mainVal, compareVal) {
                if (compareVal === undefined || compareVal === null) {
                    return `<td style="text-align:center;">${formatter.format(mainVal)}</td>`;
                }
                const diff = mainVal - compareVal;
                const percent = compareVal === 0 ? 0 : ((diff / Math.abs(compareVal)) * 100);
                let arrow = '';
                let color = '';
                if (percent > 0.01) {
                    arrow = '↑';
                    color = 'green';
                } else if (percent < -0.01) {
                    arrow = '↓';
                    color = 'red';
                } else {
                    arrow = '';
                    color = 'gray';
                }
                const percentStr = percent === 0 ? '0.0%' : `${arrow} ${Math.abs(percent).toFixed(1)}%`;
                return `<td style="text-align:center;">${formatter.format(mainVal)} – ${formatter.format(compareVal)} <span style="color:${color};font-weight:bold;">${percentStr}</span></td>`;
            }
            return `<tr>
                <td style="text-align:left;">${dayName}, ${formattedDate}</td>
                ${renderCell(row.sessions, compareRow ? compareRow.sessions : null)}
                ${renderCell(row.visits, compareRow ? compareRow.visits : null)}
                ${renderCell(row.views, compareRow ? compareRow.views : null)}
            </tr>`;
        });

        // Table header
        const header = `
            <thead>
                <tr>
                    <th style="text-align:left;" class="sortable" data-key="date">Dimension</th>
                    <th style="text-align:center;" class="sortable" data-key="sessions">Sessions ${this._sortIcon('sessions')}</th>
                    <th style="text-align:center;" class="sortable" data-key="visits">Visits ${this._sortIcon('visits')}</th>
                    <th style="text-align:center;" class="sortable" data-key="views">Views ${this._sortIcon('views')}</th>
                </tr>
            </thead>
        `;
        const body = `<tbody>${totalRow}${rows.join('')}</tbody>`;
        this.table.innerHTML = header + body;
        this._attachSortHandlers();
        const totalPages = Math.ceil(rows.length / this.rowsPerPage);
        this._renderPagination(totalPages);
    }

    _renderHourly({
        data,
        metric,
        granularity,
        start,
        end,
        requestedDates,
        comparisonData = [],
        comparisonDates = []
    }) {
        const hours = Array.from({ length: 24 }, (_, i) => `${i.toString().padStart(2, '0')}:00`);

        const makeHourMap = (rows, dates) => {
            const map = {};
            dates.forEach(date => {
                map[date] = {};
                hours.forEach(hour => {
                    map[date][hour] = {};
                });
            });

            rows.forEach(row => {
                if (!row?.date?.includes(' ')) return;
                const [date, time] = row.date.split(' ');
                const hour = time.slice(0, 2) + ':00';
                if (!map[date] || !map[date][hour]) return;

                Object.entries(row).forEach(([key, value]) => {
                    if (key === 'date') return;
                    const normalizedKey = this._normalizeMetricKey(key);
                    const val = parseFloat(value);
                    if (!isNaN(val)) {
                        map[date][hour][normalizedKey] = (map[date][hour][normalizedKey] || 0) + val;
                    }
                });
            });

            return map;
        };

        const mainHourMap = makeHourMap(data, requestedDates);
        const compareHourMap = makeHourMap(comparisonData, comparisonDates);

        const activeMetrics = this._getMetricSet(metric);

        const allDates = [...requestedDates, ...comparisonDates];

        const headerCols = allDates.map(date =>
            activeMetrics.map(m => {
                const normalizedKey = this._normalizeMetricKey(m);
                const label = this._label(m);
                return `<th class="sortable" data-key="${date}|${normalizedKey}">${date}<br/>(${label}) ${this._sortIcon(`${date}|${normalizedKey}`)}</th>`;
            }).join('')
        ).join('');

        const sortedHours = [...hours].sort((a, b) => {
            if (!this.sortKey || this.sortKey === 'hour') {
                return this.sortAsc ? a.localeCompare(b) : b.localeCompare(a);
            }

            const [dateKey, metricKeyRaw] = (this.sortKey || '').includes('|') ? this.sortKey.split('|') : [null, null];
            const metricKey = this._normalizeMetricKey(metricKeyRaw);
            const aVal = mainHourMap[dateKey]?.[a]?.[metricKey] ?? compareHourMap[dateKey]?.[a]?.[metricKey] ?? 0;
            const bVal = mainHourMap[dateKey]?.[b]?.[metricKey] ?? compareHourMap[dateKey]?.[b]?.[metricKey] ?? 0;
            return this.sortAsc ? aVal - bVal : bVal - aVal;
        });

        const bodyRows = sortedHours.map(hour => {
            const row = [`<td>${hour}</td>`];
            let hasData = false;

            allDates.forEach(date => {
                activeMetrics.forEach(m => {
                    const key = this._normalizeMetricKey(m);
                    const val = mainHourMap[date]?.[hour]?.[key] ?? compareHourMap[date]?.[hour]?.[key] ?? 0;
                    if (val > 0) hasData = true;
                    row.push(`<td>${val.toLocaleString()}</td>`);
                });
            });

            return hasData ? `<tr>${row.join('')}</tr>` : '';
        }).filter(Boolean).join('');

        this.table.innerHTML = `
            <thead>
                <tr>
                    <th style="text-align:left;" class="sortable" data-key="hour">Hour ${this._sortIcon('hour')}</th>
                    ${headerCols}
                </tr>
            </thead>
            <tbody>
                ${bodyRows || `<tr><td colspan="${1 + activeMetrics.length * allDates.length}">No hourly data available for the selected filters.</td></tr>`}
            </tbody>
        `;

        this._attachSortHandlers();
        this._renderPagination(0);
    }

    _label(metric) {
        const map = {
            pagespersession: 'Pages / Session',
            averageengagedduration: 'Avg. Engaged Duration (sec)',
            averageengageddepth: 'Avg. Engaged Depth (%)',
            averageconnectionspeed: 'Avg. Connection Speed (mb/s)',
            views: 'Views',
            visits: 'Visits',
            sessions: 'Sessions',
            geo: 'Country',
            os: 'OS',
            browser: 'Browser',
            device: 'Device',
            referrer: 'Referrer',
    
            intent: 'Intent',
            contenttype: 'Content Type',
            primarytag: 'Primary Tag',
            primarycategory: 'Primary Category',
            networkcategory: 'Network Category',
            utmcampaign: 'UTM Campaign',
            utmmedium: 'UTM Medium',
            utmsource: 'UTM Source'
        };
        return map[metric.toLowerCase()] || metric;
    }


    _normalizeMetricKey(key) {
        if (typeof key !== 'string') return '';
        const map = {
            pagespersession: 'pagespersession',
            averageengagedduration: 'averageengagedduration',
            averageengageddepth: 'averageengageddepth',
            averageconnectionspeed: 'averageconnectionspeed'
        };
        return map[key.toLowerCase()] || key.toLowerCase();
    }

    _attachSortHandlers() {
        this.table.querySelectorAll('th.sortable').forEach(header => {
            header.onclick = () => {
                const key = header.dataset.key;
                if (this.sortKey === key) {
                    this.sortAsc = !this.sortAsc;
                } else {
                    this.sortKey = key;
                    this.sortAsc = key === 'sessions' ? false : true;
                }
                // Re-render using last args
                if (this._lastArgs) this.render(this._lastArgs);
            };
        });
    }

    _renderPagination(totalPages) {
        if (!this.paginationContainer) return;
        this.paginationContainer.innerHTML = '';
        for (let i = 1; i <= totalPages; i++) {
            const btn = document.createElement('button');
            btn.textContent = i;
            btn.className = 'pagination-btn';
            btn.style.cssText = `
                margin: 0 5px; padding: 6px 12px;
                border: 1px solid #ccc;
                border-radius: 4px;
                background: ${i === this.page ? '#0073aa' : '#f1f1f1'};
                color: ${i === this.page ? '#fff' : '#000'};
            `;
            btn.onclick = () => {
                this.page = i;
                // Re-render using last args
                if (this._lastArgs) this.render(this._lastArgs);
            };
            this.paginationContainer.appendChild(btn);
        }
    }

    _cap(w) {
        return w.charAt(0).toUpperCase() + w.slice(1);
    }

    _sortIcon(key) {
        if (this.sortKey !== key) return '';
        return this.sortAsc ? '⬆️' : '⬇️';
    }

    _renderGenericDimension({ data, dimensionKey, isDaily, start, end, requestedDates }) {
        const sessionsKey = 'sessions';

        const filtered = this._filterData(data, isDaily, start, end, requestedDates);
        const cleanedData = filtered.filter(row => row && typeof row.date === 'string');

        const totals = {};

        cleanedData.forEach(row => {
            let dimValue = row[dimensionKey];

            // Allow '0' specifically for 'loginStatus'
            if (dimensionKey === 'loginStatus') {
                // Ensure dimValue is a string for consistent comparison
                if (typeof dimValue === 'number') dimValue = String(dimValue);

                // If it's loginStatus and the value is not a defined key or truly empty/null/undefined after trimming, skip
                if (!(dimValue in LOGIN_STATUS_LABELS) && (dimValue === null || dimValue.trim() === '')) {
                    return;
                }
            } else {
                // For other dimensions, maintain the original strict filtering of empty/null/undefined
                if (!dimValue || String(dimValue).trim() === '') return;
                dimValue = String(dimValue).trim();
            }

            // Map the raw value to its label for the totals object
            if (dimensionKey === 'loginStatus') {
                dimValue = LOGIN_STATUS_LABELS[dimValue] || dimValue;
            } else {
                dimValue = dimValue.toLowerCase();
            }

            const sessions = parseFloat(row[sessionsKey]) || 0;
            totals[dimValue] = (totals[dimValue] || 0) + sessions;
        });

        const topKeys = Object.keys(totals);

        const formatter = new Intl.NumberFormat();
        // Sorting should be by sessions, not the key name for generic dimensions
        const sortedKeys = topKeys.sort((a, b) => {
            if (this.sortKey === dimensionKey) { // If sorting by dimension name (alphabetical)
                return this.sortAsc ? a.localeCompare(b) : b.localeCompare(a);
            } else { // Default to sorting by sessions
                return this.sortAsc ? (totals[a] || 0) - (totals[b] || 0) : (totals[b] || 0) - (totals[a] || 0);
            }
        });

        const rows = sortedKeys.map(dim => {
            const label = dim; // `dim` already holds the correctly mapped label
            const total = totals[dim] || 0;
            return `<tr><td>${label}</td><td>${formatter.format(total)}</td></tr>`;
        });


        // ✅ Support pagination
        const startIdx = (this.page - 1) * this.rowsPerPage;
        const endIdx = startIdx + this.rowsPerPage;
        const pagedRows = rows.slice(startIdx, endIdx);

        this.table.innerHTML = `
            <thead>
                <tr>
                    <th style="text-align:left;" class="sortable" data-key="${dimensionKey}">${this._label(dimensionKey)}</th>
                    <th style="text-align:center;" class="sortable" data-key="sessions">Sessions ${this._sortIcon('sessions')}</th>
                </tr>
            </thead>
            <tbody>${pagedRows.join('')}</tbody>
        `;

        this._attachSortHandlers();
        const totalPages = Math.ceil(rows.length / this.rowsPerPage);
        this._renderPagination(totalPages);
    }
}