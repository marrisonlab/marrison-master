<?php
/**
 * Plugin Name: Marrison Master
 * Plugin URI: https://github.com/marrisonlab/marrison-master
 * Description: Master controller for Marrison Update System.
 * Version: 1.0.1
 * Author: Angelo Marra
 * Author URI: https://marrisonlab.com
 * License: GPL v2 or later
 * Text Domain: marrison-master
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MARRISON_MASTER_PATH', plugin_dir_path(__FILE__));
define('MARRISON_MASTER_URL', plugin_dir_url(__FILE__));

// Include files
require_once MARRISON_MASTER_PATH . 'includes/core.php';
require_once MARRISON_MASTER_PATH . 'includes/admin.php';
require_once MARRISON_MASTER_PATH . 'includes/api.php';

// Initialize
function marrison_master_init() {
    $master = new Marrison_Master_Core();
    // $master->init(); // If needed
}
add_action('plugins_loaded', 'marrison_master_init');
