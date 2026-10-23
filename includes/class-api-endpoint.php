<?php
/**
 * REST API upload and status endpoints.
 *
 * @package OLISSGS
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST controller.
 */
class OLISSGS_API_Endpoint {

    /**
     * Public namespace kept so existing Google Apps Scripts keep working.
     */
    const REST_NAMESPACE = 'gsheet-sftp/v1';

    /**
     * Prefixed alias registered in parallel.
     */
    const REST_NAMESPACE_ALIASED = 'olissgs/v1';

    /**
     * @var OLISSGS_API_Endpoint|null
     */
    private static $instance = null;

    /**
     * @return OLISSGS_API_Endpoint
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register current and legacy REST routes.
     */
    public function register_routes() {
        foreach ([self::REST_NAMESPACE, self::REST_NAMESPACE_ALIASED] as $namespace) {
            register_rest_route($namespace, '/upload', [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle_upload'],
                'permission_callback' => [$this, 'verify_api_key'],
            ]);

            register_rest_route($namespace, '/status', [
                'methods'             => 'GET',
                'callback'            => [$this, 'handle_status'],
                'permission_callback' => [$this, 'verify_api_key'],
            ]);
        }
    }

    /**
     * API key + rate limit.
     *
     * @param WP_REST_Request $request Request.
     * @return bool|WP_Error
     */
    public function verify_api_key($request) {
        if (!$this->check_rate_limit()) {
            OLISSGS_Plugin::log('Rate limit exceeded from ' . $this->get_client_ip(), 'error');
            return new WP_Error('rate_limit_exceeded', 'Too many requests. Please try again later.', ['status' => 429]);
        }

        $stored_key = OLISSGS_Options::get('api_key', '');

        if (empty($stored_key)) {
            OLISSGS_Plugin::log('API key not configured', 'error');
            return false;
        }

        $received_key = $request->get_header('X-API-Key');

        if (empty($received_key)) {
            $body         = $request->get_json_params();
            $received_key = is_array($body) && isset($body['api_key']) ? $body['api_key'] : '';
        }

        if (empty($received_key)) {
            $received_key = $request->get_param('api_key');
        }

        $received_key = is_string($received_key) ? $received_key : '';

        if (empty($received_key)) {
            OLISSGS_Plugin::log('No API key provided in request from ' . $this->get_client_ip(), 'error');
            return false;
        }

        if (!hash_equals($stored_key, $received_key)) {
            OLISSGS_Plugin::log('Invalid API key from ' . $this->get_client_ip(), 'error');
            return false;
        }

        return true;
    }

    /**
     * @return string
     */
    private function get_client_ip() {
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
            $ips       = explode(',', $forwarded);
            $ip        = trim($ips[0]);
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    /**
     * @return bool
     */
    private function check_rate_limit() {
        $ip            = $this->get_client_ip();
        $transient_key = 'olissgs_rate_' . md5($ip);
        $requests      = get_transient($transient_key);

        if ($requests === false) {
            set_transient($transient_key, 1, 60);
            return true;
        }

        if ($requests >= 60) {
            return false;
        }

        set_transient($transient_key, $requests + 1, 60);
        return true;
    }

    /**
     * Receive a file from Google Apps Script and upload it over SFTP.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function handle_upload($request) {
        $start_time = microtime(true);
        $client_ip  = $this->get_client_ip();

        OLISSGS_Plugin::log('Upload request received from ' . $client_ip, 'info');

        $body         = $request->get_json_params();
        $file_content = null;
        $filename     = null;

        if (is_array($body) && !empty($body['file_content']) && is_string($body['file_content'])) {
            $file_content = base64_decode($body['file_content']);
            $filename     = !empty($body['filename']) ? sanitize_file_name(wp_unslash($body['filename'])) : 'export_' . gmdate('Y-m-d_His') . '.csv';

            if ($file_content === false) {
                OLISSGS_Plugin::log('Failed to decode base64 file content', 'error');
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => 'Invalid base64 file content',
                ], 400);
            }
        }

        // REST upload is authenticated by API key in verify_api_key(), not a WP nonce.
        if (empty($file_content) && isset($_FILES['file'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $uploaded = map_deep(wp_unslash($_FILES['file']), 'sanitize_text_field'); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing
            $upload_error = isset($uploaded['error']) ? absint($uploaded['error']) : UPLOAD_ERR_NO_FILE;
            if ($upload_error !== UPLOAD_ERR_OK) {
                OLISSGS_Plugin::log('File upload error: ' . $upload_error, 'error');
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => 'File upload error: ' . $upload_error,
                ], 400);
            }

            $tmp_name = isset($_FILES['file']['tmp_name']) ? $_FILES['file']['tmp_name'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- path checked with is_uploaded_file().
            if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
                OLISSGS_Plugin::log('Invalid uploaded file', 'error');
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => 'Invalid uploaded file',
                ], 400);
            }

            // Local tmp upload, not a remote URL.
            $file_content = file_get_contents($tmp_name); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            $filename     = !empty($uploaded['name']) ? sanitize_file_name($uploaded['name']) : 'export_' . gmdate('Y-m-d_His') . '.csv';
        }

        if (empty($file_content)) {
            OLISSGS_Plugin::log('No file content provided', 'error');
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'No file content provided',
            ], 400);
        }

        $handler = OLISSGS_SFTP_Handler::get_instance();
        $result  = $handler->upload_file($file_content, $filename);

        $duration = round((microtime(true) - $start_time) * 1000);

        if ($result['success']) {
            OLISSGS_Plugin::log(
                'File uploaded successfully: ' . $result['filename'] . ' to ' . $result['remote_path'] . ' (' . $duration . 'ms, ' . $result['method'] . ')',
                'success'
            );

            return new WP_REST_Response([
                'success'     => true,
                'message'     => 'File uploaded successfully',
                'filename'    => $result['filename'],
                'remote_path' => $result['remote_path'],
                'timestamp'   => current_time('c'),
                'duration_ms' => $duration,
            ], 200);
        }

        OLISSGS_Plugin::log('Upload failed: ' . $result['error'], 'error');

        return new WP_REST_Response([
            'success' => false,
            'error'   => $result['error'],
        ], 500);
    }

    /**
     * Health/status for the configured SFTP target.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function handle_status($request) {
        unset($request);
        $handler = OLISSGS_SFTP_Handler::get_instance();
        $config  = $handler->get_config();

        return new WP_REST_Response([
            'status'          => 'active',
            'plugin_version'  => OLISSGS_VERSION,
            'sftp_configured' => !empty($config['host']) && !empty($config['username']),
            'timestamp'       => current_time('c'),
        ], 200);
    }
}
