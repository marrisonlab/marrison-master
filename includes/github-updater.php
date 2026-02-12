<?php

class Marrison_GitHub_Updater {
    
    private $plugin_file;
    private $plugin_slug;
    private $github_repo;
    private $github_user;
    private $access_token;
    private $cache_key;
    private $cache_duration = 3600; // 1 hour cache
    
    public function __construct($plugin_file, $github_user, $github_repo, $access_token = '') {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->github_user = $github_user;
        $this->github_repo = $github_repo;
        $this->access_token = $access_token;
        $this->cache_key = 'marrison_github_update_' . $this->plugin_slug;
        
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_updates']);
        add_filter('plugins_api', [$this, 'get_plugin_info'], 10, 3);
        add_action('admin_init', [$this, 'force_check']);
        add_filter('plugin_action_links_' . $this->plugin_slug, [$this, 'add_force_check_button'], 10, 2);
        add_action('admin_notices', [$this, 'display_check_result']);
    }
    
    public function display_check_result() {
        if (isset($_GET['marrison-check-result'])) {
            $status = sanitize_text_field($_GET['marrison-check-result']);
            $message = get_transient('marrison_check_message_' . get_current_user_id());
            
            if ($status === 'success') {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
            }
            
            delete_transient('marrison_check_message_' . get_current_user_id());
        }
    }
    
    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $plugin_data = get_plugin_data($this->plugin_file);
        $current_version = $plugin_data['Version'];
        
        $remote_version = $this->get_remote_version();
        
        if ($remote_version) {
            // Normalize version by removing 'v' prefix if present
            $remote_ver_clean = ltrim($remote_version->tag_name, 'v');
            
            if (version_compare($current_version, $remote_ver_clean, '<')) {
                $plugin = new stdClass();
                $plugin->id = 1;
                $plugin->slug = $this->plugin_slug;
                $plugin->plugin = $this->plugin_slug;
                $plugin->new_version = $remote_ver_clean;
                $plugin->url = $plugin_data['PluginURI'];
                $plugin->package = $this->get_download_url($remote_version);
                
                $transient->response[$this->plugin_slug] = $plugin;
            }
        }
        
        return $transient;
    }
    
    public function get_plugin_info($false, $action, $args) {
        if ($action !== 'plugin_information' || !isset($args->slug) || $args->slug !== $this->plugin_slug) {
            return $false;
        }
        
        $remote_info = $this->get_remote_info();
        
        if (!$remote_info) {
            return $false;
        }
        
        $plugin = new stdClass();
        $plugin->name = $remote_info->name;
        $plugin->slug = $this->plugin_slug;
        $plugin->version = ltrim($remote_info->tag_name, 'v');
        $plugin->author = $remote_info->author;
        $plugin->homepage = $remote_info->homepage;
        $plugin->download_link = $this->get_download_url($remote_info);
        $plugin->sections = [
            'description' => $remote_info->description ?? '',
            'changelog' => $remote_info->body ?? '',
        ];
        
        return $plugin;
    }
    
    private function get_remote_version() {
        $cached = get_transient($this->cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        $url = "https://api.github.com/repos/{$this->github_user}/{$this->github_repo}/releases/latest";
        $response = $this->github_request($url);
        
        if ($response && !is_wp_error($response)) {
            set_transient($this->cache_key, $response, $this->cache_duration);
            return $response;
        }
        
        return null;
    }
    
    private function get_remote_info() {
        return $this->get_remote_version();
    }
    
    private function get_download_url($release) {
        if (isset($release->assets) && is_array($release->assets) && count($release->assets) > 0) {
            foreach ($release->assets as $asset) {
                if (strpos($asset->name, '.zip') !== false) {
                    return $asset->browser_download_url;
                }
            }
        }
        
        // Fallback to source code download
        return "https://github.com/{$this->github_user}/{$this->github_repo}/archive/{$release->tag_name}.zip";
    }
    
    private function github_request($url) {
        $args = [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
            ],
        ];
        
        if (!empty($this->access_token)) {
            $args['headers']['Authorization'] = 'token ' . $this->access_token;
        }
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            return $response; // Return WP_Error object
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('github_api_error', 'GitHub API Error: ' . $response_code . ' ' . wp_remote_retrieve_response_message($response));
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body);
    }
    
    public function force_check() {
        if (isset($_GET['force-check']) && $_GET['force-check'] === '1' && 
            isset($_GET['plugin']) && $_GET['plugin'] === $this->plugin_slug) {
            
            check_admin_referer('marrison-force-check-' . $this->plugin_slug);
            
            delete_transient($this->cache_key);
            delete_site_transient('update_plugins'); // Force WP to refresh list
            
            // Perform direct check
            $url = "https://api.github.com/repos/{$this->github_user}/{$this->github_repo}/releases/latest";
            $response = $this->github_request($url);
            
            if (is_wp_error($response)) {
                set_transient('marrison_check_message_' . get_current_user_id(), 'Errore durante il controllo: ' . $response->get_error_message(), 60);
                wp_redirect(admin_url('plugins.php?marrison-check-result=error'));
            } else {
                $remote_ver = ltrim($response->tag_name, 'v');
                $plugin_data = get_plugin_data($this->plugin_file);
                $current_ver = $plugin_data['Version'];
                
                if (version_compare($current_ver, $remote_ver, '<')) {
                    set_transient('marrison_check_message_' . get_current_user_id(), "Trovata nuova versione: {$remote_ver} (Attuale: {$current_ver}). Aggiorna ora!", 60);
                } else {
                    set_transient('marrison_check_message_' . get_current_user_id(), "Nessun aggiornamento trovato. Ultima versione online: {$remote_ver} (Installata: {$current_ver})", 60);
                }
                wp_redirect(admin_url('plugins.php?marrison-check-result=success'));
            }
            exit;
        }
    }
    
    public function add_force_check_button($actions, $plugin_file) {
        if ($plugin_file === $this->plugin_slug && current_user_can('update_plugins')) {
            $url = wp_nonce_url(
                admin_url('plugins.php?force-check=1&plugin=' . $this->plugin_slug),
                'marrison-force-check-' . $this->plugin_slug
            );
            
            $actions['force-check'] = sprintf(
                '<a href="%s" aria-label="%s">%s</a>',
                $url,
                esc_attr__('Forza controllo aggiornamenti da GitHub', 'marrison-master'),
                __('Forza Controllo', 'marrison-master')
            );
        }
        
        return $actions;
    }
}

// Initialize GitHub updater for Marrison Master
add_action('plugins_loaded', function() {
    if (file_exists(plugin_dir_path(__FILE__) . '../marrison-master.php')) {
        new Marrison_GitHub_Updater(
            plugin_dir_path(__FILE__) . '../marrison-master.php',
            'marrisonlab',
            'marrison-master'
        );
    }
});