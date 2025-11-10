// DashboardHelpers/EventBindingManager.js

import FilterTagRenderer from './FilterTagRenderer.js';
import FilterCounterUpdater from './FilterCounterUpdater.js';

export default class EventBindingManager {
  static bindCoreDashboardEvents(dashboard) {
    document.getElementById('property-select')?.addEventListener('change', e => {
      dashboard.onPropertyChange(e.target.value);
    });

    document.getElementById('metrics-select')?.addEventListener('change', e => {
      dashboard.currentMetric = e.target.value;
      dashboard.updateChart();
      dashboard.renderTable();
    });

    document.getElementById('filters-button')?.addEventListener('click', () => {
      dashboard.openFiltersModal();
    });

    document.getElementById('close-filters-modal')?.addEventListener('click', () => {
      dashboard.closeFiltersModal();
    });

    document.getElementById('apply-filters')?.addEventListener('click', () => {
      dashboard.applyFilters();
    });

    document.getElementById('clear-filters')?.addEventListener('click', () => {
      dashboard.clearFilters();
    });

    document.getElementById('clear-date-range')?.addEventListener('click', () => {
      dashboard.clearDateRange();
    });

    document.getElementById('toggle-filters')?.addEventListener('click', () => {
      dashboard.toggleFiltersVisibility();
    });

    document.getElementById('export-data-btn')?.addEventListener('click', e => {
      e.preventDefault();
      e.stopPropagation();
      dashboard.exportData();
    });

    document.getElementById('export-chart-btn')?.addEventListener('click', async e => {
      e.preventDefault();
      e.stopPropagation();
      await dashboard.chartRenderer.exportChartToPDF('sentinelpro-chart');
    });

    document.getElementById('export-chart-table-btn')?.addEventListener('click', async e => {
      e.preventDefault();
      e.stopPropagation();
      await dashboard.chartRenderer.exportChartAndTableToPDF('sentinelpro-chart', 'sentinelpro-data-table', '.sentinelpro-table-header');
    });

    document.addEventListener('click', e => {
      const tag = e.target.closest('.sentinelpro-filter-tag-pill');
      if (e.target.classList.contains('remove-filter') && tag) {
        e.preventDefault();
        e.stopPropagation();
        FilterTagRenderer.removeFilterTag(tag);
      }
    });
}

  static bindFilterItemToggle() {
    if (this._filterItemToggleBound) return; // Prevent multiple bindings
    document.addEventListener('click', e => {
      if (e.target.closest('.sentinelpro-filter-item-header')) {
        const filterItem = e.target.closest('.sentinelpro-filter-item');
        if (filterItem) {
          filterItem.classList.toggle('expanded');
        }
      }
    });
    this._filterItemToggleBound = true;
  }

  static bindCustomDimensionUI(dashboard) {
    document.querySelectorAll('.sentinelpro-custom-select-all').forEach(btn => {
      btn.addEventListener('click', () => {
        const dimension = btn.getAttribute('data-dimension');
        const checkboxes = document.querySelectorAll(`.sentinelpro-custom-checkboxes[data-dimension="${dimension}"] input[type="checkbox"]`);
        checkboxes.forEach(cb => (cb.checked = true));
        FilterCounterUpdater.updateCustomDimensionCounts();
        FilterCounterUpdater.updateTotalFilterCount();
      });
    });

    document.querySelectorAll('.sentinelpro-custom-deselect-all').forEach(btn => {
      btn.addEventListener('click', () => {
        const dimension = btn.getAttribute('data-dimension');
        const checkboxes = document.querySelectorAll(`.sentinelpro-custom-checkboxes[data-dimension="${dimension}"] input[type="checkbox"]`);
        checkboxes.forEach(cb => (cb.checked = false));
        FilterCounterUpdater.updateCustomDimensionCounts();
        FilterCounterUpdater.updateTotalFilterCount();
      });
    });

    document.querySelectorAll('.sentinelpro-custom-search').forEach(searchInput => {
      searchInput.addEventListener('input', e => {
        // Remove the dropdown filtering functionality - this is for actual filtering only
        // const dimension = e.target.getAttribute('data-dimension');
        // const searchTerm = e.target.value.toLowerCase();
        // const checkboxes = document.querySelectorAll(`.sentinelpro-custom-checkboxes[data-dimension="${dimension}"] label`);

        // checkboxes.forEach(label => {
        //   const text = label.textContent.toLowerCase();
        //   label.style.display = text.includes(searchTerm) ? '' : 'none';
        // });
        
        // Update mutual exclusivity state
        const dimension = e.target.getAttribute('data-dimension');
        updateCustomDimensionContainsState(dimension);
      });
      
      // Add Enter key handler for contains search
      searchInput.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
          e.preventDefault();
          if (window.EnhancedDashboardInstance?.applyFilters) {
            window.EnhancedDashboardInstance.applyFilters();
          }
        }
      });
    });
    
    // Add mutual exclusivity logic for custom dimensions
    function updateCustomDimensionContainsState(dimension) {
      const searchInput = document.querySelector(`.sentinelpro-custom-search[data-dimension="${dimension}"]`);
      const checkboxGroup = document.querySelector(`.sentinelpro-custom-checkboxes[data-dimension="${dimension}"]`);
      
      if (!searchInput || !checkboxGroup) return;
      
      const hasSearchValue = searchInput.value && searchInput.value.trim().length > 0;
      const checkboxes = checkboxGroup.querySelectorAll('input[type="checkbox"]');
      
      // Disable checkboxes if contains search has value
      checkboxes.forEach(cb => {
        cb.disabled = hasSearchValue;
      });
      
      // Disable contains search if any checkbox is checked
      const hasCheckedBoxes = checkboxGroup.querySelectorAll('input[type="checkbox"]:checked').length > 0;
      searchInput.disabled = hasCheckedBoxes;
    }
    
    // Set up mutual exclusivity for all custom dimensions
    document.querySelectorAll('.sentinelpro-custom-checkboxes').forEach(checkboxGroup => {
      const dimension = checkboxGroup.getAttribute('data-dimension');
      const checkboxes = checkboxGroup.querySelectorAll('input[type="checkbox"]');
      
      checkboxes.forEach(cb => {
        cb.addEventListener('change', () => {
          updateCustomDimensionContainsState(dimension);
        });
      });
    });
    
    // Initialize mutual exclusivity state
    document.querySelectorAll('.sentinelpro-custom-search').forEach(searchInput => {
      const dimension = searchInput.getAttribute('data-dimension');
      setTimeout(() => updateCustomDimensionContainsState(dimension), 100);
    });

    // Listen for changes to any filter checkbox and update counts
    document.addEventListener('change', e => {
      if (e.target.closest('.sentinelpro-custom-checkboxes')) {
        FilterCounterUpdater.updateCustomDimensionCounts();
        FilterCounterUpdater.updateTotalFilterCount();
      }
      if (e.target.closest('.sentinelpro-checkbox-group')) {
        FilterCounterUpdater.updateDeviceFilterCount();
        FilterCounterUpdater.updateReferrerFilterCount();
        FilterCounterUpdater.updateOSFilterCount();
        FilterCounterUpdater.updateBrowserFilterCount();

        FilterCounterUpdater.updateGeoFilterCount();
        FilterCounterUpdater.updateTotalFilterCount();
      }
    });
  }
}