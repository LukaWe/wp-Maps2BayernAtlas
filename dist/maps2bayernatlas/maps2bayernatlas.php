<?php
/**
 * Plugin Name: Maps2BayernAtlas
 * Description: Wandelt Google-Maps- und OpenStreetMap-Links in aktuelle BayernAtlas-Links um und bindet das Tool per Shortcode ein.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Codex
 * Text Domain: maps2bayernatlas
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('M2BA_PLUGIN_VERSION', '1.0.0');
define('M2BA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('M2BA_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once M2BA_PLUGIN_DIR . 'includes/class-m2ba-plugin.php';

register_activation_hook(__FILE__, ['M2BA_Plugin', 'activate']);

M2BA_Plugin::instance();
