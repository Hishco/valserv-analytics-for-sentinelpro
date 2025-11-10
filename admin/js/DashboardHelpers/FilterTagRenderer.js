// DashboardHelpers/FilterTagRenderer.js

export default class FilterTagRenderer {
    static updateFilterTags(containerId, filters) {
      // Always use the current checked state as the source of truth
      const instance = window.EnhancedDashboardInstance;
      if (instance && typeof instance.getSelectedFilters === 'function') {
        filters = instance.getSelectedFilters();
      }
      const container = document.getElementById(containerId);
      if (!container) return;
  
      let tagsHTML = '';

      // Helper to get human-readable label for any filter key
      const getFilterLabel = (key) => {
        // Try to find a generic dropdown wrapper first (case-insensitive)
        const genericWrappers = document.querySelectorAll('.sentinelpro-generic-dropdown-wrapper[data-dimension]');
        const genericWrapper = Array.from(genericWrappers).find(wrapper => {
          const dim = wrapper.getAttribute('data-dimension');
          return dim && dim.toLowerCase() === key.toLowerCase();
        });
        if (genericWrapper) {
          const dimCamel = genericWrapper.getAttribute('data-dimension');
          if (dimCamel) {
            return dimCamel.replace(/([A-Z])/g, ' $1').replace(/^./, s => s.toUpperCase()).trim();
          }
        }
        // Try to find a custom dimension group (case-insensitive)
        const customGroups = document.querySelectorAll('.sentinelpro-custom-checkboxes[data-dimension]');
        const customGroup = Array.from(customGroups).find(group => {
          const dim = group.getAttribute('data-dimension');
          return dim && dim.toLowerCase() === key.toLowerCase();
        });
        if (customGroup) {
          const dim = customGroup.getAttribute('data-dimension');
          if (dim) {
            return dim.replace(/([A-Z])/g, ' $1').replace(/^./, s => s.toUpperCase()).trim();
          }
        }
        // Standard filters mapping
        const standardLabels = {
          device: 'Device',
          geo: 'Geo',
          referrer: 'Referrer',
          os: 'Operating System',
          browser: 'Browser',
        };
        if (standardLabels[key]) return standardLabels[key];
        // Always convert camelCase or PascalCase to Title Case, capitalizing every word
        return key
          .replace(/([A-Z])/g, ' $1') // space before capitals
          .split(' ')
          .map(word => word.charAt(0).toUpperCase() + word.slice(1))
          .join(' ')
          .replace(/\s+/g, ' ')
          .trim();
      };

      const appendTags = (key, values) => {
        const label = getFilterLabel(key);
        values.forEach(val => {
          tagsHTML += `
            <span class="sentinelpro-filter-tag-pill" data-filter="${key}" data-value="${val}">
              ${label}: ${val} <button type="button" class="remove-filter">×</button>
            </span>
          `;
        });
      };
  
      for (const [key, value] of Object.entries(filters)) {
        if (key.endsWith('_mode')) continue; // Skip mode keys
        if (Array.isArray(value)) {
          appendTags(key, value);
        } else if (typeof value === 'string') {
          appendTags(key, [value]);
        }
      }
  
      container.innerHTML = tagsHTML;
  
      // Defensive conversion: fix any legacy tag classes
      document.querySelectorAll('.sentinelpro-filter-tag').forEach(el => {
        el.classList.remove('sentinelpro-filter-tag');
        el.classList.add('sentinelpro-filter-tag-pill');
      });
  
      if (typeof window.safeFetch === 'function') {
        window.safeFetch();
      }
    }
  
    static clearFilterTags(containerId) {
      const container = document.getElementById(containerId);
      if (container) {
        container.innerHTML = '';
      }
    }
  
    static removeFilterTag(tagElement) {
      if (!tagElement) return;
      const filterType = tagElement.getAttribute('data-filter');
      const filterValue = tagElement.getAttribute('data-value');

      if (filterType && filterValue) {
        
        // Check if this is a contains filter by looking for the _mode key
        const currentFilters = window.EnhancedDashboardInstance?.getSelectedFilters?.() || {};
        const isContainsFilter = currentFilters[filterType + '_mode'] === 'contains';
        
        if (isContainsFilter) {
          // Clear the contains search input
          const wrappers = document.querySelectorAll('.sentinelpro-generic-dropdown-wrapper[data-dimension]');
          wrappers.forEach(wrapper => {
            const dim = wrapper.getAttribute('data-dimension');
            if (dim && dim.toLowerCase() === filterType.toLowerCase()) {
              const containsInput = wrapper.querySelector('.sentinelpro-generic-contains-search');
              if (containsInput) {
                containsInput.value = '';
                containsInput.dispatchEvent(new Event('input', { bubbles: true }));
              }
            }
          });
          
          // Also clear custom dimension contains search inputs
          const customSearchInput = document.querySelector(`.sentinelpro-custom-search[data-dimension="${filterType}"]`);
          if (customSearchInput) {
            customSearchInput.value = '';
            customSearchInput.dispatchEvent(new Event('input', { bubbles: true }));
          } else {
            // Try case-insensitive matching for custom dimensions
            const allCustomSearchInputs = document.querySelectorAll('.sentinelpro-custom-search[data-dimension]');
            allCustomSearchInputs.forEach(input => {
              const inputDimension = input.getAttribute('data-dimension');
              if (inputDimension && inputDimension.toLowerCase() === filterType.toLowerCase()) {
                input.value = '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
              }
            });
          }
        }
        
        // Handle generic dropdowns (window stack)
        // Find any stackKey that matches the filterType case-insensitively
        const stackKey = Object.keys(window).find(k => k.startsWith('sentinelpro_') && k.endsWith('Stack') && k.slice('sentinelpro_'.length, -'Stack'.length).toLowerCase() === filterType.toLowerCase());
        if (stackKey && Array.isArray(window[stackKey])) {
          const before = [...window[stackKey]];
          window[stackKey] = window[stackKey].filter(v => v !== filterValue);
          // Uncheck the box in any matching dropdown wrapper (case-insensitive)
          const wrappers = document.querySelectorAll('.sentinelpro-generic-dropdown-wrapper[data-dimension]');
          wrappers.forEach(wrapper => {
            const dim = wrapper.getAttribute('data-dimension');
            if (dim && dim.toLowerCase() === filterType.toLowerCase()) {
              const dropdown = wrapper.querySelector('.sentinelpro-generic-dropdown');
              if (dropdown) {
                const checkbox = Array.from(dropdown.querySelectorAll('input[type="checkbox"]')).find(cb => cb.value === filterValue);
                if (checkbox) {
                  checkbox.checked = false;
                  checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                }
              }
            }
          });
        }
        // First, try to find a custom dimension group by data-dimension (case-insensitive)
        let customGroup = Array.from(document.querySelectorAll('.sentinelpro-custom-checkboxes')).find(
          el => el.getAttribute('data-dimension') && el.getAttribute('data-dimension').toLowerCase() === filterType.toLowerCase()
        );
        if (customGroup) {
          const checkboxes = customGroup.querySelectorAll('input[type="checkbox"]');
          checkboxes.forEach(cb => {
            if (cb.value.trim().toLowerCase() === filterValue.trim().toLowerCase()) {
              cb.checked = false;
              cb.dispatchEvent(new Event('change', { bubbles: true }));
            }
          });
        } else {
          // Fallback: default logic for standard dimensions
          const header = Array.from(document.querySelectorAll('.sentinelpro-filter-item-header'))
            .find(h => h.textContent.trim().toUpperCase().startsWith(filterType.toUpperCase()));
          if (header) {
            const section = header.closest('.sentinelpro-filter-item');
            if (section) {
              const checkboxes = section.querySelectorAll('input[type="checkbox"]');
              checkboxes.forEach(cb => {
                if (cb.value.toLowerCase() === filterValue.toLowerCase()) {
                  cb.checked = false;
                  cb.dispatchEvent(new Event('change', { bubbles: true }));
                }
              });
            }
          }
        }
        // Explicitly update counts (but do NOT apply filters or fetch)
        const instance = window.EnhancedDashboardInstance;
        if (instance) {
          instance.updateDeviceFilterCount?.();
          instance.updateGeoFilterCount?.();
          instance.updateReferrerFilterCount?.();
          instance.updateOSFilterCount?.();
          instance.updateBrowserFilterCount?.();
  
          instance.updateCustomDimensionCounts?.();
          instance.updateTotalFilterCount?.();
          // Log the selected filters after pill removal
          // Show loading spinner when removing a filter
          if (instance.showLoading) instance.showLoading();
        }
        if (window.orchestrator && typeof window.orchestrator.fetch === 'function') {
          window.orchestrator.fetch();
        }
      }

      tagElement.remove();
  
      if (typeof window.EnhancedDashboardInstance === 'object') {
        const instance = window.EnhancedDashboardInstance;
        const filters = instance.getSelectedFilters?.();
        if (filters && instance.updateFilterTags) {
          instance.updateFilterTags(filters);
        }
        instance?.updateDeviceFilterCount?.();
        instance?.updateGeoFilterCount?.();
        instance?.updateReferrerFilterCount?.();
        instance?.updateOSFilterCount?.();
        instance?.updateBrowserFilterCount?.();

        instance?.updateCustomDimensionCounts?.();
        instance?.updateTotalFilterCount?.();
        if (typeof window.safeFetch === 'function') {
          window.safeFetch();
        }
      }
    }
}

export function setupRemoveFilterTagListener() {
    if (window.sentinelproRemoveFilterHandlerAttached) return;
  
    window.sentinelproRemoveFilterHandlerCount = 0;
  
    document.addEventListener('pointerdown', (e) => {
      const tag = e.target.closest('.sentinelpro-filter-tag-pill');
      if (e.target.classList.contains('remove-filter') && tag) {
        window.sentinelproRemoveFilterHandlerCount++;
        e.preventDefault();
        e.stopPropagation();
  
        if (
          window.EnhancedDashboardInstance &&
          typeof window.EnhancedDashboardInstance.removeFilterTag === 'function'
        ) {
          window.EnhancedDashboardInstance.removeFilterTag(tag);
        }
      }
    });
  
    window.sentinelproRemoveFilterHandlerAttached = true;
  }
  