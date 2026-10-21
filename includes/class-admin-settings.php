<?php
/**
 * Settings screen and Google Apps Script generator.
 *
 * @package OLISSGS
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin settings.
 */
class OLISSGS_Admin_Settings {

    /**
     * @var OLISSGS_Admin_Settings|null
     */
    private static $instance = null;

    /**
     * @return OLISSGS_Admin_Settings
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register the 10 prefixed settings.
     */
    public function register_settings() {
        register_setting('olissgs_settings', OLISSGS_Options::key('host'), [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('olissgs_settings', OLISSGS_Options::key('port'), [
            'sanitize_callback' => [$this, 'sanitize_port'],
            'default'           => 22,
        ]);
        register_setting('olissgs_settings', OLISSGS_Options::key('username'), [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('olissgs_settings', OLISSGS_Options::key('password'), [
            'sanitize_callback' => [$this, 'encrypt_password'],
        ]);
        register_setting('olissgs_settings', OLISSGS_Options::key('remote_path'), [
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '/',
        ]);
        register_setting('olissgs_settings', OLISSGS_Options::key('schedule'), [
            'sanitize_callback' => [$this, 'sanitize_schedule'],
            'default'           => 'daily',
        ]);
        register_setting('olissgs_settings', OLISSGS_Options::key('daily_hour'), [
            'sanitize_callback' => [$this, 'sanitize_daily_hour'],
            'default'           => 2,
        ]);
        register_setting('olissgs_settings', OLISSGS_Options::key('filename_mode'), [
            'sanitize_callback' => [$this, 'sanitize_filename_mode'],
            'default'           => 'dated',
        ]);
        register_setting('olissgs_settings', OLISSGS_Options::key('base_filename'), [
            'sanitize_callback' => 'sanitize_file_name',
            'default'           => 'sheet_export',
        ]);
        register_setting('olissgs_settings', OLISSGS_Options::key('export_format'), [
            'sanitize_callback' => [$this, 'sanitize_export_format'],
            'default'           => 'csv',
        ]);
    }

    /**
     * @param mixed $port Port.
     * @return int
     */
    public function sanitize_port($port) {
        $port = absint($port);
        if ($port < 1 || $port > 65535) {
            return 22;
        }
        return $port;
    }

    /**
     * @param mixed $value Schedule.
     * @return string
     */
    public function sanitize_schedule($value) {
        $value = sanitize_text_field((string) $value);
        return in_array($value, ['daily', 'hourly'], true) ? $value : 'daily';
    }

    /**
     * @param mixed $hour Hour.
     * @return int
     */
    public function sanitize_daily_hour($hour) {
        $hour = absint($hour);
        return ($hour >= 0 && $hour <= 23) ? $hour : 2;
    }

    /**
     * @param mixed $value Mode.
     * @return string
     */
    public function sanitize_filename_mode($value) {
        $value = sanitize_text_field((string) $value);
        return in_array($value, ['dated', 'overwrite'], true) ? $value : 'dated';
    }

    /**
     * @param mixed $value Format.
     * @return string
     */
    public function sanitize_export_format($value) {
        $value = sanitize_text_field((string) $value);
        return in_array($value, ['csv', 'xlsx'], true) ? $value : 'csv';
    }

    /**
     * Keep the stored password when the field is left blank.
     *
     * @param mixed $password Submitted password.
     * @return string
     */
    public function encrypt_password($password) {
        if ($password === '' || $password === null) {
            return OLISSGS_Options::get('password', '');
        }
        return self::encrypt((string) $password);
    }

    /**
     * Decrypt the stored SFTP password.
     *
     * @return string
     */
    public static function get_password() {
        $encrypted = OLISSGS_Options::get('password', '');
        if ($encrypted === '' || $encrypted === false) {
            return '';
        }

        $plugin_key = self::get_plugin_encryption_key(false);
        if ($plugin_key) {
            $decrypted = self::decrypt_with_key($encrypted, $plugin_key);
            if ($decrypted !== null) {
                return $decrypted;
            }
        }

        $legacy_key = hash('sha256', SECURE_AUTH_KEY . SECURE_AUTH_SALT, true);
        $decrypted  = self::decrypt_with_key($encrypted, $legacy_key);
        if ($decrypted !== null) {
            self::persist_reencrypted_password($decrypted);
            return $decrypted;
        }

        $legacy = base64_decode($encrypted, true);
        if (is_string($legacy) && $legacy !== '' && preg_match('/^[\x20-\x7E]+$/', $legacy)) {
            self::persist_reencrypted_password($legacy);
            return $legacy;
        }

        return '';
    }

    /**
     * True when a password is stored but cannot be decrypted.
     *
     * @return bool
     */
    public static function password_needs_reentry() {
        $stored = OLISSGS_Options::get('password', '');
        return $stored !== '' && $stored !== false && self::get_password() === '';
    }

    /**
     * Stable plugin key so WordPress salt rotation does not invalidate the password.
     *
     * @param bool $create Create if missing.
     * @return string|null
     */
    private static function get_plugin_encryption_key($create = false) {
        $stored = OLISSGS_Options::get('enc_key', '');
        if ($stored) {
            $decoded = base64_decode($stored, true);
            if ($decoded !== false && strlen($decoded) === 32) {
                return $decoded;
            }
        }

        if (!$create) {
            return null;
        }

        $key = random_bytes(32);
        OLISSGS_Options::update('enc_key', base64_encode($key), false);
        return $key;
    }

    /**
     * @param string $plaintext Password.
     */
    private static function persist_reencrypted_password($plaintext) {
        OLISSGS_Options::update('password', self::encrypt($plaintext), false);
    }

    /**
     * @param string $data Plaintext.
     * @return string
     */
    private static function encrypt($data) {
        $key       = self::get_plugin_encryption_key(true);
        $iv        = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        $hmac      = hash_hmac('sha256', $iv . $encrypted, $key, true);
        return 'v2:' . base64_encode($iv . $hmac . $encrypted);
    }

    /**
     * @param string $data Encrypted blob.
     * @param string $key  Raw key.
     * @return string|null
     */
    private static function decrypt_with_key($data, $key) {
        if (strpos($data, 'v2:') === 0) {
            $raw = base64_decode(substr($data, 3), true);
            if ($raw === false || strlen($raw) < 49) {
                return null;
            }
            $iv        = substr($raw, 0, 16);
            $hmac      = substr($raw, 16, 32);
            $encrypted = substr($raw, 48);
            $expected  = hash_hmac('sha256', $iv . $encrypted, $key, true);
            if (!hash_equals($expected, $hmac)) {
                return null;
            }
            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            return ($decrypted !== false && $decrypted !== '') ? $decrypted : null;
        }

        $raw = base64_decode($data, true);
        if ($raw === false || strlen($raw) < 17) {
            return null;
        }
        $iv        = substr($raw, 0, 16);
        $encrypted = substr($raw, 16);
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return ($decrypted !== false && $decrypted !== '') ? $decrypted : null;
    }

    /**
     * Render Settings → SFTP Sync.
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['olissgs_test_sftp']) && check_admin_referer('olissgs_test_connection')) {
            $this->test_connection();
        }

        if (isset($_POST['olissgs_regenerate_api_key']) && check_admin_referer('olissgs_regenerate_key')) {
            OLISSGS_Options::update('api_key', wp_generate_password(32, false));
            echo '<div class="notice notice-success"><p>' . esc_html__('API key regenerated successfully!', 'sftp-sync-for-google-sheets') . '</p></div>';
        }

        if (isset($_POST['olissgs_clear_logs']) && check_admin_referer('olissgs_clear_logs')) {
            $log_file = OLISSGS_Plugin::get_log_file();
            if (file_exists($log_file)) {
                wp_delete_file($log_file);
            }
            echo '<div class="notice notice-success"><p>' . esc_html__('Logs cleared!', 'sftp-sync-for-google-sheets') . '</p></div>';
        }

        $api_key = OLISSGS_Options::get('api_key', '');
        if ($api_key === '') {
            $api_key = wp_generate_password(32, false);
            OLISSGS_Options::update('api_key', $api_key);
            echo '<div class="notice notice-warning"><p>' . esc_html__('A new API key was generated because none was stored. Update CONFIG.API_KEY in your Google Apps Script to match the key on this page.', 'sftp-sync-for-google-sheets') . '</p></div>';
        }

        $endpoint_url           = rest_url('gsheet-sftp/v1/upload');
        $password_needs_reentry = self::password_needs_reentry();
        $has_stored_password    = (bool) OLISSGS_Options::get('password', '');
        ?>
        <div class="wrap olissgs-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php if ($password_needs_reentry) : ?>
                <div class="notice notice-error">
                    <p><?php esc_html_e('The saved SFTP password could not be decrypted. This usually happens after WordPress security keys/salts are rotated (for example by Solid Security). Re-enter the SFTP password below and click Save Settings, then test the connection.', 'sftp-sync-for-google-sheets'); ?></p>
                </div>
            <?php endif; ?>

            <div class="olissgs-section">
                <h2><?php esc_html_e('API Endpoint Information', 'sftp-sync-for-google-sheets'); ?></h2>
                <p><?php esc_html_e('Use these details in your Google Apps Script:', 'sftp-sync-for-google-sheets'); ?></p>

                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Endpoint URL', 'sftp-sync-for-google-sheets'); ?></th>
                        <td>
                            <code class="olissgs-endpoint"><?php echo esc_url($endpoint_url); ?></code>
                            <button type="button" class="button button-small" data-olissgs-copy="<?php echo esc_attr($endpoint_url); ?>">
                                <?php esc_html_e('Copy', 'sftp-sync-for-google-sheets'); ?>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('API Key', 'sftp-sync-for-google-sheets'); ?></th>
                        <td>
                            <code class="olissgs-api-key"><?php echo esc_html($api_key); ?></code>
                            <button type="button" class="button button-small" data-olissgs-copy="<?php echo esc_attr($api_key); ?>">
                                <?php esc_html_e('Copy', 'sftp-sync-for-google-sheets'); ?>
                            </button>
                            <form method="post" class="olissgs-inline-form">
                                <?php wp_nonce_field('olissgs_regenerate_key'); ?>
                                <button type="submit" name="olissgs_regenerate_api_key" class="button button-small" data-olissgs-confirm="<?php echo esc_attr(__('Are you sure? You will need to update your Google Apps Script.', 'sftp-sync-for-google-sheets')); ?>">
                                    <?php esc_html_e('Regenerate', 'sftp-sync-for-google-sheets'); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                </table>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('olissgs_settings'); ?>

                <div class="olissgs-section">
                    <h2><?php esc_html_e('SFTP Server Settings', 'sftp-sync-for-google-sheets'); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th><label for="olissgs_host"><?php esc_html_e('SFTP Host', 'sftp-sync-for-google-sheets'); ?></label></th>
                            <td>
                                <input type="text" id="olissgs_host" name="<?php echo esc_attr(OLISSGS_Options::key('host')); ?>"
                                       value="<?php echo esc_attr(OLISSGS_Options::get('host', '')); ?>"
                                       class="regular-text" placeholder="sftp.example.com or IP address">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="olissgs_port"><?php esc_html_e('SFTP Port', 'sftp-sync-for-google-sheets'); ?></label></th>
                            <td>
                                <input type="number" id="olissgs_port" name="<?php echo esc_attr(OLISSGS_Options::key('port')); ?>"
                                       value="<?php echo esc_attr(OLISSGS_Options::get('port', 22)); ?>"
                                       class="small-text" min="1" max="65535">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="olissgs_username"><?php esc_html_e('Username', 'sftp-sync-for-google-sheets'); ?></label></th>
                            <td>
                                <input type="text" id="olissgs_username" name="<?php echo esc_attr(OLISSGS_Options::key('username')); ?>"
                                       value="<?php echo esc_attr(OLISSGS_Options::get('username', '')); ?>"
                                       class="regular-text" autocomplete="off">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="olissgs_password"><?php esc_html_e('Password', 'sftp-sync-for-google-sheets'); ?></label></th>
                            <td>
                                <input type="password" id="olissgs_password" name="<?php echo esc_attr(OLISSGS_Options::key('password')); ?>"
                                       value="" class="regular-text" autocomplete="new-password"
                                       placeholder="<?php echo $password_needs_reentry ? esc_attr__('Re-enter password', 'sftp-sync-for-google-sheets') : ($has_stored_password ? '••••••••' : ''); ?>">
                                <?php if ($password_needs_reentry) : ?>
                                    <p class="description olissgs-error-text"><?php esc_html_e('The stored password is unreadable. You must type it again and save.', 'sftp-sync-for-google-sheets'); ?></p>
                                <?php else : ?>
                                    <p class="description"><?php esc_html_e('Leave blank to keep existing password.', 'sftp-sync-for-google-sheets'); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="olissgs_remote_path"><?php esc_html_e('Remote Path', 'sftp-sync-for-google-sheets'); ?></label></th>
                            <td>
                                <input type="text" id="olissgs_remote_path" name="<?php echo esc_attr(OLISSGS_Options::key('remote_path')); ?>"
                                       value="<?php echo esc_attr(OLISSGS_Options::get('remote_path', '/')); ?>"
                                       class="regular-text" placeholder="/path/to/uploads/">
                                <p class="description"><?php esc_html_e('Directory where files will be uploaded. Must end with /', 'sftp-sync-for-google-sheets'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="olissgs-section">
                    <h2><?php esc_html_e('Export Settings', 'sftp-sync-for-google-sheets'); ?></h2>
                    <p><?php esc_html_e('Configure how the Google Sheet export works. These settings are embedded in the generated Apps Script.', 'sftp-sync-for-google-sheets'); ?></p>

                    <table class="form-table">
                        <tr>
                            <th><label for="olissgs_schedule"><?php esc_html_e('Schedule', 'sftp-sync-for-google-sheets'); ?></label></th>
                            <td>
                                <select id="olissgs_schedule" name="<?php echo esc_attr(OLISSGS_Options::key('schedule')); ?>">
                                    <option value="daily" <?php selected(OLISSGS_Options::get('schedule', 'daily'), 'daily'); ?>><?php esc_html_e('Daily', 'sftp-sync-for-google-sheets'); ?></option>
                                    <option value="hourly" <?php selected(OLISSGS_Options::get('schedule', 'daily'), 'hourly'); ?>><?php esc_html_e('Hourly', 'sftp-sync-for-google-sheets'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr id="olissgs-daily-hour-row">
                            <th><label for="olissgs_daily_hour"><?php esc_html_e('Daily Export Hour', 'sftp-sync-for-google-sheets'); ?></label></th>
                            <td>
                                <select id="olissgs_daily_hour" name="<?php echo esc_attr(OLISSGS_Options::key('daily_hour')); ?>">
                                    <?php for ($h = 0; $h < 24; $h++) : ?>
                                    <option value="<?php echo esc_attr($h); ?>" <?php selected((int) OLISSGS_Options::get('daily_hour', 2), $h); ?>>
                                        <?php echo esc_html(sprintf('%02d:00', $h)); ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                                <p class="description"><?php esc_html_e('Hour of day to run export (in your timezone).', 'sftp-sync-for-google-sheets'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="olissgs_filename_mode"><?php esc_html_e('Filename Mode', 'sftp-sync-for-google-sheets'); ?></label></th>
                            <td>
                                <select id="olissgs_filename_mode" name="<?php echo esc_attr(OLISSGS_Options::key('filename_mode')); ?>">
                                    <option value="dated" <?php selected(OLISSGS_Options::get('filename_mode', 'dated'), 'dated'); ?>><?php esc_html_e('Dated (new file each export)', 'sftp-sync-for-google-sheets'); ?></option>
                                    <option value="overwrite" <?php selected(OLISSGS_Options::get('filename_mode', 'dated'), 'overwrite'); ?>><?php esc_html_e('Overwrite (same filename)', 'sftp-sync-for-google-sheets'); ?></option>
                                </select>
                                <p class="description">
                                    <strong><?php esc_html_e('Dated:', 'sftp-sync-for-google-sheets'); ?></strong> <?php esc_html_e('Creates files like sheet_export_2026-01-07_120000.csv', 'sftp-sync-for-google-sheets'); ?><br>
                                    <strong><?php esc_html_e('Overwrite:', 'sftp-sync-for-google-sheets'); ?></strong> <?php esc_html_e('Always uses the same filename, replacing the previous file', 'sftp-sync-for-google-sheets'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="olissgs_base_filename"><?php esc_html_e('Base Filename', 'sftp-sync-for-google-sheets'); ?></label></th>
                            <td>
                                <input type="text" id="olissgs_base_filename" name="<?php echo esc_attr(OLISSGS_Options::key('base_filename')); ?>"
                                       value="<?php echo esc_attr(OLISSGS_Options::get('base_filename', 'sheet_export')); ?>"
                                       class="regular-text" placeholder="sheet_export">
                                <p class="description"><?php esc_html_e('Base name for exported files (without extension).', 'sftp-sync-for-google-sheets'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="olissgs_export_format"><?php esc_html_e('Export Format', 'sftp-sync-for-google-sheets'); ?></label></th>
                            <td>
                                <select id="olissgs_export_format" name="<?php echo esc_attr(OLISSGS_Options::key('export_format')); ?>">
                                    <option value="csv" <?php selected(OLISSGS_Options::get('export_format', 'csv'), 'csv'); ?>>CSV</option>
                                    <option value="xlsx" <?php selected(OLISSGS_Options::get('export_format', 'csv'), 'xlsx'); ?>>XLSX (Excel)</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button(esc_html__('Save Settings', 'sftp-sync-for-google-sheets')); ?>
            </form>

            <div class="olissgs-section">
                <h2><?php esc_html_e('Test Connection', 'sftp-sync-for-google-sheets'); ?></h2>
                <p><?php esc_html_e('Test your SFTP connection with the currently saved settings. Click Save Settings first if you changed the password or server details.', 'sftp-sync-for-google-sheets'); ?></p>
                <form method="post">
                    <?php wp_nonce_field('olissgs_test_connection'); ?>
                    <button type="submit" name="olissgs_test_sftp" class="button button-secondary">
                        <?php esc_html_e('Test SFTP Connection', 'sftp-sync-for-google-sheets'); ?>
                    </button>
                </form>
            </div>

            <div class="olissgs-section">
                <h2><?php esc_html_e('Recent Logs', 'sftp-sync-for-google-sheets'); ?></h2>
                <form method="post" class="olissgs-clear-logs">
                    <?php wp_nonce_field('olissgs_clear_logs'); ?>
                    <button type="submit" name="olissgs_clear_logs" class="button button-small">
                        <?php esc_html_e('Clear Logs', 'sftp-sync-for-google-sheets'); ?>
                    </button>
                </form>
                <div class="olissgs-logs">
                    <?php
                    $logs = OLISSGS_Plugin::get_logs(50);
                    if (empty($logs)) {
                        echo '<span class="log-info">' . esc_html__('No logs yet.', 'sftp-sync-for-google-sheets') . '</span>';
                    } else {
                        foreach ($logs as $log) {
                            $class = 'log-info';
                            if (strpos($log, '[error]') !== false) {
                                $class = 'log-error';
                            } elseif (strpos($log, '[success]') !== false) {
                                $class = 'log-success';
                            }
                            echo '<div class="' . esc_attr($class) . '">' . esc_html($log) . '</div>';
                        }
                    }
                    ?>
                </div>
            </div>

            <div class="olissgs-section">
                <h2><?php esc_html_e('Google Apps Script', 'sftp-sync-for-google-sheets'); ?></h2>
                <p><?php esc_html_e('Copy the code below into your Google Sheet\'s Apps Script editor (Extensions → Apps Script):', 'sftp-sync-for-google-sheets'); ?></p>
                <p>
                    <button type="button" class="button" data-olissgs-show="olissgs-apps-script">
                        <?php esc_html_e('Show Google Apps Script Code', 'sftp-sync-for-google-sheets'); ?>
                    </button>
                </p>
                <textarea id="olissgs-apps-script" class="olissgs-apps-script" hidden readonly><?php echo esc_textarea($this->get_apps_script_template()); ?></textarea>
            </div>

            <p class="description olissgs-footer-note">
                <?php
                echo wp_kses(
                    sprintf(
                        /* translators: %s: donate URL */
                        __('If this plugin helps you, please consider a <a href="%s" target="_blank" rel="noopener noreferrer">donation</a>. Thank you!', 'sftp-sync-for-google-sheets'),
                        esc_url(OLISSGS_DONATE_URL)
                    ),
                    [
                        'a' => [
                            'href'   => true,
                            'target' => true,
                            'rel'    => true,
                        ],
                    ]
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Run an SFTP connection test with saved settings.
     */
    private function test_connection() {
        $handler = OLISSGS_SFTP_Handler::get_instance();
        $result  = $handler->test_connection();

        if ($result['success']) {
            echo '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>';
            OLISSGS_Plugin::log('SFTP connection test successful', 'success');
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
            OLISSGS_Plugin::log('SFTP connection test failed: ' . $result['message'], 'error');
        }
    }

    /**
     * Google Apps Script template with current settings embedded.
     *
     * @return string
     */
    private function get_apps_script_template() {
        $endpoint_url   = rest_url('gsheet-sftp/v1/upload');
        $api_key        = OLISSGS_Options::get('api_key', '');
        $schedule       = OLISSGS_Options::get('schedule', 'daily');
        $daily_hour     = (int) OLISSGS_Options::get('daily_hour', 2);
        $filename_mode  = OLISSGS_Options::get('filename_mode', 'dated');
        $base_filename  = OLISSGS_Options::get('base_filename', 'sheet_export');
        $export_format  = OLISSGS_Options::get('export_format', 'csv');
        $include_date   = $filename_mode === 'dated' ? 'true' : 'false';

        $script  = "/**\n";
        $script .= " * Google Apps Script - Export Sheet to WordPress SFTP Plugin\n";
        $script .= " * Generated by SFTP Sync for Google Sheets Plugin\n";
        $script .= " * \n";
        $script .= " * After pasting this code:\n";
        $script .= " * 1. Save the project (Ctrl+S)\n";
        $script .= " * 2. Run setupTrigger() once to enable automatic exports\n";
        $script .= " * 3. Or use the SFTP Export menu after reloading the sheet\n";
        $script .= " */\n\n";
        $script .= "const CONFIG = {\n";
        $script .= "  ENDPOINT_URL: '" . esc_js($endpoint_url) . "',\n";
        $script .= "  API_KEY: '" . esc_js($api_key) . "',\n";
        $script .= "  EXPORT_FORMAT: '" . esc_js($export_format) . "',\n";
        $script .= "  INCLUDE_DATE: " . $include_date . ",  // false = overwrite same file each time\n";
        $script .= "  BASE_FILENAME: '" . esc_js($base_filename) . "',\n";
        $script .= "  SHEET_NAME: '',  // Leave empty for active sheet, or specify sheet name\n";
        $script .= "  SCHEDULE: '" . esc_js($schedule) . "',\n";
        $script .= "  DAILY_HOUR: " . intval($daily_hour) . ",\n";
        $script .= "};\n\n";
        $script .= "function exportAndUpload() {\n";
        $script .= "  try {\n";
        $script .= "    const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();\n";
        $script .= "    let sheet = CONFIG.SHEET_NAME ? \n";
        $script .= "      spreadsheet.getSheetByName(CONFIG.SHEET_NAME) : \n";
        $script .= "      spreadsheet.getActiveSheet();\n";
        $script .= "    \n";
        $script .= "    if (!sheet) throw new Error('Sheet not found');\n";
        $script .= "    \n";
        $script .= "    const filename = generateFilename();\n";
        $script .= "    let fileContent;\n";
        $script .= "    \n";
        $script .= "    if (CONFIG.EXPORT_FORMAT === 'xlsx') {\n";
        $script .= "      fileContent = exportAsXlsx(spreadsheet);\n";
        $script .= "    } else {\n";
        $script .= "      fileContent = exportAsCsv(sheet);\n";
        $script .= "    }\n";
        $script .= "    \n";
        $script .= "    const base64Content = Utilities.base64Encode(fileContent);\n";
        $script .= "    const result = sendToEndpoint(base64Content, filename);\n";
        $script .= "    \n";
        $script .= "    Logger.log('Export completed: ' + JSON.stringify(result));\n";
        $script .= "    return result;\n";
        $script .= "    \n";
        $script .= "  } catch (error) {\n";
        $script .= "    Logger.log('Export failed: ' + error.message);\n";
        $script .= "    throw error;\n";
        $script .= "  }\n";
        $script .= "}\n\n";
        $script .= "function exportAsCsv(sheet) {\n";
        $script .= "  // Use Google's native CSV export (identical to File > Download > CSV)\n";
        $script .= "  const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();\n";
        $script .= "  const sheetId = sheet.getSheetId();\n";
        $script .= "  const url = 'https://docs.google.com/spreadsheets/d/' + spreadsheet.getId()\n";
        $script .= "    + '/export?format=csv&gid=' + sheetId;\n";
        $script .= "  const response = UrlFetchApp.fetch(url, {\n";
        $script .= "    headers: { 'Authorization': 'Bearer ' + ScriptApp.getOAuthToken() }\n";
        $script .= "  });\n";
        $script .= "  return response.getContent();\n";
        $script .= "}\n\n";
        $script .= "function exportAsXlsx(spreadsheet) {\n";
        $script .= "  const url = 'https://docs.google.com/spreadsheets/d/' + spreadsheet.getId() + '/export?format=xlsx';\n";
        $script .= "  const response = UrlFetchApp.fetch(url, {\n";
        $script .= "    headers: { 'Authorization': 'Bearer ' + ScriptApp.getOAuthToken() }\n";
        $script .= "  });\n";
        $script .= "  return response.getContent();\n";
        $script .= "}\n\n";
        $script .= "function sendToEndpoint(base64Content, filename) {\n";
        $script .= "  const payload = {\n";
        $script .= "    file_content: base64Content,\n";
        $script .= "    filename: filename,\n";
        $script .= "    timestamp: new Date().toISOString(),\n";
        $script .= "    api_key: CONFIG.API_KEY\n";
        $script .= "  };\n";
        $script .= "  \n";
        $script .= "  const response = UrlFetchApp.fetch(CONFIG.ENDPOINT_URL, {\n";
        $script .= "    method: 'post',\n";
        $script .= "    contentType: 'application/json',\n";
        $script .= "    headers: { 'X-API-Key': CONFIG.API_KEY },\n";
        $script .= "    payload: JSON.stringify(payload),\n";
        $script .= "    muteHttpExceptions: true\n";
        $script .= "  });\n";
        $script .= "  \n";
        $script .= "  if (response.getResponseCode() !== 200) {\n";
        $script .= "    throw new Error('Upload failed: ' + response.getContentText());\n";
        $script .= "  }\n";
        $script .= "  \n";
        $script .= "  return JSON.parse(response.getContentText());\n";
        $script .= "}\n\n";
        $script .= "function generateFilename() {\n";
        $script .= "  let filename = CONFIG.BASE_FILENAME;\n";
        $script .= "  if (CONFIG.INCLUDE_DATE) {\n";
        $script .= "    filename += '_' + Utilities.formatDate(new Date(), Session.getScriptTimeZone(), 'yyyy-MM-dd_HHmmss');\n";
        $script .= "  }\n";
        $script .= "  return filename + '.' + CONFIG.EXPORT_FORMAT;\n";
        $script .= "}\n\n";
        $script .= "function setupTrigger() {\n";
        $script .= "  removeTriggers();\n";
        $script .= "  if (CONFIG.SCHEDULE === 'hourly') {\n";
        $script .= "    ScriptApp.newTrigger('exportAndUpload').timeBased().everyHours(1).create();\n";
        $script .= "  } else {\n";
        $script .= "    ScriptApp.newTrigger('exportAndUpload').timeBased().everyDays(1).atHour(CONFIG.DAILY_HOUR).create();\n";
        $script .= "  }\n";
        $script .= "  Logger.log('Trigger created');\n";
        $script .= "}\n\n";
        $script .= "function removeTriggers() {\n";
        $script .= "  ScriptApp.getProjectTriggers().forEach(trigger => {\n";
        $script .= "    if (trigger.getHandlerFunction() === 'exportAndUpload') {\n";
        $script .= "      ScriptApp.deleteTrigger(trigger);\n";
        $script .= "    }\n";
        $script .= "  });\n";
        $script .= "}\n\n";
        $script .= "function onOpen() {\n";
        $script .= "  SpreadsheetApp.getUi().createMenu('SFTP Export')\n";
        $script .= "    .addItem('Export Now', 'exportAndUpload')\n";
        $script .= "    .addItem('Setup Daily Trigger', 'setupTrigger')\n";
        $script .= "    .addItem('Remove Triggers', 'removeTriggers')\n";
        $script .= "    .addToUi();\n";
        $script .= "}\n";

        return $script;
    }
}
