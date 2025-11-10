// Database Test JavaScript
jQuery(document).ready(function($) {
    $("#test-shared-db").click(function() {
        var button = $(this);
        var resultDiv = $("#test-result");
        
        button.prop("disabled", true).text("Testing...");
        resultDiv.hide();
        
        $.post(ajaxurl, {
            action: "sentinelpro_test_shared_db",
            nonce: window.valservAdminData?.testDbNonce || ''
        }, function(response) {
            button.prop("disabled", false).text("Test Shared Database Connection");
            resultDiv.html(response.message).show();
            resultDiv.removeClass("notice-success notice-error").addClass("notice-" + (response.success ? "success" : "error"));
        });
    });
});
