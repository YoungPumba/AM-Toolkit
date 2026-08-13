<?php

namespace AMToolkit\Core;

defined('ABSPATH') || exit;

final class FeatureFlags
{
    private const OPTION = 'am_toolkit_feature_flags';

    /** @var array<string, bool> */
    private array $defaults = [
        'core' => true,
        'access' => true,
        'woocommerce' => true,
        'account' => true,
        'courses' => false,
    ];

    public function isEnabled(string $moduleId): bool
    {
        $moduleId = sanitize_key($moduleId);

        if ($moduleId === 'core') {
            return true;
        }

        if (defined('AM_TOOLKIT_SAFE_MODE') && AM_TOOLKIT_SAFE_MODE) {
            return $moduleId === 'access';
        }

        $constant = 'AM_TOOLKIT_DISABLE_' . strtoupper(str_replace('-', '_', $moduleId));

        if (defined($constant) && constant($constant)) {
            return false;
        }

        $stored = get_option(self::OPTION, []);
        $enabled = is_array($stored) && array_key_exists($moduleId, $stored)
            ? (bool) $stored[$moduleId]
            : ($this->defaults[$moduleId] ?? true);

        return (bool) apply_filters(
            'am_toolkit_feature_enabled',
            $enabled,
            $moduleId
        );
    }
}
