<?php

namespace AMToolkit\Modules\Courses;

use AMToolkit\Core\FeatureFlags;
use AMToolkit\Core\ModuleInterface;
use AMToolkit\Modules\Access\WpdbActivityEventStore;
use AMToolkit\Modules\Courses\Admin\CourseAdminPage;
use AMToolkit\Modules\Courses\Frontend\CourseAssetController;
use AMToolkit\Modules\Courses\Frontend\CourseAttentionTasks;
use AMToolkit\Modules\Courses\Frontend\CourseDashboardSection;
use AMToolkit\Modules\Courses\Frontend\CourseHubPage;
use AMToolkit\Modules\Courses\Frontend\CourseProgressController;
use AMToolkit\Modules\Courses\Frontend\WordPressCourseVideoRenderer;
use AMToolkit\Modules\Courses\Services\AccessCoreCourseAccessPolicy;
use AMToolkit\Modules\Courses\Services\CourseCatalogService;
use AMToolkit\Modules\Courses\Services\CourseNextActionService;
use AMToolkit\Modules\Courses\Services\CourseProgressService;
use AMToolkit\Modules\Courses\Services\CourseMeetingService;
use AMToolkit\Modules\Courses\Services\CourseQaService;

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
        $meetingStore = (new FeatureFlags())->isEnabled('courses-meetings')
            ? new WpdbCourseMeetingStore()
            : null;
        $meetingService = $meetingStore !== null
            ? new CourseMeetingService($meetingStore, new WpdbActivityEventStore())
            : null;
        $qaStore = (new FeatureFlags())->isEnabled('courses-qa')
            ? new WpdbCourseQaStore()
            : null;
        $qaService = $qaStore !== null
            ? new CourseQaService($qaStore, new WpdbActivityEventStore())
            : null;
        (new CourseAdminPage(null, $assetStore, $meetingService, $qaService))->boot();
        $catalog = new CourseCatalogService(
            new WpdbCourseViewStore(),
            new AccessCoreCourseAccessPolicy(),
            (new FeatureFlags())->isEnabled('courses-progress')
                ? new CourseNextActionService(
                    new WpdbCourseProgressOverviewStore()
                )
                : null,
            $meetingStore,
            $qaStore
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
        (new CourseAttentionTasks($catalog))->boot();

        do_action('am_toolkit_courses_ready');
    }
}
