// DashboardHelpers/DebounceUtils.js

let debounceTimeout;
export function debouncedFetch(metric, fetchFn) {
  clearTimeout(debounceTimeout);
  debounceTimeout = setTimeout(() => {
    fetchFn(metric);
  }, 300);
}

let sentinelproFetchTimeout;
export function safeFetch(fetchFn) {
  clearTimeout(sentinelproFetchTimeout);
  sentinelproFetchTimeout = setTimeout(() => {
    fetchFn();
  }, 100);
}
