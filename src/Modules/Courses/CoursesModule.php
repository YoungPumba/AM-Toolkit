<?php

namespace AMToolkit\Modules\Courses;

use AMToolkit\Core\FeatureFlags;
use AMToolkit\Core\ModuleInterface;
use AMToolkit\Modules\Access\WpdbActivityEventStore;
use AMToolkit\Modules\Courses\Admin\CourseAdminPage;
use AMToolkit\Modules\Courses\Frontend\CourseAssetController;
use AMToolkit\Modules\Courses\Frontend\CourseDashboardSection;
use AMToolkit\Modules\Courses\Frontend\CourseHubPage;
use AMToolkit\Modules\Courses\Frontend\CourseProgressController;
use AMToolkit\Modules\Courses\Frontend\WordPressCourseVideoRenderer;
use AMToolkit\Modules\Courses\Services\AccessCoreCourseAccessPolicy;
use AMToolkit\Modules\Courses\Services\CourseCatalogService;
use AMToolkit\Modules\Courses\Services\CourseNextActionService;
use AMToolkit\Modules\Courses\Services\CourseProgressService;

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
            new AccessCoreCourseAccessPolicy(),
            (new FeatureFlags())->isEnabled('courses-progress')
                ? new CourseNextActionService(
                    new WpdbCourseProgressOverviewStore()
                )
                : null
        );
        $assets = new CourseAssetController($catalog, [$assetStore]);
        $assets->boot();
        $progress = null;
        $progressController = null;

        if ((new FeatureFlags())->isEnabled('courses-progress')) {
            $progress = new CourseProgressService(
                new WpdbCourseProgressSourceStore(),
                new WpdbProgressRepository(),
                new WpdbCompletionRepository(),
                new AccessCoreCourseAccessPolicy(),
                new WpdbActivityEventStore()
            );
            $progressController = new CourseProgressController($progress);
            $progressController->boot();
        }

        (new CourseHubPage(
            $catalog,
            $assets,
            new WordPressCourseVideoRenderer(),
            $progress,
            $progressController
        ))->boot();
        (new CourseDashboardSection($catalog))->boot();

        do_action('am_toolkit_courses_ready');
    }
}
