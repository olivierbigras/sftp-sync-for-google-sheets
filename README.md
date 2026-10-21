# SFTP Sync for Google Sheets

**Automatically export Google Sheets to your SFTP server daily or hourly.**

A WordPress plugin that receives Google Sheets exports via API and uploads them to any SFTP server. Useful for automated inventory feeds, data syncs, and backups.

![Version](https://img.shields.io/badge/version-1.6.0-blue)
![License](https://img.shields.io/badge/license-GPL--2.0-green)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)

## Features

- ✅ **Easy Setup** – Configure everything from WordPress admin
- ✅ **Secure API** – Auto-generated API keys protect your endpoint
- ✅ **Flexible Scheduling** – Daily or hourly exports
- ✅ **Filename Options** – Dated filenames or overwrite mode
- ✅ **Multiple Formats** – Export as CSV or XLSX
- ✅ **Activity Logs** – Track all uploads and errors
- ✅ **Pre-built Script** – Copy-paste Google Apps Script included
- ✅ **No paid services required** – Uses Google Apps Script and your SFTP server

## How It Works

```
Google Sheet → Apps Script (scheduled) → HTTP POST → WordPress Plugin → SFTP Server
```

1. Google Apps Script exports your sheet on a schedule
2. Sends the file to your WordPress site via REST API
3. The plugin receives the file and uploads it to your SFTP server

---

## Installation

### Method 1: Upload ZIP
1. Download the latest release ZIP
2. Go to **Plugins → Add New → Upload Plugin**
3. Upload the ZIP and activate

### Method 2: Manual
1. Download/clone this repository
2. Copy the `sftp-sync-for-google-sheets` folder to `/wp-content/plugins/`
3. Activate via **Plugins** menu

---

## Step-by-Step Setup Guide

### Step 1: SFTP server

1. Go to **Settings → SFTP Sync**
2. Fill in your SFTP server details:

| Field | Description | Example |
|-------|-------------|---------|
| **Host** | Domain or IP, without `sftp://` | `sftp.example.com` or `123.45.67.89` |
| **Port** | Usually 22 or custom | `22` or `15492` |
| **Username** | SFTP username | `myuser` |
| **Password** | SFTP password (leave blank to keep the saved one) | `mypassword` |
| **Remote folder** | Destination folder (a trailing `/` is added if needed) | `/uploads/` or `/www/feeds/` |

3. Optional **Advanced**: file format (CSV / XLSX), filename mode (Dated / Overwrite), and base filename.
4. Click **Save and test connection**. The plugin checks that the remote folder is writable.

**Common errors:**
- "Could not connect" → Check host and port
- "Authentication failed" → Check username and password
- "No SFTP library available" → See [SFTP Library Requirements](#sftp-library-requirements)

Password authentication only — SSH private keys are not supported yet.

### Step 2: Google Sheets

After a successful test (or when SFTP is already configured), set the export schedule:

| Setting | Options | Description |
|---------|---------|-------------|
| **Frequency** | Every day / Every hour | How often the Apps Script should export |
| **Send at** | 00:00 - 23:00 | Time of day in the Google account timezone |

**Filename examples** (Advanced in step 1):
- Dated: `inventory_2026-01-07_140000.csv`
- Overwrite: `inventory.csv`

Then follow the on-screen steps:

1. In the sheet: **Extensions → Apps Script**
2. Click **Copy script** and paste the code (it is already visible; endpoint URL and API key are embedded)
3. Run **`setupTrigger()`** once and authorize access

Endpoint URL, API key, and **Regenerate key** are under **Advanced** in this section.

### Step 3: Activity

The activity table lists recent sends (date, status, message). Use **Clear log** to empty it.

---

## Important: After Changing Plugin Settings

**⚠️ You must re-copy the Apps Script after changing export settings!**

The settings (schedule, filename mode, format, etc.) are embedded in the generated Apps Script. If you change settings in WordPress:

1. Save the new settings
2. Copy the script again from step 2
3. Paste into Google Apps Script (replacing the old code)
5. Save
6. Run `setupTrigger` again if you changed the schedule

---

## Troubleshooting

### Error: "SFTP configuration incomplete"

**Cause:** SFTP settings not saved in WordPress.

**Fix:** 
1. Go to Settings → SFTP Sync
2. Enter all SFTP credentials
3. Click **Save and test connection**

### Error: "rest_forbidden" or "401 Unauthorized"

**Cause:** API key mismatch between Apps Script and WordPress.

**Fix:**
1. Go to Settings → SFTP Sync
2. Copy the API Key from **2. Google Sheets → Advanced**
3. In Google Apps Script, update `CONFIG.API_KEY` with the new key
4. Or re-copy the entire Apps Script code (recommended)

### Error: "SFTP authentication failed"

**Cause:** Wrong SFTP credentials.

**Fix:**
1. Double-check username and password
2. Verify the host and port are correct
3. Test with an SFTP client (FileZilla, WinSCP) first
4. Check if your password has special characters that might need escaping

**Note:** Passwords with special characters like `+`, `@`, `!` should work, but verify they're entered correctly.

### Error: "No SFTP library available"

**Cause:** Neither ssh2 PHP extension nor phpseclib is installed.

**Fix (Option 1 - phpseclib):**
```bash
cd /path/to/wp-content/plugins/sftp-sync-for-google-sheets
composer require phpseclib/phpseclib:~3.0
```

**Fix (Option 2):** Contact your host to enable the ssh2 PHP extension.

### Error: "Could not connect to SFTP server"

**Cause:** Network/firewall issue or wrong host/port.

**Fix:**
1. Verify the host IP/hostname is correct
2. Verify the port number
3. Check if your server's firewall allows outbound connections on that port
4. Try connecting from your server via command line: `ssh -p PORT user@host`

### Error: "Failed to write file to SFTP"

**Cause:** Remote path doesn't exist or no write permission.

**Fix:**
1. Verify the remote path exists on the SFTP server
2. Check write permissions on that directory
3. Make sure the path ends with `/`

### Trigger Not Running

**Symptoms:** Trigger is set but exports don't happen.

**Fix:**
1. Check Apps Script executions: Extensions → Apps Script → Executions
2. Look for failed executions and error messages
3. Re-run `setupTrigger` to recreate the trigger
4. Make sure you authorized all permissions

### Need to Regenerate API Key

If you suspect your API key is compromised:

1. Go to Settings → SFTP Sync
2. Open **2. Google Sheets → Advanced** and click **Regenerate key**
3. **Important:** Re-copy the Apps Script code to your Google Sheet
4. The old API key will stop working immediately

---

## SFTP Library Requirements

The plugin tries these methods in order:

1. **ssh2 PHP extension** (fastest, if available)
2. **phpseclib3** (recommended fallback)
3. **phpseclib 2.x** (legacy support)

Most managed WordPress hosts (including Kinsta) have phpseclib available or allow installing it via Composer.

---

## Google Apps Script Quotas (Free Tier)

| Resource | Daily Limit |
|----------|-------------|
| URL Fetch calls | 20,000 |
| Script runtime | 6 min per execution |
| Triggers | 20 per user |

These limits are very generous for typical use cases.

---

## Security Best Practices

1. **Keep your API key secret** – Don't share it publicly
2. **Use HTTPS** – Your WordPress site should use SSL
3. **Regenerate keys periodically** – If you suspect a leak
4. **Monitor logs** – Check the plugin logs for unauthorized attempts

---

## Multiple Sheets / Sites

### Multiple Sheets → One WordPress Site
Each Google Sheet can have its own Apps Script pointing to the same WordPress endpoint. Just use different base filenames.

### One Sheet → Multiple Sites
You can create multiple Apps Scripts in the same sheet, each pointing to a different WordPress endpoint.

---

## Changelog

### 1.6.0
- WordPress.org readiness: unique `olissgs_` prefix; lossless migration of the 10 settings from `gsheet_sftp_*`
- Existing REST endpoint `gsheet-sftp/v1` kept; admin CSS/JS extracted to files
- Settings screen in 3 numbered steps (SFTP, Google Sheets, Activity), Save and test connection, single Donate link in plugin_row_meta
- External services documented in readme.txt

### 1.5.2
- Fixed Save Settings wiping the API key, which caused Google Apps Script exports to return 401 rest_forbidden
- Google Apps Script now also sends the API key in the request body as a fallback

### 1.5.1
- Fixed SFTP password becoming unreadable after WordPress salt/key rotation
- Password encryption now uses a stable plugin key instead of WordPress salts
- Clearer admin error when the stored password must be re-entered

### 1.5.0
- WordPress.org initial release
- Improved code quality based on plugin review feedback
- Moved log storage to wp-content/uploads for better security
- Updated WordPress compatibility to 6.9

### 1.4.0
- Renamed plugin to "SFTP Sync for Google Sheets" for WordPress.org compliance
- Fixed all escaping issues (using esc_html_e, esc_html__ throughout)
- Fixed input sanitization for $_SERVER variables
- Updated text domain to sftp-sync-for-google-sheets
- Removed promotional language from descriptions

### 1.3.1
- Removed hidden files (`.htaccess`, `.gitignore`) from distribution for WordPress.org plugin directory compliance
- Logs directory now protected by `index.php` only

### 1.3.0
- **Fixed:** CSV export now uses Google's native CSV export URL instead of manual CSV building
- **Fixed:** WP All Import not recognizing CSV column headers
- CSV output is now identical to Google Sheets "File > Download > CSV"
- Updated generated Google Apps Script code
- Updated WordPress compatibility to 6.9

### 1.2.0
- **Security:** Improved password encryption using AES-256-CBC with WordPress salts
- **Security:** Added rate limiting (60 requests/minute) to prevent API abuse
- Added `composer.json` for easier phpseclib dependency management
- Added `index.php` security files to prevent directory listing
- Updated WordPress compatibility to 6.7

### 1.1.0
- Added Export Settings section in admin
- New schedule options: Daily or Hourly
- New filename mode: Dated (unique files) or Overwrite (same file each time)
- Configurable base filename and export format (CSV/XLSX)
- Apps Script now auto-configured with plugin settings
- Improved SFTP error messages for debugging

### 1.0.0
- Initial release
- SFTP upload via ssh2 or phpseclib
- REST API endpoint for receiving files
- Admin settings page
- Activity logging
- Google Apps Script generator

---

## Author

**Olivier Bigras**  
Website: [https://olivierbigras.com](https://olivierbigras.com)

---

## License

GPL v2 or later - [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
