<?php

declare(strict_types=1);

namespace AMToolkit\Tests\Unit;

use AMToolkit\Core\Diagnostics\ActivityEventQuery;
use AMToolkit\Core\Diagnostics\DomainEvent;
use AMToolkit\Modules\Access\ActivityEventStore;
use AMToolkit\Modules\Courses\Contracts\CourseQaStore;
use AMToolkit\Modules\Courses\Contracts\DraftCourseResourceDeletionStore;
use AMToolkit\Modules\Courses\Services\CourseQaService;
use PHPUnit\Framework\TestCase;

final class CourseQaServiceTest extends TestCase
{
    public function testSavesValidatedEntryWithoutPuttingEditorialContentInAuditLog(): void
    {
        $store = new QaMemoryStore();
        $events = new QaEventStore();
        $service = new CourseQaService($store, $events);

        $result = $service->save([
            'id' => 0,
            'course_id' => 5,
            'lesson_id' => 7,
            'question' => 'Czy dostanę nagranie?',
            'answer' => 'Tak, po spotkaniu.',
            'position' => 2,
            'status' => 'published',
        ], 12);

        self::assertSame(41, $result);
        self::assertSame(7, $store->saved['lesson_id']);
        $record = $events->events[0]->toRecord();
        self::assertSame('course.qa.updated', $record['event_type']);
        self::assertSame(41, $record['payload']['qa_entry_id']);
        self::assertStringNotContainsString('nagranie', json_encode($record['payload'], JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('spotkaniu', json_encode($record['payload'], JSON_THROW_ON_ERROR));
    }

    public function testRejectsEmptyAnswerBeforePersistence(): void
    {
        $store = new QaMemoryStore();
        $service = new CourseQaService($store, new QaEventStore());

        $result = $service->save([
            'course_id' => 5,
            'question' => 'Pytanie',
            'answer' => ' ',
            'status' => 'draft',
        ], 12);

        self::assertTrue(is_wp_error($result));
        self::assertSame('am_toolkit_course_qa_invalid', $result->get_error_code());
        self::assertSame([], $store->saved);
    }

    public function testArchivePreservesDataAndRecordsOnlyIdentifier(): void
    {
        $store = new QaMemoryStore();
        $events = new QaEventStore();
        $service = new CourseQaService($store, $events);

        self::assertTrue($service->archive(41, 5, 12));
        self::assertSame([[41, 5]], $store->archives);
        self::assertSame(['qa_entry_id' => 41], $events->events[0]->toRecord()['payload']);
    }

    public function testUnusedDraftCanBeDeletedWithExplicitAuditReason(): void
    {
        $store = new QaMemoryStore();
        $events = new QaEventStore();
        $service = new CourseQaService($store, $events);

        self::assertTrue($service->deleteDraft(41, 5, 12));
        self::assertSame([['qa', 41, 5]], $store->deleted);
        self::assertSame('course.qa.deleted', $events->events[0]->toRecord()['event_type']);
    }
}

final class QaMemoryStore implements CourseQaStore, DraftCourseResourceDeletionStore
{
    /** @var array<string, mixed> */
    public array $saved = [];

    /** @var list<array{0: int, 1: int}> */
    public array $archives = [];

    /** @var list<array{0: string, 1: int, 2: int}> */
    public array $deleted = [];

    public function entriesForCourse(int $courseId): array|\WP_Error { return []; }

    public function publishedEntriesForCourse(int $courseId, int $programVersionId): array|\WP_Error { return []; }

    public function saveEntry(array $entry): int|\WP_Error
    {
        $this->saved = $entry;
        return 41;
    }

    public function archiveEntry(int $entryId, int $courseId): bool|\WP_Error
    {
        $this->archives[] = [$entryId, $courseId];
        return true;
    }

    public function deleteDraftResource(string $resourceType, int $resourceId, int $courseId): bool|\WP_Error
    {
        $this->deleted[] = [$resourceType, $resourceId, $courseId];
        return true;
    }
}

final class QaEventStore implements ActivityEventStore
{
    /** @var list<DomainEvent> */
    public array $events = [];

    public function record(DomainEvent $event): array|\WP_Error
    {
        $this->events[] = $event;
        return ['id' => count($this->events), 'created' => true];
    }

    public function find(ActivityEventQuery $query): array|\WP_Error { return []; }
}
