<?php

declare(strict_types=1);

namespace AMToolkit\Tests\Unit;

use AMToolkit\Modules\Courses\Contracts\CourseAccessPolicy;
use AMToolkit\Modules\Courses\Contracts\CourseViewStore;
use AMToolkit\Modules\Courses\Domain\Identifier;
use AMToolkit\Modules\Courses\Services\CourseCatalogService;
use PHPUnit\Framework\TestCase;

final class CourseCatalogServiceTest extends TestCase
{
    private ParticipantCourseStoreSpy $store;
    private ParticipantAccessPolicySpy $access;
    private CourseCatalogService $service;

    protected function setUp(): void
    {
        $this->store = new ParticipantCourseStoreSpy();
        $this->access = new ParticipantAccessPolicySpy();
        $this->service = new CourseCatalogService($this->store, $this->access);
    }

    public function testHubClassifiesOwnCoursesAndNeverOpensAnArchivedCourse(): void
    {
        $this->store->courses = [
            ['public_id' => $this->uuid(1), 'course_status' => 'published', 'has_active_access' => '1', 'has_completion' => '0', 'has_future_access' => '0'],
            ['public_id' => $this->uuid(2), 'course_status' => 'published', 'has_active_access' => '1', 'has_completion' => '1', 'has_future_access' => '0'],
            ['public_id' => $this->uuid(3), 'course_status' => 'published', 'has_active_access' => '0', 'has_completion' => '0', 'has_future_access' => '1'],
            ['public_id' => $this->uuid(4), 'course_status' => 'published', 'has_active_access' => '0', 'has_completion' => '0', 'has_future_access' => '0'],
            ['public_id' => $this->uuid(5), 'course_status' => 'archived', 'has_active_access' => '1', 'has_completion' => '0', 'has_future_access' => '0'],
        ];

        $result = $this->service->coursesForUser(9);

        self::assertIsArray($result);
        self::assertSame(['active', 'completed', 'scheduled', 'expired', 'expired'], array_column($result, 'access_state'));
        self::assertSame([true, true, false, false, false], array_column($result, 'can_open'));
        self::assertSame(9, $this->store->listedUserId);
    }

    public function testInvalidPublicIdentifierUsesSameSafeNotFoundState(): void
    {
        $result = $this->service->courseForUser(9, '../../sekret');

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('am_toolkit_course_not_available', $result->get_error_code());
        self::assertSame(0, $this->store->findCalls);
        self::assertSame(0, $this->store->programCalls);
    }

    public function testProgramIsNotReadWhenActiveAccessIsMissing(): void
    {
        $this->store->course = [
            'id' => 71,
            'public_id' => $this->uuid(1),
            'current_program_version_id' => 18,
        ];
        $this->access->allowed = false;

        $result = $this->service->courseForUser(9, $this->uuid(1));

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('am_toolkit_course_not_available', $result->get_error_code());
        self::assertSame([[9, 71]], $this->access->calls);
        self::assertSame(0, $this->store->programCalls);
    }

    public function testAuthorizedCourseReturnsProgramWithoutInternalIdentifiers(): void
    {
        $this->store->course = [
            'id' => 71,
            'public_id' => $this->uuid(1),
            'title' => 'Kurs testowy',
            'current_program_version_id' => 18,
        ];
        $this->store->program = [
            'version_number' => 2,
            'sections' => [['title' => 'Start', 'lessons' => []]],
            'lessons' => [],
        ];
        $this->access->allowed = true;

        $result = $this->service->courseForUser(9, $this->uuid(1));

        self::assertIsArray($result);
        self::assertSame('Kurs testowy', $result['title']);
        self::assertSame(2, $result['program']['version_number']);
        self::assertArrayNotHasKey('id', $result);
        self::assertArrayNotHasKey('current_program_version_id', $result);
        self::assertSame([[71, 18]], $this->store->programRequests);
    }

    public function testDatabaseFailureIsReplacedWithSafePublicError(): void
    {
        $this->store->listError = new \WP_Error(
            'database_failed',
            'SQL and table details must stay private.'
        );

        $result = $this->service->coursesForUser(9);

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('am_toolkit_course_view_read_failed', $result->get_error_code());
        self::assertStringNotContainsString('SQL', $result->get_error_message());
    }

    private function uuid(int $suffix): string
    {
        return sprintf('12345678-1234-4123-8123-%012d', $suffix);
    }
}

final class ParticipantCourseStoreSpy implements CourseViewStore
{
    /** @var list<array<string, mixed>> */
    public array $courses = [];

    /** @var array<string, mixed>|null */
    public ?array $course = null;

    /** @var array<string, mixed> */
    public array $program = ['sections' => [], 'lessons' => []];

    public ?\WP_Error $listError = null;
    public int $listedUserId = 0;
    public int $findCalls = 0;
    public int $programCalls = 0;

    /** @var list<array{0: int, 1: int}> */
    public array $programRequests = [];

    public function coursesForUser(int $userId, string $at): array|\WP_Error
    {
        $this->listedUserId = $userId;

        return $this->listError ?? $this->courses;
    }

    public function findPublishedCourse(Identifier $publicId): array|null|\WP_Error
    {
        $this->findCalls++;

        return $this->course;
    }

    public function publishedProgram(int $courseId, int $programVersionId): array|\WP_Error
    {
        $this->programCalls++;
        $this->programRequests[] = [$courseId, $programVersionId];

        return $this->program;
    }
}

final class ParticipantAccessPolicySpy implements CourseAccessPolicy
{
    public bool $allowed = false;

    /** @var list<array{0: int, 1: int}> */
    public array $calls = [];

    public function userCanAccess(int $userId, int $courseId): bool
    {
        $this->calls[] = [$userId, $courseId];

        return $this->allowed;
    }
}
