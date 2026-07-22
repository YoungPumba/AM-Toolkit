<?php
/**
 * Plugin Name: AM Toolkit
 * Plugin URI: https://github.com/ArekMokicki/am-toolkit
 * Description: Toolkit rozszerzający WordPress, Elementor i WooCommerce.
 * Version: 0.8.1
 * Requires PHP: 8.0
 * Author: Arkadiusz Mokicki
 * License: GPL-2.0-or-later
 * Text Domain: am-toolkit
 */

defined('ABSPATH') || exit;

/**
 * Ścieżki i adresy.
 */
define('AM_TOOLKIT_VERSION', '0.8.1');
define('AM_TOOLKIT_PATH', plugin_dir_path(__FILE__));
define('AM_TOOLKIT_URL', plugin_dir_url(__FILE__));

/**
 * Ładowanie klasy Plugin.
 */
require_once AM_TOOLKIT_PATH . 'src/Core/Plugin.php';
require_once AM_TOOLKIT_PATH . 'src/Core/Assets.php';
require_once AM_TOOLKIT_PATH . 'src/Settings/Notifications.php';
require_once AM_TOOLKIT_PATH . 'src/Settings/CheckoutNotice.php';
require_once AM_TOOLKIT_PATH . 'src/Admin/NotificationSettings.php';
require_once AM_TOOLKIT_PATH . 'src/Admin/CheckoutSettings.php';
require_once AM_TOOLKIT_PATH . 'src/Integrations/LiteSpeed.php';
require_once AM_TOOLKIT_PATH . 'src/Modules/WooCommerce/ToastIntegration.php';
require_once AM_TOOLKIT_PATH . 'src/Modules/WooCommerce/CartIndicator.php';
require_once AM_TOOLKIT_PATH . 'src/Modules/Account/AccountDashboard.php';
require_once AM_TOOLKIT_PATH . 'src/Modules/Account/PurchasedProducts.php';
require_once AM_TOOLKIT_PATH . 'src/Modules/Account/AccountOnboarding.php';
require_once AM_TOOLKIT_PATH . 'src/Modules/Account/WelcomeAnimation.php';

use AMToolkit\Core\Plugin;

/**
 * Uruchomienie wtyczki.
 */
$plugin = new Plugin();
$plugin->boot();
