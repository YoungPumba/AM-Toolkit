<?php

namespace AMToolkit\Modules\Courses\Contracts;

use AMToolkit\Modules\Courses\Domain\Identifier;

defined('ABSPATH') || exit;

/**
 * Read-only persistence boundary for participant-facing course views.
 * Private program data must only be requested after access is authorized.
 */
interface CourseViewStore
{
    /** @return list<array<string, mixed>>|\WP_Error */
    public function coursesForUser(int $userId, string $at): array|\WP_Error;

    /** @return array<string, mixed>|null|\WP_Error */
    public function findPublishedCourse(Identifier $publicId): array|null|\WP_Error;

    /** @return array<string, mixed>|\WP_Error */
    public function publishedProgram(int $courseId, int $programVersionId): array|\WP_Error;

    /** @return array<string, mixed>|null|\WP_Error */
    public function publishedLesson(
        int $courseId,
        int $programVersionId,
        Identifier $publicId
    ): array|null|\WP_Error;
}
