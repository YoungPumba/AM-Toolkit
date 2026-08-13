<?php

namespace AMToolkit\Modules\Courses\Contracts;

defined('ABSPATH') || exit;

interface CourseAccessPolicy
{
    public function userCanAccess(int $userId, int $courseId): bool;
}
