import { getElementReferences } from './DashboardHelpers/DOMInitializer.js';
import DatabaseCacheManager from "./DashboardHelpers/DatabaseCacheManager.js";
import DatabaseDataFetcher from './DashboardHelpers/DatabaseDataFetcher.js';
import TableRenderer from './DashboardHelpers/TableRenderer.js';
import DateUIManager from './DashboardHelpers/DateUIManager.js';
import FilterBuilder from './DashboardHelpers/FilterBuilder.js';
import StatusManager from './DashboardHelpers/StatusManager.js';
import CompareDatePicker from './DashboardHelpers/CompareDatePicker.js';
import DataOrchestrator from './DashboardHelpers/DataOrchestrator.js';
import { shouldChunk, fetchWithChunkRetry } from './DashboardHelpers/ChunkedFetchHelper.js';
import MetricStatePersistence from './DashboardHelpers/MetricStatePersistence.js';



document.addEventListener('DOMContentLoaded', async () => {

  function showOverlay() {    document.getElementById('sentinelpro-loading-overlay')?.style.setProperty('display', 'flex');
  }
  function hideOverlay() {
    document.getElementById('sentinelpro-loading-overlay')?.style.setProperty('display', 'none');
  }


  const refs = getElementReferences();
  window.refs = refs;


      const cacheManager = new DatabaseCacheManager();
  const dataFetcher = new DatabaseDataFetcher(window.valservDashboardData?.ajaxUrl);
  const status = new StatusManager('data-status');

  const filterBuilder = new FilterBuilder({
    metricSelect: refs.metricSelect,
    granularitySelect: refs.granularitySelect,
    startInput: refs.startInput,
    endInput: refs.endInput,
    eventTypeSelect: refs.eventTypeSelect,
    countryInput: refs.countryInput
  });

  const dateManager = new DateUIManager({
    startInput: refs.startInput,
    endInput: refs.endInput,
    granularitySelect: refs.granularitySelect,
    dateRangeInput: refs.dateRangeInput,
    customTag: refs.customTag,
    toggleBtn: refs.toggleBtn,
    filterBox: refs.filterBox
  });
  window.sentinelpro_dateManagerInstance = dateManager;

  // **CRITICAL CHANGE: Initialize dateManager FIRST**
  dateManager.initialize(); // This sets defaultStart and defaultEnd

  // Create table renderer for the existing table
  const tableElement = document.getElementById('sentinelpro-data-table');
  const paginationElement = document.getElementById('sentinelpro-pagination-controls');

  
  const tableRenderer = new TableRenderer(tableElement, paginationElement);
  
  const orchestrator = new DataOrchestrator({
    refs,
    dataFetcher,
    // chartRenderer, // Remove this if not needed for legacy charts
    tableRenderer,
    filterBuilder,
    status,
    dateManager
  });
  window.orchestrator = orchestrator;
  
  // Helper: decide if chunking is needed (over 30 days)

  // Patch orchestrator.fetch to use fetchWithChunkRetry for large date ranges
  const originalFetch = orchestrator.fetch.bind(orchestrator);
  orchestrator.fetch = async function(metricOverride) {
    metricOverride = metricOverride || 'traffic';
    let start, end, compareStart, compareEnd;
    if (window.EnhancedDashboardInstance) window.EnhancedDashboardInstance.showLoading();
    try {
      // Get current date range
      start = refs.startInput?.value;
      end = refs.endInput?.value;
      compareStart = refs.compareStartInput?.value;
      compareEnd = refs.compareEndInput?.value;
      const compareEnabled = document.getElementById('compare-toggle')?.checked;
      let mainData = null, compareData = null;
      // Unified chunking logic for both main and comparison ranges
      async function fetchChunkedData(startDate, endDate) {
        if (startDate && endDate && shouldChunk(startDate, endDate)) {
          const chunkParamsArray = [];
          let cur = new Date(startDate);
          const endDateObj = new Date(endDate);
          while (cur <= endDateObj) {
            const chunkStart = new Date(cur);
            const chunkEnd = new Date(cur);
            chunkEnd.setDate(chunkEnd.getDate() + 9);
            if (chunkEnd > endDateObj) chunkEnd.setTime(endDateObj.getTime());
            chunkParamsArray.push({
              metric: 'all',
              granularity: refs.granularitySelect?.value || 'daily',
              startDate: chunkStart.toISOString().split('T')[0],
              endDate: chunkEnd.toISOString().split('T')[0], // Use chunkEnd directly (inclusive)
              filters: orchestrator.filterBuilder.build(),
              postId: orchestrator.filterBuilder.build().get('post_id')
            });
            cur.setTime(chunkEnd.getTime());
            cur.setDate(cur.getDate() + 1);
          }
          return await fetchWithChunkRetry(chunkParamsArray, orchestrator, window.EnhancedDashboardInstance);
        } else {
          return await originalFetch(metricOverride);
        }
      }
      // Let the DataOrchestrator handle both main and comparison data fetching
      const result = await originalFetch(metricOverride);
      
      if (window.EnhancedDashboardInstance) {
        if (compareEnabled && result && result.comparisonData) {
          window.EnhancedDashboardInstance.setData(
            Array.isArray(result.data) ? result.data : result.data?.data || [],
            Array.isArray(result.comparisonData) ? result.comparisonData : result.comparisonData?.data || []
          );
        } else {
          window.EnhancedDashboardInstance.setData(Array.isArray(result.data) ? result.data : result.data?.data || []);
        }
      }
      return Array.isArray(result.data) ? result.data : result.data?.data || [];
    } finally {
      if (start && end && shouldChunk(start, end)) {
        // fetchWithChunkRetry handles hideLoading
      } else {
        if (window.EnhancedDashboardInstance) window.EnhancedDashboardInstance.hideLoading();
      }
    }
  };

  const comparePicker = new CompareDatePicker({
    startInput: document.getElementById('filter-start'),
    endInput: document.getElementById('filter-end'),
    compareStartInput: refs.compareStartInput,
    compareEndInput: refs.compareEndInput,
    dateRangeInput: refs.dateRangeInput,
    onRangeApplied: () => {
      dateManager.updateDateLabels();
      dateManager.checkCustomDateSelected();
      // orchestrator.fetch(refs.metricSelect?.value || 'sessions'); // REMOVE this line to prevent fetch on close
    },
  });
  
  // Initialize date picker with date range validation (async)
  comparePicker.initialize(dateManager.defaultStart, dateManager.defaultEnd).catch(error => {
  });


  // Skip setupFilterToggle since filterBox doesn't exist in current HTML
  // dateManager.setupFilterToggle();
  dateManager.updateDateLabels();
  MetricStatePersistence.restore(refs);


  window.addEventListener('rerender-table', () => {
    tableRenderer.render({
      data: orchestrator.raw || [],
      metric: orchestrator.currentMetric,
      granularity: refs.granularitySelect?.value || 'daily',
      start: refs.startInput?.value,
      end: refs.endInput?.value,
      requestedDates: orchestrator.requestedDatesGlobal || [],
      comparisonData: orchestrator.cachedCompareData || [],
      comparisonDates: orchestrator.cachedCompareDates || []
    });
  });

  // Listen for date range changes and trigger data fetch on Apply
  // Remove the apply.daterangepicker event handler for refs.dateRangeInput

  // ✅ Enforce single-select for custom dimensions and hide metric toggle on Apply
  function setupCustomDimensionToggleVisibility(orchestrator, refs) {
    const dimensionCheckboxes = document.querySelectorAll('#custom-dimension-sidebar .dimension-filter');
    const metricToggleWrapper = document.getElementById('metric-toggle-wrapper');
    const metricDropdown = document.getElementById('filter-metric');
    const granularityDropdown = document.getElementById('filter-granularity');

    function updateLockStateAndFetch() {
      const anyChecked = Array.from(dimensionCheckboxes).some(cb => cb.checked);

      // Lock/unlock metric dropdown
      if (metricDropdown) {
        metricDropdown.disabled = anyChecked;
        if (anyChecked) metricDropdown.value = 'custom_dimensions';
      }

      // Lock/unlock granularity dropdown
      if (granularityDropdown) {
        if (anyChecked) {
          granularityDropdown.value = 'daily';
          granularityDropdown.disabled = true; // Fully disable the select box
        } else {
          granularityDropdown.disabled = false; // Re-enable
        }
      }

      // Show/hide pill toggles
      if (metricToggleWrapper) {
        metricToggleWrapper.style.display = anyChecked ? 'none' : '';
      }

      // Trigger fetch
      const selectedMetric = metricDropdown?.value || 'traffic';
      orchestrator.fetch(selectedMetric);
    }

    dimensionCheckboxes.forEach(checkbox => {
      checkbox.addEventListener('change', function () {
        if (this.checked) {
          dimensionCheckboxes.forEach(cb => {
            if (cb !== this) cb.checked = false;
          });
        }
        updateLockStateAndFetch();
      });
    });

    // Run once on page load
    // updateLockStateAndFetch();
  }


  setupCustomDimensionToggleVisibility(orchestrator, refs);


  refs.resetChip?.addEventListener('click', () => {
    MetricStatePersistence.clear();
    dateManager.resetDates();
    dateManager.updateDateLabels(); // Ensure FROM display is updated
    
    orchestrator.fetch(refs.metricSelect?.value || 'traffic');
  });

  const rowsSelect = document.getElementById('rows-per-page-select');
  if (rowsSelect) {
    rowsSelect.addEventListener('change', (e) => {
      const value = parseInt(e.target.value, 10);
      if (!isNaN(value)) {
        tableRenderer.rowsPerPage = value;
        tableRenderer.page = 1;
        const event = new CustomEvent('rerender-table');
        window.dispatchEvent(event);
      }
    });
  }

  function selectAllMetrics() {
    document.querySelectorAll('.metric-traffic, .metric-avg').forEach(input => {
      input.checked = true;
    });
  }
  
  // Initial load
  selectAllMetrics();

  document.getElementById('empty-state-reset')?.addEventListener('click', () => {
    refs.resetChip?.click();
  });

  // Example usage after a chunked fetch:

  // Ensure EnhancedDashboardInstance exists
  if (!window.EnhancedDashboardInstance) {
    const ValservEnhancedDashboard = (await import('./enhanced-dashboard.js')).default;
    window.EnhancedDashboardInstance = new ValservEnhancedDashboard();
  } else {
  }

  // EnhancedDashboard handles date picker events - no need for duplicate listeners here

  // Fallback event listener for toggle filters button
  document.getElementById('toggle-filters')?.addEventListener('click', () => {
    if (window.EnhancedDashboardInstance && typeof window.EnhancedDashboardInstance.toggleFiltersVisibility === 'function') {
      window.EnhancedDashboardInstance.toggleFiltersVisibility();
    } else {
    }
  });

});
