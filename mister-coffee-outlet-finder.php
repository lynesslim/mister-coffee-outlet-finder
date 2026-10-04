<?php
/**
 * Plugin Name: Mister Coffee — Outlet Location & Stock Finder
 * Plugin URI: https://mistercoffee.com.my
 * Description: Interactive searchable and filterable retail outlet finder with Google Maps and in-store product availability matrices.
 * Version: 1.4.0
 * Author: Mister Coffee / Supercraft
 * Author URI: https://mistercoffee.com.my
 * Text Domain: mc-outlet-finder
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MC_OUTLET_VERSION', '1.4.0');
define('MC_OUTLET_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MC_OUTLET_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load Core Classes
require_once MC_OUTLET_PLUGIN_DIR . 'includes/class-mc-db.php';
require_once MC_OUTLET_PLUGIN_DIR . 'includes/class-mc-admin.php';
require_once MC_OUTLET_PLUGIN_DIR . 'includes/class-mc-shortcode.php';
require_once MC_OUTLET_PLUGIN_DIR . 'includes/class-mc-updater.php';

// Initialize GitHub Auto-Updater (YahnisElsts/plugin-update-checker v5)
MC_Outlet_Updater::init(__FILE__);

// Activation Hook: creates custom tables and seeds initial 170 outlets
register_activation_hook(__FILE__, function() {
    MC_Outlet_DB::init_tables();
});
