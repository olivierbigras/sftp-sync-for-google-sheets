<?php
/**
 * Plugin Name: SFTP Sync for Google Sheets
 * Plugin URI: https://github.com/olivierbigras/sftp-sync-for-google-sheets/
 * Description: Receive Google Sheets exports via API and upload to SFTP server. Automate daily syncs from Google Sheets to your server.
 * Version: 1.6.2
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Tested up to: 7.1
 * Author: Olivier Bigras
 * Author URI: https://olivierbigras.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sftp-sync-for-google-sheets
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('OLISSGS_VERSION', '1.6.2');
define('OLISSGS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OLISSGS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OLISSGS_DONATE_URL', 'https://www.paypal.com/donate/?hosted_button_id=29SAPKFAHMTNW');
define('OLISSGS_BASENAME', plugin_basename(__FILE__));
define('OLISSGS_SLUG', 'sftp-sync-for-google-sheets');

/**
 * Main plugin bootstrap.
 */
class OLISSGS_Plugin {

    /**
     * Singleton.
     *
     * @var OLISSGS_Plugin|null
     */
    private static $instance = null;

    /**
     * @return OLISSGS_Plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        OLISSGS_Options::maybe_migrate();
        $this->init_hooks();
    }

    private function load_dependencies() {
        $olissgs_autoload = OLISSGS_PLUGIN_DIR . 'vendor/autoload.php';
        if (file_exists($olissgs_autoload)) {
            require_once $olissgs_autoload;
        }

        require_once OLISSGS_PLUGIN_DIR . 'includes/class-olissgs-options.php';
        require_once OLISSGS_PLUGIN_DIR . 'includes/class-admin-settings.php';
        require_once OLISSGS_PLUGIN_DIR . 'includes/class-sftp-handler.php';
        require_once OLISSGS_PLUGIN_DIR . 'includes/class-api-endpoint.php';
    }

    private function init_hooks() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('init', [$this, 'maybe_load_textdomain']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_filter('plugin_action_links_' . OLISSGS_BASENAME, [$this, 'plugin_action_links']);
        add_filter('plugin_row_meta', [$this, 'plugin_row_meta'], 10, 2);
        add_action('wp_ajax_olissgs_mark_script_copied', [OLISSGS_Admin_Settings::get_instance(), 'ajax_mark_script_copied']);
    }

    /**
     * Load translations on WordPress versions before 6.7 (JIT loading).
     */
    public function maybe_load_textdomain() {
        global $wp_version;

        if (version_compare((string) $wp_version, '6.7', '>=')) {
            return;
        }

        // WordPress 6.7+ loads translations just-in-time from Domain Path.
        // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
        load_plugin_textdomain(
            'sftp-sync-for-google-sheets',
            false,
            dirname(OLISSGS_BASENAME) . '/languages'
        );
    }

    /**
     * Settings link on the Plugins screen.
     *
     * @param string[] $links Links.
     * @return string[]
     */
    public function plugin_action_links($links) {
        $settings = '<a href="' . esc_url(admin_url('options-general.php?page=' . OLISSGS_SLUG)) . '">' . esc_html__('Settings', 'sftp-sync-for-google-sheets') . '</a>';
        array_unshift($links, $settings);
        return $links;
    }

    /**
     * Single Donate link (wordpress.org: one donate call, in plugin_row_meta).
     *
     * @param string[] $links Links.
     * @param string   $file  Basename.
     * @return string[]
     */
    public function plugin_row_meta($links, $file) {
        if ($file !== OLISSGS_BASENAME) {
            return $links;
        }
        $links[] = '<a href="' . esc_url(OLISSGS_DONATE_URL) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Donate', 'sftp-sync-for-google-sheets') . '</a>';
        return $links;
    }

    /**
     * Activation: migrate options, ensure API key and log directory.
     */
    public function activate() {
        require_once OLISSGS_PLUGIN_DIR . 'includes/class-olissgs-options.php';
        OLISSGS_Options::maybe_migrate();

        if (!OLISSGS_Options::get('api_key')) {
            OLISSGS_Options::update('api_key', wp_generate_password(32, false));
        }

        $log_dir = self::get_log_directory();
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            $index = $log_dir . 'index.php';
            if (!file_exists($index)) {
                global $wp_filesystem;
                if (!function_exists('WP_Filesystem')) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                }
                WP_Filesystem();
                if ($wp_filesystem) {
                    $wp_filesystem->put_contents($index, "<?php\n// Silence is golden.\n");
                }
            }
        }

        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    public function add_admin_menu() {
        add_options_page(
            esc_html__('SFTP Sync for Google Sheets', 'sftp-sync-for-google-sheets'),
            esc_html__('SFTP Sync', 'sftp-sync-for-google-sheets'),
            'manage_options',
            OLISSGS_SLUG,
            [OLISSGS_Admin_Settings::get_instance(), 'render_settings_page']
        );
    }

    public function register_settings() {
        OLISSGS_Admin_Settings::get_instance()->register_settings();
    }

    public function register_rest_routes() {
        OLISSGS_API_Endpoint::get_instance()->register_routes();
    }

    /**
     * Enqueue settings-screen CSS and JS (no inline assets).
     *
     * @param string $hook Current admin hook.
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'settings_page_' . OLISSGS_SLUG) {
            return;
        }

        wp_enqueue_style(
            'olissgs-admin',
            OLISSGS_PLUGIN_URL . 'css/olissgs-admin.css',
            ['dashicons'],
            OLISSGS_VERSION
        );

        wp_enqueue_script(
            'olissgs-admin',
            OLISSGS_PLUGIN_URL . 'js/olissgs-admin.js',
            [],
            OLISSGS_VERSION,
            true
        );

        wp_localize_script(
            'olissgs-admin',
            'olissgsAdmin',
            [
                'ajaxUrl'      => admin_url('admin-ajax.php'),
                'nonce'        => wp_create_nonce('olissgs_admin'),
                'copied'       => __('Copied to clipboard', 'sftp-sync-for-google-sheets'),
                'previewStamp' => current_time('Y-m-d_His'),
            ]
        );
    }

    /**
     * Log directory in wp-content/uploads (slug-based, unchanged).
     *
     * @return string
     */
    public static function get_log_directory() {
        $upload_dir = wp_upload_dir();
        return trailingslashit($upload_dir['basedir']) . OLISSGS_SLUG . '/';
    }

    /**
     * @return string
     */
    public static function get_log_file() {
        return self::get_log_directory() . 'sync.log';
    }

    /**
     * Append a line to the sync log.
     *
     * @param string $message Message.
     * @param string $type    info|success|error.
     */
    public static function log($message, $type = 'info') {
        $log_dir = self::get_log_directory();

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $log_file   = self::get_log_file();
        $timestamp  = current_time('Y-m-d H:i:s');
        $log_entry  = '[' . $timestamp . '] [' . $type . '] ' . $message . "\n";

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- append-only log.
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

        if (file_exists($log_file) && filesize($log_file) > 1048576) {
            $lines = file($log_file);
            $lines = array_slice($lines, -500);
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents($log_file, implode('', $lines));
        }
    }

    /**
     * @param int $limit Max lines.
     * @return string[]
     */
    public static function get_logs($limit = 50) {
        $log_file = self::get_log_file();
        if (!file_exists($log_file)) {
            return [];
        }
        $lines = file($log_file, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return [];
        }
        return array_slice(array_reverse($lines), 0, $limit);
    }

    /**
     * Parse log lines into date / status / message rows.
     *
     * @param int $limit Max rows.
     * @return array<int,array<string,string>>
     */
    public static function get_parsed_logs($limit = 20) {
        $parsed = [];
        foreach (self::get_logs($limit) as $line) {
            if (preg_match('/^\[([^\]]+)\] \[([^\]]+)\] (.*)$/', $line, $matches)) {
                $parsed[] = [
                    'datetime' => $matches[1],
                    'type'     => $matches[2],
                    'message'  => $matches[3],
                ];
            } elseif ($line !== '') {
                $parsed[] = [
                    'datetime' => '',
                    'type'     => 'info',
                    'message'  => $line,
                ];
            }
        }
        return $parsed;
    }
}

OLISSGS_Plugin::get_instance();
