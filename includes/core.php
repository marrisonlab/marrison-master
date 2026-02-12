<?php

class Marrison_Master_Core {

    private $option_name = 'marrison_connected_clients';

    public function get_clients() {
        return get_option($this->option_name, []);
    }

    public function update_client_data($data) {
        $clients = $this->get_clients();
        $site_url = $data['site_url'];
        // Use site_url as key
        $clients[$site_url] = array_merge($data, [
            'last_sync' => current_time('mysql'),
            'status' => 'active' // Explicitly mark as active on successful update
        ]);
        update_option($this->option_name, $clients);
    }

    public function mark_client_unreachable($site_url) {
        $clients = $this->get_clients();
        
        // Normalize input to handle trailing slash mismatches
        $url_norm = untrailingslashit($site_url);
        $found_key = false;
        
        // Try exact match first
        if (isset($clients[$site_url])) {
            $found_key = $site_url;
        } else {
            // Try normalized match
            foreach (array_keys($clients) as $key) {
                if (untrailingslashit($key) === $url_norm) {
                    $found_key = $key;
                    break;
                }
            }
        }

        if ($found_key) {
            $clients[$found_key]['status'] = 'unreachable';
            // Do NOT update last_sync so we know it's stale
            update_option($this->option_name, $clients);
        }
    }

    public function delete_client($site_url) {
        $clients = $this->get_clients();
        if (isset($clients[$site_url])) {
            unset($clients[$site_url]);
            update_option($this->option_name, $clients);
            return true;
        }
        return false;
    }

    /**
     * Trigger remote update with cache clearing and translation support
     */
    public function trigger_remote_update($client_url) {
        $response = wp_remote_post($client_url . '/wp-json/marrison-agent/v1/update', [
            'body' => [
                'clear_cache' => true,
                'update_translations' => true
            ],
            'timeout' => 300,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $this->trigger_remote_sync($client_url, false);
            return new WP_Error('http_error', "Remote server returned code $code");
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (is_array($body) && isset($body['success']) && $body['success'] === false) {
            $this->trigger_remote_sync($client_url, false);
            return new WP_Error('update_failed', $body['message'] ?? 'Update failed');
        }
        
        // Sync after update to refresh data
        $this->trigger_remote_sync($client_url, false);
        return $body;
    }

    /**
     * Trigger remote sync with optimized timeout
     */
    public function trigger_remote_sync($client_url, $mark_unreachable = true) {
        // Request fresh data from Agent
        // Append cache buster to avoid cached JSON responses (e.g. Varnish/Cloudflare)
        $endpoint = trailingslashit($client_url) . 'wp-json/marrison-agent/v1/status';
        $endpoint = add_query_arg('marrison_ts', time(), $endpoint);

        $response = wp_remote_get($endpoint, [
            'timeout' => 10, // Reduced from 15 to 10 seconds for faster scanning
            'sslverify' => false
        ]);

        if (is_wp_error($response)) {
            if ($mark_unreachable) {
                $this->mark_client_unreachable($client_url);
            }
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            if ($mark_unreachable) {
                $this->mark_client_unreachable($client_url);
            }
            return new WP_Error('http_error', "Remote server returned code $code");
        }

        // Verify Content-Type is JSON (handles redirects to 404/Home which return HTML 200 OK)
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        // Use stripos for case-insensitive check
        if (stripos($content_type, 'application/json') === false) {
            if ($mark_unreachable) {
                $this->mark_client_unreachable($client_url);
            }
            return new WP_Error('invalid_content_type', 'Response was not JSON (Possible redirect or error page)');
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($data && isset($data['site_url'])) {
            $this->update_client_data($data);
            return true;
        }
        
        if ($mark_unreachable) {
            $this->mark_client_unreachable($client_url);
        }
        return new WP_Error('invalid_response', 'Invalid response from agent');
    }

    public function trigger_restore_backup($client_url, $backup_filename) {
        $response = wp_remote_post($client_url . '/wp-json/marrison-agent/v1/backups/restore', [
            'body' => ['filename' => $backup_filename],
            'timeout' => 60,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $this->trigger_remote_sync($client_url, false);
            return new WP_Error('http_error', "Remote server returned code $code");
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['success']) && $body['success']) {
            $this->trigger_remote_sync($client_url, false);
            return true;
        }

        return new WP_Error('restore_failed', $body['message'] ?? 'Restore failed unknown error');
    }

    /**
     * Bulk sync all clients in parallel for better performance
     */
    public function bulk_sync_parallel($client_urls = null) {
        if ($client_urls === null) {
            $clients = $this->get_clients();
            $client_urls = array_keys($clients);
        }

        if (empty($client_urls)) {
            return ['success' => true, 'results' => []];
        }

        $results = [];
        $requests = [];

        // Build all requests
        foreach ($client_urls as $url) {
            $endpoint = trailingslashit($url) . 'wp-json/marrison-agent/v1/status';
            $endpoint = add_query_arg('marrison_ts', time(), $endpoint);
            
            $requests[$url] = [
                'url' => $endpoint,
                'type' => 'GET',
                'timeout' => 15,
                'sslverify' => false
            ];
        }

        // Execute requests in parallel using WordPress HTTP API
        foreach ($requests as $url => $request) {
            $response = wp_remote_get($request['url'], [
                'timeout' => $request['timeout'],
                'sslverify' => $request['sslverify']
            ]);

            if (is_wp_error($response)) {
                $this->mark_client_unreachable($url);
                $results[$url] = [
                    'success' => false,
                    'error' => $response->get_error_message()
                ];
                continue;
            }

            $code = wp_remote_retrieve_response_code($response);
            $content_type = wp_remote_retrieve_header($response, 'content-type');

            if ($code !== 200 || stripos($content_type, 'application/json') === false) {
                $this->mark_client_unreachable($url);
                $results[$url] = [
                    'success' => false,
                    'error' => 'Invalid response'
                ];
                continue;
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            if ($data && isset($data['site_url'])) {
                $this->update_client_data($data);
                $results[$url] = ['success' => true];
            } else {
                $this->mark_client_unreachable($url);
                $results[$url] = [
                    'success' => false,
                    'error' => 'Invalid data format'
                ];
            }
        }

        return [
            'success' => true,
            'results' => $results
        ];
    }
}
