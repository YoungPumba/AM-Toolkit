<?php

declare(strict_types=1);

namespace AMToolkit\Tests\Unit;

use AMToolkit\Modules\Courses\Domain\CourseCompletion;
use AMToolkit\Modules\Courses\Domain\CourseMeeting;
use AMToolkit\Modules\Courses\Domain\MeetingStatus;
use AMToolkit\Modules\Courses\Domain\CourseProgramVersion;
use AMToolkit\Modules\Courses\Domain\Identifier;
use AMToolkit\Modules\Courses\Domain\LessonCompletionRequirements;
use AMToolkit\Modules\Courses\Domain\PublicationStatus;
use AMToolkit\Modules\Courses\Domain\VideoIntervalSet;
use AMToolkit\Modules\Courses\Services\RequiredLessonCompletionEvaluator;
use PHPUnit\Framework\TestCase;

final class CoursesDomainTest extends TestCase
{
    private Identifier $publicId;

    protected function setUp(): void
    {
        $this->publicId = new Identifier('123e4567-e89b-42d3-a456-426614174000');
    }

    public function testProgramVersionKeepsOrderedAndRequiredLessonSnapshot(): void
    {
        $program = $this->program([12, 10, 11], [12, 11]);

        self::assertSame([12, 10, 11], $program->lessonIds());
        self::assertSame([12, 11], $program->requiredLessonIds());
        self::assertSame(64, strlen($program->contentHash()));
        self::assertSame($program->contentHash(), $this->program([12, 10, 11], [12, 11])->contentHash());
        self::assertNotSame($program->contentHash(), $this->program([10, 12, 11], [12, 11])->contentHash());
    }

    public function testRequiredLessonsMustBelongToProgram(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->program([10], [99]);
    }

    public function testCompletionSnapshotIsCanonicalAndStable(): void
    {
        $completion = new CourseCompletion(
            0,
            5,
            8,
            13,
            [12, 10, 11],
            'requirements_met',
            '2026-08-13 20:00:00'
        );

        self::assertSame([10, 11, 12], $completion->requiredLessonIds());
        self::assertSame(hash('sha256', '10,11,12'), $completion->requirementsHash());
    }

    public function testEvaluatorUsesOnlyRequiredLessons(): void
    {
        $evaluator = new RequiredLessonCompletionEvaluator();
        $program = $this->program([10, 11, 12], [10, 12]);

        self::assertFalse($evaluator->isComplete($program, [10, 11]));
        self::assertTrue($evaluator->isComplete($program, [12, 10, 999, 10]));
    }

    public function testVideoIntervalsAreClampedMergedAndCountedOnlyOnce(): void
    {
        $firstDevice = new VideoIntervalSet([
            [-4, 10],
            [8, 20],
            [90, 120],
        ], 100);
        $secondDevice = new VideoIntervalSet([
            [15, 30],
            [40, 40],
            [50, 60],
        ], 100);
        $combined = VideoIntervalSet::combine([$firstDevice, $secondDevice], 100);

        self::assertSame([[0.0, 30.0], [50.0, 60.0], [90.0, 100.0]], $combined->intervals());
        self::assertSame(50.0, $combined->coveredSeconds());
        self::assertSame(50.0, $combined->percentage(100));
    }

    public function testLessonRequirementsNeedBothConfiguredSignals(): void
    {
        $requirements = LessonCompletionRequirements::fromArray([
            'video_percent' => 80,
            'task_required' => true,
        ]);

        self::assertFalse($requirements->isSatisfied(79.99, true));
        self::assertFalse($requirements->isSatisfied(90, false));
        self::assertTrue($requirements->isSatisfied(80, true));
        self::assertTrue(LessonCompletionRequirements::fromArray([])->hasAutomaticRequirements() === false);
    }

    public function testMeetingNormalizesDatesToUtc(): void
    {
        $meeting = new CourseMeeting(
            0,
            $this->publicId,
            8,
            'Q&A',
            'Opis spotkania',
            new \DateTimeImmutable('2026-08-13 20:00:00', new \DateTimeZone('Europe/Warsaw')),
            new \DateTimeImmutable('2026-08-13 21:00:00', new \DateTimeZone('Europe/Warsaw')),
            'Europe/Warsaw',
            'external',
            'Online',
            'protected-join-reference',
            null,
            MeetingStatus::SCHEDULED
        );

        self::assertSame('2026-08-13 18:00:00', $meeting->startsAtUtc()->format('Y-m-d H:i:s'));
        self::assertSame('UTC', $meeting->startsAtUtc()->getTimezone()->getName());
    }

    /**
     * @param list<int> $lessons
     * @param list<int> $required
     */
    private function program(array $lessons, array $required): CourseProgramVersion
    {
        return new CourseProgramVersion(
            1,
            $this->publicId,
            8,
            2,
            PublicationStatus::PUBLISHED,
            $lessons,
            $required,
            '2026-08-13 20:00:00'
        );
    }
}
