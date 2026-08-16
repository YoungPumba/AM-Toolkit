<?php

declare(strict_types=1);

namespace AMToolkit\Tests\Unit;

use AMToolkit\Core\Diagnostics\ActivityEventQuery;
use AMToolkit\Core\Diagnostics\DomainEvent;
use AMToolkit\Modules\Access\ActivityEventStore;
use AMToolkit\Modules\Courses\Contracts\CompletionRepository;
use AMToolkit\Modules\Courses\Contracts\CourseAccessPolicy;
use AMToolkit\Modules\Courses\Contracts\CourseLessonTaskStore;
use AMToolkit\Modules\Courses\Contracts\CourseProgressSourceStore;
use AMToolkit\Modules\Courses\Contracts\ProgressRepository;
use AMToolkit\Modules\Courses\Domain\CourseCompletion;
use AMToolkit\Modules\Courses\Domain\Identifier;
use AMToolkit\Modules\Courses\Domain\LessonProgress;
use AMToolkit\Modules\Courses\Domain\ProgressStatus;
use AMToolkit\Modules\Courses\Services\CourseProgressService;
use PHPUnit\Framework\TestCase;

final class CourseProgressServiceTest extends TestCase
{
    private CourseProgressSourceStoreFake $sources;

    private ProgressRepositoryFake $progress;

    private CompletionRepositoryFake $completions;

    private ActivityEventStoreFake $events;

    private CourseProgressService $service;

    protected function setUp(): void
    {
        $this->sources = new CourseProgressSourceStoreFake();
        $this->progress = new ProgressRepositoryFake();
        $this->completions = new CompletionRepositoryFake();
        $this->events = new ActivityEventStoreFake();
        $this->service = new CourseProgressService(
            $this->sources,
            $this->progress,
            $this->completions,
            new CourseAccessPolicyFake(),
            $this->events
        );
    }

    public function testAggregatesDevicesCompletesLessonAndSnapshotsCourseOnlyOnce(): void
    {
        $first = $this->service->recordVideoCheckpoint(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID,
            [[0, 50]],
            'AM-20260816-AAAAAAAAAAAA'
        );
        $duplicate = $this->service->recordVideoCheckpoint(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID,
            [[0, 50]],
            'AM-20260816-AAAAAAAAAAAA'
        );
        $secondDevice = $this->service->recordVideoCheckpoint(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID,
            [[40, 90]],
            'AM-20260816-BBBBBBBBBBBB'
        );

        self::assertSame(50.0, $first['watched_percent']);
        self::assertSame('AM-20260816-AAAAAAAAAAAA', $first['request_id']);
        self::assertSame(50.0, $duplicate['watched_percent']);
        self::assertSame(90.0, $secondDevice['watched_percent']);
        self::assertSame(90.0, $secondDevice['resume_at_seconds']);
        self::assertFalse($secondDevice['lesson_completed']);
        self::assertSame(50, $secondDevice['lesson_progress_percent']);
        self::assertCount(2, $this->sources->checkpoints);

        $task = $this->service->acknowledgeTask(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID,
            'AM-20260816-CCCCCCCCCCCC'
        );

        self::assertTrue($task['lesson_completed']);
        self::assertSame(0.0, $task['resume_at_seconds']);
        self::assertFalse($task['course_completed']);
        self::assertSame(100, $task['lesson_progress_percent']);
        self::assertSame(50, $task['course_progress_percent']);

        $this->progress->save(new LessonProgress(
            0,
            7,
            4,
            11,
            ProgressStatus::COMPLETED,
            1,
            'requirements_met',
            '2026-08-16 10:00:00'
        ));
        $finished = $this->service->acknowledgeTask(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID,
            'AM-20260816-DDDDDDDDDDDD'
        );
        $again = $this->service->acknowledgeTask(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID,
            'AM-20260816-EEEEEEEEEEEE'
        );

        self::assertTrue($finished['course_completed']);
        self::assertTrue($again['course_completed']);
        self::assertCount(1, $this->completions->items);
        self::assertSame([10, 11], $this->completions->items[0]->requiredLessonIds());
    }

    public function testLessonProgressDoesNotReuseCourseProgress(): void
    {
        $this->progress->save(new LessonProgress(
            0,
            7,
            4,
            11,
            ProgressStatus::COMPLETED,
            1,
            'requirements_met',
            '2026-08-16 10:00:00'
        ));

        $empty = $this->service->lessonState(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID
        );

        self::assertSame(0, $empty['lesson_progress_percent']);
        self::assertSame(50, $empty['course_progress_percent']);

        $video = $this->service->recordVideoCheckpoint(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID,
            [[0, 40]],
            'AM-20260816-222222222222'
        );

        self::assertSame(25, $video['lesson_progress_percent']);
        self::assertSame(50, $video['course_progress_percent']);

        $task = $this->service->acknowledgeTask(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID,
            'AM-20260816-333333333333'
        );

        self::assertFalse($task['lesson_completed']);
        self::assertSame(75, $task['lesson_progress_percent']);
        self::assertSame(50, $task['course_progress_percent']);
    }

    public function testEmptyManualVideoAndTaskLessonsExposeTheirOwnProgress(): void
    {
        $this->sources->completionRequirements = [];
        $manual = $this->service->lessonState(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID
        );
        self::assertSame(0, $manual['lesson_progress_percent']);
        self::assertTrue($manual['manual_completion_available']);

        $this->sources->completionRequirements = ['video_percent' => 80];
        $video = $this->service->recordVideoCheckpoint(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID,
            [[0, 40]],
            'AM-20260816-444444444444'
        );
        self::assertSame(50, $video['lesson_progress_percent']);

        $this->sources->checkpoints = [];
        $this->progress->items = [];
        $this->sources->completionRequirements = ['task_required' => true];
        $task = $this->service->acknowledgeTask(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID,
            'AM-20260816-555555555555'
        );
        self::assertTrue($task['lesson_completed']);
        self::assertSame(100, $task['lesson_progress_percent']);
    }

    public function testManualCompletionCannotBypassConfiguredRequirements(): void
    {
        $result = $this->service->completeManually(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID
        );

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('am_toolkit_course_progress_invalid_request', $result->get_error_code());
    }

    public function testAccessIsCheckedBeforeProgressIsExposed(): void
    {
        $service = new CourseProgressService(
            $this->sources,
            $this->progress,
            $this->completions,
            new CourseAccessPolicyFake(false),
            $this->events
        );
        $result = $service->lessonState(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID
        );

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('am_toolkit_course_progress_not_available', $result->get_error_code());
    }

    public function testManualCompletionCanBeRebuiltFromItsImmutableSource(): void
    {
        $this->sources->completionRequirements = [];
        $completed = $this->service->completeManually(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID,
            'AM-20260816-FFFFFFFFFFFF'
        );

        self::assertIsArray($completed);
        self::assertTrue($completed['lesson_completed']);

        $this->progress->items = [];
        $rebuilt = $this->service->rebuildLessonProgress(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID,
            'AM-20260816-111111111111'
        );

        self::assertIsArray($rebuilt);
        self::assertTrue($rebuilt['lesson_completed']);
    }

    public function testChecklistUsesStableItemsAndCanBeUncheckedBeforeLessonCompletion(): void
    {
        $this->sources->completionRequirements = [];
        $tasks = new CourseLessonTaskStoreFake();
        $this->service = new CourseProgressService(
            $this->sources,
            $this->progress,
            $this->completions,
            new CourseAccessPolicyFake(),
            $this->events,
            $tasks
        );

        $initial = $this->service->lessonState(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID
        );
        self::assertSame(0, $initial['lesson_progress_percent']);
        self::assertFalse($initial['manual_completion_available']);

        $first = $this->service->setLessonTask(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID,
            CourseLessonTaskStoreFake::FIRST_PUBLIC_ID,
            true,
            'AM-20260816-121212121212'
        );
        self::assertSame(50, $first['lesson_progress_percent']);
        self::assertFalse($first['lesson_completed']);

        $unchecked = $this->service->setLessonTask(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID,
            CourseLessonTaskStoreFake::FIRST_PUBLIC_ID,
            false,
            'AM-20260816-131313131313'
        );
        self::assertSame(0, $unchecked['lesson_progress_percent']);

        $this->service->setLessonTask(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID,
            CourseLessonTaskStoreFake::FIRST_PUBLIC_ID,
            true,
            'AM-20260816-141414141414'
        );
        $optional = $this->service->setLessonTask(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID,
            CourseLessonTaskStoreFake::OPTIONAL_PUBLIC_ID,
            true,
            'AM-20260816-151515151515'
        );
        self::assertSame(50, $optional['lesson_progress_percent']);

        $finished = $this->service->setLessonTask(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID,
            CourseLessonTaskStoreFake::SECOND_PUBLIC_ID,
            true,
            'AM-20260816-161616161616'
        );
        self::assertTrue($finished['lesson_completed']);
        self::assertSame(100, $finished['lesson_progress_percent']);
    }

    public function testChecklistConfigurationDoesNotTransferCompletionToAnotherTask(): void
    {
        $this->sources->completionRequirements = [];
        $tasks = new CourseLessonTaskStoreFake();
        $this->service = new CourseProgressService(
            $this->sources,
            $this->progress,
            $this->completions,
            new CourseAccessPolicyFake(),
            $this->events,
            $tasks
        );
        $this->service->setLessonTask(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID,
            CourseLessonTaskStoreFake::FIRST_PUBLIC_ID,
            true,
            'AM-20260816-171717171717'
        );

        $firstTask = $tasks->published[0];
        $secondTask = $tasks->published[1];
        $firstTask['title'] = 'Pierwsze po edycji';
        $firstTask['position'] = 9;
        $tasks->published = [$secondTask, $firstTask];
        $editedState = $this->service->lessonState(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID
        );
        $editedById = array_column($editedState['lesson_tasks'], null, 'public_id');

        self::assertTrue($editedById[CourseLessonTaskStoreFake::FIRST_PUBLIC_ID]['completed']);
        self::assertFalse($editedById[CourseLessonTaskStoreFake::SECOND_PUBLIC_ID]['completed']);

        $tasks->published = [$secondTask];
        $state = $this->service->lessonState(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID
        );

        self::assertSame(0, $state['lesson_progress_percent']);
        self::assertFalse($state['lesson_tasks'][0]['completed']);
    }

    public function testChecklistCannotBeChangedWithoutCourseAccess(): void
    {
        $this->sources->completionRequirements = [];
        $tasks = new CourseLessonTaskStoreFake();
        $this->service = new CourseProgressService(
            $this->sources,
            $this->progress,
            $this->completions,
            new CourseAccessPolicyFake(false),
            $this->events,
            $tasks
        );

        $result = $this->service->setLessonTask(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID,
            CourseLessonTaskStoreFake::FIRST_PUBLIC_ID,
            true,
            'AM-20260816-181818181818'
        );

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('am_toolkit_course_progress_not_available', $result->get_error_code());
        self::assertSame([], $tasks->completed);
        self::assertSame([], $this->events->events);
    }

    public function testOptionalChecklistItemDoesNotCompleteAnOtherwiseManualLesson(): void
    {
        $this->sources->completionRequirements = [];
        $tasks = new CourseLessonTaskStoreFake();
        $tasks->published = [$tasks->published[2]];
        $this->service = new CourseProgressService(
            $this->sources,
            $this->progress,
            $this->completions,
            new CourseAccessPolicyFake(),
            $this->events,
            $tasks
        );

        $state = $this->service->setLessonTask(
            7,
            CourseProgressSourceStoreFake::COURSE_PUBLIC_ID,
            CourseProgressSourceStoreFake::LESSON_PUBLIC_ID,
            CourseLessonTaskStoreFake::OPTIONAL_PUBLIC_ID,
            true,
            'AM-20260816-202020202020'
        );

        self::assertFalse($state['lesson_completed']);
        self::assertTrue($state['manual_completion_available']);
        self::assertSame(0, $state['lesson_progress_percent']);
    }
}

final class CourseLessonTaskStoreFake implements CourseLessonTaskStore
{
    public const FIRST_PUBLIC_ID = '123e4567-e89b-42d3-a456-426614174010';
    public const SECOND_PUBLIC_ID = '123e4567-e89b-42d3-a456-426614174011';
    public const OPTIONAL_PUBLIC_ID = '123e4567-e89b-42d3-a456-426614174012';

    /** @var list<array<string, mixed>> */
    public array $published = [
        ['id' => 31, 'public_id' => self::FIRST_PUBLIC_ID, 'title' => 'Pierwsze', 'description' => '', 'position' => 0, 'is_required' => 1],
        ['id' => 32, 'public_id' => self::SECOND_PUBLIC_ID, 'title' => 'Drugie', 'description' => '', 'position' => 1, 'is_required' => 1],
        ['id' => 33, 'public_id' => self::OPTIONAL_PUBLIC_ID, 'title' => 'Opcjonalne', 'description' => '', 'position' => 2, 'is_required' => 0],
    ];

    /** @var array<int, true> */
    public array $completed = [];

    public function tasksForCourse(int $courseId): array|\WP_Error { return $this->published; }
    public function publishedTasksForLesson(int $lessonId): array|\WP_Error { return $this->published; }
    public function saveTask(array $task): int|\WP_Error { return 1; }
    public function archiveTask(int $taskId, int $courseId): bool|\WP_Error { return true; }
    public function completedTaskIds(int $userId, int $lessonId): array|\WP_Error { return array_keys($this->completed); }

    public function setTaskCompletion(
        int $userId,
        int $courseId,
        int $lessonId,
        Identifier $taskPublicId,
        bool $completed,
        string $requestId,
        string $occurredAt
    ): int|\WP_Error {
        foreach ($this->published as $task) {
            if ((string) $task['public_id'] !== $taskPublicId->value()) {
                continue;
            }

            $id = (int) $task['id'];

            if ($completed) {
                $this->completed[$id] = true;
            } else {
                unset($this->completed[$id]);
            }

            return $id;
        }

        return new \WP_Error('am_toolkit_lesson_task_not_found', 'Brak zadania.');
    }
}

final class CourseProgressSourceStoreFake implements CourseProgressSourceStore
{
    public const COURSE_PUBLIC_ID = '123e4567-e89b-42d3-a456-426614174000';

    public const LESSON_PUBLIC_ID = '123e4567-e89b-42d3-a456-426614174001';

    /** @var array<string, list<array{0: float, 1: float}>> */
    public array $checkpoints = [];

    /** @var array<string, true> */
    public array $requirements = [];

    /** @var array<string, mixed> */
    public array $completionRequirements = [
        'video_percent' => 80,
        'task_required' => true,
    ];

    public function lessonContext(Identifier $coursePublicId, Identifier $lessonPublicId): ?array
    {
        if (
            $coursePublicId->value() !== self::COURSE_PUBLIC_ID
            || $lessonPublicId->value() !== self::LESSON_PUBLIC_ID
        ) {
            return null;
        }

        return [
            'course_id' => 4,
            'program_version_id' => 8,
            'lesson_id' => 10,
            'content_version' => 2,
            'duration_seconds' => 100,
            'completion_requirements' => $this->completionRequirements,
            'lesson_ids' => [10, 11],
            'required_lesson_ids' => [10, 11],
        ];
    }

    public function recordVideoCheckpoint(
        int $userId,
        int $courseId,
        int $lessonId,
        int $contentVersion,
        string $requestId,
        array $intervals,
        int $durationSeconds,
        float $coveredSeconds,
        string $occurredAt
    ): bool {
        $key = $userId . ':' . $lessonId . ':' . $requestId;

        if (isset($this->checkpoints[$key])) {
            return false;
        }

        $this->checkpoints[$key] = $intervals;

        return true;
    }

    public function videoCheckpointIntervals(int $userId, int $lessonId, int $contentVersion): array
    {
        return array_values($this->checkpoints);
    }

    public function latestVideoPosition(int $userId, int $lessonId, int $contentVersion): float
    {
        $latest = end($this->checkpoints);

        if (!is_array($latest)) {
            return 0.0;
        }

        return array_reduce(
            $latest,
            static fn (float $position, array $interval): float => max($position, (float) $interval[1]),
            0.0
        );
    }

    public function recordRequirementCompletion(
        int $userId,
        int $courseId,
        int $lessonId,
        int $contentVersion,
        string $requirementKey,
        string $completionSource,
        string $requestId,
        string $completedAt
    ): bool {
        $key = $userId . ':' . $lessonId . ':' . $contentVersion . ':' . $requirementKey;

        if (isset($this->requirements[$key])) {
            return false;
        }

        $this->requirements[$key] = true;

        return true;
    }

    public function hasRequirementCompletion(
        int $userId,
        int $lessonId,
        int $contentVersion,
        string $requirementKey
    ): bool {
        return isset($this->requirements[$userId . ':' . $lessonId . ':' . $contentVersion . ':' . $requirementKey]);
    }
}

final class ProgressRepositoryFake implements ProgressRepository
{
    /** @var array<string, LessonProgress> */
    public array $items = [];

    public function find(int $userId, int $courseId, int $lessonId): ?LessonProgress
    {
        return $this->items[$userId . ':' . $courseId . ':' . $lessonId] ?? null;
    }

    public function save(LessonProgress $progress): bool
    {
        $key = $progress->userId() . ':' . $progress->courseId() . ':' . $progress->lessonId();
        $existing = $this->items[$key] ?? null;

        if (
            $existing !== null
            && $existing->contentVersion() === $progress->contentVersion()
            && $existing->status() === ProgressStatus::COMPLETED
            && $progress->status() === ProgressStatus::STARTED
        ) {
            return true;
        }

        $this->items[$key] = $progress;

        return true;
    }

    public function completedLessonIds(int $userId, int $courseId, array $lessonIds): array
    {
        $completed = [];

        foreach ($lessonIds as $lessonId) {
            $item = $this->find($userId, $courseId, $lessonId);

            if ($item !== null && $item->status() === ProgressStatus::COMPLETED) {
                $completed[] = $lessonId;
            }
        }

        return $completed;
    }
}

final class CompletionRepositoryFake implements CompletionRepository
{
    /** @var list<CourseCompletion> */
    public array $items = [];

    public function find(int $userId, int $courseId, int $programVersionId): ?CourseCompletion
    {
        foreach ($this->items as $completion) {
            if (
                $completion->userId() === $userId
                && $completion->courseId() === $courseId
                && $completion->programVersionId() === $programVersionId
            ) {
                return $completion;
            }
        }

        return null;
    }

    public function record(CourseCompletion $completion): bool
    {
        if ($this->find($completion->userId(), $completion->courseId(), $completion->programVersionId()) === null) {
            $this->items[] = $completion;
        }

        return true;
    }
}

final class CourseAccessPolicyFake implements CourseAccessPolicy
{
    public function __construct(private bool $allowed = true)
    {
    }

    public function userCanAccess(int $userId, int $courseId): bool
    {
        return $this->allowed;
    }
}

final class ActivityEventStoreFake implements ActivityEventStore
{
    /** @var list<DomainEvent> */
    public array $events = [];

    public function record(DomainEvent $event): array
    {
        $this->events[] = $event;

        return ['id' => count($this->events), 'created' => true];
    }

    public function find(ActivityEventQuery $query): array
    {
        return [];
    }
}
