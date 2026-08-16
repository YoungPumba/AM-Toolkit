<?php

declare(strict_types=1);

namespace AMToolkit\Tests\Unit;

use AMToolkit\Modules\Courses\Contracts\CourseAccessPolicy;
use AMToolkit\Modules\Courses\Contracts\CourseViewStore;
use AMToolkit\Modules\Courses\Contracts\CourseMeetingStore;
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

    public function testPrivateMeetingLinksAreReadOnlyAfterCourseAuthorization(): void
    {
        $meetings = new ParticipantMeetingStoreSpy();
        $this->service = new CourseCatalogService($this->store, $this->access, null, $meetings);
        $this->store->course = [
            'id' => 71,
            'public_id' => $this->uuid(1),
            'title' => 'Kurs testowy',
            'current_program_version_id' => 18,
        ];

        $this->access->allowed = false;
        $denied = $this->service->courseForUser(9, $this->uuid(1));
        self::assertTrue(is_wp_error($denied));
        self::assertSame(0, $meetings->readCalls);

        $this->access->allowed = true;
        $allowed = $this->service->courseForUser(9, $this->uuid(1));
        self::assertIsArray($allowed);
        self::assertSame(2, $meetings->readCalls);
        self::assertSame('https://zoom.example/private', $allowed['nearest_meeting']['join_reference']);
        self::assertSame('https://t.me/private', $allowed['telegram_reference']);
        self::assertArrayNotHasKey('id', $allowed['nearest_meeting']);
        self::assertArrayNotHasKey('course_id', $allowed['nearest_meeting']);
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

    public function testLessonRequiresActiveAccessBeforePrivateDataIsRead(): void
    {
        $this->store->course = [
            'id' => 71,
            'public_id' => $this->uuid(1),
            'current_program_version_id' => 18,
        ];
        $this->access->allowed = false;

        $result = $this->service->lessonForUser(9, $this->uuid(1), $this->uuid(2));

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame(0, $this->store->lessonCalls);
    }

    public function testMissingLessonUsesDedicatedSafeError(): void
    {
        $this->store->course = [
            'id' => 71,
            'public_id' => $this->uuid(1),
            'current_program_version_id' => 18,
        ];
        $this->access->allowed = true;

        $result = $this->service->lessonForUser(9, $this->uuid(1), $this->uuid(2));

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('am_toolkit_course_lesson_not_available', $result->get_error_code());
        self::assertStringContainsString('Ta lekcja nie jest dostępna', $result->get_error_message());
    }

    public function testAuthorizedLessonReturnsOnlyPublishedViewAndMaterialAsset(): void
    {
        $this->store->course = [
            'id' => 71,
            'public_id' => $this->uuid(1),
            'title' => 'Kurs testowy',
            'current_program_version_id' => 18,
        ];
        $this->store->lesson = [
            'public_id' => $this->uuid(2),
            'title' => 'Lekcja testowa',
            'video_provider' => 'am-private',
            'video_reference' => 'videos/reference.mp4',
            'materials' => [[
                'public_id' => $this->uuid(3),
                'name' => 'Ćwiczenia',
                'storage_provider' => 'am-private',
                'storage_reference' => 'materials/reference.pdf',
            ]],
        ];
        $this->access->allowed = true;

        $lesson = $this->service->lessonForUser(9, $this->uuid(1), $this->uuid(2));
        $asset = $this->service->assetForUser(9, $this->uuid(1), $this->uuid(2), 'material', $this->uuid(3));

        self::assertIsArray($lesson);
        self::assertSame('Kurs testowy', $lesson['course']['title']);
        self::assertSame([
            [71, 18, $this->uuid(2)],
            [71, 18, $this->uuid(2)],
        ], $this->store->lessonRequests);
        self::assertIsArray($asset);
        self::assertSame('materials/reference.pdf', $asset['reference']);
        self::assertSame('attachment', $asset['disposition']);
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
    public int $lessonCalls = 0;

    /** @var array<string, mixed>|null */
    public ?array $lesson = null;

    /** @var list<array{0: int, 1: int}> */
    public array $programRequests = [];

    /** @var list<array{0: int, 1: int, 2: string}> */
    public array $lessonRequests = [];

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

    public function publishedLesson(int $courseId, int $programVersionId, Identifier $publicId): array|null|\WP_Error
    {
        $this->lessonCalls++;
        $this->lessonRequests[] = [$courseId, $programVersionId, $publicId->value()];

        return $this->lesson;
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

final class ParticipantMeetingStoreSpy implements CourseMeetingStore
{
    public int $readCalls = 0;

    public function courseSettings(int $courseId): array|null|\WP_Error
    {
        $this->readCalls++;
        return ['id' => $courseId, 'telegram_reference' => 'https://t.me/private'];
    }

    public function meetingsForCourse(int $courseId): array|\WP_Error
    {
        $this->readCalls++;
        return [$this->meeting($courseId)];
    }

    public function saveMeeting(array $meeting, int $actorId, string $requestId): int|\WP_Error
    {
        return 1;
    }

    public function saveTelegramReference(int $courseId, ?string $reference): bool|\WP_Error
    {
        return true;
    }

    public function nearestMeetings(array $courseIds, string $atUtc): array|\WP_Error
    {
        $courseId = $courseIds[0] ?? 0;
        return $courseId > 0 ? [$courseId => $this->meeting($courseId)] : [];
    }

    /** @return array<string, mixed> */
    private function meeting(int $courseId): array
    {
        return [
            'id' => 41,
            'course_id' => $courseId,
            'public_id' => '12345678-1234-4123-8123-000000000041',
            'title' => 'Q&A',
            'starts_at_utc' => '2026-08-20 18:00:00',
            'display_timezone' => 'Europe/Warsaw',
            'join_reference' => 'https://zoom.example/private',
            'status' => 'scheduled',
        ];
    }
}
