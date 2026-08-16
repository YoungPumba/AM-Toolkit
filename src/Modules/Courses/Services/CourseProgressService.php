<?php

namespace AMToolkit\Modules\Courses\Services;

use AMToolkit\Core\Diagnostics\DomainEvent;
use AMToolkit\Core\Diagnostics\RequestId;
use AMToolkit\Modules\Access\ActivityEventStore;
use AMToolkit\Modules\Courses\Contracts\CompletionRepository;
use AMToolkit\Modules\Courses\Contracts\CourseAccessPolicy;
use AMToolkit\Modules\Courses\Contracts\CourseProgressSourceStore;
use AMToolkit\Modules\Courses\Contracts\ProgressRepository;
use AMToolkit\Modules\Courses\Domain\CourseCompletion;
use AMToolkit\Modules\Courses\Domain\Identifier;
use AMToolkit\Modules\Courses\Domain\LessonCompletionRequirements;
use AMToolkit\Modules\Courses\Domain\LessonProgress;
use AMToolkit\Modules\Courses\Domain\ProgressStatus;
use AMToolkit\Modules\Courses\Domain\VideoIntervalSet;

defined('ABSPATH') || exit;

final class CourseProgressService
{
    private const TASK_KEY = 'task_acknowledged';

    private const MANUAL_COMPLETION_KEY = 'manual_completion';

    public function __construct(
        private CourseProgressSourceStore $sources,
        private ProgressRepository $progress,
        private CompletionRepository $completions,
        private CourseAccessPolicy $access,
        private ActivityEventStore $events
    ) {
    }

    /** @return array<string, mixed>|\WP_Error */
    public function lessonState(int $userId, string $coursePublicId, string $lessonPublicId): array|\WP_Error
    {
        $context = $this->authorizedContext($userId, $coursePublicId, $lessonPublicId);

        return is_wp_error($context) ? $context : $this->stateFromContext($userId, $context);
    }

    /**
     * @param list<array{0: int|float, 1: int|float}> $intervals
     * @return array<string, mixed>|\WP_Error
     */
    public function recordVideoCheckpoint(
        int $userId,
        string $coursePublicId,
        string $lessonPublicId,
        array $intervals,
        ?string $requestId = null
    ): array|\WP_Error {
        $context = $this->authorizedContext($userId, $coursePublicId, $lessonPublicId);

        if (is_wp_error($context)) {
            return $context;
        }

        if (count($intervals) > 100) {
            return $this->invalidRequest(__('Checkpoint zawiera zbyt wiele przedziałów.', 'am-toolkit'));
        }

        $duration = (int) ($context['duration_seconds'] ?? 0);

        try {
            $set = new VideoIntervalSet($intervals, (float) $duration);
        } catch (\InvalidArgumentException) {
            return $this->invalidRequest(__('Nie udało się zweryfikować czasu nagrania.', 'am-toolkit'));
        }

        if ($set->intervals() === []) {
            return $this->invalidRequest(__('Checkpoint nie zawiera obejrzanego fragmentu.', 'am-toolkit'));
        }

        $requestId = RequestId::normalize($requestId);
        $created = $this->sources->recordVideoCheckpoint(
            $userId,
            (int) $context['course_id'],
            (int) $context['lesson_id'],
            (int) $context['content_version'],
            $requestId,
            $set->intervals(),
            $duration,
            $set->coveredSeconds(),
            current_time('mysql', true)
        );

        if (is_wp_error($created)) {
            return $created;
        }

        if (!$this->markStarted($userId, $context, $requestId)) {
            return $this->writeError();
        }

        return $this->withRequestId(
            $this->evaluateAndReturn($userId, $context, $requestId, 'video_requirement_met'),
            $requestId
        );
    }

    /** @return array<string, mixed>|\WP_Error */
    public function acknowledgeTask(
        int $userId,
        string $coursePublicId,
        string $lessonPublicId,
        ?string $requestId = null
    ): array|\WP_Error {
        $context = $this->authorizedContext($userId, $coursePublicId, $lessonPublicId);

        if (is_wp_error($context)) {
            return $context;
        }

        $requirements = $this->requirements($context);

        if (!$requirements->taskRequired()) {
            return $this->invalidRequest(__('Ta lekcja nie wymaga potwierdzenia zadania.', 'am-toolkit'));
        }

        $requestId = RequestId::normalize($requestId);
        $now = current_time('mysql', true);
        $recorded = $this->sources->recordRequirementCompletion(
            $userId,
            (int) $context['course_id'],
            (int) $context['lesson_id'],
            (int) $context['content_version'],
            self::TASK_KEY,
            'participant_acknowledged',
            $requestId,
            $now
        );

        if (is_wp_error($recorded)) {
            return $recorded;
        }

        if (!$this->markStarted($userId, $context, $requestId)) {
            return $this->writeError();
        }

        $this->recordEvent(
            'course.lesson.task_acknowledged.' . $userId . '.' . (int) $context['lesson_id']
                . '.' . (int) $context['content_version'],
            'course.lesson.task_acknowledged',
            $userId,
            (int) $context['lesson_id'],
            ['course_id' => (int) $context['course_id']],
            $now,
            $requestId
        );

        return $this->withRequestId(
            $this->evaluateAndReturn($userId, $context, $requestId, 'requirements_met'),
            $requestId
        );
    }

    /** @return array<string, mixed>|\WP_Error */
    public function completeManually(
        int $userId,
        string $coursePublicId,
        string $lessonPublicId,
        ?string $requestId = null
    ): array|\WP_Error {
        $context = $this->authorizedContext($userId, $coursePublicId, $lessonPublicId);

        if (is_wp_error($context)) {
            return $context;
        }

        if ($this->requirements($context)->hasAutomaticRequirements()) {
            return $this->invalidRequest(__('Ta lekcja ma wymagania, których nie można pominąć.', 'am-toolkit'));
        }

        $requestId = RequestId::normalize($requestId);
        $recorded = $this->sources->recordRequirementCompletion(
            $userId,
            (int) $context['course_id'],
            (int) $context['lesson_id'],
            (int) $context['content_version'],
            self::MANUAL_COMPLETION_KEY,
            'participant_marked_complete',
            $requestId,
            current_time('mysql', true)
        );

        if (is_wp_error($recorded)) {
            return $recorded;
        }

        if (!$this->completeLesson($userId, $context, 'participant_marked_complete', $requestId)) {
            return $this->writeError();
        }

        return $this->withRequestId($this->stateFromContext($userId, $context), $requestId);
    }

    /** @return array<string, mixed>|\WP_Error */
    public function rebuildLessonProgress(
        int $userId,
        string $coursePublicId,
        string $lessonPublicId,
        ?string $requestId = null
    ): array|\WP_Error {
        $context = $this->authorizedContext($userId, $coursePublicId, $lessonPublicId);

        if (is_wp_error($context)) {
            return $context;
        }

        $requestId = RequestId::normalize($requestId);
        $state = $this->stateFromContext($userId, $context);

        if (is_wp_error($state)) {
            return $state;
        }

        $manualSource = $this->sources->hasRequirementCompletion(
            $userId,
            (int) $context['lesson_id'],
            (int) $context['content_version'],
            self::MANUAL_COMPLETION_KEY
        );

        if (is_wp_error($manualSource)) {
            return $manualSource;
        }

        if ($manualSource && !$this->requirements($context)->hasAutomaticRequirements()) {
            $result = $this->completeLesson($userId, $context, 'rebuilt_from_manual_source', $requestId)
                ? $this->stateFromContext($userId, $context)
                : $this->writeError();

            return $this->withRequestId($result, $requestId);
        }

        if ((float) $state['watched_percent'] > 0 || !empty($state['task_completed'])) {
            if (!$this->markStarted($userId, $context, $requestId)) {
                return $this->writeError();
            }

            return $this->withRequestId(
                $this->evaluateAndReturn($userId, $context, $requestId, 'rebuilt_from_sources'),
                $requestId
            );
        }

        return $this->withRequestId($state, $requestId);
    }

    /** @param array<string, mixed> $context */
    private function evaluateAndReturn(
        int $userId,
        array $context,
        string $requestId,
        string $completionSource
    ): array|\WP_Error {
        $state = $this->stateFromContext($userId, $context);

        if (is_wp_error($state)) {
            return $state;
        }

        if (!empty($state['lesson_completed'])) {
            if (!$this->completeCourseIfEligible($userId, $context, $requestId)) {
                return $this->writeError();
            }

            return $this->stateFromContext($userId, $context);
        }

        if (
            $this->requirements($context)->isSatisfied(
                (float) $state['watched_percent'],
                !empty($state['task_completed'])
            )
            && !$this->completeLesson($userId, $context, $completionSource, $requestId)
        ) {
            return $this->writeError();
        }

        return $this->stateFromContext($userId, $context);
    }

    /** @param array<string, mixed> $context */
    private function completeLesson(
        int $userId,
        array $context,
        string $completionSource,
        string $requestId
    ): bool {
        $existing = $this->progress->find(
            $userId,
            (int) $context['course_id'],
            (int) $context['lesson_id']
        );

        if (
            $existing !== null
            && $existing->status() === ProgressStatus::COMPLETED
            && $existing->contentVersion() === (int) $context['content_version']
        ) {
            $this->recordLessonCompletedEvent(
                $userId,
                $context,
                (string) $existing->completionSource(),
                (string) $existing->completedAt(),
                $requestId
            );

            return $this->completeCourseIfEligible($userId, $context, $requestId);
        }

        $now = current_time('mysql', true);
        $saved = $this->progress->save(new LessonProgress(
            0,
            $userId,
            (int) $context['course_id'],
            (int) $context['lesson_id'],
            ProgressStatus::COMPLETED,
            (int) $context['content_version'],
            $completionSource,
            $now,
            $requestId
        ));

        if (!$saved) {
            return false;
        }

        $this->recordLessonCompletedEvent($userId, $context, $completionSource, $now, $requestId);

        return $this->completeCourseIfEligible($userId, $context, $requestId);
    }

    /** @param array<string, mixed> $context */
    private function recordLessonCompletedEvent(
        int $userId,
        array $context,
        string $completionSource,
        string $completedAt,
        string $requestId
    ): void {
        $this->recordEvent(
            'course.lesson.completed.' . $userId . '.' . (int) $context['lesson_id']
                . '.' . (int) $context['content_version'],
            'course.lesson.completed',
            $userId,
            (int) $context['lesson_id'],
            [
                'course_id' => (int) $context['course_id'],
                'content_version' => (int) $context['content_version'],
                'completion_source' => $completionSource,
            ],
            $completedAt,
            $requestId
        );
    }

    /** @param array<string, mixed> $context */
    private function completeCourseIfEligible(int $userId, array $context, string $requestId): bool
    {
        $requiredIds = array_values(array_map('intval', (array) $context['required_lesson_ids']));
        $completedIds = $this->progress->completedLessonIds(
            $userId,
            (int) $context['course_id'],
            $requiredIds
        );

        if (array_diff($requiredIds, $completedIds) !== []) {
            return true;
        }

        $courseId = (int) $context['course_id'];
        $programId = (int) $context['program_version_id'];

        $existing = $this->completions->find($userId, $courseId, $programId);

        if ($existing !== null) {
            $this->recordCourseCompletedEvent($existing, $requestId);

            return true;
        }

        $now = current_time('mysql', true);
        $completion = new CourseCompletion(
            0,
            $userId,
            $courseId,
            $programId,
            $requiredIds,
            'required_lessons_completed',
            $now,
            $requestId
        );

        if (!$this->completions->record($completion)) {
            return false;
        }

        $this->recordCourseCompletedEvent($completion, $requestId);

        return true;
    }

    private function recordCourseCompletedEvent(CourseCompletion $completion, string $requestId): void
    {
        $this->recordEvent(
            'course.completed.' . $completion->userId() . '.' . $completion->programVersionId(),
            'course.completed',
            $completion->userId(),
            $completion->courseId(),
            [
                'program_version_id' => $completion->programVersionId(),
                'requirements_hash' => $completion->requirementsHash(),
                'required_lesson_count' => count($completion->requiredLessonIds()),
            ],
            $completion->completedAt(),
            $requestId,
            'course'
        );
    }

    /** @param array<string, mixed> $context */
    private function markStarted(int $userId, array $context, string $requestId): bool
    {
        return $this->progress->save(new LessonProgress(
            0,
            $userId,
            (int) $context['course_id'],
            (int) $context['lesson_id'],
            ProgressStatus::STARTED,
            (int) $context['content_version'],
            null,
            null,
            $requestId
        ));
    }

    /** @param array<string, mixed> $context @return array<string, mixed>|\WP_Error */
    private function stateFromContext(int $userId, array $context): array|\WP_Error
    {
        $duration = (int) ($context['duration_seconds'] ?? 0);
        $watched = 0.0;

        if ($duration > 0) {
            $rows = $this->sources->videoCheckpointIntervals(
                $userId,
                (int) $context['lesson_id'],
                (int) $context['content_version']
            );

            if (is_wp_error($rows)) {
                return $rows;
            }

            $sets = [];

            foreach ($rows as $intervals) {
                $sets[] = new VideoIntervalSet($intervals, (float) $duration);
            }

            $watched = VideoIntervalSet::combine($sets, (float) $duration)->percentage((float) $duration);
        }

        $taskCompleted = $this->sources->hasRequirementCompletion(
            $userId,
            (int) $context['lesson_id'],
            (int) $context['content_version'],
            self::TASK_KEY
        );

        if (is_wp_error($taskCompleted)) {
            return $taskCompleted;
        }

        $lessonProgress = $this->progress->find(
            $userId,
            (int) $context['course_id'],
            (int) $context['lesson_id']
        );
        $lessonCompleted = $lessonProgress !== null
            && $lessonProgress->contentVersion() === (int) $context['content_version']
            && $lessonProgress->status() === ProgressStatus::COMPLETED;
        $requiredIds = array_values(array_map('intval', (array) $context['required_lesson_ids']));
        $completedIds = $this->progress->completedLessonIds(
            $userId,
            (int) $context['course_id'],
            $requiredIds
        );
        $courseCompleted = $this->completions->find(
            $userId,
            (int) $context['course_id'],
            (int) $context['program_version_id']
        ) !== null;
        $requirements = $this->requirements($context);

        return [
            'status' => $lessonCompleted
                ? ProgressStatus::COMPLETED
                : ($lessonProgress === null ? 'no_record' : ProgressStatus::STARTED),
            'lesson_completed' => $lessonCompleted,
            'watched_percent' => $watched,
            'video_percent_required' => $requirements->videoPercent(),
            'task_required' => $requirements->taskRequired(),
            'task_completed' => $taskCompleted,
            'manual_completion_available' => !$requirements->hasAutomaticRequirements(),
            'course_completed' => $courseCompleted,
            'course_progress_percent' => $courseCompleted || $requiredIds === []
                ? 100
                : (int) floor((count(array_intersect($requiredIds, $completedIds)) / count($requiredIds)) * 100),
        ];
    }

    /** @param array<string, mixed> $context */
    private function requirements(array $context): LessonCompletionRequirements
    {
        return LessonCompletionRequirements::fromArray(
            is_array($context['completion_requirements'] ?? null)
                ? $context['completion_requirements']
                : []
        );
    }

    /** @return array<string, mixed>|\WP_Error */
    private function authorizedContext(int $userId, string $coursePublicId, string $lessonPublicId): array|\WP_Error
    {
        if ($userId <= 0) {
            return $this->notAvailable();
        }

        try {
            $courseId = new Identifier($coursePublicId);
            $lessonId = new Identifier($lessonPublicId);
        } catch (\InvalidArgumentException) {
            return $this->notAvailable();
        }

        $context = $this->sources->lessonContext($courseId, $lessonId);

        if (is_wp_error($context)) {
            return $context;
        }

        if ($context === null || !$this->access->userCanAccess($userId, (int) $context['course_id'])) {
            return $this->notAvailable();
        }

        return $context;
    }

    /** @param array<string, mixed> $payload */
    private function recordEvent(
        string $eventKey,
        string $eventType,
        int $userId,
        int $objectId,
        array $payload,
        string $occurredAt,
        string $requestId,
        string $objectType = 'lesson'
    ): void {
        $this->events->record(DomainEvent::create(
            $eventKey,
            $eventType,
            $userId,
            $userId,
            $objectType,
            $objectId,
            $payload,
            $occurredAt,
            $requestId
        ));
    }

    private function notAvailable(): \WP_Error
    {
        return new \WP_Error(
            'am_toolkit_course_progress_not_available',
            __('Nie można zapisać postępu dla tej lekcji.', 'am-toolkit')
        );
    }

    private function invalidRequest(string $message): \WP_Error
    {
        return new \WP_Error('am_toolkit_course_progress_invalid_request', $message);
    }

    private function writeError(): \WP_Error
    {
        return new \WP_Error(
            'am_toolkit_course_progress_write_failed',
            __('Nie udało się zapisać postępu kursu.', 'am-toolkit')
        );
    }

    /** @return array<string, mixed>|\WP_Error */
    private function withRequestId(array|\WP_Error $result, string $requestId): array|\WP_Error
    {
        if (!is_wp_error($result)) {
            $result['request_id'] = $requestId;
        }

        return $result;
    }
}
