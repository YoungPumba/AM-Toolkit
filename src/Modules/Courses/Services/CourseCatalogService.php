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
            $course === null ||
            empty($course['id']) ||
            empty($course['current_program_version_id']) ||
            !$this->access->userCanAccess($userId, (int) $course['id'])
        ) {
            return $this->notFound();
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

    private function readError(): \WP_Error
    {
        return new \WP_Error(
            'am_toolkit_course_view_read_failed',
            __('Nie udało się teraz wczytać kursów. Spróbuj ponownie później.', 'am-toolkit')
        );
    }
}
