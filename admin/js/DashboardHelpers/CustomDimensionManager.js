// DashboardHelpers/CustomDimensionManager.js

export default class CustomDimensionManager {
    static updateHiddenField() {
      window.sentinelpro_contenttypeStack = window.sentinelpro_contenttypeStack || [];
  
      const filters = {};
      const searchInputs = document.querySelectorAll('.sentinelpro-custom-search');
  
      searchInputs.forEach(input => {
        const dimension = input.getAttribute('data-dimension');
        const value = input.value.trim();
  
        if (dimension === 'contentType' && value) {
          if (!window.sentinelpro_contenttypeStack.includes(value)) {
            window.sentinelpro_contenttypeStack.push(value);
          }
          input.value = '';
        }
      });
  
      if (window.sentinelpro_contenttypeStack.length > 0) {
        filters['contenttype'] = window.sentinelpro_contenttypeStack.join(',');
      }
  
      let hidden = document.getElementById('sentinelpro-custom-dimension-filters');
      if (!hidden) {
        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.id = 'sentinelpro-custom-dimension-filters';
        hidden.name = 'sentinelpro_custom_dimension_filters';
        document.body.appendChild(hidden);
      }
  
      hidden.value = JSON.stringify(filters);
    }
  }