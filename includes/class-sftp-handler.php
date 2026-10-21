<?php
/**
 * SFTP upload via ssh2 or phpseclib.
 *
 * @package OLISSGS
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SFTP client wrapper.
 */
class OLISSGS_SFTP_Handler {

    /**
     * @var OLISSGS_SFTP_Handler|null
     */
    private static $instance = null;

    /**
     * @return OLISSGS_SFTP_Handler
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @return array<string,mixed>
     */
    public function get_config() {
        return [
            'host'        => OLISSGS_Options::get('host', ''),
            'port'        => (int) OLISSGS_Options::get('port', 22),
            'username'    => OLISSGS_Options::get('username', ''),
            'password'    => OLISSGS_Admin_Settings::get_password(),
            'remote_path' => OLISSGS_Options::get('remote_path', '/'),
        ];
    }

    /**
     * @param array<string,mixed> $config Config.
     * @return string|null
     */
    private function get_config_error($config) {
        if ($config['host'] === '' || $config['username'] === '') {
            return 'SFTP configuration incomplete. Please fill in host and username.';
        }

        if ($config['password'] === '') {
            if (OLISSGS_Admin_Settings::password_needs_reentry()) {
                return 'Stored SFTP password could not be decrypted. WordPress security keys may have changed. Re-enter the SFTP password and click Save Settings.';
            }
            return 'SFTP password is missing. Please enter it in plugin settings and save.';
        }

        return null;
    }

    /**
     * @param string $file_content File bytes.
     * @param string $filename     Remote filename.
     * @return array<string,mixed>
     */
    public function upload_file($file_content, $filename) {
        $config = $this->get_config();

        $config_error = $this->get_config_error($config);
        if ($config_error) {
            return [
                'success' => false,
                'error'   => $config_error,
            ];
        }

        $filename = $this->sanitize_filename($filename);

        $allowed_extensions = ['csv', 'xlsx', 'xls'];
        $ext                = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_extensions, true)) {
            return [
                'success' => false,
                'error'   => 'Invalid file type. Allowed: ' . implode(', ', $allowed_extensions),
            ];
        }

        $remote_path = rtrim($config['remote_path'], '/') . '/';

        if (function_exists('ssh2_connect')) {
            return $this->upload_via_ssh2($file_content, $filename, $config, $remote_path);
        }

        return $this->upload_via_phpseclib($file_content, $filename, $config, $remote_path);
    }

    /**
     * @param string               $file_content File bytes.
     * @param string               $filename     Name.
     * @param array<string,mixed>  $config       Config.
     * @param string               $remote_path  Remote dir.
     * @return array<string,mixed>
     */
    private function upload_via_ssh2($file_content, $filename, $config, $remote_path) {
        $connection = @ssh2_connect($config['host'], $config['port']);

        if (!$connection) {
            return [
                'success' => false,
                'error'   => 'Could not connect to SFTP server at ' . $config['host'] . ':' . $config['port'],
            ];
        }

        if (!@ssh2_auth_password($connection, $config['username'], $config['password'])) {
            return [
                'success' => false,
                'error'   => 'SFTP authentication failed for user: ' . $config['username'],
            ];
        }

        $sftp = @ssh2_sftp($connection);
        if (!$sftp) {
            return [
                'success' => false,
                'error'   => 'Could not initialize SFTP subsystem',
            ];
        }

        $remote_file = 'ssh2.sftp://' . $sftp . $remote_path . $filename;

        if (@file_put_contents($remote_file, $file_content) === false) {
            return [
                'success' => false,
                'error'   => 'Failed to write file to SFTP. Check remote path permissions.',
            ];
        }

        return [
            'success'     => true,
            'filename'    => $filename,
            'remote_path' => $remote_path . $filename,
            'method'      => 'ssh2',
        ];
    }

    /**
     * @param string               $file_content File bytes.
     * @param string               $filename     Name.
     * @param array<string,mixed>  $config       Config.
     * @param string               $remote_path  Remote dir.
     * @return array<string,mixed>
     */
    private function upload_via_phpseclib($file_content, $filename, $config, $remote_path) {
        $autoload_path = OLISSGS_PLUGIN_DIR . 'vendor/autoload.php';
        if (file_exists($autoload_path)) {
            require_once $autoload_path;
        }

        if (class_exists('phpseclib3\Net\SFTP')) {
            try {
                $sftp = new \phpseclib3\Net\SFTP($config['host'], $config['port'], 30);

                if (!$sftp->login($config['username'], $config['password'])) {
                    $errors    = $sftp->getErrors();
                    $lastError = !empty($errors) ? end($errors) : 'Unknown error';
                    return [
                        'success' => false,
                        'error'   => 'SFTP authentication failed: ' . $lastError . ' (Host: ' . $config['host'] . ':' . $config['port'] . ', User: ' . $config['username'] . ')',
                    ];
                }

                if (!$sftp->put($remote_path . $filename, $file_content)) {
                    return [
                        'success' => false,
                        'error'   => 'Failed to write file (phpseclib3)',
                    ];
                }

                return [
                    'success'     => true,
                    'filename'    => $filename,
                    'remote_path' => $remote_path . $filename,
                    'method'      => 'phpseclib3',
                ];
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'error'   => 'phpseclib3 error: ' . $e->getMessage(),
                ];
            }
        }

        if (class_exists('phpseclib\Net\SFTP')) {
            try {
                $sftp = new \phpseclib\Net\SFTP($config['host'], $config['port']);

                if (!$sftp->login($config['username'], $config['password'])) {
                    return [
                        'success' => false,
                        'error'   => 'SFTP authentication failed (phpseclib)',
                    ];
                }

                if (!$sftp->put($remote_path . $filename, $file_content)) {
                    return [
                        'success' => false,
                        'error'   => 'Failed to write file (phpseclib)',
                    ];
                }

                return [
                    'success'     => true,
                    'filename'    => $filename,
                    'remote_path' => $remote_path . $filename,
                    'method'      => 'phpseclib',
                ];
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'error'   => 'phpseclib error: ' . $e->getMessage(),
                ];
            }
        }

        return [
            'success' => false,
            'error'   => 'No SFTP library available. Please install ssh2 PHP extension or phpseclib via Composer.',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function test_connection() {
        $config = $this->get_config();

        $config_error = $this->get_config_error($config);
        if ($config_error) {
            return [
                'success' => false,
                'message' => $config_error,
            ];
        }

        if (function_exists('ssh2_connect')) {
            $connection = @ssh2_connect($config['host'], $config['port']);

            if (!$connection) {
                return [
                    'success' => false,
                    'message' => 'Could not connect to ' . $config['host'] . ':' . $config['port'],
                ];
            }

            if (!@ssh2_auth_password($connection, $config['username'], $config['password'])) {
                return [
                    'success' => false,
                    'message' => 'Authentication failed for user: ' . $config['username'],
                ];
            }

            $sftp = @ssh2_sftp($connection);
            if (!$sftp) {
                return [
                    'success' => false,
                    'message' => 'Could not initialize SFTP subsystem',
                ];
            }

            return [
                'success' => true,
                'message' => 'Connection successful! (using ssh2 extension)',
            ];
        }

        $autoload_path = OLISSGS_PLUGIN_DIR . 'vendor/autoload.php';
        if (file_exists($autoload_path)) {
            require_once $autoload_path;
        }

        if (class_exists('phpseclib3\Net\SFTP')) {
            try {
                $sftp = new \phpseclib3\Net\SFTP($config['host'], $config['port']);

                if (!$sftp->login($config['username'], $config['password'])) {
                    return [
                        'success' => false,
                        'message' => 'Authentication failed (phpseclib3)',
                    ];
                }

                return [
                    'success' => true,
                    'message' => 'Connection successful! (using phpseclib3)',
                ];
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Connection error: ' . $e->getMessage(),
                ];
            }
        }

        if (class_exists('phpseclib\Net\SFTP')) {
            try {
                $sftp = new \phpseclib\Net\SFTP($config['host'], $config['port']);

                if (!$sftp->login($config['username'], $config['password'])) {
                    return [
                        'success' => false,
                        'message' => 'Authentication failed (phpseclib)',
                    ];
                }

                return [
                    'success' => true,
                    'message' => 'Connection successful! (using phpseclib)',
                ];
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Connection error: ' . $e->getMessage(),
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'No SFTP library available. Install ssh2 PHP extension or run: composer require phpseclib/phpseclib:~3.0',
        ];
    }

    /**
     * @param string $filename Raw name.
     * @return string
     */
    private function sanitize_filename($filename) {
        $filename = basename($filename);
        $filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $filename);
        $filename = preg_replace('/\.+/', '.', $filename);
        return $filename;
    }
}
