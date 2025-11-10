// DashboardHelpers/ChunkedFetchHelper.js

export function shouldChunk(startDate, endDate) {
    const start = new Date(startDate);
    const end = new Date(endDate);
    return (end - start) / (1000 * 60 * 60 * 24) > 30;
}
  
export async function fetchWithChunkRetry(chunkParamsArray, orchestrator, dashboardInstance, comparisonData = null) {
    if (!orchestrator || !dashboardInstance) return;
    dashboardInstance.showLoading();

    const { results, failedChunks } = await orchestrator.fetchChunksSequentially(chunkParamsArray);
    const allData = results.flatMap(r => Array.isArray(r.data) ? r.data : []);

    // Pass comparison data if provided
    if (comparisonData) {
        dashboardInstance.setData(allData, comparisonData);
    } else {
        dashboardInstance.setData(allData);
    }
    dashboardInstance.hideLoading();

    if (failedChunks.length > 0 && typeof dashboardInstance.showFailedChunksWarning === 'function') {
        dashboardInstance.showFailedChunksWarning(failedChunks, async () => {
            const retryBtn = document.getElementById('sentinelpro-failed-chunks-warning')?.querySelector('button');
            if (retryBtn) {
                retryBtn.textContent = 'Retrying...';
                retryBtn.disabled = true;
            }
            await fetchWithChunkRetry(failedChunks, orchestrator, dashboardInstance, comparisonData);
            if (retryBtn) {
                retryBtn.textContent = 'Retry Failed';
                retryBtn.disabled = false;
            }
        });
    }
}

export function showFailedChunksWarning(failedChunks, retryCallback, container = null) {
    let warningDiv = document.getElementById('sentinelpro-failed-chunks-warning');
    if (!warningDiv) {
        warningDiv = document.createElement('div');
        warningDiv.id = 'sentinelpro-failed-chunks-warning';
        warningDiv.style.background = '#fff3cd';
        warningDiv.style.color = '#856404';
        warningDiv.style.border = '1px solid #ffeeba';
        warningDiv.style.padding = '12px 16px';
        warningDiv.style.margin = '16px 0';
        warningDiv.style.borderRadius = '6px';
        warningDiv.style.fontSize = '15px';
        warningDiv.style.display = 'block';
        // Flex container for message and button
        const flexContainer = document.createElement('div');
        flexContainer.style.display = 'flex';
        flexContainer.style.flexWrap = 'wrap';
        flexContainer.style.justifyContent = 'space-between';
        flexContainer.style.alignItems = 'center';
        // Message
        const messageSpan = document.createElement('span');
        messageSpan.innerHTML = `Some data could not be loaded. <span style='font-weight:600;'>${failedChunks.length} chunk(s) failed.</span>`;
        flexContainer.appendChild(messageSpan);
        // Retry button
        const retryBtn = document.createElement('button');
        retryBtn.textContent = 'Retry Failed';
        retryBtn.style.background = '#d9534f';
        retryBtn.style.color = '#fff';
        retryBtn.style.border = 'none';
        retryBtn.style.borderRadius = '4px';
        retryBtn.style.padding = '6px 14px';
        retryBtn.style.marginLeft = '18px';
        retryBtn.style.cursor = 'pointer';
        retryBtn.onclick = retryCallback;
        flexContainer.appendChild(retryBtn);
        // Optional: log failed chunk metadata
        failedChunks.forEach(chunk => {
        });
        // Optionally add a <details> element for admin debugging
        if (failedChunks.length > 0) {
            const details = document.createElement('details');
            details.style.marginTop = '8px';
            const summary = document.createElement('summary');
            summary.textContent = 'Show failed chunk details';
            details.appendChild(summary);
            const pre = document.createElement('pre');
            pre.style.fontSize = '12px';
            pre.style.whiteSpace = 'pre-wrap';
            pre.textContent = JSON.stringify(failedChunks, null, 2);
            details.appendChild(pre);
            flexContainer.appendChild(details);
        }
        warningDiv.appendChild(flexContainer);
        // Insert into container
        let target = container || document.getElementById('sentinelpro-dashboard') || document.body;
        target.insertBefore(warningDiv, target.firstChild);
    } else {
        warningDiv.style.display = 'block';
        const flexContainer = warningDiv.querySelector('div');
        if (flexContainer) {
            const messageSpan = flexContainer.querySelector('span');
            if (messageSpan) messageSpan.innerHTML = `Some data could not be loaded. <span style='font-weight:600;'>${failedChunks.length} chunk(s) failed.</span>`;
            const retryBtn = flexContainer.querySelector('button');
            if (retryBtn) retryBtn.onclick = retryCallback;
            // Update details
            const details = flexContainer.querySelector('details');
            if (details) {
                const pre = details.querySelector('pre');
                if (pre) pre.textContent = JSON.stringify(failedChunks, null, 2);
            }
        }
        // Log failed chunk metadata
        failedChunks.forEach(chunk => {
        });
    }
}
  
export function hideFailedChunksWarning() {
    const warningDiv = document.getElementById('sentinelpro-failed-chunks-warning');
    if (warningDiv) warningDiv.style.display = 'none';
}
  