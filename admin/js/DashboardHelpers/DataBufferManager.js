// DashboardHelpers/DataBufferManager.js

export function shouldSkipRedundantUpdate(newMainData, prevMainData, prevComparisonData) {
    const mainUnchanged = Array.isArray(prevMainData) &&
      Array.isArray(newMainData) &&
      prevMainData.length === newMainData.length &&
      prevMainData.every((row, i) => row === newMainData[i]);
  
    // Only skip if main data is unchanged AND we have comparison data
    // This allows re-rendering when date ranges change even if the underlying data is the same
    return mainUnchanged &&
      Array.isArray(prevComparisonData) &&
      prevComparisonData.length > 0;
  }
  
  export function updateBufferedData(instance, newMainData, newComparisonData) {
    instance._bufferedMainData = newMainData;
  
    if (newComparisonData === null) {
      instance._bufferedComparisonData = null;
    } else if (newComparisonData !== undefined) {
      instance._bufferedComparisonData = newComparisonData;
    }
  }