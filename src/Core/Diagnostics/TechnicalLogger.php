<?php

namespace AMToolkit\Core\Diagnostics;

defined('ABSPATH') || exit;

interface TechnicalLogger
{
    /** @param array<string, scalar|null> $context */
    public function error(string $message, array $context = []): void;
}
