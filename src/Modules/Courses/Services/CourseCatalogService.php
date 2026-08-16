<?php

namespace AMToolkit\Modules\Courses\Services;

use AMToolkit\Modules\Courses\Contracts\CourseAccessPolicy;
use AMToolkit\Modules\Courses\Contracts\CourseViewStore;
use AMToolkit\Modules\Courses\Domain\Identifier;

defined('ABSPATH') || exit;

final class CourseCatalogService
{
    public function __construct(
        private CourseViewStore $store,
        private CourseAccessPolicy $access
    ) {
    }

    /** @return list<array<string, mixed>>|\WP_Error */
    public function coursesForUser(int $userId): array|\WP_Error
    {
        if ($userId <= 0) {
            return [];
        }

        $courses = $this->store->coursesForUser($userId, current_time('mysql', true));

        if (is_wp_error($courses)) {
            return $this->readError();
        }

        foreach ($courses as &$course) {
            $course['access_state'] = $this->accessState($course);
            $course['can_open'] = !empty($course['has_active_access'])
                && ($course['course_status'] ?? '') === 'published';
        }
        unset($course);

        return $courses;
    }

    /** @return array<string, mixed>|\WP_Error */
    public function courseForUser(int $userId, string $publicId): array|\WP_Error
    {
        $course = $this->authorizedCourse($userId, $publicId);

        if (is_wp_error($course)) {
            return $course;
        }

        $program = $this->store->publishedProgram(
            (int) $course['id'],
            (int) $course['current_program_version_id']
        );

        if (is_wp_error($program)) {
            return $this->readError();
        }

        unset($course['id'], $course['current_program_version_id']);
        $course['program'] = $program;

        return $course;
    }

    /** @return array<string, mixed>|\WP_Error */
    public function lessonForUser(int $userId, string $coursePublicId, string $lessonPublicId): array|\WP_Error
    {
        $course = $this->authorizedCourse($userId, $coursePublicId);

        if (is_wp_error($course)) {
            return $course;
        }

        try {
            $lessonIdentifier = new Identifier($lessonPublicId);
        } catch (\InvalidArgumentException) {
            return $this->lessonNotFound();
        }

        $lesson = $this->store->publishedLesson(
            (int) $course['id'],
            (int) $course['current_program_version_id'],
            $lessonIdentifier
        );

        if (is_wp_error($lesson)) {
            return $this->readError();
        }

        if ($lesson === null) {
            return $this->lessonNotFound();
        }

        unset($course['id'], $course['current_program_version_id']);
        $lesson['course'] = $course;

        return $lesson;
    }

    /** @return array{provider: string, reference: string, name: string, disposition: string}|\WP_Error */
    public function assetForUser(
        int $userId,
        string $coursePublicId,
        string $lessonPublicId,
        string $kind,
        string $assetPublicId = ''
    ): array|\WP_Error {
        $lesson = $this->lessonForUser($userId, $coursePublicId, $lessonPublicId);

        if (is_wp_error($lesson)) {
            return $lesson;
        }

        if ($kind === 'video') {
            $provider = isset($lesson['video_provider']) ? (string) $lesson['video_provider'] : '';
            $reference = isset($lesson['video_reference']) ? (string) $lesson['video_reference'] : '';

            if ($provider === '' || $reference === '') {
                return $this->notFound();
            }

            return [
                'provider' => $provider,
                'reference' => $reference,
                'name' => (string) ($lesson['title'] ?? __('Nagranie lekcji', 'am-toolkit')),
                'disposition' => 'inline',
            ];
        }

        try {
            $materialIdentifier = new Identifier($assetPublicId);
        } catch (\InvalidArgumentException) {
            return $this->notFound();
        }

        $materials = isset($lesson['materials']) && is_array($lesson['materials'])
            ? $lesson['materials']
            : [];

        foreach ($materials as $material) {
            if ((string) ($material['public_id'] ?? '') !== $materialIdentifier->value()) {
                continue;
            }

            $provider = (string) ($material['storage_provider'] ?? '');
            $reference = (string) ($material['storage_reference'] ?? '');

            if ($provider === '' || $reference === '') {
                break;
            }

            return [
                'provider' => $provider,
                'reference' => $reference,
                'name' => (string) ($material['name'] ?? __('Materiał lekcji', 'am-toolkit')),
                'disposition' => 'attachment',
            ];
        }

        return $this->notFound();
    }

    /** @param array<string, mixed> $course */
    private function accessState(array $course): string
    {
        if (!empty($course['has_completion'])) {
            return 'completed';
        }

        if (($course['course_status'] ?? '') === 'archived') {
            return 'expired';
        }

        if (!empty($course['has_active_access'])) {
            return 'active';
        }

        if (!empty($course['has_future_access'])) {
            return 'scheduled';
        }

        return 'expired';
    }

    private function notFound(): \WP_Error
    {
        return new \WP_Error(
            'am_toolkit_course_not_available',
            __('Ten kurs nie jest dostępny dla Twojego konta.', 'am-toolkit')
        );
    }

    private function lessonNotFound(): \WP_Error
    {
        return new \WP_Error(
            'am_toolkit_course_lesson_not_available',
            __('Ta lekcja nie jest dostępna. Wróć do programu i wybierz inną lekcję.', 'am-toolkit')
        );
    }

    private function readError(): \WP_Error
    {
        return new \WP_Error(
            'am_toolkit_course_view_read_failed',
            __('Nie udało się teraz wczytać kursów. Spróbuj ponownie później.', 'am-toolkit')
        );
    }

    /** @return array<string, mixed>|\WP_Error */
    private function authorizedCourse(int $userId, string $publicId): array|\WP_Error
    {
        if ($userId <= 0) {
            return $this->notFound();
        }

        try {
            $identifier = new Identifier($publicId);
        } catch (\InvalidArgumentException) {
            return $this->notFound();
        }

        $course = $this->store->findPublishedCourse($identifier);

        if (is_wp_error($course)) {
            return $this->readError();
        }

        if (
            $course === null
            || empty($course['id'])
            || empty($course['current_program_version_id'])
            || !$this->access->userCanAccess($userId, (int) $course['id'])
        ) {
            return $this->notFound();
        }

        return $course;
    }
}
