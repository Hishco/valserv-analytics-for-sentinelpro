// DashboardHelpers/DataProcessor.js

import { toAlpha2Geo } from './DataUtils.js';
import { generateDateRange } from './DataUtils.js';

export function processAndAggregateData(mainData = [], comparisonData = [], selectedFilters = {}) {
  // Get the requested date range from the UI
  const startInput = document.getElementById('filter-start');
  const endInput = document.getElementById('filter-end');
  const requestedStartDate = startInput?.value;
  const requestedEndDate = endInput?.value;

  // --- Filter main data based on selected filters ---
  let filteredData = [...mainData];

  const hasFilters = selectedFilters && Object.keys(selectedFilters).length > 0 && Object.values(selectedFilters).some(v => v && (Array.isArray(v) ? v.length > 0 : v !== ''));

  // Check if data has already been filtered at the database level
  // If the data contains aggregated metrics (sessions, views, visits) but no dimension fields,
  // it means the database query already applied the filters
  const isDatabaseFiltered = mainData.length > 0 && 
    mainData[0].hasOwnProperty('sessions') && 
    mainData[0].hasOwnProperty('views') && 
    mainData[0].hasOwnProperty('visits') && 
    (!mainData[0].hasOwnProperty('device') && !mainData[0].hasOwnProperty('geo') && !mainData[0].hasOwnProperty('os') && !mainData[0].hasOwnProperty('browser'));

  // Check if we have device filters but the data doesn't contain device dimension
  const hasDeviceFilter = selectedFilters && selectedFilters.device && selectedFilters.device.length > 0;
  const hasDeviceData = mainData.length > 0 && mainData[0].hasOwnProperty('device');
  
  // If we have device filters but no device data, the database already filtered it
  const isDeviceFiltered = hasDeviceFilter && !hasDeviceData;

  if (hasFilters && !isDatabaseFiltered && !isDeviceFiltered) {
    Object.entries(selectedFilters).forEach(([key, value]) => {
      if (key === 'date' || key.endsWith('_mode')) return; // Skip date and mode keys

      const beforeCount = filteredData.length;
      
      filteredData = filteredData.filter(row => {
        // Try all possible field name variants for the filter key
        const possibleFields = [
          key,
          key.charAt(0).toLowerCase() + key.slice(1),
          key.charAt(0).toUpperCase() + key.slice(1),
          key.toLowerCase(),
          key.toUpperCase(),
          // Add common variations
          key === 'geo' ? 'country' : null,
          key === 'geo' ? 'geography' : null,
          key === 'geo' ? 'location' : null,
          key === 'os' ? 'operatingsystem' : null,
          key === 'adblock' ? 'adblocker' : null,
          // Add custom dimension variations
          key.replace(/([A-Z])/g, '_$1').toLowerCase(),
          key.replace(/([A-Z])/g, '_$1').toUpperCase()
        ].filter(Boolean);
        
        let fieldVal = null;
        for (const f of possibleFields) {
          if (row.hasOwnProperty(f) && row[f] !== undefined && row[f] !== null) {
            fieldVal = row[f];
            break;
          }
        }
        
        if (fieldVal === undefined || fieldVal === null) {
          return false;
        }
        
        // Handle both array and string values
        if (Array.isArray(value)) {
          return value.some(v => String(fieldVal).toLowerCase() === String(v).toLowerCase());
        } else {
          return String(fieldVal).toLowerCase() === String(value).toLowerCase();
        }
      });
    });
  }

  // --- Filter comparison data (but NOT by date filters) ---
  let filteredComparisonData = Array.isArray(comparisonData) ? [...comparisonData] : [];
  

  
  if (hasFilters && !isDatabaseFiltered && !isDeviceFiltered) {
    Object.entries(selectedFilters).forEach(([key, value]) => {
      if (key === 'date' || key.endsWith('_mode')) return; // Skip date and mode keys for comparison data too
      
      filteredComparisonData = filteredComparisonData.filter(row => {
        // Try all possible field name variants for the filter key
        const possibleFields = [
          key,
          key.charAt(0).toLowerCase() + key.slice(1),
          key.charAt(0).toUpperCase() + key.slice(1),
          key.toLowerCase(),
          key.toUpperCase(),
          // Add common variations
          key === 'geo' ? 'country' : null,
          key === 'os' ? 'operatingsystem' : null,
          key === 'adblock' ? 'adblocker' : null,
          // Add custom dimension variations
          key.replace(/([A-Z])/g, '_$1').toLowerCase(),
          key.replace(/([A-Z])/g, '_$1').toUpperCase()
        ].filter(Boolean);
        
        let fieldVal = null;
        for (const f of possibleFields) {
          if (row.hasOwnProperty(f) && row[f] !== undefined && row[f] !== null) {
            fieldVal = row[f];
            break;
          }
        }
        
        if (fieldVal === undefined || fieldVal === null) {
          return false;
        }
        
        // Handle both array and string values
        if (Array.isArray(value)) {
          return value.some(v => String(fieldVal).toLowerCase() === String(v).toLowerCase());
        } else {
          return String(fieldVal).toLowerCase() === String(value).toLowerCase();
        }
      });
    });
  }
  


  // --- Aggregate main data by date (simplified like old version) ---
  const allDates = [...new Set(filteredData.map(row => row.date))].sort();
  const aggregated = {};
  allDates.forEach(date => {
    aggregated[date] = { date, sessions: 0, visits: 0, views: 0 };
  });
  filteredData.forEach(row => {
    const key = row.date;
    if (aggregated[key]) {
      aggregated[key].sessions += Number(row.sessions) || 0;
      aggregated[key].visits += Number(row.visits) || 0;
      aggregated[key].views += Number(row.views) || 0;
    }
  });

  // Fill in missing dates with zero rows - use requested UI range to show full date range
  let filled = Object.values(aggregated);
  if (filled.length > 0) {
    // Use requested UI range to show the full requested date range
    const start = requestedStartDate || filled[0].date;
    const end = requestedEndDate || filled[filled.length - 1].date;
    

    
    // Validate dates before using generateDateRange
    if (start && end && start !== 'Invalid Date' && end !== 'Invalid Date') {
      const fullDates = generateDateRange(start, end);
      const aggMap = Object.fromEntries(filled.map(row => [row.date, row]));
      filled = fullDates.map(date => aggMap[date] || { date, sessions: 0, visits: 0, views: 0 });
    }
  }


    // --- Process comparison data (align to main data date range) ---
  let alignedComparison = [];
  let comparisonOriginalDates = [];

  if (filteredComparisonData.length > 0) {
    // Aggregate comparison data by its own dates first
    const compDates = [...new Set(filteredComparisonData.map(row => row.date))].sort();
    const compAggregated = {};
    
    // Initialize aggregation for each comparison date
    compDates.forEach(date => {
      compAggregated[date] = { date, sessions: 0, visits: 0, views: 0 };
    });
    
    // Aggregate comparison data by date
    filteredComparisonData.forEach(row => {
      const key = row.date;
      if (compAggregated[key]) {
        compAggregated[key].sessions += Number(row.sessions) || 0;
        compAggregated[key].visits += Number(row.visits) || 0;
        compAggregated[key].views += Number(row.views) || 0;
      }
    });
    
    // Convert to array and fill missing dates in comparison range
    let compFilled = Object.values(compAggregated);
    if (compFilled.length > 0) {
      const compStart = compFilled[0].date;
      const compEnd = compFilled[compFilled.length - 1].date;
      
      // Validate dates before using generateDateRange
      if (compStart && compEnd && compStart !== 'Invalid Date' && compEnd !== 'Invalid Date') {
        const compFullDates = generateDateRange(compStart, compEnd);
        const compAggMap = Object.fromEntries(compFilled.map(row => [row.date, row]));
        compFilled = compFullDates.map(date => compAggMap[date] || { date, sessions: 0, visits: 0, views: 0 });
      }
    }
    
    // Now align comparison data to main data date range
    if (filled.length > 0 && compFilled.length > 0) {
      const mainStart = filled[0].date;
      const mainEnd = filled[filled.length - 1].date;
      
      // Create a mapping from comparison dates to main dates
      // This will overlay the comparison data on the main date range
      const compDataMap = {};
      compFilled.forEach((compRow, index) => {
        compDataMap[compRow.date] = compRow;
      });
      
      // Align comparison data to main data dates
      alignedComparison = filled.map((mainRow, index) => {
        // Get the corresponding comparison data by index (cycling if needed)
        const compIndex = index % compFilled.length;
        const compRow = compFilled[compIndex];
        
        return {
          date: mainRow.date, // Use main data date
          sessions: compRow.sessions,
          visits: compRow.visits,
          views: compRow.views
        };
      });
      
      comparisonOriginalDates = alignedComparison.map(d => d.date);
    } else {
      // Fallback: keep comparison data with its original dates
      alignedComparison = compFilled;
      comparisonOriginalDates = compFilled.map(d => d.date);
    }
  }

  return {
    aggregatedMain: filled,
    alignedComparison,
    comparisonOriginalDates
  };
}