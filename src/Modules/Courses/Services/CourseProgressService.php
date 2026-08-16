<?php

namespace AMToolkit\Modules\Courses\Services;

use AMToolkit\Core\Diagnostics\DomainEvent;
use AMToolkit\Core\Diagnostics\RequestId;
use AMToolkit\Modules\Access\ActivityEventStore;
use AMToolkit\Modules\Courses\Contracts\CompletionRepository;
use AMToolkit\Modules\Courses\Contracts\CourseAccessPolicy;
use AMToolkit\Modules\Courses\Contracts\CourseLessonTaskStore;
use AMToolkit\Modules\Courses\Contracts\CourseProgressDiagnostics;
use AMToolkit\Modules\Courses\Contracts\CourseProgressSourceStore;
use AMToolkit\Modules\Courses\Contracts\ProgressRepository;
use AMToolkit\Modules\Courses\Domain\CourseCompletion;
use AMToolkit\Modules\Courses\Domain\Identifier;
use AMToolkit\Modules\Courses\Domain\LessonCompletionRequirements;
use AMToolkit\Modules\Courses\Domain\LessonProgress;
use AMToolkit\Modules\Courses\Domain\ProgressStatus;
use AMToolkit\Modules\Courses\Domain\VideoIntervalSet;

defined('ABSPATH') || exit;

final class CourseProgressService implements CourseProgressDiagnostics
{
    private const TASK_KEY = 'task_acknowledged';

    private const MANUAL_COMPLETION_KEY = 'manual_completion';

    public function __construct(
        private CourseProgressSourceStore $sources,
        private ProgressRepository $progress,
        private CompletionRepository $completions,
        private CourseAccessPolicy $access,
        private ActivityEventStore $events,
        private ?CourseLessonTaskStore $tasks = null
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
    public function setLessonTask(
        int $userId,
        string $coursePublicId,
        string $lessonPublicId,
        string $taskPublicId,
        bool $completed,
        ?string $requestId = null
    ): array|\WP_Error {
        if ($this->tasks === null) {
            return $this->invalidRequest(__('Checklista zadań jest wyłączona.', 'am-toolkit'));
        }

        $context = $this->authorizedContext($userId, $coursePublicId, $lessonPublicId);

        if (is_wp_error($context)) {
            return $context;
        }

        try {
            $taskId = new Identifier($taskPublicId);
        } catch (\InvalidArgumentException) {
            return $this->invalidRequest(__('Nieprawidłowe zadanie lekcji.', 'am-toolkit'));
        }

        $before = $this->stateFromContext($userId, $context);

        if (is_wp_error($before)) {
            return $before;
        }

        if (!empty($before['lesson_completed'])) {
            return $this->invalidRequest(__('Ukończona lekcja ma zamkniętą checklistę.', 'am-toolkit'));
        }

        $requestId = RequestId::normalize($requestId);
        $now = current_time('mysql', true);
        $storedTaskId = $this->tasks->setTaskCompletion(
            $userId,
            (int) $context['course_id'],
            (int) $context['lesson_id'],
            $taskId,
            $completed,
            $requestId,
            $now
        );

        if (is_wp_error($storedTaskId)) {
            return $storedTaskId;
        }

        if (!$this->markStarted($userId, $context, $requestId)) {
            return $this->writeError();
        }

        $eventType = $completed ? 'course.lesson_task.completed' : 'course.lesson_task.reopened';
        $this->recordEvent(
            $eventType . '.' . $userId . '.' . $storedTaskId . '.' . $requestId,
            $eventType,
            $userId,
            (int) $context['lesson_id'],
            [
                'course_id' => (int) $context['course_id'],
                'task_id' => $storedTaskId,
                'completed' => $completed,
            ],
            $now,
            $requestId
        );

        return $this->withRequestId(
            $this->evaluateAndReturn($userId, $context, $requestId, 'checklist_requirements_met'),
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

        $hasCheckedTask = array_filter(
            (array) ($state['lesson_tasks'] ?? []),
            static fn (array $task): bool => !empty($task['completed'])
        ) !== [];

        if ((float) $state['watched_percent'] > 0 || !empty($state['task_completed']) || $hasCheckedTask) {
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
            $this->requirementsSatisfied(
                $this->requirements($context),
                (float) $state['watched_percent'],
                !empty($state['task_completed']),
                (array) ($state['lesson_tasks'] ?? [])
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

        $lessonTasks = $this->lessonTaskState($userId, (int) $context['lesson_id']);

        if (is_wp_error($lessonTasks)) {
            return $lessonTasks;
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
        $resumeAt = $lessonCompleted
            ? 0.0
            : $this->sources->latestVideoPosition(
                $userId,
                (int) $context['lesson_id'],
                (int) $context['content_version']
            );

        if (is_wp_error($resumeAt)) {
            return $resumeAt;
        }

        $lessonProgressPercent = $this->lessonProgressPercent(
            $requirements,
            $watched,
            $taskCompleted,
            $lessonTasks,
            $lessonCompleted
        );
        $hasChecklistRequirements = array_filter(
            $lessonTasks,
            static fn (array $task): bool => !empty($task['is_required'])
        ) !== [];
        $hasAutomaticRequirements = $requirements->videoPercent() > 0
            || ($lessonTasks !== [] ? $hasChecklistRequirements : $requirements->taskRequired());

        return [
            'status' => $lessonCompleted
                ? ProgressStatus::COMPLETED
                : ($lessonProgress === null ? 'no_record' : ProgressStatus::STARTED),
            'lesson_completed' => $lessonCompleted,
            'watched_percent' => $watched,
            'video_percent_required' => $requirements->videoPercent(),
            'task_required' => $lessonTasks === [] && $requirements->taskRequired(),
            'task_completed' => $taskCompleted,
            'lesson_tasks' => $lessonTasks,
            'manual_completion_available' => !$hasAutomaticRequirements,
            'resume_at_seconds' => min((float) $duration, max(0.0, $resumeAt)),
            'lesson_progress_percent' => $lessonProgressPercent,
            'course_completed' => $courseCompleted,
            'course_progress_percent' => $courseCompleted || $requiredIds === []
                ? 100
                : (int) floor((count(array_intersect($requiredIds, $completedIds)) / count($requiredIds)) * 100),
        ];
    }

    private function lessonProgressPercent(
        LessonCompletionRequirements $requirements,
        float $watchedPercent,
        bool $taskCompleted,
        array $lessonTasks,
        bool $lessonCompleted
    ): int {
        if ($lessonCompleted) {
            return 100;
        }

        $requirementProgress = [];
        $videoPercent = $requirements->videoPercent();

        if ($videoPercent > 0) {
            $requirementProgress[] = min(1.0, max(0.0, $watchedPercent) / $videoPercent);
        }

        if ($lessonTasks !== []) {
            foreach ($lessonTasks as $task) {
                if (!empty($task['is_required'])) {
                    $requirementProgress[] = !empty($task['completed']) ? 1.0 : 0.0;
                }
            }
        } elseif ($requirements->taskRequired()) {
            $requirementProgress[] = $taskCompleted ? 1.0 : 0.0;
        }

        if ($requirementProgress === []) {
            return 0;
        }

        return (int) floor((array_sum($requirementProgress) / count($requirementProgress)) * 100);
    }

    /** @param list<array<string, mixed>> $lessonTasks */
    private function requirementsSatisfied(
        LessonCompletionRequirements $requirements,
        float $watchedPercent,
        bool $legacyTaskCompleted,
        array $lessonTasks
    ): bool {
        $hasAutomaticRequirement = $requirements->videoPercent() > 0;

        if ($requirements->videoPercent() > 0 && $watchedPercent < $requirements->videoPercent()) {
            return false;
        }

        if ($lessonTasks === []) {
            return $requirements->taskRequired()
                ? $legacyTaskCompleted
                : $hasAutomaticRequirement;
        }

        foreach ($lessonTasks as $task) {
            if (empty($task['is_required'])) {
                continue;
            }

            $hasAutomaticRequirement = true;

            if (empty($task['completed'])) {
                return false;
            }
        }

        return $hasAutomaticRequirement;
    }

    /** @return list<array<string, mixed>>|\WP_Error */
    private function lessonTaskState(int $userId, int $lessonId): array|\WP_Error
    {
        if ($this->tasks === null) {
            return [];
        }

        $tasks = $this->tasks->publishedTasksForLesson($lessonId);

        if (is_wp_error($tasks)) {
            return $tasks;
        }

        $completedIds = $this->tasks->completedTaskIds($userId, $lessonId);

        if (is_wp_error($completedIds)) {
            return $completedIds;
        }

        return array_map(
            static fn (array $task): array => [
                'public_id' => (string) ($task['public_id'] ?? ''),
                'title' => (string) ($task['title'] ?? ''),
                'description' => (string) ($task['description'] ?? ''),
                'position' => (int) ($task['position'] ?? 0),
                'is_required' => !empty($task['is_required']),
                'completed' => in_array((int) ($task['id'] ?? 0), $completedIds, true),
            ],
            $tasks
        );
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
