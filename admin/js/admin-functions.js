/**
 * Admin diagnostic functions for SentinelPro Analytics
 * These functions are available in the browser console for debugging
 */

// SentinelPro Diagnostic Functions - Call these from browser console

// Check current status
window.sentinelproCheckStatus = function() {        
    // Make AJAX call to get status
    jQuery.ajax({
        url: window.valservAdminData?.ajaxUrl || ajaxurl,
        type: 'POST',
        data: {
            action: 'sentinelpro_check_status',
            nonce: window.valservAdminData?.statusNonce || ''
        },
        success: function(response) {
            console.log(response);
        },
        error: function() {
            console.error('Status check failed');
        }
    });
};

// Reset to restricted clearance
window.sentinelproResetToRestricted = function() {
    jQuery.ajax({
        url: window.valservAdminData?.ajaxUrl || ajaxurl,
        type: 'POST',
        data: {
            action: 'sentinelpro_reset_clearance',
            clearance: 'restricted',
            nonce: window.valservAdminData?.clearanceNonce || ''
        },
        success: function(response) {
            if (response.success) {
                alert('Clearance reset to restricted. Please refresh the page.');
            } else {
                console.error(response);
            }
        },
        error: function() {
            console.error('Reset failed');
        }
    });
};

// Set to admin clearance
window.sentinelproSetToAdmin = function() {
    jQuery.ajax({
        url: window.valservAdminData?.ajaxUrl || ajaxurl,
        type: 'POST',
        data: {
            action: 'sentinelpro_reset_clearance',
            clearance: 'admin',
            nonce: window.valservAdminData?.clearanceNonce || ''
        },
        success: function(response) {
            if (response.success) {
                alert('Clearance set to admin. Please refresh the page.');
            } else {
                console.error(response);
            }
        },
        error: function() {
            console.error('Set to admin failed');
        }
    });
};

// Test tracking script injection
window.sentinelproTestTracking = function() {
    jQuery.ajax({
        url: window.valservAdminData?.ajaxUrl || ajaxurl,
        type: 'POST',
        data: {
            action: 'sentinelpro_test_injection',
            nonce: window.valservAdminData?.testInjectionNonce || ''
        },
        success: function(response) {
            if (response.success) {
                console.log(response.data);
            } else {
                console.error(response);
            }
        },
        error: function() {
            console.error('Test injection failed');
        }
    });
};

// Check and upgrade clearance if API credentials are configured
window.sentinelproCheckAndUpgrade = function() {
    jQuery.ajax({
        url: window.valservAdminData?.ajaxUrl || ajaxurl,
        type: 'POST',
        data: {
            action: 'sentinelpro_check_credentials_and_upgrade',
            nonce: window.valservAdminData?.credentialsNonce || ''
        },
        success: function(response) {
            if (response.success) {
                if (response.data.upgraded) {
                    alert('Clearance upgraded to admin! Please refresh the page to see all menus and enable tracking.');
                } else {
                    alert('API credentials not fully configured. Please ensure Account Name, Property ID, and API Key are all set.');
                }
            } else {
                console.error(response);
            }
        },
        error: function() {
            console.error('Check and upgrade failed');
        }
    });
};

// Upgrade clearance function for button onclick
window.upgradeClearance = function() {
    if (typeof sentinelproSetToAdmin === 'function') {
        sentinelproSetToAdmin();
    } else {
        alert('Please refresh the page to load the upgrade function');
    }
};
