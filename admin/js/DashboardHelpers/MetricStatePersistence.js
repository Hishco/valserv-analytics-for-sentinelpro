// DashboardHelpers/MetricStatePersistence.js
export default class MetricStatePersistence {
  static save(refs) {
    const metric = refs.metricSelect?.value || '';
    const granularity = refs.granularitySelect?.value || 'daily';

    localStorage.setItem('sentinelpro_last_metric', metric);
    localStorage.setItem('sentinelpro_last_granularity', granularity);
  }

  static restore(refs) {
    const savedMetric = localStorage.getItem('sentinelpro_last_metric');
    const savedGranularity = localStorage.getItem('sentinelpro_last_granularity');

    if (savedMetric && refs.metricSelect) refs.metricSelect.value = savedMetric;
    if (savedGranularity && refs.granularitySelect) refs.granularitySelect.value = savedGranularity;

  }

  static clear() {
    localStorage.removeItem('sentinelpro_last_metric');
    localStorage.removeItem('sentinelpro_last_granularity');
    localStorage.removeItem('sentinelpro_last_submetrics_traffic');
    localStorage.removeItem('sentinelpro_last_submetrics_engagement');
  }
}
