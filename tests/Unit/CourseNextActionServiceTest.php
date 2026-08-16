<?php

declare(strict_types=1);

namespace AMToolkit\Tests\Unit;

use AMToolkit\Modules\Courses\Contracts\CourseProgressOverviewStore;
use AMToolkit\Modules\Courses\Services\CourseNextActionService;
use PHPUnit\Framework\TestCase;

final class CourseNextActionServiceTest extends TestCase
{
    public function testLatestStartedLessonWinsBeforeFirstIncompleteRequiredLesson(): void
    {
        $store = new CourseProgressOverviewStoreFake([
            ['public_id' => 'first', 'is_required' => 1, 'progress_status' => null, 'progress_updated_at' => null],
            ['public_id' => 'older', 'is_required' => 1, 'progress_status' => 'started', 'progress_updated_at' => '2026-08-14 10:00:00'],
            ['public_id' => 'latest', 'is_required' => 0, 'progress_status' => 'started', 'progress_updated_at' => '2026-08-16 10:00:00'],
        ]);
        $result = (new CourseNextActionService($store))->overview(7, 4, 8);

        self::assertIsArray($result);
        self::assertSame('latest', $result['next_lesson_public_id']);
        self::assertSame('continue', $result['next_action']);
        self::assertSame(0, $result['progress_percent']);
    }

    public function testStoredCompletionPreservesOneHundredPercentAndStopsNextAction(): void
    {
        $store = new CourseProgressOverviewStoreFake([
            ['public_id' => 'new-lesson', 'is_required' => 1, 'progress_status' => null, 'progress_updated_at' => null],
        ], true);
        $result = (new CourseNextActionService($store))->overview(7, 4, 8);

        self::assertIsArray($result);
        self::assertSame(100, $result['progress_percent']);
        self::assertNull($result['next_lesson_public_id']);
        self::assertSame('completed', $result['next_action']);
    }

    public function testCompletedLessonContinuesToNextRequiredLesson(): void
    {
        $store = new CourseProgressOverviewStoreFake([
            ['public_id' => 'completed', 'is_required' => 1, 'progress_status' => 'completed', 'progress_updated_at' => '2026-08-16 10:00:00'],
            ['public_id' => 'next', 'is_required' => 1, 'progress_status' => null, 'progress_updated_at' => null],
            ['public_id' => 'last', 'is_required' => 1, 'progress_status' => null, 'progress_updated_at' => null],
        ]);
        $result = (new CourseNextActionService($store))->overview(7, 4, 8);

        self::assertIsArray($result);
        self::assertSame(1, $result['required_completed']);
        self::assertSame('next', $result['next_lesson_public_id']);
        self::assertSame('continue', $result['next_action']);
    }

    public function testCourseWithoutProgressStartsAtFirstRequiredLesson(): void
    {
        $store = new CourseProgressOverviewStoreFake([
            ['public_id' => 'first', 'is_required' => 1, 'progress_status' => null, 'progress_updated_at' => null],
            ['public_id' => 'second', 'is_required' => 1, 'progress_status' => null, 'progress_updated_at' => null],
        ]);
        $result = (new CourseNextActionService($store))->overview(7, 4, 8);

        self::assertIsArray($result);
        self::assertSame(0, $result['required_completed']);
        self::assertSame('first', $result['next_lesson_public_id']);
        self::assertSame('start', $result['next_action']);
    }
}

final class CourseProgressOverviewStoreFake implements CourseProgressOverviewStore
{
    /** @param list<array<string, mixed>> $rows */
    public function __construct(private array $rows, private bool $completed = false)
    {
    }

    public function lessons(int $userId, int $courseId, int $programVersionId): array
    {
        return $this->rows;
    }

    public function hasCompletion(int $userId, int $courseId, int $programVersionId): bool
    {
        return $this->completed;
    }
}
