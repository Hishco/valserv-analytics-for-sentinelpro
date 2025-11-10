// DashboardHelpers/FilterApplicationManager.js

import FilterCollector from './FilterCollector.js';
import FilterTagRenderer from './FilterTagRenderer.js';
import FilterCounterUpdater from './FilterCounterUpdater.js';
import FilterPersistence from './FilterPersistence.js';

export function applyFilters(dashboard) {
  // Show loading spinner when filters are applied
  if (dashboard.showLoading) dashboard.showLoading();
  // Update hidden field before collecting filters
  dashboard.updateCustomDimensionHiddenField();

  // Collect selected filters
  const filters = FilterCollector.getSelectedFilters();
  FilterTagRenderer.updateFilterTags('filter-tags-container', filters);

  // Close modal
  dashboard.closeFiltersModal();


  // Update UI filter counts
  FilterCounterUpdater.updateDeviceFilterCount();
  FilterCounterUpdater.updateReferrerFilterCount();
  FilterCounterUpdater.updateOSFilterCount();
  FilterCounterUpdater.updateBrowserFilterCount();
  
  FilterCounterUpdater.updateCustomDimensionCounts();
  FilterCounterUpdater.updateTotalFilterCount();

  // Update active dimension keys
  dashboard.activeDimensionKeys = new Set(FilterPersistence.collectActiveDimensions());
  FilterPersistence.applyToGlobalNamespace();
  window.sentinelpro_activeDimensions = Array.from(dashboard.activeDimensionKeys || []);

  // Optionally log request payload
  if (window.orchestrator?.filterBuilder?.build) {
    const payload = {};
    window.orchestrator.filterBuilder.build().forEach((v, k) => {
      payload[k] = v;
    });
  }

  // Trigger new fetch
  if (typeof window.orchestrator?.fetch === 'function') {
    window.orchestrator.fetch();
  }

  const startInput = document.getElementById('filter-start');
  const endInput = document.getElementById('filter-end');
}