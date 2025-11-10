/**
 * DateRangeValidator
 * 
 * Utility class to fetch and validate date ranges against available data
 */

export default class DateRangeValidator {
    constructor() {
        this.oldestDate = null;
        this.oldestDateMoment = null;
        this.isInitialized = false;
    }

    /**
     * Initialize the validator by fetching the oldest date from the database
     */
    async initialize() {
        if (this.isInitialized) {
            return this.oldestDate;
        }

        try {
            const response = await fetch(window.valservDashboardData?.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'sentinelpro_get_oldest_date',
                    nonce: window.valservDashboardData?.nonce
                })
            });

            const result = await response.json();
            
            if (result.success && result.data.oldest_date) {
                this.oldestDate = result.data.oldest_date;
                this.oldestDateMoment = moment(this.oldestDate);
                this.isInitialized = true;
                
                return this.oldestDate;
            } else {
                // Fallback to 30 days ago
                this.oldestDate = moment().subtract(30, 'days').format('YYYY-MM-DD');
                this.oldestDateMoment = moment(this.oldestDate);
                this.isInitialized = true;
                return this.oldestDate;
            }
        } catch (error) {
            // Fallback to 30 days ago
            this.oldestDate = moment().subtract(30, 'days').format('YYYY-MM-DD');
            this.oldestDateMoment = moment(this.oldestDate);
            this.isInitialized = true;
            return this.oldestDate;
        }
    }

    /**
     * Get the oldest available date
     */
    getOldestDate() {
        return this.oldestDate;
    }

    /**
     * Get the oldest available date as a moment object
     */
    getOldestDateMoment() {
        return this.oldestDateMoment;
    }

    /**
     * Check if a date is valid (not older than the oldest available data and not in the future)
     */
    isDateValid(date) {
        if (!this.isInitialized || !this.oldestDateMoment) {
            // Even if not initialized, don't allow future dates
            const checkDate = moment(date);
            return checkDate.isSameOrBefore(moment(), 'day');
        }

        const checkDate = moment(date);
        const today = moment();
        
        // Date must be between oldest available data and today (inclusive)
        return checkDate.isSameOrAfter(this.oldestDateMoment, 'day') && 
               checkDate.isSameOrBefore(today, 'day');
    }

    /**
     * Check if a date is in the future
     */
    isDateInFuture(date) {
        const checkDate = moment(date);
        const today = moment();
        return checkDate.isAfter(today, 'day');
    }

    /**
     * Check if a date is too old (before the oldest available data)
     */
    isDateTooOld(date) {
        if (!this.isInitialized || !this.oldestDateMoment) {
            return false; // Can't determine if too old if not initialized
        }

        const checkDate = moment(date);
        return checkDate.isBefore(this.oldestDateMoment, 'day');
    }

    /**
     * Validate a date range
     */
    validateRange(startDate, endDate) {
        const startValid = this.isDateValid(startDate);
        const endValid = this.isDateValid(endDate);
        
        let message = 'Valid range';
        if (!startValid || !endValid) {
            if (!startValid && !endValid) {
                message = `Date range must be between ${this.oldestDate} and today`;
            } else if (!startValid) {
                message = `Start date cannot be older than ${this.oldestDate}`;
            } else if (!endValid) {
                message = `End date cannot be in the future`;
            }
        }
        
        return {
            valid: startValid && endValid,
            startValid,
            endValid,
            oldestDate: this.oldestDate,
            message: message
        };
    }

    /**
     * Get the minimum date for date picker configuration
     */
    getMinimumDate() {
        return this.oldestDateMoment || moment().subtract(30, 'days');
    }

    /**
     * Get the maximum date for date picker configuration (today)
     */
    getMaximumDate() {
        return moment();
    }

    /**
     * Get preset ranges that respect the oldest date limit
     */
    getValidPresetRanges() {
        if (!this.isInitialized || !this.oldestDateMoment) {
            // Return default ranges if not initialized
            return {
                'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 days': [moment().subtract(7, 'days'), moment().subtract(1, 'days')],
                'Last 31 days': [moment().subtract(31, 'days'), moment().subtract(1, 'days')],
                'Last 12 months': [moment().subtract(364, 'days'), moment().subtract(1, 'days')],
                'Year to Date': [moment().startOf('year'), moment().subtract(1, 'days')],
            };
        }

        const today = moment();
        const yesterday = moment().subtract(1, 'days');
        const ranges = {};

        // Yesterday (if valid)
        if (yesterday.isSameOrAfter(this.oldestDateMoment, 'day')) {
            ranges['Yesterday'] = [yesterday, yesterday];
        }

        // Last 7 days (adjust start if needed)
        const sevenDaysAgo = moment().subtract(7, 'days');
        const startSeven = moment.max(sevenDaysAgo, this.oldestDateMoment);
        if (startSeven.isBefore(yesterday)) {
            ranges['Last 7 days'] = [startSeven, yesterday];
        }

        // Last 31 days (adjust start if needed)
        const thirtyOneDaysAgo = moment().subtract(31, 'days');
        const startThirtyOne = moment.max(thirtyOneDaysAgo, this.oldestDateMoment);
        if (startThirtyOne.isBefore(yesterday)) {
            ranges['Last 31 days'] = [startThirtyOne, yesterday];
        }

        // Last 12 months (adjust start if needed)
        const twelveMonthsAgo = moment().subtract(364, 'days');
        const startTwelveMonths = moment.max(twelveMonthsAgo, this.oldestDateMoment);
        if (startTwelveMonths.isBefore(yesterday)) {
            ranges['Last 12 months'] = [startTwelveMonths, yesterday];
        }

        // Year to Date (adjust start if needed)
        const yearStart = moment().startOf('year');
        const startYTD = moment.max(yearStart, this.oldestDateMoment);
        if (startYTD.isBefore(yesterday)) {
            ranges['Year to Date'] = [startYTD, yesterday];
        }

        // All time (from oldest date to yesterday)
        if (this.oldestDateMoment.isBefore(yesterday)) {
            ranges['All Available Data'] = [this.oldestDateMoment, yesterday];
        }

        return ranges;
    }

    /**
     * Show user-friendly message about date limitations
     */
    getDateLimitationMessage() {
        if (!this.isInitialized || !this.oldestDate) {
            return 'Date selection is limited to today and earlier.';
        }
        
        return `Date selection is limited to data from ${this.oldestDate} onwards, up to today.`;
    }

    /**
     * Get specific error message for a date validation issue
     */
    getDateErrorMessage(date, type = 'start') {
        if (this.isDateInFuture(date)) {
            return `${type === 'start' ? 'Start' : 'End'} date cannot be in the future.`;
        }
        
        if (this.isDateTooOld(date)) {
            return `${type === 'start' ? 'Start' : 'End'} date cannot be older than ${this.oldestDate}.`;
        }
        
        return '';
    }
}

// Create a global instance
window.sentinelProDateValidator = new DateRangeValidator();