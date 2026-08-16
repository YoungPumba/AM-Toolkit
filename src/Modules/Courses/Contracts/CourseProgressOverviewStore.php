<?php

namespace AMToolkit\Modules\Courses\Contracts;

defined('ABSPATH') || exit;

interface CourseProgressOverviewStore
{
    /** @return list<array<string, mixed>>|\WP_Error */
    public function lessons(int $userId, int $courseId, int $programVersionId): array|\WP_Error;

    public function hasCompletion(int $userId, int $courseId, int $programVersionId): bool|\WP_Error;
}
