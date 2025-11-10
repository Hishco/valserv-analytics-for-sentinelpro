// DashboardHelpers/CsvExporter.js
export default class CsvExporter {
    constructor(rawData, options) {
        this.data = rawData;
        this.metric = options.metric;
        this.granularity = options.granularity;
        this.metricSet = options.metricSet || [];
        this.start = options.start;
        this.end = options.end;
        this.requestedDates = options.requestedDates || [];
        this.sortKey = options.sortKey || 'date';
        this.sortAsc = typeof options.sortAsc === 'boolean' ? options.sortAsc : true;
    }


    export() {

        if (!this.data.length || !this.metricSet.length) {
            alert('No data available to download.');
            return;
        }

        return this.granularity === 'daily'
            ? this._exportDaily()
            : this._exportHourly();
    }

    _exportDaily() {
        const grouped = {};
        this.data.forEach(row => {
            const date = row.date.split(' ')[0];
            if (!grouped[date]) grouped[date] = {};
            this.metricSet.forEach(m => {
                grouped[date][m] = (grouped[date][m] || 0) + (parseFloat(row[m]) || 0);
            });
        });

        const header = ['Date', ...this.metricSet.map(this._cap)];

        // 🔻 Sorting
        const sortKey = (this.sortKey || 'date').toLowerCase();
        const sortAsc = typeof this.sortAsc === 'boolean' ? this.sortAsc : true;

        const sortedDates = Object.keys(grouped).sort((a, b) => {
            if (sortKey === 'date') {
                return sortAsc ? a.localeCompare(b) : b.localeCompare(a);
            }

            const aVal = grouped[a]?.[sortKey] ?? 0;
            const bVal = grouped[b]?.[sortKey] ?? 0;
            return sortAsc ? aVal - bVal : bVal - aVal;
        });

        const rows = sortedDates.map(date => {
            return [date, ...this.metricSet.map(m => grouped[date][m] ?? 0)];
        });

        this._download(
            this._toCsv(header, rows),
            `sentinelpro-${this.metric}-${this.start}${this.end ? `-${this.end}` : ''}`
        );
    }


    _exportHourly() {
        const labels = Array.from({ length: 24 }, (_, i) => `${i.toString().padStart(2, '0')}:00`);
        const dayMap = {};

        this.requestedDates.forEach(day => {
            dayMap[day] = {};
            labels.forEach(hour => {
                dayMap[day][hour] = this.metricSet.reduce((acc, m) => { acc[m] = 0; return acc; }, {});
            });
        });

        this.data.forEach(row => {
            if (!row.date.includes(' ')) return;
            const [day, time] = row.date.split(' ');
            const hour = time.split(':')[0] + ':00';
            if (dayMap[day]?.[hour]) {
                this.metricSet.forEach(m => {
                    dayMap[day][hour][m] += parseFloat(row[m]) || 0;
                });
            }
        });

        const header = ['Hour', ...this.requestedDates.flatMap(day => this.metricSet.map(m => `${day} (${this._cap(m)})`))];

        // 🔻 Sort logic
        const sortKey = this.sortKey || 'hour';
        const sortAsc = typeof this.sortAsc === 'boolean' ? this.sortAsc : true;

        const sortedHours = [...labels].sort((a, b) => {
            if (sortKey === 'hour' || !sortKey.includes('|')) {
                return sortAsc ? a.localeCompare(b) : b.localeCompare(a);
            }

            const [sortDay, metricRaw] = sortKey.split('|');
            const metric = this._normalizeKey(metricRaw);

            const aVal = dayMap[sortDay]?.[a]?.[metric] ?? 0;
            const bVal = dayMap[sortDay]?.[b]?.[metric] ?? 0;
            return sortAsc ? aVal - bVal : bVal - aVal;
        });

        const rows = sortedHours.map(hour => {
            const row = [hour];
            this.requestedDates.forEach(day => {
                this.metricSet.forEach(m => {
                    row.push(dayMap[day]?.[hour]?.[m] ?? 0);
                });
            });
            return row;
        });

        this._download(this._toCsv(header, rows), `sentinelpro-${this.metric}-hourly-${this.requestedDates.join('-')}`);
    }


    _toCsv(header, rows) {
        return [
            header.join(','),
            ...rows.map((r, idx) =>
                r.map((val, i) =>
                    (i === 0 && this.granularity === 'daily') ? `"=""${val}"""` : `"${val}"`
                ).join(',')
            )
        ].join('\n');
    }

    _download(content, filename) {
        const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `${filename}.csv`);
        link.click();
        URL.revokeObjectURL(url);
    }

    _cap(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
}
