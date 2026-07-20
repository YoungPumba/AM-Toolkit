<?php

namespace AMToolkit\Core;

use AMToolkit\Core\Assets;
use AMToolkit\Admin\NotificationSettings;
use AMToolkit\Admin\CheckoutSettings;
use AMToolkit\Integrations\LiteSpeed;
use AMToolkit\Modules\Account\AccountDashboard;
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
    public const VERSION = '0.5.7';

    /**
     * Uruchamia wtyczkę.
     */
    public function boot(): void
    {
        (new Assets())->boot();
        (new NotificationSettings())->boot();
        (new CheckoutSettings())->boot();
        (new LiteSpeed())->boot();
        (new AccountDashboard())->boot();

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
