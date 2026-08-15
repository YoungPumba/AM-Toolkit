<?php

namespace AMToolkit\Core;

defined('ABSPATH') || exit;

final class Authorization
{
    public static function canManageSettings(): bool
    {
        $allowed = current_user_can(Capabilities::MANAGE_SETTINGS)
            || current_user_can('manage_options');

        return (bool) apply_filters(
            'am_toolkit_can_manage_settings',
            $allowed
        );
    }

    public static function canManageAccess(): bool
    {
        $allowed = current_user_can(Capabilities::MANAGE_ACCESS)
            || current_user_can('manage_woocommerce')
            || current_user_can('manage_options');

        return (bool) apply_filters(
            'am_toolkit_can_manage_access',
            $allowed
        );
    }

    public static function canManageCourses(): bool
    {
        $allowed = current_user_can(Capabilities::MANAGE_COURSES)
            || current_user_can('manage_woocommerce')
            || current_user_can('manage_options');

        return (bool) apply_filters(
            'am_toolkit_can_manage_courses',
            $allowed
        );
    }
}
