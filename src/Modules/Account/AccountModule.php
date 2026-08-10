<?php

namespace AMToolkit\Modules\Account;

use AMToolkit\Core\ModuleInterface;

defined('ABSPATH') || exit;

final class AccountModule implements ModuleInterface
{
    public function id(): string
    {
        return 'account';
    }

    public function dependencies(): array
    {
        return ['core', 'access', 'woocommerce'];
    }

    public function isAvailable(): bool
    {
        return class_exists('WooCommerce');
    }

    public function boot(): void
    {
        (new AccountDashboard())->boot();
        (new WelcomeAnimation())->boot();
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
