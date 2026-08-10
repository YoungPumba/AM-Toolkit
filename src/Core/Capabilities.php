<?php

namespace AMToolkit\Core;

defined('ABSPATH') || exit;

final class Capabilities
{
    public const MANAGE_SETTINGS = 'manage_am_toolkit_settings';
    public const MANAGE_ACCESS = 'manage_am_toolkit_access';
    public const MANAGE_COURSES = 'manage_am_toolkit_courses';
    public const VIEW_DIAGNOSTICS = 'view_am_toolkit_diagnostics';

    private const VERSION = 1;
    private const OPTION = 'am_toolkit_capabilities_version';

    public static function install(): void
    {
        $administrator = get_role('administrator');

        if ($administrator === null) {
            throw new \RuntimeException('Administrator role is unavailable.');
        }

        $shopManager = get_role('shop_manager');
        $shopManagerCapabilities = [
            self::MANAGE_ACCESS,
            self::MANAGE_COURSES,
            self::VIEW_DIAGNOSTICS,
        ];

        if (
            (int) get_option(self::OPTION, 0) >= self::VERSION
            && self::hasAll($administrator, self::all())
            && (
                $shopManager === null
                || self::hasAll($shopManager, $shopManagerCapabilities)
            )
        ) {
            return;
        }

        foreach (self::all() as $capability) {
            self::addIfMissing($administrator, $capability);
        }

        if ($shopManager !== null) {
            foreach ($shopManagerCapabilities as $capability) {
                self::addIfMissing($shopManager, $capability);
            }
        }

        update_option(self::OPTION, self::VERSION, false);
    }

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::MANAGE_SETTINGS,
            self::MANAGE_ACCESS,
            self::MANAGE_COURSES,
            self::VIEW_DIAGNOSTICS,
        ];
    }

    /** @param list<string> $capabilities */
    private static function hasAll(object $role, array $capabilities): bool
    {
        foreach ($capabilities as $capability) {
            if (!$role->has_cap($capability)) {
                return false;
            }
        }

        return true;
    }

    private static function addIfMissing(object $role, string $capability): void
    {
        if (!$role->has_cap($capability)) {
            $role->add_cap($capability);
        }
    }
}
