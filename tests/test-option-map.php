<?php
/**
 * Standalone checks for the olissgs_ option map and lossless migration.
 *
 * Run: php tests/test-option-map.php
 */

define('ABSPATH', true);

$GLOBALS['olissgs_options'] = [];

function get_option($key, $default = false) {
    if (array_key_exists($key, $GLOBALS['olissgs_options'])) {
        return $GLOBALS['olissgs_options'][$key];
    }
    return $default;
}

function update_option($key, $value, $autoload = true) {
    unset($autoload);
    $GLOBALS['olissgs_options'][$key] = $value;
    return true;
}

function delete_option($key) {
    unset($GLOBALS['olissgs_options'][$key]);
    return true;
}

function get_users($args = []) {
    unset($args);
    return [];
}

function get_user_meta($user_id, $key, $single = false) {
    unset($user_id, $key, $single);
    return '';
}

function update_user_meta($user_id, $key, $value) {
    unset($user_id, $key, $value);
    return true;
}

function delete_user_meta($user_id, $key) {
    unset($user_id, $key);
    return true;
}

require_once dirname(__DIR__) . '/includes/class-olissgs-options.php';

$failed = 0;

function olissgs_assert($condition, $message) {
    global $failed;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        $failed++;
        return;
    }
    echo "OK: {$message}\n";
}

$map = OLISSGS_Options::settings_legacy_map();
olissgs_assert(count($map) === 10, 'settings_legacy_map has exactly 10 settings');

$expected_legacy = [
    'gsheet_sftp_host',
    'gsheet_sftp_port',
    'gsheet_sftp_username',
    'gsheet_sftp_password',
    'gsheet_sftp_remote_path',
    'gsheet_sftp_schedule',
    'gsheet_sftp_daily_hour',
    'gsheet_sftp_filename_mode',
    'gsheet_sftp_base_filename',
    'gsheet_sftp_export_format',
];
olissgs_assert(array_values($map) === $expected_legacy, 'legacy keys match the 10 registered settings');

$expected_new = [
    'olissgs_host',
    'olissgs_port',
    'olissgs_username',
    'olissgs_password',
    'olissgs_remote_path',
    'olissgs_schedule',
    'olissgs_daily_hour',
    'olissgs_filename_mode',
    'olissgs_base_filename',
    'olissgs_export_format',
];
$actual_new = [];
foreach (array_keys($map) as $name) {
    $actual_new[] = OLISSGS_Options::key($name);
}
olissgs_assert($actual_new === $expected_new, 'new keys use the olissgs_ prefix');

$extra = OLISSGS_Options::extra_legacy_map();
olissgs_assert($extra === ['api_key' => 'gsheet_sftp_api_key', 'enc_key' => 'gsheet_sftp_enc_key'], 'API and encryption keys are also mapped');

$GLOBALS['olissgs_options'] = [
    'gsheet_sftp_host'           => 'sftp.example.com',
    'gsheet_sftp_port'           => 15492,
    'gsheet_sftp_username'       => 'feeduser',
    'gsheet_sftp_password'       => 'v2:encrypted-blob',
    'gsheet_sftp_remote_path'    => '/uploads/',
    'gsheet_sftp_schedule'       => 'hourly',
    'gsheet_sftp_daily_hour'     => 7,
    'gsheet_sftp_filename_mode'  => 'overwrite',
    'gsheet_sftp_base_filename'  => 'inventory',
    'gsheet_sftp_export_format'  => 'xlsx',
    'gsheet_sftp_api_key'        => 'legacy-api-key-32chars-xxxxxxxx',
    'gsheet_sftp_enc_key'        => 'legacy-enc-key',
];

OLISSGS_Options::maybe_migrate();

olissgs_assert(OLISSGS_Options::get('host') === 'sftp.example.com', 'host migrated');
olissgs_assert((int) OLISSGS_Options::get('port') === 15492, 'port migrated');
olissgs_assert(OLISSGS_Options::get('username') === 'feeduser', 'username migrated');
olissgs_assert(OLISSGS_Options::get('password') === 'v2:encrypted-blob', 'password blob migrated as-is');
olissgs_assert(OLISSGS_Options::get('remote_path') === '/uploads/', 'remote_path migrated');
olissgs_assert(OLISSGS_Options::get('schedule') === 'hourly', 'schedule migrated');
olissgs_assert((int) OLISSGS_Options::get('daily_hour') === 7, 'daily_hour migrated');
olissgs_assert(OLISSGS_Options::get('filename_mode') === 'overwrite', 'filename_mode migrated');
olissgs_assert(OLISSGS_Options::get('base_filename') === 'inventory', 'base_filename migrated');
olissgs_assert(OLISSGS_Options::get('export_format') === 'xlsx', 'export_format migrated');
olissgs_assert(OLISSGS_Options::get('api_key') === 'legacy-api-key-32chars-xxxxxxxx', 'api_key migrated');
olissgs_assert(OLISSGS_Options::get('enc_key') === 'legacy-enc-key', 'enc_key migrated');

foreach (OLISSGS_Options::all_legacy_map() as $legacy) {
    olissgs_assert(!array_key_exists($legacy, $GLOBALS['olissgs_options']), "legacy option {$legacy} deleted after copy");
}

olissgs_assert(get_option('olissgs_options_migrated') === '1.6.0', 'migration flag stored');

$GLOBALS['olissgs_options']['gsheet_sftp_host'] = 'should-not-overwrite';
OLISSGS_Options::maybe_migrate();
olissgs_assert(OLISSGS_Options::get('host') === 'sftp.example.com', 'second migrate run does not overwrite new values');

$GLOBALS['olissgs_options'] = [
    'olissgs_host'     => 'already-new.example.com',
    'gsheet_sftp_host' => 'old.example.com',
];
delete_option('olissgs_options_migrated');
OLISSGS_Options::maybe_migrate();
olissgs_assert(OLISSGS_Options::get('host') === 'already-new.example.com', 'existing new option is kept when both keys exist');
olissgs_assert(!array_key_exists('gsheet_sftp_host', $GLOBALS['olissgs_options']), 'legacy host still deleted when new already exists');

if ($failed > 0) {
    fwrite(STDERR, "\n{$failed} assertion(s) failed.\n");
    exit(1);
}

$admin = file_get_contents(dirname(__DIR__) . '/includes/class-admin-settings.php');
foreach (array_keys($map) as $name) {
    $field = 'OLISSGS_Options::key(\'' . $name . '\')';
    olissgs_assert(strpos($admin, $field) !== false, "settings page still registers field {$name}");
}
olissgs_assert(strpos($admin, 'Save and test connection') !== false, 'Save and test connection button is present');
olissgs_assert(strpos($admin, '1. SFTP server') !== false, 'step 1 heading is present');
olissgs_assert(strpos($admin, '2. Google Sheets') !== false, 'step 2 heading is present');
olissgs_assert(strpos($admin, '3. Activity') !== false, 'step 3 heading is present');
olissgs_assert(strpos($admin, 'OLISSGS_DONATE_URL') === false, 'settings page has no donate call');
olissgs_assert(strpos($admin, 'donate_notice') === false, 'settings page has no donate notice');

$main = file_get_contents(dirname(__DIR__) . '/sftp-sync-for-google-sheets.php');
olissgs_assert(strpos($main, "plugin_row_meta") !== false, 'Donate lives in plugin_row_meta');
olissgs_assert(substr_count($main, 'esc_html__(\'Donate\'') === 1, 'exactly one Donate label in the main plugin file');
olissgs_assert(strpos($main, 'donate_notice') === false, 'main plugin file has no donate notice');
olissgs_assert(strpos($main, "Version: 1.6.1") !== false, 'plugin header version is 1.6.1');
olissgs_assert(strpos($main, "Tested up to: 7.1") !== false, 'plugin header tested up to 7.1');
olissgs_assert(strpos($main, "https://github.com/olivierbigras/sftp-sync-for-google-sheets") !== false, 'plugin URI uses github.com/olivierbigras');

$sftp = file_get_contents(dirname(__DIR__) . '/includes/class-sftp-handler.php');
olissgs_assert(strpos($sftp, 'unlink(') === false, 'SFTP handler does not call unlink()');
olissgs_assert(strpos($sftp, 'wp_delete_file(') !== false, 'SFTP handler uses wp_delete_file()');

$readme = file_get_contents(dirname(__DIR__) . '/readme.txt');
olissgs_assert(preg_match('/^Tested up to:\s*7\.1$/m', $readme) === 1, 'readme Tested up to is 7.1');
olissgs_assert(preg_match('/^Stable tag:\s*1\.6\.1$/m', $readme) === 1, 'readme Stable tag is 1.6.1');
olissgs_assert(strpos($readme, '= 1.6.1 =') !== false, 'readme changelog includes 1.6.1');
olissgs_assert(strpos($readme, 'github.com/bigrat95') === false, 'readme has no old GitHub account links');

echo "\nAll option-map checks passed.\n";
exit(0);
