<?php

namespace AMToolkit\Modules\Courses\Contracts;

defined('ABSPATH') || exit;

interface CourseQaStore
{
    /** @return list<array<string, mixed>>|\WP_Error */
    public function entriesForCourse(int $courseId): array|\WP_Error;

    /** @return list<array<string, mixed>>|\WP_Error */
    public function publishedEntriesForCourse(int $courseId, int $programVersionId): array|\WP_Error;

    /** @param array<string, mixed> $entry */
    public function saveEntry(array $entry): int|\WP_Error;

    public function archiveEntry(int $entryId, int $courseId): bool|\WP_Error;
}
