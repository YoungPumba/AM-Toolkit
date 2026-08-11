<?php
/**
 * Plugin Name: AM Toolkit
 * Plugin URI: https://github.com/ArekMokicki/am-toolkit
 * Description: Toolkit rozszerzający WordPress, Elementor i WooCommerce.
 * Version: 0.11.5
 * Requires PHP: 8.0
 * Author: Arkadiusz Mokicki
 * License: GPL-2.0-or-later
 * Text Domain: am-toolkit
 */

defined('ABSPATH') || exit;

/**
 * Ścieżki i adresy.
 */
define('AM_TOOLKIT_VERSION', '0.11.5');
define('AM_TOOLKIT_PATH', plugin_dir_path(__FILE__));
define('AM_TOOLKIT_URL', plugin_dir_url(__FILE__));

/**
 * Ładowanie klasy Plugin.
 */
require_once AM_TOOLKIT_PATH . 'vendor/autoload.php';

use AMToolkit\Core\Plugin;
use AMToolkit\Core\Installer;

register_activation_hook(__FILE__, [Installer::class, 'activate']);

/**
 * Uruchomienie wtyczki.
 */
$plugin = new Plugin();
$plugin->boot();
