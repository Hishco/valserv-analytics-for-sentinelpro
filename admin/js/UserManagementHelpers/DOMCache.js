/**
 * DOMCache.js
 *
 * A helper class to cache DOM elements, reducing repetitive
 * document.querySelector/All calls.
 */

export class DOMCache {
    constructor(selectors) {
        this.selectors = selectors; // ✅ Store for reference
        this.elements = {};
        this.cacheElements(selectors);
    }

    /**
     * Caches DOM elements based on provided selectors.
     * Keys ending in "All" or certain patterns trigger querySelectorAll.
     */
    cacheElements(selectors) {
        for (const key in selectors) {
            const selector = selectors[key];
            const isCollection =
                key.endsWith('All') ||
                selector.includes('tr') ||
                selector.includes('td') ||
                selector.includes('th') ||
                selector.includes('.sentinelpro-access-label');

            const result = isCollection
                ? document.querySelectorAll(selector)
                : document.querySelector(selector);

            this.elements[key] = result;

            // Warn if not found (for single elements only)
            if (
                !result ||
                (isCollection && result.length === 0)
            ) {
            }
        }
    }

    /**
     * Returns cached DOM element or NodeList
     * @param {string} key
     * @returns {HTMLElement|NodeList|null}
     */
    get(key) {
        return this.elements[key] || null;
    }
}
