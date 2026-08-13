<?php

namespace AMToolkit\Modules\Courses;

use AMToolkit\Core\ModuleInterface;

defined('ABSPATH') || exit;

final class CoursesModule implements ModuleInterface
{
    public function id(): string
    {
        return 'courses';
    }

    public function dependencies(): array
    {
        return ['core', 'access'];
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function boot(): void
    {
        /**
         * Fires only when the Courses feature flag and all dependencies allow
         * the module to boot. No UI or provider integration belongs here.
         */
        do_action('am_toolkit_courses_ready');
    }
}
