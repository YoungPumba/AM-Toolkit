<?php

namespace AMToolkit\Modules\Courses\Services;

use AMToolkit\Modules\Courses\Contracts\CompletionEvaluator;
use AMToolkit\Modules\Courses\Domain\CourseProgramVersion;

defined('ABSPATH') || exit;

final class RequiredLessonCompletionEvaluator implements CompletionEvaluator
{
    public function isComplete(
        CourseProgramVersion $program,
        array $completedLessonIds
    ): bool {
        $completedLessonIds = array_values(array_unique(array_map('intval', $completedLessonIds)));

        foreach ($program->requiredLessonIds() as $requiredLessonId) {
            if (! in_array($requiredLessonId, $completedLessonIds, true)) {
                return false;
            }
        }

        return true;
    }
}
