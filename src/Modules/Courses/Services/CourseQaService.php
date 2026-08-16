<?php

namespace AMToolkit\Modules\Courses\Services;

use AMToolkit\Core\Diagnostics\DomainEvent;
use AMToolkit\Core\Diagnostics\RequestId;
use AMToolkit\Modules\Access\ActivityEventStore;
use AMToolkit\Modules\Courses\Contracts\CourseQaStore;
use AMToolkit\Modules\Courses\Contracts\DraftCourseResourceDeletionStore;
use AMToolkit\Modules\Courses\Domain\CourseQaEntry;
use AMToolkit\Modules\Courses\Domain\Identifier;

defined('ABSPATH') || exit;

final class CourseQaService
{
    public function __construct(
        private CourseQaStore $store,
        private ActivityEventStore $events
    ) {
    }

    public function entries(int $courseId): array|\WP_Error
    {
        return $courseId > 0 ? $this->store->entriesForCourse($courseId) : $this->invalid();
    }

    /** @param array<string, mixed> $input */
    public function save(array $input, int $actorId, ?string $requestId = null): int|\WP_Error
    {
        $entryId = max(0, (int) ($input['id'] ?? 0));
        $courseId = max(0, (int) ($input['course_id'] ?? 0));
        $lessonId = max(0, (int) ($input['lesson_id'] ?? 0));
        $question = trim((string) ($input['question'] ?? ''));
        $answer = trim((string) ($input['answer'] ?? ''));
        $position = max(0, (int) ($input['position'] ?? 0));
        $status = sanitize_key((string) ($input['status'] ?? ''));
        $publicId = trim((string) ($input['public_id'] ?? ''));

        try {
            $entry = new CourseQaEntry(
                $entryId,
                $publicId !== '' ? new Identifier($publicId) : null,
                $courseId,
                $lessonId > 0 ? $lessonId : null,
                $question,
                $answer,
                $position,
                $status
            );
        } catch (\Throwable) {
            return $this->invalid();
        }

        $savedId = $this->store->saveEntry([
            'id' => $entry->id(),
            'course_id' => $entry->courseId(),
            'lesson_id' => $entry->lessonId(),
            'question' => $entry->question(),
            'answer' => $entry->answer(),
            'position' => $entry->position(),
            'status' => $entry->status(),
        ]);
        if (is_wp_error($savedId)) {
            return $savedId;
        }

        $requestId = RequestId::normalize($requestId);
        $recorded = $this->record('course.qa.updated', $courseId, $actorId, [
            'qa_entry_id' => $savedId,
            'lesson_id' => $entry->lessonId(),
            'status' => $entry->status(),
            'position' => $entry->position(),
        ], $requestId);

        return is_wp_error($recorded) ? $recorded : $savedId;
    }

    public function archive(int $entryId, int $courseId, int $actorId, ?string $requestId = null): bool|\WP_Error
    {
        if ($entryId <= 0 || $courseId <= 0) {
            return $this->invalid();
        }

        $archived = $this->store->archiveEntry($entryId, $courseId);
        if (is_wp_error($archived)) {
            return $archived;
        }

        $requestId = RequestId::normalize($requestId);
        $recorded = $this->record('course.qa.archived', $courseId, $actorId, ['qa_entry_id' => $entryId], $requestId);

        return is_wp_error($recorded) ? $recorded : true;
    }

    public function deleteDraft(int $entryId, int $courseId, int $actorId, ?string $requestId = null): bool|\WP_Error
    {
        if ($entryId <= 0 || $courseId <= 0 || !$this->store instanceof DraftCourseResourceDeletionStore) {
            return $this->invalid();
        }

        $deleted = $this->store->deleteDraftResource('qa', $entryId, $courseId);
        if (is_wp_error($deleted)) {
            return $deleted;
        }

        $requestId = RequestId::normalize($requestId);
        $recorded = $this->record(
            'course.qa.deleted',
            $courseId,
            $actorId,
            ['qa_entry_id' => $entryId, 'deletion' => 'permanent_unused_draft'],
            $requestId
        );

        return is_wp_error($recorded) ? $recorded : true;
    }

    /** @param array<string, mixed> $payload */
    private function record(string $type, int $courseId, int $actorId, array $payload, string $requestId): bool|\WP_Error
    {
        $result = $this->events->record(DomainEvent::create(
            $type . ':' . $courseId . ':' . $requestId,
            $type,
            0,
            max(0, $actorId),
            'course',
            $courseId,
            $payload,
            current_time('mysql', true),
            $requestId
        ));

        return is_wp_error($result) ? $result : true;
    }

    private function invalid(): \WP_Error
    {
        return new \WP_Error('am_toolkit_course_qa_invalid', __('Pytanie i odpowiedź są wymagane, a pozostałe dane muszą być poprawne.', 'am-toolkit'));
    }
}
