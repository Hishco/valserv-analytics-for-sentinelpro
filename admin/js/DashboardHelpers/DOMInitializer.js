// DashboardHelpers/DOMInitializer.js

export function getElementReferences() {
    const chartDiv = document.getElementById('sentinelpro-chart');
    // Canvas for Chart.js
    return {
        chartDiv,
        spinner: document.getElementById('sentinelpro-spinner'),
        chartTableWrapper: document.getElementById('sentinelpro-data-table-section'),
        metricSelect: document.getElementById('metrics-select'),
        applyBtn: document.getElementById('apply-filters'),
        startInput: document.getElementById('filter-start'),
        endInput: document.getElementById('filter-end'),
        dateRangeInput: document.getElementById('filter-daterange'),
        granularitySelect: null, // Not present in current HTML
        customTag: document.getElementById('custom-date-indicator'),
        toggleBtn: document.getElementById('toggle-filters'),
        filterBox: null, // Not present in current HTML
        countryInput: null, // Not present in current HTML
        resetChip: document.getElementById('reset-dates'),
        compareStartInput: document.getElementById('compare-start'),
        compareEndInput: document.getElementById('compare-end'),
        compareToggle: document.getElementById('compare-toggle'),
        compareModeSelect: null, // Not present in current HTML
    };
}

export function createTableAndPaginationWrapper() {
    const table = document.createElement('table');
    table.id = 'event-data-table';
    table.className = 'widefat striped';
    table.style.width = '100%';

    const paginationContainer = document.createElement('div');
    paginationContainer.id = 'sentinelpro-pagination-controls';
    paginationContainer.style.marginTop = '12px';
    paginationContainer.style.textAlign = 'center';

    const wrapper = document.createElement('div');
    wrapper.style.width = '100%';
    wrapper.style.margin = '30px 0 40px';
    wrapper.className = 'sentinelpro-full-width-table'; // Just styling

    wrapper.appendChild(table);
    table.after(paginationContainer);

    return { table, wrapper, paginationContainer };
}




export function ensureDownloadCsvButton(container) {
    let btn = document.getElementById('download-csv');
    if (btn) return btn;

    btn = document.createElement('button');
    btn.id = 'download-csv';
    btn.className = 'sentinelpro-csv-download';
    btn.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" style="margin-right:8px;" viewBox="0 0 16 16">
        <path d="M.5 9.9V14a1 1 0 0 0 1 1h13a1 1 0 0 0 1-1V9.9a.5.5 0 0 0-1 0V14H1.5V9.9a.5.5 0 0 0-1 0z"/>
        <path d="M8 1a.5.5 0 0 1 .5.5v8.793l2.146-2.147a.5.5 0 0 1 .708.708l-3 3a.5.5 0 0 1-.708 0l-3-3a.5.5 0 1 1 .708-.708L7.5 10.293V1.5A.5.5 0 0 1 8 1z"/>
        </svg>Download CSV
    `;
    btn.style.display = 'block';
    btn.style.margin = '20px auto';
    btn.style.minWidth = '180px';
    btn.style.textAlign = 'center';

    container?.appendChild(btn);
    return btn;
}

// Add a custom country checkbox to the geo filter
export function addCustomCountryCheckbox(countryObj, { onUpdateCounts } = {}) {
    const container = document.getElementById('geo-custom-checkboxes');
    if (!container) return;
    // Check if already present
    if (container.querySelector('input[type="checkbox"][value="' + countryObj.code + '"]')) return;
    // Create wrapper label for consistent styling
    const label = document.createElement('label');
    label.className = 'sentinelpro-geo-custom-checkbox';
    // Create checkbox
    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.value = countryObj.code;
    checkbox.id = 'geo-custom-' + countryObj.code;
    checkbox.checked = true;
    // Add code as label text
    label.appendChild(checkbox);
    label.appendChild(document.createTextNode(' ' + countryObj.code));
    // Create remove button
    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'geo-custom-remove';
    removeBtn.title = 'Remove';
    removeBtn.innerHTML = '&times;';
    removeBtn.style.marginLeft = '8px';
    removeBtn.style.background = 'none';
    removeBtn.style.border = 'none';
    removeBtn.style.cursor = 'pointer';
    removeBtn.style.fontSize = '18px';
    removeBtn.style.color = '#d63638';
    // Remove logic
    removeBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        label.remove();
        if (onUpdateCounts) onUpdateCounts();
    });
    label.appendChild(removeBtn);
    container.appendChild(label);
    // Dispatch change event so counts update
    checkbox.dispatchEvent(new Event('change', { bubbles: true }));
    if (onUpdateCounts) onUpdateCounts();
}

// Render a content type dropdown
export function renderContentTypeDropdown(contentTypes, selectedContentTypes, { onChange } = {}) {
    let dropdown = document.getElementById('contenttype-dropdown-panel');
    if (!dropdown) {
        dropdown = document.createElement('div');
        dropdown.id = 'contenttype-dropdown-panel';
        dropdown.className = 'dropdown-panel';
        dropdown.style.position = 'absolute';
        dropdown.style.zIndex = 1000;
        document.body.appendChild(dropdown);
    }
    dropdown.innerHTML = `
      <div class="dropdown-header">
        <span>Content type</span>
        <span>Sessions</span>
      </div>
      <div class="dropdown-search">
        <input type="text" placeholder="Type to search" id="contenttype-searchbox" />
      </div>
      <div class="dropdown-list" id="contenttype-list"></div>
    `;
    // Position the dropdown below the trigger
    const trigger = document.getElementById('contenttype-dropdown-trigger');
    if (trigger) {
        const rect = trigger.getBoundingClientRect();
        dropdown.style.left = rect.left + 'px';
        dropdown.style.top = (rect.bottom + window.scrollY) + 'px';
        dropdown.style.width = rect.width + 'px';
    }
    // Render the list
    function renderList(filter = '') {
        const list = document.getElementById('contenttype-list');
        list.innerHTML = '';
        contentTypes
            .filter(ct => ct.name.toLowerCase().includes(filter.toLowerCase()))
            .forEach(ct => {
                const isChecked = selectedContentTypes.includes(ct.name);
                const item = document.createElement('div');
                item.className = 'dropdown-item' + (isChecked ? ' selected' : '');
                item.innerHTML = `
                  <label>
                    <input type="checkbox" ${isChecked ? 'checked' : ''} data-name="${ct.name}">
                    ${ct.name}
                  </label>
                  <span class="sessions">${ct.sessions}</span>
                `;
                list.appendChild(item);
            });
    }
    renderList();
    document.getElementById('contenttype-searchbox').addEventListener('input', (e) => {
        renderList(e.target.value);
    });
    
    // Add Enter key handler for content type search
    document.getElementById('contenttype-searchbox').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (window.EnhancedDashboardInstance?.applyFilters) {
                window.EnhancedDashboardInstance.applyFilters();
            }
        }
    });
    document.getElementById('contenttype-list').addEventListener('change', (e) => {
        if (e.target.type === 'checkbox') {
            const name = e.target.getAttribute('data-name');
            if (e.target.checked) {
                if (!selectedContentTypes.includes(name)) {
                    selectedContentTypes.push(name);
                }
            } else {
                const idx = selectedContentTypes.indexOf(name);
                if (idx !== -1) selectedContentTypes.splice(idx, 1);
            }
            renderList(document.getElementById('contenttype-searchbox').value);
            if (onChange) onChange(selectedContentTypes);
        }
    });
}

// Update the filter count for a generic dropdown
export function updateGenericFilterCount(dimCamel) {
    // Find the filter header for this dimension (use camelCase for data-api-key)
    const apiKey = dimCamel.charAt(0).toLowerCase() + dimCamel.slice(1);
    const header = document.querySelector('.sentinelpro-filter-item-header[data-api-key="' + apiKey + '"]');
    if (!header) return;
    let countSpan = header.querySelector('.sentinelpro-filter-count');
    if (!countSpan) {
        countSpan = document.createElement('span');
        countSpan.className = 'sentinelpro-filter-count';
        header.insertBefore(countSpan, header.querySelector('.sentinelpro-filter-chevron'));
    }
    // Count checked checkboxes in the dropdown
    const wrapper = document.querySelector('.sentinelpro-generic-dropdown-wrapper[data-dimension="' + dimCamel + '"]');
    if (!wrapper) return;
    const dropdown = wrapper.querySelector('.sentinelpro-generic-dropdown');
    if (!dropdown) return;
    const checked = dropdown.querySelectorAll('input[type="checkbox"]:checked').length;
    countSpan.textContent = checked > 0 ? checked : '';
}