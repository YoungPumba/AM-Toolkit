<?php

namespace AMToolkit\Modules\Courses;

use AMToolkit\Core\ModuleInterface;
use AMToolkit\Modules\Courses\Admin\CourseAdminPage;
use AMToolkit\Modules\Courses\Frontend\CourseAssetController;
use AMToolkit\Modules\Courses\Frontend\CourseDashboardSection;
use AMToolkit\Modules\Courses\Frontend\CourseHubPage;
use AMToolkit\Modules\Courses\Frontend\WordPressCourseVideoRenderer;
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
        $assetStore = new WpPrivateCourseAssetStore();
        (new CourseAdminPage(null, $assetStore))->boot();
        $catalog = new CourseCatalogService(
            new WpdbCourseViewStore(),
            new AccessCoreCourseAccessPolicy()
        );
        $assets = new CourseAssetController($catalog, [$assetStore]);
        $assets->boot();
        (new CourseHubPage($catalog, $assets, new WordPressCourseVideoRenderer()))->boot();
        (new CourseDashboardSection($catalog))->boot();

        do_action('am_toolkit_courses_ready');
    }
}
