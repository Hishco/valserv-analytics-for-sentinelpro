// admin/js/UserManagementHelpers/TableManager.js

/**
 * TableManager.js
 *
 * Handles filtering and pagination logic for a generic HTML table.
 */

import { createElement } from './Utils.js';

export class TableManager {
    constructor(tableRows, rowsPerPageSelect, paginationControls, paginationStatus, tableHeadTh, noUsersRowClass) {
        this.allRows = Array.from(tableRows); // Initial set of all table rows (NodeList converted to Array)
        this.visibleRows = []; // Rows that pass the current filters (this will be populated by applyFilters)
        this.currentPage = 1;
        this.rowsPerPage = parseInt(rowsPerPageSelect?.value, 10) || 25;
        this.rowsPerPageSelect = rowsPerPageSelect;
        this.paginationControls = paginationControls;
        this.paginationStatus = paginationStatus;
        this.tableHeadTh = tableHeadTh;
        this.noUsersRowClass = noUsersRowClass;

        // Initialize all rows as visible by default. This dataset property will be updated by applyFilters.
        this.allRows.forEach(row => row.dataset.visible = 'true');

        this.setupEventListeners();
        this.paginate(); // Initial pagination render
    }

    setupEventListeners() {
        if (this.rowsPerPageSelect) {
            this.rowsPerPageSelect.addEventListener('change', () => {
                this.rowsPerPage = parseInt(this.rowsPerPageSelect.value, 10);
                this.currentPage = 1; // Reset to first page on changing rows per page
                this.paginate(); // Re-paginate based on existing filters
            });
        }
    }

    /**
     * Applies filters (search and role) to the table rows.
     * Marks rows with a data-visible attribute.
     * @param {string} searchTerm - User search input.
     * @param {string} selectedRole - Selected role filter.
     */
    applyFilters(searchTerm, selectedRole) {

        this.allRows.forEach(row => {
            // Skip the "no users found" row if it exists
            if (row.classList.contains(this.noUsersRowClass)) {
                row.style.display = 'none';
                row.dataset.visible = 'false';
                return;
            }

            const userText = row.querySelector('td:nth-child(1)')?.textContent.toLowerCase().trim() || '';
            const roleText = row.querySelector('td:nth-child(4)')?.textContent.toLowerCase().trim() || '';

            const matchesSearch = userText.includes(searchTerm);
            // KEY CHANGE: Use includes() for the role comparison for broader matching
            const matchesRole = selectedRole === '' || roleText.includes(selectedRole.toLowerCase());

            if (matchesSearch && matchesRole) {
                row.dataset.visible = 'true';
            } else {
                row.dataset.visible = 'false';
            }
        });

        // Reset current page to 1 after applying new filters
        this.currentPage = 1;
        // Trigger pagination to display the currently filtered (data-visible='true') rows
        this.paginate();
    }

    /**
     * Manages the display of rows based on current page and rows per page.
     * This method now relies on the `data-visible` attribute set by `applyFilters`.
     */
    paginate() {
        // Filter the *entire* set of all rows based on their `data-visible` attribute.
        this.visibleRows = this.allRows.filter(row => row.dataset.visible === 'true');

        const totalRows = this.visibleRows.length;
        const totalPages = Math.ceil(totalRows / this.rowsPerPage);

        // Clamp currentPage to be within valid range
        if (this.currentPage > totalPages && totalPages > 0) this.currentPage = totalPages;
        if (this.currentPage < 1 && totalPages > 0) this.currentPage = 1;
        if (totalPages === 0) this.currentPage = 1; // If no pages, ensure current page is 1 for status display


        const startIndex = (this.currentPage - 1) * this.rowsPerPage;
        const endIndex = Math.min(startIndex + this.rowsPerPage, totalRows);

        // Now, iterate over `this.allRows` (the full set) and set their display property.
        this.allRows.forEach(row => {
            // First, hide all rows not explicitly marked as visible by the filters
            if (row.dataset.visible === 'false') {
                row.style.display = 'none';
            } else {
                // For rows marked as visible, determine if they should be shown on the current page
                const isRowOnCurrentPage = this.visibleRows.indexOf(row) >= startIndex && this.visibleRows.indexOf(row) < endIndex;
                row.style.display = isRowOnCurrentPage ? '' : 'none';
            }
        });

        this.updateStatus(totalRows, startIndex, endIndex);
        this.renderPaginationButtons(totalPages);
    }

    /**
     * Updates the text showing current pagination status (e.g., "Showing 1-25 of 100 users").
     * Also handles the "No users found" message.
     * @param {number} totalRows - Total number of rows after filtering.
     * @param {number} startIndex - Starting index of displayed rows.
     * @param {number} endIndex - Ending index of displayed rows.
     */
    updateStatus(totalRows, startIndex, endIndex) {
        const tableBody = this.allRows[0]?.closest('tbody'); // Find tbody from a row

        // Remove any previous empty message row
        const existingEmptyRow = tableBody?.querySelector(`tr.${this.noUsersRowClass}`);
        if (existingEmptyRow) existingEmptyRow.remove();

        if (this.paginationStatus) {
            if (totalRows === 0) {
                this.paginationStatus.textContent = 'No users match your filters.';
            } else {
                this.paginationStatus.textContent = `Showing ${startIndex + 1}–${endIndex} of ${totalRows} users`;
            }

            if (totalRows === 0 && tableBody) {
                const emptyRow = createElement('tr', [this.noUsersRowClass], {
                    style: 'text-align:center; font-style: italic; padding: 15px; color: #666;'
                });
                const columnCount = this.tableHeadTh.length;
                emptyRow.innerHTML = `<td colspan="${columnCount}">🚫 No users match your search and filter.</td>`;
                tableBody.appendChild(emptyRow);
            }
        }
    }

    /**
     * Renders the pagination buttons.
     * @param {number} totalPages - Total number of pages.
     */
    renderPaginationButtons(totalPages) {
        if (!this.paginationControls) return;

        this.paginationControls.innerHTML = '';

        if (totalPages <= 1) return;

        const makeButton = (text, page, disabled = false) => {
            const btn = createElement('button', [], {
                disabled: disabled,
                style: `margin: 0 4px; padding: 6px 10px; cursor: ${disabled ? 'default' : 'pointer'}; opacity: ${disabled ? '0.4' : '1'}; border: 1px solid #ccd0d4; border-radius: 4px; background: ${page === this.currentPage ? '#0073aa' : '#f1f1f1'}; color: ${page === this.currentPage ? '#fff' : '#000'}`
            });
            btn.textContent = text;

            if (!disabled) {
                btn.addEventListener('click', () => {
                    this.currentPage = page;
                    this.paginate();
                });
            }
            return btn;
        };

        // Prev button
        this.paginationControls.appendChild(makeButton('« Prev', this.currentPage - 1, this.currentPage === 1));

        // Page number buttons logic
        const maxButtons = 5;
        const buttons = [];

        if (totalPages <= maxButtons + 2) {
            for (let i = 1; i <= totalPages; i++) {
                buttons.push(i);
            }
        } else {
            const start = Math.max(2, this.currentPage - Math.floor(maxButtons / 2));
            const end = Math.min(totalPages - 1, this.currentPage + Math.floor(maxButtons / 2));

            buttons.push(1);
            if (start > 2) buttons.push('...');

            for (let i = start; i <= end; i++) {
                buttons.push(i);
            }

            if (end < totalPages - 1) buttons.push('...');
            buttons.push(totalPages);
        }

        buttons.forEach(item => {
            if (item === '...') {
                const span = createElement('span', [], { style: 'margin: 0 6px; color: #999;' });
                span.textContent = '...';
                this.paginationControls.appendChild(span);
            } else {
                this.paginationControls.appendChild(makeButton(item.toString(), item, false));
            }
        });

        // Next button
        this.paginationControls.appendChild(makeButton('Next »', this.currentPage + 1, this.currentPage === totalPages));
    }

    getAllUserData() {
        const rows = this.allRows || [];
        return {
            rows: rows.map(row => {
                const cells = row.querySelectorAll('td');
                return Array.from(cells).map(cell => cell.textContent.trim());
            })
        };
    }

    getVisibleUserData() {
        const rows = this.allRows.filter(row => {
            return row.dataset.visible === 'true' && row.style.display !== 'none';
        });

        return {
            rows: rows.map(row => {
                const cells = row.querySelectorAll('td');
                return Array.from(cells).map(cell => cell.textContent.trim());
            })
        };
    }

    updatePagination(totalUsers, perPage, currentPage) {
        this.rowsPerPage = perPage;
        this.currentPage = currentPage;
        this.paginate();
    }

    /**
     * Extracts user data from DOM rows for CSV export.
     * @param {NodeList|Array} rows - DOM rows to extract from.
     * @param {boolean} visibleOnly - If true, only include rows that are visible.
     * @returns {{rows: Array<Array<string>>}}
     */
    static extractFromRows(rows, visibleOnly = false) {
        const data = [];
        rows.forEach(row => {
            if (visibleOnly && row.style.display === 'none') return;
            const cells = row.querySelectorAll('td');
            // Adjust indices to match DEFAULT_CSV_HEADERS order
            const user = cells[0]?.textContent.trim() || '';
            const fullName = cells[1]?.textContent.trim() || '';
            const email = cells[2]?.textContent.trim() || '';
            const role = cells[3]?.textContent.trim() || '';
            const apiInput = cells[4]?.textContent.trim() || '';
            const dashboard = cells[5]?.textContent.trim() || '';
            const userMgmt = cells[6]?.textContent.trim() || '';
            const postColumn = cells[7]?.textContent.trim() || '';
            data.push([user, fullName, email, role, apiInput, dashboard, userMgmt, postColumn]);
        });
        return { rows: data };
    }
}