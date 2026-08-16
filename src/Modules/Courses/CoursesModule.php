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
use AMToolkit\Modules\Courses\Services\AccessCoreCourseEntitlementGateway;
use AMToolkit\Modules\Courses\Services\CourseAccessLifecycle;
use AMToolkit\Modules\Courses\Services\CourseAdminService;
use AMToolkit\Modules\Courses\Services\CourseCatalogService;
use AMToolkit\Modules\Courses\Services\CourseNextActionService;
use AMToolkit\Modules\Courses\Services\CourseProgressService;
use AMToolkit\Modules\Courses\Services\CoursePreviewService;
use AMToolkit\Modules\Courses\Services\CourseMeetingService;
use AMToolkit\Modules\Courses\Services\CourseLessonTaskService;
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
        $taskStore = (new FeatureFlags())->isEnabled('courses-tasks')
            ? new WpdbCourseLessonTaskStore()
            : null;
        $taskService = $taskStore !== null
            ? new CourseLessonTaskService($taskStore, new WpdbActivityEventStore())
            : null;
        $mappings = new WpdbProductCourseMappingStore();
        $adminCourses = new CourseAdminService(
            new WpdbCourseAdminStore(),
            $mappings,
            new CourseAccessLifecycle($mappings, new AccessCoreCourseEntitlementGateway())
        );
        $preview = new CoursePreviewService($adminCourses, $meetingService, $qaService, $taskService);
        (new CourseAdminPage($adminCourses, $assetStore, $meetingService, $qaService, $taskService))->boot();
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
        $assets = new CourseAssetController($catalog, [$assetStore], $preview);
        $assets->boot();
        $progress = null;
        $progressController = null;

        if ((new FeatureFlags())->isEnabled('courses-progress')) {
            $progress = new CourseProgressService(
                new WpdbCourseProgressSourceStore(),
                new WpdbProgressRepository(),
                new WpdbCompletionRepository(),
                new AccessCoreCourseAccessPolicy(),
                new WpdbActivityEventStore(),
                $taskStore
            );
            $progressController = new CourseProgressController($progress);
            $progressController->boot();
        }

        (new CourseHubPage(
            $catalog,
            $assets,
            new WordPressCourseVideoRenderer(),
            $progress,
            $progressController,
            $preview
        ))->boot();
        (new CourseDashboardSection($catalog))->boot();
        (new CourseAttentionTasks($catalog))->boot();

        do_action('am_toolkit_courses_ready');
    }
}
