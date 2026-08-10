<?php

namespace AMToolkit\Core;

use AMToolkit\Modules\Access\AccessModule;
use AMToolkit\Modules\Account\AccountModule;
use AMToolkit\Modules\Core\CoreModule;
use AMToolkit\Modules\WooCommerce\WooCommerceModule;

defined('ABSPATH') || exit;

final class Plugin
{
    public const VERSION = '0.11.4';

    private ModuleRegistry $modules;

    public function __construct(?ModuleRegistry $modules = null)
    {
        $this->modules = $modules ?? $this->createModuleRegistry();
    }

    public function boot(): void
    {
        add_action('plugins_loaded', [Installer::class, 'maybeUpgrade'], 5);
        add_action('plugins_loaded', [$this, 'bootModules'], 20);
        add_action('init', [$this, 'init']);
    }

    public function bootModules(): void
    {
        $this->modules->bootAll();
    }

    public function init(): void
    {
        // Reserved for plugin-wide initialization that belongs to WordPress init.
    }

    private function createModuleRegistry(): ModuleRegistry
    {
        $registry = new ModuleRegistry(new FeatureFlags());

        $registry->register(new CoreModule());
        $registry->register(new AccessModule());
        $registry->register(new WooCommerceModule());
        $registry->register(new AccountModule());

        return $registry;
    }
}
