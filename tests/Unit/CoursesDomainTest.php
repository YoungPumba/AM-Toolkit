<?php

declare(strict_types=1);

namespace AMToolkit\Tests\Unit;

use AMToolkit\Modules\Courses\Domain\CourseCompletion;
use AMToolkit\Modules\Courses\Domain\CourseMeeting;
use AMToolkit\Modules\Courses\Domain\CourseProgramVersion;
use AMToolkit\Modules\Courses\Domain\Identifier;
use AMToolkit\Modules\Courses\Domain\PublicationStatus;
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

    public function testMeetingNormalizesDatesToUtc(): void
    {
        $meeting = new CourseMeeting(
            0,
            $this->publicId,
            8,
            'Q&A',
            new \DateTimeImmutable('2026-08-13 20:00:00', new \DateTimeZone('Europe/Warsaw')),
            new \DateTimeImmutable('2026-08-13 21:00:00', new \DateTimeZone('Europe/Warsaw')),
            'Europe/Warsaw',
            'external',
            'protected-join-reference',
            null,
            PublicationStatus::DRAFT
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
