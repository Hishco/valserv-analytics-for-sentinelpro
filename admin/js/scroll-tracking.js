/**
 * Scroll depth tracking for anonymous users
 * Part of Valserv Analytics for SentinelPro plugin
 */
document.addEventListener("DOMContentLoaded", function () {
    let maxDepth = 0;
    
    window.addEventListener("scroll", function () {
        const scrollTop = window.scrollY + window.innerHeight;
        const fullHeight = document.body.scrollHeight;
        const percent = Math.min(100, Math.ceil((scrollTop / fullHeight) * 100));
        if (percent > maxDepth) maxDepth = percent;
    });

    window.addEventListener("beforeunload", function () {
        const uuid = window.websiteSentinel?.uuid || "unknown";
        const configPair = window.sentinelData?.find(([k]) => k === "config");
        const propertyId = configPair ? configPair[1].propertyId : "unknown";

        const payload = {
            type: "engagedDepth",
            value: maxDepth,
            uuid: uuid,
            timestamp: Date.now(),
            propertyId: propertyId
        };

        navigator.sendBeacon(
            valservScrollData.ajaxUrl + '?action=' + valservScrollData.action,
            new Blob([JSON.stringify(payload)], { type: 'application/json' })
        );
    });
});
