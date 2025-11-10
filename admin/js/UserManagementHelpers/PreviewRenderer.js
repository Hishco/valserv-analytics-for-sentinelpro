// admin/js/UserManagementHelpers/PreviewRenderer.js

/**
 * PreviewRenderer.js
 *
 * Manages the rendering and interactivity of the user upload preview table.
 */

import { createElement, exportCSV } from './Utils.js';

export class PreviewRenderer {
    static EXPECTED_HEADERS = [
        'user', 'full_name', 'email', 'role',
        'api_input', 'dashboard', 'user_management', 'post_analytics_column'
    ];
    constructor(dom, previewWrapper, hiddenAccessInput, uploadForm, changedRowClass) {
        this.dom = dom;
        this.previewWrapper = previewWrapper;
        this.hiddenAccessInput = hiddenAccessInput;
        this.uploadForm = uploadForm;
        this.changedRowClass = changedRowClass;

        this.allPreviewRows = []; // All rows from the uploaded file (DOM elements)
        this.changedPreviewRows = []; // Only rows with changes (DOM elements)
        this.previewCurrentPage = 1;
        this.previewRowsPerPage = 10; // Constant for preview table pagination

        this.elements = {}; // For internal preview elements like summary, pagination, toggle
    }

    /**
     * Renders the file upload preview table.
     * @param {Array<Array<string>>} uploadedRowsData - The data rows from the uploaded file (excluding headers).
     * @param {Array<string>} headers - The header row from the uploaded file.
     * @param {function(string): HTMLElement|null} findUserTableRowCallback - Callback to find a user row in the main table by email.
     */
    render(uploadedRowsData, headers, findUserTableRowCallback) {
        if (!this.previewWrapper) return;

        this.previewWrapper.innerHTML = ''; // Clear previous preview

        // Setup Preview Container structure
        this.previewWrapper.innerHTML = `
            <h3>📋 Preview Uploaded Access</h3>
            <div id="sentinelpro-preview-summary" style="margin-bottom: 8px; font-weight: 500;"></div>
            <div style="margin-bottom: 10px;">
                <label style="font-size: 13px; cursor: pointer;">
                    <input type="checkbox" id="sentinelpro-preview-toggle-checkbox" style="margin-right: 6px;">
                    Show all uploaded rows
                </label>
            </div>
            <div id="sentinelpro-preview-pagination" style="margin-bottom: 10px;"></div>
        `;

        // Cache dynamically created elements
        this.elements.previewSummary = document.getElementById('sentinelpro-preview-summary');
        this.elements.previewPagination = document.getElementById('sentinelpro-preview-pagination');
        this.elements.previewToggleCheckbox = document.getElementById('sentinelpro-preview-toggle-checkbox');

        const table = createElement('table', ['widefat', 'striped']);
        const thead = createElement('thead');
        thead.innerHTML = '<tr>' + headers.map(h => `<th>${h}</th>`).join('') + '</tr>';
        table.appendChild(thead);

        const tbody = createElement('tbody');
        table.appendChild(tbody);
        this.previewWrapper.appendChild(table);
        this.previewWrapper.style.display = 'block';

        this.allPreviewRows = [];
        this.changedPreviewRows = [];

        // ✅ Validate expected headers
        const expectedHeaders = [
            'user', 'full_name', 'email', 'role',
            'api_input', 'dashboard', 'user_management', 'post_analytics_column'
        ];

        const uploadedHeaderSlugs = headers.map(h => h.trim().toLowerCase().replace(/[^a-z0-9]/g, '_'));
        const slugToIndex = Object.fromEntries(uploadedHeaderSlugs.map((slug, i) => [slug, i]));
        const missing = expectedHeaders.filter(slug => !(slug in slugToIndex));

        if (missing.length > 0) {
            alert(`🚫 Invalid CSV headers. Missing: ${missing.join(', ')}`);
            return;
        }

        // Make slugToIndex available in setHiddenInput
        this.slugToIndex = slugToIndex;
        this.expectedHeaders = expectedHeaders;


        uploadedRowsData.forEach(rawRow => {
            const row = expectedHeaders.map(slug => rawRow[slugToIndex[slug]]?.trim() || '');
            const email = row[2] || '';
            const cleanEmail = email.replace(/^[\s"'`]+|[\s"'`]+$/g, '').toLowerCase();
            const currentRow = findUserTableRowCallback(cleanEmail);


            const tr = createElement('tr');
            this.allPreviewRows.push(tr);

            if (!currentRow) {
                tr.classList.add('unmatched-row');
                tr.innerHTML = row.map(cell => `<td>${cell}</td>`).join('');
                tbody.appendChild(tr);
                return;
            }

            let hasChange = false;
            tr.innerHTML = row.map((cell, i) => {
                let style = '';
                // Normalize uploaded value
                const uploadedVal = (cell ?? '').trim().replace(/^[\s"'`]+|[\s"'`]+$/g, '').toUpperCase();
                if (i >= 4) { // permission columns start at index 4
                    const key = expectedHeaders[i];
                    // Try to find label by data-key, fallback to nth label
                    let label = currentRow.querySelector(`.sentinelpro-access-label[data-key="${key}"]`);
                    if (!label) {
                        label = currentRow.querySelectorAll('.sentinelpro-access-label')[i - 4];
                    }
                    const currentVal = label?.dataset.status === '1' ? 'ALLOWED' : 'RESTRICTED';
                    if (!['ALLOWED', 'RESTRICTED'].includes(uploadedVal)) {
                        style = 'color:red; font-weight:bold;';
                    } else if (label && currentVal !== uploadedVal) {
                        style = 'background-color: #fff3cd;';
                        hasChange = true;
                    }
                }
                return `<td style="${style}">${cell}</td>`;
            }).join('');


            if (hasChange) {
                tr.classList.add(this.changedRowClass);
                this.changedPreviewRows.push(tr);
            }
            tbody.appendChild(tr);
        });


        // Add event listener for the toggle checkbox
        this.elements.previewToggleCheckbox?.addEventListener('change', () => {
            this.previewCurrentPage = 1; // Reset page when toggle changes
            this.renderPreviewPage(tbody, headers);
        });

        // Show all uploaded rows by default
        if (this.elements.previewToggleCheckbox) {
            this.elements.previewToggleCheckbox.checked = true;
        }

        // Initial render of the preview page
        this.renderPreviewPage(tbody, headers);

        this.addPreviewButtons(headers, uploadedRowsData);
        this.updateSummary();
        this.setHiddenInput(headers, uploadedRowsData, findUserTableRowCallback);
        if (this.uploadForm) this.uploadForm.style.display = 'block';
    }

    /**
     * Renders a specific page of the preview table.
     * @param {HTMLElement} tbody - The tbody element of the preview table.
     * @param {Array<string>} headers - The headers for the preview table (used for CSV export if needed).
     */
    renderPreviewPage(tbody, headers) {
        tbody.innerHTML = '';
        const useAll = this.elements.previewToggleCheckbox?.checked;
        let rowsToRender;
        if (useAll) {
            rowsToRender = this.allPreviewRows;
        } else {
            // Show both changed + unmatched users by default
            rowsToRender = this.allPreviewRows.filter(row =>
                row.classList.contains(this.changedRowClass) || row.classList.contains('unmatched-row')
            );
        }


        const start = (this.previewCurrentPage - 1) * this.previewRowsPerPage;
        const end = start + this.previewRowsPerPage;

        rowsToRender.slice(start, end).forEach(tr => tbody.appendChild(tr));

        this.updatePreviewPaginationControls(rowsToRender.length);
    }

    /**
     * Updates the pagination controls for the preview table.
     * @param {number} totalRows - Total number of rows in the current view (all or changed).
     */
    updatePreviewPaginationControls(totalRows) {
        const control = this.elements.previewPagination;
        if (!control) return;

        control.innerHTML = '';
        const totalPages = Math.ceil(totalRows / this.previewRowsPerPage);

        if (totalPages <= 1) return;

        const makeButton = (text, page, disabled = false) => {
            const btn = createElement('button', [], {
                textContent: text,
                disabled: disabled,
                style: `margin: 0 5px; padding: 4px 10px; background: ${page === this.previewCurrentPage ? '#0073aa' : '#eee'}; color: ${page === this.previewCurrentPage ? '#fff' : '#000'}; border: 1px solid #ccc; border-radius: 4px;`
            });
            if (!disabled) {
                btn.addEventListener('click', () => {
                    this.previewCurrentPage = page;
                    this.renderPreviewPage(this.previewWrapper.querySelector('tbody'), []); // Pass tbody and empty headers (not needed for rendering, only in the render method itself)
                });
            }
            return btn;
        };

        control.appendChild(makeButton('Prev', this.previewCurrentPage - 1, this.previewCurrentPage === 1));
        for (let i = 1; i <= totalPages; i++) {
            control.appendChild(makeButton(i.toString(), i, false));
        }
        control.appendChild(makeButton('Next', this.previewCurrentPage + 1, this.previewCurrentPage === totalPages));
    }

    /**
     * Adds the Cancel and Download Changed Rows buttons to the preview.
     * @param {Array<string>} headers - Headers for CSV export.
     * @param {Array<Array<string>>} allUploadedRowsData - All data from the uploaded file.
     */
    addPreviewButtons(headers, allUploadedRowsData) {
        // Download Changed Rows (REMOVED)
        // Cancel Button
        const cancelBtn = document.createElement('button');
        cancelBtn.className = 'sentinelpro-btn sentinelpro-btn-secondary';
        cancelBtn.textContent = '✖ Cancel Upload';
        cancelBtn.addEventListener('click', () => this.clear());
        this.previewWrapper.insertBefore(cancelBtn, this.previewWrapper.querySelector('table'));
    }

    submitPreviewChanges() {
        const formData = new FormData();
        const csv = this.hiddenAccessInput?.value;
        if (!csv) return alert('No data to submit.');

        formData.append('action', 'sentinelpro_ajax_upload_preview');
        formData.append('csv_data', csv);

        this.previewWrapper.style.opacity = '0.5';

        fetch(ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(res => res.json())
        .then(response => {
            this.previewWrapper.style.opacity = '1';

            if (response.success) {
                const msg = response.data?.message || '✅ Preview changes saved successfully.';
                alert(msg);
                this.clear();
                location.reload();
            } else {
                const err = response.data?.message || 'Unknown error.';
                alert('❌ Error: ' + err);
            }
        })
        .catch(error => {
            this.previewWrapper.style.opacity = '1';
            alert('❌ Upload failed.');
        });
    }



    /**
     * Updates the summary text for the preview (e.g., "X changes detected").
     */
    updateSummary() {
        const summaryBox = this.elements.previewSummary;
        if (!summaryBox) return;

        const unmatchedCount = this.allPreviewRows.filter(r => r.classList.contains('unmatched-row')).length;
        const changedCount = this.changedPreviewRows.length;

        let summary = '';
        if (changedCount > 0) {
            summary += `✅ ${changedCount} user${changedCount === 1 ? '' : 's'} with permission changes detected. `;
        }
        if (unmatchedCount > 0) {
            summary += `⚠️ ${unmatchedCount} unmatched user${unmatchedCount === 1 ? '' : 's'} in file.`;
        }
        if (!summary) {
            summary = '🚫 No permission changes detected in uploaded file.';
        }

        summaryBox.textContent = summary.trim();
        summaryBox.style.color = unmatchedCount > 0 ? '#a94442' : '#2c3e50';
    }


    /**
     * Sets the value of the hidden input field with the CSV string for submission.
     * @param {Array<string>} headers - The headers for the CSV.
     * @param {Array<Array<string>>} uploadedRowsData - The data rows for the CSV.
     * @param {function(string): HTMLElement|null} findUserTableRowCallback - Callback to find a user row in the main table by email.
     */
    setHiddenInput(headers, uploadedRowsData, findUserTableRowCallback) {
        if (this.hiddenAccessInput && this.expectedHeaders && this.slugToIndex) {
            const normalized = [
                this.expectedHeaders,
                ...uploadedRowsData.filter(row => {
                    // Check if this row is for a SuperUser and skip if so
                    const email = row[this.slugToIndex['email']]?.trim().toLowerCase();
                    const userRow = findUserTableRowCallback ? findUserTableRowCallback(email) : null;
                    if (userRow) {
                        // If any access label is locked, treat as SuperUser
                        const locked = userRow.querySelector('.sentinelpro-access-label[data-locked="1"]');
                        if (locked) return false;
                    }
                    return true;
                }).map(row =>
                    this.expectedHeaders.map(slug => {
                        const i = this.slugToIndex[slug];
                        return row[i] ?? '';
                    })
                )
            ];
            const escapeCSV = (val) => {
                const v = String(val ?? '');
                if (v.includes(',') || v.includes('"') || v.includes('\n')) {
                    return `"${v.replace(/"/g, '""')}"`;
                }
                return v;
            };

            const csvString = normalized.map(row => row.map(escapeCSV).join(',')).join('\n');
            this.hiddenAccessInput.value = csvString;
        }
    }


    /**
     * Clears the preview display and resets related states.
     */
    clear() {
        if (this.previewWrapper) this.previewWrapper.innerHTML = '';
        if (this.hiddenAccessInput) this.hiddenAccessInput.value = '';
        if (this.uploadForm) this.uploadForm.style.display = 'block'; // Show the upload form again
    }
}