<?php

namespace AMToolkit\Core;

use AMToolkit\Core\Assets;
use AMToolkit\Modules\WooCommerce\ToastIntegration;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    /**
     * Wersja AM Toolkit.
     */
    public const VERSION = '0.2.0';

    /**
     * Uruchamia wtyczkę.
     */
    public function boot(): void
    {
        (new Assets())->boot();

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
