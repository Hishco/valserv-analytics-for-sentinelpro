document.addEventListener('DOMContentLoaded', () => {
    const SENTINELPRO_CACHE_KEY = 'sentinelpro_post_totals_cache';
    const MAX_CACHE_AGE_MS = 1000 * 60 * 10; // 10 minutes

    function loadCache() {
        try {
            const raw = localStorage.getItem(SENTINELPRO_CACHE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (e) {
            return null;
        }
    }

    function saveCache(data) {
        localStorage.setItem(SENTINELPRO_CACHE_KEY, JSON.stringify(data));
    }

    function invalidateCache() {
        localStorage.removeItem(SENTINELPRO_CACHE_KEY);
    }

    const postTable = document.querySelector('table.wp-list-table.posts');
    if (!postTable) return;

    const tableBody = postTable.querySelector('tbody#the-list');
    const headerRow = postTable.querySelector('thead tr');
    if (!tableBody || !headerRow) return;

    let totalViews = 0;
    let totalSessions = 0;
    let viewsColumnIndex = -1;
    let sessionsColumnIndex = -1;

    headerRow.querySelectorAll('th').forEach((th, index) => {
        const text = th.textContent.toLowerCase();
        if (th.classList.contains('column-sentinelpro_views') || text.includes('views')) viewsColumnIndex = index;
        if (th.classList.contains('column-sentinelpro_sessions') || text.includes('sessions')) sessionsColumnIndex = index;
    });

    if (viewsColumnIndex === -1 && sessionsColumnIndex === -1) return;

    const postRows = Array.from(tableBody.querySelectorAll('tr'))
        .filter(row => !row.classList.contains('no-items') && row.id && row.id.startsWith('post-'));

    // Build a rowMap for efficient lookup
    const rowMap = {};
    postRows.forEach(row => {
        const postIdMatch = row.id.match(/^post-(\d+)$/);
        const postId = postIdMatch ? postIdMatch[1] : null;
        if (postId) rowMap[postId] = row;
    });

    const cached = loadCache();
    if (
        cached &&
        cached.totalViews &&
        cached.data?.length === postRows.length &&
        cached.updated &&
        (Date.now() - cached.updated <= MAX_CACHE_AGE_MS)
    ) {
        cached.data.forEach(rowData => {
            const row = rowMap[rowData.postId];
            if (!row) return;

            const cells = row.querySelectorAll('td');
            if (viewsColumnIndex !== -1 && cells[viewsColumnIndex]) {
                cells[viewsColumnIndex].innerHTML = `
                    <span style="display:none;">${rowData.views}</span>
                    <div class="sentinelpro-bar-container">
                        <div class="sentinelpro-bar-value">${rowData.views.toLocaleString()}</div>
                        <div class="sentinelpro-bar-wrapper" title="${rowData.viewPercent.toFixed(1)}% of total views">
                            <div class="sentinelpro-bar-fill" style="width: ${rowData.viewPercent}%;"></div>
                        </div>
                    </div>`;
            }
            if (sessionsColumnIndex !== -1 && cells[sessionsColumnIndex]) {
                cells[sessionsColumnIndex].innerHTML = `
                    <span style="display:none;">${rowData.sessions}</span>
                    <div class="sentinelpro-bar-container">
                        <div class="sentinelpro-bar-value">${rowData.sessions.toLocaleString()}</div>
                        <div class="sentinelpro-bar-wrapper sessions" title="${rowData.sessionPercent.toFixed(1)}% of total sessions">
                            <div class="sentinelpro-bar-fill sessions" style="width: ${rowData.sessionPercent}%;"></div>
                        </div>
                    </div>`;
            }
        });
        totalViews = cached.totalViews;
        totalSessions = cached.totalSessions;

    } else {
        if (cached && cached.updated && (Date.now() - cached.updated > MAX_CACHE_AGE_MS)) {
            invalidateCache();
        }
        const processedData = [];

        postRows.forEach(row => {
            const postIdMatch = row.id.match(/^post-(\d+)$/);
            const postId = postIdMatch ? postIdMatch[1] : null;
            if (!postId) return;
            const cells = row.querySelectorAll('td');
            let views = 0, sessions = 0;

            if (viewsColumnIndex !== -1 && cells[viewsColumnIndex]) {
                const val = parseInt(cells[viewsColumnIndex].textContent.trim().replace(/,/g, ''), 10);
                if (!isNaN(val)) {
                    views = val;
                    totalViews += val;
                }
            }
            if (sessionsColumnIndex !== -1 && cells[sessionsColumnIndex]) {
                const val = parseInt(cells[sessionsColumnIndex].textContent.trim().replace(/,/g, ''), 10);
                if (!isNaN(val)) {
                    sessions = val;
                    totalSessions += val;
                }
            }

            processedData.push({ postId, views, sessions });
        });

        processedData.forEach(data => {
            const row = rowMap[data.postId];
            if (!row) return;

            const cells = row.querySelectorAll('td');
            const viewPercent = totalViews > 0 ? (data.views / totalViews) * 100 : 0;
            const sessionPercent = totalSessions > 0 ? (data.sessions / totalSessions) * 100 : 0;

            if (viewsColumnIndex !== -1 && cells[viewsColumnIndex]) {
                cells[viewsColumnIndex].innerHTML = `
                    <span style="display:none;">${data.views}</span>
                    <div class="sentinelpro-bar-container">
                        <div class="sentinelpro-bar-value">${data.views.toLocaleString()}</div>
                        <div class="sentinelpro-bar-wrapper" title="${viewPercent.toFixed(1)}% of total views">
                            <div class="sentinelpro-bar-fill" style="width: ${viewPercent}%;"></div>
                        </div>
                    </div>`;
            }

            if (sessionsColumnIndex !== -1 && cells[sessionsColumnIndex]) {
                cells[sessionsColumnIndex].innerHTML = `
                    <span style="display:none;">${data.sessions}</span>
                    <div class="sentinelpro-bar-container">
                        <div class="sentinelpro-bar-value">${data.sessions.toLocaleString()}</div>
                        <div class="sentinelpro-bar-wrapper sessions" title="${sessionPercent.toFixed(1)}% of total sessions">
                            <div class="sentinelpro-bar-fill sessions" style="width: ${sessionPercent}%;"></div>
                        </div>
                    </div>`;
            }

            data.viewPercent = viewPercent;
            data.sessionPercent = sessionPercent;
        });

        saveCache({
            totalViews,
            totalSessions,
            data: processedData,
            updated: Date.now()
        });


    }

    // Totals Row
    const totalsRow = document.createElement('tr');
    totalsRow.classList.add('sentinelpro-totals-row');
    const headers = headerRow.querySelectorAll('th');
    for (let index = 0; index < headers.length; index++) {
        const td = document.createElement('td');

        if (index === 0) {
            td.textContent = 'Totals:';
            td.colSpan = viewsColumnIndex + 1;
            td.style.fontWeight = 'bold';
            td.style.textAlign = 'left';
            totalsRow.appendChild(td);
            index += viewsColumnIndex - 1;
            continue;
        }

        if (index === viewsColumnIndex) {
            td.textContent = totalViews.toLocaleString();
            td.classList.add('column-sentinelpro_views');
            td.style.fontWeight = 'bold';
        } else if (index === sessionsColumnIndex) {
            td.textContent = totalSessions.toLocaleString();
            td.classList.add('column-sentinelpro_sessions');
            td.style.fontWeight = 'bold';
        } else {
            td.innerHTML = '&nbsp;';
        }

        totalsRow.appendChild(td);
    }

    let tableFoot = postTable.querySelector('tfoot');
    if (!tableFoot) {
        tableFoot = document.createElement('tfoot');
        postTable.appendChild(tableFoot);
    }
    tableFoot.appendChild(totalsRow);

    
});
