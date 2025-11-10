=== Valserv Analytics for SentinelPro ===

Plugin Name: Valserv Analytics for SentinelPro
Plugin URI: https://valserv.com/
Description: Connect your site to SentinelPro Analytics with a lightweight, privacy-aware tracking integration.
Tags: analytics, tracking, statistics, sentinelpro, privacy-first
Author: Valserv Inc
Author URI: https://valserv.com/
Requires at least: 5.9
Tested up to: 6.8
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

This plugin connects to the following external service:

### SentinelPro Tracking Service
- **Service**: SentinelPro Tracking Service
- **Hostname**: `collector.sentinelpro.com` (strictly allowlisted)
- **Purpose**: Loads the official SentinelPro tracking script
- **Data Sent**: Account name, property ID, page view data, and anonymised usage information. No personally identifiable information (PII) such as user passwords, email addresses, or personal data is transmitted by default.
- **Optional Data**: If you enable "Share Usage Metrics with SentinelPro", additional anonymised plugin usage statistics may be sent to help improve the service.
- **When**: On every front-end page load where tracking is enabled
- **Data Retention**: Handled by SentinelPro according to their privacy policy
- **Terms of Service**: https://sentinelpro.ai/terms
- **Privacy Policy**: https://sentinelpro.ai/privacy

An admin notice is displayed on the plugin settings page when tracking is enabled to inform administrators of this third-party connection.

== Privacy ==

Valserv Analytics for SentinelPro automatically adds detailed privacy policy content to your WordPress privacy policy page.

**Summary:**
- The plugin connects to `collector.sentinelpro.com` when front-end tracking is enabled
- Data sent includes: page view data, anonymised usage information, configured property ID, and account name
- No PII (passwords, emails, personal data) is transmitted by default
- If "Share Usage Metrics" is enabled, additional anonymised statistics may be sent
- Data retention is managed by SentinelPro per their privacy policy
- Administrators can disable tracking at any time from **Dashboard → Valserv Analytics**

The plugin does not store any tracking data locally on your WordPress site.
