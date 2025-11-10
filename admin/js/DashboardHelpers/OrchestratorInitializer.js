import { getElementReferences } from './DOMInitializer.js';
import DatabaseCacheManager from './DatabaseCacheManager.js';
import DatabaseDataFetcher from './DatabaseDataFetcher.js';
import ChartRenderer from './ChartRenderer.js';
import TableRenderer from './TableRenderer.js';
import FilterBuilder from './FilterBuilder.js';
import StatusManager from './StatusManager.js';
import DataOrchestrator from './DataOrchestrator.js';

export function setupOrchestratorInstance(dashboardInstance) {
  if (typeof window === 'undefined') return;

  if (!window.EnhancedDashboardInstance) {
    window.EnhancedDashboardInstance = dashboardInstance;
  }

  // Use existing orchestrator from dashboard-main.js if available
  if (window.orchestrator) {
    return;
  }

  // Fallback: create new orchestrator if one doesn't exist
  const refs = getElementReferences();
  const cacheManager = new DatabaseCacheManager();
  const dataFetcher = new DatabaseDataFetcher(window.valservDashboardData?.ajaxUrl);
  const chartRenderer = new ChartRenderer(refs.chartDiv);
  const tableRenderer = new TableRenderer(refs.chartTableWrapper);
  const filterBuilder = new FilterBuilder(refs);
  const status = new StatusManager(refs.spinner);

  window.orchestrator = new DataOrchestrator({
    dataFetcher,
    chartRenderer,
    tableRenderer,
    status,
    dateManager: null,
    filterBuilder,
    spinner: refs.spinner,
    refs
  });

  const metric = refs.metricSelect?.value || 'sessions';
  if (typeof window.orchestrator.fetch === 'function') {
    dashboardInstance.isFetching = true;
    window.orchestrator.fetch(metric).finally(() => {
      dashboardInstance.isFetching = false;
      dashboardInstance.hideLoading?.();
      dashboardInstance.status?.clear?.();
    });
  }
}
