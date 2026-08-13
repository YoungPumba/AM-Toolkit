<?php

namespace AMToolkit\Modules\Courses\Contracts;

use AMToolkit\Modules\Courses\Domain\CourseProgramVersion;

defined('ABSPATH') || exit;

interface CompletionEvaluator
{
    /** @param list<int> $completedLessonIds */
    public function isComplete(
        CourseProgramVersion $program,
        array $completedLessonIds
    ): bool;
}
