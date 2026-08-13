<?php

namespace AMToolkit\Modules\Courses\Contracts;

use AMToolkit\Modules\Courses\Domain\CourseCompletion;

defined('ABSPATH') || exit;

interface CompletionRepository
{
    public function find(
        int $userId,
        int $courseId,
        int $programVersionId
    ): ?CourseCompletion;

    /**
     * Records a completion once for the unique user/course/program tuple.
     */
    public function record(CourseCompletion $completion): bool;
}
