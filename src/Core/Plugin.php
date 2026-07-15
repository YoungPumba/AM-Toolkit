<?php

namespace AMToolkit\Core;

use AMToolkit\Core\Assets;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    /**
     * Wersja AM Toolkit.
     */
    public const VERSION = '0.1.0';

    /**
     * Uruchamia wtyczkę.
     */
    public function boot(): void
    {
        (new Assets())->boot();
    
        add_action('init', [$this, 'init']);
    }

    /**
     * Inicjalizacja AM Toolkit.
     */
    public function init(): void
    {
        // Na razie nic tutaj nie robimy.
    }
}