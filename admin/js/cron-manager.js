// Cron Manager JavaScript - Status monitoring only
jQuery(document).ready(function($) {
    // Check if required data is available
    if (!window.valservCronData?.nonce || !window.valservCronData?.ajaxUrl) {
        console.warn('SentinelPro: Cron manager data not available');
        return;
    }
    
    // Auto-refresh status every 30 seconds
    setInterval(function() {
        $.ajax({
            url: window.valservCronData?.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sentinelpro_get_cron_status',
                nonce: window.valservCronData?.nonce || ''
            },
            success: function(response) {
                if (response.success) {
                    $('#cron-status').html(response.data.status);
                    $('#last-run').text(response.data.last_run);
                    $('#next-run').text(response.data.next_run);
                }
            }
        });
    }, 30000);
});
