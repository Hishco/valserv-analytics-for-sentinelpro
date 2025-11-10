// admin/js/UserManagementHelpers/Utils.js

/**
 * Utils.js
 *
 * Contains general utility functions.
 */

/**
 * Creates a DOM element with given tag, classes, and attributes.
 * @param {string} tag
 * @param {string[]} classNames
 * @param {object} attributes
 * @returns {HTMLElement}
 */
export function createElement(tag, classNames = [], attributes = {}) {
    const el = document.createElement(tag);
    if (classNames.length) el.classList.add(...classNames);
    for (const key in attributes) {
        el.setAttribute(key, attributes[key]);
    }
    return el;
}

/**
 * Exports data to a CSV file.
 * @param {Array<Array<string>>} rows - Array of rows, where each row is an array of cell values.
 * @param {string} filename - The name of the CSV file.
 */
export function exportCSV(rows, filename) {
    const BOM = '\uFEFF'; // Byte Order Mark for Excel to detect UTF-8 properly

    // Dynamically detect email column (first row = headers)
    const headers = rows[0];
    const emailColIndex = headers.findIndex(h =>
        String(h).toLowerCase().includes('email')
    );

    const csvContent = rows.map((row, rowIndex) =>
        row.map((cell, colIndex) => {
            let value = String(cell ?? '');

            // Removed: Prevent Excel from auto-linking email column by prepending tab
            // if (rowIndex > 0 && colIndex === emailColIndex && value.includes('@')) {
            //     value = '\t' + value;
            // }

            // ✅ Escape special characters
            if (/[",\n]/.test(value)) {
                value = `"${value.replace(/"/g, '""')}"`;
            }

            return value;
        }).join(',')
    ).join('\n');

    const blob = new Blob([BOM + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = createElement('a', [], { download: filename });
    link.href = URL.createObjectURL(blob);
    link.click();
    URL.revokeObjectURL(link.href);
}



/**
 * Handles smooth scrolling for anchor links.
 * @param {Event} e - The click event.
 */
export function handleAnchorClick(e) {
    e.preventDefault();
    const href = e.currentTarget?.getAttribute?.('href');
    if (!href) return;

    const target = document.querySelector(href);
    if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}
