// DashboardHelpers/ComparePickerInitializer.js

import CompareDatePicker from './CompareDatePicker.js';

export function setupDatePickerListeners({
  startInput,
  endInput,
  compareStartInput,
  compareEndInput,
  dateRangeInput,
  onRangeApplied
}) {
  if (!dateRangeInput || !startInput || !endInput) {
    return;
  }

  const comparePicker = new CompareDatePicker({
    startInput,
    endInput,
    compareStartInput,
    compareEndInput,
    dateRangeInput,
    onRangeApplied
  });

  comparePicker.initialize(startInput.value, endInput.value);

  if (startInput.value && endInput.value) {
    dateRangeInput.value = `${startInput.value} to ${endInput.value}`;
    if (typeof window.EnhancedDashboardInstance?.dateUIManager?.updateDateRangeDisplay === 'function') {
      window.EnhancedDashboardInstance.dateUIManager.updateDateRangeDisplay();
    }
  }

  if (window.jQuery && dateRangeInput) {
    jQuery(dateRangeInput).on('keydown.daterangepicker', function (e) {
      if (e.key === 'Shift' || e.keyCode === 16) {
        e.stopPropagation();
        e.preventDefault();
        return false;
      }
    });
  }

  return comparePicker;
}
