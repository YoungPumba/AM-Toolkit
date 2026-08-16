<?php

namespace AMToolkit\Modules\Courses\Contracts;

use AMToolkit\Modules\Courses\Domain\Identifier;

defined('ABSPATH') || exit;

interface CourseLessonTaskStore
{
    /** @return list<array<string, mixed>>|\WP_Error */
    public function tasksForCourse(int $courseId): array|\WP_Error;

    /** @return list<array<string, mixed>>|\WP_Error */
    public function publishedTasksForLesson(int $lessonId): array|\WP_Error;

    /** @param array<string, mixed> $task */
    public function saveTask(array $task): int|\WP_Error;

    public function archiveTask(int $taskId, int $courseId): bool|\WP_Error;

    /** @return list<int>|\WP_Error */
    public function completedTaskIds(int $userId, int $lessonId): array|\WP_Error;

    public function setTaskCompletion(
        int $userId,
        int $courseId,
        int $lessonId,
        Identifier $taskPublicId,
        bool $completed,
        string $requestId,
        string $occurredAt
    ): int|\WP_Error;
}
