=== SFTP Sync for Google Sheets ===
Contributors: bigrat95
Donate link: https://www.paypal.com/donate/?hosted_button_id=29SAPKFAHMTNW
Plugin URI: https://olivierbigras.com
Tags: google sheets, sftp, sync, automation, csv
Requires at least: 5.0
Tested up to: 7.1
Stable tag: 1.6.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically receive Google Sheets exports and upload them to your SFTP server. Supports daily automated syncs.

== Description ==

SFTP Sync for Google Sheets allows you to automatically export Google Sheets and upload them to any SFTP server. Use cases include:

* Daily inventory updates from Google Sheets
* Automated data feeds for e-commerce
* Syncing spreadsheet data to your server
* Backing up Google Sheets to your own server

**Features:**

* **Easy Setup** – Configure everything from the WordPress admin
* **Secure API** – Auto-generated API keys protect your endpoint
* **REST API Endpoint** – Receives files from Google Apps Script
* **SFTP Upload** – Automatically uploads to your SFTP server
* **Activity Logs** – Track all uploads and errors
* **Pre-built Script** – Copy-paste Google Apps Script included
* **No paid services required** – Uses Google Apps Script and your SFTP server

**How It Works:**

1. Install and activate the plugin
2. Configure your SFTP credentials in Settings
3. Copy the provided Google Apps Script to your Sheet
4. Set up a daily trigger in Google Apps Script
5. Your sheet automatically syncs to your SFTP server

== Installation ==

1. Upload the `sftp-sync-for-google-sheets` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → SFTP Sync
4. Enter your SFTP server credentials
5. Click "Test SFTP Connection" to verify
6. Copy the Google Apps Script from the settings page
7. Paste into your Google Sheet (Extensions → Apps Script)
8. Run `setupTrigger()` to enable daily exports

== Frequently Asked Questions ==

= What SFTP libraries are supported? =

The plugin supports both the PHP ssh2 extension and phpseclib. If neither is available, you can install phpseclib via Composer.

= Are there any costs? =

This solution uses Google Apps Script and your existing SFTP server. No third-party paid services are required.

= Can I export as XLSX instead of CSV? =

Yes! Change `EXPORT_FORMAT: 'csv'` to `EXPORT_FORMAT: 'xlsx'` in the Google Apps Script configuration.

= How do I change the export schedule? =

In the Google Apps Script, change `SCHEDULE: 'daily'` to `SCHEDULE: 'hourly'` for hourly exports, or modify `DAILY_HOUR` to change the time.

= Is the API secure? =

Yes. All requests require a valid API key. The key is auto-generated on activation and can be regenerated anytime.

= Can I use this with multiple Google Sheets? =

Yes! Each Google Sheet can have its own Apps Script. They can all point to the same WordPress endpoint.

= Will my settings be kept when I update to 1.6.0? =

Yes. The 10 settings (host, port, username, password, remote path, schedule, daily hour, filename mode, base filename, export format), plus the API key and encryption key, are copied to the new `olissgs_*` option names and the old keys are then removed.

== External services ==

This plugin talks to the following external systems. None of these calls are hidden: SFTP is the feature you configure, Google runs only in the Apps Script you paste yourself, and PayPal opens only if you click Donate.

**User-configured SFTP server**

The plugin connects to the SFTP host, port, username, and remote path you save in Settings → SFTP Sync. A connection is opened when (1) a file is received on the REST upload endpoint, or (2) an administrator clicks “Save and test connection”. The plugin sends the stored SFTP credentials and the uploaded file contents to that host. No data is sent to the plugin author. Terms and privacy depend on the SFTP provider you choose.

**Google Sheets / Google Apps Script (optional, user-installed)**

The WordPress plugin does not call Google from your server. It generates a Google Apps Script that you may paste into a Google Sheet (Extensions → Apps Script). When you run that script (manually or via a trigger you create), it:

1. Calls Google’s spreadsheet export API (`https://docs.google.com/spreadsheets/d/{id}/export`) using your Google account’s OAuth token, to download CSV or XLSX content.
2. Sends that file (base64), a filename, a timestamp, and your plugin API key to your WordPress site over HTTPS (`X-API-Key` header and JSON body).

Google receives your spreadsheet ID and account token for the export. Your WordPress site receives the exported file and API key. If you never install the generated script, no Google call is made.

* Terms of Service: https://policies.google.com/terms
* Privacy Policy: https://policies.google.com/privacy
* Google APIs Terms: https://developers.google.com/terms

**PayPal (optional donate links)**

A single Donate link in the plugin meta row on the Plugins screen opens PayPal only when a user chooses to click it. The plugin does not send visitor or site data to PayPal automatically.

* User Agreement: https://www.paypal.com/legalhub/useragreement-full
* Privacy Statement: https://www.paypal.com/legalhub/privacy-full

== Screenshots ==

1. Settings step 1: SFTP server fields, Save and test connection, Advanced filename options
2. Settings step 2: Google Sheets schedule and the generated Apps Script
3. Settings step 3: activity log table

== Changelog ==

= 1.6.1 =
* Tested up to WordPress 7.1
* Use wp_delete_file() instead of unlink() when cleaning the SFTP write probe
* Exclude development files from distribution ZIPs (.distignore and export-ignore)

= 1.6.0 =
* WordPress.org readiness: unique `olissgs_` prefix for functions, classes, options, transients, hooks, and script/style handles
* Lossless upgrade: copies the 10 settings (and API / encryption keys) from `gsheet_sftp_*` to `olissgs_*`, then deletes the old options
* Existing REST namespace `gsheet-sftp/v1` kept so deployed Google Apps Scripts keep working; `olissgs/v1` registered as an alias
* Admin CSS/JS moved to enqueued files (no inline script, style, or onclick handlers)
* Settings screen is now 3 numbered steps (SFTP, Google Sheets, Activity), with “Save and test connection” and a single Donate link in plugin_row_meta
* Documented external services (SFTP, Google Apps Script, PayPal) in the readme
* Slug and text domain unchanged: `sftp-sync-for-google-sheets`

= 1.5.4 =
* Maintenance: GitHub links updated to github.com/olivierbigras (account renamed from bigrat95). No functional change.

= 1.5.3 =
* NEW: Settings and Donate links on the Plugins screen, optional support notice on the settings screen, footer donation link, and Donate link updated to PayPal on the WordPress.org plugin page

= 1.5.2 =
* Fixed Save Settings wiping the API key, which caused Google Apps Script exports to return 401 rest_forbidden
* Google Apps Script now also sends the API key in the request body as a fallback

= 1.5.1 =
* Fixed SFTP password becoming unreadable after WordPress salt/key rotation
* Password encryption now uses a stable plugin key instead of WordPress salts
* Clearer admin error when the stored password must be re-entered

= 1.5.0 =
* WordPress.org initial release
* Improved code quality based on plugin review feedback
* Moved log storage to wp-content/uploads for better security
* Updated WordPress compatibility to 6.9

= 1.4.0 =
* Renamed plugin to "SFTP Sync for Google Sheets" for WordPress.org compliance
* Fixed all escaping issues (using esc_html_e, esc_html__ throughout)
* Fixed input sanitization for $_SERVER variables
* Updated text domain to sftp-sync-for-google-sheets
* Removed promotional language from descriptions

= 1.3.1 =
* Removed hidden files (.htaccess) for WordPress.org plugin directory compliance
* Logs directory now protected by index.php only (no .htaccess needed)

= 1.3.0 =
* Fixed CSV export: Now uses Google's native CSV export instead of manual CSV building
* This fixes WP All Import not recognizing CSV headers
* CSV output is now identical to Google Sheets "File > Download > CSV"
* Updated generated Google Apps Script code
* Updated WordPress compatibility to 6.9

= 1.2.0 =
* Security: Improved password encryption using AES-256-CBC with WordPress salts
* Security: Added rate limiting (60 requests/minute) to prevent API abuse
* Added composer.json for easier phpseclib dependency management
* Added index.php security files to prevent directory listing
* Updated WordPress compatibility to 6.7

= 1.1.0 =
* Added Export Settings section in admin
* New schedule options: Daily or Hourly
* New filename mode: Dated (unique files) or Overwrite (same file each time)
* Configurable base filename and export format (CSV/XLSX)
* Apps Script now auto-configured with plugin settings
* Improved SFTP error messages for debugging

= 1.0.0 =
* Initial release
* SFTP upload via ssh2 or phpseclib
* REST API endpoint for receiving files
* Admin settings page
* Activity logging
* Google Apps Script generator

== Upgrade Notice ==

= 1.6.1 =
WordPress 7.1 compatibility and Plugin Check fixes. No settings change required.

= 1.6.0 =
WordPress.org compliance update. Your SFTP settings and API key are migrated automatically. Existing Google Apps Scripts keep working.

= 1.5.4 =
Maintenance release: GitHub links updated. No functional change.

= 1.5.3 =
Adds Donate / support links (Plugins screen, settings notice, PayPal Donate link on WordPress.org).

= 1.5.2 =
Fixes Save Settings wiping the API key (401 errors from Google Apps Script). After updating, copy the current API key into your Apps Script.

= 1.5.1 =
Fixes SFTP password decryption after WordPress security keys are rotated. Re-enter your SFTP password once after updating.

= 1.5.0 =
WordPress.org initial release with improved security and code quality.

= 1.4.0 =
Renamed plugin for WordPress.org compliance. Fixed escaping and sanitization issues.

= 1.3.1 =
WordPress.org plugin directory compliance fixes.

= 1.3.0 =
Fixes CSV header detection in WP All Import. Re-copy the Google Apps Script after updating.

= 1.2.0 =
Security improvements: Better password encryption and API rate limiting.

= 1.1.0 =
New export settings! Configure schedule, filename mode, and format directly in the plugin.

= 1.0.0 =
Initial release.
