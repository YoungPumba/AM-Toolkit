<?php

declare(strict_types=1);

namespace AMToolkit\Tests\Unit;

use AMToolkit\Core\Diagnostics\ActivityEventQuery;
use AMToolkit\Core\Diagnostics\DomainEvent;
use AMToolkit\Core\Diagnostics\TechnicalLogger;
use AMToolkit\Modules\Access\ActivityEventStore;
use AMToolkit\Modules\Courses\Contracts\CourseDiagnosticsStore;
use AMToolkit\Modules\Courses\Contracts\CourseProgressDiagnostics;
use AMToolkit\Modules\Courses\Services\CourseDiagnosticsService;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class CourseDiagnosticsServiceTest extends TestCase
{
    public function testInspectionDetectsOutdatedLessonAggregate(): void
    {
        $store = new CourseDiagnosticsStoreFake($this->snapshot('started', 1, null));
        $progress = new CourseProgressDiagnosticsFake($store, [10 => $this->lessonState(true)]);
        $service = new CourseDiagnosticsService($store, $progress, new DiagnosticsActivityStoreFake());

        $result = $service->inspect(7, 4);

        self::assertIsArray($result);
        self::assertTrue($result['active_access']);
        self::assertSame(0, $result['aggregate']['expected_progress_percent']);
        self::assertContains('stale_lesson_progress', array_column($result['issues'], 'code'));
        self::assertContains('lesson_aggregate_outdated', array_column($result['issues'], 'code'));
        self::assertTrue($result['repair_preview']['available']);
        self::assertTrue($result['repair_preview']['would_write']);
    }

    public function testRepairIsAuditedAndIdempotent(): void
    {
        $store = new CourseDiagnosticsStoreFake($this->snapshot('started', 2, null));
        $progress = new CourseProgressDiagnosticsFake($store, [10 => $this->lessonState(true)]);
        $events = new DiagnosticsActivityStoreFake();
        $service = new CourseDiagnosticsService($store, $progress, $events);

        $first = $service->repair(7, 4, 'AM-20260816-AAAAAAAAAAAA');
        $second = $service->repair(7, 4, 'AM-20260816-BBBBBBBBBBBB');

        self::assertIsArray($first);
        self::assertSame(1, $first['changed_lessons']);
        self::assertTrue($first['completion_changed']);
        self::assertSame(2, $first['changed_aggregates']);
        self::assertSame(1, $first['attempted_lessons']);
        self::assertIsArray($second);
        self::assertSame(0, $second['changed_lessons']);
        self::assertFalse($second['completion_changed']);
        self::assertSame(0, $second['changed_aggregates']);
        self::assertCount(2, $events->recorded);
        self::assertSame('course.progress.recalculated', $events->recorded[0]['event_type']);
        self::assertSame('AM-20260816-AAAAAAAAAAAA', $events->recorded[0]['request_id']);
    }

    public function testFailedRepairRemainsRetryableAndRecordsSafeError(): void
    {
        $store = new CourseDiagnosticsStoreFake($this->snapshot('started', 2, null));
        $progress = new CourseProgressDiagnosticsFake($store, [10 => $this->lessonState(true)]);
        $progress->failNextRepair = true;
        $events = new DiagnosticsActivityStoreFake();
        $logger = new DiagnosticsLoggerFake();
        $service = new CourseDiagnosticsService($store, $progress, $events, $logger);

        $failed = $service->repair(7, 4, 'AM-20260816-CCCCCCCCCCCC');
        $retry = $service->repair(7, 4, 'AM-20260816-DDDDDDDDDDDD');

        self::assertInstanceOf(WP_Error::class, $failed);
        self::assertSame('am_toolkit_course_repair_failed', $failed->get_error_code());
        self::assertIsArray($retry);
        self::assertSame(1, $retry['changed_lessons']);
        self::assertSame(2, $retry['changed_aggregates']);
        self::assertSame('course.progress.recalculation_failed', $events->recorded[0]['event_type']);
        self::assertNotEmpty($logger->errors);
        self::assertArrayNotHasKey('email', $logger->errors[0]['context']);
    }

    public function testExportOmitsPersonalDataTokensAndPrivateLinks(): void
    {
        $snapshot = $this->snapshot('completed', 2, [
            'id' => 1,
            'required_lesson_ids' => [10],
            'required_lesson_ids_valid' => true,
            'requirements_hash' => hash('sha256', '10'),
            'completion_source' => 'https://private.example/token-secret',
            'request_id' => 'AM-20260816-EEEEEEEEEEEE',
            'completed_at' => '2026-08-10 10:00:00',
        ]);
        $snapshot['email'] = 'participant@example.com';
        $snapshot['grants'][0]['source_id'] = 778899;
        $snapshot['grants'][0]['private_link'] = 'https://zoom.us/private-token';
        $events = new DiagnosticsActivityStoreFake([[
            'event_type' => 'course.lesson.completed',
            'request_id' => 'AM-20260816-EEEEEEEEEEEE',
            'user_id' => 7,
            'object_type' => 'lesson',
            'object_id' => 10,
            'payload' => ['course_id' => 4, 'token' => 'event-secret'],
            'occurred_at' => '2026-08-10 10:00:00',
        ]]);
        $service = new CourseDiagnosticsService(
            new CourseDiagnosticsStoreFake($snapshot),
            new CourseProgressDiagnosticsFake(null, [10 => $this->lessonState(true)]),
            $events
        );

        $json = $service->export(7, 4);

        self::assertIsString($json);
        self::assertStringContainsString('"user_ref"', $json);
        self::assertStringNotContainsString('"user_id"', $json);
        self::assertStringNotContainsString('participant@example.com', $json);
        self::assertStringNotContainsString('private.example', $json);
        self::assertStringNotContainsString('zoom.us', $json);
        self::assertStringNotContainsString('token-secret', $json);
        self::assertStringNotContainsString('event-secret', $json);
        self::assertStringNotContainsString('778899', $json);
    }

    /** @return array<string, mixed> */
    private function snapshot(string $status, int $progressVersion, ?array $completion): array
    {
        return [
            'user_id' => 7,
            'user_exists' => true,
            'course' => [
                'id' => 4,
                'public_id' => 'course-public-id',
                'title' => 'Kurs testowy',
                'status' => 'published',
                'published_program_version_id' => 3,
                'draft_program_version_id' => 0,
            ],
            'program' => [
                'id' => 3,
                'version_number' => 1,
                'status' => 'published',
                'published_at' => '2026-08-10 10:00:00',
                'created_at' => '2026-08-10 10:00:00',
            ],
            'grants' => [[
                'id' => 8,
                'source_type' => 'woocommerce',
                'source_id' => 99,
                'status' => 'active',
                'starts_at' => '',
                'expires_at' => '',
                'granted_at' => '2026-08-10 10:00:00',
                'revoked_at' => '',
                'is_active' => true,
            ]],
            'lessons' => [[
                'id' => 10,
                'public_id' => 'lesson-public-id',
                'title' => 'Lekcja testowa',
                'content_version' => 2,
                'is_required' => true,
                'progress_status' => $status,
                'progress_content_version' => $progressVersion,
                'completion_source' => '',
                'request_id' => '',
                'completed_at' => $status === 'completed' ? '2026-08-10 10:00:00' : '',
                'updated_at' => '2026-08-10 10:00:00',
            ]],
            'completion' => $completion,
            'last_opened_lesson' => null,
            'last_completed_lesson' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function lessonState(bool $completed): array
    {
        return [
            'status' => $completed ? 'completed' : 'started',
            'lesson_completed' => $completed,
            'watched_percent' => $completed ? 100.0 : 50.0,
            'lesson_progress_percent' => $completed ? 100 : 50,
            'course_completed' => $completed,
            'course_progress_percent' => $completed ? 100 : 0,
        ];
    }
}

final class CourseDiagnosticsStoreFake implements CourseDiagnosticsStore
{
    /** @param array<string, mixed> $snapshot */
    public function __construct(public array $snapshot)
    {
    }

    public function schemaHealth(): array|WP_Error
    {
        return [
            'checked_at' => '2026-08-10 10:00:00',
            'expected_schema_version' => 7,
            'installed_schema_version' => 7,
            'tables' => ['courses' => true],
            'orphan_counts' => ['progress_without_parent' => 0],
            'valid' => true,
        ];
    }

    public function snapshot(int $userId, int $courseId): array|WP_Error
    {
        return $this->snapshot;
    }
}

final class CourseProgressDiagnosticsFake implements CourseProgressDiagnostics
{
    public bool $failNextRepair = false;

    /** @param array<int, array<string, mixed>> $states */
    public function __construct(
        private ?CourseDiagnosticsStoreFake $store,
        private array $states
    ) {
    }

    public function lessonState(int $userId, string $coursePublicId, string $lessonPublicId): array|WP_Error
    {
        return $this->states[10];
    }

    public function rebuildLessonProgress(
        int $userId,
        string $coursePublicId,
        string $lessonPublicId,
        ?string $requestId = null
    ): array|WP_Error {
        if ($this->failNextRepair) {
            $this->failNextRepair = false;
            return new WP_Error('temporary_write_error', 'Temporary error');
        }

        if ($this->store !== null) {
            $this->store->snapshot['lessons'][0]['progress_status'] = 'completed';
            $this->store->snapshot['lessons'][0]['progress_content_version'] = 2;
            $this->store->snapshot['lessons'][0]['completed_at'] = '2026-08-10 10:00:00';
            $this->store->snapshot['completion'] = [
                'id' => 1,
                'required_lesson_ids' => [10],
                'required_lesson_ids_valid' => true,
                'requirements_hash' => hash('sha256', '10'),
                'completion_source' => 'rebuilt_from_sources',
                'request_id' => $requestId,
                'completed_at' => '2026-08-10 10:00:00',
            ];
        }

        return $this->states[10] + ['request_id' => $requestId];
    }
}

final class DiagnosticsActivityStoreFake implements ActivityEventStore
{
    /** @var list<array<string, mixed>> */
    public array $recorded = [];

    /** @param list<array<string, mixed>> $found */
    public function __construct(private array $found = [])
    {
    }

    public function record(DomainEvent $event): array|WP_Error
    {
        $this->recorded[] = $event->toRecord();

        return ['id' => count($this->recorded), 'created' => true];
    }

    public function find(ActivityEventQuery $query): array|WP_Error
    {
        return array_slice($this->found, 0, $query->limit());
    }
}

final class DiagnosticsLoggerFake implements TechnicalLogger
{
    /** @var list<array{message: string, context: array<string, scalar|null>}> */
    public array $errors = [];

    public function error(string $message, array $context = []): void
    {
        $this->errors[] = ['message' => $message, 'context' => $context];
    }
}
