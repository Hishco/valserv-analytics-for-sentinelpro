// Dashboard Filters JavaScript
document.addEventListener("DOMContentLoaded", function() {
    var applyBtn = document.getElementById("apply-filters");
    if (applyBtn) {
        applyBtn.addEventListener("click", function() {
            var filters = {};
            document.querySelectorAll(".sentinelpro-custom-search").forEach(function(input) {
                var dimension = input.getAttribute("data-dimension");
                var mode = input.getAttribute("data-mode");
                var value = input.value.trim();
                if (value) {
                    // Only set if not already set (so 'exact' takes precedence if both are filled)
                    if (!filters[dimension]) {
                        filters[dimension] = { type: mode, value: value };
                    }
                }
            });
            var hidden = document.getElementById("sentinelpro-custom-dimension-filters");
            if (!hidden) {
                hidden = document.createElement("input");
                hidden.type = "hidden";
                hidden.id = "sentinelpro-custom-dimension-filters";
                hidden.name = "sentinelpro_custom_dimension_filters";
                applyBtn.form && applyBtn.form.appendChild(hidden);
            }
            hidden.value = JSON.stringify(filters);
        });
    }
    document.querySelectorAll('.sentinelpro-custom-search[data-mode="exact"]').forEach(function(exactInput) {
        exactInput.addEventListener("input", function() {
            var containsInput = exactInput.parentElement.querySelector('.sentinelpro-custom-search[data-mode="contains"]');
            if (exactInput.value) containsInput.disabled = true;
            else containsInput.disabled = false;
        });
    });
    document.querySelectorAll('.sentinelpro-custom-search[data-mode="contains"]').forEach(function(containsInput) {
        containsInput.addEventListener("input", function() {
            var exactInput = containsInput.parentElement.querySelector('.sentinelpro-custom-search[data-mode="exact"]');
            if (containsInput.value) exactInput.disabled = true;
            else exactInput.disabled = false;
        });
        
        // Add Enter key handler for contains search
        containsInput.addEventListener("keydown", function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (window.EnhancedDashboardInstance && window.EnhancedDashboardInstance.applyFilters) {
                    window.EnhancedDashboardInstance.applyFilters();
                }
            }
        });
    });
});