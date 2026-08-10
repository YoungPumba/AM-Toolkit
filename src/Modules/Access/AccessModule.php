<?php

namespace AMToolkit\Modules\Access;

use AMToolkit\Core\ModuleInterface;

defined('ABSPATH') || exit;

final class AccessModule implements ModuleInterface
{
    public function id(): string
    {
        return 'access';
    }

    public function dependencies(): array
    {
        return ['core'];
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function boot(): void
    {
        // Access services are created lazily by the public Access facade.
    }
}
