<?php

namespace AMToolkit\Modules\Courses\Contracts;

use AMToolkit\Modules\Courses\Domain\LessonProgress;

defined('ABSPATH') || exit;

interface ProgressRepository
{
    public function find(int $userId, int $courseId, int $lessonId): ?LessonProgress;

    /**
     * Persists current state idempotently for the unique user/course/lesson tuple.
     */
    public function save(LessonProgress $progress): bool;

    /**
     * @param list<int> $lessonIds
     * @return list<int>
     */
    public function completedLessonIds(int $userId, int $courseId, array $lessonIds): array;
}
