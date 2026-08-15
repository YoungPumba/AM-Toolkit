<?php

declare(strict_types=1);

namespace AMToolkit\Tests\Unit;

use AMToolkit\Modules\Courses\Contracts\CourseAdminStore;
use AMToolkit\Modules\Courses\Contracts\CourseEntitlementGateway;
use AMToolkit\Modules\Courses\Contracts\ProductCourseMappingStore;
use AMToolkit\Modules\Courses\Domain\PublicationStatus;
use AMToolkit\Modules\Courses\Services\CourseAccessLifecycle;
use AMToolkit\Modules\Courses\Services\CourseAdminService;
use PHPUnit\Framework\TestCase;

final class CourseAdminServiceTest extends TestCase
{
    private AdminCatalogSpy $catalog;
    private AdminMappingSpy $mappings;
    private AdminEntitlementSpy $entitlements;
    private CourseAdminService $service;

    protected function setUp(): void
    {
        $this->catalog = new AdminCatalogSpy();
        $this->mappings = new AdminMappingSpy([10 => [7], 20 => [7], 30 => [8]]);
        $this->entitlements = new AdminEntitlementSpy();
        $this->service = new CourseAdminService(
            $this->catalog,
            $this->mappings,
            new CourseAccessLifecycle($this->mappings, $this->entitlements)
        );
    }

    public function testCourseValidationStopsInvalidWrite(): void
    {
        $result = $this->service->saveCourse(0, '   ', 'Opis', 0, PublicationStatus::DRAFT);

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('am_toolkit_course_title_required', $result->get_error_code());
        self::assertSame([], $this->catalog->calls);
    }

    public function testProductMappingsAreReplacedThroughMappingContract(): void
    {
        $result = $this->service->replaceProductMappings(7, [20, 30, 30, 0]);

        self::assertTrue($result);
        self::assertSame([[10, 7]], $this->mappings->unmapped);
        self::assertSame([[30, 7]], $this->mappings->mapped);
        self::assertSame([20, 30], $this->mappings->productIdsForCourse(7));
    }

    public function testManualAccessUsesAccessCoreLifecycle(): void
    {
        $grant = $this->service->grantManual(4, 7, 91, 'AM-20260815-000000000001');
        $revoke = $this->service->revokeManual(91, 'AM-20260815-000000000002');

        self::assertSame(501, $grant);
        self::assertSame(1, $revoke);
        self::assertSame([
            'user_id' => 4,
            'course_id' => 7,
            'source_type' => 'manual',
            'source_id' => 91,
            'metadata' => ['provider' => 'wordpress_admin'],
            'request_id' => 'AM-20260815-000000000001',
        ], $this->entitlements->grantCall);
        self::assertSame([
            'source_type' => 'manual',
            'source_id' => 91,
            'context' => ['request_id' => 'AM-20260815-000000000002'],
        ], $this->entitlements->revokeCall);
    }

    public function testRepeatedManualGrantReusesExistingAssignmentSource(): void
    {
        $this->catalog->participants = [[
            'id' => 501,
            'user_id' => 4,
            'source_type' => 'manual',
            'source_id' => 91,
            'status' => 'active',
        ]];

        $result = $this->service->grantManual(4, 7, 999, 'AM-20260815-000000000003');

        self::assertSame(501, $result);
        self::assertSame(91, $this->entitlements->grantCall['source_id']);
    }

    public function testArchiveDelegatesWithoutDeletingCatalogData(): void
    {
        self::assertTrue($this->service->archiveCourse(7));
        self::assertSame([['archiveCourse', 7]], $this->catalog->calls);
    }
}

final class AdminCatalogSpy implements CourseAdminStore
{
    /** @var list<array<mixed>> */
    public array $calls = [];

    /** @var list<array<string, mixed>> */
    public array $participants = [];

    public function listCourses(): array|\WP_Error { return []; }
    public function findCourse(int $courseId): array|null|\WP_Error { return null; }

    public function saveCourse(int $courseId, string $title, string $description, int $imageAttachmentId, string $status): int|\WP_Error
    {
        $this->calls[] = ['saveCourse', $courseId];
        return $courseId > 0 ? $courseId : 1;
    }

    public function archiveCourse(int $courseId): bool|\WP_Error
    {
        $this->calls[] = ['archiveCourse', $courseId];
        return true;
    }

    public function sectionsForCourse(int $courseId): array|\WP_Error { return []; }
    public function saveSection(int $sectionId, int $courseId, string $title, string $description, int $position, string $status): int|\WP_Error { return 1; }
    public function archiveSection(int $sectionId, int $courseId): bool|\WP_Error { return true; }
    public function lessonsForCourse(int $courseId): array|\WP_Error { return []; }

    public function saveLesson(
        int $lessonId,
        int $courseId,
        ?int $sectionId,
        string $title,
        string $description,
        ?string $videoProvider,
        ?string $videoReference,
        ?int $durationSeconds,
        array $completionRequirements,
        int $position,
        bool $required,
        string $status
    ): int|\WP_Error {
        return 1;
    }

    public function archiveLesson(int $lessonId, int $courseId): bool|\WP_Error { return true; }
    public function materialsForCourse(int $courseId): array|\WP_Error { return []; }
    public function saveMaterial(int $materialId, int $lessonId, string $name, string $description, string $storageProvider, string $storageReference, int $position, string $status): int|\WP_Error { return 1; }
    public function archiveMaterial(int $materialId, int $lessonId): bool|\WP_Error { return true; }
    public function participantsForCourse(int $courseId): array|\WP_Error { return $this->participants; }
    public function activityForCourse(int $courseId, int $limit = 50): array|\WP_Error { return []; }
}

final class AdminMappingSpy implements ProductCourseMappingStore
{
    /** @var list<array{0: int, 1: int}> */
    public array $mapped = [];

    /** @var list<array{0: int, 1: int}> */
    public array $unmapped = [];

    /** @param array<int, list<int>> $map */
    public function __construct(private array $map)
    {
    }

    public function map(int $productId, int $courseId): bool|\WP_Error
    {
        $this->mapped[] = [$productId, $courseId];
        $this->map[$productId] ??= [];
        $this->map[$productId][] = $courseId;
        return true;
    }

    public function unmap(int $productId, int $courseId): bool|\WP_Error
    {
        $this->unmapped[] = [$productId, $courseId];
        $this->map[$productId] = array_values(array_diff($this->map[$productId] ?? [], [$courseId]));
        return true;
    }

    public function courseIdsForProducts(array $productIds): array|\WP_Error
    {
        $courseIds = [];
        foreach ($productIds as $productId) {
            $courseIds = array_merge($courseIds, $this->map[$productId] ?? []);
        }
        return array_values(array_unique($courseIds));
    }

    public function productIdsForCourse(int $courseId): array|\WP_Error
    {
        $productIds = [];
        foreach ($this->map as $productId => $courseIds) {
            if (in_array($courseId, $courseIds, true)) {
                $productIds[] = $productId;
            }
        }
        sort($productIds);
        return $productIds;
    }
}

final class AdminEntitlementSpy implements CourseEntitlementGateway
{
    /** @var array<string, mixed> */
    public array $grantCall = [];

    /** @var array<string, mixed> */
    public array $revokeCall = [];

    public function grant(int $userId, int $courseId, array $context): int|\WP_Error
    {
        $this->grantCall = ['user_id' => $userId, 'course_id' => $courseId] + $context;
        return 501;
    }

    public function revokeAllSource(string $sourceType, int $sourceId, array $context): int|\WP_Error
    {
        $this->revokeCall = [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'context' => $context,
        ];
        return 1;
    }
}
