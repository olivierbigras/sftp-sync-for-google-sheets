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

foreach (OLISSGS_Options::uninstall_option_names() as $option_name) {
    delete_option($option_name);
}

delete_metadata('user', 0, 'olissgs_donate_dismissed', '', true);
delete_metadata('user', 0, 'sftp_sync_gs_donate_dismissed', '', true);

$upload_dir = wp_upload_dir();
$log_dir    = trailingslashit($upload_dir['basedir']) . 'sftp-sync-for-google-sheets/';
if (is_dir($log_dir)) {
    global $wp_filesystem;
    if (!function_exists('WP_Filesystem')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    WP_Filesystem();

    if ($wp_filesystem) {
        $wp_filesystem->delete($log_dir, true);
    }
}
