<?php

namespace AMToolkit\Modules\Courses\Services;

use AMToolkit\Core\Diagnostics\DomainEvent;
use AMToolkit\Core\Diagnostics\RequestId;
use AMToolkit\Modules\Access\ActivityEventStore;
use AMToolkit\Modules\Courses\Contracts\CourseLessonTaskStore;
use AMToolkit\Modules\Courses\Domain\Identifier;
use AMToolkit\Modules\Courses\Domain\LessonTask;

defined('ABSPATH') || exit;

final class CourseLessonTaskService
{
    public function __construct(
        private CourseLessonTaskStore $store,
        private ActivityEventStore $events
    ) {
    }

    /** @return list<array<string, mixed>>|\WP_Error */
    public function entries(int $courseId): array|\WP_Error
    {
        return $courseId > 0 ? $this->store->tasksForCourse($courseId) : $this->invalid();
    }

    /** @param array<string, mixed> $input */
    public function save(array $input, int $actorId, ?string $requestId = null): int|\WP_Error
    {
        $publicId = trim((string) ($input['public_id'] ?? ''));

        try {
            $task = new LessonTask(
                max(0, (int) ($input['id'] ?? 0)),
                $publicId !== '' ? new Identifier($publicId) : null,
                max(0, (int) ($input['course_id'] ?? 0)),
                max(0, (int) ($input['lesson_id'] ?? 0)),
                trim((string) ($input['title'] ?? '')),
                trim((string) ($input['description'] ?? '')),
                max(0, (int) ($input['position'] ?? 0)),
                !empty($input['is_required']),
                sanitize_key((string) ($input['status'] ?? ''))
            );
        } catch (\Throwable) {
            return $this->invalid();
        }

        $savedId = $this->store->saveTask([
            'id' => $task->id(),
            'course_id' => $task->courseId(),
            'lesson_id' => $task->lessonId(),
            'title' => $task->title(),
            'description' => $task->description(),
            'position' => $task->position(),
            'is_required' => $task->required(),
            'status' => $task->status(),
        ]);

        if (is_wp_error($savedId)) {
            return $savedId;
        }

        $requestId = RequestId::normalize($requestId);
        $recorded = $this->record('course.lesson_task.updated', $task->courseId(), $actorId, [
            'task_id' => $savedId,
            'lesson_id' => $task->lessonId(),
            'required' => $task->required(),
            'status' => $task->status(),
            'position' => $task->position(),
        ], $requestId);

        return is_wp_error($recorded) ? $recorded : $savedId;
    }

    public function archive(int $taskId, int $courseId, int $actorId, ?string $requestId = null): bool|\WP_Error
    {
        if ($taskId <= 0 || $courseId <= 0) {
            return $this->invalid();
        }

        $archived = $this->store->archiveTask($taskId, $courseId);

        if (is_wp_error($archived)) {
            return $archived;
        }

        $requestId = RequestId::normalize($requestId);
        $recorded = $this->record(
            'course.lesson_task.archived',
            $courseId,
            $actorId,
            ['task_id' => $taskId],
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
        return new \WP_Error(
            'am_toolkit_lesson_task_invalid',
            __('Zadanie wymaga lekcji, nazwy oraz poprawnych ustawień publikacji.', 'am-toolkit')
        );
    }
}
