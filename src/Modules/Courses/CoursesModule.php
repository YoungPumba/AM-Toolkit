<?php

namespace AMToolkit\Modules\Courses;

use AMToolkit\Core\ModuleInterface;
use AMToolkit\Modules\Courses\Admin\CourseAdminPage;
use AMToolkit\Modules\Courses\Frontend\CourseDashboardSection;
use AMToolkit\Modules\Courses\Frontend\CourseHubPage;
use AMToolkit\Modules\Courses\Services\AccessCoreCourseAccessPolicy;
use AMToolkit\Modules\Courses\Services\CourseCatalogService;

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
        $catalog = new CourseCatalogService(
            new WpdbCourseViewStore(),
            new AccessCoreCourseAccessPolicy()
        );
        (new CourseHubPage($catalog))->boot();
        (new CourseDashboardSection($catalog))->boot();

        do_action('am_toolkit_courses_ready');
    }
}
