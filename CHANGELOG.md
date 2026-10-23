# Changelog

## 1.6.2

- Translation template (`languages/sftp-sync-for-google-sheets.pot`), `Domain Path: /languages`, and `load_plugin_textdomain` on `init` only for WordPress older than 6.7. Distribution ZIP includes Composer `vendor/` (phpseclib, from versioned `composer.lock`) and `languages/`. `vendor/autoload.php` is loaded at plugin bootstrap when present.

## 1.6.1

- Tested up to WordPress 7.1
- Use `wp_delete_file()` instead of `unlink()` when cleaning the SFTP write probe
- Exclude development files from distribution ZIPs (`.distignore` and `export-ignore`)

## 1.6.0

- WordPress.org readiness: unique `olissgs_` prefix for functions, classes, options, transients, hooks, and script/style handles
- Lossless upgrade: copies the 10 settings (and API / encryption keys) from `gsheet_sftp_*` to `olissgs_*`, then deletes the old options
- Existing REST namespace `gsheet-sftp/v1` kept so deployed Google Apps Scripts keep working; `olissgs/v1` registered as an alias
- Admin CSS/JS moved to enqueued files (no inline script, style, or onclick handlers)
- Settings screen is now 3 numbered steps (SFTP, Google Sheets, Activity), with “Save and test connection” and a single Donate link in plugin_row_meta
- Documented external services (SFTP, Google Apps Script, PayPal) in the readme
- Slug and text domain unchanged: `sftp-sync-for-google-sheets`

## 1.5.4

- Maintenance: GitHub links updated to github.com/olivierbigras (account renamed from bigrat95). No functional change.

## 1.5.3

- Settings and Donate links on the Plugins screen, optional support notice on the settings screen, footer donation link, and Donate link updated to PayPal

## 1.5.2

- Fixed Save Settings wiping the API key (401 rest_forbidden from Google Apps Script)
- Google Apps Script now also sends the API key in the request body as a fallback

## 1.5.1

- Fixed SFTP password becoming unreadable after WordPress salt/key rotation
- Password encryption now uses a stable plugin key instead of WordPress salts

## 1.5.0

- WordPress.org initial release
- Moved log storage to wp-content/uploads
