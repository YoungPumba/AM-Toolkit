<?php

namespace AMToolkit\Core;

use AMToolkit\Core\Assets;
use AMToolkit\Admin\NotificationSettings;
use AMToolkit\Admin\CheckoutSettings;
use AMToolkit\Integrations\LiteSpeed;
use AMToolkit\Modules\Account\AccountDashboard;
use AMToolkit\Modules\Account\AccountAddresses;
use AMToolkit\Modules\Account\AccountNavigation;
use AMToolkit\Modules\Account\AccountDetails;
use AMToolkit\Modules\Account\AccountOnboarding;
use AMToolkit\Modules\Account\AccountOrderDetails;
use AMToolkit\Modules\Account\AccountOrders;
use AMToolkit\Modules\Account\AccountProductImage;
use AMToolkit\Modules\Account\ManualProductAssignments;
use AMToolkit\Modules\Account\PurchasedProducts;
use AMToolkit\Modules\Account\WelcomeAnimation;
use AMToolkit\Modules\WooCommerce\CartIndicator;
use AMToolkit\Modules\WooCommerce\ToastIntegration;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    /**
     * Wersja AM Toolkit.
     */
    public const VERSION = '0.11.2';

    /**
     * Uruchamia wtyczkę.
     */
    public function boot(): void
    {
        add_action('plugins_loaded', [Installer::class, 'maybeUpgrade'], 5);

        (new Assets())->boot();
        (new NotificationSettings())->boot();
        (new CheckoutSettings())->boot();
        (new LiteSpeed())->boot();
        (new AccountDashboard())->boot();
        (new WelcomeAnimation())->boot();

        add_action('plugins_loaded', [$this, 'bootIntegrations'], 20);
        add_action('init', [$this, 'init']);
    }

    /**
     * Uruchamia integracje opcjonalne dopiero po załadowaniu innych wtyczek.
     */
    public function bootIntegrations(): void
    {
        if (class_exists('WooCommerce')) {
            (new ToastIntegration())->boot();
            (new CartIndicator())->boot();
            (new AccountOnboarding())->boot();
            (new AccountProductImage())->boot();
            (new ManualProductAssignments())->boot();
            (new PurchasedProducts())->boot();
            (new AccountOrders())->boot();
            (new AccountOrderDetails())->boot();
            (new AccountDetails())->boot();
            (new AccountAddresses())->boot();
            (new AccountNavigation())->boot();
        }
    }

    /**
     * Inicjalizacja AM Toolkit.
     */
    public function init(): void
    {
        // Na razie nic tutaj nie robimy.
    }
}
