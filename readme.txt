=== Valserv Analytics for SentinelPro ===

Plugin Name: Valserv Analytics for SentinelPro
Plugin URI: https://valserv.com/
Description: Connect your site to SentinelPro Analytics. Includes real-time tracking, post-level metrics, and a privacy-focused dashboard.
Tags: analytics, tracking, statistics, sentinelpro, privacy-first
Author: Valserv Inc
Author URI: https://valserv.com/
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.0.0
Version: 1.0.0
Requires PHP: 7.4
Text Domain: valserv-analytics-for-sentinelpro
Domain Path: /languages
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your site to SentinelPro Analytics with real-time tracking, post-level metrics, and a privacy-focused dashboard.


== Description ==

📊 **Connect your site to SentinelPro Analytics**  
🚀 **Real-time tracking and post-level insights**  
🔒 **Privacy-focused and lightweight**

Valserv Analytics for SentinelPro brings high-performance analytics to your site with a simple setup and zero bloat.

### 🎯 Key Features

* **Instant Integration** – Add your SentinelPro tracking code using a Property ID and API Key.
* **Post-Level Metrics** – View views, sessions, and event data directly in the admin.
* **Custom Tracking Options** – Disable tracking for logged-in users or customize with filters.
* **Performance Focused** – Clean code, fast loading, and compatible with all major caching plugins.
* **Privacy-First** – No personal data is stored or transmitted by the plugin itself.

---

### 🧩 Feature Support

* Real-time analytics dashboard (built-in)
* Per-post views, sessions, and event data
* Works with all major themes and editors
* Developer-friendly filters for extending tracking logic

---

### ⚙️ General Plugin Features

* Lightweight and fast
* Simple, intuitive settings page
* Works with or without Gutenberg
* Compatible with multisite
* Regular updates and active development

---

== Installation ==

1. Upload the `valserv-analytics-for-sentinelpro` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin from the **Plugins** menu.
3. Go to **Dashboard > SentinelPro** and enter your Property ID and API Key.

That's it! Tracking starts immediately.

---

== Usage ==

1. Log in to your SentinelPro Analytics account.
2. Copy your **Account Name**, **Property ID**, and **API Key**.
3. Paste them into the plugin settings.
4. (Optional) Adjust tracking behavior, such as enabling logged-in user tracking.
5. View analytics directly from your admin dashboard or on individual post listings.

---

== Frequently Asked Questions ==

= Do I need a SentinelPro Analytics account? =
Yes. You'll need a SentinelPro account with a valid Property ID and API Key.

= Where can I find my Property ID and API Key? =
Log in to your SentinelPro Analytics dashboard and go to **Property Management** and **Account API** to retrieve them.

= Does it track logged-in users? =
By default, logged-in users are excluded from tracking. You can enable this in the plugin settings.

= Is user data collected? =
No. The plugin itself collects no user data. Analytics is handled securely by SentinelPro servers.

= Can I customize the tracking code? =
Yes. Developers can use filters to extend or alter the output of the tracking script.

= Is it compatible with caching plugins? =
Yes. SentinelPro Analytics is fully compatible with all major caching and optimization plugins.

---

== Screenshots ==

1. SentinelPro Analytics plugin settings page
2. Post-level metrics displayed in the Posts table

---

== External Services ==

This plugin connects to the following external services:

### SentinelPro Analytics API
- **Service**: SentinelPro Analytics API (analytics.sentinelpro.com)
- **Purpose**: Retrieves analytics data, tracking scripts, and configuration information
- **Data Sent**: Account name, property ID, API key, date ranges, and filter parameters
- **When**: When loading the dashboard, fetching analytics data, or retrieving tracking scripts
- **Terms of Service**: [https://sentinelpro.ai/terms](https://sentinelpro.ai/terms)
- **Privacy Policy**: [https://sentinelpro.ai/privacy](https://sentinelpro.ai/privacy)
- **Note**: Terms of Service and Privacy Policy are hosted at sentinelpro.ai while services run on sentinelpro.com domains.

### SentinelPro Tracking Service
- **Service**: SentinelPro Tracking Service (analytics.sentinelpro.com)
- **Purpose**: Loads the tracking script for analytics data collection
- **Data Sent**: Property ID for script identification
- **When**: On every page load when tracking is enabled
- **Terms of Service**: [https://sentinelpro.ai/terms](https://sentinelpro.ai/terms)
- **Privacy Policy**: [https://sentinelpro.ai/privacy](https://sentinelpro.ai/privacy)
- **Note**: Terms of Service and Privacy Policy are hosted at sentinelpro.ai while services run on sentinelpro.com domains.

### SentinelPro Data API
- **Service**: SentinelPro Data API (account-name.sentinelpro.com)
- **Purpose**: Fetches traffic data, analytics metrics, and user behavior data
- **Data Sent**: Account name, API key, query parameters, date ranges, and filter criteria
- **When**: When fetching analytics data, generating reports, or syncing data via cron jobs
- **Terms of Service**: [https://sentinelpro.ai/terms](https://sentinelpro.ai/terms)
- **Privacy Policy**: [https://sentinelpro.ai/privacy](https://sentinelpro.ai/privacy)
- **Note**: Terms of Service and Privacy Policy are hosted at sentinelpro.ai while services run on sentinelpro.com domains.

---

== Privacy ==

This plugin does **not** collect, store, or transmit any personal user data directly. However, the plugin connects to external SentinelPro services that may collect analytics data:

### Data Collection
- **Tracking Script**: Loads from `analytics.sentinelpro.com` and may collect page views, user interactions, and basic device information
- **Analytics Data**: Retrieved from `analytics.sentinelpro.com` and `account-name.sentinelpro.com` APIs
- **No Local Storage**: The plugin itself sets no cookies or stores any user data locally

### External Service Data Handling
- All analytics data collection is handled by SentinelPro's external services
- Data transmission occurs only when tracking is enabled and users visit your site
- API calls are made only when accessing the admin dashboard or when cron jobs run

### Compliance
To comply with GDPR, CCPA, or other data regulations:
- Refer to your SentinelPro account settings for privacy controls
- Configure IP anonymization or data retention policies through SentinelPro
- Ensure you have proper consent mechanisms in place for your users
- Review SentinelPro's privacy policy and terms of service

---

== Changelog ==

= 1.0.0 =
* Initial release
* Tracking code injection via Property ID
* Settings page for API Key and tracking options
* Post-level analytics integration
* Real-time dashboard support

---

== Upgrade Notice ==

= 1.0.0 =
Initial release. No upgrade steps required.

---

== Licenses ==

This plugin is licensed under GPL-2.0-or-later.

### Third-Party Libraries

This plugin includes the following third-party libraries:

* **Chart.js** (v4.5.1) - MIT License
  * Location: `assets/external-libs/chart.umd.min.js`
  * Source: https://www.chartjs.org/

* **Chart.js date-fns adapter** - MIT License
  * Location: `assets/external-libs/chartjs-adapter-date-fns.js`
  * Source: https://github.com/chartjs/chartjs-adapter-date-fns

* **SheetJS (xlsx)** - Apache-2.0 License
  * Location: `assets/external-libs/xlsx.full.min.js`
  * Source: https://sheetjs.com/

* **html2canvas** (v1.4.1) - MIT License
  * Location: `assets/vendor/html2canvas.min.js`
  * Source: https://html2canvas.hertzen.com/

* **jsPDF** (v2.5.1) - MIT License
  * Location: `assets/vendor/jspdf.umd.min.js`
  * Source: https://github.com/parallax/jsPDF

* **jQuery UI CSS** (v1.13.2) - MIT License
  * Location: `assets/external-libs/jquery-ui.css`
  * Source: https://jqueryui.com/

All third-party libraries are included locally within the plugin and comply with GPL-2.0-or-later compatibility requirements.

---

== Support and Development ==

This plugin is actively developed and maintained by [Valserv Inc](https://valserv.com/).  
For documentation, feature requests, or support, visit: [https://valserv.com/contact](https://valserv.com/contact)

Enjoy using SentinelPro Analytics?  
Please consider [leaving a 5-star review](https://wordpress.org/support/plugin/valserv-analytics-for-sentinelpro/reviews/)

---

