=== Valserv Analytics for SentinelPro ===

Plugin Name: Valserv Analytics for SentinelPro
Plugin URI: https://valserv.com/
Description: Connect your site to SentinelPro Analytics with a lightweight, privacy-aware tracking integration.
Tags: analytics, tracking, statistics, sentinelpro, privacy-first
Author: Valserv Inc
Author URI: https://valserv.com/
Requires at least: 5.0
Tested up to: 6.6.2
Stable tag: 2.0.0
Version: 2.0.0
Requires PHP: 7.4
Text Domain: valserv-analytics-for-sentinelpro
Domain Path: /languages
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Valserv Analytics for SentinelPro connects your WordPress site to SentinelPro Analytics, adds a disclosure-friendly settings page, and loads the official tracking script with inline configuration.

== Description ==

📊 **Connect to SentinelPro in minutes**
🔐 **Respect privacy and WordPress guidelines**
⚡ **Lightweight – no custom tables or cron jobs**

This plugin provides a minimal integration with SentinelPro Analytics. Enter your account credentials once and the plugin takes care of loading the official tracking script with sanitised configuration.

### Key Features

* **Simple Setup** – Enter your SentinelPro Account Name, Property ID, and API Key via the WordPress Settings API.
* **Secure Storage** – All inputs are sanitised and saved through WordPress core functions.
* **Masked API Keys** – Displayed as a password field with a reveal toggle for administrators.
* **Privacy Disclosure** – Adds explainer text to your privacy policy describing the data shared with SentinelPro.
* **Opt-in Tracking** – Tracking only runs when explicitly enabled in the settings.

== Installation ==

1. Upload the `valserv-analytics-for-sentinelpro` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin from the **Plugins** screen.
3. Navigate to **Dashboard → Valserv Analytics** and enter your SentinelPro credentials.
4. Check **Enable Front-end Tracking** and save your changes.

== Frequently Asked Questions ==

= Do I need a SentinelPro Analytics account? =
Yes. You will need an active SentinelPro Analytics account with access to your Account Name, Property ID, and API Key.

= Does the plugin store my API key in plain text? =
The key is stored using WordPress core options and never displayed publicly. Administrators can reveal the value on the settings page when needed.

= What data is shared with SentinelPro? =
When tracking is enabled, page view and usage information is sent to SentinelPro along with the configured property details. No WordPress user passwords or unrelated data are transmitted.

== External Services ==

This plugin connects to the following external services:

### SentinelPro Analytics Services
- **Services**: 
  - `api.sentinelpro.io` - API endpoints for analytics data
  - `cdn.sentinelpro.io` - Content delivery for tracking scripts
  - `analytics.sentinelpro.com` - Analytics dashboard and aggregated metrics
- **Purpose**: Loads the SentinelPro tracking script and fetches aggregated analytics metrics
- **Data Sent**: 
  - Page URL, referrer, and standard request metadata (IP address and user agent provided by the browser)
  - Event data configured by you (e.g., page views, scroll depth, property ID/account name)
  - No names, emails, passwords, or WordPress user IDs are collected or transmitted by this plugin
- **When**: On every page load where tracking is enabled
- **Data Use**: Data is used to generate aggregated, privacy-focused analytics dashboards. IP addresses and user agents are received as part of standard HTTP requests and are not used to identify individuals.
- **Terms of Service**: https://sentinelpro.ai/terms
- **Privacy Policy**: https://sentinelpro.ai/privacy

== Privacy ==

Valserv Analytics for SentinelPro adds privacy policy content to your site describing data collection and usage:

**Data Collection:**
- This plugin connects your site to SentinelPro Analytics to load a tracking script and fetch aggregated metrics.
- Data sent to SentinelPro includes: Page URL, referrer, and standard request metadata (IP address and user agent provided by the browser); Event data you configure (e.g., page views, scroll depth, property ID/account name).
- No names, emails, passwords, or WordPress user IDs are collected or transmitted by this plugin.

**Data Use:**
- Data is used to generate aggregated, privacy-focused analytics dashboards.
- IP addresses and user agents are received as part of standard HTTP requests and are not used to identify individuals.

**Controls:**
- Tracking can be disabled at any time in the plugin settings at **Dashboard → Valserv Analytics**.
- You can exclude logged-in users from tracking.

For more information, see SentinelPro's documentation and policies on your SentinelPro account site.
