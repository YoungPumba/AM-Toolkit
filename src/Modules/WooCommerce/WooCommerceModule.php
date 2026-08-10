<?php

namespace AMToolkit\Modules\WooCommerce;

use AMToolkit\Core\ModuleInterface;

defined('ABSPATH') || exit;

final class WooCommerceModule implements ModuleInterface
{
    public function id(): string
    {
        return 'woocommerce';
    }

    public function dependencies(): array
    {
        return ['core'];
    }

    public function isAvailable(): bool
    {
        return class_exists('WooCommerce');
    }

    public function boot(): void
    {
        (new ToastIntegration())->boot();
        (new CartIndicator())->boot();
    }
}
