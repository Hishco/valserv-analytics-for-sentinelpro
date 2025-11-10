// DashboardHelpers/FilterCountUpdater.js

export default class FilterCountUpdater {
    /**
     * Generalized method to update filter count for any dimension
     * @param {string} dimensionKey - The dimension key (e.g., 'device', 'geo', 'browser')
     * @param {string} headerText - The header text to find (e.g., 'DEVICE', 'GEO')
     * @param {string} selector - Custom selector for checkboxes (optional)
     */
    static updateFilterCount(dimensionKey, headerText, selector = null) {
        const header = [...document.querySelectorAll('.sentinelpro-filter-item-header')]
            .find(h => h.textContent.trim().toUpperCase().startsWith(headerText.toUpperCase()));
        
        if (!header) {
            return;
        }

        let countSpan = header.querySelector('.sentinelpro-filter-count');
        if (!countSpan) {
            countSpan = document.createElement('span');
            countSpan.className = 'sentinelpro-filter-count';
            const chevron = header.querySelector('.sentinelpro-filter-chevron');
            if (chevron) {
                header.insertBefore(countSpan, chevron);
            } else {
                header.appendChild(countSpan);
            }
        }

        const section = header.closest('.sentinelpro-filter-item');
        let checkedCount = 0;

        if (selector) {
            // Use custom selector
            checkedCount = section?.querySelectorAll(selector).length || 0;
        } else {
            // Use default selector
            checkedCount = section?.querySelectorAll('.sentinelpro-checkbox-group input[type="checkbox"]:checked').length || 0;
        }

        countSpan.textContent = checkedCount > 0 ? checkedCount : '';
        
    }

    static updateStandardFilterCount(headerStartsWith) {
      const header = [...document.querySelectorAll('.sentinelpro-filter-item-header')].find(h => h.textContent.trim().toUpperCase().startsWith(headerStartsWith));
      if (!header) return;
      let countSpan = header.querySelector('.sentinelpro-filter-count');
      if (!countSpan) {
        countSpan = document.createElement('span');
        countSpan.className = 'sentinelpro-filter-count';
        header.insertBefore(countSpan, header.querySelector('.sentinelpro-filter-chevron'));
      }
      const section = header.closest('.sentinelpro-filter-item');
      const checked = section?.querySelectorAll('.sentinelpro-checkbox-group input[type="checkbox"]:checked').length || 0;
      countSpan.textContent = checked > 0 ? checked : '';
    }
  
    static updateDeviceFilterCount() {
      this.updateFilterCount('device', 'DEVICE');
    }
  
    static updateReferrerFilterCount() {
      this.updateFilterCount('referrer', 'REFERRER');
    }
  
    static updateOSFilterCount() {
      this.updateFilterCount('os', 'OPERATING SYSTEM');
    }
  
    static updateBrowserFilterCount() {
      this.updateFilterCount('browser', 'BROWSER');
    }
  

  
    static updateGeoFilterCount() {
      this.updateFilterCount('geo', 'GEO', '#geo-checkbox-group input[type="checkbox"]:checked, #geo-custom-checkboxes input[type="checkbox"]:checked');
    }
  
    static updateCustomDimensionCounts() {
      document.querySelectorAll('.sentinelpro-custom-checkboxes').forEach(group => {
        const dimension = group.getAttribute('data-dimension');
        const header = group.closest('.sentinelpro-filter-item')?.querySelector('.sentinelpro-filter-item-header');
        if (!header) return;
        let countSpan = header.querySelector('.sentinelpro-filter-count');
        if (!countSpan) {
          countSpan = document.createElement('span');
          countSpan.className = 'sentinelpro-filter-count';
          header.insertBefore(countSpan, header.querySelector('.sentinelpro-filter-chevron'));
        }
        const checked = group.querySelectorAll('input[type="checkbox"]:checked').length;
        countSpan.textContent = checked > 0 ? checked : '';
      });
    }

    /**
     * Generalized method to populate filter options for any dimension
     * @param {string} dimensionKey - The dimension key (e.g., 'device', 'geo', 'browser')
     * @param {string} headerText - The header text to find (e.g., 'DEVICE', 'GEO')
     * @param {Array} values - Array of {value, count} objects
     * @param {Object} options - Additional options
     */
    static populateFilterOptions(dimensionKey, headerText, values, options = {}) {
        const {
            maxItems = 10,
            showCounts = true,
            customSelector = null
        } = options;

        const header = [...document.querySelectorAll('.sentinelpro-filter-item-header')]
            .find(h => h.textContent.trim().toUpperCase().startsWith(headerText.toUpperCase()));
        
        if (!header) {
            return;
        }

        const section = header.closest('.sentinelpro-filter-item');
        if (!section) {
            return;
        }

        // Find the checkbox group
        let checkboxGroup = customSelector ? 
            section.querySelector(customSelector) : 
            section.querySelector('.sentinelpro-checkbox-group');

        if (!checkboxGroup) {
            return;
        }

        // Clear existing options (except checked ones)
        const checkedBoxes = checkboxGroup.querySelectorAll('input[type="checkbox"]:checked');
        const checkedValues = Array.from(checkedBoxes).map(cb => cb.value);
        
        checkboxGroup.innerHTML = '';

        // Add options
        values.slice(0, maxItems).forEach(({ value, count }) => {
            const label = document.createElement('label');
            label.className = 'sentinelpro-checkbox-label';
            
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.value = value;
            checkbox.checked = checkedValues.includes(value);
            
            const text = showCounts ? `${value} (${count})` : value;
            label.appendChild(checkbox);
            label.appendChild(document.createTextNode(` ${text}`));
            
            checkboxGroup.appendChild(label);
        });

        // Add "Show more" if there are more items
        if (values.length > maxItems) {
            const showMoreLabel = document.createElement('label');
            showMoreLabel.className = 'sentinelpro-checkbox-label sentinelpro-show-more';
            showMoreLabel.textContent = `... and ${values.length - maxItems} more`;
            checkboxGroup.appendChild(showMoreLabel);
        }

    }

    /**
     * Populate device filter options
     */
    static populateDeviceOptions(values, options = {}) {
        this.populateFilterOptions('device', 'DEVICE', values, options);
    }

    /**
     * Populate geo filter options
     */
    static populateGeoOptions(values, options = {}) {
        this.populateFilterOptions('geo', 'GEO', values, {
            customSelector: '#geo-checkbox-group',
            ...options
        });
    }

    /**
     * Populate browser filter options
     */
    static populateBrowserOptions(values, options = {}) {
        this.populateFilterOptions('browser', 'BROWSER', values, options);
    }

    /**
     * Populate OS filter options
     */
    static populateOSOptions(values, options = {}) {
        this.populateFilterOptions('os', 'OPERATING SYSTEM', values, options);
    }

    /**
     * Populate referrer filter options
     */
    static populateReferrerOptions(values, options = {}) {
        this.populateFilterOptions('referrer', 'REFERRER', values, options);
    }

    /**
     * Populate adblock filter options
     */
    static populateAdblockOptions(values, options = {}) {
        this.populateFilterOptions('adblock', 'AD BLOCK', values, options);
    }
  
    static updateTotalFilterCount() {
      let total = 0;
  
      const countChecked = (headerText) => {
        const header = [...document.querySelectorAll('.sentinelpro-filter-item-header')].find(h => h.textContent.trim().toUpperCase().startsWith(headerText));
        if (!header) return 0;
        const section = header.closest('.sentinelpro-filter-item');
        return section?.querySelectorAll('.sentinelpro-checkbox-group input[type="checkbox"]:checked').length || 0;
      };
  
      total += countChecked('DEVICE');
      total += document.querySelectorAll('#geo-checkbox-group input[type="checkbox"]:checked, #geo-custom-checkboxes input[type="checkbox"]:checked').length;
      total += countChecked('REFERRER');
      total += countChecked('OPERATING SYSTEM');
      total += countChecked('BROWSER');
      total += countChecked('AD BLOCK');
  
      document.querySelectorAll('.sentinelpro-custom-checkboxes').forEach(group => {
        total += group.querySelectorAll('input[type="checkbox"]:checked').length;
      });
  
      document.querySelectorAll('.sentinelpro-filter-select').forEach(sel => {
        if (sel.value && sel.value !== '') total++;
      });
  
      let totalCount = document.getElementById('sentinelpro-total-filter-count');
      if (!totalCount) {
        const header = document.querySelector('.sentinelpro-modal-header h3');
        if (header) {
          totalCount = document.createElement('span');
          totalCount.id = 'sentinelpro-total-filter-count';
          totalCount.style.marginLeft = '12px';
          totalCount.style.fontSize = '15px';
          totalCount.style.color = '#1976d2';
          header.appendChild(totalCount);
        }
      }
      if (totalCount) {
        totalCount.textContent = total > 0 ? `(${total})` : '';
      }
    }
  }

/**
 * Unified Filter Manager - Handles all dimensions with a single interface
 */
export class UnifiedFilterManager {
    constructor(dataFetcher) {
        this.dataFetcher = dataFetcher;
        this.dimensionConfigs = {
            device: {
                headerText: 'DEVICE',
                selector: '.sentinelpro-checkbox-group',
                populateMethod: 'populateDeviceOptions'
            },
            geo: {
                headerText: 'GEO',
                selector: '#geo-checkbox-group',
                populateMethod: 'populateGeoOptions'
            },
            browser: {
                headerText: 'BROWSER',
                selector: '.sentinelpro-checkbox-group',
                populateMethod: 'populateBrowserOptions'
            },
            os: {
                headerText: 'OPERATING SYSTEM',
                selector: '.sentinelpro-checkbox-group',
                populateMethod: 'populateOSOptions'
            },
            referrer: {
                headerText: 'REFERRER',
                selector: '.sentinelpro-checkbox-group',
                populateMethod: 'populateReferrerOptions'
            },
            adblock: {
                headerText: 'AD BLOCK',
                selector: '.sentinelpro-checkbox-group',
                populateMethod: 'populateAdblockOptions'
            }
        };
    }

    /**
     * Get dimension configuration
     * @param {string} dimensionKey - The dimension key
     * @returns {Object} Dimension configuration
     */
    getDimensionConfig(dimensionKey) {
        return this.dimensionConfigs[dimensionKey] || {
            headerText: dimensionKey.toUpperCase(),
            selector: '.sentinelpro-checkbox-group',
            populateMethod: null
        };
    }

    /**
     * Update filter count for any dimension
     * @param {string} dimensionKey - The dimension key
     */
    updateFilterCount(dimensionKey) {
        const config = this.getDimensionConfig(dimensionKey);
        FilterCountUpdater.updateFilterCount(dimensionKey, config.headerText, config.selector);
    }

    /**
     * Populate filter options for any dimension
     * @param {string} dimensionKey - The dimension key
     * @param {Object} options - Fetch options
     * @param {Object} populateOptions - Populate options
     */
    async populateFilterOptions(dimensionKey, options = {}, populateOptions = {}) {
        try {
            
            // Fetch dimension values
            const values = await this.dataFetcher.getDimensionValues(dimensionKey, options);
            
            // Get dimension configuration
            const config = this.getDimensionConfig(dimensionKey);
            
            // Use specific populate method if available
            if (config.populateMethod && FilterCountUpdater[config.populateMethod]) {
                FilterCountUpdater[config.populateMethod](values, populateOptions);
            } else {
                // Use generic populate method
                FilterCountUpdater.populateFilterOptions(dimensionKey, config.headerText, values, {
                    customSelector: config.selector,
                    ...populateOptions
                });
            }
            
            // Update filter count
            this.updateFilterCount(dimensionKey);
            
        } catch (error) {
        }
    }

    /**
     * Populate all filter options
     * @param {Object} options - Fetch options
     * @param {Object} populateOptions - Populate options
     */
    async populateAllFilterOptions(options = {}, populateOptions = {}) {
        const dimensions = Object.keys(this.dimensionConfigs);
        const promises = dimensions.map(dimensionKey => 
            this.populateFilterOptions(dimensionKey, options, populateOptions)
        );
        
        await Promise.all(promises);
    }

    /**
     * Update all filter counts
     */
    updateAllFilterCounts() {
        const dimensions = Object.keys(this.dimensionConfigs);
        dimensions.forEach(dimensionKey => this.updateFilterCount(dimensionKey));
        FilterCountUpdater.updateCustomDimensionCounts();
        FilterCountUpdater.updateTotalFilterCount();
    }

    /**
     * Add custom dimension configuration
     * @param {string} dimensionKey - The dimension key
     * @param {Object} config - The configuration
     */
    addCustomDimension(dimensionKey, config) {
        this.dimensionConfigs[dimensionKey] = {
            headerText: dimensionKey.toUpperCase(),
            selector: '.sentinelpro-checkbox-group',
            populateMethod: null,
            ...config
        };
    }
}

if (typeof window !== 'undefined') {
  window.FilterCounterUpdater = FilterCountUpdater;
}