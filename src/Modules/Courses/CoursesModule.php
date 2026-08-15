<?php

namespace AMToolkit\Modules\Courses;

use AMToolkit\Core\ModuleInterface;
use AMToolkit\Modules\Courses\Admin\CourseAdminPage;

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
        (new CourseAdminPage())->boot();

        do_action('am_toolkit_courses_ready');
    }
}
