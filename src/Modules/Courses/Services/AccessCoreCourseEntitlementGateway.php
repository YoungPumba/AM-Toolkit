<?php

namespace AMToolkit\Modules\Courses\Services;

use AMToolkit\Modules\Access\Access;
use AMToolkit\Modules\Courses\Contracts\CourseEntitlementGateway;

defined('ABSPATH') || exit;

final class AccessCoreCourseEntitlementGateway implements CourseEntitlementGateway
{
    public function grant(int $userId, int $courseId, array $context): int|\WP_Error
    {
        return Access::grant($userId, 'course', $courseId, $context);
    }

    public function revokeAllSource(
        string $sourceType,
        int $sourceId,
        array $context
    ): int|\WP_Error {
        return Access::revokeAllSource($sourceType, $sourceId, $context);
    }
}
