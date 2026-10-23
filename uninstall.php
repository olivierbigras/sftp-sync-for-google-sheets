<?php
/**
 * Uninstall SFTP Sync for Google Sheets.
 *
 * @package OLISSGS
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/includes/class-olissgs-options.php';

/**
 * Remove plugin options, user meta, and log files.
 */
function olissgs_uninstall() {
    foreach (OLISSGS_Options::uninstall_option_names() as $olissgs_option_name) {
        delete_option($olissgs_option_name);
    }

    delete_metadata('user', 0, 'olissgs_donate_dismissed', '', true);
    delete_metadata('user', 0, 'sftp_sync_gs_donate_dismissed', '', true);

    $olissgs_upload_dir = wp_upload_dir();
    $olissgs_log_dir    = trailingslashit($olissgs_upload_dir['basedir']) . 'sftp-sync-for-google-sheets/';
    if (is_dir($olissgs_log_dir)) {
        global $wp_filesystem;
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();

        if ($wp_filesystem) {
            $wp_filesystem->delete($olissgs_log_dir, true);
        }
    }
}

olissgs_uninstall();
