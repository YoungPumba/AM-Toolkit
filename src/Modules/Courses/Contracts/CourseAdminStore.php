<?php

namespace AMToolkit\Modules\Courses\Contracts;

defined('ABSPATH') || exit;

/**
 * Persistence and read-model boundary used by the WordPress course editor.
 * Access grants remain owned by AM Access Core.
 */
interface CourseAdminStore
{
    /** @return list<array<string, mixed>>|\WP_Error */
    public function listCourses(): array|\WP_Error;

    /** @return array<string, mixed>|null|\WP_Error */
    public function findCourse(int $courseId): array|null|\WP_Error;

    public function saveCourse(
        int $courseId,
        string $title,
        string $description,
        int $imageAttachmentId,
        string $status
    ): int|\WP_Error;

    public function archiveCourse(int $courseId): bool|\WP_Error;

    /** @return list<array<string, mixed>>|\WP_Error */
    public function sectionsForCourse(int $courseId): array|\WP_Error;

    public function saveSection(
        int $sectionId,
        int $courseId,
        string $title,
        string $description,
        int $position,
        string $status
    ): int|\WP_Error;

    public function archiveSection(int $sectionId, int $courseId): bool|\WP_Error;

    /** @return list<array<string, mixed>>|\WP_Error */
    public function lessonsForCourse(int $courseId): array|\WP_Error;

    /** @param array<string, mixed> $completionRequirements */
    public function saveLesson(
        int $lessonId,
        int $courseId,
        ?int $sectionId,
        string $title,
        string $description,
        ?string $videoProvider,
        ?string $videoReference,
        ?int $durationSeconds,
        array $completionRequirements,
        int $position,
        bool $required,
        string $status
    ): int|\WP_Error;

    public function archiveLesson(int $lessonId, int $courseId): bool|\WP_Error;

    /** @return list<array<string, mixed>>|\WP_Error */
    public function materialsForCourse(int $courseId): array|\WP_Error;

    public function saveMaterial(
        int $materialId,
        int $lessonId,
        string $name,
        string $description,
        string $storageProvider,
        string $storageReference,
        int $position,
        string $status
    ): int|\WP_Error;

    public function archiveMaterial(int $materialId, int $lessonId): bool|\WP_Error;

    /** @return list<array<string, mixed>>|\WP_Error */
    public function participantsForCourse(int $courseId): array|\WP_Error;

    /** @return list<array<string, mixed>>|\WP_Error */
    public function activityForCourse(int $courseId, int $limit = 50): array|\WP_Error;
}
