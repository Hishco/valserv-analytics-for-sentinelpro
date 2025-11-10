// DashboardHelpers/GenericDropdownHelper.js

export function updateGenericFilterCount(dimCamel) {
    const apiKey = dimCamel.charAt(0).toLowerCase() + dimCamel.slice(1);
    const header = document.querySelector('.sentinelpro-filter-item-header[data-api-key="' + apiKey + '"]');
    if (!header) return;
    let countSpan = header.querySelector('.sentinelpro-filter-count');
    if (!countSpan) {
      countSpan = document.createElement('span');
      countSpan.className = 'sentinelpro-filter-count';
      header.insertBefore(countSpan, header.querySelector('.sentinelpro-filter-chevron'));
    }
    const wrapper = document.querySelector('.sentinelpro-generic-dropdown-wrapper[data-dimension="' + dimCamel + '"]');
    const dropdown = wrapper?.querySelector('.sentinelpro-generic-dropdown');
    if (!dropdown) return;
    const checked = dropdown.querySelectorAll('input[type="checkbox"]:checked').length;
    countSpan.textContent = checked > 0 ? checked : '';
  }
  
  export function updateGenericContainsState(wrapper, dropdown) {
    const containsInput = wrapper.querySelector('.sentinelpro-generic-contains-search');
    const dropdownList = dropdown.querySelector('.dropdown-list');
    if (!containsInput || !dropdownList) return;
    const checked = dropdownList.querySelectorAll('input[type="checkbox"]:checked').length;
    containsInput.disabled = checked > 0;
  }
  
  export function updateGenericExactState(wrapper, dropdown) {
    const containsInput = wrapper.querySelector('.sentinelpro-generic-contains-search');
    const exactInput = wrapper.querySelector('.sentinelpro-generic-exact-search');
    if (!containsInput || !exactInput) return;
    const hasValue = containsInput.value && containsInput.value.trim().length > 0;
    exactInput.disabled = hasValue;
    if (hasValue) {
      dropdown.style.display = 'none';
    }
  }

  function syncDropdownWithFilterState(wrapper, dropdown, dimCamel) {
    // Clear the dropdown list
    const dropdownList = dropdown.querySelector('.dropdown-list');
    dropdownList.innerHTML = '';
    // Get current filter state for this dimension
    const filters = window.orchestrator?.filterBuilder?.getSelectedFilters?.() || {};
    const apiKey = dimCamel.toLowerCase();
    const checkedValues = Array.isArray(filters[apiKey]) ? filters[apiKey] : (filters[apiKey] ? [filters[apiKey]] : []);
    checkedValues.forEach(val => {
      const li = document.createElement('li');
      li.className = 'dropdown-option';
      li.innerHTML = `
        <label>
          <input type="checkbox" class="generic-checkbox" value="${val.replace(/"/g, '&quot;')}" checked />
          <span class="option-label">${val}</span>
          <span class="option-value"></span>
          <span class="option-remove" title="Remove"><span>&#10006;</span></span>
        </label>`;
      dropdownList.appendChild(li);
    });
  }

  export function setupGenericDropdownListeners() {
    document.querySelectorAll('.sentinelpro-generic-dropdown-wrapper').forEach(function(wrapper) {
      const dimCamel = wrapper.getAttribute('data-dimension');
      const dimClass = 'generic';
      const exactInput = wrapper.querySelector('.sentinelpro-generic-exact-search');
      const containsInput = wrapper.querySelector('.sentinelpro-generic-contains-search');
      const dropdown = wrapper.querySelector('.sentinelpro-generic-dropdown');
      const dropdownSearch = dropdown.querySelector('.dropdown-search');
      const dropdownList = dropdown.querySelector('.dropdown-list');

      // Helper: get/set stack for this dimension
      function getStack() {
          const key = 'sentinelpro_' + dimCamel.toLowerCase() + 'Stack';
          window[key] = window[key] || [];
          return window[key];
      }
      function setStack(arr) {
          const key = 'sentinelpro_' + dimCamel.toLowerCase() + 'Stack';
          window[key] = arr;
      }
      // Helper: get original options (from DOM at first load)
      let originalOptions = Array.from(dropdownList.querySelectorAll('.option-label')).map(el => el.textContent);

      // Render dropdown list from union of original options and stack
      function renderDropdownList() {
          const stack = getStack();
          const allOptions = Array.from(new Set([...originalOptions, ...stack]));
          dropdownList.innerHTML = '';
          allOptions.forEach(val => {
              const checked = stack.includes(val);
              const li = document.createElement('li');
              li.className = 'dropdown-option';
              li.innerHTML = '<label>' +
                  '<input type="checkbox" class="' + dimClass + '-checkbox" value="' + val.replace(/"/g, '&quot;') + '"' + (checked ? ' checked' : '') + ' />' +
                  '<span class="option-label">' + val + '</span>' +
                  '<span class="option-value"></span>' +
                  '<span class="option-remove" title="Remove"><span>&#10006;</span></span>' +
                  '</label>';
              dropdownList.appendChild(li);
          });
      }

      // On dropdown open, re-render list
      function openDropdown() {
          renderDropdownList();
          dropdown.style.display = 'block';
          setTimeout(function() {
              dropdownSearch.focus();
              dropdownSearch.select();
          }, 0);
      }
      // Toggle dropdown on click
      exactInput.addEventListener('mousedown', function(e) {
          if (exactInput.disabled) {
              e.preventDefault();
              return false;
          }
          if (dropdown.style.display === 'block') {
              dropdown.style.display = 'none';
          } else {
              openDropdown();
          }
      });
      // Show dropdown on focus (if not already open)
      exactInput.addEventListener('focus', function() {
          if (exactInput.disabled) {
              exactInput.blur();
              return false;
          }
          if (dropdown.style.display !== 'block') {
              openDropdown();
          }
      });
      // Hide dropdown when clicking outside
      document.addEventListener('mousedown', function(e) {
          if (!wrapper.contains(e.target)) {
              dropdown.style.display = 'none';
          }
      });
      // Add new option on Enter in dropdown search
      dropdownSearch.addEventListener('keydown', function(e) {
          if (e.key === 'Enter') {
              e.preventDefault();
              var value = dropdownSearch.value.trim();
              if (!value) return;
              var stack = getStack();
              if (!stack.includes(value)) {
                  stack.push(value);
                  setStack(stack);
              }
              dropdownSearch.value = '';
              renderDropdownList();
              // Update Selected Filters list (but do NOT auto-render/fetch)
              if (window.EnhancedDashboardInstance) {
                  window.EnhancedDashboardInstance.updateFilterTags(window.EnhancedDashboardInstance.getSelectedFilters());
              }
          }
      });
      // Remove option when clicking the red x
      dropdownList.addEventListener('click', function(e) {
          if (e.target.closest('.option-remove')) {
              var li = e.target.closest('li.dropdown-option');
              if (li) {
                  var val = li.querySelector('.option-label').textContent;
                  var stack = getStack().filter(v => v !== val);
                  setStack(stack);
                  renderDropdownList();
                  // Update Selected Filters list (but do NOT auto-render/fetch)
                  if (window.EnhancedDashboardInstance) {
                      window.EnhancedDashboardInstance.updateFilterTags(window.EnhancedDashboardInstance.getSelectedFilters());
                  }
              }
              setTimeout(function() { updateGenericFilterCount(dimCamel); updateGenericContainsState(wrapper, dropdown); }, 10);
              e.stopPropagation();
          }
      });
      // After any change to checkboxes, always update the Contains state
      dropdownList.addEventListener('change', function(e) {
          if (e.target.type === 'checkbox') {
              var val = e.target.closest('label').querySelector('.option-label').textContent;
              var stack = getStack();
              if (e.target.checked) {
                  if (!stack.includes(val)) stack.push(val);
              } else {
                  stack = stack.filter(v => v !== val);
              }
              setStack(stack);
              updateGenericFilterCount(dimCamel);
              updateGenericContainsState(wrapper, dropdown); // <-- ensure Contains is updated
              // Update Selected Filters list AND trigger data reload/render
              if (window.EnhancedDashboardInstance) {
                  window.EnhancedDashboardInstance.updateFilterTags(window.EnhancedDashboardInstance.getSelectedFilters());
                  if (typeof window.EnhancedDashboardInstance.applyFilters === 'function') {
                      window.EnhancedDashboardInstance.applyFilters();
                  }
              }
          }
      });
      // Also update Contains state after adding a new option
      dropdownSearch.addEventListener('keydown', function(e) {
          if (e.key === 'Enter') {
              setTimeout(function() { updateGenericFilterCount(dimCamel); updateGenericContainsState(wrapper, dropdown); }, 10);
          }
      });
      // Also update Contains state after removing an option
      dropdownList.addEventListener('click', function(e) {
          if (e.target.closest('.option-remove')) {
              setTimeout(function() { updateGenericContainsState(wrapper, dropdown); }, 10);
          }
      });
      // Contains/Exact mutual exclusivity
      function updateGenericContainsState(wrapper, dropdown) {
          const containsInput = wrapper.querySelector('.sentinelpro-generic-contains-search');
          const dropdownList = dropdown.querySelector('.dropdown-list');
          if (!containsInput || !dropdownList) return;
          const checked = dropdownList.querySelectorAll('input[type="checkbox"]:checked').length;
          containsInput.disabled = checked > 0;
      }
      function updateGenericExactState(wrapper, dropdown) {
          const containsInput = wrapper.querySelector('.sentinelpro-generic-contains-search');
          const exactInput = wrapper.querySelector('.sentinelpro-generic-exact-search');
          if (!containsInput || !exactInput) return;
          const hasValue = containsInput.value && containsInput.value.trim().length > 0;
          exactInput.disabled = hasValue;
          if (hasValue) {
              dropdown.style.display = 'none';
          }
      }
      // Update Exact state after any input in Contains
      containsInput.addEventListener('input', function() {
          updateGenericExactState(wrapper, dropdown);
      });
      
      // Add Enter key handler for contains search
      containsInput.addEventListener('keydown', function(e) {
          if (e.key === 'Enter') {
              e.preventDefault();
              if (window.EnhancedDashboardInstance?.applyFilters) {
                  window.EnhancedDashboardInstance.applyFilters();
              }
          }
      });
      // Also update Exact state after any change to checkboxes or options
      dropdownList.addEventListener('change', function() {
          updateGenericExactState(wrapper, dropdown);
      });
      dropdownList.addEventListener('click', function(e) {
          if (e.target.closest('.option-remove')) {
              setTimeout(function() { updateGenericExactState(wrapper, dropdown); }, 10);
          }
      });
      // Initial state
      setTimeout(function() { updateGenericContainsState(wrapper, dropdown); updateGenericExactState(wrapper, dropdown); }, 100);
      // Also update on filter clear
      const clearFiltersBtn = document.getElementById('clear-filters');
      if (clearFiltersBtn) {
          clearFiltersBtn.addEventListener('click', function() {
              setTimeout(function() { updateGenericContainsState(wrapper, dropdown); updateGenericExactState(wrapper, dropdown); }, 10);
          });
      }
    });
  }