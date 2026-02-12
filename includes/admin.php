<?php

class Marrison_Master_Admin {

    private $core;

    public function __construct() {
        $this->core = new Marrison_Master_Core();
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_marrison_client_action', [$this, 'handle_ajax_action']);
        add_action('wp_ajax_marrison_master_clear_cache', [$this, 'handle_clear_cache']);
    }

    public function add_menu() {
        add_menu_page(
            'Marrison Master',
            'Marrison Master',
            'manage_options',
            'marrison-master',
            [$this, 'render_dashboard'],
            'dashicons-networking'
        );
        add_submenu_page(
            'marrison-master',
            'Impostazioni',
            'Impostazioni',
            'manage_options',
            'marrison-master-settings',
            [$this, 'render_settings']
        );
    }

    public function register_settings() {
        register_setting('marrison_master_options', 'marrison_private_plugins_repo');
        register_setting('marrison_master_options', 'marrison_private_themes_repo');
    }

    public function render_settings() {
        ?>
        <div class="wrap">
            <h1>Impostazioni Master</h1>
            <form method="post" action="options.php">
                <?php settings_fields('marrison_master_options'); ?>
                <?php do_settings_sections('marrison_master_options'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">URL Repository Plugin Privato</th>
                        <td><input type="url" name="marrison_private_plugins_repo" value="<?php echo esc_attr(get_option('marrison_private_plugins_repo')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">URL Repository Temi Privato</th>
                        <td><input type="url" name="marrison_private_themes_repo" value="<?php echo esc_attr(get_option('marrison_private_themes_repo')); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            
            <hr>
            
            <h2>Strumenti di Manutenzione</h2>
            <p>Usa questi strumenti se gli aggiornamenti non vengono rilevati correttamente.</p>
            <table class="form-table">
                <tr>
                    <th scope="row">Forza Refresh Repository</th>
                    <td>
                        <button id="marrison-force-refresh-repo" class="button button-secondary">
                            Forza Refresh Repository su Tutti i Client
                        </button>
                        <p class="description">
                            Pulisce la cache delle repository su tutti i client e forza il ricaricamento.
                            Utile quando i plugin non vengono rilevati come aggiornabili.
                        </p>
                    </td>
                </tr>
            </table>
            
            <div id="marrison-settings-notices"></div>
            
            <script>
            jQuery(document).ready(function($) {
                $('#marrison-force-refresh-repo').on('click', function(e) {
                    e.preventDefault();
                    
                    if (!confirm('Questa operazione pulirà la cache delle repository su TUTTI i client connessi e forzerà il ricaricamento. Continuare?')) {
                        return;
                    }
                    
                    var btn = $(this);
                    var originalText = btn.text();
                    btn.prop('disabled', true).text('Operazione in corso...');
                    
                    $.post(ajaxurl, {
                        action: 'marrison_force_refresh_repo',
                        nonce: '<?php echo wp_create_nonce('marrison_master_nonce'); ?>'
                    }, function(response) {
                        if (response.success) {
                            $('#marrison-settings-notices').html(
                                '<div class="notice notice-success is-dismissible"><p>' + 
                                response.data.message + 
                                '</p></div>'
                            );
                        } else {
                            $('#marrison-settings-notices').html(
                                '<div class="notice notice-error is-dismissible"><p>' + 
                                (response.data ? response.data.message : 'Errore sconosciuto') + 
                                '</p></div>'
                            );
                        }
                        btn.prop('disabled', false).text(originalText);
                    }).fail(function() {
                        $('#marrison-settings-notices').html(
                            '<div class="notice notice-error is-dismissible"><p>Errore di rete</p></div>'
                        );
                        btn.prop('disabled', false).text(originalText);
                    });
                });
            });
            </script>
        </div>
        <?php
    }

    public function handle_clear_cache() {
        check_ajax_referer('marrison_master_nonce', 'nonce');
        
        $p_repo = get_option('marrison_private_plugins_repo');
        if ($p_repo) delete_transient('marrison_repo_' . md5($p_repo));
        
        $t_repo = get_option('marrison_private_themes_repo');
        if ($t_repo) delete_transient('marrison_repo_' . md5($t_repo));

        delete_site_transient('update_plugins');
        delete_site_transient('update_themes');

        // Reset Client Data (Wipe plugin/theme info to force full re-sync)
        $clients = get_option('marrison_connected_clients', []);
        foreach ($clients as &$client) {
             // Keep identity, wipe data
             $client = [
                 'site_url' => $client['site_url'],
                 'site_name' => $client['site_name'] ?? 'Unknown',
                 'status' => 'active',
                 'last_sync' => '-' // Reset sync time
             ];
        }
        update_option('marrison_connected_clients', $clients);

        $html = $this->render_clients_table_body($clients);

        wp_send_json_success([
            'message' => 'Cache Master pulita e dati resettati. Avvia una Sync Massiva per ricaricare tutto.',
            'html' => $html
        ]);
    }

    /**
     * Handle force refresh repository cache on all clients
     */
    public function handle_force_refresh_repo() {
        check_ajax_referer('marrison_master_nonce', 'nonce');
        
        $clients = $this->core->get_clients();
        $success_count = 0;
        $error_count = 0;
        
        foreach ($clients as $url => $data) {
            $response = wp_remote_post($url . '/wp-json/marrison-agent/v1/clear-repo-cache', [
                'timeout' => 15,
                'sslverify' => false
            ]);
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $success_count++;
            } else {
                $error_count++;
            }
        }
        
        // Clear master cache too
        delete_site_transient('update_plugins');
        delete_site_transient('update_themes');
        
        $message = sprintf(
            'Cache repository aggiornata. Successo: %d, Errori: %d. Ora esegui una Sync Massiva per aggiornare i dati.',
            $success_count,
            $error_count
        );
        
        wp_send_json_success(['message' => $message]);
    }

    public function handle_ajax_action() {
        check_ajax_referer('marrison_master_nonce', 'nonce');
        
        $client_url = isset($_POST['client_url']) ? sanitize_text_field($_POST['client_url']) : '';
        $action = isset($_POST['cmd']) ? sanitize_text_field($_POST['cmd']) : '';
        $backup_file = isset($_POST['backup_file']) ? sanitize_text_field($_POST['backup_file']) : '';
        $is_bulk = isset($_POST['bulk_mode']) && $_POST['bulk_mode'] === 'true';
        
        $msg = '';
        $success = true;

        if ($action === 'sync') {
            $res = $this->core->trigger_remote_sync($client_url);
            if (is_wp_error($res)) {
                $success = false;
                $msg = 'Errore Sync: ' . $res->get_error_message();
            } else {
                $msg = 'Sync Avviato con successo';
            }
            if ($is_bulk) {
                if ($success) wp_send_json_success(['message' => $msg]);
                else wp_send_json_error(['message' => $msg]);
            }
        } elseif ($action === 'update') {
            $res = $this->core->trigger_remote_update($client_url);
            if (is_wp_error($res)) {
                $success = false;
                $msg = 'Errore Update: ' . $res->get_error_message();
            } else {
                $msg = 'Aggiornamento completato (plugin, temi e traduzioni)';
            }
        } elseif ($action === 'restore') {
            if (empty($backup_file)) {
                $success = false;
                $msg = 'File backup mancante';
            } else {
                $res = $this->core->trigger_restore_backup($client_url, $backup_file);
                if (is_wp_error($res)) {
                    $success = false;
                    $msg = 'Errore Ripristino: ' . $res->get_error_message();
                } else {
                    $msg = 'Ripristino avviato con successo';
                }
            }
        } elseif ($action === 'delete') {
            $this->core->delete_client($client_url);
            $msg = 'Client rimosso';
        } elseif ($action === 'noop') {
            $msg = 'Tabella aggiornata';
        }

        $clients = $this->core->get_clients();
        $html = $this->render_clients_table_body($clients);

        if ($success) {
            wp_send_json_success(['html' => $html, 'message' => $msg]);
        } else {
            wp_send_json_error(['html' => $html, 'message' => $msg]);
        }
    }

    private function render_clients_table_body($clients) {
        ob_start();
        if (empty($clients)): ?>
            <tr><td colspan="7">Nessun client connesso.</td></tr>
        <?php else: ?>
            <?php foreach ($clients as $url => $data): ?>
                <?php
                    $p_update_count = count($data['plugins_need_update'] ?? []);
                    $t_update_count = count($data['themes_need_update'] ?? []);
                    $trans_update = !empty($data['translations_need_update']);
                    $inactive_count = count($data['plugins_inactive'] ?? []);
                    $status = $data['status'] ?? 'active';
                    
                    // LED Logic
                    $led_color = '#46b450'; // Green
                    $led_title = 'Tutto aggiornato';
                    
                    if ($status === 'unreachable') {
                        $led_color = '#000000'; // Black
                        $led_title = 'Agente non raggiungibile';
                    } elseif ($p_update_count > 0 || $t_update_count > 0 || $trans_update) {
                        $led_color = '#dc3232'; // Red
                        $led_title = 'Aggiornamenti disponibili';
                    } elseif ($inactive_count > 0) {
                        $led_color = '#f0c330'; // Yellow
                        $led_title = 'Ci sono ' . $inactive_count . ' plugin disattivati';
                    }
                    
                    $row_key = md5($url);
                    $is_green = ($led_color === '#46b450');
                    $is_yellow = ($led_color === '#f0c330');
                    $is_black = ($led_color === '#000000');
                ?>
                <tr class="mmu-main-row" data-key="<?php echo esc_attr($row_key); ?>" style="cursor: pointer;">
                    <td style="text-align: center;">
                        <span class="mmu-led" style="color: <?php echo $led_color; ?>;" title="<?php echo esc_attr($led_title); ?>"></span>
                    </td>
                    <td><strong><?php echo esc_html($data['site_name']); ?></strong></td>
                    <td><a href="<?php echo esc_url($url); ?>" target="_blank"><?php echo esc_html($url); ?></a></td>
                    <td>
                        <?php echo $p_update_count > 0 ? '<span style="color:#dc3232">Aggiornamenti: ' . $p_update_count . '</span>' : '<span style="color:#46b450">Aggiornato</span>'; ?>
                    </td>
                    <td>
                        <?php echo $t_update_count > 0 ? '<span style="color:#dc3232">Aggiornamenti: ' . $t_update_count . '</span>' : '<span style="color:#46b450">Aggiornato</span>'; ?>
                    </td>
                    <td><?php echo esc_html($data['last_sync'] ?? '-'); ?></td>
                    <td>
                        <form style="display:inline;" onsubmit="return false;">
                            <input type="hidden" name="client_url" value="<?php echo esc_attr($url); ?>">
                            <button type="button" value="sync" class="button button-secondary marrison-action-btn">Sync</button>
                            <button type="button" value="update" class="button button-primary marrison-action-btn" <?php echo ($is_green || $is_yellow || $is_black) ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : ''; ?>>Aggiorna</button>
                            <button type="button" value="delete" class="button button-link-delete marrison-action-btn" style="color: #dc3232;">Cancella</button>
                        </form>
                    </td>
                </tr>
                
                <!-- Details Row -->
                <tr class="mmu-details-row" id="details-<?php echo esc_attr($row_key); ?>" style="display:none;">
                    <td colspan="7">
                        <div class="flex-container" style="display: flex; gap: 20px; margin-bottom: 25px;">
                            
                            <!-- Themes -->
                            <div class="mmu-details-section" style="flex: 1;">
                                <h4>Temi Installati</h4>
                                <?php 
                                $themes = $data['themes_installed'] ?? [];
                                $themes_updates = $data['themes_need_update'] ?? [];
                                $themes_update_slugs = array_column($themes_updates, 'slug');
                                ?>
                                <?php if (!empty($themes)): ?>
                                    <ul style="margin: 0; padding: 0; list-style: none;">
                                        <?php foreach ($themes as $theme): ?>
                                            <li style="margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                                <strong><?php echo esc_html($theme['name']); ?></strong>
                                                <span style="opacity: 0.8;">v. <?php echo esc_html($theme['version']); ?></span>
                                                <?php 
                                                $update_key = array_search($theme['slug'], $themes_update_slugs);
                                                if ($update_key !== false) {
                                                    $new_ver = $themes_updates[$update_key]['new_version'];
                                                    echo '<div style="color: #ff8080; font-weight: bold; font-size: 0.9em;"><span class="dashicons dashicons-warning"></span> Aggiornamento: v. ' . esc_html($new_ver) . '</div>';
                                                }
                                                ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p>Nessun tema rilevato.</p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Translations -->
                            <div class="mmu-details-section" style="flex: 1;">
                                <h4>Traduzioni</h4>
                                <?php if ($trans_update): ?>
                                    <div style="color: #ff8080; font-weight: bold;">
                                        <span class="dashicons dashicons-translation"></span> Traduzioni da aggiornare
                                    </div>
                                <?php else: ?>
                                    <div style="color: #46b450;">
                                        <span class="dashicons dashicons-yes"></span> Traduzioni aggiornate
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Backups -->
                            <div class="mmu-details-section" style="flex: 1;">
                                <h4>Backup Disponibili</h4>
                                <?php 
                                $backups = $data['backups'] ?? []; 
                                ?>
                                <?php if (!empty($backups)): ?>
                                    <ul style="margin: 0; padding: 0; list-style: none;">
                                        <?php foreach ($backups as $b): ?>
                                            <li style="margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center;">
                                                <div>
                                                    <strong style="display:block;"><?php echo esc_html($b['slug']); ?></strong>
                                                    <small style="opacity: 0.7;"><?php echo esc_html($b['type']); ?> - <?php echo esc_html($b['date']); ?></small>
                                                </div>
                                                <form style="display:inline;" onsubmit="return false;">
                                                    <input type="hidden" name="client_url" value="<?php echo esc_attr($url); ?>">
                                                    <input type="hidden" name="backup_file" value="<?php echo esc_attr($b['filename']); ?>">
                                                    <button type="button" value="restore" class="button button-small marrison-action-btn" onclick="if(confirm('Ripristinare questo backup?')) performClientAction($(this));" style="background: #fff; color: #2271b1; border: none;">Ripristina</button>
                                                </form>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p style="opacity: 0.7;">Nessun backup trovato.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Plugins -->
                        <?php
                            $all_active = $data['plugins_active'] ?? [];
                            $all_inactive = $data['plugins_inactive'] ?? [];
                            $all_updates = $data['plugins_need_update'] ?? [];
                            
                            $update_paths = array_column($all_updates, 'path');
                            
                            $display_inactive = [];
                            foreach ($all_inactive as $p) {
                                if (!in_array($p['path'], $update_paths)) {
                                    $display_inactive[] = $p;
                                }
                            }
                            
                            $display_active_updated = [];
                            foreach ($all_active as $p) {
                                if (!in_array($p['path'], $update_paths)) {
                                    $display_active_updated[] = $p;
                                }
                            }
                        ?>
                        
                        <div class="mmu-details-section">
                            <h4>Plugin</h4>
                            
                            <!-- Updates -->
                            <?php if (!empty($all_updates)): ?>
                                <h5 style="color: #ff8080;"><span class="dashicons dashicons-warning"></span> Plugin da aggiornare</h5>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; margin-bottom: 20px;">
                                    <?php foreach ($all_updates as $p): ?>
                                        <div style="padding: 12px; background: rgba(255,128,128,0.1); border-radius: 6px; border-left: 4px solid #ff8080;">
                                            <strong style="color: #fff; display: block;"><?php echo esc_html($p['name']); ?></strong>
                                            <span style="color: #ccc; font-size: 0.85em;">v. <?php echo esc_html($p['version']); ?> → v. <?php echo esc_html($p['new_version']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Inactive -->
                            <?php if (!empty($display_inactive)): ?>
                                <h5 style="color: #f0c330;"><span class="dashicons dashicons-admin-plugins"></span> Plugin disattivati</h5>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; margin-bottom: 20px;">
                                    <?php foreach ($display_inactive as $p): ?>
                                        <div style="padding: 12px; background: rgba(240,195,48,0.1); border-radius: 6px; border-left: 4px solid #f0c330;">
                                            <strong style="color: #ccc; display: block;"><?php echo esc_html($p['name']); ?></strong>
                                            <span style="color: #999; font-size: 0.85em;">v. <?php echo esc_html($p['version']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Active & Updated -->
                            <?php if (!empty($display_active_updated)): ?>
                                <h5 style="color: #46b450;"><span class="dashicons dashicons-yes"></span> Plugin attivi e aggiornati</h5>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px;">
                                    <?php foreach ($display_active_updated as $p): ?>
                                        <div style="padding: 12px; background: rgba(70,180,80,0.1); border-radius: 6px; border-left: 4px solid #46b450;">
                                            <strong style="color: #fff; display: block;"><?php echo esc_html($p['name']); ?></strong>
                                            <span style="color: #ccc; font-size: 0.85em;">v. <?php echo esc_html($p['version']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif;
        return ob_get_clean();
    }

    public function render_dashboard() {
        $clients = $this->core->get_clients();
        ?>
        <style>
            /* Modern Color Scheme */
            :root {
                --primary-color: #2271b1;
                --primary-hover: #135e96;
                --success-color: #46b450;
                --warning-color: #f0c330;
                --error-color: #dc3232;
                --text-primary: #1d2327;
                --text-secondary: #646970;
                --bg-light: #f6f7f7;
                --bg-lighter: #fafafa;
                --border-color: #c3c4c7;
                --shadow-light: 0 1px 3px rgba(0,0,0,0.1);
                --shadow-medium: 0 2px 6px rgba(0,0,0,0.15);
            }
            
            .wp-list-table.widefat {
                border: 1px solid var(--border-color);
                border-radius: 8px;
                overflow: hidden;
                box-shadow: var(--shadow-light);
            }
            
            .wp-list-table thead th {
                background: linear-gradient(180deg, #f6f7f7 0%, #f0f0f1 100%);
                border-bottom: 2px solid var(--border-color);
                font-weight: 600;
                color: var(--text-primary);
                padding: 12px 10px;
            }
            
            .mmu-main-row {
                transition: all 0.2s ease;
                border-left: 3px solid transparent;
            }
            
            .mmu-main-row:hover { 
                background-color: var(--bg-lighter) !important;
                transform: translateX(2px);
                border-left-color: var(--primary-color);
            }
            
            .mmu-main-row td {
                padding: 16px 10px;
                vertical-align: middle;
                border-bottom: 1px solid var(--border-color);
            }
            
            .mmu-details-row td { 
                background: linear-gradient(135deg, #2c3338 0%, #1d2327 100%);
                padding: 25px;
                border-bottom: 25px solid var(--bg-lighter) !important;
                box-shadow: inset 0 4px 12px rgba(0,0,0,0.2);
            }

            .mmu-details-section {
                background: rgba(255,255,255,0.08);
                padding: 20px;
                border-radius: 8px;
                border: 1px solid rgba(255,255,255,0.1);
                backdrop-filter: blur(10px);
                color: #fff !important;
            }

            .mmu-details-section h4 {
                color: #fff;
                font-weight: 600;
                margin: 0 0 15px 0;
                padding-bottom: 10px;
                border-bottom: 2px solid rgba(255,255,255,0.2);
            }

            .mmu-details-section ul li, 
            .mmu-details-section ul li strong,
            .mmu-details-section ul li span {
                color: #f0f0f1 !important;
            }
            
            .mmu-details-section h5 {
                margin: 0 0 15px 0;
                font-weight: 600;
            }

            .mmu-led {
                display: inline-block;
                width: 16px;
                height: 16px;
                border-radius: 50%;
                box-shadow: 0 0 8px currentColor;
                transition: all 0.3s ease;
                position: relative;
            }
            
            .mmu-led::after {
                content: '';
                position: absolute;
                top: 3px;
                left: 3px;
                width: 10px;
                height: 10px;
                border-radius: 50%;
                background: currentColor;
                opacity: 0.8;
            }
            
            .button.loading {
                opacity: 0.7;
                cursor: wait;
            }
        </style>

        <div class="wrap">
            <h1>Client Connessi</h1>
            <div id="marrison-notices"></div>
            
            <div class="tablenav top" style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                <div class="actions">
                    <button id="marrison-bulk-sync" class="button button-primary">Sync Massiva</button>
                    <button id="marrison-clear-cache" class="button button-secondary">Pulisci Cache Master</button>
                </div>
                <div id="marrison-progress-wrap" style="display:none; flex: 1; max-width: 400px; border: 1px solid #c3c4c7; height: 24px; background: #fff; position: relative; border-radius: 4px; overflow: hidden;">
                     <div id="marrison-progress-bar" style="width: 0%; height: 100%; background: #2271b1; transition: width 0.3s ease;"></div>
                     <div id="marrison-progress-text" style="position: absolute; top: 0; left: 0; width: 100%; text-align: center; line-height: 24px; font-size: 12px; font-weight: 600; color: #1d2327; text-shadow: 0 0 2px #fff;">0%</div>
                </div>
                <div id="marrison-bulk-status" style="font-weight: 600;"></div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">Stato</th>
                        <th>Sito</th>
                        <th>URL</th>
                        <th>Stato Plugin</th>
                        <th>Stato Temi</th>
                        <th>Ultimo Sync</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody id="marrison-clients-body">
                    <?php echo $this->render_clients_table_body($clients); ?>
                </tbody>
            </table>
        </div>
        
        <script>
        var marrison_vars = { 
            nonce: '<?php echo wp_create_nonce('marrison_master_nonce'); ?>' 
        };
        
        jQuery(document).ready(function($) {
            
            // --- Helper: Update Progress ---
            function updateProgress(current, total, message) {
                var percent = 0;
                if (total > 0) percent = Math.round((current / total) * 100);
                
                $('#marrison-progress-wrap').show();
                $('#marrison-progress-bar').css('width', percent + '%');
                $('#marrison-progress-text').text(percent + '%');
                if (message) $('#marrison-bulk-status').text(message);
                
                if (percent >= 100) {
                    setTimeout(function() {
                        $('#marrison-progress-wrap').fadeOut();
                        $('#marrison-bulk-status').text('');
                        $('#marrison-progress-bar').css('width', '0%');
                    }, 2000);
                }
            }

            // --- Helper: Perform Single Action ---
            function performClientAction(btn) {
                var form = btn.closest('form');
                var clientUrl = form.find('input[name="client_url"]').val();
                var cmd = btn.val();
                var backupFile = form.find('input[name="backup_file"]').val() || '';

                if (cmd === 'delete' && !confirm('Sei sicuro di voler cancellare questo client?')) {
                    return;
                }

                // Disable all buttons in the row
                var row = btn.closest('tr');
                var actionButtons = row.find('.marrison-action-btn');
                actionButtons.prop('disabled', true);
                
                var originalButtonText = btn.text();
                if(cmd === 'sync' || cmd === 'update' || cmd === 'restore') {
                    btn.text('In corso...');
                    var mainRow = row.hasClass('mmu-main-row') ? row : row.prev();
                    var clientName = mainRow.find('td:nth-child(2)').text();
                    updateProgress(0, 1, 'Avvio ' + cmd + ' su ' + clientName);
                }

                $.post(ajaxurl, {
                    action: 'marrison_client_action',
                    nonce: marrison_vars.nonce,
                    client_url: clientUrl,
                    cmd: cmd,
                    backup_file: backupFile
                }, function(response) {
                    if (response.success) {
                        // Update progress bar to complete
                        if(cmd === 'sync' || cmd === 'update' || cmd === 'restore') {
                            var actionLabel = cmd === 'sync' ? 'Sincronizzazione' : 
                                             cmd === 'update' ? 'Aggiornamento' : 
                                             cmd === 'restore' ? 'Ripristino backup' : 'Operazione';
                            updateProgress(1, 1, actionLabel + ' completata!');
                        }
                        
                        $('#marrison-clients-body').html(response.data.html);
                        $('#marrison-notices').html('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                        
                        var isRestore = cmd === 'restore';
                        if (isRestore) {
                            $('#marrison-notices').append('<div class="notice notice-info is-dismissible"><p>Ripristino completato. Avvio sync automatico...</p></div>');
                            
                            // Find the sync button for the client in the new HTML
                            var newSyncBtn = $('#marrison-clients-body')
                                .find('.mmu-main-row input[name="client_url"][value="' + clientUrl.replace(/"/g, '\\"') + '"]')
                                .closest('form')
                                .find('.marrison-action-btn[value="sync"]');

                            if (newSyncBtn.length) {
                                setTimeout(function() {
                                    // The events will be bound by the bindEvents() call below.
                                    // We just need to call the action on the button element.
                                    performClientAction(newSyncBtn);
                                }, 200);
                            }
                        }
                    } else {
                        // Update progress bar to show error
                        if(cmd === 'sync' || cmd === 'update' || cmd === 'restore') {
                            var actionLabel = cmd === 'sync' ? 'sincronizzazione' : 
                                             cmd === 'update' ? 'aggiornamento' : 
                                             cmd === 'restore' ? 'ripristino backup' : 'operazione';
                            updateProgress(0, 1, 'Errore durante ' + actionLabel);
                        }
                        $('#marrison-notices').html('<div class="notice notice-error is-dismissible"><p>' + (response.data ? response.data.message : 'Errore') + '</p></div>');
                    }
                    bindEvents(); // Re-bind events to new content
                }).fail(function() {
                    // Update progress bar to show network error
                    if(cmd === 'sync' || cmd === 'update' || cmd === 'restore') {
                        updateProgress(0, 1, 'Errore di rete');
                    }
                    $('#marrison-notices').html('<div class="notice notice-error is-dismissible"><p>Errore di rete.</p></div>');
                }).always(function() {
                    // Re-enable buttons is handled by bindEvents and re-rendering
                });
            }

            function bindEvents() {
                // Remove existing handlers
                $('.mmu-main-row').off('click').on('click', function(e) {
                    if ($(e.target).closest('a, button, input').length) return;
                    if ($(e.target).closest('td').is(':last-child')) return;
                    var key = $(this).data('key');
                    $('#details-' + key).toggle();
                });
                
                $('.marrison-action-btn').off('click').on('click', function(e) {
                    e.preventDefault();
                    if (window.isBulkRunning) return; // Prevent clicks during bulk
                    performClientAction($(this));
                });
            }
            
            // --- Bulk Sync Logic ---
            $('#marrison-bulk-sync').on('click', function(e) {
                e.preventDefault();
                  if (window.isBulkRunning) return;
 
                  var clients = [];
                  $('#marrison-clients-body .marrison-action-btn[value="sync"]').each(function() {
                      var clientUrl = $(this).closest('form').find('input[name="client_url"]').val();
                      if (clientUrl && clients.indexOf(clientUrl) === -1) {
                          clients.push(clientUrl);
                      }
                  });
 
                  if (clients.length === 0) {
                     alert('Nessun client disponibile per la sync.');
                      return;
                  }
 
                 if (!confirm('Avviare la sync su tutti i ' + clients.length + ' client? L\'operazione potrebbe richiedere tempo.')) return;

                window.isBulkRunning = true;
                var bulkSyncBtn = $(this);
                var originalText = bulkSyncBtn.text();
                bulkSyncBtn.prop('disabled', true);
                $('.marrison-action-btn').prop('disabled', true);
                $('#marrison-notices').empty();

                var total = clients.length;
                var current = 0;
                var successCount = 0;
                var errorCount = 0;
                
                updateProgress(0, total, 'Avvio Sync massiva...');
                
                function syncNext() {
                    if (current >= total) {
                        // All syncs are done, now refresh the table with a noop action
                        $.post(ajaxurl, {
                            action: 'marrison_client_action',
                            cmd: 'noop',
                            nonce: marrison_vars.nonce
                        }, function(response) {
                            if (response.success && response.data.html) {
                                $('#marrison-clients-body').html(response.data.html);
                                bindEvents();
                            }
                            $('#marrison-notices').html('<div class="notice notice-success is-dismissible"><p>Sync massiva completata. Successi: ' + successCount + ', Errori: ' + errorCount + '</p></div>');
                            updateProgress(total, total, 'Completato!');
                        }).fail(function() {
                             $('#marrison-notices').html('<div class="notice notice-error is-dismissible"><p>Errore durante l\'aggiornamento finale della tabella.</p></div>');
                        }).always(function() {
                            window.isBulkRunning = false;
                            bulkSyncBtn.prop('disabled', false).text(originalText);
                        });
                        return;
                    }

                    var clientUrl = clients[current];
                    updateProgress(current, total, 'Sync in corso: ' + (current + 1) + '/' + total);
                    
                    $.post(ajaxurl, {
                        action: 'marrison_client_action',
                        cmd: 'sync',
                        client_url: clientUrl,
                        bulk_mode: 'true',
                        nonce: marrison_vars.nonce
                    }, function(response) {
                        if (response.success) {
                            successCount++;
                        } else {
                            errorCount++;
                        }
                    }).fail(function() {
                        errorCount++;
                    }).always(function() {
                        current++;
                        syncNext();
                    });
                }
                
                syncNext(0);
            });
            
            // Bulk update removed as per request
            
            // --- Clear Cache Logic ---
            $('#marrison-clear-cache').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                btn.prop('disabled', true).text('Pulizia...');
                
                $.post(ajaxurl, {
                    action: 'marrison_master_clear_cache',
                    nonce: marrison_vars.nonce
                }, function(response) {
                    if (response.success) {
                        $('#marrison-notices').html('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                        if (response.data.html) {
                            $('#marrison-clients-body').html(response.data.html);
                            bindEvents();
                        }
                    } else {
                        $('#marrison-notices').html('<div class="notice notice-error is-dismissible"><p>Errore pulizia cache</p></div>');
                    }
                    btn.prop('disabled', false).text('Pulisci Cache Master');
                }).fail(function() {
                    $('#marrison-notices').html('<div class="notice notice-error is-dismissible"><p>Errore di rete</p></div>');
                    btn.prop('disabled', false).text('Pulisci Cache Master');
                });
            });
            
            bindEvents();
        });
        </script>
        <?php
    }
}

new Marrison_Master_Admin();
