<?php

namespace AMToolkit\Modules\Courses\Services;

use AMToolkit\Modules\Access\Access;
use AMToolkit\Modules\Courses\Contracts\CourseAccessPolicy;

defined('ABSPATH') || exit;

final class AccessCoreCourseAccessPolicy implements CourseAccessPolicy
{
    public function userCanAccess(int $userId, int $courseId): bool
    {
        if ($userId <= 0 || $courseId <= 0) {
            return false;
        }

        return Access::userHas($userId, 'course', $courseId);
    }
}
