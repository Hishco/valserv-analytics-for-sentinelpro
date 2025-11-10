// DashboardHelpers/FilterCollector.js

export default class FilterCollector {
    static getSelectedFilters() {
        const filters = {};

        // DEVICE
        const deviceHeader = Array.from(document.querySelectorAll('.sentinelpro-filter-item-header')).find(h => h.textContent.trim().toUpperCase().startsWith('DEVICE'));
        if (deviceHeader) {
            const deviceSection = deviceHeader.closest('.sentinelpro-filter-item');
            if (deviceSection) {
                const deviceFilters = Array.from(deviceSection.querySelectorAll('.sentinelpro-checkbox-group input[type="checkbox"]:checked')).map(cb => cb.value);
                if (deviceFilters.length > 0) filters.device = deviceFilters;
            }
        }

        // GEO
        const geoHeader = Array.from(document.querySelectorAll('.sentinelpro-filter-item-header')).find(h => h.textContent.trim().toUpperCase().startsWith('GEO'));
        if (geoHeader) {
            const geoSection = geoHeader.closest('.sentinelpro-filter-item');
            if (geoSection) {
                let geoFilters = Array.from(geoSection.querySelectorAll('#geo-checkbox-group input[type="checkbox"]:checked')).map(cb => cb.value);
                geoFilters = geoFilters.concat(Array.from(geoSection.querySelectorAll('#geo-custom-checkboxes input[type="checkbox"]:checked')).map(cb => cb.value));
                if (geoFilters.length > 0) filters.geo = geoFilters;
            }
        }

        // REFERRER
        const referrerHeader = Array.from(document.querySelectorAll('.sentinelpro-filter-item-header')).find(h => h.textContent.trim().toUpperCase().startsWith('REFERRER'));
        if (referrerHeader) {
            const referrerSection = referrerHeader.closest('.sentinelpro-filter-item');
            if (referrerSection) {
                const referrerFilters = Array.from(referrerSection.querySelectorAll('.sentinelpro-checkbox-group input[type="checkbox"]:checked')).map(cb => cb.value);
                if (referrerFilters.length > 0) filters.referrer = referrerFilters;
            }
        }

        // OPERATING SYSTEM
        const osHeader = Array.from(document.querySelectorAll('.sentinelpro-filter-item-header')).find(h => h.textContent.trim().toUpperCase().startsWith('OPERATING SYSTEM'));
        if (osHeader) {
            const osSection = osHeader.closest('.sentinelpro-filter-item');
            if (osSection) {
                const osFilters = Array.from(osSection.querySelectorAll('.sentinelpro-checkbox-group input[type="checkbox"]:checked')).map(cb => cb.value);
                if (osFilters.length > 0) filters.os = osFilters;
            }
        }

        // BROWSER
        const browserHeader = Array.from(document.querySelectorAll('.sentinelpro-filter-item-header')).find(h => h.textContent.trim().toUpperCase().startsWith('BROWSER'));
        if (browserHeader) {
            const browserSection = browserHeader.closest('.sentinelpro-filter-item');
            if (browserSection) {
                const browserFilters = Array.from(browserSection.querySelectorAll('.sentinelpro-checkbox-group input[type="checkbox"]:checked')).map(cb => cb.value);
                if (browserFilters.length > 0) filters.browser = browserFilters;
            }
        }

        // ADBLOCK
        const adblockHeader = Array.from(document.querySelectorAll('.sentinelpro-filter-item-header')).find(h => h.textContent.trim().toUpperCase().startsWith('ADBLOCK'));
        if (adblockHeader) {
            const adblockSection = adblockHeader.closest('.sentinelpro-filter-item');
            if (adblockSection) {
                const adblockFilters = Array.from(adblockSection.querySelectorAll('.sentinelpro-checkbox-group input[type="checkbox"]:checked')).map(cb => cb.value);
                if (adblockFilters.length > 0) filters.adblock = adblockFilters;
            }
        }

        // Custom dimensions
        document.querySelectorAll('.sentinelpro-custom-checkboxes').forEach(checkboxGroup => {
            const dimension = checkboxGroup.getAttribute('data-dimension');
            const checkedBoxes = checkboxGroup.querySelectorAll('input[type="checkbox"]:checked');
            if (checkedBoxes.length > 0) {
                const values = Array.from(checkedBoxes).map(cb => cb.value);
                // Use the original dimension name, not lowercase
                filters[dimension] = values;
            }
        });

        // Custom dimension contains search (like generic dropdowns)
        document.querySelectorAll('.sentinelpro-custom-search').forEach(searchInput => {
            const dimension = searchInput.getAttribute('data-dimension');
            const value = searchInput.value.trim();
            
            if (dimension && value && value.length > 0) {
                // Use the original dimension name, not lowercase
                // Only add if no checkboxes are checked for this dimension (mutual exclusivity)
                const checkboxGroup = document.querySelector(`.sentinelpro-custom-checkboxes[data-dimension="${dimension}"]`);
                const hasCheckedBoxes = checkboxGroup && checkboxGroup.querySelectorAll('input[type="checkbox"]:checked').length > 0;
                
                if (!hasCheckedBoxes) {
                    filters[dimension] = value;
                    filters[dimension + '_mode'] = 'contains';
                }
            }
        });

        // Generic dropdowns (like the old working version)
        document.querySelectorAll('.sentinelpro-generic-dropdown-wrapper').forEach(wrapper => {
            const dimCamel = wrapper.getAttribute('data-dimension');
            if (!dimCamel) return;
            
            const containsInput = wrapper.querySelector('.sentinelpro-generic-contains-search');
            const dropdown = wrapper.querySelector('.sentinelpro-generic-dropdown');
            
            if (containsInput && containsInput.value && containsInput.value.trim().length > 0) {
                filters[dimCamel] = containsInput.value.trim();
                filters[dimCamel + '_mode'] = 'contains';
            } else if (dropdown) {
                const checked = Array.from(dropdown.querySelectorAll('input[type="checkbox"]:checked')).map(cb => cb.value);
                if (checked.length > 0) {
                    filters[dimCamel] = checked;
                    filters[dimCamel + '_mode'] = 'exact';
                }
            }
        });

        // Also check window stacks for generic dropdowns (fallback)
        for (const key in window) {
            if (key.startsWith('sentinelpro_') && key.endsWith('Stack')) {
                const apiKey = key.slice('sentinelpro_'.length, -'Stack'.length);
                const checked = Array.isArray(window[key]) ? window[key] : [];
                if (checked.length > 0) {
                    // Only add if not already set by DOM check above
                    if (!filters[apiKey]) {
                        filters[apiKey] = checked;
                    }
                }
            }
        }
        return filters;
    }
  
    static clearFilters() {
      document.querySelectorAll('.sentinelpro-checkbox-group input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
      });
  
      document.querySelectorAll('.sentinelpro-custom-checkboxes input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
      });
  
      document.querySelectorAll('.sentinelpro-filter-select').forEach(sel => {
        sel.value = '';
      });
    }
  }