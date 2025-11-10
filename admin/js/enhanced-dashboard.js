// Enhanced Dashboard with Chart.js Integration

import { showFailedChunksWarning, hideFailedChunksWarning } from './DashboardHelpers/ChunkedFetchHelper.js';
import ChartRenderer from './DashboardHelpers/ChartRenderer.js';
import TableRenderer from './DashboardHelpers/TableRenderer.js';
import DatabaseCacheManager from './DashboardHelpers/DatabaseCacheManager.js';
import CsvExporter from './DashboardHelpers/CsvExporter.js';
import DateUIManager from './DashboardHelpers/DateUIManager.js';
import DimensionFilterController from './DashboardHelpers/DimensionFilterController.js';
import { setupGeoSearchBox } from './DashboardHelpers/GeoCountryHelper.js';
import { setupLegacyContentTypeDropdownUI, setupContentTypeFilterCounter, setupContentTypeContainsDisableLogic, setupContentTypeExactMatchDisableLogic } from './DashboardHelpers/ContentTypeDropdown.js';
import { setupGenericDropdownListeners } from './DashboardHelpers/GenericDropdownHelper.js';
import FilterTagRenderer, { setupRemoveFilterTagListener } from './DashboardHelpers/FilterTagRenderer.js';
import FilterCollector from './DashboardHelpers/FilterCollector.js';
import CustomDimensionManager from './DashboardHelpers/CustomDimensionManager.js';
import { processAndAggregateData } from './DashboardHelpers/DataProcessor.js';
import EventBindingManager from './DashboardHelpers/EventBindingManager.js';
import { shouldSkipRedundantUpdate, updateBufferedData } from './DashboardHelpers/DataBufferManager.js';
import { applyFilters } from './DashboardHelpers/FilterApplicationManager.js';
import { fetchClearanceLevel } from './DashboardHelpers/ClearanceHelper.js';
import { showLoadingOverlay, hideLoadingOverlay } from './DashboardHelpers/LoadingOverlay.js';
import { setupOrchestratorInstance } from './DashboardHelpers/OrchestratorInitializer.js';
import StatusManager from './DashboardHelpers/StatusManager.js';

class ValservEnhancedDashboard {
    constructor() {
        this.isFetching = false;
        this.lastValidStartDate = null;
        this.lastValidEndDate = null;
        this.chartRenderer = new ChartRenderer();
        this.dateUIManager = new DateUIManager({});
        this.tableRenderer = new TableRenderer(
            document.getElementById('sentinelpro-data-table'),
            document.getElementById('sentinelpro-table-pagination')
        );
        this.status = new StatusManager('data-status');
        // Set up date UI manager with relevant DOM elements
        this.dateUIManager.setInputs({
            dateRangeText: document.getElementById('date-range-text'),
            startInput: document.getElementById('filter-start'),
            endInput: document.getElementById('filter-end'),
            dateRangeInput: document.getElementById('filter-daterange'),
        });
        // Instantiate DimensionFilterController to handle all dimension/filter UI and state
        this.dimensionFilterController = new DimensionFilterController({
            orchestrator: window.orchestrator,
            filterBuilder: window.orchestrator ? window.orchestrator.filterBuilder : null
        });
        this.init();
    }

    updateAllFilterCounts() {
        [
            this.updateDeviceFilterCount,
            this.updateGeoFilterCount,
            this.updateReferrerFilterCount,
            this.updateOSFilterCount,
            this.updateBrowserFilterCount,
        
            this.updateCustomDimensionCounts,
            this.updateTotalFilterCount
        ].forEach(fn => fn?.call(this));
    }

    async init() {
        this.isFetching = true;
        // Always fetch clearance from backend
        this.clearance = await fetchClearanceLevel();
        // Set default metric on initial load
        const metricSelect = document.getElementById('metrics-select');
        if (metricSelect && metricSelect.value) {
            this.currentMetric = metricSelect.value;
        } else {
            this.currentMetric = 'sessions';
        }
        this.showLoading();
        this.setupEventListeners();
        this.renderTable();
        this.dateUIManager.updateDateRangeDisplay();
        // --- Force popup checkboxes to be the source of truth on initial load ---
        const deviceSection = document.querySelector('.sentinelpro-filter-item-header[data-api-key="device"]')?.closest('.sentinelpro-filter-item');
        if (deviceSection) {
            // Uncheck all device checkboxes
            deviceSection.querySelectorAll('.sentinelpro-checkbox-group input[type="checkbox"]').forEach(cb => {
                cb.checked = false;
            });
            // Check all device checkboxes by default
            deviceSection.querySelectorAll('.sentinelpro-checkbox-group input[type="checkbox"]').forEach(cb => {
                cb.checked = true;
            });
        }
        // Now update everything from the checked state
        this.updateAllFilterCounts();
        this.updateFilterTags?.(this.getSelectedFilters());
        this.attachCheckboxSyncListeners();
        this.applyFilters();
    }

    setupEventListeners() {
        EventBindingManager.bindCoreDashboardEvents(this);
        
        // Add refresh dimensions configuration button handler
        const refreshDimensionsBtn = document.getElementById('refresh-dimensions-config');
        if (refreshDimensionsBtn) {
            refreshDimensionsBtn.addEventListener('click', this.refreshDimensionsConfiguration.bind(this));
        }
        

        
        EventBindingManager.bindFilterItemToggle();
        EventBindingManager.bindCustomDimensionUI(this);
      }

      setData(data, comparisonData = null) {
      
        if (shouldSkipRedundantUpdate(data, this._bufferedMainData, this._bufferedComparisonData)) {
          return;
        }
      
        updateBufferedData(this, data, comparisonData);
      
        if (this._bufferedMainData && this._bufferedComparisonData !== undefined) {
          this._processAndRenderData();
          if (Array.isArray(data) && data.length > 0 && this.status) {
            this.status.clear();
          }
        }
      
        if (Array.isArray(data) && data.length === 0 && !this.isFetching && this.status) {
          this.status.show('No data available for the selected range.', true);
        }
        this.hideLoading(); // Ensure loading spinner is hidden after data loads
      }
      

    setComparisonData(comparisonData) {
        this._bufferedComparisonData = comparisonData;
        if (this._bufferedMainData && (this._bufferedComparisonData !== null)) {
            this._processAndRenderData();
        }
    }

    _processAndRenderData() {
        const filters = FilterCollector.getSelectedFilters();
        

        
        // Process only main data through the data processor
        const { aggregatedMain, alignedComparison } = processAndAggregateData(this._bufferedMainData, this._bufferedComparisonData, filters);
        

        
        this.currentData = aggregatedMain;
        // Use the processed comparison data (with original dates like old system)
        this.comparisonData = alignedComparison;
        

      
        this.updateChart();
        this.renderTable();
      
        this.lastValidStartDate = document.getElementById('filter-start')?.value;
        this.lastValidEndDate = document.getElementById('filter-end')?.value;
    }

    updateChart() {
        // Get current start and end date from the UI
        const startInput = document.getElementById('filter-start');
        const endInput = document.getElementById('filter-end');
        const compareToggle = document.getElementById('sentinelpro-compare-toggle') || document.getElementById('compare-toggle');
        const compareStartInput = document.getElementById('sentinelpro-compare-start') || document.getElementById('compare-start');
        const compareEndInput = document.getElementById('sentinelpro-compare-end') || document.getElementById('compare-end');
        const comparisonEnabled = compareToggle && compareToggle.checked && compareStartInput && compareEndInput && compareStartInput.value && compareEndInput.value;
        const startDate = startInput?.value;
        const endDate = endInput?.value;
        let filteredData = this.currentData || [];
        let filteredComparison = this.comparisonData || [];
        
        // Only filter main data by date range, NOT comparison data
        if (startDate && endDate) {
            filteredData = filteredData.filter(row => row.date >= startDate && row.date <= endDate);
            // Comparison data should keep its own date range - do NOT filter it
        }
        

        
        this.chartRenderer.renderChart({
            chartDivId: 'sentinelpro-chart',
            data: [...filteredData], // force new reference
            comparisonData: comparisonEnabled ? [...filteredComparison] : [], // only show if enabled
            metric: this.currentMetric,
            getMetricDisplayName: this.getMetricDisplayName.bind(this)
        });
    }

    renderTable() {
        // Get current start and end date from the UI
        const startInput = document.getElementById('filter-start');
        const endInput = document.getElementById('filter-end');
        const compareToggle = document.getElementById('sentinelpro-compare-toggle') || document.getElementById('compare-toggle');
        const compareStartInput = document.getElementById('sentinelpro-compare-start') || document.getElementById('compare-start');
        const compareEndInput = document.getElementById('sentinelpro-compare-end') || document.getElementById('compare-end');
        const comparisonEnabled = compareToggle && compareToggle.checked && compareStartInput && compareEndInput && compareStartInput.value && compareEndInput.value;
        const startDate = startInput?.value;
        const endDate = endInput?.value;
        let filteredData = this.currentData || [];
        let filteredComparison = this.comparisonData || [];
        
        // Only filter main data by date range, NOT comparison data
        if (startDate && endDate) {
            filteredData = filteredData.filter(row => row.date >= startDate && row.date <= endDate);
            // Comparison data should keep its own date range - do NOT filter it
        }
        

        this.tableRenderer.render({
            data: [...filteredData],
            metric: this.currentMetric,
            granularity: 'daily', // or determine from UI if needed
            start: startDate,
            end: endDate,
            requestedDates: filteredData ? filteredData.map(row => row.date) : [],
            comparisonData: comparisonEnabled ? [...filteredComparison] : [],
            comparisonDates: comparisonEnabled ? filteredComparison.map(row => row.date) : []
        });
    }

    formatNumber(num) {
        return num.toLocaleString();
    }

    getMetricDisplayName(metric) {
        const names = {
            'sessions': 'Sessions',
            'visits': 'Visits',
            'views': 'Views',
            'bounce_rate': 'Bounce Rate'
        };
        return names[metric] || metric;
    }

    onPropertyChange(property) {
        // Property change handler
    }

    onDimensionChange(dimension) {
        // Dimension change handler
    }

    onCompareChange(compareType) {
        // Here you would typically load comparison data
    }

    openFiltersModal() {
        const modal = document.getElementById('filters-modal');
        if (modal) {
            modal.style.display = 'flex';
            setupGenericDropdownListeners();
            // Re-sync checkboxes and pills from the checked state
            this.updateAllFilterCounts();
            this.updateFilterTags?.(this.getSelectedFilters());
            this.attachCheckboxSyncListeners();
        }
    }

    closeFiltersModal() {
        const modal = document.getElementById('filters-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    applyFilters() {
        applyFilters(this);
    }

    clearFilters() {
        FilterCollector.clearFilters();
        FilterTagRenderer.clearFilterTags('filter-tags-container');
        this.updateTotalFilterCount();
    }

    exportData() {
        // Use CsvExporter for robust CSV export
        const metric = this.currentMetric || 'sessions';
        const granularity = 'daily'; // or determine from UI if needed
        const start = document.getElementById('filter-start')?.value;
        const end = document.getElementById('filter-end')?.value;
        const metricSet = ['sessions', 'visits', 'views'];
        
        // Get current sorting state from table renderer
        const currentSortKey = this.tableRenderer?.sortKey || 'date';
        const currentSortAsc = this.tableRenderer?.sortAsc !== undefined ? this.tableRenderer.sortAsc : true;
        
        const exporter = new CsvExporter(this.currentData || [], {
            metric,
            granularity,
            metricSet,
            start,
            end,
            requestedDates: this.currentData ? this.currentData.map(row => row.date) : [],
            sortKey: currentSortKey,
            sortAsc: currentSortAsc
        });
        exporter.export();
    }

    clearDateRange() {
        if (this.comparePicker && typeof this.comparePicker.clearDateRange === 'function') {
            this.comparePicker.clearDateRange();
        }
    }

    toggleFiltersVisibility() {
        const topControls = document.querySelector('.sentinelpro-top-controls');
        const selectedFilters = document.querySelector('.sentinelpro-selected-filters');
        const toggleBtn = document.getElementById('toggle-filters');
        
        if (topControls && selectedFilters && toggleBtn) {
            // Use getComputedStyle to check the actual display value
            const topControlsDisplay = window.getComputedStyle(topControls).display;
            const selectedFiltersDisplay = window.getComputedStyle(selectedFilters).display;
            const isHidden = topControlsDisplay === 'none' || selectedFiltersDisplay === 'none';
            
            // Toggle visibility
            topControls.style.display = isHidden ? 'flex' : 'none';
            selectedFilters.style.display = isHidden ? 'flex' : 'none';
            
            // Update button text
            toggleBtn.textContent = isHidden ? 'Hide Filters' : 'Show Filters';
        }
    }

    updateDateRangeDisplay() {
        const dateRangeText = document.getElementById('date-range-text');
        const startInput = document.getElementById('filter-start');
        const endInput = document.getElementById('filter-end');
        const dateRangeInput = document.getElementById('filter-daterange');
        if (dateRangeText && startInput && endInput && startInput.value && endInput.value) {
            const startDate = new Date(startInput.value);
            const endDate = new Date(endInput.value);
            const formatDate = (date) => {
                return date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit'
                }).replace(/(\d+)\/(\d+)\/(\d+)/, '$3-$1-$2');
            };
            dateRangeText.textContent = `${formatDate(startDate)} TO ${formatDate(endDate)}`;
            // Overwrite logic removed to prevent hidden fields from being reset
        }
    }

    formatDate(date) {
        if (typeof date === 'string') return date;
        return date.toISOString().split('T')[0];
    }

    // Helper to update the hidden field with custom dimension search values
    updateCustomDimensionHiddenField() {
        CustomDimensionManager.updateHiddenField();
    }
    
    showFailedChunksWarning(failedChunks, retryCallback) {
        showFailedChunksWarning(failedChunks, retryCallback);
    }
      
    hideFailedChunksWarning() {
        hideFailedChunksWarning();
    }
      
    showLoading() {
        showLoadingOverlay();
    }
    
    hideLoading() {
        hideLoadingOverlay();
    }    

    getSelectedFilters() {
        return FilterCollector.getSelectedFilters();
    }

    updateDeviceFilterCount() {
        if (window.FilterCounterUpdater) window.FilterCounterUpdater.updateDeviceFilterCount();
    }
    updateGeoFilterCount() {
        if (window.FilterCounterUpdater) window.FilterCounterUpdater.updateGeoFilterCount();
    }
    updateReferrerFilterCount() {
        if (window.FilterCounterUpdater) window.FilterCounterUpdater.updateReferrerFilterCount();
    }
    updateOSFilterCount() {
        if (window.FilterCounterUpdater) window.FilterCounterUpdater.updateOSFilterCount();
    }
    updateBrowserFilterCount() {
        if (window.FilterCounterUpdater) window.FilterCounterUpdater.updateBrowserFilterCount();
    }

    updateCustomDimensionCounts() {
        if (window.FilterCounterUpdater) window.FilterCounterUpdater.updateCustomDimensionCounts();
    }
    updateTotalFilterCount() {
        if (window.FilterCounterUpdater) window.FilterCounterUpdater.updateTotalFilterCount();
    }
    updateFilterTags(filters) {
        if (window.FilterTagRenderer) window.FilterTagRenderer.updateFilterTags('filter-tags-container', filters);
    }

    syncPopupCheckboxesWithSelectedFilters() {
        // Uncheck all checkboxes first
        document.querySelectorAll('.sentinelpro-checkbox-group input[type="checkbox"]').forEach(cb => {
            cb.checked = false;
        });
        // Now check only those that match the selected filters
        const selected = this.getSelectedFilters();
        Object.entries(selected).forEach(([key, values]) => {
            if (Array.isArray(values)) {
                values.forEach(val => {
                    const header = Array.from(document.querySelectorAll('.sentinelpro-filter-item-header'))
                        .find(h => h.dataset.apiKey === key);
                    if (header) {
                        const section = header.closest('.sentinelpro-filter-item');
                        if (section) {
                            const checkbox = section.querySelector('.sentinelpro-checkbox-group input[type="checkbox"][value="' + val + '"]');
                            if (checkbox) checkbox.checked = true;
                        }
                    }
                });
            }
        });
        this.updateAllFilterCounts();
    }

    attachCheckboxSyncListeners() {
        document.querySelectorAll('.sentinelpro-checkbox-group input[type="checkbox"]').forEach(cb => {
            cb.addEventListener('change', function() {
                const instance = window.EnhancedDashboardInstance;
                if (instance) {
                    instance.updateDeviceFilterCount?.();
                    instance.updateGeoFilterCount?.();
                    instance.updateReferrerFilterCount?.();
                    instance.updateOSFilterCount?.();
                    instance.updateBrowserFilterCount?.();
            
                    instance.updateCustomDimensionCounts?.();
                    instance.updateTotalFilterCount?.();
                    instance.updateFilterTags?.(instance.getSelectedFilters());
                }
            });
        });
    }
    
    async refreshDimensionsConfiguration() {
        const button = document.getElementById('refresh-dimensions-config');
        if (!button) return;
        
        // Disable button and show loading state
        button.disabled = true;
        button.textContent = '🔄 Refreshing...';
        
        try {
            const response = await fetch(SentinelProAjax.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'sentinelpro_refresh_dimensions_configuration',
                    nonce: SentinelProAjax.nonce || ''
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Show success message
                alert(`✅ ${result.data.message}\n\nFound ${result.data.count} dimensions:\n${result.data.dimensions.join(', ')}\n\nPlease refresh the page to see the updated dimensions.`);
                
                // Optionally reload the page to show updated dimensions
                if (confirm('Would you like to refresh the page to see the updated dimensions?')) {
                    window.location.reload();
                }
            } else {
                alert(`❌ Error: ${result.data.message}`);
            }
            
        } catch (error) {
            alert('❌ Error refreshing dimensions configuration. Please try again.');
        } finally {
            // Re-enable button
            button.disabled = false;
            button.textContent = '🔄 Refresh Dimensions';
        }
    }
}

// Initialize the enhanced dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Wait for all required dependencies to load
    const checkDependencies = setInterval(() => {
        if (typeof window.Chart !== 'undefined' && typeof window.jQuery !== 'undefined' && typeof moment !== 'undefined') {
            clearInterval(checkDependencies);
            const dashboard = new ValservEnhancedDashboard();
            window.EnhancedDashboardInstance = dashboard;
            setupLegacyContentTypeDropdownUI();
            setupContentTypeFilterCounter();
            setupContentTypeContainsDisableLogic();
            setupContentTypeExactMatchDisableLogic();
            setupRemoveFilterTagListener();
            setupOrchestratorInstance(dashboard);
        }
    }, 100);
    
    // Fallback if dependencies don't load within 10 seconds
    setTimeout(() => {
        clearInterval(checkDependencies);
    }, 10000);

    setupGeoSearchBox();
}); 

export default ValservEnhancedDashboard; 