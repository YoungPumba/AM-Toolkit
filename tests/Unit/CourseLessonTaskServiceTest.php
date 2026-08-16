<?php

declare(strict_types=1);

namespace AMToolkit\Tests\Unit;

use AMToolkit\Core\Diagnostics\ActivityEventQuery;
use AMToolkit\Core\Diagnostics\DomainEvent;
use AMToolkit\Modules\Access\ActivityEventStore;
use AMToolkit\Modules\Courses\Contracts\CourseLessonTaskStore;
use AMToolkit\Modules\Courses\Contracts\DraftCourseResourceDeletionStore;
use AMToolkit\Modules\Courses\Domain\Identifier;
use AMToolkit\Modules\Courses\Services\CourseLessonTaskService;
use PHPUnit\Framework\TestCase;

final class CourseLessonTaskServiceTest extends TestCase
{
    public function testOwnerCanCreateAndArchiveAValidatedTask(): void
    {
        $store = new LessonTaskStoreSpy();
        $events = new LessonTaskEventStoreFake();
        $service = new CourseLessonTaskService($store, $events);

        $saved = $service->save([
            'course_id' => 4,
            'lesson_id' => 10,
            'title' => '  Zapisz trzy pomysły  ',
            'description' => 'W notatniku lub telefonie.',
            'position' => 2,
            'is_required' => true,
            'status' => 'published',
        ], 9, 'AM-20260816-181818181818');
        $archived = $service->archive(71, 4, 9, 'AM-20260816-191919191919');

        self::assertSame(71, $saved);
        self::assertTrue($archived);
        self::assertSame('Zapisz trzy pomysły', $store->saved['title']);
        self::assertSame(10, $store->saved['lesson_id']);
        self::assertTrue($store->saved['is_required']);
        self::assertSame([[71, 4]], $store->archived);
        self::assertSame(['course.lesson_task.updated', 'course.lesson_task.archived'], array_map(
            static fn (DomainEvent $event): string => (string) $event->toRecord()['event_type'],
            $events->events
        ));
    }

    public function testInvalidTaskNeverReachesPersistence(): void
    {
        $store = new LessonTaskStoreSpy();
        $service = new CourseLessonTaskService($store, new LessonTaskEventStoreFake());

        $result = $service->save([
            'course_id' => 4,
            'lesson_id' => 0,
            'title' => ' ',
            'status' => 'published',
        ], 9);

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('am_toolkit_lesson_task_invalid', $result->get_error_code());
        self::assertSame([], $store->saved);
    }

    public function testUnusedDraftCanBeDeletedAndAuditContainsNoEditorialContent(): void
    {
        $store = new LessonTaskStoreSpy();
        $events = new LessonTaskEventStoreFake();
        $service = new CourseLessonTaskService($store, $events);

        self::assertTrue($service->deleteDraft(71, 4, 9, 'AM-20260816-202020202020'));
        self::assertSame([['lesson_task', 71, 4]], $store->deleted);
        self::assertSame('course.lesson_task.deleted', $events->events[0]->toRecord()['event_type']);
        self::assertSame('permanent_unused_draft', $events->events[0]->toRecord()['payload']['deletion']);
    }
}

final class LessonTaskStoreSpy implements CourseLessonTaskStore, DraftCourseResourceDeletionStore
{
    /** @var array<string, mixed> */
    public array $saved = [];

    /** @var list<array{0: int, 1: int}> */
    public array $archived = [];

    /** @var list<array{0: string, 1: int, 2: int}> */
    public array $deleted = [];

    public function tasksForCourse(int $courseId): array|\WP_Error { return []; }
    public function publishedTasksForLesson(int $lessonId): array|\WP_Error { return []; }

    public function saveTask(array $task): int|\WP_Error
    {
        $this->saved = $task;
        return 71;
    }

    public function archiveTask(int $taskId, int $courseId): bool|\WP_Error
    {
        $this->archived[] = [$taskId, $courseId];
        return true;
    }

    public function deleteDraftResource(string $resourceType, int $resourceId, int $courseId): bool|\WP_Error
    {
        $this->deleted[] = [$resourceType, $resourceId, $courseId];
        return true;
    }

    public function completedTaskIds(int $userId, int $lessonId): array|\WP_Error { return []; }

    public function setTaskCompletion(
        int $userId,
        int $courseId,
        int $lessonId,
        Identifier $taskPublicId,
        bool $completed,
        string $requestId,
        string $occurredAt
    ): int|\WP_Error {
        return 71;
    }
}

final class LessonTaskEventStoreFake implements ActivityEventStore
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
