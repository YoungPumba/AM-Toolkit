<?php

namespace AMToolkit\Core;

defined('ABSPATH') || exit;

interface ModuleInterface
{
    public function id(): string;

    /** @return list<string> */
    public function dependencies(): array;

    public function isAvailable(): bool;

    public function boot(): void;
}
