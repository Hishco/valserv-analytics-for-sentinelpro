// DashboardHelpers/DateUIManager.js

export default class DateUIManager {
    constructor({ startInput, endInput, granularitySelect, dateRangeInput, customTag, toggleBtn, filterBox }) {
        this.startInput = startInput;
        this.endInput = endInput;
        this.granularitySelect = granularitySelect;
        this.dateRangeInput = dateRangeInput;
        this.customTag = customTag;
        this.toggleBtn = toggleBtn;
        this.filterBox = filterBox;

        this.defaultStart = '';
        this.defaultEnd = '';
    }

    initialize() {
        const today = new Date();
        const yesterday = new Date(today);
        yesterday.setDate(today.getDate() - 1);
        const past = new Date(today);
        past.setDate(today.getDate() - 30); // <-- changed from -31 to -30

        this.defaultStart = this.format(past);
        this.defaultEnd = this.format(yesterday);

        if (!this.startInput.value) this.startInput.value = this.defaultStart;
        if (!this.endInput.value) this.endInput.value = this.defaultEnd;
    }

    format(date) {
        return date.toISOString().split('T')[0];
    }

    isHourly() {
        return this.granularitySelect?.value === 'hourly';
    }

    updateDateLabels() {
        const granularity = this.granularitySelect?.value || 'daily';
        const labels = document.querySelectorAll('.date-label');
        const notice = document.getElementById('granularity-status');

        if (labels.length >= 2) {
            labels[0].textContent = granularity === 'hourly' ? 'Date 1:' : 'Start Date:';
            labels[1].textContent = granularity === 'hourly' ? 'Date 2:' : 'End Date:';
        }

        if (notice) {
            notice.textContent = granularity === 'hourly'
                ? "ℹ️ Granularity: 'Hourly' is used to compare the hourly data between 2 dates."
                : '';
        }

        if (this.dateRangeInput && this.startInput.value && this.endInput.value) {
            this.dateRangeInput.value = granularity === 'hourly'
                ? `${this.startInput.value} vs ${this.endInput.value}`
                : `${this.startInput.value} to ${this.endInput.value}`;
        }
    }

    checkCustomDateSelected() {
        if (!this.customTag) return;
        if (this.isHourly()) {
            this.customTag.style.display = 'none';
            return;
        }

        const isCustom = this.startInput.value !== this.defaultStart || this.endInput.value !== this.defaultEnd;
        this.customTag.style.display = isCustom ? 'inline-block' : 'none';
    }

    resetDates() {
        this.startInput.value = this.defaultStart;
        this.endInput.value = this.defaultEnd;

        // Sync with Daterangepicker
        if (window.jQuery && jQuery(this.dateRangeInput).data('daterangepicker')) {
            const drp = jQuery(this.dateRangeInput).data('daterangepicker');
            drp.setStartDate(this.defaultStart);
            drp.setEndDate(this.defaultEnd);
        }

        this.checkCustomDateSelected();
    }

    setInputs({ dateRangeText, startInput, endInput, dateRangeInput }) {
        this.dateRangeText = dateRangeText;
        this.startInput = startInput;
        this.endInput = endInput;
        this.dateRangeInput = dateRangeInput;
    }

    updateDateRangeDisplay() {
        const dateRangeText = this.dateRangeText || document.getElementById('date-range-text');
        const startInput = this.startInput || document.getElementById('filter-start');
        const endInput = this.endInput || document.getElementById('filter-end');
        const dateRangeInput = this.dateRangeInput || document.getElementById('filter-daterange');
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
            // if (dateRangeInput && dateRangeInput.value) {
            //     const [from, to] = dateRangeInput.value.split(' to ');
            //     if (from && to) {
            //         startInput.value = from.trim();
            //         endInput.value = to.trim();
            //     }
            // }
        }
    }

    setupFilterToggle() {
        // Skip filter toggle since filterBox doesn't exist in current HTML
        // this.toggleBtn?.addEventListener('click', () => {
        //     const isHidden = this.filterBox.style.display === 'none';
        //     this.filterBox.style.display = isHidden ? 'block' : 'none';
        //     this.toggleBtn.textContent = isHidden ? 'Hide Filters' : 'Show Filters';
        // });
    }
}
