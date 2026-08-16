<?php

namespace AMToolkit\Modules\Courses\Contracts;

defined('ABSPATH') || exit;

/** Optional archive capability for course resources that are not catalog snapshots. */
interface CourseResourceArchiveStore
{
    public function archiveCourseResource(string $resourceType, int $resourceId, int $courseId): bool|\WP_Error;
}
