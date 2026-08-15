<?php

namespace AMToolkit\Modules\Courses\Services;

use AMToolkit\Modules\Courses\Contracts\CourseAdminStore;
use AMToolkit\Modules\Courses\Contracts\ProductCourseMappingStore;
use AMToolkit\Modules\Courses\Domain\PublicationStatus;

defined('ABSPATH') || exit;

final class CourseAdminService
{
    public function __construct(
        private CourseAdminStore $catalog,
        private ProductCourseMappingStore $mappings,
        private CourseAccessLifecycle $access
    ) {
    }

    /** @return list<array<string, mixed>>|\WP_Error */
    public function courses(): array|\WP_Error
    {
        return $this->catalog->listCourses();
    }

    /** @return array<string, mixed>|null|\WP_Error */
    public function course(int $courseId): array|null|\WP_Error
    {
        return $this->catalog->findCourse($courseId);
    }

    public function saveCourse(
        int $courseId,
        string $title,
        string $description,
        int $imageAttachmentId,
        string $status
    ): int|\WP_Error {
        $error = $this->validateTitleAndStatus($title, $status);

        if ($error !== null) {
            return $error;
        }

        return $this->catalog->saveCourse(
            $courseId,
            trim($title),
            $description,
            max(0, $imageAttachmentId),
            $status
        );
    }

    public function archiveCourse(int $courseId): bool|\WP_Error
    {
        return $courseId > 0
            ? $this->catalog->archiveCourse($courseId)
            : $this->invalidIdentifier();
    }

    public function saveSection(
        int $sectionId,
        int $courseId,
        string $title,
        string $description,
        int $position,
        string $status
    ): int|\WP_Error {
        $error = $this->validateTitleAndStatus($title, $status);

        if ($courseId <= 0 || $position < 0) {
            return $this->invalidIdentifier();
        }

        return $error ?? $this->catalog->saveSection(
            $sectionId,
            $courseId,
            trim($title),
            $description,
            $position,
            $status
        );
    }

    public function archiveSection(int $sectionId, int $courseId): bool|\WP_Error
    {
        return $sectionId > 0 && $courseId > 0
            ? $this->catalog->archiveSection($sectionId, $courseId)
            : $this->invalidIdentifier();
    }

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
    ): int|\WP_Error {
        $error = $this->validateTitleAndStatus($title, $status);

        if ($error !== null) {
            return $error;
        }

        if ($courseId <= 0 || $position < 0 || ($durationSeconds !== null && $durationSeconds < 0)) {
            return $this->invalidIdentifier();
        }

        return $this->catalog->saveLesson(
            $lessonId,
            $courseId,
            $sectionId,
            trim($title),
            $description,
            $this->nullableText($videoProvider),
            $this->nullableText($videoReference),
            $durationSeconds,
            $completionRequirements,
            $position,
            $required,
            $status
        );
    }

    public function archiveLesson(int $lessonId, int $courseId): bool|\WP_Error
    {
        return $lessonId > 0 && $courseId > 0
            ? $this->catalog->archiveLesson($lessonId, $courseId)
            : $this->invalidIdentifier();
    }

    public function saveMaterial(
        int $materialId,
        int $lessonId,
        string $name,
        string $description,
        string $storageProvider,
        string $storageReference,
        int $position,
        string $status
    ): int|\WP_Error {
        $error = $this->validateTitleAndStatus($name, $status);

        if ($error !== null) {
            return $error;
        }

        if (
            $lessonId <= 0
            || $position < 0
            || trim($storageProvider) === ''
            || trim($storageReference) === ''
        ) {
            return new \WP_Error(
                'am_toolkit_invalid_course_material',
                __('Materiał wymaga lekcji, dostawcy i bezpiecznego identyfikatora pliku.', 'am-toolkit')
            );
        }

        return $this->catalog->saveMaterial(
            $materialId,
            $lessonId,
            trim($name),
            $description,
            trim($storageProvider),
            trim($storageReference),
            $position,
            $status
        );
    }

    public function archiveMaterial(int $materialId, int $lessonId): bool|\WP_Error
    {
        return $materialId > 0 && $lessonId > 0
            ? $this->catalog->archiveMaterial($materialId, $lessonId)
            : $this->invalidIdentifier();
    }

    /** @param list<int> $productIds */
    public function replaceProductMappings(int $courseId, array $productIds): bool|\WP_Error
    {
        if ($courseId <= 0) {
            return $this->invalidIdentifier();
        }

        $productIds = array_values(array_unique(array_filter(
            array_map('absint', $productIds),
            static fn (int $id): bool => $id > 0
        )));
        $existing = $this->mappings->productIdsForCourse($courseId);

        if (is_wp_error($existing)) {
            return $existing;
        }

        foreach (array_diff($existing, $productIds) as $productId) {
            $result = $this->mappings->unmap($productId, $courseId);

            if (is_wp_error($result)) {
                return $result;
            }
        }

        foreach (array_diff($productIds, $existing) as $productId) {
            $result = $this->mappings->map($productId, $courseId);

            if (is_wp_error($result)) {
                return $result;
            }
        }

        return true;
    }

    public function grantManual(
        int $userId,
        int $courseId,
        int $assignmentId,
        ?string $requestId = null
    ): int|\WP_Error {
        if ($userId <= 0 || $courseId <= 0 || $assignmentId <= 0) {
            return $this->invalidIdentifier();
        }

        $participants = $this->catalog->participantsForCourse($courseId);

        if (is_wp_error($participants)) {
            return $participants;
        }

        foreach ($participants as $participant) {
            if (
                (int) ($participant['user_id'] ?? 0) === $userId
                && ($participant['source_type'] ?? '') === 'manual'
                && (int) ($participant['source_id'] ?? 0) > 0
            ) {
                $assignmentId = (int) $participant['source_id'];
                break;
            }
        }

        return $this->access->grantManual(
            $userId,
            $courseId,
            $assignmentId,
            ['provider' => 'wordpress_admin'],
            $requestId
        );
    }

    public function revokeManual(int $assignmentId, ?string $requestId = null): int|\WP_Error
    {
        return $this->access->revokeManual($assignmentId, $requestId);
    }

    /** @return list<int>|\WP_Error */
    public function productIds(int $courseId): array|\WP_Error
    {
        return $this->mappings->productIdsForCourse($courseId);
    }

    /** @return list<array<string, mixed>>|\WP_Error */
    public function sections(int $courseId): array|\WP_Error
    {
        return $this->catalog->sectionsForCourse($courseId);
    }

    /** @return list<array<string, mixed>>|\WP_Error */
    public function lessons(int $courseId): array|\WP_Error
    {
        return $this->catalog->lessonsForCourse($courseId);
    }

    /** @return list<array<string, mixed>>|\WP_Error */
    public function materials(int $courseId): array|\WP_Error
    {
        return $this->catalog->materialsForCourse($courseId);
    }

    /** @return list<array<string, mixed>>|\WP_Error */
    public function participants(int $courseId): array|\WP_Error
    {
        return $this->catalog->participantsForCourse($courseId);
    }

    /** @return list<array<string, mixed>>|\WP_Error */
    public function activity(int $courseId, int $limit = 50): array|\WP_Error
    {
        return $this->catalog->activityForCourse($courseId, $limit);
    }

    private function validateTitleAndStatus(string $title, string $status): ?\WP_Error
    {
        if (trim($title) === '') {
            return new \WP_Error(
                'am_toolkit_course_title_required',
                __('Nazwa nie może być pusta.', 'am-toolkit')
            );
        }

        try {
            PublicationStatus::assertValid($status);
        } catch (\InvalidArgumentException) {
            return new \WP_Error(
                'am_toolkit_invalid_course_status',
                __('Nieprawidłowy stan publikacji.', 'am-toolkit')
            );
        }

        return null;
    }

    private function invalidIdentifier(): \WP_Error
    {
        return new \WP_Error(
            'am_toolkit_invalid_course_admin_target',
            __('Nieprawidłowy identyfikator zasobu kursu.', 'am-toolkit')
        );
    }

    private function nullableText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
