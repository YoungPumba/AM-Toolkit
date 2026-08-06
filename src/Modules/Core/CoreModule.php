<?php

namespace AMToolkit\Modules\Core;

use AMToolkit\Admin\CheckoutSettings;
use AMToolkit\Admin\NotificationSettings;
use AMToolkit\Core\Assets;
use AMToolkit\Core\ModuleInterface;
use AMToolkit\Integrations\LiteSpeed;

defined('ABSPATH') || exit;

final class CoreModule implements ModuleInterface
{
    public function id(): string
    {
        return 'core';
    }

    public function dependencies(): array
    {
        return [];
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function boot(): void
    {
        (new Assets())->boot();
        (new NotificationSettings())->boot();
        (new CheckoutSettings())->boot();
        (new LiteSpeed())->boot();
    }
}
