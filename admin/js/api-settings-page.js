document.addEventListener('DOMContentLoaded', () => {
    
    
    const form = document.querySelector('#sentinelpro-settings-form');
    const propertyInput = document.querySelector('input[name="sentinelpro_options[property_id]"]');
    const accountNameInput = document.querySelector('input[name="sentinelpro_options[account_name]"]');
    const apiKeyInput = document.querySelector('input[name="sentinelpro_options[api_key]"]');
    const enableTrackingCheckbox = document.querySelector('input[name="sentinelpro_options[enable_tracking]"][type="checkbox"]');

    if (!form || !propertyInput || !accountNameInput || !apiKeyInput) {
                return;
    }

    if (!window.SentinelProAuth) {
        return;
    }
    
    // Add real-time validation - clear errors when user starts typing
    [propertyInput, accountNameInput, apiKeyInput].forEach(input => {
        if (input) {
            input.addEventListener('input', function() {
                const fieldId = this.id;
                const existingError = this.parentNode.querySelector('.field-error');
                if (existingError && this.value.trim()) {
                    existingError.remove();
                    this.style.borderColor = '';
                }
            });
        }
    });

    // Function to show field validation error
    function showFieldError(fieldId, message) {
        const field = document.getElementById(fieldId);
        if (!field) return;
        
        // Remove any existing error message
        const existingError = field.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
        
        // Create error message element
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.style.cssText = 'color: #dc3232; font-size: 13px; margin-top: 4px; font-weight: 500;';
        errorDiv.textContent = message;
        
        // Insert error message after the field
        field.parentNode.appendChild(errorDiv);
        
        // Add error styling to the field
        field.style.borderColor = '#dc3232';
    }
    
    // Function to clear all validation errors
    function clearValidationErrors() {
        // Remove all error messages
        document.querySelectorAll('.field-error').forEach(error => error.remove());
        
        // Reset field borders
        document.querySelectorAll('#property_id, #account_name, #api_key').forEach(field => {
            field.style.borderColor = '';
        });
    }
    
    // Function to detect user's timezone
    function detectUserTimezone() {
        try {
            // Get the user's timezone using Intl.DateTimeFormat
            const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            return userTimezone;
        } catch (error) {
            return null;
        }
    }

    // Utility to fetch clearance from backend
    async function fetchClearanceLevel() {
        const response = await fetch(SentinelProClearance.ajax_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'sentinelpro_get_clearance',
                nonce: SentinelProClearance.nonce
            })
        });
        const data = await response.json();
        return data.success ? data.data.clearance : 'restricted';
    }

    // Function to set clearance level via AJAX
    async function setClearanceLevelViaAJAX(level) {
        try {
            const response = await fetch(SentinelProClearance.ajax_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'sentinelpro_set_clearance',
                    clearance: level,
                    nonce: SentinelProClearance.nonce
                })
            });
            const data = await response.json();
            if (data.success) {
                // Clearance level set successfully
            }
        } catch (error) {
            // Error setting clearance level
        }
    }

    form.addEventListener('submit', async function (e) {
    e.preventDefault(); // ⛔ Prevent native form submit
    

    // Extract values from form fields with sentinelpro_options[field_name] format
    const propertyID = propertyInput.value.trim();
    const accountName = accountNameInput.value.trim();
    const apiKey = apiKeyInput.value.trim();
    const enableTracking = enableTrackingCheckbox.checked ? '1' : '0';

    // Clear any existing error messages
    clearValidationErrors();
    
    let hasErrors = false;
    
    // Validate each field and show inline errors
    if (!propertyID) {
        showFieldError('property_id', 'Property ID is required');
        hasErrors = true;
    }
    
    if (!accountName) {
        showFieldError('account_name', 'Account Name is required');
        hasErrors = true;
    }
    
    if (!apiKey) {
        showFieldError('api_key', 'API Key is required');
        hasErrors = true;
    }
    
    if (hasErrors) {
        // Set clearance to restricted via AJAX, then fetch new value
        await fetch(SentinelProClearance.ajax_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'sentinelpro_set_clearance',
                clearance: 'restricted',
                nonce: SentinelProClearance.nonce
            })
        });
        const newClearance = await fetchClearanceLevel();
        return;
    }

    // 🔍 Auto-detect timezone before submitting
    const detectedTimezone = detectUserTimezone();

    // Debug: Log the data being sent
    const requestData = {
        action: 'sentinelpro_save_auth',
        plan: 'basic',
        token: apiKey,
        property_id: propertyID,
        account_name: accountName,
        user_id: '', // Optional — can be current user
        nonce: SentinelProAuth.nonce,
        enable_tracking: enableTracking,
        cron_timezone: detectedTimezone // 🕐 Add detected timezone
    };

    try {
        const response = await fetch(SentinelProAuth.ajax_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'sentinelpro_save_auth',
                plan: 'basic',
                token: apiKey,
                property_id: propertyID,
                account_name: accountName,
                user_id: '', // Optional — can be current user
                nonce: SentinelProAuth.nonce,
                enable_tracking: enableTracking,
                cron_timezone: detectedTimezone // 🕐 Add detected timezone
            })
        });

        const responseText = await response.text();
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            alert('❌ Invalid response from server. Please check console for details.');
            return;
        }

        // 🔄 Set clearance level from response
        // Check for access restricted message in both 200 and 403 responses
        if (response.status === 200 && data.success === false && data.data && data.data.message) {
            const message = data.data.message.toLowerCase();
            
            if (message.includes('access restricted') || message.includes('access is restricted')) {
                // Handle access restricted message - set to elevated
                
                // Set clearance level to elevated using the existing function
                if (window.EnhancedDashboardInstance && typeof window.EnhancedDashboardInstance.setClearanceLevel === 'function') {
                    window.EnhancedDashboardInstance.setClearanceLevel('elevated');
                } else {
                    // Fallback: use AJAX to set clearance level
                    setClearanceLevelViaAJAX('elevated');
                }
                
                // Show user-friendly message
                alert('🚫 Access is restricted. Your clearance level has been set to "elevated". You can still use the tracking script on the frontend, but access to other plugin pages is limited.');
                
                // Use the redirect URL from server response if available
                if (data.data.redirect) {
                    window.location.href = data.data.redirect;
                } else {
                    // Fallback: construct the admin URL
                    const currentUrl = window.location.href;
                    const adminBase = currentUrl.substring(0, currentUrl.indexOf('/wp-admin/') + 10);
                    const fallbackUrl = adminBase + 'admin.php?page=sentinelpro-api-input';
                    window.location.href = fallbackUrl;
                }
                return; // Prevent further execution
                
            } else if (message.includes('property not found') || message.includes('invalid credentials')) {
                // Handle property not found or invalid credentials - set to restricted
                
                // Set clearance level to restricted
                if (window.EnhancedDashboardInstance && typeof window.EnhancedDashboardInstance.setClearanceLevel === 'function') {
                    window.EnhancedDashboardInstance.setClearanceLevel('restricted');
                } else {
                    // Fallback: use AJAX to set clearance level
                    setClearanceLevelViaAJAX('restricted');
                }
                
                // Show user-friendly message
                alert('❌ Invalid credentials or property not found. Your clearance level has been set to "restricted". Please check your API credentials and property ID.');
                
                // Stay on current page for restricted users
                return; // Prevent further execution
                
            } else {
                // Handle other error types
                
                // For unknown errors, set to restricted as a safety measure
                if (window.EnhancedDashboardInstance && typeof window.EnhancedDashboardInstance.setClearanceLevel === 'function') {
                    window.EnhancedDashboardInstance.setClearanceLevel('restricted');
                } else {
                    setClearanceLevelViaAJAX('restricted');
                }
                
                alert('⚠️ An error occurred while saving settings. Your clearance level has been set to "restricted" for security.');
                return;
            }
        } else if (response.status === 200 && data.message === '🚫 Access is restricted, elevated access applied.') {
            // localStorage.setItem('sentinelpro_clearance', 'elevated');
        } else if (response.status === 200) {
            // localStorage.setItem('sentinelpro_clearance', 'admin');
        } else if (response.status === 403) {
            // Handle 403 "Access is restricted" error by setting clearance to elevated
            if (data.message && data.message.match(/access is restricted/i)) {
                // Set clearance level to elevated using the existing function
                if (window.EnhancedDashboardInstance && typeof window.EnhancedDashboardInstance.setClearanceLevel === 'function') {
                    window.EnhancedDashboardInstance.setClearanceLevel('elevated');
                } else {
                    // Fallback: use AJAX to set clearance level
                    setClearanceLevelViaAJAX('elevated');
                }
                
                // Show user-friendly message
                alert('🚫 Access is restricted. Your clearance level has been set to "elevated". You can still use the tracking script on the frontend, but access to other plugin pages is limited.');
                
                // Redirect to API input page since user is now elevated
                const currentUrl = window.location.href;
                const adminBase = currentUrl.substring(0, currentUrl.indexOf('/wp-admin/') + 10);
                const redirectUrl = adminBase + 'admin.php?page=sentinelpro-api-input';
                window.location.href = redirectUrl;
                return; // Prevent further execution
            }
            
            // Handle other 403 errors
            alert('❌ Access denied: ' + (data.message || 'Insufficient permissions'));
            return;
        } else {
            // localStorage.setItem('sentinelpro_clearance', 'restricted');
        }

        // ✅ Clear cache and update dashboard state
        localStorage.setItem('sentinelpro_last_property', propertyID);
        localStorage.setItem('sentinelpro_property_dirty', 'true');
        Object.keys(localStorage).forEach(key => {
        if (key.startsWith('sentinelpro_data_cache_')) {
            localStorage.removeItem(key);
        }
    });

    if (data.success) {
        // Fetch new clearance after save
        const newClearance = await fetchClearanceLevel();

        // Check if user is elevated or restricted and redirect to api-input page
        if (newClearance === 'elevated' || newClearance === 'restricted') {
            alert(data.message || '✅ Saved');
            // Use the redirect URL from server response if available, otherwise construct it
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                // Fallback: construct the admin URL
                const currentUrl = window.location.href;
                const adminBase = currentUrl.substring(0, currentUrl.indexOf('/wp-admin/') + 10);
                const fallbackUrl = adminBase + 'admin.php?page=sentinelpro-api-input';
                window.location.href = fallbackUrl;
            }
            return; // Prevent further execution
        }

        if (data.redirect && newClearance === 'admin') {
            window.location.href = data.redirect;
            return; // 🚨 Prevent any further execution (no alert, no reload)
        }

        alert(data.message || '✅ Saved');
        location.reload();

    } else {
        // Fetch new clearance after failed save
        const newClearance = await fetchClearanceLevel();

        // Check if user is elevated or restricted and redirect to api-input page
        if (newClearance === 'elevated' || newClearance === 'restricted') {
            alert(data.message || '✅ Saved');
            // Use the redirect URL from server response if available, otherwise construct it
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                // Fallback: construct the admin URL
                const currentUrl = window.location.href;
                const adminBase = currentUrl.substring(0, currentUrl.indexOf('/wp-admin/') + 10);
                const fallbackUrl = adminBase + 'admin.php?page=sentinelpro-api-input';
                window.location.href = fallbackUrl;
            }
            return; // Prevent further execution
        }

        if (data.redirect && newClearance === 'admin') {
            window.location.href = data.redirect;
            return; // 🚨 Prevent reload or alert after demotion
        }

        if (newClearance === 'elevated') {
            alert(data.message || '✅ Saved');
        } else {
            // Show the specific error message from the server
            const errorMessage = data.message || 'Save failed';
            alert('❌ ' + errorMessage);
        }
        location.reload();
    }




    } catch (err) {
        alert('Unexpected error during save: ' + err.message);
    }
  });
});