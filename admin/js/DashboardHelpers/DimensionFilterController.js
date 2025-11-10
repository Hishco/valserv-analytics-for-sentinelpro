export default class DimensionFilterController {
    constructor({ orchestrator, filterBuilder }) {
        this.orchestrator = orchestrator;
        this.filterBuilder = filterBuilder;
        this.selectedDimension = null;
        this.filterDimension = null;
        this.filterValue = null;
        
        // Available dimensions with their display names
        this.availableDimensions = {
            'country': 'Country',
            'device': 'Device',
            'browser': 'Browser',
            'os': 'Operating System',
            'source': 'Source',
            'medium': 'Medium',
            'campaign': 'Campaign'
        };
        
        this.initialize();
    }
    
    initialize() {
        this.setupDimensionSelect();
        this.setupFilterControls();
        this.setupEventListeners();
    }
    
    setupDimensionSelect() {
        const dimensionSelect = document.getElementById('dimension-select');
        if (!dimensionSelect) return;
        
        // Populate dimension dropdown
        dimensionSelect.innerHTML = '<option value="">Select a dimension...</option>';
        Object.entries(this.availableDimensions).forEach(([key, label]) => {
            const option = document.createElement('option');
            option.value = key;
            option.textContent = label;
            dimensionSelect.appendChild(option);
        });
    }
    
    setupFilterControls() {
        const filterDimensionSelect = document.getElementById('filter-dimension-select');
        const filterValueInput = document.getElementById('filter-value-input');
        
        if (!filterDimensionSelect || !filterValueInput) return;
        
        // Populate filter dimension dropdown
        filterDimensionSelect.innerHTML = '<option value="">Select filter dimension...</option>';
        Object.entries(this.availableDimensions).forEach(([key, label]) => {
            const option = document.createElement('option');
            option.value = key;
            option.textContent = label;
            filterDimensionSelect.appendChild(option);
        });
        
        // Initially disable filter value input
        filterValueInput.disabled = true;
    }
    
    setupEventListeners() {
        const dimensionSelect = document.getElementById('dimension-select');
        const filterDimensionSelect = document.getElementById('filter-dimension-select');
        const filterValueInput = document.getElementById('filter-value-input');
        
        if (!dimensionSelect || !filterDimensionSelect || !filterValueInput) return;
        
        // Debounce timer for filter value input
        let filterValueTimeout;
        
        // Dimension selection change
        dimensionSelect.addEventListener('change', (e) => {
            this.selectedDimension = e.target.value;
            this.updateFilterStatus();
            this.updateDashboard();
        });
        
        // Filter dimension selection change
        filterDimensionSelect.addEventListener('change', (e) => {
            this.filterDimension = e.target.value;
            filterValueInput.disabled = !this.filterDimension;
            
            if (!this.filterDimension) {
                filterValueInput.value = '';
                this.filterValue = null;
            }
            
            this.updateFilterStatus();
            this.updateDashboard();
        });
        
        // Filter value input change with debouncing
        filterValueInput.addEventListener('input', (e) => {
            this.filterValue = e.target.value.trim();
            
            // Clear existing timeout
            if (filterValueTimeout) {
                clearTimeout(filterValueTimeout);
            }
            
            // Set new timeout for debounced update
            filterValueTimeout = setTimeout(() => {
                this.updateFilterStatus();
                this.updateDashboard();
            }, 500); // 500ms delay
        });
        
        // Filter value input on Enter key (immediate update)
        filterValueInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                if (filterValueTimeout) {
                    clearTimeout(filterValueTimeout);
                }
                this.updateFilterStatus();
                this.updateDashboard();
            }
        });
    }
    
    updateDashboard() {
        // Show loading state
        this.showLoadingState();
        
        // Clear existing dimension checkboxes to avoid conflicts
        document.querySelectorAll('.dimension-filter').forEach(cb => {
            cb.checked = false;
        });
        
        // Update the filter builder with new dimension and filter values
        const filters = this.filterBuilder.build();
        
        // Set the selected dimension
        if (this.selectedDimension) {
            filters.set('dimensions', this.selectedDimension);
            filters.set('group_by', this.selectedDimension);
        }
        
        // Set the filter if both dimension and value are provided
        if (this.filterDimension && this.filterValue) {
            filters.set('filter_dimension', this.filterDimension);
            filters.set('filter_value', this.filterValue);
        }
        
        // Trigger dashboard update
        if (this.orchestrator && typeof this.orchestrator.fetch === 'function') {
            const metricSelect = document.getElementById('filter-metric');
            const metric = metricSelect ? metricSelect.value : 'traffic';
            this.orchestrator.fetch(metric).finally(() => {
                this.hideLoadingState();
            });
        } else {
            this.hideLoadingState();
        }
    }
    
    showLoadingState() {
        const dimensionSelect = document.getElementById('dimension-select');
        const filterDimensionSelect = document.getElementById('filter-dimension-select');
        const filterValueInput = document.getElementById('filter-value-input');
        
        if (dimensionSelect) dimensionSelect.disabled = true;
        if (filterDimensionSelect) filterDimensionSelect.disabled = true;
        if (filterValueInput) filterValueInput.disabled = true;
    }
    
    hideLoadingState() {
        const dimensionSelect = document.getElementById('dimension-select');
        const filterDimensionSelect = document.getElementById('filter-dimension-select');
        const filterValueInput = document.getElementById('filter-value-input');
        
        if (dimensionSelect) dimensionSelect.disabled = false;
        if (filterDimensionSelect) filterDimensionSelect.disabled = false;
        if (filterValueInput) {
            // Only enable if a filter dimension is selected
            const hasFilterDimension = filterDimensionSelect && filterDimensionSelect.value;
            filterValueInput.disabled = !hasFilterDimension;
        }
    }
    
    updateFilterStatus() {
        const statusDisplay = document.getElementById('filter-status-display');
        const dimensionDisplay = document.getElementById('current-dimension-display');
        const filterDisplay = document.getElementById('current-filter-display');
        
        if (!statusDisplay || !dimensionDisplay || !filterDisplay) return;
        
        let hasActiveFilters = false;
        let statusText = '';
        
        // Show dimension status
        if (this.selectedDimension) {
            const dimensionLabel = this.availableDimensions[this.selectedDimension] || this.selectedDimension;
            statusText += `📊 Dimension: ${dimensionLabel}`;
            hasActiveFilters = true;
        }
        
        // Show filter status
        if (this.filterDimension && this.filterValue) {
            const filterLabel = this.availableDimensions[this.filterDimension] || this.filterDimension;
            if (statusText) statusText += ' | ';
            statusText += `🔍 Filter: ${filterLabel} = "${this.filterValue}"`;
            hasActiveFilters = true;
        }
        
        if (hasActiveFilters) {
            statusDisplay.style.display = 'block';
            statusDisplay.innerHTML = `<span style="color: #0073aa; font-weight: 500;">${statusText}</span>`;
        } else {
            statusDisplay.style.display = 'none';
        }
    }
    
    // Get current filter state
    getFilterState() {
        return {
            dimension: this.selectedDimension,
            filterDimension: this.filterDimension,
            filterValue: this.filterValue
        };
    }
    
    // Clear all filters
    clearFilters() {
        this.selectedDimension = null;
        this.filterDimension = null;
        this.filterValue = null;
        
        const dimensionSelect = document.getElementById('dimension-select');
        const filterDimensionSelect = document.getElementById('filter-dimension-select');
        const filterValueInput = document.getElementById('filter-value-input');
        
        if (dimensionSelect) dimensionSelect.value = '';
        if (filterDimensionSelect) filterDimensionSelect.value = '';
        if (filterValueInput) {
            filterValueInput.value = '';
            filterValueInput.disabled = true;
        }
        
        this.updateFilterStatus();
        this.updateDashboard();
    }
    
    // Apply filters programmatically
    applyFilters(dimension, filterDimension = null, filterValue = null) {
        this.selectedDimension = dimension;
        this.filterDimension = filterDimension;
        this.filterValue = filterValue;
        
        const dimensionSelect = document.getElementById('dimension-select');
        const filterDimensionSelect = document.getElementById('filter-dimension-select');
        const filterValueInput = document.getElementById('filter-value-input');
        
        if (dimensionSelect) dimensionSelect.value = dimension || '';
        if (filterDimensionSelect) filterDimensionSelect.value = filterDimension || '';
        if (filterValueInput) {
            filterValueInput.value = filterValue || '';
            filterValueInput.disabled = !filterDimension;
        }
        
        this.updateDashboard();
    }
} 