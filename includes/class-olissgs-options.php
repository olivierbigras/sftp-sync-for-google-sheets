<?php
/**
 * Prefixed options, legacy keys, and lossless upgrade migration.
 *
 * @package OLISSGS
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Option names and one-time copy of legacy gsheet_sftp_* values.
 */
class OLISSGS_Options {

    const PREFIX = 'olissgs_';

    /**
     * The 10 registered settings (short name => legacy option).
     *
     * @return array<string,string>
     */
    public static function settings_legacy_map() {
        return [
            'host'           => 'gsheet_sftp_host',
            'port'           => 'gsheet_sftp_port',
            'username'       => 'gsheet_sftp_username',
            'password'       => 'gsheet_sftp_password',
            'remote_path'    => 'gsheet_sftp_remote_path',
            'schedule'       => 'gsheet_sftp_schedule',
            'daily_hour'     => 'gsheet_sftp_daily_hour',
            'filename_mode'  => 'gsheet_sftp_filename_mode',
            'base_filename'  => 'gsheet_sftp_base_filename',
            'export_format'  => 'gsheet_sftp_export_format',
        ];
    }

    /**
     * Internal options that must also move without loss.
     *
     * @return array<string,string>
     */
    public static function extra_legacy_map() {
        return [
            'api_key' => 'gsheet_sftp_api_key',
            'enc_key' => 'gsheet_sftp_enc_key',
        ];
    }

    /**
     * All option short names that have a legacy counterpart.
     *
     * @return array<string,string>
     */
    public static function all_legacy_map() {
        return array_merge(self::settings_legacy_map(), self::extra_legacy_map());
    }

    /**
     * Prefixed option name.
     *
     * @param string $name Short name.
     * @return string
     */
    public static function key($name) {
        return self::PREFIX . $name;
    }

    /**
     * Read a prefixed option, falling back to the legacy key until migration deletes it.
     *
     * @param string $name    Short name.
     * @param mixed  $default Default.
     * @return mixed
     */
    public static function get($name, $default = false) {
        $new = get_option(self::key($name), null);
        if (null !== $new) {
            return $new;
        }

        $map = self::all_legacy_map();
        if (isset($map[$name])) {
            $old = get_option($map[$name], null);
            if (null !== $old) {
                return $old;
            }
        }

        return $default;
    }

    /**
     * Write a prefixed option.
     *
     * @param string $name     Short name.
     * @param mixed  $value    Value.
     * @param bool   $autoload Autoload.
     * @return bool
     */
    public static function update($name, $value, $autoload = true) {
        return update_option(self::key($name), $value, $autoload);
    }

    /**
     * Copy each legacy option to its olissgs_* key, then delete the old key.
     */
    public static function maybe_migrate() {
        if (get_option(self::key('options_migrated'), false)) {
            return;
        }

        foreach (self::all_legacy_map() as $name => $legacy) {
            $new_key = self::key($name);
            $new_val = get_option($new_key, null);
            $old_val = get_option($legacy, null);

            if (null === $new_val && null !== $old_val) {
                update_option($new_key, $old_val);
            }

            if (null !== $old_val) {
                delete_option($legacy);
            }
        }

        self::migrate_user_meta();

        update_option(self::key('options_migrated'), '1.6.0');
    }

    /**
     * Move donate-notice dismissals to the prefixed user meta key.
     */
    private static function migrate_user_meta() {
        $users = get_users([
            'fields'     => 'ID',
            'meta_key'   => 'sftp_sync_gs_donate_dismissed',
            'meta_compare' => 'EXISTS',
        ]);

        foreach ($users as $user_id) {
            $old = get_user_meta($user_id, 'sftp_sync_gs_donate_dismissed', true);
            if ('' === $old || false === $old) {
                continue;
            }
            if (!get_user_meta($user_id, 'olissgs_donate_dismissed', true)) {
                update_user_meta($user_id, 'olissgs_donate_dismissed', $old);
            }
            delete_user_meta($user_id, 'sftp_sync_gs_donate_dismissed');
        }
    }

    /**
     * Option names to delete on uninstall (new + leftover legacy).
     *
     * @return string[]
     */
    public static function uninstall_option_names() {
        $names = [
            self::key('api_key'),
            self::key('enc_key'),
            self::key('options_migrated'),
            self::key('last_test'),
            self::key('script_copied_fp'),
        ];

        foreach (self::all_legacy_map() as $name => $legacy) {
            $names[] = self::key($name);
            $names[] = $legacy;
        }

        return array_values(array_unique($names));
    }
}
