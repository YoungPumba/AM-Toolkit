<?php

namespace AMToolkit\Modules\Courses\Contracts;

defined('ABSPATH') || exit;

interface CourseProgressDiagnostics
{
    /** @return array<string, mixed>|\WP_Error */
    public function lessonState(int $userId, string $coursePublicId, string $lessonPublicId): array|\WP_Error;

    /** @return array<string, mixed>|\WP_Error */
    public function rebuildLessonProgress(
        int $userId,
        string $coursePublicId,
        string $lessonPublicId,
        ?string $requestId = null
    ): array|\WP_Error;
}
