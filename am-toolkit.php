<?php
/**
 * Plugin Name: AM Toolkit
 * Plugin URI: https://github.com/ArekMokicki/am-toolkit
 * Description: Toolkit rozszerzający WordPress, Elementor i WooCommerce.
 * Version: 0.11.2
 * Requires PHP: 8.0
 * Author: Arkadiusz Mokicki
 * License: GPL-2.0-or-later
 * Text Domain: am-toolkit
 */

defined('ABSPATH') || exit;

/**
 * Ścieżki i adresy.
 */
define('AM_TOOLKIT_VERSION', '0.11.2');
define('AM_TOOLKIT_PATH', plugin_dir_path(__FILE__));
define('AM_TOOLKIT_URL', plugin_dir_url(__FILE__));

/**
 * Ładowanie klasy Plugin.
 */
require_once AM_TOOLKIT_PATH . 'src/Core/Plugin.php';
require_once AM_TOOLKIT_PATH . 'src/Core/Assets.php';
require_once AM_TOOLKIT_PATH . 'src/Core/Installer.php';
require_once AM_TOOLKIT_PATH . 'src/Modules/Access/EntitlementStore.php';
require_once AM_TOOLKIT_PATH . 'src/Modules/Access/ActivityEventStore.php';
require_once AM_TOOLKIT_PATH . 'src/Modules/Access/WpdbEntitlementStore.php';
require_once AM_TOOLKIT_PATH . 'src/Modules/Access/WpdbActivityEventStore.php';
require_once AM_TOOLKIT_PATH . 'src/Modules/Access/AccessManager.php';
require_once AM_TOOLKIT_PATH . 'src/Modules/Access/Access.php';
require_once AM_TOOLKIT_PATH . 'src/Settings/Notifications.php';
require_once AM_TOOLKIT_PATH . 'src/Settings/CheckoutNotice.php';
require_once AM_TOOLKIT_PATH . 'src/Admin/NotificationSettings.php';
require_once AM_TOOLKIT_PATH . 'src/Admin/CheckoutSettings.php';
require_once AM_TOOLKIT_PATH . 'src/Integrations/LiteSpeed.php';
require_once AM_TOOLKIT_PATH . 'src/Modules/WooCommerce/ToastIntegration.php';
require_once AM_TOOLKIT_PATH . 'src/Modules/WooCommerce/CartIndicator.php';
require_once AM_TOOLKIT_PATH . 'src/Modules/Account/AccountDashboard.php';
require_once AM_TOOLKIT_PATH . 'src/Modules/Account/AccountProductImage.php';
require_once AM_TOOLKIT_PATH . 'src/Modules/Account/ManualProductAssignments.php';
require_once AM_TOOLKIT_PATH . 'src/Modules/Account/PurchasedProducts.php';
require_once AM_TOOLKIT_PATH . 'src/Modules/Account/AccountOrders.php';
require_once AM_TOOLKIT_PATH . 'src/Modules/Account/AccountOrderDetails.php';
require_once AM_TOOLKIT_PATH . 'src/Modules/Account/AccountDetails.php';
require_once AM_TOOLKIT_PATH . 'src/Modules/Account/AccountAddresses.php';
require_once AM_TOOLKIT_PATH . 'src/Modules/Account/AccountNavigation.php';
require_once AM_TOOLKIT_PATH . 'src/Modules/Account/AccountOnboarding.php';
require_once AM_TOOLKIT_PATH . 'src/Modules/Account/WelcomeAnimation.php';

use AMToolkit\Core\Plugin;
use AMToolkit\Core\Installer;

register_activation_hook(__FILE__, [Installer::class, 'activate']);

/**
 * Uruchomienie wtyczki.
 */
$plugin = new Plugin();
$plugin->boot();
