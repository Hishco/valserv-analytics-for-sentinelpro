/**
 * StatusPopup
 * 
 * Creates user-friendly status popups for various system messages
 */

export default class StatusPopup {
    constructor() {
        this.popupContainer = null;
        this.activePopup = null;
        this.createPopupContainer();
    }

    /**
     * Create the popup container if it doesn't exist
     */
    createPopupContainer() {
        if (document.getElementById('sentinelpro-status-popup-container')) {
            this.popupContainer = document.getElementById('sentinelpro-status-popup-container');
            return;
        }

        this.popupContainer = document.createElement('div');
        this.popupContainer.id = 'sentinelpro-status-popup-container';
        this.popupContainer.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 999999;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        `;
        
        document.body.appendChild(this.popupContainer);
    }

    /**
     * Show a status popup
     */
    show({ type = 'info', title, message, actions = [], autoClose = false, duration = 5000 }) {
        this.hide(); // Hide any existing popup

        const popup = document.createElement('div');
        popup.style.cssText = `
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            animation: statusPopupSlideIn 0.3s ease-out;
        `;

        // Add animation keyframes
        if (!document.getElementById('status-popup-styles')) {
            const style = document.createElement('style');
            style.id = 'status-popup-styles';
            style.textContent = `
                @keyframes statusPopupSlideIn {
                    from {
                        opacity: 0;
                        transform: scale(0.9) translateY(-20px);
                    }
                    to {
                        opacity: 1;
                        transform: scale(1) translateY(0);
                    }
                }
                @keyframes statusPopupSlideOut {
                    from {
                        opacity: 1;
                        transform: scale(1) translateY(0);
                    }
                    to {
                        opacity: 0;
                        transform: scale(0.9) translateY(-20px);
                    }
                }
            `;
            document.head.appendChild(style);
        }

        // Get icon and color based on type
        const typeConfig = this.getTypeConfig(type);

        popup.innerHTML = `
            <div style="padding: 30px;">
                <div style="display: flex; align-items: flex-start; margin-bottom: 20px;">
                    <div style="
                        width: 50px;
                        height: 50px;
                        border-radius: 50%;
                        background: ${typeConfig.bgColor};
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        margin-right: 15px;
                        flex-shrink: 0;
                    ">
                        <span style="font-size: 24px; color: ${typeConfig.iconColor};">${typeConfig.icon}</span>
                    </div>
                    <div style="flex: 1;">
                        <h3 style="
                            margin: 0 0 10px 0;
                            color: #23282d;
                            font-size: 18px;
                            font-weight: 600;
                        ">${title}</h3>
                        <p style="
                            margin: 0;
                            color: #50575e;
                            font-size: 14px;
                            line-height: 1.5;
                        ">${message}</p>
                    </div>
                </div>
                
                <div style="
                    display: flex;
                    gap: 10px;
                    justify-content: flex-end;
                    border-top: 1px solid #ddd;
                    padding-top: 20px;
                    margin-top: 20px;
                ">
                    ${this.renderActions(actions)}
                </div>
            </div>
        `;

        this.activePopup = popup;
        this.popupContainer.appendChild(popup);
        this.popupContainer.style.display = 'flex';

        // Setup action handlers
        this.setupActionHandlers(popup, actions);

        // Auto close if specified
        if (autoClose) {
            setTimeout(() => {
                this.hide();
            }, duration);
        }

        // Close on overlay click
        this.popupContainer.addEventListener('click', (e) => {
            if (e.target === this.popupContainer) {
                e.preventDefault();
                e.stopPropagation();
                this.hide();
            }
        });

        // Close on Escape key
        const escapeHandler = (e) => {
            if (e.key === 'Escape') {
                this.hide();
                document.removeEventListener('keydown', escapeHandler);
            }
        };
        document.addEventListener('keydown', escapeHandler);

        return popup;
    }

    /**
     * Get configuration for different popup types
     */
    getTypeConfig(type) {
        const configs = {
            error: {
                icon: '❌',
                bgColor: '#ffebee',
                iconColor: '#d32f2f'
            },
            warning: {
                icon: '⚠️',
                bgColor: '#fff3e0',
                iconColor: '#f57c00'
            },
            success: {
                icon: '✅',
                bgColor: '#e8f5e8',
                iconColor: '#388e3c'
            },
            info: {
                icon: 'ℹ️',
                bgColor: '#e3f2fd',
                iconColor: '#1976d2'
            },
            date: {
                icon: '📅',
                bgColor: '#f3e5f5',
                iconColor: '#7b1fa2'
            }
        };

        return configs[type] || configs.info;
    }

    /**
     * Render action buttons
     */
    renderActions(actions) {
        if (actions.length === 0) {
            return `
                <button data-action="close" style="
                    background: #0073aa;
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 14px;
                    font-weight: 500;
                ">OK</button>
            `;
        }

        return actions.map(action => {
            const isPrimary = action.primary !== false;
            return `
                <button data-action="${action.id}" style="
                    background: ${isPrimary ? '#0073aa' : '#f1f1f1'};
                    color: ${isPrimary ? 'white' : '#23282d'};
                    border: ${isPrimary ? 'none' : '1px solid #ddd'};
                    padding: 10px 20px;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 14px;
                    font-weight: 500;
                ">${action.label}</button>
            `;
        }).join('');
    }

    /**
     * Setup action button handlers
     */
    setupActionHandlers(popup, actions) {
        const buttons = popup.querySelectorAll('button[data-action]');
        
        buttons.forEach(button => {
            button.addEventListener('click', (e) => {
                const actionId = button.getAttribute('data-action');
                
                // Prevent event propagation to avoid closing date picker
                e.preventDefault();
                e.stopPropagation();
                
                if (actionId === 'close') {
                    this.hide();
                    return;
                }

                const action = actions.find(a => a.id === actionId);
                if (action && action.callback) {
                    action.callback();
                }

                // Close popup unless action specifies otherwise
                if (!action || action.closeOnClick !== false) {
                    this.hide();
                }
            });

            // Add hover effects
            button.addEventListener('mouseenter', () => {
                button.style.opacity = '0.9';
            });
            button.addEventListener('mouseleave', () => {
                button.style.opacity = '1';
            });
        });
    }

    /**
     * Hide the popup
     */
    hide() {
        if (this.activePopup) {
            this.activePopup.style.animation = 'statusPopupSlideOut 0.2s ease-in';
            
            setTimeout(() => {
                if (this.popupContainer) {
                    this.popupContainer.style.display = 'none';
                    this.popupContainer.innerHTML = '';
                }
                this.activePopup = null;
            }, 200);
        }
    }

    /**
     * Show an invalid date range popup
     */
    showInvalidDateRange(oldestDate, selectedStart, selectedEnd) {
        return this.show({
            type: 'date',
            title: 'Invalid Date Range',
            message: `The selected date range (${selectedStart} to ${selectedEnd}) extends before the oldest available data.<br><br><strong>Oldest available data:</strong> ${oldestDate}<br><br>Please select a date range from ${oldestDate} onwards.`,
            actions: [
                {
                    id: 'reselect',
                    label: '📅 Select New Date Range',
                    primary: true,
                    callback: () => {
                        // Trigger date picker to open
                        this.reopenDatePicker();
                    }
                },
                {
                    id: 'use-valid',
                    label: '🔄 Use Valid Range',
                    primary: false,
                    callback: () => {
                        // Set to a valid range (last 30 days from oldest date)
                        this.setValidDateRange(oldestDate);
                    }
                }
            ]
        });
    }

    /**
     * Show an invalid comparison date range popup
     */
    showInvalidComparisonRange(oldestDate, selectedStart, selectedEnd) {
        return this.show({
            type: 'date',
            title: 'Invalid Comparison Date Range',
            message: `The selected comparison date range (${selectedStart} to ${selectedEnd}) extends before the oldest available data.<br><br><strong>Oldest available data:</strong> ${oldestDate}<br><br>Please select a comparison range from ${oldestDate} onwards, or choose a different comparison option.`,
            actions: [
                {
                    id: 'clear-comparison',
                    label: '🚫 Clear Comparison',
                    primary: true,
                    callback: () => {
                        this.clearComparison();
                    }
                },
                {
                    id: 'choose-different',
                    label: '🔄 Choose Different Comparison',
                    primary: false,
                    callback: () => {
                        this.showComparisonOptions();
                    }
                }
            ]
        });
    }

    /**
     * Reopen the date picker for user to select again
     */
    reopenDatePicker() {
        const dateRangeInput = document.getElementById('filter-daterange');
        if (dateRangeInput && window.jQuery) {
            // Give a small delay for the popup to close
            setTimeout(() => {
                window.jQuery(dateRangeInput).data('daterangepicker')?.show();
            }, 300);
        }
    }

    /**
     * Set a valid date range automatically
     */
    setValidDateRange(oldestDate) {
        const startInput = document.getElementById('filter-start');
        const endInput = document.getElementById('filter-end');
        const dateRangeInput = document.getElementById('filter-daterange');
        
        if (startInput && endInput) {
            // Set to 30 days from oldest date to yesterday
            const oldestMoment = moment(oldestDate);
            const yesterday = moment().subtract(1, 'days');
            const thirtyDaysFromOldest = moment(oldestDate).add(30, 'days');
            
            // Use the smaller range (either 30 days from oldest, or oldest to yesterday)
            const endDate = moment.min(thirtyDaysFromOldest, yesterday);
            
            startInput.value = oldestDate;
            endInput.value = endDate.format('YYYY-MM-DD');
            
            if (dateRangeInput) {
                dateRangeInput.value = `${oldestDate} to ${endDate.format('YYYY-MM-DD')}`;
            }
            
            // Update the date range picker
            if (window.jQuery && dateRangeInput) {
                const drp = window.jQuery(dateRangeInput).data('daterangepicker');
                if (drp) {
                    drp.setStartDate(oldestDate);
                    drp.setEndDate(endDate.format('YYYY-MM-DD'));
                }
            }
            
            // Update date labels if available
            if (window.sentinelpro_dateManagerInstance?.updateDateLabels) {
                window.sentinelpro_dateManagerInstance.updateDateLabels();
            }
            
            // Trigger a data refresh if enhanced dashboard is available
            if (window.EnhancedDashboardInstance?.applyFilters) {
                window.EnhancedDashboardInstance.applyFilters();
            }
        }
    }

    /**
     * Show a success message for date range update
     */
    showDateRangeUpdated(startDate, endDate) {
        return this.show({
            type: 'success',
            title: 'Date Range Updated',
            message: `Date range has been set to: ${startDate} to ${endDate}`,
            autoClose: true,
            duration: 3000
        });
    }

    /**
     * Clear comparison range
     */
    clearComparison() {
        const compareStartInput = document.getElementById('compare-start') || document.getElementById('sentinelpro-compare-start');
        const compareEndInput = document.getElementById('compare-end') || document.getElementById('sentinelpro-compare-end');
        const compareToggle = document.getElementById('compare-toggle');
        
        if (compareStartInput) compareStartInput.value = '';
        if (compareEndInput) compareEndInput.value = '';
        
        if (compareToggle) {
            compareToggle.checked = false;
            const compareOptions = document.getElementById('compare-options');
            if (compareOptions) {
                compareOptions.classList.remove('show');
            }
        }
        
        // Clear any active comparison selections
        document.querySelectorAll('.compare-option').forEach((opt) => opt.classList.remove('active'));
        
        // Clear localStorage
        localStorage.removeItem('sentinelpro_compare_mode');
        localStorage.removeItem('sentinelpro_compare_start');
        localStorage.removeItem('sentinelpro_compare_end');
        
        // Clear comparison data if available
        if (window.orchestrator && typeof window.orchestrator.clearComparisonData === 'function') {
            window.orchestrator.clearComparisonData();
        }
    }

    /**
     * Show comparison options for user to choose again
     */
    showComparisonOptions() {
        const compareToggle = document.getElementById('compare-toggle');
        const compareOptions = document.getElementById('compare-options');
        
        if (compareToggle && compareOptions) {
            compareToggle.checked = true;
            compareOptions.classList.add('show');
        }
        
        // Clear any previous selections
        document.querySelectorAll('.compare-option').forEach((opt) => opt.classList.remove('active'));
    }
}

// Create a global instance
window.sentinelProStatusPopup = new StatusPopup();