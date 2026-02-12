<?php

class Marrison_Master_API {

    private $core;
    private $response_sent = false;

    public function __construct() {
        $this->core = new Marrison_Master_Core();
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('marrison-master/v1', '/sync', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_sync_request'],
            'permission_callback' => '__return_true'
        ]);
    }

    private function start_guard($context) {
        $this->response_sent = false;
        ob_start();
        set_error_handler(function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        register_shutdown_function(function () use ($context) {
            if ($this->response_sent) {
                return;
            }
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
                while (ob_get_level()) {
                    ob_end_clean();
                }
                http_response_code(200);
                header('Content-Type: application/json; charset=' . get_option('blog_charset'));
                echo wp_json_encode([
                    'success' => false,
                    'message' => $context . ': ' . $error['message']
                ]);
                exit;
            }
        });
    }

    private function finish_guard($log_prefix) {
        $output = ob_get_clean();
        restore_error_handler();
        $this->response_sent = true;

        if (!empty($output)) {
            error_log($log_prefix . substr($output, 0, 500));
        }
    }

    public function handle_sync_request($request) {
        $this->start_guard('Sync failed');
        $data = $request->get_json_params();

        if (empty($data) || !isset($data['site_url'])) {
            $this->finish_guard('[Marrison Sync Output] ');
            return rest_ensure_response([
                'success' => false,
                'message' => 'Missing site_url'
            ]);
        }

        try {
            $this->core->update_client_data($data);
        } catch (Throwable $e) {
            $this->finish_guard('[Marrison Sync Output] ');
            return rest_ensure_response([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        } catch (Exception $e) {
            $this->finish_guard('[Marrison Sync Output] ');
            return rest_ensure_response([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        // Return current repo configuration
        $config = [
            'plugins_repo' => get_option('marrison_private_plugins_repo'),
            'themes_repo' => get_option('marrison_private_themes_repo')
        ];

        $this->finish_guard('[Marrison Sync Output] ');
        return rest_ensure_response([
            'success' => true,
            'message' => 'Data synced successfully',
            'config' => $config
        ]);
    }
}

new Marrison_Master_API();
