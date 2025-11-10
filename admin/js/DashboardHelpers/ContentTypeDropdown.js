// DashboardHelpers/ContentTypeDropdown.js

export function renderContentTypeDropdown(contentTypes, selectedContentTypes, {
    onUpdateHiddenField = () => {},
    onApplyFilters = () => {}
  } = {}) {
    let dropdown = document.getElementById('contenttype-dropdown-panel');
    if (!dropdown) {
      dropdown = document.createElement('div');
      dropdown.id = 'contenttype-dropdown-panel';
      dropdown.className = 'dropdown-panel';
      dropdown.style.position = 'absolute';
      dropdown.style.zIndex = 1000;
      document.body.appendChild(dropdown);
    }
  
    dropdown.innerHTML = `
      <div class="dropdown-header">
        <span>Content type</span>
        <span>Sessions</span>
      </div>
      <div class="dropdown-search">
        <input type="text" placeholder="Type to search" id="contenttype-searchbox" />
      </div>
      <div class="dropdown-list" id="contenttype-list"></div>
    `;
  
    const trigger = document.getElementById('contenttype-dropdown-trigger');
    if (trigger) {
      const rect = trigger.getBoundingClientRect();
      dropdown.style.left = `${rect.left}px`;
      dropdown.style.top = `${rect.bottom + window.scrollY}px`;
      dropdown.style.width = `${rect.width}px`;
    }
  
    function renderList(filter = '') {
      const list = document.getElementById('contenttype-list');
      list.innerHTML = '';
      contentTypes
        .filter(ct => ct.name.toLowerCase().includes(filter.toLowerCase()))
        .forEach(ct => {
          const isChecked = selectedContentTypes.includes(ct.name);
          const item = document.createElement('div');
          item.className = 'dropdown-item' + (isChecked ? ' selected' : '');
          item.innerHTML = `
            <label>
              <input type="checkbox" ${isChecked ? 'checked' : ''} data-name="${ct.name}">
              ${ct.name}
            </label>
            <span class="sessions">${ct.sessions}</span>
          `;
          list.appendChild(item);
        });
    }
  
    renderList();
  
    document.getElementById('contenttype-searchbox').addEventListener('input', (e) => {
      renderList(e.target.value);
    });
    
    // Add Enter key handler for content type search
    document.getElementById('contenttype-searchbox').addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        if (window.EnhancedDashboardInstance?.applyFilters) {
          window.EnhancedDashboardInstance.applyFilters();
        }
      }
    });
  
    document.getElementById('contenttype-list').addEventListener('change', (e) => {
      if (e.target.type === 'checkbox') {
        const name = e.target.getAttribute('data-name');
        if (e.target.checked) {
          if (!window.sentinelpro_contenttypeStack.includes(name)) {
            window.sentinelpro_contenttypeStack.push(name);
          }
        } else {
          window.sentinelpro_contenttypeStack = window.sentinelpro_contenttypeStack.filter(n => n !== name);
        }
        renderList(document.getElementById('contenttype-searchbox').value);
        onUpdateHiddenField();
        onApplyFilters();
      }
    });
}

export function setupLegacyContentTypeDropdownUI() {
    const wrapper = document.querySelector('.sentinelpro-contenttype-dropdown-wrapper');
    if (!wrapper) return;
  
    const exactInput = wrapper.querySelector('.sentinelpro-contenttype-exact-search');
    const dropdown = wrapper.querySelector('.sentinelpro-dropdown-contenttype');
    const dropdownSearch = dropdown.querySelector('.dropdown-search');
    let options = dropdown.querySelectorAll('.dropdown-option');
    let dropdownJustOpened = false;
  
    // Toggle dropdown on click
    exactInput.addEventListener('mousedown', function (e) {
      e.preventDefault();
      dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
      if (dropdown.style.display === 'block') {
        dropdownJustOpened = true;
        setTimeout(() => (dropdownJustOpened = false), 200);
        dropdownSearch.focus();
        dropdownSearch.select();
      }
    });
  
    // Show dropdown on focus (if not already open)
    exactInput.addEventListener('focus', function () {
      if (dropdown.style.display !== 'block' && !dropdownJustOpened) {
        dropdown.style.display = 'block';
        dropdownSearch.focus();
        dropdownSearch.select();
      }
    });
  
    // Hide dropdown when clicking outside
    document.addEventListener('mousedown', function (e) {
      if (!wrapper.contains(e.target)) {
        dropdown.style.display = 'none';
      }
    });
  
    // Filter dropdown options
    dropdownSearch.addEventListener('input', function (e) {
      const search = e.target.value.toLowerCase();
      options.forEach(option => {
        const label = option.querySelector('.option-label').textContent.toLowerCase();
        option.style.display = label.includes(search) ? '' : 'none';
      });
    });
  
    // Add new option on Enter
    dropdownSearch.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        const value = dropdownSearch.value.trim();
        if (!value) return;
  
        options = dropdown.querySelectorAll('.dropdown-option');
        const exists = Array.from(options).some(option =>
          option.querySelector('.option-label').textContent.toLowerCase() === value.toLowerCase()
        );
  
        if (!exists) {
          const li = document.createElement('li');
          li.className = 'dropdown-option';
          li.innerHTML = `
            <label>
              <input type="checkbox" class="contenttype-checkbox" value="${value.replace(/"/g, '&quot;')}" checked />
              <span class="option-label">${value}</span>
              <span class="option-value"></span>
              <span class="option-remove" title="Remove"><span>&#10006;</span></span>
            </label>`;
          dropdown.querySelector('.dropdown-list').appendChild(li);
        }
        dropdownSearch.value = '';
      }
    });
  
    // Remove option via red X
    dropdown.querySelector('.dropdown-list').addEventListener('click', function (e) {
      if (e.target.closest('.option-remove')) {
        const li = e.target.closest('li.dropdown-option');
        if (li) li.remove();
        e.stopPropagation();
      }
    });
}
  
export function setupContentTypeFilterCounter() {
    const updateContentTypeFilterCount = () => {
      const header = document.querySelector('.sentinelpro-filter-item-header[data-api-key="contentType"]');
      if (!header) return;
  
      let countSpan = header.querySelector('.sentinelpro-filter-count');
      if (!countSpan) {
        countSpan = document.createElement('span');
        countSpan.className = 'sentinelpro-filter-count';
        header.insertBefore(countSpan, header.querySelector('.sentinelpro-filter-chevron'));
      }
  
      const checked = document.querySelectorAll('.sentinelpro-dropdown-contenttype input[type="checkbox"]:checked').length;
      countSpan.textContent = checked > 0 ? checked : '';
    };
  
    const dropdownList = document.querySelector('.sentinelpro-dropdown-contenttype .dropdown-list');
    if (dropdownList) {
      dropdownList.addEventListener('change', e => {
        if (e.target.type === 'checkbox') updateContentTypeFilterCount();
      });
      dropdownList.addEventListener('click', e => {
        if (e.target.closest('.option-remove')) {
          setTimeout(updateContentTypeFilterCount, 10);
        }
      });
    }
  
    const dropdownSearch = document.querySelector('.sentinelpro-dropdown-contenttype .dropdown-search');
    if (dropdownSearch) {
      dropdownSearch.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
          setTimeout(updateContentTypeFilterCount, 10);
        }
      });
    }
  }
  
  export function setupContentTypeContainsDisableLogic() {
    const updateContainsState = () => {
      const containsInput = document.querySelector('.sentinelpro-contenttype-contains-search');
      const dropdownList = document.querySelector('.sentinelpro-dropdown-contenttype .dropdown-list');
      if (!containsInput || !dropdownList) return;
  
      const checked = dropdownList.querySelectorAll('input[type="checkbox"]:checked').length;
      containsInput.disabled = checked > 0;
    };
  
    const dropdownList = document.querySelector('.sentinelpro-dropdown-contenttype .dropdown-list');
    if (dropdownList) {
      dropdownList.addEventListener('change', e => {
        if (e.target.type === 'checkbox') updateContainsState();
      });
      dropdownList.addEventListener('click', e => {
        if (e.target.closest('.option-remove')) {
          setTimeout(updateContainsState, 10);
        }
      });
    }
  
    const dropdownSearch = document.querySelector('.sentinelpro-dropdown-contenttype .dropdown-search');
    if (dropdownSearch) {
      dropdownSearch.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
          setTimeout(updateContainsState, 10);
        }
      });
    }
  
    const clearFiltersBtn = document.getElementById('clear-filters');
    if (clearFiltersBtn) {
      clearFiltersBtn.addEventListener('click', () => {
        setTimeout(updateContainsState, 10);
      });
    }
  
    setTimeout(updateContainsState, 100);
  }
  
  export function setupContentTypeExactMatchDisableLogic() {
    const containsInput = document.querySelector('.sentinelpro-contenttype-contains-search');
    const exactInput = document.querySelector('.sentinelpro-contenttype-exact-search');
    if (!containsInput || !exactInput) return;
  
    const updateExactMatchState = () => {
      const hasValue = containsInput.value && containsInput.value.trim().length > 0;
      exactInput.disabled = hasValue;
      if (hasValue) {
        const dropdown = document.querySelector('.sentinelpro-dropdown-contenttype');
        if (dropdown) dropdown.style.display = 'none';
      }
    };
  
    containsInput.addEventListener('input', updateExactMatchState);
  
    containsInput.addEventListener('keydown', e => {
      if (e.key === 'Enter') {
        e.preventDefault();
        if (window.EnhancedDashboardInstance?.applyFilters) {
          window.EnhancedDashboardInstance.applyFilters();
        }
      }
    });
  
    setTimeout(updateExactMatchState, 100);
  }   