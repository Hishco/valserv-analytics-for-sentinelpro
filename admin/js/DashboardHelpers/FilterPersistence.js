// DashboardHelpers/FilterPersistence.js

export default class FilterPersistence {
    static collectActiveDimensions() {
      const activeKeys = new Set();
  
      document.querySelectorAll('.sentinelpro-filter-item-header[data-api-key]').forEach(header => {
        const apiKey = header.getAttribute('data-api-key');
        const group = header.parentElement.querySelector('.sentinelpro-checkbox-group');
  
        if (apiKey === 'geo') {
          const geoSection = header.parentElement;
          const checkedMain = geoSection.querySelectorAll('#geo-checkbox-group input[type="checkbox"]:checked').length;
          const checkedCustom = geoSection.querySelectorAll('#geo-custom-checkboxes input[type="checkbox"]:checked').length;
          if ((checkedMain + checkedCustom) > 0) {
            activeKeys.add(apiKey);
          }
        } else if (apiKey && group) {
          const checked = group.querySelectorAll('input[type="checkbox"]:checked');
          if (checked.length > 0) {
            activeKeys.add(apiKey);
          }
        }
      });
  
      return Array.from(activeKeys);
    }
  
    static applyToGlobalNamespace() {
      window.sentinelpro_activeDimensions = FilterPersistence.collectActiveDimensions();
    }
  }