import DateRangeValidator from './DateRangeValidator.js';
import StatusPopup from './StatusPopup.js';

export default class CompareDatePicker {
    constructor({
        startInput,
        endInput,
        compareStartInput,
        compareEndInput,
        dateRangeInput,
        onRangeApplied = () => {},
        onHighlightCompare = () => {},
    }) {
        this.startInput = startInput;
        this.endInput = endInput;
        this.compareStartInput = compareStartInput;
        this.compareEndInput = compareEndInput;
        this.dateRangeInput = dateRangeInput;
        this.onRangeApplied = onRangeApplied;
        this.onHighlightCompare = onHighlightCompare;
        this.pendingStart = null;
        this.pendingEnd = null;
        this.dateValidator = window.sentinelProDateValidator || new DateRangeValidator();
        this.statusPopup = window.sentinelProStatusPopup || new StatusPopup();
    }

    async initialize(defaultStart, defaultEnd) {

        const $ = window.jQuery;
        if (!this.dateRangeInput || !$) return;

        // Initialize the date validator to get the oldest available date
        await this.dateValidator.initialize();

        const elementId = `#${this.dateRangeInput.id}`;
        const compareStartInput = this.compareStartInput;
        const compareEndInput = this.compareEndInput;

        // Initialize last valid date range as instance properties
        this.lastValidStart = this.startInput.value;
        this.lastValidEnd = this.endInput.value;

        // Get dynamic date limits and ranges
        const minDate = this.dateValidator.getMinimumDate();
        const maxDate = this.dateValidator.getMaximumDate();
        const validRanges = this.dateValidator.getValidPresetRanges();

        $(elementId).daterangepicker(
            {
                autoApply: false,
                autoUpdateInput: false,
                linkedCalendars: false,
                alwaysShowCalendars: true,
                showDropdowns: true,
                opens: 'right',
                parentEl: '#custom-daterange-wrapper',
                locale: {
                    format: 'YYYY-MM-DD',
                    applyLabel: 'Apply',
                    cancelLabel: 'Cancel',
                    customRangeLabel: 'Custom Range',
                },
                startDate: defaultStart,
                endDate: defaultEnd,
                maxYear: moment().year(),
                minDate: minDate,
                maxDate: maxDate,
                ranges: validRanges,
            },
            // REMOVE all logic from this callback to prevent updates except on Apply
            () => {}
        );

        const drp = $(elementId).data('daterangepicker');
        if (drp) {
        const originalUpdateCalendars = drp.updateCalendars.bind(drp);
        drp.updateCalendars = () => {
            originalUpdateCalendars();
            if (this.compareStartInput.value && this.compareEndInput.value) {
            this.highlightCompareRange(
                drp,
                this.compareStartInput.value,
                this.compareEndInput.value
            );
            }
        };
        }

        // Preset click logic
        setTimeout(() => {
            const drp = $(elementId).data('daterangepicker');
            if (!drp) return;

            $('.daterangepicker .ranges li')
            .off('click')
            .on('click', (e) => {
                if ($(e.currentTarget).hasClass('sentinelpro-ignore-range')) return;
                e.preventDefault();
                e.stopPropagation();

                const label = $(e.currentTarget).text();
                const range = drp.ranges[label];
                if (!range) return;

                const [start, end] = range;
                this.pendingStart = start.clone();
                this.pendingEnd = end.clone();

                drp.setStartDate(start);
                drp.setEndDate(end);

                this.fixCalendarViewToCompareStart();
                drp.updateView();
                drp.updateCalendars();
                this.highlightCompareRange(drp, this.compareStartInput.value, this.compareEndInput.value);

                // Update the input values to match the preset selection
                // this.startInput.value = start.format('YYYY-MM-DD');
                // this.endInput.value = end.format('YYYY-MM-DD');
            });
        }, 100);

        // Track last valid main date range
        $(elementId).on('apply.daterangepicker', (ev, picker) => {
            // Use the picker's selected dates, not pendingStart/pendingEnd
            const drp = $(elementId).data('daterangepicker');
            if (drp) {
                const selectedStart = drp.startDate.format('YYYY-MM-DD');
                const selectedEnd = drp.endDate.format('YYYY-MM-DD');
                
                // Validate the selected date range
                const validation = this.dateValidator.validateRange(selectedStart, selectedEnd);
                
                if (!validation.valid) {
                    // Show user-friendly status popup
                    this.statusPopup.showInvalidDateRange(
                        validation.oldestDate,
                        selectedStart,
                        selectedEnd
                    );
                    
                    // Reset to last valid values
                    this.startInput.value = this.lastValidStart;
                    this.endInput.value = this.lastValidEnd;
                    
                    // Update the date picker display
                    if (window.jQuery && this.dateRangeInput) {
                        const drp = window.jQuery(`#${this.dateRangeInput.id}`).data('daterangepicker');
                        if (drp) {
                            drp.setStartDate(this.lastValidStart);
                            drp.setEndDate(this.lastValidEnd);
                        }
                    }
                    
                    return;
                }
                
                // Only set the hidden fields if validation passes
                this.startInput.value = selectedStart;
                this.endInput.value = selectedEnd;
            }
            
            // Check if the date range has actually changed
            const newStart = this.startInput.value;
            const newEnd = this.endInput.value;
            const dateRangeChanged = (newStart !== this.lastValidStart || newEnd !== this.lastValidEnd);
            
            // Update last valid range if both start and end are present and not equal
            if (this.startInput.value && this.endInput.value && this.startInput.value !== this.endInput.value) {
                this.lastValidStart = this.startInput.value;
                this.lastValidEnd = this.endInput.value;
            }

            this.clearCompareIfMainChanged();
            this.pendingStart = null;
            this.pendingEnd = null;

            // --- FIX: Set compareStartInput/compareEndInput if compare is enabled and a compare option is selected ---
            const toggle = document.getElementById('compare-toggle');
            if (toggle?.checked && this.pendingCompareStart && this.pendingCompareEnd) {
                const compareStart = this.pendingCompareStart.format('YYYY-MM-DD');
                const compareEnd = this.pendingCompareEnd.format('YYYY-MM-DD');
                
                // Validate comparison date range
                if (this.validateAndSetComparisonDates(compareStart, compareEnd)) {
                    this.compareStartInput.value = compareStart;
                    this.compareEndInput.value = compareEnd;
                } else {
                    // Clear invalid comparison dates
                    this.compareStartInput.value = '';
                    this.compareEndInput.value = '';
                    this.pendingCompareStart = null;
                    this.pendingCompareEnd = null;
                    
                    // Prevent the daterangepicker from closing
                    ev.preventDefault();
                    ev.stopPropagation();
                    
                    // Keep the picker open
                    setTimeout(() => {
                        if (drp && !drp.isShowing) {
                            drp.show();
                        }
                    }, 50);
                    
                    return false; // Prevent further processing
                }
            }

            this.onRangeApplied();

            if (drp && toggle?.checked && this.compareStartInput.value && this.compareEndInput.value) {
                this.highlightCompareRange(drp, this.compareStartInput.value,this.compareEndInput.value);
            }

            setTimeout(() => {
                const drp = $(elementId).data('daterangepicker');
                if (drp && this.compareStartInput.value && this.compareEndInput.value) {
                    this.highlightCompareRange(drp, this.compareStartInput.value, this.compareEndInput.value);
                }
            }, 0); // short delay allows calendar redraw to complete

            // --- Only trigger data refresh if date range has actually changed ---
            if (dateRangeChanged && window.EnhancedDashboardInstance && typeof window.EnhancedDashboardInstance.applyFilters === 'function') {
                // Clear comparison cache when date range changes
                if (window.orchestrator && typeof window.orchestrator.clearComparisonData === 'function') {
                    window.orchestrator.clearComparisonData();
                }
                window.EnhancedDashboardInstance.applyFilters();
            } else if (!dateRangeChanged && window.EnhancedDashboardInstance) {
                // Check if comparison data has been set
                const compareToggle = document.getElementById('compare-toggle');
                const compareStartInput = document.getElementById('sentinelpro-compare-start') || document.getElementById('compare-start');
                const compareEndInput = document.getElementById('sentinelpro-compare-end') || document.getElementById('compare-end');
                const comparisonEnabled = compareToggle && compareToggle.checked && compareStartInput && compareEndInput && compareStartInput.value && compareEndInput.value;
                
                // If comparison is enabled and we have comparison dates, we need to fetch comparison data
                if (comparisonEnabled && compareStartInput.value && compareEndInput.value) {
                    window.EnhancedDashboardInstance.applyFilters();
                } else {
                    // If date range hasn't changed and no comparison, just re-render the chart with current data
                    if (typeof window.EnhancedDashboardInstance._processAndRenderData === 'function') {
                        window.EnhancedDashboardInstance._processAndRenderData();
                    } else if (typeof window.EnhancedDashboardInstance.updateChart === 'function') {
                        // Fallback to just updating the chart
                        window.EnhancedDashboardInstance.updateChart();
                    } else {
                        // Final fallback - trigger a minimal refresh
                        window.EnhancedDashboardInstance.applyFilters();
                    }
                }
            }
        });

        // On hide, only restore the last valid range if the current range is incomplete. Do NOT trigger onRangeApplied or fetch.
        $(elementId).on('hide.daterangepicker', () => {
            // Only restore if the range is truly invalid (empty or single day)
            // Don't restore if user has manually selected a valid range
            if (!this.startInput.value || !this.endInput.value || this.startInput.value === this.endInput.value) {
                this.startInput.value = this.lastValidStart;
                this.endInput.value = this.lastValidEnd;
                const drp = $(elementId).data('daterangepicker');
                if (drp) {
                    drp.setStartDate(this.lastValidStart);
                    drp.setEndDate(this.lastValidEnd);
                }
            } else {
                // If we have a valid range, update the last valid range
                this.lastValidStart = this.startInput.value;
                this.lastValidEnd = this.endInput.value;
            }
        });

        // Inject Compare Toggle as a <li> under 'Custom Range' in the preset ranges
        $(elementId).on('show.daterangepicker', () => {
            const sidebar = $('.daterangepicker .ranges');
            const presetList = sidebar.find('ul');
            if (!sidebar.length || document.getElementById('compare-toggle-container')) return;

            // Create the Compare switch container (outside the preset <ul>)
            const compareDiv = document.createElement('div');
            compareDiv.id = 'compare-toggle-container';
            compareDiv.style.marginTop = '16px';
            compareDiv.style.paddingTop = '12px';
            compareDiv.style.borderTop = '1px solid #ddd';
            compareDiv.style.fontSize = '14px';
            compareDiv.style.display = 'flex';
            compareDiv.style.alignItems = 'center';
            compareDiv.style.justifyContent = 'space-between';
            compareDiv.innerHTML = `
                <span style="font-size:14px;display:inline-block;">Compare</span>
                <label class="compare-switch" style="margin-left:12px;display:inline-flex;align-items:center;">
                    <input type="checkbox" id="compare-toggle" ${compareStartInput.value && compareEndInput.value ? 'checked' : ''} />
                    <span class="slider"></span>
                </label>
            `;
            // Insert after the preset ranges <ul>
            presetList.after(compareDiv);

            // Create the compare options <ul> (hidden by default)
            let optionsUl = document.getElementById('compare-options-ul');
            if (!optionsUl) {
                optionsUl = document.createElement('ul');
                optionsUl.id = 'compare-options-ul';
                optionsUl.style.display = (compareStartInput.value && compareEndInput.value) ? 'block' : 'none';
                optionsUl.style.paddingLeft = '0';
                optionsUl.style.marginTop = '8px';
                optionsUl.style.listStyle = 'none';
                optionsUl.innerHTML = `
                    <li class="sentinelpro-ignore-range"><div class="compare-option preset-option" data-compare="preceding-range">Preceding Period</div></li>
                    <li class="sentinelpro-ignore-range"><div class="compare-option preset-option" data-compare="preceding-range-dow">Preceding Period (Match day of week)</div></li>
                `;
                $(compareDiv).after(optionsUl);
            }

            // Prevent click bubbling for compare options <li>
            optionsUl?.querySelectorAll('li.sentinelpro-ignore-range').forEach(li => {
                li.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                });
            });
            optionsUl?.querySelectorAll('.compare-option').forEach(opt => {
                opt.addEventListener('click', (e) => {
                    e.stopPropagation();
                });
            });

            const toggle = document.getElementById('compare-toggle');
            const options = document.getElementById('compare-options-ul');

            toggle?.addEventListener('change', () => {
                if (options) options.style.display = toggle.checked ? 'block' : 'none';
                if (!toggle.checked) {
                    this.clearCompareRange();
                    document.querySelectorAll('.compare-option').forEach(opt => opt.classList.remove('active'));
                    // Clear stored compare mode
                    localStorage.removeItem('sentinelpro_compare_mode');
                    // Restore original clickDate behavior when compare is turned off
                    if (drp && drp.originalClickDate) {
                        drp.clickDate = drp.originalClickDate;
                        delete drp.originalClickDate;
                    }
                }
            });

            this.setupCompareOptionClicks();

            const storedMode = localStorage.getItem('sentinelpro_compare_mode');
            document.querySelectorAll('.compare-option').forEach(opt => {
                opt.classList.toggle('active', opt.dataset.compare === storedMode);
            });

            // Prevent click bubbling for compare options only
            optionsUl?.addEventListener('click', (e) => {
                if (e.target === optionsUl) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
            optionsUl?.querySelectorAll('.compare-option').forEach(opt => {
                opt.addEventListener('click', (e) => {
                    e.stopPropagation();
                });
            });

            // After all setup, re-highlight the comparison period if present
            const drp = $(elementId).data('daterangepicker');
            if (drp && this.compareStartInput.value && this.compareEndInput.value) {
                setTimeout(() => {
                    this.highlightCompareRange(drp, this.compareStartInput.value, this.compareEndInput.value);
                }, 0);
            }
        });

    }


    setupCompareOptionClicks() {
        const $ = window.jQuery;
        const drp = $(this.dateRangeInput).data('daterangepicker');
        if (!drp) return;

        const options = document.querySelectorAll('.compare-option');
        options.forEach((option) => {
            option.addEventListener('click', () => {
                const mainStart = drp.startDate?.clone?.();
                const mainEnd = drp.endDate?.clone?.();

                if (!mainStart.isValid() || !mainEnd.isValid()) return;

                window.sentinelproInitialRange = [];
                let current = mainStart.clone();
                while (current.isSameOrBefore(mainEnd, 'day')) {
                    window.sentinelproInitialRange.push(current.format('YYYY-MM-DD'));
                    current.add(1, 'day');
                }

                const duration = mainEnd.diff(mainStart, 'days') + 1;
                let compareStart, compareEnd;

                switch (option.dataset.compare) {
                    case 'preceding-range':
                        compareStart = mainStart.clone().subtract(duration, 'days');
                        compareEnd = mainStart.clone().subtract(1, 'days');
                        break;
                    case 'preceding-range-last-year':
                        compareStart = mainStart.clone().subtract(1, 'year');
                        compareEnd = mainEnd.clone().subtract(1, 'year');
                        break;
                    case 'preceding-range-dow': {
                        const startDow = mainStart.day();
                        compareStart = mainStart.clone().subtract(1, 'week').day(startDow);
                        compareEnd = compareStart.clone().add(duration - 1, 'days');
                        break;
                    }
                    case 'preceding-range-dow-last-year': {
                        const startDow = mainStart.day();
                        compareStart = mainStart.clone().subtract(1, 'year').day(startDow);
                        compareEnd = compareStart.clone().add(duration - 1, 'days');
                        break;
                    }
                }

                if (compareStart && compareEnd) {
                    const compareStartStr = compareStart.format('YYYY-MM-DD');
                    const compareEndStr = compareEnd.format('YYYY-MM-DD');
                    
                    // Validate comparison date range
                    if (this.validateAndSetComparisonDates(compareStartStr, compareEndStr)) {
                        this.compareStartInput.value = compareStartStr;
                        this.compareEndInput.value = compareEndStr;
                        this.pendingCompareStart = compareStart;
                        this.pendingCompareEnd = compareEnd;
                        localStorage.setItem('sentinelpro_compare_start', this.compareStartInput.value);
                        localStorage.setItem('sentinelpro_compare_end', this.compareEndInput.value);
                        this.highlightCompareRange(drp, this.compareStartInput.value, this.compareEndInput.value);
                        // Removed: programmatic Apply trigger. Now waits for user to click 'Apply'.
                    } else {
                        // Clear invalid comparison dates and don't proceed
                        this.clearCompareRange();
                    }
                }

                document.querySelectorAll('.compare-option').forEach((opt) => opt.classList.remove('active'));
                option.classList.add('active');
                localStorage.setItem('sentinelpro_compare_mode', option.dataset.compare);
            });
        });
    }

    clearCompareIfMainChanged() {
        const $ = window.jQuery;
        try {
            const stored = window.sentinelproInitialRange;
            const currentStart = this.startInput.value;
            const currentEnd = this.endInput.value;

            if (stored && (stored[0] !== currentStart || stored.at(-1) !== currentEnd)) {
                this.clearCompareRange();
            }
        } catch (e) {
        }
    }

    clearCompareRange() {
        this.compareStartInput.value = '';
        this.compareEndInput.value = '';
        localStorage.removeItem('sentinelpro_compare_start');
        localStorage.removeItem('sentinelpro_compare_end');

        const drp = jQuery(this.dateRangeInput).data('daterangepicker');
        if (drp?.container) {
        drp.container.find('td').removeClass('compare-range compare-start-date compare-end-date compare-preview compare-highlight');
        }
    }

    highlightCompareRange(drp, startStr, endStr) {
        if (!drp?.container) return;
        const container = drp.container;

        const start = moment(startStr, 'YYYY-MM-DD');
        const end = moment(endStr, 'YYYY-MM-DD');

        container.find('td').removeClass(
            'compare-highlight compare-range compare-start-date compare-end-date'
        );

        container.find('td[data-date]').each(function () {
            const cell = jQuery(this);
            const date = moment(cell.attr('data-date'), 'YYYY-MM-DD');

            if (date.isSame(start, 'day')) {
                cell.addClass('compare-start-date');
            }
            if (date.isSame(end, 'day')) {
                cell.addClass('compare-end-date');
            }
            if (date.isBetween(start, end, 'day')) {
                cell.addClass('compare-range');
            }
        });

        this.onHighlightCompare();
    }



    fixCalendarViewToCompareStart() {
        const $ = window.jQuery;
        const drp = $(this.dateRangeInput).data('daterangepicker');
        const compareStartMoment = moment(this.compareStartInput?.value, 'YYYY-MM-DD');
        const compareEndMoment = moment(this.compareEndInput?.value, 'YYYY-MM-DD');

        if (drp && compareStartMoment.isValid() && compareEndMoment.isValid()) {
        drp.leftCalendar.month = compareStartMoment.clone().date(2);
        drp.rightCalendar.month = compareStartMoment.clone().add(1, 'month').date(2);
        drp.updateCalendars();
        this.highlightCompareRange(drp, this.compareStartInput.value, this.compareEndInput.value);

        }
    }

    clearDateRange() {
        // Clear main date inputs
        if (this.startInput) this.startInput.value = '';
        if (this.endInput) this.endInput.value = '';
        // Clear compare inputs
        if (this.compareStartInput) this.compareStartInput.value = '';
        if (this.compareEndInput) this.compareEndInput.value = '';
        // Clear date range input
        if (this.dateRangeInput) this.dateRangeInput.value = '';
        // Clear compare toggle and options
        const compareToggle = document.getElementById('compare-toggle');
        if (compareToggle) {
            compareToggle.checked = false;
            const compareOptions = document.getElementById('compare-options');
            if (compareOptions) {
                compareOptions.classList.remove('show');
            }
        }
        // Reset daterangepicker to today
        const $ = window.jQuery;
        if ($ && this.dateRangeInput && $(this.dateRangeInput).data('daterangepicker')) {
            const drp = $(this.dateRangeInput).data('daterangepicker');
            drp.setStartDate(moment());
            drp.setEndDate(moment());
        }
        // Clear compare highlights
        this.clearCompareRange();
    }

    /**
     * Validate comparison date range and show popup if invalid
     * @param {string} compareStart - Start date in YYYY-MM-DD format
     * @param {string} compareEnd - End date in YYYY-MM-DD format
     * @returns {boolean} - True if valid, false if invalid
     */
    validateAndSetComparisonDates(compareStart, compareEnd) {
        if (!this.dateValidator || !this.statusPopup) {
            return true; // Allow if validation isn't available
        }

        // Validate the comparison date range
        const validation = this.dateValidator.validateRange(compareStart, compareEnd);
        
        if (!validation.valid) {
            // Show comparison-specific popup
            this.statusPopup.show({
                type: 'date',
                title: 'Invalid Comparison Date Range',
                message: `The selected comparison date range (${compareStart} to ${compareEnd}) extends before the oldest available data.<br><br><strong>Oldest available data:</strong> ${validation.oldestDate}<br><br>Please select a comparison range from ${validation.oldestDate} onwards, or choose a different comparison option.`,
                actions: [
                    {
                        id: 'close',
                        label: 'Close',
                        primary: true,
                        callback: () => {
                            // Clear the invalid comparison selection and show options again
                            this.clearCompareRange();
                            const compareOptions = document.getElementById('compare-options');
                            if (compareOptions) {
                                compareOptions.classList.add('show');
                            }
                        }
                    }
                ]
            });
            
            return false;
        }
        
        return true;
    }


}
