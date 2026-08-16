<?php

namespace AMToolkit\Modules\Courses\Services;

use AMToolkit\Modules\Courses\Domain\Identifier;

defined('ABSPATH') || exit;

/**
 * Builds the participant read model from the current editorial workspace.
 *
 * Authorization and nonce verification stay at the HTTP boundary. This service
 * deliberately does not depend on access grants or progress repositories.
 */
final class CoursePreviewService
{
    public const QUERY_COURSE = 'amt-course-preview';
    public const QUERY_NONCE = 'amt-preview-nonce';

    public function __construct(
        private CourseAdminService $courses,
        private ?CourseMeetingService $meetings = null,
        private ?CourseQaService $qa = null,
        private ?CourseLessonTaskService $tasks = null
    ) {
    }

    public static function nonceAction(int $courseId): string
    {
        return 'am_toolkit_course_preview:' . max(0, $courseId);
    }

    /** @return array<string, mixed>|\WP_Error */
    public function course(int $courseId): array|\WP_Error
    {
        $course = $this->courses->course($courseId);

        if (is_wp_error($course)) {
            return $course;
        }

        if (!is_array($course) || (int) ($course['id'] ?? 0) !== $courseId) {
            return $this->notFound();
        }

        $sections = $this->courses->sections($courseId);
        $lessons = $this->courses->lessons($courseId);

        if (is_wp_error($sections) || is_wp_error($lessons)) {
            return $this->readError();
        }

        $sections = array_values(array_filter(
            $sections,
            static fn (array $item): bool => ($item['status'] ?? '') !== 'archived'
        ));
        $lessons = array_values(array_filter(
            $lessons,
            static fn (array $item): bool => ($item['status'] ?? '') !== 'archived'
        ));
        $program = $this->program($sections, $lessons);
        $result = [
            'public_id' => (string) ($course['public_id'] ?? ''),
            'title' => (string) ($course['title'] ?? ''),
            'description' => (string) ($course['description'] ?? ''),
            'image_attachment_id' => (int) ($course['image_attachment_id'] ?? 0),
            'program' => $program,
        ];

        if ($this->meetings !== null) {
            $meetingRows = $this->meetings->meetings($courseId);
            $settings = $this->meetings->courseSettings($courseId);

            if (is_array($meetingRows)) {
                $result['meetings'] = array_map([$this, 'participantMeeting'], $meetingRows);
                $nearest = $this->nearestMeeting($meetingRows);
                if ($nearest !== null) {
                    $result['nearest_meeting'] = $this->participantMeeting($nearest);
                }
            }

            if (is_array($settings) && !empty($settings['telegram_reference'])) {
                $result['telegram_reference'] = (string) $settings['telegram_reference'];
            }
        }

        if ($this->qa !== null) {
            $entries = $this->qa->entries($courseId);
            if (is_array($entries)) {
                $lessonMap = [];
                foreach ($lessons as $lesson) {
                    $lessonMap[(int) ($lesson['id'] ?? 0)] = $lesson;
                }
                $result['qa'] = [];
                foreach ($entries as $entry) {
                    if (($entry['status'] ?? '') === 'archived') {
                        continue;
                    }
                    $lesson = $lessonMap[(int) ($entry['lesson_id'] ?? 0)] ?? null;
                    $result['qa'][] = [
                        'public_id' => (string) ($entry['public_id'] ?? ''),
                        'question' => (string) ($entry['question'] ?? ''),
                        'answer' => (string) ($entry['answer'] ?? ''),
                        'position' => (int) ($entry['position'] ?? 0),
                        'lesson_public_id' => is_array($lesson) ? (string) ($lesson['public_id'] ?? '') : null,
                        'lesson_title' => is_array($lesson) ? (string) ($lesson['title'] ?? '') : null,
                    ];
                }
            }
        }

        return $result;
    }

    /** @return array<string, mixed>|\WP_Error */
    public function lesson(int $courseId, string $lessonPublicId): array|\WP_Error
    {
        try {
            $identifier = new Identifier($lessonPublicId);
        } catch (\InvalidArgumentException) {
            return $this->lessonNotFound();
        }

        $course = $this->course($courseId);
        $lessons = $this->courses->lessons($courseId);
        $materials = $this->courses->materials($courseId);

        if (is_wp_error($course) || is_wp_error($lessons) || is_wp_error($materials)) {
            return is_wp_error($course) ? $course : $this->readError();
        }

        $visible = array_values(array_filter(
            $lessons,
            static fn (array $item): bool => ($item['status'] ?? '') !== 'archived'
        ));
        $currentIndex = null;
        foreach ($visible as $index => $candidate) {
            if ((string) ($candidate['public_id'] ?? '') === $identifier->value()) {
                $currentIndex = $index;
                break;
            }
        }

        if ($currentIndex === null) {
            return $this->lessonNotFound();
        }

        $lesson = $visible[$currentIndex];
        $lessonId = (int) ($lesson['id'] ?? 0);
        $lesson['materials'] = array_values(array_map(
            static function (array $material): array {
                unset($material['id'], $material['lesson_id'], $material['archived_at']);
                return $material;
            },
            array_filter(
                $materials,
                static fn (array $material): bool => (int) ($material['lesson_id'] ?? 0) === $lessonId
                    && ($material['status'] ?? '') !== 'archived'
            )
        ));
        $lesson['previous'] = $currentIndex > 0 ? $this->navigationItem($visible[$currentIndex - 1]) : null;
        $lesson['next'] = isset($visible[$currentIndex + 1]) ? $this->navigationItem($visible[$currentIndex + 1]) : null;
        $lesson['program_lessons'] = array_map([$this, 'navigationItem'], $visible);
        $lesson['preview_tasks'] = [];

        if ($this->tasks !== null) {
            $tasks = $this->tasks->entries($courseId);
            if (is_array($tasks)) {
                foreach ($tasks as $task) {
                    if ((int) ($task['lesson_id'] ?? 0) !== $lessonId || ($task['status'] ?? '') === 'archived') {
                        continue;
                    }
                    $lesson['preview_tasks'][] = [
                        'public_id' => (string) ($task['public_id'] ?? ''),
                        'title' => (string) ($task['title'] ?? ''),
                        'description' => (string) ($task['description'] ?? ''),
                        'required' => !empty($task['is_required']),
                    ];
                }
            }
        }

        unset($lesson['id'], $lesson['course_id'], $lesson['section_id'], $lesson['status'], $lesson['archived_at']);
        $lesson['course'] = [
            'public_id' => (string) ($course['public_id'] ?? ''),
            'title' => (string) ($course['title'] ?? ''),
            'description' => (string) ($course['description'] ?? ''),
            'image_attachment_id' => (int) ($course['image_attachment_id'] ?? 0),
        ];

        return $lesson;
    }

    /** @return array{provider: string, reference: string, name: string, disposition: string}|\WP_Error */
    public function asset(
        int $courseId,
        string $lessonPublicId,
        string $kind,
        string $assetPublicId = ''
    ): array|\WP_Error {
        $lesson = $this->lesson($courseId, $lessonPublicId);
        if (is_wp_error($lesson)) {
            return $lesson;
        }

        if ($kind === 'video') {
            $provider = (string) ($lesson['video_provider'] ?? '');
            $reference = (string) ($lesson['video_reference'] ?? '');
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

        foreach ((array) ($lesson['materials'] ?? []) as $material) {
            if ((string) ($material['public_id'] ?? '') !== $assetPublicId) {
                continue;
            }
            return [
                'provider' => (string) ($material['storage_provider'] ?? ''),
                'reference' => (string) ($material['storage_reference'] ?? ''),
                'name' => (string) ($material['name'] ?? __('Materiał lekcji', 'am-toolkit')),
                'disposition' => 'attachment',
            ];
        }

        return $this->notFound();
    }

    /** @param list<array<string, mixed>> $sections @param list<array<string, mixed>> $lessons */
    private function program(array $sections, array $lessons): array
    {
        $grouped = [];
        foreach ($sections as $section) {
            $id = (int) ($section['id'] ?? 0);
            $grouped[$id] = [
                'public_id' => (string) ($section['public_id'] ?? ''),
                'title' => (string) ($section['title'] ?? ''),
                'description' => (string) ($section['description'] ?? ''),
                'position' => (int) ($section['position'] ?? 0),
                'lessons' => [],
            ];
        }

        $unsectioned = [];
        foreach ($lessons as $lesson) {
            $item = $this->navigationItem($lesson) + [
                'duration_seconds' => isset($lesson['duration_seconds']) ? (int) $lesson['duration_seconds'] : null,
                'is_required' => !empty($lesson['is_required']) ? 1 : 0,
            ];
            $sectionId = (int) ($lesson['section_id'] ?? 0);
            if ($sectionId > 0 && isset($grouped[$sectionId])) {
                $grouped[$sectionId]['lessons'][] = $item;
            } else {
                $unsectioned[] = $item;
            }
        }

        return [
            'version_number' => 0,
            'sections' => array_values($grouped),
            'lessons' => $unsectioned,
        ];
    }

    /** @param array<string, mixed> $lesson @return array<string, mixed> */
    private function navigationItem(array $lesson): array
    {
        return [
            'public_id' => (string) ($lesson['public_id'] ?? ''),
            'title' => (string) ($lesson['title'] ?? ''),
            'duration_seconds' => isset($lesson['duration_seconds']) ? (int) $lesson['duration_seconds'] : null,
            'is_required' => !empty($lesson['is_required']) ? 1 : 0,
            'progress_status' => 'no_record',
        ];
    }

    /** @param array<string, mixed> $meeting @return array<string, mixed> */
    private function participantMeeting(array $meeting): array
    {
        unset($meeting['id'], $meeting['course_id'], $meeting['archived_at']);
        return $meeting;
    }

    /** @param list<array<string, mixed>> $meetings @return array<string, mixed>|null */
    private function nearestMeeting(array $meetings): ?array
    {
        $now = current_time('mysql', true);
        $candidates = array_values(array_filter(
            $meetings,
            static fn (array $meeting): bool => in_array((string) ($meeting['status'] ?? ''), ['scheduled', 'rescheduled'], true)
                && (string) ($meeting['starts_at_utc'] ?? '') >= $now
        ));
        usort($candidates, static fn (array $left, array $right): int => strcmp(
            (string) ($left['starts_at_utc'] ?? ''),
            (string) ($right['starts_at_utc'] ?? '')
        ));
        return $candidates[0] ?? null;
    }

    private function notFound(): \WP_Error
    {
        return new \WP_Error('am_toolkit_course_preview_not_found', __('Nie znaleziono szkicu kursu do podglądu.', 'am-toolkit'));
    }

    private function lessonNotFound(): \WP_Error
    {
        return new \WP_Error('am_toolkit_course_preview_lesson_not_found', __('Nie znaleziono tej lekcji w szkicu kursu.', 'am-toolkit'));
    }

    private function readError(): \WP_Error
    {
        return new \WP_Error('am_toolkit_course_preview_read_failed', __('Nie udało się przygotować podglądu szkicu.', 'am-toolkit'));
    }
}
