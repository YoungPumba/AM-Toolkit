<?php

namespace AMToolkit\Modules\WooCommerce;

use AMToolkit\Core\FeatureFlags;
use AMToolkit\Modules\Courses\Services\AccessCoreCourseEntitlementGateway;
use AMToolkit\Modules\Courses\Services\CourseAccessLifecycle;
use AMToolkit\Modules\Courses\Services\HistoricalPurchaseMigrator;
use AMToolkit\Modules\Courses\WpOptionMigrationCheckpointStore;
use AMToolkit\Modules\Courses\WpdbProductCourseMappingStore;

defined('ABSPATH') || exit;

final class CourseAccessIntegration
{
    public function boot(): void
    {
        if (!(new FeatureFlags())->isEnabled('courses-access-automation')) {
            return;
        }

        $records = new WooCommerceAccessRecordFactory();
        $lifecycle = new CourseAccessLifecycle(
            new WpdbProductCourseMappingStore(),
            new AccessCoreCourseEntitlementGateway()
        );

        (new WooCommerceOrderAccessHandler($lifecycle, $records))->boot();
        (new WooCommerceSubscriptionsAccessHandler($lifecycle, $records))->boot();

        $migrator = new HistoricalPurchaseMigrator(
            new WooCommercePaidPurchaseSource($records),
            new WpOptionMigrationCheckpointStore(),
            $lifecycle
        );

        add_action(
            'am_toolkit_courses_migrate_historical_purchases',
            static function (int $limit = 50) use ($migrator): void {
                $result = $migrator->runBatch($limit);
                do_action('am_toolkit_courses_historical_migration_result', $result);
            },
            10,
            1
        );
    }
}
