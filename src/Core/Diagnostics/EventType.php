<?php

namespace AMToolkit\Core\Diagnostics;

defined('ABSPATH') || exit;

final class EventType
{
    public static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9._-]/', '', $value) ?? '';

        return trim($value, '._-');
    }
}
